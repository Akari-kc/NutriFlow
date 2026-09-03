<?php

return [
    'enabled' => env('NUTRIFLOW_ML_ENABLED', true),
    'python' => env('NUTRIFLOW_ML_PYTHON', 'python'),
    'pythonpath' => env('NUTRIFLOW_ML_PYTHONPATH'),
    'timeout_seconds' => (float) env('NUTRIFLOW_ML_TIMEOUT', 30),
    'risk_threshold' => 0.50,
    'script_path' => base_path('ml/inference/predictor.py'),
    'model_path' => base_path('ml/models/nutriflow_undernutrition_risk_v2.joblib'),
    'metadata_path' => base_path('ml/models/model_metadata.json'),
    'pdri_path' => base_path('ml/reference/pdri_one_third_feeding_targets.csv'),
];
