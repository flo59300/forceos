#!/usr/bin/env python3
"""
ForceOS : modèles de documents Force Ouvrière (LibreOffice Writer)
Conçu et développé par Florian Bigotte pour Force Ouvrière

Chaque modèle reprend la mise en page de « Lettre à la direction FO.odt »
(styles, logo, police Carlito, couleurs FO) avec un contenu propre. Les
passages à compléter sont en rouge italique entre crochets.

    python3 modeles-fo.py <dossier Modèles>
"""
import re
import sys
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape

SOURCE = "Lettre à la direction FO.odt"


def para(style: str, texte: str = "") -> str:
    """Paragraphe ; [passage] devient un champ à compléter (rouge italique),
    **passage** du gras."""
    t = escape(texte)
    t = re.sub(r"\*\*(.+?)\*\*", r'<text:span text:style-name="T1">\1</text:span>', t)
    t = re.sub(r"\[(.+?)\]", r'<text:span text:style-name="T2">[\1]</text:span>', t)
    return f'<text:p text:style-name="{style}">{t}</text:p>'


def entete(destinataire: list[str] | None = None) -> list[str]:
    lignes = [
        "<LOGO>Section syndicale Force Ouvrière",
        para("Standard", "Stellantis Valenciennes"),
        para("Standard", "Adresse du local syndical"),
        para("Filet", "Téléphone · adresse e-mail de la section"),
    ]
    if destinataire:
        lignes.append(para("P1", destinataire[0]))
        lignes += [para("Droite", l) for l in destinataire[1:]]
        lignes.append(para("Droite"))
    return lignes


PIED = para("Pied", "Section syndicale Force Ouvrière · Stellantis Valenciennes · Ensemble, + FOrts !")

MODELES = {
    "Compte rendu de réunion CSE FO.odt": entete() + [
        para("Objet", "**Compte rendu de la réunion du CSE du [JJ mois AAAA]**"),
        para("Standard", "**Type de réunion : **[ordinaire / extraordinaire / CSSCT]"),
        para("Standard", "**Élus Force Ouvrière présents : **[Prénom NOM, Prénom NOM]"),
        para("Standard", "**Direction : **[Prénom NOM, fonction]"),
        para("Standard"),
        para("Objet", "**1. [Premier point de l'ordre du jour]**"),
        para("Corps", "**Ce que la direction a présenté : **[résumé des informations et des chiffres donnés]"),
        para("Corps", "**Position de Force Ouvrière : **[questions posées, arguments, vote]"),
        para("Corps", "**Résultat : **[avis rendu, vote, réponse obtenue ou attendue, échéance]"),
        para("Objet", "**2. [Deuxième point de l'ordre du jour]**"),
        para("Corps", "**Ce que la direction a présenté : **[…]"),
        para("Corps", "**Position de Force Ouvrière : **[…]"),
        para("Corps", "**Résultat : **[…]"),
        para("Objet", "**Questions des salariés portées par FO**"),
        para("Corps", "[Question posée et réponse de la direction]"),
        para("Objet", "**Ce que nous suivons pour la prochaine réunion**"),
        para("Corps", "[Engagements de la direction, documents attendus, dates]"),
        para("Signature", "Vos élus Force Ouvrière restent à votre écoute,"),
        para("Droite", "[Prénom NOM, contact]"),
        PIED,
    ],
    "Signalement de danger grave et imminent CSSCT FO.odt": entete([
        "Monsieur le Directeur du site", "Stellantis Valenciennes", "Adresse de l'établissement",
        "Code postal Ville"]) + [
        para("Droite", "À Valenciennes, le [JJ mois AAAA] à [HH:MM]"),
        para("Mention", "Remise en main propre contre décharge"),
        para("Objet", "**Objet : **exercice du droit d'alerte pour danger grave et imminent"),
        para("Standard", "**Références : **articles L.2312-60 et L.4131-2 du Code du travail"),
        para("Standard"),
        para("Corps", "Monsieur le Directeur,"),
        para("Corps", "En qualité de membre [du CSE / de la CSSCT], je vous informe avoir constaté, "
                      "le [date] à [heure], une cause de danger grave et imminent, directement ou par "
                      "l'intermédiaire d'un salarié."),
        para("Corps", "**Poste ou lieu concerné : **[atelier, ligne, poste, équipement]"),
        para("Corps", "**Nature et cause du danger : **[description précise et factuelle]"),
        para("Corps", "**Salariés exposés : **[nombre, équipes, postes]"),
        para("Corps", "Conformément au Code du travail, cette alerte doit être consignée dans le registre "
                      "spécial des dangers graves et imminents. Je vous demande de procéder sans délai "
                      "à une enquête avec moi et de prendre les dispositions nécessaires pour y remédier."),
        para("Corps", "Je vous prie d'agréer, Monsieur le Directeur, l'expression de mes salutations distinguées."),
        para("Signature", "Pour la section syndicale Force Ouvrière,"),
        para("Droite", "[Prénom NOM]"),
        para("Droite", "[Mandat : membre du CSE, de la CSSCT]"),
        PIED,
    ],
    "Convocation à une réunion de section FO.odt": entete() + [
        para("Droite", "À Valenciennes, le [JJ mois AAAA]"),
        para("Objet", "**Objet : **convocation à la réunion de la section Force Ouvrière"),
        para("Standard"),
        para("Corps", "Chère adhérente, cher adhérent,"),
        para("Corps", "Tu es invité(e) à la réunion de la section syndicale Force Ouvrière qui se tiendra le "
                      "**[jour JJ mois AAAA] à [HH:MM]**, **[lieu : local syndical, salle]**."),
        para("Corps", "**Ordre du jour :**"),
        para("Corps", "1. [Compte rendu du dernier CSE]"),
        para("Corps", "2. [Négociations en cours]"),
        para("Corps", "3. [Questions diverses]"),
        para("Corps", "Ta présence compte : c'est ensemble que nous décidons des positions de la section. "
                      "En cas d'empêchement, merci de prévenir [Prénom NOM, téléphone]."),
        para("Signature", "Syndicalement,"),
        para("Droite", "[Prénom NOM]"),
        para("Droite", "[Secrétaire de la section]"),
        PIED,
    ],
    "Communiqué aux salariés FO.odt": entete() + [
        para("Objet", "**[TITRE DU COMMUNIQUÉ EN QUELQUES MOTS]**"),
        para("Droite", "Valenciennes, le [JJ mois AAAA]"),
        para("Standard"),
        para("Corps", "**Ce qui se passe : **[les faits, en deux ou trois phrases simples et datées]"),
        para("Corps", "**Ce que cela change pour vous : **[conséquences concrètes : salaire, horaires, "
                      "conditions de travail, emploi]"),
        para("Corps", "**Ce que Force Ouvrière demande : **[revendications claires]"),
        para("Corps", "**Ce que Force Ouvrière a obtenu : **[résultats, s'il y en a]"),
        para("Corps", "**Vos élus FO à votre écoute : **[noms, ateliers, équipes, téléphone]"),
        para("Signature", "Ensemble, + FOrts !"),
        PIED,
    ],
}


def main(dossier: Path) -> None:
    source = dossier / SOURCE
    with zipfile.ZipFile(source) as z:
        fichiers = {n: z.read(n) for n in z.namelist()}
    contenu = fichiers["content.xml"].decode("utf-8")
    debut = contenu.index('<text:p text:style-name="P2">')
    fin = contenu.index("</office:text>")
    premier = contenu[debut:contenu.index("</text:p>", debut)]
    logo = premier[premier.index("<draw:frame"):premier.index("</draw:frame>") + len("</draw:frame>")]
    for nom, corps in MODELES.items():
        xml = ""
        for p in corps:
            if p.startswith("<LOGO>"):
                xml += f'<text:p text:style-name="P2">{logo}{escape(p[6:])}</text:p>'
            else:
                xml += p
        nouveau = contenu[:debut] + xml + contenu[fin:]
        cible = dossier / nom
        with zipfile.ZipFile(cible, "w") as z:
            # « mimetype » en premier et non compressé (format OpenDocument)
            z.writestr(zipfile.ZipInfo("mimetype"), fichiers["mimetype"], compress_type=zipfile.ZIP_STORED)
            for n, d in fichiers.items():
                if n == "mimetype":
                    continue
                donnees = nouveau.encode("utf-8") if n == "content.xml" else d
                z.writestr(n, donnees, compress_type=zipfile.ZIP_DEFLATED)
        print(f"modèle écrit : {cible.name}")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(Path(sys.argv[1]))
