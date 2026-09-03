"""Controlled JSON inference for the isolated ERIS experimental artifact."""

from __future__ import annotations

import hashlib
import json
import math
import sys
from pathlib import Path

import joblib
import pandas as pd


MODEL_DIR = Path(__file__).resolve().parents[1] / "models"
METADATA_PATH = MODEL_DIR / "model_metadata.json"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def fail(message: str, status: str = "invalid_input") -> dict:
    return {"status": status, "message": message}


def predict(payload: dict) -> dict:
    metadata = json.loads(METADATA_PATH.read_text(encoding="utf-8"))
    artifact_path = MODEL_DIR / metadata["artifact_file"]
    if not artifact_path.is_file():
        return fail("The trusted experimental model artifact is missing.", "unavailable")
    if sha256(artifact_path) != metadata["artifact_sha256"]:
        return fail("The experimental model artifact failed its integrity check.", "unavailable")

    missing = [name for name in metadata["feature_columns"] if payload.get(name) in (None, "")]
    if missing:
        return fail("Missing required features: " + ", ".join(missing) + ".")
    try:
        age_months = float(payload["age_months_at_anchor"])
        for name in ("current_weight_kg", "current_height_cm", "current_bmi"):
            value = float(payload[name])
            if not math.isfinite(value) or value <= 0:
                return fail(f"{name} must be a positive finite number.")
    except (TypeError, ValueError):
        return fail("Numeric features must contain valid numbers.")

    if age_months < metadata["observed_age_months_min"] or age_months > metadata["observed_age_months_max"]:
        return fail(
            "Age is outside the range observed in the ERIS experimental cohort.",
            "out_of_scope",
        )

    feature_row = {name: payload[name] for name in metadata["feature_columns"]}
    model = joblib.load(artifact_path)
    probability = float(model.predict_proba(pd.DataFrame([feature_row]))[0, 1])
    threshold = float(metadata["risk_threshold"])
    return {
        "status": "experimental_only",
        "model_version": metadata["model_version"],
        "risk_probability": round(probability, 6),
        "risk_threshold": threshold,
        "risk_label": "Elevated experimental risk" if probability >= threshold else "Lower experimental risk",
        "target_horizon": "Approximately 248 days after one baseline assessment",
        "warning": "Not clinically validated. Frontend and production decision integration are disabled.",
    }


def main() -> None:
    try:
        payload = json.load(sys.stdin)
        if not isinstance(payload, dict):
            result = fail("Input must be one JSON object.")
        else:
            result = predict(payload)
    except json.JSONDecodeError:
        result = fail("Input must be valid JSON.")
    except Exception:
        result = fail("Experimental prediction is temporarily unavailable.", "unavailable")
    json.dump(result, sys.stdout)
    sys.stdout.write("\n")


if __name__ == "__main__":
    main()
