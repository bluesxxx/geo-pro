<?php

namespace GeoPro\AuditEngine;

/**
 * Plain-curl fetcher used by the JetSocio audit API (no Laravel available).
 *
 * Includes a compact SSRF guard: only public http(s) targets are allowed.
 * In GEO PRO itself this class is NOT used — the Laravel side implements
 * WebPageFetcherInterface with the SafeOutboundHttpClient gateway instead.
 */
final class CurlWebPageFetcher implements WebPageFetcherInterface
{
    private const USER_AGENT = 'JetSocioAuditBot/1.0 (+https://jetsocio.com)';

    public function __construct(
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxBytes = 2 * 1024 * 1024,
        private readonly int $maxRedirects = 3,
    ) {}

    public function fetch(string $url): string
    {
        $this->assertSafeUrl($url);

        $body = '';
        $write = static function ($ch, string $chunk) use (&$body, $url): int {
            // Stop early once the cap is reached to avoid pulling huge files.
            $body .= $chunk;
            if (strlen($body) > self::MAX_WRITE_BUFFER) {
                return 0; // abort transfer
            }

            return strlen($chunk);
        };

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSeconds),
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
            CURLOPT_ENCODING => '',
            CURLOPT_WRITEFUNCTION => $write,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $html = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($html === false) {
            $detail = $errno === CURLE_ABORTED_BY_CALLBACK
                ? '页面过大，已截断分析（上限 '.$this->maxBytes.' 字节）'
                : ($error !== '' ? $error : '未知网络错误');
            throw new AuditException('抓取失败：'.$detail);
        }
        if ($status >= 400) {
            throw new AuditException('目标页返回 HTTP '.$status);
        }

        return mb_substr($html, 0, $this->maxBytes);
    }

    private const MAX_WRITE_BUFFER = 16 * 1024 * 1024;

    private function assertSafeUrl(string $url): void
    {
        if ($url === '' || $url !== trim($url)) {
            throw new AuditException('URL 格式无效');
        }
        $parts = parse_url($url);
        if (! is_array($parts)) {
            throw new AuditException('URL 格式无效');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new AuditException('仅支持 http/https 链接');
        }
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if ($host === '' || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            throw new AuditException('域名无效');
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new AuditException('目标地址不允许访问');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new AuditException('目标地址不允许访问');
        }

        $addresses = @gethostbynamel($host);
        if (! is_array($addresses) || $addresses === []) {
            throw new AuditException('域名解析失败');
        }
        foreach ($addresses as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                || $this->isReservedIpv4($ip)) {
                throw new AuditException('目标地址不允许访问');
            }
        }
    }

    private function isReservedIpv4(string $ip): bool
    {
        if (str_starts_with($ip, '0.') || str_starts_with($ip, '127.')
            || str_starts_with($ip, '169.254.') || str_starts_with($ip, '192.0.2.')
            || str_starts_with($ip, '198.51.100.') || str_starts_with($ip, '203.0.113.')
            || str_starts_with($ip, '100.64.') || str_starts_with($ip, '192.88.99.')) {
            return true;
        }

        return false;
    }
}
