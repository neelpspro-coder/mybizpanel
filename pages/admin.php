<?php
requireAdmin();

// Gestion des actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                if (empty($_POST['email']) || empty($_POST['password'])) {
                    $error = "Email et mot de passe obligatoires";
                    break;
                }
                
                $id = generateId();
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (id, email, password, first_name, last_name, role, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $id,
                        trim($_POST['email']),
                        $hashedPassword,
                        trim($_POST['first_name']) ?: null,
                        trim($_POST['last_name']) ?: null,
                        $_POST['role'] ?: 'employee'
                    ]);
                    
                    logSystemEvent('info', "Nouvel utilisateur créé : {$_POST['email']}", 'user_create');
                    $success = "Utilisateur créé avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la création : " . $e->getMessage();
                }
                break;
                
            case 'update_user':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, role = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        trim($_POST['first_name']) ?: null,
                        trim($_POST['last_name']) ?: null,
                        $_POST['role'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['user_id']
                    ]);
                    
                    logSystemEvent('info', "Utilisateur modifié : {$_POST['email']}", 'user_update');
                    $success = "Utilisateur modifié avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la modification : " . $e->getMessage();
                }
                break;
                
            case 'delete_user':
                try {
                    // Vérifier qu'on ne supprime pas le dernier admin
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
                    $stmt->execute();
                    $adminCount = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $userRole = $stmt->fetchColumn();
                    
                    if ($userRole === 'admin' && $adminCount <= 1) {
                        $error = "Impossible de supprimer le dernier administrateur";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    logSystemEvent('warning', "Utilisateur désactivé", 'user_deactivate');
                    $success = "Utilisateur désactivé avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la désactivation : " . $e->getMessage();
                }
                break;
                
            case 'clear_logs':
                try {
                    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $stmt->execute();
                    $success = "Logs anciens supprimés !";
                } catch (Exception $e) {
                    $error = "Erreur lors du nettoyage : " . $e->getMessage();
                }
                break;
        }
    }
}

// Récupération des utilisateurs
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
    $error = "Erreur lors de la récupération des utilisateurs";
}

// Récupération des logs système
try {
    $stmt = $pdo->query("
        SELECT l.*, u.email as user_email 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.created_at DESC 
        LIMIT 20
    ");
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $logs = [];
}

// Statistiques système
try {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
        'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
        'total_tasks' => $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn(),
        'total_clients' => $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income'")->fetchColumn(),
        'total_logs' => $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['total_users' => 0, 'total_projects' => 0, 'total_tasks' => 0, 'total_clients' => 0, 'total_revenue' => 0, 'total_logs' => 0];
}

$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}
?>

<div class="space-y-6">
    <!-- Messages -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Statistiques système -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="card p-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <p class="text-2xl font-bold text-blue-600"><?= $stats['total_users'] ?></p>
                <p class="text-xs text-gray-600">Utilisateurs</p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-project-diagram text-purple-600 text-xl"></i>
                </div>
                <p class="text-2xl font-bold text-purple-600"><?= $stats['total_projects'] ?></p>
                <p class="text-xs text-gray-600">Projets</p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-tasks text-green-600 text-xl"></i>
                </div>
                <p class="text-2xl font-bold text-green-600"><?= $stats['total_tasks'] ?></p>
                <p class="text-xs text-gray-600">Tâches</p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-handshake text-yellow-600 text-xl"></i>
                </div>
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['total_clients'] ?></p>
                <p class="text-xs text-gray-600">Clients</p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-euro-sign text-emerald-600 text-xl"></i>
                </div>
                <p class="text-lg font-bold text-emerald-600"><?= number_format($stats['total_revenue'], 0) ?>€</p>
                <p class="text-xs text-gray-600">CA Total</p>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-clipboard-list text-gray-600 text-xl"></i>
                </div>
                <p class="text-2xl font-bold text-gray-600"><?= $stats['total_logs'] ?></p>
                <p class="text-xs text-gray-600">Logs</p>
            </div>
        </div>
    </div>

    <!-- Gestion des utilisateurs -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'new_user' || $editUser): ?>
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <?= $editUser ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' ?>
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?= $editUser ? 'update_user' : 'create_user' ?>">
            <?php if ($editUser): ?>
            <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
            <input type="hidden" name="email" value="<?= $editUser['email'] ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if (!$editUser): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mot de passe *</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                </div>
                <?php else: ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" value="<?= htmlspecialchars($editUser['email']) ?>" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    <p class="text-xs text-gray-500 mt-1">L'email ne peut pas être modifié</p>
                </div>
                <?php endif; ?>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                    <input type="text" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= htmlspecialchars($editUser['first_name'] ?? '') ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                    <input type="text" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= htmlspecialchars($editUser['last_name'] ?? '') ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="employee" <?= ($editUser['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employé</option>
                        <option value="support" <?= ($editUser['role'] ?? '') === 'support' ? 'selected' : '' ?>>Support</option>
                        <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </div>
                
                <?php if ($editUser): ?>
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" <?= $editUser['is_active'] ? 'checked' : '' ?> class="rounded border-gray-300 text-violet-600 shadow-sm focus:border-violet-300 focus:ring focus:ring-violet-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Utilisateur actif</span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex space-x-4">
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <?= $editUser ? 'Modifier' : 'Créer' ?>
                </button>
                <a href="?page=admin" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">Annuler</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    
    <!-- Liste des utilisateurs -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold">Gestion des Utilisateurs</h3>
            <a href="?page=admin&action=new_user" class="btn-primary px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Nouvel Utilisateur
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 px-4">Utilisateur</th>
                        <th class="text-left py-3 px-4">Email</th>
                        <th class="text-left py-3 px-4">Rôle</th>
                        <th class="text-left py-3 px-4">Statut</th>
                        <th class="text-left py-3 px-4">Inscrit le</th>
                        <th class="text-center py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-violet-500 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                                    <?= strtoupper(substr($user['first_name'] ?: $user['email'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars(($user['first_name'] . ' ' . $user['last_name']) ?: 'Nom non défini') ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                <?php
                                switch($user['role']) {
                                    case 'admin': echo 'bg-red-100 text-red-800'; break;
                                    case 'support': echo 'bg-blue-100 text-blue-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td class="py-3 px-4"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex space-x-2 justify-center">
                                <a href="?page=admin&edit=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Désactiver cet utilisateur ?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-user-times"></i>
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
    </div>

    <!-- Logs système -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold">Logs Système Récents</h3>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer les logs de plus de 30 jours ?')">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="text-gray-600 hover:text-gray-800 text-sm">
                    <i class="fas fa-trash mr-1"></i>Nettoyer
                </button>
            </form>
        </div>
        
        <?php if (empty($logs)): ?>
        <p class="text-gray-500 text-center py-8">Aucun log système</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($logs as $log): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center">
                    <span class="w-2 h-2 rounded-full mr-3
                        <?php
                        switch($log['level']) {
                            case 'error': echo 'bg-red-500'; break;
                            case 'warning': echo 'bg-yellow-500'; break;
                            default: echo 'bg-blue-500';
                        }
                        ?>"></span>
                    <div>
                        <p class="text-sm font-medium"><?= htmlspecialchars($log['message']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= htmlspecialchars($log['user_email'] ?: 'Système') ?> • 
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                        </p>
                    </div>
                </div>
                <span class="text-xs text-gray-400"><?= htmlspecialchars($log['action'] ?: '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>