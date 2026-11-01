<?php

namespace App\Controllers;

use App\Domain\Users;

/**
 * Comptes du site (secrétaire) : création, modification, activation, suppression protégée,
 * fonctions syndicales, import de collecteurs.
 */
class Utilisateurs extends BaseController
{
    private function users(): Users
    {
        return new Users($this->db, (int) $this->auth->siteId(), (int) $this->auth->id());
    }

    private function back(string $type, string $msg, string $anchor = '')
    {
        return redirect()->to(site_url('administration/utilisateurs') . $anchor)->with($type, $msg);
    }

    public function index()
    {
        return $this->page('admin/users', ['title' => 'Utilisateurs', 'users' => $this->users()->list(), 'functions' => $this->users()->functions(), 'me' => $this->auth->user()]);
    }

    public function new()
    {
        return $this->page('admin/user_form', ['title' => 'Nouvel utilisateur', 'u' => null, 'members' => $this->memberOptions()]);
    }

    public function edit(int $id)
    {
        $u = $this->users()->find($id) ?? throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();

        return $this->page('admin/user_form', ['title' => 'Modifier le compte', 'u' => $u, 'members' => $this->memberOptions(), 'isSelf' => $id === (int) $this->auth->id(), 'history' => $this->users()->history($id)]);
    }

    public function create()
    {
        $d = $this->validated(null);
        if (is_array($d) && isset($d['_errors'])) {
            return redirect()->back()->withInput()->with('errors', $d['_errors']);
        }
        $id = $this->users()->create($d);

        return $this->back('success', 'Compte de ' . $d['first_name'] . ' ' . $d['last_name'] . ' créé. Communiquez-lui son mot de passe de vive voix.', '#utilisateur-' . $id);
    }

    public function update(int $id)
    {
        $this->users()->find($id) ?? throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $d = $this->validated($id);
        if (isset($d['_errors'])) {
            return redirect()->back()->withInput()->with('errors', $d['_errors']);
        }
        $this->users()->update($id, $d);

        return $this->back('success', 'Compte modifié.', '#utilisateur-' . $id);
    }

    public function toggle(int $id)
    {
        $active = (bool) $this->request->getPost('active');
        $r      = $this->users()->setActive($id, $active);

        return match ($r) {
            'ok'   => $this->back('success', $active ? 'Compte réactivé.' : 'Compte désactivé : la personne ne peut plus se connecter, son historique est conservé.', '#utilisateur-' . $id),
            'self' => $this->back('error', 'Vous ne pouvez pas désactiver votre propre compte.'),
            default => $this->back('error', 'Compte introuvable.'),
        };
    }

    public function delete(int $id)
    {
        $r = $this->users()->delete($id);

        return match ($r) {
            'ok'      => $this->back('success', 'Compte supprimé.'),
            'self'    => $this->back('error', 'Vous ne pouvez pas supprimer votre propre compte.'),
            'history' => $this->back('error', 'Ce compte a un historique (encaissements, reversements, heures ou messages) : le supprimer effacerait ces données. Désactivez-le plutôt.', '#utilisateur-' . $id),
            default   => $this->back('error', 'Compte introuvable.'),
        };
    }

    public function functions(int $id)
    {
        $ok = $this->users()->setFunctions($id, (array) ($this->request->getPost('functions') ?? []));

        return $ok ? $this->back('success', 'Fonctions syndicales enregistrées.', '#utilisateur-' . $id) : $this->back('error', 'Compte introuvable.');
    }

    public function addFunction()
    {
        $label = trim((string) $this->request->getPost('label'));
        if ($label === '' || mb_strlen($label) > 100) {
            return $this->back('error', 'Indiquez le nom de la fonction (100 caractères au plus).', '#fonctions');
        }
        $this->users()->addFunction($label, mb_substr(trim((string) $this->request->getPost('abbreviation')), 0, 20), (string) $this->request->getPost('color'));

        return $this->back('success', 'Fonction « ' . $label . ' » ajoutée.', '#fonctions');
    }

    public function deleteFunction(int $id)
    {
        return $this->users()->deleteFunction($id) ? $this->back('success', 'Fonction supprimée.', '#fonctions') : $this->back('error', 'Fonction introuvable.', '#fonctions');
    }

    public function import()
    {
        $file = $this->request->getFile('csv');
        $pw   = (string) $this->request->getPost('default_password');
        if (Users::passwordErrors($pw)) {
            return $this->back('error', 'Mot de passe par défaut trop faible : il faut ' . implode(', ', Users::passwordErrors($pw)) . '.', '#import');
        }
        if (! $file || ! $file->isValid() || strtolower($file->getClientExtension()) !== 'csv' || $file->getSize() > 2 * 1024 * 1024) {
            return $this->back('error', 'Choisissez un fichier CSV de 2 Mo au plus.', '#import');
        }
        $r   = $this->users()->importCsv($file->getTempName(), $pw);
        $msg = plural($r['imported'], 'collecteur importé', 'collecteurs importés') . ($r['skipped'] ? ', ' . plural($r['skipped'], 'ligne ignorée', 'lignes ignorées') : '') . '.';
        if ($r['errors']) {
            $msg .= ' Détail : ' . implode(' ; ', array_slice($r['errors'], 0, 5)) . (count($r['errors']) > 5 ? '…' : '');
        }

        return $this->back($r['imported'] ? 'success' : 'error', $msg, '#import');
    }

    // ------------------------------------------------------------------

    private function memberOptions(): array
    {
        return $this->db->table('members')->select("id, CONCAT(last_name, ' ', first_name) AS name")
            ->where(['site_id' => $this->auth->siteId(), 'is_radiated' => 0])->orderBy('last_name')->get()->getResultArray();
    }

    /** Validation commune. Mot de passe obligatoire à la création, facultatif ensuite. */
    private function validated(?int $id): array
    {
        $p      = $this->request->getPost();
        $errors = [];
        $d      = [
            'first_name' => trim((string) ($p['first_name'] ?? '')), 'last_name' => trim((string) ($p['last_name'] ?? '')),
            'email' => trim((string) ($p['email'] ?? '')), 'phone' => trim((string) ($p['phone'] ?? '')),
            'role' => (string) ($p['role'] ?? 'collecteur'), 'password' => (string) ($p['password'] ?? ''), 'member_id' => $p['member_id'] ?? null,
        ];
        if ($d['last_name'] === '') {
            $errors['last_name'] = 'Indiquez le nom.';
        }
        if ($d['first_name'] === '') {
            $errors['first_name'] = 'Indiquez le prénom.';
        }
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indiquez une adresse e-mail valide.';
        } elseif ($this->users()->emailTaken($d['email'], $id)) {
            $errors['email'] = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
        }
        if (! isset(Users::ROLES[$d['role']])) {
            $errors['role'] = 'Choisissez un rôle.';
        }
        if ($id === null || $d['password'] !== '') {
            if ($e = Users::passwordErrors($d['password'])) {
                $errors['password'] = 'Le mot de passe doit contenir ' . implode(', ', $e) . '.';
            }
        }
        if (mb_strlen($d['phone']) > 20) {
            $errors['phone'] = 'Le numéro est trop long.';
        }

        return $errors ? ['_errors' => $errors] : $d;
    }
}
