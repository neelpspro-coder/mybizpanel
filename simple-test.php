<?php
// Test ultra-simple pour diagnostiquer les problèmes
echo "<h1>Test Simple MyBizPanel</h1>";

// Test 1: PHP fonctionne
echo "<p>✅ PHP fonctionne</p>";

// Test 2: Session
session_start();
echo "<p>✅ Sessions fonctionnent</p>";

// Test 3: Connexion DB
try {
    $host = 'localhost';
    $dbname = 'mybizpanel';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Connexion base de données OK</p>";
    
    // Test 4: Table users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "<p>✅ Table users: $count utilisateurs</p>";
    
    // Test 5: Test connexion admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['admin@mybizpanel.fr']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p>✅ Utilisateur admin trouvé</p>";
        
        // Test password
        if (password_verify('admin123', $admin['password'])) {
            echo "<p>✅ Mot de passe admin correct</p>";
        } else {
            echo "<p>❌ Mot de passe admin incorrect</p>";
        }
    } else {
        echo "<p>❌ Utilisateur admin introuvable</p>";
        
        // Créer l'admin
        try {
            $adminId = 'admin_' . time();
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (id, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$adminId, 'admin@mybizpanel.fr', $hashedPassword, 'Admin', 'MyBizPanel', 'admin']);
            
            echo "<p>✅ Utilisateur admin créé</p>";
        } catch (Exception $e) {
            echo "<p>❌ Erreur création admin: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur DB: " . $e->getMessage() . "</p>";
    echo "<p>➡️ Vérifiez vos paramètres dans config.php</p>";
}

echo "<hr>";
echo "<p><a href='install.php'>Installer MyBizPanel</a></p>";
echo "<p><a href='index.php'>Aller au dashboard</a></p>";
echo "<p><a href='index.php?page=login'>Page de connexion</a></p>";
?>