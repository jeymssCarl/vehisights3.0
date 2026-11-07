# VehiSights traffic prediction

The application uses a hybrid Random Forest design. A `RandomForestRegressor` forecasts hourly PCU, while a `RandomForestClassifier` independently classifies LOS. V/C ratio and the final standards-consistent LOS/status are calculated from the predicted PCU and stored road capacity; the RFC output is displayed as an independent classification check.

## Local setup

From the project root on Windows:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r ml\requirements.txt
```

Laravel automatically uses `.venv/Scripts/python.exe`. Override it with `PREDICTION_PYTHON_BINARY` only when Python is installed elsewhere.

## Data requirements

- Training source: completed manual and automatic results written to `traffic_history`.
- Hard minimum: 48 hourly records, configurable with `PREDICTION_MINIMUM_TRAINING_ROWS`.
- Recommended starting point: at least 168 hourly records and preferably several weeks covering weekdays, weekends, and different traffic periods.
- More observations per road generally produce more credible road-specific forecasts.

Open **Predictions** and select **Train Model** after enough records are available. Retrain after a meaningful amount of new traffic data has been collected.

## Model design

Both estimators use hour, weekday, month, seasonal cyclical features, road, direction, and record source. Validation uses time-ordered splits and records both regression error and RFC classification accuracy/F1. The trained model and its metadata are private runtime artifacts in `storage/app/private/predictions` and are excluded from Git.

Google Colab is optional. It can be used for experimentation, but the production artifact should be trained by this Laravel application so its scikit-learn version and feature pipeline remain consistent. Never load an untrusted `.joblib` model.
