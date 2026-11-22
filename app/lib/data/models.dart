/// Data shapes received from the API (`/api/v1`) and stored offline.
library;

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
  });

  factory Unit.fromJson(Map<String, Object?> json) => Unit(
    id: asInt(json['id']),
    subjectId: asInt(json['subject_id']),
    grade: asInt(json['grade']),
    stream: asStringOrNull(json['stream']),
    number: asInt(json['number']),
    title: asString(json['title']),
    sort: asInt(json['sort']),
  );

  final int id;
  final int subjectId;
  final int grade;
  final String? stream;
  final int number;
  final String title;
  final int sort;

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

  /// Unknown switches default to on, matching the server defaults.
  bool feature(String key) => features[key] ?? true;
}
