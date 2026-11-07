# Verify: المعمارية وتجهيز المشروع · spec 0001 · updated 2026-11-07
_الخطوات مشتقة من معايير القبول في المواصفة 0001. يشغّلها `/check verify`، ويمكن لـ`/test` تثبيت الخطوات المناسبة لاحقًا._

## UI / manual

- [ ] افتح `http://127.0.0.1:8000` بعد تشغيل `php artisan serve`. تأكد من عودة صفحة HTML عامة من مسار Laravel Blade. ثم راجع `resources/views/app.blade.php` و`HandleInertiaRequests` للتأكد من تجهيز جذر Inertia الوسيط للوحة Vue، وأن ملفات البناء موجودة في `public/build` من دون حاجة إلى خادم Node دائم في الإنتاج. → AC-2
- [ ] على بيئة MySQL 8.4 أو أحدث إصدار مدعوم من 8.0، اضبط اتصال قاعدة البيانات من متغيرات البيئة فقط، ثم نفذ الترحيلات. تأكد من نجاحها من دون تعديل شيفرة التطبيق أو إعداداته البرمجية. → AC-3

## Commands

- [ ] على إعداد محلي جديد، انسخ `.env.example` إلى `.env` ثم نفذ `composer install`, `php artisan key:generate`, `php artisan migrate`, `npm ci`, و`npm run build`. تأكد من `DB_CONNECTION=sqlite` ومن ظهور الترحيلات الأساسية كـ`Ran` في `php artisan migrate:status`. → AC-1
- [ ] بعد تهيئة Git، نفذ `git check-ignore .env` وتأكد من تجاهل الملف. راجع `.env.example` للتأكد من أنه يحتوي قيمًا فارغة أو محلية فقط لمتغيرات الخدمة، ولا يحتوي مفاتيح أو كلمات مرور حقيقية. → AC-4

## Acceptance-criteria coverage

- AC-1 covered by local install, migrations, and SQLite status.
- AC-2 covered by Blade response, Inertia shell and middleware, and the Vite production build.
- AC-3 covered by switching the environment connection to supported MySQL without code changes.
- AC-4 covered by Git ignore behavior and environment examples without real credentials.
