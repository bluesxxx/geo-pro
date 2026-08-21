<?php

namespace GeoPro\AuditEngine;

interface ScorerInterface
{
    /**
     * Score extracted page features.
     *
     * @param  array<string, mixed>  $features  Output of FeatureExtractor::extract()
     * @return array{score: int, missing_faq: bool, missing_schema: bool, suggestions: list<string>}
     */
    public function score(array $features, string $text): array;
}
