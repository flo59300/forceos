<?php

namespace App\Controllers;

use App\Domain\KnowledgeBase;

/**
 * Base documentaire : recherche pour tous, gestion des documents pour le secrétaire.
 */
class Documents extends BaseController
{
    private function kb(): KnowledgeBase
    {
        return new KnowledgeBase($this->db, (int) $this->auth->siteId());
    }

    private function canManage(): bool
    {
        return $this->auth->canConfigure();
    }

    public function index()
    {
        $kb     = $this->kb();
        $manage = $this->canManage();
        $tab    = $manage && $this->request->getGet('onglet') === 'documents' ? 'documents' : 'recherche';
        $q      = mb_substr(trim((string) $this->request->getGet('q')), 0, 200);
        $results = [];
        $answer  = '';
        $ms      = 0;
        if ($q !== '' && $tab === 'recherche') {
            $t0      = microtime(true);
            $results = $kb->search($q, 8);
            $answer  = KnowledgeBase::answer($results, $q);
            $ms      = (int) round((microtime(true) - $t0) * 1000);
        }

        return $this->page('documents/index', [
            'title' => $q !== '' ? 'Recherche : ' . $q : 'Base documentaire', 'tab' => $tab, 'canManage' => $manage,
            'q' => $q, 'results' => $results, 'answer' => $answer, 'ms' => $ms, 'stats' => $kb->stats(),
            'documents' => $tab === 'documents' ? $kb->documents() : [], 'shared' => KnowledgeBase::shared(),
        ]);
    }

    public function upload()
    {
        $back  = site_url('documents') . '?onglet=documents';
        $files = $this->request->getFileMultiple('kb_files') ?: [];
        $files = array_values(array_filter($files, static fn ($f) => $f && $f->getError() !== UPLOAD_ERR_NO_FILE));
        if (! $files) {
            return redirect()->to($back)->with('error', 'Choisissez au moins un fichier.');
        }
        $cat = (string) $this->request->getPost('doc_category');
        if (! isset(KnowledgeBase::CATEGORIES[$cat])) {
            $cat = 'general';
        }
        $customTitle = trim((string) $this->request->getPost('doc_title'));
        $ok          = 0;
        $errors      = [];
        $files       = array_slice($files, 0, KnowledgeBase::MAX_FILES);
        foreach ($files as $f) {
            $name = $f->getClientName();
            $ext  = strtolower($f->getClientExtension());
            if (! $f->isValid()) {
                $errors[] = $name . ' : envoi interrompu';
            } elseif (! in_array($ext, KnowledgeBase::EXTENSIONS, true)) {
                $errors[] = $name . ' : format non pris en charge';
            } elseif ($f->getSize() > KnowledgeBase::MAX_SIZE) {
                $errors[] = $name . ' : plus de 5 Mo';
            } elseif ($ext === 'docx' && ! class_exists(\ZipArchive::class)) {
                $errors[] = $name . ' : lecture des fichiers Word indisponible sur le serveur';
            } else {
                $title = count($files) === 1 && $customTitle !== '' ? $customTitle : pathinfo($name, PATHINFO_FILENAME);
                $r     = $this->kb()->ingest((int) $this->auth->id(), $title, $f->getTempName(), $name, $cat);
                $r['success'] ? $ok++ : $errors[] = $name . ' : ' . $r['error'];
            }
        }
        $resp = redirect()->to($back);
        if ($ok) {
            $resp = $resp->with('success', plural($ok, 'document indexé', 'documents indexés') . '.');
        }
        if ($errors) {
            $resp = $resp->with('error', 'Non indexé : ' . implode(' ; ', $errors) . '.');
        }

        return $resp;
    }

    public function delete(int $id)
    {
        if (! $this->kb()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url('documents') . '?onglet=documents')->with('success', 'Document supprimé, index recalculé.');
    }

    public function reindex()
    {
        $this->kb()->rebuild();

        return redirect()->to(site_url('documents') . '?onglet=documents')->with('success', 'Index de recherche recalculé.');
    }

    public function download(int $id)
    {
        $doc  = $this->kb()->document($id);
        $path = $doc ? KnowledgeBase::locate($doc['file_path']) : null;
        if (! $path) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $mime = [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain; charset=utf-8', 'md' => 'text/markdown; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
        ][strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        return $this->response->download($path, null)->setFileName($doc['filename'])->setContentType($mime)
            ->setHeader('Cache-Control', 'private, max-age=3600')->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
