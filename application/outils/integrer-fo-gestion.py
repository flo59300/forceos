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

    # 9. Apparence ForceOS : pictogrammes supplémentaires et feuille « verre »
    glyphes = {
        'calendar': '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17"/><path d="M8 3v4M16 3v4"/><path d="M8 14h2M14 14h2M8 17h2"/>',
        'pin':      '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.4"/>',
        'megaphone':'<path d="M4 10v4a1 1 0 0 0 1 1h2l8 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M8 15.2 9.2 20h2.3"/><path d="M18.5 9.5a3.5 3.5 0 0 1 0 5"/>',
        'notebook': '<rect x="5" y="3" width="14" height="18" rx="2.5"/><path d="M5 7.5H3.5M5 12H3.5M5 16.5H3.5"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'gauge':    '<path d="M4.2 17a8.5 8.5 0 1 1 15.6 0"/><path d="m12 13 3.8-4.2"/><circle cx="12" cy="13.2" r="1.4"/><path d="M6.6 11.3h.01M12 6.8h.01M17.4 11.3h.01"/>',
        'swaphand': '<path d="M4 9h12l-3.5-3.5"/><path d="M20 15H8l3.5 3.5"/>',
        'folder':   '<path d="M3.5 7a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z"/><path d="M3.5 10.5h17"/>',
        'rss':      '<path d="M5 11a8 8 0 0 1 8 8"/><path d="M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.4"/>',
        'sparkles': '<path d="M10 4.5 11.4 9l4.6 1.5-4.6 1.5L10 16.5 8.6 12 4 10.5 8.6 9Z"/><path d="M17.5 14.5l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7Z"/><path d="M17 3.5v3M15.5 5h3"/>',
        'ballot':   '<path d="M4 13.5 6.5 10h11l2.5 3.5V20H4Z"/><path d="M4 13.5h16"/><path d="m9.5 7.2 2 2 4-4.2"/>',
        'list':     '<path d="M9 6.5h11M9 12h11M9 17.5h11"/><path d="m3.5 6.5 1 1 2-2M3.5 12l1 1 2-2M3.5 17.5l1 1 2-2"/>',
        'cog':      '<circle cx="12" cy="12" r="3"/><path d="M12 2.8v2.4M12 18.8v2.4M2.8 12h2.4M18.8 12h2.4M5.5 5.5l1.7 1.7M16.8 16.8l1.7 1.7M5.5 18.5l1.7-1.7M16.8 7.2l1.7-1.7"/><circle cx="12" cy="12" r="6.6"/>',
        'coins':    '<ellipse cx="9" cy="7" rx="5.5" ry="2.5"/><path d="M3.5 7v4c0 1.4 2.5 2.5 5.5 2.5s5.5-1.1 5.5-2.5V7"/><path d="M9.5 15.8c.9 1.2 3 2 5.5 2 3 0 5.5-1.1 5.5-2.5v-4c0-1.3-2-2.3-4.6-2.5"/>',
        'box':      '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5"/><path d="M12 12v9"/>',
        'report':   '<path d="M6 3.5h8.5l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M14 3.5v4.5h4.5"/><path d="M8.5 17v-3M12 17v-5M15.5 17v-2"/>',
        'usercog':  '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6 1.4 0 2.6.3 3.6.9"/><circle cx="17.5" cy="17" r="2.2"/><path d="M17.5 13.3v1.5M17.5 19.2v1.5M13.8 17h1.5M19.7 17h1.5"/>',
        'sliders':  '<path d="M4 6.5h9M17 6.5h3M4 12h3M11 12h9M4 17.5h11M19 17.5h1"/><circle cx="15" cy="6.5" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="17.5" r="2"/>',
        'alarm':    '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.5 1.8"/><path d="M4 5.5 6.5 3M20 5.5 17.5 3"/>',
        'upload':   '<path d="M12 15V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/>',
        'lock':     '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/><path d="M12 14.4v2.6"/>',
        'user':     '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c0-4 3.4-6.5 7.5-6.5s7.5 2.5 7.5 6.5"/>',
        'help':     '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 1 1 3.4 2.4c-.6.3-1 .8-1 1.5v.6"/><path d="M12 17h.01"/>',
        'layers':   '<path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'star':     '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8-4.3-4.1 5.9-.9Z"/>',
    }
    remplacer(www / "app/Helpers/fo_helper.php", "        $p = [\n",
              "        $p = [\n" + "".join(f"            '{n}' => '{s}',\n" for n, s in glyphes.items()))
    remplacer(www / "app/Views/layouts/hub.php",
              "    <link rel=\"stylesheet\" href=\"<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>\">\n",
              "    <link rel=\"stylesheet\" href=\"<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>\">\n"
              "    <link rel=\"stylesheet\" href=\"<?= base_url('modules/forceos/verre.css?v=' . FO_ASSET_VERSION) ?>\">\n")

    # 10. Vocabulaire : « syndicat FO » plutôt que « section syndicale »
    vocabulaire = {
        "app/Views/guide/legal.php": [
            ("de la section syndicale Force Ouvrière.", "du syndicat FO."),
            ("aux intérêts de la section", "aux intérêts du syndicat FO"),
            ("La section ne garantit pas", "Le syndicat FO ne garantit pas"),
            ("Force Ouvrière, section syndicale Stellantis.", "Syndicat FO Stellantis."),
            ("le secrétaire de section.", "le secrétaire du syndicat."),
            ("responsable technique de la section", "responsable technique du syndicat"),
            ("coordonnées internes de la section FO", "coordonnées internes du syndicat FO"),
            ("la propriété de la section syndicale Force Ouvrière", "la propriété du syndicat FO"),
            ("La section syndicale Force Ouvrière de Stellantis est responsable", "Le syndicat FO de Stellantis est responsable"),
            ("responsables habilités de la section", "responsables habilités du syndicat"),
        ],
        "app/Views/actualites/index.php": [
            ("suivis par la section.", "suivis par le syndicat."),
            ("suivi par la section.", "suivi par le syndicat."),
        ],
        "app/Views/platform/settings.php": [("Conditions propres à la section", "Conditions propres au syndicat")],
        "app/Views/agenda/form.php": [("Réunion de section", "Réunion du syndicat")],
    }
    for relatif, paires in vocabulaire.items():
        f = www / relatif
        texte = f.read_text(encoding="utf-8")
        for ancien, nouveau in paires:
            if ancien not in texte:
                sys.exit(f"ARRÊT : « {ancien} » introuvable dans {f}")
            texte = texte.replace(ancien, nouveau)
        f.write_text(texte, encoding="utf-8")
    # « secrétaire de section » partout dans les écrans
    for f in (www / "app/Views").rglob("*.php"):
        texte = f.read_text(encoding="utf-8")
        nouveau = (texte.replace("secrétaires de section", "secrétaires du syndicat")
                        .replace("secrétaire de section", "secrétaire du syndicat"))
        if nouveau != texte:
            f.write_text(nouveau, encoding="utf-8")

    # 11. Interface : feuille « verre » aussi sur les écrans simples (connexion),
    #     état vide des adhérents avec son bouton
    remplacer(www / "app/Views/layouts/simple.php",
              "    <link rel=\"stylesheet\" href=\"<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>\">\n",
              "    <link rel=\"stylesheet\" href=\"<?= base_url('assets/app.css?v=' . FO_ASSET_VERSION) ?>\">\n"
              "    <link rel=\"stylesheet\" href=\"<?= base_url('modules/forceos/verre.css?v=' . FO_ASSET_VERSION) ?>\">\n")
    remplacer(www / "app/Views/adherents/index.php",
              "<p class=\"card mt-3\"><?= ($v('q') !== '' || $activeFilters) ? 'Aucun adhérent ne correspond. Essayez un autre nom ou retirez des filtres.' : 'Aucun adhérent pour le moment. Ajoutez le premier avec le bouton « Nouvel adhérent ».' ?></p>",
              "<div class=\"card fo-vide mt-3\"><?= icon(($v('q') !== '' || $activeFilters) ? 'search' : 'users', 'size-8') ?><div><p class=\"m-0 text-lg font-bold\"><?= ($v('q') !== '' || $activeFilters) ? 'Aucun adhérent ne correspond' : 'Aucun adhérent pour le moment' ?></p>"
              "<p class=\"m-0 text-muted\"><?= ($v('q') !== '' || $activeFilters) ? 'Essayez un autre nom ou retirez des filtres.' : 'Commencez par enregistrer le premier adhérent du syndicat FO.' ?></p>"
              "<?php if ($v('q') === '' && ! $activeFilters): ?><a class=\"btn btn-primary mt-3\" href=\"<?= site_url('adherents/nouveau') ?>\"><?= icon('plus', 'size-5') ?> Ajouter le premier adhérent</a><?php endif ?></div></div>")

    print(f"FO Gestion intégrée dans {www}")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(Path(sys.argv[1]).resolve(), Path(sys.argv[2]).resolve())
