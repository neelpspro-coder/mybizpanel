<?php
requireAdmin();

// Gestion des actions administratives avancées
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // === GESTION DES CATÉGORIES DYNAMIQUES ===
            case 'create_category':
                try {
                    $stmt = $pdo->prepare("INSERT INTO dynamic_categories (id, module, name, color, icon, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        generateId(),
                        $_POST['module'],
                        trim($_POST['name']),
                        $_POST['color'],
                        $_POST['icon'],
                        $_SESSION['user_id']
                    ]);
                    // Log optionnel
                    try {
                        if (function_exists('logSystemEvent')) {
                            logSystemEvent('info', "Nouvelle catégorie créée : {$_POST['name']} pour {$_POST['module']}", 'category_create');
                        }
                    } catch (Exception $e) { /* Ignorer */ }
                    $success = "Catégorie créée avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la création : " . $e->getMessage();
                }
                break;
                
            case 'update_category':
                try {
                    $stmt = $pdo->prepare("UPDATE dynamic_categories SET name = ?, color = ?, icon = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        trim($_POST['name']),
                        $_POST['color'],
                        $_POST['icon'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['category_id']
                    ]);
                    // Log optionnel
                    try {
                        if (function_exists('logSystemEvent')) {
                            logSystemEvent('info', "Catégorie modifiée : {$_POST['name']}", 'category_update');
                        }
                    } catch (Exception $e) { /* Ignorer */ }
                    $success = "Catégorie modifiée avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la modification : " . $e->getMessage();
                }
                break;
                
            case 'delete_category':
                try {
                    $stmt = $pdo->prepare("DELETE FROM dynamic_categories WHERE id = ?");
                    $stmt->execute([$_POST['category_id']]);
                    // Log optionnel
                    try {
                        if (function_exists('logSystemEvent')) {
                            logSystemEvent('warning', "Catégorie supprimée", 'category_delete');
                        }
                    } catch (Exception $e) { /* Ignorer */ }
                    $success = "Catégorie supprimée !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
                
            // === EXPORT/IMPORT DONNÉES ===
            case 'export_clients':
                try {
                    $stmt = $pdo->query("
                        SELECT c.*, u.email as creator_email 
                        FROM clients c 
                        LEFT JOIN users u ON c.user_id = u.id 
                        ORDER BY c.created_at DESC
                    ");
                    $clients = $stmt->fetchAll();
                    
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment;filename="clients_export_' . date('Y-m-d') . '.csv"');
                    header('Cache-Control: max-age=0');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['ID', 'Nom', 'Email', 'Entreprise', 'Téléphone', 'Adresse', 'Créé par', 'Date création']);
                    
                    foreach ($clients as $client) {
                        fputcsv($output, [
                            $client['id'],
                            $client['name'],
                            $client['email'],
                            $client['company'],
                            $client['phone'],
                            $client['address'],
                            $client['creator_email'],
                            $client['created_at']
                        ]);
                    }
                    fclose($output);
                    logSystemEvent('info', "Export clients réalisé", 'export_clients');
                    exit;
                } catch (Exception $e) {
                    $error = "Erreur lors de l'export : " . $e->getMessage();
                }
                break;
                
            case 'import_clients':
                if (isset($_FILES['client_file']) && $_FILES['client_file']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $file = fopen($_FILES['client_file']['tmp_name'], 'r');
                        $header = fgetcsv($file); // Skip header
                        $imported = 0;
                        
                        while (($data = fgetcsv($file)) !== FALSE) {
                            if (count($data) >= 4) {
                                $stmt = $pdo->prepare("INSERT INTO clients (id, name, email, company, phone, address, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    generateId(),
                                    trim($data[1]), // Nom
                                    trim($data[2]), // Email
                                    trim($data[3]), // Entreprise
                                    isset($data[4]) ? trim($data[4]) : null, // Téléphone
                                    isset($data[5]) ? trim($data[5]) : null, // Adresse
                                    $_SESSION['user_id']
                                ]);
                                $imported++;
                            }
                        }
                        fclose($file);
                        logSystemEvent('info', "$imported clients importés", 'import_clients');
                        $success = "$imported clients importés avec succès !";
                    } catch (Exception $e) {
                        $error = "Erreur lors de l'import : " . $e->getMessage();
                    }
                } else {
                    $error = "Fichier non fourni ou erreur d'upload";
                }
                break;
                
            // === NETTOYAGE BASE DE DONNÉES ===
            case 'cleanup_logs':
                try {
                    $days = intval($_POST['days'] ?? 30);
                    $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                    $stmt->execute([$days]);
                    $deleted = $stmt->rowCount();
                    logSystemEvent('info', "$deleted anciens logs supprimés", 'cleanup_logs');
                    $success = "$deleted anciens logs supprimés !";
                } catch (Exception $e) {
                    $error = "Erreur lors du nettoyage : " . $e->getMessage();
                }
                break;
                
            case 'cleanup_sessions':
                try {
                    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $stmt->execute();
                    $deleted = $stmt->rowCount();
                    logSystemEvent('info', "$deleted sessions inactives supprimées", 'cleanup_sessions');
                    $success = "$deleted sessions inactives supprimées !";
                } catch (Exception $e) {
                    $error = "Erreur lors du nettoyage : " . $e->getMessage();
                }
                break;
        }
    }
}

// Récupération des données pour l'affichage
try {
    // Catégories par module
    $stmt = $pdo->query("SELECT * FROM dynamic_categories ORDER BY module, name");
    $categories = $stmt->fetchAll();
    $categoriesByModule = [];
    foreach ($categories as $cat) {
        $categoriesByModule[$cat['module']][] = $cat;
    }
    
    // Statistiques système
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE created_at >= CURDATE()");
    $todayLogs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_sessions WHERE is_online = 1");
    $onlineUsers = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM clients");
    $totalClients = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des données : " . $e->getMessage();
}

$modules = ['blog', 'finance', 'project', 'task'];
$icons = [
    'fas fa-graduation-cap', 'fas fa-gavel', 'fas fa-cogs', 'fas fa-book', 'fas fa-bullhorn',
    'fas fa-handshake', 'fas fa-box', 'fas fa-receipt', 'fas fa-chart-line', 'fas fa-desktop',
    'fas fa-tag', 'fas fa-folder', 'fas fa-star', 'fas fa-heart', 'fas fa-bell'
];
?>

<div class="space-y-6">
    <!-- Messages d'état -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-cogs mr-3 text-blue-600"></i>
                Administration Avancée
            </h1>
            <p class="text-gray-600 mt-1">Gestion des catégories, import/export, maintenance système</p>
        </div>
    </div>

    <!-- Statistiques système -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Logs Aujourd'hui</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $todayLogs ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Utilisateurs En Ligne</p>
                    <p class="text-2xl font-bold text-green-600"><?= $onlineUsers ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-address-book text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Total Clients</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $totalClients ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cube text-orange-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Total Produits</p>
                    <p class="text-2xl font-bold text-orange-600"><?= $totalProducts ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglets -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" id="admin-tabs">
            <button class="admin-tab-btn active" data-tab="categories">
                <i class="fas fa-tags mr-2"></i>
                Gestion des Catégories
            </button>
            <button class="admin-tab-btn" data-tab="import-export">
                <i class="fas fa-exchange-alt mr-2"></i>
                Import/Export
            </button>
            <button class="admin-tab-btn" data-tab="maintenance">
                <i class="fas fa-tools mr-2"></i>
                Maintenance
            </button>
            <button class="admin-tab-btn" data-tab="analytics">
                <i class="fas fa-chart-bar mr-2"></i>
                Analytics
            </button>
        </nav>
    </div>

    <!-- Contenu des onglets -->
    
    <!-- === GESTION DES CATÉGORIES === -->
    <div id="tab-categories" class="admin-tab-content">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Formulaire de création -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-plus-circle mr-2 text-green-600"></i>
                        Ajouter une Catégorie
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_category">
                        
                        <div>
                            <label class="form-label">Module</label>
                            <select name="module" class="form-input" required>
                                <option value="">Choisir un module</option>
                                <option value="blog">📝 Blog</option>
                                <option value="finance">💰 Finance</option>
                                <option value="project">📋 Projets</option>
                                <option value="task">✅ Tâches</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Nom de la catégorie</label>
                            <input type="text" name="name" class="form-input" required 
                                   placeholder="Ex: Nouveau service">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Couleur</label>
                                <input type="color" name="color" value="#6366f1" class="form-input h-10">
                            </div>
                            
                            <div>
                                <label class="form-label">Icône</label>
                                <select name="icon" class="form-input">
                                    <?php foreach ($icons as $icon): ?>
                                    <option value="<?= $icon ?>"><?= $icon ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">
                            <i class="fas fa-save mr-2"></i>
                            Créer la Catégorie
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Liste des catégories existantes -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-list mr-2 text-blue-600"></i>
                        Catégories Existantes
                    </h3>
                    
                    <?php foreach ($categoriesByModule as $module => $cats): ?>
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span>
                            <?= ucfirst($module) ?>
                        </h4>
                        
                        <div class="space-y-2">
                            <?php foreach ($cats as $cat): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <i class="<?= $cat['icon'] ?> mr-3" style="color: <?= $cat['color'] ?>"></i>
                                    <span class="font-medium"><?= htmlspecialchars($cat['name']) ?></span>
                                    <?php if (!$cat['is_active']): ?>
                                    <span class="ml-2 px-2 py-0.5 text-xs bg-red-100 text-red-600 rounded">Inactif</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <button onclick="editCategory('<?= $cat['id'] ?>', '<?= htmlspecialchars($cat['name']) ?>', '<?= $cat['color'] ?>', '<?= $cat['icon'] ?>', <?= $cat['is_active'] ? 'true' : 'false' ?>)" 
                                            class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- === IMPORT/EXPORT === -->
    <div id="tab-import-export" class="admin-tab-content" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Export de données -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-download mr-2 text-green-600"></i>
                        Export de Données
                    </h3>
                    
                    <div class="space-y-4">
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="export_clients">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Clients</h4>
                                    <p class="text-sm text-gray-600">Exporter la base de données clients</p>
                                </div>
                                <button type="submit" class="btn-primary btn-sm">
                                    <i class="fas fa-file-excel mr-2"></i>
                                    Export CSV
                                </button>
                            </div>
                        </form>
                        
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="export_analytics">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Analytics Financiers</h4>
                                    <p class="text-sm text-gray-600">Rapport détaillé des finances</p>
                                </div>
                                <button type="submit" class="btn-primary btn-sm">
                                    <i class="fas fa-chart-line mr-2"></i>
                                    Export Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Import de données -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-upload mr-2 text-blue-600"></i>
                        Import de Données
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="import_clients">
                        
                        <div>
                            <label class="form-label">Import Clients (CSV)</label>
                            <input type="file" name="client_file" accept=".csv" class="form-input" required>
                            <p class="text-xs text-gray-500 mt-1">
                                Format: ID, Nom, Email, Entreprise, Téléphone, Adresse
                            </p>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full">
                            <i class="fas fa-upload mr-2"></i>
                            Importer les Clients
                        </button>
                    </form>
                    
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <h4 class="font-medium text-blue-800 mb-2">Format CSV requis :</h4>
                        <code class="text-xs text-blue-700">
                            ID,Nom,Email,Entreprise,Téléphone,Adresse<br>
                            1,"Jean Dupont","jean@email.com","Ma Société","0123456789","123 Rue..."
                        </code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- === MAINTENANCE === -->
    <div id="tab-maintenance" class="admin-tab-content" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Nettoyage de la base -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-broom mr-2 text-orange-600"></i>
                        Nettoyage Base de Données
                    </h3>
                    
                    <div class="space-y-4">
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="cleanup_logs">
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label">Supprimer les logs de plus de :</label>
                                    <select name="days" class="form-input">
                                        <option value="7">7 jours</option>
                                        <option value="30" selected>30 jours</option>
                                        <option value="90">90 jours</option>
                                        <option value="365">1 an</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-warning w-full" onclick="return confirm('Supprimer les anciens logs ?')">
                                    <i class="fas fa-trash mr-2"></i>
                                    Nettoyer les Logs
                                </button>
                            </div>
                        </form>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="cleanup_sessions">
                            <button type="submit" class="btn-warning w-full" onclick="return confirm('Supprimer les sessions inactives ?')">
                                <i class="fas fa-user-times mr-2"></i>
                                Nettoyer les Sessions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Informations système -->
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                        Informations Système
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Version PHP :</span>
                            <span class="font-medium"><?= PHP_VERSION ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Mémoire utilisée :</span>
                            <span class="font-medium"><?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Limite mémoire :</span>
                            <span class="font-medium"><?= ini_get('memory_limit') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Taille upload max :</span>
                            <span class="font-medium"><?= ini_get('upload_max_filesize') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- === ANALYTICS === -->
    <div id="tab-analytics" class="admin-tab-content" style="display: none;">
        <div class="space-y-6">
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-bold mb-4">
                        <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                        Rapports Avancés - Prochainement
                    </h3>
                    <p class="text-gray-600">Cette section contiendra les analytics avancés avec graphiques et exports Excel.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'édition de catégorie -->
<div id="edit-category-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold mb-4">Modifier la Catégorie</h3>
        <form method="POST" id="edit-category-form" class="space-y-4">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="category_id" id="edit-category-id">
            
            <div>
                <label class="form-label">Nom</label>
                <input type="text" name="name" id="edit-category-name" class="form-input" required>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Couleur</label>
                    <input type="color" name="color" id="edit-category-color" class="form-input h-10">
                </div>
                
                <div>
                    <label class="form-label">Icône</label>
                    <select name="icon" id="edit-category-icon" class="form-input">
                        <?php foreach ($icons as $icon): ?>
                        <option value="<?= $icon ?>"><?= $icon ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="edit-category-active" class="mr-2">
                <label for="edit-category-active" class="text-sm">Catégorie active</label>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="btn-secondary">Annuler</button>
                <button type="submit" class="btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<style>
.admin-tab-btn {
    @apply px-4 py-2 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300;
}
.admin-tab-btn.active {
    @apply text-blue-600 border-blue-600;
}
.btn-warning {
    @apply px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors;
}
.btn-sm {
    @apply px-3 py-1 text-sm;
}
</style>

<script>
// Gestion des onglets
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.admin-tab-btn');
    const tabContents = document.querySelectorAll('.admin-tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            // Désactiver tous les onglets
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.style.display = 'none');
            
            // Activer l'onglet sélectionné
            this.classList.add('active');
            document.getElementById('tab-' + tabId).style.display = 'block';
        });
    });
});

// Modal d'édition de catégorie
function editCategory(id, name, color, icon, isActive) {
    document.getElementById('edit-category-id').value = id;
    document.getElementById('edit-category-name').value = name;
    document.getElementById('edit-category-color').value = color;
    document.getElementById('edit-category-icon').value = icon;
    document.getElementById('edit-category-active').checked = isActive;
    
    document.getElementById('edit-category-modal').classList.remove('hidden');
    document.getElementById('edit-category-modal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('edit-category-modal').classList.add('hidden');
    document.getElementById('edit-category-modal').classList.remove('flex');
}

// Fermer modal avec Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>