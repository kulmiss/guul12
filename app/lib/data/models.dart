/// Data shapes received from the API (`/api/v1`) and stored offline.
library;

import 'branding.dart';

/// Reads a JSON value as a map, or throws [FormatException].
Map<String, Object?> asMap(Object? value, [String what = 'object']) {
  if (value is Map<String, Object?>) return value;
  if (value is Map) return value.cast<String, Object?>();
  throw FormatException('Expected $what');
}

List<Object?> asList(Object? value, [String what = 'list']) {
  if (value is List) return value.cast<Object?>();
  throw FormatException('Expected $what');
}

int asInt(Object? value, [String what = 'int']) {
  if (value is int) return value;
  if (value is num && value == value.roundToDouble()) return value.toInt();
  throw FormatException('Expected $what');
}

int? asIntOrNull(Object? value) => value == null ? null : asInt(value);

String asString(Object? value, [String what = 'string']) {
  if (value is String) return value;
  throw FormatException('Expected $what');
}

String? asStringOrNull(Object? value) => value == null ? null : asString(value);

bool asBool(Object? value, [String what = 'bool']) {
  if (value is bool) return value;
  throw FormatException('Expected $what');
}

enum QuestionType {
  singleChoice('single_choice'),
  trueFalse('true_false'),
  fillIn('fill_in'),
  numeric('numeric'),
  matching('matching'),
  ordering('ordering'),
  multipleAnswers('multiple_answers'),
  imageChoice('image_choice'),
  passageChoice('passage_choice');

  const QuestionType(this.wire);

  final String wire;

  static QuestionType fromWire(String value) => QuestionType.values.firstWhere(
    (t) => t.wire == value,
    orElse: () => throw FormatException('Unknown question type $value'),
  );

  bool get isSingleKey =>
      this == singleChoice || this == trueFalse || this == imageChoice || this == passageChoice;
}

class Subject {
  const Subject({
    required this.id,
    required this.code,
    required this.name,
    required this.stream,
    required this.isExamSubject,
    required this.icon,
    required this.sort,
  });

  factory Subject.fromJson(Map<String, Object?> json) => Subject(
    id: asInt(json['id']),
    code: asString(json['code']),
    name: asString(json['name']),
    stream: asStringOrNull(json['stream']),
    isExamSubject: asBool(json['is_exam_subject']),
    icon: asStringOrNull(json['icon']),
    sort: asInt(json['sort']),
  );

  final int id;
  final String code;
  final String name;

  /// null = taken by both streams.
  final String? stream;
  final bool isExamSubject;
  final String? icon;
  final int sort;

  bool isFor(String? studentStream) =>
      stream == null || studentStream == null || stream == studentStream;
}

class Unit {
  const Unit({
    required this.id,
    required this.subjectId,
    required this.grade,
    required this.stream,
    required this.number,
    required this.title,
    required this.sort,
    this.reviewQuestionCount = 0,
  });

  factory Unit.fromJson(Map<String, Object?> json) => Unit(
    id: asInt(json['id']),
    subjectId: asInt(json['subject_id']),
    grade: asInt(json['grade']),
    stream: asStringOrNull(json['stream']),
    number: asInt(json['number']),
    title: asString(json['title']),
    sort: asInt(json['sort']),
    // Older servers and catalogs saved before this field: no review exercise.
    reviewQuestionCount: asIntOrNull(json['review_question_count']) ?? 0,
  );

  final int id;
  final int subjectId;
  final int grade;
  final String? stream;
  final int number;
  final String title;
  final int sort;

  /// Questions in the unit's solved textbook review exercise; 0 when there is none.
  final int reviewQuestionCount;

  bool isFor(String? studentStream) =>
      stream == null || studentStream == null || stream == studentStream;
}

class ChapterHeader {
  const ChapterHeader({
    required this.id,
    required this.unitId,
    required this.number,
    required this.title,
    required this.textbookUrl,
    required this.videoYoutubeId,
    required this.videoIsPremium,
    required this.hasSummaryPdf,
    required this.summaryPdfUrl,
    required this.sort,
  });

  factory ChapterHeader.fromJson(Map<String, Object?> json) => ChapterHeader(
    id: asInt(json['id']),
    unitId: asInt(json['unit_id']),
    number: asInt(json['number']),
    title: asString(json['title']),
    textbookUrl: asStringOrNull(json['textbook_url']),
    videoYoutubeId: asStringOrNull(json['video_youtube_id']),
    videoIsPremium: asString(json['video_access']) == 'premium',
    hasSummaryPdf: asBool(json['has_summary_pdf']),
    summaryPdfUrl: asStringOrNull(json['summary_pdf_url']),
    sort: asInt(json['sort']),
  );

  final int id;
  final int unitId;
  final int number;
  final String title;
  final String? textbookUrl;
  final String? videoYoutubeId;
  final bool videoIsPremium;
  final bool hasSummaryPdf;

  /// Chapter summary PDF (free for everyone). It links only to the price-free landing page.
  final String? summaryPdfUrl;
  final int sort;
}

class Catalog {
  const Catalog({
    required this.cursor,
    required this.subjects,
    required this.units,
    required this.chapters,
  });

  factory Catalog.fromJson(Map<String, Object?> json) => Catalog(
    cursor: asInt(json['cursor']),
    subjects: asList(json['subjects']).map((e) => Subject.fromJson(asMap(e))).toList(),
    units: asList(json['units']).map((e) => Unit.fromJson(asMap(e))).toList(),
    chapters: asList(json['chapters']).map((e) => ChapterHeader.fromJson(asMap(e))).toList(),
  );

  static const empty = Catalog(cursor: 0, subjects: [], units: [], chapters: []);

  final int cursor;
  final List<Subject> subjects;
  final List<Unit> units;
  final List<ChapterHeader> chapters;

  List<Subject> subjectsFor(String? stream) =>
      (subjects.where((s) => s.isFor(stream)).toList()..sort((a, b) => a.sort.compareTo(b.sort)));

  List<int> gradesFor(int subjectId, String? stream) {
    final grades =
        units
            .where((u) => u.subjectId == subjectId && u.isFor(stream))
            .map((u) => u.grade)
            .toSet()
            .toList()
          ..sort();
    return grades;
  }

  List<Unit> unitsFor(int subjectId, int grade, String? stream) =>
      units.where((u) => u.subjectId == subjectId && u.grade == grade && u.isFor(stream)).toList()
        ..sort(
          (a, b) => a.sort != b.sort ? a.sort.compareTo(b.sort) : a.number.compareTo(b.number),
        );

  List<ChapterHeader> chaptersFor(int unitId) => chapters.where((c) => c.unitId == unitId).toList()
    ..sort((a, b) => a.sort != b.sort ? a.sort.compareTo(b.sort) : a.number.compareTo(b.number));

  ChapterHeader? chapter(int id) {
    for (final c in chapters) {
      if (c.id == id) return c;
    }
    return null;
  }

  Unit? unit(int id) {
    for (final u in units) {
      if (u.id == id) return u;
    }
    return null;
  }

  Subject? subject(int id) {
    for (final s in subjects) {
      if (s.id == id) return s;
    }
    return null;
  }
}

class Formula {
  const Formula({required this.label, required this.latex});

  factory Formula.fromJson(Map<String, Object?> json) =>
      Formula(label: asString(json['label']), latex: asString(json['latex']));

  final String label;
  final String latex;
}

class Summary {
  const Summary({required this.keyPoints, required this.formulas, required this.body});

  factory Summary.fromJson(Map<String, Object?> json) => Summary(
    keyPoints: asList(json['key_points']).map((e) => asString(e)).toList(),
    formulas: asList(json['formulas']).map((e) => Formula.fromJson(asMap(e))).toList(),
    body: asStringOrNull(json['body']),
  );

  final List<String> keyPoints;
  final List<Formula> formulas;
  final String? body;
}

class SolvedExercise {
  const SolvedExercise({
    required this.id,
    required this.problem,
    required this.steps,
    required this.finalAnswer,
  });

  factory SolvedExercise.fromJson(Map<String, Object?> json) => SolvedExercise(
    id: asInt(json['id']),
    problem: asString(json['problem']),
    steps: asList(json['steps']).map((e) => asString(e)).toList(),
    finalAnswer: asString(json['final_answer']),
  );

  final int id;
  final String problem;
  final List<String> steps;
  final String finalAnswer;
}

class Flashcard {
  const Flashcard({required this.id, required this.term, required this.definition});

  factory Flashcard.fromJson(Map<String, Object?> json) => Flashcard(
    id: asInt(json['id']),
    term: asString(json['term']),
    definition: asString(json['definition']),
  );

  final int id;
  final String term;
  final String definition;
}

class Passage {
  const Passage({required this.id, required this.title, required this.body});

  factory Passage.fromJson(Map<String, Object?> json) =>
      Passage(id: asInt(json['id']), title: asString(json['title']), body: asString(json['body']));

  final int id;
  final String title;
  final String body;

  Map<String, Object?> toJson() => {'id': id, 'title': title, 'body': body};
}

class QuestionOption {
  const QuestionOption({
    required this.key,
    required this.side,
    required this.body,
    required this.mediaUrl,
  });

  factory QuestionOption.fromJson(Map<String, Object?> json) => QuestionOption(
    key: asString(json['key']),
    side: asStringOrNull(json['side']),
    body: asString(json['body']),
    mediaUrl: asStringOrNull(json['media_url']),
  );

  final String key;

  /// null, 'left' or 'right' (matching).
  final String? side;
  final String body;
  final String? mediaUrl;

  Map<String, Object?> toJson() => {'key': key, 'side': side, 'body': body, 'media_url': mediaUrl};
}

class Question {
  const Question({
    required this.id,
    required this.chapterId,
    required this.passageId,
    required this.type,
    required this.stem,
    required this.mediaUrl,
    required this.difficulty,
    required this.isBeta,
    required this.answerKey,
    required this.explanation,
    required this.version,
    required this.options,
  });

  factory Question.fromJson(Map<String, Object?> json) => Question(
    id: asInt(json['id']),
    chapterId: asInt(json['chapter_id']),
    passageId: asIntOrNull(json['passage_id']),
    type: QuestionType.fromWire(asString(json['type'])),
    stem: asString(json['stem']),
    mediaUrl: asStringOrNull(json['media_url']),
    difficulty: asInt(json['difficulty']),
    isBeta: asBool(json['is_beta']),
    answerKey: asMap(json['answer_key']),
    explanation: asString(json['explanation']),
    version: asInt(json['version']),
    options: asList(json['options']).map((e) => QuestionOption.fromJson(asMap(e))).toList(),
  );

  final int id;
  final int chapterId;
  final int? passageId;
  final QuestionType type;
  final String stem;
  final String? mediaUrl;
  final int difficulty;
  final bool isBeta;
  final Map<String, Object?> answerKey;
  final String explanation;
  final int version;
  final List<QuestionOption> options;

  List<QuestionOption> get leftOptions => options.where((o) => o.side == 'left').toList();
  List<QuestionOption> get rightOptions => options.where((o) => o.side == 'right').toList();

  /// The same shape the API sends, so a saved exam can be read back with [Question.fromJson].
  Map<String, Object?> toJson() => {
    'id': id,
    'chapter_id': chapterId,
    'passage_id': passageId,
    'type': type.wire,
    'stem': stem,
    'media_url': mediaUrl,
    'difficulty': difficulty,
    'is_beta': isBeta,
    'answer_key': answerKey,
    'explanation': explanation,
    'version': version,
    'options': [for (final o in options) o.toJson()],
  };
}

class ChapterContent {
  const ChapterContent({
    required this.header,
    required this.summary,
    required this.solvedExercises,
    required this.flashcards,
    required this.passages,
    required this.questions,
  });

  factory ChapterContent.fromJson(Map<String, Object?> json) => ChapterContent(
    header: ChapterHeader.fromJson(asMap(json['chapter'])),
    summary: json['summary'] == null ? null : Summary.fromJson(asMap(json['summary'])),
    solvedExercises: asList(json['solved_exercises'])
        .map((e) => SolvedExercise.fromJson(asMap(e)))
        .toList(),
    flashcards: asList(json['flashcards']).map((e) => Flashcard.fromJson(asMap(e))).toList(),
    passages: asList(json['passages']).map((e) => Passage.fromJson(asMap(e))).toList(),
    questions: asList(json['questions']).map((e) => Question.fromJson(asMap(e))).toList(),
  );

  final ChapterHeader header;
  final Summary? summary;
  final List<SolvedExercise> solvedExercises;
  final List<Flashcard> flashcards;
  final List<Passage> passages;
  final List<Question> questions;

  Passage? passage(int? id) {
    if (id == null) return null;
    for (final p in passages) {
      if (p.id == id) return p;
    }
    return null;
  }
}

class Profile {
  const Profile({
    required this.studentId,
    required this.signedIn,
    required this.grade,
    required this.stream,
    required this.isRetaker,
    required this.region,
    required this.city,
    required this.gender,
    required this.targetScore,
    required this.reminderTime,
    required this.onboarded,
    required this.premiumActive,
    required this.premiumUntil,
    this.nickname,
    this.avatarStyle,
    this.avatarSeed,
  });

  factory Profile.fromJson(Map<String, Object?> json) {
    final premium = asMap(json['premium']);
    final until = asStringOrNull(premium['until']);
    return Profile(
      studentId: asStringOrNull(json['student_id']),
      signedIn: asBool(json['signed_in']),
      grade: asIntOrNull(json['grade']),
      stream: asStringOrNull(json['stream']),
      isRetaker: asBool(json['is_retaker']),
      region: asStringOrNull(json['region']),
      city: asStringOrNull(json['city']),
      gender: asStringOrNull(json['gender']),
      targetScore: asIntOrNull(json['target_score']),
      reminderTime: asStringOrNull(json['reminder_time']),
      onboarded: asBool(json['onboarded']),
      premiumActive: asBool(premium['active']),
      premiumUntil: until == null ? null : DateTime.parse(until),
      nickname: asStringOrNull(json['nickname']),
      avatarStyle: asStringOrNull(json['avatar_style']),
      avatarSeed: asStringOrNull(json['avatar_seed']),
    );
  }

  /// null until the phone has reached the server once.
  final String? studentId;
  final bool signedIn;
  final int? grade;
  final String? stream;
  final bool isRetaker;
  final String? region;
  final String? city;
  final String? gender;
  final int? targetScore;
  final String? reminderTime;
  final bool onboarded;
  final bool premiumActive;
  final DateTime? premiumUntil;

  /// Shown on the leaderboard and in battles, with the built-in avatar. Nothing else is public.
  final String? nickname;
  final String? avatarStyle;
  final String? avatarSeed;

  /// Streams only apply in Grades 11–12; earlier grades see every subject.
  String? get effectiveStream => (grade != null && grade! >= 11) ? stream : null;
}

class AppConfig {
  const AppConfig({
    required this.examDate,
    required this.minSupportedBuild,
    required this.recommendedBuild,
    required this.maintenanceMessage,
    required this.features,
    required this.premium,
    required this.showAds,
    this.paperMaxScore = 100,
    this.freeRandomExamsPerDay = 1,
    this.branding = Branding.sunrise,
    this.contactWhatsapp,
    this.contactTelegram,
  });

  factory AppConfig.fromJson(Map<String, Object?> json) {
    final settings = asMap(json['settings']);
    final features = asMap(json['features']).map((k, v) => MapEntry(k, asBool(v)));
    final exam = asStringOrNull(settings['exam_date']);
    return AppConfig(
      examDate: exam == null ? null : DateTime.parse(exam),
      minSupportedBuild: asInt(settings['min_supported_build']),
      recommendedBuild: asInt(settings['recommended_build']),
      maintenanceMessage: asStringOrNull(settings['maintenance_message']),
      features: features,
      premium: asBool(json['premium']),
      showAds: asBool(json['show_ads']),
      paperMaxScore: asIntOrNull(settings['paper_max_score']) ?? 100,
      freeRandomExamsPerDay: asIntOrNull(settings['free_random_exams_per_day']) ?? 1,
      branding: Branding.fromJsonOrSunrise(json['branding']),
      contactWhatsapp: _contact(json['contact'], 'whatsapp', RegExp(r'^\d{9,15}$')),
      contactTelegram: _contact(json['contact'], 'telegram', RegExp(r'^[A-Za-z0-9_]{5,32}$')),
    );
  }

  static const fallback = AppConfig(
    examDate: null,
    minSupportedBuild: 1,
    recommendedBuild: 1,
    maintenanceMessage: null,
    features: {},
    premium: false,
    showAds: false,
  );

  final DateTime? examDate;
  final int minSupportedBuild;
  final int recommendedBuild;
  final String? maintenanceMessage;
  final Map<String, bool> features;
  final bool premium;
  final bool showAds;

  /// The real paper's top score (EAES has not confirmed the Aptitude scale; editable on the server).
  final int paperMaxScore;
  final int freeRandomExamsPerDay;

  /// Colours, fonts, roundness and logo from Nova (Sunrise when none or broken).
  final Branding branding;

  /// WhatsApp number (digits only) and Telegram name for the Premium page (owner decision #40).
  /// Null when not set, switched off in Nova, sent by an older server, or not in the safe shape.
  final String? contactWhatsapp;
  final String? contactTelegram;

  static String? _contact(Object? block, String key, RegExp shape) {
    if (block is! Map) return null;
    final value = block[key];
    return value is String && shape.hasMatch(value) ? value : null;
  }

  /// Unknown switches default to on, matching the server defaults.
  bool feature(String key) => features[key] ?? true;
}

/// How a student is doing in one chapter, from their own answer history on the phone.
class ChapterStat {
  const ChapterStat({required this.chapterId, required this.answered, required this.correct});

  final int chapterId;

  /// Different questions answered at least once.
  final int answered;

  /// Of those, how many were right the last time.
  final int correct;

  double get accuracy => answered == 0 ? 0 : correct / answered;
}

/// A fixed mock exam in the list.
class MockHeader {
  const MockHeader({
    required this.id,
    required this.subjectId,
    required this.grade,
    required this.number,
    required this.title,
    required this.minutes,
    required this.questionCount,
    required this.isPremium,
    required this.locked,
    required this.contentHash,
  });

  factory MockHeader.fromJson(Map<String, Object?> json) => MockHeader(
    id: asInt(json['id']),
    subjectId: asInt(json['subject_id']),
    grade: asInt(json['grade']),
    number: asInt(json['number']),
    title: asString(json['title']),
    minutes: asInt(json['minutes']),
    questionCount: asInt(json['question_count']),
    isPremium: asString(json['access']) == 'premium',
    locked: asBool(json['locked']),
    contentHash: asString(json['content_hash']),
  );

  final int id;
  final int subjectId;
  final int grade;
  final int number;
  final String title;
  final int minutes;
  final int questionCount;
  final bool isPremium;

  /// Premium mock and the student has no Premium (decided by the server).
  final bool locked;
  final String contentHash;
}

/// One row of the weekly leaderboard: only nickname, avatar and points are ever shown.
class LeaderboardEntry {
  const LeaderboardEntry({
    required this.rank,
    required this.nickname,
    required this.avatarStyle,
    required this.avatarSeed,
    required this.xp,
    required this.isMe,
    this.reportRef,
  });

  factory LeaderboardEntry.fromJson(Map<String, Object?> json) => LeaderboardEntry(
    rank: asInt(json['rank']),
    nickname: asString(json['nickname']),
    avatarStyle: asStringOrNull(json['avatar_style']),
    avatarSeed: asStringOrNull(json['avatar_seed']),
    xp: asInt(json['xp']),
    isMe: asBool(json['is_me']),
    reportRef: asStringOrNull(json['ref']),
  );

  final int rank;
  final String nickname;
  final String? avatarStyle;
  final String? avatarSeed;
  final int xp;
  final bool isMe;

  /// Private server reference used by "Report nickname" (null on my own row).
  final String? reportRef;
}

class WeeklyLeaderboard {
  const WeeklyLeaderboard({
    required this.weekStart,
    required this.top,
    required this.myXp,
    required this.myRank,
    required this.hasNickname,
  });

  factory WeeklyLeaderboard.fromJson(Map<String, Object?> json) {
    final me = asMap(json['me']);
    return WeeklyLeaderboard(
      weekStart: DateTime.parse(asString(json['week_start'])),
      top: asList(json['top']).map((e) => LeaderboardEntry.fromJson(asMap(e))).toList(),
      myXp: asInt(me['xp']),
      myRank: asIntOrNull(me['rank']),
      hasNickname: asBool(me['has_nickname']),
    );
  }

  /// Monday of this week (Addis Ababa time).
  final DateTime weekStart;
  final List<LeaderboardEntry> top;
  final int myXp;

  /// Null without a nickname or before any points this week.
  final int? myRank;
  final bool hasNickname;

  /// XP needed to move up one place (to pass the student just above); null when first, without
  /// a rank, or when not everyone above is in [top] (then the gap is not known).
  int? get xpToNextPlace {
    final rank = myRank;
    if (rank == null || rank <= 1) return null;
    final above = top.where((e) => !e.isMe && e.rank < rank).toList();
    if (above.isEmpty || above.length < rank - 1) return null;
    final lowest = above.map((e) => e.xp).reduce((a, b) => a < b ? a : b);
    final gap = lowest - myXp + 1;
    return gap < 1 ? 1 : gap;
  }
}

/// Today's daily challenge: the same questions for every student of a grade and stream.
class DailyChallengeInfo {
  const DailyChallengeInfo({required this.date, required this.questions, required this.result});

  factory DailyChallengeInfo.fromJson(Map<String, Object?> json) {
    final result = json['result'];
    return DailyChallengeInfo(
      date: asString(json['date']),
      questions: asList(json['questions']).map((e) => Question.fromJson(asMap(e))).toList(),
      result: result is Map ? ChallengeResult.fromJson(asMap(result)) : null,
    );
  }

  /// `yyyy-mm-dd`, Addis Ababa time; sent back with the attempt.
  final String date;
  final List<Question> questions;

  /// The first try of the day, once uploaded.
  final ChallengeResult? result;
}

class ChallengeResult {
  const ChallengeResult({
    required this.correct,
    required this.total,
    required this.betterThanPercent,
    required this.players,
  });

  factory ChallengeResult.fromJson(Map<String, Object?> json) => ChallengeResult(
    correct: asInt(json['correct']),
    total: asInt(json['total']),
    betterThanPercent: asIntOrNull(json['better_than_percent']),
    players: asInt(json['players']),
  );

  final int correct;
  final int total;

  /// Null until enough other students played today.
  final int? betterThanPercent;
  final int players;
}

/// A battle as one player sees it: only nickname, avatar and scores of the other player.
class BattleInfo {
  const BattleInfo({
    required this.id,
    required this.code,
    required this.isRandom,
    required this.questionCount,
    required this.expiresAt,
    required this.status,
    required this.me,
    required this.opponent,
  });

  factory BattleInfo.fromJson(Map<String, Object?> json) {
    final opponent = json['opponent'];
    return BattleInfo(
      id: asInt(json['id']),
      code: asStringOrNull(json['code']),
      isRandom: asBool(json['is_random']),
      questionCount: asInt(json['question_count']),
      expiresAt: DateTime.parse(asString(json['expires_at'])),
      status: BattleStatus.fromWire(asString(json['status'])),
      me: BattlePlayer.fromJson(asMap(json['me'])),
      opponent: opponent is Map ? BattlePlayer.fromJson(asMap(opponent)) : null,
    );
  }

  final int id;

  /// Only for the student who started a friend battle.
  final String? code;
  final bool isRandom;
  final int questionCount;
  final DateTime expiresAt;
  final BattleStatus status;
  final BattlePlayer me;
  final BattlePlayer? opponent;
}

enum BattleStatus {
  waiting('waiting'),
  yourTurn('your_turn'),
  theirTurn('their_turn'),
  won('won'),
  lost('lost'),
  draw('draw'),
  expired('expired');

  const BattleStatus(this.wire);

  final String wire;

  static BattleStatus fromWire(String value) =>
      values.firstWhere((s) => s.wire == value, orElse: () => BattleStatus.expired);
}

class BattlePlayer {
  const BattlePlayer({
    required this.nickname,
    required this.avatarStyle,
    required this.avatarSeed,
    required this.correct,
    required this.total,
    required this.timeMs,
    this.reportRef,
  });

  factory BattlePlayer.fromJson(Map<String, Object?> json) {
    final result = json['result'];
    final r = result is Map ? asMap(result) : null;
    return BattlePlayer(
      nickname: asStringOrNull(json['nickname']),
      reportRef: asStringOrNull(json['ref']),
      avatarStyle: asStringOrNull(json['avatar_style']),
      avatarSeed: asStringOrNull(json['avatar_seed']),
      correct: r == null ? null : asInt(r['correct']),
      total: r == null ? null : asInt(r['total']),
      timeMs: r == null ? null : asInt(r['time_ms']),
    );
  }

  final String? nickname;
  final String? avatarStyle;
  final String? avatarSeed;

  /// Private server reference used by "Report nickname" (only for the opponent).
  final String? reportRef;

  /// Null until this player has played.
  final int? correct;
  final int? total;
  final int? timeMs;

  bool get played => correct != null;
}

class BattleWithQuestions {
  const BattleWithQuestions({required this.battle, required this.questions});

  factory BattleWithQuestions.fromJson(Map<String, Object?> json) => BattleWithQuestions(
    battle: BattleInfo.fromJson(asMap(json['battle'])),
    questions: asList(json['questions']).map((e) => Question.fromJson(asMap(e))).toList(),
  );

  final BattleInfo battle;
  final List<Question> questions;
}

/// My invite code and what my invites have earned.
class ReferralInfo {
  const ReferralInfo({
    required this.code,
    required this.pending,
    required this.valid,
    required this.rewardedDays,
    required this.canEnterCode,
    required this.rewardDays,
    required this.minAnswers,
    required this.qualifyDays,
    required this.maxPerMonth,
    required this.friendsPerReward,
    required this.towardNext,
    required this.waitingRewards,
  });

  factory ReferralInfo.fromJson(Map<String, Object?> json) => ReferralInfo(
    code: asString(json['code']),
    pending: asInt(json['pending']),
    valid: asInt(json['valid']),
    rewardedDays: asInt(json['rewarded_days']),
    canEnterCode: asBool(json['can_enter_code']),
    rewardDays: asInt(json['reward_days']),
    minAnswers: asInt(json['min_answers']),
    qualifyDays: asInt(json['qualify_days']),
    maxPerMonth: asInt(json['max_per_month']),
    friendsPerReward: asInt(json['friends_per_reward']).clamp(1, 1000),
    towardNext: asInt(json['toward_next']),
    waitingRewards: asInt(json['waiting_rewards']),
  );

  final String code;

  /// Friends who entered my code and have not studied enough yet.
  final int pending;

  /// Friends who really studied.
  final int valid;
  final int rewardedDays;

  /// True while I may still enter the code of a friend who invited me.
  final bool canEnterCode;

  /// Premium days per reward (0 = no Premium reward).
  final int rewardDays;

  /// A friend counts after answering this many different questions within [qualifyDays] days.
  final int minAnswers;
  final int qualifyDays;

  /// Rewards a month at most.
  final int maxPerMonth;

  /// Counted friends needed for one reward.
  final int friendsPerReward;

  /// Counted friends toward the next reward (less than [friendsPerReward]).
  final int towardNext;

  /// Full groups waiting for next month because of the monthly limit.
  final int waitingRewards;
}

/// An AI explanation ("Explain more", Premium).
class AiExplanation {
  const AiExplanation({required this.id, required this.body, required this.remainingToday});

  factory AiExplanation.fromJson(Map<String, Object?> json) {
    final e = asMap(json['explanation']);
    return AiExplanation(
      id: asInt(e['id']),
      body: asString(e['body']),
      remainingToday: asInt(json['remaining_today']),
    );
  }

  final int id;
  final String body;
  final int remainingToday;
}

/// One question (or part of a question) of a textbook review exercise, with its solution.
class ReviewPart {
  const ReviewPart({
    required this.ref,
    required this.question,
    this.note,
    this.steps = const [],
    this.answer,
  });

  factory ReviewPart.fromJson(Map<String, Object?> json) => ReviewPart(
    ref: asString(json['ref']),
    question: asString(json['question']),
    note: asStringOrNull(json['note']),
    steps: [for (final s in asList(json['steps'] ?? const <Object?>[])) asString(s)],
    answer: asStringOrNull(json['answer']),
  );

  final String ref;
  final String question;
  final String? note;
  final List<String> steps;
  final String? answer;

  bool get hasSolution => steps.isNotEmpty || answer != null;
}

class ReviewItem extends ReviewPart {
  const ReviewItem({
    required super.ref,
    required super.question,
    super.note,
    super.steps,
    super.answer,
    this.figure,
    this.table,
    this.bookError = false,
    this.parts = const [],
  });

  factory ReviewItem.fromJson(Map<String, Object?> json) {
    final base = ReviewPart.fromJson(json);
    final table = json['table'];
    return ReviewItem(
      ref: base.ref,
      question: base.question,
      note: base.note,
      steps: base.steps,
      answer: base.answer,
      figure: asStringOrNull(json['figure']),
      table: table == null
          ? null
          : {
              for (final e in asMap(table).entries) e.key: [for (final c in asList(e.value)) '$c'],
            },
      bookError: json['book_error'] == true,
      parts: [
        for (final p in asList(json['parts'] ?? const <Object?>[])) ReviewPart.fromJson(asMap(p)),
      ],
    );
  }

  /// A figure in the textbook the question refers to (for example "Figure 2.40").
  final String? figure;

  /// A small data table: column (row) name to its cells, in order.
  final Map<String, List<String>>? table;

  /// The question cannot be solved as printed in the book (explained in [note]).
  final bool bookError;
  final List<ReviewPart> parts;
}

/// The solved review exercise at the end of a textbook unit.
class TextbookReview {
  const TextbookReview({
    required this.unitId,
    required this.book,
    required this.pages,
    required this.intro,
    required this.items,
  });

  factory TextbookReview.fromJson(Map<String, Object?> json) => TextbookReview(
    unitId: asInt(json['unit_id']),
    book: asString(json['book']),
    pages: asStringOrNull(json['pages']),
    intro: asStringOrNull(json['intro']),
    items: [for (final i in asList(json['items'])) ReviewItem.fromJson(asMap(i))],
  );

  final int unitId;
  final String book;
  final String? pages;

  /// Formulas or conventions the solutions use (as in the book).
  final String? intro;
  final List<ReviewItem> items;
}
