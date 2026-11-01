#!/usr/bin/env bash
# =====================================================================
# ForceOS : fabrication des modèles de documents (clic droit, « Nouveau »)
# Conçu et développé par Florian Bigotte pour Force Ouvrière
#
# Prérequis sur la machine de construction : scribus, libreoffice
# (writer et calc), xvfb, police Carlito. Les fichiers produits sont
# placés dans config/includes.chroot/etc/skel/Modèles/.
# =====================================================================
set -euo pipefail
ICI=$(cd "$(dirname "$0")" && pwd)
RACINE=$(cd "$ICI/../.." && pwd)
LOGO=$RACINE/config/includes.chroot/usr/share/forceos/images/fo-stellantis.png
DEST=$RACINE/config/includes.chroot/etc/skel/Modèles
TRAVAIL=$(mktemp -d); trap 'rm -rf "$TRAVAIL"' EXIT
mkdir -p "$DEST"; cd "$TRAVAIL"

# 1. Tract A5 (Scribus, fabriqué par script : fichier toujours valide)
timeout 300 xvfb-run -a scribus -g -ns -py "$ICI/tract_scribus.py" \
  "$TRAVAIL/Tract A5 Force Ouvrière.sla" "$TRAVAIL/tract.pdf" "$LOGO" >/dev/null 2>&1
[[ -s "Tract A5 Force Ouvrière.sla" ]] || { echo "Échec du tract Scribus" >&2; exit 1; }
# Le logo est référencé à son emplacement sur le poste ForceOS, pas sur la machine de construction
sed -i -E 's#PFILE="[^"]*fo-stellantis\.png"#PFILE="/usr/share/forceos/images/fo-stellantis.png"#' "Tract A5 Force Ouvrière.sla"
grep -q 'PFILE="/usr/share/forceos/images/fo-stellantis.png"' "Tract A5 Force Ouvrière.sla" \
  || { echo "Chemin du logo du tract non corrigé" >&2; exit 1; }

# 2. Lettre à la direction (logo intégré au fichier)
python3 -I - "$ICI/lettre.fodt" "$LOGO" lettre.fodt <<'PY'
import sys, base64, io
from PIL import Image
src, logo, out = sys.argv[1:4]
im = Image.open(logo); im.thumbnail((400, 400)); b = io.BytesIO(); im.save(b, 'PNG', optimize=True)
t = open(src, encoding='utf-8').read()
if '<office:binary-data>' not in t:
    t = t.replace('<draw:image xlink:href="file:///usr/share/forceos/images/fo-stellantis.png" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/>',
                  '<draw:image><office:binary-data>' + base64.b64encode(b.getvalue()).decode() + '</office:binary-data></draw:image>')
open(out, 'w', encoding='utf-8').write(t)
PY
soffice --headless --convert-to odt lettre.fodt >/dev/null 2>&1
mv lettre.odt "Lettre à la direction FO.odt"

# 3. Grille de salaires (formules, puis impression paysage sur une page)
python3 -I "$ICI/grille_salaires.py" grille.fods >/dev/null
soffice --headless --convert-to ods grille.fods >/dev/null 2>&1
mv grille.ods "Grille de salaires FO.ods"
python3 -I "$ICI/mise-en-page-ods.py" "Grille de salaires FO.ods" >/dev/null

# 4. Contrôle : chaque modèle doit s'exporter en PDF d'une page avec du texte
for f in "Lettre à la direction FO.odt" "Grille de salaires FO.ods"; do
  soffice --headless --convert-to pdf "$f" >/dev/null 2>&1
  pdf="${f%.*}.pdf"
  [[ $(pdfinfo "$pdf" | awk '/^Pages/ {print $2}') == 1 && $(pdftotext "$pdf" - | wc -w) -gt 50 ]] \
    || { echo "Contrôle échoué : $f" >&2; exit 1; }
done

install -m 0644 "Tract A5 Force Ouvrière.sla" "Lettre à la direction FO.odt" "Grille de salaires FO.ods" "$DEST/"
echo "Modèles prêts dans $DEST"
