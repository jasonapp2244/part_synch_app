<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\LegalContent;
use Illuminate\Database\Seeder;

/**
 * Suggested starting text for the four Legal & Support pages, written for what
 * Part Synch actually does today: what the app collects (no location, no
 * advertising or analytics SDKs, no in-app payment processing) and what
 * account deletion really removes (AccountController::destroy).
 *
 * It only fills a document that is still empty, so it is safe to run again
 * and never overwrites text an admin has edited. Business details the
 * codebase cannot know are left as [PLACEHOLDERS]; the admin screen flags
 * them until they are replaced. This is a template, not legal advice.
 *
 *   php artisan db:seed --class=LegalContentSeeder
 */
class LegalContentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            'privacy_policy_content' => $this->privacyPolicy(),
            'terms_content' => $this->terms(),
            'support_page_content' => $this->support(),
            'account_deletion_content' => $this->accountDeletion(),
        ];

        foreach ($documents as $key => $html) {
            if (blank(Setting::get($key))) {
                Setting::set($key, LegalContent::sanitize($html), 'legal');
            }
        }
    }

    private function privacyPolicy(): string
    {
        return <<<'HTML'
<p>This Privacy Policy explains how [COMPANY LEGAL NAME] ("Part Synch", "we", "us") collects, uses and protects your information when you use the Part Synch mobile app and related services. By using Part Synch you agree to this policy.</p>

<h2>1. Information we collect</h2>
<h3>Information you give us</h3>
<ul>
<li><strong>Account details:</strong> your name, email address, phone number and password (stored encrypted).</li>
<li><strong>Seller details:</strong> if you register as a vendor, your business name, business type, description, business licence, logo, opening hours and business address.</li>
<li><strong>Profile:</strong> an optional profile photo.</li>
<li><strong>Listings:</strong> product and service details and the photos you upload.</li>
<li><strong>Orders:</strong> the items you order, quantities, prices and the delivery address you enter.</li>
<li><strong>Messages:</strong> chat messages and attachments you exchange with other users.</li>
<li><strong>Reviews and reports:</strong> ratings and reviews you write, and reports you submit about listings or users.</li>
<li><strong>Support requests:</strong> what you send us through Help &amp; Support.</li>
</ul>
<h3>Information collected automatically</h3>
<ul>
<li><strong>Device and app information:</strong> your device platform (Android or iOS), app version, and a device token used to deliver notifications.</li>
<li><strong>Technical logs:</strong> standard server logs such as IP address, request time and errors, used to keep the service secure and working.</li>
</ul>
<h3>Camera and photos</h3>
<p>The app asks for access to your camera or photo library only when you choose to take or attach a photo (for example a listing photo, profile photo or chat attachment). Photos are resized on your device before upload. We do not access your camera or photos at any other time.</p>
<h3>What we do not collect</h3>
<p>We do not collect your precise location, we do not use advertising or third-party analytics trackers, and we do not track you across other companies' apps or websites.</p>

<h2>2. How we use your information</h2>
<ul>
<li>To create and manage your account and verify your email address with a one-time code.</li>
<li>To show your listings to buyers and let buyers find, order and review parts and services.</li>
<li>To process orders between buyers and sellers and send order updates.</li>
<li>To let you chat with other users.</li>
<li>To review reports, enforce our Terms of Use and keep the marketplace safe.</li>
<li>To answer support requests and send important service messages.</li>
<li>To maintain, secure and improve the service, and to meet legal obligations.</li>
</ul>

<h2>3. How information is shared</h2>
<ul>
<li><strong>With other users:</strong> your public profile, business details, listings and reviews are visible to other users. When you place an order, the seller receives the details needed to fulfil it, including your name, contact details and delivery address.</li>
<li><strong>With service providers:</strong> companies that host our servers and send our emails, acting on our instructions.</li>
<li><strong>For legal reasons:</strong> when required by law, or to protect the rights and safety of our users and the public.</li>
</ul>
<p>We do not sell your personal information.</p>

<h2>4. Payments</h2>
<p>Part Synch does not process card payments inside the app. Payment for an order is arranged between the buyer and the seller. If we add in-app payments in the future, we will update this policy first.</p>

<h2>5. How long we keep information</h2>
<p>We keep your information while your account is active. When you delete your account, we delete or anonymise your personal information as described on our account deletion page. We keep anonymised order records where we need them for accounting, tax or legal reasons.</p>

<h2>6. Your choices and rights</h2>
<ul>
<li>You can view and update your profile in the app.</li>
<li>You can delete your account at any time in the app under <strong>Settings → Delete account</strong>.</li>
<li>Depending on where you live, you may have the right to access, correct, delete or export your information, or object to how we use it. Contact us to make a request.</li>
</ul>

<h2>7. Security</h2>
<p>We use reasonable technical and organisational measures to protect your information, including encrypted connections and encrypted password storage. No system is completely secure, so please keep your password private.</p>

<h2>8. Children</h2>
<p>Part Synch is a business marketplace and is not intended for anyone under 18. We do not knowingly collect information from children.</p>

<h2>9. Changes to this policy</h2>
<p>We may update this policy. The date at the top of this page shows when it last changed. If the changes are significant, we will let you know in the app.</p>

<h2>10. Contact us</h2>
<p>[COMPANY LEGAL NAME]<br>[REGISTERED ADDRESS]<br>Email: [SUPPORT EMAIL]</p>
HTML;
    }

    private function terms(): string
    {
        return <<<'HTML'
<p>These Terms of Use ("Terms") are an agreement between you and [COMPANY LEGAL NAME] ("Part Synch", "we", "us") and govern your use of the Part Synch mobile app and services. By creating an account or using Part Synch you agree to these Terms and to our Privacy Policy.</p>

<h2>1. The service</h2>
<p>Part Synch is a marketplace where buyers find parts and services and sellers ("vendors") list them. Unless we say otherwise, we are not the seller of items listed on Part Synch. Each sale is a contract between the buyer and the seller.</p>

<h2>2. Eligibility and accounts</h2>
<ul>
<li>You must be at least 18 and able to form a binding contract.</li>
<li>You must give accurate information and keep it up to date.</li>
<li>You are responsible for keeping your password secure and for everything that happens under your account.</li>
<li>Vendor accounts are reviewed before approval. We may refuse or remove a vendor account at our discretion.</li>
</ul>

<h2>3. Seller responsibilities</h2>
<ul>
<li>List only items and services you are legally allowed to sell, with accurate descriptions, photos, prices and stock levels.</li>
<li>Fulfil accepted orders as described, and honour your stated return and warranty terms.</li>
<li>Comply with all laws that apply to your business, including consumer protection and tax laws.</li>
</ul>

<h2>4. Buyer responsibilities</h2>
<ul>
<li>Give accurate order and delivery information.</li>
<li>Pay the seller as agreed for the orders you place.</li>
<li>Check that a part is compatible and suitable for your use before ordering.</li>
</ul>

<h2>5. Prohibited content and conduct</h2>
<p>You must not:</p>
<ul>
<li>list stolen, counterfeit, illegal, unsafe or recalled items;</li>
<li>post false, misleading, abusive, hateful or sexually explicit content;</li>
<li>harass, threaten or defraud other users;</li>
<li>post fake reviews or manipulate ratings;</li>
<li>infringe anyone's intellectual property or privacy;</li>
<li>try to access other accounts, interfere with the service or use it to send spam.</li>
</ul>

<h2>6. Reviews, reports and moderation</h2>
<p>Reviews must reflect genuine experiences. You can report listings and users, and block users, from inside the app. We review reports and may remove content, suspend accounts or take other action when these Terms are broken.</p>

<h2>7. Your content</h2>
<p>You keep ownership of the content you post. You give us a non-exclusive, worldwide, royalty-free licence to host, display and distribute it in order to operate and promote Part Synch. You confirm you have the rights to everything you post.</p>

<h2>8. Fees</h2>
<p>Creating an account is free. Optional paid features for sellers, such as promoting a listing, are described in the app before you buy them.</p>

<h2>9. Suspension and termination</h2>
<p>You can stop using Part Synch and delete your account at any time in the app under Settings → Delete account. We may suspend or close an account that breaks these Terms or puts other users at risk.</p>

<h2>10. Disclaimers</h2>
<p>Part Synch is provided "as is". We do not guarantee the quality, safety, legality or compatibility of items listed by sellers, or that the service will always be available or error-free.</p>

<h2>11. Limitation of liability</h2>
<p>To the extent the law allows, we are not liable for indirect or consequential losses, or for disputes between buyers and sellers. Nothing in these Terms limits liability that cannot be limited by law.</p>

<h2>12. Changes to these Terms</h2>
<p>We may update these Terms. The date at the top of this page shows when they last changed. Continuing to use Part Synch after a change means you accept the updated Terms.</p>

<h2>13. Governing law</h2>
<p>These Terms are governed by the laws of [COUNTRY/STATE OF GOVERNING LAW].</p>

<h2>14. Contact us</h2>
<p>[COMPANY LEGAL NAME]<br>[REGISTERED ADDRESS]<br>Email: [SUPPORT EMAIL]</p>
HTML;
    }

    private function support(): string
    {
        return <<<'HTML'
<p>Need help with Part Synch? We're here to help buyers and sellers.</p>

<h2>Contact us from the app</h2>
<p>Open the app and go to <strong>Settings → Help &amp; Support</strong>, write your message and tap Send. Our team will reply by email.</p>

<h2>Common questions</h2>
<h3>I didn't receive my verification code</h3>
<p>Check your spam or junk folder, make sure the email address is correct, then request a new code from the app.</p>
<h3>I forgot my password</h3>
<p>On the sign-in screen tap <strong>Forgot password</strong> and follow the steps sent to your email.</p>
<h3>My vendor account is waiting for approval</h3>
<p>New seller accounts are reviewed before they go live. You will be able to list products as soon as your account is approved.</p>
<h3>There is a problem with an order</h3>
<p>Message the seller from the order or chat screen first. If you cannot resolve it, contact us and include your order number.</p>
<h3>I want to report a listing or a user</h3>
<p>Use the Report option on the listing or the user's profile. You can also block a user from their profile.</p>
<h3>How do I delete my account?</h3>
<p>See our account deletion page, or go to <strong>Settings → Delete account</strong> in the app.</p>
HTML;
    }

    private function accountDeletion(): string
    {
        return <<<'HTML'
<p>You can delete your Part Synch account at any time. This page explains how, and what happens to your information.</p>

<h2>Delete your account in the app</h2>
<ol>
<li>Open the Part Synch app and sign in.</li>
<li>Go to <strong>Settings</strong>.</li>
<li>Tap <strong>Delete account</strong>.</li>
<li>Confirm with your password and tap Delete.</li>
</ol>
<p>If you have orders that are still in progress, you will be asked to wait until they are completed or cancelled.</p>

<h2>Can't use the app?</h2>
<p>Email us from the address registered to your account with the subject "Delete my account". We will verify the request and delete your account within 30 days.</p>

<h2>What is deleted</h2>
<ul>
<li>Your name, email address, phone number, address and profile photo.</li>
<li>Your business details, business licence and logo (sellers).</li>
<li>Your cart, saved items, delivery addresses and chat messages.</li>
<li>Device tokens used for notifications.</li>
<li>Your listings are taken down and are no longer visible.</li>
</ul>

<h2>What we keep</h2>
<p>Records of completed orders are kept in anonymised form, no longer linked to your name or contact details, where we need them for accounting, tax or legal reasons.</p>
HTML;
    }
}
