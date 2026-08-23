@if(!$isEdit)
  <div class="full">
    <section
      class="cohort-recommendation-panel"
      data-cohort-recommendations
      data-endpoint="{{ route('feeding-schedules.recommendations') }}"
      data-csrf="{{ csrf_token() }}"
      data-enabled="{{ ($nutritionPlanEnabled ?? false) ? 'true' : 'false' }}"
    >
      <div class="cohort-recommendation-head">
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="cohort-recommendation-title">Prototype Group Meal Suggestions</div>
            <span class="cohort-prototype-badge">Synthetic Simulation</span>
          </div>
          <div class="cohort-recommendation-copy">Combines the eligible selected children’s nutrient priorities and gives higher-risk children greater influence.</div>
        </div>
        @if($nutritionPlanEnabled ?? false)
          <button class="btn btn-outline-primary btn-sm nl-btn" type="button" data-generate-cohort-recommendations>
            Generate Group Suggestions
          </button>
        @endif
      </div>

      <div class="cohort-recommendation-state" data-cohort-state>
        @if($nutritionPlanEnabled ?? false)
          Select participating students, then generate suggestions. Nothing is saved automatically.
        @else
          Prototype recommendations are disabled. You can continue selecting menu items manually.
        @endif
      </div>

      <div data-cohort-results hidden>
        <div class="cohort-summary-grid" data-cohort-summary></div>

        <div class="mt-3">
          <div class="cohort-section-label">Combined Nutrient Priorities</div>
          <div class="cohort-priority-list" data-cohort-priorities></div>
        </div>

        <div class="mt-3">
          <div class="cohort-section-label">Recommended Menu Items</div>
          <div class="small text-muted mb-2">Choose any suggestion to add it to the manual menu selection below.</div>
          <div class="cohort-meal-grid" data-cohort-meals></div>
        </div>

        <div class="mt-3">
          <div class="cohort-section-label">Group Planning Checks</div>
          <div class="cohort-check-list" data-cohort-checks></div>
        </div>

        <div class="cohort-prototype-notice mt-3" data-cohort-notice></div>
      </div>

      <div class="cohort-budget mt-3" data-cohort-budget>
        <div class="cohort-section-label">School Budget Scenario</div>
        <div class="small text-muted mb-2">This planning calculation belongs to the selected group. It is not stored and cannot filter suggestions until verified food prices are available.</div>
        <div class="cohort-budget-grid">
          <div>
            <label class="form-label small fw-bold" for="cohortBudgetAmount">Available Budget</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">₱</span>
              <input id="cohortBudgetAmount" class="form-control" type="number" min="0.01" step="0.01" placeholder="3000.00" data-cohort-budget-amount>
            </div>
          </div>
          <div>
            <label class="form-label small fw-bold">Selected Servings</label>
            <div class="cohort-budget-result" data-cohort-serving-count>0 children selected</div>
          </div>
          <div>
            <label class="form-label small fw-bold">Maximum Budget per Serving</label>
            <div class="cohort-budget-result" data-cohort-budget-result>Enter a budget and select children</div>
          </div>
        </div>
      </div>
    </section>
  </div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById(@json($modalId));
    const panel = modal?.querySelector('[data-cohort-recommendations]');
    if (!panel) return;

    const studentInputs = Array.from(modal.querySelectorAll('[data-student-row] input[type="checkbox"]'));
    const foodInputs = Array.from(modal.querySelectorAll('[data-food-row] input[type="checkbox"]'));
    const button = panel.querySelector('[data-generate-cohort-recommendations]');
    const state = panel.querySelector('[data-cohort-state]');
    const results = panel.querySelector('[data-cohort-results]');
    const summaryRoot = panel.querySelector('[data-cohort-summary]');
    const prioritiesRoot = panel.querySelector('[data-cohort-priorities]');
    const mealsRoot = panel.querySelector('[data-cohort-meals]');
    const checksRoot = panel.querySelector('[data-cohort-checks]');
    const notice = panel.querySelector('[data-cohort-notice]');
    const budgetAmount = panel.querySelector('[data-cohort-budget-amount]');
    const servingCount = panel.querySelector('[data-cohort-serving-count]');
    const budgetResult = panel.querySelector('[data-cohort-budget-result]');
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    let resultSelection = null;

    function selectedIds() {
      return studentInputs
        .filter((input) => input.checked)
        .map((input) => Number(input.value))
        .sort((left, right) => left - right);
    }

    function setState(message, isError) {
      state.textContent = message;
      state.classList.toggle('error', Boolean(isError));
    }

    function clearResults(message) {
      results.hidden = true;
      resultSelection = null;
      if (message) setState(message, false);
    }

    function updateBudget() {
      const count = selectedIds().length;
      servingCount.textContent = count + ' ' + (count === 1 ? 'child' : 'children') + ' selected';
      const budget = Number(budgetAmount.value);
      budgetResult.textContent = budget > 0 && count > 0
        ? peso.format(budget / count)
        : 'Enter a budget and select children';
    }

    function selectionChanged() {
      const signature = selectedIds().join(',');
      updateBudget();
      if (resultSelection !== null && signature !== resultSelection) {
        clearResults('Student selection changed. Generate new group suggestions for the updated participants.');
      }
    }

    function summaryItem(label, value) {
      const item = document.createElement('div');
      item.className = 'cohort-summary-item';
      const labelElement = document.createElement('div');
      labelElement.className = 'cohort-summary-label';
      labelElement.textContent = label;
      const valueElement = document.createElement('div');
      valueElement.className = 'cohort-summary-value';
      valueElement.textContent = String(value);
      item.append(labelElement, valueElement);

      return item;
    }

    function render(payload) {
      const summary = payload.summary || {};
      summaryRoot.replaceChildren(
        summaryItem('Selected', summary.selected_count || 0),
        summaryItem('Eligible', summary.eligible_count || 0),
        summaryItem('Higher risk', summary.higher_risk_count || 0),
        summaryItem('Not assessed', summary.not_assessed_count || 0)
      );

      prioritiesRoot.replaceChildren();
      Object.entries(payload.priorities || {}).forEach(([nutrient, priority]) => {
        const chip = document.createElement('span');
        chip.className = 'cohort-priority-chip';
        chip.textContent = nutrient + ' · ' + priority + ' priority';
        prioritiesRoot.appendChild(chip);
      });

      mealsRoot.replaceChildren();
      (payload.meals || []).forEach((meal, index) => {
        const card = document.createElement('article');
        card.className = 'cohort-meal-card';

        const name = document.createElement('div');
        name.className = 'cohort-meal-name';
        name.textContent = (index + 1) + '. ' + meal.name;

        const portion = document.createElement('div');
        portion.className = 'cohort-meal-meta';
        portion.textContent = meal.portion + ' · supports ' + meal.support_count + ' of ' + summary.eligible_count + ' eligible children';

        const match = document.createElement('span');
        match.className = 'cohort-match';
        match.textContent = meal.match_label;

        const nutrients = document.createElement('div');
        nutrients.className = 'cohort-meal-meta';
        nutrients.textContent = 'Leading nutrients: ' + (meal.key_nutrients || []).join(', ');

        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'btn btn-sm btn-outline-primary nl-btn';
        addButton.textContent = 'Add to Menu';
        addButton.addEventListener('click', function () {
          const foodInput = foodInputs.find((input) => Number(input.value) === Number(meal.food_id));
          if (!foodInput) return;
          foodInput.checked = true;
          foodInput.dispatchEvent(new Event('change', { bubbles: true }));
          addButton.textContent = 'Added to Menu';
          addButton.disabled = true;
        });

        card.append(name, portion, match, nutrients, addButton);
        mealsRoot.appendChild(card);
      });

      if (!(payload.meals || []).length) {
        const empty = document.createElement('div');
        empty.className = 'cohort-recommendation-state';
        empty.textContent = 'No menu items passed the available nutrition and recorded-allergy checks.';
        mealsRoot.appendChild(empty);
      }

      checksRoot.replaceChildren();
      Object.entries(payload.checks || {}).forEach(([check, checkStatus]) => {
        const row = document.createElement('div');
        row.className = 'cohort-check';
        const checkName = document.createElement('span');
        checkName.textContent = check;
        const statusElement = document.createElement('strong');
        statusElement.textContent = checkStatus;
        row.append(checkName, statusElement);
        checksRoot.appendChild(row);
      });

      notice.textContent = payload.notice || '';
      results.hidden = false;
      resultSelection = selectedIds().join(',');
      setState(
        'Suggestions generated for ' + summary.eligible_count + ' eligible of ' + summary.selected_count + ' selected children. Review before adding menu items.',
        false
      );
    }

    async function generate() {
      const ids = selectedIds();
      if (!ids.length) {
        setState('Select at least one participating student first.', true);
        return;
      }

      button.disabled = true;
      button.textContent = 'Generating suggestions...';
      clearResults();
      setState('Assessing eligible children and combining their nutrient priorities...', false);

      try {
        const response = await fetch(panel.dataset.endpoint, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': panel.dataset.csrf
          },
          body: JSON.stringify({ participant_student_ids: ids })
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Unable to generate group suggestions.');
        if (payload.status !== 'success') {
          clearResults();
          setState(
            payload.message || 'Group suggestions are unavailable.',
            payload.status === 'service_error'
          );
          return;
        }
        render(payload);
      } catch (error) {
        clearResults();
        setState(error.message || 'Unable to generate group suggestions.', true);
      } finally {
        button.disabled = false;
        button.textContent = 'Generate Group Suggestions';
      }
    }

    studentInputs.forEach((input) => input.addEventListener('change', selectionChanged));
    budgetAmount.addEventListener('input', updateBudget);
    button?.addEventListener('click', generate);
    updateBudget();
  });
</script>
@endif
