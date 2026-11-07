import argparse
import json
import math
from datetime import datetime, timezone

import joblib
import numpy as np
import sklearn
from sklearn.base import clone
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor
from sklearn.metrics import accuracy_score, f1_score, mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import TimeSeriesSplit
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder


NUMERIC_FEATURES = [
    "hour",
    "day_of_week",
    "month",
    "day_of_year",
    "is_weekend",
    "hour_sin",
    "hour_cos",
    "weekday_sin",
    "weekday_cos",
]
CATEGORICAL_FEATURES = ["road_name", "direction", "source"]
FEATURES = NUMERIC_FEATURES + CATEGORICAL_FEATURES


def parse_arguments():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--metadata", required=True)
    return parser.parse_args()


def feature_row(record):
    timestamp = datetime.fromisoformat(record["hour_timestamp"].replace("Z", "+00:00"))
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
    if stored_los in "ABCDEF" and len(stored_los) == 1:
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


def build_preprocessor():
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
    return preprocessor


def build_regression_pipeline():
    forest = RandomForestRegressor(
        n_estimators=300,
        min_samples_leaf=2,
        max_features=0.8,
        random_state=42,
        n_jobs=-1,
    )
    return Pipeline([("features", build_preprocessor()), ("model", forest)])


def build_classifier_pipeline():
    classifier = RandomForestClassifier(
        n_estimators=300,
        min_samples_leaf=2,
        max_features="sqrt",
        class_weight="balanced_subsample",
        random_state=42,
        n_jobs=-1,
    )
    return Pipeline([("features", build_preprocessor()), ("model", classifier)])


def validation_metrics(regressor, classifier, x, pcu_targets, los_targets):
    split_count = min(5, max(2, len(pcu_targets) // 24))
    splitter = TimeSeriesSplit(n_splits=split_count)
    pcu_predictions = []
    pcu_actual = []
    los_predictions = []
    los_actual = []
    for train_index, test_index in splitter.split(x):
        regression_candidate = clone(regressor)
        classification_candidate = clone(classifier)
        train_x = [x[index] for index in train_index]
        test_x = [x[index] for index in test_index]
        regression_candidate.fit(train_x, pcu_targets[train_index])
        classification_candidate.fit(train_x, los_targets[train_index])
        pcu_predictions.extend(regression_candidate.predict(test_x))
        pcu_actual.extend(pcu_targets[test_index])
        los_predictions.extend(classification_candidate.predict(test_x))
        los_actual.extend(los_targets[test_index])

    mae = float(mean_absolute_error(pcu_actual, pcu_predictions))
    rmse = float(math.sqrt(mean_squared_error(pcu_actual, pcu_predictions)))
    r2 = float(r2_score(pcu_actual, pcu_predictions)) if len(pcu_actual) > 1 else 0.0
    mean_pcu = max(float(np.mean(pcu_actual)), 1.0)
    regression_confidence = max(0.0, min(100.0, 100.0 * (1.0 - mae / mean_pcu)))
    classifier_accuracy = float(accuracy_score(los_actual, los_predictions))
    classifier_f1 = float(f1_score(los_actual, los_predictions, average="macro", zero_division=0))
    combined_confidence = (regression_confidence + classifier_accuracy * 100.0) / 2.0
    return {
        "mae": round(mae, 2),
        "rmse": round(rmse, 2),
        "r2": round(r2, 4),
        "regression_confidence": round(regression_confidence, 2),
        "classifier_accuracy": round(classifier_accuracy, 4),
        "classifier_macro_f1": round(classifier_f1, 4),
        "validation_confidence": round(combined_confidence, 2),
    }


def main():
    args = parse_arguments()
    with open(args.input, "r", encoding="utf-8") as handle:
        records = json.load(handle)["records"]

    records.sort(key=lambda row: row["hour_timestamp"])
    feature_rows = [feature_row(record) for record in records]
    x = rows_to_matrix(feature_rows)
    pcu_targets = np.asarray([float(record["total_pcu"]) for record in records], dtype=float)
    los_targets = np.asarray([los_target(record) for record in records])
    regressor = build_regression_pipeline()
    classifier = build_classifier_pipeline()
    metrics = validation_metrics(regressor, classifier, x, pcu_targets, los_targets)
    regressor.fit(x, pcu_targets)
    classifier.fit(x, los_targets)

    trained_at = datetime.now(timezone.utc).isoformat()
    model_version = datetime.now(timezone.utc).strftime("rf-%Y%m%d%H%M%S")
    artifact = {
        "regressor": regressor,
        "classifier": classifier,
        "features": FEATURES,
        "model_version": model_version,
    }
    metadata = {
        "algorithm": "Hybrid Random Forest",
        "pcu_estimator": "RandomForestRegressor",
        "los_estimator": "RandomForestClassifier",
        "model_version": model_version,
        "trained_at": trained_at,
        "training_rows": len(records),
        "date_from": records[0]["hour_timestamp"],
        "date_to": records[-1]["hour_timestamp"],
        "roads": sorted({record.get("road_name") for record in records if record.get("road_name")}),
        "metrics": metrics,
        "scikit_learn_version": sklearn.__version__,
    }

    joblib.dump(artifact, args.model)
    with open(args.metadata, "w", encoding="utf-8") as handle:
        json.dump(metadata, handle, indent=2)
    print(json.dumps({"success": True, **metadata}))


if __name__ == "__main__":
    main()
