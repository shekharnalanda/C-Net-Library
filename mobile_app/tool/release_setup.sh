#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v flutter >/dev/null 2>&1; then
  echo "Flutter SDK is required on the build machine."
  exit 1
fi

flutter create . \
  --platforms=android,ios \
  --org com.mciedu \
  --project-name cnetlibrary

flutter pub get
flutter analyze
flutter test

echo "Platform generation complete."
echo "Android applicationId: com.mciedu.cnetlibrary"
echo "iOS bundle identifier: com.mciedu.cnetlibrary"
echo "Debug APK: flutter build apk --debug"
echo "Play Store AAB: flutter build appbundle --release"
echo "iOS release: flutter build ios --release (requires macOS/Xcode signing)"
