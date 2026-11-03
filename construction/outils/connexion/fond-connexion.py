#!/usr/bin/env python3
"""
ForceOS : fond de l'écran de connexion avec la version de l'image
Conçu et développé par Florian Bigotte pour Force Ouvrière

Écrit la version et la date de construction en blanc, dans le bandeau bleu
du bas du fond d'écran (à gauche, la signature restant à droite).

    python3 fond-connexion.py <fond source> <fond produit> <texte> <police TTF>
"""
import sys
from PIL import Image, ImageDraw, ImageFont


def main(source: str, cible: str, texte: str, police: str) -> None:
    image = Image.open(source).convert("RGB")
    largeur, hauteur = image.size
    taille = round(hauteur * 0.024)                     # 26 px pour 1080 lignes
    fonte = ImageFont.truetype(police, taille)
    dessin = ImageDraw.Draw(image)
    marge = round(largeur * 0.03)
    y = hauteur - round(hauteur * 0.040) - taille
    dessin.text((marge, y), texte, font=fonte, fill=(255, 255, 255))
    image.save(cible, optimize=True)


if __name__ == "__main__":
    if len(sys.argv) != 5:
        sys.exit(__doc__)
    main(*sys.argv[1:])
