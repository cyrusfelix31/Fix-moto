<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'user_id',
        'role',
        'action',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $details
     */
    public static function record(User $actor, string $action, ?User $subject = null, array $details = []): self
    {
        $user = $subject ?? ($actor->role === 'admin' ? null : $actor);

        return static::query()->create([
            'admin_id' => $actor->role === 'admin' ? $actor->getKey() : null,
            'user_id' => $user?->getKey(),
            'role' => $user?->role ?? $actor->role,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
