# -*- coding: utf-8 -*-
"""ForceOS : génère le modèle de tableur « Grille de salaires FO » (OpenDocument plat).
Conçu par Florian Bigotte pour Force Ouvrière.
Usage : python3 -I grille_salaires.py <sortie.fods>
La mise en page (paysage, une page) est appliquée après conversion par mise-en-page-ods.py."""
import sys
from xml.sax.saxutils import escape

SORTIE = sys.argv[1]

# Valeurs d'exemple fictives, à remplacer par la grille réelle
LIGNES = [
    ("Groupe A, classe 1", 1802.00, 1830.00),
    ("Groupe A, classe 2", 1825.00, 1850.00),
    ("Groupe B, classe 3", 1860.00, 1910.00),
    ("Groupe B, classe 4", 1910.00, 1985.00),
    ("Groupe C, classe 5", 1980.00, 2060.00),
    ("Groupe C, classe 6", 2080.00, 2150.00),
    ("Groupe D, classe 7", 2210.00, 2290.00),
    ("Groupe D, classe 8", 2360.00, 2340.00),
]
PREMIERE = 8                       # première ligne de données (numérotation tableur)
DERNIERE = PREMIERE + len(LIGNES) - 1

def cel_texte(v, style="Texte"):
    return f'<table:table-cell table:style-name="{style}" office:value-type="string"><text:p>{escape(v)}</text:p></table:table-cell>'

def cel_nombre(v, style="Euro"):
    return f'<table:table-cell table:style-name="{style}" office:value-type="float" office:value="{v}"><text:p>{v}</text:p></table:table-cell>'

def cel_formule(f, style="Euro", type_="float"):
    return f'<table:table-cell table:style-name="{style}" table:formula="of:={escape(f, {chr(34): "&quot;"})}" office:value-type="{type_}"/>'

def vide(n=1):
    return '<table:table-cell/>' * n

lignes_xml = []
for i, (emploi, mini, base) in enumerate(LIGNES):
    r = PREMIERE + i
    lignes_xml.append(
        '<table:table-row>'
        + cel_texte(emploi, "Saisie")
        + cel_nombre(mini, "EuroSaisie")
        + cel_nombre(base, "EuroSaisie")
        + cel_formule(f"[.C{r}]-[.B{r}]", "EuroEcart")
        + cel_formule(f"IF([.B{r}]>0;[.D{r}]/[.B{r}];\"\")", "Pourcent", "percentage")
        + cel_formule(f"ROUND([.C{r}]*(1+[.$B$3]);2)", "Euro")
        + cel_formule(f"[.F{r}]-[.C{r}]", "Euro")
        + cel_formule(f"[.G{r}]*[.$B$4]", "Euro")
        + '</table:table-row>')

entetes = ["Emploi / classification", "Salaire minimum conventionnel", "Salaire de base actuel",
           "Écart au minimum", "Écart (%)", "Nouveau salaire de base", "Gain mensuel brut", "Gain annuel brut"]

doc = f'''<?xml version="1.0" encoding="UTF-8"?>
<!-- ForceOS : modèle de grille de salaires (Florian Bigotte pour Force Ouvrière) -->
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  xmlns:of="urn:oasis:names:tc:opendocument:xmlns:of:1.2"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3" office:mimetype="application/vnd.oasis.opendocument.spreadsheet">
 <office:meta><dc:title>Grille de salaires FO</dc:title></office:meta>
 <office:font-face-decls>
  <style:font-face style:name="Carlito" svg:font-family="Carlito" style:font-family-generic="swiss"/>
 </office:font-face-decls>
 <office:styles>
  <number:currency-style style:name="N_euro_pos" number:language="fr" number:country="FR">
   <number:number number:decimal-places="2" number:min-decimal-places="2" number:min-integer-digits="1" number:grouping="true"/>
   <number:text> </number:text><number:currency-symbol number:language="fr" number:country="FR">€</number:currency-symbol>
  </number:currency-style>
  <number:currency-style style:name="N_euro" number:language="fr" number:country="FR">
   <style:text-properties fo:color="#FF0000"/>
   <number:text>-</number:text>
   <number:number number:decimal-places="2" number:min-decimal-places="2" number:min-integer-digits="1" number:grouping="true"/>
   <number:text> </number:text><number:currency-symbol number:language="fr" number:country="FR">€</number:currency-symbol>
   <style:map style:condition="value()&gt;=0" style:apply-style-name="N_euro_pos"/>
  </number:currency-style>
  <number:percentage-style style:name="N_pct" number:language="fr" number:country="FR">
   <number:number number:decimal-places="2" number:min-decimal-places="2" number:min-integer-digits="1"/><number:text> %</number:text>
  </number:percentage-style>
  <style:default-style style:family="table-cell">
   <style:text-properties style:font-name="Carlito" fo:font-size="11pt" fo:language="fr" fo:country="FR"/>
  </style:default-style>
  <style:style style:name="Texte" style:family="table-cell"/>
  <style:style style:name="Titre" style:family="table-cell">
   <style:text-properties fo:font-size="16pt" fo:font-weight="bold" fo:color="#002B7F"/>
  </style:style>
  <style:style style:name="Note" style:family="table-cell">
   <style:text-properties fo:font-style="italic" fo:color="#4A5670" fo:font-size="9.5pt"/>
  </style:style>
  <style:style style:name="Libelle" style:family="table-cell">
   <style:text-properties fo:font-weight="bold"/>
  </style:style>
  <style:style style:name="Parametre" style:family="table-cell" style:data-style-name="N_pct">
   <style:table-cell-properties fo:background-color="#FFF4D6" fo:border="0.5pt solid #002B7F"/>
   <style:text-properties fo:font-weight="bold"/>
  </style:style>
  <style:style style:name="ParametreNombre" style:family="table-cell">
   <style:table-cell-properties fo:background-color="#FFF4D6" fo:border="0.5pt solid #002B7F"/>
   <style:text-properties fo:font-weight="bold"/>
  </style:style>
  <style:style style:name="Entete" style:family="table-cell">
   <style:table-cell-properties fo:background-color="#002B7F" fo:wrap-option="wrap" style:vertical-align="middle" fo:border-bottom="1.5pt solid #E2001A"/>
   <style:paragraph-properties fo:text-align="center"/>
   <style:text-properties fo:color="#FFFFFF" fo:font-weight="bold"/>
  </style:style>
  <style:style style:name="Saisie" style:family="table-cell">
   <style:table-cell-properties fo:background-color="#FFF4D6" fo:border="0.5pt solid #C9CFDB"/>
  </style:style>
  <style:style style:name="EuroSaisie" style:family="table-cell" style:data-style-name="N_euro">
   <style:table-cell-properties fo:background-color="#FFF4D6" fo:border="0.5pt solid #C9CFDB"/>
  </style:style>
  <style:style style:name="Euro" style:family="table-cell" style:data-style-name="N_euro">
   <style:table-cell-properties fo:border="0.5pt solid #C9CFDB"/>
  </style:style>
  <style:style style:name="EuroEcart" style:family="table-cell" style:data-style-name="N_euro">
   <style:table-cell-properties fo:border="0.5pt solid #C9CFDB"/>
   <style:text-properties fo:font-weight="bold"/>
  </style:style>
  <style:style style:name="Pourcent" style:family="table-cell" style:data-style-name="N_pct">
   <style:table-cell-properties fo:border="0.5pt solid #C9CFDB"/>
  </style:style>
  <style:style style:name="Total" style:family="table-cell" style:data-style-name="N_euro">
   <style:table-cell-properties fo:background-color="#F3F5FA" fo:border-top="1.5pt solid #002B7F"/>
   <style:text-properties fo:font-weight="bold"/>
  </style:style>
  <style:style style:name="TotalTexte" style:family="table-cell">
   <style:table-cell-properties fo:background-color="#F3F5FA" fo:border-top="1.5pt solid #002B7F"/>
   <style:text-properties fo:font-weight="bold"/>
  </style:style>
 </office:styles>
 <office:automatic-styles>
  <style:style style:name="colA" style:family="table-column"><style:table-column-properties style:column-width="7.4cm"/></style:style>
  <style:style style:name="colN" style:family="table-column"><style:table-column-properties style:column-width="3.3cm"/></style:style>
  <style:style style:name="ligEntete" style:family="table-row"><style:table-row-properties style:row-height="1.25cm"/></style:style>
 </office:automatic-styles>
 <office:body>
  <office:spreadsheet>
   <table:table table:name="Grille">
    <table:table-column table:style-name="colA"/>
    <table:table-column table:style-name="colN" table:number-columns-repeated="7"/>
    <table:table-row>{cel_texte("Grille de salaires : simulation d'augmentation", "Titre")}</table:table-row>
    <table:table-row>{cel_texte("Syndicat FO · cases jaunes à remplir, les autres se calculent seules", "Note")}</table:table-row>
    <table:table-row>{cel_texte("Augmentation générale négociée", "Libelle")}<table:table-cell table:style-name="Parametre" office:value-type="percentage" office:value="0.03"><text:p>3,00 %</text:p></table:table-cell></table:table-row>
    <table:table-row>{cel_texte("Nombre de mois de salaire par an", "Libelle")}<table:table-cell table:style-name="ParametreNombre" office:value-type="float" office:value="13"><text:p>13</text:p></table:table-cell></table:table-row>
    <table:table-row>{cel_texte("Valeurs d'exemple fictives : remplacez-les par les minima de la convention collective en vigueur et les salaires réels.", "Note")}</table:table-row>
    <table:table-row>{vide()}</table:table-row>
    <table:table-row table:style-name="ligEntete">{''.join(cel_texte(e, "Entete") for e in entetes)}</table:table-row>
    {''.join(lignes_xml)}
    <table:table-row>{cel_texte("Moyenne", "TotalTexte")}{cel_formule(f"AVERAGE([.B{PREMIERE}:.B{DERNIERE}])", "Total")}{cel_formule(f"AVERAGE([.C{PREMIERE}:.C{DERNIERE}])", "Total")}{cel_formule(f"AVERAGE([.D{PREMIERE}:.D{DERNIERE}])", "Total")}<table:table-cell table:style-name="TotalTexte"/>{cel_formule(f"AVERAGE([.F{PREMIERE}:.F{DERNIERE}])", "Total")}{cel_formule(f"AVERAGE([.G{PREMIERE}:.G{DERNIERE}])", "Total")}{cel_formule(f"AVERAGE([.H{PREMIERE}:.H{DERNIERE}])", "Total")}</table:table-row>
    <table:table-row>{cel_texte("Salaires sous le minimum conventionnel", "TotalTexte")}{cel_formule(f"COUNTIF([.D{PREMIERE}:.D{DERNIERE}];\"<0\")", "TotalTexte")}</table:table-row>
   </table:table>
  </office:spreadsheet>
 </office:body>
</office:document>
'''
open(SORTIE, 'w', encoding='utf-8').write(doc)
print('modèle écrit :', SORTIE)
