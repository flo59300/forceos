#!/usr/bin/env bash
# =====================================================================
# ForceOS : transformation automatique d'un PC Debian 13 en poste ForceOS
# Conçu et développé par Florian Bigotte pour Force Ouvrière
#
# À lancer depuis le dossier « forceos » extrait de l'archive, avec le
# paquet forceos-fo-gestion_*.deb à côté du dossier ou dedans :
#   sudo bash installer-forceos.sh
# ou, si votre compte n'a pas sudo :
#   su -c "bash installer-forceos.sh"
#
# Tout est automatique après une seule confirmation. Le compte qui lance
# le script devient le compte du militant, seul autorisé à ouvrir
# FO Gestion. Journal complet : /var/log/forceos-installation.log
# =====================================================================
set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
export DEBIAN_FRONTEND=noninteractive
umask 022

ICI=$(dirname "$(readlink -f "$0")")
CONSTR=$ICI/construction
INC=$CONSTR/config/includes.chroot
CACHE=/var/cache/forceos-installation
JOURNAL=/var/log/forceos-installation.log

bleu() { printf '\n\033[1;34m== %s\033[0m\n' "$*"; }
info() { printf '   %s\n' "$*"; }
erreur() { printf '\n\033[1;31mERREUR : %s\033[0m\n' "$*" >&2; printf 'Journal complet : %s\n' "$JOURNAL" >&2; exit 1; }
trap 'erreur "la commande « $BASH_COMMAND » a échoué (ligne $LINENO)."' ERR

# --- 0. Vérifications, avant toute modification ------------------------------------
[[ $EUID -eq 0 ]] || { echo "À lancer en administrateur : sudo bash installer-forceos.sh  (ou : su -c \"bash installer-forceos.sh\")" >&2; exit 1; }
[[ -d $INC && -f $CONSTR/versions.conf ]] || erreur "lancez ce script depuis le dossier « forceos » extrait de l'archive."
# shellcheck source=construction/versions.conf
. "$CONSTR/versions.conf"
. /etc/os-release
[[ ${VERSION_CODENAME:-} == trixie ]] || erreur "ce PC doit être sous Debian 13 (trixie) ; trouvé : ${PRETTY_NAME:-inconnu}."
[[ $(dpkg --print-architecture) == amd64 ]] || erreur "PC 64 bits (amd64) requis."

# Compte du militant : celui qui a lancé le script
MILITANT=${SUDO_USER:-$(logname 2>/dev/null || true)}
if [[ -z $MILITANT || $MILITANT == root ]]; then
  MILITANT=$(getent passwd 1000 | cut -d: -f1 || true)
fi
[[ -n $MILITANT ]] && getent passwd "$MILITANT" >/dev/null || erreur "impossible de trouver le compte du militant."
MAISON=$(getent passwd "$MILITANT" | cut -d: -f6)

# Paquet FO Gestion, cherché aux endroits habituels
DEB_APP=""
for d in "$ICI" "$(dirname "$ICI")" "$MAISON/Téléchargements" "$MAISON/Downloads" "$MAISON"; do
  for f in "$d"/forceos-fo-gestion_*_amd64.deb; do
    [[ -f $f ]] && DEB_APP=$f
  done
  [[ -n $DEB_APP ]] && break
done
[[ -n $DEB_APP ]] || erreur "paquet forceos-fo-gestion_1.0.0_amd64.deb introuvable : placez-le à côté du dossier « forceos »."

LIBRE=$(df --output=avail -BG / | tail -1 | tr -dc 0-9)
(( LIBRE >= 25 )) || erreur "il faut au moins 25 Go libres sur le disque (trouvé : ${LIBRE} Go)."
getent hosts deb.debian.org >/dev/null || erreur "pas de connexion à Internet."

CHIFFRE=non
lsblk -nro TYPE | grep -qx crypt && CHIFFRE=oui

clear || true
cat <<TEXTE

  ForceOS ${FORCEOS_VERSION} : transformation de ce PC en poste Force Ouvrière
  ----------------------------------------------------------------------

  Compte du militant      : ${MILITANT}
  Paquet FO Gestion       : $(basename "$DEB_APP")
  Disque chiffré          : ${CHIFFRE}

  Ce qui va changer sur ce PC :
   - bureau XFCE aux couleurs FO et écran de connexion ForceOS ;
   - FO Gestion, l'IA locale et la transcription audio installées ;
   - navigateur LibreWolf verrouillé, filtrage DNS, pare-feu ;
   - clés USB inconnues bloquées ;
   - le compte « ${MILITANT} » perd les droits administrateur (sudo) :
     seul le mot de passe root permettra d'administrer le poste.

  Durée : 30 à 60 minutes, selon la connexion (environ 5 Go à télécharger).

TEXTE
if [[ $CHIFFRE == non ]]; then
  printf '  \033[1;33mATTENTION : le disque de ce PC n'"'"'est pas chiffré. En cas de vol, les\n'
  printf '  fichiers seraient lisibles. Seule une réinstallation de Debian avec\n'
  printf '  « chiffrement » permet de le corriger.\033[0m\n\n'
fi
read -r -p "  Tapez OUI pour continuer : " REPONSE </dev/tty
[[ ${REPONSE^^} == OUI ]] || { echo "Abandon : rien n'a été modifié."; exit 0; }

# Mot de passe administrateur (root) : indispensable puisque le militant n'aura plus sudo
if passwd -S root 2>/dev/null | awk '{exit !($2 == "L" || $2 == "NP")}'; then
  echo
  echo "  Ce PC n'a pas encore de mot de passe administrateur (root)."
  echo "  Choisissez-le maintenant et notez-le : il servira pour autoriser les clés USB"
  echo "  et modifier les consignes de l'IA."
  until passwd root </dev/tty; do echo "  Recommencez."; done
fi

exec > >(tee -a "$JOURNAL") 2>&1
echo "=== Installation ForceOS du $(date '+%d/%m/%Y %H:%M') ==="
mkdir -p "$CACHE"

telecharger() {   # url fichier algo empreinte
  local url=$1 dest=$2 algo=$3 attendu=$4
  if [[ ! -f $dest ]] || ! echo "$attendu  $dest" | "${algo}sum" -c --status; then
    curl -fL --retry 3 --progress-bar -o "$dest.part" "$url"
    mv "$dest.part" "$dest"
  fi
  echo "$attendu  $dest" | "${algo}sum" -c --quiet || erreur "empreinte incorrecte pour $(basename "$dest") ; relancez le script."
}

# --- 1. Paquets Debian ---------------------------------------------------------------
bleu "1/8 Paquets Debian (le plus long)"
install -D -m 0644 "$CONSTR/config/includes.chroot_before_packages/etc/dpkg/dpkg.cfg.d/50-forceos-francais" \
  /etc/dpkg/dpkg.cfg.d/50-forceos-francais
echo "lightdm shared/default-x-display-manager select lightdm" | debconf-set-selections
echo "gdm3 shared/default-x-display-manager select lightdm" | debconf-set-selections
apt-get update
LISTE=()
while read -r p; do
  # Un paquet absent des dépôts ne bloque pas toute l'installation
  if apt-cache show "$p" >/dev/null 2>&1; then LISTE+=("$p"); else info "paquet indisponible, ignoré : $p"; fi
done < <(grep -hvE '^\s*(#|$)' "$CONSTR"/config/package-lists/*.list.chroot | sort -u)
apt-get install -y -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
  "${LISTE[@]}" curl zstd cmake build-essential git ca-certificates

# --- 2. Fichiers ForceOS ---------------------------------------------------------------
bleu "2/8 Fichiers ForceOS"
cp -r --preserve=mode,timestamps "$INC"/. /
chown -R root:root /etc/forceos /usr/libexec/forceos /usr/share/forceos
printf '%s\n' "$FORCEOS_VERSION" > /etc/forceos/version

# --- 3. Transcription audio (whisper.cpp) ------------------------------------------------
bleu "3/8 Transcription audio"
SRC=$CACHE/whisper.cpp
[[ -d $SRC/.git ]] || git clone --quiet https://github.com/ggml-org/whisper.cpp "$SRC"
git -C "$SRC" fetch --quiet --tags
git -C "$SRC" checkout --quiet "$WHISPER_COMMIT"
cmake -S "$SRC" -B "$SRC/build" -DCMAKE_BUILD_TYPE=Release -DGGML_NATIVE=OFF \
      -DBUILD_SHARED_LIBS=OFF -DWHISPER_BUILD_TESTS=OFF -DWHISPER_BUILD_EXAMPLES=ON >/dev/null
cmake --build "$SRC/build" --target whisper-cli -j"$(nproc)" >/dev/null
install -D -m 0755 "$SRC/build/bin/whisper-cli" /usr/lib/forceos/whisper/whisper-cli
telecharger "$WHISPER_MODELE_URL" "$CACHE/ggml-small.bin" sha1 "$WHISPER_MODELE_SHA1"
install -D -m 0644 "$CACHE/ggml-small.bin" /usr/share/forceos/whisper/ggml-small.bin

# --- 4. IA locale (Ollama et son modèle) --------------------------------------------------
bleu "4/8 IA locale"
telecharger "https://github.com/ollama/ollama/releases/download/$OLLAMA_VERSION/ollama-linux-amd64.tar.zst" \
  "$CACHE/ollama.tar.zst" sha256 "$OLLAMA_SHA256"
systemctl stop ollama.service 2>/dev/null || true
rm -rf /usr/lib/ollama
tar --zstd -xf "$CACHE/ollama.tar.zst" -C /usr bin/ollama lib/ollama
find /usr/lib/ollama -mindepth 1 -maxdepth 1 -type d \( -iname '*cuda*' -o -iname '*rocm*' -o -iname '*vulkan*' \) -exec rm -rf {} +
mkdir -p /var/lib/ollama/models
OLLAMA_MODELS=/var/lib/ollama/models OLLAMA_HOST=127.0.0.1:11499 /usr/bin/ollama serve >"$CACHE/ollama.log" 2>&1 &
PID_OLLAMA=$!
for _ in $(seq 30); do OLLAMA_HOST=127.0.0.1:11499 /usr/bin/ollama list >/dev/null 2>&1 && break; sleep 1; done
OLLAMA_HOST=127.0.0.1:11499 /usr/bin/ollama pull "$OLLAMA_MODELE"
kill "$PID_OLLAMA"; wait "$PID_OLLAMA" 2>/dev/null || true

# --- 5. RustDesk et FO Gestion --------------------------------------------------------------
bleu "5/8 Assistance à distance et FO Gestion"
telecharger "https://github.com/rustdesk/rustdesk/releases/download/$RUSTDESK_VERSION/rustdesk-$RUSTDESK_VERSION-x86_64.deb" \
  "$CACHE/rustdesk.deb" sha256 "$RUSTDESK_SHA256"
apt-get install -y "$CACHE/rustdesk.deb"
systemctl stop rustdesk.service 2>/dev/null || true
cp "$DEB_APP" "$CACHE/fo-gestion.deb"
apt-get install -y "$CACHE/fo-gestion.deb"

# --- 6. Réglages du système (mêmes scripts que pour l'image ISO) ------------------------
bleu "6/8 Réglages du système"
for h in 0100-forceos-depots 0200-forceos-francais 0300-forceos-services 0400-forceos-securite 0500-forceos-ia 0700-forceos-apparence; do
  info "$h"
  sh "$CONSTR/config/hooks/normal/$h.hook.chroot"
done
# Écran de connexion ForceOS à la place de celui de GNOME
echo /usr/sbin/lightdm > /etc/X11/default-display-manager
systemctl disable gdm3.service 2>/dev/null || true
systemctl enable --force lightdm.service
update-grub || true
# La base antivirale est téléchargée par le service clamav-freshclam, déjà lancé
systemctl restart clamav-freshclam.service 2>/dev/null || true

# --- 7. Compte du militant et base de FO Gestion ------------------------------------------
bleu "7/8 Compte du militant : $MILITANT"
printf '%s\n' "$MILITANT" > /etc/forceos/utilisateur-autorise
chown root:root /etc/forceos/utilisateur-autorise; chmod 0644 /etc/forceos/utilisateur-autorise
systemctl daemon-reload
if ! systemctl restart mariadb.service; then
  journalctl -u mariadb.service -n 20 --no-pager || true
  erreur "la base de données MariaDB ne démarre pas (voir les lignes ci-dessus)."
fi
/usr/libexec/forceos/db-provisionner
mkdir -p /var/lib/forceos && touch /var/lib/forceos/premier-demarrage-fait
systemctl enable ollama.service

# Lanceurs, modèles et dossiers du militant (sans écraser ses fichiers)
BUREAU=$(runuser -u "$MILITANT" -- xdg-user-dir DESKTOP 2>/dev/null || echo "$MAISON/Bureau")
[[ $BUREAU == "$MAISON" || -z $BUREAU ]] && BUREAU=$MAISON/Bureau
install -d -o "$MILITANT" -g "$MILITANT" "$BUREAU" "$MAISON/Modèles" "$MAISON/.config"
cp -n /etc/skel/Bureau/*.desktop "$BUREAU/"
cp -rn /etc/skel/Modèles/. "$MAISON/Modèles/"
cp -rn /etc/skel/Documents "$MAISON/" 2>/dev/null || true
cp -rn /etc/skel/.config/. "$MAISON/.config/"
chown -R "$MILITANT:$MILITANT" "$BUREAU" "$MAISON/Modèles" "$MAISON/Documents" "$MAISON/.config"
chmod 0755 "$BUREAU"/forceos-*.desktop
rm -f "$MAISON/.config/forceos/bureau-prepare"
printf '[Desktop]\nSession=xfce\n' > "$MAISON/.dmrc"; chown "$MILITANT:$MILITANT" "$MAISON/.dmrc"

# Le militant n'est plus administrateur
gpasswd -d "$MILITANT" sudo 2>/dev/null || true

# --- 8. Nettoyage -----------------------------------------------------------------------------
bleu "8/8 Nettoyage"
apt-get autoremove --purge -y
apt-get clean
rm -rf "$CACHE/whisper.cpp/build"

trap - ERR
cat <<TEXTE

  ======================================================================
   ForceOS est installé.

   Redémarrez l'ordinateur depuis le menu (ou : su -c reboot).

   À l'écran de connexion, entrez avec le compte « ${MILITANT} », puis
   ouvrez FO Gestion depuis le bureau pour créer la section et le
   compte du secrétaire.
  ======================================================================

TEXTE
