<?php
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                
                // Log de connexion (si la table existe)
                try {
                    $stmt2 = $pdo->prepare("INSERT INTO activity_logs (id, user_id, action, page, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt2->execute([uniqid('log_', true), $user['id'], 'login', 'login']);
                } catch (Exception $e) {
                    // Ignorer si la table n'existe pas encore
                }
                header('Location: ?page=dashboard');
                exit;
            } else {
                $error = "Email ou mot de passe incorrect";
            }
        } catch (Exception $e) {
            $error = "Erreur de connexion";
        }
    } else {
        $error = "Veuillez remplir tous les champs";
    }
}
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-violet-500 to-purple-700">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <!-- Logo et titre -->
            <div class="text-center mb-8">
                <div class="mx-auto w-16 h-16 bg-gradient-to-r from-violet-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-2xl mb-4">
                    M
                </div>
                <h2 class="text-3xl font-bold text-gray-900">MyBizPanel</h2>
                <p class="text-gray-600 mt-2">Connectez-vous à votre espace</p>
                <p class="text-xs text-gray-400 mt-1">By Neelps</p>
            </div>

            <!-- Message d'erreur -->
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= sanitizeOutput($error) ?>
            </div>
            <?php endif; ?>

            <!-- Formulaire de connexion -->
            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2 text-violet-500"></i>
                        Adresse email
                    </label>
                    <input type="email" id="email" name="email" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all"
                           placeholder="votre@email.com"
                           value="<?= sanitizeOutput($_POST['email'] ?? '') ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-violet-500"></i>
                        Mot de passe
                    </label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all"
                           placeholder="••••••••">
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-violet-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-violet-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-200 shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Se connecter
                </button>
            </form>

            <!-- Informations de test -->
            <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600 font-medium mb-2">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                    Compte de test
                </p>
                <p class="text-xs text-gray-500">
                    Email: admin@mybizpanel.com<br>
                    Mot de passe: password
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center text-white/80">
            <p class="text-sm">© 2025 MyBizPanel - Système de gestion collaborative</p>
        </div>
    </div>
</div>