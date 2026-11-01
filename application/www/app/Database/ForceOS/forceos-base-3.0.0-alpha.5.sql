-- ============================================================================
-- ForceOS : base initiale de FO Gestion 3.0.0-alpha.5 (mode mono-site)
-- Dérivée de la base vierge officielle, conçue par Florian Bigotte.
--
-- Appliquée automatiquement au premier lancement sur une base vide, puis les
-- mises à jour de app/Database/Updates/ prennent le relais (schema_migrations).
-- Différences avec la base vierge d'origine :
--   * mode « standalone » (un seul site, celui de la section) ;
--   * aucun compte préinstallé : le compte du secrétaire est créé à la
--     première configuration, avec un mot de passe choisi sur le poste ;
--   * directive « sandbox mode » des dumps MariaDB retirée ;
--   * convention ForceOS : chaque instruction finit par « ; » en fin de ligne.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `priority` enum('normale','importante','urgente') NOT NULL DEFAULT 'normale',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` date DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_ann_site_active` (`site_id`,`active`,`pinned` DESC,`created_at` DESC),
  KEY `idx_announcements_site` (`site_id`,`active`,`expires_at`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `app_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `barometre_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `secteur_id` int(10) unsigned DEFAULT NULL,
  `ambiance` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `tags` varchar(500) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `entry_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `secteur_id` (`secteur_id`),
  KEY `idx_baro_site_date` (`site_id`,`entry_date`),
  KEY `idx_barometre_site` (`site_id`,`entry_date`),
  CONSTRAINT `barometre_entries_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barometre_entries_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barometre_entries_ibfk_3` FOREIGN KEY (`secteur_id`) REFERENCES `secteurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `barometre_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `label` varchar(50) NOT NULL,
  `icon` varchar(10) NOT NULL DEFAULT '?️',
  `color` varchar(7) NOT NULL DEFAULT '#6366f1',
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `barometre_tags_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `delegate_questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `priority` enum('normale','urgente') NOT NULL DEFAULT 'normale',
  `status` enum('ouverte','en_cours','resolue','fermee') NOT NULL DEFAULT 'ouverte',
  `response` text DEFAULT NULL,
  `responded_by` int(10) unsigned DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dq_site_status` (`site_id`,`status`),
  KEY `idx_dq_author` (`author_id`),
  KEY `idx_dq_created` (`created_at` DESC),
  CONSTRAINT `delegate_questions_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delegate_questions_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `delegate_todos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('basse','normale','haute') NOT NULL DEFAULT 'normale',
  `status` enum('a_faire','en_cours','fait') NOT NULL DEFAULT 'a_faire',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dt_user_status` (`user_id`,`status`),
  KEY `idx_dt_site` (`site_id`),
  KEY `idx_dt_due` (`due_date`),
  CONSTRAINT `delegate_todos_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delegate_todos_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `delegation_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `entry_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `duration_minutes` int(10) unsigned NOT NULL,
  `motif` enum('reunion_cse','reunion_cssct','terrain','formation','negociation','info_salaries','permanence','convocation_direction','autre') NOT NULL DEFAULT 'terrain',
  `description` varchar(500) DEFAULT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = généré par chrono tournées',
  `tour_log_id` int(10) unsigned DEFAULT NULL COMMENT 'Lien vers tour_logs si auto',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tour_log_id` (`tour_log_id`),
  KEY `idx_deleg_user_date` (`user_id`,`entry_date`),
  KEY `idx_deleg_user_month` (`user_id`,`entry_date`,`motif`),
  KEY `idx_delegation_entries_user` (`user_id`,`entry_date`),
  CONSTRAINT `delegation_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delegation_entries_ibfk_2` FOREIGN KEY (`tour_log_id`) REFERENCES `tour_logs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `delegation_hours` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `quota_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `carry_over_minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Report du mois précédent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_period` (`user_id`,`year`,`month`),
  CONSTRAINT `delegation_hours_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `delegation_transfers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `from_user_id` int(10) unsigned NOT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `minutes` int(10) unsigned NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `idx_transfer_to_status` (`to_user_id`,`status`),
  KEY `idx_transfer_from_period` (`from_user_id`,`year`,`month`),
  CONSTRAINT `delegation_transfers_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delegation_transfers_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delegation_transfers_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `election_colleges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `simulation_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT '',
  `seats` int(10) unsigned NOT NULL,
  `seats_sup` int(10) unsigned NOT NULL DEFAULT 0,
  `inscrits` int(10) unsigned NOT NULL DEFAULT 0,
  `votants` int(10) unsigned NOT NULL DEFAULT 0,
  `votants_sup` int(10) unsigned NOT NULL DEFAULT 0,
  `blancs_nuls` int(10) unsigned NOT NULL DEFAULT 0,
  `blancs_nuls_sup` int(10) unsigned NOT NULL DEFAULT 0,
  `bureaux_json` text DEFAULT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `simulation_id` (`simulation_id`),
  CONSTRAINT `election_colleges_ibfk_1` FOREIGN KEY (`simulation_id`) REFERENCES `election_simulations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `election_listes_electorales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pap_id` int(10) unsigned NOT NULL,
  `site_id` int(10) unsigned NOT NULL,
  `college_index` tinyint(4) NOT NULL DEFAULT 0,
  `college_name` varchar(255) NOT NULL,
  `type` enum('electeur','eligible') NOT NULL,
  `member_id` int(10) unsigned NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `member_matricule` varchar(50) DEFAULT NULL,
  `member_metier` varchar(255) DEFAULT NULL,
  `member_secteur` varchar(255) DEFAULT NULL,
  `member_genre` enum('H','F') DEFAULT NULL,
  `date_entry` date DEFAULT NULL,
  `anciennete_mois` int(11) DEFAULT NULL,
  `is_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `exclusion_motif` varchar(255) DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `idx_liste_pap` (`pap_id`,`type`,`college_index`),
  CONSTRAINT `election_listes_electorales_ibfk_1` FOREIGN KEY (`pap_id`) REFERENCES `election_pap` (`id`) ON DELETE CASCADE,
  CONSTRAINT `election_listes_electorales_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `election_lists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `college_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `sigle` varchar(50) DEFAULT NULL,
  `votes` int(10) unsigned NOT NULL DEFAULT 0,
  `voix_sup` int(10) unsigned NOT NULL DEFAULT 0,
  `categoriel` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = liste catégorielle (ex: CFE-CGC, 2e/3e collège uniquement)',
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `college_id` (`college_id`),
  CONSTRAINT `election_lists_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `election_colleges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `election_pap` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'PAP CSE',
  `election_date` date DEFAULT NULL,
  `election_date_t2` date DEFAULT NULL,
  `anciennete_electeur_mois` int(11) NOT NULL DEFAULT 3,
  `anciennete_eligible_mois` int(11) NOT NULL DEFAULT 12,
  `age_min_electeur` int(11) NOT NULL DEFAULT 16,
  `age_min_eligible` int(11) NOT NULL DEFAULT 18,
  `nb_colleges` tinyint(4) NOT NULL DEFAULT 3,
  `colleges_json` text NOT NULL,
  `syndicats_json` text DEFAULT NULL,
  `regles_complementaires` text DEFAULT NULL,
  `status` enum('brouillon','actif','archive') NOT NULL DEFAULT 'brouillon',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_site_status` (`site_id`,`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `election_pap_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `election_pap_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `election_simulations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `effectif` int(10) unsigned NOT NULL,
  `inscrits` int(10) unsigned NOT NULL DEFAULT 0,
  `votants` int(10) unsigned NOT NULL DEFAULT 0,
  `blancs_nuls` int(10) unsigned NOT NULL DEFAULT 0,
  `type_scrutin` enum('titulaires','suppleants') NOT NULL DEFAULT 'titulaires',
  `is_official` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=simulation, 1=résultat officiel',
  `syndicats_json` text DEFAULT NULL COMMENT 'Liste des syndicats en JSON',
  `election_date` date DEFAULT NULL COMMENT 'Date de l''élection',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_sim_site_official` (`site_id`,`is_official`),
  CONSTRAINT `election_simulations_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `election_simulations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `event_type` enum('reunion','permanence','formation','evenement','election','autre') NOT NULL DEFAULT 'evenement',
  `location` varchar(255) DEFAULT NULL,
  `participants` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_site_date` (`site_id`,`event_date`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `kb_chunks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `site_id` int(10) unsigned NOT NULL,
  `chunk_index` smallint(5) unsigned NOT NULL DEFAULT 0,
  `chunk_text` text NOT NULL,
  `chunk_length` int(10) unsigned NOT NULL DEFAULT 0,
  `tf_json` text DEFAULT NULL COMMENT 'Term frequencies JSON {term: count}',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kb_chunks_doc` (`document_id`),
  KEY `idx_kb_chunks_site` (`site_id`),
  FULLTEXT KEY `ft_chunk_text` (`chunk_text`),
  CONSTRAINT `kb_chunks_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `kb_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kb_chunks_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `kb_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `file_path` varchar(500) DEFAULT NULL COMMENT 'Chemin du fichier original',
  `category` varchar(100) DEFAULT 'general',
  `total_chunks` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_kb_docs_site` (`site_id`),
  CONSTRAINT `kb_documents_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kb_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `kb_idf` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `term` varchar(191) NOT NULL,
  `idf` float NOT NULL DEFAULT 0,
  `doc_freq` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_term` (`site_id`,`term`),
  KEY `idx_kb_idf_site` (`site_id`),
  CONSTRAINT `kb_idf_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `date_birth` date DEFAULT NULL,
  `date_entry` date DEFAULT NULL COMMENT 'Date entrée entreprise',
  `date_adhesion` date DEFAULT NULL COMMENT 'Date adhésion FO',
  `secteur_id` int(10) unsigned DEFAULT NULL,
  `metier_id` int(10) unsigned DEFAULT NULL,
  `categorie` enum('college_1','college_2','college_3') DEFAULT NULL COMMENT 'CSP pour collèges électoraux',
  `genre` enum('H','F') DEFAULT NULL COMMENT 'Genre pour règle de parité électorale',
  `assigned_user_id` int(10) unsigned DEFAULT NULL COMMENT 'Collecteur attribué',
  `tournee_id` int(10) unsigned DEFAULT NULL,
  `payment_mode` enum('manuel','prelevement') NOT NULL DEFAULT 'manuel',
  `year` int(11) NOT NULL DEFAULT 2026 COMMENT 'Année de cotisation',
  `has_card` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Carte délivrée',
  `is_radiated` tinyint(1) NOT NULL DEFAULT 0,
  `radiation_date` date DEFAULT NULL,
  `radiation_motif` text DEFAULT NULL,
  `radiation_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `secteur_id` (`secteur_id`),
  KEY `metier_id` (`metier_id`),
  KEY `tournee_id` (`tournee_id`),
  KEY `idx_members_site` (`site_id`),
  KEY `idx_members_assigned` (`assigned_user_id`),
  KEY `idx_members_radiated` (`is_radiated`),
  KEY `idx_members_payment_mode` (`payment_mode`),
  KEY `idx_members_site_radiated` (`site_id`,`is_radiated`),
  KEY `idx_members_collecteur` (`assigned_user_id`,`is_radiated`),
  CONSTRAINT `members_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `members_ibfk_2` FOREIGN KEY (`secteur_id`) REFERENCES `secteurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `members_ibfk_3` FOREIGN KEY (`metier_id`) REFERENCES `metiers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `members_ibfk_4` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `members_ibfk_5` FOREIGN KEY (`tournee_id`) REFERENCES `tournees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `sender_id` int(10) unsigned NOT NULL,
  `recipient_id` int(10) unsigned NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_recipient_read` (`recipient_id`,`is_read`),
  KEY `idx_site_created` (`site_id`,`created_at`),
  KEY `idx_messages_unread` (`recipient_id`,`site_id`,`is_read`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `metiers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `metiers_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `site_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `url` varchar(500) DEFAULT '/index.php',
  `type` enum('message','delegation','agenda','transfer','system') NOT NULL DEFAULT 'system',
  `icon` varchar(10) DEFAULT '?',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notif_user_date` (`user_id`,`created_at`),
  KEY `idx_notifications_user` (`user_id`,`is_read`),
  KEY `idx_notif_unread` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `permanence_bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slot_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `member_id` int(10) unsigned DEFAULT NULL,
  `booked_by` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','email_sent','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `attended` tinyint(1) DEFAULT NULL COMMENT 'NULL=pas encore, 1=présent, 0=absent',
  `notes` text DEFAULT NULL COMMENT 'Notes internes secrétaire',
  `verification_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `booked_by` (`booked_by`),
  KEY `idx_booking_slot` (`slot_id`),
  KEY `idx_booking_verification` (`verification_token`),
  KEY `idx_booking_status` (`slot_id`,`status`),
  CONSTRAINT `permanence_bookings_ibfk_1` FOREIGN KEY (`slot_id`) REFERENCES `permanence_slots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permanence_bookings_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `permanence_bookings_ibfk_3` FOREIGN KEY (`booked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `permanence_slots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `permanence_id` int(10) unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_bookings` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_slot_perm` (`permanence_id`),
  KEY `idx_slot_perm_order` (`permanence_id`,`start_time`),
  CONSTRAINT `permanence_slots_ibfk_1` FOREIGN KEY (`permanence_id`) REFERENCES `permanences` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `permanences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `perm_date` date NOT NULL,
  `max_participants` int(10) unsigned DEFAULT NULL COMMENT 'Quota total (NULL = illimité)',
  `token` varchar(64) NOT NULL,
  `series_token` varchar(64) DEFAULT NULL COMMENT 'Token de série calendrier',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `created_by` (`created_by`),
  KEY `idx_perm_date` (`perm_date`),
  KEY `idx_perm_site` (`site_id`),
  KEY `idx_perm_token` (`token`),
  CONSTRAINT `permanences_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permanences_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `prelevement_validations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `year` int(11) NOT NULL,
  `semester` tinyint(3) unsigned NOT NULL COMMENT '1 = S1 (jan-juin), 2 = S2 (juil-déc)',
  `nb_adherents` int(10) unsigned NOT NULL DEFAULT 0,
  `nb_timbres_attendus` int(10) unsigned NOT NULL DEFAULT 0,
  `nb_timbres_presents` int(10) unsigned NOT NULL DEFAULT 0,
  `nb_manquants` int(10) unsigned NOT NULL DEFAULT 0,
  `montant_attendu` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_present` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('conforme','anomalies','force') NOT NULL DEFAULT 'conforme',
  `notes` text DEFAULT NULL,
  `validated_by` int(10) unsigned NOT NULL,
  `validated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_year_sem` (`site_id`,`year`,`semester`),
  KEY `validated_by` (`validated_by`),
  CONSTRAINT `prelevement_validations_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prelevement_validations_ibfk_2` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `push_agenda_sent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `reminder_type` enum('created','reminder_1d','reminder_1h') NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_reminder` (`event_id`,`reminder_type`),
  CONSTRAINT `push_agenda_sent_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `push_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_push_user` (`user_id`),
  CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `reminder_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Collecteur destinataire',
  `nb_members` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_site_sent` (`site_id`,`sent_at`),
  CONSTRAINT `reminder_logs_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reminder_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `reversements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Collecteur qui reverse',
  `amount` decimal(8,2) NOT NULL,
  `nb_timbres` int(11) NOT NULL DEFAULT 0,
  `nb_cartes` int(11) NOT NULL DEFAULT 0,
  `payment_method` enum('especes','cheque','virement') NOT NULL DEFAULT 'especes',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `validated_by` int(10) unsigned DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `validated_by` (`validated_by`),
  KEY `idx_reversements_user` (`user_id`),
  CONSTRAINT `reversements_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reversements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reversements_ibfk_3` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `rss_feeds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_fetched` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `rss_feeds_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `sales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int(10) unsigned NOT NULL,
  `site_id` int(10) unsigned NOT NULL,
  `type` enum('timbre','carte') NOT NULL,
  `month` tinyint(4) DEFAULT NULL COMMENT '1-12, NULL pour carte',
  `year` int(11) NOT NULL,
  `amount` decimal(6,2) NOT NULL,
  `sold_by` int(10) unsigned DEFAULT NULL COMMENT 'Utilisateur vendeur',
  `payment_method` enum('especes','cheque','virement','prelevement') NOT NULL DEFAULT 'especes',
  `is_reversed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Reversé à la trésorerie',
  `reversement_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reversement_id` (`reversement_id`),
  KEY `idx_sales_member` (`member_id`),
  KEY `idx_sales_site_year` (`site_id`,`year`),
  KEY `idx_sales_sold_by` (`sold_by`),
  KEY `idx_sales_type_month` (`site_id`,`year`,`type`,`month`),
  KEY `idx_sales_payment_method` (`payment_method`),
  KEY `idx_sales_year_type` (`year`,`type`,`site_id`),
  KEY `idx_sales_reversed` (`year`,`is_reversed`,`site_id`),
  KEY `idx_sales_soldby` (`sold_by`,`year`,`is_reversed`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_ibfk_4` FOREIGN KEY (`reversement_id`) REFERENCES `reversements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `schema_migrations` (
  `migration` varchar(100) NOT NULL,
  `mode` enum('run','baseline') NOT NULL DEFAULT 'run',
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `statements` int(10) unsigned NOT NULL DEFAULT 0,
  `warnings` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `secteurs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `secteurs_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `secteurs_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `secteurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `tarif_timbre` decimal(6,2) NOT NULL DEFAULT 5.00,
  `tarif_carte` decimal(6,2) NOT NULL DEFAULT 2.00,
  `seuil_timbres_annuel` tinyint(3) unsigned DEFAULT NULL,
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `reminder_frequency_days` tinyint(3) unsigned NOT NULL DEFAULT 7 COMMENT 'Fréquence rappels auto en jours (0 = désactivé)',
  `effectif_site` int(10) unsigned DEFAULT NULL COMMENT 'Effectif total du site (salariés) pour calcul représentativité',
  `logo_path` varchar(255) DEFAULT NULL COMMENT 'Logo personnalisé du site',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `bulletin_board_enabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `type` enum('timbre','carte') NOT NULL,
  `movement` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `tour_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `tournee_id` int(10) unsigned DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL COMMENT 'Nom libre si pas de tournée',
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `tournee_id` (`tournee_id`),
  CONSTRAINT `tour_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tour_logs_ibfk_2` FOREIGN KEY (`tournee_id`) REFERENCES `tournees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `tournees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `tournees_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `user_function_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `function_id` int(10) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_func` (`user_id`,`function_id`),
  KEY `function_id` (`function_id`),
  CONSTRAINT `user_function_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_function_assignments_ibfk_2` FOREIGN KEY (`function_id`) REFERENCES `user_functions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_functions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `label` varchar(100) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6366f1',
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `user_functions_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `user_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `category` enum('terrain','suivi','personnel','reunion') NOT NULL DEFAULT 'terrain',
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_user_notes_order` (`user_id`,`pinned`,`updated_at`),
  CONSTRAINT `user_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `user_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `priority` enum('haute','normale','basse') NOT NULL DEFAULT 'normale',
  `due_date` date DEFAULT NULL,
  `done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` enum('super_admin','secretaire','tresorier','collecteur') NOT NULL DEFAULT 'collecteur',
  `phone` varchar(20) DEFAULT NULL,
  `member_id` int(10) unsigned DEFAULT NULL COMMENT 'Lien double casquette',
  `tournee_id` int(10) unsigned DEFAULT NULL COMMENT 'Tournée assignée',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `push_notif_messages` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Notif: nouveau message',
  `push_notif_delegation` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Notif: seuil délégation',
  `push_notif_agenda` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Notif: rappel événement',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `member_id` (`member_id`),
  KEY `tournee_id` (`tournee_id`),
  KEY `idx_users_site_role` (`site_id`,`role`,`active`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`tournee_id`) REFERENCES `tournees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE `years` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `year` int(11) NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `tarif_timbre` decimal(6,2) NOT NULL DEFAULT 5.00,
  `tarif_carte` decimal(6,2) NOT NULL DEFAULT 2.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_year` (`site_id`,`year`),
  CONSTRAINT `years_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------------------------------------------------------
-- Migrations déjà incluses dans cette structure
-- (la 2.x comme la 3.0 ne les rejoueront pas)
-- ----------------------------------------------------------------------------
INSERT INTO `schema_migrations` (`migration`, `mode`, `success`) VALUES
('UPDATE.sql', 'baseline', 1),
('UPDATE-v2.sql', 'baseline', 1),
('UPDATE-v3.sql', 'baseline', 1),
('UPDATE-v4.sql', 'baseline', 1),
('UPDATE-v5.sql', 'baseline', 1),
('UPDATE-v6.sql', 'baseline', 1),
('UPDATE-v7.sql', 'baseline', 1),
('UPDATE-v8.sql', 'baseline', 1),
('UPDATE-v9.sql', 'baseline', 1),
('UPDATE-v10.sql', 'baseline', 1),
('UPDATE-v11.sql', 'baseline', 1),
('UPDATE-v12.sql', 'baseline', 1),
('UPDATE-v13.sql', 'baseline', 1),
('UPDATE-v14.sql', 'baseline', 1),
('UPDATE-v15.sql', 'baseline', 1),
('UPDATE-v16.sql', 'baseline', 1),
('UPDATE-v17.sql', 'baseline', 1),
('UPDATE-v18.sql', 'baseline', 1),
('UPDATE-v19.sql', 'baseline', 1),
('UPDATE-v20.sql', 'baseline', 1),
('UPDATE-v21.sql', 'baseline', 1),
('UPDATE-v22.sql', 'baseline', 1),
('UPDATE-v23.sql', 'baseline', 1),
('UPDATE-v24.sql', 'baseline', 1),
('UPDATE-v25.sql', 'baseline', 1),
('UPDATE-v26.sql', 'baseline', 1),
('UPDATE-v27.sql', 'baseline', 1),
('UPDATE-v28.sql', 'baseline', 1),
('UPDATE-v29.sql', 'baseline', 1),
('UPDATE-v30.sql', 'baseline', 1),
('UPDATE-v31.sql', 'baseline', 1),
('UPDATE-v32.sql', 'baseline', 1),
('UPDATE-v33.sql', 'baseline', 1),
('UPDATE-v34.sql', 'baseline', 1),
('UPDATE-v35.sql', 'baseline', 1),
('UPDATE-v36.sql', 'baseline', 1),
('UPDATE-v37.sql', 'baseline', 1),
('UPDATE-v38.sql', 'baseline', 1),
('UPDATE-v39.sql', 'baseline', 1),
('UPDATE-v40.sql', 'baseline', 1),
('fo3/3.0.0-001-notes-index.sql', 'baseline', 1);

-- ----------------------------------------------------------------------------
-- Réglages par défaut (modifiables dans Plateforme, Réglages)
-- ----------------------------------------------------------------------------
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'FO Gestion'),
('app_description', 'Hub syndical Force Ouvrière : adhérents, finances, terrain, élections'),
('app_mode', 'standalone'),
('footer_copyright', 'Force Ouvrière'),
('footer_text', 'ForceOS : conçu et développé par Florian Bigotte'),
('kb_shared', '0'),
('maintenance_mode', '0'),
('legal_mentions', ''),
('legal_rgpd', ''),
('legal_cgu', ''),
('pwa_short_name', 'FO Gestion'),
('pwa_theme_color', '#002b7f'),
('pwa_background_color', '#f1f5f9'),
('pwa_display', 'standalone'),
('pwa_logo_path', '/assets/icons/fostellantis.png'),
('pwa_favicon_path', '/assets/icons/favicon.svg');


SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 1;
-- FIN DE LA BASE INITIALE FORCEOS
