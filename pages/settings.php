<?php
// Gestion des paramètres utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        trim($_POST['first_name']) ?: null,
                        trim($_POST['last_name']) ?: null,
                        $_SESSION['user_id']
                    ]);
                    
                    // Mettre à jour la session
                    $_SESSION['first_name'] = trim($_POST['first_name']) ?: null;
                    $_SESSION['last_name'] = trim($_POST['last_name']) ?: null;
                    
                    logSystemEvent('info', "Profil utilisateur modifié", 'profile_update');
                    $success = "Profil mis à jour avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la mise à jour : " . $e->getMessage();
                }
                break;
                
            case 'change_password':
                if (empty($_POST['current_password']) || empty($_POST['new_password'])) {
                    $error = "Tous les champs sont obligatoires";
                    break;
                }
                
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    $error = "Les nouveaux mots de passe ne correspondent pas";
                    break;
                }
                
                if (strlen($_POST['new_password']) < 6) {
                    $error = "Le nouveau mot de passe doit contenir au moins 6 caractères";
                    break;
                }
                
                try {
                    // Vérifier le mot de passe actuel
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $currentHash = $stmt->fetchColumn();
                    
                    if (!password_verify($_POST['current_password'], $currentHash)) {
                        $error = "Mot de passe actuel incorrect";
                        break;
                    }
                    
                    // Mettre à jour le mot de passe
                    $newHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $_SESSION['user_id']]);
                    
                    logSystemEvent('info', "Mot de passe modifié", 'password_change');
                    $success = "Mot de passe modifié avec succès !";
                } catch (Exception $e) {
                    $error = "Erreur lors de la modification : " . $e->getMessage();
                }
                break;
        }
    }
}

// Récupération des informations utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
} catch (Exception $e) {
    $currentUser = null;
    $error = "Erreur lors de la récupération des informations";
}

// Statistiques personnelles
try {
    $userStats = [
        'projects_created' => $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?"),
        'tasks_created' => $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?"),
        'notes_created' => $pdo->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ?"),
        'transactions_created' => $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?"),
    ];
    
    foreach ($userStats as $key => $stmt) {
        $stmt->execute([$_SESSION['user_id']]);
        $userStats[$key] = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    $userStats = ['projects_created' => 0, 'tasks_created' => 0, 'notes_created' => 0, 'transactions_created' => 0];
}
?>

<div class="space-y-6">
    <!-- Messages -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Profil utilisateur -->
    <div class="card p-6">
        <div class="flex items-center mb-6">
            <div class="w-16 h-16 bg-gradient-to-r from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-2xl">
                <?= strtoupper(substr($currentUser['first_name'] ?: $currentUser['email'], 0, 1)) ?>
            </div>
            <div class="ml-4">
                <h2 class="text-2xl font-bold text-gray-900">
                    <?= htmlspecialchars(($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?: 'Nom non défini') ?>
                </h2>
                <p class="text-gray-600"><?= htmlspecialchars($currentUser['email']) ?></p>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-violet-100 text-violet-800">
                    <?= ucfirst($currentUser['role']) ?>
                </span>
            </div>
        </div>
        
        <!-- Statistiques personnelles -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?= $userStats['projects_created'] ?></div>
                <div class="text-sm text-blue-700">Projets créés</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?= $userStats['tasks_created'] ?></div>
                <div class="text-sm text-green-700">Tâches créées</div>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-2xl font-bold text-purple-600"><?= $userStats['notes_created'] ?></div>
                <div class="text-sm text-purple-700">Notes créées</div>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600"><?= $userStats['transactions_created'] ?></div>
                <div class="text-sm text-yellow-700">Transactions</div>
            </div>
        </div>
        
        <div class="text-sm text-gray-500">
            <p>Membre depuis le <?= date('d F Y', strtotime($currentUser['created_at'])) ?></p>
            <p>Dernière modification : <?= date('d F Y à H:i', strtotime($currentUser['updated_at'])) ?></p>
        </div>
    </div>

    <!-- Modification du profil -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">Informations personnelles</h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                    <input type="text" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= htmlspecialchars($currentUser['first_name'] ?: '') ?>" placeholder="Votre prénom">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                    <input type="text" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= htmlspecialchars($currentUser['last_name'] ?: '') ?>" placeholder="Votre nom">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    <p class="text-xs text-gray-500 mt-1">L'email ne peut pas être modifié</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                    <input type="text" value="<?= ucfirst($currentUser['role']) ?>" disabled 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                    <p class="text-xs text-gray-500 mt-1">Le rôle est défini par l'administrateur</p>
                </div>
            </div>
            
            <div>
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <i class="fas fa-save mr-2"></i>Sauvegarder le profil
                </button>
            </div>
        </form>
    </div>

    <!-- Modification du mot de passe -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">Sécurité</h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="change_password">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mot de passe actuel *</label>
                    <input type="password" name="current_password" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe *</label>
                    <input type="password" name="new_password" required minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 caractères</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirmer le nouveau mot de passe *</label>
                    <input type="password" name="confirm_password" required minlength="6"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
            
            <div>
                <button type="submit" class="bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700">
                    <i class="fas fa-key mr-2"></i>Changer le mot de passe
                </button>
            </div>
        </form>
    </div>

    <!-- Informations système -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">Informations système</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Application</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Nom :</span>
                        <span class="font-medium">MyBizPanel</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Version :</span>
                        <span class="font-medium">1.0.0</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Développé par :</span>
                        <span class="font-medium">Neelps</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Type :</span>
                        <span class="font-medium">Intranet collaboratif</span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Session</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Connecté depuis :</span>
                        <span class="font-medium">Cette session</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Navigateur :</span>
                        <span class="font-medium">Détecté automatiquement</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Langue :</span>
                        <span class="font-medium">Français</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Fuseau horaire :</span>
                        <span class="font-medium">Europe/Paris</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 pt-6 border-t">
            <div class="flex justify-between items-center">
                <div>
                    <h4 class="font-medium text-gray-900">Déconnexion</h4>
                    <p class="text-sm text-gray-600">Se déconnecter de votre session actuelle</p>
                </div>
                <a href="?page=logout" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                    <i class="fas fa-sign-out-alt mr-2"></i>Se déconnecter
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Validation des mots de passe en temps réel
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    if (newPassword && confirmPassword) {
        function validatePasswords() {
            if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
});
</script>