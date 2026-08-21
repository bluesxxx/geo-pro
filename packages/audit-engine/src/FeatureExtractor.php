<?php

namespace GeoPro\AuditEngine;

/**
 * Extract GEO-relevant structural features from raw HTML.
 *
 * Uses PHP's DOMDocument (ext-dom). Deliberately dependency-free: no
 * mbstring required, byte-length heuristics are fine for scoring thresholds.
 */
final class FeatureExtractor
{
    /** @var list<string> Buzzwords that erode AI citation willingness. */
    public const BUZZWORDS = [
        '极致', '颠覆', '领先', '最好', '最牛', '无敌', '革命性', '史上最强',
    ];

    /**
     * @return array{
     *   has_h1: bool,
     *   has_schema: bool,
     *   has_faq_schema: bool,
     *   title: string,
     *   meta_description: string,
     *   text: string,
     *   text_length: int,
     *   buzzword_count: int
     * }
     */
    public function extract(string $html): array
    {
        $html = (string) $html;
        $empty = $this->emptyFeatures();

        if (trim($html) === '') {
            return $empty;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $empty;
        }

        $xpath = new \DOMXPath($dom);

        $hasH1 = $xpath->query('//h1')->length > 0;

        $hasSchema = false;
        $hasFaqSchema = false;
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
            $content = (string) $node->textContent;
            if (trim($content) === '') {
                continue;
            }
            if (str_contains($content, '"@type"')) {
                $hasSchema = true;
            }
            if (str_contains($content, '"FAQPage"')) {
                $hasFaqSchema = true;
            }
        }

        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim((string) $titleNode->textContent) : '';

        $metaDescription = '';
        foreach ($xpath->query('//meta[@name="description"]') as $meta) {
            $metaDescription = trim((string) $meta->getAttribute('content'));
            if ($metaDescription !== '') {
                break;
            }
        }

        $bodyNode = $xpath->query('//body')->item(0);
        $text = $bodyNode
            ? trim((string) preg_replace('/\s+/u', ' ', (string) $bodyNode->textContent))
            : '';

        return [
            'has_h1' => $hasH1,
            'has_schema' => $hasSchema,
            'has_faq_schema' => $hasFaqSchema,
            'title' => $title,
            'meta_description' => $metaDescription,
            'text' => $text,
            'text_length' => strlen($text),
            'buzzword_count' => $this->countBuzzwords($text),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyFeatures(): array
    {
        return [
            'has_h1' => false,
            'has_schema' => false,
            'has_faq_schema' => false,
            'title' => '',
            'meta_description' => '',
            'text' => '',
            'text_length' => 0,
            'buzzword_count' => 0,
        ];
    }

    private function countBuzzwords(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $count = 0;
        foreach (self::BUZZWORDS as $word) {
            $count += substr_count($text, $word);
        }

        return $count;
    }
}
