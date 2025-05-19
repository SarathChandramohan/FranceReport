<?php
// conges-handler.php - Handles all AJAX requests for leave management operations

// Include database connection
require_once 'db-connection.php';
require_once 'session-management.php';

// Ensure user is logged in
requireLogin();

// Get the current user ID
$user = getCurrentUser();
$user_id = $user['user_id'];

// Get the action from the POST request
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Handle different actions
switch($action) {
    case 'submit_request':
        submitLeaveRequest($user_id);
        break;
    case 'cancel_request':
        cancelLeaveRequest($user_id);
        break;
    case 'get_history':
        getLeaveHistory($user_id);
        break;
    case 'get_stats':
        getLeaveStats($user_id);
        break;
    case 'get_details': // This is for the user's own leave details
        getLeaveDetails($user_id);
        break;
    case 'get_details_for_admin': // MODIFIED: New action for admin details view
        getLeaveDetailsForAdmin($user_id);
        break;
    case 'get_pending_requests':
        getPendingRequests($user_id);
        break;
    case 'approve_request':
        approveLeaveRequest($user_id);
        break;
    case 'reject_request':
        rejectLeaveRequest($user_id);
        break;
    default:
        respondWithError('Invalid action specified: ' . htmlspecialchars($action)); // Added action to error message
}

/**
 * Gets detailed information for a specific leave request (Admin version)
 * * @param int $admin_user_id The admin user ID (for permission check)
 */
function getLeaveDetailsForAdmin($admin_user_id) {
    global $conn;

    // Ensure the current user is an admin
    $currentUser = getCurrentUser();
    if ($currentUser['role'] !== 'admin') {
        respondWithError('Accès refusé. Cette action est réservée aux administrateurs.');
        return;
    }
    
    // Get leave ID
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return;
    }
    
    try {
        // Get the leave request details and include employee name
        $stmt = $conn->prepare("SELECT 
                                c.conge_id as id, 
                                c.user_id as employee_user_id,
                                u.prenom as employee_firstname,
                                u.nom as employee_lastname,
                                c.date_debut, 
                                c.date_fin, 
                                c.type_conge, 
                                c.duree, 
                                c.commentaire, 
                                c.document, 
                                c.status, 
                                c.date_demande, 
                                c.date_reponse, 
                                c.reponse_commentaire 
                               FROM Conges c
                               JOIN Users u ON c.user_id = u.user_id
                               WHERE c.conge_id = ?");
        
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
            return;
        }
        
        // Format dates for display and add employee name
        $leave['employee_name'] = $leave['employee_firstname'] . ' ' . $leave['employee_lastname'];
        $leave['date_debut'] = date('d/m/Y', strtotime($leave['date_debut']));
        $leave['date_fin'] = date('d/m/Y', strtotime($leave['date_fin']));
        $leave['date_demande'] = date('d/m/Y H:i', strtotime($leave['date_demande']));
        $leave['status_display'] = getStatusDisplayName($leave['status']); // Helper for display
        
        if ($leave['date_reponse']) {
            $leave['date_reponse'] = date('d/m/Y H:i', strtotime($leave['date_reponse']));
        }
        
        respondWithSuccess('Details retrieved successfully for admin', $leave);
        
    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}


/**
 * Gets all pending leave requests (for admin only)
 * * @param int $user_id The user ID
 */
function getPendingRequests($user_id) {
    global $conn;
    
    $user = getCurrentUser();
    if ($user['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
        return; // Added return
    }
    
    try {
        $stmt = $conn->prepare("SELECT 
                                c.conge_id as id,
                                c.user_id, 
                                c.date_debut, 
                                c.date_fin, 
                                c.type_conge, 
                                c.duree, 
                                c.document, 
                                c.date_demande,
                                u.prenom as employee_firstname,
                                u.nom as employee_lastname
                               FROM Conges c
                               JOIN Users u ON c.user_id = u.user_id 
                               WHERE c.status = 'pending' 
                               ORDER BY c.date_demande ASC");
        
        $stmt->execute();
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pending as &$entry) {
            $entry['date_debut'] = date('d/m/Y', strtotime($entry['date_debut']));
            $entry['date_fin'] = date('d/m/Y', strtotime($entry['date_fin']));
            $entry['date_demande'] = date('d/m/Y H:i', strtotime($entry['date_demande']));
            $entry['employee_name'] = $entry['employee_firstname'] . ' ' . $entry['employee_lastname'];
        }
        
        respondWithSuccess('Pending requests retrieved successfully', $pending);
        
    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Approves a leave request
 * * @param int $user_id The user ID of the admin
 */
function approveLeaveRequest($user_id) {
    global $conn;
    
    $user = getCurrentUser();
    if ($user['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
        return; // Added return
    }
    
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return; // Added return
    }
    
    try {
        $stmt = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ?");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
            return; // Added return
        }
        
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être approuvées.');
            return; // Added return
        }
        
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("UPDATE Conges 
                               SET status = 'approved', 
                                   date_reponse = GetDate(),
                                   reponse_commentaire = ?
                               WHERE conge_id = ?");
        $stmt->execute([$commentaire, $leave_id]);
        
        $conn->commit();
        
        respondWithSuccess('Demande de congé approuvée avec succès.');
        
    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Rejects a leave request
 * * @param int $user_id The user ID of the admin
 */
function rejectLeaveRequest($user_id) {
    global $conn;
    
    $user = getCurrentUser();
    if ($user['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
        return; // Added return
    }
    
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return; // Added return
    }
    
    if (empty($commentaire)) {
        respondWithError('Un motif de refus est requis.');
        return; // Added return
    }
    
    try {
        $stmt = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ?");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
            return; // Added return
        }
        
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être refusées.');
            return; // Added return
        }
        
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("UPDATE Conges 
                               SET status = 'rejected', 
                                   date_reponse = GetDate(),
                                   reponse_commentaire = ?
                                   WHERE conge_id = ?");
        $stmt->execute([$commentaire, $leave_id]);
        
        $conn->commit();
        
        respondWithSuccess('Demande de congé refusée avec succès.');
        
    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}
/**
 * Submits a new leave request
 * * @param int $user_id The user ID
 */
function submitLeaveRequest($user_id) {
    global $conn;
    
    $date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $type_conge = isset($_POST['type_conge']) ? $_POST['type_conge'] : null;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';
    
    if (!$date_debut || !$date_fin || !$type_conge) {
        respondWithError('Tous les champs obligatoires doivent être remplis.');
        return; // Added return
    }
    
    if (strtotime($date_fin) < strtotime($date_debut)) {
        respondWithError('La date de fin ne peut pas être antérieure à la date de début.');
        return; // Added return
    }
    
    $duration = calculateDateDiff($date_debut, $date_fin);
    $status = 'pending';
    $document_path = null;

    if (!empty($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $upload_dir = 'uploads/conges/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                respondWithError('Échec de la création du répertoire de téléchargement.');
                return;
            }
        }
        
        $filename = uniqid() . '_' . basename($_FILES['document']['name']);
        $target_file = $upload_dir . $filename;
        
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ["pdf", "jpg", "jpeg", "png"];
        if (!in_array($file_type, $allowed_types)) {
            respondWithError('Seuls les fichiers PDF, JPG, JPEG et PNG sont autorisés.');
            return; 
        }
        
        if ($_FILES['document']['size'] > 5000000) { // 5MB
            respondWithError('Le fichier est trop volumineux. Taille maximum: 5MB.');
            return; 
        }
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
            $document_path = $target_file;
        } else {
            respondWithError('Erreur lors du téléchargement du fichier.');
            return; 
        }
    }
    
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("INSERT INTO Conges 
                               (user_id, date_debut, date_fin, type_conge, 
                                duree, commentaire, document, status, date_demande) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, GetDate())");
        
        $stmt->execute([
            $user_id, $date_debut, $date_fin, $type_conge,
            $duration, $commentaire, $document_path, $status
        ]);
        
        $conge_id = $conn->lastInsertId();
        $conn->commit();
        
        respondWithSuccess('Demande de congé soumise avec succès.', ['conge_id' => $conge_id]);
        
    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Cancels a leave request
 * * @param int $user_id The user ID
 */
function cancelLeaveRequest($user_id) {
    global $conn;
    
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return; // Added return
    }
    
    try {
        $stmt = $conn->prepare("SELECT status FROM Conges 
                               WHERE conge_id = ? AND user_id = ?");
        $stmt->execute([$leave_id, $user_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée ou non autorisée.');
            return; // Added return
        }
        
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être annulées.');
            return; // Added return
        }
        
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("UPDATE Conges 
                               SET status = 'cancelled', 
                                   date_reponse = GetDate() 
                               WHERE conge_id = ?");
        $stmt->execute([$leave_id]);
        
        $conn->commit();
        
        respondWithSuccess('Demande de congé annulée avec succès.');
        
    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets the leave history for a user
 * * @param int $user_id The user ID
 */
function getLeaveHistory($user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT 
                                conge_id as id, 
                                date_debut, 
                                date_fin, 
                                type_conge, 
                                duree, 
                                status, 
                                document, 
                                date_demande 
                               FROM Conges 
                               WHERE user_id = ? 
                               ORDER BY date_demande DESC");
        
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($history as &$entry) {
            $entry['date_debut'] = date('d/m/Y', strtotime($entry['date_debut']));
            $entry['date_fin'] = date('d/m/Y', strtotime($entry['date_fin']));
            $entry['date_demande'] = date('d/m/Y H:i', strtotime($entry['date_demande']));
        }
        
        respondWithSuccess('History retrieved successfully', $history);
        
    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets the leave statistics for a user
 * * @param int $user_id The user ID
 */
function getLeaveStats($user_id) {
    global $conn;
    
    try {
        $current_year = date('Y');
        $leave_types = [
            'cp' => ['acquis' => 25, 'name' => 'Congés Payés'],
            'rtt' => ['acquis' => 12, 'name' => 'RTT'],
            'sans-solde' => ['acquis' => 0, 'name' => 'Sans Solde'],
            'special' => ['acquis' => 5, 'name' => 'Congé Spécial'],
            'maladie' => ['acquis' => 0, 'name' => 'Congé Maladie']
        ];
        $results = [];
        
        foreach ($leave_types as $type => $info) {
            $stmt_taken = $conn->prepare("SELECT COALESCE(SUM(duree), 0) as total_taken 
                                   FROM Conges 
                                   WHERE user_id = ? AND type_conge = ? AND status = 'approved' AND YEAR(date_debut) = ?");
            $stmt_taken->execute([$user_id, $type, $current_year]);
            $taken = $stmt_taken->fetch(PDO::FETCH_ASSOC);
            
            $stmt_pending = $conn->prepare("SELECT COALESCE(SUM(duree), 0) as total_pending 
                                   FROM Conges 
                                   WHERE user_id = ? AND type_conge = ? AND status = 'pending' AND YEAR(date_debut) = ?");
            $stmt_pending->execute([$user_id, $type, $current_year]);
            $pending = $stmt_pending->fetch(PDO::FETCH_ASSOC);
            
            $balance = $info['acquis'] - ($taken['total_taken'] ?? 0);
            
            $results[] = [
                'type' => $type,
                'acquis' => $info['acquis'],
                'pris' => ($taken['total_taken'] ?? 0),
                'pending' => ($pending['total_pending'] ?? 0),
                'solde' => $balance
            ];
        }
        
        respondWithSuccess('Stats retrieved successfully', $results);
        
    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets detailed information for a specific leave request (User's own)
 * * @param int $user_id The user ID
 */
function getLeaveDetails($user_id) {
    global $conn;
    
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return; // Added return
    }
    
    try {
        $stmt = $conn->prepare("SELECT 
                                conge_id as id, 
                                date_debut, 
                                date_fin, 
                                type_conge, 
                                duree, 
                                commentaire, 
                                document, 
                                status, 
                                date_demande, 
                                date_reponse, 
                                reponse_commentaire 
                               FROM Conges 
                               WHERE conge_id = ? AND user_id = ?");
        
        $stmt->execute([$leave_id, $user_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée ou non autorisée.');
            return; // Added return
        }
        
        $leave['date_debut'] = date('d/m/Y', strtotime($leave['date_debut']));
        $leave['date_fin'] = date('d/m/Y', strtotime($leave['date_fin']));
        $leave['date_demande'] = date('d/m/Y H:i', strtotime($leave['date_demande']));
        
        if ($leave['date_reponse']) {
            $leave['date_reponse'] = date('d/m/Y H:i', strtotime($leave['date_reponse']));
        }
        
        respondWithSuccess('Details retrieved successfully', $leave);
        
    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

function calculateDateDiff($start_date, $end_date) {
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        // It's important to add 1 day to the end date to include it in the count
        $end->modify('+1 day'); 
        $interval = $start->diff($end);
        return $interval->days > 0 ? $interval->days : 1; // Ensure at least 1 day if start and end are same
    } catch (Exception $e) {
        error_log("Error in calculateDateDiff: " . $e->getMessage());
        return 1; // Default to 1 day on error
    }
}

function getStatusDisplayName($statusKey) {
    $statuses = [
        'pending' => 'En attente', 'approved' => 'Approuvé',
        'rejected' => 'Refusé', 'cancelled' => 'Annulé'
    ];
    return $statuses[$statusKey] ?? ucfirst($statusKey);
}

function respondWithSuccess($message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function respondWithError($message) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}
?>
