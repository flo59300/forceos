<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;

/**
 * Agenda syndical d'un site (table events, partagée avec la 2.x).
 * Mêmes règles que pages/agenda.php : anti-doublon titre + date + heure, export et import iCalendar.
 */
class Agenda
{
    public const TYPES = [
        'reunion'    => 'Réunion',
        'permanence' => 'Permanence',
        'formation'  => 'Formation',
        'evenement'  => 'Événement',
        'election'   => 'Élection',
        'autre'      => 'Autre',
    ];

    /** Correspondance CATEGORIES iCalendar vers type (import), comme la 2.x. */
    private const ICS_TYPES = ['réunion' => 'reunion', 'reunion' => 'reunion', 'permanence' => 'permanence', 'formation' => 'formation', 'élection' => 'election', 'election' => 'election'];

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    /** Événements d'un mois, avec le nom de l'auteur. */
    public function month(int $year, int $month): array
    {
        $first = sprintf('%04d-%02d-01', $year, $month);

        return $this->db->table('events e')
            ->select("e.*, CONCAT(u.first_name, ' ', u.last_name) AS creator_name", false)
            ->join('users u', 'u.id = e.created_by', 'left')
            ->where('e.site_id', $this->siteId)
            ->where('e.event_date >=', $first)->where('e.event_date <=', date('Y-m-t', strtotime($first)))
            ->orderBy('e.event_date')->orderBy('e.start_time')->get()->getResultArray();
    }

    /**
     * Prochains événements (la règle de la 2.x : on écarte ceux du jour dont l'heure est passée).
     */
    public function upcoming(int $limit = 5, ?string $until = null): array
    {
        $b = $this->db->table('events')->where('site_id', $this->siteId)
            ->where("(event_date > CURDATE()
                OR (event_date = CURDATE() AND end_time IS NOT NULL AND end_time > CURTIME())
                OR (event_date = CURDATE() AND end_time IS NULL AND start_time IS NOT NULL AND start_time > CURTIME())
                OR (event_date = CURDATE() AND end_time IS NULL AND start_time IS NULL))", null, false);
        if ($until) {
            $b->where('event_date <=', $until);
        }

        return $b->orderBy('event_date')->orderBy('start_time')->limit($limit)->get()->getResultArray();
    }

    public function find(int $id): ?array
    {
        return $this->db->table('events e')
            ->select("e.*, CONCAT(u.first_name, ' ', u.last_name) AS creator_name", false)
            ->join('users u', 'u.id = e.created_by', 'left')
            ->where(['e.id' => $id, 'e.site_id' => $this->siteId])->get()->getRowArray();
    }

    /** Un événement identique (même titre, même date, même heure de début) existe-t-il déjà ? */
    public function duplicateOf(string $title, string $date, ?string $start, int $exceptId = 0): bool
    {
        $b = $this->db->table('events')->where(['site_id' => $this->siteId, 'title' => $title, 'event_date' => $date]);
        $start ? $b->where('start_time', $start) : $b->where('start_time IS NULL');
        if ($exceptId) {
            $b->where('id !=', $exceptId);
        }

        return $b->countAllResults() > 0;
    }

    public function save(array $d, int $userId, int $id = 0): int
    {
        $row = [
            'title' => $d['title'], 'event_date' => $d['event_date'],
            'start_time' => $d['start_time'] ?: null, 'end_time' => $d['end_time'] ?: null,
            'event_type' => $d['event_type'], 'description' => $d['description'], 'location' => $d['location'],
        ];
        if ($id) {
            $this->db->table('events')->where(['id' => $id, 'site_id' => $this->siteId])->update($row);

            return $id;
        }
        $this->db->table('events')->insert($row + ['site_id' => $this->siteId, 'created_by' => $userId]);

        return (int) $this->db->insertID();
    }

    public function duplicate(int $id, int $userId): ?int
    {
        $e = $this->find($id);
        if (! $e) {
            return null;
        }
        $this->db->table('events')->insert([
            'site_id' => $this->siteId, 'title' => $e['title'] . ' (copie)', 'description' => $e['description'],
            'event_date' => $e['event_date'], 'start_time' => $e['start_time'], 'end_time' => $e['end_time'],
            'event_type' => $e['event_type'], 'location' => $e['location'], 'created_by' => $userId,
        ]);

        return (int) $this->db->insertID();
    }

    public function delete(int $id): bool
    {
        $this->db->table('events')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }

    // ------------------------------------------------------------------
    // iCalendar
    // ------------------------------------------------------------------

    public static function icsEscape(string $s): string
    {
        // Même ordre que la 2.x pour un export identique.
        return str_replace(["\r\n", "\n", "\r", ',', ';', '\\'], ['\\n', '\\n', '\\n', '\\,', '\\;', '\\\\'], $s);
    }

    public static function icsUnescape(string $s): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $s);
    }

    /**
     * Calendrier complet du site, ou un seul événement (même structure que la 2.x).
     * Sortie identique à la 2.x, à une exception près : les lignes de plus de 75 octets sont repliées
     * comme l'exige la norme (RFC 5545), ce que la 2.x ne faisait pas et que certains agendas refusaient.
     */
    public function ics(string $appName, string $siteName, ?int $eventId = null): ?string
    {
        if ($eventId !== null) {
            $e = $this->db->table('events')->where(['id' => $eventId, 'site_id' => $this->siteId])->get()->getRowArray();
            if (! $e) {
                return null;
            }
            $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//{$appName}//Agenda//FR\r\n";
            $out .= $this->vevent($e, gmdate('Ymd\THis\Z'), false);

            return $out . "END:VCALENDAR\r\n";
        }
        $events = $this->db->table('events')->where('site_id', $this->siteId)->orderBy('event_date')->get()->getResultArray();
        $out    = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//{$appName}//Agenda Syndical//FR\r\nCALSCALE:GREGORIAN\r\n";
        $out .= self::fold("X-WR-CALNAME:Agenda FO {$siteName}") . "\r\n";
        foreach ($events as $e) {
            $out .= $this->vevent($e, gmdate('Ymd\THis\Z', strtotime($e['created_at'])), true);
        }

        return $out . "END:VCALENDAR\r\n";
    }

    private function vevent(array $e, string $stamp, bool $withCategory): string
    {
        $day = date('Ymd', strtotime($e['event_date']));
        $l   = ['BEGIN:VEVENT', 'UID:fo-event-' . $e['id'] . '@hub-fo', 'DTSTAMP:' . $stamp];
        if ($e['start_time']) {
            $l[] = 'DTSTART:' . $day . 'T' . str_replace(':', '', substr($e['start_time'], 0, 5)) . '00';
            if ($e['end_time']) {
                $l[] = 'DTEND:' . $day . 'T' . str_replace(':', '', substr($e['end_time'], 0, 5)) . '00';
            }
        } else {
            $l[] = 'DTSTART;VALUE=DATE:' . $day;
        }
        $l[] = 'SUMMARY:' . self::icsEscape($e['title']);
        if ($e['description']) {
            $l[] = 'DESCRIPTION:' . self::icsEscape($e['description']);
        }
        if ($e['location']) {
            $l[] = 'LOCATION:' . self::icsEscape($e['location']);
        }
        if ($withCategory) {
            $l[] = 'CATEGORIES:' . (self::TYPES[$e['event_type']] ?? 'Autre');
        }
        $l[] = 'END:VEVENT';

        return implode("\r\n", array_map([self::class, 'fold'], $l)) . "\r\n";
    }

    /** Repli des lignes à 75 octets sans couper un caractère UTF-8 (RFC 5545, § 3.1). */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $cur = '';
        $max = 75;
        foreach (mb_str_split($line) as $ch) {
            if (strlen($cur) + strlen($ch) > $max) {
                $out .= $cur . "\r\n ";
                $cur = '';
                $max = 74;
            }
            $cur .= $ch;
        }

        return $out . $cur;
    }

    /**
     * Import d'un fichier .ics (même analyse que la 2.x, lignes repliées en plus).
     * @return array{imported:int, skipped:int}
     */
    public function import(string $content, int $userId): array
    {
        // Lignes repliées : une ligne qui commence par une espace prolonge la précédente.
        $content = preg_replace("/\r?\n[ \t]/", '', $content);
        $imported = $skipped = 0;
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);
        foreach ($matches[1] as $block) {
            $ev = ['title' => '', 'date' => '', 'start' => null, 'end' => null, 'desc' => '', 'location' => '', 'type' => 'evenement'];
            if (preg_match('/SUMMARY[^:]*:(.+)/i', $block, $m)) {
                $ev['title'] = mb_substr(self::icsUnescape(trim($m[1])), 0, 255);
            }
            if (preg_match('/DTSTART[^:]*:(\d{8})(?:T(\d{4}))?/i', $block, $m)) {
                $ev['date'] = substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
                if (! empty($m[2])) {
                    $ev['start'] = substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2);
                }
            }
            if (preg_match('/DTEND[^:]*:(\d{8})(?:T(\d{4}))?/i', $block, $m) && ! empty($m[2])) {
                $ev['end'] = substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2);
            }
            if (preg_match('/DESCRIPTION[^:]*:(.+?)(?=\r?\n[A-Z])/is', $block, $m)) {
                $ev['desc'] = self::icsUnescape(trim($m[1]));
            }
            if (preg_match('/LOCATION[^:]*:(.+)/i', $block, $m)) {
                $ev['location'] = mb_substr(self::icsUnescape(trim($m[1])), 0, 255);
            }
            if (preg_match('/CATEGORIES[^:]*:(.+)/i', $block, $m)) {
                $ev['type'] = self::ICS_TYPES[mb_strtolower(trim($m[1]))] ?? 'evenement';
            }
            $participants = [];
            if (preg_match_all('/ATTENDEE[^:]*:(?:mailto:)?(.+)/i', $block, $att)) {
                foreach ($att[1] as $a) {
                    $participants[] = self::icsUnescape(trim($a));
                }
            }
            if (preg_match_all('/ATTENDEE[^;]*;[^:]*CN=([^;:]+)/i', $block, $cn)) {
                foreach ($cn[1] as $c) {
                    $c = trim(str_replace('"', '', $c));
                    if ($c !== '' && ! in_array($c, $participants, true)) {
                        array_unshift($participants, $c);
                    }
                }
            }
            if (preg_match('/ORGANIZER[^;]*;[^:]*CN=([^;:]+)/i', $block, $o)) {
                $name = trim(str_replace('"', '', $o[1]));
                if ($name !== '') {
                    array_unshift($participants, $name . ' (organisateur)');
                }
            } elseif (preg_match('/ORGANIZER[^:]*:(?:mailto:)?(.+)/i', $block, $o)) {
                array_unshift($participants, trim($o[1]) . ' (organisateur)');
            }

            // Date réelle exigée (la 2.x acceptait « 2026-13-45 » et MariaDB enregistrait 0000-00-00).
            if ($ev['title'] === '' || ! self::validDate($ev['date'])) {
                continue;
            }
            $b = $this->db->table('events')->where(['site_id' => $this->siteId, 'title' => $ev['title'], 'event_date' => $ev['date']]);
            if ($ev['start']) {
                $b->where('start_time', $ev['start']);
            }
            if ($b->countAllResults() > 0) {
                $skipped++;

                continue;
            }
            $row = [
                'site_id' => $this->siteId, 'title' => $ev['title'], 'event_date' => $ev['date'],
                'start_time' => $ev['start'], 'end_time' => $ev['end'], 'event_type' => $ev['type'],
                'description' => $ev['desc'], 'location' => $ev['location'], 'created_by' => $userId,
            ];
            $p = implode(', ', array_unique($participants));
            if ($p !== '') {
                $row['participants'] = $p;
            }
            $this->db->table('events')->insert($row);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public static function validDate(string $d): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
