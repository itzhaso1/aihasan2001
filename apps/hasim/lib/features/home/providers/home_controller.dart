import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

class HomeState {
  const HomeState({
    this.loading = false,
    this.snapshot,
    this.recentConversations = const [],
    this.todaysAppointments = const [],
    this.error,
  });

  final bool loading;
  final HomeSnapshot? snapshot;
  final List<ConversationModel> recentConversations;
  final List<AppointmentModel> todaysAppointments;
  final String? error;

  HomeState copyWith({
    bool? loading,
    HomeSnapshot? snapshot,
    List<ConversationModel>? recentConversations,
    List<AppointmentModel>? todaysAppointments,
    String? error,
  }) =>
      HomeState(
        loading: loading ?? this.loading,
        snapshot: snapshot ?? this.snapshot,
        recentConversations: recentConversations ?? this.recentConversations,
        todaysAppointments: todaysAppointments ?? this.todaysAppointments,
        error: error,
      );
}

class HomeController extends StateNotifier<HomeState> {
  HomeController(this._ref) : super(const HomeState()) {
    refresh();
  }
  final Ref _ref;

  Future<void> refresh() async {
    if (!_ref.read(authControllerProvider).isAuthenticated) return;
    state = state.copyWith(loading: true, error: null);
    try {
      final snap = await _ref.read(homeRepositoryProvider).home();
      List<ConversationModel> recent = const [];
      List<AppointmentModel> today = const [];
      try {
        final page = await _ref.read(conversationRepositoryProvider).list();
        recent = page.items.take(5).toList();
        await _ref.read(localCacheProvider).saveConversations(page.items);
      } catch (_) {
        recent = _ref.read(localCacheProvider).readConversations()?.take(5).toList() ?? const [];
      }
      try {
        today = await _ref.read(appointmentRepositoryProvider).today();
      } catch (_) {}
      state = HomeState(
        loading: false,
        snapshot: snap,
        recentConversations: recent,
        todaysAppointments: today,
      );
    } on ApiException catch (e) {
      state = HomeState(
        loading: false,
        snapshot: state.snapshot,
        recentConversations: state.recentConversations,
        todaysAppointments: state.todaysAppointments,
        error: e.message,
      );
    } catch (_) {
      state = HomeState(
        loading: false,
        snapshot: state.snapshot,
        recentConversations: state.recentConversations,
        todaysAppointments: state.todaysAppointments,
        error: 'تعذر تحديث الرئيسية.',
      );
    }
  }
}

final homeControllerProvider = StateNotifierProvider<HomeController, HomeState>((ref) => HomeController(ref));
