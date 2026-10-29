<?php

namespace Tests\Unit;

use App\Mail\BoostStatusMail;
use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\VendorBoost;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The packages are named "Weekly Boost", "Monthly Boost", so "Your {package}
 * boost" read "Your Weekly Boost boost is live" (full regression run
 * 2026-10-30).
 */
class BoostStatusMailTest extends TestCase
{
    private function boost(string $packageName): VendorBoost
    {
        $boost = new VendorBoost(['end_date' => Carbon::parse('2026-11-06')]);
        $boost->setRelation('package', new BoostPackage(['name' => $packageName]));
        $boost->setRelation('position', new BoostPosition(['name' => 'Middle Section']));
        $boost->setRelation('boostedProducts', collect([1]));

        return $boost;
    }

    public function test_a_package_named_boost_is_not_followed_by_boost_again(): void
    {
        [, $body] = BoostStatusMail::summary($this->boost('Weekly Boost'), 'live');

        $this->assertStringStartsWith('Your Weekly Boost is now live in Middle Section', $body);
        $this->assertStringNotContainsStringIgnoringCase('boost boost', $body);
    }

    public function test_any_other_package_name_still_reads_as_a_boost(): void
    {
        [, $body] = BoostStatusMail::summary($this->boost('Spring Promo'), 'cancelled');

        $this->assertStringStartsWith('Your Spring Promo boost in Middle Section was cancelled', $body);
    }
}
