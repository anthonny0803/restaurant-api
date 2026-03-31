<?php

namespace Tests\Feature;

use App\Filament\Resources\ReservationResource\Pages\ListReservations;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ReservationResourceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);
    }

    public function test_admin_can_see_reservations_list(): void
    {
        $admin = $this->adminUser();

        $reservation = Reservation::factory()->confirmed()->create();

        Livewire::actingAs($admin)
            ->test(ListReservations::class)
            ->assertCanSeeTableRecords([$reservation]);
    }

    public function test_no_show_action_is_visible_for_completed_reservations(): void
    {
        $admin = $this->adminUser();

        $reservation = Reservation::factory()->create([
            'status' => Reservation::STATUS_COMPLETED,
        ]);

        Livewire::actingAs($admin)
            ->test(ListReservations::class)
            ->assertTableActionVisible('no_show', $reservation);
    }

    public function test_no_show_action_is_hidden_for_non_completed_reservations(): void
    {
        $admin = $this->adminUser();

        $confirmed = Reservation::factory()->confirmed()->create();
        $pending = Reservation::factory()->pending()->create();
        $cancelled = Reservation::factory()->cancelled()->create();

        $component = Livewire::actingAs($admin)
            ->test(ListReservations::class);

        $component->assertTableActionHidden('no_show', $confirmed);
        $component->assertTableActionHidden('no_show', $pending);
        $component->assertTableActionHidden('no_show', $cancelled);
    }

    public function test_no_show_action_changes_status_to_no_show(): void
    {
        $admin = $this->adminUser();

        $reservation = Reservation::factory()->create([
            'status' => Reservation::STATUS_COMPLETED,
        ]);

        Livewire::actingAs($admin)
            ->test(ListReservations::class)
            ->callTableAction('no_show', $reservation);

        $this->assertSame(Reservation::STATUS_NO_SHOW, $reservation->fresh()->status);
    }
}
