<?php
require_once 'config.php';

$currentPage = $_GET['page'] ?? 'dashboard';
$pageTitle = 'MyBizPanel';

// Définition des titres de pages
$pageTitles = [
    'dashboard' => 'Tableau de Bord',
    'projects' => 'Projets',
    'tasks' => 'Tâches',
    'notes' => 'Notes',
    'finances' => 'Finances',
    'clients' => 'Clients',
    'messages' => 'Messages',
    'blog' => 'Blog',
    'inventory' => 'Inventaire',
    'analytics' => 'Analytics',
    'admin' => 'Administration',
    'admin-advanced' => 'Administration Avancée',
    'settings' => 'Paramètres',
    'login' => 'Connexion'
];

// Récupération des préférences utilisateur pour le thème
$userTheme = 'light';
$userPreferences = ['theme' => 'light', 'notifications_enabled' => true, 'sound_enabled' => true];

if (isset($_SESSION['user_id'])) {
    try {
        // Récupération simple des préférences depuis la base
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        if ($result) {
            $userTheme = $result['theme'] ?? 'light';
            $userPreferences = $result;
        } else {
            // Créer des préférences par défaut si elles n'existent pas
            $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme, notifications_enabled, sound_enabled) VALUES (?, 'light', 1, 1)");
            $stmt->execute([$_SESSION['user_id']]);
        }
    } catch (Exception $e) {
        // En cas d'erreur (table n'existe pas), utiliser les valeurs par défaut
        $userTheme = 'light';
        error_log("Erreur préférences utilisateur: " . $e->getMessage());
    }
}

if (isset($pageTitles[$currentPage])) {
    $pageTitle = $pageTitles[$currentPage] . ' - MyBizPanel';
}
?>
<!DOCTYPE html>
<html lang="fr" class="<?= $userTheme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            100: '#1f2937',
                            200: '#374151',
                            300: '#4b5563',
                            400: '#6b7280',
                            500: '#9ca3af',
                            600: '#d1d5db',
                            700: '#e5e7eb',
                            800: '#f3f4f6',
                            900: '#f9fafb'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #f3f4f6;
            --accent: #fbbf24;
        }
        
        .dark {
            --primary: #a855f7;
            --primary-dark: #9333ea;
            --secondary: #374151;
            --accent: #fbbf24;
        }
        .btn-primary {
            background-color: var(--primary);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }
        /* Mode clair */
        body {
            background-color: #f8fafc;
            color: #1f2937;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        
        /* Mode sombre - Arrière-plan NOIR */
        .dark body {
            background-color: #000000 !important;
            color: #e5e7eb;
        }
        
        .dark .card {
            background: #1a1a1a !important;
            border-color: #333333;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.6);
            color: #e5e7eb;
        }
        
        /* Sidebar en mode sombre */
        .dark #sidebar {
            background-color: #111111 !important;
            border-color: #333333;
        }
        
        /* Header en mode sombre */
        .dark .bg-white {
            background-color: #1a1a1a !important;
        }
        
        /* Textes en mode sombre */
        .dark .text-gray-900 {
            color: #e5e7eb !important;
        }
        
        .dark .text-gray-700 {
            color: #d1d5db !important;
        }
        
        .dark .text-gray-600 {
            color: #9ca3af !important;
        }
        
        .dark .text-gray-500 {
            color: #6b7280 !important;
        }
        .nav-link {
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background-color: #f3f4f6;
            transform: translateX(4px);
        }
        .nav-link.active {
            background-color: var(--primary);
            color: white;
        }
        /* Supprimé - Mode sombre défini plus haut */
        .main-content {
            background: transparent;
        }
        
        /* Navigation links en mode sombre */
        .dark .nav-link {
            color: #d1d5db;
        }
        
        .dark .nav-link:hover {
            background-color: #2d3748 !important;
            color: #e5e7eb;
        }
        
        .dark .nav-link.active {
            background-color: var(--primary) !important;
            color: white !important;
        }
        
        /* Formulaires en mode sombre */
        .dark input, .dark select, .dark textarea {
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
            color: #e5e7eb !important;
        }
        
        .dark input:focus, .dark select:focus, .dark textarea:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 1px var(--primary) !important;
        }
        
        /* Tables en mode sombre */
        .dark table {
            background-color: #1a1a1a !important;
        }
        
        .dark th {
            background-color: #2d3748 !important;
            color: #e5e7eb !important;
        }
        
        .dark td {
            border-color: #4a5568 !important;
            color: #d1d5db !important;
        }
        
        /* Boutons en mode sombre */
        .dark .btn-secondary {
            background-color: #4a5568 !important;
            color: #e5e7eb !important;
            border-color: #6b7280 !important;
        }
        
        .dark .btn-secondary:hover {
            background-color: #6b7280 !important;
        }
        
        /* Section utilisateur en mode sombre */
        .dark .bg-gray-50 {
            background-color: #0d1117 !important;
        }
        
        /* Header en mode sombre */
        .dark .border-b {
            border-color: #333333 !important;
        }
        
        .dark .shadow-sm {
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.5) !important;
        }
        
        /* Mode sombre pour les éléments spéciaux */
        .dark .bg-gradient-to-r {
            background: linear-gradient(135deg, #a855f7, #9333ea) !important;
        }
        
        /* Amélioration des contrastes en mode sombre */
        .dark h1, .dark h2, .dark h3, .dark h4, .dark h5, .dark h6 {
            color: #f3f4f6 !important;
        }
        
        .dark p {
            color: #d1d5db !important;
        }
        
        .dark .text-xs {
            color: #9ca3af !important;
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        
        .notification-dot {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .online-indicator {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .offline-indicator {
            width: 8px;
            height: 8px;
            background: #6b7280;
            border-radius: 50%;
        }
        
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            padding: 16px;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .dark .notification-toast {
            background: #1f2937;
            border-color: #374151;
        }
        
        .notification-toast.show {
            transform: translateX(0);
        }
        
        .dark-mode-toggle {
            padding: 8px;
            border-radius: 8px;
            background: #f3f4f6;
            color: #6b7280;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .dark .dark-mode-toggle {
            background: #374151;
            color: #d1d5db;
        }
        
        .dark-mode-toggle:hover {
            background: #e5e7eb;
        }
        
        .dark .dark-mode-toggle:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="flex min-h-screen">
        <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white shadow-lg">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-violet-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">
                            M
                        </div>
                        <div class="ml-3">
                            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200">MyBizPanel</h1>
                            <p class="text-xs text-gray-500 dark:text-gray-400">By Neelps</p>
                        </div>
                    </div>
                    
                    <!-- Contrôles utilisateur -->
                    <div class="flex items-center space-x-2">
                        <!-- Statut en ligne -->
                        <div class="flex items-center" id="online-status">
                            <div class="online-indicator"></div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">En ligne</span>
                        </div>
                        
                        <!-- Toggle mode sombre -->
                        <button onclick="toggleDarkMode()" class="dark-mode-toggle" title="Basculer le mode sombre">
                            <i class="fas fa-moon dark:hidden"></i>
                            <i class="fas fa-sun hidden dark:inline"></i>
                        </button>
                        
                        <!-- Notifications -->
                        <div class="relative">
                            <button onclick="toggleNotifications()" class="dark-mode-toggle relative">
                                <i class="fas fa-bell"></i>
                                <span id="notification-count" class="notification-dot hidden">0</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <nav class="mt-6">
                <div class="px-6 py-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Navigation</p>
                </div>
                
                <div class="mt-4 space-y-1 px-3">
                    <a href="?page=dashboard" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'dashboard' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-chart-pie mr-3"></i>
                        Tableau de Bord
                    </a>
                    <a href="?page=projects" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'projects' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-project-diagram mr-3"></i>
                        Projets
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM projects WHERE is_archived = 1");
                            $archivedCount = $stmt->fetchColumn();
                            if ($archivedCount > 0): ?>
                            <span class="ml-auto bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full"><?= $archivedCount ?></span>
                        <?php endif; } catch (Exception $e) {} ?>
                    </a>
                    <a href="?page=tasks" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'tasks' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-tasks mr-3"></i>
                        Tâches
                    </a>
                    <a href="?page=notes" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'notes' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-sticky-note mr-3"></i>
                        Notes
                    </a>
                    <a href="?page=finances" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'finances' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-euro-sign mr-3"></i>
                        Finances
                    </a>
                    <a href="?page=clients" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'clients' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-users mr-3"></i>
                        Clients
                    </a>
                </div>
                
                <div class="px-6 py-2 mt-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Communication</p>
                </div>
                
                <div class="mt-4 space-y-1 px-3">
                    <a href="?page=messages" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'messages' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-comments mr-3"></i>
                        Messages
                    </a>
                    <a href="?page=blog" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'blog' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-blog mr-3"></i>
                        Blog
                    </a>
                </div>
                
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="px-6 py-2 mt-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Administration</p>
                </div>
                
                <div class="mt-4 space-y-1 px-3">
                    <a href="?page=inventory" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'inventory' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-warehouse mr-3"></i>
                        Inventaire
                    </a>
                    <a href="?page=analytics" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'analytics' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-chart-bar mr-3"></i>
                        Analytics
                    </a>
                    <a href="?page=admin" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'admin' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-users mr-3"></i>
                        Gestion Utilisateurs
                    </a>
                    <a href="?page=admin-advanced" class="nav-link flex items-center px-3 py-2 rounded-lg <?= $currentPage === 'admin-advanced' ? 'active' : 'text-gray-700' ?>">
                        <i class="fas fa-cogs mr-3"></i>
                        Admin Avancé
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            
            <!-- User info -->
            <div class="absolute bottom-0 w-64 p-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-violet-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                        <?= strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-700">
                            <?= sanitizeOutput($_SESSION['first_name'] ?? 'Utilisateur') ?>
                        </p>
                        <p class="text-xs text-gray-500"><?= sanitizeOutput($_SESSION['role'] ?? 'employee') ?></p>
                    </div>
                    <a href="?page=logout" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <div class="flex-1 <?= isset($_SESSION['user_id']) ? 'main-content' : '' ?>">
            <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Header -->
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">
                            <?= $pageTitles[$currentPage] ?? 'MyBizPanel' ?>
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?= date('d F Y') ?> • Bonjour <?= sanitizeOutput($_SESSION['first_name'] ?? 'Utilisateur') ?>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="window.location.reload()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="?page=settings" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-cog"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="p-6">
            <?php endif; ?>
            
                <?php
                // Inclusion des pages
                switch ($currentPage) {
                    case 'login':
                        include 'pages/login.php';
                        break;
                    case 'logout':
                        session_destroy();
                        header('Location: ?page=login');
                        exit;
                        break;
                    case 'dashboard':
                        include 'pages/dashboard.php';
                        break;
                    case 'projects':
                        include 'pages/projects.php';
                        break;
                    case 'tasks':
                        include 'pages/tasks.php';
                        break;
                    case 'notes':
                        include 'pages/notes.php';
                        break;
                    case 'finances':
                        include 'pages/finances.php';
                        break;
                    case 'clients':
                        include 'pages/clients.php';
                        break;
                    case 'messages':
                        include 'pages/messages.php';
                        break;
                    case 'blog':
                        include 'pages/blog.php';
                        break;
                    case 'inventory':
                        include 'pages/inventory.php';
                        break;
                    case 'analytics':
                        include 'pages/analytics.php';
                        break;
                    case 'admin':
                        requireAdmin();
                        include 'pages/admin.php';
                        break;
                    case 'admin-advanced':
                        requireAdmin();
                        include 'pages/admin-advanced.php';
                        break;
                    case 'settings':
                        include 'pages/settings.php';
                        break;
                    default:
                        include 'pages/dashboard.php';
                }
                ?>
                
            <?php if (isset($_SESSION['user_id'])): ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Panneau de notifications (caché par défaut) -->
    <div id="notification-panel" style="display: none;"></div>

    <!-- JavaScript global avec fonctionnalités avancées -->
    <script>
        // === VARIABLES GLOBALES ===
        let notificationPanel = null;
        let unreadNotifications = [];

        // === SYSTÈME DE MODE SOMBRE ===
        function toggleDarkMode() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            
            if (isDark) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                updateUserPreference('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                updateUserPreference('theme', 'dark');
            }
            
            logUserAction('theme_toggle', isDark ? 'light' : 'dark');
        }

        // === SYSTÈME DE NOTIFICATIONS TEMPS RÉEL ===
        function toggleNotifications() {
            if (notificationPanel && notificationPanel.style.display === 'block') {
                hideNotifications();
            } else {
                showNotifications();
            }
            logUserAction('notifications_toggle', 'toggle');
        }

        function showNotifications() {
            if (!notificationPanel) {
                createNotificationPanel();
            }
            
            notificationPanel.style.display = 'block';
            loadNotifications();
        }

        function hideNotifications() {
            if (notificationPanel) {
                notificationPanel.style.display = 'none';
            }
        }

        function createNotificationPanel() {
            notificationPanel = document.createElement('div');
            notificationPanel.innerHTML = `
                <div class="fixed top-16 right-4 w-80 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl z-50">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Notifications</h3>
                            <button onclick="markAllAsRead()" class="text-sm text-blue-600 hover:text-blue-800">
                                Tout marquer comme lu
                            </button>
                        </div>
                    </div>
                    <div id="notification-list" class="max-h-64 overflow-y-auto">
                        <div class="p-4 text-center text-gray-500">
                            <i class="fas fa-bell text-2xl mb-2"></i>
                            <p>Chargement des notifications...</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(notificationPanel);
            
            // Fermer en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                if (notificationPanel && !notificationPanel.contains(e.target) && !e.target.closest('[onclick*="toggleNotifications"]')) {
                    hideNotifications();
                }
            });
        }

        async function loadNotifications() {
            try {
                const response = await fetch('api/notifications.php?action=unread');
                const notifications = await response.json();
                
                const listElement = document.getElementById('notification-list');
                if (notifications.length === 0) {
                    listElement.innerHTML = `
                        <div class="p-4 text-center text-gray-500">
                            <i class="fas fa-check-circle text-2xl mb-2"></i>
                            <p>Aucune nouvelle notification</p>
                        </div>
                    `;
                } else {
                    listElement.innerHTML = notifications.map(notif => `
                        <div class="p-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div class="flex items-start">
                                <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mr-3">
                                    <i class="fas fa-${getNotificationIcon(notif.type)} text-blue-600 dark:text-blue-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-sm text-gray-900 dark:text-gray-100">${notif.title}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">${notif.message}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">${formatDate(notif.created_at)}</div>
                                </div>
                                <button onclick="markAsRead('${notif.id}')" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
                
                updateNotificationCount(notifications.length);
            } catch (error) {
                console.error('Erreur chargement notifications:', error);
                document.getElementById('notification-list').innerHTML = `
                    <div class="p-4 text-center text-red-500">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <p>Erreur de chargement</p>
                    </div>
                `;
            }
        }

        function getNotificationIcon(type) {
            const icons = {
                'message': 'comment',
                'project': 'project-diagram', 
                'task': 'tasks',
                'finance': 'euro-sign',
                'system': 'cog',
                'user': 'user',
                'warning': 'exclamation-triangle',
                'success': 'check-circle'
            };
            return icons[type] || 'bell';
        }

        function updateNotificationCount(count) {
            const badge = document.getElementById('notification-count');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }

        async function markAsRead(notificationId) {
            try {
                await fetch('api/notifications.php?action=mark_read', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({notification_id: notificationId})
                });
                loadNotifications();
            } catch (error) {
                console.error('Erreur marquage notification:', error);
            }
        }

        async function markAllAsRead() {
            try {
                await fetch('api/notifications.php?action=mark_all_read', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
                loadNotifications();
            } catch (error) {
                console.error('Erreur marquage toutes notifications:', error);
            }
        }

        // === SYSTÈME DE STATUT EN LIGNE ===
        async function updateOnlineStatus() {
            try {
                const response = await fetch('api/notifications.php?action=online');
                const data = await response.json();
                
                const statusElement = document.getElementById('online-status');
                if (statusElement && data.online_users) {
                    const count = data.online_users.length;
                    const userExists = data.online_users.some(user => user.id == '<?= $_SESSION['user_id'] ?? '' ?>');
                    
                    statusElement.innerHTML = `
                        <div class="${userExists ? 'online-indicator' : 'offline-indicator'}"></div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                            ${userExists ? 'En ligne' : 'Hors ligne'} (${count})
                        </span>
                    `;
                }
            } catch (error) {
                console.error('Erreur statut en ligne:', error);
                const statusElement = document.getElementById('online-status');
                if (statusElement) {
                    statusElement.innerHTML = `
                        <div class="offline-indicator"></div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">Hors ligne</span>
                    `;
                }
            }
        }

        // === NOTIFICATIONS TOAST ===
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `notification-toast show`;
            
            const bgClass = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
            const iconClass = type === 'success' ? 'fa-check' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            toast.innerHTML = `
                <div class="flex items-center text-white ${bgClass} p-4 rounded-lg">
                    <i class="fas ${iconClass} mr-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function showAutoNotification(message, type = 'info') {
            const notification = document.createElement('div');
            
            let bgColor = 'bg-blue-500';
            let icon = 'fa-info-circle';
            
            switch(type) {
                case 'success': bgColor = 'bg-green-500'; icon = 'fa-check-circle'; break;
                case 'error': bgColor = 'bg-red-500'; icon = 'fa-exclamation-triangle'; break;
                case 'warning': bgColor = 'bg-yellow-500'; icon = 'fa-exclamation-triangle'; break;
            }
            
            notification.className = `notification-toast show`;
            notification.innerHTML = `
                <div class="flex items-center ${bgColor} text-white p-4 rounded-lg shadow-lg max-w-sm">
                    <i class="fas ${icon} mr-3"></i>
                    <div>
                        <div class="font-medium text-sm">Nouvelle activité</div>
                        <div class="text-sm opacity-90">${message}</div>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Son de notification si activé
            if (window.userPreferences && window.userPreferences.sound_enabled !== false) {
                playNotificationSound();
            }
            
            // Auto-disparition après 5 secondes
            setTimeout(() => {
                notification.classList.remove('show');
                notification.classList.add('hide');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }

        // === SON DE NOTIFICATION ===
        function playNotificationSound() {
            try {
                // Son de notification simple
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaBz6Y2u+7diMGJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaBz6Y2u+7diMGJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaBz6Y2u+7diMGJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaBz6Y2u+7diMGJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaBz6Y2u+7diMGJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaBz6Y2u+7diMG');
                audio.volume = 0.2;
                audio.play().catch(() => {}); // Ignorer les erreurs de lecture
            } catch (error) {
                // Ignorer les erreurs audio
            }
        }

        // === LOGGING DES ACTIONS UTILISATEUR ===
        function logUserAction(action, details = '', page = '<?= $currentPage ?>') {
            console.log(`[USER ACTION] ${action}: ${details} on ${page}`);
            
            // Envoi au serveur pour traçabilité
            fetch('api/log_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: action,
                    details: details,
                    page: page,
                    timestamp: new Date().toISOString()
                })
            }).catch(() => {}); // Ignorer les erreurs réseau
        }

        // === PRÉFÉRENCES UTILISATEUR ===
        async function updateUserPreference(key, value) {
            try {
                await fetch('api/notifications.php?action=preferences', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({[key]: value})
                });
            } catch (error) {
                console.error('Erreur mise à jour préférence:', error);
            }
        }

        // === FONCTIONS UTILITAIRES ===
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        }
        
        function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
            logUserAction('confirm_delete', message);
            return confirm(message);
        }
        
        function switchTab(activeTab, contentId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
            activeTab.classList.add('active');
            document.getElementById(contentId).style.display = 'block';
            logUserAction('tab_switch', contentId);
        }

        // === INITIALISATION ===
        document.addEventListener('DOMContentLoaded', function() {
            // Charger les préférences utilisateur
            <?php if (isset($_SESSION['user_id']) && $userPreferences): ?>
            window.userPreferences = <?= json_encode($userPreferences) ?>;
            <?php endif; ?>

            // Logging automatique des clics sur liens et boutons
            document.addEventListener('click', function(e) {
                const element = e.target.closest('a, button, [data-action]');
                if (element) {
                    const action = element.dataset.action || element.tagName.toLowerCase() + '_click';
                    const details = element.textContent?.trim() || element.title || element.href || element.className;
                    if (details && details.length > 0) {
                        logUserAction(action, details.substring(0, 100));
                    }
                }
            });

            <?php if (isset($_SESSION['user_id'])): ?>
            // Initialisation du système de notifications et statut
            updateOnlineStatus();
            loadNotifications();
            
            // Mise à jour périodique
            setInterval(updateOnlineStatus, 30000); // Statut toutes les 30 secondes
            setInterval(() => {
                if (document.visibilityState === 'visible') {
                    loadNotifications();
                }
            }, 60000); // Notifications toutes les minutes
            
            // Refresh automatique pour certaines pages sensibles
            <?php if (in_array($currentPage, ['dashboard', 'messages'])): ?>
            setInterval(() => {
                if (document.visibilityState === 'visible' && document.hasFocus()) {
                    // Refresh léger uniquement si l'utilisateur est actif
                    window.location.reload();
                }
            }, 300000); // 5 minutes
            <?php endif; ?>
            <?php endif; ?>
            
            // Initialiser le thème depuis le localStorage
            const savedTheme = localStorage.getItem('theme') || '<?= $userTheme ?>';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            }
            
            logUserAction('page_load', '<?= $currentPage ?>');
        });

        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                logUserAction('page_focus', '<?= $currentPage ?>');
                <?php if (isset($_SESSION['user_id'])): ?>
                updateOnlineStatus();
                <?php endif; ?>
            } else {
                logUserAction('page_blur', '<?= $currentPage ?>');
            }
        });

        // Détection de l'inactivité utilisateur
        let inactivityTimer;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                logUserAction('user_inactive', '5min_idle');
            }, 300000); // 5 minutes
        }

        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        resetInactivityTimer();
    </script>
</body>
</html>