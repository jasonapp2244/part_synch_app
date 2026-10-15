<?php

namespace App\Support;

use App\Models\Setting;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The legal and support documents edited in the admin panel's Legal & Support
 * screen, and the single sanitiser every one of them passes through.
 *
 * The same stored HTML is served three ways: rendered on the public pages the
 * store listings link to, returned by /api/legal/* for the app's native legal
 * screen, and loaded back into the admin editor. Sanitising on save *and* on
 * render means a value written before this existed (plain text from the old
 * textarea, or anything typed straight into the database) still comes out
 * safe and formatted.
 */
class LegalContent
{
    /**
     * Document key => title, the setting holding its body, the optional
     * "hosted elsewhere" URL setting, and the public route that renders it.
     */
    public const DOCUMENTS = [
        'privacy_policy' => [
            'title' => 'Privacy Policy',
            'content_key' => 'privacy_policy_content',
            'url_key' => 'privacy_policy_url',
            'route' => 'legal.privacy',
        ],
        'terms' => [
            'title' => 'Terms of Use',
            'content_key' => 'terms_content',
            'url_key' => 'terms_url',
            'route' => 'legal.terms',
        ],
        'support' => [
            'title' => 'Support',
            'content_key' => 'support_page_content',
            'url_key' => null,
            'route' => 'support.page',
        ],
        'account_deletion' => [
            'title' => 'Delete Your Account',
            'content_key' => 'account_deletion_content',
            'url_key' => null,
            'route' => 'account.delete.info',
        ],
    ];

    /** The settings column is TEXT: 65,535 bytes. */
    public const MAX_BYTES = 65000;

    private const ALLOWED = ['h2', 'h3', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a'];

    private const RENAMED = [
        'h1' => 'h2', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3',
        'b' => 'strong', 'i' => 'em',
    ];

    /** Removed together with everything inside them. */
    private const DROPPED = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'select', 'textarea', 'svg', 'math', 'template', 'noscript', 'head', 'title', 'meta', 'link',
    ];

    /**
     * Reduce any input to the small set of tags the app and the public pages
     * both understand. Text without a single tag is treated as the plain-text
     * format the old textarea used (`## heading`, `- bullet`, blank-line
     * paragraphs) and converted first.
     */
    public static function sanitize(?string $input): string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return '';
        }

        // Only a real formatting tag makes this HTML; "a < b" or "<c>" in
        // plain text must be escaped, not parsed.
        if (! preg_match('/<\/?(p|h[1-6]|ul|ol|li|br|div|span|strong|em|b|i|a|script|style|iframe)\b/i', $input)) {
            $input = self::fromPlainText($input);
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="legal-root">' . $input . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('legal-root');
        if (! $root) {
            return '';
        }

        self::clean($root);

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        // Editors pad with non-breaking spaces; they stop text wrapping.
        $html = str_replace(["\u{00A0}", '&nbsp;'], ' ', $html);

        return trim($html);
    }

    /** True when the text still carries a template placeholder like [COMPANY LEGAL NAME]. */
    public static function hasPlaceholders(?string $content): bool
    {
        return (bool) preg_match('/\[[A-Z][A-Z0-9 \/,&\'-]{2,}\]/', strip_tags((string) $content));
    }

    /** Title, sanitised HTML and last-updated time for one document. */
    public static function document(string $key): array
    {
        $meta = self::DOCUMENTS[$key];
        $row = Setting::where('key', $meta['content_key'])->first();

        return [
            'key' => $key,
            'title' => $meta['title'],
            'html' => self::sanitize($row?->value),
            'updated_at' => $row?->updated_at,
            'route' => $meta['route'],
        ];
    }

    private static function clean(DOMNode $node): void
    {
        // Iterate over a copy: children are removed and replaced as we go.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::cleanElement($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanElement(DOMElement $el): void
    {
        $tag = strtolower($el->tagName);
        $parent = $el->parentNode;

        if (in_array($tag, self::DROPPED, true)) {
            $parent->removeChild($el);
            return;
        }

        // Quill 2 writes every list as <ol>, marking bullet items with
        // data-list="bullet". Without this a bulleted list would be numbered.
        if ($tag === 'ol') {
            $first = null;
            foreach ($el->childNodes as $c) {
                if ($c instanceof DOMElement) {
                    $first = $c;
                    break;
                }
            }
            if ($first && $first->getAttribute('data-list') === 'bullet') {
                $tag = 'ul';
            }
        }

        $tag = self::RENAMED[$tag] ?? $tag;

        self::clean($el);

        if (! in_array($tag, self::ALLOWED, true)) {
            // Unknown wrappers (span, div, u, section...) keep their text.
            while ($el->firstChild) {
                $parent->insertBefore($el->firstChild, $el);
            }
            $parent->removeChild($el);
            return;
        }

        $href = $tag === 'a' ? trim($el->getAttribute('href')) : '';

        $clean = $el->ownerDocument->createElement($tag);
        while ($el->firstChild) {
            $clean->appendChild($el->firstChild);
        }

        if ($tag === 'a') {
            if (! preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $href)) {
                // A link with an unsafe or missing target keeps its text only.
                while ($clean->firstChild) {
                    $parent->insertBefore($clean->firstChild, $el);
                }
                $parent->removeChild($el);
                return;
            }
            $clean->setAttribute('href', $href);
            $clean->setAttribute('rel', 'nofollow noopener noreferrer');
            if (preg_match('#^https?://#i', $href)) {
                $clean->setAttribute('target', '_blank');
            }
        }

        // An empty block (Quill's blank line is <p><br></p>) is just noise.
        if (in_array($tag, ['p', 'h2', 'h3', 'li'], true) && trim(str_replace("\u{00A0}", ' ', $clean->textContent)) === '') {
            $parent->removeChild($el);
            return;
        }

        $parent->replaceChild($clean, $el);
    }

    /** The old textarea format: blank-line paragraphs, `#` headings, `-` bullets. */
    private static function fromPlainText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $html = '';

        foreach (preg_split('/\n\s*\n/', $text) as $chunk) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $chunk)), 'strlen'));
            $paragraph = [];
            $bullets = [];

            $flushParagraph = function () use (&$paragraph, &$html) {
                if ($paragraph) {
                    $html .= '<p>' . implode('<br>', $paragraph) . '</p>';
                    $paragraph = [];
                }
            };
            $flushBullets = function () use (&$bullets, &$html) {
                if ($bullets) {
                    $html .= '<ul><li>' . implode('</li><li>', $bullets) . '</li></ul>';
                    $bullets = [];
                }
            };

            foreach ($lines as $line) {
                if (preg_match('/^(#{1,6})\s*(.+)$/', $line, $m)) {
                    $flushParagraph();
                    $flushBullets();
                    $level = strlen($m[1]) <= 2 ? 'h2' : 'h3';
                    $html .= "<{$level}>" . e($m[2]) . "</{$level}>";
                } elseif (preg_match('/^[-*•]\s+(.+)$/u', $line, $m)) {
                    $flushParagraph();
                    $bullets[] = e($m[1]);
                } else {
                    $flushBullets();
                    $paragraph[] = e($line);
                }
            }
            $flushParagraph();
            $flushBullets();
        }

        return $html;
    }
}
