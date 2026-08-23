<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_requires_confirmation_before_submitting_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('aria-label="Open logout confirmation"', false)
            ->assertSee('data-bs-target="#logoutConfirmationModal"', false)
            ->assertSee('Log out of NutriFlow?')
            ->assertSee('data-bs-dismiss="modal">Cancel</button>', false)
            ->assertSee('form="logoutForm">Log out</button>', false);
    }
}
