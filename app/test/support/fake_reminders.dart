import 'package:guul12/services/reminders.dart';

/// Records what the app asks of the phone's reminder service.
class FakeReminders implements Reminders {
  final scheduled = <DateTime>[];
  var cancels = 0;
  final shown = <(String, String, String)>[];

  @override
  Future<void> scheduleAt(DateTime at, {required DateTime now}) async => scheduled.add(at);

  @override
  Future<void> cancel() async => cancels++;

  var permissionAsks = 0;

  @override
  Future<bool> askPermission() async {
    permissionAsks++;
    return true;
  }

  @override
  Future<void> show({
    required String title,
    required String body,
    required String channelName,
  }) async => shown.add((title, body, channelName));
}
