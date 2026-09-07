import 'dart:convert';

import 'package:drift/drift.dart';

import '../local_db/app_database.dart';
import '../pos/pos_mode.dart';
import '../repositories/sync_queue_repository.dart';

/// Why a queue row is still visible after a successful takeaway push.
enum SyncQueueBucket {
  /// A — paid takeaway/table sale ready for /sync/push.
  ready,

  /// Invoice waiting for its local order.server_id.
  waitingParent,

  /// B — permanently failed.
  failed,

  /// C — local entity already has a server id; queue row was not ACKed.
  alreadyApplied,

  /// D — table_session / stock / order update outside the invoice contract.
  unsupported,

  /// E — reserved standalone workspace 900001 mixed into a connected queue.
  standalone,

  /// Paid table/takeaway sale not yet ready (backoff or missing product id).
  blocked,
}

class SyncQueueClassification {
  const SyncQueueClassification({
    required this.row,
    required this.bucket,
    required this.reason,
  });

  final SyncQueueItem row;
  final SyncQueueBucket bucket;
  final String reason;
}

class SyncQueueCountsByBucket {
  const SyncQueueCountsByBucket({
    this.ready = 0,
    this.waitingParent = 0,
    this.failed = 0,
    this.alreadyApplied = 0,
    this.unsupported = 0,
    this.standalone = 0,
    this.blocked = 0,
  });

  final int ready;
  final int waitingParent;
  final int failed;
  final int alreadyApplied;
  final int unsupported;
  final int standalone;
  final int blocked;

  /// Paid takeaway/table rows that should move on the next successful push.
  /// Unpaid table leftovers, session ops, and missing product ids stay out.
  int get invoicePending => ready + waitingParent;

  int get totalOpen =>
      ready +
      waitingParent +
      failed +
      alreadyApplied +
      unsupported +
      standalone +
      blocked;
}

/// Classifies sync_queue rows without deleting or rewriting them.
class SyncQueueClassifier {
  const SyncQueueClassifier(this._db);

  final AppDatabase _db;

  static const saleTypes = {'takeaway', 'table'};

  static Map<String, dynamic> decodePayload(String raw) {
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {}
    return const {};
  }

  static String? saleTypeOf(SyncQueueItem row) {
    final payload = decodePayload(row.payloadJson);
    final type = '${payload['order_type'] ?? ''}'.trim().toLowerCase();
    if (saleTypes.contains(type)) return type;
    return null;
  }

  Future<List<SyncQueueClassification>> classifyWorkspace(int workspaceId) async {
    final rows = await SyncQueueRepository(_db).pendingForWorkspace(workspaceId);
    return [for (final row in rows) await classify(row)];
  }

  Future<SyncQueueCountsByBucket> counts(int workspaceId) async {
    final items = await classifyWorkspace(workspaceId);
    var ready = 0;
    var waitingParent = 0;
    var failed = 0;
    var alreadyApplied = 0;
    var unsupported = 0;
    var standalone = 0;
    var blocked = 0;
    for (final item in items) {
      switch (item.bucket) {
        case SyncQueueBucket.ready:
          ready++;
        case SyncQueueBucket.waitingParent:
          waitingParent++;
        case SyncQueueBucket.failed:
          failed++;
        case SyncQueueBucket.alreadyApplied:
          alreadyApplied++;
        case SyncQueueBucket.unsupported:
          unsupported++;
        case SyncQueueBucket.standalone:
          standalone++;
        case SyncQueueBucket.blocked:
          blocked++;
      }
    }
    return SyncQueueCountsByBucket(
      ready: ready,
      waitingParent: waitingParent,
      failed: failed,
      alreadyApplied: alreadyApplied,
      unsupported: unsupported,
      standalone: standalone,
      blocked: blocked,
    );
  }

  Future<SyncQueueClassification> classify(SyncQueueItem row) async {
    if (PosMode.isReservedStandaloneWorkspace(row.workspaceId)) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.standalone,
        reason: 'عملية على المساحة المحلية 900001 وليست مساحة السحابة.',
      );
    }
    if (row.status == 'failed') {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.failed,
        reason: row.lastError ?? 'فشلت العملية بشكل دائم.',
      );
    }

    if (row.entityType == 'table_session' ||
        row.entityType == 'stock' ||
        row.entityType == 'stock_movement' ||
        (row.entityType == 'order' &&
            (row.operation == 'update' || row.operation == 'delete'))) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.unsupported,
        reason:
            'خارج عقد مزامنة الفواتير الحالي (order.created + invoice.created).',
      );
    }

    if (row.entityType == 'customer' && row.operation == 'create') {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.ready,
        reason: 'عميل جديد مطلوب قبل دفع الطلب.',
      );
    }

    if (row.entityType == 'order' && row.operation == 'create') {
      return _classifyOrderCreate(row);
    }
    if (row.entityType == 'invoice' && row.operation == 'create') {
      return _classifyInvoiceCreate(row);
    }

    return SyncQueueClassification(
      row: row,
      bucket: SyncQueueBucket.unsupported,
      reason: 'نوع العملية ${row.entityType}.${row.operation} غير مدعوم للدفع.',
    );
  }

  Future<SyncQueueClassification> _classifyOrderCreate(SyncQueueItem row) async {
    final payload = decodePayload(row.payloadJson);
    final type = '${payload['order_type'] ?? ''}'.trim().toLowerCase();
    if (!saleTypes.contains(type)) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.unsupported,
        reason: 'نوع الطلب "$type" خارج عقد السفري/الطاولة.',
      );
    }
    final order = await _order(row.workspaceId, row.entityId);
    if (order?.serverId != null && order!.serverId! > 0) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.alreadyApplied,
        reason: 'الطلب المحلي لديه server_id=${order.serverId} والطابور لم يُحدَّث.',
      );
    }
    if (type == 'table' &&
        (order == null || order.paymentStatus.trim().toLowerCase() != 'paid')) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.unsupported,
        reason: 'طلب طاولة غير مدفوع بعد. يُرسل بعد إغلاق الحساب نقداً.',
      );
    }
    final items = payload['items'];
    if (items is! List || items.isEmpty) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.blocked,
        reason: 'لا توجد عناصر صالحة للدفع.',
      );
    }
    for (final item in items) {
      if (item is! Map) {
        return SyncQueueClassification(
          row: row,
          bucket: SyncQueueBucket.blocked,
          reason: 'عنصر بدون معرّف سحابة.',
        );
      }
      final menuId = (item['pos_menu_item_id'] as num?)?.toInt() ?? 0;
      final qty = (item['quantity'] as num?)?.toInt() ?? 0;
      if (menuId <= 0 || qty < 1) {
        return SyncQueueClassification(
          row: row,
          bucket: SyncQueueBucket.blocked,
          reason: 'صنف محلي بدون server id — لا يُرسل إلى Laravel.',
        );
      }
    }
    return SyncQueueClassification(
      row: row,
      bucket: SyncQueueBucket.ready,
      reason: type == 'table'
          ? 'طلب طاولة مدفوع جاهز للدفع.'
          : 'طلب سفري جاهز للدفع.',
    );
  }

  Future<SyncQueueClassification> _classifyInvoiceCreate(
    SyncQueueItem row,
  ) async {
    final payload = decodePayload(row.payloadJson);
    var type = '${payload['order_type'] ?? ''}'.trim().toLowerCase();
    final orderLocalId = '${payload['order_local_id'] ?? ''}'.trim();
    final invoice = await _invoice(row.workspaceId, row.entityId);
    if (invoice?.serverId != null && invoice!.serverId! > 0) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.alreadyApplied,
        reason:
            'الفاتورة المحلية لديها server_id=${invoice.serverId} والطابور لم يُحدَّث.',
      );
    }
    if (orderLocalId.isEmpty) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.blocked,
        reason: 'الفاتورة بلا order_local_id.',
      );
    }
    final order = await _order(row.workspaceId, orderLocalId);
    if (order == null) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.blocked,
        reason: 'الطلب المحلي للفاتورة غير موجود.',
      );
    }
    if (type.isEmpty) {
      type = order.orderType.trim().toLowerCase();
    }
    if (!saleTypes.contains(type)) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.unsupported,
        reason: 'فاتورة لنوع طلب خارج العقد.',
      );
    }
    if (order.serverId == null || order.serverId! <= 0) {
      return SyncQueueClassification(
        row: row,
        bucket: SyncQueueBucket.waitingParent,
        reason: 'الفاتورة تنتظر وصول الطلب إلى Laravel أولاً.',
      );
    }
    return SyncQueueClassification(
      row: row,
      bucket: SyncQueueBucket.ready,
      reason: 'فاتورة جاهزة بعد وجود طلب السحابة.',
    );
  }

  Future<LocalOrder?> _order(int workspaceId, String localId) {
    return (_db.select(_db.localOrders)..where(
          (t) => t.workspaceId.equals(workspaceId) & t.localId.equals(localId),
        ))
        .getSingleOrNull();
  }

  Future<LocalInvoice?> _invoice(int workspaceId, String localId) {
    return (_db.select(_db.localInvoices)..where(
          (t) => t.workspaceId.equals(workspaceId) & t.localId.equals(localId),
        ))
        .getSingleOrNull();
  }
}
