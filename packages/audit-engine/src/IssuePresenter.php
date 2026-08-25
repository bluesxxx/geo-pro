<?php

namespace GeoPro\AuditEngine;

/**
 * Translates raw facts into severity-ordered issues + passed checks.
 *
 * Ported from GEO PRO admin (App\Support\GeoAuditIssuePresenter). Each issue
 * carries a stable machine `code` (fact key with dots/dashes → underscores)
 * which the JetSocio Hub frontend maps to localized copy via its i18n dict.
 *
 * Unknown fact codes fall back gracefully — the UI never breaks when new
 * checkers are added.
 */
final class IssuePresenter
{
    public const SEV_CRITICAL = 'critical';
    public const SEV_WARNING = 'warning';
    public const SEV_INFO = 'info';

    /** fact key → severity. Anything not listed defaults to warning. */
    private const SEVERITY_MAP = [
        'meta.title' => self::SEV_CRITICAL,
        'meta.description' => self::SEV_WARNING,
        'meta.canonical' => self::SEV_INFO,
        'meta.robots' => self::SEV_WARNING,
        'meta.h1' => self::SEV_WARNING,
        'meta.og_title' => self::SEV_INFO,
        'meta.og_image' => self::SEV_INFO,
        'meta.twitter_card' => self::SEV_INFO,
        'jsonld.present' => self::SEV_CRITICAL,
        'jsonld.schema_types' => self::SEV_INFO,
        'jsonld.has_organization' => self::SEV_WARNING,
        'jsonld.has_website' => self::SEV_INFO,
        'jsonld.has_breadcrumblist' => self::SEV_INFO,
        'llms_txt.present' => self::SEV_CRITICAL,
        'llms_full_txt.present' => self::SEV_INFO,
        'llms_txt.parse_error' => self::SEV_WARNING,
        'llms_full_txt.parse_error' => self::SEV_WARNING,
        'structured_data.has_faq_schema' => self::SEV_WARNING,
        'structured_data.has_howto_schema' => self::SEV_INFO,
        'structured_data.has_product_schema' => self::SEV_INFO,
        'structured_data.has_article_schema' => self::SEV_WARNING,
    ];

    /** Statuses that represent a failing condition worth surfacing as an issue. */
    private const ISSUE_STATUSES = ['absent', 'error', 'parse_error', 'none'];

    /**
     * @param  list<array{check?: string, key?: string, status?: string, evidence?: string|null}>  $facts
     * @return array{
     *   issues: list<array{code: string, key: string, check: string, status: string, severity: string, evidence: string|null}>,
     *   passed: list<array{code: string, key: string, check: string, status: string, evidence: string|null}>
     * }
     */
    public static function build(array $facts): array
    {
        $issues = [];
        $passed = [];

        foreach ($facts as $fact) {
            $key = is_array($fact) ? (string) ($fact['key'] ?? '') : '';
            if ($key === '') {
                continue;
            }

            $status = is_array($fact) ? (string) ($fact['status'] ?? 'unknown') : 'unknown';
            $evidence = is_array($fact) ? (isset($fact['evidence']) ? (string) $fact['evidence'] : null) : null;
            $check = is_array($fact) ? (string) ($fact['check'] ?? '') : '';

            if (in_array($status, ['present', 'valid'], true)) {
                $passed[] = [
                    'code' => self::normalizeKey($key),
                    'key' => $key,
                    'check' => $check,
                    'status' => $status,
                    'evidence' => $evidence,
                ];
                continue;
            }

            // Numeric statuses (block_count, section_count) and informational
            // free-text statuses (e.g. a schema type listing) are not issues.
            if (is_numeric($status) || ! in_array($status, self::ISSUE_STATUSES, true)) {
                continue;
            }

            $issues[] = [
                'code' => self::normalizeKey($key),
                'key' => $key,
                'check' => $check,
                'status' => $status,
                'severity' => self::SEVERITY_MAP[strtolower($key)] ?? self::SEV_WARNING,
                'evidence' => $evidence,
            ];
        }

        // Order: critical → warning → info
        $weight = [self::SEV_CRITICAL => 0, self::SEV_WARNING => 1, self::SEV_INFO => 2];
        usort($issues, fn ($a, $b) => ($weight[$a['severity']] ?? 9) <=> ($weight[$b['severity']] ?? 9));

        return ['issues' => $issues, 'passed' => $passed];
    }

    private static function normalizeKey(string $key): string
    {
        return strtolower(str_replace(['.', '-'], '_', $key));
    }
}
