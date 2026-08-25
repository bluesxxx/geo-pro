<?php

namespace GeoPro\AuditEngine;

/**
 * Immutable snapshot of a fetched page, parsed once from raw HTML.
 *
 * Carries everything the fact checkers need: metadata, raw JSON-LD blocks,
 * visible text and heading text. Dependency-free (DOMDocument only).
 */
final class PageSnapshot
{
    /** @param array<string, string> $metadata @param list<string> $jsonLdBlocks */
    public function __construct(
        public readonly string $url,
        public readonly string $html,
        public readonly array $metadata,
        public readonly array $jsonLdBlocks,
        public readonly string $text,
        public readonly string $headings,
    ) {}

    public static function fromHtml(string $url, string $html): self
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return new self($url, '', [], [], '', '');
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return new self($url, $html, [], [], '', '');
        }

        $xpath = new \DOMXPath($dom);

        $meta = static function (string $selector) use ($xpath): string {
            foreach ($xpath->query($selector) as $node) {
                $value = trim((string) $node->getAttribute('content'));
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $canonical = '';
        foreach ($xpath->query('//link[@rel="canonical"]') as $node) {
            $canonical = trim((string) $node->getAttribute('href'));
            break;
        }

        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim((string) $titleNode->textContent) : '';

        $metadata = [
            'title'       => $title,
            'description' => $meta('//meta[@name="description"] | //meta[@property="description"]'),
            'canonical'   => $canonical,
            'robots'      => $meta('//meta[@name="robots"]'),
            'ogTitle'     => $meta('//meta[@property="og:title"]'),
            'ogImage'     => $meta('//meta[@property="og:image"]'),
        ];

        $jsonLdBlocks = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $content = trim((string) $node->textContent);
            if ($content !== '') {
                $jsonLdBlocks[] = $content;
            }
        }

        $bodyNode = $xpath->query('//body')->item(0);
        $text = $bodyNode
            ? (string) preg_replace('/\s+/u', ' ', (string) $bodyNode->textContent)
            : '';

        $headings = [];
        foreach ($xpath->query('//h1|//h2|//h3') as $node) {
            $heading = trim((string) $node->textContent);
            if ($heading !== '') {
                $headings[] = $heading;
            }
        }

        return new self(
            url: $url,
            html: $html,
            metadata: $metadata,
            jsonLdBlocks: $jsonLdBlocks,
            text: $text,
            headings: implode(' ', $headings),
        );
    }
}
