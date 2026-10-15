<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\LegalContent;
use Database\Seeders\LegalContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The Legal & Support pages: what the store listings link to, what the app's
 * legal screen reads, and the admin screen that edits both.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Test',
            'last_name' => $roleId === 1 ? 'Admin' : 'Customer',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
    }

    // ── Public pages ────────────────────────────────────────────────────

    public function test_seeded_public_pages_render_for_guests(): void
    {
        $this->seed(LegalContentSeeder::class);

        $this->get('/legal/privacy-policy')->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Information we collect')
            ->assertSee('Last updated');
        $this->get('/legal/terms')->assertOk()->assertSee('Terms of Use')->assertSee('Prohibited content');
        $this->get('/support')->assertOk()->assertSee('Common questions');
        $this->get('/account/delete')->assertOk()->assertSee('Delete your account in the app');
    }

    public function test_unpublished_documents_return_404_but_support_page_still_shows_contact(): void
    {
        Setting::set('support_email', 'help@example.com', 'legal');

        $this->get('/legal/privacy-policy')->assertNotFound()->assertSee('has not been published yet');
        $this->get('/legal/terms')->assertNotFound();
        $this->get('/account/delete')->assertNotFound();
        $this->get('/support')->assertOk()->assertSee('help@example.com');
    }

    public function test_seeder_never_overwrites_edited_text(): void
    {
        Setting::set('privacy_policy_content', '<p>Our own lawyer-approved policy.</p>', 'legal');

        $this->seed(LegalContentSeeder::class);

        $this->assertSame('<p>Our own lawyer-approved policy.</p>', Setting::get('privacy_policy_content'));
        $this->assertStringContainsString('Prohibited content', Setting::get('terms_content'));
    }

    public function test_unsafe_markup_stored_directly_is_stripped_on_render(): void
    {
        Setting::set('terms_content', '<p onclick="steal()">Rules<script>alert(1)</script> <a href="javascript:alert(2)">click</a></p>', 'legal');

        $this->get('/legal/terms')->assertOk()
            ->assertSee('Rules')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:', false);
    }

    public function test_old_plain_text_format_renders_as_headings_and_lists(): void
    {
        Setting::set('privacy_policy_content', "## What we collect\n- Your email\n- Your phone", 'legal');

        $this->get('/legal/privacy-policy')->assertOk()
            ->assertSee('<h2>What we collect</h2>', false)
            ->assertSee('<li>Your email</li>', false);
    }

    // ── API used by the app ────────────────────────────────────────────

    public function test_legal_api_keeps_its_shape_and_serves_sanitised_html(): void
    {
        Setting::set('privacy_policy_content', '<h2>Intro</h2><p>Hi<script>x()</script></p>', 'legal');

        $this->getJson('/api/legal/privacy-policy')->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Privacy Policy')
            ->assertJsonPath('data.content', '<h2>Intro</h2><p>Hi</p>')
            ->assertJsonPath('data.url', route('legal.privacy'))
            ->assertJsonStructure(['data' => ['title', 'content', 'url', 'updated_at']]);

        $this->getJson('/api/legal/terms')->assertNotFound()->assertJsonPath('status', false);
    }

    public function test_app_config_links_resolve_even_when_settings_were_saved_empty(): void
    {
        // The rows exist but are empty: get()'s default would not apply.
        Setting::set('privacy_policy_url', '', 'legal');
        Setting::set('terms_url', '', 'legal');
        Setting::set('support_email', '', 'legal');
        Setting::set('site_email', 'office@example.com', 'general');

        $this->getJson('/api/app-config?platform=android&app_version=1.0.0')->assertOk()
            ->assertJsonPath('data.privacy_policy_url', route('legal.privacy'))
            ->assertJsonPath('data.terms_url', route('legal.terms'))
            ->assertJsonPath('data.support_url', route('support.page'))
            ->assertJsonPath('data.account_deletion_url', route('account.delete.info'))
            ->assertJsonPath('data.support_email', 'office@example.com');
    }

    // ── Admin screen ────────────────────────────────────────────────────

    public function test_admin_screen_requires_an_admin(): void
    {
        $this->get('/legal-support')->assertRedirect(route('admin.login'));

        $this->actingAs($this->user(3))->get('/legal-support')->assertRedirect(route('admin.login'));
        $this->actingAs($this->user(3))->post('/legal-support', ['terms_content' => '<p>x</p>'])
            ->assertRedirect(route('admin.login'));
        $this->assertNull(Setting::get('terms_content'));
    }

    public function test_admin_sees_all_four_documents_and_placeholder_warning(): void
    {
        $this->seed(LegalContentSeeder::class);

        $this->actingAs($this->user(1))->get('/legal-support')->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Terms of Use')
            ->assertSee('Delete Your Account')
            ->assertSee('Needs details')
            ->assertSee(route('legal.privacy'));
    }

    public function test_admin_saves_editor_html_sanitised(): void
    {
        $quill = '<h2>Intro</h2><p>Hello&nbsp;<strong>world</strong></p><p><br></p>'
            . '<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>One</li></ol>'
            . '<img src=x onerror=alert(1)>';

        $this->actingAs($this->user(1))->post('/legal-support', [
            'privacy_policy_content' => $quill,
            'support_email' => 'help@example.com',
            'support_phone' => '+1 555 0100',
            'privacy_policy_url' => '',
        ])->assertRedirect(route('admin.legal'))->assertSessionHas('success');

        $this->assertSame(
            '<h2>Intro</h2><p>Hello <strong>world</strong></p><ul><li>One</li></ul>',
            Setting::get('privacy_policy_content')
        );
        $this->assertSame('help@example.com', Setting::get('support_email'));
        $this->get('/legal/privacy-policy')->assertOk()->assertSee('<strong>world</strong>', false);
    }

    public function test_admin_validation_rejects_bad_email_and_url(): void
    {
        $this->actingAs($this->user(1))->post('/legal-support', [
            'support_email' => 'not-an-email',
            'terms_url' => 'nope',
        ])->assertSessionHasErrors(['support_email', 'terms_url']);
    }

    public function test_oversized_document_is_refused(): void
    {
        $huge = '<p>' . str_repeat('a', LegalContent::MAX_BYTES + 10) . '</p>';

        $this->actingAs($this->user(1))->post('/legal-support', ['terms_content' => $huge])
            ->assertSessionHasErrors('terms_content');
        $this->assertNull(Setting::get('terms_content'));
    }
}
