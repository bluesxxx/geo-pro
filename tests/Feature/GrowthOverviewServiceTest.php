<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\DistributionChannel;
use App\Services\Admin\Analytics\GrowthOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrowthOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_distribution_failure_alert_links_to_the_unbounded_job_queue(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $author = Author::query()->create(['name' => '总览作者', 'slug' => 'overview-author', 'status' => 'active']);
        $category = Category::query()->create(['name' => '总览分类', 'slug' => 'overview-category', 'status' => 'active']);
        $article = Article::query()->create([
            'title' => '历史失败分发',
            'slug' => 'old-failed-distribution',
            'content' => '正文',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'status' => 'published',
        ]);
        $channel = DistributionChannel::query()->create([
            'name' => '总览渠道',
            'domain' => 'overview.example.com',
            'endpoint_url' => 'https://overview.example.com',
            'status' => 'active',
        ]);
        DB::table('article_distributions')->insert([
            'article_id' => $article->id,
            'distribution_channel_id' => $channel->id,
            'action' => 'publish',
            'status' => 'failed',
            'idempotency_key' => 'overview-old-failure',
            'attempt_count' => 1,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-01 10:00:00',
        ]);

        $overview = app(GrowthOverviewService::class)->snapshot(true);

        $this->assertSame('distribution_failed', $overview['alert']['type']);
        $this->assertSame(route('admin.distribution.jobs', ['status' => 'failed']), $overview['alert']['href']);
    }

    public function test_traffic_snapshot_counts_get_requests_only(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        DB::table('view_logs')->insert([
            [
                'source' => 'local',
                'method' => 'GET',
                'path' => '/',
                'status_code' => 200,
                'ip_address' => '10.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => now(),
            ],
            [
                'source' => 'local',
                'method' => 'HEAD',
                'path' => '/article/head',
                'status_code' => 200,
                'ip_address' => '10.0.0.2',
                'user_agent' => 'ChatGPT-User/1.0',
                'created_at' => now(),
            ],
        ]);

        $overview = app(GrowthOverviewService::class)->snapshot(false);

        $this->assertSame(1, $overview['metrics']['today_visits']);
        $this->assertSame([
            'pv' => 1,
            'unique_ip' => 1,
            'ai' => 0,
        ], $overview['cards']['traffic']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
