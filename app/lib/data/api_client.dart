import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'models.dart';
import 'token_store.dart';

/// Thrown for any API problem. [isOffline] is true when the phone could not reach the server.
class ApiException implements Exception {
  const ApiException({required this.status, this.code, this.message, this.isOffline = false});

  const ApiException.offline() : this(status: 0, isOffline: true);

  final int status;
  final String? code;
  final String? message;
  final bool isOffline;

  bool get isUnauthorized => status == 401;

  @override
  String toString() => 'ApiException($status, $code, $message, offline: $isOffline)';
}

class ApiClient {
  ApiClient({
    required this.baseUrl,
    required this.tokens,
    http.Client? client,
    this.timeout = const Duration(seconds: 20),
  }) : _client = client ?? http.Client();

  /// e.g. https://guul12.kulmiss.com (no trailing slash).
  final String baseUrl;
  final TokenStore tokens;
  final Duration timeout;
  final http.Client _client;

  Uri _uri(String path, [Map<String, String>? query]) =>
      Uri.parse('$baseUrl/api/v1/$path').replace(queryParameters: query);

  Future<Map<String, Object?>> get(String path, {Map<String, String>? query, bool auth = true}) =>
      _send('GET', path, query: query, auth: auth);

  Future<Map<String, Object?>> post(String path, Object body, {bool auth = true}) =>
      _send('POST', path, body: body, auth: auth);

  Future<Map<String, Object?>> patch(String path, Object body) => _send('PATCH', path, body: body);

  Future<Map<String, Object?>> put(String path) => _send('PUT', path);

  Future<Map<String, Object?>> delete(String path, {Map<String, String>? query}) =>
      _send('DELETE', path, query: query);

  Future<Map<String, Object?>> _send(
    String method,
    String path, {
    Map<String, String>? query,
    Object? body,
    bool auth = true,
  }) async {
    final request = http.Request(method, _uri(path, query));
    request.headers['Accept'] = 'application/json';
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }
    if (auth) {
      final token = await tokens.readToken();
      if (token != null) request.headers['Authorization'] = 'Bearer $token';
    }

    final http.Response response;
    try {
      response = await http.Response.fromStream(await _client.send(request).timeout(timeout));
    } on SocketException {
      throw const ApiException.offline();
    } on TimeoutException {
      throw const ApiException.offline();
    } on http.ClientException {
      throw const ApiException.offline();
    }

    Map<String, Object?> json = const {};
    if (response.body.isNotEmpty) {
      try {
        json = asMap(jsonDecode(response.body));
      } on FormatException {
        throw ApiException(status: response.statusCode, message: 'Invalid response');
      }
    }

    if (response.statusCode >= 200 && response.statusCode < 300) return json;

    throw ApiException(
      status: response.statusCode,
      code: json['code'] is String ? json['code']! as String : null,
      message: json['message'] is String ? json['message']! as String : null,
    );
  }

  void close() => _client.close();
}
