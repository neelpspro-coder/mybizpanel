-- Mise à jour pour ajouter le système d'archivage aux projets existants

-- Ajouter les colonnes d'archivage si elles n'existent pas déjà
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS is_archived BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS archived_by VARCHAR(50);

-- Créer les index pour optimiser les requêtes
ALTER TABLE projects 
ADD INDEX IF NOT EXISTS idx_archived (is_archived),
ADD INDEX IF NOT EXISTS idx_archived_by (archived_by);

-- Marquer automatiquement les projets "completed" comme candidats à l'archivage
-- (Optionnel : décommentez la ligne suivante si vous voulez auto-archiver les projets terminés)
-- UPDATE projects SET is_archived = 1, archived_at = NOW() WHERE status = 'completed' AND is_archived = 0;