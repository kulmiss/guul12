@Tags(['golden'])
library;

import 'dart:convert';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/repository.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

/// Real fonts, so the pictures look like the phone (tests use a box font by default).
Future<void> loadFonts() async {
  Future<void> load(String family, List<String> paths) async {
    final loader = FontLoader(family);
    for (final path in paths) {
      loader.addFont(Future.value(ByteData.sublistView(File(path).readAsBytesSync())));
    }
    await loader.load();
  }

  await load('Nunito', ['assets/fonts/Nunito-ExtraBold.ttf', 'assets/fonts/Nunito-Black.ttf']);
  await load('NotoSans', [
    'assets/fonts/NotoSans-Regular.ttf',
    'assets/fonts/NotoSans-Medium.ttf',
    'assets/fonts/NotoSans-Bold.ttf',
  ]);
  await load('MaterialIcons', [
    '${_flutterRoot()}/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf',
  ]);
  // KaTeX fonts for formulas (flutter_math_fork), found through the package config.
  final config =
      jsonDecode(File('.dart_tool/package_config.json').readAsStringSync()) as Map<String, Object?>;
  final mathPackage = (config['packages']! as List).cast<Map<String, Object?>>().firstWhere(
    (p) => p['name'] == 'flutter_math_fork',
  );
  final root = Uri.parse(mathPackage['rootUri']! as String);
  final katexPath = root.isAbsolute
      ? root.toFilePath()
      : File('.dart_tool/${root.path}').absolute.path;
  final katex = Directory(katexPath);
  final fontsDir = Directory('${katex.path}/lib/katex_fonts/fonts');
  final byFamily = <String, List<String>>{};
  for (final f in fontsDir.listSync().whereType<File>()) {
    final name = f.uri.pathSegments.last;
    if (!name.endsWith('.ttf')) continue;
    final family = name.substring(0, name.indexOf('-'));
    byFamily.putIfAbsent(family, () => []).add(f.path);
  }
  for (final e in byFamily.entries) {
    await load('packages/flutter_math_fork/${e.key}', e.value);
  }
}

/// Flutter SDK folder: from FLUTTER_ROOT, or from the test runner's own location
/// (`bin/cache/artifacts/engine/<platform>/flutter_tester`).
String _flutterRoot() {
  final fromEnv = Platform.environment['FLUTTER_ROOT'];
  if (fromEnv != null && fromEnv.isNotEmpty) return fromEnv;
  var dir = File(Platform.resolvedExecutable).parent;
  for (var i = 0; i < 5; i++) {
    dir = dir.parent;
  }
  return dir.path;
}

FakeServer richServer() {
  final s = FakeServer();
  onboardedProfile(s);
  return s;
}

Future<void> prepared(Repository repo) async {
  await repo.syncAll();
  await repo.chapter(21);
  await repo.rememberChapter(21);
}

typedef Scene = Future<void> Function(WidgetTester tester);

void main() {
  setUpAll(loadFonts);

  final scenes = <String, Scene>{
    'home': (tester) async {},
    'study': (tester) async => tapOn(tester, find.text('Study')),
    'chapter': (tester) async {
      await tapOn(tester, find.text('1. Chapter 21'));
    },
    'question_feedback': (tester) async {
      await tapOn(tester, find.text('Quick 10'));
      await tapOn(tester, find.text('22'));
      await tapOn(tester, find.byKey(const ValueKey('check')));
    },
    'me': (tester) async => tapOn(tester, find.text('Me')),
  };

  for (final (sizeName, size) in [('phone', phoneSize), ('tablet', tabletSize)]) {
    for (final dark in [false, true]) {
      for (final MapEntry(key: name, value: scene) in scenes.entries) {
        final file = '${name}_${sizeName}_${dark ? 'dark' : 'light'}.png';
        testWidgets(file, (tester) async {
          await pumpGuul(
            tester,
            server: richServer(),
            size: size,
            before: (repo) async {
              await prepared(repo);
              await repo.db.writeString('theme_mode', dark ? 'dark' : 'light');
            },
          );
          await scene(tester);
          await expectLater(find.byType(MaterialApp), matchesGoldenFile('goldens/$file'));
        });
      }
    }
  }

  testWidgets('onboarding_grade_phone_light.png', (tester) async {
    await pumpGuul(tester);
    await tapOn(tester, find.byKey(const ValueKey('onboarding-continue')));
    await tapOn(tester, find.text('Grade 12'));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('goldens/onboarding_grade_phone_light.png'),
    );
  });
}
