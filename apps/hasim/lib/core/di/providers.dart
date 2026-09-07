import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/network/api_client.dart';
import 'package:hasim/core/storage/local_cache.dart';
import 'package:hasim/core/storage/prefs_store.dart';
import 'package:hasim/core/storage/secure_store.dart';
import 'package:hasim/features/appointments/data/appointment_repository.dart';
import 'package:hasim/features/auth/data/auth_repository.dart';
import 'package:hasim/features/contacts/data/contact_group_repository.dart';
import 'package:hasim/features/contacts/data/contact_repository.dart';
import 'package:hasim/features/conversations/data/conversation_repository.dart';
import 'package:hasim/features/email/data/campaign_repository.dart';
import 'package:hasim/features/email/data/email_repository.dart';
import 'package:hasim/features/home/data/home_repository.dart';
import 'package:hasim/features/notifications/data/notification_repository.dart';
import 'package:hasim/features/settings/data/plan_channel_repository.dart';
import 'package:hasim/features/settings/data/session_repository.dart';
import 'package:hasim/features/stories/data/story_repository.dart';
import 'package:hasim/features/workspace/data/workspace_repository.dart';
import 'package:hasim/push/push_service.dart';
import 'package:hasim/realtime/realtime_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

final sharedPrefsProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError('SharedPreferences must be overridden in main');
});

final secureStoreProvider = Provider<SecureStore>((ref) => SecureStore());

final prefsStoreProvider = Provider<PrefsStore>((ref) {
  return PrefsStore(ref.watch(sharedPrefsProvider));
});

final localCacheProvider = Provider<LocalCache>((ref) => LocalCache());

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    secureStore: ref.watch(secureStoreProvider),
    prefsStore: ref.watch(prefsStoreProvider),
  );
});

final authRepositoryProvider = Provider((ref) => AuthRepository(ref.watch(apiClientProvider)));
final workspaceRepositoryProvider = Provider((ref) => WorkspaceRepository(ref.watch(apiClientProvider)));
final homeRepositoryProvider = Provider((ref) => HomeRepository(ref.watch(apiClientProvider)));
final conversationRepositoryProvider = Provider((ref) => ConversationRepository(ref.watch(apiClientProvider)));
final emailRepositoryProvider = Provider((ref) => EmailRepository(ref.watch(apiClientProvider)));
final campaignRepositoryProvider = Provider((ref) => CampaignRepository(ref.watch(apiClientProvider)));
final contactRepositoryProvider = Provider((ref) => ContactRepository(ref.watch(apiClientProvider)));
final contactGroupRepositoryProvider = Provider((ref) => ContactGroupRepository(ref.watch(apiClientProvider)));
final storyRepositoryProvider = Provider((ref) => StoryRepository(ref.watch(apiClientProvider)));
final appointmentRepositoryProvider = Provider((ref) => AppointmentRepository(ref.watch(apiClientProvider)));
final notificationRepositoryProvider = Provider((ref) => NotificationRepository(ref.watch(apiClientProvider)));
final sessionRepositoryProvider = Provider((ref) => SessionRepository(ref.watch(apiClientProvider)));
final deviceRepositoryProvider = Provider((ref) => DeviceRepository(ref.watch(apiClientProvider)));
final planRepositoryProvider = Provider((ref) => PlanRepository(ref.watch(apiClientProvider)));
final channelRepositoryProvider = Provider((ref) => ChannelRepository(ref.watch(apiClientProvider)));
final brandingRepositoryProvider = Provider((ref) => BrandingRepository(ref.watch(apiClientProvider)));
final customerRepositoryProvider = Provider((ref) => CustomerRepository(ref.watch(apiClientProvider)));
final aiRepositoryProvider = Provider((ref) => AiRepository(ref.watch(apiClientProvider)));

final realtimeServiceProvider = Provider<RealtimeService>((ref) {
  return PollingRealtimeService(ref.watch(conversationRepositoryProvider));
});

final pushServiceProvider = Provider<PushService>((ref) {
  return NoopPushService(ref.watch(deviceRepositoryProvider));
});
