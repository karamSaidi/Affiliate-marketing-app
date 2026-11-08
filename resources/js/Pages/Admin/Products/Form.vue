<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { onBeforeUnmount } from 'vue';

type Image = { id: number; alt_ar: string; alt_en: string; url: string };
type Product = { id: number; name_ar: string; name_en: string; description_ar: string; description_en: string; slug: string; affiliate_url: string; price: string; currency: string; category_id: number | null; brand_id: number | null; tag_ids: number[]; status: string; lock_version: number; images: Image[] };
type NewImage = { key: string; file: File; alt_ar: string; alt_en: string; url: string };
const props = defineProps<{ product: Product | null; creationKey: string | null; categories: { id: number; name_ar: string; name_en: string }[]; brands: { id: number; name_ar: string; name_en: string }[]; tags: { id: number; name_ar: string; name_en: string }[]; currencies: string[]; locale: 'ar' | 'en' }>();
const ar = computed(() => props.locale === 'ar');
const labels = computed(() => ar.value ? {
    title: props.product ? 'تعديل المنتج' : 'إضافة منتج', save: 'حفظ المسودة', saveChanges: 'حفظ التعديلات', preview: 'معاينة', publish: 'نشر المنتج', hide: 'إخفاء المنتج', status: 'الحالة', draft: 'مسودة', published: 'منشور', hidden: 'مخفي', nameAr: 'الاسم بالعربية', nameEn: 'الاسم بالإنجليزية', descAr: 'الوصف بالعربية', descEn: 'الوصف بالإنجليزية', slug: 'الرابط المختصر', link: 'رابط الإحالة', price: 'السعر', currency: 'العملة', category: 'التصنيف النهائي', brand: 'العلامة التجارية', tags: 'الوسوم', images: 'صور المنتج', imageHelp: 'JPEG أو PNG أو WebP، حتى 5 MB للصورة و10 صور إجمالًا.', altAr: 'النص البديل بالعربية', altEn: 'النص البديل بالإنجليزية', remove: 'إزالة', up: 'تحريك للأعلى', down: 'تحريك للأسفل', required: 'مطلوب للنشر', cancel: 'إلغاء', created: 'مسودة محفوظة', confirmHide: 'هل تريد إخفاء هذا المنتج؟ سيختفي من المتجر العام.', confirmPublish: 'نشر هذا المنتج وإتاحته للجميع؟', errors: 'راجع الحقول المطلوبة قبل النشر.', language: 'العربية', addImages: 'اختيار الصور', imageLabel: (n: number) => `الصورة ${n}`,
} : {
    title: props.product ? 'Edit product' : 'Add product', save: 'Save draft', saveChanges: 'Save changes', preview: 'Preview', publish: 'Publish product', hide: 'Hide product', status: 'Status', draft: 'Draft', published: 'Published', hidden: 'Hidden', nameAr: 'Name in Arabic', nameEn: 'Name in English', descAr: 'Description in Arabic', descEn: 'Description in English', slug: 'URL slug', link: 'Affiliate URL', price: 'Price', currency: 'Currency', category: 'Leaf category', brand: 'Brand', tags: 'Tags', images: 'Product images', imageHelp: 'JPEG, PNG, or WebP, up to 5 MB each and 10 images total.', altAr: 'Alternative text in Arabic', altEn: 'Alternative text in English', remove: 'Remove', up: 'Move up', down: 'Move down', required: 'Required before publishing', cancel: 'Cancel', created: 'Draft saved', confirmHide: 'Hide this product from the public catalog?', confirmPublish: 'Publish this product for everyone?', errors: 'Review required fields before publishing.', language: 'English', addImages: 'Choose images', imageLabel: (n: number) => `Image ${n}`,
});
const form = useForm({ name_ar: props.product?.name_ar ?? '', name_en: props.product?.name_en ?? '', description_ar: props.product?.description_ar ?? '', description_en: props.product?.description_en ?? '', slug: props.product?.slug ?? '', affiliate_url: props.product?.affiliate_url ?? '', price: props.product?.price ?? '', currency: props.product?.currency ?? '', category_id: props.product?.category_id ? String(props.product.category_id) : '', brand_id: props.product?.brand_id ? String(props.product.brand_id) : '', tag_ids: (props.product?.tag_ids ?? []).map(String), lock_version: props.product?.lock_version ?? null as number | null, images: [] as File[], image_alt_ar: [] as string[], image_alt_en: [] as string[], image_sequence: [] as string[], delete_image_ids: [] as number[], image_alt_existing: {} as Record<number, { ar: string; en: string }> });
let creationKey = props.creationKey;
if (creationKey) {
    try {
        creationKey = sessionStorage.getItem('product-create-key') || creationKey;
        sessionStorage.setItem('product-create-key', creationKey);
    } catch { /* The server key remains valid for this form session. */ }
}
const oldImages = ref<Image[]>(props.product?.images ? [...props.product.images] : []);
const newImages = ref<NewImage[]>([]);
const imageOrder = ref<Array<{ kind: 'old' | 'new'; key: number | string }>>(oldImages.value.map((image) => ({ kind: 'old', key: image.id })));
const slugManuallyEdited = ref(!!props.product?.slug);
watch(() => form.name_en, (name) => {
    if (!slugManuallyEdited.value) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});
const totalImages = computed(() => oldImages.value.length + newImages.value.length);
const publishable = computed(() => !!props.product && props.product.status !== 'published');
function setFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    const room = Math.max(0, 10 - totalImages.value);
    const accepted = files.slice(0, room);
    const added = accepted.map((file) => ({ key: crypto.randomUUID(), file, alt_ar: '', alt_en: '', url: URL.createObjectURL(file) }));
    newImages.value.push(...added);
    imageOrder.value.push(...added.map((image) => ({ kind: 'new' as const, key: image.key })));
    form.images = newImages.value.map((image) => image.file);
    form.image_alt_ar = newImages.value.map((image) => image.alt_ar);
    form.image_alt_en = newImages.value.map((image) => image.alt_en);
    input.value = '';
}
function syncUploadFields() {
    form.images = newImages.value.map((image) => image.file);
    form.image_alt_ar = newImages.value.map((image) => image.alt_ar);
    form.image_alt_en = newImages.value.map((image) => image.alt_en);
}
function moveImage(index: number, direction: -1 | 1) {
    const target = index + direction;
    const list = imageOrder.value;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
}
function removeImage(index: number) {
    const entry = imageOrder.value.splice(index, 1)[0];
    if (entry.kind === 'old') {
        const oldIndex = oldImages.value.findIndex((image) => image.id === entry.key);
        const image = oldImages.value.splice(oldIndex, 1)[0];
        form.delete_image_ids.push(image.id);
    } else {
        const newIndex = newImages.value.findIndex((image) => image.key === entry.key);
        const [image] = newImages.value.splice(newIndex, 1);
        URL.revokeObjectURL(image.url);
        syncUploadFields();
    }
}
function submit() {
    syncUploadFields();
    form.transform((data) => ({ ...data, category_id: data.category_id || null, brand_id: data.brand_id || null, tag_ids: data.tag_ids, image_sequence: imageOrder.value.map((entry) => entry.kind === 'old' ? `existing:${entry.key}` : `new:${newImages.value.findIndex((image) => image.key === entry.key)}`) }));
    if (props.product) form.put(`/${props.locale}/admin/products/${props.product.id}`, { forceFormData: true });
    else form.post(`/${props.locale}/admin/products`, { forceFormData: true, headers: { 'Idempotency-Key': creationKey ?? '' }, onSuccess: () => { try { sessionStorage.removeItem('product-create-key'); } catch {} } });
}
function publish() {
    if (!props.product || !window.confirm(labels.value.confirmPublish)) return;
    router.post(`/${props.locale}/admin/products/${props.product.id}/publish`, { lock_version: props.product.lock_version });
}
function hide() {
    if (!props.product || !window.confirm(labels.value.confirmHide)) return;
    router.post(`/${props.locale}/admin/products/${props.product.id}/hide`, { lock_version: props.product.lock_version, confirmed: true });
}
function error(key: string): string | undefined { return (form.errors as Record<string, string>)[key]; }
onBeforeUnmount(() => newImages.value.forEach((image) => URL.revokeObjectURL(image.url)));
</script>

<template>
    <Head :title="labels.title" />
    <div class="admin-shell" :dir="ar ? 'rtl' : 'ltr'">
        <header class="site-header"><div class="site-container site-header-inner"><Link class="brand-mark" :href="`/${locale}/admin/products`"><span class="brand-symbol">A</span><span>{{ $page.props.appName || 'Affiliate' }}</span></Link><div class="site-actions"><a class="site-action" :href="ar ? '/en/admin/products' : '/ar/admin/products'">{{ ar ? 'English' : 'العربية' }}</a><button type="button" class="site-action" data-theme-toggle :aria-label="ar ? 'تبديل الوضع' : 'Toggle color theme'">◐</button></div></div></header>
        <main class="site-container section-block">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4"><div><p class="eyebrow mb-2">{{ ar ? 'إدارة الكتالوج' : 'Catalog management' }}</p><h1 class="h2 mb-1 text-body-emphasis">{{ labels.title }}</h1><p v-if="product" class="text-secondary mb-0">{{ labels.status }}: {{ labels[product.status as 'draft' | 'published' | 'hidden'] }}</p></div><Link class="button-secondary" :href="`/${locale}/admin/products`">{{ labels.cancel }}</Link></div>
            <div v-if="$page.props.flash?.success" class="alert alert-success" role="status">{{ $page.props.flash.success }}</div>
            <form class="admin-panel p-3 p-md-4" @submit.prevent="submit">
                <div v-if="Object.keys(form.errors).length" class="alert alert-danger" role="alert"><strong>{{ labels.errors }}</strong><ul class="mb-0 mt-2"><li v-for="(message, key) in form.errors" :key="key">{{ message }}</li></ul></div>
                <div class="row g-4">
                    <section class="col-12" :aria-labelledby="'content-heading'"><h2 id="content-heading" class="h5 section-title">{{ ar ? 'المحتوى' : 'Content' }}</h2><div class="row g-3">
                        <div class="col-lg-6"><label class="form-label" for="name_ar">{{ labels.nameAr }}</label><input id="name_ar" v-model="form.name_ar" class="form-control" maxlength="255" :aria-invalid="!!error('name_ar')"><div v-if="error('name_ar')" class="field-error">{{ error('name_ar') }}</div></div>
                        <div class="col-lg-6"><label class="form-label" for="name_en">{{ labels.nameEn }}</label><input id="name_en" v-model="form.name_en" class="form-control" maxlength="255" :aria-invalid="!!error('name_en')"></div>
                        <div class="col-lg-6"><label class="form-label" for="description_ar">{{ labels.descAr }}</label><textarea id="description_ar" v-model="form.description_ar" class="form-control" rows="5" maxlength="10000"></textarea><small class="text-secondary">{{ form.description_ar.length }}/10,000</small></div>
                        <div class="col-lg-6"><label class="form-label" for="description_en">{{ labels.descEn }}</label><textarea id="description_en" v-model="form.description_en" class="form-control" rows="5" maxlength="10000"></textarea><small class="text-secondary">{{ form.description_en.length }}/10,000</small></div>
                    </div></section>
                    <section class="col-12" :aria-labelledby="'commerce-heading'"><h2 id="commerce-heading" class="h5 section-title">{{ ar ? 'السعر والتصنيف' : 'Price and classification' }}</h2><div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="slug">{{ labels.slug }}</label><input id="slug" v-model="form.slug" class="form-control" maxlength="255" dir="ltr" @input="slugManuallyEdited = true"><small class="text-secondary">/{{ locale }}/products/{{ form.slug || 'product-name' }}</small></div>
                        <div class="col-md-6"><label class="form-label" for="affiliate_url">{{ labels.link }}</label><input id="affiliate_url" v-model="form.affiliate_url" class="form-control" type="text" inputmode="url" maxlength="2048" placeholder="https://" dir="ltr"></div>
                        <div class="col-md-4"><label class="form-label" for="price">{{ labels.price }}</label><input id="price" v-model="form.price" class="form-control" inputmode="decimal" pattern="(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,4})?" placeholder="0.0000" dir="ltr"></div>
                        <div class="col-md-4"><label class="form-label" for="currency">{{ labels.currency }}</label><select id="currency" v-model="form.currency" class="form-select"><option value="">{{ ar ? 'اختر العملة' : 'Select currency' }}</option><option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option></select></div>
                        <div class="col-md-4"><label class="form-label" for="category_id">{{ labels.category }}</label><select id="category_id" v-model="form.category_id" class="form-select"><option value="">{{ ar ? 'اختر تصنيفًا' : 'Select a category' }}</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ ar ? category.name_ar : category.name_en }}</option></select></div>
                        <div class="col-md-6"><label class="form-label" for="brand_id">{{ labels.brand }}</label><select id="brand_id" v-model="form.brand_id" class="form-select"><option value="">{{ ar ? 'بلا علامة' : 'No brand' }}</option><option v-for="brand in brands" :key="brand.id" :value="String(brand.id)">{{ ar ? brand.name_ar : brand.name_en }}</option></select></div>
                        <div class="col-md-6"><label class="form-label" for="tag_ids">{{ labels.tags }}</label><select id="tag_ids" v-model="form.tag_ids" class="form-select" multiple size="3"><option v-for="tag in tags" :key="tag.id" :value="String(tag.id)">{{ ar ? tag.name_ar : tag.name_en }}</option></select></div>
                    </div></section>
                    <section class="col-12" :aria-labelledby="'images-heading'"><h2 id="images-heading" class="h5 section-title">{{ labels.images }} <span class="text-secondary fs-6">({{ totalImages }}/10)</span></h2><p class="text-secondary">{{ labels.imageHelp }}</p><label class="button-secondary upload-button" for="product-images">＋ {{ labels.addImages }}<input id="product-images" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" multiple :disabled="totalImages >= 10" @change="setFiles"></label>
                        <div class="image-list mt-3">
                            <article v-for="(entry, index) in imageOrder" :key="`${entry.kind}-${entry.key}`" class="image-row">
                                <template v-if="entry.kind === 'old'"><img :src="oldImages.find((image) => image.id === entry.key)?.url" :alt="oldImages.find((image) => image.id === entry.key)?.alt_ar || ''"><div class="image-fields"><label class="visually-hidden" :for="`old-ar-${entry.key}`">{{ labels.altAr }}, {{ labels.imageLabel(index + 1) }}</label><input :id="`old-ar-${entry.key}`" :value="form.image_alt_existing[Number(entry.key)]?.ar ?? oldImages.find((image) => image.id === entry.key)?.alt_ar" class="form-control" :placeholder="labels.altAr" @input="(event) => { const image = oldImages.find((candidate) => candidate.id === entry.key)!; (form.image_alt_existing[image.id] ||= { ar: image.alt_ar, en: image.alt_en }).ar = (event.target as HTMLInputElement).value }"><label class="visually-hidden" :for="`old-en-${entry.key}`">{{ labels.altEn }}, {{ labels.imageLabel(index + 1) }}</label><input :id="`old-en-${entry.key}`" :value="form.image_alt_existing[Number(entry.key)]?.en ?? oldImages.find((image) => image.id === entry.key)?.alt_en" class="form-control" :placeholder="labels.altEn" @input="(event) => { const image = oldImages.find((candidate) => candidate.id === entry.key)!; (form.image_alt_existing[image.id] ||= { ar: image.alt_ar, en: image.alt_en }).en = (event.target as HTMLInputElement).value }"></div></template>
                                <template v-else><img :src="newImages.find((image) => image.key === entry.key)?.url" :alt="newImages.find((image) => image.key === entry.key)?.alt_ar || ''"><div class="image-fields"><label class="visually-hidden" :for="`new-ar-${entry.key}`">{{ labels.altAr }}, {{ labels.imageLabel(index + 1) }}</label><input :id="`new-ar-${entry.key}`" v-model="newImages.find((image) => image.key === entry.key)!.alt_ar" class="form-control" :placeholder="labels.altAr" @input="syncUploadFields"><label class="visually-hidden" :for="`new-en-${entry.key}`">{{ labels.altEn }}, {{ labels.imageLabel(index + 1) }}</label><input :id="`new-en-${entry.key}`" v-model="newImages.find((image) => image.key === entry.key)!.alt_en" class="form-control" :placeholder="labels.altEn" @input="syncUploadFields"></div></template>
                                <div class="image-actions"><button type="button" class="button-secondary button-compact" :aria-label="labels.up" :disabled="index === 0" @click="moveImage(index, -1)">↑</button><button type="button" class="button-secondary button-compact" :aria-label="labels.down" :disabled="index + 1 === imageOrder.length" @click="moveImage(index, 1)">↓</button><button type="button" class="button-secondary button-compact text-danger" @click="removeImage(index)">{{ labels.remove }}</button></div>
                            </article>
                        </div>
                        <div v-if="!totalImages" class="empty-state py-4 mt-3"><p>{{ ar ? 'أضف صورة واحدة على الأقل قبل النشر.' : 'Add at least one image before publishing.' }}</p></div>
                    </section>
                </div>
                <div class="form-footer mt-4 pt-4"><Link class="button-secondary" :href="`/${locale}/admin/products`">{{ labels.cancel }}</Link><div class="d-flex flex-wrap gap-2"><Link v-if="product" class="button-secondary" :href="`/${locale}/admin/products/${product.id}/preview`" target="_blank" rel="noopener noreferrer">{{ labels.preview }} ↗</Link><button class="button-primary" type="submit" :disabled="form.processing">{{ form.processing ? (ar ? 'جارٍ الحفظ…' : 'Saving…') : (product ? labels.saveChanges : labels.save) }}</button><button v-if="publishable" class="button-primary" type="button" :disabled="form.isDirty" @click="publish">{{ labels.publish }}</button><button v-if="product && product.status !== 'hidden'" class="button-secondary" type="button" @click="hide">{{ labels.hide }}</button></div></div>
                <p class="text-secondary small mt-3 mb-0">{{ labels.required }}: {{ ar ? 'الاسمان والوصفان، رابط صالح، سعر وعملة، تصنيف فعال، وصورة بنص بديل باللغتين.' : 'Both names and descriptions, a valid URL, price and currency, an active category, and images with alt text in both languages.' }}</p>
            </form>
        </main>
    </div>
</template>
