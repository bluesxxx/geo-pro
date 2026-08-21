<?php

namespace GeoPro\AuditEngine;

final class AuditEngine
{
    public function __construct(
        private readonly WebPageFetcherInterface $fetcher,
        private readonly ScorerInterface $scorer = new HeuristicScorer(),
    ) {}

    public function run(string $url): AuditResult
    {
        try {
            $html = $this->fetcher->fetch($url);
        } catch (AuditException $e) {
            return AuditResult::failed($url, $e->getMessage());
        }

        $features = (new FeatureExtractor())->extract($html);
        $scored = $this->scorer->score($features, (string) ($features['text'] ?? ''));

        return new AuditResult(
            score: $scored['score'],
            missingFaq: $scored['missing_faq'],
            missingSchema: $scored['missing_schema'],
            suggestions: $scored['suggestions'],
            rawFeatures: $features,
            url: $url,
        );
    }
}
