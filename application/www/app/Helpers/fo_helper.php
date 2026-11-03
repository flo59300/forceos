<?php

/**
 * Fonctions d'affichage communes du hub FO.
 */

if (! function_exists('icon')) {
    /**
     * Pictogramme SVG décoratif (masqué aux lecteurs d'écran : le texte voisin porte le sens).
     */
    function icon(string $name, string $class = 'size-5'): string
    {
        $p = [
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17"/><path d="M8 3v4M16 3v4"/><path d="M8 14h2M14 14h2M8 17h2"/>',
            'pin' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.4"/>',
            'megaphone' => '<path d="M4 10v4a1 1 0 0 0 1 1h2l8 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M8 15.2 9.2 20h2.3"/><path d="M18.5 9.5a3.5 3.5 0 0 1 0 5"/>',
            'notebook' => '<rect x="5" y="3" width="14" height="18" rx="2.5"/><path d="M5 7.5H3.5M5 12H3.5M5 16.5H3.5"/><path d="M9 8h6M9 12h6M9 16h3"/>',
            'gauge' => '<path d="M4.2 17a8.5 8.5 0 1 1 15.6 0"/><path d="m12 13 3.8-4.2"/><circle cx="12" cy="13.2" r="1.4"/><path d="M6.6 11.3h.01M12 6.8h.01M17.4 11.3h.01"/>',
            'swaphand' => '<path d="M4 9h12l-3.5-3.5"/><path d="M20 15H8l3.5 3.5"/>',
            'folder' => '<path d="M3.5 7a2 2 0 0 1 2-2h4l2 2h7a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z"/><path d="M3.5 10.5h17"/>',
            'rss' => '<path d="M5 11a8 8 0 0 1 8 8"/><path d="M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.4"/>',
            'sparkles' => '<path d="M10 4.5 11.4 9l4.6 1.5-4.6 1.5L10 16.5 8.6 12 4 10.5 8.6 9Z"/><path d="M17.5 14.5l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7Z"/><path d="M17 3.5v3M15.5 5h3"/>',
            'ballot' => '<path d="M4 13.5 6.5 10h11l2.5 3.5V20H4Z"/><path d="M4 13.5h16"/><path d="m9.5 7.2 2 2 4-4.2"/>',
            'list' => '<path d="M9 6.5h11M9 12h11M9 17.5h11"/><path d="m3.5 6.5 1 1 2-2M3.5 12l1 1 2-2M3.5 17.5l1 1 2-2"/>',
            'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.8v2.4M12 18.8v2.4M2.8 12h2.4M18.8 12h2.4M5.5 5.5l1.7 1.7M16.8 16.8l1.7 1.7M5.5 18.5l1.7-1.7M16.8 7.2l1.7-1.7"/><circle cx="12" cy="12" r="6.6"/>',
            'coins' => '<ellipse cx="9" cy="7" rx="5.5" ry="2.5"/><path d="M3.5 7v4c0 1.4 2.5 2.5 5.5 2.5s5.5-1.1 5.5-2.5V7"/><path d="M9.5 15.8c.9 1.2 3 2 5.5 2 3 0 5.5-1.1 5.5-2.5v-4c0-1.3-2-2.3-4.6-2.5"/>',
            'box' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5"/><path d="M12 12v9"/>',
            'report' => '<path d="M6 3.5h8.5l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M14 3.5v4.5h4.5"/><path d="M8.5 17v-3M12 17v-5M15.5 17v-2"/>',
            'usercog' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6 1.4 0 2.6.3 3.6.9"/><circle cx="17.5" cy="17" r="2.2"/><path d="M17.5 13.3v1.5M17.5 19.2v1.5M13.8 17h1.5M19.7 17h1.5"/>',
            'sliders' => '<path d="M4 6.5h9M17 6.5h3M4 12h3M11 12h9M4 17.5h11M19 17.5h1"/><circle cx="15" cy="6.5" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="17.5" r="2"/>',
            'alarm' => '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.5 1.8"/><path d="M4 5.5 6.5 3M20 5.5 17.5 3"/>',
            'upload' => '<path d="M12 15V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/>',
            'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/><path d="M12 14.4v2.6"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c0-4 3.4-6.5 7.5-6.5s7.5 2.5 7.5 6.5"/>',
            'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 1 1 3.4 2.4c-.6.3-1 .8-1 1.5v.6"/><path d="M12 17h.01"/>',
            'layers' => '<path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 13 9 5 9-5"/>',
            'wifi' => '<path d="M2.5 9a14 14 0 0 1 19 0"/><path d="M5.5 12.5a9.5 9.5 0 0 1 13 0"/><path d="M8.7 16a5 5 0 0 1 6.6 0"/><circle cx="12" cy="19.3" r="1.1"/>',
            'star' => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8-4.3-4.1 5.9-.9Z"/>',
            'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>',
            'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.3c2.1.6 3.5 2.6 3.5 5.7"/>',
            'check'   => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="m8 12 3 3 5-6"/>',
            'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'chat'    => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12Z"/>',
            'wallet'  => '<rect x="3" y="6" width="18" height="14" rx="3"/><path d="M3 10h18"/><path d="M16 15h2"/><path d="M6 6V5a2 2 0 0 1 2-2h9"/>',
            'grid'    => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
            'plus'    => '<path d="M12 5v14M5 12h14"/>',
            'ticket'  => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4Z"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
            'bell'    => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 7 2 7H4s2-2 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/>',
            'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'x'       => '<path d="M6 6l12 12M18 6 6 18"/>',
            'arrow'   => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
            'alert'   => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4"/><path d="M12 17.5v.01"/>',
            'swap'    => '<path d="M7 4 3 8l4 4"/><path d="M3 8h14"/><path d="m17 20 4-4-4-4"/><path d="M21 16H7"/>',
            'trash'   => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
            'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
            'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'download'=> '<path d="M12 4v11"/><path d="m7 10 5 5 5-5"/><path d="M5 20h14"/>',
            'shield'  => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z"/>',
            'book'    => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M4 19a2 2 0 0 1 2-2h13"/>',
            'external'=> '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
        ][$name] ?? '';

        return '<svg class="' . esc($class, 'attr') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }
}

if (! function_exists('date_fr')) {
    /** Date lisible en français : « mardi 27 octobre », « 27 oct. 2026 »… */
    function date_fr(?string $date, string $pattern = 'd MMMM y'): string
    {
        if (! $date) {
            return '';
        }
        $f = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Paris', null, $pattern);

        return (string) $f->format(new DateTimeImmutable($date));
    }
}

if (! function_exists('due_label')) {
    /** Échéance relative : « aujourd'hui », « demain », « en retard de 3 jours », « le 4 novembre ». */
    function due_label(?string $date): string
    {
        if (! $date) {
            return '';
        }
        $d    = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date));
        $days = (int) $d->format('%r%a');

        return match (true) {
            $days === 0  => "aujourd'hui",
            $days === 1  => 'demain',
            $days === -1 => 'en retard d’un jour',
            $days < -1   => 'en retard de ' . abs($days) . ' jours',
            $days < 7    => date_fr($date, 'EEEE'),
            default      => 'le ' . date_fr($date, 'd MMMM'),
        };
    }
}

if (! function_exists('money')) {
    function money(float $amount): string
    {
        return number_format($amount, 2, ',', "\u{202F}") . "\u{00A0}€";
    }
}

if (! function_exists('plural')) {
    function plural(int $n, string $one, ?string $many = null): string
    {
        return $n . "\u{00A0}" . ($n > 1 ? ($many ?? $one . 's') : $one);
    }
}

if (! function_exists('legacy_url')) {
    /** Lien vers l'écran équivalent de la version 2.x (pendant la migration). */
    function legacy_url(string $path): ?string
    {
        $base = rtrim((string) env('fo.legacyUrl', ''), '/');

        return $base !== '' ? $base . '/' . ltrim($path, '/') : null;
    }
}

if (! function_exists('priority_badge')) {
    /** Priorité : toujours un mot, la couleur ne fait que le renforcer. */
    function priority_badge(string $p): string
    {
        $cls = ['haute' => 'badge-danger', 'normale' => 'badge-neutral', 'basse' => 'badge-muted'][$p] ?? 'badge-neutral';
        $txt = ['haute' => 'Priorité haute', 'normale' => 'Priorité normale', 'basse' => 'Priorité basse'][$p] ?? $p;

        return '<span class="badge ' . $cls . '">' . esc($txt) . '</span>';
    }
}

if (! function_exists('field_attrs')) {
    /**
     * Attributs d'un champ : id, name, et, en cas d'erreur, aria-invalid + aria-describedby
     * reliant l'aide et le message d'erreur au champ.
     */
    function field_attrs(string $name, array $errors, string $hintId = ''): string
    {
        $describe = trim($hintId . (isset($errors[$name]) ? ' err-' . $name : ''));

        return ' id="' . esc($name, 'attr') . '" name="' . esc($name, 'attr') . '"'
            . (isset($errors[$name]) ? ' aria-invalid="true"' : '')
            . ($describe !== '' ? ' aria-describedby="' . esc($describe, 'attr') . '"' : '');
    }
}

if (! function_exists('field_error')) {
    function field_error(string $name, array $errors): string
    {
        return isset($errors[$name])
            ? '<p id="err-' . esc($name, 'attr') . '" class="error-text">' . icon('alert', 'size-5 shrink-0 mt-0.5') . esc($errors[$name]) . '</p>'
            : '';
    }
}

if (! function_exists('status_badge')) {
    /** Statut de cotisation : toujours le mot, la couleur le renforce. */
    function status_badge(string $status): string
    {
        $cls = ['a_jour' => 'badge-success', 'en_attente' => 'badge-warning', 'sympathisant' => 'badge-neutral', 'radie' => 'badge-muted'][$status] ?? 'badge-neutral';

        return '<span class="badge ' . $cls . '">' . esc(\App\Domain\MemberStatus::LABELS[$status] ?? $status) . '</span>';
    }
}

if (! function_exists('pager')) {
    /** Pagination accessible : liens précédent / suivant et pages proches, page courante annoncée. */
    function pager(int $page, int $pages, array $query, string $base): string
    {
        if ($pages <= 1) {
            return '';
        }
        $url = static function (int $p) use ($query, $base) {
            $q = array_filter($query + ['page' => $p], static fn ($v) => $v !== '' && $v !== null);
            if (($q['page'] ?? 1) === 1) {
                unset($q['page']);
            }

            return site_url($base) . ($q ? '?' . http_build_query($q) : '');
        };
        $html = '<nav aria-label="Pagination" class="mt-6"><ul class="flex flex-wrap items-center gap-2" role="list">';
        if ($page > 1) {
            $html .= '<li><a class="btn btn-secondary" href="' . esc($url($page - 1), 'attr') . '">Page précédente</a></li>';
        }
        for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
            $html .= $p === $page
                ? '<li><span class="btn btn-primary" aria-current="page"><span class="sr-only">Page </span>' . $p . '</span></li>'
                : '<li><a class="btn btn-ghost" href="' . esc($url($p), 'attr') . '"><span class="sr-only">Page </span>' . $p . '</a></li>';
        }
        if ($page < $pages) {
            $html .= '<li><a class="btn btn-secondary" href="' . esc($url($page + 1), 'attr') . '">Page suivante</a></li>';
        }

        return $html . '</ul><p class="mt-2 text-muted">Page ' . $page . ' sur ' . $pages . '</p></nav>';
    }
}

if (! function_exists('csv_safe')) {
    /**
     * Neutralise une cellule CSV qu'un tableur prendrait pour une formule (=, +, -, @).
     * Un numéro de téléphone (+33 6…) ou un nombre négatif reste tel quel.
     */
    function csv_safe($v)
    {
        if (! is_string($v) || $v === '' || ! str_contains('=+-@', $v[0])) {
            return $v;
        }
        if ($v[0] !== '=' && $v[0] !== '@' && preg_match('/^[+-][\d\s().\/-]+$/', $v)) {
            return $v;
        }

        return "'" . $v;
    }
}

if (! function_exists('public_url')) {
    /**
     * Adresse publique des liens imprimés ou envoyés par courriel (QR codes des permanences, lien de confirmation).
     * Par défaut l'adresse de ce site ; fo.publicUrl permet d'en imposer une autre (nom de domaine définitif).
     * Les adresses gardent la forme de la 2.x (booking.php, verify.php) : après la bascule, les QR codes déjà
     * imprimés et les liens déjà envoyés continuent de fonctionner.
     */
    function public_url(string $path): string
    {
        $base = rtrim((string) env('fo.publicUrl', ''), '/');

        return $base !== '' ? $base . '/' . ltrim($path, '/') : site_url($path);
    }
}

if (! function_exists('safe_html')) {
    /**
     * HTML simple saisi par le Super Admin (textes légaux) : balises de mise en forme seulement,
     * aucun attribut sauf href en http(s) ou mailto sur les liens.
     */
    function safe_html(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|iframe|object)\b.*?</\1\s*>#is', '', $html);
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><a><blockquote>');
        $html = preg_replace_callback('#<(\w+)([^>]*)>#', static function ($m) {
            if (strtolower($m[1]) === 'a' && preg_match('#href\s*=\s*(["\'])((?:https?:|mailto:)[^"\']*)\1#i', $m[2], $h)) {
                return '<a href="' . esc($h[2], 'attr') . '" rel="noopener">';
            }

            return '<' . $m[1] . '>';
        }, $html);

        return (string) $html;
    }
}
