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
        {--user= : Also cast a vote as this real user id, so the app shows the result}
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

        $voters = $this->seedVoters((int) $this->option('voters'));
        $this->castVotes($place, $voters, $period);

        // Optionally put a real account in the pool so the win is visible in
        // the app. Forced to win by clearing the competition for that period.
        $realUserId = $this->option('user') ? (int) $this->option('user') : null;
        if ($realUserId) {
            if (!User::find($realUserId)) {
                $this->error("User #{$realUserId} not found.");
                return self::FAILURE;
            }
            PlaceVote::updateOrCreate(
                ['place_id' => $place->id, 'user_id' => $realUserId, 'period' => $period],
                ['rating' => 5, 'is_flagged' => false]
            );
            $this->line("  Added real user #{$realUserId} to the pool");
        }

        $winners = $winnerService->closePeriod($period);

        if ($winners->isEmpty()) {
            $this->error('closePeriod awarded nothing — check that the votes landed.');
            return self::FAILURE;
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
            $this->line($mine
                ? "  User #{$realUserId} won: {$mine->code}  (open My Prizes in the app)"
                : "  User #{$realUserId} was NOT drawn — they may be inside the 30-day cooldown.");
            $this->newLine();
        }

        $this->components->info('Counter link (open on a phone)');
        $this->line('  ' . $place->redeem_url);
        $this->newLine();
        $this->comment('  Undo everything:  php artisan placestovisit:simulate-week --cleanup');

        return self::SUCCESS;
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
