<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProfileApiTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up any test users created
        User::where('email', 'like', 'token_test_%@example.com')->delete();
        parent::tearDown();
    }

    private function createTestUser(): User
    {
        return User::create([
            'name' => 'Token Tester',
            'email' => 'token_test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'active' => 1,
        ]);
    }

    public function test_user_can_view_profile_page_with_tokens(): void
    {
        $user = $this->createTestUser();
        $token = $user->createToken('Mi Dispositivo');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Mi Dispositivo')
        );

        $token->accessToken->delete();
        $user->delete();
    }

    public function test_user_can_generate_new_api_token(): void
    {
        $user = $this->createTestUser();

        $response = $this->actingAs($user)->post(route('profile.tokens.store'), [
            'name' => 'Token Móvil',
            'expiration' => '30',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Token de API generado exitosamente.');
        $response->assertSessionHas('newToken');

        $this->assertCount(1, $user->tokens);
        $this->assertSame('Token Móvil', $user->tokens->first()->name);

        // Verify the plain text token can authenticate to the API
        $plainTextToken = session('newToken')['plainTextToken'];
        $this->assertNotEmpty($plainTextToken);

        $apiResponse = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/v1/assistant/feed');

        $apiResponse->assertOk();

        $user->tokens()->delete();
        $user->delete();
    }

    public function test_token_generation_requires_name(): void
    {
        $user = $this->createTestUser();

        $response = $this->actingAs($user)->post(route('profile.tokens.store'), [
            'name' => '',
            'expiration' => 'never',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertCount(0, $user->tokens);

        $user->delete();
    }

    public function test_user_can_revoke_specific_token(): void
    {
        $user = $this->createTestUser();
        $token = $user->createToken('Token a Eliminar');
        $tokenId = (string) $token->accessToken->_id;

        $this->assertCount(1, $user->tokens);

        $response = $this->actingAs($user)->delete(route('profile.tokens.destroy', $tokenId));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Token de API revocado exitosamente.');

        $user->unsetRelation('tokens');
        $this->assertCount(0, $user->tokens()->get());

        // Clear active session authentication from actingAs
        \Illuminate\Support\Facades\Auth::forgetGuards();

        // Attempting to call API with revoked token must fail with 401
        $apiResponse = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/assistant/feed');
        $apiResponse->assertUnauthorized();

        $user->delete();
    }

    public function test_user_can_revoke_all_tokens(): void
    {
        $user = $this->createTestUser();
        $token1 = $user->createToken('Token 1');
        $token2 = $user->createToken('Token 2');
        $token3 = $user->createToken('Token 3');

        $this->assertCount(3, $user->tokens()->get());

        $response = $this->actingAs($user)->delete(route('profile.tokens.destroy-all'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Todos los tokens de API han sido revocados.');

        $this->assertCount(0, $user->tokens()->get());

        $user->delete();
    }
}
