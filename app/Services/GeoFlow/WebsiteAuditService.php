<?php

namespace App\Services\GeoFlow;

use App\Services\Outbound\OutboundRequestBlockedException;
use App\Services\Outbound\OutboundRequestFailedException;
use App\Services\Outbound\SafeOutboundHttpClient;
use App\Services\Outbound\SafeOutboundRequest;
use GeoPro\AuditEngine\AuditException;
use GeoPro\AuditEngine\WebPageFetcherInterface;
use Illuminate\Support\Facades\Http;

/**
 * GEO PRO 本地体检抓取器：通过 SSRF 白名单网关（SafeOutboundHttpClient）
 * 抓取目标网页，实现 WebPageFetcherInterface 供 audit-engine 消费。
 */
final class WebsiteAuditService implements WebPageFetcherInterface
{
    private const USER_AGENT = 'GEO-PRO-Audit/1.0 (+https://jetsocio.com)';

    public function __construct(
        private readonly SafeOutboundHttpClient $safeHttp,
    ) {}

    public function fetch(string $url): string
    {
        $request = Http::timeout(12)
            ->connectTimeout(6)
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]);

        $safe = new SafeOutboundRequest($this->safeHttp, $request, 10 * 1024 * 1024, 3);

        try {
            $response = $safe->get($url);
        } catch (OutboundRequestBlockedException|OutboundRequestFailedException $exception) {
            throw new AuditException($this->humanize($exception->getMessage()));
        }

        if ($response->status() >= 400) {
            throw new AuditException('目标页返回 HTTP '.$response->status());
        }

        // 分析只需要正文前 500KB；抓取上限 10MB 由网关保证。
        return mb_substr((string) $response->body(), 0, 500000);
    }

    private function humanize(string $code): string
    {
        return match ($code) {
            'unsafe_address', 'mapped_address', 'ambiguous_ip', 'dns_resolution_failed',
            'invalid_address', 'localhost_forbidden', 'private_target_forbidden' => '目标地址不允许访问（SSRF 防护）',
            'response_too_large' => '目标页响应过大，无法分析',
            'redirect_limit_exceeded' => '目标页重定向过多',
            'invalid_scheme' => '仅支持 http/https 链接',
            'invalid_url', 'control_character', 'ambiguous_authority', 'invalid_host',
            'invalid_port', 'userinfo_forbidden', 'fragment_forbidden', 'invalid_request_policy' => '链接格式无效',
            default => '抓取失败：'.$code,
        };
    }
}
