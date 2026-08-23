@php
  $nutritionAssessment = session('nutritionAssessment');
  $assessmentStatus = $nutritionAssessment['status'] ?? null;
  $assessmentSuccess = $assessmentStatus === 'success';
  $riskHigher = $assessmentSuccess && ($nutritionAssessment['risk_category'] ?? null) === 'higher';
@endphp

<style>
  .nutrition-plan { margin-top: 1.2rem; padding: 1.35rem; scroll-margin-top: 90px; }
  .nutrition-plan-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding-bottom:1rem; border-bottom:1px solid var(--nf-line, #e8edf5); }
  .prototype-badge { display:inline-flex; align-items:center; gap:.4rem; border:1px solid #e3b341; background:#fff8df; color:#8b6100; border-radius:999px; padding:.3rem .65rem; font-size:.72rem; font-weight:900; }
  .nutrition-empty, .nutrition-state { border:1px solid #dbe4f0; background:#f8fbff; border-radius:12px; padding:1rem; margin-top:1rem; }
  .nutrition-state.alert { background:#fff7f4; border-color:#f2c8bc; }
  .risk-overview { display:grid; grid-template-columns:minmax(0, .85fr) minmax(0, 1.6fr); gap:1rem; margin-top:1rem; }
  .risk-score-card { border-radius:14px; padding:1.2rem; color:#fff; background:linear-gradient(135deg,#0b3b82,#165ba7); }
  .risk-score-card.higher { background:linear-gradient(135deg,#a63b1f,#df6b28); }
  .risk-score { font-size:2.55rem; line-height:1; font-weight:950; margin:.5rem 0; }
  .risk-name { font-size:1rem; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
  .risk-copy-card { border:1px solid #e2e8f1; border-radius:14px; padding:1.15rem; background:var(--nf-surface, #fff); }
  .nutrition-section-title { color:var(--nf-text-primary, #082858); font-weight:900; font-size:.95rem; margin-bottom:.75rem; }
  .progress-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.65rem; }
  .progress-item { border:1px solid #e2e8f1; background:var(--nf-surface, #fff); border-radius:11px; padding:.8rem; }
  .progress-item-label { color:var(--nf-text-muted, #7c8bad); font-size:.72rem; }
  .progress-item-value { color:var(--nf-text-primary, #082858); font-size:.9rem; font-weight:900; margin-top:.25rem; }
  .priority-list { display:flex; flex-wrap:wrap; gap:.55rem; }
  .priority-chip { border:1px solid #dbe4f0; border-radius:10px; padding:.55rem .7rem; background:var(--nf-surface, #fff); font-size:.8rem; }
  .priority-level { margin-left:.4rem; font-weight:900; color:#b66e00; }
  .meal-option-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
  .meal-option { border:1px solid #dfe6ef; border-radius:12px; padding:1rem; background:var(--nf-surface, #fff); }
  .meal-rank { color:#b87700; font-size:.72rem; font-weight:900; text-transform:uppercase; }
  .meal-name { color:var(--nf-text-primary, #082858); font-weight:900; margin:.22rem 0; }
  .match-label { display:inline-block; color:#0b6b47; background:#e8f6ef; border-radius:999px; padding:.24rem .55rem; font-size:.72rem; font-weight:900; }
  .nutrient-tags { display:flex; flex-wrap:wrap; gap:.35rem; margin:.65rem 0; }
  .nutrient-tag { background:#edf3fb; color:#31527c; border-radius:6px; padding:.18rem .42rem; font-size:.7rem; font-weight:800; }
  .coverage-row { display:grid; grid-template-columns:90px 1fr 52px; align-items:center; gap:.45rem; margin:.45rem 0; font-size:.74rem; }
  .coverage-track { height:7px; border-radius:999px; background:#e8edf4; overflow:hidden; }
  .coverage-fill { height:100%; background:#e0a21c; border-radius:999px; }
  .meal-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.4rem; }
  .meal-check { display:flex; justify-content:space-between; gap:.5rem; border-bottom:1px solid #edf0f4; padding:.4rem 0; font-size:.75rem; }
  .check-available { color:#0b6b47; font-weight:900; }
  .check-unavailable { color:#8b6100; font-weight:900; }
  .prototype-notice { border-left:3px solid #e0a21c; padding:.55rem .75rem; color:var(--nf-text-muted, #65758f); font-size:.78rem; background:rgba(224,162,28,.08); }
  body.dark .nutrition-plan :where(.nutrition-empty,.nutrition-state,.risk-copy-card,.progress-item,.priority-chip,.meal-option) { background:var(--nf-surface-raised); border-color:var(--nf-line); }
  body.dark .prototype-badge { background:#342a18; color:#ffd166; border-color:#7d6223; }
  body.dark .match-label { background:#153526; color:#a9e8bd; }
  body.dark .nutrient-tag { background:#1c3048; color:#b9d7f7; }
  @media (max-width: 900px) { .risk-overview, .meal-option-grid { grid-template-columns:1fr; } .progress-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width: 640px) { .progress-grid, .meal-checks { grid-template-columns:1fr; } }
</style>

<section class="nl-card nutrition-plan" id="nutritionPlan">
  <div class="nutrition-plan-head">
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
        <div class="panel-title mb-0">Nutrition Plan</div>
        <span class="prototype-badge">Prototype Simulation</span>
      </div>
      <div class="panel-subtitle">Future nutrition risk, recent progress, and nutrient guidance for this child alone.</div>
    </div>
    @if($nutritionPlanEnabled ?? false)
      <form method="POST" action="{{ route('students.nutrition-assessment', $student) }}" data-assessment-form>
        @csrf
        <button class="btn btn-primary btn-sm nl-btn" data-assessment-button>
          {{ $nutritionAssessment ? 'Update Nutrition Assessment' : 'Generate Nutrition Assessment' }}
        </button>
      </form>
    @endif
  </div>

  @if(!($nutritionPlanEnabled ?? false))
    <div class="nutrition-state">
      <div class="fw-bold">Prototype assessment is currently disabled.</div>
      <div class="small text-muted mt-1">Existing nutrition records remain available and unchanged.</div>
    </div>
  @elseif(!$nutritionAssessment)
    <div class="nutrition-empty">
      <div class="fw-bold">No prototype assessment has been generated for this view.</div>
      <div class="small text-muted mt-1">The assessment requires at least two usable growth records from different dates. Results are generated on demand and are not stored as the child's measured status.</div>
    </div>
  @elseif(!$assessmentSuccess)
    <div class="nutrition-state {{ $assessmentStatus === 'service_error' ? 'alert' : '' }}">
      <div class="fw-bold">
        @if($assessmentStatus === 'insufficient_data')
          Predictive assessment unavailable
        @elseif($assessmentStatus === 'out_of_scope')
          Outside prototype scope
        @else
          Assessment temporarily unavailable
        @endif
      </div>
      <div class="small text-muted mt-1">{{ $nutritionAssessment['message'] ?? 'The assessment could not be generated.' }}</div>
      @if(!empty($nutritionAssessment['current_status']))
        <div class="small mt-2"><strong>Current measured status:</strong> {{ $nutritionAssessment['current_status'] }}</div>
      @endif
    </div>
  @else
    @php
      $summary = $nutritionAssessment['input_summary'] ?? [];
      $recommendation = $nutritionAssessment['recommendation'] ?? [];
      $horizon = $nutritionAssessment['prediction_horizon_days'] ?? ['minimum' => 21, 'maximum' => 40];
    @endphp
    <div class="risk-overview">
      <div class="risk-score-card {{ $riskHigher ? 'higher' : '' }}">
        <div class="small opacity-75">Nutrition Risk</div>
        <div class="risk-score">{{ $nutritionAssessment['risk_percent'] }}%</div>
        <div class="risk-name">{{ $nutritionAssessment['risk_label'] }}</div>
        <div class="small opacity-75 mt-2">Approximately 30 days</div>
      </div>
      <div class="risk-copy-card">
        <div class="nutrition-section-title">What this result means</div>
        <p class="small mb-2">
          @if($riskHigher)
            Based on this child's recent growth records, the child may remain or become undernourished within the next 21–40 days.
          @else
            Recent growth records suggest a lower likelihood of undernutrition at the next assessment within 21–40 days.
          @endif
        </p>
        <div class="prototype-notice">This synthetic prototype supports monitoring and interface testing. It is not a medical diagnosis and must not replace qualified judgment.</div>
        <details class="small mt-3">
          <summary class="fw-bold">About this assessment</summary>
          <div class="text-muted mt-2">Version {{ $nutritionAssessment['model_version'] }} · synthetic training data · current threshold {{ number_format($nutritionAssessment['threshold'] * 100) }}% · not clinically validated.</div>
        </details>
      </div>
    </div>

    <div class="mt-4">
      <div class="nutrition-section-title">Recent Progress</div>
      <div class="progress-grid">
        <div class="progress-item"><div class="progress-item-label">Current Status</div><div class="progress-item-value">{{ $summary['current_status'] ?? '-' }}</div></div>
        <div class="progress-item"><div class="progress-item-label">Current Weight</div><div class="progress-item-value">{{ number_format($summary['current_weight_kg'] ?? 0, 2) }} kg</div></div>
        <div class="progress-item"><div class="progress-item-label">Previous Weight</div><div class="progress-item-value">{{ number_format($summary['previous_weight_kg'] ?? 0, 2) }} kg</div></div>
        <div class="progress-item"><div class="progress-item-label">Weight Change</div><div class="progress-item-value">{{ ($summary['weight_change_kg'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($summary['weight_change_kg'] ?? 0, 2) }} kg</div></div>
        <div class="progress-item"><div class="progress-item-label">Current BMI</div><div class="progress-item-value">{{ number_format($summary['current_bmi'] ?? 0, 2) }}</div></div>
        <div class="progress-item"><div class="progress-item-label">Previous BMI</div><div class="progress-item-value">{{ number_format($summary['previous_bmi'] ?? 0, 2) }}</div></div>
        <div class="progress-item"><div class="progress-item-label">BMI Change</div><div class="progress-item-value">{{ ($summary['bmi_change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($summary['bmi_change'] ?? 0, 2) }}</div></div>
        <div class="progress-item"><div class="progress-item-label">Record Dates</div><div class="progress-item-value">{{ $summary['previous_measurement_date'] ?? '-' }} → {{ $summary['current_measurement_date'] ?? '-' }}</div></div>
      </div>
    </div>

    <div class="mt-4">
      <div class="nutrition-section-title">Nutrition Priorities</div>
      <div class="priority-list">
        @foreach(($nutritionAssessment['nutrition_priority'] ?? []) as $nutrient => $priority)
          <div class="priority-chip">{{ $nutrient }} <span class="priority-level">{{ $priority }} Priority</span></div>
        @endforeach
      </div>
      <div class="small text-muted mt-2">Reference basis: applicable one-third PDRI prototype target.</div>
    </div>

    <div class="mt-4">
      <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
        <div>
          <div class="nutrition-section-title mb-1">Food Guidance for This Child</div>
          <div class="small text-muted">Foods ranked for this child’s nutrient priorities. School budget is not part of this individual view.</div>
        </div>
        <span class="prototype-badge">Not feasibility cleared</span>
      </div>
      @if(!empty($recommendation['meals']))
        <div class="meal-option-grid">
          @foreach($recommendation['meals'] as $index => $meal)
            <article class="meal-option">
              <div class="meal-rank">Option {{ $index + 1 }}</div>
              <div class="meal-name">{{ $meal['name'] }}</div>
              <div class="small text-muted">{{ $meal['portion'] }}</div>
              <span class="match-label mt-2">{{ $meal['match_label'] }}</span>
              <div class="nutrient-tags">
                @foreach($meal['key_nutrients'] as $nutrient)<span class="nutrient-tag">{{ $nutrient }}</span>@endforeach
              </div>
              <details class="small">
                <summary class="fw-bold">View nutrition details</summary>
                <div class="mt-2">
                  @foreach($meal['coverage'] as $nutrient => $coverage)
                    <div class="coverage-row">
                      <span>{{ $nutrient }}</span>
                      <div class="coverage-track"><div class="coverage-fill" style="width:{{ min(100, $coverage) }}%"></div></div>
                      <strong>{{ number_format($coverage) }}%</strong>
                    </div>
                  @endforeach
                  <div class="small text-muted mt-2">Technical nutrition score: {{ number_format($meal['nutrition_score'], 3) }}</div>
                </div>
              </details>
            </article>
          @endforeach
        </div>
      @else
        <div class="nutrition-state">No candidate meals could be ranked using the currently available nutrition data.</div>
      @endif

      <div class="mt-3">
        <div class="nutrition-section-title">Individual Guidance Checks</div>
        <div class="meal-checks">
          @foreach(($recommendation['checks'] ?? []) as $check => $checkStatus)
            <div class="meal-check"><span>{{ $check }}</span><span class="{{ $checkStatus === 'Evaluated' ? 'check-available' : 'check-unavailable' }}">{{ $checkStatus }}</span></div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

</section>

<script>
  (function () {
    const form = document.querySelector('[data-assessment-form]');
    const button = document.querySelector('[data-assessment-button]');
    form?.addEventListener('submit', function () {
      if (!button) return;
      button.disabled = true;
      button.textContent = 'Generating nutrition assessment...';
    });
  })();
</script>
