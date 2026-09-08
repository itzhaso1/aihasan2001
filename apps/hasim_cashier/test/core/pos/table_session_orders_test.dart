import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/pos/table_session_orders.dart';

void main() {
  final opened = DateTime.utc(2026, 9, 8, 12, 0);

  test('unpaid table orders stay on the open session', () {
    expect(
      isOrderInOpenTableSession(
        posStatus: 'new',
        paymentStatus: 'unpaid',
        createdAt: opened.subtract(const Duration(hours: 2)),
        openedAt: opened,
      ),
      isTrue,
    );
  });

  test('paid orders from an earlier sitting do not follow a new session', () {
    expect(
      isOrderInOpenTableSession(
        posStatus: 'completed',
        paymentStatus: 'paid',
        createdAt: opened.subtract(const Duration(minutes: 1)),
        completedAt: opened.subtract(const Duration(minutes: 1)),
        openedAt: opened,
      ),
      isFalse,
    );
  });

  test('paid orders created in the current sitting stay visible', () {
    expect(
      isOrderInOpenTableSession(
        posStatus: 'completed',
        paymentStatus: 'paid',
        createdAt: opened.add(const Duration(minutes: 2)),
        openedAt: opened,
      ),
      isTrue,
    );
  });

  test('session id mismatch drops paid history even if timestamps overlap', () {
    expect(
      isOrderInOpenTableSession(
        posStatus: 'completed',
        paymentStatus: 'paid',
        createdAt: opened.add(const Duration(minutes: 1)),
        openedAt: opened,
        orderSessionLocalId: 'sess-old',
        currentSessionLocalId: 'sess-new',
      ),
      isFalse,
    );
  });

  test('occupy snapshots without timestamps stay on the current sitting', () {
    expect(
      isSnapshotInOpenTableSession(
        {
          'pos_status': 'completed',
          'payment_status': 'paid',
          'total_amount': 15,
        },
        openedAt: opened,
        currentSessionLocalId: 'sess-now',
      ),
      isTrue,
    );
  });

  test('dated paid snapshots from before opened_at are dropped', () {
    expect(
      isSnapshotInOpenTableSession(
        {
          'pos_status': 'completed',
          'payment_status': 'paid',
          'created_at': opened
              .subtract(const Duration(minutes: 2))
              .toIso8601String(),
          'total_amount': 90,
        },
        openedAt: opened,
        currentSessionLocalId: 'sess-now',
      ),
      isFalse,
    );
  });
}
