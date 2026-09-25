#!/usr/bin/env bash
#
# release-apk.sh — Upload APK ke GitHub Releases + arahkan link "latest" ke versi baru.
#
# Kenapa script ini ada:
#   InfinityFree memblokir file .apk (dihapus otomatis) dan batasi ukuran file 10MB.
#   Jadi Hello Store host APK-nya di GitHub Releases sebagai asset bernama HelloStore.apk.
#   Link publik tetap: https://github.com/wforyu/hello-store/releases/latest/download/HelloStore.apk
#
# Cara pakai:
#   ./release-apk.sh [path-ke-APK]           # auto-detect versi dari nama file, atau auto-increment
#   ./release-apk.sh mobile/HelloStore.apk
#   ./release-apk.sh my.apk v1.0.0-120       # paksa tag tertentu
#
# Prasyarat:
#   - gh CLI terinstall & login (gh auth status)
#   - bash (pakai Git Bash di Windows, atau terminal Linux/macOS)

set -euo pipefail

REPO="wforyu/hello-store"
ASSET_NAME="HelloStore.apk"
DEFAULT_APK_DIR="mobile"
DEFAULT_TAG_PREFIX="v1.0.0"

APK_FILE="${1:-}"
FORCED_TAG="${2:-}"

# ---- Resolve APK kalau tidak dikasih argumen ----
if [[ -z "$APK_FILE" ]]; then
    NEWEST=$(ls -t "$DEFAULT_APK_DIR"/HelloStore-*.apk 2>/dev/null | head -1 || true)
    if [[ -z "$NEWEST" ]]; then
        echo "ERROR: tidak ada APK ditemukan. Kasih argumen: $0 <path-ke-APK>"
        exit 1
    fi
    APK_FILE="$NEWEST"
    echo ">> APK otomatis: $APK_FILE"
else
    if [[ ! -f "$APK_FILE" ]]; then
        echo "ERROR: file tidak ditemukan: $APK_FILE"
        exit 1
    fi
fi

# ---- Tentukan tag ----
# Deteksi versi dari nama file seperti HelloStore-v1.0.0-116.apk
TAG=""
if [[ -z "$FORCED_TAG" ]]; then
    if [[ "$APK_FILE" =~ HelloStore-v([0-9]+\.[0-9]+\.[0-9]+)-([0-9]+)\.apk$ ]]; then
        TAG="v${BASH_REMATCH[1]}-${BASH_REMATCH[2]}"
    else
        # Auto increment dari release terakhir kalau nama file tidak standar
        LAST_AUX=$(gh release list --repo "$REPO" --limit 1 --json tagName --jq '.[0].tagName' 2>/dev/null || true)
        if [[ "$LAST_AUX" =~ ^v[0-9]+\.[0-9]+\.[0-9]+-([0-9]+)$ ]]; then
            NEXT=$(( ${BASH_REMATCH[1]} + 1 ))
            TAG="${DEFAULT_TAG_PREFIX}-${NEXT}"
        else
            TAG="${DEFAULT_TAG_PREFIX}-1"
        fi
        echo ">> Tag otomatis (auto-increment): $TAG"
    fi
else
    TAG="$FORCED_TAG"
fi

echo ">> Tag: $TAG"
echo ">> Upload asset sebagai: $ASSET_NAME"

# ---- Guard: jangan pernah turunkan versi "latest" ----
# Link permanen di website (releases/latest/download/HelloStore.apk) ikut
# release yang GitHub tandai "latest". GitHub memilihnya berdasarkan TANGGAL
# PUBLISH, bukan nomor versi. Jadi kalau release lama (mis. 116) dihapus lalu
# dibuat ulang, tag itu jadi paling baru DIBUAT -> otomatis jadi "latest" ->
# seluruh user download APK LAMA tanpa sadar.
# Tolak Publishing versi yang lebih lama, kecuali dipaksa lewat RELEASE_FORCE=1.
version_code_of() { [[ "$1" =~ -([0-9]+)$ ]] && echo "${BASH_REMATCH[1]}"; }

NEW_CODE="$(version_code_of "$TAG")"
CURRENT_TAG="$(gh api "repos/${REPO}/releases/latest" --jq '.tag_name' 2>/dev/null || true)"
CURRENT_CODE="$(version_code_of "$CURRENT_TAG")"

if [[ -n "$NEW_CODE" && -n "$CURRENT_CODE" && "$TAG" != "$CURRENT_TAG" && "$NEW_CODE" -le "$CURRENT_CODE" ]]; then
    if [[ "${RELEASE_FORCE:-0}" != "1" ]]; then
        echo "" >&2
        echo "ERROR: VERSI MENURUN - refusing to publish." >&2
        echo "       Tag yang mau di-upload : $TAG (versionCode $NEW_CODE)" >&2
        echo "       Release 'latest' sekarang: $CURRENT_TAG (versionCode $CURRENT_CODE)" >&2
        echo "" >&2
        echo "  Kalau ini di-publish, GitHub akan menandai $TAG sebagai 'latest',"
        echo "  dan link download di website akan menyajikan APK LAMA (v$NEW_CODE)," >&2
        echo "  bukan v$CURRENT_CODE yang sekarang." >&2
        echo "" >&2
        echo "  Bump versionCode dulu di mobile/android/app/build.gradle + mobile/app.json," >&2
        echo "  lalu release ulang. Kalau memang mau memaksa (tahu risikonya):" >&2
        echo "      RELEASE_FORCE=1 ./release-apk.sh <file.apk>" >&2
        exit 1
    fi
    echo "WARNING: RELEASE_FORCE=1 - tetap publish $TAG (> $CURRENT_CODE) sebagai 'latest'." >&2
    echo "         Website akan menyajikan APK v$NEW_CODE sampai ada release yang lebih baru." >&2
fi

# ---- Hapus release & tag lama dengan nama yang sama (kalau re-run) ----
if gh release view "$TAG" --repo "$REPO" >/dev/null 2>&1; then
    echo ">> Release $TAG sudah ada — hapus dulu biar asset diganti..."
    gh release delete "$TAG" --repo "$REPO" --yes

    # delete tag juga supaya create baru
    git push --delete origin "$TAG" 2>/dev/null || true
    git tag -d "$TAG" 2>/dev/null || true
fi

# ---- Create release (asset di-upload dengan nama persis "HelloStore.apk") ----
# PENTING: jangan pakai sintaks "file.apk#Label" dari gh release create.
# Itu mengatur *label*, bukan *nama* asset, jadi filenya tetap terupload
# dengan nama aslinya (mis. HelloStore-v1.0.0-117.apk) dan link permanen
# .../releases/latest/download/HelloStore.apk jadi 404.
# Jadi: salin dulu ke nama sementara "HelloStore.apk", lalu upload itu.
STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT
STAGED_APK="$STAGE_DIR/$ASSET_NAME"
cp "$APK_FILE" "$STAGED_APK"

echo ">> Membuat release & upload APK ($(du -h "$APK_FILE" | cut -f1))..."
if ! gh release create "$TAG" "$STAGED_APK" \
    --repo "$REPO" \
    --title "Hello Store ${TAG}" \
    --notes "- Aplikasi Android Hello Store (APK)
- Download lalu buka file di HP Android (izinkan instal dari 'Sumber Tidak Dikenal' jika diminta)" \
    --latest; then
    # gh release create gagal saat upload asset (mis. transient 404 dari
    # uploads.github.com) dan karena `set -e` proses berhenti di tengah —
    # release pun bisa tertinggal setengah jadi. Buang dulu, lalu coba lagi
    # dengan urutan: buat release kosong, baru upload terpisah.
    echo ">> Upload via create gagal — buat release dulu, upload terpisah..."
    sleep 5
    gh release view "$TAG" --repo "$REPO" >/dev/null 2>&1 \
        && gh release delete "$TAG" --repo "$REPO" --yes
    gh release create "$TAG" --repo "$REPO" \
        --title "Hello Store ${TAG}" \
        --notes "- Aplikasi Android Hello Store (APK)
- Download lalu buka file di HP Android (izinkan instal dari 'Sumber Tidak Dikenal' jika diminta)" \
        --latest
    gh release upload "$TAG" "$STAGED_APK" --repo "$REPO" --clobber
fi

DL_URL="https://github.com/${REPO}/releases/latest/download/${ASSET_NAME}"

# Sanity check: link permanen hanya valid kalau nama asset persis HelloStore.apk
if ! gh api "repos/${REPO}/releases/tags/${TAG}" --jq ".assets[].name" 2>/dev/null | grep -qx "$ASSET_NAME"; then
    echo "ERROR: asset bernama '$ASSET_NAME' tidak ditemukan di release $TAG." >&2
    echo "       Link permanen akan 404: $DL_URL" >&2
    exit 1
fi

echo
echo "============================================================"
echo "  SELESAI ✓"
echo "  Halaman release : https://github.com/${REPO}/releases/tag/${TAG}"
echo "  Link download   : ${DL_URL}"
echo "  (Link di atas TIDAK berubah setiap release baru)"
echo "============================================================"