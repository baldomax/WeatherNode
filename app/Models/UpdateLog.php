<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpdateLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'status',
        'deployed_at',
        'deployed_by',
        'rollback_at',
        'rollback_by',
        'error_message',
        'release_dir',
        'duration_seconds',
        'validation_results',
        'health_check_results',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
        'rollback_at' => 'datetime',
        'validation_results' => 'array',
        'health_check_results' => 'array',
    ];

    /**
     * User who initiated the deployment
     */
    public function deployedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    /**
     * User who initiated the rollback
     */
    public function rollbackByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rollback_by');
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'success' => 'green',
            'failed' => 'red',
            'rolled_back' => 'yellow',
            'pending' => 'gray',
            default => 'gray',
        };
    }
}
