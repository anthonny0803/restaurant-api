<?php

use App\Jobs\SendReservationRemindersJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SendReservationRemindersJob())->everyThirtyMinutes();
