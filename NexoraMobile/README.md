# NexoraMobile (React Native CLI)

This mobile app is a **pure React Native CLI** app (no Expo), connected to your Symfony backend.

## Features
- Email/password login against Symfony (`/api/mobile/login`)
- Profile read/update (`/api/mobile/profile`)
- Notifications (`/api/mobile/notifications`)
- Customer support chat (`/api/mobile/support/messages`)
- QR scanner for:
  - trusting a mobile device from portal profile QR
  - approving website QR login from login page QR

## Backend requirements
Use the Symfony project root (`PiDevSymfony`) and apply SQL from:
- `scratch/mobile_qr_auth.sql`

Then run Symfony normally (`symfony server:start` or your current setup).

## Install and run
From `NexoraMobile/`:

```bash
npm install
```

iOS only:

```bash
cd ios
pod install
cd ..
```

Run Metro:

```bash
npm start
```

Run Android:

```bash
npm run android
```

Run iOS:

```bash
npm run ios
```

## Localhost base URL notes
Inside the app login screen, set Symfony base URL:
- Android emulator: `http://10.0.2.2:8001`
- iOS simulator: `http://127.0.0.1:8001`
- Physical device: use your PC LAN IP, for example `http://192.168.1.50:8001`

## Native permissions/config
Already configured in this project:
- Android:
  - `INTERNET` + `CAMERA` permissions
  - cleartext HTTP enabled (`usesCleartextTraffic=true`) for local `http://...`
- iOS:
  - `NSCameraUsageDescription` in `Info.plist`

## QR flow summary
1. In website profile (`/portal?tab=profile`), generate trust QR.
2. In mobile app, open `SCANNER` tab and scan trust QR.
3. Device becomes trusted.
4. On website login page, generate QR login code.
5. In mobile scanner, scan login QR and approve.
6. Website auto-logs into the linked account.