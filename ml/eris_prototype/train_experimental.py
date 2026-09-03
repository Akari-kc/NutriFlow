"""Train a separate ERIS long-horizon experimental prototype.

This artifact is intentionally isolated from NutriFlow prototype-v2. It predicts
the ERIS post-intervention undernutrition label from one baseline assessment and
must not be used for clinical or production feeding decisions.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import platform
from datetime import datetime
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import sklearn
from sklearn.base import clone
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import HistGradientBoostingClassifier, RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    average_precision_score,
    balanced_accuracy_score,
    f1_score,
    roc_auc_score,
)
from sklearn.model_selection import StratifiedKFold
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sklearn.tree import DecisionTreeClassifier


NUMERIC_FEATURES = [
    "age_months_at_anchor",
    "current_weight_kg",
    "current_height_cm",
    "current_bmi",
]
CATEGORICAL_FEATURES = [
    "sex_recorded",
    "current_bmi_flag",
    "current_hfa_flag",
]
FEATURES = NUMERIC_FEATURES + CATEGORICAL_FEATURES
SEED = 20260831


def file_sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def preprocessing() -> ColumnTransformer:
    numeric = Pipeline(
        [
            ("impute", SimpleImputer(strategy="median")),
            ("scale", StandardScaler()),
        ]
    )
    categorical = Pipeline(
        [
            ("impute", SimpleImputer(strategy="most_frequent")),
            ("encode", OneHotEncoder(handle_unknown="ignore", sparse_output=False)),
        ]
    )
    return ColumnTransformer(
        [
            ("numeric", numeric, NUMERIC_FEATURES),
            ("categorical", categorical, CATEGORICAL_FEATURES),
        ],
        sparse_threshold=0,
    )


def candidates() -> dict[str, object]:
    return {
        "Logistic Regression": LogisticRegression(
            class_weight="balanced", max_iter=2000, random_state=SEED
        ),
        "Decision Tree": DecisionTreeClassifier(
            class_weight="balanced", max_depth=3, min_samples_leaf=3, random_state=SEED
        ),
        "Random Forest": RandomForestClassifier(
            n_estimators=300,
            class_weight="balanced",
            max_depth=4,
            min_samples_leaf=2,
            random_state=SEED,
            n_jobs=1,
        ),
        "Gradient Boosting": HistGradientBoostingClassifier(
            class_weight="balanced",
            learning_rate=0.05,
            max_depth=3,
            max_iter=100,
            random_state=SEED,
        ),
    }


def evaluate_model(pipeline: Pipeline, x: pd.DataFrame, y: pd.Series, splitter: StratifiedKFold) -> tuple[dict, np.ndarray]:
    probabilities = np.zeros(len(y), dtype=float)
    fold_rows = []
    for fold, (train_index, test_index) in enumerate(splitter.split(x, y), start=1):
        fitted = clone(pipeline)
        fitted.fit(x.iloc[train_index], y.iloc[train_index])
        probability = fitted.predict_proba(x.iloc[test_index])[:, 1]
        prediction = (probability >= 0.5).astype(int)
        truth = y.iloc[test_index].to_numpy()
        probabilities[test_index] = probability
        fold_rows.append(
            {
                "fold": fold,
                "test_rows": int(len(test_index)),
                "test_positive_rows": int(truth.sum()),
                "roc_auc": float(roc_auc_score(truth, probability)),
                "average_precision": float(average_precision_score(truth, probability)),
                "balanced_accuracy": float(balanced_accuracy_score(truth, prediction)),
                "f1": float(f1_score(truth, prediction, zero_division=0)),
            }
        )
    metrics = {}
    for metric in ("roc_auc", "average_precision", "balanced_accuracy", "f1"):
        values = np.array([row[metric] for row in fold_rows], dtype=float)
        metrics[f"mean_{metric}"] = float(values.mean())
        metrics[f"std_{metric}"] = float(values.std(ddof=0))
    metrics["folds"] = fold_rows
    return metrics, probabilities


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--staging-json", required=True, type=Path)
    parser.add_argument("--output-dir", required=True, type=Path)
    parser.add_argument("--evaluation-json", required=True, type=Path)
    args = parser.parse_args()

    document = json.loads(args.staging_json.read_text(encoding="utf-8"))
    frame = pd.DataFrame(document["model_cohort_long_horizon"])
    frame = frame[frame["training_eligibility"].str.startswith("Eligible")].copy()
    frame = frame.dropna(subset=FEATURES + ["future_undernutrition"]).reset_index(drop=True)
    x = frame[FEATURES]
    y = frame["future_undernutrition"].astype(int)
    positives = int(y.sum())
    if len(frame) < 20 or positives < 5 or y.nunique() != 2:
        raise SystemExit(
            f"Experimental training guard failed: rows={len(frame)}, positives={positives}, classes={y.nunique()}"
        )

    splitter = StratifiedKFold(n_splits=5, shuffle=True, random_state=SEED)
    evaluations: dict[str, dict] = {}
    probabilities_by_model: dict[str, np.ndarray] = {}
    pipelines: dict[str, Pipeline] = {}
    for name, estimator in candidates().items():
        pipeline = Pipeline([("preprocess", preprocessing()), ("model", estimator)])
        metrics, probabilities = evaluate_model(pipeline, x, y, splitter)
        evaluations[name] = metrics
        probabilities_by_model[name] = probabilities
        pipelines[name] = pipeline

    selected_name = max(
        evaluations,
        key=lambda name: (
            evaluations[name]["mean_roc_auc"],
            evaluations[name]["mean_f1"],
        ),
    )
    selected = pipelines[selected_name]
    selected.fit(x, y)

    args.output_dir.mkdir(parents=True, exist_ok=True)
    artifact_path = args.output_dir / "eris_long_horizon_experimental_v0.joblib"
    joblib.dump(selected, artifact_path)
    artifact_hash = file_sha256(artifact_path)

    selected_probabilities = probabilities_by_model[selected_name]
    oof_rows = []
    for index, row in frame.iterrows():
        probability = float(selected_probabilities[index])
        oof_rows.append(
            {
                "learner_uid": row["learner_uid"],
                "actual_future_undernutrition": int(row["future_undernutrition"]),
                "oof_probability": probability,
                "oof_prediction_at_0_5": int(probability >= 0.5),
            }
        )

    metadata = {
        "model_version": "eris-long-horizon-experimental-v0",
        "selected_model": selected_name,
        "selection_rule": "Highest mean ROC-AUC in one fixed 5-fold stratified cross-validation; mean F1 tie-breaker.",
        "target": "ERIS post-intervention Undernourished or Severely Undernourished approximately 248 days after baseline.",
        "positive_label": "Future Undernourished or Severely Undernourished",
        "negative_label": "Future Normal",
        "feature_columns": FEATURES,
        "numeric_features": NUMERIC_FEATURES,
        "categorical_features": CATEGORICAL_FEATURES,
        "risk_threshold": 0.5,
        "training_rows": int(len(frame)),
        "positive_rows": positives,
        "negative_rows": int(len(frame) - positives),
        "observed_followup_days": sorted(frame["days_to_followup"].dropna().astype(int).unique().tolist()),
        "observed_age_months_min": int(frame["age_months_at_anchor"].min()),
        "observed_age_months_max": int(frame["age_months_at_anchor"].max()),
        "artifact_file": artifact_path.name,
        "artifact_sha256": artifact_hash,
        "source_staging_sha256": file_sha256(args.staging_json),
        "name_used_as_feature": False,
        "allergies_used_as_feature": False,
        "four_ps_used_as_feature": False,
        "synthetic_data_only": False,
        "single_school_single_cohort": True,
        "clinical_validity": False,
        "production_integration_approved": False,
        "frontend_integration_enabled": False,
        "replaces_prototype_v2": False,
        "limitations": [
            "Only 48 usable learners and 5 positive outcomes.",
            "All outcomes come from one school and one intervention cycle.",
            "The observed follow-up interval is approximately 248 days, not the existing prototype-v2 21-40 day horizon.",
            "Cross-validation estimates have very high uncertainty and must not be presented as clinical accuracy.",
            "Source classifications and assessment methods require confirmation before production use.",
        ],
        "runtime": {
            "python": platform.python_version(),
            "pandas": pd.__version__,
            "scikit_learn": sklearn.__version__,
            "joblib": joblib.__version__,
        },
        "trained_at": datetime.now().astimezone().isoformat(timespec="seconds"),
    }
    (args.output_dir / "model_metadata.json").write_text(
        json.dumps(metadata, indent=2), encoding="utf-8"
    )
    evaluation = {
        "metadata": metadata,
        "candidate_metrics": evaluations,
        "selected_model_oof_predictions": oof_rows,
        "warning": "Experimental comparison only. Do not interpret these metrics as validated clinical performance.",
    }
    args.evaluation_json.parent.mkdir(parents=True, exist_ok=True)
    args.evaluation_json.write_text(json.dumps(evaluation, indent=2), encoding="utf-8")
    print(json.dumps({"selected_model": selected_name, "metrics": evaluations[selected_name], "artifact_sha256": artifact_hash}, indent=2))


if __name__ == "__main__":
    main()
