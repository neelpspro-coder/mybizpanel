<?php
// Gestion des actions CRUD pour les tâches
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $validationErrors = validateTask($_POST['title'], $_POST['status'], $_POST['priority']);
                if (!empty($validationErrors)) {
                    $error = implode(', ', $validationErrors);
                    break;
                }
                
                $id = generateId();
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (id, title, description, status, priority, due_date, project_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    trim($_POST['title']),
                    trim($_POST['description']) ?: null,
                    $_POST['status'] ?: 'todo',
                    $_POST['priority'] ?: 'medium',
                    $_POST['due_date'] ?: null,
                    $_POST['project_id'] ?: null,
                    $_SESSION['user_id']
                ]);
                
                logSystemEvent('info', "Nouvelle tâche créée : {$_POST['title']}", 'task_create');
                $success = "Tâche créée avec succès !";
                
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Nouvelle tâche créée : " . addslashes($_POST['title']) . "', 'success'); });</script>";
                break;
                
            case 'update':
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET title = ?, description = ?, status = ?, priority = ?, due_date = ?, project_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['status'],
                    $_POST['priority'],
                    $_POST['due_date'] ?: null,
                    $_POST['project_id'] ?: null,
                    $_POST['id']
                ]);
                
                logSystemEvent('info', "Tâche modifiée : {$_POST['title']}", 'task_update');
                $success = "Tâche modifiée avec succès !";
                
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Tâche modifiée : " . addslashes($_POST['title']) . "', 'success'); });</script>";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $taskTitle = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                logSystemEvent('info', "Tâche supprimée : $taskTitle", 'task_delete');
                $success = "Tâche supprimée avec succès !";
                break;
        }
    }
}

// Récupération de la tâche à éditer
$editTask = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editTask = $stmt->fetch();
}

// Récupération des tâches (PARTAGÉES)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.email as author_email, p.name as project_name 
        FROM tasks t 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN projects p ON t.project_id = p.id 
        ORDER BY t.due_date ASC, t.created_at DESC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll();
} catch (Exception $e) {
    $tasks = [];
    $error = "Erreur lors de la récupération des tâches : " . $e->getMessage();
}

// Récupération des projets pour le dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC");
    $projects = $stmt->fetchAll();
} catch (Exception $e) {
    $projects = [];
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

    <!-- Formulaire nouveau/édition tâche -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $editTask): ?>
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <?= $editTask ? 'Modifier la tâche' : 'Nouvelle tâche' ?>
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?= $editTask ? 'update' : 'create' ?>">
            <?php if ($editTask): ?>
            <input type="hidden" name="id" value="<?= $editTask['id'] ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Titre de la tâche *</label>
                    <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= sanitizeOutput($editTask['title'] ?? '') ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Projet</label>
                    <select name="project_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="">Aucun projet</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>" <?= ($editTask['project_id'] ?? '') === $project['id'] ? 'selected' : '' ?>>
                            <?= sanitizeOutput($project['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="todo" <?= ($editTask['status'] ?? '') === 'todo' ? 'selected' : '' ?>>À faire</option>
                        <option value="in-progress" <?= ($editTask['status'] ?? '') === 'in-progress' ? 'selected' : '' ?>>En cours</option>
                        <option value="completed" <?= ($editTask['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminé</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priorité</label>
                    <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="low" <?= ($editTask['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Basse</option>
                        <option value="medium" <?= ($editTask['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Moyenne</option>
                        <option value="high" <?= ($editTask['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Haute</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date d'échéance</label>
                    <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= $editTask['due_date'] ?? '' ?>">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500"><?= sanitizeOutput($editTask['description'] ?? '') ?></textarea>
            </div>
            
            <div class="flex space-x-4">
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <?= $editTask ? 'Modifier' : 'Créer' ?>
                </button>
                <a href="?page=tasks" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">Annuler</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    
    <!-- Résumé des tâches -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php
        $todoCount = count(array_filter($tasks, fn($t) => $t['status'] === 'todo'));
        $inProgressCount = count(array_filter($tasks, fn($t) => $t['status'] === 'in-progress'));
        $completedCount = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
        ?>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-circle text-yellow-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">À faire</p>
                    <p class="text-2xl font-bold text-yellow-600"><?= $todoCount ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">En cours</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $inProgressCount ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Terminées</p>
                    <p class="text-2xl font-bold text-green-600"><?= $completedCount ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des tâches -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold">Tâches de l'Équipe</h3>
            <a href="?page=tasks&action=new" class="btn-primary px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Nouvelle Tâche
            </a>
        </div>
        
        <?php if (empty($tasks)): ?>
        <div class="text-center text-gray-500 py-12">
            <i class="fas fa-tasks text-4xl mb-4"></i>
            <p>Aucune tâche trouvée</p>
            <p class="text-sm">Commencez par créer votre première tâche</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 px-4">Tâche</th>
                        <th class="text-left py-3 px-4">Projet</th>
                        <th class="text-left py-3 px-4">Statut</th>
                        <th class="text-left py-3 px-4">Priorité</th>
                        <th class="text-left py-3 px-4">Échéance</th>
                        <th class="text-left py-3 px-4">Auteur</th>
                        <th class="text-center py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div>
                                <p class="font-medium"><?= sanitizeOutput($task['title']) ?></p>
                                <?php if ($task['description']): ?>
                                <p class="text-sm text-gray-500"><?= sanitizeOutput(substr($task['description'], 0, 50)) ?>...</p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($task['project_name']): ?>
                            <span class="text-sm font-medium text-violet-600"><?= sanitizeOutput($task['project_name']) ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">Aucun projet</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                <?php
                                switch($task['status']) {
                                    case 'completed': echo 'bg-green-100 text-green-800'; break;
                                    case 'in-progress': echo 'bg-blue-100 text-blue-800'; break;
                                    default: echo 'bg-yellow-100 text-yellow-800';
                                }
                                ?>">
                                <?= ucfirst(str_replace('-', ' ', $task['status'])) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                <?php
                                switch($task['priority']) {
                                    case 'high': echo 'bg-red-100 text-red-800'; break;
                                    case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?= ucfirst($task['priority']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($task['due_date']): ?>
                                <?php
                                $dueDate = new DateTime($task['due_date']);
                                $today = new DateTime();
                                $isOverdue = $dueDate < $today && $task['status'] !== 'completed';
                                ?>
                                <span class="<?= $isOverdue ? 'text-red-600 font-medium' : 'text-gray-700' ?>">
                                    <?= $dueDate->format('d/m/Y') ?>
                                </span>
                                <?php if ($isOverdue): ?>
                                <br><span class="text-xs text-red-500">En retard</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400">Pas de date</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?= sanitizeOutput($task['author_email']) ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex space-x-2 justify-center">
                                <a href="?page=tasks&edit=<?= $task['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Supprimer cette tâche ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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