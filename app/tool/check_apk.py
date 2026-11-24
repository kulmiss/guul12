#!/usr/bin/env python3
"""Checks a built release APK before it goes to Google Play.

Run from app/ after `flutter build apk --release ...` (same --dart-define values as the AAB):
    python3 tool/check_apk.py build/app/outputs/flutter-apk/app-release.apk          # any build
    python3 tool/check_apk.py build/app/outputs/flutter-apk/app-release.apk --store  # upload build

Always checked: package name, version = pubspec.yaml, target SDK, exact permission list,
not debuggable, adaptive icon. With --store also: signed with the upload key (not the debug key)
and no Google test AdMob IDs inside. Needs the Android SDK (ANDROID_HOME): aapt and apksigner.
Exit code 0 = all good, 1 = a problem (each problem is printed).
"""
import glob
import os
import re
import subprocess
import sys
import zipfile

PACKAGE = 'com.kulmiss.guul12'
MIN_TARGET_SDK = 36  # Google Play: new apps and updates must target Android 16 (API 36).
# Exactly these, no more (see docs/android-release.md and the manifest test).
ALLOWED = {
    'android.permission.INTERNET',
    'android.permission.POST_NOTIFICATIONS',
    'android.permission.RECEIVE_BOOT_COMPLETED',  # WorkManager keeps the reminder after a restart
    'android.permission.VIBRATE',  # notifications
    'android.permission.ACCESS_NETWORK_STATE',  # WorkManager, ads
    'android.permission.WAKE_LOCK',  # WorkManager
    'com.google.android.gms.permission.AD_ID',  # AdMob (declared in Play Console)
    f'{PACKAGE}.DYNAMIC_RECEIVER_NOT_EXPORTED_PERMISSION',  # androidx.core, app-private
}
SPLIT_ABI_CODE = {'armeabi-v7a': 1, 'arm64-v8a': 2, 'x86_64': 4}  # Flutter's FlutterPluginConstants
TEST_ADMOB = '3940256099942544'  # Google's public test publisher ID


def tool(name):
    home = os.environ.get('ANDROID_HOME') or os.environ.get('ANDROID_SDK_ROOT')
    if not home:
        sys.exit('Set ANDROID_HOME to the Android SDK folder.')
    found = sorted(glob.glob(os.path.join(home, 'build-tools', '*', name)),
                   key=lambda p: [int(x) if x.isdigit() else 0 for x in re.split(r'[.-]', p.split(os.sep)[-2])])
    if not found:
        sys.exit(f'{name} not found in {home}/build-tools.')
    return found[-1]


def run(*cmd):
    return subprocess.run(cmd, check=True, capture_output=True, text=True).stdout


def pubspec_version():
    here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    with open(os.path.join(here, 'pubspec.yaml'), encoding='utf-8') as f:
        m = re.search(r'^version:\s*([0-9.]+)\+([0-9]+)\s*$', f.read(), re.MULTILINE)
    if not m:
        sys.exit('pubspec.yaml has no "version: x.y.z+n" line.')
    return m.group(1), m.group(2)


def check(apk, store):
    problems = []
    badging = run(tool('aapt'), 'dump', 'badging', apk)

    m = re.search(r"^package: name='([^']+)' versionCode='([^']+)' versionName='([^']+)'", badging, re.MULTILINE)
    if not m:
        return ['Could not read the package line.']
    name, code, version = m.groups()
    if name != PACKAGE:
        problems.append(f'Package is {name}, expected {PACKAGE}.')
    want_version, want_code = os.environ.get('APP_VERSION', '+'.join(pubspec_version())).split('+')
    split = re.search(r'-(armeabi-v7a|arm64-v8a|x86_64)-release\.apk$', apk)
    if split:  # `--split-per-abi`: Flutter adds ABI_VERSION[abi] * 1000 to each APK's version code.
        want_code = str(SPLIT_ABI_CODE[split.group(1)] * 1000 + int(want_code))
    if (version, code) != (want_version, want_code):
        problems.append(f'Version is {version}+{code}, expected {want_version}+{want_code} (pubspec.yaml or APP_VERSION).')

    target = re.search(r"^targetSdkVersion:'(\d+)'", badging, re.MULTILINE)
    if not target or int(target.group(1)) < MIN_TARGET_SDK:
        problems.append(f'Target SDK must be at least {MIN_TARGET_SDK}.')

    permissions = set(re.findall(r"^uses-permission: name='([^']+)'", badging, re.MULTILINE))
    for extra in sorted(permissions - ALLOWED):
        problems.append(f'Permission not allowed: {extra}')
    for missing in sorted({'android.permission.INTERNET', 'android.permission.POST_NOTIFICATIONS'} - permissions):
        problems.append(f'Permission missing: {missing}')

    if 'application-debuggable' in badging:
        problems.append('The APK is debuggable: build with --release.')

    icon = re.search(r"^application: label='([^']*)' icon='([^']+)'", badging, re.MULTILINE)
    if not icon:
        problems.append('No app label or icon.')
    else:
        if icon.group(1) != 'Guul 12':
            problems.append(f'App label is "{icon.group(1)}", expected "Guul 12".')
        tree = run(tool('aapt'), 'dump', 'xmltree', apk, icon.group(2)) if icon.group(2).endswith('.xml') else ''
        if 'adaptive-icon' not in tree:
            problems.append('The launcher icon is not an adaptive icon.')

    if store:
        certs = run(tool('apksigner'), 'verify', '--print-certs', apk)
        if 'CN=Android Debug' in certs:
            problems.append('Signed with the debug key: add android/key.properties (docs/android-release.md).')
        manifest = run(tool('aapt'), 'dump', 'xmltree', apk, 'AndroidManifest.xml')
        if TEST_ADMOB in manifest:
            problems.append('Google test AdMob app ID in the manifest: set admobAppId in key.properties.')
        with zipfile.ZipFile(apk) as z:
            libs = [e for e in z.namelist() if e.endswith('libapp.so')]
            if any(TEST_ADMOB.encode() in z.read(e) for e in libs):
                problems.append('Google test AdMob ad unit in the app code: set ADMOB_BANNER_ID and ADMOB_REWARDED_ID.')

    print(f'{os.path.basename(apk)}: {name} {version}+{code}, target SDK '
          f'{target.group(1) if target else "?"}, {len(permissions)} permissions, '
          f'{os.path.getsize(apk) / 1e6:.1f} MB')
    return problems


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    if len(args) != 1:
        sys.exit(__doc__)
    if not os.path.isfile(args[0]):
        sys.exit(f'APK not found: {args[0]} (build it first, see docs/android-release.md).')
    problems = check(args[0], '--store' in sys.argv[1:])
    for p in problems:
        print('PROBLEM:', p)
    if problems:
        sys.exit(1)
    print('All checks passed.')


if __name__ == '__main__':
    main()
