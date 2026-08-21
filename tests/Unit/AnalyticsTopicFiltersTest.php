<?php

namespace Tests\Unit;

use App\Services\Admin\Analytics\AiVisibilityAnalyticsFilter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTopicFiltersTest extends TestCase
{
    public function test_ai_visibility_filter_supports_presets_dimensions_and_safe_custom_dates(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $preset = AiVisibilityAnalyticsFilter::fromRequest([
            'ai_preset' => '90d',
            'ai_keyword' => ' GEOFlow ',
            'ai_provider' => 'deepseek_analysis',
        ]);
        $custom = AiVisibilityAnalyticsFilter::fromRequest([
            'ai_preset' => 'custom',
            'ai_date_from' => '2026-08-05',
            'ai_date_to' => '2026-07-30',
        ]);

        $this->assertSame('2026-05-05', $preset->dateFrom->toDateString());
        $this->assertSame('GEOFlow', $preset->keyword);
        $this->assertSame('deepseek_analysis', $preset->provider);
        $this->assertSame('2026-07-30', $custom->dateFrom->toDateString());
        $this->assertSame('2026-08-02', $custom->dateTo->toDateString());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
