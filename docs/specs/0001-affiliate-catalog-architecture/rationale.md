# سجل قرار معمارية متجر الإحالة

**Date**: 2026-11-07

## Context

> ⚠️ Premise note: بدأت باختيار الكاش والطوابير قبل قياس حمل حقيقي، مع أن الاستخدام المتوقع صغير في البداية. إضافة Redis وطابور قاعدة بيانات تزيد متطلبات الاستضافة ومخاطر انقطاع الشبكة. أبقيت Redis لأنك طلبت الكاش واخترت Upstash، وأبقيت الطابور لأنك اخترت تشغيله عبر cron. يجب أن تعمل صفحات المتجر من قاعدة البيانات عند تعطل الكاش، ويجب أن يقتصر الطابور على المهام القصيرة التي تحتاج تشغيلًا خلفيًا.

المشروع كتالوج منتجات وروابط إحالة مع لوحة إدارة متعددة الصلاحيات، بالعربية والإنجليزية. التصفح العام يحتاج صفحات مناسبة لمحركات البحث، ولوحة الإدارة تحتاج تفاعلًا، كما يلزم تسجيل المشاهدات والنقرات. البداية صغيرة، ولا توجد حاجة حالية لفصل المنتج إلى خدمات متعددة.

حدد اختيارك استضافة PHP مشتركة من cPanel، وقاعدة MySQL على الخادم نفسه، وتشغيل SQLite محليًا. أضفت خدمات خارجية للكاش والنسخ ومراقبة الأخطاء. لا يوجد مزود استضافة محدد حتى الآن، لذلك لا يمكن تأكيد أن خطة بعينها تسمح بكل الاتصالات والأوامر المطلوبة. اعتمدنا فحص التوافق شرطًا قبل شرائها. مساحة القرص وذاكرة PHP وأوقات تنفيذ cron غير معروفة. النسخة اليومية لا تشمل صور المنتجات، ونسخة استرداد مفتاح التشفير تحفظ في مدير أسرار منفصل.

## Options considered

### Option 1: تطبيق Laravel واحد، صفحات عامة من الخادم ولوحة Inertia مع Vue

تستخدم كل وظائف الموقع تطبيق Laravel وقاعدة MySQL واحدة. صفحات المتجر تستخدم Blade، ولوحة الإدارة تستخدم Inertia وVue.

**الإيجابيات**:

1. نشر واحد ومسارات جلسات موحدة.
2. صفحات عامة جاهزة للفهرسة من دون خادم Node دائم.
3. يطابق خياراتك التقنية والاستضافة التي حددتها.

**السلبيات**:

1. يجب المحافظة على واجهتين، Blade للمتجر وVue للإدارة.
2. تحتاج الخدمات الخارجية اتصالات صادرة تعمل من خطة cPanel.

الأساس: مواصفات النطاق واختياراتك، ودعم Laravel لواجهات Inertia من داخل تطبيق واحد، ودعم Laravel للصفحات المولدة على الخادم. (basis: [Laravel frontend](https://laravel.com/framework/docs/frontend), [Laravel deployment](https://laravel.com/framework/docs/deployment))

### Option 2: Laravel API منفصل وواجهة Vue مستقلة

تتواصل واجهة Vue مع API في Laravel عبر HTTP. يمكن نشر الواجهة والخادم كلًا على حدة.

**الإيجابيات**:

1. يمكن تطوير الواجهة والخادم بشكل مستقل.
2. قد يسمح بتطبيقات أخرى تستخدم API نفسها مستقبلًا.

**السلبيات**:

1. يضيف حدودًا لخدمتين واحتياجات مصادقة واتصال ونشر منفصلة.
2. يزيد العمل من دون وجود عميل منفصل أو حاجة توزيع مستقلة.
3. يتطلب تصميمًا إضافيًا لفهرسة صفحات Vue العامة.

الأساس: لا توجد واجهة API أو تطبيق هاتف مطلوب في النطاق، بينما المستخدمون يستعملون الموقع نفسه. (basis: [Laravel authentication guidance](https://laravel.com/docs/13.x/authentication), [Laravel frontend](https://laravel.com/framework/docs/frontend))

### Option 3: Inertia وVue لكل الصفحات مع SSR

تستخدم المتجر ولوحة الإدارة تطبيق Vue واحدًا، ويولد خادم Node صفحة HTML أولية لكل زيارة.

**الإيجابيات**:

1. تجربة ومكونات موحدة بين المتجر ولوحة الإدارة.
2. يدعم إظهار HTML مفيد لمحركات البحث.

**السلبيات**:

1. يحتاج خادم Node دائمًا في الإنتاج، بالإضافة إلى Laravel.
2. خطة cPanel المشتركة لم تحدد دعم تشغيل عامل دائم.
3. يزيد عدد العمليات التي يجب مراقبتها ونشرها.

الأساس: اختياراتك لا تتضمن Node وقت التشغيل، وInertia SSR يتطلب خدمة Node منفصلة. (basis: [Inertia server side rendering](https://inertiacore.net/docs/v3/advanced/server-side-rendering/))

### Option 4: صفحات Laravel فقط من دون Vue أو Inertia

تُبنى لوحة الإدارة والمتجر بصفحات Laravel وBlade، مع تفاعلات محدودة.

**الإيجابيات**:

1. أبسط تشغيلًا ونشرًا على cPanel.
2. يقل عدد الاعتماديات في الواجهة.

**السلبيات**:

1. يلغي اختيارك Inertia وVue.
2. يجعل الواجهات الإدارية كثيرة التفاعل أصعب في التطوير.

الأساس: اخترت صراحة واجهة Inertia مع Vue للوحة الإدارة. (basis: اختياراتك المعمارية)

## Rationale

يحافظ الخيار الأول على قيد الاستضافة المشتركة، مع إتاحة لوحة تفاعلية وعدم تشغيل Node في الإنتاج. كما أن تطبيقًا واحدًا يلائم موقعًا صغيرًا لا يملك حدًا مستقلًا لتوسيع كل مجال. تدعم Laravel خدمة MySQL وSQLite، كما توفر واجهات موحدة للكاش والطوابير والتخزين، ما يسمح بتغيير بعض الخدمات لاحقًا دون تبديل إطار التطبيق. (basis: [Laravel database](https://laravel.com/framework/docs/database/config), [Laravel cache](https://laravel.com/framework/docs/cache/config), [Laravel queues](https://laravel.com/framework/docs/13.x/queues.))

اخترت Upstash وPredis لأنهما يحققان الكاش المطلوب من دون الاعتماد على امتداد Redis مثبت في PHP. ينتقل Laravel صراحة إلى file cache عند تعطل Redis، ويجب اختبار هذا المسار. اخترت MySQL full text مع فلاتر قاعدة البيانات كي لا تضيف خدمة بحث على استضافة مشتركة، واعتمدت ترميز utf8mb4 للنصوص العربية والإنجليزية. اخترت Sentry للواجهة والخادم، وR2 وSpatie Backup للنسخ اليومية لقاعدة البيانات فقط. أبقيت صور المنتجات على قرص الخادم وفق اختيارك، لذا لا تحميها نسخة R2 الحالية من فقدان الخادم. هذه الاختيارات ترفع عدد الاتصالات الخارجية، لذلك توافق خطة الاستضافة شرط قبل شرائها، لا افتراض مضمون. (basis: [Upstash Laravel integration](https://upstash.com/docs/redis/quickstarts/laravel), [Laravel cache failover](https://api.laravel.com/docs/13.x/Illuminate/Cache/FailoverStore.html), [Predis for PHP](https://redis.io/docs/latest/clients/php/), [MySQL full text](https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html), [Cloudflare R2 S3 API](https://developers.cloudflare.com/r2/get-started/s3/), [Spatie Laravel Backup](https://spatie.be/docs/laravel-backup/v10/requirements))

## References

**Project sources**:

1. `docs/scope/scope.md`, product goals and confirmed workflow.
2. Your answers in this architecture discussion, including hosting, framework, services, deployment, and backup decisions.

**Practices and standards**:

1. Keep cache ephemeral and rebuildable.
2. Use one deployable application until scale or team ownership requires a split.
3. Keep backups separate from the server they protect, encrypt them, and test restoration.

**Links**:

1. [Laravel 13 framework requirements](https://github.com/laravel/laravel/blob/13.x/composer.json)
2. [Laravel deployment](https://laravel.com/framework/docs/deployment)
3. [Laravel database support](https://laravel.com/framework/docs/database/config)
4. [Laravel authentication](https://laravel.com/docs/13.x/authentication)
5. [Laravel frontend and Inertia](https://laravel.com/framework/docs/frontend)
6. [Laravel cache](https://laravel.com/framework/docs/cache/config)
7. [Laravel queues](https://laravel.com/framework/docs/13.x/queues)
8. [Laravel file storage](https://laravel.com/framework/docs/13.x/filesystem)
9. [Laravel logging](https://laravel.com/framework/docs/logging)
10. [Laravel query builder full text support](https://laravel.com/framework/docs/13.x/queries)
11. [Laravel official starter kits](https://laravel.com/framework/docs/starter-kits)
12. [Upstash Redis with Laravel](https://upstash.com/docs/redis/quickstarts/laravel)
13. [Predis PHP client](https://redis.io/docs/latest/clients/php/)
14. [MySQL 8.4 full text search](https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html)
15. [MySQL 8.4 utf8mb4 character set](https://dev.mysql.com/doc/refman/8.4/en/charset-unicode-utf8mb4.html)
16. [Spatie Laravel Permission 8 prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites)
17. [Bootstrap 5.3.8](https://getbootstrap.com/docs/5.3/getting-started/download/)
18. [Sentry Laravel SDK](https://github.com/getsentry/sentry-laravel)
19. [Sentry Vue SDK](https://www.npmjs.com/package/@sentry/vue)
20. [Cloudflare R2 S3 compatibility](https://developers.cloudflare.com/r2/get-started/s3/)
21. [Spatie Laravel Backup 10 requirements](https://spatie.be/docs/laravel-backup/v10/requirements)
22. [Spatie Laravel Backup scheduling and storage](https://spatie.be/docs/laravel-backup/v10/installation-and-setup)
23. [Laravel Fortify 13](https://laravel.com/framework/docs/13.x/fortify)
24. [Laravel S3 filesystem adapter](https://laravel.com/framework/docs/13.x/filesystem)

