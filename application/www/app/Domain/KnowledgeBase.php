<?php

namespace App\Domain;

use App\Libraries\Settings;
use CodeIgniter\Database\BaseConnection;
use DOMDocument;
use DOMElement;
use ZipArchive;

/**
 * Base documentaire : découpage en passages, index TF-IDF, recherche plein texte puis reclassement
 * par similarité cosinus, réponse extractive. Algorithme repris de includes/kb_helpers.php (2.x),
 * mêmes tables (kb_documents, kb_chunks, kb_idf), donc mêmes résultats et mêmes documents dans les deux versions.
 *
 * Écarts voulus avec la 2.x :
 *  - en mode partagé (réglage kb_shared), la 2.x vidait l'index de pertinence de tous les sites puis échouait
 *    à le réécrire (identifiant de site 0 refusé par la base) : la recherche perdait son classement.
 *    Ici, l'index partagé est calculé à la volée et mis en cache, sans toucher à la table ;
 *  - les fichiers sont rangés dans writable/, hors de l'espace public (la 2.x les plaçait dans uploads/, lisible
 *    par simple adresse) ; ceux de la 2.x restent lisibles si fo.legacyPath indique son dossier ;
 *  - le téléchargement respecte le mode partagé (la 2.x renvoyait « introuvable » pour un document d'un autre site).
 */
class KnowledgeBase
{
    public const EXTENSIONS = ['txt', 'md', 'docx', 'csv'];
    public const MAX_FILES  = 5;
    public const MAX_SIZE   = 5 * 1024 * 1024;

    public const CATEGORIES = [
        'general'    => 'Général',
        'juridique'  => 'Juridique',
        'accord'     => 'Accords',
        'convention' => 'Conventions',
        'reglement'  => 'Règlement',
        'formation'  => 'Formation',
        'sante'      => 'Santé et sécurité',
        'social'     => 'Social',
        'technique'  => 'Technique',
    ];

    private const STOP = 'le la les un une des de du au aux en et ou mais ni car que qui dont ce ces '
        . 'cette cet son sa ses mon ma mes ton ta tes notre nos votre vos leur leurs '
        . 'je tu il elle on nous vous ils elles me te se ne pas plus très aussi bien '
        . 'tout tous toute toutes même autre autres quel quelle quels quelles pour par '
        . 'avec sans sur sous dans entre vers chez avant après pendant depuis lors '
        . 'si comme quand comment pourquoi où là ici encore déjà trop peu assez beaucoup '
        . 'être avoir faire pouvoir aller dire voir venir prendre savoir vouloir falloir '
        . 'est sont été sera sera été fait été à a y ai eu c d j l m n s t qu';

    private static ?array $stop = null;

    public function __construct(private BaseConnection $db, private int $siteId)
    {
    }

    public static function shared(): bool
    {
        return Settings::get('kb_shared', '0') === '1';
    }

    // ------------------------------------------------------------------
    // Texte
    // ------------------------------------------------------------------

    public static function tokenize(string $text): array
    {
        self::$stop ??= array_flip(explode(' ', self::STOP));
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\'-]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        $out  = [];
        foreach (explode(' ', $text) as $w) {
            $w = trim($w, "'-");
            if (mb_strlen($w) < 2 || isset(self::$stop[$w])) {
                continue;
            }
            $out[] = self::stem($w);
        }

        return $out;
    }

    public static function stem(string $w): string
    {
        $len = mb_strlen($w);
        $cut = static function (array $sufs) use ($w): ?string {
            foreach ($sufs as $suf) {
                if (mb_substr($w, -mb_strlen($suf)) === $suf) {
                    return mb_substr($w, 0, -mb_strlen($suf));
                }
            }

            return null;
        };
        if ($len > 6 && ($r = $cut(['ement', 'ation', 'ement', 'ments', 'ables', 'ement', 'iques'])) !== null) {
            return $r;
        }
        if ($len > 4 && ($r = $cut(['eur', 'eux', 'ant', 'ent', 'ons', 'ées', 'ers', 'ais', 'ait'])) !== null) {
            return $r;
        }
        if ($len > 3 && ($r = $cut(['és', 'er', 'es', 'ée', 'ir'])) !== null) {
            return $r;
        }

        return $w;
    }

    public static function tf(array $tokens): array
    {
        $tf = [];
        foreach ($tokens as $t) {
            $tf[$t] = ($tf[$t] ?? 0) + 1;
        }

        return $tf;
    }

    public static function chunk(string $text, int $target = 500, int $overlap = 80): array
    {
        $text      = preg_replace('/\r\n|\r/', "\n", $text);
        $text      = preg_replace('/\n{3,}/', "\n\n", $text);
        $sentences = preg_split('/(?<=[.!?…])\s+/', $text);
        if (! $sentences) {
            return [];
        }
        $chunks = [];
        $cur    = '';
        foreach ($sentences as $s) {
            $s = trim($s);
            if ($s === '') {
                continue;
            }
            if (mb_strlen($cur . ' ' . $s) > $target && mb_strlen($cur) > 100) {
                $chunks[] = trim($cur);
                $words    = explode(' ', $cur);
                $cur      = implode(' ', array_slice($words, -min(count($words), 12))) . ' ' . $s;
            } else {
                $cur .= ($cur ? ' ' : '') . $s;
            }
        }
        if (mb_strlen(trim($cur)) > 30) {
            $chunks[] = trim($cur);
        }
        if (! $chunks && mb_strlen($text) > 0) {
            foreach (preg_split('/\n\n+/', $text) as $p) {
                $p = trim($p);
                if (mb_strlen($p) > $target) {
                    for ($o = 0; $o < mb_strlen($p); $o += $target - $overlap) {
                        $chunks[] = trim(mb_substr($p, $o, $target));
                    }
                } elseif (mb_strlen($p) > 30) {
                    $chunks[] = $p;
                }
            }
        }

        return $chunks;
    }

    public static function readFile(string $path, string $ext): string
    {
        return match (strtolower($ext)) {
            'txt', 'md', 'csv' => (string) file_get_contents($path),
            'docx' => self::readDocx($path),
            default => '',
        };
    }

    public static function readDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! $xml) {
            return '';
        }
        $dom = new DOMDocument();
        // LIBXML_NONET : aucune ressource externe n'est chargée pendant la lecture du document.
        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            return '';
        }
        $ns   = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $body = $dom->getElementsByTagNameNS($ns, 'body')->item(0);
        if (! $body) {
            return '';
        }
        $text = '';
        foreach ($body->childNodes as $node) {
            if ($node->localName === 'p') {
                $text .= self::docxParagraph($node, $ns) . "\n";
            } elseif ($node->localName === 'tbl') {
                $text .= self::docxTable($node, $ns) . "\n";
            }
        }

        return trim($text);
    }

    private static function docxParagraph(DOMElement $p, string $ns): string
    {
        $pPr  = $p->getElementsByTagNameNS($ns, 'pPr')->item(0);
        $list = $pPr && $pPr->getElementsByTagNameNS($ns, 'numPr')->length > 0;
        $line = '';
        foreach ($p->getElementsByTagNameNS($ns, 't') as $r) {
            $line .= $r->textContent;
        }
        $line = trim($line);

        return ($list && $line !== '') ? '• ' . $line : $line;
    }

    private static function docxTable(DOMElement $tbl, string $ns): string
    {
        $rows = [];
        foreach ($tbl->getElementsByTagNameNS($ns, 'tr') as $row) {
            $cells = [];
            foreach ($row->getElementsByTagNameNS($ns, 'tc') as $cell) {
                $ct = '';
                foreach ($cell->getElementsByTagNameNS($ns, 'p') as $cp) {
                    $pt = '';
                    foreach ($cp->getElementsByTagNameNS($ns, 't') as $r) {
                        $pt .= $r->textContent;
                    }
                    $ct .= trim($pt) . ' ';
                }
                $cells[] = trim($ct);
            }
            $rows[] = $cells;
        }
        $out = '';
        foreach ($rows as $i => $r) {
            $out .= implode(' | ', $r) . "\n";
            if ($i === 0) {
                $out .= str_repeat('---', count($r)) . "\n";
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Stockage des fichiers
    // ------------------------------------------------------------------

    /** Dossier des nouveaux fichiers : writable/, hors de l'espace public. */
    public static function storageRoot(): string
    {
        return rtrim(WRITEPATH, '/');
    }

    /**
     * Chemin réel d'un fichier enregistré : writable/, puis le dossier de la 2.x (fo.legacyPath) pour les documents
     * déposés avant la migration.
     */
    public static function locate(?string $relative): ?string
    {
        if (! $relative || str_contains($relative, '..') || ! preg_match('#^uploads/kb/\d+/[A-Za-z0-9._-]+$#', $relative)) {
            return null;
        }
        foreach (array_unique([self::storageRoot(), rtrim((string) env('fo.legacyPath', ''), '/'), rtrim(WRITEPATH, '/')]) as $root) {
            if ($root !== '' && is_file($root . '/' . $relative)) {
                return $root . '/' . $relative;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Documents
    // ------------------------------------------------------------------

    private function scope(string $alias = 'd'): array
    {
        return self::shared() ? [] : [$alias . '.site_id' => $this->siteId];
    }

    public function documents(): array
    {
        return $this->db->table('kb_documents d')
            ->select("d.*, CONCAT(u.first_name, ' ', u.last_name) AS uploader, s.name AS site_name", false)
            ->join('users u', 'u.id = d.uploaded_by', 'left')->join('sites s', 's.id = d.site_id', 'left')
            ->where($this->scope())->orderBy('d.created_at', 'DESC')->orderBy('d.id', 'DESC')->get()->getResultArray();
    }

    public function stats(): array
    {
        $w = self::shared() ? [] : ['site_id' => $this->siteId];

        return [
            'docs'   => $this->db->table('kb_documents')->where($w)->countAllResults(),
            'chunks' => $this->db->table('kb_chunks')->where($w)->countAllResults(),
            'terms'  => self::shared() ? count($this->idf()) : $this->db->table('kb_idf')->where($w)->countAllResults(),
        ];
    }

    public function document(int $id): ?array
    {
        return $this->db->table('kb_documents d')->where(['d.id' => $id] + $this->scope())->get()->getRowArray();
    }

    /**
     * Indexe un fichier : lecture, découpage, fréquences, copie du fichier, recalcul de l'index.
     * @return array{success:bool, error?:string, doc_id?:int, chunks?:int}
     */
    public function ingest(int $userId, string $title, string $tmpPath, string $filename, string $category): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        try {
            $text = self::readFile($tmpPath, $ext);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'lecture impossible'];
        }
        if (trim($text) === '') {
            return ['success' => false, 'error' => 'impossible de lire le contenu du fichier'];
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $chunks = self::chunk($text);
        if (! $chunks) {
            return ['success' => false, 'error' => 'pas assez de texte exploitable'];
        }
        $dir  = self::storageRoot() . '/uploads/kb/' . $this->siteId;
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return ['success' => false, 'error' => 'dossier de stockage inaccessible'];
        }
        $safe = $this->siteId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        if (! copy($tmpPath, $dir . '/' . $safe)) {
            return ['success' => false, 'error' => 'copie du fichier impossible'];
        }
        $this->db->transStart();
        $this->db->table('kb_documents')->insert([
            'site_id' => $this->siteId, 'title' => mb_substr($title, 0, 255), 'filename' => mb_substr($filename, 0, 255),
            'file_size' => (int) filesize($tmpPath), 'file_path' => 'uploads/kb/' . $this->siteId . '/' . $safe,
            'category' => $category, 'total_chunks' => count($chunks), 'uploaded_by' => $userId,
        ]);
        $docId = (int) $this->db->insertID();
        foreach ($chunks as $i => $c) {
            $this->db->table('kb_chunks')->insert([
                'document_id' => $docId, 'site_id' => $this->siteId, 'chunk_index' => $i, 'chunk_text' => $c,
                'chunk_length' => mb_strlen($c), 'tf_json' => json_encode(self::tf(self::tokenize($c)), JSON_UNESCAPED_UNICODE),
            ]);
        }
        $this->db->transComplete();
        $this->rebuild();

        return ['success' => true, 'doc_id' => $docId, 'chunks' => count($chunks)];
    }

    public function delete(int $id): bool
    {
        $doc = $this->document($id);
        if (! $doc) {
            return false;
        }
        $this->db->table('kb_chunks')->where('document_id', $id)->delete();
        $this->db->table('kb_documents')->where('id', $id)->delete();
        if ($path = self::locate($doc['file_path'])) {
            @unlink($path);
        }
        // L'index du site d'origine du document est recalculé (en mode partagé aussi).
        (new self($this->db, (int) $doc['site_id']))->rebuildSite();
        cache()->delete('kb_idf_shared');

        return true;
    }

    /** Recalcul de l'index (celui du site, et l'index partagé est invalidé). */
    public function rebuild(): void
    {
        $this->rebuildSite();
        cache()->delete('kb_idf_shared');
    }

    /** IDF lissé du site, écrit dans kb_idf comme la 2.x : log(N / (1 + df)) + 1. */
    private function rebuildSite(): void
    {
        $rows = $this->db->table('kb_chunks')->select('tf_json')->where('site_id', $this->siteId)->get()->getResultArray();
        $this->db->table('kb_idf')->where('site_id', $this->siteId)->delete();
        if (! $rows) {
            return;
        }
        $batch = [];
        foreach (self::documentFrequencies($rows) as $term => $df) {
            $batch[] = ['site_id' => $this->siteId, 'term' => mb_substr((string) $term, 0, 191), 'idf' => log(count($rows) / (1 + $df)) + 1, 'doc_freq' => $df];
        }
        foreach (array_chunk($batch, 500) as $part) {
            // Termes tronqués à 191 caractères : doublons possibles, la dernière valeur l'emporte (comme la 2.x).
            $this->db->table('kb_idf')->upsertBatch($part);
        }
    }

    private static function documentFrequencies(array $rows): array
    {
        $df = [];
        foreach ($rows as $r) {
            foreach (array_keys(json_decode($r['tf_json'] ?: '{}', true) ?: []) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }

        return $df;
    }

    /** Table IDF utilisée pour la recherche. */
    public function idf(): array
    {
        if (! self::shared()) {
            $map = [];
            foreach ($this->db->table('kb_idf')->select('term, idf')->where('site_id', $this->siteId)->get()->getResultArray() as $r) {
                $map[$r['term']] = (float) $r['idf'];
            }

            return $map;
        }
        $cached = cache('kb_idf_shared');
        if (is_array($cached)) {
            return $cached;
        }
        $rows = $this->db->table('kb_chunks')->select('tf_json')->get()->getResultArray();
        $map  = [];
        foreach (self::documentFrequencies($rows) as $term => $df) {
            // Même valeur que la colonne FLOAT de kb_idf, pour un classement identique.
            $map[$term] = (float) unpack('g', pack('g', log(count($rows) / (1 + $df)) + 1))[1];
        }
        cache()->save('kb_idf_shared', $map, 3600);

        return $map;
    }

    // ------------------------------------------------------------------
    // Recherche
    // ------------------------------------------------------------------

    private static function vector(array $tf, array $idf): array
    {
        $v = [];
        foreach ($tf as $t => $n) {
            $v[$t] = $n * ($idf[$t] ?? 1.0);
        }

        return $v;
    }

    private static function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $t) {
            $x = $a[$t] ?? 0.0;
            $y = $b[$t] ?? 0.0;
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }
        $den = sqrt($na) * sqrt($nb);

        return $den > 0 ? $dot / $den : 0.0;
    }

    /** Recherche : 30 candidats plein texte (ou LIKE en secours), reclassés par 0,3 × plein texte + 0,7 × cosinus. */
    public function search(string $query, int $top = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $shared = self::shared();
        $where  = $shared ? '1=1' : 'c.site_id = ?';
        $params = $shared ? [] : [$this->siteId];
        $sel    = 'c.*, d.title AS doc_title, d.category, d.id AS doc_id, d.file_path, d.filename AS doc_filename';
        $cands  = $this->db->query(
            "SELECT {$sel}, MATCH(c.chunk_text) AGAINST(? IN NATURAL LANGUAGE MODE) AS ft_score
             FROM kb_chunks c JOIN kb_documents d ON c.document_id = d.id
             WHERE {$where} AND MATCH(c.chunk_text) AGAINST(? IN NATURAL LANGUAGE MODE)
             ORDER BY ft_score DESC LIMIT 30",
            array_merge([$query], $params, [$query])
        )->getResultArray();
        if (! $cands) {
            $words = array_values(array_filter(explode(' ', $query), static fn ($w) => mb_strlen($w) > 2));
            if (! $words) {
                return [];
            }
            $like  = implode(' OR ', array_fill(0, count($words), 'c.chunk_text LIKE ?'));
            $cands = $this->db->query(
                "SELECT {$sel}, 1.0 AS ft_score FROM kb_chunks c JOIN kb_documents d ON c.document_id = d.id WHERE {$where} AND ({$like}) LIMIT 30",
                array_merge($params, array_map(static fn ($w) => '%' . $w . '%', $words))
            )->getResultArray();
            if (! $cands) {
                return [];
            }
        }
        $idf  = $this->idf();
        $qv   = self::vector(self::tf(self::tokenize($query)), $idf);
        $best = max(1, (float) $cands[0]['ft_score']);
        foreach ($cands as &$c) {
            $c['cosine']      = self::cosine($qv, self::vector(json_decode($c['tf_json'] ?: '{}', true) ?: [], $idf));
            $c['final_score'] = 0.3 * ((float) $c['ft_score'] / $best) + 0.7 * $c['cosine'];
        }
        unset($c);
        usort($cands, static fn ($a, $b) => $b['final_score'] <=> $a['final_score']);

        return array_slice($cands, 0, $top);
    }

    /** Réponse extractive : les quatre phrases qui partagent le plus de termes avec la question. */
    public static function answer(array $results, string $query, int $max = 4): string
    {
        if (! $results) {
            return '';
        }
        $q      = array_flip(self::tokenize($query));
        $scored = [];
        foreach ($results as $r) {
            foreach (preg_split('/(?<=[.!?…])\s+/', $r['chunk_text']) as $s) {
                $s = trim($s);
                if (mb_strlen($s) < 15) {
                    continue;
                }
                $ov = 0;
                foreach (self::tokenize($s) as $t) {
                    if (isset($q[$t])) {
                        $ov++;
                    }
                }
                $scored[] = ['text' => $s, 'score' => $ov * $r['final_score']];
            }
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $seen = $sel = [];
        foreach ($scored as $s) {
            $k = mb_substr($s['text'], 0, 50);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $sel[]    = $s['text'];
            if (count($sel) >= $max) {
                break;
            }
        }

        return implode(' ', $sel);
    }

    /**
     * Passage mis en forme : texte échappé, termes surlignés, lignes « a | b | c » rendues en tableau.
     * Le surlignage porte sur le texte d'origine (la 2.x surlignait le HTML échappé et pouvait casser &amp;).
     */
    public static function render(string $chunk, string $query): string
    {
        $words = array_values(array_filter(explode(' ', $query), static fn ($w) => mb_strlen($w) > 2));
        $mark  = static function (string $line) use ($words): string {
            if (! $words) {
                return esc($line);
            }
            $re    = '/(' . implode('|', array_map(static fn ($w) => preg_quote($w, '/'), $words)) . ')/iu';
            $parts = preg_split($re, $line, -1, PREG_SPLIT_DELIM_CAPTURE);
            $out   = '';
            foreach ($parts as $i => $p) {
                $out .= $i % 2 ? '<mark>' . esc($p) . '</mark>' : esc($p);
            }

            return $out;
        };
        $html  = '';
        $table = false;
        $row   = 0;
        foreach (explode("\n", $chunk) as $line) {
            $t = trim($line);
            if (substr_count($t, '|') >= 2) {
                if (preg_match('/^[|\s\-:]+$/', $t) && str_contains($t, '-')) {
                    continue;
                }
                if (! $table) {
                    $html .= '<div class="overflow-x-auto"><table class="kb-table">';
                    $table = true;
                    $row   = 0;
                }
                $tag   = $row === 0 ? 'th' : 'td';
                $html .= '<tr>';
                foreach (array_map('trim', explode('|', trim($t, '| '))) as $cell) {
                    if ($cell !== '') {
                        $html .= "<{$tag}" . ($tag === 'th' ? ' scope="col"' : '') . '>' . $mark($cell) . "</{$tag}>";
                    }
                }
                $html .= '</tr>';
                $row++;
            } else {
                if ($table) {
                    $html .= '</table></div>';
                    $table = false;
                }
                if (preg_match('/^---+$/', $t)) {
                    continue;
                }
                if ($t !== '') {
                    $html .= $mark($t) . '<br>';
                }
            }
        }

        return $table ? $html . '</table></div>' : $html;
    }
}
