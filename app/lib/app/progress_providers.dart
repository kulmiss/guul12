import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/mastery.dart';
import 'providers.dart';

/// Mastery of every chapter the student opened or answered, by chapter id.
final masteryProvider = FutureProvider<Map<int, ChapterMastery>>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  final saved = await repo.db.chapters();
  final list = chapterMastery(
    stats: await repo.db.chapterStats(),
    questionsPerChapter: {for (final c in saved) c.header.id: c.questions.length},
  );
  return {for (final m in list) m.chapterId: m};
});
