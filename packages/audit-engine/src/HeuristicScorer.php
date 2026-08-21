<?php

namespace GeoPro\AuditEngine;

/**
 * Deterministic heuristic GEO scorer, ported from the geo-saas audit hook.
 *
 * Score breakdown (mirrors lib/llm.ts heuristicScore):
 *   base 40 + H1(20) + JSON-LD Schema(15) + FAQPage(10)
 *   + length bonus(>800 bytes: +10) / penalty(<200 bytes: -10)
 *   - buzzwords(min(count*3, 15))
 *   clamped to 0-100.
 */
final class HeuristicScorer implements ScorerInterface
{
    public function score(array $features, string $text): array
    {
        $score = 40;
        if (! empty($features['has_h1'])) {
            $score += 20;
        }
        if (! empty($features['has_schema'])) {
            $score += 15;
        }
        if (! empty($features['has_faq_schema'])) {
            $score += 10;
        }

        $len = strlen((string) $text);
        if ($len > 800) {
            $score += 10;
        } elseif ($len < 200) {
            $score -= 10;
        }

        $buzz = (int) ($features['buzzword_count'] ?? 0);
        $score -= min($buzz * 3, 15);

        $score = max(0, min(100, (int) round($score)));

        $missingFaq = empty($features['has_faq_schema']);
        $missingSchema = empty($features['has_schema']);

        $suggestions = [];
        if (empty($features['has_h1'])) {
            $suggestions[] = '补充一个清晰的 <h1> 主标题，帮助 AI 理解页面主题。';
        }
        if ($missingSchema) {
            $suggestions[] = '添加 JSON-LD 结构化数据（如 Article / Organization），提升被 AI 直接引用的概率。';
        }
        if ($missingFaq) {
            $suggestions[] = '增加 FAQPage 结构化数据，覆盖用户常见提问，利于 AI 直接作答时引用你的内容。';
        }
        if ($buzz > 0) {
            $suggestions[] = '减少过度营销词汇，改用客观、权威的表述，AI 更愿意引用。';
        }
        if ($len < 400) {
            $suggestions[] = '扩充正文内容，提供更具体的统计数据、年份与案例，增强可信度。';
        }
        if ($suggestions === []) {
            $suggestions[] = '整体结构良好，可进一步补充原创数据与权威引用以拉开差距。';
        }

        return [
            'score' => $score,
            'missing_faq' => $missingFaq,
            'missing_schema' => $missingSchema,
            'suggestions' => array_slice($suggestions, 0, 5),
        ];
    }
}
