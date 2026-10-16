<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Models\Market;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Shop\Contracts\InquiryServiceInterface;
use Modules\Shop\DTOs\InquirySubmission;
use Modules\Shop\Models\Inquiry;
use Modules\Shop\Models\Product;

final class InquiryService implements InquiryServiceInterface
{
    public function submit(InquirySubmission $data, Market $market, string $locale): Inquiry
    {
        abort_unless((bool) module_setting('shop', 'enable_inquiry', true), 404);
        Product::active()->forMarket($market)->findOrFail($data->productId);

        return DB::transaction(function () use ($data, $market, $locale): Inquiry {
            $inquiry = Inquiry::create([
                'product_id' => $data->productId, 'market_id' => $market->getKey(), 'locale' => $locale,
                'name' => $data->name, 'email' => $data->email, 'phone' => $data->phone, 'message' => $data->message,
            ]);
            DB::afterCommit(function () use ($inquiry): void {
                try {
                    app(ModuleNotificationServiceInterface::class)->notify('shop', 'inquiry', 'Nueva solicitud de presupuesto', 'Solicitud #'.$inquiry->getKey(), route('admin.shop.inquiries.index'), ['inquiry_id' => $inquiry->getKey()], false);
                    $recipient = (string) module_setting('shop', 'notification_email', '');
                    if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
                        Mail::raw('Solicitud #'.$inquiry->getKey()."\n".$inquiry->name."\n".$inquiry->email."\n".$inquiry->phone."\n".$inquiry->message, function ($message) use ($recipient): void {
                            $message->to($recipient)->subject('Nueva solicitud de presupuesto');
                        });
                    }
                } catch (\Throwable $error) {
                    Log::warning('Shop inquiry notification failed', ['inquiry_id' => $inquiry->getKey(), 'exception' => $error::class]);
                }
            });

            return $inquiry;
        });
    }
}
