<?php
class OrderStatus
{
    private const NEXT_STATUSES = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public static function nextStatuses($currentStatus)
    {
        return self::NEXT_STATUSES[$currentStatus] ?? [];
    }

    public static function isAllowed($currentStatus, $newStatus)
    {
        return $currentStatus === $newStatus
            || in_array($newStatus, self::nextStatuses($currentStatus), true);
    }

    public static function paymentStatusForUpdate($paymentMethod, $orderStatus, $requestedPaymentStatus)
    {
        if ($paymentMethod === 'cod' && $orderStatus === 'delivered') {
            return 'completed';
        }

        if ($paymentMethod === 'cod' && $orderStatus === 'cancelled') {
            return 'pending';
        }

        return $requestedPaymentStatus;
    }
}