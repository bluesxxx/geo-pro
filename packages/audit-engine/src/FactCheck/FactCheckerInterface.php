<?php

namespace GeoPro\AuditEngine\FactCheck;

use GeoPro\AuditEngine\PageSnapshot;

/**
 * Contract for a single deterministic GEO fact checker.
 */
interface FactCheckerInterface
{
    public function name(): string;

    /**
     * @return list<\GeoPro\AuditEngine\Fact>
     */
    public function check(PageSnapshot $page): array;
}
