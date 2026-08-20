<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDateRangePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_use_an_interactive_calendar_range_picker_with_the_existing_filter_format(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);

        $this->actingAs($user)
            ->get('/reports?date_range=2026-08-01%20to%202026-08-15')
            ->assertOk()
            ->assertSee('Aug 1, 2026 to Aug 15, 2026')
            ->assertSee('id="reportDateRangePicker"', false)
            ->assertSee('id="reportDateRangeValue"', false)
            ->assertSee('type="hidden"', false)
            ->assertSee('value="2026-08-01 to 2026-08-15"', false)
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('aria-label="Choose report date range"', false)
            ->assertDontSee('placeholder="YYYY-MM-DD to YYYY-MM-DD"', false);
    }

    public function test_dashboard_risk_labels_have_matching_dark_mode_alert_accents(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('risk-label d-block fw-bold small mt-1', false)
            ->assertSee('--risk-alert-accent: #ff8a94;', false)
            ->assertSee('--risk-alert-accent: #ffd166;', false)
            ->assertSee('background-color: var(--risk-alert-accent) !important;', false);
    }
}
