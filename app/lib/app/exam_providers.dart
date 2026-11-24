import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models.dart';
import '../domain/exam.dart';
import 'providers.dart';

/// Fixed mocks as last downloaded (offline-safe).
final mocksProvider = FutureProvider<List<MockHeader>>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).mocks();
});

/// The exam being sat, if any.
final examInProgressProvider = FutureProvider<ExamSession?>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).examInProgress();
});

/// Finished exams, newest first.
final examResultsProvider = FutureProvider<List<ExamRecord>>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).examResults();
});

final examResultProvider = FutureProvider.family<ExamRecord?, String>((ref, uuid) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).examResult(uuid);
});

/// mm:ss, or h:mm:ss for an hour or more.
String formatDuration(Duration d) {
  final total = d.inSeconds < 0 ? 0 : d.inSeconds;
  final h = total ~/ 3600;
  final m = (total % 3600) ~/ 60;
  final s = total % 60;
  String two(int v) => v.toString().padLeft(2, '0');
  return h > 0 ? '$h:${two(m)}:${two(s)}' : '${two(m)}:${two(s)}';
}
