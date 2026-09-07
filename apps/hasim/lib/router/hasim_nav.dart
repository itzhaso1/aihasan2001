/// ثوابت تنقل تطبيق حاسم — مصدر واحد للتبويبات والمسار الأولي.
abstract final class HasimNav {
  static const initialShellRoute = '/conversations';

  /// ترتيب التبويبات في RTL (يمين → يسار بصريًا عبر Directionality).
  static const bottomLabels = <String>[
    'المحادثات',
    'البريد',
    'الحجوزات',
    'التحديثات',
    'المزيد',
  ];

  static const conversationsIndex = 0;
  static const emailIndex = 1;
  static const appointmentsIndex = 2;
  static const updatesIndex = 3;
  static const moreIndex = 4;
}
