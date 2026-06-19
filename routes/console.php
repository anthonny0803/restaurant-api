<?php

use App\Jobs\AutoCompleteReservationsJob;
use App\Jobs\ResetDailyStockJob;
use App\Jobs\SendReservationRemindersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendReservationRemindersJob())->everyThirtyMinutes();
Schedule::job(new AutoCompleteReservationsJob())->everyFifteenMinutes();
Schedule::job(new ResetDailyStockJob())->dailyAt('04:00');
