<?php
// Inclure le système BB Code
require_once 'includes/bbcode.php';

// Gestion des actions pour les articles de blog
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                try {
                    $stmt = $pdo->prepare("INSERT INTO blog_posts (id, title, content, category, author_id, is_published) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        generateId(),
                        trim($_POST['title']),
                        trim($_POST['content']),
                        $_POST['category'],
                        $_SESSION['user_id'],
                        isset($_POST['is_published']) ? 1 : 0
                    ]);
                    $_SESSION['flash_success'] = "Article créé avec succès !";
                    header('Location: ?page=blog');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la création de l'article";
                    header('Location: ?page=blog');  
                    exit;
                }
                break;
                
            case 'edit':
                try {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ?, category = ?, is_published = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([
                        trim($_POST['title']),
                        trim($_POST['content']),
                        $_POST['category'],
                        isset($_POST['is_published']) ? 1 : 0,
                        $_POST['post_id']
                    ]);
                    $_SESSION['flash_success'] = "Article modifié avec succès !";
                    header('Location: ?page=blog');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la modification";
                    header('Location: ?page=blog');
                    exit;
                }
                break;
                
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                    $stmt->execute([$_POST['post_id']]);
                    $_SESSION['flash_success'] = "Article supprimé !";
                    header('Location: ?page=blog');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la suppression";
                    header('Location: ?page=blog');
                    exit;
                }
                break;
        }
    }
}

// Gestion de l'affichage d'un article spécifique
$viewPost = null;
$editPost = null;
if (isset($_GET['view'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT bp.*, u.first_name, u.last_name, u.email as author_email 
            FROM blog_posts bp 
            LEFT JOIN users u ON bp.author_id = u.id 
            WHERE bp.id = ? AND bp.is_published = 1
        ");
        $stmt->execute([$_GET['view']]);
        $viewPost = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Article non trouvé";
    }
}

if (isset($_GET['edit']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'support')) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editPost = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Article non trouvé";
    }
}

// Récupération des catégories dynamiques pour le blog
$blogCategories = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM dynamic_categories WHERE module = 'blog' AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $blogCategories = $stmt->fetchAll();
} catch (Exception $e) {
    // Utiliser des catégories par défaut si la table n'existe pas
    $blogCategories = [
        ['id' => 'tutoriels', 'name' => 'Tutoriels', 'color' => '#3b82f6', 'icon' => 'fas fa-graduation-cap'],
        ['id' => 'reglements', 'name' => 'Règlements', 'color' => '#ef4444', 'icon' => 'fas fa-gavel'],
        ['id' => 'processus', 'name' => 'Processus', 'color' => '#10b981', 'icon' => 'fas fa-cogs'],
        ['id' => 'guides', 'name' => 'Guides', 'color' => '#f59e0b', 'icon' => 'fas fa-book'],
        ['id' => 'annonces', 'name' => 'Annonces', 'color' => '#8b5cf6', 'icon' => 'fas fa-bullhorn']
    ];
}

// Gestion des messages flash
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Filtrage par catégorie
$selectedCategory = $_GET['category'] ?? 'all';
$categoryFilter = $selectedCategory !== 'all' ? "AND category = ?" : "";

// Récupération des articles de blog
try {
    $sql = "
        SELECT bp.*, u.first_name, u.last_name, u.email as author_email 
        FROM blog_posts bp 
        LEFT JOIN users u ON bp.author_id = u.id 
        WHERE bp.is_published = 1 $categoryFilter
        ORDER BY bp.created_at DESC 
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    
    if ($selectedCategory !== 'all') {
        $stmt->execute([$selectedCategory]);
    } else {
        $stmt->execute();
    }
    
    $blogPosts = $stmt->fetchAll();
} catch (Exception $e) {
    $blogPosts = [];
    $error = "Erreur lors de la récupération des articles";
}

// Statistiques
$totalPosts = count($blogPosts);
$todayPosts = count(array_filter($blogPosts, fn($p) => date('Y-m-d', strtotime($p['created_at'])) === date('Y-m-d')));
?>

<?php if ($viewPost): ?>
<!-- Affichage d'un article spécifique -->
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="?page=blog" class="btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>
            Retour au blog
        </a>
        
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'support'): ?>
        <a href="?page=blog&edit=<?= $viewPost['id'] ?>" class="btn-primary">
            <i class="fas fa-edit mr-2"></i>
            Modifier
        </a>
        <?php endif; ?>
    </div>

    <article class="card">
        <div class="p-6">
            <div class="mb-4">
                <span class="inline-block px-3 py-1 text-xs font-medium bg-violet-100 text-violet-800 rounded-full">
                    <?= ucfirst($viewPost['category']) ?>
                </span>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($viewPost['title']) ?></h1>
            
            <div class="flex items-center text-sm text-gray-500 mb-6">
                <div class="w-8 h-8 bg-gradient-to-r from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                    <?= strtoupper(substr($viewPost['author_email'], 0, 1)) ?>
                </div>
                <div>
                    <span class="font-medium">
                        <?= htmlspecialchars($viewPost['first_name'] . ' ' . $viewPost['last_name']) ?>
                    </span>
                    <span class="mx-2">•</span>
                    <time><?= date('d/m/Y à H:i', strtotime($viewPost['created_at'])) ?></time>
                    <?php if ($viewPost['updated_at'] !== $viewPost['created_at']): ?>
                    <span class="mx-2">•</span>
                    <span class="italic">Modifié le <?= date('d/m/Y', strtotime($viewPost['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="prose max-w-none bbcode-content">
                <?= parseBBCode($viewPost['content']) ?>
            </div>
        </div>
    </article>
</div>

<?php elseif ($editPost): ?>
<!-- Formulaire d'édition -->
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Modifier l'article</h1>
        <a href="?page=blog" class="btn-secondary">
            <i class="fas fa-times mr-2"></i>
            Annuler
        </a>
    </div>

    <div class="card">
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="post_id" value="<?= $editPost['id'] ?>">
                
                <div>
                    <label class="form-label">Titre de l'article</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editPost['title']) ?>" class="form-input" required>
                </div>
                
                <div>
                    <label class="form-label">Catégorie</label>
                    <select name="category" class="form-input" required>
                        <?php if (empty($blogCategories)): ?>
                        <option value="tutoriels" <?= $editPost['category'] === 'tutoriels' ? 'selected' : '' ?>>📚 Tutoriels</option>
                        <option value="reglements" <?= $editPost['category'] === 'reglements' ? 'selected' : '' ?>>📋 Règlements</option>
                        <option value="processus" <?= $editPost['category'] === 'processus' ? 'selected' : '' ?>>⚙️ Processus</option>
                        <option value="guides" <?= $editPost['category'] === 'guides' ? 'selected' : '' ?>>📖 Guides</option>
                        <option value="annonces" <?= $editPost['category'] === 'annonces' ? 'selected' : '' ?>>📢 Annonces</option>
                        <?php else: ?>
                        <?php foreach ($blogCategories as $category): ?>
                        <option value="<?= $category['name'] ?>" <?= $editPost['category'] === $category['name'] ? 'selected' : '' ?>>
                            <i class="<?= $category['icon'] ?>"></i> <?= $category['name'] ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Contenu avec BB Code</label>
                    <div class="mb-2">
                        <button type="button" onclick="toggleBBCodeGuide()" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Guide BB Code
                        </button>
                    </div>
                    <textarea name="content" rows="15" class="form-input" required><?= htmlspecialchars($editPost['content']) ?></textarea>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_published" id="is_published" <?= $editPost['is_published'] ? 'checked' : '' ?> class="mr-2">
                    <label for="is_published" class="text-sm">Publier l'article</label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-2"></i>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des articles et gestion -->
<div class="space-y-6">
    <!-- Messages d'état -->
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

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-blog mr-3 text-violet-600"></i>
                Centre de Ressources - Blog Interne
            </h1>
            <p class="text-gray-600 mt-1">Tutoriels, guides, règlements et processus de l'entreprise</p>
        </div>
        
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'support'): ?>
        <button onclick="toggleForm()" class="btn-primary">
            <i class="fas fa-plus mr-2"></i>
            Nouvel Article
        </button>
        <?php endif; ?>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-newspaper text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Articles Total</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $totalPosts ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Aujourd'hui</p>
                    <p class="text-2xl font-bold text-green-600"><?= $todayPosts ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-tags text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Catégories</p>
                    <p class="text-2xl font-bold text-purple-600"><?= count($blogCategories) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres par catégorie -->
    <div class="card p-4">
        <div class="flex flex-wrap gap-2">
            <a href="?page=blog&category=all" class="filter-btn <?= $selectedCategory === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe mr-2"></i>
                Toutes les catégories
            </a>
            <?php if (empty($blogCategories)): ?>
            <a href="?page=blog&category=tutoriels" class="filter-btn <?= $selectedCategory === 'tutoriels' ? 'active' : '' ?>">
                📚 Tutoriels
            </a>
            <a href="?page=blog&category=reglements" class="filter-btn <?= $selectedCategory === 'reglements' ? 'active' : '' ?>">
                📋 Règlements
            </a>
            <a href="?page=blog&category=processus" class="filter-btn <?= $selectedCategory === 'processus' ? 'active' : '' ?>">
                ⚙️ Processus
            </a>
            <a href="?page=blog&category=guides" class="filter-btn <?= $selectedCategory === 'guides' ? 'active' : '' ?>">
                📖 Guides
            </a>
            <a href="?page=blog&category=annonces" class="filter-btn <?= $selectedCategory === 'annonces' ? 'active' : '' ?>">
                📢 Annonces
            </a>
            <?php else: ?>
            <?php foreach ($blogCategories as $category): ?>
            <a href="?page=blog&category=<?= urlencode($category['name']) ?>" 
               class="filter-btn <?= $selectedCategory === $category['name'] ? 'active' : '' ?>"
               style="background-color: <?= $category['color'] ?>15; border-color: <?= $category['color'] ?>; color: <?= $category['color'] ?>;">
                <i class="<?= $category['icon'] ?> mr-2"></i>
                <?= htmlspecialchars($category['name']) ?>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulaire de création (masqué par défaut) -->
    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'support'): ?>
    <div id="create-form" class="card" style="display: none;">
        <div class="p-6">
            <h3 class="text-lg font-bold mb-4">Créer un nouvel article</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create">
                
                <div>
                    <label class="form-label">Titre de l'article</label>
                    <input type="text" name="title" class="form-input" required 
                           placeholder="Ex: Comment envoyer un message sur le chat">
                </div>
                
                <div>
                    <label class="form-label">Catégorie</label>
                    <select name="category" class="form-input" required>
                        <option value="">Choisir une catégorie</option>
                        <?php if (empty($blogCategories)): ?>
                        <option value="tutoriels">📚 Tutoriels</option>
                        <option value="reglements">📋 Règlements</option>
                        <option value="processus">⚙️ Processus</option>
                        <option value="guides">📖 Guides</option>
                        <option value="annonces">📢 Annonces</option>
                        <?php else: ?>
                        <?php foreach ($blogCategories as $category): ?>
                        <option value="<?= $category['name'] ?>">
                            <i class="<?= $category['icon'] ?>"></i> <?= $category['name'] ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Contenu avec BB Code</label>
                    <div class="mb-2">
                        <button type="button" onclick="toggleBBCodeGuide()" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Guide BB Code
                        </button>
                    </div>
                    <textarea name="content" rows="12" class="form-input" required 
                              placeholder="Rédigez votre article avec les codes BB pour le formatage...

Exemples :
[b]Gras[/b] - [i]Italique[/i] - [u]Souligné[/u]
[color=rouge]Texte rouge[/color]
[info]Information importante[/info]
[code]Code exemple[/code]
[list][*]Point 1[*]Point 2[/list]"></textarea>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_published" id="is_published" checked class="mr-2">
                    <label for="is_published" class="text-sm">Publier immédiatement</label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleForm()" class="btn-secondary">Annuler</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-2"></i>
                        Créer l'article
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Liste des articles -->
    <div class="space-y-4">
        <?php if (empty($blogPosts)): ?>
        <div class="card p-8 text-center text-gray-500">
            <i class="fas fa-blog text-4xl mb-4"></i>
            <p class="text-lg">Aucun article publié</p>
            <p class="text-sm">
                <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'support'): ?>
                Créez le premier article pour commencer le blog !
                <?php else: ?>
                Les administrateurs n'ont pas encore publié d'articles.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
            <?php foreach ($blogPosts as $post): ?>
            <article class="card hover:shadow-lg transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="mb-3">
                                <span class="inline-block px-2 py-1 text-xs font-medium bg-violet-100 text-violet-800 rounded">
                                    <?= ucfirst($post['category']) ?>
                                </span>
                            </div>
                            
                            <h2 class="text-xl font-bold text-gray-900 mb-2">
                                <a href="?page=blog&view=<?= $post['id'] ?>" class="hover:text-violet-600">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                            </h2>
                            
                            <div class="text-gray-600 mb-4 line-clamp-3 bbcode-content">
                                <?= substr(strip_tags(parseBBCode($post['content'])), 0, 200) ?>
                                <?= strlen($post['content']) > 200 ? '...' : '' ?>
                            </div>
                            
                            <div class="flex items-center text-sm text-gray-500">
                                <div class="w-6 h-6 bg-gradient-to-r from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-medium mr-2">
                                    <?= strtoupper(substr($post['author_email'], 0, 1)) ?>
                                </div>
                                <span class="font-medium">
                                    <?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?>
                                </span>
                                <span class="mx-2">•</span>
                                <time><?= date('d/m/Y', strtotime($post['created_at'])) ?></time>
                            </div>
                        </div>
                        
                        <div class="ml-4 flex items-center space-x-2">
                            <a href="?page=blog&view=<?= $post['id'] ?>" class="btn-secondary btn-sm">
                                <i class="fas fa-eye mr-1"></i>
                                Lire
                            </a>
                            
                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'support'): ?>
                            <a href="?page=blog&edit=<?= $post['id'] ?>" class="btn-primary btn-sm">
                                <i class="fas fa-edit mr-1"></i>
                                Éditer
                            </a>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cet article ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit" class="btn-secondary btn-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.filter-btn {
    @apply px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors;
}
.filter-btn.active {
    @apply bg-violet-600 text-white border-violet-600;
}
.btn-sm {
    @apply px-3 py-1 text-sm;
}
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Styles BB Code */
.bbcode-content {
    word-wrap: break-word;
}

.bbcode-content pre {
    max-width: 100%;
    overflow-x: auto;
}

.bbcode-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.bbcode-guide {
    max-height: 400px;
    overflow-y: auto;
}

.bbcode-example {
    font-family: monospace;
    background: #f3f4f6;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>

<script>
function toggleForm() {
    const form = document.getElementById('create-form');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}

function toggleBBCodeGuide() {
    const guide = document.getElementById('bbcode-guide');
    guide.style.display = guide.style.display === 'none' ? 'block' : 'none';
}

// Fermer le guide en cliquant à l'extérieur
window.onclick = function(event) {
    const guide = document.getElementById('bbcode-guide');
    if (event.target === guide) {
        guide.style.display = 'none';
    }
}

// Auto-scroll pour les formulaires
if (window.location.hash === '#create') {
    toggleForm();
}
</script>

<!-- Guide BB Code (masqué par défaut) -->
<div id="bbcode-guide" class="fixed inset-0 bg-black bg-opacity-50 z-50" style="display: none;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        <i class="fas fa-code mr-2"></i>
                        Guide des BB Codes
                    </h3>
                    <button onclick="toggleBBCodeGuide()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="bbcode-guide space-y-6">
                    <?php foreach (getBBCodeGuide() as $section => $codes): ?>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3"><?= $section ?></h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($codes as $code => $description): ?>
                            <div class="flex flex-col">
                                <code class="bbcode-example mb-1"><?= htmlspecialchars($code) ?></code>
                                <span class="text-sm text-gray-600 dark:text-gray-300"><?= $description ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>
                            Conseils d'utilisation
                        </h4>
                        <ul class="text-sm text-blue-700 dark:text-blue-200 space-y-1">
                            <li>• Vous pouvez combiner plusieurs codes : <code>[b][color=rouge]Texte gras rouge[/color][/b]</code></li>
                            <li>• Utilisez les cadres spéciaux ([info], [warning], [success]) pour mettre en valeur des informations</li>
                            <li>• Les images doivent être des URLs complètes (https://...)</li>
                            <li>• Les spoilers sont parfaits pour masquer des réponses ou des informations sensibles</li>
                        </ul>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="toggleBBCodeGuide()" class="btn-primary">
                        <i class="fas fa-check mr-2"></i>
                        Compris !
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= getBBCodeJS() ?>

<?php endif; ?>