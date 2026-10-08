<?php

declare(strict_types=1);

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $product_id
 * @property int|null $market_id
 * @property string $locale
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $message
 * @property bool $is_reviewed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Inquiry extends Model
{
    protected $table = 'shop_inquiries';

    protected $fillable = ['product_id', 'market_id', 'locale', 'name', 'email', 'phone', 'message', 'is_reviewed'];

    protected function casts(): array
    {
        return ['is_reviewed' => 'boolean'];
    }
}
