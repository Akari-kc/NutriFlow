"""Apply documented PDF-fragment normalization to an ERIS staging extraction."""

from __future__ import annotations

import argparse
import json
from collections import Counter
from pathlib import Path


def normalized_status(raw: str | None) -> str | None:
    text = "".join(character for character in (raw or "").lower() if character.isalpha())
    if not text:
        return None
    if ("severe" in text and "wast" in text) or text.startswith("swast"):
        return "Severely Undernourished"
    if "wast" in text:
        return "Undernourished"
    if text.startswith("overweigh"):
        return "Overweight"
    if "obese" in text:
        return "Obese"
    if text.startswith("norma"):
        return "Normal"
    return "Needs Review"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    document = json.loads(args.input.read_text(encoding="utf-8"))
    for measurement in document["growth_measurements_staging"]:
        measurement["nutriflow_status_from_source"] = normalized_status(measurement["source_nutrition_status"])

    document["data_quality_issues"] = [
        row
        for row in document["data_quality_issues"]
        if not (
            row["cohort"] == "master"
            and row["field"] == "nutrition_status"
            and row["message"] == "Nutrition status could not be normalized."
        )
    ]
    for index, row in enumerate(document["data_quality_issues"], start=1):
        row["issue_id"] = f"DQ-{index:04d}"

    document["summary"]["master_statuses"] = dict(
        Counter(row["nutriflow_status_from_source"] for row in document["growth_measurements_staging"])
    )
    document["summary"]["issue_severity"] = dict(
        Counter(row["severity"] for row in document["data_quality_issues"])
    )
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(document, indent=2, ensure_ascii=False), encoding="utf-8")
    print(json.dumps(document["summary"], indent=2))


if __name__ == "__main__":
    main()
