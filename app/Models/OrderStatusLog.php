<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    protected $fillable = [
        'order_id',
        'previous_status',
        'new_status',
        'updated_by_type',
        'updated_by_id',
        'reason',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that this log belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to filter by order
     */
    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('new_status', $status);
    }

    /**
     * Scope to filter by updater type
     */
    public function scopeByUpdaterType($query, string $type)
    {
        return $query->where('updated_by_type', $type);
    }

    /**
     * Create a log entry for status change
     * 
     * @param Order $order
     * @param string $previousStatus
     * @param string $newStatus
     * @param string $updatedByType
     * @param int|null $updatedById
     * @param string|null $reason
     * @param array|null $metadata
     * @return static
     */
    public static function logStatusChange(
        Order $order,
        string $previousStatus,
        string $newStatus,
        string $updatedByType = 'system',
        ?int $updatedById = null,
        ?string $reason = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'order_id' => $order->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'updated_by_type' => $updatedByType,
            'updated_by_id' => $updatedById,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Get formatted timeline for an order
     */
    public static function getOrderTimeline(int $orderId): array
    {
        return self::where('order_id', $orderId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($log) {
                return [
                    'previous_status' => $log->previous_status,
                    'new_status' => $log->new_status,
                    'updated_by' => $log->updated_by_type,
                    'reason' => $log->reason,
                    'timestamp' => $log->created_at->toIso8601String(),
                ];
            })
            ->toArray();
    }
}
