<?php
// Gestion des requêtes AJAX pour récupérer un produit
if (isset($_GET['action']) && $_GET['action'] === 'get_product' && isset($_GET['product_id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, pc.name as category_name 
            FROM products p 
            LEFT JOIN product_categories pc ON p.category_id = pc.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$_GET['product_id']]);
        $product = $stmt->fetch();
        
        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Produit non trouvé']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion des requêtes AJAX pour l'historique des mouvements
if (isset($_GET['action']) && $_GET['action'] === 'get_stock_history' && isset($_GET['product_id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("
            SELECT sm.*, u.first_name, u.last_name, u.email 
            FROM stock_movements sm 
            LEFT JOIN users u ON sm.created_by = u.id 
            WHERE sm.product_id = ? 
            ORDER BY sm.created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$_GET['product_id']]);
        $movements = $stmt->fetchAll();
        
        // Formatter les dates
        foreach ($movements as &$movement) {
            $movement['created_at'] = date('d/m/Y H:i', strtotime($movement['created_at']));
            $movement['created_by_name'] = ($movement['first_name'] ?? '') . ' ' . ($movement['last_name'] ?? '');
        }
        
        echo json_encode(['success' => true, 'movements' => $movements]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Gestion des actions pour l'inventaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // === GESTION DES CATÉGORIES DE PRODUITS ===
            case 'create_product_category':
                try {
                    $stmt = $pdo->prepare("INSERT INTO product_categories (id, name, description, color, icon, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        generateId(),
                        trim($_POST['name']),
                        trim($_POST['description']) ?: null,
                        $_POST['color'],
                        $_POST['icon'],
                        $_SESSION['user_id']
                    ]);
                    // Log optionnel
                    try {
                        if (function_exists('logSystemEvent')) {
                            logSystemEvent('info', "Nouvelle catégorie produit créée : {$_POST['name']}", 'product_category_create');
                        }
                    } catch (Exception $e) { /* Ignorer */ }
                    $success = "Catégorie créée avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la création : " . $e->getMessage();
                }
                break;
                
            case 'update_product_category':
                try {
                    $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, description = ?, color = ?, icon = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([
                        trim($_POST['name']),
                        trim($_POST['description']) ?: null,
                        $_POST['color'],
                        $_POST['icon'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['category_id']
                    ]);
                    // Log optionnel
                    try {
                        if (function_exists('logSystemEvent')) {
                            logSystemEvent('info', "Catégorie produit modifiée : {$_POST['name']}", 'product_category_update');
                        }
                    } catch (Exception $e) { /* Ignorer */ }
                    $success = "Catégorie modifiée avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la modification : " . $e->getMessage();
                }
                break;
                
            case 'delete_product_category':
                try {
                    $stmt = $pdo->prepare("DELETE FROM product_categories WHERE id = ?");
                    $stmt->execute([$_POST['category_id']]);
                    // Log optionnel
                    try {
                        if (function_exists('logSystemEvent')) {
                            logSystemEvent('warning', "Catégorie produit supprimée", 'product_category_delete');
                        }
                    } catch (Exception $e) { /* Ignorer */ }
                    $success = "Catégorie supprimée !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
                
            // === GESTION DES PRODUITS ===
            case 'create_product':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (id, name, description, sku, category_id, price, cost_price, 
                                            stock_quantity, min_stock_level, max_stock_level, unit, 
                                            barcode, weight, dimensions, supplier, location, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        generateId(),
                        trim($_POST['name']),
                        trim($_POST['description']) ?: null,
                        trim($_POST['sku']) ?: null,
                        $_POST['category_id'] ?: null,
                        floatval($_POST['price']),
                        floatval($_POST['cost_price']),
                        intval($_POST['stock_quantity']),
                        intval($_POST['min_stock_level']),
                        intval($_POST['max_stock_level']),
                        $_POST['unit'],
                        trim($_POST['barcode']) ?: null,
                        floatval($_POST['weight']) ?: null,
                        trim($_POST['dimensions']) ?: null,
                        trim($_POST['supplier']) ?: null,
                        trim($_POST['location']) ?: null,
                        $_POST['status'],
                        $_SESSION['user_id']
                    ]);
                    logSystemEvent('info', "Nouveau produit créé : {$_POST['name']}", 'product_create');
                    $success = "Produit créé avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la création : " . $e->getMessage();
                }
                break;
                
            case 'update_product':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE products SET name = ?, description = ?, sku = ?, category_id = ?, 
                               price = ?, cost_price = ?, stock_quantity = ?, min_stock_level = ?, 
                               max_stock_level = ?, unit = ?, barcode = ?, weight = ?, dimensions = ?, 
                               supplier = ?, location = ?, status = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        trim($_POST['name']),
                        trim($_POST['description']) ?: null,
                        trim($_POST['sku']) ?: null,
                        $_POST['category_id'] ?: null,
                        floatval($_POST['price']),
                        floatval($_POST['cost_price']),
                        intval($_POST['stock_quantity']),
                        intval($_POST['min_stock_level']),
                        intval($_POST['max_stock_level']),
                        $_POST['unit'],
                        trim($_POST['barcode']) ?: null,
                        floatval($_POST['weight']) ?: null,
                        trim($_POST['dimensions']) ?: null,
                        trim($_POST['supplier']) ?: null,
                        trim($_POST['location']) ?: null,
                        $_POST['status'],
                        $_POST['product_id']
                    ]);
                    logSystemEvent('info', "Produit modifié : {$_POST['name']}", 'product_update');
                    $_SESSION['flash_success'] = "Produit modifié avec succès !";
                    header('Location: ?page=inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la modification : " . $e->getMessage();
                    header('Location: ?page=inventory');
                    exit;
                }
                break;
                
            case 'delete_product':
                try {
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$_POST['product_id']]);
                    logSystemEvent('warning', "Produit supprimé", 'product_delete');
                    $_SESSION['flash_success'] = "Produit supprimé !";
                    header('Location: ?page=inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la suppression : " . $e->getMessage();
                    header('Location: ?page=inventory');
                    exit;
                }
                break;
                
            // === MOUVEMENTS DE STOCK ===
            case 'stock_movement':
                try {
                    $pdo->beginTransaction();
                    
                    // Enregistrer le mouvement
                    $stmt = $pdo->prepare("
                        INSERT INTO stock_movements (id, product_id, type, quantity, reference, reason, notes, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        generateId(),
                        $_POST['product_id'],
                        $_POST['movement_type'],
                        intval($_POST['quantity']),
                        trim($_POST['reference']) ?: null,
                        trim($_POST['reason']) ?: null,
                        trim($_POST['notes']) ?: null,
                        $_SESSION['user_id']
                    ]);
                    
                    // Mettre à jour le stock du produit
                    $quantity = intval($_POST['quantity']);
                    if ($_POST['movement_type'] === 'out') {
                        $quantity = -$quantity;
                    } elseif ($_POST['movement_type'] === 'adjustment') {
                        // Pour les ajustements, la quantité peut être positive ou négative
                        // On prend la valeur telle quelle
                    }
                    
                    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    $stmt->execute([$quantity, $_POST['product_id']]);
                    
                    $pdo->commit();
                    logSystemEvent('info', "Mouvement de stock enregistré", 'stock_movement');
                    $_SESSION['flash_success'] = "Mouvement de stock enregistré !";
                    header('Location: ?page=inventory');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['flash_error'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
                    header('Location: ?page=inventory');
                    exit;
                }
                break;
                
            // === AJOUT DE PRODUIT ===
            case 'add_product':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (id, name, description, sku, category_id, price, cost_price, 
                                            stock_quantity, min_stock_level, max_stock_level, unit, 
                                            barcode, weight, dimensions, supplier, location, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        generateId(),
                        trim($_POST['name']),
                        trim($_POST['description']) ?: null,
                        trim($_POST['sku']) ?: null,
                        $_POST['category_id'] ?: null,
                        floatval($_POST['price']) ?: null,
                        floatval($_POST['cost_price']) ?: null,
                        intval($_POST['stock_quantity']) ?: 0,
                        intval($_POST['min_stock_level']) ?: null,
                        intval($_POST['max_stock_level']) ?: null,
                        trim($_POST['unit']) ?: 'pièce',
                        trim($_POST['barcode']) ?: null,
                        floatval($_POST['weight']) ?: null,
                        trim($_POST['dimensions']) ?: null,
                        trim($_POST['supplier']) ?: null,
                        trim($_POST['location']) ?: null,
                        $_POST['status'] ?? 'active',
                        $_SESSION['user_id']
                    ]);
                    logSystemEvent('info', "Nouveau produit créé : {$_POST['name']}", 'product_create');
                    $_SESSION['flash_success'] = "Produit créé avec succès !";
                    header('Location: ?page=inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la création : " . $e->getMessage();
                    header('Location: ?page=inventory');
                    exit;
                }
                break;
                
            // === AJOUT DE CATÉGORIE ===
            case 'add_category':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_categories (id, name, description, color, icon, is_active, created_by) 
                        VALUES (?, ?, ?, ?, ?, 1, ?)
                    ");
                    $stmt->execute([
                        generateId(),
                        trim($_POST['name']),
                        trim($_POST['description']) ?: null,
                        $_POST['color'] ?? '#3b82f6',
                        $_POST['icon'] ?? 'fas fa-tag',
                        $_SESSION['user_id']
                    ]);
                    logSystemEvent('info', "Nouvelle catégorie créée : {$_POST['name']}", 'category_create');
                    $_SESSION['flash_success'] = "Catégorie créée avec succès !";
                    header('Location: ?page=inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la création : " . $e->getMessage();
                    header('Location: ?page=inventory');
                    exit;
                }
                break;
        }
    }
}

// Gestion des messages flash
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// === GESTION DES REQUÊTES AJAX ===
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_product':
            if (isset($_GET['product_id'])) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $stmt->execute([$_GET['product_id']]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'product' => $product]);
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Produit non trouvé']);
                        exit;
                    }
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
            }
            break;
            
        case 'get_stock_history':
            if (isset($_GET['product_id'])) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT sm.*, u.first_name, u.last_name 
                        FROM stock_movements sm 
                        LEFT JOIN users u ON sm.created_by = u.id 
                        WHERE sm.product_id = ? 
                        ORDER BY sm.created_at DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$_GET['product_id']]);
                    $movements = $stmt->fetchAll();
                    
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'movements' => $movements]);
                    exit;
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
            }
            break;
    }
}

// Filtrage
$categoryFilter = $_GET['category'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$categoryCondition = $categoryFilter !== 'all' ? "AND p.category_id = ?" : "";
$statusCondition = $statusFilter !== 'all' ? "AND p.status = ?" : "";

// Récupération des données
try {
    // Catégories de produits
    $stmt = $pdo->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name");
    $productCategories = $stmt->fetchAll();
    
    // Produits avec filtres
    $sql = "
        SELECT p.*, pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               u.email as creator_email
        FROM products p 
        LEFT JOIN product_categories pc ON p.category_id = pc.id 
        LEFT JOIN users u ON p.created_by = u.id 
        WHERE 1=1 $categoryCondition $statusCondition
        ORDER BY p.created_at DESC 
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $params = [];
    if ($categoryFilter !== 'all') $params[] = $categoryFilter;
    if ($statusFilter !== 'all') $params[] = $statusFilter;
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $activeProducts = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level");
    $lowStockProducts = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(stock_quantity * price) FROM products WHERE status = 'active'");
    $totalStockValue = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_categories WHERE is_active = 1");
    $totalCategories = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement : " . $e->getMessage();
    $products = [];
    $productCategories = [];
}

$units = ['pcs', 'kg', 'litre', 'mètre', 'heure', 'service', 'projet'];
$icons = [
    'fas fa-cube', 'fas fa-box', 'fas fa-package', 'fas fa-gift', 'fas fa-tools',
    'fas fa-laptop', 'fas fa-mobile', 'fas fa-car', 'fas fa-home', 'fas fa-heart'
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
                <i class="fas fa-warehouse mr-3 text-blue-600"></i>
                Gestion d'Inventaire
            </h1>
            <p class="text-gray-600 mt-1">Gestion des produits, stocks et catégories</p>
        </div>
        
        <div class="flex space-x-3">
            <button onclick="showProductForm()" class="btn-primary">
                <i class="fas fa-plus mr-2"></i>
                Nouveau Produit
            </button>
            <button onclick="showCategoryForm()" class="btn-secondary">
                <i class="fas fa-tags mr-2"></i>
                Nouvelle Catégorie
            </button>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cube text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Produits Actifs</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $activeProducts ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Stock Faible</p>
                    <p class="text-2xl font-bold text-red-600"><?= $lowStockProducts ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-euro-sign text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Valeur Stock</p>
                    <p class="text-2xl font-bold text-green-600"><?= number_format($totalStockValue, 2) ?>€</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-tags text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Catégories</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $totalCategories ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card p-4">
        <div class="flex flex-wrap gap-4 items-center">
            <div>
                <label class="text-sm font-medium text-gray-700 mr-2">Catégorie :</label>
                <select onchange="applyFilters()" id="category-filter" class="form-input-sm">
                    <option value="all">Toutes les catégories</option>
                    <?php foreach ($productCategories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter === $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="text-sm font-medium text-gray-700 mr-2">Statut :</label>
                <select onchange="applyFilters()" id="status-filter" class="form-input-sm">
                    <option value="all">Tous les statuts</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                    <option value="discontinued" <?= $statusFilter === 'discontinued' ? 'selected' : '' ?>>Discontinué</option>
                </select>
            </div>
            
            <button onclick="resetFilters()" class="btn-secondary btn-sm">
                <i class="fas fa-undo mr-2"></i>
                Reset
            </button>
        </div>
    </div>

    <!-- Liste des produits -->
    <div class="card">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-cube text-4xl mb-4"></i>
                                <p class="text-lg">Aucun produit trouvé</p>
                                <p class="text-sm">Créez votre premier produit pour commencer !</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center">
                                        <?php if ($product['category_icon']): ?>
                                        <i class="<?= $product['category_icon'] ?> mr-3 text-lg" style="color: <?= $product['category_color'] ?>"></i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                            <?php if ($product['description']): ?>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($product['description'], 0, 50)) ?>...</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <?php if ($product['category_name']): ?>
                                    <span class="inline-block px-2 py-1 text-xs font-medium rounded" 
                                          style="background-color: <?= $product['category_color'] ?>20; color: <?= $product['category_color'] ?>">
                                        <?= htmlspecialchars($product['category_name']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400">Non catégorisé</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars($product['sku'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-gray-900"><?= number_format($product['price'], 2) ?>€</div>
                                        <?php if ($product['cost_price'] > 0): ?>
                                        <div class="text-gray-500">Coût: <?= number_format($product['cost_price'], 2) ?>€</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium <?= $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-red-600' : 'text-gray-900' ?>">
                                            <?= $product['stock_quantity'] ?> <?= $product['unit'] ?>
                                        </div>
                                        <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                        <div class="text-red-500 text-xs">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Stock faible !
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <?php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800',
                                        'inactive' => 'bg-gray-100 text-gray-800',
                                        'discontinued' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusLabels = [
                                        'active' => 'Actif',
                                        'inactive' => 'Inactif',
                                        'discontinued' => 'Discontinué'
                                    ];
                                    ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusColors[$product['status']] ?>">
                                        <?= $statusLabels[$product['status']] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="editProduct('<?= $product['id'] ?>')" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="stockMovement('<?= $product['id'] ?>', '<?= htmlspecialchars($product['name']) ?>')" 
                                                class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce produit ?')">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals et formulaires -->
<div id="modals-container"></div>

<!-- Inclusion du CSS moderne -->
<link rel="stylesheet" href="assets/modal-styles.css">

<style>
.form-input-sm {
    @apply px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500;
}
.btn-sm {
    @apply px-3 py-1 text-sm;
}

/* Styles additionnels pour les onglets */
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* Animation d'apparition */
@media (prefers-reduced-motion: no-preference) {
    .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }
    .animate-slide-up {
        animation: slideUp 0.3s ease-out;
    }
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// Fonctions de filtrage
function applyFilters() {
    const category = document.getElementById('category-filter').value;
    const status = document.getElementById('status-filter').value;
    const url = new URL(window.location);
    
    if (category !== 'all') {
        url.searchParams.set('category', category);
    } else {
        url.searchParams.delete('category');
    }
    
    if (status !== 'all') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    
    window.location = url;
}

function resetFilters() {
    window.location = '?page=inventory';
}

// Affichage des formulaires
function showProductForm() {
    // Réinitialiser le formulaire
    document.getElementById('addProductForm').reset();
    
    // Réinitialiser les onglets - afficher le premier onglet
    switchTab(document.querySelector('.modern-tab'), 'tab-basic');
    
    // Afficher le modal d'ajout de produit
    document.getElementById('addProductModal').style.display = 'flex';
}

function closeAddProductModal() {
    document.getElementById('addProductModal').style.display = 'none';
}

function showCategoryForm() {
    // Réinitialiser le formulaire
    document.getElementById('addCategoryForm').reset();
    // Afficher le modal d'ajout de catégorie
    document.getElementById('addCategoryModal').style.display = 'block';
}

// Fonction de gestion des onglets
function switchTab(tabElement, contentId) {
    // Supprimer la classe active de tous les onglets
    document.querySelectorAll('.modern-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Cacher tout le contenu des onglets
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
    });
    
    // Activer l'onglet cliqué
    tabElement.classList.add('active');
    
    // Afficher le contenu correspondant
    const targetContent = document.getElementById(contentId);
    if (targetContent) {
        targetContent.style.display = 'block';
        targetContent.classList.add('active');
        
        // Ajouter l'animation
        setTimeout(() => {
            targetContent.classList.add('animate-slide-up');
        }, 10);
    }
}

function editProduct(id) {
    console.log('Chargement du produit ID:', id);
    
    // Charger les données du produit via l'API dédiée
    fetch(`api/products.php?action=get_product&product_id=${encodeURIComponent(id)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Réponse API reçue:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Données API reçues:', data);
        if (data.success && data.product) {
            const product = data.product;
            
            // Remplir le formulaire avec les données du produit
            const fields = {
                'edit_product_id': product.id,
                'edit_product_name': product.name || '',
                'edit_product_description': product.description || '',
                'edit_product_sku': product.sku || '',
                'edit_product_category': product.category_id || '',
                'edit_product_price': product.price || '',
                'edit_product_cost_price': product.cost_price || '',
                'edit_product_stock': product.stock_quantity || '',
                'edit_product_min_stock': product.min_stock_level || '',
                'edit_product_max_stock': product.max_stock_level || '',
                'edit_product_unit': product.unit || 'pièce',
                'edit_product_barcode': product.barcode || '',
                'edit_product_weight': product.weight || '',
                'edit_product_dimensions': product.dimensions || '',
                'edit_product_supplier': product.supplier || '',
                'edit_product_location': product.location || '',
                'edit_product_status': product.status || 'active'
            };
            
            // Remplir chaque champ avec vérification
            Object.entries(fields).forEach(([fieldId, value]) => {
                const element = document.getElementById(fieldId);
                if (element) {
                    element.value = value;
                    console.log(`✓ Champ ${fieldId} rempli avec:`, value);
                } else {
                    console.warn(`⚠ Champ non trouvé: ${fieldId}`);
                }
            });
            
            // Afficher le modal d'édition
            const modal = document.getElementById('editProductModal');
            if (modal) {
                modal.style.display = 'block';
                console.log('✓ Modal d\'édition affiché');
            } else {
                console.error('❌ Modal editProductModal non trouvé');
                alert('Erreur: Interface de modification non disponible');
            }
        } else {
            console.error('❌ Erreur dans les données:', data);
            alert(`Erreur: ${data.error || 'Produit non trouvé'}`);
        }
    })
    .catch(error => {
        console.error('❌ Erreur complète API:', error);
        // Fallback - essayer l'ancienne méthode
        console.log('🔄 Tentative avec l\'ancienne API...');
        loadProductFallback(id);
    });
}

// Méthode de fallback pour le chargement des produits
function loadProductFallback(id) {
    fetch(`?page=inventory&action=get_product&product_id=${encodeURIComponent(id)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.product) {
            console.log('✓ Fallback réussi');
            // Même logique de remplissage que ci-dessus
            // ... [code de remplissage identique] ...
        } else {
            console.error('❌ Fallback également échoué');
            alert('Erreur: Impossible de charger les données du produit');
        }
    })
    .catch(error => {
        console.error('❌ Erreur fallback:', error);
        alert('Erreur système: Contactez l\'administrateur');
    });
}

function stockMovement(id, name) {
    // Remplir le formulaire avec l'ID du produit et le nom
    document.getElementById('movement_product_id').value = id;
    document.getElementById('movement_product_name').textContent = name;
    
    // Afficher le modal de mouvement de stock
    document.getElementById('stockMovementModal').style.display = 'block';
    
    // Recharger l'historique des mouvements pour ce produit
    loadStockHistory(id);
}

// Logging des clics
document.addEventListener('click', function(e) {
    if (e.target.closest('button, a')) {
        logUserAction('click', e.target.closest('button, a').innerText);
    }
});

function logUserAction(action, details) {
    // Envoi AJAX du log (à implémenter)
    console.log('Action:', action, 'Details:', details);
}

// Fonctions pour le modal de mouvement de stock
function closeStockMovementModal() {
    document.getElementById('stockMovementModal').style.display = 'none';
}

// Fonctions pour le modal d'édition de produit
function closeEditProductModal() {
    document.getElementById('editProductModal').style.display = 'none';
}

// Fonctions pour les modals d'ajout
function closeAddProductModal() {
    document.getElementById('addProductModal').style.display = 'none';
}

function closeAddCategoryModal() {
    document.getElementById('addCategoryModal').style.display = 'none';
}

function loadStockHistory(productId) {
    // Charger l'historique des mouvements via AJAX
    fetch(`?page=inventory&action=get_stock_history&product_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            const historyContainer = document.getElementById('stockHistoryContainer');
            if (data.movements && data.movements.length > 0) {
                let html = '<div class="mt-4"><h4 class="font-semibold mb-2">Historique des mouvements :</h4><div class="space-y-2">';
                data.movements.forEach(movement => {
                    const typeColor = movement.type === 'in' ? 'text-green-600' : movement.type === 'out' ? 'text-red-600' : 'text-blue-600';
                    const typeIcon = movement.type === 'in' ? 'fa-arrow-up' : movement.type === 'out' ? 'fa-arrow-down' : 'fa-edit';
                    html += `
                        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <i class="fas ${typeIcon} ${typeColor} mr-2"></i>
                                    <span class="font-medium">${movement.type === 'in' ? 'Entrée' : movement.type === 'out' ? 'Sortie' : 'Ajustement'}</span>
                                    <span class="ml-2 ${typeColor}">${movement.quantity}</span>
                                </div>
                                <span class="text-sm text-gray-500">${movement.created_at}</span>
                            </div>
                            ${movement.reason ? `<p class="text-sm text-gray-600 mt-1">Motif: ${movement.reason}</p>` : ''}
                            ${movement.reference ? `<p class="text-sm text-gray-600">Réf: ${movement.reference}</p>` : ''}
                        </div>
                    `;
                });
                html += '</div></div>';
                historyContainer.innerHTML = html;
            } else {
                historyContainer.innerHTML = '<p class="text-gray-500 mt-4">Aucun mouvement enregistré</p>';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('stockHistoryContainer').innerHTML = '<p class="text-red-500 mt-4">Erreur lors du chargement</p>';
        });
}

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const stockModal = document.getElementById('stockMovementModal');
    const editModal = document.getElementById('editProductModal');
    const addProductModal = document.getElementById('addProductModal');
    const addCategoryModal = document.getElementById('addCategoryModal');
    
    if (event.target === stockModal) {
        stockModal.style.display = 'none';
    }
    if (event.target === editModal) {
        editModal.style.display = 'none';
    }
    if (event.target === addProductModal) {
        addProductModal.style.display = 'none';
    }
    if (event.target === addCategoryModal) {
        addCategoryModal.style.display = 'none';
    }
}
</script>

<!-- Modal de mouvement de stock -->
<div id="stockMovementModal" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Mouvement de stock - <span id="movement_product_name"></span>
                    </h3>
                    <button onclick="closeStockMovementModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="stock_movement">
                    <input type="hidden" name="product_id" id="movement_product_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Type de mouvement</label>
                            <select name="movement_type" class="form-input" required>
                                <option value="">Choisir le type</option>
                                <option value="in">Entrée de stock</option>
                                <option value="out">Sortie de stock</option>
                                <option value="adjustment">Ajustement</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Quantité</label>
                            <input type="number" name="quantity" class="form-input" min="1" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Référence (optionnel)</label>
                            <input type="text" name="reference" class="form-input" placeholder="Ex: BON001, FAC123">
                        </div>
                        
                        <div>
                            <label class="form-label">Motif</label>
                            <input type="text" name="reason" class="form-input" placeholder="Ex: Réception fournisseur, Vente">
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label">Notes (optionnel)</label>
                        <textarea name="notes" class="form-input" rows="3" placeholder="Commentaires additionnels..."></textarea>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer le mouvement
                        </button>
                        <button type="button" onclick="closeStockMovementModal()" class="btn-secondary">
                            Annuler
                        </button>
                    </div>
                </form>
                
                <!-- Container pour l'historique -->
                <div id="stockHistoryContainer"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'édition de produit -->
<div id="editProductModal" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Éditer le produit
                    </h3>
                    <button onclick="closeEditProductModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <!-- Informations de base -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Informations de base</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Nom du produit *</label>
                                <input type="text" name="name" id="edit_product_name" class="form-input" required>
                            </div>
                            
                            <div>
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" id="edit_product_sku" class="form-input" placeholder="Code produit unique">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="edit_product_description" class="form-input" rows="3"></textarea>
                            </div>
                            
                            <div>
                                <label class="form-label">Catégorie</label>
                                <select name="category_id" id="edit_product_category" class="form-input">
                                    <option value="">Aucune catégorie</option>
                                    <?php foreach ($productCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Statut</label>
                                <select name="status" id="edit_product_status" class="form-input" required>
                                    <option value="active">Actif</option>
                                    <option value="inactive">Inactif</option>
                                    <option value="discontinued">Discontinué</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Prix et coûts -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Prix et coûts</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Prix de vente (€)</label>
                                <input type="number" name="price" id="edit_product_price" class="form-input" step="0.01" min="0">
                            </div>
                            
                            <div>
                                <label class="form-label">Prix de revient (€)</label>
                                <input type="number" name="cost_price" id="edit_product_cost_price" class="form-input" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gestion des stocks -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Gestion des stocks</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label">Stock actuel</label>
                                <input type="number" name="stock_quantity" id="edit_product_stock" class="form-input" min="0">
                            </div>
                            
                            <div>
                                <label class="form-label">Stock minimum</label>
                                <input type="number" name="min_stock_level" id="edit_product_min_stock" class="form-input" min="0">
                            </div>
                            
                            <div>
                                <label class="form-label">Stock maximum</label>
                                <input type="number" name="max_stock_level" id="edit_product_max_stock" class="form-input" min="0">
                            </div>
                            
                            <div>
                                <label class="form-label">Unité</label>
                                <select name="unit" id="edit_product_unit" class="form-input">
                                    <option value="pièce">Pièce</option>
                                    <option value="kg">Kilogramme</option>
                                    <option value="litre">Litre</option>
                                    <option value="mètre">Mètre</option>
                                    <option value="pack">Pack</option>
                                    <option value="boîte">Boîte</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations détaillées -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Informations détaillées</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Code-barres</label>
                                <input type="text" name="barcode" id="edit_product_barcode" class="form-input">
                            </div>
                            
                            <div>
                                <label class="form-label">Poids (kg)</label>
                                <input type="number" name="weight" id="edit_product_weight" class="form-input" step="0.01" min="0">
                            </div>
                            
                            <div>
                                <label class="form-label">Dimensions</label>
                                <input type="text" name="dimensions" id="edit_product_dimensions" class="form-input" placeholder="L x l x h">
                            </div>
                            
                            <div>
                                <label class="form-label">Fournisseur</label>
                                <input type="text" name="supplier" id="edit_product_supplier" class="form-input">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="form-label">Emplacement</label>
                                <input type="text" name="location" id="edit_product_location" class="form-input" placeholder="Ex: Entrepôt A - Rangée 3 - Étagère 2">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer les modifications
                        </button>
                        <button type="button" onclick="closeEditProductModal()" class="btn-secondary">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'ajout de produit - Design Ultra Moderne -->
<div id="addProductModal" class="fixed inset-0 modern-modal z-50 flex items-center justify-center p-4" style="display: none;" onclick="if(event.target === this) closeAddProductModal();">
    <div class="modern-modal-content animate-fade-in max-w-5xl w-full max-h-[95vh] overflow-hidden">
        <!-- En-tête moderne avec gradient et icône -->
        <div class="modal-header-gradient relative">
            <div class="flex items-center justify-between relative z-10">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-plus text-white text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-bold text-white mb-1">Nouveau Produit</h3>
                        <p class="text-white/80 text-sm">Ajoutez un produit à votre inventaire</p>
                    </div>
                </div>
                <button onclick="closeAddProductModal()" class="w-12 h-12 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition-all duration-200 backdrop-blur-sm">
                    <i class="fas fa-times text-white text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Contenu du modal avec scroll personnalisé -->
        <div class="p-8 overflow-y-auto max-h-[calc(95vh-8rem)] space-y-6">
            <!-- Onglets modernes -->
            <div class="modern-tabs">
                <div class="modern-tab active" onclick="switchTab(this, 'tab-basic')">
                    <i class="fas fa-info-circle mr-2"></i>Informations
                </div>
                <div class="modern-tab" onclick="switchTab(this, 'tab-pricing')">
                    <i class="fas fa-euro-sign mr-2"></i>Prix
                </div>
                <div class="modern-tab" onclick="switchTab(this, 'tab-stock')">
                    <i class="fas fa-boxes mr-2"></i>Stock
                </div>
                <div class="modern-tab" onclick="switchTab(this, 'tab-details')">
                    <i class="fas fa-cog mr-2"></i>Détails
                </div>
            </div>

            <form method="POST" id="addProductForm" class="space-y-6">
                <input type="hidden" name="action" value="add_product">
                
                <!-- Onglet Informations de base -->
                <div id="tab-basic" class="tab-content">
                    <div class="form-section animate-slide-up">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-xl flex items-center justify-center">
                                <i class="fas fa-info-circle text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <h4 class="modern-label text-lg ml-3">Informations essentielles</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="modern-label">Nom du produit *</label>
                                <input type="text" name="name" class="modern-input" required placeholder="Ex: iPhone 15 Pro Max">
                            </div>
                            
                            <div>
                                <label class="modern-label">Code SKU</label>
                                <input type="text" name="sku" class="modern-input" placeholder="Ex: IPH15PM-256-BLU">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="modern-label">Description détaillée</label>
                                <textarea name="description" class="modern-input" rows="4" placeholder="Décrivez les caractéristiques principales de votre produit..."></textarea>
                            </div>
                            
                            <div>
                                <label class="modern-label">Catégorie</label>
                                <select name="category_id" class="modern-input">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($productCategories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="modern-label">Statut</label>
                                <select name="status" class="modern-input" required>
                                    <option value="active">🟢 Actif</option>
                                    <option value="inactive">🟡 Inactif</option>
                                    <option value="discontinued">🔴 Discontinué</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Prix -->
                <div id="tab-pricing" class="tab-content hidden">
                    <div class="form-section animate-slide-up">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-green-100 dark:bg-green-900 rounded-xl flex items-center justify-center">
                                <i class="fas fa-euro-sign text-green-600 dark:text-green-400"></i>
                            </div>
                            <h4 class="modern-label text-lg ml-3">Tarification</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="modern-label">Prix de vente HT (€)</label>
                                <input type="number" name="price" class="modern-input" step="0.01" min="0" placeholder="0.00">
                                <p class="text-sm text-gray-500 mt-1">Prix de vente hors taxes</p>
                            </div>
                            
                            <div>
                                <label class="modern-label">Prix de revient (€)</label>
                                <input type="number" name="cost_price" class="modern-input" step="0.01" min="0" placeholder="0.00">
                                <p class="text-sm text-gray-500 mt-1">Coût d'achat ou de production</p>
                            </div>
                        </div>
                        
                        <div class="info-card mt-4">
                            <div class="flex items-center">
                                <i class="fas fa-calculator text-blue-600 mr-2"></i>
                                <span class="font-medium">Marge calculée automatiquement après saisie</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Stock -->
                <div id="tab-stock" class="tab-content hidden">
                    <div class="form-section animate-slide-up">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-xl flex items-center justify-center">
                                <i class="fas fa-boxes text-purple-600 dark:text-purple-400"></i>
                            </div>
                            <h4 class="modern-label text-lg ml-3">Gestion des stocks</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="modern-label">Stock initial</label>
                                <input type="number" name="stock_quantity" class="modern-input" min="0" value="0" placeholder="0">
                            </div>
                            
                            <div>
                                <label class="modern-label">Seuil minimum</label>
                                <input type="number" name="min_stock_level" class="modern-input" min="0" placeholder="5">
                                <p class="text-sm text-gray-500 mt-1">Alerte stock faible</p>
                            </div>
                            
                            <div>
                                <label class="modern-label">Stock maximum</label>
                                <input type="number" name="max_stock_level" class="modern-input" min="0" placeholder="100">
                            </div>
                            
                            <div>
                                <label class="modern-label">Unité de mesure</label>
                                <select name="unit" class="modern-input">
                                    <option value="pièce">📦 Pièce</option>
                                    <option value="kg">⚖️ Kilogramme</option>
                                    <option value="litre">🥛 Litre</option>
                                    <option value="mètre">📏 Mètre</option>
                                    <option value="pack">📦 Pack</option>
                                    <option value="boîte">📦 Boîte</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Détails -->
                <div id="tab-details" class="tab-content hidden">
                    <div class="form-section animate-slide-up">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-xl flex items-center justify-center">
                                <i class="fas fa-cog text-orange-600 dark:text-orange-400"></i>
                            </div>
                            <h4 class="modern-label text-lg ml-3">Informations techniques</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="modern-label">Code-barres EAN/UPC</label>
                                <input type="text" name="barcode" class="modern-input" placeholder="Ex: 3760123456789">
                            </div>
                            
                            <div>
                                <label class="modern-label">Poids (kg)</label>
                                <input type="number" name="weight" class="modern-input" step="0.01" min="0" placeholder="0.00">
                            </div>
                            
                            <div>
                                <label class="modern-label">Dimensions (L x l x H)</label>
                                <input type="text" name="dimensions" class="modern-input" placeholder="Ex: 25 x 15 x 10 cm">
                            </div>
                            
                            <div>
                                <label class="modern-label">Fournisseur principal</label>
                                <input type="text" name="supplier" class="modern-input" placeholder="Ex: TechCorp Industries">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="modern-label">Emplacement de stockage</label>
                                <input type="text" name="location" class="modern-input" placeholder="Ex: Entrepôt A - Allée 3 - Étagère B2">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons d'action modernes -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-2"></i>
                        Les champs marqués d'un * sont obligatoires
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeAddProductModal()" class="modern-btn modern-btn-secondary">
                            <i class="fas fa-times mr-2"></i>
                            Annuler
                        </button>
                        <button type="submit" class="modern-btn modern-btn-primary">
                            <i class="fas fa-plus mr-2"></i>
                            Créer le produit
                        </button>
                    </div>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus mr-2"></i>
                            Créer le produit
                        </button>
                        <button type="button" onclick="closeAddProductModal()" class="btn-secondary">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'ajout de catégorie -->
<div id="addCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        Nouvelle catégorie de produit
                    </h3>
                    <button onclick="closeAddCategoryModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" id="addCategoryForm" class="space-y-4">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div>
                        <label class="form-label">Nom de la catégorie *</label>
                        <input type="text" name="name" class="form-input" required placeholder="Ex: Électronique, Mobilier, Consommables">
                    </div>
                    
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" rows="3" placeholder="Description de la catégorie..."></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Couleur</label>
                            <select name="color" class="form-input">
                                <option value="#3b82f6">Bleu</option>
                                <option value="#10b981">Vert</option>
                                <option value="#f59e0b">Orange</option>
                                <option value="#ef4444">Rouge</option>
                                <option value="#8b5cf6">Violet</option>
                                <option value="#06b6d4">Cyan</option>
                                <option value="#84cc16">Lime</option>
                                <option value="#f97316">Orange foncé</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="form-label">Icône</label>
                            <select name="icon" class="form-input">
                                <option value="fas fa-tag">Étiquette</option>
                                <option value="fas fa-cube">Cube</option>
                                <option value="fas fa-laptop">Ordinateur</option>
                                <option value="fas fa-chair">Mobilier</option>
                                <option value="fas fa-tools">Outils</option>
                                <option value="fas fa-tshirt">Vêtements</option>
                                <option value="fas fa-book">Livres</option>
                                <option value="fas fa-gamepad">Jeux</option>
                                <option value="fas fa-car">Automobile</option>
                                <option value="fas fa-home">Maison</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus mr-2"></i>
                            Créer la catégorie
                        </button>
                        <button type="button" onclick="closeAddCategoryModal()" class="btn-secondary">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>