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

# ---- Hapus release & tag lama dengan nama yang sama (kalau re-run) ----
if gh release view "$TAG" --repo "$REPO" >/dev/null 2>&1; then
    echo ">> Release $TAG sudah ada — hapus dulu biar asset diganti..."
    gh release delete "$TAG" --repo "$REPO" --yes

    # delete tag juga supaya create baru
    git push --delete origin "$TAG" 2>/dev/null || true
    git tag -d "$TAG" 2>/dev/null || true
fi

# ---- Create release (file sementara di-rename jadi HelloStore.apk) ----
echo ">> Membuat release & upload APK ($(du -h "$APK_FILE" | cut -f1))..."
gh release create "$TAG" "$APK_FILE#$ASSET_NAME" "$@" \
    --repo "$REPO" \
    --title "Hello Store ${TAG}" \
    --notes "- Aplikasi Android Hello Store (APK)
- Download lalu buka file di HP Android (izinkan instal dari 'Sumber Tidak Dikenal' jika diminta)" \
    --latest

DL_URL="https://github.com/${REPO}/releases/latest/download/${ASSET_NAME}"
echo
echo "============================================================"
echo "  SELESAI ✓"
echo "  Halaman release : https://github.com/${REPO}/releases/tag/${TAG}"
echo "  Link download   : ${DL_URL}"
echo "  (Link di atas TIDAK berubah setiap release baru)"
echo "============================================================"