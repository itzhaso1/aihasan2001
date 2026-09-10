// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appName => 'حاسم للمالية';

  @override
  String get loginTitle => 'تسجيل الدخول';

  @override
  String get emailOrPhone => 'البريد أو الجوال';

  @override
  String get password => 'كلمة المرور';

  @override
  String get loginAction => 'دخول';

  @override
  String get loginSubtitle => 'سجّل الدخول بنفس حساب حاسم';

  @override
  String get orDivider => 'أو';

  @override
  String get continueWithGoogle => 'الدخول باستخدام Google';

  @override
  String get googleSigningIn => 'جار تسجيل الدخول...';

  @override
  String get googleFailed => 'تعذر تسجيل الدخول عبر Google.';

  @override
  String get forgotPassword => 'نسيت كلمة المرور؟';

  @override
  String get forgotPasswordHint =>
      'أدخل بريدك الإلكتروني لإرسال رابط إعادة التعيين.';

  @override
  String get forgotPasswordSent => 'تم إرسال رابط إعادة تعيين كلمة المرور.';

  @override
  String get sendResetLink => 'إرسال الرابط';

  @override
  String get haveResetToken => 'لدي رمز إعادة التعيين';

  @override
  String get resetPassword => 'إعادة تعيين كلمة المرور';

  @override
  String get resetToken => 'رمز إعادة التعيين';

  @override
  String get confirmPassword => 'تأكيد كلمة المرور';

  @override
  String get passwordResetDone => 'تم إعادة تعيين كلمة المرور بنجاح.';

  @override
  String get selectWorkspaceHint =>
      'اختر مساحة العمل. لن يتم اختيار منشأة تلقائياً عند وجود أكثر من واحدة.';

  @override
  String get financeEnabled => 'المالية مفعّلة';

  @override
  String get financeDisabled => 'المالية غير مفعّلة';

  @override
  String get financeUnavailableTitle => 'هذه المنشأة لا تملك منتج المالية';

  @override
  String get financeUnavailableBody =>
      'الحساب مسجّل الدخول، لكن صلاحية المالية غير مفعّلة لهذه المساحة. يمكنك تبديل المنشأة أو تسجيل الخروج.';

  @override
  String get googleAccountLinked =>
      'هذا البريد مرتبط بحساب موجود. سجّل الدخول بكلمة المرور أولاً لربط حساب Google.';

  @override
  String get logout => 'تسجيل الخروج';

  @override
  String get workspace => 'مساحة العمل';

  @override
  String get switchWorkspace => 'تبديل المنشأة';

  @override
  String get dashboard => 'لوحة المالية';

  @override
  String get customers => 'العملاء';

  @override
  String get quotes => 'عروض الأسعار';

  @override
  String get invoices => 'الفواتير';

  @override
  String get payments => 'المدفوعات';

  @override
  String get receipts => 'الإيصالات';

  @override
  String get statements => 'كشوف الحساب';

  @override
  String get notes => 'الإشعارات';

  @override
  String get contracts => 'العقود';

  @override
  String get expenses => 'المصروفات';

  @override
  String get purchases => 'المشتريات';

  @override
  String get reports => 'التقارير';

  @override
  String get settings => 'الإعدادات';

  @override
  String get search => 'بحث';

  @override
  String get retry => 'إعادة المحاولة';

  @override
  String get empty => 'لا توجد بيانات';

  @override
  String get save => 'حفظ';

  @override
  String get create => 'إنشاء';

  @override
  String get edit => 'تعديل';

  @override
  String get delete => 'حذف';

  @override
  String get cancel => 'إلغاء';

  @override
  String get issue => 'إصدار';

  @override
  String get send => 'إرسال';

  @override
  String get remind => 'تذكير';

  @override
  String get accept => 'قبول';

  @override
  String get reject => 'رفض';

  @override
  String get convert => 'تحويل إلى فاتورة';

  @override
  String get pdf => 'ملف PDF';

  @override
  String get download => 'تنزيل';

  @override
  String get copy => 'نسخ';

  @override
  String get open => 'فتح';

  @override
  String get refresh => 'تحديث';

  @override
  String get recordPayment => 'تسجيل دفعة';

  @override
  String get reversePayment => 'عكس الدفعة';

  @override
  String get checkout => 'رابط الدفع الإلكتروني';

  @override
  String get createCheckout => 'إنشاء رابط الدفع';

  @override
  String get paymentLinkHint => 'إنشاء الرابط لا يعني أن الفاتورة دُفعت.';

  @override
  String get permissionDenied => 'لا تملك صلاحية تنفيذ هذا الإجراء.';

  @override
  String get sessionExpired => 'انتهت جلسة تسجيل الدخول.';

  @override
  String get offline => 'تعذر الاتصال بالخادم. تحقق من الشبكة.';

  @override
  String get documentStatus => 'حالة المستند';

  @override
  String get outcome => 'النتيجة التجارية';

  @override
  String get deliveryStatus => 'حالة التسليم';

  @override
  String get paymentStatus => 'حالة التحصيل';

  @override
  String get outstanding => 'الرصيد المستحق';

  @override
  String get subtotal => 'المجموع الفرعي';

  @override
  String get tax => 'الضريبة';

  @override
  String get total => 'الإجمالي';

  @override
  String get paid => 'المدفوع';

  @override
  String get due => 'المستحق';

  @override
  String get customer => 'العميل';

  @override
  String get amount => 'المبلغ';

  @override
  String get method => 'طريقة الدفع';

  @override
  String get reference => 'المرجع';

  @override
  String get date => 'التاريخ';

  @override
  String get status => 'الحالة';

  @override
  String get confirm => 'تأكيد';

  @override
  String get confirmDestructive => 'هل أنت متأكد من تنفيذ هذا الإجراء؟';

  @override
  String get copied => 'تم النسخ';

  @override
  String get success => 'تم بنجاح';

  @override
  String get more => 'المزيد';

  @override
  String get overview => 'نظرة عامة';

  @override
  String get lines => 'البنود';

  @override
  String get notesField => 'ملاحظات';

  @override
  String get vatNumber => 'الرقم الضريبي';

  @override
  String get crNumber => 'السجل التجاري';

  @override
  String get email => 'البريد الإلكتروني';

  @override
  String get phone => 'الجوال';

  @override
  String get company => 'شركة';

  @override
  String get individual => 'فرد';

  @override
  String get draft => 'مسودة';

  @override
  String get issued => 'صادرة';

  @override
  String get cancelled => 'ملغاة';

  @override
  String get pending => 'قيد الانتظار';

  @override
  String get accepted => 'مقبولة';

  @override
  String get rejected => 'مرفوضة';

  @override
  String get expired => 'منتهية';

  @override
  String get converted => 'محوّلة';

  @override
  String get unpaid => 'غير مدفوعة';

  @override
  String get partial => 'مدفوعة جزئياً';

  @override
  String get overdue => 'متأخرة';

  @override
  String get posted => 'مرحّلة';

  @override
  String get voided => 'ملغاة';

  @override
  String get reversed => 'معكوسة';

  @override
  String get credit => 'إشعار دائن';

  @override
  String get debit => 'إشعار مدين';

  @override
  String get apiHost => 'عنوان الخادم';

  @override
  String get theme => 'المظهر';

  @override
  String get language => 'اللغة';

  @override
  String get arabic => 'العربية';

  @override
  String get english => 'English';

  @override
  String get sales => 'المبيعات';

  @override
  String get receivables => 'الذمم المدينة';

  @override
  String get payables => 'الذمم الدائنة';

  @override
  String get invoicesDue => 'فواتير مستحقة';

  @override
  String get overdueInvoices => 'فواتير متأخرة';

  @override
  String get paidThisPeriod => 'المحصّل هذه الفترة';

  @override
  String get recentInvoices => 'أحدث الفواتير';

  @override
  String get recentPayments => 'أحدث المدفوعات';

  @override
  String get noPermissionScreen => 'هذه الشاشة غير متاحة لصلاحياتك الحالية.';

  @override
  String get checkoutUnavailable => 'رابط الدفع الإلكتروني غير متاح.';

  @override
  String get neverMarkPaidLocally =>
      'لن تُعلَّم الفاتورة مدفوعة إلا بعد تأكيد الخادم.';

  @override
  String get recipient => 'المستلم';

  @override
  String get quantity => 'الكمية';

  @override
  String get price => 'السعر';

  @override
  String get description => 'الوصف';

  @override
  String get unit => 'الوحدة';

  @override
  String get issueDate => 'تاريخ الإصدار';

  @override
  String get dueDate => 'تاريخ الاستحقاق';

  @override
  String get expiryDate => 'تاريخ الانتهاء';

  @override
  String get from => 'من';

  @override
  String get to => 'إلى';

  @override
  String get openingBalance => 'الرصيد الافتتاحي';

  @override
  String get closingBalance => 'الرصيد الختامي';

  @override
  String get csv => 'CSV';

  @override
  String get suppliers => 'الموردون';

  @override
  String get category => 'التصنيف';

  @override
  String get attachment => 'مرفق';

  @override
  String get generateInvoice => 'توليد فاتورة مسودة';

  @override
  String get signContract => 'تفعيل / توقيع';

  @override
  String get closeContract => 'إغلاق';

  @override
  String get billingSchedule => 'جدول الفوترة';

  @override
  String get profitLoss => 'الأرباح والخسائر';

  @override
  String get trialBalance => 'ميزان المراجعة';

  @override
  String get cashFlow => 'التدفقات النقدية';

  @override
  String get balanceSheet => 'الميزانية';

  @override
  String get generalLedger => 'دفتر الأستاذ';

  @override
  String get arAging => 'أعمار الذمم المدينة';

  @override
  String get apAging => 'أعمار الذمم الدائنة';

  @override
  String get loadMore => 'تحميل المزيد';

  @override
  String get globalSearch => 'بحث مالي';

  @override
  String get exports => 'تصدير CSV';

  @override
  String get auditTrail => 'سجل التدقيق';

  @override
  String get downloadAttachment => 'تنزيل المرفق';

  @override
  String get noResults => 'لا توجد نتائج';

  @override
  String get selectCustomer => 'اختر العميل';

  @override
  String get selectSupplier => 'اختر المورد';

  @override
  String get addLine => 'إضافة بند';

  @override
  String get removeLine => 'حذف البند';

  @override
  String get taxRate => 'نسبة الضريبة';

  @override
  String get discount => 'الخصم';

  @override
  String get invoiceId => 'رقم الفاتورة';

  @override
  String get taxableAmount => 'المبلغ الخاضع للضريبة';
  @override
  String get amountCredited => 'المُشعَر دائناً';
  @override
  String get amountDebited => 'المُشعَر مديناً';
  @override
  String get whatsapp => 'واتساب';
  @override
  String get buildingNumber => 'رقم المبنى';
  @override
  String get district => 'الحي';
  @override
  String get postalCode => 'الرمز البريدي';
  @override
  String get city => 'المدينة';
  @override
  String get street => 'الشارع';
  @override
  String get country => 'الدولة';
  @override
  String get paymentTerms => 'شروط الدفع';
  @override
  String get treasuryAccount => 'حساب الخزينة';
  @override
  String get recurring => 'متكرر (علم فقط)';
  @override
  String get netProfit => 'صافي الربح';
  @override
  String get outputVat => 'ضريبة المخرجات';
  @override
  String get inputVat => 'ضريبة المدخلات';
  @override
  String get netVat => 'صافي الضريبة';
  @override
  String get cashBalance => 'النقد';
  @override
  String get bankBalance => 'البنك';
  @override
  String get activeContracts => 'عقود نشطة';
  @override
  String get recentExpenses => 'أحدث المصروفات';
  @override
  String get statementDebit => 'مدين';
  @override
  String get statementCredit => 'دائن';
  @override
  String get runningBalance => 'الرصيد الجاري';
  @override
  String get invoicesTotal => 'إجمالي الفواتير';
  @override
  String get paymentsTotal => 'إجمالي الدفعات';
  @override
  String get creditsTotal => 'إجمالي الدائن';
  @override
  String get debitsTotal => 'إجمالي المدين';
  @override
  String get zatcaQr => 'رمز الزكاة حاضر';
  @override
  String get terms => 'الشروط';
  @override
  String get rejectionReason => 'سبب الرفض';
  @override
  String get website => 'الموقع';
  @override
  String get currency => 'العملة';
  @override
  String get invoicePrefix => 'بادئة الفاتورة';
  @override
  String get defaultVatRate => 'نسبة الضريبة الافتراضية';
  @override
  String get zatcaMode => 'وضع الربط مع الزكاة';
  @override
  String get nextRun => 'التشغيل التالي';
  @override
  String get frequency => 'التكرار';
  @override
  String get autoIssue => 'إصدار تلقائي';
  @override
  String get generatedCount => 'المُولَّد';
  @override
  String get paymentDate => 'تاريخ الدفع';
  @override
  String get supplier => 'المورد';
  @override
  String get openingCash => 'افتتاحي النقد';
  @override
  String get netChange => 'صافي التغير';
  @override
  String get closingCash => 'ختامي النقد';
  @override
  String get assets => 'الأصول';
  @override
  String get liabilities => 'الالتزامات';
  @override
  String get equity => 'حقوق الملكية';
  @override
  String get revenue => 'الإيرادات';
  @override
  String get cogs => 'تكلفة المبيعات';
  @override
  String get grossProfit => 'مجمل الربح';
  @override
  String get additionalNumber => 'الرقم الإضافي';
  @override
  String get companyNameAr => 'اسم الشركة بالعربية';
  @override
  String get addressLine => 'سطر العنوان';
  @override
  String get filterAll => 'الكل';
  @override
  String get generatedInvoices => 'الفواتير المولّدة';
  @override
  String get invoicedTotal => 'المفوتر';
  @override
  String get snapshots => 'اللقطات';
  @override
  String get reason => 'السبب';
  @override
  String get inventoryValuation => 'تقييم المخزون';
}
