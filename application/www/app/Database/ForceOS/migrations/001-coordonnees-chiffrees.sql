-- ============================================================================
-- ForceOS : migration 001, coordonnées des adhérents chiffrées
-- Conçu par Florian Bigotte pour Force Ouvrière
--
-- Une valeur chiffrée (nonce, données, étiquette, en base64) est bien plus
-- longue que la valeur d'origine : un téléphone de 10 chiffres devient une
-- chaîne d'environ 70 caractères. Les colonnes chiffrées passent donc en
-- texte. La date de naissance devient du texte (la date reste testable par
-- « IS NULL », seul usage qu'en fait la base). Propre à ForceOS : cette
-- migration n'est jamais appliquée sur hub-fo.
-- ============================================================================
ALTER TABLE `members`
  MODIFY `email` text DEFAULT NULL COMMENT 'Chiffré (ForceOS)',
  MODIFY `phone` text DEFAULT NULL COMMENT 'Chiffré (ForceOS)',
  MODIFY `address` text DEFAULT NULL COMMENT 'Chiffré (ForceOS)',
  MODIFY `city` text DEFAULT NULL COMMENT 'Chiffré (ForceOS)',
  MODIFY `postal_code` text DEFAULT NULL COMMENT 'Chiffré (ForceOS)',
  MODIFY `date_birth` varchar(255) DEFAULT NULL COMMENT 'Chiffré (ForceOS)';
