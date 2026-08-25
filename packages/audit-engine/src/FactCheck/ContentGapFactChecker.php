<?php

namespace GeoPro\AuditEngine\FactCheck;

use GeoPro\AuditEngine\Fact;
use GeoPro\AuditEngine\PageSnapshot;

/**
 * Checks whether page content covers a set of GEO-essential topics.
 *
 * Purely textual check — no LLM scoring, no semantic inference. Topics are
 * matched via normalized keyword + heading presence with plural tolerance.
 */
class ContentGapFactChecker implements FactCheckerInterface
{
    /** @var list<string> */
    private const DEFAULT_TOPICS = [
        'about', 'pricing', 'features', 'faq',
        'documentation', 'integrations', 'contact',
        'terms', 'privacy',
    ];

    /** @var list<string> */
    private array $topics;

    /**
     * @param  list<string>|null  $topics  Override topic list (null = defaults)
     */
    public function __construct(?array $topics = null)
    {
        $this->topics = $topics ?? self::DEFAULT_TOPICS;
    }

    public function name(): string
    {
        return 'content_gap';
    }

    public function check(PageSnapshot $page): array
    {
        $facts = [];
        $haystack = $this->buildHaystack($page);

        foreach ($this->topics as $topic) {
            $found = $this->topicPresent($topic, $haystack);

            $facts[] = new Fact(
                check: $this->name(),
                key: "content_gap.{$topic}",
                status: $found ? 'present' : 'absent',
                evidence: $found ? null : "No content matched topic: {$topic}",
            );
        }

        return $facts;
    }

    /**
     * Combine visible text, headings and metadata into a normalized search space.
     *
     * @return array{text: string, headings: string}
     */
    private function buildHaystack(PageSnapshot $page): array
    {
        $title = (string) ($page->metadata['title'] ?? '');
        $description = (string) ($page->metadata['description'] ?? '');

        $mdHeadings = '';
        if (preg_match_all('/^#{1,3}\s+(.+)$/m', $page->text, $m)) {
            $mdHeadings = ' '.implode(' ', $m[1]);
        }

        $text = strtolower(
            strip_tags($page->text).' '.$title.' '.$description.' '.strip_tags($page->html)
        );

        return [
            'text'     => $text,
            'headings' => strtolower($page->headings.' '.$mdHeadings),
        ];
    }

    /**
     * @param  array{text: string, headings: string}  $haystack
     */
    private function topicPresent(string $topic, array $haystack): bool
    {
        $text = $haystack['text'];
        $headings = $haystack['headings'];

        // Heading match is the strongest signal
        if (str_contains($headings, $topic)) {
            return true;
        }

        // Exact keyword in text
        if (str_contains($text, $topic)) {
            return true;
        }

        // Plural/singular tolerance
        $variant = str_ends_with($topic, 's')
            ? rtrim($topic, 's')
            : $topic.'s';

        return str_contains($text, $variant);
    }
}
