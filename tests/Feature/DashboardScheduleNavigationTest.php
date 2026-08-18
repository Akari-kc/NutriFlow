<?php

namespace Tests\Feature;

use App\Models\FeedingSchedule;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardScheduleNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_navigates_dates_and_shows_sessions_for_the_selected_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 08:00:00', 'Asia/Manila'));
        [$user, $school] = $this->userAndSchool();

        FeedingSchedule::create([
            'school_id' => $school->id,
            'batch_name' => 'Monday Breakfast Group',
            'grade_range' => 'Grade 1',
            'meal_type' => 'Breakfast',
            'status' => 'Scheduled',
            'session_date' => '2026-08-17',
            'start_time' => '07:00',
            'end_time' => '07:30',
            'student_count' => 12,
            'assigned_aide' => 'Maria Santos',
        ]);

        $response = $this->actingAs($user)->get('/dashboard?schedule_date=2026-08-17');

        $response
            ->assertOk()
            ->assertSee('Mon, Aug 17')
            ->assertSee('Monday Breakfast Group')
            ->assertSeeText('12 students')
            ->assertSeeText('Scheduled')
            ->assertSee('schedule_date=2026-08-16', false)
            ->assertSee('schedule_date=2026-08-18', false)
            ->assertSee('/feeding-schedules?mode=week&amp;date=2026-08-18', false)
            ->assertDontSee('No feeding session scheduled for this date.');
    }

    public function test_dashboard_navigation_keeps_working_on_dates_without_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 08:00:00', 'Asia/Manila'));
        [$user] = $this->userAndSchool();

        $response = $this->actingAs($user)->get('/dashboard?schedule_date=2026-08-19');

        $response
            ->assertOk()
            ->assertSee('Wed, Aug 19')
            ->assertSee('No feeding session scheduled for this date.')
            ->assertSee('schedule_date=2026-08-18', false)
            ->assertSee('schedule_date=2026-08-20', false);
    }

    public function test_invalid_schedule_date_falls_back_to_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 08:00:00', 'Asia/Manila'));
        [$user] = $this->userAndSchool();

        $this->actingAs($user)
            ->get('/dashboard?schedule_date=not-a-date')
            ->assertOk()
            ->assertSee('Tue, Aug 18');
    }

    public function test_schedule_fragment_returns_only_the_selected_school_schedule_section(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 08:00:00', 'Asia/Manila'));
        [$user, $school] = $this->userAndSchool();
        $otherSchool = School::create(['name' => 'Other School']);

        foreach ([[$school->id, 'Own School Session'], [$otherSchool->id, 'Other School Session']] as [$schoolId, $batchName]) {
            FeedingSchedule::create([
                'school_id' => $schoolId,
                'batch_name' => $batchName,
                'grade_range' => 'Grade 1',
                'meal_type' => 'Lunch',
                'status' => 'Scheduled',
                'session_date' => '2026-08-18',
                'start_time' => '12:00',
                'end_time' => '12:30',
                'student_count' => 10,
            ]);
        }

        $this->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/dashboard/feeding-schedule?schedule_date=2026-08-18')
            ->assertOk()
            ->assertSee('Feeding Schedule')
            ->assertSee('Own School Session')
            ->assertDontSee('Other School Session')
            ->assertDontSee('Total Students');
    }

    private function userAndSchool(): array
    {
        $school = School::create(['name' => 'Dashboard Test School']);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);

        return [$user, $school];
    }
}
