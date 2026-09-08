/// Arabic copy for Settings → مزامنة الآن.
///
/// Do not treat session/stock leftovers as "Laravel is down".
class SyncNowCopy {
  const SyncNowCopy._();

  static String afterFlush({
    required int synced,
    required int failed,
    required int scopedPending,
    required int leftovers,
    required bool authRequired,
    required String apiBase,
  }) {
    if (authRequired) {
      return 'الخادم رفض التوكن. أعد ربط السحابة من شاشة الدخول.';
    }
    if (failed > 0) {
      return 'فشلت $failed عملية · نجحت $synced · فواتير/منيو/طاولات بانتظار الدفع: $scopedPending.';
    }
    if (synced > 0) {
      return 'تمت مزامنة $synced عملية.';
    }
    if (scopedPending > 0) {
      return 'ما زال $scopedPending فاتورة/منيو/طاولة بانتظار القبول. تحقق من Laravel على $apiBase.';
    }
    if (leftovers > 0) {
      return 'لا توجد فواتير أو منيو/طاولات جاهزة للدفع. $leftovers عملية جلسة/مخزون/أخرى خارج العقد تبقى محلية ولا تُرسل إلى Laravel.';
    }
    return 'لا توجد فواتير أو تغييرات منيو/طاولات بانتظار المزامنة.';
  }
}
