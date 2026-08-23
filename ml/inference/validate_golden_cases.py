
from __future__ import annotations

import json
from pathlib import Path

from predictor import predict

ROOT = Path(__file__).resolve().parents[1]
cases = json.loads((ROOT / "inference" / "golden_cases.json").read_text(encoding="utf-8"))
for case in cases:
    actual = predict(case["features"])
    delta = abs(actual["future_undernutrition_probability"] - case["expected_probability"])
    assert delta <= 1e-10, f"Probability mismatch for {case['case_id']}: {delta}"
    assert actual["predicted_class"] == case["expected_class"]
print(f"PASS: {len(cases)} golden inference cases")
