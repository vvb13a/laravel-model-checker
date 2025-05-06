<?php

namespace Vvb13a\LaravelModelChecker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Vvb13a\LaravelResponseChecker\Enums\FindingLevel;

class Finding extends Model
{
    protected $table = 'checkable_findings';
    protected $guarded = ['id'];

    protected $casts = [
        'level' => FindingLevel::class,
        'details' => 'array',
        'configuration' => 'array',
    ];

    public function checkable(): MorphTo
    {
        return $this->morphTo();
    }
}
