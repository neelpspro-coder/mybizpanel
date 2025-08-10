<?php
// Gestion des actions CRUD pour les clients
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (empty($_POST['name'])) {
                    $error = "Le nom est obligatoire";
                    break;
                }
                
                $id = generateId();
                $stmt = $pdo->prepare("
                    INSERT INTO clients (id, name, email, phone, company, address, notes, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    trim($_POST['name']),
                    trim($_POST['email']) ?: null,
                    trim($_POST['phone']) ?: null,
                    trim($_POST['company']) ?: null,
                    trim($_POST['address']) ?: null,
                    trim($_POST['notes']) ?: null,
                    $_SESSION['user_id']
                ]);
                
                logSystemEvent('info', "Nouveau client créé : {$_POST['name']}", 'client_create');
                $success = "Client créé avec succès !";
                
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Nouveau client créé : " . addslashes($_POST['name']) . "', 'success'); });</script>";
                break;
                
            case 'update':
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET name = ?, email = ?, phone = ?, company = ?, address = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'] ?: null,
                    $_POST['phone'] ?: null,
                    $_POST['company'] ?: null,
                    $_POST['address'] ?: null,
                    $_POST['notes'] ?: null,
                    $_POST['id']
                ]);
                
                logSystemEvent('info', "Client modifié : {$_POST['name']}", 'client_update');
                $success = "Client modifié avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $clientName = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                logSystemEvent('info', "Client supprimé : $clientName", 'client_delete');
                $success = "Client supprimé avec succès !";
                break;
        }
    }
}

// Récupération du client à éditer ou voir
$editClient = null;
$viewClient = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editClient = $stmt->fetch();
} elseif (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_GET['view']]);
    $viewClient = $stmt->fetch();
    
    // Récupération de l'historique des projets pour ce client
    if ($viewClient) {
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, u.email as author_email, archiver.email as archived_by_email 
                FROM projects p 
                LEFT JOIN users u ON p.user_id = u.id 
                LEFT JOIN users archiver ON p.archived_by = archiver.id
                WHERE p.client_id = ? 
                ORDER BY p.is_archived ASC, p.created_at DESC
            ");
            $stmt->execute([$viewClient['id']]);
            $clientProjects = $stmt->fetchAll();
        } catch (Exception $e) {
            $clientProjects = [];
        }
        
        // Récupération des transactions pour ce client
        try {
            $stmt = $pdo->prepare("
                SELECT t.*, u.email as author_email 
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE t.client_id = ? 
                ORDER BY t.date DESC
            ");
            $stmt->execute([$viewClient['id']]);
            $clientTransactions = $stmt->fetchAll();
        } catch (Exception $e) {
            $clientTransactions = [];
        }
    }
}

// Récupération des clients (PARTAGÉS)
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.email as author_email,
               COUNT(DISTINCT p.id) as projects_count,
               COUNT(DISTINCT t.id) as transactions_count,
               COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_revenue
        FROM clients c 
        LEFT JOIN users u ON c.user_id = u.id 
        LEFT JOIN projects p ON c.id = p.client_id
        LEFT JOIN transactions t ON c.id = t.client_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll();
} catch (Exception $e) {
    $clients = [];
    $error = "Erreur lors de la récupération des clients : " . $e->getMessage();
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

    <!-- Formulaire nouveau/édition client -->
    <?php if ($viewClient): ?>
    <!-- Vue détaillée du client -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <div class="w-16 h-16 bg-gradient-to-r from-violet-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-2xl mr-4">
                    <?= strtoupper(substr($viewClient['name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900"><?= sanitizeOutput($viewClient['name']) ?></h2>
                    <?php if ($viewClient['company']): ?>
                    <p class="text-lg text-gray-600"><?= sanitizeOutput($viewClient['company']) ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500">Client depuis le <?= date('d F Y', strtotime($viewClient['created_at'])) ?></p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="?page=clients&edit=<?= $viewClient['id'] ?>" class="btn-primary px-4 py-2 rounded-lg">
                    <i class="fas fa-edit mr-2"></i>Modifier
                </a>
                <a href="?page=clients" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>
        
        <!-- Informations de contact -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Informations de contact</h4>
                <div class="space-y-3">
                    <?php if ($viewClient['email']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-envelope w-5 text-gray-400"></i>
                        <a href="mailto:<?= $viewClient['email'] ?>" class="ml-3 text-violet-600 hover:text-violet-700">
                            <?= sanitizeOutput($viewClient['email']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($viewClient['phone']): ?>
                    <div class="flex items-center">
                        <i class="fas fa-phone w-5 text-gray-400"></i>
                        <a href="tel:<?= $viewClient['phone'] ?>" class="ml-3 text-violet-600 hover:text-violet-700">
                            <?= sanitizeOutput($viewClient['phone']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($viewClient['address']): ?>
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt w-5 text-gray-400 mt-1"></i>
                        <span class="ml-3 text-gray-700"><?= nl2br(sanitizeOutput($viewClient['address'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Statistiques</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600"><?= count($clientProjects) ?></div>
                        <div class="text-sm text-blue-700">Projets Total</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <div class="text-lg font-bold text-green-600">
                            <?= number_format(array_sum(array_column($clientTransactions, 'amount')), 0) ?>€
                        </div>
                        <div class="text-sm text-green-700">CA Total</div>
                    </div>
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600">
                            <?= count(array_filter($clientProjects, fn($p) => !$p['is_archived'])) ?>
                        </div>
                        <div class="text-sm text-purple-700">Projets Actifs</div>
                    </div>
                    <div class="text-center p-3 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600">
                            <?= count(array_filter($clientProjects, fn($p) => $p['is_archived'])) ?>
                        </div>
                        <div class="text-sm text-orange-700">Projets Archivés</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <?php if ($viewClient['notes']): ?>
        <div class="mb-8">
            <h4 class="font-medium text-gray-900 mb-3">Notes internes</h4>
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-700"><?= nl2br(sanitizeOutput($viewClient['notes'])) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historique des projets -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <i class="fas fa-history mr-2"></i>Historique des Projets
        </h3>
        
        <?php if (empty($clientProjects)): ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-project-diagram text-4xl mb-4"></i>
            <p>Aucun projet pour ce client</p>
            <a href="?page=projects&action=new&client_id=<?= $viewClient['id'] ?>" class="mt-3 inline-block text-violet-600 hover:text-violet-700">
                Créer le premier projet
            </a>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($clientProjects as $project): ?>
            <div class="border rounded-lg p-4 <?= $project['is_archived'] ? 'bg-gray-50 border-gray-200' : 'bg-white border-gray-200' ?>">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <?php if ($project['is_archived']): ?>
                            <i class="fas fa-archive text-orange-500 mr-2"></i>
                            <?php endif; ?>
                            <h4 class="font-medium text-gray-900 <?= $project['is_archived'] ? 'opacity-75' : '' ?>">
                                <?= sanitizeOutput($project['name']) ?>
                            </h4>
                            <span class="ml-3 inline-flex items-center px-2 py-1 rounded-full text-xs
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
                            <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs
                                <?php
                                switch($project['priority']) {
                                    case 'high': echo 'bg-red-100 text-red-800'; break;
                                    case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?= ucfirst($project['priority']) ?>
                            </span>
                        </div>
                        
                        <?php if ($project['description']): ?>
                        <p class="text-sm text-gray-600 mb-2 <?= $project['is_archived'] ? 'opacity-75' : '' ?>">
                            <?= sanitizeOutput(substr($project['description'], 0, 100)) ?>
                            <?= strlen($project['description']) > 100 ? '...' : '' ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="flex items-center text-xs text-gray-500 space-x-4">
                            <span>
                                <i class="fas fa-user mr-1"></i>
                                Créé par <?= sanitizeOutput($project['author_email']) ?>
                            </span>
                            <span>
                                <i class="fas fa-calendar mr-1"></i>
                                <?= date('d/m/Y', strtotime($project['created_at'])) ?>
                            </span>
                            <?php if ($project['budget']): ?>
                            <span>
                                <i class="fas fa-euro-sign mr-1"></i>
                                <?= number_format($project['budget'], 2) ?>€
                            </span>
                            <?php endif; ?>
                            <?php if ($project['is_archived']): ?>
                            <span class="text-orange-600">
                                <i class="fas fa-archive mr-1"></i>
                                Archivé le <?= date('d/m/Y', strtotime($project['archived_at'])) ?>
                                par <?= sanitizeOutput($project['archived_by_email']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ml-4">
                        <?php if (!$project['is_archived']): ?>
                        <a href="?page=projects&edit=<?= $project['id'] ?>" class="text-blue-600 hover:text-blue-800 mr-2">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        <a href="?page=projects<?= $project['is_archived'] ? '&view=archived' : '' ?>" class="text-violet-600 hover:text-violet-700">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historique des transactions -->
    <?php if (!empty($clientTransactions)): ?>
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <i class="fas fa-euro-sign mr-2"></i>Historique Financier
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2">Date</th>
                        <th class="text-left py-2">Description</th>
                        <th class="text-left py-2">Catégorie</th>
                        <th class="text-right py-2">Montant</th>
                        <th class="text-left py-2">Auteur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($clientTransactions, 0, 10) as $transaction): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= date('d/m/Y', strtotime($transaction['date'])) ?></td>
                        <td class="py-2"><?= sanitizeOutput($transaction['description']) ?></td>
                        <td class="py-2">
                            <?= $transaction['category'] ? sanitizeOutput($transaction['category']) : '-' ?>
                        </td>
                        <td class="py-2 text-right font-medium <?= $transaction['type'] === 'income' ? 'text-green-600' : 'text-red-600' ?>">
                            <?= ($transaction['type'] === 'income' ? '+' : '-') . number_format($transaction['amount'], 2) ?>€
                        </td>
                        <td class="py-2 text-gray-600"><?= sanitizeOutput($transaction['author_email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (count($clientTransactions) > 10): ?>
            <div class="mt-4 text-center">
                <a href="?page=finances&client_filter=<?= $viewClient['id'] ?>" class="text-violet-600 hover:text-violet-700 text-sm">
                    Voir toutes les transactions (<?= count($clientTransactions) ?>)
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php elseif (isset($_GET['action']) && $_GET['action'] === 'new' || $editClient): ?>
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <?= $editClient ? 'Modifier le client' : 'Nouveau client' ?>
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?= $editClient ? 'update' : 'create' ?>">
            <?php if ($editClient): ?>
            <input type="hidden" name="id" value="<?= $editClient['id'] ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= sanitizeOutput($editClient['name'] ?? '') ?>" placeholder="Nom du client">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= sanitizeOutput($editClient['email'] ?? '') ?>" placeholder="email@exemple.com">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= sanitizeOutput($editClient['phone'] ?? '') ?>" placeholder="+33 1 23 45 67 89">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Entreprise</label>
                    <input type="text" name="company" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= sanitizeOutput($editClient['company'] ?? '') ?>" placeholder="Nom de l'entreprise">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                          placeholder="Adresse complète"><?= sanitizeOutput($editClient['address'] ?? '') ?></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                          placeholder="Notes internes sur le client"><?= sanitizeOutput($editClient['notes'] ?? '') ?></textarea>
            </div>
            
            <div class="flex space-x-4">
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <i class="fas fa-save mr-2"></i>
                    <?= $editClient ? 'Modifier' : 'Créer' ?>
                </button>
                <a href="?page=clients" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">Annuler</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    
    <!-- Statistiques clients -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Total Clients</p>
                    <p class="text-2xl font-bold text-blue-600"><?= count($clients) ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-euro-sign text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">CA Total</p>
                    <p class="text-lg font-bold text-green-600">
                        <?= number_format(array_sum(array_column($clients, 'total_revenue')), 0) ?>€
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-project-diagram text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Projets Actifs</p>
                    <p class="text-2xl font-bold text-purple-600">
                        <?= array_sum(array_column($clients, 'projects_count')) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-handshake text-yellow-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Clients Actifs</p>
                    <p class="text-2xl font-bold text-yellow-600">
                        <?= count(array_filter($clients, fn($c) => $c['projects_count'] > 0)) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des clients -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold">Clients de l'Équipe</h3>
            <a href="?page=clients&action=new" class="btn-primary px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Nouveau Client
            </a>
        </div>
        
        <?php if (empty($clients)): ?>
        <div class="text-center text-gray-500 py-12">
            <i class="fas fa-users text-4xl mb-4"></i>
            <p>Aucun client trouvé</p>
            <p class="text-sm">Commencez par ajouter votre premier client</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($clients as $client): ?>
            <div class="card p-6 hover:shadow-lg transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-violet-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">
                            <?= strtoupper(substr($client['name'], 0, 1)) ?>
                        </div>
                        <div class="ml-3">
                            <h4 class="font-semibold text-gray-900"><?= sanitizeOutput($client['name']) ?></h4>
                            <?php if ($client['company']): ?>
                            <p class="text-sm text-gray-600"><?= sanitizeOutput($client['company']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <a href="?page=clients&view=<?= $client['id'] ?>" class="text-violet-600 hover:text-violet-800" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="?page=clients&edit=<?= $client['id'] ?>" class="text-blue-600 hover:text-blue-800" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Supprimer ce client ?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $client['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Informations de contact -->
                <div class="space-y-2 mb-4">
                    <?php if ($client['email']): ?>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-envelope w-4"></i>
                        <a href="mailto:<?= $client['email'] ?>" class="ml-2 hover:text-violet-600">
                            <?= sanitizeOutput($client['email']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($client['phone']): ?>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-phone w-4"></i>
                        <a href="tel:<?= $client['phone'] ?>" class="ml-2 hover:text-violet-600">
                            <?= sanitizeOutput($client['phone']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($client['address']): ?>
                    <div class="flex items-start text-sm text-gray-600">
                        <i class="fas fa-map-marker-alt w-4 mt-0.5"></i>
                        <span class="ml-2"><?= sanitizeOutput($client['address']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Statistiques -->
                <div class="grid grid-cols-3 gap-4 py-4 border-t border-gray-100">
                    <div class="text-center">
                        <p class="text-lg font-bold text-purple-600"><?= $client['projects_count'] ?></p>
                        <p class="text-xs text-gray-500">Projets</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-blue-600"><?= $client['transactions_count'] ?></p>
                        <p class="text-xs text-gray-500">Transactions</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-bold text-green-600"><?= number_format($client['total_revenue'], 0) ?>€</p>
                        <p class="text-xs text-gray-500">CA</p>
                    </div>
                </div>
                
                <!-- Notes -->
                <?php if ($client['notes']): ?>
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-600">
                        <?= nl2br(sanitizeOutput(substr($client['notes'], 0, 100))) ?>
                        <?= strlen($client['notes']) > 100 ? '...' : '' ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Auteur et date -->
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <span>Ajouté par <?= sanitizeOutput($client['author_email']) ?></span>
                    <span><?= date('d/m/Y', strtotime($client['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>