@extends('admin.layout')

@php
    $mainTrans = $product->translations->where('locale', 'es')->first() ?? $product->translations->first();
    $prodName = $mainTrans?->name ?? $product->sku;
    $slug = $mainTrans?->slug ?? '';
    $localization = app(\App\Contracts\Services\LocalizationServiceInterface::class);
    $liveUrl = $slug ? $localization->frontUrl('shop', $slug, $mainTrans?->locale) : null;
@endphp

@section('title', __('admin.page_titles.shop_product_edit'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">
    <x-admin.form-header
        :title="'Editar producto: ' . $prodName . ' (' . $product->sku . ')'"
        description="Actualiza información técnica, existencias, precios por mercado y bloques modulares."
        :backRoute="route('admin.shop.products.index')"
        backLabel="Volver al catálogo"
        :liveUrl="$liveUrl"
        liveLabel="Ver en tienda &nearr;"
    />

    @include('admin.shop.products._form', [
        'product' => $product,
        'categories' => $categories,
        'markets' => $markets,
        'languages' => $languages ?? [],
        'isShop' => $isShop,
    ])
</div>
@endsection
