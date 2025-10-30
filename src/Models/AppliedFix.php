<?php

namespace NDEstates\LaravelModelSchemaChecker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppliedFix extends Model
{
    protected $fillable = [
        'check_result_id',
        'user_id',
        'fix_title',
        'fix_description',
        'file_path',
        'improvement_class',
        'fix_data',
        'can_rollback',
        'rollback_data',
        'applied_at',
    ];

    protected $casts = [
        'fix_data' => 'array',
        'rollback_data' => 'array',
        'applied_at' => 'datetime',
        'can_rollback' => 'boolean',
    ];

    /**
     * Get the check result that this fix belongs to
     */
    public function checkResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class);
    }

    /**
     * Get the user that applied this fix
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }

    /**
     * Check if this fix can be rolled back
     */
    public function canRollback(): bool
    {
        return $this->can_rollback && !empty($this->rollback_data);
    }
}