<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Élections CSE : quorum, répartition des sièges, représentativité (simulations et résultats officiels).
 * Tables election_simulations, election_colleges, election_lists, partagées avec la 2.x.
 *
 * Règles appliquées (Code du travail) :
 *  - quorum (art. L2314-29) : il est atteint quand les suffrages valablement exprimés, blancs et nuls exclus,
 *    représentent au moins la moitié des inscrits (jurisprudence constante de la Cour de cassation).
 *    La 2.x comptait les votants, blancs et nuls compris, et pouvait donc annoncer un quorum atteint à tort ;
 *  - sièges (art. R2314-20) : quotient électoral, puis plus forte moyenne ;
 *  - égalité de moyenne (art. R2314-21) : le siège va à la liste qui a le plus de voix ; à voix égales,
 *    au candidat le plus âgé, ce que l'outil signale sans pouvoir le trancher. La 2.x donnait le siège
 *    à la première liste saisie ;
 *  - représentativité (art. L2122-1 et L2122-2) : 10 % des suffrages exprimés au premier tour des titulaires,
 *    quel que soit le nombre de votants ; pour un syndicat catégoriel, calculés sur les seuls collèges où il
 *    présente des candidats (Cass. soc., 22 janvier 2025, n° 23-21.936). La 2.x divisait par tous les collèges.
 */
class Cse
{
    public const MAX_COLLEGES  = 4;
    public const MAX_SYNDICATS = 10;

    /** État vide, identique au simulateur de la 2.x. */
    public static function blank(): array
    {
        $col = static fn (string $n, string $s) => ['nom' => $n, 'subtitle' => $s, 'inscrits' => 0,
            'tit' => ['sieges' => 0, 'votants' => 0, 'blancs' => 0, 'votes' => []],
            'sup' => ['sieges' => 0, 'votants' => 0, 'blancs' => 0, 'votes' => []], 'bureaux' => []];

        return [
            'syndicats' => [
                ['nom' => 'FO', 'categoriel' => false, 'exclus' => []], ['nom' => 'CGT', 'categoriel' => false, 'exclus' => []],
                ['nom' => 'CFDT', 'categoriel' => false, 'exclus' => []], ['nom' => 'CFE-CGC', 'categoriel' => true, 'exclus' => [0]],
                ['nom' => 'CFTC', 'categoriel' => false, 'exclus' => []], ['nom' => 'SUD', 'categoriel' => false, 'exclus' => []],
            ],
            'colleges' => [$col('1er collège', 'Ouvriers et employés'), $col('2e collège', 'Techniciens et agents de maîtrise'), $col('3e collège', 'Cadres')],
        ];
    }

    /**
     * Nettoie un état reçu du navigateur : nombres entiers positifs, tailles bornées, totaux recalculés
     * à partir des bureaux de vote quand il y en a.
     */
    public static function normalize(array $in): array
    {
        $int  = static fn ($v) => max(0, min(10_000_000, (int) $v));
        $txt  = static fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);
        $syn  = [];
        foreach (array_slice((array) ($in['syndicats'] ?? []), 0, self::MAX_SYNDICATS) as $s) {
            $nom = $txt($s['nom'] ?? '', 50);
            $syn[] = ['nom' => $nom !== '' ? $nom : 'Syndicat', 'categoriel' => ! empty($s['categoriel']),
                'exclus' => array_values(array_unique(array_map('intval', array_filter((array) ($s['exclus'] ?? []), 'is_numeric'))))];
        }
        $nS   = count($syn);
        $cols = [];
        foreach (array_slice((array) ($in['colleges'] ?? []), 0, self::MAX_COLLEGES) as $c) {
            $col = ['nom' => $txt($c['nom'] ?? 'Collège', 100) ?: 'Collège', 'subtitle' => $txt($c['subtitle'] ?? '', 100), 'inscrits' => $int($c['inscrits'] ?? 0), 'bureaux' => []];
            foreach (array_slice((array) ($c['bureaux'] ?? []), 0, 30) as $b) {
                $bu = ['name' => $txt($b['name'] ?? 'Bureau', 60) ?: 'Bureau'];
                foreach (['tit', 'sup'] as $t) {
                    $bu[$t] = ['votants' => $int($b[$t]['votants'] ?? 0), 'blancs' => $int($b[$t]['blancs'] ?? 0), 'votes' => []];
                    for ($i = 0; $i < $nS; $i++) {
                        $bu[$t]['votes'][$i] = $int($b[$t]['votes'][$i] ?? 0);
                    }
                }
                $col['bureaux'][] = $bu;
            }
            foreach (['tit', 'sup'] as $t) {
                $col[$t] = ['sieges' => min(500, $int($c[$t]['sieges'] ?? 0)), 'votants' => $int($c[$t]['votants'] ?? 0), 'blancs' => $int($c[$t]['blancs'] ?? 0), 'votes' => []];
                for ($i = 0; $i < $nS; $i++) {
                    $col[$t]['votes'][$i] = $int($c[$t]['votes'][$i] ?? 0);
                }
                if ($col['bureaux']) {   // totaux du collège = somme des bureaux (comme la 2.x)
                    $col[$t]['votants'] = array_sum(array_map(static fn ($b) => $b[$t]['votants'], $col['bureaux']));
                    $col[$t]['blancs']  = array_sum(array_map(static fn ($b) => $b[$t]['blancs'], $col['bureaux']));
                    for ($i = 0; $i < $nS; $i++) {
                        $col[$t]['votes'][$i] = array_sum(array_map(static fn ($b) => $b[$t]['votes'][$i], $col['bureaux']));
                    }
                }
            }
            $cols[] = $col;
        }

        return ['syndicats' => $syn, 'colleges' => $cols];
    }

    public static function excluded(array $syndicat, int $collegeIndex): bool
    {
        return $syndicat['categoriel'] && in_array($collegeIndex, $syndicat['exclus'], true);
    }

    /**
     * Répartition des sièges d'un collège : quotient électoral puis plus forte moyenne.
     * @param list<array{voix:int, exclu:bool}> $lists
     * @return array{seats:list<int>, ties:list<array{seat:int, lists:list<int>}>}
     */
    public static function allocate(array $lists, int $seats, int $exprimes): array
    {
        $alloc = array_fill(0, count($lists), 0);
        $ties  = [];
        if ($seats <= 0 || $exprimes <= 0) {
            return ['seats' => $alloc, 'ties' => $ties];
        }
        // Quotient : sièges = partie entière de voix × sièges / exprimés (calcul exact en entiers).
        foreach ($lists as $i => $l) {
            $alloc[$i] = $l['exclu'] ? 0 : intdiv($l['voix'] * $seats, $exprimes);
        }
        $left = $seats - array_sum($alloc);
        for ($k = 1; $left > 0; $k++) {
            $best = [];
            foreach ($lists as $i => $l) {
                if ($l['exclu'] || $l['voix'] <= 0) {
                    continue;
                }
                if (! $best) {
                    $best = [$i];

                    continue;
                }
                $j   = $best[0];
                $cmp = $l['voix'] * ($alloc[$j] + 1) <=> $lists[$j]['voix'] * ($alloc[$i] + 1);   // moyennes comparées sans arrondi
                if ($cmp > 0) {
                    $best = [$i];
                } elseif ($cmp === 0) {
                    $best[] = $i;
                }
            }
            if (! $best) {
                break;
            }
            if (count($best) > 1) {
                // R2314-21 : à moyenne égale, la liste qui a le plus de voix ; à voix égales, le candidat le plus âgé.
                $maxV  = max(array_map(static fn ($i) => $lists[$i]['voix'], $best));
                $top   = array_values(array_filter($best, static fn ($i) => $lists[$i]['voix'] === $maxV));
                if (count($top) > 1 && $left < count($top)) {
                    $ties[] = ['seat' => $seats - $left + 1, 'lists' => $top];
                }
                $best = $top;
            }
            $alloc[$best[0]]++;
            $left--;
        }

        return ['seats' => $alloc, 'ties' => $ties];
    }

    /** Résultats complets pour un état normalisé. */
    public static function compute(array $st): array
    {
        $out = ['types' => [], 'repr' => [], 'has' => false];
        foreach (['tit' => 'Titulaires', 'sup' => 'Suppléants'] as $t => $label) {
            $cols = [];
            $tot  = ['inscrits' => 0, 'votants' => 0, 'exprimes' => 0, 'sieges' => 0, 'parSyndicat' => array_fill(0, count($st['syndicats']), 0)];
            foreach ($st['colleges'] as $ci => $c) {
                $d    = $c[$t];
                $se   = max(0, $d['votants'] - $d['blancs']);
                $lists = [];
                foreach ($st['syndicats'] as $si => $s) {
                    $ex      = self::excluded($s, $ci);
                    $lists[] = ['voix' => $ex ? 0 : $d['votes'][$si], 'exclu' => $ex];
                }
                $quorum = $c['inscrits'] > 0 && $se * 2 >= $c['inscrits'];
                $alloc  = $quorum ? self::allocate($lists, $d['sieges'], $se) : ['seats' => array_fill(0, count($lists), 0), 'ties' => []];
                $rows   = [];
                foreach ($lists as $si => $l) {
                    $rows[] = ['syndicat' => $st['syndicats'][$si]['nom'], 'voix' => $l['voix'], 'exclu' => $l['exclu'],
                        'pct' => $se > 0 ? $l['voix'] / $se * 100 : 0, 'sieges' => $alloc['seats'][$si]];
                    $tot['parSyndicat'][$si] += $alloc['seats'][$si];
                }
                $sumVotes = array_sum(array_column($lists, 'voix'));
                $cols[]   = [
                    'nom' => $c['nom'], 'subtitle' => $c['subtitle'], 'inscrits' => $c['inscrits'], 'sieges' => $d['sieges'],
                    'votants' => $d['votants'], 'blancs' => $d['blancs'], 'exprimes' => $se,
                    'participation' => $c['inscrits'] > 0 ? $d['votants'] / $c['inscrits'] * 100 : 0,
                    'quorum' => $quorum, 'quorumVotants' => $c['inscrits'] > 0 && $d['votants'] * 2 >= $c['inscrits'],
                    'rows' => $rows, 'ties' => $alloc['ties'], 'ecart' => $sumVotes !== $se, 'sumVotes' => $sumVotes,
                ];
                $tot['inscrits'] += $c['inscrits'];
                $tot['votants'] += $d['votants'];
                $tot['exprimes'] += $se;
                $tot['sieges'] += $d['sieges'];
            }
            $tot['participation'] = $tot['inscrits'] > 0 ? $tot['votants'] / $tot['inscrits'] * 100 : 0;
            $out['types'][$t] = ['label' => $label, 'colleges' => $cols, 'totaux' => $tot, 'has' => $tot['votants'] > 0 || $tot['sieges'] > 0];
            $out['has'] = $out['has'] || $out['types'][$t]['has'];
        }

        // Représentativité : premier tour des titulaires
        foreach ($st['syndicats'] as $si => $s) {
            $voix = $den = 0;
            foreach ($out['types']['tit']['colleges'] as $ci => $c) {
                if (self::excluded($s, $ci)) {
                    continue;
                }
                $voix += $c['rows'][$si]['voix'];
                $den += $c['exprimes'];
            }
            $pct = $den > 0 ? $voix / $den * 100 : 0;
            $out['repr'][] = ['nom' => $s['nom'], 'voix' => $voix, 'base' => $den, 'pct' => $pct, 'representatif' => $pct >= 10, 'categoriel' => $s['categoriel']];
        }
        usort($out['repr'], static fn ($a, $b) => $b['pct'] <=> $a['pct']);

        return $out;
    }

    // ------------------------------------------------------------------
    // Enregistrement (même structure que la 2.x)
    // ------------------------------------------------------------------

    public function __construct(private ?BaseConnection $db = null, private int $siteId = 0)
    {
    }

    public function list(bool $official): array
    {
        return $this->db->table('election_simulations s')
            ->select("s.id, s.name, s.election_date, s.updated_at, s.inscrits, s.votants, CONCAT(u.first_name, ' ', u.last_name) AS creator, (SELECT COUNT(*) FROM election_colleges c WHERE c.simulation_id = s.id) AS nb_colleges, (SELECT SUM(seats) FROM election_colleges c WHERE c.simulation_id = s.id) AS total_seats", false)
            ->join('users u', 'u.id = s.created_by', 'left')
            ->where(['s.site_id' => $this->siteId, 's.is_official' => $official ? 1 : 0])
            ->orderBy($official ? 's.election_date' : 's.updated_at', 'DESC')->orderBy('s.id', 'DESC')->get()->getResultArray();
    }

    /** Charge un enregistrement (simulation ou résultat officiel de la 2.x) dans l'état du simulateur. */
    public function load(int $id): ?array
    {
        $sim = $this->db->table('election_simulations')->where(['id' => $id, 'site_id' => $this->siteId])->get()->getRowArray();
        if (! $sim) {
            return null;
        }
        $cols = $this->db->table('election_colleges')->where('simulation_id', $id)->orderBy('sort_order')->orderBy('id')->get()->getResultArray();
        $raw  = json_decode((string) $sim['syndicats_json'], true) ?: [];
        $syn  = [];
        foreach ($raw as $s) {
            // 2.x : objets {nom, categoriel, collegesExclus (identifiants 1, 2, 3)} pour les simulations, simples noms pour les résultats officiels
            $syn[] = is_array($s)
                ? ['nom' => (string) ($s['nom'] ?? ''), 'categoriel' => ! empty($s['categoriel']), 'exclus' => array_map(static fn ($x) => (int) $x - 1, (array) ($s['collegesExclus'] ?? []))]
                : ['nom' => (string) $s, 'categoriel' => false, 'exclus' => []];
        }
        $state = ['syndicats' => $syn, 'colleges' => []];
        foreach ($cols as $c) {
            $lists = $this->db->table('election_lists')->where('college_id', $c['id'])->orderBy('sort_order')->orderBy('id')->get()->getResultArray();
            if (! $syn) {
                foreach ($lists as $l) {
                    $state['syndicats'][] = ['nom' => $l['name'], 'categoriel' => false, 'exclus' => []];
                }
                $syn = $state['syndicats'];
            }
            $col = ['nom' => $c['name'], 'subtitle' => (string) $c['subtitle'], 'inscrits' => (int) $c['inscrits'],
                'tit' => ['sieges' => (int) $c['seats'], 'votants' => (int) $c['votants'], 'blancs' => (int) $c['blancs_nuls'], 'votes' => []],
                'sup' => ['sieges' => (int) $c['seats_sup'], 'votants' => (int) $c['votants_sup'], 'blancs' => (int) $c['blancs_nuls_sup'], 'votes' => []],
                'bureaux' => []];
            $byName = [];
            foreach ($lists as $i => $l) {
                $byName[$l['name']] ??= $l;
            }
            foreach ($state['syndicats'] as $si => $s) {
                $l = $byName[$s['nom']] ?? ($lists[$si] ?? null);
                $col['tit']['votes'][$si] = (int) ($l['votes'] ?? 0);
                $col['sup']['votes'][$si] = (int) ($l['voix_sup'] ?? 0);
            }
            $bx = json_decode((string) $c['bureaux_json'], true);
            if (is_array($bx)) {
                $data  = isset($bx['data']) ? (array) $bx['data'] : (array_is_list($bx) ? $bx : []);
                $votes = (array) ($bx['_votes'] ?? []);
                foreach ($data as $bi => $b) {
                    $bu = ['name' => (string) ($b['name'] ?? 'Bureau')];
                    foreach (['tit', 'sup'] as $t) {
                        $bu[$t] = ['votants' => (int) ($b[$t]['votants'] ?? 0), 'blancs' => (int) ($b[$t]['blancs'] ?? 0), 'votes' => []];
                        foreach ($state['syndicats'] as $si => $s) {
                            $bu[$t]['votes'][$si] = (int) ($votes[$bi][$t][$si] ?? 0);
                        }
                    }
                    $col['bureaux'][] = $bu;
                }
            }
            $state['colleges'][] = $col;
        }

        return ['sim' => $sim, 'state' => self::normalize($state)];
    }

    /** Enregistre un état (création ou mise à jour). Les syndicats sont écrits au format de la 2.x. */
    public function save(array $st, string $name, bool $official, ?string $date, int $userId, int $id = 0): ?int
    {
        $tot  = static fn (string $k, string $t) => array_sum(array_map(static fn ($c) => $c[$t][$k], $st['colleges']));
        $insc = array_sum(array_column($st['colleges'], 'inscrits'));
        $row  = [
            'name' => mb_substr($name, 0, 255), 'effectif' => $insc, 'inscrits' => $insc,
            'votants' => $tot('votants', 'tit'), 'blancs_nuls' => $tot('blancs', 'tit'), 'type_scrutin' => 'titulaires',
            'syndicats_json' => json_encode(array_map(static fn ($s) => ['nom' => $s['nom'], 'categoriel' => $s['categoriel'], 'collegesExclus' => array_map(static fn ($x) => $x + 1, $s['exclus'])], $st['syndicats']), JSON_UNESCAPED_UNICODE),
            'election_date' => $official ? $date : null,
        ];
        $this->db->transStart();
        if ($id) {
            if (! $this->db->table('election_simulations')->where(['id' => $id, 'site_id' => $this->siteId, 'is_official' => $official ? 1 : 0])->countAllResults()) {
                $this->db->transComplete();

                return null;
            }
            $this->db->table('election_simulations')->where('id', $id)->update($row);
            $this->db->table('election_colleges')->where('simulation_id', $id)->delete();
        } else {
            $this->db->table('election_simulations')->insert($row + ['site_id' => $this->siteId, 'is_official' => $official ? 1 : 0, 'created_by' => $userId]);
            $id = (int) $this->db->insertID();
        }
        foreach ($st['colleges'] as $ci => $c) {
            $bx = null;
            if ($c['bureaux']) {
                $data = $votes = [];
                foreach ($c['bureaux'] as $bi => $b) {
                    $data[] = ['name' => $b['name'], 'tit' => ['votants' => $b['tit']['votants'], 'blancs' => $b['tit']['blancs']], 'sup' => ['votants' => $b['sup']['votants'], 'blancs' => $b['sup']['blancs']]];
                    $votes[$bi] = ['tit' => (object) $b['tit']['votes'], 'sup' => (object) $b['sup']['votes']];
                }
                $bx = json_encode(['data' => $data, '_votes' => $votes], JSON_UNESCAPED_UNICODE);
            }
            $this->db->table('election_colleges')->insert([
                'simulation_id' => $id, 'name' => $c['nom'], 'subtitle' => $c['subtitle'],
                'seats' => $c['tit']['sieges'], 'seats_sup' => $c['sup']['sieges'], 'inscrits' => $c['inscrits'],
                'votants' => $c['tit']['votants'], 'votants_sup' => $c['sup']['votants'],
                'blancs_nuls' => $c['tit']['blancs'], 'blancs_nuls_sup' => $c['sup']['blancs'],
                'bureaux_json' => $bx, 'sort_order' => $ci,
            ]);
            $cid = (int) $this->db->insertID();
            foreach ($st['syndicats'] as $si => $s) {
                $this->db->table('election_lists')->insert([
                    'college_id' => $cid, 'name' => $s['nom'], 'sigle' => mb_substr($s['nom'], 0, 50),
                    'votes' => $c['tit']['votes'][$si], 'voix_sup' => $c['sup']['votes'][$si],
                    'categoriel' => $s['categoriel'] ? 1 : 0, 'sort_order' => $si,
                ]);
            }
        }
        $this->db->transComplete();

        return $id;
    }

    public function delete(int $id): bool
    {
        $this->db->table('election_simulations')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }
}
