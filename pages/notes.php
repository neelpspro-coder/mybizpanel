<?php
// Gestion des actions CRUD pour les notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (empty($_POST['title'])) {
                    $error = "Le titre est obligatoire";
                    break;
                }
                
                $id = generateId();
                $stmt = $pdo->prepare("
                    INSERT INTO notes (id, title, content, user_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    trim($_POST['title']),
                    trim($_POST['content']) ?: null,
                    $_SESSION['user_id']
                ]);
                
                logSystemEvent('info', "Nouvelle note créée : {$_POST['title']}", 'note_create');
                $success = "Note créée avec succès !";
                
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Nouvelle note créée : " . addslashes($_POST['title']) . "', 'success'); });</script>";
                break;
                
            case 'update':
                $stmt = $pdo->prepare("
                    UPDATE notes 
                    SET title = ?, content = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['content'],
                    $_POST['id']
                ]);
                
                logSystemEvent('info', "Note modifiée : {$_POST['title']}", 'note_update');
                $success = "Note modifiée avec succès !";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("SELECT title FROM notes WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $noteTitle = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                logSystemEvent('info', "Note supprimée : $noteTitle", 'note_delete');
                $success = "Note supprimée avec succès !";
                break;
        }
    }
}

// Récupération de la note à éditer
$editNote = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editNote = $stmt->fetch();
}

// Récupération des notes (PARTAGÉES - base de connaissances collaborative)
try {
    $searchTerm = $_GET['search'] ?? '';
    if ($searchTerm) {
        $stmt = $pdo->prepare("
            SELECT n.*, u.email as author_email 
            FROM notes n 
            LEFT JOIN users u ON n.user_id = u.id 
            WHERE n.title LIKE ? OR n.content LIKE ?
            ORDER BY n.updated_at DESC
        ");
        $stmt->execute(["%$searchTerm%", "%$searchTerm%"]);
    } else {
        $stmt = $pdo->prepare("
            SELECT n.*, u.email as author_email 
            FROM notes n 
            LEFT JOIN users u ON n.user_id = u.id 
            ORDER BY n.updated_at DESC
        ");
        $stmt->execute();
    }
    $notes = $stmt->fetchAll();
} catch (Exception $e) {
    $notes = [];
    $error = "Erreur lors de la récupération des notes : " . $e->getMessage();
}
?>

<div class="space-y-6">
    <!-- Messages -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?= sanitizeOutput($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?= sanitizeOutput($error) ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire nouveau/édition note -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'new' || $editNote): ?>
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">
            <?= $editNote ? 'Modifier la note' : 'Nouvelle note' ?>
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?= $editNote ? 'update' : 'create' ?>">
            <?php if ($editNote): ?>
            <input type="hidden" name="id" value="<?= $editNote['id'] ?>">
            <?php endif; ?>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Titre *</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                       value="<?= sanitizeOutput($editNote['title'] ?? '') ?>" placeholder="Titre de votre note">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contenu</label>
                <textarea name="content" rows="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                          placeholder="Écrivez votre note ici..."><?= sanitizeOutput($editNote['content'] ?? '') ?></textarea>
            </div>
            
            <div class="flex space-x-4">
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <i class="fas fa-save mr-2"></i>
                    <?= $editNote ? 'Modifier' : 'Créer' ?>
                </button>
                <a href="?page=notes" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">Annuler</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    
    <!-- Header avec recherche -->
    <div class="card p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h3 class="text-lg font-semibold">Base de Connaissances Partagée</h3>
                <p class="text-sm text-gray-600">Notes collaboratives de toute l'équipe</p>
            </div>
            
            <div class="flex space-x-4">
                <!-- Recherche -->
                <form method="GET" class="flex">
                    <input type="hidden" name="page" value="notes">
                    <input type="text" name="search" value="<?= sanitizeOutput($_GET['search'] ?? '') ?>" 
                           placeholder="Rechercher..." 
                           class="px-3 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-violet-500">
                    <button type="submit" class="btn-primary px-4 py-2 rounded-r-lg">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                
                <a href="?page=notes&action=new" class="btn-primary px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Nouvelle Note
                </a>
            </div>
        </div>
        
        <?php if (!empty($_GET['search'])): ?>
        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
            <p class="text-sm text-blue-700">
                <i class="fas fa-search mr-2"></i>
                Résultats pour : "<strong><?= sanitizeOutput($_GET['search']) ?></strong>"
                <a href="?page=notes" class="ml-3 text-blue-600 hover:text-blue-800">Effacer</a>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-sticky-note text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Total Notes</p>
                    <p class="text-2xl font-bold text-purple-600"><?= count($notes) ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Collaborateurs</p>
                    <p class="text-2xl font-bold text-blue-600">
                        <?= count(array_unique(array_column($notes, 'author_email'))) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Dernière Mise à Jour</p>
                    <p class="text-sm font-medium text-green-600">
                        <?= !empty($notes) ? date('d/m/Y', strtotime($notes[0]['updated_at'])) : 'Aucune' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des notes -->
    <?php if (empty($notes)): ?>
    <div class="card p-12">
        <div class="text-center text-gray-500">
            <i class="fas fa-sticky-note text-4xl mb-4"></i>
            <p class="text-lg">Aucune note trouvée</p>
            <?php if (!empty($_GET['search'])): ?>
            <p class="text-sm">Essayez d'autres mots-clés ou <a href="?page=notes" class="text-violet-600">voir toutes les notes</a></p>
            <?php else: ?>
            <p class="text-sm">Commencez par créer votre première note</p>
            <a href="?page=notes&action=new" class="mt-4 inline-block btn-primary px-6 py-2 rounded-lg">
                Créer la première note
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($notes as $note): ?>
        <div class="card p-6 hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-3">
                <h4 class="font-semibold text-gray-900 line-clamp-2"><?= sanitizeOutput($note['title']) ?></h4>
                <div class="flex space-x-2 ml-2">
                    <a href="?page=notes&edit=<?= $note['id'] ?>" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Supprimer cette note ?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $note['id'] ?>">
                        <button type="submit" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if ($note['content']): ?>
            <div class="text-gray-600 text-sm mb-4 line-clamp-4">
                <?= nl2br(sanitizeOutput(substr($note['content'], 0, 150))) ?>
                <?= strlen($note['content']) > 150 ? '...' : '' ?>
            </div>
            <?php endif; ?>
            
            <div class="flex items-center justify-between text-xs text-gray-500">
                <div class="flex items-center">
                    <div class="w-6 h-6 bg-violet-500 rounded-full flex items-center justify-center text-white text-xs mr-2">
                        <?= strtoupper(substr($note['author_email'], 0, 1)) ?>
                    </div>
                    <span><?= sanitizeOutput($note['author_email']) ?></span>
                </div>
                <span><?= date('d/m/Y H:i', strtotime($note['updated_at'])) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>