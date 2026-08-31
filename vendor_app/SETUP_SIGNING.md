# eVendor — Release Signing Setup

## Step 1: Generate Keystore (run once)

```bash
keytool -genkey -v -keystore vendor_app/android/evendor-release.jks \
  -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 \
  -alias evendor \
  -dname "CN=eSahlan, OU=Mobile, O=eSahlan, L=Mogadishu, S=Banadir, C=SO"
```

When prompted:
- **Store password**: choose a strong password (save it!)
- **Key password**: same or different strong password (save it!)

## Step 2: Create key.properties

Create file: `vendor_app/android/key.properties`

```
storePassword=YOUR_STORE_PASSWORD
keyPassword=YOUR_KEY_PASSWORD
keyAlias=evendor
storeFile=../evendor-release.jks
```

⚠️ NEVER commit key.properties or evendor-release.jks to git!

## Step 3: Build Release APK / AAB

```bash
cd vendor_app

# AAB (required for Play Store)
flutter build appbundle --release

# APK (for direct install / testing)
flutter build apk --release
```

## iOS Bundle ID

In Xcode → Runner → Signing & Capabilities:
- Bundle Identifier: `com.esahlan.evendor`
- Team: your Apple Developer account

## App Store Connect

1. Create new app: `com.esahlan.evendor`
2. App name: **eVendor**
3. Upload IPA via Xcode or Transporter

## Play Store

1. Create new app in Google Play Console
2. Package name: `com.esahlan.evendor`
3. Upload AAB from `build/app/outputs/bundle/release/app-release.aab`
