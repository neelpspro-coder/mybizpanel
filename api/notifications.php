<?php
header('Content-Type: application/json');
require_once '../config.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'unread':
            // Récupérer les notifications non lues
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = 0 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll();
            echo json_encode($notifications);
            break;
            
        case 'mark_read':
            // Marquer une notification comme lue
            $notificationId = $_POST['id'] ?? '';
            if ($notificationId) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notificationId, $userId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'ID manquant']);
            }
            break;
            
        case 'mark_all_read':
            // Marquer toutes les notifications comme lues
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
            break;
            
        case 'online':
            // Récupérer les utilisateurs en ligne
            $stmt = $pdo->query("
                SELECT u.id, u.first_name, u.last_name, us.last_activity 
                FROM users u 
                LEFT JOIN user_status us ON u.id = us.user_id 
                WHERE us.status = 'online' 
                AND us.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY us.last_activity DESC
            ");
            $onlineUsers = $stmt->fetchAll();
            echo json_encode($onlineUsers);
            break;
            
        case 'preferences':
            // Récupérer les préférences utilisateur
            $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$userId]);
            $preferences = $stmt->fetch();
            
            if (!$preferences) {
                // Créer des préférences par défaut
                $stmt = $pdo->prepare("
                    INSERT INTO user_preferences (user_id, theme, notifications_enabled, sound_enabled) 
                    VALUES (?, 'light', 1, 1)
                ");
                $stmt->execute([$userId]);
                $preferences = [
                    'user_id' => $userId,
                    'theme' => 'light',
                    'notifications_enabled' => 1,
                    'sound_enabled' => 1
                ];
            }
            echo json_encode($preferences);
            break;
            
        case 'update_status':
            // Mettre à jour le statut utilisateur
            $status = $_POST['status'] ?? 'online';
            
            // Vérifier si l'entrée existe
            $stmt = $pdo->prepare("SELECT id FROM user_status WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            if ($stmt->fetch()) {
                // Mettre à jour
                $stmt = $pdo->prepare("UPDATE user_status SET status = ?, last_activity = NOW() WHERE user_id = ?");
                $stmt->execute([$status, $userId]);
            } else {
                // Insérer
                $id = uniqid('status_', true);
                $stmt = $pdo->prepare("INSERT INTO user_status (id, user_id, status, last_activity) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$id, $userId, $status]);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'create':
            // Créer une nouvelle notification
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'info';
            $targetUserId = $_POST['target_user_id'] ?? $userId;
            
            if ($title && $message) {
                $id = uniqid('notif_', true);
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (id, user_id, title, message, type, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$id, $targetUserId, $title, $message, $type]);
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                echo json_encode(['error' => 'Titre et message requis']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Action non reconnue']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>