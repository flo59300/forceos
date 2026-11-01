#!/usr/bin/env python3
"""
ForceOS : intégration d'une version de FO Gestion 3 dans l'application Electron
Conçu et développé par Florian Bigotte pour Force Ouvrière

Usage : python3 -I integrer-fo-gestion.py <dossier fo-gestion-3> <dossier www de forceos-app>

Copie FO Gestion 3 dans www/, puis applique les adaptations ForceOS :
  1. retrait de l'installeur web et du modèle .env (configuration par Electron) ;
  2. dossier writable/ déplacé dans le dossier privé du militant ;
  3. fichiers ForceOS ajoutés (bibliothèques, filtre, base initiale) ;
  4. filtre de neutralisation de la Plateforme enregistré.
Chaque modification vérifie que le code d'origine est bien celui attendu :
si une version future de FO Gestion le change, le script s'arrête au lieu
de produire une intégration douteuse.
"""
import shutil
import sys
from pathlib import Path

ICI = Path(__file__).resolve().parent
AJOUTS = ICI / "ajouts"          # fichiers ForceOS, à la même place que dans www/


def remplacer(fichier: Path, ancien: str, nouveau: str) -> None:
    texte = fichier.read_text(encoding="utf-8")
    if texte.count(ancien) != 1:
        sys.exit(f"ARRÊT : passage attendu introuvable ou ambigu dans {fichier}")
    fichier.write_text(texte.replace(ancien, nouveau), encoding="utf-8")


def main(source: Path, www: Path) -> None:
    if not (source / "app" / "Config" / "Routes.php").is_file():
        sys.exit("ARRÊT : le dossier source n'est pas FO Gestion 3.")
    if www.exists():
        shutil.rmtree(www)
    shutil.copytree(source, www, ignore=shutil.ignore_patterns(".env", "writable"))

    # 1. Installeur web et modèle de configuration retirés
    for inutile in ("public/installer.php", "env"):
        (www / inutile).unlink(missing_ok=True)

    # 2. writable/ propre à chaque militant (/opt est en lecture seule)
    # (contrôleur frontal pour les pages, spark pour les tâches planifiées)
    for point_entree in ("public/index.php", "spark"):
        remplacer(www / point_entree, "$paths = new Paths();\n", """$paths = new Paths();

// ForceOS : « writable » est propre à chaque militant et situé hors de /opt,
// qui est en lecture seule (Florian Bigotte)
if (($forceosDonnees = getenv('FORCEOS_DONNEES')) !== false && $forceosDonnees !== '') {
    $paths->writableDirectory = rtrim($forceosDonnees, '/') . '/writable';
}
""")

    # 3. Fichiers ForceOS
    for f in AJOUTS.rglob("*"):
        if f.is_file():
            cible = www / f.relative_to(AJOUTS)
            cible.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(f, cible)

    # 4. Filtre ForceOS : déclaré puis appliqué à toutes les requêtes
    filtres = www / "app/Config/Filters.php"
    remplacer(filtres, "'maintenance'   => \\App\\Filters\\MaintenanceFilter::class,\n",
              "'maintenance'   => \\App\\Filters\\MaintenanceFilter::class,\n"
              "        'forceos'       => \\App\\Filters\\ForceosPlateforme::class,\n")
    remplacer(filtres, "        'before' => [\n            // 'honeypot',\n",
              "        'before' => [\n            'forceos',\n            // 'honeypot',\n")
    # Points d'entrée internes : filtre à jeton, exemptés du CSRF (pas de session)
    remplacer(filtres, "        'forceos'       => \\App\\Filters\\ForceosPlateforme::class,\n",
              "        'forceos'       => \\App\\Filters\\ForceosPlateforme::class,\n"
              "        'interne'       => \\App\\Filters\\AccesInterne::class,\n")
    remplacer(filtres, "'csrf' => ['except' => ['booking.php', 'verify.php']],",
              "'csrf' => ['except' => ['booking.php', 'verify.php', 'interne/*']],")
    routes = www / "app/Config/Routes.php"
    routes.write_text(routes.read_text(encoding="utf-8").rstrip() + """

// ForceOS : points d'entrée internes, réservés au processus principal d'Electron
$routes->group('interne', ['filter' => 'interne', 'namespace' => 'App\\Controllers\\Interne'], static function (RouteCollection $i) {
    $i->post('migrer', 'Maintenance::migrer');
    $i->get('etat', 'Maintenance::etat');
    $i->post('premiere-configuration', 'Maintenance::premiereConfiguration');
    $i->post('instantane/restaurer', 'Maintenance::restaurerInstantane');
    $i->post('kb/synchroniser', 'BaseDocumentaire::synchroniser');
    $i->post('kb/rechercher', 'BaseDocumentaire::rechercher');
    $i->get('sauvegarde/export', 'Sauvegarde::exporter');
    $i->post('restauration/(debut|lot|fin|annuler)', 'Restauration::etape/$1');
});
""", encoding="utf-8")

    # 5. Fonctions ForceOS chargées automatiquement par CodeIgniter
    remplacer(www / "app/Config/Autoload.php", "    public $files = [];\n", """    public $files = [
        APPPATH . 'Libraries/Forceos/restauration.php',
        APPPATH . 'Libraries/Forceos/installation.php',
        APPPATH . 'Libraries/Forceos/tickets.php',
    ];
""")

    # 6. Coordonnées des adhérents chiffrées à l'écriture, déchiffrées à la lecture
    cc = "\\App\\Libraries\\Forceos\\ChampsChiffres"
    membres = www / "app/Domain/Members.php"
    remplacer(membres, "$this->db->table('members')->insert($data + [",
              f"$this->db->table('members')->insert({cc}::chiffrer($data) + [")
    remplacer(membres, "$this->db->table('members')->where(['id' => $id, 'site_id' => $this->siteId])->update($data);",
              f"$this->db->table('members')->where(['id' => $id, 'site_id' => $this->siteId])->update({cc}::chiffrer($data));")
    remplacer(membres, "        return $q->get()->getRowArray();\n    }\n\n    /**",
              f"        return {cc}::dechiffrer($q->get()->getRowArray());\n    }}\n\n    /**")

    imports = www / "app/Domain/MemberImport.php"
    remplacer(imports, "$this->db->table('members')->insert(array_filter($d, static fn ($v) => $v !== null) + [",
              f"$this->db->table('members')->insert({cc}::chiffrer(array_filter($d, static fn ($v) => $v !== null)) + [")
    remplacer(imports, "return array_merge([$head], self::safe($this->db->query(\n            \"SELECT m.id, m.matricule",
              f"return array_merge([$head], self::safe({cc}::dechiffrerTout($this->db->query(\n            \"SELECT m.id, m.matricule")
    remplacer(imports, "WHERE m.site_id = ? ORDER BY m.is_radiated, m.last_name\", [$this->siteId]\n        )->getResultArray()));",
              "WHERE m.site_id = ? ORDER BY m.is_radiated, m.last_name\", [$this->siteId]\n        )->getResultArray())));")

    listes = www / "app/Domain/ElectoralLists.php"
    remplacer(listes, "        $this->db->transStart();\n        $this->db->table('election_listes_electorales')->where('pap_id', $pap['id'])->delete();",
              f"        $members = {cc}::dechiffrerTout($members);   // ForceOS : date de naissance\n"
              "        $this->db->transStart();\n        $this->db->table('election_listes_electorales')->where('pap_id', $pap['id'])->delete();")

    # 7. Hors ligne : dernier cours et derniers articles connus conservés 30 jours
    trente_jours = "2592000"
    remplacer(www / "app/Domain/Stocks.php", """            $q = self::quote($sym);
            if ($q) {""", f"""            $q = self::quote($sym);
            // ForceOS : dernier cours connu conservé, affiché hors ligne avec sa date
            $dernierCle = 'stocks_dernier_' . md5($sym);
            if ($q && self::$fake === null) {{
                cache()->save($dernierCle, $q, {trente_jours});
            }} elseif (! $q && self::$fake === null && is_array($dernier = cache($dernierCle))) {{
                $q = $dernier + ['horsLigne' => true];
            }}
            if ($q) {{""")
    remplacer(www / "app/Domain/Feeds.php",
              "                $got = $xml === null ? ['error' => true] : ['items' => self::parse($xml)];\n",
              f"""                // ForceOS : derniers articles connus conservés, consultables hors ligne
                $dernierCle = 'rss_dernier_' . md5($f['url']);
                if ($xml !== null) {{
                    $got = ['items' => self::parse($xml)];
                    cache()->save($dernierCle, $got, {trente_jours});
                }} else {{
                    $dernier = cache($dernierCle);
                    $got = is_array($dernier) ? $dernier + ['horsLigne' => true] : ['error' => true];
                }}
""")

    # 8. Page « Sauvegarde chiffrée » (secrétaire et trésorier) et assistant IA
    routes.write_text(routes.read_text(encoding="utf-8").rstrip() + """

// ForceOS : sauvegarde chiffrée (secrétaire et trésorier)
$routes->group('forceos', ['filter' => ['auth', 'role:secretaire,tresorier']], static function (RouteCollection $f) {
    $f->get('sauvegarde', 'Forceos::sauvegarde');
    $f->post('ticket', 'Forceos::ticket');
});
""", encoding="utf-8")
    navigation = www / "app/Libraries/Navigation.php"
    remplacer(navigation,
              "                ['label' => 'Fil d\\'actualité', 'url' => '/actualites', 'roles' => self::SITE, 'desc' => 'Flux RSS suivis'],\n",
              "                ['label' => 'Fil d\\'actualité', 'url' => '/actualites', 'roles' => self::SITE, 'desc' => 'Flux RSS suivis'],\n"
              "                ['label' => 'Assistant IA', 'url' => '/modules/assistant/index.html', 'roles' => self::SITE, 'desc' => 'Accords, tracts, comptes rendus audio (hors ligne)'],\n")
    remplacer(navigation,
              "                ['label' => 'Import et export', 'url' => '/administration/import', 'roles' => ['secretaire'], 'desc' => 'Fichiers CSV'],\n",
              "                ['label' => 'Import et export', 'url' => '/administration/import', 'roles' => ['secretaire'], 'desc' => 'Fichiers CSV'],\n"
              "                ['label' => 'Sauvegarde chiffrée', 'url' => '/forceos/sauvegarde', 'roles' => ['secretaire', 'tresorier'], 'desc' => 'Sauvegarder ou restaurer toutes les données'],\n")

    print(f"FO Gestion intégrée dans {www}")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(Path(sys.argv[1]).resolve(), Path(sys.argv[2]).resolve())
