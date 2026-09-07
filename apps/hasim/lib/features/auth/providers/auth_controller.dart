import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_client.dart';
import 'package:hasim/core/network/api_exception.dart';

class AuthState {
  const AuthState({
    this.bootstrapping = true,
    this.loading = false,
    this.token,
    this.user,
    this.workspaces = const [],
    this.workspace,
    this.error,
  });

  final bool bootstrapping;
  final bool loading;
  final String? token;
  final UserModel? user;
  final List<WorkspaceModel> workspaces;
  final WorkspaceModel? workspace;
  final String? error;

  bool get isAuthenticated => token != null && token!.isNotEmpty;

  AuthState copyWith({
    bool? bootstrapping,
    bool? loading,
    String? token,
    UserModel? user,
    List<WorkspaceModel>? workspaces,
    WorkspaceModel? workspace,
    String? error,
    bool clearError = false,
    bool clearSession = false,
  }) {
    return AuthState(
      bootstrapping: bootstrapping ?? this.bootstrapping,
      loading: loading ?? this.loading,
      token: clearSession ? null : (token ?? this.token),
      user: clearSession ? null : (user ?? this.user),
      workspaces: clearSession ? const [] : (workspaces ?? this.workspaces),
      workspace: clearSession ? null : (workspace ?? this.workspace),
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._ref) : super(const AuthState()) {
    _wireUnauthorized();
    bootstrap();
  }

  final Ref _ref;
  StreamSubscription? _unauthSub;
  bool _clearingUnauthorized = false;

  void _wireUnauthorized() {
    final api = _ref.read(apiClientProvider);
    api.setOnUnauthorized(() {
      unawaited(clearSessionFromUnauthorized());
    });
    _unauthSub = unauthorizedEvents.stream.listen((_) {
      unawaited(clearSessionFromUnauthorized());
    });
  }

  Future<void> clearSessionFromUnauthorized() async {
    if (_clearingUnauthorized || !state.isAuthenticated) return;
    _clearingUnauthorized = true;
    try {
      await _ref.read(realtimeServiceProvider).stop();
      await _ref.read(secureStoreProvider).clearToken();
      await _ref.read(prefsStoreProvider).setWorkspaceId(null);
      _ref.read(apiClientProvider).resetUnauthorizedGate();
      state = const AuthState(bootstrapping: false);
    } finally {
      _clearingUnauthorized = false;
    }
  }

  @override
  void dispose() {
    _unauthSub?.cancel();
    super.dispose();
  }

  Future<void> bootstrap() async {
    final token = await _ref.read(secureStoreProvider).readToken();
    if (token == null || token.isEmpty) {
      state = state.copyWith(bootstrapping: false, clearSession: true);
      return;
    }
    try {
      final me = await _ref.read(authRepositoryProvider).me();
      final prefs = _ref.read(prefsStoreProvider);
      WorkspaceModel? workspace = me.workspace;
      if (workspace == null && prefs.workspaceId != null) {
        for (final w in me.workspaces) {
          if (w.id == prefs.workspaceId) {
            workspace = w;
            break;
          }
        }
      }
      workspace ??= me.workspaces.isNotEmpty ? me.workspaces.first : null;
      if (workspace != null) {
        await prefs.setWorkspaceId(workspace.id);
      }
      _ref.read(apiClientProvider).resetUnauthorizedGate();
      state = AuthState(
        bootstrapping: false,
        token: token,
        user: me.user,
        workspaces: me.workspaces,
        workspace: workspace,
      );
      await _ref.read(realtimeServiceProvider).start();
      await _ref.read(pushServiceProvider).initializeAndRegister();
    } catch (_) {
      await _ref.read(secureStoreProvider).clearToken();
      state = const AuthState(bootstrapping: false);
    }
  }

  Future<void> _applyLogin(LoginResult result) async {
    await _ref.read(secureStoreProvider).saveToken(result.token);
    final workspace = result.workspace ?? (result.workspaces.isNotEmpty ? result.workspaces.first : null);
    if (workspace != null) {
      await _ref.read(prefsStoreProvider).setWorkspaceId(workspace.id);
    }
    _ref.read(apiClientProvider).resetUnauthorizedGate();
    state = AuthState(
      bootstrapping: false,
      loading: false,
      token: result.token,
      user: result.user,
      workspaces: result.workspaces,
      workspace: workspace,
    );
    await _ref.read(realtimeServiceProvider).start();
    await _ref.read(pushServiceProvider).initializeAndRegister();
  }

  Future<bool> login(String emailOrPhone, String password) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final result = await _ref.read(authRepositoryProvider).login(
            emailOrPhone: emailOrPhone,
            password: password,
          );
      await _applyLogin(result);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
      return false;
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر تسجيل الدخول.');
      return false;
    }
  }

  Future<bool> socialLogin({
    required String provider,
    required String accessToken,
  }) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final result = await _ref.read(authRepositoryProvider).socialLogin(
            provider: provider,
            accessToken: accessToken,
          );
      await _applyLogin(result);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
      return false;
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر تسجيل الدخول عبر Google.');
      return false;
    }
  }

  Future<String?> forgotPassword(String email) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final msg = await _ref.read(authRepositoryProvider).forgotPassword(email);
      state = state.copyWith(loading: false);
      return msg;
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
      return null;
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر إرسال رابط إعادة التعيين.');
      return null;
    }
  }

  Future<bool> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      await _ref.read(authRepositoryProvider).resetPassword(
            email: email,
            token: token,
            password: password,
            passwordConfirmation: passwordConfirmation,
          );
      state = state.copyWith(loading: false);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
      return false;
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر إعادة تعيين كلمة المرور.');
      return false;
    }
  }

  Future<bool> updateProfile({
    required String name,
    String? email,
    String? phone,
  }) async {
    try {
      final user = await _ref.read(authRepositoryProvider).updateProfile(
            name: name,
            email: email,
            phone: phone,
          );
      state = state.copyWith(user: user);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(error: e.message);
      return false;
    } catch (_) {
      state = state.copyWith(error: 'تعذر تحديث الملف الشخصي.');
      return false;
    }
  }

  void setUser(UserModel user) {
    state = state.copyWith(user: user);
  }

  Future<void> switchWorkspace(WorkspaceModel workspace) async {
    await _ref.read(workspaceRepositoryProvider).switchTo(workspace.id);
    await _ref.read(prefsStoreProvider).setWorkspaceId(workspace.id);
    state = state.copyWith(workspace: workspace);
  }

  Future<void> logout() async {
    try {
      await _ref.read(authRepositoryProvider).logout();
    } catch (_) {}
    await _ref.read(realtimeServiceProvider).stop();
    await _ref.read(secureStoreProvider).clearToken();
    await _ref.read(prefsStoreProvider).setWorkspaceId(null);
    _ref.read(apiClientProvider).resetUnauthorizedGate();
    state = const AuthState(bootstrapping: false);
  }
}

final authControllerProvider = StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(ref);
});
