<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'ape_pat' => 'Paterno',
                'ape_mat' => 'Materno',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('Paterno', $user->ape_pat);
        $this->assertSame('Materno', $user->ape_mat);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Test User',
                'ape_pat' => 'Paterno',
                'ape_mat' => 'Materno',
                'email' => 'taken@example.com',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect('/profile');

        $this->assertNotSame('taken@example.com', $user->fresh()->email);
    }

    public function test_role_area_and_permissions_cannot_be_updated_from_profile(): void
    {
        $user = User::factory()->create([
            'role_id' => 'role-original',
            'area_id' => 'area-original',
            'status' => 1,
        ]);

        $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'ape_pat' => 'Paterno',
                'ape_mat' => 'Materno',
                'email' => 'nuevo@example.com',
                'role_id' => 'role-hack',
                'area_id' => 'area-hack',
                'status' => 0,
                'permissions' => ['users', 'roles'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('nuevo@example.com', $user->email);
        $this->assertSame('role-original', (string) $user->role_id);
        $this->assertSame('area-original', (string) $user->area_id);
        $this->assertSame(1, (int) $user->status);
        $this->assertFalse(isset($user->permissions));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'ape_pat' => $user->ape_pat ?? 'Paterno',
                'ape_mat' => $user->ape_mat ?? 'Materno',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
