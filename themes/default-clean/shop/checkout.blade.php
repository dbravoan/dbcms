@extends('themes.default-clean.layouts.app')

@section('title', __('Complete Order'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
    <h1 class="text-3xl font-bold">{{ __('Complete Order') }}</h1>
    <p class="text-slate-500 text-sm">{{ __('Enter the shipping details and confirm your preferred payment method.') }}</p>
    <form method="POST" action="{{ route('shop.checkout.process') }}" class="space-y-4 text-sm">
        @csrf
        <input type="text" name="customer_name" required placeholder="{{ __('Full Name') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <input type="email" name="customer_email" required placeholder="tu@email.com" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <input type="hidden" name="payment_method" value="bank_transfer">
        <input type="text" name="city" required placeholder="{{ __('City') }}">
        <input type="text" name="postal_code" required placeholder="{{ __('Postal Code') }}">
        <input type="text" name="country" required placeholder="{{ __('Country') }}">
        <input type="text" name="address" required placeholder="{{ __('Street and Number') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <button type="submit" class="btn-neon">{{ __('Complete Order') }} &rarr;</button>
    </form>
</div>
@endsection
