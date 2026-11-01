# FO Gestion pour ForceOS : application Electron

Conçu et développé par Florian Bigotte pour Force Ouvrière.

## Ce que contient ce dossier

L'application de bureau qui embarque FO Gestion 3 (CodeIgniter 4) sur un poste ForceOS. Aucun serveur n'écoute : le processus principal Electron (`main.js` et `lib/`) intercepte les requêtes de sa propre fenêtre et les confie à `php-cgi`, un processus PHP neuf par requête (`lib/moteur-php.js`), branché sur la base MariaDB du poste par son socket Unix. La fenêtre n'a ni Node ni accès à Internet : elle ne parle qu'à FO Gestion, et les fonctions du poste (sauvegarde, assistant IA, transcription audio, documents de l'IA) passent par un pont limité défini dans `preload.js`.

Le dossier `www/` est produit par `outils/integrer-fo-gestion.py` à partir des sources de FO Gestion 3. Il retire l'installeur web, place `writable/` dans le dossier du militant, chiffre les coordonnées des adhérents, ajoute les points d'entrée internes, l'assistant IA et la page « Sauvegarde chiffrée », et conserve hors ligne les derniers cours de bourse et articles RSS connus. Les fichiers ajoutés sont dans `outils/ajouts/`. Le script s'arrête si une future version de FO Gestion modifie le code qu'il adapte.

## Construire le paquet

Sur une machine Debian 13 ou équivalente, avec Node.js 22 et les sources de FO Gestion 3 placées dans `../fo-gestion-3` :

```
npm ci
npm run build:deb
```

Le paquet obtenu, `dist/forceos-fo-gestion_1.0.0_amd64.deb`, s'installe dans `/opt/FO Gestion/` et se dépose dans `../construction/config/packages.chroot/` par le script de construction de l'ISO. Si `www/` est déjà à jour, `npx electron-builder --linux deb --x64 --publish never` suffit.

## Fichiers système attendus sur le poste

Le paquet est fait pour ForceOS et lit ces emplacements fixes (les variables `FORCEOS_*` de développement sont ignorées une fois l'application empaquetée, pour qu'un militant ne puisse pas les détourner) :

`/etc/forceos/utilisateur-autorise` (le seul compte Linux autorisé, fichier root en 0644), `/etc/forceos/ia/prompts.json` (consignes de l'IA, modifiables avec le mot de passe administrateur par `/usr/libexec/forceos/ecrire-prompts`), `/usr/bin/php-cgi` (moteur de FO Gestion), `/run/mysqld/mysqld.sock` (MariaDB, accès par le compte Linux), `http://127.0.0.1:11434` (Ollama), `/usr/lib/forceos/whisper/whisper-cli` et `/usr/share/forceos/whisper/ggml-small.bin` (transcription). Les données du militant sont dans `~/.config/ForceOS`, dossier en 0700 ; la clé maîtresse y est conservée chiffrée par le trousseau GNOME de la session.

## Protection contre PHP2Shell

Les failles PHP2Shell (CVE-2026-109618 et CVE-2026-109619, corrigées le 21 octobre 2026) se déclenchent pendant la lecture d'un envoi de fichier, avant tout code de l'application : le jeton du routeur ne protégeait donc pas l'ancien serveur intégré, que n'importe quelle page web ouverte dans le navigateur du poste pouvait atteindre sur 127.0.0.1. Cinq barrières sont désormais en place. Plus aucun port n'est ouvert, seule la fenêtre FO Gestion et le processus principal atteignent PHP. Chaque requête a son propre processus, ce qui rend impossible l'enchaînement de deux requêtes sur un même processus. OPcache ne garde son cache que sur disque, sans mémoire partagée. Le suivi de progression des envois, voie d'exploitation confirmée, est désactivé. Enfin, l'application refuse de démarrer avec un PHP non corrigé (Debian 13 : php8.4 8.4.27-1~deb13u1 ou plus récent), et le paquet comme l'image l'exigent.

## Sauvegarde et restauration

La page « Sauvegarde chiffrée » (secrétaire et trésorier) produit, de préférence sur une clé USB autorisée, un fichier `sauvegarde-fo-<date>.sql.enc` chiffré en AES-256-GCM avec une clé dérivée de la phrase de passe par scrypt. Il contient la base complète et la clé maîtresse, ce qui permet de le restaurer sur un autre poste. La restauration (secrétaire seulement) prend d'abord un instantané chiffré de la base, revient en arrière en cas de problème, ferme toutes les sessions ouvertes, puis redémarre l'application.

## Tests réalisés

Sous Electron 45 réel, sans écran, avec MariaDB 10.11 et PHP 8.3, en quatre phases : premier lancement et première configuration, sécurité de la fenêtre (pas de Node, Internet bloqué, points internes refusés, pont limité), absence de tout serveur PHP joignable par un autre programme (requête simple et envoi multipart piégé), envoi de fichier légitime depuis la fenêtre, menu, assistant, chiffrement des coordonnées, sauvegarde illisible sans sa phrase, restauration qui annule une modification ; relancement après restauration ; restauration sur un autre poste avec une autre clé maîtresse, refus d'une mauvaise phrase sans aucune modification ; relancement sur ce nouveau poste avec reconnexion exigée et coordonnées déchiffrées. S'y ajoutent les suites PHP (installation, chiffrement, cycle de migrations et instantanés, base documentaire, fonctionnement hors ligne) et un démarrage du paquet construit, en mode empaqueté, jusqu'à la fenêtre de première configuration.
