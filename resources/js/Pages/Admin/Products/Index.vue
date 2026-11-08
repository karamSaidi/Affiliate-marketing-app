<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Product = { id: number; name_ar: string | null; name_en: string | null; status: string; updated_at: string | null; created_at: string | null; price: string | null; currency: string | null; image_id: number | null };
type Pagination = { data: Product[]; current_page: number; last_page: number; total: number; links: { url: string | null; label: string; active: boolean }[] };
const props = defineProps<{ products: Pagination; filters: { q: string; status: string }; locale: 'ar' | 'en' }>();
const ar = computed(() => props.locale === 'ar');
const query = ref(props.filters.q);
const status = ref(props.filters.status);
let timer: ReturnType<typeof setTimeout> | undefined;
watch([query, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(`/${props.locale}/admin/products`, { q: query.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true, preserveScroll: true }), 250);
});
const labels: Record<string, { ar: string; en: string }> = {
    draft: { ar: 'مسودة', en: 'Draft' }, published: { ar: 'منشور', en: 'Published' }, hidden: { ar: 'مخفي', en: 'Hidden' },
};
</script>

<template>
    <Head :title="ar ? 'المنتجات' : 'Products'" />
    <div class="admin-shell" :dir="ar ? 'rtl' : 'ltr'">
        <header class="site-header">
            <div class="site-container site-header-inner">
                <Link class="brand-mark" :href="`/${locale}/admin/products`"><span class="brand-symbol">A</span><span>{{ $page.props.appName || 'Affiliate' }}</span></Link>
                <div class="site-actions"><a class="site-action" :href="ar ? '/en/admin/products' : '/ar/admin/products'">{{ ar ? 'English' : 'العربية' }}</a><button type="button" class="site-action" data-theme-toggle :aria-label="ar ? 'تبديل الوضع' : 'Toggle color theme'">◐</button></div>
            </div>
        </header>
        <main class="site-container section-block">
            <div v-if="$page.props.flash?.success" class="alert alert-success" role="status">{{ $page.props.flash.success }}</div>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                <div><p class="eyebrow mb-2">{{ ar ? 'إدارة الكتالوج' : 'Catalog management' }}</p><h1 class="h2 mb-1 text-body-emphasis">{{ ar ? 'المنتجات' : 'Products' }}</h1><p class="text-secondary mb-0">{{ ar ? `${products.total} منتج` : `${products.total} products` }}</p></div>
                <Link class="button-primary" :href="`/${locale}/admin/products/create`">{{ ar ? 'إضافة منتج' : 'Add product' }} <span aria-hidden="true">＋</span></Link>
            </div>
            <section class="admin-panel p-3 p-md-4" :aria-label="ar ? 'قائمة المنتجات' : 'Product list'">
                <div class="row g-3 mb-3">
                    <div class="col-md-8"><label class="form-label" for="product-search">{{ ar ? 'بحث بالاسم' : 'Search by name' }}</label><input id="product-search" v-model="query" class="form-control" type="search" :placeholder="ar ? 'ابحث بالعربية أو الإنجليزية' : 'Search Arabic or English names'"></div>
                    <div class="col-md-4"><label class="form-label" for="product-status">{{ ar ? 'الحالة' : 'Status' }}</label><select id="product-status" v-model="status" class="form-select"><option value="">{{ ar ? 'كل الحالات' : 'All statuses' }}</option><option value="draft">{{ ar ? 'مسودة' : 'Draft' }}</option><option value="published">{{ ar ? 'منشور' : 'Published' }}</option><option value="hidden">{{ ar ? 'مخفي' : 'Hidden' }}</option></select></div>
                </div>
                <div v-if="products.data.length" class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col">{{ ar ? 'المنتج' : 'Product' }}</th><th scope="col">{{ ar ? 'السعر' : 'Price' }}</th><th scope="col">{{ ar ? 'الحالة' : 'Status' }}</th><th scope="col">{{ ar ? 'آخر تعديل' : 'Updated' }}</th><th scope="col"><span class="visually-hidden">{{ ar ? 'إجراءات' : 'Actions' }}</span></th></tr></thead>
                        <tbody><tr v-for="product in products.data" :key="product.id">
                            <td><div class="d-flex align-items-center gap-3"><img v-if="product.image_id" :src="`/media/products/${product.image_id}`" class="product-thumb" alt="" loading="lazy"><span v-else class="product-thumb d-grid place-items-center" aria-hidden="true">◇</span><div><strong class="d-block">{{ product.name_ar || (ar ? 'بلا اسم' : 'Untitled') }}</strong><small class="text-secondary">{{ product.name_en }}</small></div></div></td>
                            <td>{{ product.price ? `${product.price} ${product.currency}` : '—' }}</td>
                            <td><span class="status-pill" :class="`status-${product.status}`">{{ labels[product.status]?.[locale] || product.status }}</span></td>
                            <td><time v-if="product.updated_at" :datetime="product.updated_at">{{ new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(product.updated_at)) }}</time></td>
                            <td class="text-end"><Link class="button-secondary button-compact" :href="`/${locale}/admin/products/${product.id}/edit`">{{ ar ? 'فتح' : 'Open' }}</Link></td>
                        </tr></tbody>
                    </table>
                </div>
                <div v-else class="empty-state py-5"><span class="empty-state-icon" aria-hidden="true">◇</span><h2 class="h5 mt-3">{{ filters.q || filters.status ? (ar ? 'لا توجد نتائج مطابقة' : 'No matching products') : (ar ? 'ابدأ بإضافة أول منتج' : 'Add your first product') }}</h2><p>{{ ar ? 'يمكنك حفظ المنتج كمسودة ومراجعته قبل النشر.' : 'Save products as drafts and review them before publishing.' }}</p><Link v-if="!filters.q && !filters.status" class="button-primary mt-3" :href="`/${locale}/admin/products/create`">{{ ar ? 'إضافة منتج' : 'Add product' }}</Link></div>
                <nav v-if="products.last_page > 1" class="d-flex flex-wrap gap-2 justify-content-center mt-4" :aria-label="ar ? 'صفحات النتائج' : 'Result pages'"><template v-for="link in products.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="button-secondary button-compact" :class="{ 'page-current': link.active }" v-html="link.label" /><span v-else class="button-secondary button-compact page-disabled" v-html="link.label" /></template></nav>
            </section>
        </main>
    </div>
</template>
