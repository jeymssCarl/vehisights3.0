import argparse
import json
import math
from datetime import datetime, timedelta, timezone

import joblib


MANILA_TIMEZONE = timezone(timedelta(hours=8), name="Asia/Manila")
ARTIFACT_SCHEMA = "rfc-los-v2"
FEATURES = [
    "hour", "day_of_week", "month", "day_of_year", "is_weekend",
    "hour_sin", "hour_cos", "weekday_sin", "weekday_cos",
    "road_name", "direction", "source",
]
LOS_DEFAULT_RATIO = {"A": 0.10, "B": 0.325, "C": 0.575, "D": 0.775, "E": 0.925, "F": 1.10}
LOS_BOUNDS = {
    "A": (0.0, 0.199999), "B": (0.20, 0.449999), "C": (0.45, 0.699999),
    "D": (0.70, 0.849999), "E": (0.85, 1.0), "F": (1.000001, None),
}


def parse_arguments():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", required=True)
    parser.add_argument("--metadata", required=True)
    parser.add_argument("--input", required=True)
    return parser.parse_args()


def to_manila(timestamp):
    if timestamp.tzinfo is None:
        timestamp = timestamp.replace(tzinfo=MANILA_TIMEZONE)
    return timestamp.astimezone(MANILA_TIMEZONE)


def feature_row(timestamp, road_name, direction, source):
    timestamp = to_manila(timestamp)
    hour = timestamp.hour
    weekday = timestamp.weekday()
    values = {
        "hour": hour,
        "day_of_week": weekday,
        "month": timestamp.month,
        "day_of_year": timestamp.timetuple().tm_yday,
        "is_weekend": 1 if weekday >= 5 else 0,
        "hour_sin": math.sin(2 * math.pi * hour / 24),
        "hour_cos": math.cos(2 * math.pi * hour / 24),
        "weekday_sin": math.sin(2 * math.pi * weekday / 7),
        "weekday_cos": math.cos(2 * math.pi * weekday / 7),
        "road_name": road_name or "unknown",
        "direction": direction or "unknown",
        "source": source or "unknown",
    }
    return [values[name] for name in FEATURES]


def traffic_status(los):
    return {"A": "Free Flow", "B": "Light", "C": "Moderate", "D": "Heavy", "E": "Severe", "F": "Breakdown"}[los]


def statistic_indexes(entries):
    road_hour, road, global_los = {}, {}, {}
    for entry in entries:
        if entry.get("scope") == "road_hour_los":
            road_hour[(entry["road_name"], int(entry["hour"]), entry["los"])] = entry
        elif entry.get("scope") == "road_los":
            road[(entry["road_name"], entry["los"])] = entry
        elif entry.get("scope") == "global_los":
            global_los[entry["los"]] = entry
    return road_hour, road, global_los


def estimated_ratio(indexes, road_name, hour, los):
    road_hour, road, global_los = indexes
    entry = road_hour.get((road_name, hour, los))
    basis = "road/hour/LOS median"
    if entry is None:
        entry = road.get((road_name, los))
        basis = "road/LOS median"
    if entry is None:
        entry = global_los.get(los)
        basis = "global LOS median"
    ratio = float(entry["median_vc_ratio"]) if entry else LOS_DEFAULT_RATIO[los]
    if entry is None:
        basis = "LOS standard midpoint"
    lower, upper = LOS_BOUNDS[los]
    ratio = max(lower, ratio)
    if upper is not None:
        ratio = min(upper, ratio)
    return ratio, basis, int(entry["sample_count"]) if entry else 0


def main():
    args = parse_arguments()
    artifact = joblib.load(args.model)
    with open(args.metadata, "r", encoding="utf-8") as handle:
        metadata = json.load(handle)
    with open(args.input, "r", encoding="utf-8") as handle:
        request = json.load(handle)
    if artifact.get("artifact_schema") != ARTIFACT_SCHEMA or metadata.get("artifact_schema") != ARTIFACT_SCHEMA:
        raise ValueError("The stored model is obsolete. Retrain the RandomForestClassifier before forecasting.")

    start = datetime.fromisoformat(request["start_at"].replace("Z", "+00:00"))
    hours = int(request["hours"])
    capacity = max(float(request["capacity"]), 1.0)
    timestamps = [start + timedelta(hours=offset) for offset in range(hours)]
    road_name = request.get("road_name") or "unknown"
    x = [feature_row(timestamp, road_name, request.get("direction"), request.get("source")) for timestamp in timestamps]
    classified_los = artifact["classifier"].predict(x)
    class_probabilities = artifact["classifier"].predict_proba(x)
    indexes = statistic_indexes(artifact.get("class_statistics", []))
    predictions = []
    for timestamp, los_value, probabilities in zip(timestamps, classified_los, class_probabilities):
        local_time = to_manila(timestamp)
        los = str(los_value)
        ratio, basis, sample_count = estimated_ratio(indexes, road_name, local_time.hour, los)
        predictions.append({
            "timestamp": local_time.isoformat(),
            "hour": local_time.hour,
            "predicted_pcu": round(ratio * capacity, 2),
            "predicted_vc_ratio": round(ratio, 2),
            "predicted_los": los,
            "status": traffic_status(los),
            "confidence_score": round(float(max(probabilities) * 100.0), 2),
            "derivation_basis": basis,
            "historical_samples": sample_count,
        })
    print(json.dumps({
        "success": True,
        "model_version": artifact["model_version"],
        "algorithm": "RandomForestClassifier",
        "capacity": capacity,
        "predictions": predictions,
        "metrics": metadata.get("metrics", {}),
    }))


if __name__ == "__main__":
    main()
