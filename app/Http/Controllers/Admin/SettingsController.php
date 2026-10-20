<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');

        // Default settings structure
        $defaults = [
            'general' => [
                'site_name' => 'Part Synch',
                'site_email' => '',
                'site_phone' => '',
                'currency' => 'USD',
                'currency_symbol' => '$',
            ],
            'commission' => [
                'vendor_commission_rate' => '10',
                'commission_type' => 'percentage',
            ],
            'orders' => [
                'min_order_amount' => '0',
                'tax_rate' => '0',
                'shipping_default' => '0',
            ],
            // Served to the mobile app by /api/app-config on launch. The
            // minimum version drives the force-update prompt, so old builds
            // can be retired instead of failing against a changed API.
            'mobile' => [
                'min_app_version_android' => '1.0.0',
                'latest_app_version_android' => '1.0.0',
                'min_app_version_ios' => '1.0.0',
                'latest_app_version_ios' => '1.0.0',
                'play_store_url' => '',
                'app_store_url' => '',
                'force_update_message' => 'A newer version of Part Synch is required to continue.',
                'maintenance_mode' => '0',
                'maintenance_message' => '',
            ],
            // Boosts are paid by card in the app; products and services are
            // paid outside it. Off pauses card payment without an app release.
            'payments' => [
                'boost_card_payment' => '1',
            ],
            // Legal documents and support contact details are edited on the
            // Legal & Support screen (LegalPageController), not here.
        ];

        // Merge defaults with DB values
        $currentSettings = [];
        foreach ($defaults as $group => $keys) {
            foreach ($keys as $key => $default) {
                $currentSettings[$key] = Setting::get($key, $default);
            }
        }

        return view('admin.settings', compact('currentSettings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_email' => 'nullable|email|max:255',
            'vendor_commission_rate' => 'nullable|numeric|min:0|max:100',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'min_order_amount' => 'nullable|numeric|min:0',
            'shipping_default' => 'nullable|numeric|min:0',
            'play_store_url' => 'nullable|url|max:500',
            'app_store_url' => 'nullable|url|max:500',
            'min_app_version_android' => ['nullable', 'regex:/^\d+(\.\d+){0,3}$/'],
            'latest_app_version_android' => ['nullable', 'regex:/^\d+(\.\d+){0,3}$/'],
            'min_app_version_ios' => ['nullable', 'regex:/^\d+(\.\d+){0,3}$/'],
            'latest_app_version_ios' => ['nullable', 'regex:/^\d+(\.\d+){0,3}$/'],
        ]);

        $settingKeys = [
            'site_name' => 'general',
            'site_email' => 'general',
            'site_phone' => 'general',
            'currency' => 'general',
            'currency_symbol' => 'general',
            'vendor_commission_rate' => 'commission',
            'commission_type' => 'commission',
            'min_order_amount' => 'orders',
            'tax_rate' => 'orders',
            'shipping_default' => 'orders',
            'min_app_version_android' => 'mobile',
            'latest_app_version_android' => 'mobile',
            'min_app_version_ios' => 'mobile',
            'latest_app_version_ios' => 'mobile',
            'play_store_url' => 'mobile',
            'app_store_url' => 'mobile',
            'force_update_message' => 'mobile',
            'maintenance_message' => 'mobile',
        ];

        foreach ($settingKeys as $key => $group) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key), $group);
            }
        }

        // An unchecked checkbox is absent from the request, so it has to be
        // written explicitly or maintenance mode could never be turned off.
        Setting::set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0', 'mobile');
        Setting::set('boost_card_payment', $request->boolean('boost_card_payment') ? '1' : '0', 'payments');

        return redirect()->route('admin.settings')->with('success', 'Settings updated successfully.');
    }
}
