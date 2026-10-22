<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use App\Models\Market;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property int|null $market_id
 * @property string $currency
 * @property string $status
 * @property string $payment_status
 * @property string $payment_method
 * @property float $subtotal
 * @property float $tax_total
 * @property float $shipping_total
 * @property float $discount_total
 * @property float $grand_total
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property array<string, mixed>|null $billing_address
 * @property array<string, mixed>|null $shipping_address
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Market|null $market
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'market_id',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'tax_total',
        'shipping_total',
        'discount_total',
        'grand_total',
        'customer_name',
        'customer_email',
        'customer_phone',
        'billing_address',
        'shipping_address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'tax_total' => 'float',
            'shipping_total' => 'float',
            'discount_total' => 'float',
            'grand_total' => 'float',
            'billing_address' => 'array',
            'shipping_address' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getFormattedGrandTotal(): string
    {
        return match ($this->currency) {
            'USD' => '$'.number_format($this->grand_total, 2),
            'GBP' => '£'.number_format($this->grand_total, 2),
            'MXN' => '$'.number_format($this->grand_total, 2).' MXN',
            default => number_format($this->grand_total, 2, ',', '.').' €',
        };
    }
}
