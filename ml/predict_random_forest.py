import argparse
import json
import math
from datetime import datetime, timedelta

import joblib


FEATURES = [
    "hour",
    "day_of_week",
    "month",
    "day_of_year",
    "is_weekend",
    "hour_sin",
    "hour_cos",
    "weekday_sin",
    "weekday_cos",
    "road_name",
    "direction",
    "source",
]


def parse_arguments():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", required=True)
    parser.add_argument("--metadata", required=True)
    parser.add_argument("--input", required=True)
    return parser.parse_args()


def feature_row(timestamp, road_name, direction, source):
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


def level_of_service(ratio):
    if ratio < 0.20:
        return "A"
    if ratio < 0.45:
        return "B"
    if ratio < 0.70:
        return "C"
    if ratio < 0.85:
        return "D"
    if ratio <= 1.00:
        return "E"
    return "F"


def traffic_status(los):
    return {
        "A": "Free Flow",
        "B": "Light",
        "C": "Moderate",
        "D": "Heavy",
        "E": "Severe",
        "F": "Breakdown",
    }[los]


def main():
    args = parse_arguments()
    artifact = joblib.load(args.model)
    with open(args.metadata, "r", encoding="utf-8") as handle:
        metadata = json.load(handle)
    with open(args.input, "r", encoding="utf-8") as handle:
        request = json.load(handle)

    start = datetime.fromisoformat(request["start_at"].replace("Z", "+00:00"))
    hours = int(request["hours"])
    capacity = max(float(request["capacity"]), 1.0)
    timestamps = [start + timedelta(hours=offset) for offset in range(hours)]
    x = [
        feature_row(
            timestamp,
            request.get("road_name"),
            request.get("direction"),
            request.get("source"),
        )
        for timestamp in timestamps
    ]
    predicted_pcu = artifact["regressor"].predict(x)
    classified_los = artifact["classifier"].predict(x)
    class_probabilities = artifact["classifier"].predict_proba(x)
    classifier_confidence = [float(max(probabilities) * 100.0) for probabilities in class_probabilities]
    validation_confidence = metadata.get("metrics", {}).get("validation_confidence", 0)
    predictions = []
    for timestamp, pcu_value, rfc_los, rfc_confidence in zip(
        timestamps, predicted_pcu, classified_los, classifier_confidence
    ):
        pcu = max(0.0, float(pcu_value))
        ratio = pcu / capacity
        calculated_los = level_of_service(ratio)
        predictions.append(
            {
                "timestamp": timestamp.isoformat(),
                "hour": timestamp.hour,
                "predicted_pcu": round(pcu, 2),
                "predicted_vc_ratio": round(ratio, 2),
                "predicted_los": calculated_los,
                "status": traffic_status(calculated_los),
                "rfc_predicted_los": str(rfc_los),
                "rfc_confidence": round(rfc_confidence, 2),
                "rfc_matches_capacity_los": str(rfc_los) == calculated_los,
                "confidence_score": validation_confidence,
            }
        )

    print(
        json.dumps(
            {
                "success": True,
                "model_version": artifact["model_version"],
                "capacity": capacity,
                "predictions": predictions,
                "metrics": metadata.get("metrics", {}),
            }
        )
    )


if __name__ == "__main__":
    main()
