#!/usr/bin/env python3
"""
ForceOS : jeu d'icônes « verre » du bureau
Conçu et développé par Florian Bigotte pour Force Ouvrière

Chaque icône est une tuile de verre arrondie : fond dégradé dans une teinte
de la charte FO, reflet dépoli sur la moitié haute, liseré lumineux, ombre
portée douce, et un pictogramme blanc au trait. Le script écrit les SVG dans
le thème d'icônes hicolor et les PNG aux tailles utilisées par le bureau.

    python3 icones_verre.py <dossier includes.chroot>
"""
import subprocess
import sys
from pathlib import Path

# Teintes : bleu FO, bleu nuit, indigo (IA), rouge FO (urgence)
TEINTES = {
    "bleu":   ("#2F6BEA", "#002B7F"),
    "nuit":   ("#3A4A86", "#101C45"),
    "indigo": ("#6C5CE7", "#2B1C8F"),
    "rouge":  ("#FF4D5E", "#B00014"),
    "vert":   ("#1FB58F", "#0B6B55"),
}

# Pictogrammes au trait, dessinés sur une grille de 24 unités
GLYPHES = {
    "gestion": ('bleu', '''
        <rect x="3.5" y="4.5" width="17" height="15" rx="3"/>
        <path d="M3.5 9h17"/>
        <path d="M7.5 13h4M7.5 16h7"/>
        <circle cx="16.5" cy="14.5" r="2.2"/>'''),
    "coffre": ('nuit', '''
        <rect x="3.5" y="4" width="17" height="15" rx="2.5"/>
        <circle cx="12" cy="11.5" r="3.6"/>
        <path d="M12 9.6v1.9l1.3 1.1"/>
        <path d="M6.5 19v1.6M17.5 19v1.6"/>'''),
    "ia": ('indigo', '''
        <path d="M7 3.5h7l4 4v13H7z"/>
        <path d="M14 3.5v4h4"/>
        <path d="M10.2 13.2 11 11l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8z"/>'''),
    "bibliotheque": ('bleu', '''
        <path d="M4.5 4.5h3.5v15H4.5zM9 4.5h3.5v15H9z"/>
        <path d="m14.2 5.6 3.3-.9 3.4 13.6-3.3.9z"/>
        <path d="M4.5 8h3.5M9 8h3.5"/>'''),
    "presentation": ('nuit', '''
        <rect x="3" y="4" width="18" height="11.5" rx="2"/>
        <path d="M12 15.5V20M8.5 20.5h7"/>
        <path d="m10.5 7.7 4.2 2.1-4.2 2.1z" fill="#fff"/>'''),
    "support": ('vert', '''
        <path d="M5 13.5v-1.5a7 7 0 0 1 14 0v1.5"/>
        <rect x="3.5" y="13" width="3.6" height="5.5" rx="1.4"/>
        <rect x="16.9" y="13" width="3.6" height="5.5" rx="1.4"/>
        <path d="M18.7 18.5c0 1.5-1.6 2.5-4.2 2.5H12.5"/>'''),
    "verrouillage": ('rouge', '''
        <rect x="5" y="10.5" width="14" height="10" rx="2.5"/>
        <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>
        <path d="M12 14.4v2.6"/>'''),
    "usb": ('nuit', '''
        <rect x="7.5" y="9" width="9" height="12" rx="2"/>
        <path d="M9 9V3.5h6V9"/>
        <path d="M10.6 5.6h.01M13.4 5.6h.01"/>
        <path d="M12 12.5v4.5"/>'''),
    "bureautique": ('bleu', '''
        <path d="M5 3.5h8l4 4V13"/>
        <path d="M13 3.5v4h4"/>
        <path d="M5 3.5v17h6"/>
        <path d="M8 10h5M8 13.5h3"/>
        <path d="m14.5 20.5 5.6-5.6a1.5 1.5 0 0 0-2.1-2.1l-5.6 5.6-.6 2.7z"/>'''),
    "courriel": ('indigo', '''
        <rect x="3" y="5.5" width="18" height="13" rx="2.5"/>
        <path d="m3.8 7 8.2 6 8.2-6"/>'''),
    "apropos": ('nuit', '''
        <circle cx="12" cy="12" r="8.5"/>
        <path d="M12 11v5.5"/>
        <path d="M12 7.6v.01"/>'''),
    "reglages": ('nuit', '''
        <rect x="3.5" y="6" width="17" height="5" rx="2.5"/>
        <circle cx="15.5" cy="8.5" r="1.4" fill="#fff"/>
        <rect x="3.5" y="13" width="17" height="5" rx="2.5"/>
        <circle cx="8.5" cy="15.5" r="1.4" fill="#fff"/>'''),
    "bouclier": ('vert', '''
        <path d="M12 3.2 19 6v5.6c0 4.3-3 7.6-7 9.2-4-1.6-7-4.9-7-9.2V6z"/>
        <path d="m8.8 12.2 2.2 2.2 4.3-4.4"/>'''),
    "wifi": ('indigo', '''
        <path d="M2.8 9.2a13.5 13.5 0 0 1 18.4 0"/>
        <path d="M5.8 12.6a9.2 9.2 0 0 1 12.4 0"/>
        <path d="M8.9 16a4.8 4.8 0 0 1 6.2 0"/>
        <path d="M12 19.3h.01"/>'''),
    "documents": ('bleu', '''
        <path d="M3.5 7a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>
        <path d="M3.5 10h17"/>'''),
}

TAILLES = (16, 22, 24, 32, 48, 64, 96, 128, 256)


def tuile(nom: str, teinte: str, glyphe: str) -> str:
    haut, bas = TEINTES[teinte]
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <!-- ForceOS : icône « {nom} » (Florian Bigotte pour Force Ouvrière) -->
  <defs>
    <linearGradient id="fond" x1="0" y1="0" x2="0.35" y2="1">
      <stop offset="0" stop-color="{haut}"/>
      <stop offset="1" stop-color="{bas}"/>
    </linearGradient>
    <linearGradient id="reflet" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fff" stop-opacity=".55"/>
      <stop offset="1" stop-color="#fff" stop-opacity=".04"/>
    </linearGradient>
    <linearGradient id="lisere" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fff" stop-opacity=".75"/>
      <stop offset=".5" stop-color="#fff" stop-opacity=".12"/>
      <stop offset="1" stop-color="#fff" stop-opacity=".35"/>
    </linearGradient>
    <radialGradient id="lueur" cx=".5" cy="1.05" r=".7">
      <stop offset="0" stop-color="#fff" stop-opacity=".28"/>
      <stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </radialGradient>
    <filter id="ombre" x="-20%" y="-20%" width="140%" height="150%">
      <feGaussianBlur in="SourceAlpha" stdDeviation="7"/>
      <feOffset dy="7"/>
      <feComponentTransfer><feFuncA type="linear" slope=".38"/></feComponentTransfer>
      <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <filter id="relief" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur in="SourceAlpha" stdDeviation="1.2"/>
      <feOffset dy="2"/>
      <feComponentTransfer><feFuncA type="linear" slope=".35"/></feComponentTransfer>
      <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <clipPath id="forme"><rect x="20" y="16" width="216" height="216" rx="54"/></clipPath>
  </defs>
  <g filter="url(#ombre)">
    <rect x="20" y="16" width="216" height="216" rx="54" fill="url(#fond)"/>
    <g clip-path="url(#forme)">
      <rect x="20" y="16" width="216" height="216" fill="url(#lueur)"/>
      <path d="M20 16h216v86c-64 26-152 26-216 0z" fill="url(#reflet)"/>
    </g>
    <rect x="21.5" y="17.5" width="213" height="213" rx="52.5" fill="none" stroke="url(#lisere)" stroke-width="3"/>
  </g>
  <g filter="url(#relief)" transform="translate(56 50) scale(6)" fill="none" stroke="#fff"
     stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round">{glyphe}
  </g>
</svg>
'''


def main(cible: Path) -> None:
    theme = cible / "usr/share/icons/hicolor"
    for nom, (teinte, glyphe) in GLYPHES.items():
        svg = tuile(nom, teinte, glyphe)
        nom_icone = f"forceos-{nom}"
        scalable = theme / "scalable/apps" / f"{nom_icone}.svg"
        scalable.parent.mkdir(parents=True, exist_ok=True)
        scalable.write_text(svg, encoding="utf-8")
        for t in TAILLES:
            png = theme / f"{t}x{t}/apps/{nom_icone}.png"
            png.parent.mkdir(parents=True, exist_ok=True)
            subprocess.run(["rsvg-convert", "-w", str(t), "-h", str(t), "-o", str(png), str(scalable)], check=True)
    print(f"{len(GLYPHES)} icônes écrites dans {theme}")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(Path(sys.argv[1]))
