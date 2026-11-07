@php
$language = app()->getLocale() === 'ar' ? 'ar' : 'en';
$copy = [
    'ar' => [
        'tagline' => 'اختيارات تستحق الاكتشاف',
        'headline' => 'اعثر على ما يجعل يومك أفضل.',
        'intro' => 'كتالوج مختار بعناية، يجمع منتجات مميزة في مكان واحد. تصفح بهدوء، وقارن التفاصيل، ثم انتقل إلى المتجر بثقة.',
        'browse' => 'استكشف المنتجات',
        'how' => 'كيف يعمل المتجر',
        'featured' => 'اكتشف الكتالوج',
        'featured_intro' => 'نضيف المنتجات وتفاصيلها تباعًا، لتجد ما يناسبك بسهولة.',
        'empty_title' => 'نجهز اختياراتنا الأولى',
        'empty_body' => 'لا توجد منتجات منشورة الآن. عد قريبًا لاكتشاف أحدث الإضافات.',
        'curated_title' => 'اختيارات واضحة',
        'curated_body' => 'تفاصيل المنتج وسعره وصوره في صفحة واحدة.',
        'simple_title' => 'تصفح على راحتك',
        'simple_body' => 'ابحث واكتشف المنتجات من هاتفك أو حاسوبك.',
        'direct_title' => 'انتقال مباشر',
        'direct_body' => 'عندما تختار منتجًا، نوصلك إلى متجره الخارجي.',
        'footer' => 'اكتشف منتجات تستحق مكانًا في يومك.',
        'language' => 'English',
        'theme' => 'تبديل الوضع اللوني',
    ],
    'en' => [
        'tagline' => 'Good finds, thoughtfully gathered',
        'headline' => 'Find something that makes your day.',
        'intro' => 'A considered catalog of useful products in one place. Browse at your pace, compare the details, then visit the store with confidence.',
        'browse' => 'Explore products',
        'how' => 'How it works',
        'featured' => 'Explore the catalog',
        'featured_intro' => 'We are adding products and details so you can find the right fit.',
        'empty_title' => 'Our first picks are on the way',
        'empty_body' => 'There are no published products yet. Come back soon to see what is new.',
        'curated_title' => 'Clear product details',
        'curated_body' => 'See product information, price, and images together.',
        'simple_title' => 'Browse your way',
        'simple_body' => 'Explore products from your phone or computer.',
        'direct_title' => 'Go straight to the store',
        'direct_body' => 'When you find a product, we take you to its external store.',
        'footer' => 'Discover products that earn a place in your day.',
        'language' => 'العربية',
        'theme' => 'Toggle color theme',
    ],
][$language];
@endphp
<!doctype html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $copy['intro'] }}">
    <meta name="theme-color" content="#f5f7fa">
    <title>{{ config('app.name') }} | {{ $copy['tagline'] }}</title>
    <script>
        (() => {
            let saved = null;
            try { saved = localStorage.getItem('affiliate-theme'); } catch {}
            const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.bsTheme = saved || preferred;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
<div class="site-shell">
    <header class="site-header">
        <div class="site-container site-header-inner">
            <a class="brand-mark" href="{{ $language === 'ar' ? '/ar' : '/en' }}" aria-label="{{ config('app.name') }}">
                <span class="brand-symbol" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span>
                <span>{{ config('app.name') }}</span>
            </a>
            <nav class="site-actions" aria-label="{{ $language === 'ar' ? 'إعدادات الموقع' : 'Site preferences' }}">
                <a class="site-action" href="{{ $language === 'ar' ? '/en' : '/ar' }}" lang="{{ $language === 'ar' ? 'en' : 'ar' }}">{{ $copy['language'] }}</a>
                <button class="site-action" type="button" data-theme-toggle aria-label="{{ $copy['theme'] }}" aria-pressed="false">
                    <span aria-hidden="true">◐</span><span class="visually-hidden">{{ $copy['theme'] }}</span>
                </button>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero site-container" aria-labelledby="hero-title">
            <div class="hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow"><svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="m12 2 2.55 6.95L22 12l-7.45 3.05L12 22l-2.55-6.95L2 12l7.45-3.05L12 2Z"/></svg>{{ $copy['tagline'] }}</span>
                    <h1 id="hero-title">{{ $copy['headline'] }}</h1>
                    <p>{{ $copy['intro'] }}</p>
                    <div class="hero-actions">
                        <a class="button-primary" href="#catalog">{{ $copy['browse'] }} <span aria-hidden="true">↗</span></a>
                        <a class="button-secondary" href="#about">{{ $copy['how'] }}</a>
                    </div>
                </div>
                <div class="hero-art" aria-hidden="true">
                <span class="hero-art-label">{{ $copy['tagline'] }}</span>
                </div>
            </div>
        </section>

        <section class="section-block site-container" id="catalog" aria-labelledby="catalog-title">
            <div class="section-heading">
                <div>
                    <h2 id="catalog-title">{{ $copy['featured'] }}</h2>
                    <p>{{ $copy['featured_intro'] }}</p>
                </div>
            </div>
            <div class="empty-state" role="status">
                <span class="empty-state-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="m12 2 2.55 6.95L22 12l-7.45 3.05L12 22l-2.55-6.95L2 12l7.45-3.05L12 2Z"/></svg></span>
                <h3>{{ $copy['empty_title'] }}</h3>
                <p>{{ $copy['empty_body'] }}</p>
            </div>
        </section>

        <section class="section-block site-container" id="about" aria-label="{{ $copy['how'] }}">
            <div class="value-grid">
                <article class="value-card"><h3>{{ $copy['curated_title'] }}</h3><p>{{ $copy['curated_body'] }}</p></article>
                <article class="value-card"><h3>{{ $copy['simple_title'] }}</h3><p>{{ $copy['simple_body'] }}</p></article>
                <article class="value-card"><h3>{{ $copy['direct_title'] }}</h3><p>{{ $copy['direct_body'] }}</p></article>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-container site-footer-inner">
            <a class="brand-mark" href="{{ $language === 'ar' ? '/ar' : '/en' }}"><span class="brand-symbol" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span><span>{{ config('app.name') }}</span></a>
            <p>{{ $copy['footer'] }}</p>
        </div>
    </footer>
</div>
</body>
</html>
