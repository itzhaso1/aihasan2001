
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_client.dart';
import 'package:hasim/core/network/api_exception.dart';

class AppointmentRepository {
  AppointmentRepository(this._api);
  final ApiClient _api;

  Future<List<AppointmentModel>> today({String? search}) async {
    final res = await _api.get('/appointments/today', query: {
      if (search != null && search.isNotEmpty) 'search': search,
    });
    return asMapList(res.data).map(AppointmentModel.fromJson).toList();
  }

  Future<List<AppointmentModel>> upcoming({String? search}) async {
    final res = await _api.get('/appointments/upcoming', query: {
      if (search != null && search.isNotEmpty) 'search': search,
    });
    return asMapList(res.data).map(AppointmentModel.fromJson).toList();
  }

  Future<AppointmentModel> show(int id) async {
    final res = await _api.get('/appointments/$id');
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'الحجز غير موجود.');
    return AppointmentModel.fromJson(map);
  }

  Future<AppointmentModel> confirm(int id) async {
    final res = await _api.post('/appointments/$id/confirm', idempotent: true);
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'تعذر تأكيد الحجز.');
    return AppointmentModel.fromJson(map);
  }

  Future<AppointmentModel> cancel(int id, {String? reason}) async {
    final res = await _api.post('/appointments/$id/cancel', idempotent: true, body: {
      'reason': ?reason,
    });
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'تعذر إلغاء الحجز.');
    return AppointmentModel.fromJson(map);
  }

  Future<AppointmentModel> reschedule(int id, {required String startsAt, String? endsAt}) async {
    final res = await _api.post('/appointments/$id/reschedule', idempotent: true, body: {
      'starts_at': startsAt,
      'ends_at': ?endsAt,
    });
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'تعذر إعادة الجدولة.');
    return AppointmentModel.fromJson(map);
  }
}
