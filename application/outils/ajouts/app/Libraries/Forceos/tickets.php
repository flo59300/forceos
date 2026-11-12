<?php
/**
 * ForceOS : tickets à usage unique pour la sauvegarde et la restauration
 * Conçu et développé par Florian Bigotte pour Force Ouvrière
 *
 * La page de FO Gestion obtient un ticket (session et rôle vérifiés), puis le
 * transmet au processus principal d'Electron, seul détenteur du jeton
 * interne. Un ticket sert une seule fois, pendant 60 secondes, pour un usage.
 */
declare(strict_types=1);

const FORCEOS_TICKET_DUREE = 60;

/** Rôles effectifs autorisés par usage (le Super Admin mono-site agit en secrétaire). */
const FORCEOS_ROLES_TICKETS = [
    'export'       => ['secretaire', 'tresorier'],
    'restauration' => ['secretaire'],
];

function forceos_dossier_tickets(): string
{
    $d = rtrim((string) getenv('FORCEOS_DONNEES'), '/') . '/tmp/tickets';
    if (! is_dir($d) && ! mkdir($d, 0700, true) && ! is_dir($d)) {
        throw new RuntimeException('Dossier des tickets inaccessible.');
    }
    return $d;
}

function forceos_creer_ticket(int $idUtilisateur, string $usage): string
{
    if (! isset(FORCEOS_ROLES_TICKETS[$usage])) {
        throw new InvalidArgumentException('Usage de ticket inconnu.');
    }
    $dossier = forceos_dossier_tickets();
    foreach (glob($dossier . '/*.json') ?: [] as $f) {
        $d = json_decode((string) @file_get_contents($f), true);
        if (! is_array($d) || ($d['expire'] ?? 0) < time()) {
            @unlink($f);
        }
    }
    $ticket  = bin2hex(random_bytes(32));
    $fichier = $dossier . '/' . hash('sha256', $ticket) . '.json';
    file_put_contents($fichier, json_encode([
        'utilisateur' => $idUtilisateur, 'usage' => $usage, 'expire' => time() + FORCEOS_TICKET_DUREE,
    ]), LOCK_EX);
    chmod($fichier, 0600);
    return $ticket;
}

/** Consomme un ticket : renvoie l'identifiant de l'utilisateur, ou null. */
function forceos_consommer_ticket(string $ticket, string $usage): ?int
{
    if (! preg_match('/^[a-f0-9]{64}$/', $ticket)) {
        return null;
    }
    $fichier = forceos_dossier_tickets() . '/' . hash('sha256', $ticket) . '.json';
    $contenu = @file_get_contents($fichier);
    // Supprimé avant tout usage : un ticket ne sert qu'une fois
    if ($contenu === false || ! @unlink($fichier)) {
        return null;
    }
    $d = json_decode($contenu, true);
    if (! is_array($d) || ($d['expire'] ?? 0) < time() || ($d['usage'] ?? '') !== $usage) {
        return null;
    }
    return (int) $d['utilisateur'];
}
