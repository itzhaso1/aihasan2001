
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';

class AppointmentsState {
  const AppointmentsState({this.tab = 'today', this.loading = false, this.items = const [], this.error});
  final String tab;
  final bool loading;
  final List<AppointmentModel> items;
  final String? error;
  AppointmentsState copyWith({String? tab, bool? loading, List<AppointmentModel>? items, String? error}) =>
      AppointmentsState(tab: tab ?? this.tab, loading: loading ?? this.loading, items: items ?? this.items, error: error);
}

class AppointmentsController extends StateNotifier<AppointmentsState> {
  AppointmentsController(this._ref) : super(const AppointmentsState()) {
    refresh();
  }
  final Ref _ref;

  Future<void> setTab(String tab) async {
    state = state.copyWith(tab: tab);
    await refresh();
  }

  Future<void> refresh() async {
    state = state.copyWith(loading: true, error: null);
    try {
      final repo = _ref.read(appointmentRepositoryProvider);
      final items = state.tab == 'upcoming' ? await repo.upcoming() : await repo.today();
      state = state.copyWith(loading: false, items: items);
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر تحميل الحجوزات.');
    }
  }
}

final appointmentsControllerProvider =
    StateNotifierProvider<AppointmentsController, AppointmentsState>((ref) => AppointmentsController(ref));
