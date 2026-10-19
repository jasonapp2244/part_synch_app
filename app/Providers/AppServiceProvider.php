<?php

namespace App\Providers;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Models\VendorBoost;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap any application services.
     */

     public function boot(): void
    {
        $this->configureRateLimiting();
        $this->shareModerationCounts();
    }

    /**
     * Pending-moderation badge counts for the admin sidebar.
     *
     * Guarded by a Schema check and a try/catch so that a machine which has
     * not run the new migrations yet — or has no database configured, as when
     * running `artisan` offline — still renders the panel instead of throwing
     * on every page.
     */
    protected function shareModerationCounts(): void
    {
        View::composer('layouts.partials.sidebar', function ($view) {
            $counts = ['pendingReportCount' => 0, 'pendingReviewCount' => 0, 'pendingBoostCount' => 0, 'pendingVendorCount' => 0];

            try {
                $counts['pendingVendorCount'] = User::where('role_id', 2)->where('business_status', '!=', 'active')->count();

                if (Schema::hasTable('reports')) {
                    $counts['pendingReportCount'] = Report::where('status', 'pending')->count();
                }

                if (Schema::hasTable('reviews')) {
                    $counts['pendingReviewCount'] = Review::where('status', 'pending')->count();
                }

                if (Schema::hasColumn('vendor_boosts', 'approval_status')) {
                    $counts['pendingBoostCount'] = VendorBoost::where('approval_status', 'pending')->count();
                }
            } catch (\Throwable $e) {
                // Leave both at zero — a sidebar badge is not worth a 500.
            }

            $view->with($counts);
        });
    }

    /**
     * Rate limiters for the API.
     *
     * 'api'  - blanket limit applied to every API route (see bootstrap/app.php).
     *          Keyed per authenticated user, falling back to IP for guests.
     * 'auth' - tight limit for unauthenticated credential/OTP endpoints, keyed
     *          on IP + submitted email so one attacker cannot lock out a user
     *          and one IP cannot spray many accounts.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by($request->ip().'|'.$email),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }
}
