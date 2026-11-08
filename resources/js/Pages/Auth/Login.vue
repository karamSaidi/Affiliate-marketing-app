<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ locale: 'ar' | 'en' }>();
const isArabic = props.locale === 'ar';
const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="isArabic ? 'دخول المشرفين' : 'Administrator sign in'" />
    <main class="admin-shell d-flex align-items-center py-5" :dir="isArabic ? 'rtl' : 'ltr'">
        <div class="container" style="max-width: 30rem">
            <a class="brand-mark mb-4" :href="`/${locale}`"><span class="brand-symbol">{{ $page.props.appName?.[0] || 'A' }}</span><span>{{ $page.props.appName || 'Affiliate' }}</span></a>
            <section class="admin-panel p-4 p-md-5">
                <p class="eyebrow mb-2">{{ isArabic ? 'مساحة آمنة' : 'Secure workspace' }}</p>
                <h1 class="h3 mb-4 text-body-emphasis">{{ isArabic ? 'دخول المشرفين' : 'Administrator sign in' }}</h1>
                <form @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="email">{{ isArabic ? 'البريد الإلكتروني' : 'Email address' }}</label>
                        <input id="email" v-model="form.email" class="form-control" type="email" name="email" autocomplete="username" required autofocus :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'email-error' : undefined">
                        <div v-if="form.errors.email" id="email-error" class="status-error small mt-1" role="alert">{{ form.errors.email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">{{ isArabic ? 'كلمة المرور' : 'Password' }}</label>
                        <input id="password" v-model="form.password" class="form-control" type="password" name="password" autocomplete="current-password" required :aria-invalid="!!form.errors.password" :aria-describedby="form.errors.password ? 'password-error' : undefined">
                        <div v-if="form.errors.password" id="password-error" class="status-error small mt-1" role="alert">{{ form.errors.password }}</div>
                    </div>
                    <label class="form-check mb-4">
                        <input v-model="form.remember" class="form-check-input" type="checkbox" name="remember">
                        <span class="form-check-label">{{ isArabic ? 'تذكرني' : 'Remember me' }}</span>
                    </label>
                    <button class="button-primary w-100" type="submit" :disabled="form.processing">{{ form.processing ? (isArabic ? 'جارٍ الدخول…' : 'Signing in…') : (isArabic ? 'دخول' : 'Sign in') }}</button>
                </form>
            </section>
        </div>
    </main>
</template>
