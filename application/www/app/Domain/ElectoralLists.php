<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

/**
 * Protocole d'accord préélectoral (PAP) et listes électorales générées à partir des adhérents.
 * Tables election_pap et election_listes_electorales, partagées avec la 2.x ; mêmes règles de génération.
 *
 * Écarts voulus avec la 2.x :
 *  - une date d'entrée ou de naissance manquante ne vaut plus « 0 mois » ou « 0 an » (ce qui excluait la personne) :
 *    l'adhérent reste sur la liste avec la mention « à vérifier » ;
 *  - la proportion de femmes et d'hommes exigée sur chaque liste (art. L2314-30) se calcule sur la part de femmes
 *    de la liste électorale du collège, indiquée dans le PAP ; à défaut seulement, sur les adhérents, avec un avertissement.
 */
class ElectoralLists
{
    public const CATEGORIES = ['college_1' => '1er collège', 'college_2' => '2e collège', 'college_3' => '3e collège'];

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public function pap(): ?array
    {
        $p = $this->db->query("SELECT * FROM election_pap WHERE site_id = ? AND status IN ('brouillon','actif') ORDER BY status = 'actif' DESC, updated_at DESC, id DESC LIMIT 1", [$this->siteId])->getRowArray();
        if ($p) {
            $p['colleges'] = json_decode((string) $p['colleges_json'], true) ?: [];
            $p['syndicats'] = json_decode((string) $p['syndicats_json'], true) ?: [];
        }

        return $p;
    }

    public function savePap(array $d, int $userId): void
    {
        $row = [
            'site_id' => $this->siteId, 'name' => $d['name'], 'election_date' => $d['election_date'] ?: null, 'election_date_t2' => $d['election_date_t2'] ?: null,
            'anciennete_electeur_mois' => $d['anc_electeur'], 'anciennete_eligible_mois' => $d['anc_eligible'],
            'age_min_electeur' => $d['age_electeur'], 'age_min_eligible' => $d['age_eligible'],
            'nb_colleges' => count($d['colleges']), 'colleges_json' => json_encode($d['colleges'], JSON_UNESCAPED_UNICODE),
            'syndicats_json' => $d['syndicats'] ? json_encode($d['syndicats'], JSON_UNESCAPED_UNICODE) : null,
            'regles_complementaires' => $d['regles'] ?: null, 'status' => $d['status'], 'created_by' => $userId,
        ];
        $pap = $this->pap();
        if ($pap) {
            $this->db->table('election_pap')->where('id', $pap['id'])->update($row);
        } else {
            $this->db->table('election_pap')->insert($row);
        }
    }

    public function deletePap(): bool
    {
        $pap = $this->pap();
        if (! $pap) {
            return false;
        }
        $this->db->table('election_listes_electorales')->where('pap_id', $pap['id'])->delete();
        $this->db->table('election_pap')->where('id', $pap['id'])->delete();

        return true;
    }

    /** Ancienneté en mois révolus (même calcul que la 2.x) ; null si la date manque. */
    public static function months(?string $entry, string $ref): ?int
    {
        if (! $entry) {
            return null;
        }
        $e = new DateTimeImmutable($entry);
        $r = new DateTimeImmutable($ref);
        if ($e > $r) {
            return 0;
        }
        $d = $e->diff($r);

        return $d->y * 12 + $d->m;
    }

    public static function age(?string $birth, string $ref): ?int
    {
        return $birth ? (new DateTimeImmutable($birth))->diff(new DateTimeImmutable($ref))->y : null;
    }

    /** Fiches incomplètes, pour prévenir avant la génération. */
    public function memberStats(): array
    {
        return $this->db->query(
            "SELECT COUNT(*) AS total, SUM(date_entry IS NULL) AS no_date, SUM(date_birth IS NULL) AS no_birth,
                    SUM(categorie IS NULL OR categorie = '') AS no_cat, SUM(genre IS NULL) AS no_genre,
                    SUM(genre = 'H') AS nb_h, SUM(genre = 'F') AS nb_f
             FROM members WHERE site_id = ? AND is_radiated = 0", [$this->siteId]
        )->getRowArray();
    }

    /**
     * Génère les listes (électeurs et éligibles) à la date du premier tour.
     * @return array{electeurs:int, eligibles:int, exclus:int, a_verifier:int}
     */
    public function generate(array $pap): array
    {
        $ref  = $pap['election_date'] ?: date('Y-m-d');
        $cols = $pap['colleges'];
        $members = $this->db->query(
            'SELECT m.*, mt.name AS metier_name, s.name AS secteur_name FROM members m
             LEFT JOIN metiers mt ON m.metier_id = mt.id LEFT JOIN secteurs s ON m.secteur_id = s.id
             WHERE m.site_id = ? AND m.is_radiated = 0 ORDER BY m.last_name, m.first_name', [$this->siteId]
        )->getResultArray();
        $members = \App\Libraries\Forceos\ChampsChiffres::dechiffrerTout($members);   // ForceOS : date de naissance
        $this->db->transStart();
        $this->db->table('election_listes_electorales')->where('pap_id', $pap['id'])->delete();
        $verify = 0;
        $rows   = [];
        foreach ($members as $m) {
            $anc = self::months($m['date_entry'], $ref);
            $age = self::age($m['date_birth'], $ref);
            $cat = (string) ($m['categorie'] ?? '');
            $ci  = null;
            foreach ($cols as $i => $c) {
                if (empty($c['categories']) || in_array($cat, (array) $c['categories'], true)) {
                    $ci = $i;

                    break;
                }
            }
            $ci ??= count($cols) - 1;
            $electeur = true;
            $motif    = null;
            $missing  = [];
            if ($anc === null) {
                $missing[] = 'date d’entrée inconnue';
            } elseif ($anc < (int) $pap['anciennete_electeur_mois']) {
                $electeur = false;
                $motif    = "Ancienneté insuffisante ({$anc} mois < {$pap['anciennete_electeur_mois']} requis)";
            }
            if ($electeur) {
                if ($age === null) {
                    $missing[] = 'date de naissance inconnue';
                } elseif ($age < (int) $pap['age_min_electeur']) {
                    $electeur = false;
                    $motif    = "Âge insuffisant ({$age} ans < {$pap['age_min_electeur']} requis)";
                }
            }
            if ($electeur && $missing) {
                $motif = 'À vérifier : ' . implode(', ', $missing);
                $verify++;
            }
            $eligible = $electeur && ($anc === null || $anc >= (int) $pap['anciennete_eligible_mois']) && ($age === null || $age >= (int) $pap['age_min_eligible']);
            $base = [
                'pap_id' => $pap['id'], 'site_id' => $this->siteId, 'college_index' => $ci,
                'college_name' => $cols[$ci]['name'] ?? 'Collège ' . ($ci + 1), 'member_id' => $m['id'],
                'member_name' => $m['last_name'] . ' ' . $m['first_name'], 'member_matricule' => $m['matricule'],
                'member_metier' => $m['metier_name'], 'member_secteur' => $m['secteur_name'], 'member_genre' => $m['genre'],
                'date_entry' => $m['date_entry'], 'anciennete_mois' => $anc ?? 0,
            ];
            $rows[] = $base + ['type' => 'electeur', 'is_eligible' => $electeur ? 1 : 0, 'exclusion_motif' => $motif];
            if ($eligible) {
                $rows[] = $base + ['type' => 'eligible', 'is_eligible' => 1, 'exclusion_motif' => $missing ? $motif : null];
            }
        }
        foreach (array_chunk($rows, 300) as $part) {
            $this->db->table('election_listes_electorales')->insertBatch($part);
        }
        $this->db->transComplete();
        $c = fn (string $where) => (int) $this->db->query("SELECT COUNT(*) AS n FROM election_listes_electorales WHERE pap_id = ? AND {$where}", [$pap['id']])->getRow()->n;

        return ['electeurs' => $c("type = 'electeur' AND is_eligible = 1"), 'eligibles' => $c("type = 'eligible'"), 'exclus' => $c("type = 'electeur' AND is_eligible = 0"), 'a_verifier' => $verify];
    }

    public function rows(int $papId): array
    {
        return $this->db->table('election_listes_electorales')->where('pap_id', $papId)
            ->orderBy('college_index')->orderBy('type', 'DESC')->orderBy('member_name')->get()->getResultArray();
    }

    /**
     * Proportion femmes / hommes par collège et nombre de candidates et candidats exigés (art. L2314-30).
     * Arrondi arithmétique à l'entier le plus proche (comme la 2.x).
     */
    public function parity(array $pap): array
    {
        $stats = $this->db->query(
            "SELECT college_index, SUM(member_genre = 'H' AND is_eligible = 1) AS h, SUM(member_genre = 'F' AND is_eligible = 1) AS f,
                    SUM(member_genre IS NULL AND is_eligible = 1) AS inconnu, SUM(is_eligible = 1) AS total
             FROM election_listes_electorales WHERE pap_id = ? AND type = 'electeur' GROUP BY college_index ORDER BY college_index", [$pap['id']]
        )->getResultArray();
        $out = [];
        foreach ($stats as $s) {
            $c        = $pap['colleges'][(int) $s['college_index']] ?? [];
            $fromPap  = isset($c['part_femmes']) && $c['part_femmes'] !== '' && $c['part_femmes'] !== null;
            $pctF     = $fromPap ? (float) $c['part_femmes'] : ((int) $s['total'] > 0 ? round($s['f'] / $s['total'] * 100, 1) : 0);
            $need     = static function (int $seats) use ($pctF): array {
                $f = $seats > 0 ? max(0, (int) round($seats * $pctF / 100)) : 0;

                return ['f' => $f, 'h' => $seats - $f];
            };
            $out[] = [
                'name' => $c['name'] ?? 'Collège', 'h' => (int) $s['h'], 'f' => (int) $s['f'], 'inconnu' => (int) $s['inconnu'], 'total' => (int) $s['total'],
                'pctF' => $pctF, 'fromPap' => $fromPap, 'tit' => $need((int) ($c['seats_tit'] ?? 0)), 'sup' => $need((int) ($c['seats_sup'] ?? 0)),
                'seats_tit' => (int) ($c['seats_tit'] ?? 0), 'seats_sup' => (int) ($c['seats_sup'] ?? 0),
            ];
        }

        return $out;
    }

    /** Lignes du fichier CSV (mêmes colonnes que la 2.x), cellules neutralisées. */
    public function csv(int $papId, string $type): array
    {
        $out = [['Collège', 'Matricule', 'Nom', 'Genre', 'Métier', 'Secteur', 'Date entrée', 'Ancienneté (mois)', 'Éligible', 'Motif exclusion']];
        foreach ($this->db->query(
            "SELECT college_name, member_matricule, member_name, CASE WHEN member_genre = 'H' THEN 'Homme' WHEN member_genre = 'F' THEN 'Femme' ELSE '' END AS genre,
                    member_metier, member_secteur, date_entry, anciennete_mois, CASE WHEN is_eligible = 1 THEN 'Oui' ELSE 'Non' END AS eligible, exclusion_motif
             FROM election_listes_electorales WHERE pap_id = ? AND type = ? ORDER BY college_index, member_name", [$papId, $type]
        )->getResultArray() as $r) {
            $out[] = array_map('csv_safe', array_values($r));
        }

        return $out;
    }
}
