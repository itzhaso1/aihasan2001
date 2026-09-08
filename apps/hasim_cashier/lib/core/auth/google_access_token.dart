import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../api/cashier_api.dart';
import '../config/app_config.dart';
import 'google_desktop_oauth.dart'
    if (dart.library.html) 'google_desktop_oauth_unsupported.dart';

/// Obtains a Google access token for Laravel `POST /auth/social`.
class GoogleAccessTokenClient {
  Future<String> obtainAccessToken() async {
    Object? pluginError;
    if (_pluginSupported) {
      try {
        return await _pluginSignIn();
      } catch (e) {
        pluginError = e;
        if (!_isDesktop) {
          throw _mapPluginError(e);
        }
      }
    }
    try {
      return await GoogleDesktopOauth.signIn();
    } catch (e) {
      if (e is ApiException) rethrow;
      throw _mapPluginError(pluginError ?? e);
    }
  }

  bool get _pluginSupported {
    if (kIsWeb) return true;
    return defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS ||
        defaultTargetPlatform == TargetPlatform.macOS;
  }

  bool get _isDesktop {
    if (kIsWeb) return false;
    return defaultTargetPlatform == TargetPlatform.windows ||
        defaultTargetPlatform == TargetPlatform.linux ||
        defaultTargetPlatform == TargetPlatform.macOS;
  }

  Future<String> _pluginSignIn() async {
    final clientId = AppConfig.googleOAuthClientId.trim();
    final serverClientId = AppConfig.googleOAuthServerClientId.trim();
    final google = GoogleSignIn(
      scopes: const ['email', 'profile'],
      clientId: clientId.isEmpty ? null : clientId,
      serverClientId: serverClientId.isEmpty ? null : serverClientId,
    );
    final account = await google.signIn();
    if (account == null) {
      throw ApiException('تم إلغاء تسجيل الدخول عبر Google.');
    }
    final auth = await account.authentication;
    final token = (auth.accessToken ?? auth.idToken)?.trim() ?? '';
    if (token.isEmpty) {
      throw ApiException('يحتاج إعداد Google');
    }
    return token;
  }

  ApiException _mapPluginError(Object error) {
    if (error is ApiException) return error;
    final msg = error.toString().toLowerCase();
    final needsSetup = msg.contains('client') ||
        msg.contains('platform') ||
        msg.contains('missing') ||
        msg.contains('not been configured') ||
        msg.contains('sign_in_failed') ||
        msg.contains('missingpluginexception') ||
        msg.contains('10:') ||
        msg.contains('12500');
    return ApiException(
      needsSetup ? 'يحتاج إعداد Google' : 'تعذر تسجيل الدخول عبر Google.',
    );
  }
}
