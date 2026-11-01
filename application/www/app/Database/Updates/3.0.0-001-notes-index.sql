-- FO Gestion 3 : première migration de la version 3.
-- Index adapté à l'ordre d'affichage des notes personnelles (épinglées, puis dernières modifiées).
-- Sans effet sur la version 2.x, qui lit la même table.
CREATE INDEX idx_user_notes_order ON user_notes (user_id, pinned, updated_at);
