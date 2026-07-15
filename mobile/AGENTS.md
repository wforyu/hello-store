# Expo HAS CHANGED

Read the exact versioned docs at https://docs.expo.dev/versions/v57.0.0/ before writing any code.

---

## CRITICAL: Yang BOLEH dan TIDAK BOLEH

### TIDAK BOLEH — `expo prebuild --clean`
- **JANGAN PERNAH** jalankan `npx expo prebuild --clean` kecuali ada perubahan native dependency/plugin baru
- `--clean` menghapus seluruh folder `android/` dan regenerate dari nol
- Setiap regenerate:
  - Splash PNG (`drawable-mdpi/splashscreen_logo.png`) balik lagi putih + gambar pecah
  - `colors.xml` reset ke `#FFFFFF`
  - `splashscreen_logo.xml` drawable dihapus
- **Semua splash screen fix akan HILANG**

### Cara Build yang BENAR
```
# Cukup clean + build (JANGAN prebuild)
gradlew clean → gradlew assembleRelease
```
- Gradle otomatis re-bundle JS tanpa regenerate android folder
- Splash fixes tetap aman
- Build time: ~15-20 menit (full clean)

### Kapan BOLEH pakai `prebuild --clean`
- Pertama kali setup project
- Tambah/hapus native dependency (misal: expo-camera, expo-location)
- Tambah/hapus Expo plugin di `app.json`
- **Setelah prebuild, HARUS apply splash fix lagi**

### Splash Fix Checklist (setelah prebuild --clean)
1. Hapus semua `drawable-*/splashscreen_logo.png`
2. `colors.xml`: ganti `#FFFFFF` → `#FEF3C7`
3. Buat `drawable/splashscreen_logo.xml` (layer-list solid amber)
4. JANGAN rebuild tanpa fix ini — APK akan ada double splash

---

## Mobile App Info

- **Framework**: Expo SDK 57, React Native 0.86
- **Bundle ID**: `com.hellostore.app`
- **API**: Token-based auth via SecureStore + Axios `Bearer` header
- **API_URL config**: `mobile/src/config.js` → `API_URL`
- **Splash**: Custom JS splash (HS logo + slogan) di `App.js` — native splash = solid amber #FEF3C7

## Build Commands

### Keystore (Signing)
- **File**: `android/keystore/hello-store.keystore` (sudah di-commit ke GitHub)
- **Store password**: `hellostore123`
- **Key alias**: `hello-store`
- **Key password**: `hellostore123`
- **Distinguished Name**: `CN=Hello Store, OU=Dev, O=HelloStore, L=Bandung, ST=JawaBarat, C=ID`
- **Validity**: 10,000 hari (~27 tahun)
- **PENTING**: Keystore ini WAJIB dipakai untuk semua build. Kalau hilang, user harus uninstall APK lama dulu.
- **Config reference**: `android/app/build.gradle` → `signingConfigs.release`

### Build (Release)

| Perintah | Fungsi | Waktu |
|---|---|---|
| `gradlew assembleRelease -PreactNativeArchitectures=arm64-v8a` | **Recommended** — build cuma arm64 (99% device) | ~1-2 menit |
| `gradlew assembleRelease` | Full build semua arsitektur (arm64, armv7, x86, x86_64) | ~3-5 menit |
| `gradlew assembleRelease -PreactNativeArchitectures=arm64-v8a,armeabi-v7a` | Build untuk 32-bit + 64-bit | ~2-3 menit |
| `npx expo export --platform android` | Export JS bundle saja (bukan APK) | ~30 detik |

### Build Checklist
1. Bump `versionCode` di `mobile/app.json` (sekarang: 101)
2. Pastikan keystore ada di `android/keystore/hello-store.keystore`
3. Jalankan build command di atas (working directory: `mobile/android/`)
4. APK output: `mobile/android/app/build/outputs/apk/release/app-release.apk`
5. Copy ke `mobile/HelloStore-v1.0.0-{versionCode}.apk` untuk distribusi

### Incremental Build (Perubahan JS/React saja)
Kalau cuma ubah JS/React (bukan native code), pakai incremental build:
```
.\gradlew.bat assembleRelease -PreactNativeArchitectures=arm64-v8a
```
Tidak perlu `clean` — Gradle otomatis rebuild JS bundle.

### Full Clean Build (Native code berubah)
Hanya kalau ada perubahan native (splash, permissions, plugins):
```
Remove-Item -Recurse -Force "android\app\.cxx" -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force "android\build" -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force "android\app\build" -ErrorAction SilentlyContinue
.\gradlew.bat assembleRelease -PreactNativeArchitectures=arm64-v8a
```

### Yang TIDAK BOLEH
- `npx expo prebuild --clean` — kecuali native dependency berubah
- Build tanpa keystore — akan gagal di `validateSigningRelease`

## Bug Fixes Log

### Mobile App — 9 Fixes (2026-07-14)

| # | Bug | Penyebab | Fix | File |
|---|---|---|---|---|
| 1 | Cart ga ilang setelah order | Backend gak hapus cart | Hapus cart items + cart setelah DB transaction + frontend `setCart(null)` | `OrderController.php`, `CheckoutScreen.js` |
| 2 | Bukti bayar ga muncul | `Payment.php` accessor pakai `asset()` → localhost URL | Ganti ke relative path `/storage/...` di `formatOrder()` | `OrderController.php:552` |
| 3 | Stats profile ga load | Mobile baca JSON path salah (`data.meta` vs `data.data.meta`) | Fix ke `data.data?.meta?.total` | `ProfileScreen.js` |
| 4 | Gambar produk ga tampil | Gue salah ganti `$img->url` jadi raw `$img->image` tanpa prefix `/storage/` | Prepend `/storage/` di `formatProduct()` + `show()` + categories + variants | `ProductController.php` |
| 5 | Gambar detail kebesaran | `resizeMode="cover"` memotong gambar | Ganti ke `contain` + height 350px | `ProductDetailScreen.js` |
| 6 | Notifikasi kosong | User model `notifications()` conflict Notifiable trait + field `message` vs `body` | Override `notifications()` di User model + fix field name | `User.php`, `NotificationScreen.js` |
| 7 | Ikon kategori default 📦 | `CATEGORY_ICONS` cuma 12 nama exact | Ganti ke fuzzy matching 21 keyword arrays | `HomeScreen.js` |
| 8 | Splash double | `drawable-mdpi/splashscreen_logo.png` putih+pecah dari prebuild | Hapus PNG, `#FEF3C7`, XML layer-list | `colors.xml`, `drawable/splashscreen_logo.xml` |
| 9 | Upload bukti bayar error "must be an image" | Axios hardcoded `Content-Type: application/json` — FormData ga kekirim sebagai multipart | Tambah `headers: { 'Content-Type': 'multipart/form-data' }` | `OrderController.php`, `OrderDetailScreen.js` |

### Root Cause Penting — Image URL di Mobile
- **Backend `asset()` generates `http://localhost:8000/storage/...`** — broken di mobile
- **Mobile butuh relative path `/storage/...`** — `getImageUrl()` di `config.js` prepend `API_URL`
- **Semua image URL di API controllers WAJIB pakai `/storage/` prefix**, BUKAN `asset()`
- `Storage::url()` returns `/storage/...` — tapi harus manual prepend kalau akses raw `$img->image`

### CustomAlert System
- `src/components/CustomAlert.js` — animated modal dengan icons + colors
- `src/context/AlertContext.js` — `showAlert` + `showConfirm`
- Wrapper di `App.js` dengan `AlertProvider`
- **GUNAKAN INI**, jangan `Alert.alert()` — tampil jelek di Android
