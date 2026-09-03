# ERIS long-horizon experimental prototype

This module is a separate, disabled-by-default experiment built from the censored ERIS reports. It does not replace `ml/models/nutriflow_undernutrition_risk_v2.joblib` and is not connected to the NutriFlow frontend.

## Contract

- Input: one baseline assessment containing age, sex, weight, height, BMI, source-normalized BMI status, and height-for-age status.
- Target: ERIS post-intervention undernutrition approximately 248 days later.
- Training cohort: 48 usable learners, including 5 positive outcomes, from one school and one feeding cycle.
- Models compared: logistic regression, decision tree, random forest, and gradient boosting.
- Selection: highest fixed five-fold stratified mean ROC-AUC, with mean F1 as a tie-breaker.

Names, allergies, 4Ps membership, section, learner labels, and UIDs are not model features. UIDs are retained only to audit out-of-fold evaluation rows.

## Safety status

The artifact is technically runnable but not clinically validated, not production-approved, and not suitable for autonomous feeding or meal decisions. Its cross-validation estimates are highly uncertain because only five positive outcomes are available. Frontend integration must remain disabled until a later review explicitly approves a model version and its horizon.

Run controlled inference with the project-local Python environment and send one JSON object through standard input:

```text
.venv\Scripts\python.exe ml\eris_prototype\inference\predictor.py
```

The artifact SHA-256 is verified against `models/model_metadata.json` before it is loaded.
