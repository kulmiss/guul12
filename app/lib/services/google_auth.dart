import 'package:google_sign_in/google_sign_in.dart';

/// Gets a Google ID token for our backend. The backend verifies it; the app never trusts it itself.
abstract class GoogleAuth {
  bool get isConfigured;

  /// Returns an ID token, or null when the student cancels.
  Future<String?> idToken();
}

/// Used until the owner adds the Google OAuth client ID (`--dart-define=GOOGLE_SERVER_CLIENT_ID=...`).
class DisabledGoogleAuth implements GoogleAuth {
  const DisabledGoogleAuth();

  @override
  bool get isConfigured => false;

  @override
  Future<String?> idToken() async => null;
}

class PluginGoogleAuth implements GoogleAuth {
  PluginGoogleAuth(this.serverClientId);

  /// The *web* OAuth client ID of the Google Cloud project (also set as GOOGLE_CLIENT_IDS on the server).
  final String serverClientId;
  bool _initialised = false;

  @override
  bool get isConfigured => serverClientId.isNotEmpty;

  @override
  Future<String?> idToken() async {
    if (!isConfigured) return null;
    final signIn = GoogleSignIn.instance;
    if (!_initialised) {
      await signIn.initialize(serverClientId: serverClientId);
      _initialised = true;
    }
    try {
      final account = await signIn.authenticate(scopeHint: const ['email']);
      return account.authentication.idToken;
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) return null;
      rethrow;
    }
  }
}
