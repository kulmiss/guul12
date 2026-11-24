import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

/// Keeps the API token and the random install ID on the phone (encrypted storage).
abstract class TokenStore {
  Future<String?> readToken();
  Future<void> writeToken(String token);
  Future<void> clearToken();

  /// Random ID created once per install. Reinstalling the app creates a new one ("new phone = start fresh").
  Future<String> installId();
}

class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'api_token';
  static const _installKey = 'install_id';

  @override
  Future<String?> readToken() => _storage.read(key: _tokenKey);

  @override
  Future<void> writeToken(String token) => _storage.write(key: _tokenKey, value: token);

  @override
  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  @override
  Future<String> installId() async {
    final existing = await _storage.read(key: _installKey);
    if (existing != null && existing.isNotEmpty) return existing;
    final id = const Uuid().v4();
    await _storage.write(key: _installKey, value: id);
    return id;
  }
}

/// In-memory store for tests.
class MemoryTokenStore implements TokenStore {
  MemoryTokenStore({this.token, String? installId}) : _installId = installId ?? const Uuid().v4();

  String? token;
  final String _installId;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> writeToken(String value) async => token = value;

  @override
  Future<void> clearToken() async => token = null;

  @override
  Future<String> installId() async => _installId;
}
