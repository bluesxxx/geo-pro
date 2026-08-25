<?php

namespace GeoPro\AuditEngine;

/**
 * A single factual finding from a fact checker.
 * Contains no score or grade — only the status of a measurable condition.
 *
 * Statuses:
 *   present | valid          — check passed
 *   absent | none            — expected thing is missing
 *   error | parse_error      — check could not be completed / content malformed
 *   numeric string           — informational counter (block_count, section_count)
 *   other free text          — informational listing (e.g. schema types found)
 */
final class Fact
{
    public function __construct(
        public readonly string $check,
        public readonly string $key,
        public readonly string $status,
        public readonly ?string $evidence = null,
    ) {}

    /** @return array{check: string, key: string, status: string, evidence: string|null} */
    public function toArray(): array
    {
        return [
            'check'    => $this->check,
            'key'      => $this->key,
            'status'   => $this->status,
            'evidence' => $this->evidence,
        ];
    }
}
