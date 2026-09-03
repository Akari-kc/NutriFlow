# NutriFlow working memory

Last updated: 2026-09-03

This file records the agreed product rules, ERIS extraction decisions, predictive-model boundaries, and the intended implementation order. It is a continuity document for future Codex sessions. It does not authorize later phases by itself; the latest user request still controls what should be changed.

## Current implementation order

1. Extract and validate the censored ERIS data.
2. Build a separate predictive prototype compatible with a later NutriFlow integration.
3. Integrate the approved ERIS-compatible data contract into the application while preserving the current UI appearance.
4. Import actual ERIS records only after administrator review and an explicit go signal.

The first three phases have been completed. Actual ERIS child-level records have not been imported.

## Identity and privacy rules

- NutriFlow must use a permanent privacy-safe internal UID for operational relationships.
- A learner name is optional display metadata associated with the UID.
- Changing, adding, or removing a name must not affect measurements, schedules, meal logs, recommendations, assessments, or model output.
- Names must never be used in preprocessing, training, inference, model evaluation, or feature exports.
- If a name is absent, the UI should display the source learner label or internal UID.
- Searching may use an authorized optional name or UID, but all relationships remain keyed by UID.
- The current censored ERIS labels are not stable between the 360-row master list and the 49-child BMI reports. These cohorts must remain separate unless authorized staff manually reconcile them later.
- Date of birth and sex may assist a manual reconciliation review but must not be treated as a unique identifier.
- 4Ps information is restricted administrative data and is excluded from predictive features.

## Allergy rule

- ERIS has no allergy data.
- Imported allergy values must remain blank with the state `Not recorded`.
- Blank or missing allergy data must never be interpreted as `No allergies`.
- Authorized staff may optionally add allergies later through the learner profile.
- Before a meal is assigned or recommended, an unknown allergy state should remain visible and require staff confirmation according to the eventual safety workflow.

## ERIS sources and extracted cohorts

Source folder:

`C:\Users\vince\Downloads\ERIS - Classified Data (Censored)`

Sources reviewed:

- `ERIS SBFP Final Masterlist 2025-2026 JRU.pdf`
- `BMI SCHOOL JUNE 2025 JRU.pdf` (internal baseline date is 2025-07-14)
- `BMI SCHOOL MARCH 2026.pdf` (post-intervention date is 2026-03-19)
- `ERIS PROGRAM TERMINAL REPORT 2025-2026-JRU.pdf`

Extracted structure:

- Master-list cohort: 360 privacy-safe UIDs in the namespace `ERIS-2025-MASTER-###`.
- Paired longitudinal cohort: 49 privacy-safe UIDs in the namespace `ERIS-2025-PAIR-###`.
- The paired cohort contains 98 measurement rows: one baseline and one follow-up per child.
- Forty-eight paired learners are complete enough for the experimental long-horizon model.
- Five of those 48 have a future undernutrition outcome.

Normalized master-list status counts:

- Normal: 246
- Wasted: 58
- Severely Wasted: 40
- Overweight: 15
- Obese: 1

Do not reshape these real observations to match the earlier synthetic 70/20/10 demonstration distribution.

## Extraction and normalization rules

- Original PDFs are never modified.
- Raw source fields and normalized fields are stored separately.
- Heights labelled as centimetres in the master-list PDF contain metre-form values; values below 3 are interpreted as metres and converted to centimetres while preserving the raw value and label.
- BMI is recalculated from normalized weight and height for comparison. The source BMI remains preserved.
- The application-facing classifications now use the ERIS report vocabulary: `Wasted`, `Severely Wasted`, `Normal`, `Overweight`, and `Obese`.
- Legacy values `Undernourished` and `Severely Undernourished` are accepted only for backward compatibility and at the existing prototype-v2 model boundary.
- Height-for-age is stored separately from BMI/nutritional status.
- Missing or suspicious values are recorded in the Data Quality sheet instead of being silently corrected.
- Under-six records with blank BMI retain the ERIS source status and an unspecified age-specific assessment-method note. NutriFlow must not automatically treat this as the same method as the current BMI-for-age classifier.

Extraction scripts:

- `ml/eris_prototype/extract_eris.py`
- `ml/eris_prototype/normalize_eris.py`

The normalization pass exists because the PDFs contain clipped text fragments such as `Overweigh` and `NormaSle`.

Final review workbook:

`outputs/01a01955-c21f-7cf0-949e-76c1d7e938a8/ERIS_NutriFlow_Staging_and_Model_Audit.xlsx`

The workbook includes README, source registry, learner staging, growth staging, program enrollment, model cohort, model evaluation, out-of-fold predictions, data-quality issues, raw extracts, and a codebook. The `outputs` and `tmp` directories are ignored to reduce the risk of accidentally committing censored child-level data or temporary renders.

## Known ERIS limitations

- No stable cross-source learner identifier is available; do not automatically join the master list to the paired reports.
- The terminal baseline BMI categories total 357 rather than 360.
- The terminal baseline height-for-age categories total 409 despite 360 beneficiaries.
- Terminal sex totals differ from the master list by one learner in each sex.
- Terminal and master-list 4Ps totals differ by one.
- The extraction records missing birthdates, source/computed BMI discrepancies, height/weight review values, duplicate birthdate/sex combinations, and recorded height or weight decreases.
- No allergy, recipe, ingredient, nutrient, price, budget, inventory, portion, preparation-cost, per-session attendance, or individual meal-log dataset is provided.

## Existing prototype-v2 remains unchanged

NutriFlow's existing `prototype-v2` is synthetic, simulation-only, and predicts future undernutrition at the first valid follow-up 21-40 days after an anchor. It requires two prior measurements to produce its current feature set. It is not clinically valid or production-approved.

The ERIS paired history is about 248 days apart and contains only baseline and follow-up measurements. It cannot train or validate the same two-history-to-third-outcome contract. Do not replace the v2 artifact with ERIS data.

The application converts `Wasted` and `Severely Wasted` to the legacy model categories only immediately before calling prototype-v2. The serialized model artifact and its metadata remain unchanged.

## ERIS experimental model

Module:

`ml/eris_prototype`

Artifact version:

`eris-long-horizon-experimental-v0`

Contract:

- Inputs: age in months, current weight, current height, current BMI, sex, source-normalized nutritional status, and height-for-age status from one baseline assessment.
- Target: undernutrition at the ERIS post-intervention measurement approximately 248 days later.
- Explicitly excluded features: optional name, allergies, UID, source learner label, grade, section, 4Ps, and previous beneficiary status.

Models compared using one fixed five-fold stratified cross-validation:

- Logistic regression
- Decision tree
- Random forest
- Gradient boosting

Selected experimental candidate: Decision Tree.

Selected cross-validation summary:

- Mean ROC-AUC: approximately 0.551
- Standard deviation ROC-AUC: approximately 0.253
- Mean average precision: approximately 0.212
- Mean balanced accuracy: approximately 0.564
- Mean F1: approximately 0.213

These results are weak and highly unstable. They demonstrate that the training and inference pipeline works; they do not demonstrate useful or reliable predictive performance.

Safety flags in the metadata must remain false until a later explicit approval:

- `clinical_validity: false`
- `production_integration_approved: false`
- `frontend_integration_enabled: false`
- `replaces_prototype_v2: false`

The controlled inference entry point is `ml/eris_prototype/inference/predictor.py`. It verifies the trusted artifact SHA-256, validates required fields and the observed age range, and returns an experimental-only warning.

## Implemented application integration (2026-09-03)

- Added permanent, school-scoped operational learner IDs in the format `LEARNER-001`.
- Kept numeric database IDs as physical primary/foreign keys; learner IDs are the user-facing operational identifiers.
- Made names nullable and display-only, with the fallback order: optional name, source learner reference, learner ID.
- Made LRN optional.
- Added learner and measurement origin/provenance fields.
- Added assessment phases: Baseline, Midline, Endline, and Additional Monitoring.
- Added separate source nutrition status, height-for-age status, assessment method, and source-record reference fields.
- Added feeding-program enrollment records for school year, milk consent, 4Ps status, and previous beneficiary status.
- Kept 4Ps out of predictive features and limited its profile display to school administrators.
- Updated the CSV template/importer to match records by learner ID, allow blank names/LRN/birthdates, preserve source classifications, and accept enrollment fields.
- Updated profile, roster, schedules, meal logs, reports, search, and exports to use display fallback and show the learner ID.
- Updated blank allergy UI to `Not recorded`.
- Updated classifications to `Wasted` and `Severely Wasted`; severity levels remain separate: Moderate and Severe respectively.
- Updated the synthetic roster to 280 learners, `LEARNER-001` through `LEARNER-280`, with 56 intentionally blank names, five phased growth records each, and one feeding-program enrollment each.
- Preserved the synthetic distribution: 196 Wasted (70%), 56 Normal (20%), and 28 Severely Wasted (10%).
- Preserved the current NutriFlow visual language, navigation, schedule/meal-log relationship, recommendation behavior, and RBAC.
- Remapped 99 pre-integration synthetic schedule participant arrays to the regenerated learner rows through `LEARNER-001`–`LEARNER-280`; verification found zero stale references, zero empty schedules, and zero count mismatches.
- Preserved the original 99 schedule arrays in `feeding_schedule_participant_backups` under operation key `eris-schema-remap-20260903`. One obsolete reference with no current learner was removed from `Breakfast Group B`, changing its participant count from 11 to 10.
- The shared predictive runtime timeout was increased from 10 to 30 seconds after repeated cold-start timeouts made individual, group-session, and meal-log assessments appear temporarily unavailable. Laravel caches were cleared after the change.
- Live verification after the timeout fix: individual assessment succeeded with five ranked meals; the shared group/session/meal-log service succeeded for all 280 selected and eligible synthetic learners with five ranked meal options.
- Full test result after integration: 40 passed, 1 optional model-runtime test skipped.

## Still required before actual ERIS production import

- Add an import-batch/source-provenance record and idempotency keys.
- Add a formal validation-status/data-quality-review record and an administrator-only cross-source reconciliation screen.
- Do not automatically join the 360-row master-list cohort with the 49-child paired cohort.
- Confirm the final reviewed mapping from staging-workbook identifiers to application `LEARNER-###` IDs.
- Review records outside the current classifier's validated age/method scope rather than silently applying clamped thresholds.
- Add model applicability guards so the 21-40-day prototype cannot silently score an approximately 248-day history, and vice versa.
- Keep any ERIS model selectable only through a later reviewed, versioned configuration. Never silently replace an existing artifact.

## Meal recommendation boundary

The ERIS extraction supports child assessment and prototype experimentation only. It cannot make the complete meal recommendation system data-driven because budget and allergy requirements are hard constraints. Until validated allergies, meal composition, serving counts, prices, budgets, inventory, and preparation costs are available, recommendations must retain missing-data warnings and must not invent feasibility inputs.
