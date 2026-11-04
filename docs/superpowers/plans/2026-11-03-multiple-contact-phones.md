# أرقام متعددة للعملاء والوسطاء: خطة التنفيذ

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** دعم أرقام أساسية وإضافية للعملاء والوسطاء في الإيجار وإعادة البيع مع التحقق حسب الدولة والتكرار والبحث والتواصل والتصدير.

**Architecture:** جدول موحد يحفظ كل رقم ويرتبط بنوع سجل العميل أو الوسيط، وعلاقة Eloquent مشتركة وخدمة مجال واحدة لتطبيع الأرقام والتحقق منها واستبدالها داخل معاملة. نماذج Filament تعرض مجموعة أرقام، والأساسي منها يبقى الافتراضي في واجهات الاتصال الحالية. ترحيل البيانات يتم على مراحل: إضافة العلاقة وملء البيانات القديمة، تحديث كل المستهلكين، ثم إزالة الأعمدة القديمة.

**Tech Stack:** Laravel 12, PHP, Eloquent, Filament 5, MySQL, libphonenumber.

**Spec:** `docs/superpowers/specs/2026-11-03-multiple-contact-phone-numbers-design.md`

## Global Constraints

- يجب أن يملك العميل رقمًا واحدًا على الأقل ورقمًا أساسيًا واحدًا بالضبط.
- يجوز للوسيط ألا يملك أرقامًا؛ وإذا وجدت أرقام، يجب أن يكون بينها رقم أساسي واحد بالضبط.
- كل رقم جديد أو معدل يمر عبر `CustomerPhoneNormalizer`، وخيار `Other` يتطلب صيغة دولية صالحة.
- تستمر سياسة التكرار الحالية لكل نوع سجل، وتشمل العملاء المحذوفين حذفًا منطقيًا.
- الرقم الأساسي هو الافتراضي لإجراءات الاتصال وWhatsApp الحالية؛ كل الأرقام الإضافية تظل متاحة للبحث والتواصل والتصدير.
- يظل استيراد Excel الحالي صالحًا ويحوّل رقم الهاتف الموجود فيه إلى الرقم الأساسي.
- لا يتغير حقل هاتف البائع المستقل في الصفقة.
- لا يُسمح بترحيل عكسي يسقط أرقامًا إضافية بصمت.
- لا تنفذ أي commit ضمن هذه المهمة.

## Review Focus

1. رقم إضافي يطابق رقم سجل آخر من النوع نفسه، بما فيه العميل المحذوف منطقيًا؛ يجب أن يُرفض قبل الحفظ.
2. قائمة هاتف فارغة أو بها أكثر من رقم أساسي؛ يجب أن تلتزم بقيود العميل والوسيط المحددة.
3. رقم غير صالح أو لا يطابق الدولة المختارة؛ يجب أن يفشل عبر `CustomerPhoneNormalizer`.
4. حذف وسيط له طلبات؛ يجب أن يحفظ الأساسي وكل الأرقام الإضافية في تاريخ الطلب.
5. ترحيل سجل قديم أو التراجع عن إزالة الأعمدة؛ يجب أن يحافظ على رقم السجل الأساسي دون فقد إضافات.

---

## خريطة الملفات

### ملفات جديدة

- `app/Models/ContactPhoneNumber.php` — نموذج سجل رقم الهاتف.
- `app/Models/Concerns/HasContactPhoneNumbers.php` — علاقة الأرقام وطرق الوصول إلى الأساسي والقائمة المرتبة.
- `app/Services/ContactPhoneNumberService.php` — تطبيع الأرقام والتحقق من التفرد واستبدال القائمة ذريًا.
- `app/Filament/Support/ContactPhonesField.php` — واجهة Filament لإضافة الأرقام وحذفها، مع جعل الأول أساسيًا تلقائيًا.
- `resources/views/filament/modals/contact-phone-numbers.blade.php` — عرض أرقام جهة الاتصال وروابط الهاتف وWhatsApp.
- `resources/views/filament/modals/request-contact-phone-numbers.blade.php` — عرض أرقام العميل والوسيط مع الأرقام التاريخية بعد حذف الوسيط.
- `database/migrations/2026_11_03_130000_create_contact_phone_numbers_table.php` — إنشاء العلاقة ونسخ الأرقام الحالية إلى سجل أساسي.
- `database/migrations/2026_11_03_140000_add_broker_phone_numbers_snapshots.php` — تخزين لقطة JSON لأرقام الوسيط على الطلب.
- `database/migrations/2026_11_03_150000_drop_legacy_contact_phone_columns.php` — إزالة الأعمدة القديمة بعد تحويل القراءات والكتابات.

### ملفات معدلة

- `app/Providers/AppServiceProvider.php` — تسجيل أسماء morph ثابتة لأنواع سجلات العملاء والوسطاء الأربعة.
- `app/Models/RentalCustomer.php`, `app/Models/ResaleCustomer.php`, `app/Models/RentalBroker.php`, `app/Models/ResaleBroker.php` — تفعيل علاقة الأرقام وإزالة الاعتماد على أعمدة الرقم القديمة بعد مرحلة التوافق.
- `app/Models/RentalRequest.php`, `app/Models/ResaleRequest.php` — cast لقائمة أرقام الوسيط التاريخية.
- `app/Services/CustomerService.php`, `app/Services/BrokerService.php` — استقبال قائمة الأرقام، حفظها في معاملة، التحقق من التكرار، تحديث التدقيق واللقطات التاريخية.
- `app/Services/CustomerSpreadsheetImportService.php` — تحويل العمود الحالي إلى قائمة من رقم أساسي واحد.
- `app/Filament/Support/CustomerResource.php`, `app/Filament/Support/BrokerFormSchema.php`, `app/Filament/Support/BrokerResource.php` — إضافة واجهة إدارة الأرقام وعرض الأساسي وعدد الإضافات.
- `app/Filament/Support/SectionRequestResource.php`, `app/Filament/Support/RequestFormSchema.php`, `app/Filament/Support/AftercareResource.php` — البحث بكل الأرقام وإتاحة الأرقام الإضافية في صفحات الطلبات والمتابعة.
- `app/Filament/Support/AuditEventResource.php` — عرض قيمة تدقيق قائمة الأرقام.
- `app/Exports/RentalCustomerExporter.php`, `app/Exports/ResaleCustomerExporter.php` — إضافة عمود للأرقام الإضافية مع إبقاء عمود الأساسي.
- `app/Exports/RentalRequestExporter.php`, `app/Exports/ResaleRequestExporter.php` — إضافة أرقام العميل والوسيط الإضافية مع الإبقاء على أعمدة الأساسي.
- `resources/css/filament/admin/theme.css` — تحديث محددات تنسيق عمود الهاتف بعد تغيير اسم العمود المعروض.
- `docs/DATA_MODEL.md`, `docs/DECISIONS.md`, `GLOSSARY.md`, ومستند التصميم — توثيق قرار المجال والنموذج المعتمد.

## Tasks

### Task 1: إنشاء نموذج الأرقام والعلاقة وترحيل البيانات

**Files:**
- Create: `app/Models/ContactPhoneNumber.php`
- Create: `app/Models/Concerns/HasContactPhoneNumbers.php`
- Create: `database/migrations/2026_11_03_130000_create_contact_phone_numbers_table.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: نماذج العملاء والوسطاء الأربعة المذكورة أعلاه

**Interfaces:**
- Produces: `ContactPhoneNumber::phoneable(): MorphTo`.
- Produces: `HasContactPhoneNumbers::phoneNumbers(): MorphMany` و`primaryPhoneNumber(): MorphOne` و`primaryPhone(): ?ContactPhoneNumber`.
- يعتمد جدول الأرقام على aliases ثابتة `rental_customer`, `resale_customer`, `rental_broker`, `resale_broker`، وقيد تفرد `(phoneable_type, phone_e164)`.

- [x] إضافة morph map ثابت للأنواع الأربعة.
- [x] إنشاء جدول يحوي `phoneable_type`, `phoneable_id`, `phone_e164`, `phone_country`, `is_primary`, `sort_order` مع الفهارس اللازمة.
- [x] نسخ رقم كل عميل ووسيط موجود إلى سجل أساسي واحد، مع تخطي رقم الوسيط الفارغ.
- [x] إضافة العلاقة المشتركة للنماذج الأربعة مع تنظيف سجلات الأرقام عند الحذف النهائي، والإبقاء مؤقتًا على أعمدة الهاتف القديمة.
- [x] التحقق بمراجعة استعلامات الترحيل أن عدد الأرقام المنقولة يساوي عدد الأرقام القديمة غير الفارغة، وأن كل سجل عميل له أساسي.

### Task 2: تطبيق حفظ الأرقام والتحقق المشترك

**Files:**
- Create: `app/Services/ContactPhoneNumberService.php`
- Modify: `app/Services/CustomerService.php`
- Modify: `app/Services/BrokerService.php`
- Modify: `app/Services/CustomerSpreadsheetImportService.php`

**Interfaces:**
- Produces: `prepareFor(Model $contact, array $phones, bool $required): array` لتطبيع الصفوف والتحقق قبل حفظ جهة الاتصال، ثم `replaceFor(Model $contact, array $normalizedPhones): Collection` للحفظ الذري.
- Consumes: `CustomerPhoneNormalizer::normalize()` و`HasContactPhoneNumbers::phoneNumbers()`.

- [x] تطبيع كل عنصر في `prepareFor()` وتوحيد أخطاء التحقق مع مفاتيح صفوف الأرقام.
- [x] رفض الصف الجزئي أو الرقم غير الصالح أو تكرار الرقم داخل القائمة.
- [x] رفض أي رقم مستخدم لسجل آخر من النوع نفسه، بما يشمل العملاء المحذوفين منطقيًا، مع ترك قيد قاعدة البيانات كحماية من السباق.
- [x] فرض رقم واحد على الأقل للعميل، والسماح بقائمة فارغة للوسيط، وفرض أساسي واحد بالضبط عند وجود أرقام.
- [x] استبدال القائمة المحضرة في `replaceFor()` داخل معاملة مع قفل سجل جهة الاتصال، والحفاظ على ترتيب العناصر وتحديث القيمة الأساسية القديمة مؤقتًا أثناء مرحلة التوافق.
- [x] تحديث خدمات العميل والوسيط لتستقبل `phones` وتضمّن القائمة المرتبة في قيم التدقيق قبل وبعد التغيير.
- [x] تحويل استيراد Excel الحالي إلى `{phone, phone_country, is_primary: true}` دون تغيير أعمدته أو سلوكه.

### Task 3: واجهة إدارة الأرقام للعملاء والوسطاء

**Files:**
- Create: `app/Filament/Support/ContactPhonesField.php`
- Create: `resources/views/filament/modals/contact-phone-numbers.blade.php`
- Modify: `app/Filament/Support/CustomerResource.php`
- Modify: `app/Filament/Support/BrokerFormSchema.php`
- Modify: `app/Filament/Support/BrokerResource.php`

- [x] بناء مكوّن قائمة أرقام يعيد `phones` بالشكل الذي تستهلكه خدمة المجال.
- [x] إظهار اختيار الدولة لكل صف والتحقق منه عبر نفس normalizer المستخدم في الخلفية.
- [x] توفير إضافة وحذف الصفوف؛ أول رقم أساسي تلقائيًا، مع إظهار رقم واحد على الأقل للعميل وعدم فرض رقم للوسيط.
- [x] تحميل كل الأرقام في نموذج التعديل مع الأساسي الحالي وترتيبها.
- [x] عرض الأساسي وعدد الأرقام الإضافية في جدولي العميل والوسيط، وإضافة نافذة قائمة أرقام في إجراءات السجل تعرض كل الأرقام وروابط التواصل.
- [x] إبقاء روابط الاتصال السريعة على الأساسي وتقديم الأرقام الإضافية كخيارات واضحة للتواصل.

### Task 4: تحديث البحث والعرض في الطلبات والمتابعة

**Files:**
- Modify: `app/Filament/Support/SectionRequestResource.php`
- Modify: `app/Filament/Support/RequestFormSchema.php`
- Modify: `app/Filament/Support/AftercareResource.php`
- Modify: `app/Filament/Support/AuditEventResource.php`
- Modify: `app/Models/RentalRequest.php`, `app/Models/ResaleRequest.php`
- Create: `database/migrations/2026_11_03_140000_add_broker_phone_numbers_snapshots.php`

- [x] تحديث بحث العميل في الطلب والبحث في جدولي العميل والوسيط ليفتش الاسم وكل الأرقام عبر العلاقة دون خلط نطاق القسم.
- [x] إبقاء الأساسي كقيمة افتراضية في تفاصيل الطلب وما بعد الصفقة، وإتاحة أرقام العميل والوسيط البديلة كرابط أو اختيار تواصل.
- [x] إضافة cast لـ `broker_phone_numbers_snapshot`، وتحديث حذف الوسيط لنسخ قائمة مرتبة تشمل الأساسي إلى الطلبات المرتبطة قبل فصل العلاقة.
- [x] عرض القائمة التاريخية في الطلب بعد حذف الوسيط، مع الاحتفاظ بالعمود الحالي `broker_phone_snapshot` كقيمة أساسية.
- [x] جعل التراجع عن هجرة اللقطة يرفض إسقاط طلبات تاريخية تحتوي أكثر من رقم محفوظ.
- [x] عرض قائمة الهاتف في شاشة التدقيق بدل إظهار حقول أعمدة الهاتف القديمة فقط.

### Task 5: تحديث تصدير العملاء والطلبات

**Files:**
- Modify: `app/Exports/RentalCustomerExporter.php`, `app/Exports/ResaleCustomerExporter.php`
- Modify: `app/Exports/RentalRequestExporter.php`, `app/Exports/ResaleRequestExporter.php`

- [x] إبقاء أعمدة الهاتف الحالية للأساسي وإضافة عمود منفصل للأرقام الإضافية مرتبطة بـ `; `.
- [x] تضمين إضافات العميل والوسيط في تصدير الطلب.
- [x] عند تصدير طلب حُذف وسيطه، استخدم لقطة الأرقام التاريخية بدلًا من العلاقة الحية.

### Task 6: إزالة أعمدة الهاتف القديمة وتحديث وثائق المجال

**Files:**
- Create: `database/migrations/2026_11_03_150000_drop_legacy_contact_phone_columns.php`
- Modify: النماذج والخدمات والواجهات والمصدّرات المذكورة في المهام السابقة لإزالة آخر قراءات وكتابات للأعمدة القديمة.
- Modify: `docs/DATA_MODEL.md`, `docs/DECISIONS.md`, `GLOSSARY.md`, `docs/superpowers/specs/2026-11-03-multiple-contact-phone-numbers-design.md`, `resources/css/filament/admin/theme.css`

- [x] البحث في المشروع عن `phone_e164` و`phone_country` كأعمدة مباشرة والتأكد أن المتبقي خاص بعلاقة الأرقام أو رقم البائع المستقل فقط.
- [x] إزالة قيود التفرد والأعمدة القديمة بعد أن أصبحت كل القراءات والكتابات على العلاقة الجديدة.
- [x] جعل التراجع يعيد قيود التفرد القديمة قبل استعادة الرقم الأساسي إلى أعمدته.
- [x] جعل التراجع عن هذه الهجرة يعيد أعمدة الأساسي من السجلات المرتبطة دون حذف قائمة الأرقام.
- [x] جعل التراجع عن هجرة إنشاء العلاقة يرفض التنفيذ إذا وجدت أرقام إضافية لا يمكن تمثيلها في الأعمدة القديمة.
- [x] تحديث نموذج البيانات وسجل القرارات لتعريف علاقة العميل والوسيط بالأرقام المتعددة وحدود التفرد.

## التحقق بعد التنفيذ

- لا تُضاف ملفات اختبارات ولا تُشغّل أوامر الاختبارات ضمن هذه المهمة.
- نفّذ `php -l` لكل ملفات PHP الجديدة أو المعدلة.
- نفّذ `git diff --check` لمراجعة المسافات والتنسيق.
- راجع مقارنة البيانات قبل وبعد الهجرة: كل رقم قديم يجب أن يظهر مرة واحدة كرقم أساسي، وأي تراجع يجب أن يوقف نفسه قبل إسقاط الأرقام الإضافية.
- راجع جميع استدعاءات البحث والاتصال وWhatsApp والتصدير للتأكد أنها لا تعتمد على عمود الهاتف بعد إزالته.
