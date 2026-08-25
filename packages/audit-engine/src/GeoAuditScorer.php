<?php

namespace GeoPro\AuditEngine;

/**
 * Computes a single "GEO readiness" score (0-100) from a fact set.
 *
 * Ported from GEO PRO admin (App\Support\GeoAuditScore) so the public free
 * checkup and the self-hosted platform measure with the same rules:
 * binary facts (present/absent) and llms.txt validity facts are counted;
 * purely numeric facts and informational listings are ignored.
 *
 * Returns null when there is nothing scoreable.
 */
final class GeoAuditScorer
{
    /**
     * @param  list<array{check?: string, key?: string, status?: string, evidence?: string|null}>  $facts
     */
    public static function score(array $facts): ?int
    {
        $passed = 0;
        $scored = 0;

        foreach ($facts as $fact) {
            $status = is_array($fact) ? ($fact['status'] ?? null) : null;

            if (in_array($status, ['present', 'valid'], true)) {
                $passed++;
                $scored++;
            } elseif (in_array($status, ['absent', 'parse_error', 'error'], true)) {
                $scored++;
            }
        }

        return $scored === 0 ? null : (int) round(($passed / $scored) * 100);
    }
}
