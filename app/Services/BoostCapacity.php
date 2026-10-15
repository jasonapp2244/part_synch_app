<?php

namespace App\Services;

use App\Models\BoostedProduct;
use App\Models\BoostPosition;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Slot accounting for home-page boost positions.
 *
 * A position shows at most `display_limit` products at once, so that is its
 * capacity. Every approved, paid boost occupies one slot per product for its
 * [boost_start, boost_end) window. A new boost fits at a start date when, at
 * every moment of its window, occupied + requested <= capacity.
 */
class BoostCapacity
{
    /**
     * Occupied windows in a position that end after $from, as [start, end] pairs.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    public function occupied(BoostPosition $position, CarbonInterface $from, ?int $ignoreBoostId = null): array
    {
        return BoostedProduct::query()
            ->where('is_active', true)
            ->where('boost_end', '>', $from)
            ->whereHas('vendorBoost', function ($q) use ($position, $ignoreBoostId) {
                $q->approvedAndPaid()->where('boost_position_id', $position->id);
                if ($ignoreBoostId) {
                    $q->where('id', '!=', $ignoreBoostId);
                }
            })
            ->get(['boost_start', 'boost_end'])
            ->map(fn ($row) => [Carbon::parse($row->boost_start), Carbon::parse($row->boost_end)])
            ->all();
    }

    /**
     * Highest number of slots in use at any moment of [start, end).
     */
    public function peakUsage(array $windows, CarbonInterface $start, CarbonInterface $end): int
    {
        // Usage only rises at the window start or where another window begins.
        $checkpoints = [$start];
        foreach ($windows as [$s]) {
            if ($s->gt($start) && $s->lt($end)) {
                $checkpoints[] = $s;
            }
        }

        $peak = 0;
        foreach ($checkpoints as $t) {
            $inUse = 0;
            foreach ($windows as [$s, $e]) {
                if ($s->lte($t) && $e->gt($t)) {
                    $inUse++;
                }
            }
            $peak = max($peak, $inUse);
        }

        return $peak;
    }

    public function fits(BoostPosition $position, int $productCount, CarbonInterface $start, int $days, ?int $ignoreBoostId = null): bool
    {
        if ($productCount > $position->display_limit) {
            return false;
        }

        $end = $start->copy()->addDays($days);
        $windows = $this->occupied($position, $start, $ignoreBoostId);

        return $this->peakUsage($windows, $start, $end) + $productCount <= $position->display_limit;
    }

    /**
     * Earliest moment at or after $from when $productCount products fit for
     * $days days, or null if the position can never hold that many.
     */
    public function nextAvailableStart(BoostPosition $position, int $productCount, int $days, ?CarbonInterface $from = null, ?int $ignoreBoostId = null): ?Carbon
    {
        if ($productCount < 1 || $productCount > $position->display_limit) {
            return null;
        }

        $from = Carbon::parse($from ?? now());
        $windows = $this->occupied($position, $from, $ignoreBoostId);

        // Room can only open up at $from or when an existing window ends.
        $candidates = [$from];
        foreach ($windows as [, $e]) {
            if ($e->gt($from)) {
                $candidates[] = $e;
            }
        }
        usort($candidates, fn ($a, $b) => $a <=> $b);

        foreach ($candidates as $start) {
            $end = $start->copy()->addDays($days);
            if ($this->peakUsage($windows, $start, $end) + $productCount <= $position->display_limit) {
                return $start->copy();
            }
        }

        return null;
    }

    /**
     * Slots in use right now.
     */
    public function inUseNow(BoostPosition $position): int
    {
        $now = now();

        return $this->peakUsage($this->occupied($position, $now), $now, $now->copy()->addSecond());
    }
}
