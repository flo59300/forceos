<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Questions des délégués au secrétaire (table delegate_questions, partagée avec la 2.x).
 */
class Questions
{
    public const STATUSES = ['ouverte' => 'Ouverte', 'en_cours' => 'En cours', 'resolue' => 'Résolue', 'fermee' => 'Fermée'];
    public const OPEN     = ['ouverte', 'en_cours'];

    public function __construct(private BaseConnection $db)
    {
    }

    /** Questions d'un délégué, ou de tout le site pour le secrétaire. */
    public function list(int $siteId, ?int $authorId, ?string $filter = null): array
    {
        $q = $this->db->table('delegate_questions q')
            ->select("q.*, CONCAT(a.first_name, ' ', a.last_name) AS author_name, CONCAT(r.first_name, ' ', r.last_name) AS responder_name")
            ->join('users a', 'a.id = q.author_id')
            ->join('users r', 'r.id = q.responded_by', 'left')
            ->where('q.site_id', $siteId);
        if ($authorId !== null) {
            $q->where('q.author_id', $authorId);
        }
        if ($filter === 'ouvertes') {
            $q->whereIn('q.status', self::OPEN);
        } elseif ($filter === 'repondues') {
            $q->whereIn('q.status', ['resolue', 'fermee']);
        }

        return $q->orderBy("FIELD(q.status, 'ouverte', 'en_cours', 'resolue', 'fermee')", '', false)
            ->orderBy("FIELD(q.priority, 'urgente', 'normale')", '', false)
            ->orderBy('q.created_at', 'DESC')->get()->getResultArray();
    }

    public function counts(int $siteId, ?int $authorId): array
    {
        $q = $this->db->table('delegate_questions')->select('status, COUNT(*) AS n')->where('site_id', $siteId)->groupBy('status');
        if ($authorId !== null) {
            $q->where('author_id', $authorId);
        }
        $c = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($q->get()->getResultArray() as $r) {
            $c[$r['status']] = (int) $r['n'];
        }

        return $c + ['open' => $c['ouverte'] + $c['en_cours']];
    }

    public function find(int $id, int $siteId): ?array
    {
        return $this->db->table('delegate_questions')->where(['id' => $id, 'site_id' => $siteId])->get()->getRowArray();
    }

    /** Réponses reçues ces sept derniers jours (carte « À faire maintenant »). */
    public function recentAnswers(int $authorId): array
    {
        return $this->db->table('delegate_questions')
            ->where('author_id', $authorId)->where('response IS NOT NULL')
            ->where('responded_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->orderBy('responded_at', 'DESC')->get()->getResultArray();
    }
}
