<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Comptes d'un site (vue du secrétaire).
 *
 * Corrections de la 2.x :
 *  - un compte qui a un historique financier ou syndical ne peut plus être supprimé :
 *    la base effaçait en cascade ses reversements, validations, événements et permanences ;
 *    il est désactivé à la place ;
 *  - toute action vérifie d'abord que le compte appartient au site (fonctions, suppression) ;
 *  - l'e-mail est validé et unique aussi en modification ;
 *  - le secrétaire ne peut ni se désactiver ni retirer son propre rôle.
 */
class Users
{
    public const ROLES = ['collecteur' => 'Délégué collecteur', 'tresorier' => 'Trésorier', 'secretaire' => 'Secrétaire'];

    /** Tables dont le contenu serait effacé en cascade à la suppression du compte. */
    private const HISTORY = ['sales' => 'sold_by', 'reversements' => 'user_id', 'prelevement_validations' => 'validated_by', 'events' => 'created_by', 'permanences' => 'created_by', 'election_simulations' => 'created_by', 'delegation_entries' => 'user_id', 'messages' => 'sender_id'];

    public function __construct(private BaseConnection $db, private int $siteId, private int $actorId)
    {
    }

    public function list(): array
    {
        $rows = $this->db->table('users u')
            ->select("u.id, u.email, u.first_name, u.last_name, u.role, u.phone, u.active, u.last_login, u.member_id, m.categorie AS member_college, (SELECT COUNT(*) FROM members x WHERE x.assigned_user_id = u.id AND x.is_radiated = 0) AS nb_members", false)
            ->join('members m', 'm.id = u.member_id', 'left')
            ->where('u.site_id', $this->siteId)->where('u.role !=', 'super_admin')
            ->orderBy('u.active', 'DESC')->orderBy("FIELD(u.role, 'secretaire', 'tresorier', 'collecteur')", '', false)->orderBy('u.last_name')
            ->get()->getResultArray();
        $fn = [];
        foreach ($this->db->table('user_function_assignments a')->select('a.user_id, f.id, f.label, f.abbreviation')
            ->join('user_functions f', 'f.id = a.function_id')->where('f.site_id', $this->siteId)->get()->getResultArray() as $r) {
            $fn[(int) $r['user_id']][] = $r;
        }
        foreach ($rows as &$r) {
            $r['functions'] = $fn[(int) $r['id']] ?? [];
        }

        return $rows;
    }

    public function find(int $id): ?array
    {
        return $this->db->table('users')->where(['id' => $id, 'site_id' => $this->siteId])->where('role !=', 'super_admin')->get()->getRowArray();
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $q = $this->db->table('users')->where('email', $email);
        if ($exceptId) {
            $q->where('id !=', $exceptId);
        }

        return $q->countAllResults() > 0;
    }

    /** Adhérent lié (double casquette) : doit être un adhérent du site. */
    public function cleanMemberId($v): ?int
    {
        $id = (int) $v;

        return $id > 0 && $this->db->table('members')->where(['id' => $id, 'site_id' => $this->siteId])->countAllResults() ? $id : null;
    }

    public function create(array $d): int
    {
        $this->db->table('users')->insert([
            'site_id' => $this->siteId, 'email' => $d['email'], 'password' => password_hash($d['password'], PASSWORD_DEFAULT),
            'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'role' => $d['role'],
            'phone' => $d['phone'] ?: null, 'member_id' => $this->cleanMemberId($d['member_id'] ?? null),
        ]);

        return (int) $this->db->insertID();
    }

    public function update(int $id, array $d): void
    {
        $row = ['email' => $d['email'], 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'phone' => $d['phone'] ?: null, 'member_id' => $this->cleanMemberId($d['member_id'] ?? null)];
        if ($id !== $this->actorId) {
            $row['role'] = $d['role'];
        }
        if (! empty($d['password'])) {
            $row['password'] = password_hash($d['password'], PASSWORD_DEFAULT);
            $row['remember_token'] = null;
        }
        $this->db->table('users')->where(['id' => $id, 'site_id' => $this->siteId])->update($row);
    }

    /** @return string 'ok' | 'self' | 'absent' */
    public function setActive(int $id, bool $active): string
    {
        if ($id === $this->actorId) {
            return 'self';
        }
        if (! $this->find($id)) {
            return 'absent';
        }
        $this->db->table('users')->where(['id' => $id, 'site_id' => $this->siteId])->update(['active' => $active ? 1 : 0, 'remember_token' => $active ? null : null]);

        return 'ok';
    }

    /** Ce qui serait effacé si l'on supprimait le compte. */
    public function history(int $id): array
    {
        $out = [];
        foreach (self::HISTORY as $table => $col) {
            $n = $this->db->table($table)->where($col, $id)->countAllResults();
            if ($n) {
                $out[$table] = $n;
            }
        }

        return $out;
    }

    /** @return string 'ok' | 'self' | 'absent' | 'history' */
    public function delete(int $id): string
    {
        if ($id === $this->actorId) {
            return 'self';
        }
        if (! $this->find($id)) {
            return 'absent';
        }
        if ($this->history($id)) {
            return 'history';
        }
        $this->db->transStart();
        $this->db->table('members')->where(['assigned_user_id' => $id, 'site_id' => $this->siteId])->update(['assigned_user_id' => null]);
        $this->db->table('users')->where(['id' => $id, 'site_id' => $this->siteId])->delete();
        $this->db->transComplete();

        return 'ok';
    }

    // --- Fonctions syndicales ---

    public function functions(): array
    {
        return $this->db->table('user_functions')->where(['site_id' => $this->siteId, 'active' => 1])->orderBy('sort_order')->orderBy('label')->get()->getResultArray();
    }

    public function setFunctions(int $userId, array $ids): bool
    {
        if (! $this->find($userId)) {
            return false;
        }
        $valid = array_map('intval', array_column($this->db->table('user_functions')->select('id')->where('site_id', $this->siteId)
            ->whereIn('id', array_merge([0], array_map('intval', $ids)))->get()->getResultArray(), 'id'));
        $this->db->transStart();
        $this->db->table('user_function_assignments')->where('user_id', $userId)->delete();
        foreach ($valid as $fid) {
            $this->db->table('user_function_assignments')->insert(['user_id' => $userId, 'function_id' => $fid]);
        }
        $this->db->transComplete();

        return true;
    }

    public function addFunction(string $label, string $abbr, string $color): void
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#002B7F';
        $this->db->table('user_functions')->insert(['site_id' => $this->siteId, 'label' => $label, 'abbreviation' => $abbr !== '' ? $abbr : mb_substr($label, 0, 10), 'color' => $color]);
    }

    public function deleteFunction(int $id): bool
    {
        $owned = $this->db->table('user_functions')->where(['id' => $id, 'site_id' => $this->siteId])->countAllResults();
        if (! $owned) {
            return false;
        }
        $this->db->table('user_function_assignments')->where('function_id', $id)->delete();
        $this->db->table('user_functions')->where('id', $id)->delete();

        return true;
    }

    /**
     * Import de collecteurs depuis un CSV (mêmes colonnes reconnues que la 2.x).
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function importCsv(string $path, string $defaultPassword): array
    {
        $res = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $h   = fopen($path, 'r');
        if (! $h) {
            $res['errors'][] = 'Fichier illisible.';

            return $res;
        }
        $first = (string) fgets($h);
        rewind($h);
        $sep    = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(static fn ($x) => mb_strtolower(trim(str_replace(["\xEF\xBB\xBF", '"'], '', (string) $x))), fgetcsv($h, 0, $sep) ?: []);
        $map    = ['prenom' => ['prenom', 'prénom', 'firstname', 'first_name'], 'nom' => ['nom', 'lastname', 'last_name'], 'email' => ['email', 'mail', 'e-mail'], 'telephone' => ['telephone', 'téléphone', 'tel', 'phone'], 'mot_de_passe' => ['mot_de_passe', 'password', 'mdp', 'pass']];
        $cols   = [];
        foreach ($map as $k => $aliases) {
            foreach ($aliases as $a) {
                if (($i = array_search($a, $header, true)) !== false) {
                    $cols[$k] = $i;
                    break;
                }
            }
        }
        if (! isset($cols['prenom'], $cols['nom'], $cols['email'])) {
            fclose($h);
            $res['errors'][] = 'Colonnes obligatoires manquantes : prénom, nom, e-mail.';

            return $res;
        }
        while (($row = fgetcsv($h, 0, $sep)) !== false) {
            $fn = trim($row[$cols['prenom']] ?? '');
            $ln = trim($row[$cols['nom']] ?? '');
            $em = trim($row[$cols['email']] ?? '');
            if ($fn === '' || $ln === '' || $em === '') {
                $res['skipped']++;
                continue;
            }
            if (! filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $res['errors'][] = "{$fn} {$ln} : e-mail invalide";
                $res['skipped']++;
                continue;
            }
            if ($this->emailTaken($em)) {
                $res['errors'][] = "{$fn} {$ln} : e-mail déjà utilisé";
                $res['skipped']++;
                continue;
            }
            $pw = isset($cols['mot_de_passe']) ? trim($row[$cols['mot_de_passe']] ?? '') : '';
            if ($pw === '' || self::passwordErrors($pw)) {
                $pw = $defaultPassword;
            }
            $this->create(['email' => $em, 'password' => $pw, 'first_name' => $fn, 'last_name' => $ln, 'role' => 'collecteur', 'phone' => isset($cols['telephone']) ? trim($row[$cols['telephone']] ?? '') : '']);
            $res['imported']++;
        }
        fclose($h);

        return $res;
    }

    /** Règles de mot de passe de la 2.x : 8 caractères, une majuscule, une minuscule, un chiffre. */
    public static function passwordErrors(string $pw): array
    {
        $e = [];
        if (mb_strlen($pw) < 8) {
            $e[] = 'au moins 8 caractères';
        }
        if (! preg_match('/[A-Z]/', $pw)) {
            $e[] = 'une majuscule';
        }
        if (! preg_match('/[a-z]/', $pw)) {
            $e[] = 'une minuscule';
        }
        if (! preg_match('/[0-9]/', $pw)) {
            $e[] = 'un chiffre';
        }

        return $e;
    }
}
