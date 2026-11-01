# -*- coding: utf-8 -*-
# Fabrication du modèle de tract A5 Force Ouvrière dans Scribus (Florian Bigotte)
# Lancement : xvfb-run scribus -g -ns -py tract_scribus.py <sortie.sla> <apercu.pdf> <logo.png>
import sys
import scribus as s

SORTIE, APERCU, LOGO = sys.argv[1], sys.argv[2], sys.argv[3]
L, H = 148.0, 210.0          # A5 portrait, en millimètres
M = 10.0                     # marges

s.newDocument((L, H), (M, M, M, M), s.PORTRAIT, 1, s.UNIT_MILLIMETERS,
              s.NOFACINGPAGES, s.FIRSTPAGERIGHT, 1)
s.defineColorRGB("FO Bleu", 0, 43, 127)
s.defineColorRGB("FO Rouge", 226, 0, 26)
s.defineColorRGB("FO Fond", 243, 245, 250)

POLICE = "Carlito Regular"
GRAS = "Carlito Bold"
ITAL = "Carlito Italic"

def texte(x, y, w, h, contenu, police=POLICE, taille=10, couleur="Black", nom=None, align=s.ALIGN_LEFT, interligne=None):
    t = s.createText(x, y, w, h, nom)
    s.setText(contenu, t)
    s.setFont(police, t)
    s.setFontSize(taille, t)
    s.setTextColor(couleur, t)
    s.setTextAlignment(align, t)
    if interligne:
        s.setLineSpacing(interligne, t)
    s.setTextDistances(0, 0, 0, 0, t)
    return t

# --- Bandeau d'en-tête : fond clair, logo, nom de la section, filet rouge ----
bandeau = s.createRect(0, 0, L, 38, "Bandeau")
s.setFillColor("FO Fond", bandeau); s.setLineColor("None", bandeau)
logo = s.createImage(M, 5, 28, 28, "Logo")
s.loadImage(LOGO, logo)
s.setScaleImageToFrame(True, True, logo)
texte(M + 32, 9, L - 2 * M - 32, 8, "SECTION SYNDICALE FORCE OUVRIÈRE", GRAS, 11, "FO Bleu", "Section")
texte(M + 32, 18, L - 2 * M - 32, 6, "Stellantis Valenciennes", POLICE, 10, "FO Bleu", "Site")
texte(M + 32, 25, L - 2 * M - 32, 6, "Tract n° 000 du JJ/MM/AAAA", ITAL, 8.5, "Black", "Numero")
filet = s.createRect(0, 38, L, 1.6, "Filet")
s.setFillColor("FO Rouge", filet); s.setLineColor("None", filet)

# --- Titre et chapeau ------------------------------------------------------
texte(M, 45, L - 2 * M, 24, "TITRE DU TRACT EN DEUX LIGNES MAXIMUM", GRAS, 22, "FO Bleu", "Titre", interligne=24)
texte(M, 71, L - 2 * M, 16,
      "Chapeau : en deux ou trois phrases, l'essentiel du message. "
      "Ce que la direction annonce, ce que FO en pense, ce que nous demandons.",
      ITAL, 10.5, "Black", "Chapeau", interligne=13)

# --- Corps sur deux colonnes ----------------------------------------------
corps = texte(M, 90, L - 2 * M, 62,
      "Les faits. Exposez la situation de façon précise et vérifiable : dates, chiffres, "
      "décisions annoncées en CSE ou en réunion. Citez vos sources.\n\n"
      "La position de FO. Expliquez en quoi la mesure touche les salariés, et pourquoi "
      "Force Ouvrière s'y oppose ou la soutient. Restez factuel : aucune attaque "
      "personnelle, aucune information confidentielle de l'entreprise.\n\n"
      "Ce que nous avons fait. Interventions en CSE, courriers à la direction, "
      "rencontres avec les salariés concernés.",
      POLICE, 9.5, "Black", "Corps", interligne=12)

# --- Encadré des revendications ------------------------------------------
cadre = s.createRect(M, 155, L - 2 * M, 31, "Encadre")
s.setFillColor("None", cadre); s.setLineColor("FO Rouge", cadre); s.setLineWidth(1.2, cadre)
texte(M + 4, 158, L - 2 * M - 8, 6, "FO REVENDIQUE", GRAS, 11, "FO Rouge", "TitreEncadre")
texte(M + 4, 165, L - 2 * M - 8, 19,
      "• Première revendication, courte et chiffrée\n"
      "• Deuxième revendication\n"
      "• Troisième revendication",
      POLICE, 9.5, "Black", "Revendications", interligne=12)

# --- Pied : appel à l'action et contact ------------------------------------
pied = s.createRect(0, 191, L, 19, "Pied")
s.setFillColor("FO Bleu", pied); s.setLineColor("None", pied)
texte(M, 194, L - 2 * M, 6, "Ensemble, + FOrts ! Contactez vos délégués Force Ouvrière.", GRAS, 10.5, "White", "Appel", s.ALIGN_CENTERED)
texte(M, 201.5, L - 2 * M, 5, "Permanences au local syndical sur rendez-vous · Ne pas jeter sur la voie publique",
      POLICE, 7.5, "White", "Contact", s.ALIGN_CENTERED)

s.saveDocAs(SORTIE)
pdf = s.PDFfile()
pdf.file = APERCU
pdf.save()
s.closeDoc()
