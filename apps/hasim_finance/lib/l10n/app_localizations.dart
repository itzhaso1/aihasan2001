import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// No description provided for @appName.
  ///
  /// In ar, this message translates to:
  /// **'حاسم للمالية'**
  String get appName;

  /// No description provided for @loginTitle.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الدخول'**
  String get loginTitle;

  /// No description provided for @emailOrPhone.
  ///
  /// In ar, this message translates to:
  /// **'البريد أو الجوال'**
  String get emailOrPhone;

  /// No description provided for @password.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور'**
  String get password;

  /// No description provided for @loginAction.
  ///
  /// In ar, this message translates to:
  /// **'دخول'**
  String get loginAction;

  /// No description provided for @loginSubtitle.
  ///
  /// In ar, this message translates to:
  /// **'سجّل الدخول بنفس حساب حاسم'**
  String get loginSubtitle;

  /// No description provided for @orDivider.
  ///
  /// In ar, this message translates to:
  /// **'أو'**
  String get orDivider;

  /// No description provided for @continueWithGoogle.
  ///
  /// In ar, this message translates to:
  /// **'الدخول باستخدام Google'**
  String get continueWithGoogle;

  /// No description provided for @googleSigningIn.
  ///
  /// In ar, this message translates to:
  /// **'جار تسجيل الدخول...'**
  String get googleSigningIn;

  /// No description provided for @googleFailed.
  ///
  /// In ar, this message translates to:
  /// **'تعذر تسجيل الدخول عبر Google.'**
  String get googleFailed;

  /// No description provided for @forgotPassword.
  ///
  /// In ar, this message translates to:
  /// **'نسيت كلمة المرور؟'**
  String get forgotPassword;

  /// No description provided for @forgotPasswordHint.
  ///
  /// In ar, this message translates to:
  /// **'أدخل بريدك الإلكتروني لإرسال رابط إعادة التعيين.'**
  String get forgotPasswordHint;

  /// No description provided for @forgotPasswordSent.
  ///
  /// In ar, this message translates to:
  /// **'تم إرسال رابط إعادة تعيين كلمة المرور.'**
  String get forgotPasswordSent;

  /// No description provided for @sendResetLink.
  ///
  /// In ar, this message translates to:
  /// **'إرسال الرابط'**
  String get sendResetLink;

  /// No description provided for @haveResetToken.
  ///
  /// In ar, this message translates to:
  /// **'لدي رمز إعادة التعيين'**
  String get haveResetToken;

  /// No description provided for @resetPassword.
  ///
  /// In ar, this message translates to:
  /// **'إعادة تعيين كلمة المرور'**
  String get resetPassword;

  /// No description provided for @resetToken.
  ///
  /// In ar, this message translates to:
  /// **'رمز إعادة التعيين'**
  String get resetToken;

  /// No description provided for @confirmPassword.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد كلمة المرور'**
  String get confirmPassword;

  /// No description provided for @passwordResetDone.
  ///
  /// In ar, this message translates to:
  /// **'تم إعادة تعيين كلمة المرور بنجاح.'**
  String get passwordResetDone;

  /// No description provided for @selectWorkspaceHint.
  ///
  /// In ar, this message translates to:
  /// **'اختر مساحة العمل. لن يتم اختيار منشأة تلقائياً عند وجود أكثر من واحدة.'**
  String get selectWorkspaceHint;

  /// No description provided for @financeEnabled.
  ///
  /// In ar, this message translates to:
  /// **'المالية مفعّلة'**
  String get financeEnabled;

  /// No description provided for @financeDisabled.
  ///
  /// In ar, this message translates to:
  /// **'المالية غير مفعّلة'**
  String get financeDisabled;

  /// No description provided for @financeUnavailableTitle.
  ///
  /// In ar, this message translates to:
  /// **'هذه المنشأة لا تملك منتج المالية'**
  String get financeUnavailableTitle;

  /// No description provided for @financeUnavailableBody.
  ///
  /// In ar, this message translates to:
  /// **'الحساب مسجّل الدخول، لكن صلاحية المالية غير مفعّلة لهذه المساحة. يمكنك تبديل المنشأة أو تسجيل الخروج.'**
  String get financeUnavailableBody;

  /// No description provided for @googleAccountLinked.
  ///
  /// In ar, this message translates to:
  /// **'هذا البريد مرتبط بحساب موجود. سجّل الدخول بكلمة المرور أولاً لربط حساب Google.'**
  String get googleAccountLinked;

  /// No description provided for @logout.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الخروج'**
  String get logout;

  /// No description provided for @workspace.
  ///
  /// In ar, this message translates to:
  /// **'مساحة العمل'**
  String get workspace;

  /// No description provided for @switchWorkspace.
  ///
  /// In ar, this message translates to:
  /// **'تبديل المنشأة'**
  String get switchWorkspace;

  /// No description provided for @dashboard.
  ///
  /// In ar, this message translates to:
  /// **'لوحة المالية'**
  String get dashboard;

  /// No description provided for @customers.
  ///
  /// In ar, this message translates to:
  /// **'العملاء'**
  String get customers;

  /// No description provided for @quotes.
  ///
  /// In ar, this message translates to:
  /// **'عروض الأسعار'**
  String get quotes;

  /// No description provided for @invoices.
  ///
  /// In ar, this message translates to:
  /// **'الفواتير'**
  String get invoices;

  /// No description provided for @payments.
  ///
  /// In ar, this message translates to:
  /// **'المدفوعات'**
  String get payments;

  /// No description provided for @receipts.
  ///
  /// In ar, this message translates to:
  /// **'الإيصالات'**
  String get receipts;

  /// No description provided for @statements.
  ///
  /// In ar, this message translates to:
  /// **'كشوف الحساب'**
  String get statements;

  /// No description provided for @notes.
  ///
  /// In ar, this message translates to:
  /// **'الإشعارات'**
  String get notes;

  /// No description provided for @contracts.
  ///
  /// In ar, this message translates to:
  /// **'العقود'**
  String get contracts;

  /// No description provided for @expenses.
  ///
  /// In ar, this message translates to:
  /// **'المصروفات'**
  String get expenses;

  /// No description provided for @purchases.
  ///
  /// In ar, this message translates to:
  /// **'المشتريات'**
  String get purchases;

  /// No description provided for @reports.
  ///
  /// In ar, this message translates to:
  /// **'التقارير'**
  String get reports;

  /// No description provided for @settings.
  ///
  /// In ar, this message translates to:
  /// **'الإعدادات'**
  String get settings;

  /// No description provided for @search.
  ///
  /// In ar, this message translates to:
  /// **'بحث'**
  String get search;

  /// No description provided for @retry.
  ///
  /// In ar, this message translates to:
  /// **'إعادة المحاولة'**
  String get retry;

  /// No description provided for @empty.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد بيانات'**
  String get empty;

  /// No description provided for @save.
  ///
  /// In ar, this message translates to:
  /// **'حفظ'**
  String get save;

  /// No description provided for @create.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء'**
  String get create;

  /// No description provided for @edit.
  ///
  /// In ar, this message translates to:
  /// **'تعديل'**
  String get edit;

  /// No description provided for @delete.
  ///
  /// In ar, this message translates to:
  /// **'حذف'**
  String get delete;

  /// No description provided for @cancel.
  ///
  /// In ar, this message translates to:
  /// **'إلغاء'**
  String get cancel;

  /// No description provided for @issue.
  ///
  /// In ar, this message translates to:
  /// **'إصدار'**
  String get issue;

  /// No description provided for @send.
  ///
  /// In ar, this message translates to:
  /// **'إرسال'**
  String get send;

  /// No description provided for @remind.
  ///
  /// In ar, this message translates to:
  /// **'تذكير'**
  String get remind;

  /// No description provided for @accept.
  ///
  /// In ar, this message translates to:
  /// **'قبول'**
  String get accept;

  /// No description provided for @reject.
  ///
  /// In ar, this message translates to:
  /// **'رفض'**
  String get reject;

  /// No description provided for @convert.
  ///
  /// In ar, this message translates to:
  /// **'تحويل إلى فاتورة'**
  String get convert;

  /// No description provided for @pdf.
  ///
  /// In ar, this message translates to:
  /// **'ملف PDF'**
  String get pdf;

  /// No description provided for @download.
  ///
  /// In ar, this message translates to:
  /// **'تنزيل'**
  String get download;

  /// No description provided for @copy.
  ///
  /// In ar, this message translates to:
  /// **'نسخ'**
  String get copy;

  /// No description provided for @open.
  ///
  /// In ar, this message translates to:
  /// **'فتح'**
  String get open;

  /// No description provided for @refresh.
  ///
  /// In ar, this message translates to:
  /// **'تحديث'**
  String get refresh;

  /// No description provided for @recordPayment.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل دفعة'**
  String get recordPayment;

  /// No description provided for @reversePayment.
  ///
  /// In ar, this message translates to:
  /// **'عكس الدفعة'**
  String get reversePayment;

  /// No description provided for @checkout.
  ///
  /// In ar, this message translates to:
  /// **'رابط الدفع الإلكتروني'**
  String get checkout;

  /// No description provided for @createCheckout.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء رابط الدفع'**
  String get createCheckout;

  /// No description provided for @paymentLinkHint.
  ///
  /// In ar, this message translates to:
  /// **'إنشاء الرابط لا يعني أن الفاتورة دُفعت.'**
  String get paymentLinkHint;

  /// No description provided for @permissionDenied.
  ///
  /// In ar, this message translates to:
  /// **'لا تملك صلاحية تنفيذ هذا الإجراء.'**
  String get permissionDenied;

  /// No description provided for @sessionExpired.
  ///
  /// In ar, this message translates to:
  /// **'انتهت جلسة تسجيل الدخول.'**
  String get sessionExpired;

  /// No description provided for @offline.
  ///
  /// In ar, this message translates to:
  /// **'تعذر الاتصال بالخادم. تحقق من الشبكة.'**
  String get offline;

  /// No description provided for @documentStatus.
  ///
  /// In ar, this message translates to:
  /// **'حالة المستند'**
  String get documentStatus;

  /// No description provided for @outcome.
  ///
  /// In ar, this message translates to:
  /// **'النتيجة التجارية'**
  String get outcome;

  /// No description provided for @deliveryStatus.
  ///
  /// In ar, this message translates to:
  /// **'حالة التسليم'**
  String get deliveryStatus;

  /// No description provided for @paymentStatus.
  ///
  /// In ar, this message translates to:
  /// **'حالة التحصيل'**
  String get paymentStatus;

  /// No description provided for @outstanding.
  ///
  /// In ar, this message translates to:
  /// **'الرصيد المستحق'**
  String get outstanding;

  /// No description provided for @subtotal.
  ///
  /// In ar, this message translates to:
  /// **'المجموع الفرعي'**
  String get subtotal;

  /// No description provided for @tax.
  ///
  /// In ar, this message translates to:
  /// **'الضريبة'**
  String get tax;

  /// No description provided for @total.
  ///
  /// In ar, this message translates to:
  /// **'الإجمالي'**
  String get total;

  /// No description provided for @paid.
  ///
  /// In ar, this message translates to:
  /// **'المدفوع'**
  String get paid;

  /// No description provided for @due.
  ///
  /// In ar, this message translates to:
  /// **'المستحق'**
  String get due;

  /// No description provided for @customer.
  ///
  /// In ar, this message translates to:
  /// **'العميل'**
  String get customer;

  /// No description provided for @amount.
  ///
  /// In ar, this message translates to:
  /// **'المبلغ'**
  String get amount;

  /// No description provided for @method.
  ///
  /// In ar, this message translates to:
  /// **'طريقة الدفع'**
  String get method;

  /// No description provided for @reference.
  ///
  /// In ar, this message translates to:
  /// **'المرجع'**
  String get reference;

  /// No description provided for @date.
  ///
  /// In ar, this message translates to:
  /// **'التاريخ'**
  String get date;

  /// No description provided for @status.
  ///
  /// In ar, this message translates to:
  /// **'الحالة'**
  String get status;

  /// No description provided for @confirm.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد'**
  String get confirm;

  /// No description provided for @confirmDestructive.
  ///
  /// In ar, this message translates to:
  /// **'هل أنت متأكد من تنفيذ هذا الإجراء؟'**
  String get confirmDestructive;

  /// No description provided for @copied.
  ///
  /// In ar, this message translates to:
  /// **'تم النسخ'**
  String get copied;

  /// No description provided for @success.
  ///
  /// In ar, this message translates to:
  /// **'تم بنجاح'**
  String get success;

  /// No description provided for @more.
  ///
  /// In ar, this message translates to:
  /// **'المزيد'**
  String get more;

  /// No description provided for @overview.
  ///
  /// In ar, this message translates to:
  /// **'نظرة عامة'**
  String get overview;

  /// No description provided for @lines.
  ///
  /// In ar, this message translates to:
  /// **'البنود'**
  String get lines;

  /// No description provided for @notesField.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات'**
  String get notesField;

  /// No description provided for @vatNumber.
  ///
  /// In ar, this message translates to:
  /// **'الرقم الضريبي'**
  String get vatNumber;

  /// No description provided for @crNumber.
  ///
  /// In ar, this message translates to:
  /// **'السجل التجاري'**
  String get crNumber;

  /// No description provided for @email.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني'**
  String get email;

  /// No description provided for @phone.
  ///
  /// In ar, this message translates to:
  /// **'الجوال'**
  String get phone;

  /// No description provided for @company.
  ///
  /// In ar, this message translates to:
  /// **'شركة'**
  String get company;

  /// No description provided for @individual.
  ///
  /// In ar, this message translates to:
  /// **'فرد'**
  String get individual;

  /// No description provided for @draft.
  ///
  /// In ar, this message translates to:
  /// **'مسودة'**
  String get draft;

  /// No description provided for @issued.
  ///
  /// In ar, this message translates to:
  /// **'صادرة'**
  String get issued;

  /// No description provided for @cancelled.
  ///
  /// In ar, this message translates to:
  /// **'ملغاة'**
  String get cancelled;

  /// No description provided for @pending.
  ///
  /// In ar, this message translates to:
  /// **'قيد الانتظار'**
  String get pending;

  /// No description provided for @accepted.
  ///
  /// In ar, this message translates to:
  /// **'مقبولة'**
  String get accepted;

  /// No description provided for @rejected.
  ///
  /// In ar, this message translates to:
  /// **'مرفوضة'**
  String get rejected;

  /// No description provided for @expired.
  ///
  /// In ar, this message translates to:
  /// **'منتهية'**
  String get expired;

  /// No description provided for @converted.
  ///
  /// In ar, this message translates to:
  /// **'محوّلة'**
  String get converted;

  /// No description provided for @unpaid.
  ///
  /// In ar, this message translates to:
  /// **'غير مدفوعة'**
  String get unpaid;

  /// No description provided for @partial.
  ///
  /// In ar, this message translates to:
  /// **'مدفوعة جزئياً'**
  String get partial;

  /// No description provided for @overdue.
  ///
  /// In ar, this message translates to:
  /// **'متأخرة'**
  String get overdue;

  /// No description provided for @posted.
  ///
  /// In ar, this message translates to:
  /// **'مرحّلة'**
  String get posted;

  /// No description provided for @voided.
  ///
  /// In ar, this message translates to:
  /// **'ملغاة'**
  String get voided;

  /// No description provided for @reversed.
  ///
  /// In ar, this message translates to:
  /// **'معكوسة'**
  String get reversed;

  /// No description provided for @credit.
  ///
  /// In ar, this message translates to:
  /// **'إشعار دائن'**
  String get credit;

  /// No description provided for @debit.
  ///
  /// In ar, this message translates to:
  /// **'إشعار مدين'**
  String get debit;

  /// No description provided for @apiHost.
  ///
  /// In ar, this message translates to:
  /// **'عنوان الخادم'**
  String get apiHost;

  /// No description provided for @theme.
  ///
  /// In ar, this message translates to:
  /// **'المظهر'**
  String get theme;

  /// No description provided for @language.
  ///
  /// In ar, this message translates to:
  /// **'اللغة'**
  String get language;

  /// No description provided for @arabic.
  ///
  /// In ar, this message translates to:
  /// **'العربية'**
  String get arabic;

  /// No description provided for @english.
  ///
  /// In ar, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @sales.
  ///
  /// In ar, this message translates to:
  /// **'المبيعات'**
  String get sales;

  /// No description provided for @receivables.
  ///
  /// In ar, this message translates to:
  /// **'الذمم المدينة'**
  String get receivables;

  /// No description provided for @payables.
  ///
  /// In ar, this message translates to:
  /// **'الذمم الدائنة'**
  String get payables;

  /// No description provided for @invoicesDue.
  ///
  /// In ar, this message translates to:
  /// **'فواتير مستحقة'**
  String get invoicesDue;

  /// No description provided for @overdueInvoices.
  ///
  /// In ar, this message translates to:
  /// **'فواتير متأخرة'**
  String get overdueInvoices;

  /// No description provided for @paidThisPeriod.
  ///
  /// In ar, this message translates to:
  /// **'المحصّل هذه الفترة'**
  String get paidThisPeriod;

  /// No description provided for @recentInvoices.
  ///
  /// In ar, this message translates to:
  /// **'أحدث الفواتير'**
  String get recentInvoices;

  /// No description provided for @recentPayments.
  ///
  /// In ar, this message translates to:
  /// **'أحدث المدفوعات'**
  String get recentPayments;

  /// No description provided for @noPermissionScreen.
  ///
  /// In ar, this message translates to:
  /// **'هذه الشاشة غير متاحة لصلاحياتك الحالية.'**
  String get noPermissionScreen;

  /// No description provided for @checkoutUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'رابط الدفع الإلكتروني غير متاح.'**
  String get checkoutUnavailable;

  /// No description provided for @neverMarkPaidLocally.
  ///
  /// In ar, this message translates to:
  /// **'لن تُعلَّم الفاتورة مدفوعة إلا بعد تأكيد الخادم.'**
  String get neverMarkPaidLocally;

  /// No description provided for @recipient.
  ///
  /// In ar, this message translates to:
  /// **'المستلم'**
  String get recipient;

  /// No description provided for @quantity.
  ///
  /// In ar, this message translates to:
  /// **'الكمية'**
  String get quantity;

  /// No description provided for @price.
  ///
  /// In ar, this message translates to:
  /// **'السعر'**
  String get price;

  /// No description provided for @description.
  ///
  /// In ar, this message translates to:
  /// **'الوصف'**
  String get description;

  /// No description provided for @unit.
  ///
  /// In ar, this message translates to:
  /// **'الوحدة'**
  String get unit;

  /// No description provided for @issueDate.
  ///
  /// In ar, this message translates to:
  /// **'تاريخ الإصدار'**
  String get issueDate;

  /// No description provided for @dueDate.
  ///
  /// In ar, this message translates to:
  /// **'تاريخ الاستحقاق'**
  String get dueDate;

  /// No description provided for @expiryDate.
  ///
  /// In ar, this message translates to:
  /// **'تاريخ الانتهاء'**
  String get expiryDate;

  /// No description provided for @from.
  ///
  /// In ar, this message translates to:
  /// **'من'**
  String get from;

  /// No description provided for @to.
  ///
  /// In ar, this message translates to:
  /// **'إلى'**
  String get to;

  /// No description provided for @openingBalance.
  ///
  /// In ar, this message translates to:
  /// **'الرصيد الافتتاحي'**
  String get openingBalance;

  /// No description provided for @closingBalance.
  ///
  /// In ar, this message translates to:
  /// **'الرصيد الختامي'**
  String get closingBalance;

  /// No description provided for @csv.
  ///
  /// In ar, this message translates to:
  /// **'CSV'**
  String get csv;

  /// No description provided for @suppliers.
  ///
  /// In ar, this message translates to:
  /// **'الموردون'**
  String get suppliers;

  /// No description provided for @category.
  ///
  /// In ar, this message translates to:
  /// **'التصنيف'**
  String get category;

  /// No description provided for @attachment.
  ///
  /// In ar, this message translates to:
  /// **'مرفق'**
  String get attachment;

  /// No description provided for @generateInvoice.
  ///
  /// In ar, this message translates to:
  /// **'توليد فاتورة مسودة'**
  String get generateInvoice;

  /// No description provided for @signContract.
  ///
  /// In ar, this message translates to:
  /// **'تفعيل / توقيع'**
  String get signContract;

  /// No description provided for @closeContract.
  ///
  /// In ar, this message translates to:
  /// **'إغلاق'**
  String get closeContract;

  /// No description provided for @billingSchedule.
  ///
  /// In ar, this message translates to:
  /// **'جدول الفوترة'**
  String get billingSchedule;

  /// No description provided for @profitLoss.
  ///
  /// In ar, this message translates to:
  /// **'الأرباح والخسائر'**
  String get profitLoss;

  /// No description provided for @trialBalance.
  ///
  /// In ar, this message translates to:
  /// **'ميزان المراجعة'**
  String get trialBalance;

  /// No description provided for @cashFlow.
  ///
  /// In ar, this message translates to:
  /// **'التدفقات النقدية'**
  String get cashFlow;

  /// No description provided for @balanceSheet.
  ///
  /// In ar, this message translates to:
  /// **'الميزانية'**
  String get balanceSheet;

  /// No description provided for @generalLedger.
  ///
  /// In ar, this message translates to:
  /// **'دفتر الأستاذ'**
  String get generalLedger;

  /// No description provided for @arAging.
  ///
  /// In ar, this message translates to:
  /// **'أعمار الذمم المدينة'**
  String get arAging;

  /// No description provided for @apAging.
  ///
  /// In ar, this message translates to:
  /// **'أعمار الذمم الدائنة'**
  String get apAging;

  /// No description provided for @loadMore.
  ///
  /// In ar, this message translates to:
  /// **'تحميل المزيد'**
  String get loadMore;

  /// No description provided for @globalSearch.
  ///
  /// In ar, this message translates to:
  /// **'بحث مالي'**
  String get globalSearch;

  /// No description provided for @exports.
  ///
  /// In ar, this message translates to:
  /// **'تصدير CSV'**
  String get exports;

  /// No description provided for @auditTrail.
  ///
  /// In ar, this message translates to:
  /// **'سجل التدقيق'**
  String get auditTrail;

  /// No description provided for @downloadAttachment.
  ///
  /// In ar, this message translates to:
  /// **'تنزيل المرفق'**
  String get downloadAttachment;

  /// No description provided for @noResults.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد نتائج'**
  String get noResults;

  /// No description provided for @selectCustomer.
  ///
  /// In ar, this message translates to:
  /// **'اختر العميل'**
  String get selectCustomer;

  /// No description provided for @selectSupplier.
  ///
  /// In ar, this message translates to:
  /// **'اختر المورد'**
  String get selectSupplier;

  /// No description provided for @addLine.
  ///
  /// In ar, this message translates to:
  /// **'إضافة بند'**
  String get addLine;

  /// No description provided for @removeLine.
  ///
  /// In ar, this message translates to:
  /// **'حذف البند'**
  String get removeLine;

  /// No description provided for @taxRate.
  ///
  /// In ar, this message translates to:
  /// **'نسبة الضريبة'**
  String get taxRate;

  /// No description provided for @discount.
  ///
  /// In ar, this message translates to:
  /// **'الخصم'**
  String get discount;

  /// No description provided for @invoiceId.
  ///
  /// In ar, this message translates to:
  /// **'رقم الفاتورة'**
  String get invoiceId;

  String get taxableAmount;
  String get amountCredited;
  String get amountDebited;
  String get whatsapp;
  String get buildingNumber;
  String get district;
  String get postalCode;
  String get city;
  String get street;
  String get country;
  String get paymentTerms;
  String get treasuryAccount;
  String get recurring;
  String get netProfit;
  String get outputVat;
  String get inputVat;
  String get netVat;
  String get cashBalance;
  String get bankBalance;
  String get activeContracts;
  String get recentExpenses;
  String get statementDebit;
  String get statementCredit;
  String get runningBalance;
  String get invoicesTotal;
  String get paymentsTotal;
  String get creditsTotal;
  String get debitsTotal;
  String get zatcaQr;
  String get terms;
  String get rejectionReason;
  String get website;
  String get currency;
  String get invoicePrefix;
  String get defaultVatRate;
  String get zatcaMode;
  String get nextRun;
  String get frequency;
  String get autoIssue;
  String get generatedCount;
  String get paymentDate;
  String get supplier;
  String get openingCash;
  String get netChange;
  String get closingCash;
  String get assets;
  String get liabilities;
  String get equity;
  String get revenue;
  String get cogs;
  String get grossProfit;
  String get additionalNumber;
  String get companyNameAr;
  String get addressLine;
  String get filterAll;
  String get generatedInvoices;
  String get invoicedTotal;
  String get snapshots;
  String get reason;
  String get inventoryValuation;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
