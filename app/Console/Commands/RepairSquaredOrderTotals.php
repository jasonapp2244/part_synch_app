<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * BCR-14 repair for order lines written before the fix.
 *
 * Checkout used to store `price` = the line total and `total_price` = the
 * line total times the quantity again. Not every old row is wrong, though:
 * buy-now stored the unit price, so its rows were correct all along. A row
 * is only repaired when it can be proven squared: quantity above 1, its
 * `price` equals the product's current price times the quantity, and its
 * `total_price` equals `price` times the quantity. Anything else (including a
 * product whose price has since changed) is listed and left alone.
 *
 * Dry run unless --apply is given.
 */
class RepairSquaredOrderTotals extends Command
{
    protected $signature = 'orders:repair-squared-totals
                            {--apply : Write the corrections (otherwise only report them)}';

    protected $description = 'Find order lines stored with squared totals (BCR-14) and optionally correct them';

    public function handle(): int
    {
        $rows = DB::table('order_items as oi')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.quantity', '>', 1)
            ->get(['oi.id', 'oi.order_id', 'oi.quantity', 'oi.price', 'oi.total_price', 'p.price as product_price']);

        $fix = [];
        $unsure = [];

        foreach ($rows as $row) {
            $qty = (int) $row->quantity;
            $price = (float) $row->price;
            $total = (float) $row->total_price;
            $squared = abs($total - $price * $qty) < 0.01;

            if (! $squared) {
                continue; // already a unit/line pair, or something else entirely
            }

            if ($row->product_price !== null && abs($price - (float) $row->product_price * $qty) < 0.01) {
                $fix[] = [$row->id, $row->order_id, $qty, $price, $total, round($price / $qty, 2), $price];
            } elseif ($row->product_price === null || abs($price - (float) $row->product_price) >= 0.01) {
                $unsure[] = [$row->id, $row->order_id, $qty, $price, $total, $row->product_price ?? 'deleted'];
            }
        }

        $this->info(count($fix) . ' line(s) provably squared.');
        if ($fix) {
            $this->table(['item', 'order', 'qty', 'price', 'total', 'new price', 'new total'], $fix);
        }

        if ($unsure) {
            $this->warn(count($unsure) . ' line(s) look squared but the product price no longer proves it; left alone:');
            $this->table(['item', 'order', 'qty', 'price', 'total', 'product price now'], $unsure);
        }

        if (! $this->option('apply')) {
            $this->line('Dry run. Re-run with --apply to write the corrections.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($fix) {
            foreach ($fix as [$id, , , , , $newPrice, $newTotal]) {
                DB::table('order_items')->where('id', $id)->update(['price' => $newPrice, 'total_price' => $newTotal]);
            }
        });

        $this->info('Corrected ' . count($fix) . ' line(s).');

        return self::SUCCESS;
    }
}
