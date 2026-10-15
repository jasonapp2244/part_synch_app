<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\LegalContent;
use Illuminate\Http\Request;

/**
 * Public app configuration and legal documents.
 *
 * Two things the stores need that the API did not expose:
 *
 *  - A privacy policy and terms of service the app can link to. Both stores
 *    require a reachable privacy policy, and Apple requires an EULA link
 *    wherever an account can be created.
 *  - A minimum supported version, so a build that is too old to talk to the
 *    current API can tell the user to update instead of failing strangely.
 *    This is the standard alternative to letting old clients break.
 *
 * Version and store values are edited on the admin panel's Settings screen;
 * legal documents and support contact details on its Legal & Support screen.
 */
class AppConfigController extends Controller
{
    public function config(Request $request)
    {
        $platform = strtolower((string) $request->query('platform', 'android'));
        $platform = in_array($platform, ['android', 'ios'], true) ? $platform : 'android';

        $current = (string) $request->query('app_version', '');
        $minimum = (string) Setting::get("min_app_version_{$platform}", '1.0.0');
        $latest = (string) Setting::get("latest_app_version_{$platform}", $minimum);

        // Only claim an update is required when the client actually told us
        // what it is running — otherwise an empty version string would compare
        // as older than everything and lock every caller out.
        $forceUpdate = $current !== '' && version_compare($current, $minimum, '<');
        $updateAvailable = $current !== '' && version_compare($current, $latest, '<');

        return response()->json([
            'status' => true,
            'message' => 'App configuration fetched.',
            'data' => [
                'platform' => $platform,
                'minimum_version' => $minimum,
                'latest_version' => $latest,
                'force_update' => $forceUpdate,
                'update_available' => $updateAvailable,
                'update_message' => $forceUpdate
                    ? Setting::get('force_update_message', 'A newer version of Part Synch is required to continue.')
                    : null,
                'store_url' => $platform === 'ios'
                    ? Setting::get('app_store_url', '')
                    : Setting::get('play_store_url', ''),
                'maintenance_mode' => (bool) Setting::get('maintenance_mode', 0),
                'maintenance_message' => Setting::get('maintenance_message', ''),
                // `?:` not a get() default: once a field has been saved empty
                // the row exists, and get() would hand back '' instead of the
                // fallback.
                'support_email' => Setting::get('support_email') ?: (string) Setting::get('site_email', ''),
                'support_phone' => Setting::get('support_phone') ?: (string) Setting::get('site_phone', ''),
                'privacy_policy_url' => Setting::get('privacy_policy_url') ?: route('legal.privacy'),
                'terms_url' => Setting::get('terms_url') ?: route('legal.terms'),
                'support_url' => route('support.page'),
                'account_deletion_url' => route('account.delete.info'),
                'currency' => Setting::get('currency', 'USD'),
                'currency_symbol' => Setting::get('currency_symbol', '$'),
            ],
        ]);
    }

    /**
     * Privacy policy body, so the app can render it in a native screen instead
     * of a webview.
     */
    public function privacyPolicy()
    {
        return $this->legalDocument('privacy_policy', 'Privacy Policy');
    }

    public function terms()
    {
        return $this->legalDocument('terms', 'Terms of Service');
    }

    private function legalDocument(string $key, string $title)
    {
        $content = LegalContent::sanitize(Setting::get($key . '_content'));

        if (blank($content)) {
            return response()->json([
                'status' => false,
                'message' => $title . ' has not been published yet.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => $title . ' fetched.',
            'data' => [
                'title' => $title,
                'content' => $content,
                'url' => Setting::get($key . '_url') ?: route(LegalContent::DOCUMENTS[$key]['route']),
                'updated_at' => optional(Setting::where('key', $key . '_content')->first())->updated_at,
            ],
        ]);
    }
}
