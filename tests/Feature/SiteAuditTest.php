<?php

namespace Tests\Feature;

use App\Models\AuditRun;
use GeoPro\AuditEngine\AuditException;
use GeoPro\AuditEngine\WebPageFetcherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteAuditTest extends TestCase
{
    use RefreshDatabase;

    private static function sampleHtml(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>示例页面</title>
<meta name="description" content="示例描述。">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article"}</script>
<script type="application/ld+json">{"@type":"FAQPage"}</script>
</head>
<body>
<h1>示例主标题</h1>
HTML
            .'<p>'.str_repeat('GEO 内容工程实践与案例分享，让可信知识进入 AI 答案。', 30).'</p>'
            .'</body></html>';
    }

    public function test_audit_form_page_renders(): void
    {
        $this->get(route('site.audit.form'))
            ->assertOk()
            ->assertSee('你的网站，AI 会引用吗？')
            ->assertSee(route('site.audit.run'), false);
    }

    public function test_audit_run_requires_a_valid_http_url(): void
    {
        $this->post(route('site.audit.run'), [])
            ->assertSessionHasErrors('url');

        $this->post(route('site.audit.run'), ['url' => 'ftp://example.com/file'])
            ->assertSessionHasErrors('url');

        $this->post(route('site.audit.run'), ['url' => 'not-a-url at all'])
            ->assertSessionHasErrors('url');
    }

    public function test_audit_run_normalizes_scheme_and_renders_report(): void
    {
        $fetcher = \Mockery::mock(WebPageFetcherInterface::class);
        $fetcher->shouldReceive('fetch')
            ->once()
            ->with('https://example.com')
            ->andReturn(self::sampleHtml());
        $this->app->instance(WebPageFetcherInterface::class, $fetcher);

        $this->post(route('site.audit.run'), ['url' => 'example.com'])
            ->assertRedirect();

        $run = AuditRun::query()->first();
        $this->assertNotNull($run);
        $this->assertSame(AuditRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('https://example.com', $run->url);
        $this->assertSame(95, $run->score);
        $this->assertFalse($run->missing_faq);
        $this->assertFalse($run->missing_schema);
        $this->assertNotEmpty($run->suggestions);
        $this->assertTrue($run->raw_features['has_h1']);

        $this->get(route('site.audit.show', $run))
            ->assertOk()
            ->assertSee('95', false)
            ->assertSee('example.com')
            ->assertSee('H1 主标题')
            ->assertSee('JSON-LD 结构化数据');
    }

    public function test_audit_run_handles_fetch_failure(): void
    {
        $fetcher = \Mockery::mock(WebPageFetcherInterface::class);
        $fetcher->shouldReceive('fetch')
            ->once()
            ->andThrow(new AuditException('目标地址不允许访问（SSRF 防护）'));
        $this->app->instance(WebPageFetcherInterface::class, $fetcher);

        $this->post(route('site.audit.run'), ['url' => 'https://127.0.0.1/'])
            ->assertRedirect();

        $run = AuditRun::query()->first();
        $this->assertNotNull($run);
        $this->assertSame(AuditRun::STATUS_FAILED, $run->status);
        $this->assertSame('目标地址不允许访问（SSRF 防护）', $run->error_message);

        $this->get(route('site.audit.show', $run))
            ->assertOk()
            ->assertSee('未能完成体检')
            ->assertSee('目标地址不允许访问（SSRF 防护）');
    }

    public function test_audit_run_records_short_content_penalty(): void
    {
        $fetcher = \Mockery::mock(WebPageFetcherInterface::class);
        $fetcher->shouldReceive('fetch')
            ->once()
            ->andReturn('<html><head><title>空白页</title></head><body><p>hi</p></body></html>');
        $this->app->instance(WebPageFetcherInterface::class, $fetcher);

        $this->post(route('site.audit.run'), ['url' => 'https://example.com/bare'])
            ->assertRedirect();

        $run = AuditRun::query()->first();
        $this->assertSame(30, $run->score);
        $this->assertTrue($run->missing_faq);
        $this->assertTrue($run->missing_schema);
    }

    public function test_audit_run_is_rate_limited(): void
    {
        $fetcher = \Mockery::mock(WebPageFetcherInterface::class);
        $fetcher->shouldReceive('fetch')
            ->andReturn(self::sampleHtml());
        $this->app->instance(WebPageFetcherInterface::class, $fetcher);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('site.audit.run'), ['url' => "https://example.com/$i"])
                ->assertRedirect();
        }

        $this->post(route('site.audit.run'), ['url' => 'https://example.com/too-many'])
            ->assertStatus(429);
    }
}
