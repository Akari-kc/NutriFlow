# NutriFlow predictive model integration

This module contains the trusted `prototype-v2` simulation artifact and its controlled JSON inference entry point. Training remains external to NutriFlow; the web application performs inference only.

## Runtime

Use Python 3.12.13 with the exact packages in `requirements-lock.txt`. Configure the application with:

```text
NUTRIFLOW_ML_ENABLED=true
NUTRIFLOW_ML_PYTHON=C:\path\to\python.exe
NUTRIFLOW_ML_PYTHONPATH=C:\optional\path\to\installed\packages
NUTRIFLOW_ML_TIMEOUT=10
```

The Laravel integration invokes the fixed `inference/predictor.py` path with a fixed executable, sends structured JSON over standard input, verifies the model SHA-256 from `models/model_metadata.json`, applies a timeout, and never interpolates user data into a command.

## Contract

- Target `1`: first valid follow-up 21–40 days later is `Undernourished` or `Severely Undernourished`.
- Target `0`: that follow-up is `Normal`.
- Supported current statuses: `Normal`, `Undernourished`, `Severely Undernourished`.
- At least two valid measurements on distinct dates are required.
- The entire preprocessing-and-model pipeline is serialized in the joblib artifact.
- The artifact is synthetic, simulation-only, and not clinically validated.

Run `inference/validate_golden_cases.py` after provisioning the pinned runtime. Only load this trusted repository artifact; joblib/pickle artifacts must never come from untrusted sources.

Meal ranking is deterministic and separate from the risk model. Allergy, cost, budget, and inventory feasibility remain unavailable until validated input data exists.
