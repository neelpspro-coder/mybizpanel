<?php
// Script de test pour diagnostiquer les problèmes de base de données
session_start();

// Test de connexion à la base de données
try {
    require_once 'config.php';
    echo "<h2>✅ Connexion à la base de données : OK</h2>";
    
    // Test des tables principales
    $tables = [
        'users' => 'Utilisateurs',
        'user_preferences' => 'Préférences utilisateur',
        'categories' => 'Catégories',
        'notifications' => 'Notifications',
        'activity_logs' => 'Logs d\'activité',
        'user_status' => 'Statut utilisateur',
        'projects' => 'Projets',
        'tasks' => 'Tâches',
        'notes' => 'Notes',
        'transactions' => 'Transactions',
        'clients' => 'Clients',
        'messages' => 'Messages',
        'blog_posts' => 'Articles de blog',
        'inventory' => 'Inventaire',
        'financial_analytics' => 'Analytics financières'
    ];
    
    echo "<h3>Vérification des tables :</h3>";
    foreach ($tables as $table => $description) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "<p>✅ $description ($table) : $count enregistrements</p>";
        } catch (Exception $e) {
            echo "<p>❌ $description ($table) : Table manquante ou erreur - " . $e->getMessage() . "</p>";
        }
    }
    
    // Test des utilisateurs par défaut
    echo "<h3>Utilisateurs par défaut :</h3>";
    try {
        $stmt = $pdo->query("SELECT id, email, role FROM users ORDER BY role");
        $users = $stmt->fetchAll();
        if (empty($users)) {
            echo "<p>❌ Aucun utilisateur trouvé - Vérifiez que les données par défaut ont été importées</p>";
        } else {
            foreach ($users as $user) {
                echo "<p>✅ {$user['email']} - {$user['role']}</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p>❌ Erreur lors de la récupération des utilisateurs : " . $e->getMessage() . "</p>";
    }
    
    // Test de connexion utilisateur
    echo "<h3>Test de connexion :</h3>";
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            echo "<p>✅ Connexion réussie ! Redirection vers le dashboard...</p>";
            echo "<script>setTimeout(() => window.location.href = 'index.php', 2000);</script>";
        } else {
            echo "<p>❌ Email ou mot de passe incorrect</p>";
        }
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo "<form method='post' style='background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h4>Test de connexion :</h4>
            <p>
                <label>Email :</label><br>
                <input type='email' name='email' value='admin@mybizpanel.fr' style='width: 300px; padding: 8px;' required>
            </p>
            <p>
                <label>Mot de passe :</label><br>
                <input type='password' name='password' value='admin123' style='width: 300px; padding: 8px;' required>
            </p>
            <button type='submit' name='login' style='background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Se connecter</button>
        </form>";
    } else {
        echo "<p>✅ Utilisateur connecté : {$_SESSION['email']} ({$_SESSION['role']})</p>";
        echo "<p><a href='index.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Aller au dashboard</a></p>";
        echo "<p><a href='?logout=1' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Se déconnecter</a></p>";
    }
    
    if (isset($_GET['logout'])) {
        session_destroy();
        echo "<script>window.location.href = 'test-db.php';</script>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Erreur de connexion : " . $e->getMessage() . "</h2>";
    echo "<p>Vérifiez votre fichier config.php et les paramètres de base de données.</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test MyBizPanel</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2 { color: #059669; }
        h3 { color: #0369a1; margin-top: 30px; }
        p { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🔧 Diagnostic MyBizPanel</h1>
    <p>Cette page permet de diagnostiquer les problèmes d'installation.</p>
    <hr>
</body>
</html>