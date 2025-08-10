<?php
session_start();

// Configuration des erreurs pour production/développement
if (isset($_GET['debug']) || $_SERVER['HTTP_HOST'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Configuration de la base de données
$host = '127.0.0.1:3306';
$dbname = 'u327946036_mybizpanel';
$username = 'u327946036_mybizpanel';
$password = 'Mybizpanel95@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    if (isset($_GET['debug'])) {
        die("<h1>Erreur de connexion à la base de données</h1><p>" . $e->getMessage() . "</p><p>Vérifiez vos paramètres dans config.php</p>");
    } else {
        die("<h1>Erreur de connexion</h1><p>Impossible de se connecter à la base de données. Contactez l'administrateur.</p>");
    }
}

// Fonctions utilitaires
function generateId() {
    return uniqid('', true) . '_' . bin2hex(random_bytes(8));
}

function sanitizeOutput($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirectTo($page) {
    header("Location: ?page=$page");
    exit;
}

function validateProject($name, $status, $priority) {
    $errors = [];
    if (empty(trim($name))) {
        $errors[] = "Le nom du projet est obligatoire";
    }
    if (!in_array($status, ['planning', 'active', 'completed', 'on-hold'])) {
        $errors[] = "Statut invalide";
    }
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $errors[] = "Priorité invalide";
    }
    return $errors;
}

function validateTask($title, $status, $priority) {
    $errors = [];
    if (empty(trim($title))) {
        $errors[] = "Le titre de la tâche est obligatoire";
    }
    if (!in_array($status, ['todo', 'in-progress', 'completed'])) {
        $errors[] = "Statut invalide";
    }
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $errors[] = "Priorité invalide";
    }
    return $errors;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?page=login');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if ($_SESSION['role'] !== 'admin') {
        die('Accès refusé - Privilèges administrateur requis');
    }
}

function logSystemEvent($level, $message, $action = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (level, message, action, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$level, $message, $action, $_SESSION['user_id'] ?? null]);
    } catch (Exception $e) {
        error_log("Erreur log système: " . $e->getMessage());
    }
}

function createNotification($message, $type = 'info') {
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        showAutoNotification('$message', '$type');
    });
    </script>";
}

// Vérification de l'authentification pour les pages protégées
$publicPages = ['login', 'register'];
$currentPage = $_GET['page'] ?? 'dashboard';

if (!in_array($currentPage, $publicPages)) {
    requireAuth();
}
?>