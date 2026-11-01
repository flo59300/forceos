"""ForceOS : impression en paysage, ajustée à une page, d'un tableur .ods (Florian Bigotte).
La mise en page est écrite dans la page maîtresse par défaut, seule méthode qui
survit à l'enregistrement par LibreOffice. Usage : python3 -I mise-en-page-ods.py <fichier.ods>"""
import re, sys, zipfile, shutil, os
src = sys.argv[1]; tmp = src + '.tmp'
with zipfile.ZipFile(src) as zin, zipfile.ZipFile(tmp, 'w') as zout:
    for item in zin.infolist():
        data = zin.read(item.filename)
        if item.filename == 'styles.xml':
            t = data.decode('utf-8')
            m = re.search(r'<style:master-page style:name="Default" style:page-layout-name="([^"]+)"', t)
            assert m, 'page maîtresse par défaut introuvable'
            pl = m.group(1)
            avant = f'<style:page-layout style:name="{pl}"><style:page-layout-properties '
            assert t.count(avant) == 1
            t = t.replace(avant, avant + 'fo:page-width="29.7cm" fo:page-height="21cm" style:print-orientation="landscape" '
                          'style:scale-to-pages="1" fo:margin-top="1.2cm" fo:margin-bottom="1.2cm" '
                          'fo:margin-left="1.2cm" fo:margin-right="1.2cm" ')
            data = t.encode('utf-8')
        comp = zipfile.ZIP_STORED if item.filename == 'mimetype' else zipfile.ZIP_DEFLATED
        zout.writestr(item, data, compress_type=comp)
shutil.move(tmp, src); print('mise en page paysage appliquée :', os.path.basename(src))
