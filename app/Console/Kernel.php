<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Send push notifications for prizes expiring within 24 hours
        $schedule->command('xp:prize-expiry-reminders')->dailyAt('10:00');

        // Expire stale active challenges
        $schedule->call(fn () => \App\Services\ChallengeService::expireOldChallenges())->hourly();

        // Crown last week's place winners right after the week locks.
        // Race runs on Cairo time while app.timezone stays UTC —
        // see Modules\PlacesToVisit\Services\RaceClock.
        // (WinnerService also lazy-closes on read if this ever misses.)
        $raceTz = config('placestovisit.timezone', 'Africa/Cairo');
        $schedule->command('placestovisit:close-week')
            ->weeklyOn(1, '00:10')->timezone($raceTz);

        // Sunday-evening nudge when the weekly spot race is close (locks midnight)
        $schedule->command('placestovisit:final-hours-push')
            ->weeklyOn(0, '21:00')->timezone($raceTz);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
