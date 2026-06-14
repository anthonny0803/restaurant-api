<?php

namespace App\Jobs;

use App\Notifications\ReservationReminderNotification;
use App\Repositories\ReservationRepository;
use App\Repositories\RestaurantSettingRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReservationRemindersJob implements ShouldQueue
{
    use Queueable;

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
