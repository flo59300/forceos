<?php

namespace App\Domain;

use App\Libraries\Settings;

/**
 * Cours de bourse (Yahoo Finance), mis en cache 60 secondes comme pages/stock_api.php de la 2.x.
 *
 * Écarts voulus avec la 2.x :
 *  - la liste des entreprises suivies est propre à chaque site (bourse_companies_site_<id>), avec repli sur
 *    la liste commune de la 2.x : un secrétaire ne modifie plus la page des autres sites ;
 *  - les noms saisis sont affichés comme du texte (la 2.x les injectait tels quels dans la page, ce qui
 *    permettait d'y glisser du code exécuté chez tous les utilisateurs) ;
 *  - un symbole ne peut contenir que des lettres, chiffres, point, tiret, ^ ou =.
 */
class Stocks
{
    public const STELLANTIS = ['STLA' => 'Stellantis'];
    public const INDICES    = ['^FCHI' => 'CAC 40', '^GSPC' => 'S&P 500', '^FTSE' => 'FTSE 100', '^GDAXI' => 'DAX', '^N225' => 'Nikkei 225'];
    public const DEFAULTS   = ['STLA' => 'Stellantis', 'RNO.PA' => 'Renault', 'TM' => 'Toyota', 'BYDDF' => 'BYD', 'VOW3.DE' => 'Volkswagen'];

    /** Pour les tests : réponses simulées par symbole. */
    public static ?array $fake = null;

    public function __construct(private int $siteId)
    {
    }

    public static function validSymbol(string $s): bool
    {
        return (bool) preg_match('/^[A-Z0-9.^=\-]{1,15}$/', $s);
    }

    private function key(): string
    {
        return 'bourse_companies_site_' . $this->siteId;
    }

    /** Entreprises suivies par le site : sa liste, sinon la liste commune de la 2.x, sinon la liste par défaut. */
    public function companies(): array
    {
        foreach ([$this->key(), 'bourse_companies'] as $k) {
            $list = json_decode(Settings::get($k), true);
            if (is_array($list)) {
                $out = [];
                foreach ($list as $i) {
                    $sym = strtoupper(trim((string) ($i['symbol'] ?? '')));
                    $nm  = trim((string) ($i['name'] ?? ''));
                    if ($nm !== '' && self::validSymbol($sym)) {
                        $out[$sym] = mb_substr($nm, 0, 60);
                    }
                }
                if ($out) {
                    return $out;
                }
            }
        }

        return self::DEFAULTS;
    }

    /** @param array<string,string> $list symbole => nom ; liste vide = retour à la liste par défaut */
    public function save(array $list): void
    {
        $rows = [];
        foreach ($list as $s => $n) {
            $rows[] = ['symbol' => $s, 'name' => $n];
        }
        Settings::set($this->key(), $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE) : '');
        cache()->delete('stocks_companies_' . $this->siteId);
    }

    /** Cours d'un groupe : stellantis, companies ou indices. */
    public function quotes(string $group): array
    {
        $symbols = match ($group) {
            'stellantis' => self::STELLANTIS,
            'companies'  => $this->companies(),
            'indices'    => self::INDICES,
            default      => [],
        };
        if (! $symbols) {
            return [];
        }
        $key = 'stocks_' . $group . ($group === 'companies' ? '_' . $this->siteId : '');
        $hit = self::$fake === null ? cache($key) : null;
        if (is_array($hit)) {
            return $hit;
        }
        $out = [];
        foreach ($symbols as $sym => $name) {
            $q = self::quote($sym);
            // ForceOS : dernier cours connu conservé, affiché hors ligne avec sa date
            $dernierCle = 'stocks_dernier_' . md5($sym);
            if ($q && self::$fake === null) {
                cache()->save($dernierCle, $q, 2592000);
            } elseif (! $q && self::$fake === null && is_array($dernier = cache($dernierCle))) {
                $q = $dernier + ['horsLigne' => true];
            }
            if ($q) {
                $out[] = $q + ['symbol' => $sym, 'name' => $name];
            }
        }
        if (self::$fake === null) {
            cache()->save($key, $out, 60);
        }

        return $out;
    }

    /** Un cours (mêmes champs et mêmes arrondis que la 2.x). */
    private static function quote(string $symbol): ?array
    {
        $raw = self::$fake !== null ? (self::$fake[$symbol] ?? null) : self::download('https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?interval=1d&range=2d');
        $j   = $raw ? json_decode($raw, true) : null;
        $m   = $j['chart']['result'][0]['meta'] ?? null;
        if (! is_array($m)) {
            return null;
        }
        $price = (float) ($m['regularMarketPrice'] ?? 0);
        $prev  = (float) ($m['chartPreviousClose'] ?? $m['previousClose'] ?? 0);
        $chg   = $prev > 0 ? $price - $prev : 0;

        return [
            'price' => round($price, 2), 'change' => round($chg, 2), 'changePct' => round($prev > 0 ? $chg / $prev * 100 : 0, 2),
            'prevClose' => round($prev, 2), 'currency' => (string) ($m['currency'] ?? 'USD'),
            'marketState' => (string) ($m['marketState'] ?? 'CLOSED'),
            'timestamp' => (int) ($m['regularMarketTime'] ?? time()), 'timezone' => (string) ($m['exchangeTimezoneName'] ?? 'Europe/Paris'),
        ];
    }

    private static function download(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FOGestion/3.0)', CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $r    = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ($code === 200 && is_string($r) && $r !== '') ? $r : null;
    }
}
