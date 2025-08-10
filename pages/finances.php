<?php
// Gestion des actions CRUD pour les finances
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (empty($_POST['description']) || empty($_POST['amount'])) {
                    $error = "Description et montant obligatoires";
                    break;
                }
                
                $id = generateId();
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (id, type, amount, description, category, date, attachment_url, client_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    $_POST['type'],
                    floatval($_POST['amount']),
                    $_POST['description'],
                    $_POST['category'] ?: null,
                    $_POST['date'] ?: date('Y-m-d'),
                    $_POST['attachment_url'] ?: null,
                    $_POST['client_id'] ?: null,
                    $_SESSION['user_id']
                ]);
                
                logSystemEvent('info', "Nouvelle transaction : {$_POST['description']} - " . number_format($_POST['amount'], 2) . "€", 'transaction_create');
                $success = "Transaction ajoutée avec succès !";
                
                // Notification automatique
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showAutoNotification('Nouvelle transaction : " . addslashes($_POST['description']) . "', 'success'); });</script>";
                break;
                
            case 'delete':
                if (!empty($_POST['id'])) {
                    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success = "Transaction supprimée !";
                }
                break;
        }
    }
}

// Récupération des transactions (PARTAGÉES - visibles par tous)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.email as author_email, c.name as client_name, c.company as client_company 
        FROM transactions t 
        LEFT JOIN users u ON t.user_id = u.id 
        LEFT JOIN clients c ON t.client_id = c.id 
        ORDER BY t.date DESC, t.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {
    $transactions = [];
    $error = "Erreur de récupération : " . $e->getMessage();
}

// Calcul des totaux
$totalIncome = 0;
$totalExpense = 0;
$currentMonthIncome = 0;
$currentMonthExpense = 0;
$currentMonth = date('Y-m');

foreach ($transactions as $t) {
    if ($t['type'] === 'income') {
        $totalIncome += $t['amount'];
        if (date('Y-m', strtotime($t['date'])) === $currentMonth) {
            $currentMonthIncome += $t['amount'];
        }
    } else {
        $totalExpense += $t['amount'];
        if (date('Y-m', strtotime($t['date'])) === $currentMonth) {
            $currentMonthExpense += $t['amount'];
        }
    }
}

$balance = $totalIncome - $totalExpense;
$monthlyBalance = $currentMonthIncome - $currentMonthExpense;
?>

<div class="space-y-6">
    <!-- Messages -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <?= sanitizeOutput($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <?= sanitizeOutput($error) ?>
    </div>
    <?php endif; ?>

    <!-- Résumé financier -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-arrow-up text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Revenus Total</p>
                    <p class="text-lg font-bold text-green-600">+<?= number_format($totalIncome, 2) ?>€</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-arrow-down text-red-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Dépenses Total</p>
                    <p class="text-lg font-bold text-red-600">-<?= number_format($totalExpense, 2) ?>€</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-balance-scale text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Solde Total</p>
                    <p class="text-lg font-bold <?= $balance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ($balance >= 0 ? '+' : '') . number_format($balance, 2) ?>€
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Ce Mois</p>
                    <p class="text-lg font-bold <?= $monthlyBalance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ($monthlyBalance >= 0 ? '+' : '') . number_format($monthlyBalance, 2) ?>€
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire d'ajout de transaction -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">Nouvelle Transaction</h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <input type="text" name="description" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                    <select name="client_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="">Aucun client</option>
                        <?php
                        try {
                            $clientsStmt = $pdo->query("SELECT id, name, company FROM clients ORDER BY name ASC");
                            $clientsList = $clientsStmt->fetchAll();
                            foreach ($clientsList as $client):
                        ?>
                        <option value="<?= $client['id'] ?>">
                            <?= sanitizeOutput($client['name']) ?><?= $client['company'] ? ' (' . sanitizeOutput($client['company']) . ')' : '' ?>
                        </option>
                        <?php endforeach; } catch (Exception $e) {} ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="income">Revenu</option>
                        <option value="expense">Dépense</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Montant (€) *</label>
                    <input type="number" step="0.01" name="amount" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           value="<?= date('Y-m-d') ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie</label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500">
                        <option value="">Aucune catégorie</option>
                        <option value="vente">Vente</option>
                        <option value="service">Service</option>
                        <option value="frais">Frais</option>
                        <option value="materiel">Matériel</option>
                        <option value="marketing">Marketing</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fichier (Lien Discord/URL)</label>
                    <input type="url" name="attachment_url" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500" 
                           placeholder="https://discord.com/...">
                    <p class="text-xs text-gray-500 mt-1">Lien vers une image Discord ou autre justificatif</p>
                </div>
            </div>
            
            <div>
                <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Ajouter Transaction
                </button>
            </div>
        </form>
    </div>

    <!-- Liste des transactions -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4">Transactions de l'Équipe</h3>
        
        <?php if (empty($transactions)): ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-receipt text-4xl mb-4"></i>
            <p>Aucune transaction trouvée</p>
            <p class="text-sm">Commencez par ajouter votre première transaction</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 px-4">Date</th>
                        <th class="text-left py-3 px-4">Description</th>
                        <th class="text-left py-3 px-4">Client</th>
                        <th class="text-left py-3 px-4">Catégorie</th>
                        <th class="text-left py-3 px-4">Auteur</th>
                        <th class="text-center py-3 px-4">Fichier</th>
                        <th class="text-right py-3 px-4">Montant</th>
                        <th class="text-center py-3 px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4"><?= date('d/m/Y', strtotime($transaction['date'])) ?></td>
                        <td class="py-3 px-4 font-medium"><?= sanitizeOutput($transaction['description']) ?></td>
                        <td class="py-3 px-4">
                            <?php if ($transaction['client_name']): ?>
                                <span class="text-sm"><?= sanitizeOutput($transaction['client_name']) ?></span>
                                <?php if ($transaction['client_company']): ?>
                                <br><span class="text-xs text-gray-500"><?= sanitizeOutput($transaction['client_company']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php if ($transaction['category']): ?>
                            <span class="inline-block bg-gray-200 text-gray-800 text-xs px-2 py-1 rounded">
                                <?= sanitizeOutput($transaction['category']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?= sanitizeOutput($transaction['author_email']) ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <?php if (!empty($transaction['attachment_url'])): ?>
                            <a href="<?= sanitizeOutput($transaction['attachment_url']) ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800" title="Voir le fichier">
                                <i class="fas fa-paperclip"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-right font-medium <?= $transaction['type'] === 'income' ? 'text-green-600' : 'text-red-600' ?>">
                            <?= ($transaction['type'] === 'income' ? '+' : '-') . number_format($transaction['amount'], 2) ?> €
                        </td>
                        <td class="py-3 px-4 text-center">
                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Supprimer cette transaction ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $transaction['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>