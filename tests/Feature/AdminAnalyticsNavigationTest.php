<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Task;
use App\Models\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAnalyticsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_growth_center_exposes_overview_and_topic_pages(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.analytics'));

        $response
            ->assertOk()
            ->assertSee(route('admin.analytics.content'), false)
            ->assertSee(route('admin.analytics.traffic'), false)
            ->assertSee(route('admin.analytics.ai-visibility'), false)
            ->assertDontSee(route('admin.analytics.leads'), false)
            ->assertSee(route('admin.analytics.distribution'), false)
            ->assertDontSee('data-analytics-log-chart', false)
            ->assertDontSee('data-ai-visibility-series', false)
            ->assertDontSee('data-analytics-health-grid', false);

        $this->assertSame(3, substr_count($response->getContent(), 'lg:col-span-6'));
        $this->assertStringNotContainsString('lg:col-span-5', $response->getContent());
        $this->assertStringNotContainsString('lg:col-span-7', $response->getContent());

        foreach (['content', 'traffic', 'ai-visibility', 'distribution'] as $page) {
            $this->get(route("admin.analytics.{$page}"))->assertOk();
        }
    }

    public function test_overview_shows_core_metrics_and_only_the_highest_priority_alert(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        DB::table('view_logs')->insert([
            'source' => 'local',
            'method' => 'GET',
            'path' => '/',
            'status_code' => 200,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.analytics'));

        $response
            ->assertOk()
            ->assertSee(__('admin.analytics.overview.metrics.today_visits'))
            ->assertSee(__('admin.analytics.overview.metrics.published_7d'))
            ->assertSee(__('admin.analytics.overview.metrics.brand_visibility_60d'))
            ->assertSee(__('admin.analytics.overview.alerts.ai_unconfigured.title'));

        $this->assertSame(1, substr_count($response->getContent(), 'data-analytics-priority-alert'));
    }

    public function test_content_report_does_not_render_dashboard_health_modules(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.analytics.content'));

        $response
            ->assertOk()
            ->assertDontSee('data-analytics-health-grid', false)
            ->assertDontSee(__('admin.dashboard.task_health'))
            ->assertDontSee(__('admin.dashboard.material_health'))
            ->assertDontSee(__('admin.dashboard.ai_health'))
            ->assertDontSee(__('admin.dashboard.url_import_health'));
    }

    public function test_regular_admin_can_open_business_reports_but_cannot_open_distribution_report(): void
    {
        $admin = $this->admin('admin');

        $overview = $this->actingAs($admin, 'admin')->get(route('admin.analytics'));

        $overview
            ->assertOk()
            ->assertDontSee(route('admin.analytics.distribution'), false);

        foreach (['content', 'traffic', 'ai-visibility'] as $page) {
            $this->get(route("admin.analytics.{$page}"))->assertOk();
        }

        $this->get(route('admin.analytics.distribution'))->assertForbidden();
    }

    public function test_legacy_growth_center_queries_redirect_to_the_matching_topic_page(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.analytics', [
                'log_preset' => '60d',
                'log_source' => 'local',
                'article_id' => 9,
            ]))
            ->assertRedirect(route('admin.analytics.traffic', [
                'log_preset' => '60d',
                'log_source' => 'local',
                'article_id' => 9,
            ]));

        $this->get(route('admin.analytics', [
            'preset' => '30d',
            'category_id' => 3,
        ]))->assertRedirect(route('admin.analytics.content', [
            'preset' => '30d',
            'category_id' => 3,
        ]));
    }

    public function test_legacy_channel_filter_redirects_to_the_protected_distribution_report(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.analytics', [
                'preset' => '30d',
                'channel_id' => 7,
                'article_id' => 9,
            ]))
            ->assertRedirect(route('admin.analytics.distribution', [
                'preset' => '30d',
                'channel_id' => 7,
                'article_id' => 9,
            ]));

        $admin->update(['role' => 'admin']);

        $this->actingAs($admin->fresh(), 'admin')
            ->get(route('admin.analytics', ['channel_id' => 7]))
            ->assertForbidden();
    }

    public function test_growth_reports_require_admin_authentication(): void
    {
        foreach (['admin.analytics', 'admin.analytics.content', 'admin.analytics.traffic', 'admin.analytics.ai-visibility', 'admin.analytics.distribution'] as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('admin.login'));
        }
    }

    public function test_distribution_report_has_a_safe_empty_state_when_a_dependency_table_is_missing(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('distribution_channels');
        Schema::enableForeignKeyConstraints();

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.analytics.distribution'))
            ->assertOk()
            ->assertSee(__('admin.analytics.no_data'));
    }

    public function test_old_task_failures_trigger_the_content_failed_alert(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $task = Task::query()->create(['name' => '历史任务', 'status' => 'active']);
        $run = TaskRun::query()->create([
            'task_id' => $task->id,
            'status' => 'failed',
            'error_message' => '历史错误',
        ]);
        $run->forceFill(['created_at' => Carbon::parse('2026-01-01 10:00:00')])->saveQuietly();

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee(__('admin.analytics.overview.alerts.content_failed.title', ['count' => 1]));
    }

    private function admin(string $role = 'super_admin'): Admin
    {
        return Admin::query()->create([
            'username' => 'analytics_navigation_admin',
            'password' => 'secret-123',
            'email' => 'analytics-navigation@example.com',
            'display_name' => 'Analytics Navigation Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
