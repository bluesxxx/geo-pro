<?php

namespace GeoPro\AuditEngine;

/**
 * Result of a full audit run.
 *
 * Legacy fields (score / missing_faq / missing_schema / suggestions /
 * raw_features) are preserved for backward compatibility with the original
 * JetSocio Hub frontend and GEO PRO's Laravel audit flow. The new fields
 * carry the deep GeoAudit-style analysis: raw facts, severity-ordered
 * issues, per-category summaries and readiness counters.
 */
final class AuditResult
{
    /**
     * @param  list<string>  $suggestions
     * @param  array<string, mixed>  $rawFeatures
     * @param  list<array{check: string, key: string, status: string, evidence: string|null}>  $facts
     * @param  list<array{id: string, title_key: string, passed: int, total: int, issues: list<array{code: string, severity: string, status: string, evidence: string|null}>}>  $categories
     * @param  list<array{code: string, key: string, check: string, status: string, severity: string, evidence: string|null}>  $issues
     * @param  list<array{code: string, key: string, check: string, status: string, evidence: string|null}>  $passed
     */
    public function __construct(
        public readonly int $score,
        public readonly bool $missingFaq,
        public readonly bool $missingSchema,
        public readonly array $suggestions,
        public readonly array $rawFeatures,
        public readonly string $url,
        public readonly ?string $error = null,
        public readonly array $facts = [],
        public readonly array $categories = [],
        public readonly array $issues = [],
        public readonly array $passed = [],
        public readonly ?int $totalChecks = null,
        public readonly ?int $passedChecks = null,
    ) {}

    public static function failed(string $url, string $message): self
    {
        return new self(
            score: 0,
            missingFaq: false,
            missingSchema: false,
            suggestions: [],
            rawFeatures: [],
            url: $url,
            error: $message,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url'             => $this->url,
            'score'           => $this->score,
            'total_checks'    => $this->totalChecks,
            'passed_checks'   => $this->passedChecks,
            'categories'      => $this->categories,
            'issues'          => $this->issues,
            'passed'          => $this->passed,
            'facts'           => $this->facts,
            // legacy fields (backward compatibility)
            'missing_faq'     => $this->missingFaq,
            'missing_schema'  => $this->missingSchema,
            'suggestions'     => $this->suggestions,
            'raw_features'    => $this->rawFeatures,
            'error'           => $this->error,
        ];
    }
}
