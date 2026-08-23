<?php

namespace Tests\Feature;

use App\Models\FeedingSchedule;
use App\Models\Food;
use App\Models\Meal;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealScheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $aide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Linked Meal Log School']);
        $this->aide = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);
    }

    public function test_schedule_and_meal_log_pages_expose_the_linked_workflow(): void
    {
        [$schedule] = $this->scheduleFixture();

        $this->actingAs($this->aide)
            ->get(route('feeding-schedules.index'))
            ->assertOk()
            ->assertSee('Select all')
            ->assertSee('Clear selection')
            ->assertSee('Log This Session');

        $this->actingAs($this->aide)
            ->get(route('meals.batch', ['feeding_schedule_id' => $schedule->id]))
            ->assertOk()
            ->assertSee('Scheduled Feeding Session')
            ->assertSee($schedule->batch_name)
            ->assertSee('Actual Attendance')
            ->assertSee('Prototype Group Nutrition Assessment')
            ->assertSee('Ad hoc meal (not scheduled)');
    }

    public function test_valid_add_session_submission_is_saved_and_visible_in_the_selected_week(): void
    {
        $student = Student::create([
            'name' => 'New Session Child',
            'school_id' => $this->school->id,
        ]);
        $food = Food::create([
            'name' => 'New Session Meal',
            'school_id' => $this->school->id,
        ]);
        $date = now('Asia/Manila')->toDateString();

        $this->actingAs($this->aide)
            ->post(route('feeding-schedules.store'), [
                'session_name' => 'New Visible Session',
                'meal_type' => 'Lunch',
                'status' => 'Scheduled',
                'session_date' => $date,
                'start_time' => '11:00',
                'end_time' => '12:00',
                'assigned_aide' => $this->aide->name,
                'participant_student_ids' => [$student->id],
                'selected_food_ids' => [$food->id],
            ])
            ->assertRedirect(route('feeding-schedules.index', ['mode' => 'week', 'date' => $date]));

        $this->assertDatabaseHas('feeding_schedules', [
            'school_id' => $this->school->id,
            'batch_name' => 'New Visible Session',
            'student_count' => 1,
            'status' => 'Scheduled',
        ]);

        $this->actingAs($this->aide)
            ->get(route('feeding-schedules.index', ['mode' => 'week', 'date' => $date]))
            ->assertOk()
            ->assertSee('New Visible Session')
            ->assertSee('New Session Meal');

        $this->actingAs($this->aide)
            ->get(route('meals.batch'))
            ->assertOk()
            ->assertSee('New Visible Session');
    }

    public function test_rejected_add_session_reopens_with_clear_validation_feedback(): void
    {
        $student = Student::create([
            'name' => 'Validation Child',
            'school_id' => $this->school->id,
        ]);

        $response = $this->actingAs($this->aide)
            ->from(route('feeding-schedules.index'))
            ->post(route('feeding-schedules.store'), [
                '_schedule_form_mode' => 'add',
                'session_name' => 'Incomplete Session',
                'meal_type' => 'Lunch',
                'status' => 'Scheduled',
                'session_date' => now('Asia/Manila')->toDateString(),
                'start_time' => '11:00',
                'end_time' => '12:00',
                'participant_student_ids' => [$student->id],
            ]);

        $response
            ->assertRedirect(route('feeding-schedules.index'))
            ->assertSessionHasErrors([
                'selected_food_ids' => 'Select at least one menu item before adding the session.',
            ]);

        $this->actingAs($this->aide)
            ->get(route('feeding-schedules.index'))
            ->assertOk()
            ->assertSee('The feeding session was not added.')
            ->assertSee('Select at least one menu item before adding the session.')
            ->assertSee('Incomplete Session')
            ->assertSee('getOrCreateInstance(modalElement).show()', false);

        $this->assertDatabaseMissing('feeding_schedules', [
            'batch_name' => 'Incomplete Session',
        ]);
    }

    public function test_upcoming_session_is_visible_as_a_future_reference_in_meal_logging(): void
    {
        [$schedule] = $this->scheduleFixture();
        $schedule->update([
            'session_date' => now('Asia/Manila')->addDay()->toDateString(),
            'status' => 'Scheduled',
        ]);

        $this->actingAs($this->aide)
            ->get(route('meals.batch'))
            ->assertOk()
            ->assertSee($schedule->batch_name)
            ->assertSee('available on session date');
    }

    public function test_linked_log_uses_actual_attendance_and_marks_the_session_completed(): void
    {
        [$schedule, $firstStudent, $secondStudent, $plannedFood, $substituteFood] = $this->scheduleFixture();

        $response = $this->actingAs($this->aide)->post(route('meals.batch.store'), [
            'feeding_schedule_id' => $schedule->id,
            'meal_type' => 'Lunch',
            'served_at' => now('Asia/Manila')->subMinute()->format('Y-m-d H:i:s'),
            'served_students' => [$firstStudent->id],
            'items' => [['food_id' => $substituteFood->id, 'quantity' => 1]],
        ]);

        $response->assertRedirect(route('meals.index'));
        $this->assertDatabaseHas('meals', [
            'student_id' => $firstStudent->id,
            'feeding_schedule_id' => $schedule->id,
            'logged_by_user_id' => $this->aide->id,
        ]);
        $this->assertDatabaseMissing('meals', [
            'student_id' => $secondStudent->id,
            'feeding_schedule_id' => $schedule->id,
        ]);
        $meal = Meal::where('feeding_schedule_id', $schedule->id)->sole();
        $this->assertDatabaseHas('meal_items', [
            'meal_id' => $meal->id,
            'food_id' => $substituteFood->id,
        ]);
        $this->assertDatabaseMissing('meal_items', [
            'meal_id' => $meal->id,
            'food_id' => $plannedFood->id,
        ]);
        $this->assertSame('Completed', $schedule->fresh()->status);
    }

    public function test_linked_log_rejects_duplicates_unscheduled_attendees_and_cross_school_sessions(): void
    {
        [$schedule, $firstStudent, , $plannedFood] = $this->scheduleFixture();
        $sameSchoolOutsider = Student::create([
            'name' => 'Not Scheduled',
            'school_id' => $this->school->id,
        ]);
        $payload = [
            'feeding_schedule_id' => $schedule->id,
            'meal_type' => 'Lunch',
            'served_at' => now('Asia/Manila')->subMinute()->format('Y-m-d H:i:s'),
            'served_students' => [$firstStudent->id],
            'items' => [['food_id' => $plannedFood->id, 'quantity' => 1]],
        ];

        $this->actingAs($this->aide)
            ->post(route('meals.batch.store'), [
                ...$payload,
                'served_students' => [$sameSchoolOutsider->id],
            ])
            ->assertForbidden();

        $this->actingAs($this->aide)->post(route('meals.batch.store'), $payload)->assertRedirect(route('meals.index'));
        $this->actingAs($this->aide)
            ->from(route('meals.batch'))
            ->post(route('meals.batch.store'), $payload)
            ->assertSessionHasErrors('feeding_schedule_id');
        $this->assertSame(1, Meal::where('feeding_schedule_id', $schedule->id)->count());

        $otherSchool = School::create(['name' => 'Other Linked Log School']);
        $otherStudent = Student::create(['name' => 'Other Child', 'school_id' => $otherSchool->id]);
        $otherFood = Food::create(['name' => 'Other Food', 'school_id' => $otherSchool->id]);
        $otherSchedule = FeedingSchedule::create([
            'school_id' => $otherSchool->id,
            'batch_name' => 'Other Session',
            'grade_range' => 'Grade 1',
            'participant_student_ids' => [$otherStudent->id],
            'selected_food_ids' => [$otherFood->id],
            'meal_type' => 'Lunch',
            'status' => 'Scheduled',
            'session_date' => now('Asia/Manila')->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'student_count' => 1,
        ]);

        $this->actingAs($this->aide)
            ->post(route('meals.batch.store'), [
                ...$payload,
                'feeding_schedule_id' => $otherSchedule->id,
                'served_students' => [$otherStudent->id],
                'items' => [['food_id' => $otherFood->id, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_ad_hoc_logs_remain_visible_and_separate_from_schedules(): void
    {
        $student = Student::create(['name' => 'Ad Hoc Child', 'school_id' => $this->school->id]);
        $food = Food::create(['name' => 'Ad Hoc Food', 'school_id' => $this->school->id]);

        $this->actingAs($this->aide)->post(route('meals.batch.store'), [
            'meal_type' => 'Snack',
            'served_at' => now('Asia/Manila')->subMinute()->format('Y-m-d H:i:s'),
            'served_students' => [$student->id],
            'items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect(route('meals.index'));

        $this->actingAs($this->aide)
            ->get(route('meals.index'))
            ->assertOk()
            ->assertSee('Ad hoc')
            ->assertSee('Manually logged')
            ->assertSee($student->name);
    }

    private function scheduleFixture(): array
    {
        $firstStudent = Student::create([
            'name' => 'Scheduled Child One',
            'school_id' => $this->school->id,
        ]);
        $secondStudent = Student::create([
            'name' => 'Scheduled Child Two',
            'school_id' => $this->school->id,
        ]);
        $plannedFood = Food::create([
            'name' => 'Planned Meal',
            'school_id' => $this->school->id,
        ]);
        $substituteFood = Food::create([
            'name' => 'Actual Substitute',
            'school_id' => $this->school->id,
        ]);
        $schedule = FeedingSchedule::create([
            'school_id' => $this->school->id,
            'batch_name' => 'Linked Lunch Session',
            'grade_range' => 'Grade 1',
            'participant_student_ids' => [$firstStudent->id, $secondStudent->id],
            'selected_food_ids' => [$plannedFood->id],
            'meal_type' => 'Lunch',
            'status' => 'Scheduled',
            'session_date' => now('Asia/Manila')->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'student_count' => 2,
            'menu_items' => $plannedFood->name,
        ]);

        return [$schedule, $firstStudent, $secondStudent, $plannedFood, $substituteFood];
    }
}
