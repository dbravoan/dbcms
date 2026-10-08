@extends('admin.layout')

@section('title', __('admin.page_titles.shop_product_create'))

@section('content')
<div class="space-y-6 font-mono text-xs">
    <x-admin.form-header
        title="Crear nuevo producto"
        description="Completa la ficha técnica, precios multi-mercado, existencias y bloques modulares."
        :backRoute="route('admin.shop.products.index')"
        backLabel="Volver al catálogo"
    />

    @include('admin.shop.products._form', [
        'product' => null,
        'categories' => $categories,
        'markets' => $markets,
        'languages' => $languages ?? [],
        'isShop' => $isShop,
    ])
</div>
@endsection
