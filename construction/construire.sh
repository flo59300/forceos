#!/usr/bin/env bash
# =====================================================================
# ForceOS : construction de l'image ISO
# Conçu et développé par Florian Bigotte pour Force Ouvrière
#
# À lancer en root sur une machine Debian 13 (trixie) amd64 :
#   sudo ./construire.sh /chemin/vers/forceos-fo-gestion_X.Y.Z_amd64.deb
#
# Étapes : vérification des prérequis, téléchargement vérifié des
# composants hors Debian (mis en cache dans ./cache), compilation de
# whisper.cpp, préparation du modèle d'IA, puis live-build.
# Résultat : forceos-<version>-amd64.iso et son empreinte SHA-256.
# =====================================================================
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")"
RACINE=$PWD
# shellcheck source=versions.conf
. "$RACINE/versions.conf"

CACHE=$RACINE/cache
INC=$RACINE/config/includes.chroot
PAQUETS=$RACINE/config/packages.chroot
mkdir -p "$CACHE" "$PAQUETS"

etape() { printf '\n\033[1;34m== %s\033[0m\n' "$*"; }
erreur() { printf '\033[1;31mERREUR : %s\033[0m\n' "$*" >&2; exit 1; }

# Téléchargement avec contrôle d'empreinte ; le fichier en cache est réutilisé
# seulement s'il est intact.
telecharger() {   # url fichier algo empreinte
  local url=$1 dest=$2 algo=$3 attendu=$4
  if [[ ! -f $dest ]] || ! echo "$attendu  $dest" | "${algo}sum" -c --status; then
    curl -fL --retry 3 -o "$dest.part" "$url"
    mv "$dest.part" "$dest"
  fi
  echo "$attendu  $dest" | "${algo}sum" -c --quiet || erreur "empreinte incorrecte pour $dest"
}

# --- 0. Prérequis ----------------------------------------------------------------
etape "Vérification des prérequis"
[[ $EUID -eq 0 ]] || erreur "lancer ce script en root (sudo)."
. /etc/os-release
[[ ${VERSION_CODENAME:-} == "$DEBIAN_VERSION" ]] || erreur "machine Debian $DEBIAN_VERSION requise (trouvé : ${PRETTY_NAME:-inconnu})."
[[ $(dpkg --print-architecture) == amd64 ]] || erreur "architecture amd64 requise."
DEB_APP=${1:-}
[[ -f $DEB_APP ]] || erreur "indiquer le paquet FO Gestion (.deb) en argument."
dpkg-deb -f "$DEB_APP" Package | grep -qx forceos-fo-gestion || erreur "$DEB_APP n'est pas le paquet FO Gestion."
if ! grep -q '^SERVEUR=..' "$INC/etc/forceos/support.conf" 2>/dev/null; then
  printf '\033[1;33mATTENTION : aucun serveur RustDesk dans config/includes.chroot/etc/forceos/support.conf.\n'
  printf "L'image sera construite, mais le bouton « Contacter le support » restera inactif.\033[0m\n"
  sleep 5
fi
apt-get update >/dev/null
apt-get install -y --no-install-recommends live-build file cpio curl zstd cmake build-essential git ca-certificates \
  nftables libxml2-utils gnupg >/dev/null

# --- 1. Paquet FO Gestion -----------------------------------------------------------
etape "Paquet FO Gestion"
rm -f "$PAQUETS"/forceos-fo-gestion_*.deb
cp "$DEB_APP" "$PAQUETS/"
printf '%s\n' "$FORCEOS_VERSION" > "$INC/etc/forceos/version"

# --- 2. RustDesk -------------------------------------------------------------------
etape "RustDesk $RUSTDESK_VERSION"
telecharger "https://github.com/rustdesk/rustdesk/releases/download/$RUSTDESK_VERSION/rustdesk-$RUSTDESK_VERSION-x86_64.deb" \
  "$CACHE/rustdesk-$RUSTDESK_VERSION.deb" sha256 "$RUSTDESK_SHA256"
rm -f "$PAQUETS"/rustdesk*.deb
cp "$CACHE/rustdesk-$RUSTDESK_VERSION.deb" "$PAQUETS/"

# --- 3. Ollama (moteur seul ; le modèle est préparé à l'étape 5) ----------------------
etape "Ollama $OLLAMA_VERSION"
telecharger "https://github.com/ollama/ollama/releases/download/$OLLAMA_VERSION/ollama-linux-amd64.tar.zst" \
  "$CACHE/ollama-$OLLAMA_VERSION.tar.zst" sha256 "$OLLAMA_SHA256"
rm -rf "$INC/usr/lib/ollama" "$INC/usr/bin/ollama"
# L'archive contient bin/ollama et lib/ollama (bibliothèques CPU et GPU) : seules
# les bibliothèques CPU sont gardées, les postes n'ont pas de carte graphique dédiée.
tar --zstd -xf "$CACHE/ollama-$OLLAMA_VERSION.tar.zst" -C "$INC/usr" bin/ollama lib/ollama
find "$INC/usr/lib/ollama" -mindepth 1 -maxdepth 1 -type d \( -iname '*cuda*' -o -iname '*rocm*' -o -iname '*vulkan*' \) -exec rm -rf {} +

# --- 4. whisper.cpp -------------------------------------------------------------------
etape "whisper.cpp $WHISPER_VERSION"
SRC=$CACHE/whisper.cpp
if [[ ! -d $SRC/.git ]]; then
  git clone --quiet https://github.com/ggml-org/whisper.cpp "$SRC"
fi
git -C "$SRC" fetch --quiet --tags
git -C "$SRC" checkout --quiet "$WHISPER_COMMIT"
[[ $(git -C "$SRC" rev-parse HEAD) == "$WHISPER_COMMIT" ]] || erreur "commit whisper.cpp inattendu."
# GGML_NATIVE=OFF : binaire portable, utilisable sur tous les processeurs x86-64
cmake -S "$SRC" -B "$SRC/build" -DCMAKE_BUILD_TYPE=Release -DGGML_NATIVE=OFF \
      -DBUILD_SHARED_LIBS=OFF -DWHISPER_BUILD_TESTS=OFF -DWHISPER_BUILD_EXAMPLES=ON >/dev/null
cmake --build "$SRC/build" --target whisper-cli -j"$(nproc)" >/dev/null
install -D -m 0755 "$SRC/build/bin/whisper-cli" "$INC/usr/lib/forceos/whisper/whisper-cli"
telecharger "$WHISPER_MODELE_URL" "$CACHE/ggml-small.bin" sha1 "$WHISPER_MODELE_SHA1"
install -D -m 0644 "$CACHE/ggml-small.bin" "$INC/usr/share/forceos/whisper/ggml-small.bin"

# --- 5. Modèle d'IA -------------------------------------------------------------------
# Téléchargé une fois sur la machine de construction avec le binaire Ollama vérifié,
# puis copié dans l'image : le poste n'a jamais besoin d'Internet pour l'IA.
etape "Modèle d'IA $OLLAMA_MODELE"
MODELES=$CACHE/ollama-modeles
mkdir -p "$MODELES"
export OLLAMA_MODELS=$MODELES OLLAMA_HOST=127.0.0.1:11499
"$INC/usr/bin/ollama" serve >"$CACHE/ollama.log" 2>&1 &
PID_OLLAMA=$!
trap 'kill $PID_OLLAMA 2>/dev/null || true' EXIT
for _ in $(seq 30); do "$INC/usr/bin/ollama" list >/dev/null 2>&1 && break; sleep 1; done
"$INC/usr/bin/ollama" pull "$OLLAMA_MODELE"
"$INC/usr/bin/ollama" show "$OLLAMA_MODELE" >/dev/null || erreur "modèle $OLLAMA_MODELE indisponible."
kill "$PID_OLLAMA"; wait "$PID_OLLAMA" 2>/dev/null || true
trap - EXIT
rm -rf "$INC/var/lib/ollama/models"
mkdir -p "$INC/var/lib/ollama"
cp -a "$MODELES" "$INC/var/lib/ollama/models"

# --- 6. Contrôle des fichiers ForceOS -------------------------------------------------
etape "Contrôle des fichiers ForceOS"
for s in "$INC"/usr/bin/forceos-* "$INC"/usr/libexec/forceos/* config/hooks/normal/*.hook.chroot; do
  head -1 "$s" | grep -q '^#!/usr/bin/env bash\|^#!/bin/sh' || continue
  bash -n "$s" || erreur "erreur de syntaxe dans $s"
done
nft -c -f "$INC/etc/nftables.conf" || erreur "pare-feu invalide."
if command -v xmllint >/dev/null; then
  xmllint --noout "$INC"/usr/share/polkit-1/actions/*.policy || erreur "action polkit invalide."
fi

# --- 7. Paquets disponibles -------------------------------------------------------------
# Un nom de paquet absent de Debian 13 ferait échouer toute la construction au bout
# d'une heure : les listes sont vérifiées d'abord, les absents signalés et retirés.
etape "Vérification des listes de paquets"
VERIF=$(mktemp -d)
mkdir -p "$VERIF/lists/partial"
printf 'deb http://deb.debian.org/debian %s main contrib non-free-firmware\n' "$DEBIAN_VERSION" "$DEBIAN_VERSION-updates" > "$VERIF/sources.list"
printf 'deb http://security.debian.org/debian-security %s-security main contrib non-free-firmware\n' "$DEBIAN_VERSION" >> "$VERIF/sources.list"
APTV=(-o Dir::Etc::sourcelist="$VERIF/sources.list" -o Dir::Etc::sourceparts=- -o Dir::State::Lists="$VERIF/lists" -o APT::Get::List-Cleanup=0)
apt-get "${APTV[@]}" update -qq
for liste in config/package-lists/*.list.chroot; do
  while read -r p; do
    [[ -z $p || $p == \#* ]] && continue
    if ! apt-cache "${APTV[@]}" show "$p" >/dev/null 2>&1; then
      printf '\033[1;33mpaquet absent de Debian %s, retiré : %s\033[0m\n' "$DEBIAN_VERSION" "$p"
      sed -i "s/^$p\$/# absent de Debian : $p/" "$liste"
    fi
  done < "$liste"
done
rm -rf "$VERIF"

# --- 8. live-build ----------------------------------------------------------------------
etape "Construction de l'image (une à deux heures)"
lb clean --purge >/dev/null 2>&1 || true
lb config
lb build 2>&1 | tee "$RACINE/construction.log"
ISO=$(ls -1 "$RACINE"/*.hybrid.iso 2>/dev/null | head -1)
[[ -f $ISO ]] || erreur "aucune image produite : voir construction.log"
FINAL=$RACINE/forceos-$FORCEOS_VERSION-amd64.iso
mv "$ISO" "$FINAL"
( cd "$RACINE" && sha256sum "$(basename "$FINAL")" > "$(basename "$FINAL").sha256" )
etape "Terminé : $FINAL ($(du -h "$FINAL" | cut -f1))"
