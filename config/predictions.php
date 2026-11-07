<?php

return [
    'python_binary' => env(
        'PREDICTION_PYTHON_BINARY',
        PHP_OS_FAMILY === 'Windows'
            ? base_path('.venv/Scripts/python.exe')
            : base_path('.venv/bin/python')
    ),
    'minimum_training_rows' => (int) env('PREDICTION_MINIMUM_TRAINING_ROWS', 48),
    'recommended_training_rows' => (int) env('PREDICTION_RECOMMENDED_TRAINING_ROWS', 168),
    'process_timeout' => (int) env('PREDICTION_PROCESS_TIMEOUT', 300),
];
