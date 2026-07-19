<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Pharmacy\Models\Dispense;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DispenseApiTest extends TestCase
{
    use DatabaseTransactions;

    private Organization $organization;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('ViewAny Dispense', 'web');
        Permission::findOrCreate('View Dispense', 'web');
        Permission::findOrCreate('Update Dispense', 'web');

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'display_name' => 'Test Org',
            'slug' => 'test-org-dispense-api',
            'is_active' => true,
        ]);

        $this->branchA = Branch::create([
            'organization_id' => $this->organization->id,
            'name' => 'Branch A',
            'display_name' => 'Branch A',
            'code' => 'BR-DA',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'organization_id' => $this->organization->id,
            'name' => 'Branch B',
            'display_name' => 'Branch B',
            'code' => 'BR-DB',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/dispenses')->assertUnauthorized();
    }

    public function test_branch_scope_prevents_cross_branch_dispense_access(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo('View Dispense');

        Context::add('current_branch_id', $this->branchB->id);
        $dispenseInBranchB = Dispense::factory()->create(['branch_id' => $this->branchB->id]);
        Context::forget('current_branch_id');

        $response = $this->actingAs($user)->getJson("/api/v1/dispenses/{$dispenseInBranchB->id}");

        $response->assertNotFound();
    }

    public function test_show_includes_branch_id(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo('View Dispense');

        Context::add('current_branch_id', $this->branchA->id);
        $dispense = Dispense::factory()->create(['branch_id' => $this->branchA->id]);
        Context::forget('current_branch_id');

        $response = $this->actingAs($user)->getJson("/api/v1/dispenses/{$dispense->id}");

        $response->assertSuccessful();
        $response->assertJsonPath('data.id', $dispense->id);
        $response->assertJsonPath('data.branch_id', $this->branchA->id);
    }

    public function test_branch_id_can_be_updated_via_api(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branchA->id]);
        $user->givePermissionTo(['View Dispense', 'Update Dispense']);

        Context::add('current_branch_id', $this->branchA->id);
        $dispense = Dispense::factory()->create(['branch_id' => $this->branchA->id]);
        Context::forget('current_branch_id');

        $response = $this->actingAs($user)->putJson("/api/v1/dispenses/{$dispense->id}", [
            'branch_id' => $this->branchB->id,
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.branch_id', $this->branchB->id);

        $this->assertDatabaseHas('dispenses', [
            'id' => $dispense->id,
            'branch_id' => $this->branchB->id,
        ]);
    }
}
