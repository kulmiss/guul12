import 'package:flutter_tts/flutter_tts.dart';

/// Reads text aloud with the phone's own text-to-speech (free, offline on most phones).
abstract class Speech {
  Future<void> speak(String text);
  Future<void> stop();
}

class PhoneSpeech implements Speech {
  PhoneSpeech() : _tts = FlutterTts();

  final FlutterTts _tts;

  @override
  Future<void> speak(String text) async {
    await _tts.stop();
    await _tts.setLanguage('en-US');
    await _tts.speak(speakable(text));
  }

  @override
  Future<void> stop() async {
    await _tts.stop();
  }
}

class SilentSpeech implements Speech {
  const SilentSpeech();

  @override
  Future<void> speak(String text) async {}

  @override
  Future<void> stop() async {}
}

/// Removes LaTeX markers so the voice does not read "dollar backslash frac".
String speakable(String text) => text
    .replaceAll(RegExp(r'\$\$?'), ' ')
    .replaceAll(r'\frac', ' fraction ')
    .replaceAll(r'\sqrt', ' square root of ')
    .replaceAll(r'\times', ' times ')
    .replaceAll(RegExp(r'\\[a-zA-Z]+'), ' ')
    .replaceAll(RegExp(r'[{}^_]'), ' ')
    .replaceAll(RegExp(r'\s+'), ' ')
    .trim();
