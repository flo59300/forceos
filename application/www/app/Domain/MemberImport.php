<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Import et exports CSV du secrétaire (pages/import.php de la 2.x).
 *
 * Import : même format de 15 colonnes que la 2.x (séparateur point-virgule), plus quatre colonnes facultatives
 * à la fin pour les listes électorales : date d'entrée, genre, collège. Écarts voulus :
 *  - les dates au format 31/12/1980 sont comprises (la 2.x les enregistrait vides ou fausses) ;
 *  - la colonne Collecteur, annoncée par la 2.x mais ignorée, rattache l'adhérent (nom complet ou adresse e-mail) ;
 *  - chaque ligne refusée est expliquée.
 */
class MemberImport
{
    public const COLUMNS = ['Matricule', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Adresse', 'Ville', 'Code postal', 'Date naissance', 'Date adhésion', 'Secteur', 'Métier', 'Tournée', 'Collecteur', 'Mode paiement', 'Date entrée', 'Genre', 'Collège'];

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public static function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        return '!';
    }

    /**
     * @return array{imported:int, errors:list<string>}
     */
    public function import(string $path, int $year): array
    {
        $fh = fopen($path, 'r');
        if (fread($fh, 3) !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $ids = function (string $table) {
            $out = [];
            foreach ($this->db->table($table)->select('id, name')->where(['site_id' => $this->siteId, 'active' => 1])->get()->getResultArray() as $r) {
                $out[mb_strtolower(trim($r['name']))] = (int) $r['id'];
            }

            return $out;
        };
        $secteurs = $ids('secteurs');
        $metiers  = $ids('metiers');
        $tournees = $ids('tournees');
        $users    = [];
        foreach ($this->db->table('users')->select('id, first_name, last_name, email')->where(['site_id' => $this->siteId, 'active' => 1])->where('role !=', 'super_admin')->get()->getResultArray() as $u) {
            $users[mb_strtolower($u['email'])] = (int) $u['id'];
            $users[mb_strtolower($u['first_name'] . ' ' . $u['last_name'])] = (int) $u['id'];
            $users[mb_strtolower($u['last_name'] . ' ' . $u['first_name'])] = (int) $u['id'];
        }
        $seen   = [];
        $ok     = 0;
        $errors = [];
        fgetcsv($fh, 0, ';', '"', '');   // ligne d'en-tête
        $line = 1;
        $this->db->transStart();
        while (($row = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            $line++;
            if ($row === [null] || count(array_filter($row, static fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = array_map(static fn ($c) => mb_check_encoding((string) $c, 'UTF-8') ? trim((string) $c) : trim(mb_convert_encoding((string) $c, 'UTF-8', 'Windows-1252')), $row);
            $c   = static fn (int $i) => $row[$i] ?? '';
            if ($c(1) === '' || $c(2) === '') {
                $errors[] = "ligne {$line} : nom et prénom obligatoires";

                continue;
            }
            $d = [
                'matricule' => mb_substr($c(0), 0, 50) ?: null, 'last_name' => mb_substr($c(1), 0, 100), 'first_name' => mb_substr($c(2), 0, 100),
                'email' => filter_var($c(3), FILTER_VALIDATE_EMAIL) ? $c(3) : null, 'phone' => mb_substr($c(4), 0, 20) ?: null,
                'address' => $c(5) ?: null, 'city' => mb_substr($c(6), 0, 100) ?: null, 'postal_code' => mb_substr($c(7), 0, 10) ?: null,
            ];
            $bad = [];
            foreach (['date_birth' => 8, 'date_adhesion' => 9, 'date_entry' => 15] as $f => $i) {
                $v = self::date($c($i));
                if ($v === '!') {
                    $bad[] = 'date « ' . $c($i) . ' » illisible';
                    $v     = null;
                }
                $d[$f] = $v;
            }
            $d['date_adhesion'] ??= date('Y-m-d');
            foreach (['secteur_id' => [10, $secteurs], 'metier_id' => [11, $metiers], 'tournee_id' => [12, $tournees]] as $f => [$i, $map]) {
                if ($c($i) !== '') {
                    $d[$f] = $map[mb_strtolower($c($i))] ?? null;
                    if ($d[$f] === null) {
                        $bad[] = '« ' . $c($i) . ' » inconnu';
                    }
                }
            }
            if ($c(13) !== '') {
                $d['assigned_user_id'] = $users[mb_strtolower($c(13))] ?? null;
                if (! $d['assigned_user_id']) {
                    $bad[] = 'collecteur « ' . $c(13) . ' » inconnu';
                }
            }
            if (in_array(mb_strtolower($c(14)), ['prelevement', 'prélèvement'], true)) {
                $d['payment_mode'] = 'prelevement';
            }
            $g = mb_strtoupper(mb_substr($c(16), 0, 1));
            if (in_array($g, ['H', 'F'], true)) {
                $d['genre'] = $g;
            }
            if (preg_match('/([123])/', $c(17), $m)) {
                $d['categorie'] = 'college_' . $m[1];
            }
            if ($d['matricule']) {
                if (isset($seen[$d['matricule']]) || $this->db->table('members')->where(['site_id' => $this->siteId, 'matricule' => $d['matricule']])->countAllResults()) {
                    $errors[] = "ligne {$line} : matricule {$d['matricule']} déjà présent, ligne ignorée";

                    continue;
                }
                $seen[$d['matricule']] = true;
            }
            $this->db->table('members')->insert(\App\Libraries\Forceos\ChampsChiffres::chiffrer(array_filter($d, static fn ($v) => $v !== null)) + ['site_id' => $this->siteId, 'year' => $year]);
            $ok++;
            if ($bad) {
                $errors[] = "ligne {$line} : importée, mais " . implode(', ', $bad);
            }
        }
        $this->db->transComplete();
        fclose($fh);

        return ['imported' => $ok, 'errors' => $errors];
    }

    // --- Exports (mêmes colonnes que la 2.x), cellules neutralisées ---

    private static function safe(array $rows): array
    {
        return array_map(static fn ($r) => array_map('csv_safe', array_values($r)), $rows);
    }

    public function members(): array
    {
        $head = ['ID', 'Matricule', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Adresse', 'Ville', 'Code postal', 'Date naissance', 'Date entrée entreprise', 'Date adhésion FO', 'Secteur', 'Métier', 'Tournée', 'Collecteur', 'Mode paiement', 'Année cotisation', 'Carte délivrée', 'Radié', 'Date radiation', 'Motif radiation', 'Radié par', 'Notes', 'Créé le', 'Modifié le', 'Site'];

        return array_merge([$head], self::safe(\App\Libraries\Forceos\ChampsChiffres::dechiffrerTout($this->db->query(
            "SELECT m.id, m.matricule, m.last_name, m.first_name, m.email, m.phone, m.address, m.city, m.postal_code, m.date_birth, m.date_entry, m.date_adhesion,
                    s.name AS secteur, mt.name AS metier, t.name AS tournee, CONCAT(u.first_name, ' ', u.last_name) AS collecteur, m.payment_mode, m.year,
                    CASE WHEN m.has_card = 1 THEN 'Oui' ELSE 'Non' END, CASE WHEN m.is_radiated = 1 THEN 'Oui' ELSE 'Non' END, m.radiation_date, m.radiation_motif,
                    CONCAT(ru.first_name, ' ', ru.last_name), m.notes, m.created_at, m.updated_at, si.name
             FROM members m LEFT JOIN secteurs s ON m.secteur_id = s.id LEFT JOIN metiers mt ON m.metier_id = mt.id LEFT JOIN tournees t ON m.tournee_id = t.id
             LEFT JOIN users u ON m.assigned_user_id = u.id LEFT JOIN users ru ON m.radiation_by = ru.id LEFT JOIN sites si ON m.site_id = si.id
             WHERE m.site_id = ? ORDER BY m.is_radiated, m.last_name", [$this->siteId]
        )->getResultArray())));
    }

    public function sales(int $year): array
    {
        return array_merge([['ID', 'Date', 'Nom', 'Prénom', 'Matricule', 'Type', 'Mois', 'Année', 'Montant', 'Paiement', 'Vendeur', 'Reversé', 'Site']], self::safe($this->db->query(
            "SELECT s.id, s.created_at, m.last_name, m.first_name, m.matricule, s.type, s.month, s.year, s.amount, s.payment_method,
                    CONCAT(u.first_name, ' ', u.last_name), CASE WHEN s.is_reversed = 1 THEN 'Oui' ELSE 'Non' END, si.name
             FROM sales s JOIN members m ON s.member_id = m.id LEFT JOIN users u ON s.sold_by = u.id LEFT JOIN sites si ON s.site_id = si.id
             WHERE s.site_id = ? AND s.year = ? ORDER BY s.created_at DESC, s.id DESC", [$this->siteId, $year]
        )->getResultArray()));
    }

    public function users(?int $siteId): array
    {
        $fn = [];
        foreach ($this->db->query("SELECT ufa.user_id, GROUP_CONCAT(uf.abbreviation ORDER BY uf.sort_order SEPARATOR ', ') AS f FROM user_function_assignments ufa JOIN user_functions uf ON ufa.function_id = uf.id GROUP BY ufa.user_id")->getResultArray() as $r) {
            $fn[$r['user_id']] = $r['f'];
        }
        $rows = $this->db->query(
            "SELECT u.id, u.email, u.last_name, u.first_name, u.role, u.phone, s.name AS site, t.name AS tournee, CONCAT(ml.first_name, ' ', ml.last_name) AS adherent,
                    CASE WHEN u.active = 1 THEN 'Actif' ELSE 'Inactif' END AS statut, u.last_login, u.created_at, u.updated_at
             FROM users u LEFT JOIN sites s ON u.site_id = s.id LEFT JOIN tournees t ON u.tournee_id = t.id LEFT JOIN members ml ON u.member_id = ml.id
             WHERE " . ($siteId === null ? '1=1' : 'u.site_id = ' . (int) $siteId) . ' ORDER BY s.name, u.role, u.last_name'
        )->getResultArray();
        $out = [['ID', 'Email', 'Nom', 'Prénom', 'Rôle', 'Téléphone', 'Site', 'Tournée', 'Adhérent lié', 'Fonctions syndicales', 'Statut', 'Dernière connexion', 'Créé le', 'Modifié le']];
        foreach ($rows as $u) {
            $r = array_values($u);
            array_splice($r, 9, 0, [$fn[$u['id']] ?? '']);
            $out[] = array_map('csv_safe', $r);
        }

        return $out;
    }

    public static function csv(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($out, $r, ';', '"', '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
