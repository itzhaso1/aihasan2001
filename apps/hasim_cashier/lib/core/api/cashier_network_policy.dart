import '../pos/pos_mode.dart';

/// Phase 1A network gate.
///
/// `offlineOnly` still blocks catalog/order/sync HTTP. First-connect auth,
/// workspace, and device registration are the only allowed cloud calls.
class CashierNetworkPolicy {
  const CashierNetworkPolicy._();

  static const cloudSetupPaths = <String>{
    '/auth/login',
    '/auth/logout',
    '/auth/me',
    '/workspaces',
    '/workspaces/current',
    '/workspaces/switch',
    '/devices/register',
  };

  static bool isCloudSetupPath(String path) {
    final normalized = path.split('?').first.trim();
    if (normalized.contains('/sync/') ||
        normalized.contains('/catalog/') ||
        normalized.contains('/orders') ||
        normalized.contains('/invoices') ||
        normalized.contains('/payments')) {
      return false;
    }
    for (final allowed in cloudSetupPaths) {
      if (normalized == allowed) return true;
      if (normalized.endsWith(allowed) &&
          (normalized.contains('/cashier/v1') ||
              normalized.contains('/api/cashier/v1'))) {
        return true;
      }
    }
    return false;
  }

  static bool allowRequest({
    required bool offlineOnly,
    required String? token,
    required String path,
  }) {
    if (PosMode.isStandaloneToken(token)) return false;
    if (!offlineOnly) return true;
    return isCloudSetupPath(path);
  }
}
