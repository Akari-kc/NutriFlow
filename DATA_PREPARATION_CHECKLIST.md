# NutriFlow Data Preparation Checklist

Good morning,

To help your team prepare the data for NutriFlow, please provide the datasets below in **Excel (`.xlsx`) or CSV (`.csv`) format**. One Excel workbook with a separate sheet for each dataset is preferred. Please provide the most current and complete records available, including historical records where specified.

## 1. School and Academic Information

Please provide one row for each school, with the following fields:

- `school_name` — official school name
- `school_address` — complete address
- `street`
- `city_or_municipality`
- `region`
- `school_year` — example: `2026-2027`

Please also provide the official list of grade levels and sections offered by the school:

- `grade` — accepted values: `Kinder`, `Grade 1`, `Grade 2`, `Grade 3`, `Grade 4`, `Grade 5`, or `Grade 6`
- `section`

## 2. Learner Master List

Please provide one row per learner. The following fields are required unless marked optional:

- `lrn` — Learner Reference Number; this must be unique per learner
- `first_name`
- `middle_initial` — optional
- `last_name`
- `suffix` — optional; example: `Jr.`, `III`
- `gender` — `Male` or `Female`
- `birthdate` — `YYYY-MM-DD`
- `grade` — `Kinder` through `Grade 6`
- `section`
- `allergies` — optional; list all known food allergies or intolerances, separated by commas. Write `None` only when this is confirmed; otherwise leave blank.
- `photo_filename` — optional; if learner photos are available, name each image using the learner's LRN and provide the images in a separate folder

For allergy records, please use the most specific description available, such as peanuts, tree nuts, milk, lactose, eggs, soy, wheat/gluten, fish, shellfish, shrimp, crab, sesame, corn, banana, or another identified allergen.

## 3. Learner Anthropometric or Growth Measurements

Please provide **one row per learner per measurement date**. If several measurements exist for one learner, repeat the LRN on multiple rows.

- `lrn` — used to match the measurement to the learner
- `measured_at` — actual measurement date in `YYYY-MM-DD`
- `weight_kg` — numeric value in kilograms
- `height_cm` — numeric value in centimeters

Please include the baseline measurement and all available follow-up measurements. NutriFlow will calculate the learner's age at measurement, BMI, BMI trend, and nutrition classification; these do not need to be supplied as mandatory source fields.

If your records already contain the following official assessment results, they may be included as reference fields:

- `recorded_bmi` — optional
- `recorded_nutrition_status` — optional; retain the exact classification used in the source record

## 4. Food and Menu Catalog

Please provide one row per food, dish, or menu item used in the feeding program:

- `food_name`
- `standard_portion` — example: `1 cup`, `1 bowl`, `1 piece`, or `250 mL`
- `recipe_or_ingredients` — ingredients and, if available, quantities used for one recipe or batch
- `energy_kcal` — kilocalories per stated standard portion
- `protein_g` — grams per portion
- `carbohydrates_g` — grams per portion
- `fat_g` — grams per portion
- `iron_mg` — milligrams per portion
- `vitamin_a_iu` — IU per portion
- `vitamin_c_mg` — milligrams per portion
- `calcium_mg` — milligrams per portion
- `allergens_present` — known allergens in the item; optional but strongly recommended

Please state the source of the nutrient values, if available, such as a nutrition label, standardized recipe computation, FNRI reference, or supplier specification.

## 5. Historical Meal-Service Records

If existing feeding or attendance records will be migrated, please provide one row per learner per meal service, or one row per learner-food combination when a meal contains multiple items:

- `lrn`
- `served_at` — actual date and time served; preferred format: `YYYY-MM-DD HH:MM`
- `meal_type` — `Breakfast`, `Lunch`, `Snack`, or `Dinner`
- `food_name` — must correspond to the food/menu catalog
- `quantity` — number of portions served
- `portion_served` — optional when different from the standard portion
- `feeding_session_name` — optional, if the meal belongs to a scheduled feeding activity
- `recorded_by` — optional; name or staff identifier of the person who logged or served the meal

If individual learner-level meal records are unavailable, please provide the most detailed attendance and menu records available for each feeding date and clearly indicate their level of aggregation.

## 6. Feeding Program Schedules

Please provide all upcoming schedules and any historical sessions that need to appear in the system:

- `session_name` — name or batch label of the feeding activity
- `session_date` — `YYYY-MM-DD`
- `start_time` — 24-hour `HH:MM`
- `end_time` — 24-hour `HH:MM`
- `meal_type` — `Breakfast`, `Lunch`, `Snack`, or `Dinner`
- `status` — `Scheduled`, `Ongoing`, `Completed`, or `Cancelled`
- `assigned_aide` — assigned staff member; optional
- `participant_lrns` — list of participating learners' LRNs, or provide these in a separate participant sheet using `session_name` and `lrn`
- `menu_items` — list of food/menu items, or provide these in a separate menu sheet using `session_name`, `food_name`, and `quantity`
- `notes` — optional

## 7. Authorized System Users

Please provide one row per staff member who needs access to NutriFlow:

- `full_name`
- `email_address`
- `preferred_username`
- `role` — identify whether the person is a nutrition aide or administrator
- `assigned_school`

Please **do not send existing passwords**. Temporary passwords should be created separately and changed by each user after initial login.

## File and Data-Quality Requirements

- Prefer one workbook with these sheets: `School`, `Grade_Sections`, `Learners`, `Measurements`, `Foods`, `Meal_History`, `Feeding_Schedules`, and `Users`.
- Keep one value per cell and one record per row; do not merge cells.
- Use LRN as the learner-matching identifier across all sheets.
- Use `YYYY-MM-DD` for dates and 24-hour `HH:MM` for times.
- Use kilograms for weight and centimeters for height.
- Do not replace missing values with guessed data. Leave the cell blank and, if necessary, explain the reason in a notes column.
- Remove duplicate learner records and verify that names, LRNs, grades, and sections are consistent across files.
- Please identify the reporting period or school year covered by every historical dataset.
- Because the files contain personal and health information, please transmit them only through the agreed secure channel after the NDA and required authorizations are complete.

## Fields Calculated by NutriFlow

The following do not need to be prepared as mandatory source data because the system calculates or summarizes them:

- learner age and age in months
- BMI
- BMI-for-age nutrition status
- nutrition-risk level
- BMI trends
- total students and participants
- number of meals served
- screening progress
- nutrient totals based on the food catalog and quantity served

