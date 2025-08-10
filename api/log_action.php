<?php
header('Content-Type: application/json');
require_once '../config.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $page = $_POST['page'] ?? '';
        $details = $_POST['details'] ?? '';
        $element = $_POST['element'] ?? '';
        
        if ($action) {
            // Générer un ID unique
            $id = uniqid('log_', true);
            
            // Insérer le log d'activité
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (id, user_id, action, page, details, element, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$id, $userId, $action, $page, $details, $element]);
            
            echo json_encode(['success' => true, 'logged' => true]);
        } else {
            echo json_encode(['error' => 'Action requise']);
        }
    } else {
        // GET - Récupérer les logs récents de l'utilisateur
        $limit = $_GET['limit'] ?? 50;
        $stmt = $pdo->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, (int)$limit]);
        $logs = $stmt->fetchAll();
        
        echo json_encode($logs);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>