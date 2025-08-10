<?php
// Gestion des actions CRUD pour les projets
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                // Validation des données
                $validationErrors = validateProject($_POST['name'], $_POST['status'], $_POST['priority']);
                if (!empty($validationErrors)) {
                    $error = implode(', ', $validationErrors);
                    break;
                }
                
                $id = generateId();
                $stmt = $pdo->prepare("
                    INSERT INTO projects (id, name, description, status, priority, start_date, end_date, budget, client_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    trim($_POST['name']),
                    trim($_POST['description']) ?: null,
                    $_POST['status'] ?: 'planning',
                    $_POST['priority'] ?: 'medium',
                    $_POST['start_date'] ?: null,
                    $_POST['end_date'] ?: null,
                    $_POST['budget'] ? floatval($_POST['budget']) : null,
                    $_POST['client_id'] ?: null,
                    $_SESSION['user_id']
                ]);
                logSystemEvent('info', "Nouveau projet créé : {$_POST['name']}", 'project_create');
                $success = "Projet créé avec succès !";
                
                // Notification automatique
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Nouveau projet créé : " . addslashes($_POST['name']) . "', 'success'); });</script>";
                break;
                
            case 'update':
                $stmt = $pdo->prepare("
                    UPDATE projects 
                    SET name = ?, description = ?, status = ?, priority = ?, start_date = ?, end_date = ?, budget = ?, client_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['status'],
                    $_POST['priority'],
                    $_POST['start_date'] ?: null,
                    $_POST['end_date'] ?: null,
                    $_POST['budget'] ?: null,
                    $_POST['client_id'] ?: null,
                    $_POST['id']
                ]);
                logSystemEvent('info', "Projet modifié : {$_POST['name']}", 'project_update');
                $success = "Projet modifié avec succès !";
                
                // Notification automatique
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Projet modifié : " . addslashes($_POST['name']) . "', 'success'); });</script>";
                break;
                
            case 'archive':
                $stmt = $pdo->prepare("SELECT name, status FROM projects WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $project = $stmt->fetch();
                
                if ($project) {
                    $stmt = $pdo->prepare("
                        UPDATE projects 
                        SET is_archived = 1, archived_at = NOW(), archived_by = ?, status = 'completed' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $_POST['id']]);
                    logSystemEvent('info', "Projet archivé : {$project['name']}", 'project_archive');
                    $success = "Projet archivé avec succès !";
                    
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Projet archivé : " . addslashes($project['name']) . "', 'success'); });</script>";
                }
                break;
                
            case 'unarchive':
                $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $projectName = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    UPDATE projects 
                    SET is_archived = 0, archived_at = NULL, archived_by = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['id']]);
                logSystemEvent('info', "Projet désarchivé : $projectName", 'project_unarchive');
                $success = "Projet désarchivé avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $projectName = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                logSystemEvent('warning', "Projet supprimé définitivement : $projectName", 'project_delete');
                $success = "Projet supprimé définitivement !";
                break;
        }
    }
}

// Récupération du projet à éditer
$editProject = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editProject = $stmt->fetch();
}

// Gestion de l'affichage (actifs ou archives)
$showArchived = isset($_GET['view']) && $_GET['view'] === 'archived';

// Récupération des projets (PARTAGÉS - visibles par tous)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email as author_email, c.name as client_name, c.company as client_company,
               archiver.email as archived_by_email
        FROM projects p 
        LEFT JOIN users u ON p.user_id = u.id 
        LEFT JOIN clients c ON p.client_id = c.id 
        LEFT JOIN users archiver ON p.archived_by = archiver.id
        WHERE p.is_archived = ?
        ORDER BY " . ($showArchived ? "p.archived_at DESC" : "p.created_at DESC") . "
    ");
    $stmt->execute([$showArchived ? 1 : 0]);
    $projects = $stmt->fetchAll();
} catch (Exception $e) {
    $projects = [];
    $error = "Erreur lors de la récupération des projets : " . $e->getMessage();
}
?>

<div class="space-y-6">
    <!-- Messages -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?= sanitizeOutput($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?= sanitizeOutput($error) ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire nouveau/édition projet -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $editProject): ?>
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <?= $editProject ? 'Modifier le projet' : 'Nouveau projet' ?>
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?= $editProject ? 'update' : 'create' ?>">
            <?php if ($editProject): ?>
            <input type="hidden" name="id" value="<?= $editProject['id'] ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom du projet *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= sanitizeOutput($editProject['name'] ?? '') ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                    <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="">Aucun client</option>
                        <?php
                        try {
                            $clientsStmt = $pdo->query("SELECT id, name, company FROM clients ORDER BY name ASC");
                            $clientsList = $clientsStmt->fetchAll();
                            foreach ($clientsList as $client):
                        ?>
                        <option value="<?= $client['id'] ?>" <?= ($editProject['client_id'] ?? '') === $client['id'] ? 'selected' : '' ?>>
                            <?= sanitizeOutput($client['name']) ?><?= $client['company'] ? ' (' . sanitizeOutput($client['company']) . ')' : '' ?>
                        </option>
                        <?php endforeach; } catch (Exception $e) {} ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="planning" <?= ($editProject['status'] ?? '') === 'planning' ? 'selected' : '' ?>>Planification</option>
                        <option value="active" <?= ($editProject['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                        <option value="completed" <?= ($editProject['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminé</option>
                        <option value="on-hold" <?= ($editProject['status'] ?? '') === 'on-hold' ? 'selected' : '' ?>>En pause</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priorité</label>
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="low" <?= ($editProject['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Basse</option>
                        <option value="medium" <?= ($editProject['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Moyenne</option>
                        <option value="high" <?= ($editProject['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Haute</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Budget (€)</label>
                    <input type="number" step="0.01" name="budget" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= $editProject['budget'] ?? '' ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de début</label>
                    <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= $editProject['start_date'] ?? '' ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de fin</label>
                    <input type="date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= $editProject['end_date'] ?? '' ?>">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500"><?= sanitizeOutput($editProject['description'] ?? '') ?></textarea>
            </div>
            
            <div class="flex space-x-4">
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <?= $editProject ? 'Modifier' : 'Créer' ?>
                </button>
                <a href="?page=projects" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">Annuler</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    
    <!-- Liste des projets -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-semibold">
                    <?= $showArchived ? 'Archives Projets' : 'Projets Actifs' ?>
                </h3>
                <div class="flex space-x-2">
                    <a href="?page=projects" 
                       class="px-3 py-1 rounded-lg text-sm <?= !$showArchived ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <i class="fas fa-list mr-1"></i>Actifs
                    </a>
                    <a href="?page=projects&view=archived" 
                       class="px-3 py-1 rounded-lg text-sm <?= $showArchived ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        <i class="fas fa-archive mr-1"></i>Archives
                    </a>
                </div>
            </div>
            <?php if (!$showArchived): ?>
            <a href="?page=projects&action=new" class="btn-primary px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Nouveau Projet
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($projects)): ?>
        <div class="text-center text-gray-500 py-12">
            <i class="fas fa-project-diagram text-4xl mb-4"></i>
            <p>Aucun projet trouvé</p>
            <p class="text-sm">Commencez par créer votre premier projet</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 px-4">Nom</th>
                        <th class="text-left py-3 px-4">Client</th>
                        <th class="text-left py-3 px-4">Statut</th>
                        <th class="text-left py-3 px-4">Priorité</th>
                        <th class="text-left py-3 px-4">Budget</th>
                        <th class="text-left py-3 px-4">Auteur</th>
                        <th class="text-center py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div>
                                <p class="font-medium"><?= sanitizeOutput($project['name']) ?></p>
                                <?php if ($project['description']): ?>
                                <p class="text-sm text-gray-500"><?= sanitizeOutput(substr($project['description'], 0, 50)) ?>...</p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($project['client_name']): ?>
                                <span class="text-sm font-medium"><?= sanitizeOutput($project['client_name']) ?></span>
                                <?php if ($project['client_company']): ?>
                                <br><span class="text-xs text-gray-500"><?= sanitizeOutput($project['client_company']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400">Aucun client</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                <?php
                                switch($project['status']) {
                                    case 'active': echo 'bg-green-100 text-green-800'; break;
                                    case 'completed': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'on-hold': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?= ucfirst($project['status']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                <?php
                                switch($project['priority']) {
                                    case 'high': echo 'bg-red-100 text-red-800'; break;
                                    case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?= ucfirst($project['priority']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <?= $project['budget'] ? number_format($project['budget'], 2) . ' €' : '-' ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?= sanitizeOutput($project['author_email']) ?>
                            <?php if ($showArchived && $project['archived_by_email']): ?>
                            <br><small class="text-xs text-orange-600">
                                Archivé par <?= sanitizeOutput($project['archived_by_email']) ?>
                                <br><?= date('d/m/Y', strtotime($project['archived_at'])) ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex space-x-2 justify-center">
                                <?php if (!$showArchived): ?>
                                <a href="?page=projects&edit=<?= $project['id'] ?>" class="text-blue-600 hover:text-blue-800" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($project['status'] === 'completed'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Archiver ce projet terminé ?')">
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="text-green-600 hover:text-green-800" title="Archiver">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Supprimer définitivement ce projet ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="unarchive">
                                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="text-blue-600 hover:text-blue-800" title="Désarchiver">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Supprimer définitivement ce projet archivé ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>