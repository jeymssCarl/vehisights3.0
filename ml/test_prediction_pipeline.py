import json
import math
import subprocess
import sys
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path


ML_DIRECTORY = Path(__file__).resolve().parent


class PredictionPipelineTest(unittest.TestCase):
    def test_training_and_forecast_commands(self):
        with tempfile.TemporaryDirectory() as directory:
            temporary = Path(directory)
            training_path = temporary / "training.json"
            request_path = temporary / "request.json"
            model_path = temporary / "model.joblib"
            metadata_path = temporary / "metadata.json"
            start = datetime(2026, 1, 1, tzinfo=timezone.utc)
            records = []

            for offset in range(72):
                timestamp = start + timedelta(hours=offset)
                records.append(
                    {
                        "road_name": "Test Road",
                        "direction": "Northbound",
                        "hour_timestamp": timestamp.isoformat(),
                        "total_pcu": 300 + 120 * math.sin(2 * math.pi * timestamp.hour / 24),
                        "vc_ratio": (300 + 120 * math.sin(2 * math.pi * timestamp.hour / 24)) / 720,
                        "los": "B" if timestamp.hour < 12 else "C",
                        "source": "manual",
                    }
                )

            training_path.write_text(json.dumps({"records": records}), encoding="utf-8")
            training = subprocess.run(
                [
                    sys.executable,
                    str(ML_DIRECTORY / "train_random_forest.py"),
                    "--input",
                    str(training_path),
                    "--model",
                    str(model_path),
                    "--metadata",
                    str(metadata_path),
                ],
                capture_output=True,
                text=True,
            )
            if training.returncode != 0:
                self.fail(training.stderr or training.stdout)

            request_path.write_text(
                json.dumps(
                    {
                        "road_name": "Test Road",
                        "direction": "Northbound",
                        "source": "manual",
                        "start_at": datetime(2026, 2, 1, tzinfo=timezone.utc).isoformat(),
                        "hours": 6,
                        "capacity": 720,
                    }
                ),
                encoding="utf-8",
            )
            forecast = subprocess.run(
                [
                    sys.executable,
                    str(ML_DIRECTORY / "predict_random_forest.py"),
                    "--model",
                    str(model_path),
                    "--metadata",
                    str(metadata_path),
                    "--input",
                    str(request_path),
                ],
                check=True,
                capture_output=True,
                text=True,
            )
            payload = json.loads(forecast.stdout)
            metadata = json.loads(metadata_path.read_text(encoding="utf-8"))

            self.assertTrue(payload["success"])
            self.assertEqual("RandomForestClassifier", payload["algorithm"])
            self.assertEqual("RandomForestClassifier", metadata["algorithm"])
            self.assertEqual("rfc-los-v2", metadata["artifact_schema"])
            self.assertEqual(6, len(payload["predictions"]))
            self.assertTrue(all(row["predicted_pcu"] >= 0 for row in payload["predictions"]))
            self.assertTrue(all(row["predicted_los"] in "ABCDEF" for row in payload["predictions"]))
            self.assertTrue(all(0 <= row["confidence_score"] <= 100 for row in payload["predictions"]))
            self.assertTrue(all(row["derivation_basis"] for row in payload["predictions"]))


if __name__ == "__main__":
    unittest.main()
