# Migrations de la version 3

Chaque fichier `.sql` de ce dossier est exécuté une fois, dans l'ordre des noms, lors d'une mise à jour
(espace Super Admin, Mises à jour) ou par la commande `php spark fo:migrer`.
Le passage est noté dans la table `schema_migrations` sous le nom `fo3/<fichier>`.

Règles : nommer `<version>-<numéro>-<objet>.sql`, écrire des instructions rejouables
(un index ou une colonne déjà présents sont tolérés), ne jamais supprimer de données
tant que la version 2.x partage la base.
