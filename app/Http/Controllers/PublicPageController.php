<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\LegalContent;

/**
 * The public pages the store listings link to: App Store Connect and Play
 * Console both want a privacy policy URL and a support URL, and Play wants a
 * web page that explains how to delete an account. No sign-in, no admin
 * chrome; the text is whatever the admin panel's Legal & Support screen holds.
 */
class PublicPageController extends Controller
{
    public function privacyPolicy()
    {
        return $this->render('privacy_policy');
    }

    public function terms()
    {
        return $this->render('terms');
    }

    public function accountDeletion()
    {
        return $this->render('account_deletion');
    }

    /**
     * The support page always renders: the contact details alone are enough
     * for a store reviewer, and the editable text is an optional extra.
     */
    public function support()
    {
        return $this->render('support', alwaysShow: true);
    }

    private function render(string $key, bool $alwaysShow = false)
    {
        $document = LegalContent::document($key);
        $contact = [
            'email' => Setting::get('support_email') ?: Setting::get('site_email'),
            'phone' => Setting::get('support_phone') ?: Setting::get('site_phone'),
        ];

        $published = $document['html'] !== '' || $alwaysShow;

        return response()
            ->view('public.document', [
                'document' => $document,
                'contact' => $contact,
                'published' => $published,
                'siteName' => Setting::get('site_name') ?: 'Part Synch',
                'pages' => LegalContent::DOCUMENTS,
            ], $published ? 200 : 404);
    }
}
