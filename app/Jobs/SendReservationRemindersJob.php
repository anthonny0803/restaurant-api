<?php

namespace App\Jobs;

use App\Notifications\ReservationReminderNotification;
use App\Repositories\ReservationRepository;
use App\Repositories\RestaurantSettingRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendReservationRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        RestaurantSettingRepository $settingRepository,
        ReservationRepository $reservationRepository,
    ): void {
        $reminderHours = $settingRepository->get()->reminder_hours_before;
        $reservations = $reservationRepository->findDueForReminder($reminderHours);

        foreach ($reservations as $reservation) {
            if (! $reservation->user) {
                continue;
            }

            $reservation->user->notify(new ReservationReminderNotification($reservation));
            $reservationRepository->markReminderSent($reservation);
        }
    }
}
