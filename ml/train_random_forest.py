import argparse
import json
import math
import statistics
from collections import defaultdict
from datetime import datetime, timedelta, timezone

import joblib
import numpy as np
import sklearn
from sklearn.base import clone
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, f1_score
from sklearn.model_selection import TimeSeriesSplit
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder


MANILA_TIMEZONE = timezone(timedelta(hours=8), name="Asia/Manila")
ARTIFACT_SCHEMA = "rfc-los-v2"
NUMERIC_FEATURES = [
    "hour", "day_of_week", "month", "day_of_year", "is_weekend",
    "hour_sin", "hour_cos", "weekday_sin", "weekday_cos",
]
CATEGORICAL_FEATURES = ["road_name", "direction", "source"]
FEATURES = NUMERIC_FEATURES + CATEGORICAL_FEATURES


def parse_arguments():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--metadata", required=True)
    return parser.parse_args()


def local_timestamp(value):
    timestamp = datetime.fromisoformat(value.replace("Z", "+00:00"))
    if timestamp.tzinfo is None:
        timestamp = timestamp.replace(tzinfo=MANILA_TIMEZONE)
    return timestamp.astimezone(MANILA_TIMEZONE)


def feature_row(record):
    timestamp = local_timestamp(record["hour_timestamp"])
    hour = timestamp.hour
    weekday = timestamp.weekday()
    return {
        "hour": hour,
        "day_of_week": weekday,
        "month": timestamp.month,
        "day_of_year": timestamp.timetuple().tm_yday,
        "is_weekend": 1 if weekday >= 5 else 0,
        "hour_sin": math.sin(2 * math.pi * hour / 24),
        "hour_cos": math.cos(2 * math.pi * hour / 24),
        "weekday_sin": math.sin(2 * math.pi * weekday / 7),
        "weekday_cos": math.cos(2 * math.pi * weekday / 7),
        "road_name": record.get("road_name") or "unknown",
        "direction": record.get("direction") or "unknown",
        "source": record.get("source") or "unknown",
    }


def rows_to_matrix(rows):
    return [[row[name] for name in FEATURES] for row in rows]


def los_target(record):
    stored_los = str(record.get("los") or "").upper()
    if len(stored_los) == 1 and stored_los in "ABCDEF":
        return stored_los
    ratio = float(record.get("vc_ratio") or 0)
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


def build_classifier_pipeline():
    preprocessor = ColumnTransformer(
        transformers=[
            ("numeric", "passthrough", list(range(len(NUMERIC_FEATURES)))),
            (
                "categorical",
                OneHotEncoder(handle_unknown="ignore", sparse_output=False),
                list(range(len(NUMERIC_FEATURES), len(FEATURES))),
            ),
        ]
    )
    classifier = RandomForestClassifier(
        n_estimators=400,
        min_samples_leaf=2,
        max_features="sqrt",
        class_weight="balanced_subsample",
        random_state=42,
        n_jobs=-1,
    )
    return Pipeline([("features", preprocessor), ("model", classifier)])


def validation_metrics(classifier, x, targets):
    split_count = min(5, max(2, len(targets) // 24))
    splitter = TimeSeriesSplit(n_splits=split_count)
    predictions = []
    actual = []
    evaluated_folds = 0
    for train_index, test_index in splitter.split(x):
        candidate = clone(classifier)
        train_x = [x[index] for index in train_index]
        test_x = [x[index] for index in test_index]
        candidate.fit(train_x, targets[train_index])
        predictions.extend(candidate.predict(test_x))
        actual.extend(targets[test_index])
        evaluated_folds += 1
    return {
        "classifier_accuracy": round(float(accuracy_score(actual, predictions)), 4),
        "classifier_macro_f1": round(float(f1_score(actual, predictions, average="macro", zero_division=0)), 4),
        "validation_folds": evaluated_folds,
    }


def statistic_entry(scope, key, values):
    return {
        "scope": scope,
        **key,
        "median_vc_ratio": round(float(statistics.median(value["vc_ratio"] for value in values)), 6),
        "median_total_pcu": round(float(statistics.median(value["total_pcu"] for value in values)), 2),
        "sample_count": len(values),
    }


def build_class_statistics(records):
    road_hour = defaultdict(list)
    road = defaultdict(list)
    global_groups = defaultdict(list)
    for record in records:
        los = los_target(record)
        road_name = record.get("road_name") or "unknown"
        hour = local_timestamp(record["hour_timestamp"]).hour
        value = {
            "vc_ratio": max(0.0, float(record.get("vc_ratio") or 0)),
            "total_pcu": max(0.0, float(record.get("total_pcu") or 0)),
        }
        road_hour[(road_name, hour, los)].append(value)
        road[(road_name, los)].append(value)
        global_groups[los].append(value)

    entries = []
    for (road_name, hour, los), values in sorted(road_hour.items()):
        entries.append(statistic_entry("road_hour_los", {"road_name": road_name, "hour": hour, "los": los}, values))
    for (road_name, los), values in sorted(road.items()):
        entries.append(statistic_entry("road_los", {"road_name": road_name, "los": los}, values))
    for los, values in sorted(global_groups.items()):
        entries.append(statistic_entry("global_los", {"los": los}, values))
    return entries


def main():
    args = parse_arguments()
    with open(args.input, "r", encoding="utf-8") as handle:
        records = json.load(handle)["records"]
    records.sort(key=lambda row: row["hour_timestamp"])
    x = rows_to_matrix([feature_row(record) for record in records])
    targets = np.asarray([los_target(record) for record in records])
    classes = sorted(set(targets.tolist()))
    if len(classes) < 2:
        raise ValueError("RandomForestClassifier training requires at least two LOS classes.")

    classifier = build_classifier_pipeline()
    metrics = validation_metrics(classifier, x, targets)
    classifier.fit(x, targets)
    trained_at = datetime.now(timezone.utc).isoformat()
    model_version = datetime.now(timezone.utc).strftime("rfc-los-%Y%m%d%H%M%S")
    artifact = {
        "artifact_schema": ARTIFACT_SCHEMA,
        "classifier": classifier,
        "features": FEATURES,
        "class_statistics": build_class_statistics(records),
        "model_version": model_version,
    }
    metadata = {
        "algorithm": "RandomForestClassifier",
        "target": "LOS",
        "artifact_schema": ARTIFACT_SCHEMA,
        "model_version": model_version,
        "trained_at": trained_at,
        "training_rows": len(records),
        "date_from": records[0]["hour_timestamp"],
        "date_to": records[-1]["hour_timestamp"],
        "roads": sorted({record.get("road_name") for record in records if record.get("road_name")}),
        "los_classes": classes,
        "metrics": metrics,
        "scikit_learn_version": sklearn.__version__,
    }
    joblib.dump(artifact, args.model)
    with open(args.metadata, "w", encoding="utf-8") as handle:
        json.dump(metadata, handle, indent=2)
    print(json.dumps({"success": True, **metadata}))


if __name__ == "__main__":
    main()
