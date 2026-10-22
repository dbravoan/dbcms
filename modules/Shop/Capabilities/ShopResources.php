<?php

declare(strict_types=1);

namespace Modules\Shop\Capabilities;

use App\Core\Capabilities\Resources\CapabilityResource;
use Modules\Shop\Models\Order;

final class ShopResources
{
    public static function descriptor(): CapabilityResource
    {
        return new CapabilityResource(
            key: 'order',
            label: 'Pedido de tienda',
            model: Order::class,
            permission: 'manage:shop',
            description: 'Pedidos: estado, estado de pago y notas internas.',
            fillable: ['status', 'payment_status', 'notes'],
            rules: [
                'status' => ['sometimes', 'required', 'string', 'in:pending,processing,completed,cancelled'],
                'payment_status' => ['sometimes', 'required', 'string', 'in:unpaid,paid,refunded'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            searchable: ['order_number', 'customer_email', 'customer_name'],
            columns: ['id', 'order_number', 'status', 'payment_status', 'grand_total', 'currency', 'customer_email', 'created_at'],
            allowCreate: false,
            orderBy: 'id',
            orderDirection: 'desc',
        );
    }
}
