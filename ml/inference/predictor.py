
from __future__ import annotations

import json
import sys
from pathlib import Path

import joblib
import pandas as pd

ROOT = Path(__file__).resolve().parents[1]
MODEL_PATH = ROOT / "models" / "nutriflow_undernutrition_risk_v2.joblib"
FEATURES = ['age_months_at_anchor', 'current_weight_kg', 'current_height_cm', 'current_bmi', 'prior_weight_kg', 'prior_height_cm', 'prior_bmi', 'days_since_prior', 'weight_change_kg', 'height_change_cm', 'bmi_change', 'weight_change_kg_per_30d', 'bmi_change_per_30d', 'sex_recorded', 'current_bmi_flag']
NUMERIC = set(['age_months_at_anchor', 'current_weight_kg', 'current_height_cm', 'current_bmi', 'prior_weight_kg', 'prior_height_cm', 'prior_bmi', 'days_since_prior', 'weight_change_kg', 'height_change_cm', 'bmi_change', 'weight_change_kg_per_30d', 'bmi_change_per_30d'])
SUPPORTED_SEX = {"Male", "Female"}
SUPPORTED_STATUS = {"Normal", "Undernourished", "Severely Undernourished"}
THRESHOLD = 0.50


def predict(payload: dict, model=None) -> dict:
    missing = [name for name in FEATURES if name not in payload]
    extra = sorted(set(payload) - set(FEATURES))
    if missing or extra:
        raise ValueError(f"Feature schema mismatch; missing={missing}, extra={extra}")
    if payload["sex_recorded"] not in SUPPORTED_SEX:
        raise ValueError("sex_recorded must be Male or Female")
    if payload["current_bmi_flag"] not in SUPPORTED_STATUS:
        raise ValueError("current_bmi_flag is outside prototype-v2 scope")
    clean = {}
    for name in FEATURES:
        if name in NUMERIC:
            value = float(payload[name])
            if not pd.notna(value):
                raise ValueError(f"{name} must be finite")
            clean[name] = value
        else:
            clean[name] = str(payload[name])
    model = model or joblib.load(MODEL_PATH)
    probability = float(model.predict_proba(pd.DataFrame([clean], columns=FEATURES))[0, 1])
    return {
        "model_version": "prototype-v2",
        "future_undernutrition_probability": probability,
        "predicted_class": int(probability >= THRESHOLD),
        "threshold": THRESHOLD,
        "horizon_days": {"minimum": 21, "maximum": 40},
        "simulation_only": True,
        "clinical_use_permitted": False,
    }


def predict_many(payloads: list[dict]) -> list[dict]:
    if not payloads:
        return []
    model = joblib.load(MODEL_PATH)
    return [predict(payload, model=model) for payload in payloads]


if __name__ == "__main__":
    try:
        payload = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8")) if len(sys.argv) > 1 else json.load(sys.stdin)
        result = predict_many(payload["batch"]) if isinstance(payload, dict) and "batch" in payload else predict(payload)
        print(json.dumps({"ok": True, "result": result}))
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc)}))
        raise SystemExit(2)
