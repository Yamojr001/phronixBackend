<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'ip_address',
        'user_agent',
        'status',
        'description',
    ];

    protected $casts = [
        'changes' => 'json',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function log(string $action, string $entityType, ?int $entityId = null, ?array $changes = null, ?string $description = null)
    {
        return self::create([
            'admin_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => 'completed',
            'description' => $description,
        ]);
    }
}
