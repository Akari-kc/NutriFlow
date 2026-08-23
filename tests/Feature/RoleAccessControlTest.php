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

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $aide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'RBAC Test School']);
        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => User::ROLE_SCHOOL_ADMIN,
        ]);
        $this->aide = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => User::ROLE_NUTRITION_AIDE,
        ]);
    }

    public function test_guests_are_not_silently_logged_into_the_prototype(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_nutrition_aide_can_view_monitoring_pages_without_admin_controls(): void
    {
        $this->actingAs($this->aide)
            ->get('/students')
            ->assertOk()
            ->assertDontSee('Add Child');

        $this->actingAs($this->aide)
            ->get('/menu-items')
            ->assertOk()
            ->assertDontSee('Add Meal')
            ->assertDontSee('Upload Meals CSV');

        $this->actingAs($this->aide)
            ->get('/meals')
            ->assertOk()
            ->assertSee('Log Meals');

        $this->actingAs($this->aide)
            ->get('/reports')
            ->assertOk()
            ->assertDontSee('Export PDF')
            ->assertDontSee('Export CSV');

        $this->actingAs($this->aide)
            ->get('/settings')
            ->assertOk()
            ->assertDontSee('Open Import Data');
    }

    public function test_nutrition_aide_receives_403_for_administrative_endpoints(): void
    {
        $student = Student::create([
            'name' => 'Protected Student',
            'school_id' => $this->school->id,
        ]);
        $food = Food::create([
            'name' => 'Protected Food',
            'school_id' => $this->school->id,
        ]);
        $meal = Meal::create([
            'student_id' => $student->id,
            'logged_by_user_id' => $this->admin->id,
            'meal_type' => 'Lunch',
            'served_at' => now(),
        ]);
        $schedule = FeedingSchedule::create([
            'school_id' => $this->school->id,
            'batch_name' => 'Protected Schedule',
            'grade_range' => 'Grade 1',
            'meal_type' => 'Lunch',
            'session_date' => now()->toDateString(),
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        $requests = [
            ['get', '/students/create'],
            ['post', '/students'],
            ['post', '/students/sections'],
            ['get', "/students/{$student->id}/edit"],
            ['patch', "/students/{$student->id}"],
            ['delete', "/students/{$student->id}"],
            ['get', '/student-import'],
            ['get', '/student-import/template'],
            ['post', '/student-import'],
            ['delete', "/meals/{$meal->id}"],
            ['delete', "/feeding-schedules/{$schedule->id}"],
            ['get', '/menu-items/create'],
            ['post', '/menu-items'],
            ['get', "/menu-items/{$food->id}/edit"],
            ['patch', "/menu-items/{$food->id}"],
            ['delete', "/menu-items/{$food->id}"],
            ['post', '/menu-items/upload-csv'],
            ['get', '/reports/export/csv'],
            ['get', '/reports/export/pdf'],
        ];

        foreach ($requests as [$method, $url]) {
            $this->actingAs($this->aide)->{$method}($url)->assertForbidden();
        }
    }

    public function test_school_admin_retains_administrative_page_access(): void
    {
        $this->actingAs($this->admin)->get('/students/create')->assertOk();
        $this->actingAs($this->admin)->get('/student-import')->assertOk();
        $this->actingAs($this->admin)->get('/menu-items/create')->assertOk();
        $this->actingAs($this->admin)->get('/reports/export/pdf')->assertOk();
    }

    public function test_nutrition_aide_can_record_measurements_and_meal_logs_for_own_school(): void
    {
        $student = Student::create([
            'name' => 'Operational Student',
            'gender' => 'Female',
            'birthdate' => now()->subYears(9)->toDateString(),
            'school_id' => $this->school->id,
        ]);
        $food = Food::create([
            'name' => 'Operational Meal',
            'school_id' => $this->school->id,
        ]);

        $this->actingAs($this->aide)->post("/students/{$student->id}/measurements", [
            'measured_at' => now()->toDateString(),
            'weight_value' => 28,
            'weight_unit' => 'kg',
            'height_value' => 130,
            'height_unit' => 'cm',
        ])->assertRedirect();

        $this->actingAs($this->aide)->post('/meals/batch', [
            'meal_type' => 'Lunch',
            'served_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'served_students' => [$student->id],
            'items' => [['food_id' => $food->id, 'quantity' => 1]],
        ])->assertRedirect('/meals');

        $this->assertDatabaseHas('growth_measurements', ['student_id' => $student->id]);
        $this->assertDatabaseHas('meals', [
            'student_id' => $student->id,
            'logged_by_user_id' => $this->aide->id,
        ]);
    }

    public function test_operational_writes_cannot_reference_another_schools_records(): void
    {
        $otherSchool = School::create(['name' => 'Other School']);
        $otherStudent = Student::create([
            'name' => 'Other Student',
            'school_id' => $otherSchool->id,
        ]);
        $otherFood = Food::create([
            'name' => 'Other Food',
            'school_id' => $otherSchool->id,
        ]);

        $this->actingAs($this->aide)->post('/meals/batch', [
            'meal_type' => 'Lunch',
            'served_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'served_students' => [$otherStudent->id],
            'items' => [['food_id' => $otherFood->id, 'quantity' => 1]],
        ])->assertForbidden();
    }
}
