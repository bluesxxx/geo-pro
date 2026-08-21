<?php

namespace GeoPro\AuditEngine;

final class AuditResult
{
    /**
     * @param  list<string>  $suggestions
     * @param  array<string, mixed>  $rawFeatures
     */
    public function __construct(
        public readonly int $score,
        public readonly bool $missingFaq,
        public readonly bool $missingSchema,
        public readonly array $suggestions,
        public readonly array $rawFeatures,
        public readonly string $url,
        public readonly ?string $error = null,
    ) {}

    public static function failed(string $url, string $message): self
    {
        return new self(
            score: 0,
            missingFaq: false,
            missingSchema: false,
            suggestions: [],
            rawFeatures: [],
            url: $url,
            error: $message,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'score' => $this->score,
            'missing_faq' => $this->missingFaq,
            'missing_schema' => $this->missingSchema,
            'suggestions' => $this->suggestions,
            'raw_features' => $this->rawFeatures,
            'error' => $this->error,
        ];
    }
}
