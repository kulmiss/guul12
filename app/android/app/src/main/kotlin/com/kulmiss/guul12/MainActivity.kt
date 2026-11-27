package com.kulmiss.guul12

import android.os.SystemClock
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        // Time since the phone started, in milliseconds. The exam clock uses it because the student
        // cannot change it (unlike the phone clock).
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "guul12/clock").setMethodCallHandler { call, result ->
            if (call.method == "elapsedRealtime") result.success(SystemClock.elapsedRealtime()) else result.notImplemented()
        }
    }
}
