import 'package:share_plus/share_plus.dart';

/// Opens the phone's share sheet (WhatsApp, Telegram, SMS…). Nothing is sent by the app itself.
abstract class Sharing {
  Future<void> shareText(String text);
}

class PhoneSharing implements Sharing {
  const PhoneSharing();

  @override
  Future<void> shareText(String text) async {
    await SharePlus.instance.share(ShareParams(text: text));
  }
}

class NoSharing implements Sharing {
  const NoSharing();

  @override
  Future<void> shareText(String text) async {}
}
