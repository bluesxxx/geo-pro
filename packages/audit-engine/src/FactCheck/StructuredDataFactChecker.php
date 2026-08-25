<?php

namespace GeoPro\AuditEngine\FactCheck;

use GeoPro\AuditEngine\Fact;
use GeoPro\AuditEngine\PageSnapshot;

/**
 * Checks for rich-result structured data schema types (GEO relevant).
 *
 * Produces one fact per tracked schema type:
 *   structured_data.has_faq_schema      — present | absent
 *   structured_data.has_howto_schema    — present | absent
 *   structured_data.has_product_schema  — present | absent
 *   structured_data.has_article_schema  — present | absent
 */
class StructuredDataFactChecker implements FactCheckerInterface
{
    /**
     * Schema family → fact suffix. Each family matches any of its @type
     * spellings (e.g. FAQPage / NewsArticle / BlogPosting).
     */
    private const TRACKED_TYPES = [
        'faq_schema'     => ['FAQ', 'FAQPage'],
        'howto_schema'   => ['HowTo', 'HowToPage'],
        'product_schema' => ['Product'],
        'article_schema' => ['Article', 'NewsArticle', 'BlogPosting', 'TechArticle'],
    ];

    public function name(): string
    {
        return 'structured_data';
    }

    public function check(PageSnapshot $page): array
    {
        $types = array_flip($this->collectTypes($page->jsonLdBlocks));

        $facts = [];
        foreach (self::TRACKED_TYPES as $factSuffix => $aliases) {
            $found = false;
            foreach ($aliases as $alias) {
                if (isset($types[$alias])) {
                    $found = true;
                    break;
                }
            }

            $facts[] = new Fact(
                check: $this->name(),
                key: "structured_data.has_{$factSuffix}",
                status: $found ? 'present' : 'absent',
                evidence: $found ? null : 'Missing schema type: '.implode('/', $aliases),
            );
        }

        return $facts;
    }

    /**
     * Collect all @type values from JSON-LD blocks, handling @graph and arrays.
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
                        foreach ((array) $node['@type'] as $t) {
                            $types[] = (string) $t;
                        }
                    }
                }
            } elseif (isset($data['@type'])) {
                foreach ((array) $data['@type'] as $t) {
                    $types[] = (string) $t;
                }
            }
        }

        return $types;
    }
}
