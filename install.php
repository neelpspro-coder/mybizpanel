<?php
// Script d'installation automatique MyBizPanel
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 Installation MyBizPanel</h1>";

// Étape 1: Vérifier la connexion DB
echo "<h2>1. Test de connexion base de données</h2>";
try {
    require_once 'config.php';
    echo "✅ Connexion DB réussie<br>";
} catch (Exception $e) {
    echo "❌ Erreur DB: " . $e->getMessage() . "<br>";
    echo "➡️ Modifiez les paramètres dans config.php<br>";
    exit;
}

// Étape 2: Créer les tables manquantes
echo "<h2>2. Création des tables</h2>";

$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(50) PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        role ENUM('admin', 'support', 'employee') DEFAULT 'employee',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    'user_preferences' => "CREATE TABLE IF NOT EXISTS user_preferences (
        user_id VARCHAR(50) PRIMARY KEY,
        theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
        language VARCHAR(10) DEFAULT 'fr',
        notifications_enabled BOOLEAN DEFAULT TRUE,
        sound_enabled BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'categories' => "CREATE TABLE IF NOT EXISTS categories (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        module VARCHAR(50) NOT NULL,
        description TEXT,
        color VARCHAR(7) DEFAULT '#3b82f6',
        icon VARCHAR(50) DEFAULT 'fas fa-folder',
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
        id VARCHAR(50) PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'user_status' => "CREATE TABLE IF NOT EXISTS user_status (
        id VARCHAR(50) PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        status ENUM('online', 'offline', 'away') DEFAULT 'offline',
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'activity_logs' => "CREATE TABLE IF NOT EXISTS activity_logs (
        id VARCHAR(50) PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        page VARCHAR(50),
        details TEXT,
        element VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'projects' => "CREATE TABLE IF NOT EXISTS projects (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('planning', 'active', 'completed', 'on-hold') DEFAULT 'planning',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        budget DECIMAL(10,2),
        start_date DATE,
        end_date DATE,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'tasks' => "CREATE TABLE IF NOT EXISTS tasks (
        id VARCHAR(50) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('todo', 'in-progress', 'completed') DEFAULT 'todo',
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        due_date DATE,
        project_id VARCHAR(50),
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'notes' => "CREATE TABLE IF NOT EXISTS notes (
        id VARCHAR(50) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'transactions' => "CREATE TABLE IF NOT EXISTS transactions (
        id VARCHAR(50) PRIMARY KEY,
        type ENUM('income', 'expense') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description VARCHAR(255),
        category VARCHAR(100),
        date DATE NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'clients' => "CREATE TABLE IF NOT EXISTS clients (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(50),
        company VARCHAR(255),
        address TEXT,
        notes TEXT,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'messages' => "CREATE TABLE IF NOT EXISTS messages (
        id VARCHAR(50) PRIMARY KEY,
        message TEXT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'blog_posts' => "CREATE TABLE IF NOT EXISTS blog_posts (
        id VARCHAR(50) PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(100) NOT NULL,
        author_email VARCHAR(255) NOT NULL,
        status ENUM('draft', 'published') DEFAULT 'published',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'inventory' => "CREATE TABLE IF NOT EXISTS inventory (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        quantity INT DEFAULT 0,
        price DECIMAL(10,2) DEFAULT 0.00,
        category VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'financial_analytics' => "CREATE TABLE IF NOT EXISTS financial_analytics (
        id VARCHAR(50) PRIMARY KEY,
        period VARCHAR(20) NOT NULL,
        total_income DECIMAL(12,2) DEFAULT 0.00,
        total_expense DECIMAL(12,2) DEFAULT 0.00,
        net_profit DECIMAL(12,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'dynamic_categories' => "CREATE TABLE IF NOT EXISTS dynamic_categories (
        id VARCHAR(50) PRIMARY KEY,
        module VARCHAR(50) NOT NULL,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(7) DEFAULT '#3b82f6',
        icon VARCHAR(50) DEFAULT 'fas fa-folder',
        is_active BOOLEAN DEFAULT TRUE,
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    'product_categories' => "CREATE TABLE IF NOT EXISTS product_categories (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        color VARCHAR(7) DEFAULT '#10b981',
        icon VARCHAR(50) DEFAULT 'fas fa-cube',
        is_active BOOLEAN DEFAULT TRUE,
        created_by VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $tableName => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Table $tableName créée<br>";
    } catch (Exception $e) {
        echo "⚠️ Table $tableName: " . $e->getMessage() . "<br>";
    }
}

// Étape 3: Créer l'utilisateur admin par défaut
echo "<h2>3. Création utilisateur admin</h2>";
try {
    // Vérifier si admin existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@mybizpanel.fr']);
    
    if (!$stmt->fetch()) {
        // Créer l'admin
        $adminId = 'admin_' . time();
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (id, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, 'admin@mybizpanel.fr', $hashedPassword, 'Admin', 'MyBizPanel', 'admin']);
        
        // Créer ses préférences
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, notifications_enabled, sound_enabled) VALUES (?, ?, ?, ?)");
        $stmt->execute([$adminId, 'light', 1, 1]);
        
        echo "✅ Utilisateur admin créé: admin@mybizpanel.fr / admin123<br>";
    } else {
        echo "✅ Utilisateur admin existe déjà<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur création admin: " . $e->getMessage() . "<br>";
}

// Étape 4: Créer quelques données de test
echo "<h2>4. Données de test</h2>";
try {
    // Catégories par défaut
    $categories = [
        ['cat_dev', 'Développement Web', 'projects', '#3b82f6', 'fas fa-code'],
        ['cat_marketing', 'Marketing', 'projects', '#ef4444', 'fas fa-bullhorn'],
        ['cat_urgent', 'Urgent', 'tasks', '#ef4444', 'fas fa-exclamation'],
        ['cat_revenus', 'Revenus', 'finances', '#10b981', 'fas fa-plus']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (id, name, module, color, icon) VALUES (?, ?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }
    
    // Articles de blog par défaut
    $blogPosts = [
        ['blog_guide', 'Guide utilisation chat', 'Le système de chat permet une communication en temps réel...', 'Guides', 'admin@mybizpanel.fr'],
        ['blog_reglement', 'Règlement intérieur', 'Règles de conduite et procédures à suivre...', 'Règlements', 'admin@mybizpanel.fr']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO blog_posts (id, title, content, category, author_email) VALUES (?, ?, ?, ?, ?)");
    foreach ($blogPosts as $post) {
        $stmt->execute($post);
    }
    
    echo "✅ Données de test créées<br>";
} catch (Exception $e) {
    echo "⚠️ Données de test: " . $e->getMessage() . "<br>";
}

echo "<h2>🎉 Installation terminée!</h2>";
echo "<p><strong>Connexion :</strong></p>";
echo "<ul>";
echo "<li>Email: admin@mybizpanel.fr</li>";
echo "<li>Mot de passe: admin123</li>";
echo "</ul>";
echo "<p><a href='index.php' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Accéder au tableau de bord</a></p>";
echo "<p><a href='test-db.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Tester l'installation</a></p>";

?>