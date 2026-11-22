import 'dart:convert';
import 'dart:io';

import 'package:guul12/data/api_client.dart';
import 'package:guul12/data/local_db.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/data/token_store.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

/// A tiny in-memory imitation of the backend API, enough to test sync logic.
class FakeServer {
  bool online = true;
  int registrations = 0;
  String token = 't1';
  int cursor = 10;
  final List<Map<String, Object?>> changes = [];
  final List<Map<String, Object?>> receivedAttempts = [];
  final Map<String, Object?> profile = {
    'student_id': 'ST-7K3Q9',
    'signed_in': false,
    'grade': null,
    'stream': null,
    'is_retaker': false,
    'region': null,
    'city': null,
    'gender': null,
    'target_score': null,
    'reminder_time': null,
    'onboarded': false,
    'premium': {'active': false, 'until': null},
  };
  final Set<int> bookmarks = {};
  final Map<int, int> chapterDownloads = {};
  final List<String> calls = [];
  int Function(Map<String, Object?> body)? attemptStatus;

  /// Answer for POST auth/google: 200 signs in; 409 = account conflict.
  int googleStatus = 200;
  int minSupportedBuild = 1;

  Map<String, Object?> catalogJson() => {
    'cursor': cursor,
    'subjects': [
      {
        'id': 1,
        'code': 'physics',
        'name': 'Physics',
        'stream': 'natural',
        'is_exam_subject': true,
        'icon': 'bolt',
        'sort': 40,
      },
      {
        'id': 2,
        'code': 'english',
        'name': 'English',
        'stream': null,
        'is_exam_subject': true,
        'icon': null,
        'sort': 10,
      },
      {
        'id': 3,
        'code': 'history',
        'name': 'History',
        'stream': 'social',
        'is_exam_subject': true,
        'icon': null,
        'sort': 80,
      },
    ],
    'units': [
      {
        'id': 11,
        'subject_id': 1,
        'grade': 12,
        'stream': null,
        'number': 2,
        'title': 'Two-dimensional motion',
        'sort': 2,
      },
    ],
    'chapters': [chapterHeader(21), chapterHeader(22)],
  };

  static Map<String, Object?> chapterHeader(int id) => {
    'id': id,
    'unit_id': 11,
    'number': id - 20,
    'title': 'Chapter $id',
    'textbook_url': null,
    'video_youtube_id': null,
    'video_access': 'free',
    'has_summary_pdf': false,
    'sort': id,
  };

  static Map<String, Object?> chapterJson(int id) => {
    'cursor': 10,
    'chapter': chapterHeader(id),
    'summary': {
      'id': 1,
      'key_points': ['A projectile falls with constant acceleration g.'],
      'formulas': [
        {'label': 'Fall time', 'latex': r't = \sqrt{\frac{2h}{g}}'},
      ],
      'body': null,
    },
    'solved_exercises': <Object?>[],
    'flashcards': [
      {
        'id': 1,
        'term': 'Projectile',
        'definition': 'An object moving only under gravity.',
        'sort': 1,
      },
    ],
    'passages': <Object?>[],
    'questions': [
      {
        'id': id * 100 + 1,
        'chapter_id': id,
        'passage_id': null,
        'type': 'single_choice',
        'stem': 'What is 2 + 2?',
        'media_url': null,
        'difficulty': 2,
        'is_beta': false,
        'answer_key': {'correct': 'B'},
        'explanation': '2 + 2 = 4.',
        'version': 1,
        'options': [
          {'key': 'A', 'side': null, 'body': '3', 'media_url': null},
          {'key': 'B', 'side': null, 'body': '4', 'media_url': null},
          {'key': 'C', 'side': null, 'body': '5', 'media_url': null},
          {'key': 'D', 'side': null, 'body': '22', 'media_url': null},
        ],
      },
    ],
  };

  http.Client client() => MockClient((request) async {
    calls.add('${request.method} ${request.url.path}');
    if (!online) throw const SocketException('offline');

    final path = request.url.path.replaceFirst('/api/v1/', '');
    final auth = request.headers['Authorization'];
    Map<String, Object?> body() => request.body.isEmpty ? {} : asMap(jsonDecode(request.body));
    http.Response json(Object data, [int status = 200]) =>
        http.Response(jsonEncode(data), status, headers: {'content-type': 'application/json'});

    if (path == 'auth/device') {
      registrations++;
      token = 't$registrations';
      return json({'token': token, 'created': true, 'user': profile}, 201);
    }
    if (auth != 'Bearer $token') return json({'message': 'Unauthenticated.'}, 401);

    switch ((request.method, path)) {
      case ('GET', 'config'):
        return json({
          'server_time': '2026-11-22T10:00:00Z',
          'settings': {
            'exam_date': '2027-06-29',
            'free_fixed_mocks': 2,
            'premium_explain_more_per_day': 20,
            'min_supported_build': minSupportedBuild,
            'recommended_build': 1,
            'maintenance_message': null,
            'paper_max_score': 100,
          },
          'features': {'battles': true},
          'premium': false,
          'show_ads': true,
        });
      case ('GET', 'me'):
        return json({'user': profile});
      case ('PATCH', 'me'):
        profile.addAll(body());
        return json({'user': profile});
      case ('GET', 'catalog'):
        return json(catalogJson());
      case ('GET', 'sync/changes'):
        final since = int.parse(request.url.queryParameters['since']!);
        final newer = changes.where((c) => (c['id']! as int) > since).toList();
        return json({
          'cursor': newer.isEmpty ? since : newer.last['id'],
          'has_more': false,
          'catalog_changed': newer.any((c) => c['entity'] == 'unit'),
          'chapter_ids': newer.map((c) => c['chapter_id']).whereType<int>().toSet().toList(),
        });
      case ('POST', 'attempts'):
        final b = body();
        final status = attemptStatus?.call(b) ?? 201;
        if (status < 300) receivedAttempts.add(b);
        return json({'attempt': <String, Object?>{}}, status);
      case ('POST', 'auth/google'):
        if (googleStatus == 409) {
          return json({'code': 'account_conflict', 'message': 'conflict'}, 409);
        }
        token = 'g$registrations';
        profile['signed_in'] = true;
        return json({'token': token, 'merged': false, 'user': profile});
      case ('GET', 'bookmarks'):
        return json({'question_ids': bookmarks.toList()});
    }
    if (request.method == 'GET' && path.startsWith('chapters/')) {
      final id = int.parse(path.split('/').last);
      chapterDownloads[id] = (chapterDownloads[id] ?? 0) + 1;
      return json(chapterJson(id));
    }
    if (path.startsWith('bookmarks/')) {
      final id = int.parse(path.split('/').last);
      request.method == 'PUT' ? bookmarks.add(id) : bookmarks.remove(id);
      return json({'ok': true});
    }
    return json({'message': 'not found'}, 404);
  });
}

/// [noIsolate] keeps the database in the test's own isolate, which widget tests need
/// (their fake clock never lets a background isolate answer).
Future<(Repository, FakeServer, MemoryTokenStore)> makeRepository({
  FakeServer? server,
  bool noIsolate = false,
}) async {
  sqfliteFfiInit();
  final s = server ?? FakeServer();
  final tokens = MemoryTokenStore(installId: '6f1c1b7e-3c2a-4a7e-9a52-2f1d3c9b8a10');
  final db = await LocalDb.open(
    factory: noIsolate ? databaseFactoryFfiNoIsolate : databaseFactoryFfi,
    path: inMemoryDatabasePath,
  );
  final repo = Repository(
    api: ApiClient(baseUrl: 'https://api.test', tokens: tokens, client: s.client()),
    db: db,
    tokens: tokens,
    clock: () => DateTime.utc(2026, 11, 22, 10),
  );
  return (repo, s, tokens);
}
