-- Base de données MyBizPanel - Structure complète
CREATE DATABASE IF NOT EXISTS mybizpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mybizpanel;

-- Table des utilisateurs
CREATE TABLE users (
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

-- Table des clients
CREATE TABLE clients (
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
    INDEX idx_email (email)
);

-- Table des projets
CREATE TABLE projects (
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
    INDEX idx_archived_by (archived_by)
);

-- Table des tâches
CREATE TABLE tasks (
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
    INDEX idx_due_date (due_date)
);

-- Table des notes
CREATE TABLE notes (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    user_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
);

-- Table des transactions financières
CREATE TABLE transactions (
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
    INDEX idx_category (category)
);

-- Table des messages
CREATE TABLE messages (
    id VARCHAR(50) PRIMARY KEY,
    sender_id VARCHAR(50) NOT NULL,
    receiver_id VARCHAR(50) NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id),
    INDEX idx_created_at (created_at)
);

-- Table pour les articles de blog
CREATE TABLE blog_posts (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('tutoriels', 'reglements', 'processus', 'guides', 'annonces') NOT NULL,
    author_id VARCHAR(50) NOT NULL,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_author_id (author_id),
    INDEX idx_category (category),
    INDEX idx_is_published (is_published),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des logs système complets
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('info', 'warning', 'error') DEFAULT 'info',
    message TEXT NOT NULL,
    action VARCHAR(100),
    page VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    user_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level (level),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Table pour les catégories dynamiques
CREATE TABLE dynamic_categories (
    id VARCHAR(50) PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) DEFAULT 'fas fa-tag',
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_module (module),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table pour le statut en ligne des utilisateurs
CREATE TABLE user_sessions (
    id VARCHAR(50) PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_online BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_online (is_online),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table pour les notifications temps réel
CREATE TABLE real_time_notifications (
    id VARCHAR(50) PRIMARY KEY,
    type ENUM('message', 'project', 'task', 'finance', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    target_user_id VARCHAR(50),
    sender_id VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target_user (target_user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table pour la gestion de stock/produits
CREATE TABLE product_categories (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#10b981',
    icon VARCHAR(50) DEFAULT 'fas fa-cube',
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE products (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    sku VARCHAR(100) UNIQUE,
    category_id VARCHAR(50),
    price DECIMAL(10,2) DEFAULT 0.00,
    cost_price DECIMAL(10,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 5,
    max_stock_level INT DEFAULT 100,
    unit VARCHAR(20) DEFAULT 'pcs',
    barcode VARCHAR(100),
    weight DECIMAL(8,2),
    dimensions VARCHAR(100),
    supplier VARCHAR(200),
    location VARCHAR(100),
    status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_sku (sku),
    INDEX idx_status (status),
    INDEX idx_stock (stock_quantity),
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table pour les mouvements de stock
CREATE TABLE stock_movements (
    id VARCHAR(50) PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    type ENUM('in', 'out', 'adjustment', 'transfer') NOT NULL,
    quantity INT NOT NULL,
    reference VARCHAR(100),
    reason VARCHAR(200),
    notes TEXT,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table pour les préférences utilisateur (mode sombre, etc.)
CREATE TABLE user_preferences (
    user_id VARCHAR(50) PRIMARY KEY,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    language VARCHAR(10) DEFAULT 'fr',
    timezone VARCHAR(50) DEFAULT 'Europe/Paris',
    notifications_enabled BOOLEAN DEFAULT TRUE,
    sound_enabled BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Données de test - Utilisateurs par défaut
INSERT INTO users (id, email, password, first_name, last_name, role, is_active) VALUES 
('admin_001', 'admin@mybizpanel.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'MyBizPanel', 'admin', TRUE),
('support_001', 'support@mybizpanel.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Support', 'Team', 'support', TRUE),
('employee_001', 'employee@mybizpanel.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', 'Test', 'employee', TRUE);

-- Articles de blog par défaut
INSERT INTO blog_posts (id, title, content, category, author_email, status) VALUES
('blog_001', 'Guide d\'utilisation du chat temps réel', 'Le système de chat permet une communication instantanée entre tous les membres de l\'équipe. Utilisez #général pour les discussions publiques et les messages privés pour les conversations individuelles.', 'Guides', 'admin@mybizpanel.fr', 'published'),
('blog_002', 'Règlement intérieur', 'Règles de conduite et procédures à suivre au sein de l\'organisation. Respectez les délais, communiquez efficacement et collaborez de manière constructive.', 'Règlements', 'admin@mybizpanel.fr', 'published'),
('blog_003', 'Processus de commande', 'Étapes détaillées pour le traitement des commandes : réception, validation, préparation, expédition et suivi. Chaque étape doit être documentée dans le système.', 'Processus', 'admin@mybizpanel.fr', 'published');

-- Catégories par défaut
INSERT INTO categories (id, name, module, description, color, icon, sort_order) VALUES
('cat_001', 'Développement Web', 'projects', 'Projets de développement web et applications', '#3b82f6', 'fas fa-code', 1),
('cat_002', 'Marketing Digital', 'projects', 'Campagnes marketing et communication', '#ef4444', 'fas fa-bullhorn', 2),
('cat_003', 'Consultation', 'projects', 'Services de conseil et expertise', '#10b981', 'fas fa-handshake', 3),
('cat_004', 'Urgent', 'tasks', 'Tâches à traiter en priorité', '#ef4444', 'fas fa-exclamation', 1),
('cat_005', 'Routine', 'tasks', 'Tâches récurrentes', '#6b7280', 'fas fa-clock', 2),
('cat_006', 'Revenus', 'finances', 'Entrées d\'argent', '#10b981', 'fas fa-plus', 1),
('cat_007', 'Dépenses', 'finances', 'Sorties d\'argent', '#ef4444', 'fas fa-minus', 2);

-- Préférences par défaut pour les utilisateurs
INSERT INTO user_preferences (user_id, theme, notifications_enabled, sound_enabled) VALUES
('admin_001', 'light', TRUE, TRUE),
('support_001', 'light', TRUE, TRUE),
('employee_001', 'light', TRUE, TRUE);
    sound_enabled BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    dashboard_layout JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Utilisateur admin par défaut
INSERT INTO users (id, email, password, first_name, last_name, role) VALUES 
('admin_1', 'admin@mybizpanel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'System', 'admin');

-- Données de test pour les clients
INSERT INTO clients (id, name, email, company, user_id) VALUES 
('client_1', 'Marie Dupont', 'marie.dupont@email.com', 'Entreprise ABC', 'admin_1'),
('client_2', 'Jean Martin', 'jean.martin@email.com', 'StartUp XYZ', 'admin_1'),
('client_3', 'Sophie Bernard', 'sophie.bernard@email.com', '', 'admin_1');

-- Projets de test
INSERT INTO projects (id, name, description, status, priority, budget, client_id, user_id) VALUES 
('proj_1', 'Site Web Entreprise ABC', 'Développement du site vitrine pour Marie Dupont', 'active', 'high', 2500.00, 'client_1', 'admin_1'),
('proj_2', 'Application Mobile XYZ', 'App mobile pour la startup de Jean Martin', 'planning', 'medium', 5000.00, 'client_2', 'admin_1');

-- Tâches de test
INSERT INTO tasks (id, title, description, status, priority, due_date, project_id, user_id) VALUES 
('task_1', 'Design des maquettes', 'Créer les maquettes du site web', 'completed', 'high', '2025-08-01', 'proj_1', 'admin_1'),
('task_2', 'Développement frontend', 'Intégration HTML/CSS', 'in-progress', 'high', '2025-08-05', 'proj_1', 'admin_1');

-- Transactions de test
INSERT INTO transactions (id, type, amount, description, category, date, client_id, user_id) VALUES 
('trans_1', 'income', 1250.00, 'Acompte Site Web ABC', 'service', '2025-08-01', 'client_1', 'admin_1'),
('trans_2', 'expense', 150.00, 'Hébergement serveur', 'frais', '2025-08-01', NULL, 'admin_1');

-- Notes de test
INSERT INTO notes (id, title, content, user_id) VALUES 
('note_1', 'Réunion client ABC', 'Points discutés :\n- Logo à intégrer\n- Couleurs : bleu et blanc\n- Livraison fin août', 'admin_1'),
('note_2', 'Idées nouvelles fonctionnalités', 'Dashboard temps réel\nNotifications push\nExport PDF', 'admin_1');

-- Articles de blog de démonstration
INSERT INTO blog_posts (id, title, content, category, author_id, is_published) VALUES 
('blog_1', 'Comment utiliser le chat MyBizPanel', 'Le système de messagerie de MyBizPanel permet une communication en temps réel entre tous les membres de l\'équipe.\n\n## Fonctionnalités principales :\n\n• **Messages instantanés** : Vos messages apparaissent immédiatement\n• **Notifications sonores** : Recevez des alertes quand d\'autres utilisateurs écrivent\n• **Raccourcis clavier** : Utilisez Ctrl+Enter pour envoyer rapidement\n• **Suppression** : Vous pouvez supprimer vos propres messages\n\n## Comment envoyer un message :\n\n1. Accédez à la section "Messages" dans le menu\n2. Tapez votre message dans la zone de texte (500 caractères max)\n3. Cliquez sur "Envoyer" ou utilisez Ctrl+Enter\n4. Votre message apparaît instantanément pour tous les utilisateurs\n\n## Bonnes pratiques :\n\n• Restez professionnel dans vos communications\n• Utilisez des messages courts et clairs\n• Mentionnez les personnes concernées par votre message\n• Évitez le spam de messages courts successifs', 'tutoriels', 'admin_1', 1),

('blog_2', 'Règlement intérieur - Communication', 'Ce règlement définit les bonnes pratiques de communication au sein de MyBizPanel.\n\n## 1. Respect et courtoisie\n\n• Tous les échanges doivent se faire dans le respect mutuel\n• Utilisez un langage professionnel et courtois\n• Évitez les messages en majuscules (considérés comme des cris)\n\n## 2. Utilisation du chat\n\n• Le chat est réservé aux communications professionnelles\n• Évitez les conversations personnelles prolongées\n• Respectez les heures de travail pour les messages urgents\n\n## 3. Gestion des projets\n\n• Chaque projet doit avoir un responsable désigné\n• Les mises à jour importantes doivent être communiquées à l\'équipe\n• Utilisez les notes pour documenter les décisions importantes\n\n## 4. Confidentialité\n\n• Les informations clients sont confidentielles\n• Ne partagez jamais d\'informations sensibles par message\n• Respectez la confidentialité des projets en cours\n\n## Sanctions\n\nLe non-respect de ces règles peut entraîner un avertissement ou une restriction d\'accès.', 'reglements', 'admin_1', 1),

('blog_3', 'Processus de commande client', 'Guide complet du processus de prise de commande et de gestion client.\n\n## Étape 1 : Premier contact\n\n1. Réception de la demande (email, téléphone, formulaire)\n2. Création de la fiche client dans MyBizPanel\n3. Qualification du besoin et budget\n4. Envoi du devis dans les 48h\n\n## Étape 2 : Validation\n\n1. Négociation et ajustements du devis\n2. Signature du bon de commande\n3. Réception de l\'acompte (30% minimum)\n4. Création du projet dans MyBizPanel\n\n## Étape 3 : Réalisation\n\n1. Planification des tâches\n2. Assignation de l\'équipe\n3. Suivi hebdomadaire avec le client\n4. Livraisons intermédiaires si nécessaire\n\n## Étape 4 : Livraison\n\n1. Tests et validation finale\n2. Formation du client si nécessaire\n3. Livraison et recette\n4. Facturation du solde\n5. Support post-livraison\n\n## Documents requis :\n\n• Fiche client complète\n• Devis signé\n• Bon de commande\n• Cahier des charges\n• Planning prévisionnel\n• Factures', 'processus', 'admin_1', 1),

('blog_4', 'Guide des appels clients', 'Meilleures pratiques pour les appels téléphoniques avec les clients.\n\n## Préparation de l\'appel\n\n• Consultez la fiche client avant l\'appel\n• Préparez les points à aborder\n• Ayez sous les yeux le dossier du projet\n• Choisissez un moment calme et sans interruption\n\n## Pendant l\'appel\n\n• **Accueil professionnel** : "Bonjour [Nom], [Votre prénom] de MyBizPanel"\n• **Écoute active** : Laissez parler le client et prenez des notes\n• **Reformulation** : Répétez les points importants pour confirmation\n• **Solutions** : Proposez des solutions concrètes\n\n## Prise de notes\n\n• Notez tous les points importants dans MyBizPanel\n• Enregistrez les décisions prises\n• Définissez les prochaines étapes\n• Fixez un suivi si nécessaire\n\n## Suivi post-appel\n\n• Envoyez un email de synthèse dans les 2h\n• Mettez à jour le projet dans MyBizPanel\n• Planifiez les actions à réaliser\n• Fixez le prochain point si nécessaire\n\n## Gestion des conflits\n\n• Restez calme et professionnel\n• Écoutez les préoccupations du client\n• Proposez des solutions alternatives\n• Escaladez vers un manager si nécessaire', 'guides', 'admin_1', 1),

('blog_5', 'Nouvelle fonctionnalité - Archivage des projets', 'Découvrez le nouveau système d\'archivage automatique des projets terminés.\n\n## Qu\'est-ce que l\'archivage ?\n\nL\'archivage permet de conserver l\'historique des projets terminés tout en gardant une interface claire pour les projets en cours.\n\n## Fonctionnalités :\n\n• **Archivage automatique** : Les projets "completed" sont automatiquement archivés après 30 jours\n• **Conservation des données** : Toutes les informations restent accessibles\n• **Recherche** : Possibilité de rechercher dans les projets archivés\n• **Restauration** : Les projets peuvent être désarchivés si nécessaire\n\n## Comment ça marche ?\n\n1. Un projet passe au statut "completed"\n2. Après 30 jours, il est automatiquement archivé\n3. Il disparaît de la liste principale mais reste accessible\n4. Un badge indique le nombre de projets archivés\n\n## Avantages :\n\n• Interface plus claire\n• Meilleures performances\n• Historique préservé\n• Recherche facilitée\n\nCette fonctionnalité est déjà active sur votre plateforme !', 'annonces', 'admin_1', 1);

-- Catégories dynamiques par défaut
INSERT INTO dynamic_categories (id, module, name, color, icon) VALUES 
('cat_blog_1', 'blog', 'Tutoriels', '#3b82f6', 'fas fa-graduation-cap'),
('cat_blog_2', 'blog', 'Règlements', '#ef4444', 'fas fa-gavel'),
('cat_blog_3', 'blog', 'Processus', '#f59e0b', 'fas fa-cogs'),
('cat_blog_4', 'blog', 'Guides', '#10b981', 'fas fa-book'),
('cat_blog_5', 'blog', 'Annonces', '#8b5cf6', 'fas fa-bullhorn'),
('cat_finance_1', 'finance', 'Service', '#06b6d4', 'fas fa-handshake'),
('cat_finance_2', 'finance', 'Produit', '#84cc16', 'fas fa-box'),
('cat_finance_3', 'finance', 'Frais', '#f97316', 'fas fa-receipt'),
('cat_finance_4', 'finance', 'Marketing', '#ec4899', 'fas fa-bullhorn'),
('cat_finance_5', 'finance', 'Équipement', '#6b7280', 'fas fa-desktop');

-- Catégories de produits par défaut
INSERT INTO product_categories (id, name, description, color, icon, created_by) VALUES 
('prod_cat_1', 'Services Digitaux', 'Sites web, applications, développement', '#3b82f6', 'fas fa-code', 'admin_1'),
('prod_cat_2', 'Consulting', 'Conseil, formation, audit', '#10b981', 'fas fa-users', 'admin_1'),
('prod_cat_3', 'Design & Branding', 'Logo, identité visuelle, supports', '#f59e0b', 'fas fa-palette', 'admin_1'),
('prod_cat_4', 'Marketing Digital', 'SEO, publicité, réseaux sociaux', '#ec4899', 'fas fa-chart-line', 'admin_1'),
('prod_cat_5', 'Hébergement & Domaines', 'Serveurs, noms de domaine, SSL', '#6b7280', 'fas fa-server', 'admin_1');

-- Produits/services par défaut
INSERT INTO products (id, name, description, sku, category_id, price, cost_price, stock_quantity, unit, created_by) VALUES 
('prod_1', 'Site Web Vitrine', 'Site web responsive avec CMS', 'WEB-VIT-001', 'prod_cat_1', 1500.00, 800.00, 999, 'projet', 'admin_1'),
('prod_2', 'Application Web', 'Application web sur mesure', 'WEB-APP-001', 'prod_cat_1', 5000.00, 2500.00, 999, 'projet', 'admin_1'),
('prod_3', 'Logo + Charte Graphique', 'Création logo et identité visuelle', 'DES-LOG-001', 'prod_cat_3', 800.00, 300.00, 999, 'projet', 'admin_1'),
('prod_4', 'Audit SEO Complet', 'Analyse et recommandations SEO', 'MKT-SEO-001', 'prod_cat_4', 400.00, 150.00, 999, 'service', 'admin_1'),
('prod_5', 'Formation WordPress', 'Formation utilisation WordPress', 'CON-WP-001', 'prod_cat_2', 250.00, 100.00, 999, 'heure', 'admin_1');

-- Préférences utilisateur par défaut
INSERT INTO user_preferences (user_id, theme, notifications_enabled, sound_enabled) VALUES 
('admin_1', 'light', TRUE, TRUE);