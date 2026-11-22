import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:workmanager/workmanager.dart';

/// Daily reminder on the phone: an inexact background task (WorkManager) that may show one
/// notification. No exact alarms, no server push.
abstract class Reminders {
  /// Plans the next run for about [at] (replaces any planned run).
  Future<void> scheduleAt(DateTime at, {required DateTime now});

  Future<void> cancel();

  /// Asks for the notification permission (Android 13+). True when notifications may be shown.
  Future<bool> askPermission();

  Future<void> show({required String title, required String body, required String channelName});
}

/// Used in tests and wherever reminders are not available.
class NoReminders implements Reminders {
  const NoReminders();

  @override
  Future<void> scheduleAt(DateTime at, {required DateTime now}) async {}

  @override
  Future<void> cancel() async {}

  @override
  Future<bool> askPermission() async => false;

  @override
  Future<void> show({
    required String title,
    required String body,
    required String channelName,
  }) async {}
}

const reminderUniqueName = 'guul12-daily-reminder';
const reminderTaskName = 'daily_reminder';
const _channelId = 'daily_reminder';
const _notificationId = 1;

class PhoneReminders implements Reminders {
  PhoneReminders._(this._notifications);

  final FlutterLocalNotificationsPlugin _notifications;

  /// [dispatcher] is the background entry point (a top-level function).
  static Future<PhoneReminders> start({Function? dispatcher}) async {
    final notifications = FlutterLocalNotificationsPlugin();
    await notifications.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
    );
    if (dispatcher != null) await Workmanager().initialize(dispatcher);
    return PhoneReminders._(notifications);
  }

  @override
  Future<void> scheduleAt(DateTime at, {required DateTime now}) async {
    final delay = at.difference(now);
    await Workmanager().registerOneOffTask(
      reminderUniqueName,
      reminderTaskName,
      initialDelay: delay.isNegative ? Duration.zero : delay,
      existingWorkPolicy: ExistingWorkPolicy.replace,
    );
  }

  @override
  Future<void> cancel() => Workmanager().cancelByUniqueName(reminderUniqueName);

  @override
  Future<bool> askPermission() async {
    final android = _notifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    if (android == null) return false;
    return await android.requestNotificationsPermission() ?? false;
  }

  @override
  Future<void> show({required String title, required String body, required String channelName}) =>
      _notifications.show(
        id: _notificationId,
        title: title,
        body: body,
        notificationDetails: NotificationDetails(
          android: AndroidNotificationDetails(
            _channelId,
            channelName,
            importance: Importance.defaultImportance,
            priority: Priority.defaultPriority,
          ),
        ),
      );
}
