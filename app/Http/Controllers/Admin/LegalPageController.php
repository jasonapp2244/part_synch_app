<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\LegalContent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Legal & Support: the privacy policy, terms of use, support page and
 * account-deletion page, plus the support contact details. One screen owns
 * these settings; the app and the public pages both read what is saved here.
 */
class LegalPageController extends Controller
{
    public function index()
    {
        $documents = [];
        foreach (array_keys(LegalContent::DOCUMENTS) as $key) {
            $document = LegalContent::document($key);
            $document['content_key'] = LegalContent::DOCUMENTS[$key]['content_key'];
            $document['has_placeholders'] = LegalContent::hasPlaceholders($document['html']);
            $documents[$key] = $document;
        }

        $contact = [
            'support_email' => Setting::get('support_email', ''),
            'support_phone' => Setting::get('support_phone', ''),
            'privacy_policy_url' => Setting::get('privacy_policy_url', ''),
            'terms_url' => Setting::get('terms_url', ''),
        ];

        return view('admin.legal', compact('documents', 'contact'));
    }

    public function update(Request $request)
    {
        $rules = [
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'privacy_policy_url' => 'nullable|url|max:500',
            'terms_url' => 'nullable|url|max:500',
        ];
        foreach (LegalContent::DOCUMENTS as $meta) {
            $rules[$meta['content_key']] = 'nullable|string';
        }
        $request->validate($rules);

        $contents = [];
        foreach (LegalContent::DOCUMENTS as $meta) {
            $key = $meta['content_key'];
            if (! $request->has($key)) {
                continue;
            }
            $html = LegalContent::sanitize($request->input($key));
            if (strlen($html) > LegalContent::MAX_BYTES) {
                throw ValidationException::withMessages([
                    $key => 'This document is too long to save. Shorten it and try again.',
                ]);
            }
            $contents[$key] = $html;
        }

        foreach ($contents as $key => $html) {
            Setting::set($key, $html, 'legal');
        }
        foreach (['support_email', 'support_phone', 'privacy_policy_url', 'terms_url'] as $key) {
            if ($request->has($key)) {
                Setting::set($key, (string) $request->input($key), 'legal');
            }
        }

        return redirect()->route('admin.legal')->with('success', 'Legal & Support pages saved.');
    }
}
