# NutriFlow

NutriFlow is a school nutrition monitoring app built for nutrition aides, feeding program coordinators, and school staff who need a clearer way to track student health, meal service, and nutrition reports.

The app helps schools keep student nutrition records in one place: children, grade sections, BMI measurements, meal logs, food catalog items, feeding schedules, and exportable reports. It is designed around day-to-day school feeding workflows rather than generic record keeping.

## What NutriFlow Is For

NutriFlow supports school-based feeding and nutrition monitoring programs. It gives staff a simple workspace for answering practical questions such as:

- Which students are undernourished, severely undernourished, overweight, obese, normal, or missing measurements?
- How many meals were served today or during a selected date range?
- Which students need review because of missing or incomplete growth data?
- What meals and nutrients has a child received?
- How is BMI trending across a class, section, school, or reporting period?
- What data should be exported for school nutrition documentation?

The current prototype opens in a nutrition-aide workflow and includes seeded demo data so the app can be explored quickly.

## Main Features

- Nutrition aide dashboard with student count, at-risk counts, meals served today, average calories, average protein, BMI trends, and suggested meal items.
- Student registry for Kinder through Grade 6, with grade and section organization.
- Student profiles with birthdate, gender, allergies, growth measurements, meal history, BMI charts, and latest nutrition status.
- BMI-for-age classification using child BMI thresholds for statuses such as Normal, Undernourished, Severely Undernourished, Overweight, Obese, Needs Review, and No Measurement.
- Batch meal logging for recording meals served to multiple students at once.
- Meal catalog for school-specific food and menu items, including nutrition fields and CSV import.
- Feeding schedule management for planned or recurring school feeding activities.
- Reports page with filters for school year, grade, section, date range, and nutrition status.
- CSV export for nutrition monitoring reports, including student measurements, meals served, calories, and protein totals.
- Multi-school data scoping so users can work within their assigned school.
- Settings area for theme and password management.

## Who Uses It

NutriFlow is intended for:

- Nutrition aides logging daily meals and student measurements.
- School feeding coordinators reviewing program coverage and risk levels.
- Administrators who need nutrition summaries and exportable reports.
- Schools running feeding programs that need a lightweight digital monitoring tool.

## Current Prototype Scope

This repository contains a working prototype. It focuses on the core nutrition-aide workflow and uses demo data to make the app usable immediately.

Implemented now:

- Login and nutrition-aide session flow.
- Dashboard, students, meals, feeding schedules, meal catalog, reports, and settings pages.
- Student measurement tracking with BMI calculation and classification.
- Batch meal logging and per-student meal history.
- Report charts and CSV export.
- Seeded schools, students, meals, foods, growth measurements, and demo activity.

Still planned or partial:

- PDF export is currently rendered through a printable report view.
- More advanced recommendations can be expanded beyond the current nutrition-profile heuristic.
- Production deployment hardening, user roles, and real school onboarding would need additional review before live use.

## Run Locally

On Windows, the quickest way to try the prototype is:

```powershell
.\start-nutriflow.bat
```

Then open:

```text
http://127.0.0.1:8001
```

The prototype uses seeded demo data and is intended to be easy to open for review.

## Manual Setup

If you prefer to run it manually:

1. Copy the environment file.

```powershell
cp .env.example .env
```

2. Configure the database settings in `.env`, then generate the app key.

```powershell
php artisan key:generate
```

3. Run migrations and seed demo data.

```powershell
php artisan migrate
php artisan db:seed
```

4. Install and build frontend assets if needed.

```powershell
npm install
npm run build
```

5. Start the development server.

```powershell
php artisan serve
```

Demo login:

```text
Email: aide@example.com
Password: password
```

## Food CSV Import

The meal catalog can import food records from CSV. Use these headers:

```text
name,portion,kcal,protein_g,carbs_g,fat_g,iron_mg,vit_a_iu,vit_c_mg,calcium_mg
```

Each imported item becomes available in the school's meal catalog for meal logging and nutrient calculations.

## Reporting

NutriFlow reports summarize:

- Student nutrition status.
- Latest measurement date, BMI, weight, and height.
- Meals served during the selected period.
- Estimated calories and protein served.
- Screening progress and BMI trend data.

CSV exports are available from the reports page for external documentation or spreadsheet review.

## Tech Notes

NutriFlow is implemented as a Laravel application with Blade views, Eloquent models, migrations, seeders, and Chart.js-powered reporting visuals. Laravel is the framework behind the app; the primary product goal is school nutrition monitoring.

Ignored local/runtime files include `.env`, `vendor`, `node_modules`, generated build output, logs, storage keys, and other machine-specific artifacts.
