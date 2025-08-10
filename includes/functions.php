<?php
// ===================================================================
// MyBizPanel - Fonctions utilitaires complètes
// Version finale avec toutes les fonctions nécessaires
// ===================================================================

// Récupération des préférences utilisateur avec gestion d'erreurs
function getUserPreferences($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $preferences = $stmt->fetch();
        
        if (!$preferences) {
            // Créer des préférences par défaut avec INSERT IGNORE pour éviter les doublons
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_preferences (user_id, theme, notifications_enabled, sound_enabled, language) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, 'light', 1, 1, 'fr']);
            
            return [
                'user_id' => $userId,
                'theme' => 'light',
                'notifications_enabled' => 1,
                'sound_enabled' => 1,
                'language' => 'fr'
            ];
        }
        
        return $preferences;
    } catch (Exception $e) {
        error_log("Erreur getUserPreferences: " . $e->getMessage());
        return [
            'user_id' => $userId,
            'theme' => 'light',
            'notifications_enabled' => 1,
            'sound_enabled' => 1,
            'language' => 'fr'
        ];
    }
}

// Mise à jour des préférences utilisateur
function updateUserPreference($userId, $key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE user_preferences SET $key = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$value, $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Erreur mise à jour préférence: " . $e->getMessage());
        return false;
    }
}

// Récupération des notifications non lues
function getUnreadNotifications($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Création d'une notification avec ID sécurisé
function createNotification($userId, $title, $message, $type = 'info') {
    global $pdo;
    try {
        $notificationId = 'notif_' . time() . '_' . bin2hex(random_bytes(4));
        $stmt = $pdo->prepare("INSERT INTO notifications (id, user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$notificationId, $userId, $title, $message, $type]);
        return $notificationId;
    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

// Mise à jour du statut utilisateur
function updateUserStatus($userId, $status = 'online') {
    global $pdo;
    try {
        // Vérifier si l'entrée existe
        $stmt = $pdo->prepare("SELECT id FROM user_status WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            // Mettre à jour
            $stmt = $pdo->prepare("UPDATE user_status SET status = ?, last_activity = NOW() WHERE user_id = ?");
            $stmt->execute([$status, $userId]);
        } else {
            // Insérer
            $stmt = $pdo->prepare("INSERT INTO user_status (id, user_id, status, last_activity) VALUES (?, ?, ?, NOW())");
            $stmt->execute([generateId(), $userId, $status]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Erreur mise à jour statut: " . $e->getMessage());
        return false;
    }
}

// Récupération des utilisateurs en ligne
function getOnlineUsers() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.first_name, u.last_name, us.last_activity 
            FROM users u 
            JOIN user_status us ON u.id = us.user_id 
            WHERE us.status = 'online' 
            AND us.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY us.last_activity DESC
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Logging des actions utilisateur
function logUserAction($userId, $action, $details = '', $page = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (id, user_id, action, details, page, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([generateId(), $userId, $action, $details, $page]);
        return true;
    } catch (Exception $e) {
        error_log("Erreur log action: " . $e->getMessage());
        return false;
    }
}

// Récupération des catégories dynamiques
function getCategories($module) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE module = ? AND is_active = 1 ORDER BY sort_order, name");
        $stmt->execute([$module]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Formatage des montants
function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

// Formatage des dates
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

// Vérification des permissions
function hasPermission($requiredRole) {
    if (!isset($_SESSION['role'])) return false;
    
    $roles = ['employee' => 1, 'support' => 2, 'admin' => 3];
    $userLevel = $roles[$_SESSION['role']] ?? 0;
    $requiredLevel = $roles[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

// Génération d'un token CSRF
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Vérification du token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Nettoyage des données entrantes
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validation email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Génération de mots de passe
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

// Hash sécurisé des mots de passe
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Vérification des mots de passe
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Analytics financières avec gestion des valeurs nulles
function getFinancialAnalytics($period = 'month') {
    global $pdo;
    
    $dateFilter = '';
    switch ($period) {
        case 'week':
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case 'year':
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
            break;
        default:
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    }
    
    try {
        $stmt = $pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
                COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count
            FROM transactions $dateFilter
        ");
        $financial = $stmt->fetch();
        
        return [
            'total_income' => floatval($financial['total_income'] ?? 0),
            'total_expense' => floatval($financial['total_expense'] ?? 0),
            'net_profit' => floatval(($financial['total_income'] ?? 0) - ($financial['total_expense'] ?? 0)),
            'income_count' => intval($financial['income_count'] ?? 0),
            'expense_count' => intval($financial['expense_count'] ?? 0)
        ];
    } catch (Exception $e) {
        error_log("Erreur getFinancialAnalytics: " . $e->getMessage());
        return [
            'total_income' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
            'income_count' => 0,
            'expense_count' => 0
        ];
    }
}

// Récupération d'un produit par ID avec gestion d'erreurs
function getProductById($productId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, pc.name as category_name 
            FROM products p 
            LEFT JOIN product_categories pc ON p.category_id = pc.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erreur getProductById: " . $e->getMessage());
        return false;
    }
}

// Récupération de toutes les catégories de produits
function getProductCategories() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM product_categories ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur getProductCategories: " . $e->getMessage());
        return [];
    }
}

// Export Excel
function exportToExcel($data, $filename, $headers = []) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    if (!empty($headers)) {
        echo "<tr>";
        foreach ($headers as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";
    }
    
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>