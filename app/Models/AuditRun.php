<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditRun extends Model
{
    use HasUuids;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'missing_faq' => 'boolean',
            'missing_schema' => 'boolean',
            'suggestions' => 'array',
            'raw_features' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        // uuid 是独立 CHAR(36) 列，id 保持自增主键。
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
