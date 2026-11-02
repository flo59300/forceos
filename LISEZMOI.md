# ForceOS 1.0.0

Conçu et développé par Florian Bigotte pour Force Ouvrière.

ForceOS est un poste de travail Debian 13 durci pour les sections Force Ouvrière : bureau Cinnamon avec le thème Linux Mint aux couleurs FO, FO Gestion en application de bureau qui fonctionne sans Internet, assistant IA local, transcription audio, coffre-fort chiffré, verrouillage d'urgence et assistance à distance encadrée.

## Installation automatique sur un PC sous Debian 13

C'est la méthode la plus simple. Sur un PC où Debian 13 est déjà installé (de préférence avec le disque chiffré, option proposée par l'installateur de Debian), placez le paquet `forceos-fo-gestion_1.0.0_amd64.deb` à côté du dossier `forceos`, ouvrez un terminal dans le dossier `forceos` et tapez :

```
sudo bash installer-forceos.sh
```

ou, si votre compte n'a pas le droit d'utiliser sudo :

```
su -c "bash installer-forceos.sh"
```

Après une seule confirmation (taper OUI), tout se fait seul en 30 à 60 minutes : paquets, FO Gestion, IA locale, transcription, navigateur verrouillé, filtrage, réglages. Le compte qui lance la commande devient le compte du militant ; il perd ses droits d'administration, et le mot de passe root (demandé s'il n'existe pas encore) devient le seul moyen d'administrer le poste. Il ne reste qu'à redémarrer. Journal complet : `/var/log/forceos-installation.log`. En cas de coupure, la commande peut être relancée : ce qui est déjà téléchargé est réutilisé.

Les mises à jour de sécurité de Debian restent automatiques et invisibles. Il n'y a pas d'autre mise à jour à gérer.

## Autres dossiers

`application/` contient FO Gestion pour ForceOS (Electron et FO Gestion 3 adaptée) ; son `LISEZMOI.md` explique comment reconstruire le paquet `.deb`.

`construction/` sert à fabriquer une image d'installation ISO avec live-build, pour équiper de nombreux postes depuis une clé USB. Cette méthode est plus technique ; son `LISEZMOI.md` la décrit.
