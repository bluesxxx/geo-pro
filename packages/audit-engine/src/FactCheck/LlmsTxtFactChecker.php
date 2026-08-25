<?php

namespace GeoPro\AuditEngine\FactCheck;

use GeoPro\AuditEngine\Fact;
use GeoPro\AuditEngine\PageSnapshot;

/**
 * Checks for the presence and health of /llms.txt and /llms-full.txt.
 *
 * Transport is injected as a callable so this package stays dependency-free:
 *
 *   fn(string $url): array{status: int, body: string}
 *
 * The callable MUST NOT throw on HTTP >= 400 (a 404 simply means "absent");
 * it SHOULD throw on transport-level failures (DNS, timeout), which this
 * checker maps to the "error" status.
 */
class LlmsTxtFactChecker implements FactCheckerInterface
{
    /** @var callable(string): array{status: int, body: string} */
    private $get;

    /**
     * @param  callable(string): array{status: int, body: string}  $get
     */
    public function __construct(callable $get)
    {
        $this->get = $get;
    }

    public function name(): string
    {
        return 'llms_txt';
    }

    public function check(PageSnapshot $page): array
    {
        $facts = [];
        $baseUrl = $this->extractBaseUrl($page->url);

        foreach (['llms.txt', 'llms-full.txt'] as $path) {
            $facts = array_merge($facts, $this->checkSingleFile($baseUrl, $path));
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function checkSingleFile(string $baseUrl, string $path): array
    {
        $facts = [];
        $fullUrl = $baseUrl.'/'.$path;
        $fileKey = str_replace(['-', '.'], '_', $path); // llms_txt / llms_full_txt

        try {
            $response = ($this->get)($fullUrl);
            $status = (int) ($response['status'] ?? 0);
            $body = (string) ($response['body'] ?? '');

            $facts[] = new Fact(
                check: $this->name(),
                key: "{$fileKey}.present",
                status: $status >= 200 && $status < 300 ? 'present' : 'absent',
                evidence: ($status >= 200 && $status < 300) ? null : "HTTP {$status}",
            );

            if ($status >= 200 && $status < 300) {
                $parseError = $this->detectParseError($body);
                $sectionCount = $this->countSections($body);

                $facts[] = new Fact(
                    check: $this->name(),
                    key: "{$fileKey}.parse_error",
                    status: $parseError !== null ? 'parse_error' : 'valid',
                    evidence: $parseError,
                );

                $facts[] = new Fact(
                    check: $this->name(),
                    key: "{$fileKey}.section_count",
                    status: (string) $sectionCount,
                    evidence: null,
                );
            }
        } catch (\Throwable $e) {
            $facts[] = new Fact(
                check: $this->name(),
                key: "{$fileKey}.present",
                status: 'error',
                evidence: $e->getMessage(),
            );
        }

        return $facts;
    }

    private function extractBaseUrl(string $url): string
    {
        $parsed = parse_url($url);

        return ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
    }

    private function detectParseError(string $body): ?string
    {
        if (trim($body) === '') {
            return 'empty body';
        }

        return null;
    }

    private function countSections(string $body): int
    {
        return preg_match_all('/^## /m', $body);
    }
}
