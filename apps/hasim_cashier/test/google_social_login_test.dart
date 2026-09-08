import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_cashier/core/api/cashier_api.dart';
import 'package:hasim_cashier/core/api/cashier_network_policy.dart';
import 'package:hasim_cashier/core/config/app_config.dart';

void main() {
  test('offline-only still allows Laravel Google social login', () {
    expect(AppConfig.offlineOnly, isTrue);
    expect(
      CashierNetworkPolicy.allowRequest(
        offlineOnly: true,
        token: null,
        path: '/auth/social',
        method: 'POST',
      ),
      isTrue,
    );
    expect(
      CashierNetworkPolicy.cloudSetupPaths.contains('/auth/social'),
      isTrue,
    );
  });

  test('cashier client posts Google token to /auth/social', () async {
    late String path;
    late Map<String, dynamic> posted;
    final dio = Dio(
      BaseOptions(baseUrl: 'http://cashier.test/api/cashier/v1'),
    );
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          path = options.path;
          posted = Map<String, dynamic>.from(options.data as Map);
          handler.resolve(
            Response(
              requestOptions: options,
              statusCode: 200,
              data: {
                'success': true,
                'data': {
                  'token': 'sanctum-google',
                  'user': {
                    'id': 9,
                    'name': 'Google User',
                    'email': 'owner@hasim.test',
                  },
                  'workspace': {
                    'id': 12,
                    'name': 'المتجر',
                    'pos_enabled': true,
                  },
                  'workspaces': [
                    {'id': 12, 'name': 'المتجر', 'pos_enabled': true},
                  ],
                  'pos_enabled': true,
                },
              },
            ),
          );
        },
      ),
    );

    final data = await CashierApiClient(dio).post(
      '/auth/social',
      data: {
        'provider': 'google',
        'access_token': 'ya29.google-token',
        'device_name': 'كاشير حاسم',
        'device_type': 'cashier',
      },
    );

    expect(path, '/auth/social');
    expect(posted['provider'], 'google');
    expect(posted['access_token'], 'ya29.google-token');
    expect(data['token'], 'sanctum-google');
    expect(data['pos_enabled'], isTrue);
  });
}
