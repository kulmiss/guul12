import 'dart:async';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/api_client.dart';
import 'package:guul12/data/token_store.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

ApiClient clientThatThrows(Object error) => ApiClient(
  baseUrl: 'https://api.test',
  tokens: MemoryTokenStore(installId: '6f1c1b7e-3c2a-4a7e-9a52-2f1d3c9b8a10'),
  client: MockClient((_) async => throw error),
);

void main() {
  // Every way a phone can fail to reach the server becomes "offline", so callers that catch
  // ApiException (sync, quiet uploads, screens) never see a raw crash.
  for (final (name, error) in <(String, Object)>[
    ('no network', const SocketException('Failed host lookup')),
    // The server's certificate is for another name (domain not set up yet, captive Wi-Fi page).
    ('certificate for another host', const HandshakeException('CERTIFICATE_VERIFY_FAILED')),
    ('other TLS problem', const TlsException('bad record')),
    ('connection closed early', const HttpException('Connection closed')),
    ('client error', http.ClientException('closed')),
    ('too slow', TimeoutException('slow')),
  ]) {
    test('$name: ApiException.offline', () async {
      final api = clientThatThrows(error);
      await expectLater(
        api.get('/api/v1/config', auth: false),
        throwsA(isA<ApiException>().having((e) => e.isOffline, 'isOffline', isTrue)),
      );
      await expectLater(
        api.post('/api/v1/attempts', {'a': 1}),
        throwsA(isA<ApiException>().having((e) => e.isOffline, 'isOffline', isTrue)),
      );
    });
  }

  test('a programming error is not hidden as "offline"', () async {
    final api = clientThatThrows(StateError('bug'));
    await expectLater(api.get('/api/v1/config', auth: false), throwsStateError);
  });
}
