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
        // XS-2: next dispatch wave for orders still without a rider.
        $schedule->call(fn () => \App\Services\DispatchService::tick())->everyMinute()->name('orders:dispatch-waves')->withoutOverlapping();

        // XS-3: ring stores again / re-offer to riders / alert admin for stuck orders.
        $schedule->call(fn () => \App\Services\OrderRescueService::run())->everyMinute()->name('orders:rescue')->withoutOverlapping();

        // SM-7: end-of-day summary push to each store that had orders.
        $schedule->command('stores:daily-summary')->dailyAt('23:30')->timezone(config('placestovisit.timezone', 'Africa/Cairo'));

        // Busy mode (SM-4): reopen paused stores, drop expired extra minutes.
        $schedule->call(fn () => \App\Services\StoreBusyService::endExpired())->everyMinute()->name('stores:end-busy')->withoutOverlapping();

        // noon (NP-07/16): settle payments noon hasn't answered for, close
        // expired wallet-or-card choices to the card, send / confirm card refunds.
        $schedule->call(fn () => \App\Services\NoonPaymentService::reconcilePayments())->everyFiveMinutes()->name('payments:noon-reconcile')->withoutOverlapping();
        $schedule->call(fn () => \App\Services\NoonRefundService::processDue())->everyMinute()->name('payments:noon-refunds')->withoutOverlapping();

        // Menu: show items that were out of stock for a while (2 hours / rest of today).
        $schedule->call(fn () => \App\Services\ItemAvailabilityService::reopenDue())->everyMinute()->name('items:reopen')->withoutOverlapping();

        // Send push notifications for prizes expiring within 24 hours
        $schedule->command('xp:prize-expiry-reminders')->dailyAt('10:00');

        // Nudge users about in-progress challenges expiring within a few hours
        $schedule->command('xp:challenge-expiry-reminders')->hourly();

        // Expire stale active challenges
        $schedule->call(fn () => \App\Services\ChallengeService::expireOldChallenges())->hourly();

        // Crown last week's place winners right after the week locks.
        // Race runs on Cairo time while app.timezone stays UTC —
        // see Modules\PlacesToVisit\Services\RaceClock.
        // (WinnerService also lazy-closes on read if this ever misses.)
        $raceTz = config('placestovisit.timezone', 'Africa/Cairo');
        $schedule->command('placestovisit:close-week')
            ->weeklyOn(5, '00:10')->timezone($raceTz);

        // Thursday-evening nudge when the weekly spot race is close (locks Friday 00:00)
        $schedule->command('placestovisit:final-hours-push')
            ->weeklyOn(4, '21:00')->timezone($raceTz);

        // Voter-prize vouchers: auto-expire after their 7-day window and
        // nudge winners in the last 24h. No reissue, no rollover.
        $schedule->command('placestovisit:expire-prizes')->hourly();

        // Pet lifecycle pushes (PetPushService holds the guardrails: per-pet
        // off switch, one push per event, 7-day cap on automatic ones).
        // Morning for the happy ones, early evening for "food running low",
        // when people are home to reorder. Reminders run hourly so a
        // reminder set at 3pm fires at 3pm.
        $schedule->command('pets:pushes birthdays')->dailyAt('10:00')->timezone($raceTz);
        $schedule->command('pets:pushes life-stage')->dailyAt('10:05')->timezone($raceTz);
        $schedule->command('pets:pushes replenish')->dailyAt('18:00')->timezone($raceTz);
        $schedule->command('pets:pushes reminders')->hourly();
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
