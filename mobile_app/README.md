# C-Net Library Mobile App

Flutter client for Android and iOS.

## Backend

Production API base URL:

`https://cnetlibrary.mciedu.com/api/mobile/v1`

## Current foundation

- Splash / session restore
- Student login
- Secure token storage
- Dashboard summary
- Profile and logout
- Bottom navigation: Home, Library, Activity, Profile
- API hooks for membership, payments, attendance, seat allocation, books, issued books, digital resources, jobs, QR member ID and support

## Local setup

From `mobile_app/` run:

```bash
flutter pub get
flutter test
flutter run
```

Before release, generate platform folders using the project's approved Flutter toolchain and configure Android package / iOS bundle identifier as `com.mciedu.cnetlibrary`.
