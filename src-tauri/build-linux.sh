#!/bin/bash
# ─────────────────────────────────────────────────────────────
# Kankio — Arch Linux Build Script
# Builds AppImage + .deb via Tauri
# ─────────────────────────────────────────────────────────────

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ── 1. Check dependencies ────────────────────────────────────
info "Bağımlılıklar kontrol ediliyor..."

check_cmd() {
    command -v "$1" &>/dev/null || error "'$1' bulunamadı. Kurulum: $2"
}

check_cmd rustc   "curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh"
check_cmd cargo   "rustup bileşeni"
check_cmd node    "sudo pacman -S nodejs"
check_cmd npm     "sudo pacman -S npm"

# Check Tauri CLI
if ! cargo tauri --version &>/dev/null 2>&1; then
    warn "Tauri CLI bulunamadı, kuruluyor..."
    cargo install tauri-cli
fi

# ── 2. Check system libraries (Arch packages) ────────────────
info "Sistem kütüphaneleri kontrol ediliyor..."

REQUIRED_PKGS=(
    "webkit2gtk-4.1"     # WebKitGTK — Tauri'nin render engine'i
    "gtk3"               # GTK3 — pencere yönetimi
    "libayatana-appindicator"  # Tray icon desteği
    "openssl"            # TLS
    "appmenu-gtk-module" # Global menu entegrasyonu
)

MISSING=()
for pkg in "${REQUIRED_PKGS[@]}"; do
    if ! pacman -Qi "$pkg" &>/dev/null 2>&1; then
        MISSING+=("$pkg")
    fi
done

if [ ${#MISSING[@]} -gt 0 ]; then
    warn "Eksik paketler: ${MISSING[*]}"
    echo -e "Kurmak için: ${YELLOW}sudo pacman -S ${MISSING[*]}${NC}"
    read -p "Şimdi kurmak ister misiniz? [E/h] " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ee]$ ]] || [[ -z $REPLY ]]; then
        sudo pacman -S --needed "${MISSING[@]}"
    else
        error "Eksik paketler kurulmadan devam edilemez."
    fi
fi

# ── 3. Build ──────────────────────────────────────────────────
info "Kankio derleniyor (release modu)..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"
cargo tauri build 2>&1 | tail -20

# ── 4. Output ─────────────────────────────────────────────────
BUNDLE_DIR="$SCRIPT_DIR/target/release/bundle"

echo ""
info "Build tamamlandı!"
echo ""

if [ -d "$BUNDLE_DIR/appimage" ]; then
    APPIMAGE=$(find "$BUNDLE_DIR/appimage" -name "*.AppImage" 2>/dev/null | head -1)
    if [ -n "$APPIMAGE" ]; then
        SIZE=$(du -h "$APPIMAGE" | cut -f1)
        info "AppImage: $APPIMAGE ($SIZE)"
    fi
fi

if [ -d "$BUNDLE_DIR/deb" ]; then
    DEB=$(find "$BUNDLE_DIR/deb" -name "*.deb" 2>/dev/null | head -1)
    if [ -n "$DEB" ]; then
        SIZE=$(du -h "$DEB" | cut -f1)
        info "DEB: $DEB ($SIZE)"
    fi
fi

echo ""
echo -e "${GREEN}Çalıştırmak için:${NC}"
echo -e "  chmod +x $APPIMAGE && $APPIMAGE"
echo ""
