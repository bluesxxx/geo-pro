<?php

namespace GeoPro\AuditEngine;

use GeoPro\AuditEngine\FactCheck\ContentGapFactChecker;
use GeoPro\AuditEngine\FactCheck\JsonLdFactChecker;
use GeoPro\AuditEngine\FactCheck\LlmsTxtFactChecker;
use GeoPro\AuditEngine\FactCheck\MetaTagFactChecker;
use GeoPro\AuditEngine\FactCheck\StructuredDataFactChecker;

/**
 * GEO audit engine — ported from GEO PRO admin's GeoAudit module so the free
 * public checkup measures with the same deterministic rules as the platform:
 * meta tags, JSON-LD, rich-result schema types, /llms.txt health and content
 * coverage. Score = % of passed binary checks (GEO readiness).
 */
final class AuditEngine
{
    /** fact checker name → frontend category id */
    private const CATEGORY_MAP = [
        'meta'            => 'meta',
        'jsonld'          => 'structured',
        'structured_data' => 'structured',
        'llms_txt'        => 'ai_ready',
        'content_gap'     => 'content',
    ];

    private const SCOREABLE_STATUSES = ['present', 'valid', 'absent', 'parse_error', 'error'];

    public function __construct(
        private readonly WebPageFetcherInterface $fetcher,
        private readonly ScorerInterface $scorer = new HeuristicScorer(), // legacy fallback only
        private readonly ?\Closure $textProbe = null, // injectable llms.txt probe (tests)
    ) {}

    public function run(string $url): AuditResult
    {
        try {
            $html = $this->fetcher->fetch($url);
        } catch (AuditException $e) {
            return AuditResult::failed($url, $e->getMessage());
        }

        $snapshot = PageSnapshot::fromHtml($url, $html);
        $facts = $this->collectFacts($snapshot);

        $readiness = GeoAuditScorer::score($facts);

        if ($readiness === null) {
            // Nothing scoreable — fall back to the legacy heuristic score.
            $features = (new FeatureExtractor())->extract($html);
            $scored = $this->scorer->score($features, (string) ($features['text'] ?? ''));
            $readiness = (int) $scored['score'];
        }

        $presented = (new IssuePresenter())->build($facts);
        $categories = $this->buildCategories($facts, $presented['issues']);

        [$passedChecks, $totalChecks] = $this->countScoreable($facts);

        return new AuditResult(
            score: $readiness,
            missingFaq: ! in_array('present', $this->statusesForKey($facts, 'structured_data.has_faq_schema'), true),
            missingSchema: ! $this->anyPresent($facts, ['jsonld.present', 'structured_data.has_faq_schema', 'structured_data.has_howto_schema', 'structured_data.has_product_schema', 'structured_data.has_article_schema']),
            suggestions: [],
            rawFeatures: (new FeatureExtractor())->extract($html),
            url: $url,
            facts: $facts,
            categories: $categories,
            issues: $presented['issues'],
            passed: $presented['passed'],
            totalChecks: $totalChecks,
            passedChecks: $passedChecks,
        );
    }

    /**
     * Run every fact checker against the snapshot and flatten results.
     *
     * @return list<array{check: string, key: string, status: string, evidence: string|null}>
     */
    private function collectFacts(PageSnapshot $snapshot): array
    {
        $checkers = [
            new MetaTagFactChecker(),
            new JsonLdFactChecker(),
            new StructuredDataFactChecker(),
            new LlmsTxtFactChecker($this->textProbe ?? fn (string $url): array => $this->probeText($url)),
            new ContentGapFactChecker(),
        ];

        $all = [];
        foreach ($checkers as $checker) {
            foreach ($checker->check($snapshot) as $fact) {
                $all[] = $fact->toArray();
            }
        }

        return $all;
    }

    /**
     * Group facts into user-facing categories ("几类问题"), each with a
     * passed/total tally and its severity-ordered issue codes.
     *
     * @param  list<array{check: string, key: string, status: string, evidence: string|null}>  $facts
     * @param  list<array{code: string, key: string, check: string, status: string, severity: string, evidence: string|null}>  $issues
     * @return list<array{id: string, title_key: string, passed: int, total: int, issues: list<array{code: string, severity: string, status: string, evidence: string|null}>}>
     */
    private function buildCategories(array $facts, array $issues): array
    {
        $buckets = [];
        foreach (self::CATEGORY_MAP as $check => $categoryId) {
            $buckets[$categoryId] ??= [];
            foreach ($facts as $fact) {
                if (($fact['check'] ?? '') === $check) {
                    $buckets[$categoryId][] = $fact;
                }
            }
        }

        $issueCodesByCategory = [];
        foreach ($issues as $issue) {
            $categoryId = self::CATEGORY_MAP[$issue['check']] ?? null;
            if ($categoryId !== null) {
                $issueCodesByCategory[$categoryId][$issue['code']] = $issue;
            }
        }

        $categories = [];
        foreach ($buckets as $categoryId => $categoryFacts) {
            [$passed, $total] = $this->tally($categoryFacts);

            $categoryIssues = [];
            foreach ($issueCodesByCategory[$categoryId] ?? [] as $issue) {
                $categoryIssues[] = [
                    'code'     => $issue['code'],
                    'severity' => $issue['severity'],
                    'status'   => $issue['status'],
                    'evidence' => $issue['evidence'],
                ];
            }

            $categories[] = [
                'id'        => $categoryId,
                'title_key' => 'r_cat_'.$categoryId,
                'passed'    => $passed,
                'total'     => $total,
                'issues'    => $categoryIssues,
            ];
        }

        return $categories;
    }

    /**
     * @param  list<array{check: string, key: string, status: string, evidence: string|null}>  $facts
     * @return array{0: int, 1: int}
     */
    private function tally(array $facts): array
    {
        $passed = 0;
        $total = 0;

        foreach ($facts as $fact) {
            $status = (string) ($fact['status'] ?? '');
            if (in_array($status, self::SCOREABLE_STATUSES, true)) {
                $total++;
                if (in_array($status, ['present', 'valid'], true)) {
                    $passed++;
                }
            }
        }

        return [$passed, $total];
    }

    /**
     * @param  list<array{check: string, key: string, status: string, evidence: string|null}>  $facts
     * @return array{0: int, 1: int} [passedChecks, totalChecks]
     */
    private function countScoreable(array $facts): array
    {
        $passed = 0;
        $total = 0;

        foreach ($facts as $fact) {
            $status = (string) ($fact['status'] ?? '');
            if (in_array($status, ['present', 'valid'], true)) {
                $passed++;
                $total++;
            } elseif (in_array($status, ['absent', 'parse_error', 'error'], true)) {
                $total++;
            }
        }

        return [$passed, $total];
    }

    /**
     * @param  list<array{check: string, key: string, status: string, evidence: string|null}>  $facts
     * @return list<string>
     */
    private function statusesForKey(array $facts, string $key): array
    {
        $statuses = [];
        foreach ($facts as $fact) {
            if (($fact['key'] ?? '') === $key) {
                $statuses[] = (string) ($fact['status'] ?? '');
            }
        }

        return $statuses;
    }

    /**
     * @param  list<array{check: string, key: string, status: string, evidence: string|null}>  $facts
     * @param  list<string>  $keys
     */
    private function anyPresent(array $facts, array $keys): bool
    {
        foreach ($keys as $key) {
            if (in_array('present', $this->statusesForKey($facts, $key), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Plain-curl text probe for llms.txt files (no redirects, hard timeout).
     *
     * The URL always derives from the already-validated target host, so this
     * inherits the fetcher's SSRF decision; still enforce scheme sanity here.
     *
     * @return array{status: int, body: string}
     */
    private function probeText(string $url): array
    {
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new AuditException('仅支持 http/https 链接');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'JetSocioAuditBot/1.0 (+https://jetsocio.com)',
            CURLOPT_HTTPHEADER => ['Accept: text/plain,*/*;q=0.8'],
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new AuditException($error !== '' ? $error : '网络错误');
        }

        return ['status' => $status, 'body' => (string) $body];
    }
}
