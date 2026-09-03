"""Extract the censored ERIS PDFs into a privacy-safe staging JSON document.

This script never modifies the source PDFs and never attempts to reconstruct real
learner identities. The 360-row master list and 49-row longitudinal report use
separate UID namespaces because the censored labels are not stable across those
sources.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import re
from collections import Counter, defaultdict
from datetime import date, datetime
from pathlib import Path
from typing import Any

import pdfplumber


MASTER_FILE = "ERIS SBFP Final Masterlist 2025-2026 JRU.pdf"
BASELINE_FILE = "BMI SCHOOL JUNE 2025 JRU.pdf"
FOLLOWUP_FILE = "BMI SCHOOL MARCH 2026.pdf"
TERMINAL_FILE = "ERIS PROGRAM TERMINAL REPORT 2025-2026-JRU.pdf"
BASELINE_DATE = date(2025, 7, 14)
FOLLOWUP_DATE = date(2026, 3, 19)


def clean(value: Any) -> str:
    if value is None:
        return ""
    return re.sub(r"\s+", " ", str(value).replace("\n", " ")).strip()


def as_float(value: Any) -> float | None:
    text = clean(value)
    if not text:
        return None
    try:
        number = float(re.sub(r"[^0-9.\-]", "", text))
    except ValueError:
        return None
    return number if math.isfinite(number) else None


def parse_date(value: Any, formats: tuple[str, ...]) -> date | None:
    text = clean(value)
    if not text:
        return None
    for pattern in formats:
        try:
            return datetime.strptime(text, pattern).date()
        except ValueError:
            continue
    return None


def iso(value: date | None) -> str | None:
    return value.isoformat() if value else None


def age_months(birthdate: date | None, measured_at: date | None) -> int | None:
    if not birthdate or not measured_at or measured_at < birthdate:
        return None
    months = (measured_at.year - birthdate.year) * 12 + measured_at.month - birthdate.month
    if measured_at.day < birthdate.day:
        months -= 1
    return months


def parse_report_age_months(value: Any) -> int | None:
    text = clean(value).lower().replace(" ", "")
    match = re.search(r"(?:(\d+)y)?[,]?(?:(\d+)m)?", text)
    if not match or not any(match.groups()):
        return None
    years = int(match.group(1) or 0)
    months = int(match.group(2) or 0)
    return years * 12 + months


def normalize_sex(value: Any) -> str | None:
    return {"m": "Male", "male": "Male", "f": "Female", "female": "Female"}.get(clean(value).lower())


def normalize_grade(value: Any) -> str | None:
    text = clean(value).upper()
    if text == "K":
        return "Kinder"
    match = re.search(r"([1-6])", text)
    return f"Grade {match.group(1)}" if match else None


def normalize_nutrition_status(value: Any) -> str | None:
    text = clean(value).lower()
    compact = re.sub(r"[^a-z]", "", text)
    if not compact:
        return None
    if "severe" in compact and "wast" in compact:
        return "Severely Undernourished"
    if compact.startswith("swast") or compact in {"severelywasted", "severewasted"}:
        return "Severely Undernourished"
    if "wast" in compact:
        return "Undernourished"
    if "overweight" in compact:
        return "Overweight"
    if "obese" in compact:
        return "Obese"
    if "normal" in compact:
        return "Normal"
    return "Needs Review"


def normalize_hfa(value: Any) -> str | None:
    text = clean(value).lower()
    compact = re.sub(r"[^a-z]", "", text)
    if not compact:
        return None
    if "severe" in compact and "stunt" in compact:
        return "Severely Stunted"
    if compact.startswith("sstunt") or compact.startswith("verelystun"):
        return "Severely Stunted"
    if "stunt" in compact:
        return "Stunted"
    if "tall" in compact:
        return "Tall"
    if "normal" in compact:
        return "Normal"
    return "Needs Review"


def calculate_bmi(weight_kg: float | None, height_cm: float | None) -> float | None:
    if not weight_kg or not height_cm or weight_kg <= 0 or height_cm <= 0:
        return None
    return round(weight_kg / ((height_cm / 100) ** 2), 2)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def issue(
    issues: list[dict[str, Any]],
    severity: str,
    cohort: str,
    learner_uid: str | None,
    field: str,
    source_value: Any,
    message: str,
) -> None:
    issues.append(
        {
            "issue_id": f"DQ-{len(issues) + 1:04d}",
            "severity": severity,
            "cohort": cohort,
            "learner_uid": learner_uid,
            "field": field,
            "source_value": clean(source_value),
            "message": message,
            "resolution_status": "Open",
        }
    )


def extract_master(path: Path, issues: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], list[dict[str, Any]], list[dict[str, Any]]]:
    raw_rows: list[dict[str, Any]] = []
    learners: list[dict[str, Any]] = []
    measurements: list[dict[str, Any]] = []
    identity_keys: defaultdict[tuple[str, str], list[str]] = defaultdict(list)

    with pdfplumber.open(path) as pdf:
        for page_number, page in enumerate(pdf.pages, start=1):
            tables = page.extract_tables()
            if not tables:
                continue
            for row in tables[0]:
                if len(row) < 16 or not clean(row[0]).isdigit():
                    continue
                source_row = int(clean(row[0]))
                uid = f"ERIS-2025-MASTER-{source_row:03d}"
                birthdate = parse_date(row[5], ("%m/%d/%Y", "%m/%d/%y"))
                measured_at = parse_date(row[6], ("%m/%d/%Y", "%m/%d/%y"))
                sex = normalize_sex(row[2])
                report_age = parse_report_age_months(row[7])
                calculated_age = age_months(birthdate, measured_at)
                weight_kg = as_float(row[8])
                height_raw = as_float(row[9])
                height_cm = round(height_raw * 100, 1) if height_raw is not None and height_raw < 3 else height_raw
                source_bmi = as_float(row[10])
                computed_bmi = calculate_bmi(weight_kg, height_cm)
                normalized_status = normalize_nutrition_status(row[11])
                normalized_hfa = normalize_hfa(row[12])

                raw_rows.append(
                    {
                        "source_file": path.name,
                        "source_page": page_number,
                        "source_row": source_row,
                        "source_learner_label": clean(row[1]),
                        "sex_raw": clean(row[2]),
                        "grade_raw": clean(row[3]),
                        "section_raw": clean(row[4]),
                        "birthdate_raw": clean(row[5]),
                        "measured_at_raw": clean(row[6]),
                        "age_raw": clean(row[7]),
                        "weight_kg_raw": clean(row[8]),
                        "height_raw_labelled_cm": clean(row[9]),
                        "bmi_raw": clean(row[10]),
                        "nutrition_status_raw": clean(row[11]),
                        "height_for_age_raw": clean(row[12]),
                        "milk_consent_raw": clean(row[13]),
                        "four_ps_raw": clean(row[14]),
                        "previous_beneficiary_raw": clean(row[15]),
                    }
                )
                learners.append(
                    {
                        "learner_uid": uid,
                        "cohort": "ERIS master list 2025-2026",
                        "source_learner_reference": clean(row[1]),
                        "optional_name": None,
                        "sex": sex,
                        "birthdate": iso(birthdate),
                        "grade": normalize_grade(row[3]),
                        "section": clean(row[4]) or None,
                        "allergies": None,
                        "allergy_status": "Not recorded",
                        "identity_reconciliation_status": "Source-local UID only",
                    }
                )
                measurements.append(
                    {
                        "measurement_uid": f"{uid}-{iso(measured_at) or 'UNKNOWN-DATE'}",
                        "learner_uid": uid,
                        "measured_at": iso(measured_at),
                        "age_months": calculated_age,
                        "weight_kg": weight_kg,
                        "height_cm": height_cm,
                        "source_height_value": height_raw,
                        "source_height_unit_label": "cm",
                        "interpreted_source_height_unit": "m" if height_raw is not None and height_raw < 3 else "cm",
                        "source_bmi": source_bmi,
                        "calculated_bmi": computed_bmi,
                        "source_nutrition_status": clean(row[11]) or None,
                        "nutriflow_status_from_source": normalized_status,
                        "source_height_for_age": clean(row[12]) or None,
                        "normalized_height_for_age": normalized_hfa,
                        "assessment_method": "ERIS BMI-for-age (source)" if source_bmi is not None else "ERIS age-specific method (not specified in PDF)",
                        "source_file": path.name,
                        "source_page": page_number,
                        "source_row": source_row,
                        "validation_status": "Review required",
                    }
                )

                if not birthdate:
                    issue(issues, "Error", "master", uid, "birthdate", row[5], "Birthdate is missing or unreadable.")
                if not measured_at:
                    issue(issues, "Error", "master", uid, "measured_at", row[6], "Measurement date is missing or unreadable.")
                if source_bmi is None and calculated_age is not None and calculated_age >= 72:
                    issue(issues, "Warning", "master", uid, "source_bmi", row[10], "BMI is blank although the learner is at least 6 years old.")
                if source_bmi is not None and computed_bmi is not None and abs(source_bmi - computed_bmi) > 0.25:
                    issue(issues, "Error", "master", uid, "source_bmi", source_bmi, f"Source BMI differs from weight/height BMI ({computed_bmi:.2f}) by more than 0.25.")
                if weight_kg is not None and (weight_kg < 10 or weight_kg > 80):
                    issue(issues, "Error", "master", uid, "weight_kg", weight_kg, "Weight is outside the conservative elementary-school review range of 10-80 kg.")
                if height_cm is not None and (height_cm < 80 or height_cm > 170):
                    issue(issues, "Error", "master", uid, "height_cm", height_cm, "Height is outside the conservative elementary-school review range of 80-170 cm.")
                if report_age is not None and calculated_age is not None and abs(report_age - calculated_age) > 1:
                    issue(issues, "Warning", "master", uid, "age", row[7], f"Reported age differs from birthdate-derived age ({calculated_age} months).")
                if normalized_status == "Needs Review":
                    issue(issues, "Error", "master", uid, "nutrition_status", row[11], "Nutrition status could not be normalized.")
                if normalized_hfa == "Needs Review":
                    issue(issues, "Warning", "master", uid, "height_for_age", row[12], "Height-for-age status could not be normalized.")
                if birthdate and sex:
                    identity_keys[(birthdate.isoformat(), sex)].append(uid)

    for (birthdate_text, sex), uids in identity_keys.items():
        if len(uids) > 1:
            for uid in uids:
                issue(
                    issues,
                    "Warning",
                    "master",
                    uid,
                    "identity",
                    f"{birthdate_text}|{sex}",
                    "Birthdate and sex are shared by multiple learners and must not be used as a unique identifier.",
                )
    return raw_rows, learners, measurements


def extract_bmi_report(path: Path, measured_at: date, phase: str, issues: list[dict[str, Any]]) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []
    with pdfplumber.open(path) as pdf:
        table = pdf.pages[0].extract_tables()[0]
        for row in table:
            source_number = clean(row[0]).rstrip(".") if len(row) >= 11 else ""
            if not source_number.isdigit():
                continue
            source_row = int(source_number)
            uid = f"ERIS-2025-PAIR-{source_row:03d}"
            birthdate = parse_date(row[2], ("%d-%b-%y", "%d-%b-%Y", "%d/%m/%Y"))
            weight_kg = as_float(row[3])
            height_m = as_float(row[4])
            height_cm = round(height_m * 100, 1) if height_m is not None else None
            source_bmi = as_float(row[8])
            computed_bmi = calculate_bmi(weight_kg, height_cm)
            record = {
                "learner_uid": uid,
                "phase": phase,
                "source_row": source_row,
                "source_learner_reference": clean(row[1]),
                "optional_name": None,
                "birthdate": iso(birthdate),
                "sex": normalize_sex(row[5]),
                "measured_at": measured_at.isoformat(),
                "source_age_months": int(as_float(row[7])) if as_float(row[7]) is not None else None,
                "calculated_age_months": age_months(birthdate, measured_at),
                "weight_kg": weight_kg,
                "height_cm": height_cm,
                "source_height_m": height_m,
                "source_bmi": source_bmi,
                "calculated_bmi": computed_bmi,
                "model_bmi": source_bmi if source_bmi is not None else computed_bmi,
                "source_nutrition_status": clean(row[9]) or None,
                "nutriflow_status_from_source": normalize_nutrition_status(row[9]),
                "source_height_for_age": clean(row[10]) or None,
                "normalized_height_for_age": normalize_hfa(row[10]),
                "source_file": path.name,
                "source_page": 1,
            }
            records.append(record)
            if not birthdate:
                issue(issues, "Error", "paired", uid, f"{phase}_birthdate", row[2], "Birthdate is missing or unreadable.")
            if weight_kg is None or height_cm is None:
                issue(issues, "Error", "paired", uid, f"{phase}_measurement", f"{row[3]}|{row[4]}", "Weight or height is missing.")
            if source_bmi is not None and computed_bmi is not None and abs(source_bmi - computed_bmi) > 0.25:
                issue(issues, "Warning", "paired", uid, f"{phase}_bmi", source_bmi, f"Source BMI differs from weight/height BMI ({computed_bmi:.2f}) by more than 0.25.")
    return records


def build_longitudinal(records: list[dict[str, Any]], issues: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: defaultdict[str, dict[str, dict[str, Any]]] = defaultdict(dict)
    for record in records:
        grouped[record["learner_uid"]][record["phase"]] = record
    cohort: list[dict[str, Any]] = []
    for uid in sorted(grouped):
        baseline = grouped[uid].get("baseline")
        followup = grouped[uid].get("followup")
        if not baseline or not followup:
            issue(issues, "Error", "paired", uid, "longitudinal_pair", "", "Baseline or follow-up record is missing.")
            continue
        baseline_date = date.fromisoformat(baseline["measured_at"])
        followup_date = date.fromisoformat(followup["measured_at"])
        days = (followup_date - baseline_date).days
        future_status = followup["nutriflow_status_from_source"]
        target = 1 if future_status in {"Undernourished", "Severely Undernourished"} else 0 if future_status == "Normal" else None
        usable = all(
            value is not None
            for value in (
                baseline["calculated_age_months"],
                baseline["sex"],
                baseline["weight_kg"],
                baseline["height_cm"],
                baseline["model_bmi"],
                baseline["nutriflow_status_from_source"],
                baseline["normalized_height_for_age"],
                target,
            )
        )
        cohort.append(
            {
                "learner_uid": uid,
                "optional_name": None,
                "sex_recorded": baseline["sex"],
                "birthdate": baseline["birthdate"],
                "baseline_date": baseline["measured_at"],
                "followup_date": followup["measured_at"],
                "days_to_followup": days,
                "age_months_at_anchor": baseline["calculated_age_months"],
                "current_weight_kg": baseline["weight_kg"],
                "current_height_cm": baseline["height_cm"],
                "current_bmi": baseline["model_bmi"],
                "current_bmi_flag": baseline["nutriflow_status_from_source"],
                "current_hfa_flag": baseline["normalized_height_for_age"],
                "followup_weight_kg": followup["weight_kg"],
                "followup_height_cm": followup["height_cm"],
                "followup_bmi": followup["model_bmi"],
                "followup_bmi_flag": future_status,
                "future_undernutrition": target,
                "training_eligibility": "Eligible for experimental long-horizon prototype" if usable else "Excluded - incomplete or unsupported",
            }
        )
        if baseline["height_cm"] is not None and followup["height_cm"] is not None and followup["height_cm"] < baseline["height_cm"]:
            issue(issues, "Warning", "paired", uid, "height_change_cm", followup["height_cm"] - baseline["height_cm"], "Recorded height decreased between baseline and follow-up.")
        if baseline["weight_kg"] is not None and followup["weight_kg"] is not None and followup["weight_kg"] < baseline["weight_kg"]:
            issue(issues, "Warning", "paired", uid, "weight_change_kg", followup["weight_kg"] - baseline["weight_kg"], "Recorded weight decreased between baseline and follow-up; verify whether this is real or a measurement issue.")
    return cohort


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-dir", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    source_dir = args.source_dir.resolve()
    paths = {name: source_dir / name for name in (MASTER_FILE, BASELINE_FILE, FOLLOWUP_FILE, TERMINAL_FILE)}
    missing = [str(path) for path in paths.values() if not path.is_file()]
    if missing:
        raise SystemExit(f"Missing source files: {missing}")

    issues: list[dict[str, Any]] = []
    master_raw, learners, master_measurements = extract_master(paths[MASTER_FILE], issues)
    paired = extract_bmi_report(paths[BASELINE_FILE], BASELINE_DATE, "baseline", issues)
    paired.extend(extract_bmi_report(paths[FOLLOWUP_FILE], FOLLOWUP_DATE, "followup", issues))
    longitudinal = build_longitudinal(paired, issues)

    program_enrollment = []
    by_row = {row["source_row"]: row for row in master_raw}
    for learner in learners:
        source_row = int(learner["learner_uid"].rsplit("-", 1)[-1])
        raw = by_row[source_row]
        program_enrollment.append(
            {
                "learner_uid": learner["learner_uid"],
                "school_year": "2025-2026",
                "program": "School-Based Feeding Program",
                "milk_consent": "Yes" if clean(raw["milk_consent_raw"]).lower().startswith("y") else "No" if clean(raw["milk_consent_raw"]).lower().startswith("n") else "Not recorded",
                "four_ps": "Yes" if clean(raw["four_ps_raw"]).lower().startswith("y") else "No" if clean(raw["four_ps_raw"]).lower().startswith("n") else "Not recorded",
                "previous_sbfp_beneficiary": "Yes" if clean(raw["previous_beneficiary_raw"]).lower().startswith("y") else "No" if clean(raw["previous_beneficiary_raw"]).lower().startswith("n") else "Not recorded",
                "sensitivity_note": "4Ps is restricted administrative data and is excluded from predictive features.",
            }
        )

    issue(issues, "Critical", "cross-source", None, "identity_linkage", "Master list vs paired BMI reports", "Censored learner labels are not stable across the master list and paired reports. No automatic cross-source join was performed.")
    issue(issues, "Critical", "terminal-report", None, "baseline_bmi_total", "357", "Terminal baseline BMI categories total 357 rather than 360 beneficiaries.")
    issue(issues, "Critical", "terminal-report", None, "baseline_hfa_total", "409", "Terminal baseline height-for-age categories total 409 despite 360 beneficiaries.")
    issue(issues, "Warning", "terminal-report", None, "sex_total", "195 M / 165 F", "Terminal sex totals differ from the master list extraction (196 M / 164 F).")
    issue(issues, "Warning", "terminal-report", None, "four_ps_total", "12", "Terminal 4Ps count differs from the master list extraction (13).")

    source_registry = [
        {
            "source_file": path.name,
            "sha256": sha256(path),
            "role": (
                "360-row roster and baseline anthropometry" if name == MASTER_FILE else
                "49-row baseline longitudinal measurement" if name == BASELINE_FILE else
                "49-row post-intervention longitudinal measurement" if name == FOLLOWUP_FILE else
                "Aggregate image-based terminal report; no row-level automated extraction"
            ),
        }
        for name, path in paths.items()
    ]

    counts = {
        "master_learners": len(learners),
        "master_measurements": len(master_measurements),
        "paired_measurement_rows": len(paired),
        "paired_children": len(longitudinal),
        "experimental_training_rows": sum(row["training_eligibility"].startswith("Eligible") for row in longitudinal),
        "experimental_positive_outcomes": sum(row["future_undernutrition"] == 1 for row in longitudinal),
        "master_statuses": dict(Counter(row["nutriflow_status_from_source"] for row in master_measurements)),
        "issue_severity": dict(Counter(row["severity"] for row in issues)),
    }

    document = {
        "metadata": {
            "dataset_name": "ERIS censored NutriFlow staging extraction",
            "created_at": datetime.now().astimezone().isoformat(timespec="seconds"),
            "privacy": "Censored source. NutriFlow-generated UIDs only; optional names and allergies are blank.",
            "identity_rule": "Master-list and paired-report UID namespaces are intentionally separate until authorized manual reconciliation.",
            "allergy_rule": "Blank means Not recorded, never No allergies.",
            "model_rule": "Experimental use only; not clinically validated and not approved for production decisions.",
        },
        "source_registry": source_registry,
        "summary": counts,
        "master_raw": master_raw,
        "paired_raw": paired,
        "learners_staging": learners,
        "growth_measurements_staging": master_measurements,
        "program_enrollment_staging": program_enrollment,
        "model_cohort_long_horizon": longitudinal,
        "data_quality_issues": issues,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(document, indent=2, ensure_ascii=False), encoding="utf-8")
    print(json.dumps(counts, indent=2))


if __name__ == "__main__":
    main()
