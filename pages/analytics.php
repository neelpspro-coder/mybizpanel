<?php
// Fonction pour récupérer les analytics financières
function getFinancialAnalytics($period = 'month') {
    global $pdo;
    
    $dateFilter = '';
    switch ($period) {
        case 'week':
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case 'year':
            $dateFilter = 'WHERE date >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
            break;
    }
    
    try {
        // Revenus et dépenses
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
                COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
                COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count
            FROM transactions $dateFilter
        ");
        $financial = $stmt->fetch();
        
        $analytics = [
            'total_income' => $financial['total_income'] ?: 0,
            'total_expense' => $financial['total_expense'] ?: 0,
            'net_profit' => ($financial['total_income'] ?: 0) - ($financial['total_expense'] ?: 0),
            'income_count' => $financial['income_count'] ?: 0,
            'expense_count' => $financial['expense_count'] ?: 0
        ];
        
        return $analytics;
    } catch (Exception $e) {
        return [
            'total_income' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
            'income_count' => 0,
            'expense_count' => 0
        ];
    }
}

// Fonction d'export Excel simple
function exportToExcel($data, $filename, $headers = []) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    if (!empty($headers)) {
        echo "<tr>";
        foreach ($headers as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";
    }
    
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// Récupération des paramètres
$period = $_GET['period'] ?? 'month';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export_financial':
                try {
                    $stmt = $pdo->prepare("
                        SELECT t.*, c.name as client_name, u.email as creator_email
                        FROM transactions t 
                        LEFT JOIN clients c ON t.client_id = c.id 
                        LEFT JOIN users u ON t.user_id = u.id 
                        WHERE t.date BETWEEN ? AND ?
                        ORDER BY t.date DESC
                    ");
                    $stmt->execute([$_POST['start_date'], $_POST['end_date']]);
                    $transactions = $stmt->fetchAll();
                    
                    $data = [];
                    foreach ($transactions as $t) {
                        $data[] = [
                            $t['date'],
                            $t['type'] === 'income' ? 'Recette' : 'Dépense',
                            number_format($t['amount'], 2),
                            $t['description'],
                            $t['category'],
                            $t['client_name'] ?: 'N/A',
                            $t['creator_email']
                        ];
                    }
                    
                    $headers = ['Date', 'Type', 'Montant (€)', 'Description', 'Catégorie', 'Client', 'Créé par'];
                    exportToExcel($data, 'rapport_financier', $headers);
                    exit;
                } catch (Exception $e) {
                    $error = "Erreur lors de l'export : " . $e->getMessage();
                }
                break;
                
            case 'export_projects':
                try {
                    $stmt = $pdo->prepare("
                        SELECT p.*, c.name as client_name, u.email as creator_email
                        FROM projects p 
                        LEFT JOIN clients c ON p.client_id = c.id 
                        LEFT JOIN users u ON p.user_id = u.id 
                        WHERE p.created_at BETWEEN ? AND ?
                        ORDER BY p.created_at DESC
                    ");
                    $stmt->execute([$_POST['start_date'], $_POST['end_date']]);
                    $projects = $stmt->fetchAll();
                    
                    $data = [];
                    foreach ($projects as $p) {
                        $data[] = [
                            $p['name'],
                            $p['status'],
                            $p['priority'],
                            number_format($p['budget'], 2),
                            $p['client_name'] ?: 'N/A',
                            date('d/m/Y', strtotime($p['created_at'])),
                            $p['creator_email']
                        ];
                    }
                    
                    $headers = ['Nom', 'Statut', 'Priorité', 'Budget (€)', 'Client', 'Date création', 'Créé par'];
                    exportToExcel($data, 'rapport_projets', $headers);
                    exit;
                } catch (Exception $e) {
                    $error = "Erreur lors de l'export : " . $e->getMessage();
                }
                break;
        }
    }
}

// Récupération des analytics
try {
    $analytics = getFinancialAnalytics($period);
    
    // Analytics détaillés par période
    $stmt = $pdo->prepare("
        SELECT 
            DATE(date) as day,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as daily_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as daily_expense
        FROM transactions 
        WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(date)
        ORDER BY day DESC
        LIMIT 30
    ");
    $stmt->execute();
    $dailyData = $stmt->fetchAll();
    
    // Top clients par revenus
    $stmt = $pdo->prepare("
        SELECT c.name, c.company, SUM(t.amount) as total_revenue, COUNT(t.id) as transaction_count
        FROM clients c 
        JOIN transactions t ON c.id = t.client_id 
        WHERE t.type = 'income' AND t.date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
        GROUP BY c.id 
        ORDER BY total_revenue DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $topClients = $stmt->fetchAll();
    
    // Projets par statut
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM projects 
        GROUP BY status
    ");
    $projectStatus = $stmt->fetchAll();
    
    // Evolution mensuelle
    $stmt = $pdo->prepare("
        SELECT 
            YEAR(date) as year,
            MONTH(date) as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as monthly_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as monthly_expense
        FROM transactions 
        WHERE date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(date), MONTH(date)
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute();
    $monthlyData = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Erreur lors du chargement des analytics : " . $e->getMessage();
    $analytics = null;
}
?>

<div class="space-y-6">
    <!-- Messages d'état -->
    <?php if (isset($success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold flex items-center">
                <i class="fas fa-chart-bar mr-3 text-blue-600"></i>
                Analytics & Rapports
            </h1>
            <p class="text-gray-600 mt-1">Analyse des performances et génération de rapports</p>
        </div>
        
        <div class="flex space-x-3">
            <select onchange="changePeriod(this.value)" class="form-input-sm">
                <option value="day" <?= $period === 'day' ? 'selected' : '' ?>>Aujourd'hui</option>
                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Cette semaine</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Ce mois</option>
                <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Cette année</option>
            </select>
        </div>
    </div>

    <!-- KPIs principaux -->
    <?php if ($analytics): ?>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-arrow-up text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Revenus</p>
                    <p class="text-2xl font-bold text-green-600"><?= number_format($analytics['income'] ?? 0, 2) ?>€</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-arrow-down text-red-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Dépenses</p>
                    <p class="text-2xl font-bold text-red-600"><?= number_format($analytics['expense'] ?? 0, 2) ?>€</p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-coins text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Bénéfice</p>
                    <p class="text-2xl font-bold <?= ($analytics['profit'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= number_format($analytics['profit'] ?? 0, 2) ?>€
                    </p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-project-diagram text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Projets Actifs</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $analytics['projects'] ?? 0 ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Graphiques et analyses -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Evolution quotidienne -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-chart-line mr-2 text-blue-600"></i>
                    Évolution des 30 derniers jours
                </h3>
                
                <div class="space-y-3">
                    <?php if (!empty($dailyData)): ?>
                        <?php foreach (array_slice($dailyData, 0, 10) as $day): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="text-sm font-medium">
                                <?= date('d/m/Y', strtotime($day['day'])) ?>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="text-green-600 font-medium">
                                    +<?= number_format($day['daily_income'], 2) ?>€
                                </div>
                                <div class="text-red-600 font-medium">
                                    -<?= number_format($day['daily_expense'], 2) ?>€
                                </div>
                                <div class="font-bold <?= ($day['daily_income'] - $day['daily_expense']) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= number_format($day['daily_income'] - $day['daily_expense'], 2) ?>€
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Aucune donnée disponible</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top clients -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-trophy mr-2 text-yellow-600"></i>
                    Top Clients (12 derniers mois)
                </h3>
                
                <div class="space-y-3">
                    <?php if (!empty($topClients)): ?>
                        <?php foreach ($topClients as $index => $client): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                    <?= $index + 1 ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?= htmlspecialchars($client['name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($client['company']) ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-green-600"><?= number_format($client['total_revenue'], 2) ?>€</div>
                                <div class="text-sm text-gray-500"><?= $client['transaction_count'] ?> transactions</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Aucun client trouvé</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statuts des projets -->
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-bold mb-4">
                <i class="fas fa-tasks mr-2 text-green-600"></i>
                Répartition des Projets par Statut
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php if (!empty($projectStatus)): ?>
                    <?php
                    $statusColors = [
                        'planning' => ['bg-blue-100', 'text-blue-800', 'fas fa-clock'],
                        'active' => ['bg-green-100', 'text-green-800', 'fas fa-play'],
                        'on-hold' => ['bg-yellow-100', 'text-yellow-800', 'fas fa-pause'],
                        'completed' => ['bg-purple-100', 'text-purple-800', 'fas fa-check'],
                        'cancelled' => ['bg-red-100', 'text-red-800', 'fas fa-times']
                    ];
                    ?>
                    <?php foreach ($projectStatus as $status): ?>
                    <div class="text-center p-4 rounded-lg <?= $statusColors[$status['status']][0] ?? 'bg-gray-100' ?>">
                        <div class="mb-2">
                            <i class="<?= $statusColors[$status['status']][2] ?? 'fas fa-question' ?> text-2xl <?= $statusColors[$status['status']][1] ?? 'text-gray-800' ?>"></i>
                        </div>
                        <div class="text-2xl font-bold <?= $statusColors[$status['status']][1] ?? 'text-gray-800' ?>">
                            <?= $status['count'] ?>
                        </div>
                        <div class="text-sm font-medium <?= $statusColors[$status['status']][1] ?? 'text-gray-800' ?>">
                            <?= ucfirst($status['status']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Exports et rapports -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Export financier -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-file-excel mr-2 text-green-600"></i>
                    Export Rapport Financier
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="export_financial">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Date début</label>
                            <input type="date" name="start_date" value="<?= $startDate ?>" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Date fin</label>
                            <input type="date" name="end_date" value="<?= $endDate ?>" class="form-input" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary w-full">
                        <i class="fas fa-download mr-2"></i>
                        Télécharger Excel
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Export projets -->
        <div class="card">
            <div class="p-6">
                <h3 class="text-lg font-bold mb-4">
                    <i class="fas fa-file-excel mr-2 text-blue-600"></i>
                    Export Rapport Projets
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="export_projects">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Date début</label>
                            <input type="date" name="start_date" value="<?= $startDate ?>" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Date fin</label>
                            <input type="date" name="end_date" value="<?= $endDate ?>" class="form-input" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary w-full">
                        <i class="fas fa-download mr-2"></i>
                        Télécharger Excel
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.form-input-sm {
    @apply px-3 py-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500;
}
</style>

<script>
function changePeriod(period) {
    const url = new URL(window.location);
    url.searchParams.set('period', period);
    window.location = url;
}

// Refresh automatique des données
setInterval(function() {
    // Recharger la page toutes les 5 minutes pour actualiser les données
    if (document.visibilityState === 'visible') {
        window.location.reload();
    }
}, 300000); // 5 minutes
</script>