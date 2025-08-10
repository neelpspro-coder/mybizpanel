<?php
// Récupération des statistiques globales (partagées)
try {
    // Projets actifs de toute l'équipe (non archivés)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status = 'active' AND is_archived = 0");
    $activeProjects = $stmt->fetchColumn();
    
    // Tâches d'équipe pour aujourd'hui  
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE due_date = ? AND status != 'completed'");
    $stmt->execute([$today]);
    $todayTasks = $stmt->fetchColumn();
    
    // Notes partagées totales
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notes");
    $totalNotes = $stmt->fetchColumn();
    
    // Revenus du mois collectifs
    $currentMonth = date('Y-m');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income' AND DATE_FORMAT(date, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $monthlyRevenue = $stmt->fetchColumn();
    
    // Projets récents avec auteur (non archivés)
    $stmt = $pdo->prepare("
        SELECT p.*, u.email as author_email, c.name as client_name 
        FROM projects p 
        LEFT JOIN users u ON p.user_id = u.id 
        LEFT JOIN clients c ON p.client_id = c.id 
        WHERE p.is_archived = 0
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentProjects = $stmt->fetchAll();
    
    // Tâches récentes avec auteur
    $stmt = $pdo->prepare("
        SELECT t.*, u.email as author_email, p.name as project_name 
        FROM tasks t 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN projects p ON t.project_id = p.id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentTasks = $stmt->fetchAll();
    
    // Messages récents
    $stmt = $pdo->prepare("
        SELECT m.*, u.email as sender_email 
        FROM messages m 
        LEFT JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id IS NULL 
        ORDER BY m.created_at DESC 
        LIMIT 3
    ");
    $stmt->execute();
    $recentMessages = $stmt->fetchAll();
    
} catch (Exception $e) {
    $activeProjects = 0;
    $todayTasks = 0;
    $totalNotes = 0;
    $monthlyRevenue = 0;
    $recentProjects = [];
    $recentTasks = [];
    $recentMessages = [];
}
?>

<div class="space-y-6">
    <!-- Statistiques globales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Projets Actifs</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $activeProjects ?></p>
                    <p class="text-xs text-gray-500">Équipe complète</p>
                </div>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-euro-sign text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Revenus du Mois</p>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($monthlyRevenue, 0) ?>€</p>
                    <p class="text-xs text-gray-500">Organisation</p>
                </div>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-tasks text-orange-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Tâches Aujourd'hui</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $todayTasks ?></p>
                    <p class="text-xs text-gray-500">Équipe</p>
                </div>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-sticky-note text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Notes Partagées</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $totalNotes ?></p>
                    <p class="text-xs text-gray-500">Base connaissance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Projets récents -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Projets Récents</h3>
                <a href="?page=projects" class="text-violet-600 hover:text-violet-700 text-sm font-medium">
                    Voir tout →
                </a>
            </div>
            
            <?php if (empty($recentProjects)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-project-diagram text-3xl mb-3"></i>
                <p>Aucun projet récent</p>
                <a href="?page=projects&action=new" class="text-violet-600 hover:text-violet-700 text-sm">
                    Créer le premier projet
                </a>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recentProjects as $project): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900"><?= sanitizeOutput($project['name']) ?></h4>
                        <div class="flex items-center text-sm text-gray-500 mt-1">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-<?= $project['status'] === 'active' ? 'green' : ($project['status'] === 'completed' ? 'blue' : 'yellow') ?>-100 text-<?= $project['status'] === 'active' ? 'green' : ($project['status'] === 'completed' ? 'blue' : 'yellow') ?>-800 mr-2">
                                <?= ucfirst($project['status']) ?>
                            </span>
                            <?php if ($project['client_name']): ?>
                            <span class="mr-2">• <?= sanitizeOutput($project['client_name']) ?></span>
                            <?php endif; ?>
                            <span>• Par <?= sanitizeOutput($project['author_email']) ?></span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400">
                        <?= date('d/m', strtotime($project['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tâches récentes -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Tâches Récentes</h3>
                <a href="?page=tasks" class="text-violet-600 hover:text-violet-700 text-sm font-medium">
                    Voir tout →
                </a>
            </div>
            
            <?php if (empty($recentTasks)): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-tasks text-3xl mb-3"></i>
                <p>Aucune tâche récente</p>
                <a href="?page=tasks&action=new" class="text-violet-600 hover:text-violet-700 text-sm">
                    Créer la première tâche
                </a>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recentTasks as $task): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900"><?= sanitizeOutput($task['title']) ?></h4>
                        <div class="flex items-center text-sm text-gray-500 mt-1">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-<?= $task['status'] === 'completed' ? 'green' : ($task['status'] === 'in-progress' ? 'blue' : 'yellow') ?>-100 text-<?= $task['status'] === 'completed' ? 'green' : ($task['status'] === 'in-progress' ? 'blue' : 'yellow') ?>-800 mr-2">
                                <?= ucfirst(str_replace('-', ' ', $task['status'])) ?>
                            </span>
                            <?php if ($task['project_name']): ?>
                            <span class="mr-2">• <?= sanitizeOutput($task['project_name']) ?></span>
                            <?php endif; ?>
                            <span>• Par <?= sanitizeOutput($task['author_email']) ?></span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400">
                        <?= $task['due_date'] ? date('d/m', strtotime($task['due_date'])) : 'Pas de date' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages récents -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Messages Récents</h3>
            <a href="?page=messages" class="text-violet-600 hover:text-violet-700 text-sm font-medium">
                Accéder au chat →
            </a>
        </div>
        
        <?php if (empty($recentMessages)): ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-comments text-3xl mb-3"></i>
            <p>Aucun message récent</p>
            <a href="?page=messages" class="text-violet-600 hover:text-violet-700 text-sm">
                Démarrer une conversation
            </a>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($recentMessages as $message): ?>
            <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                <div class="w-8 h-8 bg-violet-500 rounded-full flex items-center justify-center text-white text-xs font-medium mr-3">
                    <?= strtoupper(substr($message['sender_email'], 0, 1)) ?>
                </div>
                <div class="flex-1">
                    <div class="flex items-center mb-1">
                        <span class="font-medium text-sm text-violet-700 mr-2">
                            <?= sanitizeOutput($message['sender_email']) ?>
                        </span>
                        <span class="text-xs text-gray-500">
                            <?= date('d/m à H:i', strtotime($message['created_at'])) ?>
                        </span>
                    </div>
                    <p class="text-gray-700 text-sm">
                        <?= sanitizeOutput(substr($message['content'], 0, 100)) ?>
                        <?= strlen($message['content']) > 100 ? '...' : '' ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions rapides -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions Rapides</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="?page=projects&action=new" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-plus text-blue-600 text-2xl mb-2"></i>
                <span class="text-sm font-medium text-blue-700">Nouveau Projet</span>
            </a>
            <a href="?page=tasks&action=new" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                <i class="fas fa-check text-green-600 text-2xl mb-2"></i>
                <span class="text-sm font-medium text-green-700">Nouvelle Tâche</span>
            </a>
            <a href="?page=finances&action=new" class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                <i class="fas fa-euro-sign text-yellow-600 text-2xl mb-2"></i>
                <span class="text-sm font-medium text-yellow-700">Transaction</span>
            </a>
            <a href="?page=clients&action=new" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                <i class="fas fa-user-plus text-purple-600 text-2xl mb-2"></i>
                <span class="text-sm font-medium text-purple-700">Nouveau Client</span>
            </a>
        </div>
    </div>
</div>