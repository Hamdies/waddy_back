<?php

namespace Modules\PlacesToVisit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Entities\PlaceDrawEntrant;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Entities\PlaceVote;

/**
 * CLAW-Z4 — give past draws just enough of a record to be replayable.
 *
 * Periods drawn before `place_draw_entrants` existed have prizes but no
 * entrants. The losers of those weeks are **unrecoverable**: votes can be
 * flagged, removed or re-cast after a draw, so recomputing the pool today
 * would invent a machine that never existed and show people as entrants who
 * were not eligible at the time.
 *
 * So this backfills winners only, with `total_entrants` left NULL. The client
 * treats NULL as "losers unknown" and renders the pile without claiming a pool
 * size — see `SpotsDraw.displayTotal`. A replay of a backfilled week is honest
 * about being partial rather than convincingly wrong.
 */
class BackfillDrawEntrantsCommand extends Command
{
    protected $signature = 'placestovisit:backfill-draw-entrants
        {--period= : Only this ISO week (e.g. 2026-W28); defaults to every period with prizes}
        {--dry-run : Report what would be written and exit}';

    protected $description = 'Backfill draw entrants (winners only) for periods drawn before the claw replay existed';

    /**
     * The one period format this system has: ISO year + week, `o-\WW`.
     *
     * Matches the route constraint on `places/draw/{period?}` deliberately. A
     * period this rejects is one the endpoint cannot serve, so backfilling it
     * would write rows that are unreachable by definition.
     */
    protected const PERIOD_PATTERN = '/^\d{4}-W\d{1,2}$/';

    public function handle(): int
    {
        $periods = PlacePrize::query()
            ->when($this->option('period'), fn($q, $p) => $q->where('period', $p))
            ->distinct()
            ->pluck('period');

        if ($periods->isEmpty()) {
            $this->info('No periods with prizes. Nothing to backfill.');
            return self::SUCCESS;
        }

        // `place_prizes.period` is an unconstrained varchar(10), and production
        // contains at least one value that is not an ISO week ("9", 5 prizes).
        // Those rows are real prizes against a period the endpoint cannot
        // address, so this refuses to extend the problem into a second table
        // and names them instead of skipping quietly.
        [$valid, $malformed] = $periods->partition(
            fn($p) => preg_match(self::PERIOD_PATTERN, (string) $p) === 1
        );

        if ($malformed->isNotEmpty()) {
            $this->newLine();
            $this->error('Skipping ' . $malformed->count() . ' period(s) that are not ISO weeks:');
            foreach ($malformed as $bad) {
                $count = PlacePrize::where('period', $bad)->count();
                $this->line("  \"{$bad}\" — {$count} prize(s), unreachable at GET places/draw/{$bad}");
            }
            $this->warn('These need their period corrected before they can be replayed.');
            $this->newLine();
        }

        if ($valid->isEmpty()) {
            $this->info('No well-formed periods to backfill.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $written = 0;
        $skipped = 0;

        foreach ($valid as $period) {
            // Never touch a period that already has entrants: it either drew
            // after CLAW-Z1 and is complete, or was backfilled already.
            if (PlaceDrawEntrant::forPeriod($period)->exists()) {
                $skipped++;
                continue;
            }

            $prizes = PlacePrize::where('period', $period)
                ->orderBy('id')
                ->get();

            if ($prizes->isEmpty()) {
                continue;
            }

            // Vote counts that week across all venues — the same number the
            // leaderboard means by a voter's score, and what the winner row
            // shows. Unlike the pool itself, this is still derivable.
            $votes = PlaceVote::query()
                ->selectRaw('user_id, COUNT(*) as votes')
                ->where('period', $period)
                ->where('is_flagged', false)
                ->whereIn('user_id', $prizes->pluck('user_id'))
                ->groupBy('user_id')
                ->pluck('votes', 'user_id');

            $now = now();
            $rank = 0;
            $rows = $prizes->map(function (PlacePrize $prize) use ($votes, $now, &$rank) {
                $rank++;
                return [
                    'period' => $prize->period,
                    'place_winner_id' => $prize->place_winner_id,
                    'place_id' => $prize->place_id,
                    'user_id' => $prize->user_id,
                    'votes' => (int) ($votes[$prize->user_id] ?? 1),
                    // Prize id order is the only pull order still available —
                    // the real one was never recorded. It is a plausible
                    // sequence, not the historical one.
                    'rank' => $rank,
                    // NULL, deliberately: the true pool size is unknowable and
                    // a guess here would be stated to users as fact.
                    'total_entrants' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            $this->line("{$period}: " . count($rows) . ' winner(s), losers unknown');

            if (!$dryRun) {
                DB::transaction(fn() => PlaceDrawEntrant::insert($rows));
            }

            $written += count($rows);
        }

        $verb = $dryRun ? 'Would write' : 'Wrote';
        $this->info("{$verb} {$written} entrant row(s). Skipped {$skipped} period(s) that already had entrants.");

        return self::SUCCESS;
    }
}
