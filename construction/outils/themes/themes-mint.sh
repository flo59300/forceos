#!/usr/bin/env bash
# =====================================================================
# ForceOS : thème Linux Mint (Mint-Y) pour le bureau Cinnamon
# Conçu et développé par Florian Bigotte pour Force Ouvrière
#
# Compile le thème depuis les sources officielles de Linux Mint (GitHub,
# commit épinglé dans versions.conf), puis installe les trois variantes
# utilisées et le thème Cinnamon « ForceOS » (barre du bas en bleu FO).
#
#   themes-mint.sh <dossier de cache> <racine cible>     (racine : / ou includes.chroot)
# Prérequis : git, python3, sassc (ou pysassc).
# =====================================================================
set -euo pipefail
ICI=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=../../versions.conf
. "$ICI/../../versions.conf"
CACHE=${1:?dossier de cache}; CIBLE=${2:?racine cible}
SRC=$CACHE/mint-themes
# Compilateur Sass : le script de Mint appelle « pysassc ». Sous Debian, la
# commande équivalente est « sassc » (même bibliothèque libsass, mêmes arguments).
if ! command -v pysassc >/dev/null; then
  command -v sassc >/dev/null || { echo "compilateur Sass absent (paquet sassc)" >&2; exit 1; }
  ENVELOPPE=$(mktemp -d)
  printf '#!/bin/sh\nexec sassc "$@"\n' > "$ENVELOPPE/pysassc"; chmod +x "$ENVELOPPE/pysassc"
  PATH=$ENVELOPPE:$PATH
fi
[[ -d $SRC/.git ]] || git clone --quiet https://github.com/linuxmint/mint-themes "$SRC"
git -C "$SRC" fetch --quiet origin "$MINT_THEMES_COMMIT" 2>/dev/null || git -C "$SRC" fetch --quiet --tags origin
git -C "$SRC" checkout --quiet --force "$MINT_THEMES_COMMIT"
[[ $(git -C "$SRC" rev-parse HEAD) == "$MINT_THEMES_COMMIT" ]] || { echo "commit mint-themes inattendu" >&2; exit 1; }
git -C "$SRC" clean -qfdx
( cd "$SRC" && python3 generate-themes.py >/dev/null )

DEST=$CIBLE/usr/share/themes
mkdir -p "$DEST"
for t in Mint-Y Mint-Y-Blue Mint-Y-Dark-Blue; do
  [[ -f $SRC/usr/share/themes/$t/index.theme && -s $SRC/usr/share/themes/$t/gtk-3.0/gtk.css \
     && -s $SRC/usr/share/themes/$t/cinnamon/cinnamon.css ]] || { echo "variante $t incomplète" >&2; exit 1; }
  rm -rf "${DEST:?}/$t"
  cp -a "$SRC/usr/share/themes/$t" "$DEST/"
done

# Thème Cinnamon « ForceOS » : celui de Mint-Y-Dark-Blue, barre du bas bleu FO translucide
rm -rf "$DEST/ForceOS"
mkdir -p "$DEST/ForceOS"
cp -a "$SRC/usr/share/themes/Mint-Y-Dark-Blue/cinnamon" "$DEST/ForceOS/"
cat > "$DEST/ForceOS/index.theme" <<'THEME'
[Desktop Entry]
Type=X-GNOME-Metatheme
Name=ForceOS
Comment=Thème Mint-Y aux couleurs Force Ouvrière (Florian Bigotte)
Encoding=UTF-8

[X-GNOME-Metatheme]
GtkTheme=Mint-Y-Blue
MetacityTheme=Mint-Y
IconTheme=Mint-Y-Blue
CursorTheme=Bibata-Modern-Classic
ButtonLayout=:minimize,maximize,close
THEME
cat >> "$DEST/ForceOS/cinnamon/cinnamon.css" <<'CSS'

/* ForceOS : barre du bas bleu Force Ouvrière, translucide (Florian Bigotte) */
.panel-top, .panel-bottom, .panel-left, .panel-right {
  background-color: rgba(0, 43, 127, 0.88);
  border-color: rgba(255, 255, 255, 0.12);
}
.panel-bottom { box-shadow: 0 -1px 0 rgba(255, 255, 255, 0.10); }
.menu, .popup-menu-boxpointer { border-radius: 10px; }
.applet-box:hover, .applet-box:checked { background-color: rgba(255, 255, 255, 0.14); }

/* Disposition « dock » : barre du haut fine, en verre bleu FO, sans trait */
.panel-top {
  background-color: rgba(0, 31, 92, 0.72);
  box-shadow: none;
  border: none;
}
.panel-top .applet-box { border-radius: 8px; margin: 2px 1px; }
/* Menus et calendrier : grandes cartes arrondies, ombre douce */
.menu, .popup-menu-boxpointer, .applet-menu, #menu-box { border-radius: 16px; }
.popup-menu-item { border-radius: 8px; }
.popup-menu-item:active, .popup-menu-item:hover { background-color: rgba(23, 73, 184, 0.85); color: #ffffff; }
.menu-application-button-selected, .menu-category-button-selected {
  background-color: rgba(23, 73, 184, 0.85); border-radius: 10px; color: #ffffff;
}
.menu-favorites-box { border-radius: 14px; }
/* Bascule d'espace de travail et notifications aux couleurs FO */
.workspace-button.selected { background-color: #e2001a; border-color: #e2001a; }
.notification { border-radius: 14px; }
CSS
echo "Thème Mint compilé et installé dans $DEST"
