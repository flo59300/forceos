<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Permanences syndicales d'un site : créneaux, inscriptions, présence (tables permanences,
 * permanence_slots, permanence_bookings, partagées avec la 2.x et ses liens publics booking.php / verify.php).
 *
 * Écarts voulus avec la 2.x :
 *  - les places sont contrôlées sous verrou : deux réservations simultanées ne peuvent plus dépasser la capacité ;
 *  - une date impossible (31 février) est refusée au lieu d'être enregistrée à 0000-00-00 ;
 *  - un créneau ne peut plus recevoir moins de places qu'il n'a déjà d'inscrits.
 */
class Permanences
{
    /** Statuts qui occupent une place (identiques à la 2.x). */
    public const HOLDING = ['pending', 'email_sent', 'confirmed'];

    public const STATUS = ['pending' => 'En attente', 'email_sent' => 'Confirmation envoyée', 'confirmed' => 'Confirmée', 'cancelled' => 'Annulée'];

    public const MAX_DATES = 60;

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public static function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function validDate(string $d): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function validTime(string $t): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
    }

    // ------------------------------------------------------------------
    // Lecture
    // ------------------------------------------------------------------

    /**
     * Toutes les permanences du site avec créneaux, inscriptions et compteurs (mêmes calculs que la 2.x).
     */
    public function all(): array
    {
        $perms = $this->db->table('permanences p')
            ->select("p.*, CONCAT(u.first_name, ' ', u.last_name) AS creator_name", false)
            ->join('users u', 'u.id = p.created_by', 'left')
            ->where('p.site_id', $this->siteId)->orderBy('p.perm_date', 'DESC')->orderBy('p.id', 'DESC')
            ->get()->getResultArray();

        return $this->hydrate($perms);
    }

    public function find(int $id): ?array
    {
        $p = $this->db->table('permanences p')
            ->select("p.*, CONCAT(u.first_name, ' ', u.last_name) AS creator_name", false)
            ->join('users u', 'u.id = p.created_by', 'left')
            ->where(['p.id' => $id, 'p.site_id' => $this->siteId])->get()->getRowArray();

        return $p ? $this->hydrate([$p])[0] : null;
    }

    /** Ajoute créneaux, inscriptions actives et compteurs à une liste de permanences. */
    public function hydrate(array $perms): array
    {
        if (! $perms) {
            return [];
        }
        $ids   = array_column($perms, 'id');
        $slots = $this->db->table('permanence_slots ps')
            ->select("ps.*, (SELECT COUNT(*) FROM permanence_bookings pb WHERE pb.slot_id = ps.id AND pb.status IN ('pending','email_sent','confirmed')) AS nb_bookings", false)
            ->whereIn('ps.permanence_id', $ids)->orderBy('ps.permanence_id')->orderBy('ps.start_time')->get()->getResultArray();
        $books = $this->db->table('permanence_bookings pb')
            ->select("pb.*, ps.start_time, ps.end_time, ps.permanence_id, CONCAT(u.first_name, ' ', u.last_name) AS booked_by_name", false)
            ->join('permanence_slots ps', 'ps.id = pb.slot_id')
            ->join('users u', 'u.id = pb.booked_by', 'left')
            ->whereIn('ps.permanence_id', $ids)->whereIn('pb.status', self::HOLDING)
            ->orderBy('ps.permanence_id')->orderBy('pb.status')->orderBy('ps.start_time')->orderBy('pb.name')->get()->getResultArray();
        $sBy = $bBy = [];
        foreach ($slots as $s) {
            $sBy[$s['permanence_id']][] = $s;
        }
        foreach ($books as $b) {
            $bBy[$b['permanence_id']][] = $b;
        }
        foreach ($perms as &$p) {
            $p['slots']    = $sBy[$p['id']] ?? [];
            $p['bookings'] = $bBy[$p['id']] ?? [];
            $count         = static fn (string $st) => count(array_filter($p['bookings'], static fn ($b) => $b['status'] === $st));
            $p['total_bookings']   = count($p['bookings']);
            $p['pending_count']    = $count('pending');
            $p['email_sent_count'] = $count('email_sent');
            $p['confirmed_count']  = $count('confirmed');
            $p['attended_count']   = count(array_filter($p['bookings'], static fn ($b) => (string) $b['attended'] === '1'));
            $p['total_capacity']   = array_sum(array_map('intval', array_column($p['slots'], 'max_bookings')));
            $p['capacity']         = $p['max_participants'] ? (int) $p['max_participants'] : $p['total_capacity'];
            $p['fill_pct']         = $p['total_capacity'] > 0 && $p['capacity'] > 0 ? (int) round($p['total_bookings'] / $p['capacity'] * 100) : 0;
            $p['quota_reached']    = $p['max_participants'] && $p['total_bookings'] >= (int) $p['max_participants'];
        }
        unset($p);

        return $perms;
    }

    /** Indicateurs du haut de page (permanences à venir), comme la 2.x. */
    public static function stats(array $upcoming): array
    {
        $cap = array_sum(array_column($upcoming, 'total_capacity'));

        return [
            'upcoming'   => count($upcoming),
            'pending'    => array_sum(array_column($upcoming, 'pending_count')),
            'email_sent' => array_sum(array_column($upcoming, 'email_sent_count')),
            'confirmed'  => array_sum(array_column($upcoming, 'confirmed_count')),
            'fill'       => $cap > 0 ? (int) round(array_sum(array_column($upcoming, 'total_bookings')) / $cap * 100) : 0,
        ];
    }

    /** Pour « Ma journée » : demandes en attente de validation sur les permanences à venir. */
    public function pendingCount(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) AS n FROM permanence_bookings pb JOIN permanence_slots ps ON ps.id = pb.slot_id JOIN permanences p ON p.id = ps.permanence_id
             WHERE p.site_id = ? AND p.perm_date >= CURDATE() AND pb.status = 'pending'", [$this->siteId]
        )->getRow()->n;
    }

    /** Pour « Ma journée » : permanences actives des prochains jours. */
    public function next(string $until, int $limit = 3): array
    {
        return $this->db->table('permanences')->where('site_id', $this->siteId)->where('active', 1)
            ->where('perm_date >=', date('Y-m-d'))->where('perm_date <=', $until)
            ->orderBy('perm_date')->limit($limit)->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    // Gestion (secrétaire)
    // ------------------------------------------------------------------

    /**
     * Crée une permanence par date (série si plusieurs dates, même jeton de série pour le calendrier public).
     * @param list<string> $dates
     * @param list<array{0:string,1:string}> $slots
     * @return array{created:int, series:?string, ids:list<int>}
     */
    public function create(array $d, array $dates, array $slots, int $userId): array
    {
        $series = count($dates) > 1 ? self::token() : null;
        $ids    = [];
        $this->db->transStart();
        foreach ($dates as $date) {
            $this->db->table('permanences')->insert([
                'site_id' => $this->siteId, 'title' => $d['title'], 'description' => $d['description'] ?: null,
                'location' => $d['location'] ?: null, 'perm_date' => $date, 'max_participants' => $d['max_participants'],
                'token' => self::token(), 'series_token' => $series, 'created_by' => $userId,
            ]);
            $pid   = (int) $this->db->insertID();
            $ids[] = $pid;
            foreach ($slots as [$s, $e]) {
                $this->db->table('permanence_slots')->insert(['permanence_id' => $pid, 'start_time' => $s, 'end_time' => $e, 'max_bookings' => $d['max_bookings']]);
            }
        }
        $this->db->transComplete();

        return ['created' => count($ids), 'series' => $series, 'ids' => $ids];
    }

    public function update(int $id, array $d): void
    {
        $this->db->table('permanences')->where(['id' => $id, 'site_id' => $this->siteId])->update([
            'title' => $d['title'], 'description' => $d['description'] ?: null, 'location' => $d['location'] ?: null,
            'perm_date' => $d['perm_date'], 'max_participants' => $d['max_participants'],
        ]);
    }

    /** Copie identique (créneaux, quota, série) à une autre date. */
    public function duplicate(int $id, string $date, int $userId): ?int
    {
        $src = $this->db->table('permanences')->where(['id' => $id, 'site_id' => $this->siteId])->get()->getRowArray();
        if (! $src) {
            return null;
        }
        $this->db->transStart();
        $this->db->table('permanences')->insert([
            'site_id' => $this->siteId, 'title' => $src['title'], 'description' => $src['description'], 'location' => $src['location'],
            'perm_date' => $date, 'max_participants' => $src['max_participants'], 'token' => self::token(),
            'series_token' => $src['series_token'], 'created_by' => $userId,
        ]);
        $new = (int) $this->db->insertID();
        foreach ($this->db->table('permanence_slots')->where('permanence_id', $id)->get()->getResultArray() as $s) {
            $this->db->table('permanence_slots')->insert(['permanence_id' => $new, 'start_time' => $s['start_time'], 'end_time' => $s['end_time'], 'max_bookings' => $s['max_bookings']]);
        }
        $this->db->transComplete();

        return $new;
    }

    public function toggle(int $id): ?bool
    {
        $p = $this->db->table('permanences')->select('active')->where(['id' => $id, 'site_id' => $this->siteId])->get()->getRow();
        if (! $p) {
            return null;
        }
        $this->db->table('permanences')->where('id', $id)->update(['active' => $p->active ? 0 : 1]);

        return ! $p->active;
    }

    public function delete(int $id): bool
    {
        $this->db->table('permanences')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }

    public function addSlot(int $permId, string $start, string $end, int $max): bool
    {
        if (! $this->db->table('permanences')->where(['id' => $permId, 'site_id' => $this->siteId])->countAllResults()) {
            return false;
        }
        $this->db->table('permanence_slots')->insert(['permanence_id' => $permId, 'start_time' => $start, 'end_time' => $end, 'max_bookings' => $max]);

        return true;
    }

    /** @return string 'ok' | 'booked' | 'missing' */
    public function deleteSlot(int $slotId): string
    {
        $s = $this->slot($slotId);
        if (! $s) {
            return 'missing';
        }
        if ((int) $s['nb_bookings'] > 0) {
            return 'booked';
        }
        $this->db->table('permanence_slots')->where('id', $slotId)->delete();

        return 'ok';
    }

    /** Créneau du site, avec son nombre d'inscrits actifs. */
    public function slot(int $slotId): ?array
    {
        return $this->db->table('permanence_slots ps')
            ->select("ps.*, p.max_participants, p.id AS perm_id, (SELECT COUNT(*) FROM permanence_bookings pb WHERE pb.slot_id = ps.id AND pb.status IN ('pending','email_sent','confirmed')) AS nb_bookings", false)
            ->join('permanences p', 'p.id = ps.permanence_id')
            ->where(['ps.id' => $slotId, 'p.site_id' => $this->siteId])->get()->getRowArray();
    }

    /**
     * Inscription saisie par le secrétaire : confirmée d'office (comme la 2.x).
     * @return string 'ok' | 'full' | 'missing'
     */
    public function manualBooking(int $slotId, array $d, int $userId): string
    {
        $s = $this->slot($slotId);
        if (! $s) {
            return 'missing';
        }

        return self::reserve($this->db, (int) $s['perm_id'], $slotId, [
            'name' => $d['name'], 'phone' => $d['phone'] ?: null, 'email' => $d['email'] ?: null,
            'subject' => $d['subject'] ?: null, 'notes' => $d['notes'] ?: null, 'booked_by' => $userId, 'status' => 'confirmed',
        ]) ? 'ok' : 'full';
    }

    /**
     * Réserve une place sous verrou (la permanence est verrouillée le temps du contrôle et de l'insertion).
     * Contrôle la capacité du créneau et, s'il existe, le quota global de la permanence.
     */
    public static function reserve(BaseConnection $db, int $permId, int $slotId, array $row, ?callable $extraCheck = null): bool|string
    {
        $db->transBegin();
        try {
            $perm = $db->query('SELECT id, max_participants FROM permanences WHERE id = ? FOR UPDATE', [$permId])->getRowArray();
            $slot = $db->query('SELECT id, max_bookings FROM permanence_slots WHERE id = ? AND permanence_id = ?', [$slotId, $permId])->getRowArray();
            if (! $perm || ! $slot) {
                $db->transRollback();

                return false;
            }
            $inSlot = (int) $db->query("SELECT COUNT(*) AS n FROM permanence_bookings WHERE slot_id = ? AND status IN ('pending','email_sent','confirmed')", [$slotId])->getRow()->n;
            if ($perm['max_participants']) {
                $total = (int) $db->query("SELECT COUNT(*) AS n FROM permanence_bookings pb JOIN permanence_slots ps ON pb.slot_id = ps.id WHERE ps.permanence_id = ? AND pb.status IN ('pending','email_sent','confirmed')", [$permId])->getRow()->n;
                if ($total >= (int) $perm['max_participants']) {
                    $db->transRollback();

                    return 'quota';
                }
            }
            if ($inSlot >= (int) $slot['max_bookings']) {
                $db->transRollback();

                return 'slot';
            }
            if ($extraCheck && ($err = $extraCheck()) !== null) {
                $db->transRollback();

                return $err;
            }
            $db->table('permanence_bookings')->insert($row + ['slot_id' => $slotId]);
            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /** Inscription du site (quel que soit son statut). */
    public function booking(int $id): ?array
    {
        return $this->db->table('permanence_bookings pb')
            ->select('pb.*, ps.start_time, ps.end_time, p.id AS perm_id, p.title, p.perm_date, p.location')
            ->join('permanence_slots ps', 'ps.id = pb.slot_id')->join('permanences p', 'p.id = ps.permanence_id')
            ->where(['pb.id' => $id, 'p.site_id' => $this->siteId])->get()->getRowArray();
    }

    /**
     * Validation d'une demande en attente.
     * Avec une adresse : passe en « confirmation envoyée » et renvoie le jeton du lien à envoyer.
     * Sans adresse : confirmée directement (comme la 2.x).
     * @return array{booking:array, token:?string}|null
     */
    public function validate(int $id): ?array
    {
        $b = $this->booking($id);
        if (! $b || $b['status'] !== 'pending') {
            return null;
        }
        if ($b['email']) {
            $t = self::token();
            $this->db->table('permanence_bookings')->where('id', $id)->update(['status' => 'email_sent', 'verification_token' => $t]);

            return ['booking' => $b, 'token' => $t];
        }
        $this->db->table('permanence_bookings')->where('id', $id)->update(['status' => 'confirmed']);

        return ['booking' => $b, 'token' => null];
    }

    /** Envoi impossible : la demande est confirmée directement, comme la 2.x l'avait prévu. */
    public function confirmDirectly(int $id): void
    {
        $this->db->table('permanence_bookings')->where('id', $id)->update(['status' => 'confirmed', 'verification_token' => null]);
    }

    /** Demandes en attente d'une permanence (pour « Tout valider »). */
    public function pendingOf(int $permId): array
    {
        return array_column($this->db->table('permanence_bookings pb')->select('pb.id')
            ->join('permanence_slots ps', 'ps.id = pb.slot_id')->join('permanences p', 'p.id = ps.permanence_id')
            ->where(['ps.permanence_id' => $permId, 'p.site_id' => $this->siteId, 'pb.status' => 'pending'])->get()->getResultArray(), 'id');
    }

    /** Refus (demande en attente ou confirmation envoyée) ou annulation (toute inscription active). */
    public function cancel(int $id, bool $onlyPending): bool
    {
        $b = $this->booking($id);
        if (! $b || ! in_array($b['status'], $onlyPending ? ['pending', 'email_sent'] : self::HOLDING, true)) {
            return false;
        }
        $this->db->table('permanence_bookings')->where('id', $id)->update(['status' => 'cancelled', 'verification_token' => null]);

        return true;
    }

    public function attendance(int $id, ?bool $present): bool
    {
        $b = $this->booking($id);
        if (! $b || $b['status'] !== 'confirmed') {
            return false;
        }
        $this->db->table('permanence_bookings')->where('id', $id)->update(['attended' => $present === null ? null : (int) $present]);

        return true;
    }

    public function note(int $id, string $note): bool
    {
        if (! $this->booking($id)) {
            return false;
        }
        $this->db->table('permanence_bookings')->where('id', $id)->update(['notes' => $note !== '' ? $note : null]);

        return true;
    }

    /** Lignes du fichier CSV (mêmes colonnes que la 2.x), cellules neutralisées. */
    public function csvRows(int $permId): ?array
    {
        if (! $this->db->table('permanences')->where(['id' => $permId, 'site_id' => $this->siteId])->countAllResults()) {
            return null;
        }
        $rows = $this->db->table('permanence_bookings pb')->select('pb.*, ps.start_time, ps.end_time')
            ->join('permanence_slots ps', 'ps.id = pb.slot_id')
            ->where('ps.permanence_id', $permId)->whereIn('pb.status', self::HOLDING)
            ->orderBy('ps.start_time')->orderBy('pb.name')->get()->getResultArray();
        $out = [['Créneau', 'Nom', 'Téléphone', 'Email', 'Motif', 'Statut', 'Présence', 'Notes', 'Inscrit le']];
        foreach ($rows as $b) {
            $out[] = array_map('csv_safe', [
                substr($b['start_time'], 0, 5) . '-' . substr($b['end_time'], 0, 5), $b['name'], $b['phone'] ?? '', $b['email'] ?? '',
                $b['subject'] ?? '', $b['status'] === 'confirmed' ? 'Validé' : 'En attente',
                $b['attended'] === null ? '' : ($b['attended'] ? 'Présent' : 'Absent'),
                $b['notes'] ?? '', date('d/m/Y H:i', strtotime($b['created_at'])),
            ]);
        }

        return $out;
    }
}
