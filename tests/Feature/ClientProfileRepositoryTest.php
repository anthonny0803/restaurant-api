<?php

namespace Tests\Feature;

use App\Models\ClientProfile;
use App\Repositories\ClientProfileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ClientProfileRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    private ClientProfileRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->repository = app(ClientProfileRepository::class);
    }

    public function test_create_persists_profile_linked_to_user(): void
    {
        $user = $this->clientUser();

        $profile = $this->repository->create($user, ['phone' => '612345678']);

        $this->assertInstanceOf(ClientProfile::class, $profile);
        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame('612345678', $profile->phone);
        $this->assertDatabaseHas('client_profiles', [
            'user_id' => $user->id,
            'phone'   => '612345678',
        ]);
    }

    public function test_update_changes_phone_and_returns_fresh_profile(): void
    {
        $user = $this->clientUser();
        $profile = $this->repository->create($user, ['phone' => '612345678']);

        $updated = $this->repository->update($profile, ['phone' => '698765432']);

        $this->assertSame('698765432', $updated->phone);
        $this->assertDatabaseHas('client_profiles', [
            'user_id' => $user->id,
            'phone'   => '698765432',
        ]);
    }
}
