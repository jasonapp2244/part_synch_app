<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * data:professional-names — every account and delivery address US-based,
 * South-Asian sample names replaced, the owner's QA login kept.
 */
class ProfessionalSampleNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(array $fields): int
    {
        return DB::table('users')->insertGetId(array_merge([
            'role_id' => 3,
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $fields));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $id = $this->user(['first_name' => 'Abdul Mutal', 'email' => 'abdulmutal4211@gmail.com', 'city' => 'Karachi']);

        $this->artisan('data:professional-names')->assertSuccessful();

        $this->assertSame('Karachi', DB::table('users')->where('id', $id)->value('city'));
        $this->assertSame('Abdul Mutal', DB::table('users')->where('id', $id)->value('first_name'));
    }

    public function test_apply_replaces_south_asian_names_and_makes_everyone_us_based(): void
    {
        $vendor = $this->user([
            'role_id' => 2, 'first_name' => 'Abdul Mutal', 'email' => 'abdulmutal4211@gmail.com',
            'phone_number' => '03162442347', 'address' => 'House no L-285 sec 48-B korangi no 2',
            'city' => 'Karachi', 'state' => 'Sindh', 'country' => 'Pakistan', 'zipcode' => '12334',
        ]);
        $plain = $this->user(['first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'test.customer@example.com']);
        $address = DB::table('delivery_addresses')->insertGetId([
            'user_id' => $plain, 'full_name' => 'Ali Khan', 'phone_number' => '03001234567',
            'address_line1' => '12 Mall Road', 'city' => 'Lahore', 'state' => 'Punjab',
            'country' => 'Pakistan', 'postal_code' => '54000', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('data:professional-names', ['--apply' => true])->assertSuccessful();

        $v = DB::table('users')->where('id', $vendor)->first();
        $this->assertDoesNotMatchRegularExpression('/abdul|mutal/i', $v->first_name . ' ' . $v->last_name);
        $this->assertSame("vendor{$vendor}@example.com", $v->email);
        $this->assertSame('United States', $v->country);
        $this->assertStringStartsWith('555', $v->phone_number);
        $this->assertDoesNotMatchRegularExpression('/korangi|karachi|sindh/i', "{$v->address} {$v->city} {$v->state}");

        $p = DB::table('users')->where('id', $plain)->first();
        $this->assertSame('Test', $p->first_name, 'non-Asian names are kept');
        $this->assertSame('test.customer@example.com', $p->email);
        $this->assertSame('United States', $p->country);

        $a = DB::table('delivery_addresses')->where('id', $address)->first();
        $this->assertDoesNotMatchRegularExpression('/ali|khan/i', $a->full_name);
        $this->assertSame('United States', $a->country);
        $this->assertStringStartsWith('555', $a->phone_number);
        $this->assertDoesNotMatchRegularExpression('/lahore|punjab|mall road/i', "{$a->address_line1} {$a->city} {$a->state}");
    }

    public function test_the_owners_qa_login_keeps_its_email_and_password_but_moves_to_the_us(): void
    {
        $hash = Hash::make('qa-password');
        $id = $this->user([
            'first_name' => 'Test', 'last_name' => 'Customer', 'password' => $hash,
            'email' => 'tauseefchoohan0401+c1020@gmail.com', 'city' => 'Lahore', 'country' => 'Pakistan',
        ]);

        $this->artisan('data:professional-names', ['--apply' => true])->assertSuccessful();

        $qa = DB::table('users')->where('id', $id)->first();
        $this->assertSame('tauseefchoohan0401+c1020@gmail.com', $qa->email);
        $this->assertSame($hash, $qa->password);
        $this->assertSame('Test', $qa->first_name);
        $this->assertSame('United States', $qa->country);
    }

    public function test_any_abdul_email_and_forbidden_personal_accounts_are_replaced(): void
    {
        $abdul = $this->user(['role_id' => 2, 'first_name' => 'vendor333', 'email' => 'abdulmudd4@gmail.com']);
        $personal = $this->user(['first_name' => 'Josh', 'email' => 'joshmarkus1994@gmail.com']);

        $this->artisan('data:professional-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame("vendor{$abdul}@example.com", DB::table('users')->where('id', $abdul)->value('email'));
        $this->assertSame("tauseefchoohan0401+u{$personal}@gmail.com", DB::table('users')->where('id', $personal)->value('email'));
    }

    public function test_running_twice_changes_nothing_the_second_time(): void
    {
        $this->user(['first_name' => 'Nouman', 'email' => 'noumanzindanii@gmail.com', 'city' => 'Lahore']);

        $this->artisan('data:professional-names', ['--apply' => true])->assertSuccessful();
        $before = DB::table('users')->get()->toJson();

        $this->artisan('data:professional-names', ['--apply' => true])
            ->expectsOutputToContain('0 row(s) to change')
            ->assertSuccessful();
        $this->assertSame($before, DB::table('users')->get()->toJson());
    }
}
