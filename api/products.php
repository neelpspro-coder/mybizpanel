<?php
// ===================================================================
// API pour la gestion des produits - MyBizPanel
// ===================================================================

require_once '../config.php';
require_once '../includes/functions.php';

// Gestion CORS et headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Gestion des requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$productId = $_GET['product_id'] ?? $_POST['product_id'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $productId);
            break;
        case 'POST':
            handlePostRequest($action);
            break;
        case 'PUT':
            handlePutRequest($productId);
            break;
        case 'DELETE':
            handleDeleteRequest($productId);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    error_log("Erreur API produits: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}

// ===================================================================
// GESTION DES REQUÊTES GET
// ===================================================================
function handleGetRequest($action, $productId) {
    global $pdo;
    
    switch ($action) {
        case 'get_product':
            if (empty($productId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID produit manquant']);
                return;
            }
            
            $product = getProductById($productId);
            if ($product) {
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Produit non trouvé']);
            }
            break;
            
        case 'list_products':
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 20);
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $products = listProducts($page, $limit, $search, $category, $status);
            echo json_encode(['success' => true, 'products' => $products]);
            break;
            
        case 'get_categories':
            $categories = getProductCategories();
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'stock_movements':
            if (empty($productId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID produit manquant']);
                return;
            }
            
            $movements = getStockMovements($productId);
            echo json_encode(['success' => true, 'movements' => $movements]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}

// ===================================================================
// GESTION DES REQUÊTES POST
// ===================================================================
function handlePostRequest($action) {
    global $pdo;
    
    switch ($action) {
        case 'create_product':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            // Validation des données
            $required = ['name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Champ {$field} requis"]);
                    return;
                }
            }
            
            $productId = addProduct($data);
            if ($productId) {
                echo json_encode(['success' => true, 'product_id' => $productId, 'message' => 'Produit créé avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du produit']);
            }
            break;
            
        case 'stock_movement':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $required = ['product_id', 'type', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Champ {$field} requis"]);
                    return;
                }
            }
            
            $movementId = recordStockMovement(
                $data['product_id'],
                $data['type'],
                intval($data['quantity']),
                $data['reason'] ?? null,
                $data['reference'] ?? null
            );
            
            if ($movementId) {
                echo json_encode(['success' => true, 'movement_id' => $movementId, 'message' => 'Mouvement de stock enregistré']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement du mouvement']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non supportée']);
    }
}

// ===================================================================
// GESTION DES REQUÊTES PUT (Mise à jour)
// ===================================================================
function handlePutRequest($productId) {
    if (empty($productId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID produit manquant']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        return;
    }
    
    $success = updateProduct($productId, $data);
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Produit mis à jour avec succès']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
    }
}

// ===================================================================
// GESTION DES REQUÊTES DELETE
// ===================================================================
function handleDeleteRequest($productId) {
    global $pdo;
    
    if (empty($productId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID produit manquant']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = 'discontinued', updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([$productId]);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Produit supprimé avec succès']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
    }
}

// ===================================================================
// FONCTIONS UTILITAIRES POUR L'API
// ===================================================================

function listProducts($page = 1, $limit = 20, $search = '', $category = '', $status = '') {
    global $pdo;
    
    $offset = ($page - 1) * $limit;
    $conditions = ['p.status != ?'];
    $params = ['discontinued'];
    
    if (!empty($search)) {
        $conditions[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)';
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $conditions[] = 'p.category_id = ?';
        $params[] = $category;
    }
    
    if (!empty($status)) {
        $conditions[] = 'p.status = ?';
        $params[] = $status;
    }
    
    $whereClause = implode(' AND ', $conditions);
    $params[] = $limit;
    $params[] = $offset;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, pc.name as category_name, u.email as user_email,
                   CASE 
                       WHEN p.stock_quantity <= p.min_stock_level THEN 'low'
                       WHEN p.stock_quantity = 0 THEN 'out'
                       ELSE 'normal'
                   END as stock_status
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE {$whereClause}
            ORDER BY p.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur listProducts: " . $e->getMessage());
        return [];
    }
}

function getStockMovements($productId, $limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT sm.*, u.email as user_email, p.name as product_name
            FROM stock_movements sm
            LEFT JOIN users u ON sm.user_id = u.id
            LEFT JOIN products p ON sm.product_id = p.id
            WHERE sm.product_id = ?
            ORDER BY sm.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur getStockMovements: " . $e->getMessage());
        return [];
    }
}

// Ajout d'un produit avec validation complète
function addProduct($data) {
    global $pdo;
    
    try {
        $productId = 'prod_' . time() . '_' . bin2hex(random_bytes(4));
        
        $stmt = $pdo->prepare("
            INSERT INTO products (
                id, name, description, sku, category_id, price, cost_price, 
                stock_quantity, min_stock_level, max_stock_level, unit, 
                barcode, weight, dimensions, supplier, location, status, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $productId,
            $data['name'],
            $data['description'] ?? null,
            $data['sku'] ?? null,
            $data['category_id'] ?: null,
            floatval($data['price'] ?? 0),
            floatval($data['cost_price'] ?? 0),
            intval($data['stock_quantity'] ?? 0),
            intval($data['min_stock_level'] ?? 0),
            intval($data['max_stock_level'] ?? 1000),
            $data['unit'] ?? 'pièce',
            $data['barcode'] ?? null,
            isset($data['weight']) ? floatval($data['weight']) : null,
            $data['dimensions'] ?? null,
            $data['supplier'] ?? null,
            $data['location'] ?? null,
            $data['status'] ?? 'active',
            $_SESSION['user_id']
        ]);
        
        return $result ? $productId : false;
    } catch (Exception $e) {
        error_log("Erreur addProduct: " . $e->getMessage());
        return false;
    }
}

// Mise à jour d'un produit
function updateProduct($productId, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE products SET 
                name = ?, description = ?, sku = ?, category_id = ?, 
                price = ?, cost_price = ?, stock_quantity = ?, 
                min_stock_level = ?, max_stock_level = ?, unit = ?, 
                barcode = ?, weight = ?, dimensions = ?, supplier = ?, 
                location = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['sku'],
            $data['category_id'] ?: null,
            floatval($data['price'] ?? 0),
            floatval($data['cost_price'] ?? 0),
            intval($data['stock_quantity'] ?? 0),
            intval($data['min_stock_level'] ?? 0),
            intval($data['max_stock_level'] ?? 1000),
            $data['unit'] ?? 'pièce',
            $data['barcode'],
            isset($data['weight']) ? floatval($data['weight']) : null,
            $data['dimensions'],
            $data['supplier'],
            $data['location'],
            $data['status'] ?? 'active',
            $productId
        ]);
    } catch (Exception $e) {
        error_log("Erreur updateProduct: " . $e->getMessage());
        return false;
    }
}

// Enregistrement d'un mouvement de stock
function recordStockMovement($productId, $type, $quantity, $reason = null, $reference = null) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer le stock actuel
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Produit non trouvé");
        }
        
        $previousStock = intval($product['stock_quantity']);
        
        // Calculer le nouveau stock
        switch ($type) {
            case 'entry':
                $newStock = $previousStock + $quantity;
                break;
            case 'exit':
                $newStock = $previousStock - $quantity;
                if ($newStock < 0) {
                    throw new Exception("Stock insuffisant pour cette opération");
                }
                break;
            case 'adjustment':
                $newStock = $quantity; // quantity est la nouvelle valeur absolue
                $quantity = $newStock - $previousStock; // calculer la différence
                break;
            default:
                throw new Exception("Type de mouvement invalide");
        }
        
        // Enregistrer le mouvement
        $movementId = 'move_' . time() . '_' . bin2hex(random_bytes(4));
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (
                id, product_id, type, quantity, previous_stock, new_stock, 
                reason, reference, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $movementId,
            $productId,
            $type,
            $quantity,
            $previousStock,
            $newStock,
            $reason,
            $reference,
            $_SESSION['user_id']
        ]);
        
        // Mettre à jour le stock du produit
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStock, $productId]);
        
        $pdo->commit();
        return $movementId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur recordStockMovement: " . $e->getMessage());
        throw $e;
    }
}

?>