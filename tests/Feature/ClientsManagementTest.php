<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\RoleModel;
use App\Models\User;
use App\Services\Tenancy\PermissionCacheService;
use Tests\TestCase;

class ClientsManagementTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SystemMasterSeeder::class);

        $this->user = User::where('email', 'johan_palma45@hotmail.com')->first();
        app(PermissionCacheService::class)->invalidateAll();
    }

    public function test_can_create_a_new_client(): void
    {
        $response = $this->actingAs($this->user)->post(route('clients.store'), [
            'name' => 'Corporativo Prueba Nuevas Cuentas',
            'document_number' => 'CPN-123456',
            'billing_email' => 'admin@nuevascuentas.com',
            'phone' => '+52 998 111 2233',
            'plan' => 'enterprise',
            'max_companies' => 8,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseHas('clients', [
            'name' => 'Corporativo Prueba Nuevas Cuentas',
            'document_number' => 'CPN-123456',
            'plan' => 'enterprise',
        ], 'mongodb');
    }

    public function test_can_update_an_existing_client(): void
    {
        $client = Client::create([
            'name' => 'Cliente Original',
            'plan' => 'basic',
            'max_companies' => 1,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->patch(route('clients.update', (string) $client->_id), [
            'name' => 'Cliente Modificado',
            'plan' => 'pro',
            'max_companies' => 5,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'success');

        $client->refresh();
        $this->assertEquals('Cliente Modificado', $client->name);
        $this->assertEquals('pro', $client->plan);
        $this->assertEquals(5, $client->max_companies);
    }

    public function test_cannot_delete_client_with_attached_companies(): void
    {
        $client = Client::first();
        $companyCount = $client->companies()->count();
        $this->assertGreaterThan(0, $companyCount);

        $response = $this->actingAs($this->user)->delete(route('clients.destroy', (string) $client->_id));

        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'error');

        $this->assertNotNull(Client::find((string) $client->_id));
    }

    public function test_can_delete_client_without_companies(): void
    {
        $client = Client::create([
            'name' => 'Cliente Vacio Para Eliminar',
            'plan' => 'basic',
            'status' => 'active',
        ]);

        $clientId = (string) $client->_id;

        $response = $this->actingAs($this->user)->delete(route('clients.destroy', $clientId));

        $response->assertRedirect();
        $response->assertSessionHas('flash.type', 'success');

        $this->assertNull(Client::find($clientId));
    }
}

