# C-Net Library Mobile App

Flutter client for Android and iOS.

## Backend

Production API base URL:

`https://cnetlibrary.mciedu.com/api/mobile/v1`

## Current foundation

- Branded splash / session restore
- Student login
- Secure token storage
- Automatic sign-out on expired/invalid API token
- Dashboard summary
- Profile and logout
- Bottom navigation: Home, Library, Activity, Profile
- Membership, payments, attendance and seat allocation
- Books and issued books
- Digital resources and jobs
- Scannable QR Member ID backed by the existing attendance URL
- Support/enquiry submission into the existing C-Net Library enquiry system

## Automated platform setup

On a machine with Flutter installed, from `mobile_app/` run:

```bash
bash tool/release_setup.sh
```

The script generates Android and iOS platform folders with:

- Android application ID: `com.mciedu.cnetlibrary`
- iOS bundle identifier: `com.mciedu.cnetlibrary`
- Project name: `cnetlibrary`

It then runs `flutter pub get`, `flutter analyze`, and `flutter test`.

## Build commands

```bash
flutter build apk --debug
flutter build appbundle --release
```

The debug APK is for direct Android testing. The release AAB is for Google Play Console. iOS release builds require macOS/Xcode and Apple signing.

## Release assets still required

Final C-Net Library logo/app icon artwork, Android/iOS signing credentials, store screenshots, privacy policy/store listing details, and live API deployment verification are release-stage inputs rather than source-code blockers.
