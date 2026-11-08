@php
    $language = $locale === 'ar' ? 'ar' : 'en';
    $categoryName = $product->category ? ($language === 'ar' ? $product->category->name_ar : $product->category->name_en) : null;
    $brandName = $product->brand ? ($language === 'ar' ? $product->brand->name_ar : $product->brand->name_en) : null;
    $themeLabel = $language === 'ar' ? 'تبديل الوضع اللوني' : 'Toggle color theme';
    $backLabel = $language === 'ar' ? 'العودة إلى المتجر' : 'Back to the catalog';
    $visitLabel = $language === 'ar' ? 'زيارة المتجر' : 'Visit store';
    $previewLabel = $language === 'ar' ? 'معاينة خاصة للمشرف' : 'Private administrator preview';
    $imageAlt = fn ($image) => $language === 'ar' ? $image->alt_ar : $image->alt_en;
@endphp
<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" data-bs-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $seoDescription }}">
    @if($preview)<meta name="robots" content="noindex,nofollow">@endif
    <meta name="theme-color" content="#f5f7fa">
    <title>{{ $name }} | {{ config('app.name') }}</title>
    <script>(() => { let saved = null; try { saved = localStorage.getItem('affiliate-theme'); } catch {} const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.dataset.bsTheme = saved || preferred; })();</script>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
<div class="site-shell">
    @if($preview)<div class="preview-banner" role="status">{{ $previewLabel }}</div>@endif
    <header class="site-header"><div class="site-container site-header-inner">
        <a class="brand-mark" href="/{{ $language }}"><span class="brand-symbol" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span><span>{{ config('app.name') }}</span></a>
        <nav class="site-actions" aria-label="{{ $language === 'ar' ? 'إعدادات الموقع' : 'Site preferences' }}"><a class="site-action" lang="{{ $language === 'ar' ? 'en' : 'ar' }}" href="{{ $preview ? route('admin.products.preview', ['locale' => $language === 'ar' ? 'en' : 'ar', 'product' => $product->id]) : '/'.($language === 'ar' ? 'en' : 'ar').'/products/'.$product->slug }}">{{ $language === 'ar' ? 'English' : 'العربية' }}</a><button type="button" class="site-action" data-theme-toggle aria-label="{{ $themeLabel }}" aria-pressed="false">◐</button></nav>
    </div></header>
    <main class="site-container product-page">
        <a class="back-link" href="/{{ $language }}">← {{ $backLabel }}</a>
        <article class="product-detail">
            <div class="product-gallery">
                @if($product->images->isNotEmpty())
                    <figure class="product-main-image"><img src="{{ route('products.media', ['image' => $product->images->first()->id]) }}" alt="{{ $imageAlt($product->images->first()) }}" fetchpriority="high"></figure>
                    @if($product->images->count() > 1)
                        <div class="product-image-strip" aria-label="{{ $language === 'ar' ? 'صور المنتج' : 'Product images' }}">@foreach($product->images->skip(1) as $image)<img src="{{ route('products.media', ['image' => $image->id]) }}" alt="{{ $imageAlt($image) }}" loading="lazy">@endforeach</div>
                    @endif
                @else
                    <div class="product-main-image image-placeholder" aria-hidden="true">◇</div>
                @endif
            </div>
            <div class="product-copy">
                @if($categoryName)<span class="eyebrow">{{ $categoryName }}</span>@endif
                <h1>{{ $name }}</h1>
                @if($brandName)<p class="product-brand">{{ $brandName }}</p>@endif
                <p class="product-price">{{ app(App\Services\Catalog\ProductPriceFormatter::class)->format((int) $product->price_minor, $product->currency, $language) }}</p>
                <div class="product-description">{!! nl2br(e($description)) !!}</div>
                @if($product->tags->isNotEmpty())<ul class="tag-list" aria-label="{{ $language === 'ar' ? 'الوسوم' : 'Tags' }}">@foreach($product->tags as $tag)<li>{{ $language === 'ar' ? $tag->name_ar : $tag->name_en }}</li>@endforeach</ul>@endif
                @unless($preview)<a class="button-primary product-cta" href="{{ route('products.go', ['locale' => $language, 'slug' => $product->slug]) }}" target="_blank" rel="noopener noreferrer">{{ $visitLabel }} <span aria-hidden="true">↗</span></a>@endunless
                @if($preview)<p class="preview-note">{{ $language === 'ar' ? 'رابط الإحالة مخفي في المعاينة.' : 'The affiliate action is disabled in preview.' }}</p>@endif
            </div>
        </article>
    </main>
    <footer class="site-footer"><div class="site-container site-footer-inner"><a class="brand-mark" href="/{{ $language }}"><span class="brand-symbol" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span><span>{{ config('app.name') }}</span></a><p>{{ $language === 'ar' ? 'اختيارات تستحق الاكتشاف' : 'Good finds, thoughtfully gathered' }}</p></div></footer>
</div>
</body>
</html>
