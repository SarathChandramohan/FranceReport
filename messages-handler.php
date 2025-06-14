<?php
require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expirée.']);
    exit;
}

$currentUser = getCurrentUser();
$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    switch ($action) {
        case 'send_message':
            sendMessage($conn, $currentUser);
            break;
        case 'get_sent_messages':
            getSentMessages($conn, $currentUser['user_id']);
            break;
        case 'get_received_messages':
            // This would require more complex logic based on roles
            getReceivedMessages($conn, $currentUser);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Action non valide.']);
    }
} catch (Exception $e) {
    error_log("Messages handler error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Une erreur interne est survenue.']);
}

function sendMessage($conn, $user) {
    // Validation
    if (empty($_POST['recipient_type']) || empty($_POST['subject']) || empty($_POST['content'])) {
        echo json_encode(['status' => 'error', 'message' => 'Tous les champs sont requis.']);
        return;
    }

    $attachmentPath = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $uploadDir = 'uploads/messages/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = uniqid() . '-' . basename($_FILES['attachment']['name']);
        $targetFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
            $attachmentPath = $targetFile;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors du téléchargement du fichier.']);
            return;
        }
    }

    $sql = "INSERT INTO Messages (sender_user_id, recipient_type, subject, content, priority, attachment_path, status, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'sent', GETDATE())";
    
    $params = [
        $user['user_id'],
        $_POST['recipient_type'],
        $_POST['subject'],
        $_POST['content'],
        $_POST['priority'],
        $attachmentPath
    ];

    $stmt = $conn->prepare($sql);
    if ($stmt->execute($params)) {
        echo json_encode(['status' => 'success', 'message' => 'Message envoyé avec succès.']);
    } else {
        error_log("DB Error sending message: " . print_r($stmt->errorInfo(), true));
        echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
    }
}


function getSentMessages($conn, $userId) {
    $sql = "SELECT subject, recipient_type, priority, status, FORMAT(sent_at, 'dd/MM/yyyy HH:mm') as sent_at 
            FROM Messages 
            WHERE sender_user_id = ? 
            ORDER BY sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($messages as &$msg) {
        $msg['recipient_display'] = getRecipientDisplayName($msg['recipient_type']);
    }

    echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getReceivedMessages($conn, $user) {
    // Placeholder for receiving logic. This needs to be built out.
    // An admin or RH role would see messages sent to 'rh' or 'direction'.
    $messages = [];
    if ($user['role'] === 'admin') {
        // Example: Admins can see messages to 'rh' and 'direction'
        $sql = "SELECT m.subject, m.priority, u.prenom, u.nom, FORMAT(m.sent_at, 'dd/MM/yyyy HH:mm') as sent_at
                FROM Messages m
                JOIN Users u ON m.sender_user_id = u.user_id
                WHERE m.recipient_type IN ('rh', 'direction')
                ORDER BY m.sent_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rawMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($rawMessages as $msg) {
             $messages[] = [
                'subject' => htmlspecialchars($msg['subject']),
                'priority' => htmlspecialchars($msg['priority']),
                'sender_name' => htmlspecialchars($msg['prenom'] . ' ' . $msg['nom']),
                'sent_at' => $msg['sent_at']
             ];
        }
    }
     echo json_encode(['status' => 'success', 'data' => $messages]);
}

function getRecipientDisplayName($type) {
    switch($type) {
        case 'rh': return 'Service RH';
        case 'direction': return 'Direction';
        case 'superviseur': return 'Superviseur';
        default: return ucfirst($type);
    }
}
?>