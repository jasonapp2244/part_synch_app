<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request 2026-10-21: every account and delivery address is US-based,
 * and the Pakistani / South-Asian sample names left over from the original
 * data are replaced with professional US-style ones.
 *
 * - Every user (soft-deleted included) and every delivery address gets a US
 *   street address, city, state, ZIP, country and a phone in the fictional
 *   555-01xx range. Each row keeps the same address on every run (by id).
 * - Names and emails are only changed on rows whose name or email is of
 *   South-Asian origin. The owner's QA login (tauseefchoohan0401@gmail.com
 *   and its +variants) keeps its email.
 * - Passwords, tokens and anything else are never touched.
 *
 * Dry run unless --apply is given. Running it twice changes nothing.
 */
class ProfessionalSampleNames extends Command
{
    protected $signature = 'data:professional-names
                            {--apply : Write the changes (otherwise only report them)}';

    protected $description = 'Make every account and delivery address US-based and replace South-Asian sample names';

    /** Real US business districts: street, city, state, ZIP, latitude, longitude. */
    public const ADDRESSES = [
        ['1200 Main Street', 'Houston', 'Texas', '77002', 29.7550, -95.3640],
        ['500 Congress Avenue', 'Austin', 'Texas', '78701', 30.2660, -97.7430],
        ['1700 Lincoln Street', 'Denver', 'Colorado', '80203', 39.7440, -104.9860],
        ['233 South Wacker Drive', 'Chicago', 'Illinois', '60606', 41.8789, -87.6359],
        ['100 Peachtree Street NW', 'Atlanta', 'Georgia', '30303', 33.7560, -84.3880],
        ['1 Market Street', 'San Francisco', 'California', '94105', 37.7940, -122.3950],
        ['400 Broad Street', 'Seattle', 'Washington', '98109', 47.6200, -122.3490],
        ['200 East Washington Street', 'Phoenix', 'Arizona', '85004', 33.4480, -112.0700],
        ['1500 Market Street', 'Philadelphia', 'Pennsylvania', '19102', 39.9520, -75.1660],
        ['2200 Ross Avenue', 'Dallas', 'Texas', '75201', 32.7880, -96.7990],
    ];

    /** US names for rows whose name is replaced, picked by id. */
    public const PEOPLE = [
        ['Michael', 'Carter'], ['Sarah', 'Mitchell'], ['Daniel', 'Brooks'], ['Emily', 'Turner'],
        ['Ryan', 'Foster'], ['Jessica', 'Hayes'], ['Andrew', 'Collins'], ['Megan', 'Parker'],
        ['Brian', 'Sullivan'], ['Lauren', 'Reed'], ['Kevin', 'Morgan'], ['Rachel', 'Bennett'],
    ];

    /** Name parts and email local-parts of South-Asian origin found in the data. */
    private const ASIAN_NAME = '/\b(abdul|mutal|nouman|noman|khan|muhammad|mohammad|ahmed|ahmad|hussain|hassan|iqbal|raza|bilal|usman|hamza|imran|zain|ayesha|fatima|tauseef|choohan|chooha|aslam)\b/i';
    private const ASIAN_EMAIL = '/^(khan|khanmuhammad|ali\d|abdul|nouman|noumai|tauseef|choohan)/i';
    private const QA_EMAIL = '/^tauseefchoohan0401(\+[a-z0-9]+)?@gmail\.com$/i';

    /**
     * Real personal/billing accounts that must never be test identities
     * (owner's standing rule); a row carrying one moves to a QA alias.
     */
    private const FORBIDDEN_EMAIL = '/^(alanpaul5433|joshmarkus1994)@gmail\.com$/i';

    /** Placeholder names on delivery addresses that are replaced too. */
    private const PLACEHOLDER_NAMES = ['john doe', 'jane doe'];

    public function handle(): int
    {
        $changes = array_merge($this->userChanges(), $this->addressChanges());

        $rows = [];
        foreach ($changes as [$table, $id, $fields]) {
            foreach ($fields as $field => [$old, $new]) {
                $rows[] = ["{$table}#{$id}", $field, $this->short($old), $this->short($new)];
            }
        }

        $this->info(count($changes) . ' row(s) to change, ' . count($rows) . ' field(s).');
        if ($rows && $this->output->isVerbose()) {
            $this->table(['row', 'field', 'old', 'new'], $rows);
        }

        if (! $this->option('apply')) {
            $this->line('Dry run. Re-run with --apply to write the changes (-v lists every field).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as [$table, $id, $fields]) {
                DB::table($table)->where('id', $id)->update(array_map(fn ($pair) => $pair[1], $fields));
            }
        });

        $this->info('Updated ' . count($changes) . ' row(s).');

        return self::SUCCESS;
    }

    /** @return array<int, array{0: string, 1: int, 2: array<string, array{0: mixed, 1: mixed}>}> */
    private function userChanges(): array
    {
        $columns = Schema::getColumnListing('users');
        $changes = [];

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            [$street, $city, $state, $zip, $lat, $lng] = self::ADDRESSES[$user->id % count(self::ADDRESSES)];
            $want = [
                'address' => $street,
                'city' => $city,
                'state' => $state,
                'zipcode' => $zip,
                'country' => 'United States',
                'phone_number' => self::phone($user->id),
                'latitude' => (string) $lat,
                'longitude' => (string) $lng,
            ];

            if ($this->isAsianUser($user)) {
                [$first, $last] = self::PEOPLE[$user->id % count(self::PEOPLE)];
                $want['first_name'] = $first;
                $want['last_name'] = $last;
                if ($user->middle_name !== null && $user->middle_name !== '') {
                    $want['middle_name'] = null;
                }
                if ($user->business_name && preg_match(self::ASIAN_NAME, $user->business_name)) {
                    $want['business_name'] = "{$last} Industrial Supply";
                }
                if (! preg_match(self::QA_EMAIL, (string) $user->email) && $this->isAsianEmail($user->email)) {
                    $want['email'] = $this->freeEmail($user);
                }
            }

            if (preg_match(self::FORBIDDEN_EMAIL, (string) $user->email)) {
                $want['email'] = "tauseefchoohan0401+u{$user->id}@gmail.com";
            }

            $diff = $this->diff($user, array_intersect_key($want, array_flip($columns)));
            if ($diff) {
                $changes[] = ['users', (int) $user->id, $diff];
            }
        }

        return $changes;
    }

    /** @return array<int, array{0: string, 1: int, 2: array<string, array{0: mixed, 1: mixed}>}> */
    private function addressChanges(): array
    {
        $changes = [];

        foreach (DB::table('delivery_addresses')->orderBy('id')->get() as $row) {
            [$street, $city, $state, $zip] = self::ADDRESSES[$row->id % count(self::ADDRESSES)];
            $want = [
                'address_line1' => $street,
                'address_line2' => null,
                'city' => $city,
                'state' => $state,
                'postal_code' => $zip,
                'country' => 'United States',
                'phone_number' => self::phone($row->id),
            ];

            $name = trim((string) $row->full_name);
            if (preg_match(self::ASIAN_NAME, $name) || preg_match('/^ali\b/i', $name)
                || in_array(strtolower($name), self::PLACEHOLDER_NAMES, true)) {
                [$first, $last] = self::PEOPLE[$row->id % count(self::PEOPLE)];
                $want['full_name'] = "{$first} {$last}";
            }

            $diff = $this->diff($row, $want);
            if ($diff) {
                $changes[] = ['delivery_addresses', (int) $row->id, $diff];
            }
        }

        return $changes;
    }

    /** 555-01xx is reserved for fictional use; 5550100000 + id keeps it unique. */
    public static function phone(int $id): string
    {
        return (string) (5550100000 + $id);
    }

    private function isAsianUser(object $user): bool
    {
        $name = trim(implode(' ', array_filter([$user->first_name, $user->middle_name, $user->last_name, $user->business_name])));

        return (bool) preg_match(self::ASIAN_NAME, $name) || preg_match('/^ali\b/i', $name) || $this->isAsianEmail($user->email);
    }

    private function isAsianEmail(?string $email): bool
    {
        $local = strstr((string) $email, '@', true) ?: '';

        return (bool) preg_match(self::ASIAN_EMAIL, $local) && ! preg_match(self::QA_EMAIL, (string) $email);
    }

    private function freeEmail(object $user): string
    {
        $prefix = match ((int) $user->role_id) {
            1 => 'admin',
            2 => 'vendor',
            3 => 'customer',
            default => 'user',
        };
        $email = "{$prefix}{$user->id}@example.com";

        // Unique by id already; guard against a hand-made clash anyway.
        return DB::table('users')->where('email', $email)->where('id', '!=', $user->id)->exists()
            ? "{$prefix}{$user->id}.account@example.com"
            : $email;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    private function diff(object $row, array $want): array
    {
        $diff = [];
        foreach ($want as $field => $value) {
            $current = $row->{$field} ?? null;
            // Coordinates come back as decimals ("29.7550000"); compare those as numbers.
            $same = is_numeric($current) && is_numeric($value) && str_contains((string) $value, '.')
                ? abs((float) $current - (float) $value) < 0.000001
                : (string) $current === (string) $value && ($current === null) === ($value === null);
            if (! $same) {
                $diff[$field] = [$current, $value];
            }
        }

        return $diff;
    }

    private function short(mixed $value): string
    {
        $text = $value === null ? 'NULL' : (string) $value;

        return mb_strlen($text) > 48 ? mb_substr($text, 0, 45) . '...' : $text;
    }
}
