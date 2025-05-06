<?php

namespace Vvb13a\LaravelModelChecker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class Summary extends Model
{
    protected $table = 'checkable_summaries';

    protected $guarded = ['id'];

    protected $casts = [
        'status' => FindingLevel::class,
        'check_counts' => 'array',
        'check_totals' => 'integer',
        'finding_counts' => 'array',
        'finding_totals' => 'integer',
    ];

    public static function initializeLevelCounts(): array
    {
        return collect(FindingLevel::cases())
            ->mapWithKeys(fn(FindingLevel $level) => [$level->value => 0])
            ->all();
    }

    public function checkable(): MorphTo
    {
        return $this->morphTo();
    }
}
