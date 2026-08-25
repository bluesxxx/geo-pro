<?php

namespace GeoPro\AuditEngine\FactCheck;

use GeoPro\AuditEngine\Fact;
use GeoPro\AuditEngine\PageSnapshot;

/**
 * Checks for JSON-LD structured data presence and essential schema types.
 *
 * Fields produced:
 * - jsonld.present             — present | absent
 * - jsonld.block_count         — numeric string
 * - jsonld.schema_types        — comma-separated list (or "none")
 * - jsonld.has_organization    — present | absent
 * - jsonld.has_website         — present | absent
 * - jsonld.has_breadcrumb_list — present | absent
 */
class JsonLdFactChecker implements FactCheckerInterface
{
    private const ESSENTIAL_TYPES = [
        'Organization',
        'WebSite',
        'BreadcrumbList',
    ];

    public function name(): string
    {
        return 'jsonld';
    }

    public function check(PageSnapshot $page): array
    {
        $blocks = $page->jsonLdBlocks;
        $blockCount = count($blocks);
        $facts = [];

        $facts[] = new Fact(
            check: $this->name(),
            key: 'jsonld.present',
            status: $blockCount > 0 ? 'present' : 'absent',
            evidence: $blockCount > 0 ? null : 'No JSON-LD blocks found',
        );

        $facts[] = new Fact(
            check: $this->name(),
            key: 'jsonld.block_count',
            status: (string) $blockCount,
            evidence: null,
        );

        $types = $this->collectTypes($blocks);

        $facts[] = new Fact(
            check: $this->name(),
            key: 'jsonld.schema_types',
            status: $types === [] ? 'none' : implode(', ', array_unique($types)),
            evidence: null,
        );

        foreach (self::ESSENTIAL_TYPES as $type) {
            $found = in_array($type, $types, true);

            $facts[] = new Fact(
                check: $this->name(),
                key: 'jsonld.has_'.strtolower($type),
                status: $found ? 'present' : 'absent',
                evidence: $found ? null : "Missing schema type: {$type}",
            );
        }

        return $facts;
    }

    /**
     * Collect all @type values from JSON-LD blocks, handling @graph.
     *
     * @param  list<string>  $blocks
     * @return list<string>
     */
    private function collectTypes(array $blocks): array
    {
        $types = [];

        foreach ($blocks as $block) {
            $data = json_decode((string) $block, true);

            if (! is_array($data)) {
                continue;
            }

            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $node) {
                    if (isset($node['@type'])) {
                        $types[] = is_array($node['@type']) ? implode(', ', $node['@type']) : (string) $node['@type'];
                    }
                }
            } elseif (isset($data['@type'])) {
                $types[] = is_array($data['@type']) ? implode(', ', $data['@type']) : (string) $data['@type'];
            }
        }

        return $types;
    }
}
