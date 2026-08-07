<?php

namespace Modules\PlacesToVisit\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Entities\PlaceWinner;
use Modules\PlacesToVisit\Services\PrizeDrawService;
use Modules\PlacesToVisit\Services\RaceClock;
use Modules\PlacesToVisit\Services\WinnerService;

/**
 * Dev-only: play a whole Spots week in one command.
 *
 * Stuffs votes into a finished period, closes it, and prints the crowned
 * venue, the voucher codes, and the counter link — so the redemption flow can
 * be walked through without waiting a real week for the cron to fire.
 *
 * Test users are tagged with a reserved email domain so --cleanup can find
 * and remove exactly what this command created and nothing else.
 */
class SimulateWeekCommand extends Command
{
    protected $signature = 'placestovisit:simulate-week
        {--place= : Place id to crown (defaults to the first active place)}
        {--voters=12 : How many test voters to put in the pool}
        {--period= : ISO week to play (defaults to last week)}
        {--user= : Also vote as a real user (id, phone or email) so the win shows in the app}
        {--cleanup : Delete everything a previous simulation created, then stop}';

    protected $description = '[dev] Simulate a full Spots week: votes → winner → prize draw → printable codes';

    /** Reserved so cleanup can never touch a real account */
    private const TEST_EMAIL_DOMAIN = '@spots-sim.invalid';

    public function handle(WinnerService $winnerService): int
    {
        if (app()->environment('production') && !$this->confirmProduction()) {
            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $period = $this->option('period') ?: RaceClock::lastClosedPeriod();

        if ($period === RaceClock::period()) {
            $this->error("{$period} is the running week — it can't be closed. Pick a finished one.");
            return self::FAILURE;
        }

        $place = $this->resolvePlace();
        if (!$place) {
            $this->error('No active place found. Create one in admin first.');
            return self::FAILURE;
        }

        $this->components->info("Simulating {$period} — crowning \"{$place->title}\" (#{$place->id})");

        // A previous run of this same week would make the draw a no-op
        if (PlaceWinner::where('period', $period)->exists()) {
            $this->warn("{$period} is already closed. Run with --cleanup first, or pass a different --period.");
            return self::FAILURE;
        }

        // Resolve the real account BEFORE anything is written — a bad --user
        // must not leave seeded voters and votes behind.
        $realUser = null;
        if ($this->option('user')) {
            $realUser = $this->resolveUser($this->option('user'));
            if (!$realUser) {
                $this->error("No user matched \"{$this->option('user')}\".");
                $this->newLine();
                $this->line('  Pass an id, phone or email. To find yours:');
                $this->line('    php artisan tinker --execute="echo App\Models\User::where(\'phone\',\'like\',\'%1234%\')->get([\'id\',\'f_name\',\'phone\',\'email\'])"');
                return self::FAILURE;
            }
        }

        $voters = $this->seedVoters((int) $this->option('voters'));
        $this->castVotes($place, $voters, $period);

        if ($realUser) {
            PlaceVote::updateOrCreate(
                ['place_id' => $place->id, 'user_id' => $realUser->id, 'period' => $period],
                ['rating' => 5, 'is_flagged' => false]
            );
            $name = trim("{$realUser->f_name} {$realUser->l_name}") ?: "#{$realUser->id}";
            $this->line("  Added real user {$name} (#{$realUser->id}) to the pool");
        }
        $realUserId = $realUser?->id;

        $winners = $winnerService->closePeriod($period);

        if ($winners->isEmpty()) {
            $this->error('closePeriod awarded nothing — check that the votes landed.');
            return self::FAILURE;
        }

        // The draw is genuinely random, so a real tester usually isn't picked.
        // The point of --user is to see the win in the app, so hand them a
        // slot after the fact rather than making them re-roll the command.
        $forcedWin = false;
        if ($realUserId && !PlacePrize::where('period', $period)->where('user_id', $realUserId)->exists()) {
            $donor = PlacePrize::where('period', $period)
                ->whereNotIn('user_id', [$realUserId])
                ->orderByDesc('id')
                ->first();

            if ($donor) {
                $donor->update(['user_id' => $realUserId]);
                app(\Modules\PlacesToVisit\Services\LeaderboardService::class)->clearRecentWinnersCache();
                $forcedWin = true;

                // The draw already pushed to whoever originally held this
                // slot, so the real tester has to be notified explicitly —
                // otherwise --user produces a prize with no notification.
                $pushed = app(PrizeDrawService::class)->notifyWinner($donor->fresh('place'));
                $this->line($pushed
                    ? '  Win push sent'
                    : '  No win push — that account has no cm_firebase_token yet');
            }
        }

        $prizes = PlacePrize::with('user')->where('period', $period)->get();

        $this->newLine();
        $this->components->info('Winner');
        $overall = $winners->firstWhere('zone_id', null);
        $this->line("  Venue      {$place->title} (#{$place->id})");
        $this->line("  Votes      " . ($overall?->votes_count ?? 0));
        $this->line("  Zone wins  " . $winners->where('zone_id', '!=', null)->count() . ' (no prize draw — overall only)');

        $this->newLine();
        $this->components->info("Voucher codes ({$prizes->count()})");
        $this->table(
            ['Code', 'Winner', 'User id', 'Expires'],
            $prizes->map(fn(PlacePrize $p) => [
                $p->code,
                trim(($p->user->f_name ?? '?') . ' ' . ($p->user->l_name ?? '')),
                $p->user_id,
                $p->expires_at?->format('d M Y H:i'),
            ])->all()
        );

        if ($realUserId) {
            $mine = $prizes->firstWhere('user_id', $realUserId);
            if ($mine) {
                $this->line("  User #{$realUserId} won: {$mine->code}   ← open My Prizes in the app");
                if ($forcedWin) {
                    $this->comment('  (slot reassigned to them — the real draw is random and did not pick them)');
                }
            } else {
                $this->warn("  User #{$realUserId} has no prize — they are inside the 30-day repeat-winner cooldown.");
            }
            $this->newLine();
        }

        $this->components->info('Counter link (open on a phone)');
        $this->line('  ' . $place->redeem_url);
        $this->newLine();
        $this->comment('  Undo everything:  php artisan placestovisit:simulate-week --cleanup');

        return self::SUCCESS;
    }

    /**
     * Accept whatever the operator actually has to hand — the numeric id is
     * the least memorable of the three.
     */
    protected function resolveUser(string $needle): ?User
    {
        $needle = trim($needle);

        if (ctype_digit($needle)) {
            if ($user = User::find((int) $needle)) {
                return $user;
            }
        }

        if (str_contains($needle, '@')) {
            return User::where('email', $needle)->first();
        }

        // Phones are stored inconsistently (+20…, 0020…, 01…), so match on
        // the trailing digits rather than demanding an exact format.
        $digits = preg_replace('/\D/', '', $needle);
        if ($digits === '') {
            return null;
        }

        return User::where('phone', 'like', '%' . substr($digits, -9))->first();
    }

    protected function resolvePlace(): ?Place
    {
        $place = $this->option('place')
            ? Place::find((int) $this->option('place'))
            : Place::active()->orderBy('id')->first();

        // Places created before the redeem-token migration, or by a seeder
        // that predates it, still need a link to hand the cashier.
        if ($place && !$place->getRawOriginal('redeem_token')) {
            $place->update(['redeem_token' => Str::random(32)]);
        }

        return $place;
    }

    /**
     * @return \Illuminate\Support\Collection<User>
     */
    protected function seedVoters(int $count): \Illuminate\Support\Collection
    {
        $voters = collect();

        for ($i = 1; $i <= $count; $i++) {
            $voters->push(User::firstOrCreate(
                ['email' => "spots-sim-{$i}" . self::TEST_EMAIL_DOMAIN],
                [
                    'f_name' => 'Sim',
                    'l_name' => "Voter{$i}",
                    'phone' => '+2010000' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'password' => bcrypt(Str::random(32)),
                    'is_phone_verified' => 1,
                    'status' => 1,
                ]
            ));
        }

        $this->line("  Seeded {$voters->count()} test voters");

        return $voters;
    }

    protected function castVotes(Place $place, $voters, string $period): void
    {
        foreach ($voters as $voter) {
            PlaceVote::updateOrCreate(
                ['place_id' => $place->id, 'user_id' => $voter->id, 'period' => $period],
                ['rating' => random_int(4, 5), 'is_flagged' => false]
            );
        }

        $this->line("  Cast {$voters->count()} votes for period {$period}");
    }

    protected function cleanup(): int
    {
        $userIds = User::where('email', 'like', '%' . self::TEST_EMAIL_DOMAIN)->pluck('id');

        if ($userIds->isEmpty()) {
            $this->info('Nothing to clean up.');
            return self::SUCCESS;
        }

        // Periods this simulation touched — safe to unwind winners for those
        $periods = PlaceVote::whereIn('user_id', $userIds)->distinct()->pluck('period');

        DB::transaction(function () use ($userIds, $periods) {
            PlacePrize::whereIn('period', $periods)->delete();
            PlaceWinner::whereIn('period', $periods)->delete();
            PlaceVote::whereIn('user_id', $userIds)->delete();
            User::whereIn('id', $userIds)->delete();
        });

        app(\Modules\PlacesToVisit\Services\LeaderboardService::class)->clearCache();
        app(\Modules\PlacesToVisit\Services\LeaderboardService::class)->clearRecentWinnersCache();

        $this->components->info('Cleaned up');
        $this->line("  Removed {$userIds->count()} test voters");
        $this->line('  Removed winners + prizes for: ' . $periods->implode(', '));
        $this->newLine();
        $this->warn('  Votes real users cast in those periods were left alone,');
        $this->warn('  but their weekly winner rows were removed — rerun close-week if needed.');

        return self::SUCCESS;
    }

    protected function confirmProduction(): bool
    {
        return $this->confirm('This is PRODUCTION. Really create fake voters and prizes?', false);
    }
}
