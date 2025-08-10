-- ===================================================================
-- MyBizPanel - Base de données complète et corrigée
-- Version finale avec toutes les tables nécessaires
-- ===================================================================

CREATE DATABASE IF NOT EXISTS mybizpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mybizpanel;

-- ===================================================================
-- TABLE DES UTILISATEURS
-- ===================================================================
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('admin', 'support', 'employee') DEFAULT 'employee',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ===================================================================
-- PRÉFÉRENCES UTILISATEUR
-- ===================================================================
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    theme ENUM('light', 'dark') DEFAULT 'light',
    notifications_enabled BOOLEAN DEFAULT TRUE,
    sound_enabled BOOLEAN DEFAULT TRUE,
    language VARCHAR(5) DEFAULT 'fr',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_prefs (user_id)
);

-- ===================================================================
-- TABLE DES CLIENTS
-- ===================================================================
CREATE TABLE IF NOT EXISTS clients (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    company VARCHAR(255),
    address TEXT,
    notes TEXT,
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- TABLE DES PROJETS
-- ===================================================================
CREATE TABLE IF NOT EXISTS projects (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('planning', 'active', 'completed', 'on-hold') DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    budget DECIMAL(10,2),
    start_date DATE,
    end_date DATE,
    client_id VARCHAR(50),
    user_id VARCHAR(50) NOT NULL,
    is_archived BOOLEAN DEFAULT FALSE,
    archived_at TIMESTAMP NULL,
    archived_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_archived (is_archived),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================================================================
-- TABLE DES TÂCHES
-- ===================================================================
CREATE TABLE IF NOT EXISTS tasks (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('todo', 'in-progress', 'completed') DEFAULT 'todo',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    due_date DATE,
    project_id VARCHAR(50),
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- ===================================================================
-- TABLE DES NOTES
-- ===================================================================
CREATE TABLE IF NOT EXISTS notes (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- TABLE DES TRANSACTIONS FINANCIÈRES
-- ===================================================================
CREATE TABLE IF NOT EXISTS transactions (
    id VARCHAR(50) PRIMARY KEY,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    date DATE NOT NULL,
    attachment_url TEXT,
    client_id VARCHAR(50),
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_client_id (client_id),
    INDEX idx_type (type),
    INDEX idx_date (date),
    INDEX idx_category (category),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

-- ===================================================================
-- TABLE DES MESSAGES ET CHAT
-- ===================================================================
CREATE TABLE IF NOT EXISTS messages (
    id VARCHAR(50) PRIMARY KEY,
    sender_id VARCHAR(50) NOT NULL,
    receiver_id VARCHAR(50) NULL,
    content TEXT NOT NULL,
    is_general BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id),
    INDEX idx_created_at (created_at),
    INDEX idx_general (is_general),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- TABLE DES CATÉGORIES DYNAMIQUES POUR BLOG
-- ===================================================================
CREATE TABLE IF NOT EXISTS dynamic_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#3B82F6',
    icon VARCHAR(50) DEFAULT 'fas fa-folder',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_name (name)
);

-- ===================================================================
-- TABLE DES ARTICLES DE BLOG
-- ===================================================================
CREATE TABLE IF NOT EXISTS blog_posts (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    content TEXT NOT NULL,
    category_id INT NOT NULL,
    author_id VARCHAR(50) NOT NULL,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_id (category_id),
    INDEX idx_author_id (author_id),
    INDEX idx_published (is_published),
    FOREIGN KEY (category_id) REFERENCES dynamic_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- TABLE DES CATÉGORIES PRODUITS
-- ===================================================================
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(20) DEFAULT '#6B7280',
    icon VARCHAR(50) DEFAULT 'fas fa-tag',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_category (name)
);

-- ===================================================================
-- TABLE DES PRODUITS/INVENTAIRE
-- ===================================================================
CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sku VARCHAR(100),
    category_id INT,
    price DECIMAL(10,2) DEFAULT 0.00,
    cost_price DECIMAL(10,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 0,
    max_stock_level INT DEFAULT 1000,
    unit VARCHAR(50) DEFAULT 'pièce',
    barcode VARCHAR(100),
    weight DECIMAL(8,2),
    dimensions VARCHAR(100),
    supplier VARCHAR(255),
    location VARCHAR(255),
    status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_sku (sku),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
);

-- ===================================================================
-- TABLE DES MOUVEMENTS DE STOCK
-- ===================================================================
CREATE TABLE IF NOT EXISTS stock_movements (
    id VARCHAR(50) PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    type ENUM('entry', 'exit', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reason VARCHAR(255),
    reference VARCHAR(100),
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_id (product_id),
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- TABLE DES NOTIFICATIONS
-- ===================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id VARCHAR(50) PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- TABLE DES LOGS SYSTÈME
-- ===================================================================
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info',
    message TEXT NOT NULL,
    action VARCHAR(255),
    user_id VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================================================================
-- INSERTION DES DONNÉES DE BASE
-- ===================================================================

-- Utilisateur admin par défaut (mot de passe: admin123)
INSERT IGNORE INTO users (id, email, password, first_name, last_name, role) VALUES 
('admin_user_001', 'admin@mybizpanel.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 'MyBizPanel', 'admin');

-- Catégories par défaut pour le blog
INSERT IGNORE INTO dynamic_categories (name, color, icon, description) VALUES 
('Tutoriels', '#10B981', 'fas fa-graduation-cap', 'Guides et tutoriels techniques'),
('Règlements', '#EF4444', 'fas fa-gavel', 'Règles et procédures internes'),
('Processus', '#3B82F6', 'fas fa-cogs', 'Processus métier et workflows'),
('Guides', '#8B5CF6', 'fas fa-book', 'Guides utilisateur et documentation'),
('Annonces', '#F59E0B', 'fas fa-bullhorn', 'Annonces importantes et news');

-- Catégories par défaut pour les produits
INSERT IGNORE INTO product_categories (name, description, color, icon) VALUES 
('Électronique', 'Appareils et composants électroniques', '#3B82F6', 'fas fa-microchip'),
('Informatique', 'Matériel et logiciels informatiques', '#10B981', 'fas fa-laptop'),
('Mobilier', 'Meubles et équipements de bureau', '#8B5CF6', 'fas fa-chair'),
('Fournitures', 'Fournitures de bureau et consommables', '#F59E0B', 'fas fa-paperclip'),
('Services', 'Services et prestations diverses', '#EF4444', 'fas fa-handshake');

-- Articles de blog de démonstration
INSERT IGNORE INTO blog_posts (id, title, content, category_id, author_id) VALUES 
('blog_001', 'Guide d\'utilisation du chat temps réel', '[b]Utilisation du système de chat[/b]\n\n[color=blue]Le chat vous permet de communiquer en temps réel avec votre équipe.[/color]\n\n[list][*]Messages généraux visibles par tous[*]Messages privés entre utilisateurs[*]Notifications sonores automatiques[/list]\n\n[quote]Astuce: Utilisez @nom pour mentionner un utilisateur spécifique![/quote]', 1, 'admin_user_001'),
('blog_002', 'Règlement interne MyBizPanel', '[b]Règles d\'utilisation de la plateforme[/b]\n\n[color=red]Respect et professionnalisme sont de mise.[/color]\n\n[frame=warning]Attention: Toute violation de ces règles sera sanctionnée.[/frame]\n\n[list][*]Respecter les autres utilisateurs[*]Protéger les données confidentielles[*]Utiliser la plateforme de manière appropriée[/list]', 2, 'admin_user_001'),
('blog_003', 'Processus de traitement des commandes', '[b]Workflow standard des commandes[/b]\n\n[color=green]Suivez ces étapes pour traiter une commande:[/color]\n\n[list][*]Réception et validation[*]Préparation et vérification[*]Expédition et suivi[*]Confirmation client[/list]\n\n[frame=info]Chaque étape doit être documentée dans le système.[/frame]', 3, 'admin_user_001');

-- ===================================================================
-- OPTIMISATIONS ET INDEX SUPPLÉMENTAIRES
-- ===================================================================

-- Index composites pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_projects_user_status ON projects(user_id, status);
CREATE INDEX IF NOT EXISTS idx_tasks_user_status ON tasks(user_id, status);
CREATE INDEX IF NOT EXISTS idx_transactions_user_date ON transactions(user_id, date);
CREATE INDEX IF NOT EXISTS idx_products_category_status ON products(category_id, status);
CREATE INDEX IF NOT EXISTS idx_stock_movements_product_date ON stock_movements(product_id, created_at);

-- ===================================================================
-- VUES POUR FACILITER LES REQUÊTES
-- ===================================================================

-- Vue pour les statistiques produits
CREATE OR REPLACE VIEW product_stats AS
SELECT 
    p.id,
    p.name,
    p.stock_quantity,
    p.min_stock_level,
    pc.name as category_name,
    CASE 
        WHEN p.stock_quantity <= p.min_stock_level THEN 'low'
        WHEN p.stock_quantity = 0 THEN 'out'
        ELSE 'normal'
    END as stock_status
FROM products p
LEFT JOIN product_categories pc ON p.category_id = pc.id
WHERE p.status = 'active';

-- Vue pour les transactions avec clients
CREATE OR REPLACE VIEW transaction_details AS
SELECT 
    t.*,
    c.name as client_name,
    u.email as user_email
FROM transactions t
LEFT JOIN clients c ON t.client_id = c.id
LEFT JOIN users u ON t.user_id = u.id;

-- ===================================================================
-- TRIGGERS POUR LA COHÉRENCE DES DONNÉES
-- ===================================================================

-- Trigger pour mettre à jour le stock lors d'un mouvement
DELIMITER //
CREATE TRIGGER IF NOT EXISTS update_stock_on_movement 
AFTER INSERT ON stock_movements
FOR EACH ROW
BEGIN
    UPDATE products 
    SET stock_quantity = NEW.new_stock,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.product_id;
END//
DELIMITER ;

-- Trigger pour créer une notification de stock faible
DELIMITER //
CREATE TRIGGER IF NOT EXISTS check_low_stock 
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    IF NEW.stock_quantity <= NEW.min_stock_level AND NEW.stock_quantity > 0 THEN
        INSERT INTO notifications (id, user_id, title, message, type)
        VALUES (
            CONCAT('notif_', UNIX_TIMESTAMP(), '_', SUBSTRING(MD5(RAND()), 1, 8)),
            NEW.user_id,
            'Stock faible',
            CONCAT('Le produit "', NEW.name, '" a un stock faible (', NEW.stock_quantity, ' restant)'),
            'warning'
        );
    ELSEIF NEW.stock_quantity = 0 THEN
        INSERT INTO notifications (id, user_id, title, message, type)
        VALUES (
            CONCAT('notif_', UNIX_TIMESTAMP(), '_', SUBSTRING(MD5(RAND()), 1, 8)),
            NEW.user_id,
            'Rupture de stock',
            CONCAT('Le produit "', NEW.name, '" est en rupture de stock'),
            'error'
        );
    END IF;
END//
DELIMITER ;

-- ===================================================================
-- FIN DU SCRIPT
-- ===================================================================

-- Affichage du statut de la base de données
SELECT 'Base de données MyBizPanel créée avec succès!' as status;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'mybizpanel';