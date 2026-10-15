<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boosts are requested in the app and paid outside it (invoice, payment link,
 * bank transfer, cash), so an admin has to approve each one. Card checkout in
 * the app is gone: Apple 3.1.1 and Google Play billing reject it for a
 * digital promotion.
 *
 * approval_status drives the workflow; payment_status keeps recording money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_boosts', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')->after('boost_position_id');
            $table->text('vendor_note')->nullable()->after('metadata');
            $table->text('admin_note')->nullable()->after('vendor_note');
            $table->string('rejection_reason', 500)->nullable()->after('admin_note');
            $table->string('payment_reference')->nullable()->after('transaction_id');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
            $table->foreignId('approved_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('live_notified_at')->nullable()->after('approved_at');
            $table->timestamp('ending_notified_at')->nullable()->after('live_notified_at');

            $table->index(['approval_status', 'boost_position_id']);
        });

        // Rows from the old card flow: a paid one was effectively approved,
        // an unpaid one was an abandoned checkout.
        DB::table('vendor_boosts')->where('payment_status', 'success')
            ->update(['approval_status' => 'approved', 'live_notified_at' => now(), 'ending_notified_at' => now()]);
        DB::table('vendor_boosts')->where('payment_status', '!=', 'success')
            ->update(['approval_status' => 'cancelled', 'is_active' => false]);

        // Nothing in the app renders the Category Section, so vendors must not
        // be able to request (and pay for) a slot nobody sees. The admin can
        // switch it back on if the app ever shows it.
        DB::table('boost_positions')->where('name', 'Category Section')->update(['status' => false]);
    }

    public function down(): void
    {
        Schema::table('vendor_boosts', function (Blueprint $table) {
            $table->dropIndex(['approval_status', 'boost_position_id']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'approval_status', 'vendor_note', 'admin_note', 'rejection_reason',
                'payment_reference', 'paid_at', 'approved_at', 'live_notified_at', 'ending_notified_at',
            ]);
        });
    }
};
