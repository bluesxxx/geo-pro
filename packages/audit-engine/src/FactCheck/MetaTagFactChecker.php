<?php

namespace GeoPro\AuditEngine\FactCheck;

use GeoPro\AuditEngine\Fact;
use GeoPro\AuditEngine\PageSnapshot;

/**
 * Checks for essential meta tags in page metadata and HTML.
 *
 * Fields produced:
 * - meta.title        — present | absent
 * - meta.description  — present | absent
 * - meta.canonical    — present | absent
 * - meta.robots       — present | absent
 * - meta.h1           — present | absent
 * - meta.og_title     — present | absent
 * - meta.og_image     — present | absent
 * - meta.twitter_card — present | absent
 */
class MetaTagFactChecker implements FactCheckerInterface
{
    public function name(): string
    {
        return 'meta';
    }

    public function check(PageSnapshot $page): array
    {
        $meta = $page->metadata;
        $html = $page->html;
        $facts = [];

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.title',
            status: $this->hasTitle($meta, $html) ? 'present' : 'absent',
            evidence: $this->getTitlePreview($meta, $html),
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.description',
            status: $this->hasDescription($meta, $html) ? 'present' : 'absent',
            evidence: (string) ($meta['description'] ?? '') !== '' ? mb_substr((string) $meta['description'], 0, 160) : null,
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.canonical',
            status: $this->hasCanonical($meta, $html) ? 'present' : 'absent',
            evidence: null,
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.robots',
            status: $this->hasRobots($meta, $html) ? 'present' : 'absent',
            evidence: $this->getRobotsValue($meta, $html),
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.h1',
            status: $this->hasH1($html) ? 'present' : 'absent',
            evidence: $this->getH1Preview($html),
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.og_title',
            status: $this->hasOpenGraph($meta, $html, 'title') ? 'present' : 'absent',
            evidence: null,
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.og_image',
            status: $this->hasOpenGraph($meta, $html, 'image') ? 'present' : 'absent',
            evidence: null,
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'meta.twitter_card',
            status: $this->hasTwitterCard($html) ? 'present' : 'absent',
            evidence: $this->getTwitterCard($html),
        );

        return $facts;
    }

    private function hasH1(string $html): bool
    {
        return preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html) === 1;
    }

    /** @return string|null */
    private function getH1Preview(string $html)
    {
        if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $count = count($m[1]);
            $first = trim(strip_tags($m[1][0] ?? ''));

            return $count > 1
                ? "{$count} h1 tags, first: ".mb_substr($first, 0, 60)
                : mb_substr($first, 0, 60);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function hasTitle(array $meta, string $html): bool
    {
        return ! empty($meta['title'])
            || ! empty($meta['ogTitle'])
            || preg_match('/<title[^>]*>(.+?)<\/title>/is', $html) === 1;
    }

    /**
     * @param  array<string, string>  $meta
     * @return string|null
     */
    private function getTitlePreview(array $meta, string $html)
    {
        $title = $meta['title'] ?? '' ?: ($meta['ogTitle'] ?? '');

        if ($title === '' && preg_match('/<title[^>]*>(.+?)<\/title>/is', $html, $m)) {
            $title = trim($m[1]);
        }

        return $title !== '' ? mb_substr($title, 0, 120) : null;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function hasDescription(array $meta, string $html): bool
    {
        if (! empty($meta['description'])) {
            return true;
        }

        return preg_match('/<meta\s+(?:name|property)=["\']?description["\']?\s+content=["\']([^"\']+)["\']/is', $html) === 1;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function hasCanonical(array $meta, string $html): bool
    {
        if (! empty($meta['canonical'])) {
            return true;
        }

        return preg_match('/<link\s+rel=["\']canonical["\']\s+href=/is', $html) === 1;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function hasRobots(array $meta, string $html): bool
    {
        if (! empty($meta['robots'])) {
            return true;
        }

        return preg_match('/<meta\s+name=["\']robots["\']/is', $html) === 1;
    }

    /**
     * @param  array<string, string>  $meta
     * @return string|null
     */
    private function getRobotsValue(array $meta, string $html)
    {
        $robots = $meta['robots'] ?? '';

        if ($robots === '' && preg_match('/<meta\s+name=["\']robots["\']\s+content=["\']([^"\']+)["\']/is', $html, $m)) {
            $robots = $m[1];
        }

        return $robots !== '' ? mb_substr($robots, 0, 60) : null;
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function hasOpenGraph(array $meta, string $html, string $property): bool
    {
        $ogKey = 'og'.ucfirst($property);

        if (! empty($meta[$ogKey])) {
            return true;
        }

        return preg_match('/<meta\s+(?:property|name)=["\'](?:og|twitter):'.$property."['\"]?/is", $html) === 1
            || preg_match('/<meta\s+property=["\']og:'.$property."['\"].*?content=/is", $html) === 1;
    }

    private function hasTwitterCard(string $html): bool
    {
        return preg_match('/<meta\s+name=["\']twitter:card["\']/is', $html) === 1;
    }

    /** @return string|null */
    private function getTwitterCard(string $html)
    {
        if (preg_match('/<meta\s+name=["\']twitter:card["\']\s+content=["\']([^"\']+)["\']/is', $html, $m)) {
            return $m[1];
        }

        return null;
    }
}
