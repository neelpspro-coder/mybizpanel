<?php
// Gestion des actions pour les messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send':
                if (empty(trim($_POST['content']))) {
                    $error = "Le message ne peut pas être vide";
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO messages (id, sender_id, content) VALUES (?, ?, ?)");
                    $stmt->execute([
                        generateId(),
                        $_SESSION['user_id'],
                        trim($_POST['content'])
                    ]);
                    $_SESSION['flash_success'] = "Message envoyé !";
                    header('Location: ?page=messages');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de l'envoi du message";
                    header('Location: ?page=messages');
                    exit;
                }
                break;
                
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
                    $stmt->execute([$_POST['message_id'], $_SESSION['user_id']]);
                    $_SESSION['flash_success'] = "Message supprimé !";
                    header('Location: ?page=messages');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash_error'] = "Erreur lors de la suppression";
                    header('Location: ?page=messages');
                    exit;
                }
                break;
        }
    }
}

// API pour vérifier les nouveaux messages (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'check_new') {
    header('Content-Type: application/json');
    
    try {
        $lastId = $_GET['last_id'] ?? '0';
        
        $stmt = $pdo->prepare("
            SELECT m.*, u.email as sender_email, u.first_name, u.last_name 
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.id 
            WHERE m.receiver_id IS NULL AND m.id > ? 
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$lastId]);
        $newMessages = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'new_messages' => $newMessages
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Gestion des messages flash
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Récupération des messages
try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.email as sender_email, u.first_name, u.last_name 
        FROM messages m 
        LEFT JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id IS NULL 
        ORDER BY m.created_at ASC 
        LIMIT 100
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
    $error = "Erreur lors de la récupération des messages";
}

// Statistiques
$totalMessages = count($messages);
$todayMessages = count(array_filter($messages, fn($m) => date('Y-m-d', strtotime($m['created_at'])) === date('Y-m-d')));
$uniqueUsers = count(array_unique(array_column($messages, 'sender_id')));
?>

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

    <!-- Statistiques du chat -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-comments text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Messages Total</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $totalMessages ?></p>
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
                    <p class="text-2xl font-bold text-green-600"><?= $todayMessages ?></p>
                </div>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-gray-600">Participants</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $uniqueUsers ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Interface de chat -->
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-violet-500 to-purple-600 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold flex items-center">
                        <i class="fas fa-comments mr-2"></i>
                        Canal Général - Messages d'Équipe
                        <span id="online-indicator" class="ml-3 w-3 h-3 bg-green-400 rounded-full animate-pulse"></span>
                    </h1>
                    <p class="text-sm opacity-90 mt-1">Communication collaborative en temps réel</p>
                </div>
                <div class="text-right text-sm opacity-75">
                    <div id="last-update">Dernière mise à jour : maintenant</div>
                </div>
            </div>
        </div>

        <!-- Zone de messages -->
        <div id="messages-container" class="h-96 overflow-y-auto p-4 bg-gray-50 space-y-4">
            <?php if (empty($messages)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-comments text-4xl mb-4"></i>
                <p class="text-lg">Aucun message pour le moment</p>
                <p class="text-sm">Soyez le premier à écrire dans le canal général !</p>
            </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                <div class="bg-white rounded-lg p-4 shadow-sm border hover:shadow-md transition-shadow" data-message-id="<?= $message['id'] ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="w-8 h-8 bg-gradient-to-r from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                                    <?= strtoupper(substr($message['sender_email'], 0, 1)) ?>
                                </div>
                                <div>
                                    <span class="font-medium text-violet-700">
                                        <?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?>
                                    </span>
                                    <span class="text-sm text-gray-500 ml-2">
                                        (<?= htmlspecialchars($message['sender_email']) ?>)
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ml-11">
                                <p class="text-gray-800 mb-2 leading-relaxed"><?= nl2br(htmlspecialchars($message['content'])) ?></p>
                                <div class="text-xs text-gray-500 flex items-center">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?= date('d/m/Y à H:i', strtotime($message['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bouton supprimer (seulement pour ses propres messages) -->
                        <?php if ($message['sender_id'] === $_SESSION['user_id']): ?>
                        <div class="ml-3">
                            <button onclick="deleteMessage('<?= $message['id'] ?>')" class="text-red-500 hover:text-red-700 text-sm p-2 rounded hover:bg-red-50 transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulaire d'envoi -->
        <div class="p-4 border-t bg-white">
            <form method="POST" onsubmit="return validateMessage()" class="space-y-3">
                <input type="hidden" name="action" value="send">
                
                <div>
                    <textarea 
                        id="message-input"
                        name="content" 
                        rows="3" 
                        maxlength="500"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-violet-500 resize-none" 
                        placeholder="Tapez votre message... (Ctrl+Enter pour envoyer)"
                        required></textarea>
                    <div class="flex justify-between items-center mt-2">
                        <div class="text-xs text-gray-500">
                            <span id="char-count">0</span>/500 caractères
                        </div>
                        <div class="text-xs text-gray-400">
                            💡 Utilisez Ctrl+Enter pour envoyer rapidement
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Audio pour notifications -->
<audio id="notification-sound" preload="auto">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmUdBDKI1PLNdyUELI3e9M+LQQ0XZr3n4qVPEwxMpe7tx2Icd0iI3vnKdCUGKIHE7N2OSQ8VYLTq7K5VFwpHnt/yvWEcBTOI1PLNdywGJnnk" type="audio/wav">
</audio>

<script>
// Variables globales
let lastMessageId = null;
let isPageVisible = true;
let currentUserId = '<?= $_SESSION['user_id'] ?>';
let chatContainer = null;
let updateInterval = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    chatContainer = document.getElementById('messages-container');
    
    // Auto-scroll vers le bas initial
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        // Récupérer l'ID du dernier message
        const lastMessage = chatContainer.querySelector('[data-message-id]:last-child');
        if (lastMessage) {
            lastMessageId = lastMessage.getAttribute('data-message-id');
        }
    }
    
    // Compteur de caractères
    setupCharacterCounter();
    
    // Démarrer les mises à jour en temps réel
    startRealTimeUpdates();
    
    // Détecter si la page est visible
    setupVisibilityDetection();
    
    // Raccourcis clavier
    setupKeyboardShortcuts();
});

// Configuration du compteur de caractères
function setupCharacterCounter() {
    const messageInput = document.getElementById('message-input');
    const charCount = document.getElementById('char-count');
    
    if (messageInput && charCount) {
        messageInput.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            charCount.style.color = this.value.length > 450 ? '#ef4444' : '#6b7280';
        });
    }
}

// Démarrer les mises à jour temps réel
function startRealTimeUpdates() {
    // Première vérification immédiate
    checkForNewMessages();
    
    // Puis toutes les 3 secondes
    updateInterval = setInterval(checkForNewMessages, 3000);
}

// Vérifier les nouveaux messages
function checkForNewMessages() {
    fetch('?page=messages&action=check_new&last_id=' + (lastMessageId || '0'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_messages && data.new_messages.length > 0) {
                // Ajouter les nouveaux messages
                data.new_messages.forEach(message => {
                    addMessageToChat(message);
                    
                    // Si ce n'est pas notre message, notifier
                    if (message.sender_id !== currentUserId) {
                        showNotification(message);
                        playNotificationSound();
                    }
                });
                
                // Mettre à jour l'ID du dernier message
                if (data.new_messages.length > 0) {
                    lastMessageId = data.new_messages[data.new_messages.length - 1].id;
                }
                
                // Auto-scroll
                scrollToBottom();
                
                // Mettre à jour l'indicateur
                updateLastUpdateTime();
            }
        })
        .catch(error => {
            console.error('Erreur lors de la vérification des messages:', error);
        });
}

// Ajouter un message au chat
function addMessageToChat(message) {
    if (!chatContainer) return;
    
    const messageElement = document.createElement('div');
    messageElement.className = 'bg-white rounded-lg p-4 shadow-sm border hover:shadow-md transition-shadow';
    messageElement.setAttribute('data-message-id', message.id);
    
    const canDelete = message.sender_id === currentUserId;
    
    messageElement.innerHTML = `
        <div class="flex justify-between items-start">
            <div class="flex-1">
                <div class="flex items-center mb-2">
                    <div class="w-8 h-8 bg-gradient-to-r from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white text-sm font-medium mr-3">
                        ${message.sender_email.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <span class="font-medium text-violet-700">
                            ${escapeHtml(message.first_name + ' ' + message.last_name)}
                        </span>
                        <span class="text-sm text-gray-500 ml-2">
                            (${escapeHtml(message.sender_email)})
                        </span>
                    </div>
                </div>
                
                <div class="ml-11">
                    <p class="text-gray-800 mb-2 leading-relaxed">${escapeHtml(message.content).replace(/\n/g, '<br>')}</p>
                    <div class="text-xs text-gray-500 flex items-center">
                        <i class="fas fa-clock mr-1"></i>
                        ${formatDate(message.created_at)}
                    </div>
                </div>
            </div>
            
            ${canDelete ? `
            <div class="ml-3">
                <button onclick="deleteMessage('${message.id}')" class="text-red-500 hover:text-red-700 text-sm p-2 rounded hover:bg-red-50 transition-colors">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            ` : ''}
        </div>
    `;
    
    chatContainer.appendChild(messageElement);
}

// Supprimer un message
function deleteMessage(messageId) {
    if (!confirm('Supprimer ce message ?')) return;
    
    fetch('?page=messages', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&message_id=' + messageId
    })
    .then(response => response.text())
    .then(() => {
        // Supprimer l'élément du DOM directement
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            messageElement.remove();
        }
    })
    .catch(error => {
        console.error('Erreur lors de la suppression:', error);
        alert('Erreur lors de la suppression du message');
    });
}

// Afficher une notification
function showNotification(message) {
    // Notification navigateur si disponible
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Nouveau message - MyBizPanel', {
            body: `${message.first_name}: ${message.content.substring(0, 50)}${message.content.length > 50 ? '...' : ''}`,
            icon: '/favicon.ico',
            tag: 'chat-message'
        });
    }
    
    // Notification visuelle personnalisée
    showAutoNotification(
        `💬 ${message.first_name} ${message.last_name}: ${message.content.substring(0, 60)}${message.content.length > 60 ? '...' : ''}`,
        'info'
    );
}

// Jouer le son de notification
function playNotificationSound() {
    if (!isPageVisible) {
        const audio = document.getElementById('notification-sound');
        if (audio) {
            audio.play().catch(e => console.log('Son de notification non disponible'));
        }
    }
}

// Détecter la visibilité de la page
function setupVisibilityDetection() {
    document.addEventListener('visibilitychange', function() {
        isPageVisible = !document.hidden;
    });
    
    window.addEventListener('focus', function() {
        isPageVisible = true;
    });
    
    window.addEventListener('blur', function() {
        isPageVisible = false;
    });
}

// Auto-scroll vers le bas
function scrollToBottom() {
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
}

// Mettre à jour l'heure de dernière mise à jour
function updateLastUpdateTime() {
    const indicator = document.getElementById('last-update');
    if (indicator) {
        const now = new Date();
        indicator.textContent = `Dernière mise à jour : ${now.toLocaleTimeString()}`;
    }
}

// Validation et envoi du message
function validateMessage() {
    const messageInput = document.getElementById('message-input');
    const content = messageInput.value.trim();
    
    if (content.length === 0) {
        alert('Veuillez saisir un message');
        return false;
    }
    
    if (content.length > 500) {
        alert('Le message est trop long (maximum 500 caractères)');
        return false;
    }
    
    return true;
}

// Raccourcis clavier
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            const form = document.querySelector('form');
            if (form && validateMessage()) {
                form.submit();
            }
        }
    });
}

// Utilitaires
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'À l\'instant';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' min';
    if (diff < 86400000) return date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
    return date.toLocaleDateString('fr-FR') + ' à ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
}

// Demander permission pour les notifications
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

// Nettoyer à la fermeture
window.addEventListener('beforeunload', function() {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});
</script>