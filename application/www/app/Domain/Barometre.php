<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Baromètre social : remontées du terrain (ambiance de 1 à 5, thèmes, commentaire) par secteur.
 * Tables barometre_entries et barometre_tags, partagées avec la 2.x ; mêmes thèmes par défaut et mêmes calculs.
 *
 * Écarts voulus avec la 2.x : le secteur doit appartenir au site, la date doit être réelle et pas dans le futur,
 * et seuls les thèmes du site sont acceptés.
 */
class Barometre
{
    public const AMBIANCE = [1 => 'Très mauvaise', 2 => 'Mauvaise', 3 => 'Neutre', 4 => 'Bonne', 5 => 'Excellente'];

    /** Thèmes créés au premier usage, comme la 2.x (libellé, pictogramme, couleur). */
    private const DEFAULT_TAGS = [
        ['Salaires', '💰', '#16a34a'], ['Conditions de travail', '🏭', '#ea580c'],
        ['Organisation', '📋', '#6366f1'], ['Sécurité', '🦺', '#dc2626'],
        ['Management', '👔', '#7c3aed'], ['Horaires', '🕐', '#0ea5e9'],
        ['Formation', '🎓', '#d946ef'], ['Emploi', '👷', '#f59e0b'],
    ];

    public const PERIODS = [1 => 'le dernier mois', 3 => 'les 3 derniers mois', 6 => 'les 6 derniers mois', 12 => 'les 12 derniers mois'];

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public function tags(): array
    {
        $q = fn () => $this->db->table('barometre_tags')->where('site_id', $this->siteId)->orderBy('sort_order')->orderBy('label')->get()->getResultArray();
        $tags = $q();
        if (! $tags) {
            foreach (self::DEFAULT_TAGS as $i => [$l, $ic, $c]) {
                $this->db->table('barometre_tags')->insert(['site_id' => $this->siteId, 'label' => $l, 'icon' => $ic, 'color' => $c, 'sort_order' => $i]);
            }
            $tags = $q();
        }

        return $tags;
    }

    public function secteurs(): array
    {
        return $this->db->table('secteurs')->select('id, name')->where('site_id', $this->siteId)->orderBy('name')->get()->getResultArray();
    }

    /** @return array|string identifiant créé, ou message d'erreur */
    public function add(int $userId, ?int $secteurId, int $ambiance, array $tags, string $comment, string $date): int|string
    {
        if ($ambiance < 1 || $ambiance > 5) {
            return 'Choisissez l’ambiance.';
        }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return 'La date n’est pas valide.';
        }
        if ($date > date('Y-m-d')) {
            return 'La date ne peut pas être dans le futur.';
        }
        if ($secteurId && ! $this->db->table('secteurs')->where(['id' => $secteurId, 'site_id' => $this->siteId])->countAllResults()) {
            return 'Choisissez un secteur de votre site.';
        }
        $allowed = array_column($this->tags(), 'label');
        $tags    = array_values(array_intersect(array_unique(array_map('trim', $tags)), $allowed));
        $this->db->table('barometre_entries')->insert([
            'site_id' => $this->siteId, 'user_id' => $userId, 'secteur_id' => $secteurId ?: null, 'ambiance' => $ambiance,
            'tags' => mb_substr(implode(',', $tags), 0, 500), 'comment' => $comment, 'entry_date' => $date,
        ]);

        return (int) $this->db->insertID();
    }

    public function delete(int $id): bool
    {
        $this->db->table('barometre_entries')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }

    /** Remontées depuis N mois (même borne que la 2.x : strtotime("-N months")). */
    public function entries(int $months): array
    {
        return $this->db->table('barometre_entries b')
            ->select("b.*, s.name AS secteur_name, CONCAT(u.first_name, ' ', u.last_name) AS user_name", false)
            ->join('secteurs s', 's.id = b.secteur_id', 'left')->join('users u', 'u.id = b.user_id', 'left')
            ->where('b.site_id', $this->siteId)->where('b.entry_date >=', date('Y-m-d', strtotime("-{$months} months")))
            ->orderBy('b.entry_date', 'DESC')->orderBy('b.created_at', 'DESC')->orderBy('b.id', 'DESC')->get()->getResultArray();
    }

    /** Indicateurs du tableau de bord, calculés exactement comme la 2.x. */
    public static function stats(array $entries): array
    {
        $s = ['total' => count($entries), 'avg_ambiance' => 0, 'top_tags' => [], 'by_secteur' => []];
        if (! $entries) {
            return $s;
        }
        $s['avg_ambiance'] = round(array_sum(array_column($entries, 'ambiance')) / $s['total'], 1);
        $count = [];
        foreach ($entries as $e) {
            foreach (array_filter(array_map('trim', explode(',', (string) $e['tags']))) as $t) {
                $count[$t] = ($count[$t] ?? 0) + 1;
            }
        }
        arsort($count);
        $s['top_tags'] = array_slice($count, 0, 8, true);
        foreach ($entries as $e) {
            $n = $e['secteur_name'] ?: 'Non précisé';
            $s['by_secteur'][$n] ??= ['count' => 0, 'total_amb' => 0];
            $s['by_secteur'][$n]['count']++;
            $s['by_secteur'][$n]['total_amb'] += (int) $e['ambiance'];
        }
        foreach ($s['by_secteur'] as &$b) {
            $b['avg'] = round($b['total_amb'] / $b['count'], 1);
        }
        unset($b);
        uasort($s['by_secteur'], static fn ($a, $b) => $a['avg'] <=> $b['avg']);

        return $s;
    }
}
