<?php

namespace App\Domain;

use CodeIgniter\Database\BaseConnection;
use SimpleXMLElement;

/**
 * Fil d'actualité : flux RSS du site (table rss_feeds, partagée avec la 2.x).
 *
 * Écarts voulus avec la 2.x :
 *  - le serveur refuse d'interroger une adresse interne (réseau local, 127.0.0.1, métadonnées d'hébergeur) :
 *    la 2.x allait chercher n'importe quelle adresse saisie, y compris sur le réseau privé de l'hébergeur ;
 *  - un lien d'article qui n'est pas en http(s) (javascript:…) n'est jamais rendu cliquable ;
 *  - chaque flux est mis en cache 15 minutes : la page ne recharge plus tous les flux à chaque visite ;
 *  - les flux Atom sont lus, en plus des flux RSS.
 */
class Feeds
{
    public const CACHE_TTL = 900;
    private const MAX_BYTES = 2_000_000;

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public function all(): array
    {
        return $this->db->table('rss_feeds')->where('site_id', $this->siteId)->orderBy('title')->get()->getResultArray();
    }

    public function add(string $title, string $url): int
    {
        $this->db->table('rss_feeds')->insert(['site_id' => $this->siteId, 'title' => $title, 'url' => $url]);

        return (int) $this->db->insertID();
    }

    public function delete(int $id): bool
    {
        $this->db->table('rss_feeds')->where(['id' => $id, 'site_id' => $this->siteId])->delete();

        return $this->db->affectedRows() > 0;
    }

    public function toggle(int $id): ?bool
    {
        $f = $this->db->table('rss_feeds')->where(['id' => $id, 'site_id' => $this->siteId])->get()->getRowArray();
        if (! $f) {
            return null;
        }
        $this->db->table('rss_feeds')->where('id', $id)->update(['active' => $f['active'] ? 0 : 1]);
        cache()->delete('rss_' . md5($f['url']));

        return ! $f['active'];
    }

    /** Adresse acceptable : http(s), nom d'hôte public. */
    public static function validUrl(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) && mb_strlen($url) <= 500;
    }

    /**
     * Articles des flux actifs (30 au plus, du plus récent au plus ancien), comme la 2.x.
     * Le filtre de source reprend l'identifiant de la 2.x : md5 de l'adresse du flux.
     * @return array{items:list<array>, errors:list<string>}
     */
    public function items(?string $only = null, int $limit = 30): array
    {
        $items = $errors = [];
        foreach ($this->all() as $f) {
            if (! $f['active'] || ($only && $only !== md5($f['url']))) {
                continue;
            }
            $key = 'rss_' . md5($f['url']);
            $got = cache($key);
            if (! is_array($got)) {
                $xml = self::fetch($f['url']);
                // ForceOS : derniers articles connus conservés, consultables hors ligne
                $dernierCle = 'rss_dernier_' . md5($f['url']);
                if ($xml !== null) {
                    $got = ['items' => self::parse($xml)];
                    cache()->save($dernierCle, $got, 2592000);
                } else {
                    $dernier = cache($dernierCle);
                    $got = is_array($dernier) ? $dernier + ['horsLigne' => true] : ['error' => true];
                }
                cache()->save($key, $got, $xml === null ? 300 : self::CACHE_TTL);
                if ($xml !== null) {
                    $this->db->table('rss_feeds')->where('id', $f['id'])->update(['last_fetched' => date('Y-m-d H:i:s')]);
                }
            }
            if (! empty($got['error'])) {
                $errors[] = $f['title'];

                continue;
            }
            foreach ($got['items'] as $it) {
                $items[] = $it + ['source' => $f['title'], 'source_hash' => md5($f['url'])];
            }
        }
        usort($items, static fn ($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));

        return ['items' => array_slice($items, 0, $limit), 'errors' => $errors];
    }

    /** Lecture d'un flux (RSS 2.0, RSS 1.0 ou Atom). */
    public static function parse(string $content): array
    {
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $xml) {
            return [];
        }
        $out  = [];
        $root = strtolower($xml->getName());
        if ($root === 'feed') {
            foreach ($xml->children('http://www.w3.org/2005/Atom')->entry ?? [] as $e) {
                $link = '';
                foreach ($e->link as $l) {
                    $rel = (string) ($l['rel'] ?? 'alternate');
                    if ($rel === 'alternate' || $link === '') {
                        $link = (string) $l['href'];
                    }
                }
                $out[] = self::item((string) $e->title, $link, (string) ($e->updated ?: $e->published), (string) ($e->summary ?: $e->content));
            }
            if (! $out) {
                foreach ($xml->entry ?? [] as $e) {
                    $out[] = self::item((string) $e->title, (string) ($e->link['href'] ?? ''), (string) ($e->updated ?? ''), (string) ($e->summary ?? ''));
                }
            }
        } else {
            $list = isset($xml->channel->item) ? $xml->channel->item : ($xml->item ?? []);
            foreach ($list as $i) {
                $dc    = $i->children('http://purl.org/dc/elements/1.1/');
                $out[] = self::item((string) $i->title, (string) $i->link, (string) ($i->pubDate ?: ($dc->date ?? '')), (string) $i->description);
            }
        }

        return $out;
    }

    private static function item(string $title, string $link, string $date, string $desc): array
    {
        $desc = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $link = trim($link);
        $ts   = $date !== '' ? strtotime($date) : false;

        return [
            'title' => trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'link' => preg_match('#^https?://#i', $link) ? $link : '',
            'ts' => $ts ?: null,
            'description' => mb_substr($desc, 0, 300),
            'truncated' => mb_strlen($desc) > 300,
        ];
    }

    /** Adresse IP publique ? (refuse réseau privé, boucle locale, plages réservées et lien local) */
    public static function publicIp(string $ip): bool
    {
        if (env('fo.feedsAllowLocal')) {   // tests uniquement
            return (bool) filter_var($ip, FILTER_VALIDATE_IP);
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Télécharge un flux : http(s) seulement, adresse publique vérifiée puis imposée à la connexion
     * (pas de détournement par le DNS), trois redirections au plus, chacune revérifiée, 2 Mo au plus.
     */
    public static function fetch(string $url, int $hops = 3): ?string
    {
        for ($i = 0; $i <= $hops; $i++) {
            $p = parse_url($url);
            if (! $p || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
                return null;
            }
            $host = trim($p['host'], '[]');
            $port = (int) ($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
            $ips  = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
                array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'),
                array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
            );
            if (! $ips && ! filter_var($host, FILTER_VALIDATE_IP)) {
                $ips = gethostbynamel($host) ?: [];
            }
            if (! $ips || array_filter($ips, static fn ($ip) => ! self::publicIp($ip))) {
                return null;
            }
            $ip   = $ips[0];
            $body = '';
            $ch   = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_USERAGENT => 'FO Gestion RSS/3.0', CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5'],
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                    $body .= $chunk;

                    return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $code     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $err      = curl_errno($ch);
            curl_close($ch);
            if ($code >= 300 && $code < 400 && $location !== '') {
                $url = $location;

                continue;
            }

            return ($err === 0 && $code === 200 && $body !== '') ? $body : null;
        }

        return null;
    }
}
