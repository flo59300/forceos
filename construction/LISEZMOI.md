# ForceOS 1.0.0 : construction de l'image d'installation

Conçu et développé par Florian Bigotte pour Force Ouvrière.

## Construire une image (méthode avancée)

Pour transformer simplement un PC déjà sous Debian 13, utilisez plutôt `installer-forceos.sh`, à la racine du dossier `forceos` (voir son `LISEZMOI.md`). La construction d'une image ISO ci-dessous sert à installer de nombreux postes depuis une clé USB.

## Préparer une fois

La construction se fait en root sur une machine Debian 13 (trixie) amd64 reliée à Internet, avec une quarantaine de gigaoctets libres. Deux éléments peuvent être fournis avant la première construction.

Le serveur d'assistance se déclare dans `config/includes.chroot/etc/forceos/support.conf` : nom de votre serveur RustDesk et clé publique de son service hbbs. Sans lui, l'image se construit quand même, mais le bouton « Contacter le support » reste inactif.

Les livres numériques du syndicat (ePub) se déposent dans `config/includes.chroot/etc/skel/Documents/Bibliothèque_Syndicale/`. Les modèles de documents sont déjà fabriqués ; après une modification, `outils/modeles/construire-modeles.sh` les refait (scribus, libreoffice, xvfb et la police Carlito sont nécessaires).

## Construire

```
sudo ./construire.sh /chemin/vers/forceos-fo-gestion_1.0.0_amd64.deb
```

Le script vérifie la machine, copie le paquet FO Gestion, télécharge RustDesk et Ollama en contrôlant leurs empreintes (fichier `versions.conf`), compile whisper.cpp à une révision précise et sans optimisation propre au processeur, télécharge le modèle de transcription en contrôlant son empreinte, prépare le modèle d'IA avec le binaire Ollama vérifié, contrôle la syntaxe des scripts, du pare-feu et des règles polkit, puis lance live-build. Les téléchargements restent dans `cache/` pour les constructions suivantes. Le résultat est `forceos-1.0.0-amd64.iso` accompagné de son empreinte SHA-256, à copier sur une clé avec `dd` ou Raspberry Pi Imager.

## Installer un poste

La clé démarre sur l'installateur Debian en français, préréglé. Restent posés volontairement : le disque à effacer, la phrase de chiffrement du disque, le mot de passe administrateur (root, que vous gardez) et le nom et mot de passe du militant. Le disque entier est chiffré (LUKS2 et LVM), sans partition d'échange sur disque. Le militant n'est pas administrateur. À la fin, son compte devient le seul autorisé à ouvrir FO Gestion, et sa base de données est créée au premier démarrage. Au premier lancement de FO Gestion, il saisit le nom de la section et crée son compte de secrétaire.

## Ce que contient l'image

Système : Debian 13, noyau et microcodes à jour, mémoire d'échange compressée en RAM, journaux en mémoire, réglages de sécurité du noyau, mises à jour de sécurité Debian et LibreWolf automatiques, uniquement les traductions françaises (la règle dpkg s'applique aussi aux mises à jour).

Bureau XFCE : thème Greybird aux couleurs FO, police Carlito, écran de démarrage bleu où se saisit la phrase du disque, écran de connexion LightDM, verrouillage après 2 minutes, huit lanceurs sur le bureau, raccourci discret Ctrl+Alt+Pause pour le verrouillage d'urgence. Les réglages passent par `/etc/xdg/forceos`, placé avant `/etc/xdg` : aucun fichier de paquet Debian n'est modifié.

FO Gestion et son environnement : PHP sans aucun port réseau, un processus par requête, version corrigée contre PHP2Shell exigée à la construction comme au démarrage ; MariaDB sans aucun port réseau, isolée par systemd et accessible par le seul compte Linux du militant ; Ollama avec le modèle qwen2.5:3b-instruct, écoutant sur 127.0.0.1 et interdit de réseau par systemd ; whisper.cpp et son modèle « small » pour la transcription.

Coffre-fort FO : fichier chiffré `~/.coffre-fo.luks` (LUKS2, argon2id) monté sur `~/Coffre-FO`, créé, ouvert et fermé par l'icône du bureau. Il s'ajoute au chiffrement du disque : un poste volé allumé ne donne pas accès au coffre fermé. La phrase du coffre ne passe jamais sur une ligne de commande.

Verrouillage d'urgence : écran verrouillé, FO Gestion fermée (sa clé quitte la mémoire), applications de documents fermées sans enregistrement, coffre refermé de force, assistance coupée, caches vidés. Le comportement final (verrouiller ou éteindre) se règle dans `/etc/forceos/urgence.conf`.

Assistance : RustDesk n'est démarré que pendant une assistance acceptée par le militant ; le pare-feu ne l'ouvre que vers votre serveur, pour 120 minutes au plus, puis se referme seul.

Mises à jour : seules les mises à jour de sécurité Debian et LibreWolf, automatiques et invisibles. Il n'existe plus d'outil de mise à jour ForceOS par clé USB.

Clés USB : seules les clés autorisées par l'administrateur (fabricant, modèle et numéro de série) sont lisibles ; claviers, souris, imprimantes et téléphones ne sont pas concernés. Une clé inconnue est bloquée par le noyau, puis autorisée en une fois par « Autoriser une clé USB » (menu Système, mot de passe administrateur). Pensez à autoriser les clés de sauvegarde de la section.

Navigateur : LibreWolf verrouillé (configuration et extensions bloquées, DNS chiffré et proxy interdits, effacement à la fermeture), isolé par Firejail qui lui masque le coffre, les données de FO Gestion et les documents de l'IA ; filtrage DNS CleanBrowsing « Adult » et pare-feu qui bloque tout autre serveur DNS.

Administration : polkit ne reconnaît que root comme administrateur ; installer des logiciels, gérer les comptes, changer l'heure ou monter un disque interne demande son mot de passe.

Outils : clic droit « Analyser avec l'antivirus » (ClamTk, sans démon résident), « Rendre le PDF consultable (OCR) », « Ajouter aux documents de l'IA » ; mode Présentation ; modèles « Tract A5 Force Ouvrière », « Lettre à la direction FO », « Grille de salaires FO » ; impression des imprimantes réseau détectée automatiquement.

## Organisation des fichiers

`construire.sh` et `versions.conf` pilotent la construction ; `auto/` contient les paramètres de live-build ; `config/package-lists/` les paquets Debian ; `config/hooks/normal/` les scripts exécutés dans l'image (dépôt LibreWolf, français, services, sécurité, contrôle de l'IA, apparence, finalisation) ; `config/includes.chroot/` les fichiers copiés tels quels ; `config/includes.chroot_before_packages/` la règle des traductions ; `config/includes.installer/preseed.cfg` les réponses de l'installateur ; `outils/` la fabrication des modèles de documents.

## Vérifications faites ici, et celles qui restent à faire

L'image n'a pas pu être construite dans l'environnement de travail : les miroirs Debian et les dépôts LibreWolf, Ollama et Hugging Face y sont inaccessibles. Ont été vérifiés : syntaxe de tous les scripts (shellcheck), pare-feu (nft), actions et règles polkit, règle udev ; les empreintes de RustDesk 1.5.0, Ollama v0.40.12 et du modèle whisper relevées à la source le 28 octobre 2026. Ont été testés en fonctionnement réel : le coffre-fort (refus d'une phrase courte, d'un lien piégé et d'un fichier d'un autre compte, nettoyage complet après un échec, format LUKS2 argon2id) ; l'autorisation des clés USB ; l'ouverture et la fermeture de l'assistance dans le pare-feu, avec expiration ; l'écriture des consignes de l'IA et ses refus ; la création de la base pour le seul compte du militant.

Au premier essai sur une vraie machine, à contrôler : la construction elle-même (`construction.log`), en particulier la disponibilité de tous les paquets de `forceos.list.chroot` dans trixie ; l'installation complète et la saisie de la phrase de chiffrement au clavier français ; l'ouverture réelle du coffre (le montage chiffré n'a pas pu être essayé ici) ; `about:policies` dans LibreWolf et sa langue ; le DNS filtrant sur le réseau de l'entreprise, qui peut bloquer les serveurs extérieurs ; une clé USB inconnue puis autorisée ; une session d'assistance avec votre serveur ; la reconnaissance du service RustDesk, dont le paquet peut changer de nom de service selon les versions.
