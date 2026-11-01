<?php

namespace App\Controllers;

use App\Domain\Agenda;
use App\Libraries\Settings;

/**
 * Agenda syndical : tout le monde consulte et exporte, le secrétaire crée, modifie, duplique, supprime, importe.
 */
class AgendaController extends BaseController
{
    private function agenda(): Agenda
    {
        return new Agenda($this->db, (int) $this->auth->siteId());
    }

    private function canEdit(): bool
    {
        return $this->auth->canConfigure();
    }

    /** Mois demandé (?mois=2026-10), par défaut le mois courant. */
    private function period(): array
    {
        $p = (string) $this->request->getGet('mois');
        if (preg_match('/^(\d{4})-(\d{2})$/', $p, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [(int) date('Y'), (int) date('n')];
    }

    private function monthUrl(string $date): string
    {
        return site_url('agenda') . '?mois=' . substr($date, 0, 7);
    }

    public function index()
    {
        [$y, $m] = $this->period();
        $events  = $this->agenda()->month($y, $m);
        $byDay   = [];
        foreach ($events as $e) {
            $byDay[$e['event_date']][] = $e;
        }

        return $this->page('agenda/index', [
            'title' => 'Agenda', 'year' => $y, 'month' => $m, 'byDay' => $byDay, 'count' => count($events),
            'upcoming' => $this->agenda()->upcoming(5), 'canEdit' => $this->canEdit(),
            'prev' => $m === 1 ? sprintf('%d-12', $y - 1) : sprintf('%d-%02d', $y, $m - 1),
            'next' => $m === 12 ? sprintf('%d-01', $y + 1) : sprintf('%d-%02d', $y, $m + 1),
            'isCurrent' => $y === (int) date('Y') && $m === (int) date('n'),
        ]);
    }

    public function show(int $id)
    {
        $e = $this->agenda()->find($id);
        if (! $e) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->page('agenda/show', ['title' => $e['title'], 'e' => $e, 'canEdit' => $this->canEdit()]);
    }

    public function new()
    {
        $date = (string) $this->request->getGet('date');

        return $this->page('agenda/form', [
            'title' => 'Nouvel événement', 'e' => null,
            'defaults' => ['event_date' => Agenda::validDate($date) ? $date : '', 'event_type' => 'reunion'],
        ]);
    }

    public function edit(int $id)
    {
        $e = $this->agenda()->find($id);
        if (! $e) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->page('agenda/form', ['title' => 'Modifier l’événement', 'e' => $e, 'defaults' => []]);
    }

    public function create()
    {
        return $this->save(0);
    }

    public function update(int $id)
    {
        if (! $this->agenda()->find($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    private function save(int $id)
    {
        $rules = [
            'title'       => ['rules' => 'required|max_length[255]', 'errors' => ['required' => 'Indiquez un titre.', 'max_length' => 'Le titre est trop long (255 caractères au plus).']],
            'event_date'  => ['rules' => 'required|valid_date[Y-m-d]', 'errors' => ['required' => 'Indiquez la date.', 'valid_date' => 'La date n’est pas valide.']],
            'event_type'  => ['rules' => 'required|in_list[' . implode(',', array_keys(Agenda::TYPES)) . ']', 'errors' => ['required' => 'Choisissez un type.', 'in_list' => 'Choisissez un type.']],
            'start_time'  => ['rules' => 'permit_empty|regex_match[/^\d{2}:\d{2}$/]', 'errors' => ['regex_match' => 'L’heure de début n’est pas valide.']],
            'end_time'    => ['rules' => 'permit_empty|regex_match[/^\d{2}:\d{2}$/]', 'errors' => ['regex_match' => 'L’heure de fin n’est pas valide.']],
            'location'    => ['rules' => 'permit_empty|max_length[255]', 'errors' => ['max_length' => 'Le lieu est trop long (255 caractères au plus).']],
            'description' => ['rules' => 'permit_empty|max_length[2000]', 'errors' => ['max_length' => 'La description est trop longue (2 000 caractères au plus).']],
        ];
        $back = $id ? site_url("agenda/{$id}/modifier") : site_url('agenda/nouveau');
        if (! $this->validate($rules)) {
            return redirect()->to($back)->withInput()->with('errors', $this->validator->getErrors());
        }
        $d = [];
        foreach (array_keys($rules) as $f) {
            $d[$f] = trim((string) $this->request->getPost($f));
        }
        if ($d['start_time'] === '' && $d['end_time'] !== '') {
            return redirect()->to($back)->withInput()->with('errors', ['start_time' => 'Indiquez l’heure de début, ou retirez l’heure de fin.']);
        }
        if ($d['start_time'] !== '' && $d['end_time'] !== '' && $d['end_time'] <= $d['start_time']) {
            return redirect()->to($back)->withInput()->with('errors', ['end_time' => 'L’heure de fin doit suivre l’heure de début.']);
        }
        $a = $this->agenda();
        if ($a->duplicateOf($d['title'], $d['event_date'], $d['start_time'] ?: null, $id)) {
            return redirect()->to($back)->withInput()->with('errors', ['title' => 'Un événement identique (même titre, même date, même heure) existe déjà.']);
        }
        $newId = $a->save($d, (int) $this->auth->id(), $id);
        if (! $id) {
            $when = date_fr($d['event_date'], 'd MMMM') . ($d['start_time'] ? ' à ' . $d['start_time'] : '');
            $this->notifySite('Agenda : ' . $d['title'], $when . ($d['location'] ? ', ' . $d['location'] : ''), '/agenda/' . $newId, 'agenda', '📅');
        }

        return redirect()->to($this->monthUrl($d['event_date']) . '#jour-' . $d['event_date'])
            ->with('success', $id ? 'Événement modifié.' : 'Événement ajouté à l’agenda.');
    }

    public function duplicate(int $id)
    {
        $new = $this->agenda()->duplicate($id, (int) $this->auth->id());
        if (! $new) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(site_url("agenda/{$new}/modifier"))->with('success', 'Événement dupliqué : ajustez la copie si besoin.');
    }

    public function delete(int $id)
    {
        $e = $this->agenda()->find($id);
        if (! $e || ! $this->agenda()->delete($id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return redirect()->to($this->monthUrl($e['event_date']))->with('success', 'Événement supprimé.');
    }

    public function export()
    {
        $ics = $this->agenda()->ics(Settings::appName(), (string) session('site_name'));

        return $this->response->setHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="agenda-fo-' . date('Y-m-d') . '.ics"')
            ->setBody($ics);
    }

    public function exportOne(int $id)
    {
        $ics = $this->agenda()->ics(Settings::appName(), (string) session('site_name'), $id);
        if ($ics === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="evenement-' . $id . '.ics"')
            ->setBody($ics);
    }

    public function import()
    {
        $f = $this->request->getFile('ics_file');
        if (! $f || ! $f->isValid()) {
            return redirect()->to(site_url('agenda') . '#import')->with('error', 'Choisissez un fichier .ics.');
        }
        if (strtolower($f->getClientExtension()) !== 'ics' || $f->getSize() > 2 * 1024 * 1024) {
            return redirect()->to(site_url('agenda') . '#import')->with('error', 'Le fichier doit être au format .ics et peser 2 Mo au plus.');
        }
        $r   = $this->agenda()->import((string) file_get_contents($f->getTempName()), (int) $this->auth->id());
        $msg = plural($r['imported'], 'événement importé', 'événements importés') . '.';
        if ($r['skipped']) {
            $msg .= ' ' . plural($r['skipped'], 'doublon ignoré', 'doublons ignorés') . '.';
        }

        return redirect()->to(site_url('agenda'))->with('success', $msg);
    }
}
