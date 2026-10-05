<?php

namespace Modules\PlacesToVisit\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceDrawEntrant;
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
        {--user= : A real user (id, phone or email) to see the result as, in the app}
        {--outcome=won : What --user sees: won (picked by the claw), lost (voted for the winning venue, not picked) or onlooker (did not vote for it)}
        {--cleanup : Delete everything a previous simulation created, then stop}';

    protected $description = '[dev] Simulate a full Spots week: votes → winner → prize draw → printable codes';

    /** Reserved so cleanup can never touch a real account */
    private const TEST_EMAIL_DOMAIN = '@spots-sim.invalid';

    /** Human-looking names for seeded voters, so tables read like real people instead of "Sim Voter3" */
    private const TEST_FIRST_NAMES = ['Layla', 'Omar', 'Yasmin', 'Karim', 'Nour', 'Amir', 'Salma', 'Youssef', 'Farida', 'Hassan', 'Mariam', 'Adam'];
    private const TEST_LAST_NAMES = ['Ibrahim', 'Fahmy', 'Saeed', 'Nasser', 'Aziz', 'Rashad', 'Tawfik', 'Mansour', 'Gaber', 'Khalil', 'Sabry', 'Zaki'];

    public function handle(WinnerService $winnerService): int
    {
        if (app()->environment('production') && !$this->confirmProduction()) {
            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $outcome = (string) $this->option('outcome');
        if (!in_array($outcome, ['won', 'lost', 'onlooker'], true)) {
            $this->error("--outcome must be won, lost or onlooker (got \"{$outcome}\").");
            return self::FAILURE;
        }

        $period = $this->option('period') ?: RaceClock::lastClosedPeriod();

        // `--period` was passed straight through unvalidated, and `period` is
        // an unconstrained varchar(10) in every table it reaches. Production
        // consequently holds votes, winners and prizes under "9", "2026-07"
        // and half a dozen other bare integers — a hand-typed week number
        // creating a whole draw in a namespace nothing can address, since
        // `GET places/draw/{period?}` only matches ISO weeks.
        //
        // One malformed run costs a manual cleanup across three tables, so it
        // is refused here rather than explained later.
        if (!preg_match('/^\d{4}-W\d{1,2}$/', $period)) {
            $this->error("\"{$period}\" is not an ISO week.");
            $this->line('  Expected `YYYY-Www`, e.g. ' . RaceClock::lastClosedPeriod() . '.');
            $this->line('  A bare week number creates votes and prizes that no endpoint can serve.');
            return self::FAILURE;
        }

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

        $realUserId = $realUser?->id;

        $winners = $winnerService->closePeriod($period);

        if ($winners->isEmpty()) {
            $this->error('closePeriod awarded nothing — check that the votes landed.');
            return self::FAILURE;
        }

        // The real tester is placed AFTER the draw, not voted in before it.
        // The draw is random, so voting them in first would pick them one time
        // in five and push them a "you won" they were not meant to see, and a
        // reassigned prize left the claw's record naming someone else. Placing
        // them afterwards makes each outcome deterministic and keeps the prize,
        // the claw record and the pushes telling the same story.
        $overall = $winners->firstWhere('zone_id', null);
        if ($realUser && $overall) {
            $this->placeRealUser($realUser, $outcome, $overall, $place, $period);
        }

        $prizes = PlacePrize::with('user')->where('period', $period)->get();

        $this->newLine();
        $this->components->info('Winner');
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
            $label = ['won' => 'WON the claw', 'lost' => 'was in the machine and LOST', 'onlooker' => 'did NOT vote for the winner'][$outcome];
            $this->line("  User #{$realUserId} {$label}" . ($mine ? ": {$mine->code}   ← open My Prizes in the app" : ''));
            $this->line("  Open in the app: Spots home card, or /spots/draw?period={$period}");
            $this->newLine();
        }

        $this->components->info('Counter link (open on a phone)');
        $this->line('  ' . $place->redeem_url);
        $this->newLine();
        $this->comment('  Undo everything:  php artisan placestovisit:simulate-week --cleanup');

        return self::SUCCESS;
    }

    /**
     * Put the real tester into the finished draw as [$outcome], writing every
     * row the app reads (prize, claw record) and sending the matching push.
     *
     *  - won      takes a drawn winner's slot (prize + pull order) and gets the
     *             win push.
     *  - lost     joins the machine as a never-pulled entrant and gets the
     *             "the claw has picked" push.
     *  - onlooker is not in the draw at all and gets nothing.
     */
    protected function placeRealUser(User $user, string $outcome, $overall, Place $place, string $period): void
    {
        if ($outcome === 'onlooker') {
            $this->line("  User #{$user->id} left out of the draw (onlooker) — no push");
            return;
        }

        $entrants = PlaceDrawEntrant::forPeriod($period);
        $total = (clone $entrants)->value('total_entrants');

        // The machine is one bigger now. Every row carries the real pool size.
        if ($total !== null) {
            (clone $entrants)->increment('total_entrants');
            $total++;
        }

        $mine = PlaceDrawEntrant::updateOrCreate(
            ['period' => $period, 'user_id' => $user->id],
            [
                'place_winner_id' => $overall->id,
                'place_id' => $place->id,
                'votes' => 1,
                'rank' => 0,
                'total_entrants' => $total,
            ]
        );

        if ($outcome === 'lost') {
            $winnerIds = PlacePrize::where('period', $period)->pluck('user_id')->map(fn($id) => (int) $id);
            $sent = app(PrizeDrawService::class)->notifyEntrants(
                $overall,
                collect([['user_id' => $user->id]]),
                $winnerIds,
                $place
            );
            $this->line($sent > 0
                ? '  "Claw has picked" push QUEUED (delivery is not confirmed — it goes through the queue worker and FCM)'
                : '  No push — that account has no cm_firebase_token yet');
            return;
        }

        // won: take over the last drawn winner's slot, prize and pull order.
        $donor = PlacePrize::where('period', $period)->where('user_id', '!=', $user->id)->orderByDesc('id')->first();
        if (!$donor) {
            $this->warn('  No drawn winner to take a slot from — nothing to reassign.');
            return;
        }

        $donorEntrant = PlaceDrawEntrant::forPeriod($period)->where('user_id', $donor->user_id)->first();
        if ($donorEntrant) {
            $mine->update(['rank' => $donorEntrant->rank]);
            $donorEntrant->update(['rank' => 0]);
        }

        $donor->update(['user_id' => $user->id]);
        app(\Modules\PlacesToVisit\Services\LeaderboardService::class)->clearRecentWinnersCache();

        $pushed = app(PrizeDrawService::class)->notifyWinner($donor->fresh('place'));
        $this->line($pushed
            ? '  Win push QUEUED (delivery is not confirmed — it goes through the queue worker and FCM)'
            : '  No win push — that account has no cm_firebase_token yet');
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
            $fName = self::TEST_FIRST_NAMES[($i - 1) % count(self::TEST_FIRST_NAMES)];
            $lName = self::TEST_LAST_NAMES[($i - 1) % count(self::TEST_LAST_NAMES)];

            $voters->push(User::firstOrCreate(
                ['email' => "spots-sim-{$i}" . self::TEST_EMAIL_DOMAIN],
                [
                    'f_name' => $fName,
                    'l_name' => $lName,
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
            // The claw's record of who was in the machine. Leaving these behind
            // made a re-run of the same week fail on the (period, user_id)
            // unique key, and orphaned rows for deleted users.
            PlaceDrawEntrant::whereIn('period', $periods)->delete();
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
