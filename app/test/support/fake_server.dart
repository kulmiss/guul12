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

  /// Feature switches sent in /config (missing = on).
  final Map<String, Object?> features = {'battles': true};

  /// Today's challenge: 3 questions (keys B, D, A).
  List<Map<String, Object?>> challengeQuestions = [
    examQuestion(951, 'B'),
    examQuestion(952, 'D'),
    examQuestion(953, 'A', chapterId: 22),
  ];

  /// "Better than X%" once enough students played; null = not enough yet.
  int? challengeBetterThan;

  int challengeCorrect(Map<String, Object?> attempt) {
    final keys = {
      for (final q in challengeQuestions) q['id']: (q['answer_key']! as Map)['correct'],
    };
    return asList(attempt['answers'])
        .map(asMap)
        .where((a) => keys[a['question_id']] == a['response'])
        .length;
  }

  /// What GET /referral returns.
  Map<String, Object?> referral = {
    'code': 'Q4WX7P',
    'pending': 1,
    'valid': 2,
    'rewarded_days': 14,
    'can_enter_code': true,
    'reward_days': 7,
    'min_answers': 50,
    'max_per_month': 4,
  };

  /// Battles started or joined by the student, oldest first.
  final battles = <Map<String, Object?>>[];

  List<Map<String, Object?>> battleQuestions = [
    examQuestion(961, 'C'),
    examQuestion(962, 'A'),
    examQuestion(963, 'B'),
  ];

  Map<String, Object?> battleJson(Map<String, Object?> b) {
    final mine = receivedAttempts.where((a) => a['mode'] == 'battle' && a['battle_id'] == b['id']);
    final played = mine.isNotEmpty;
    final opponent = b['opponent'] as String?;
    return {
      'id': b['id'],
      'code': b['friend'] == true ? 'K7Q2MZ' : null,
      'is_random': b['friend'] != true,
      'subject_id': null,
      'question_count': battleQuestions.length,
      'seconds_per_question': 60,
      'expires_at': '2026-11-24T10:00:00Z',
      'status': !played ? 'your_turn' : (opponent == null ? 'waiting' : 'won'),
      'me': {
        'nickname': profile['nickname'],
        'avatar_style': profile['avatar_style'],
        'avatar_seed': profile['avatar_seed'],
        'result': played ? {'correct': 2, 'total': 3, 'time_ms': 30000} : null,
      },
      'opponent': opponent == null
          ? null
          : {
              'nickname': opponent,
              'avatar_style': 'thumbs',
              'avatar_seed': 'a04',
              'result': {'correct': 1, 'total': 3, 'time_ms': 40000},
            },
    };
  }

  /// What GET /leaderboard returns.
  Map<String, Object?> leaderboard = {
    'week_start': '2026-11-16',
    'top': <Object?>[],
    'me': {'has_nickname': false, 'xp': 0, 'rank': null},
  };

  /// Nicknames the server's word filter refuses (422).
  final refusedNicknames = <String>{};

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

  /// Summary PDF URL per chapter id (none by default).
  final Map<int, String> summaryPdfUrls = {};

  /// Exams. Mock 1 is free, mock 2 is Premium; both use [examQuestion]s.
  bool premium = false;
  int randomStatus = 201;
  int mockDownloads = 0;
  int historyRequests = 0;

  /// "better_than_percent" and "students" sent back for exam attempts.
  int? betterThanPercent;
  int students = 0;

  /// Answers returned by GET history (in pages of [historyPage]).
  final List<Map<String, Object?>> history = [];
  int historyPage = 1000;

  static Map<String, Object?> examQuestion(
    int id,
    String key, {
    int chapterId = 21,
    int? passageId,
  }) => {
    'id': id,
    'chapter_id': chapterId,
    'passage_id': passageId,
    'type': 'single_choice',
    'stem': 'Exam question $id',
    'media_url': null,
    'difficulty': 2,
    'is_beta': false,
    'answer_key': {'correct': key},
    'explanation': 'Because $key.',
    'version': 1,
    'options': [
      for (final k in ['A', 'B', 'C', 'D'])
        {'key': k, 'side': null, 'body': 'Option $k', 'media_url': null},
    ],
  };

  /// Questions of mock 1: keys A, C, B, D; the last two from chapter 22.
  List<Map<String, Object?>> mockQuestions = [
    examQuestion(901, 'A'),
    examQuestion(902, 'C'),
    examQuestion(903, 'B', chapterId: 22),
    examQuestion(904, 'D', chapterId: 22),
  ];

  String mockHash = 'h1';

  Map<String, Object?> mockHeader(int id) => {
    'id': id,
    'subject_id': 1,
    'grade': 12,
    'number': id,
    'title': 'Physics mock exam $id',
    'minutes': id == 1 ? 6 : 75,
    'question_count': id == 1 ? mockQuestions.length : 50,
    'access': id == 1 ? 'free' : 'premium',
    'locked': id != 1 && !premium,
    'content_hash': id == 1 ? mockHash : 'h2',
  };
  int Function(Map<String, Object?> body)? attemptStatus;

  /// Answer for POST auth/google: 200 signs in; 409 = account conflict.
  int googleStatus = 200;
  int minSupportedBuild = 1;

  /// Reports received by POST questions/{id}/reports, and the status to answer with.
  final List<Map<String, Object?>> receivedReports = [];
  int reportStatus = 201;
  String? reportCode;

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

  Map<String, Object?> chapterHeader(int id) => {
    'id': id,
    'unit_id': 11,
    'number': id - 20,
    'title': 'Chapter $id',
    'textbook_url': null,
    'video_youtube_id': null,
    'video_access': 'free',
    'has_summary_pdf': summaryPdfUrls.containsKey(id),
    'summary_pdf_url': summaryPdfUrls[id],
    'sort': id,
  };

  Map<String, Object?> chapterJson(int id) => {
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
            'free_random_exams_per_day': 1,
            'premium_explain_more_per_day': 20,
            'min_supported_build': minSupportedBuild,
            'recommended_build': 1,
            'maintenance_message': null,
            'paper_max_score': 100,
          },
          'features': features,
          'premium': false,
          'show_ads': true,
        });
      case ('GET', 'referral'):
        return json({'referral': referral});
      case ('POST', 'referral'):
        final code = (body()['code']! as String).trim().toUpperCase();
        if (code != 'FRIEND') return json({'code': 'not_found', 'message': 'No'}, 404);
        referral = {...referral, 'can_enter_code': false};
        return json({'referral': referral}, 201);
      case ('GET', 'battles'):
        return json({
          'battles': [for (final b in battles.reversed) battleJson(b)],
        });
      case ('POST', 'battles'):
        final friend = body()['type'] == 'friend';
        final b = {'id': battles.length + 1, 'friend': friend, 'opponent': null as String?};
        battles.add(b);
        return json({'battle': battleJson(b), 'questions': battleQuestions}, 201);
      case ('POST', 'battles/join'):
        final code = (body()['code']! as String).trim().toUpperCase();
        if (code != 'JOIN22') return json({'code': 'not_found', 'message': 'No'}, 404);
        final b = {'id': battles.length + 1, 'friend': false, 'opponent': 'Ayan', 'joined': true};
        battles.add(b);
        return json({'battle': battleJson(b), 'questions': battleQuestions});
      case ('GET', 'challenge'):
        final mine = receivedAttempts.where((a) => a['mode'] == 'daily_challenge').toList();
        return json({
          'date': '2026-11-22',
          'questions': challengeQuestions,
          'result': mine.isEmpty
              ? null
              : {
                  'correct': challengeCorrect(mine.first),
                  'total': challengeQuestions.length,
                  'better_than_percent': challengeBetterThan,
                  'players': challengeBetterThan == null ? 4 : 40,
                },
        });
      case ('GET', final p) when p.startsWith('battles/'):
        final b = battles[int.parse(p.split('/').last) - 1];
        return json({'battle': battleJson(b), 'questions': battleQuestions});
      case ('GET', 'leaderboard'):
        return json(leaderboard);
      case ('GET', 'me'):
        return json({'user': profile});
      case ('PATCH', 'me'):
        final changes = body();
        if (refusedNicknames.contains(changes['nickname'])) {
          return json({'message': 'Please choose another nickname.'}, 422);
        }
        profile.addAll(changes);
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
        return json({'attempt': attemptJson(b)}, status);
      case ('GET', 'mocks'):
        return json({
          'mocks': [mockHeader(1), mockHeader(2)],
        });
      case ('POST', 'exams/random'):
        if (randomStatus == 429) return json({'code': 'daily_limit', 'message': 'Premium'}, 429);
        return json({
          'exam': {
            'subject_id': body()['subject_id'],
            'grade': 12,
            'minutes': 3,
            'question_count': 2,
          },
          'passages': <Object?>[],
          'questions': [examQuestion(951, 'B'), examQuestion(952, 'D')],
        }, randomStatus);
      case ('GET', 'history'):
        historyRequests++;
        final after = int.parse(request.url.queryParameters['after'] ?? '0');
        final rest = history.where((h) => (h['id']! as int) > after).toList();
        final page = rest.take(historyPage).toList();
        return json({
          'answers': page,
          'next_after': page.isEmpty ? after : page.last['id'],
          'has_more': rest.length > page.length,
        });
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
    if (request.method == 'GET' && RegExp(r'^mocks/\d+$').hasMatch(path)) {
      final id = int.parse(path.split('/').last);
      if (id != 1 && !premium) return json({'code': 'premium_required', 'message': 'Premium'}, 403);
      mockDownloads++;
      return json({'mock': mockHeader(id), 'passages': <Object?>[], 'questions': mockQuestions});
    }
    if (request.method == 'GET' && path.startsWith('attempts/')) {
      final uuid = path.split('/').last;
      final found = receivedAttempts.where((a) => a['client_uuid'] == uuid).toList();
      if (found.isEmpty) return json({'message': 'not found'}, 404);
      return json({'attempt': attemptJson(found.last)});
    }
    if (request.method == 'GET' && path.startsWith('chapters/')) {
      final id = int.parse(path.split('/').last);
      chapterDownloads[id] = (chapterDownloads[id] ?? 0) + 1;
      return json(chapterJson(id));
    }
    if (request.method == 'POST' && RegExp(r'^questions/\d+/reports$').hasMatch(path)) {
      final id = int.parse(path.split('/')[1]);
      if (reportStatus < 300) receivedReports.add({'question_id': id, ...body()});
      return json({'code': ?reportCode, 'report': <String, Object?>{}}, reportStatus);
    }
    if (path.startsWith('bookmarks/')) {
      final id = int.parse(path.split('/').last);
      request.method == 'PUT' ? bookmarks.add(id) : bookmarks.remove(id);
      return json({'ok': true});
    }
    return json({'message': 'not found'}, 404);
  });
}

extension on FakeServer {
  Map<String, Object?> attemptJson(Map<String, Object?> body) {
    final mode = body['mode'];
    final isExam = mode is String && mode.startsWith('exam_');
    return {
      'client_uuid': body['client_uuid'],
      'mode': mode,
      'mock_id': body['mock_id'],
      'exam': isExam
          ? {
              'chapters': <Object?>[],
              'better_than_percent': mode == 'exam_fixed' ? betterThanPercent : null,
              'students': mode == 'exam_fixed' ? students : null,
            }
          : null,
    };
  }
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
