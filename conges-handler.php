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
    case 'get_details':
        getLeaveDetails($user_id);
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
        respondWithError('Invalid action specified');
}
/**
 * Gets all pending leave requests (for admin only)
 * 
 * @param int $user_id The user ID
 */
function getPendingRequests($user_id) {
    global $conn;
    
    // Check if the user is an admin
    $user = getCurrentUser();
    if ($user['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
    }
    
    try {
        // Get all pending leave requests from all users
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
        
        // Format dates and add employee name
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
 * 
 * @param int $user_id The user ID of the admin
 */
function approveLeaveRequest($user_id) {
    global $conn;
    
    // Check if the user is an admin
    $user = getCurrentUser();
    if ($user['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
    }
    
    // Get leave ID and comment
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
    }
    
    try {
        // Check if the leave request exists and is pending
        $stmt = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ?");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
        }
        
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être approuvées.');
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Update leave status to approved
        $stmt = $conn->prepare("UPDATE Conges 
                               SET status = 'approved', 
                                   date_reponse = GetDate(),
                                   reponse_commentaire = ?
                               WHERE conge_id = ?");
        $stmt->execute([$commentaire, $leave_id]);
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        respondWithSuccess('Demande de congé approuvée avec succès.');
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Rejects a leave request
 * 
 * @param int $user_id The user ID of the admin
 */
function rejectLeaveRequest($user_id) {
    global $conn;
    
    // Check if the user is an admin
    $user = getCurrentUser();
    if ($user['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
    }
    
    // Get leave ID and comment
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
    }
    
    if (empty($commentaire)) {
        respondWithError('Un motif de refus est requis.');
    }
    
    try {
        // Check if the leave request exists and is pending
        $stmt = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ?");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
        }
        
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être refusées.');
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Update leave status to rejected
        $stmt = $conn->prepare("UPDATE Conges 
                               SET status = 'rejected', 
                                   date_reponse = GetDate(),
                                   reponse_commentaire = ?
                                   WHERE conge_id = ?");
        $stmt->execute([$commentaire, $leave_id]);
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        respondWithSuccess('Demande de congé refusée avec succès.');
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}
/**
 * Submits a new leave request
 * 
 * @param int $user_id The user ID
 */
function submitLeaveRequest($user_id) {
    global $conn;
    
    // Get form data
    $date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $type_conge = isset($_POST['type_conge']) ? $_POST['type_conge'] : null;
    $commentaire = isset($_POST['commentaire']) ? $_POST['commentaire'] : '';
    
    // Validate required fields
    if (!$date_debut || !$date_fin || !$type_conge) {
        respondWithError('Tous les champs obligatoires doivent être remplis.');
    }
    
    // Validate date range
    if (strtotime($date_fin) < strtotime($date_debut)) {
        respondWithError('La date de fin ne peut pas être antérieure à la date de début.');
    }
    
    // Calculate duration in days (including weekends for now)
    $duration = calculateDateDiff($date_debut, $date_fin);
    
    // Set default status
    $status = 'pending';
    
    // Handle file upload
    $document_path = null;
    if (!empty($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $upload_dir = 'uploads/conges/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . basename($_FILES['document']['name']);
        $target_file = $upload_dir . $filename;
        
        // Check file type
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        if ($file_type != "pdf" && $file_type != "jpg" && $file_type != "jpeg" && $file_type != "png") {
            respondWithError('Seuls les fichiers PDF, JPG, JPEG et PNG sont autorisés.');
        }
        
        // Check file size (max 5MB)
        if ($_FILES['document']['size'] > 5000000) {
            respondWithError('Le fichier est trop volumineux. Taille maximum: 5MB.');
        }
        
        // Upload the file
        if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
            $document_path = $target_file;
        } else {
            respondWithError('Erreur lors du téléchargement du fichier.');
        }
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert new leave request
        $stmt = $conn->prepare("INSERT INTO Conges 
                               (user_id, date_debut, date_fin, type_conge, 
                                duree, commentaire, document, status, date_demande) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, GetDate())");
        
        $stmt->execute([
            $user_id,
            $date_debut,
            $date_fin,
            $type_conge,
            $duration,
            $commentaire,
            $document_path,
            $status
        ]);
        
        $conge_id = $conn->lastInsertId();
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        respondWithSuccess('Demande de congé soumise avec succès.', [
            'conge_id' => $conge_id
        ]);
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Cancels a leave request
 * 
 * @param int $user_id The user ID
 */
function cancelLeaveRequest($user_id) {
    global $conn;
    
    // Get leave ID
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
    }
    
    try {
        // Check if the leave request belongs to the user and is pending
        $stmt = $conn->prepare("SELECT status FROM Conges 
                               WHERE conge_id = ? AND user_id = ?");
        $stmt->execute([$leave_id, $user_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$leave) {
            respondWithError('Demande de congé non trouvée ou non autorisée.');
        }
        
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être annulées.');
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Update leave status to cancelled
        $stmt = $conn->prepare("UPDATE Conges 
                               SET status = 'cancelled', 
                                   date_reponse = GetDate() 
                               WHERE conge_id = ?");
        $stmt->execute([$leave_id]);
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        respondWithSuccess('Demande de congé annulée avec succès.');
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets the leave history for a user
 * 
 * @param int $user_id The user ID
 */
function getLeaveHistory($user_id) {
    global $conn;
    
    try {
        // Get all leave requests for the user, ordered by most recent first
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
        
        // Format dates for display
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
 * 
 * @param int $user_id The user ID
 */
function getLeaveStats($user_id) {
    global $conn;
    
    try {
        // Get the current year
        $current_year = date('Y');
        
        // Get default leave allowances (this would normally come from a configuration table)
        $leave_types = [
            'cp' => ['acquis' => 25, 'name' => 'Congés Payés'],
            'rtt' => ['acquis' => 12, 'name' => 'RTT'],
            'sans-solde' => ['acquis' => 0, 'name' => 'Sans Solde'],
            'special' => ['acquis' => 5, 'name' => 'Congé Spécial'],
            'maladie' => ['acquis' => 0, 'name' => 'Congé Maladie']
        ];
        
        // Initialize results array
        $results = [];
        
        // For each leave type, calculate taken and pending days
        foreach ($leave_types as $type => $info) {
            // Calculate days taken (approved leaves)
            $stmt = $conn->prepare("SELECT COALESCE(SUM(duree), 0) as total_taken 
                                   FROM Conges 
                                   WHERE user_id = ? 
                                   AND type_conge = ? 
                                   AND status = 'approved'
                                   AND YEAR(date_debut) = ?");
            $stmt->execute([$user_id, $type, $current_year]);
            $taken = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate pending days
            $stmt = $conn->prepare("SELECT COALESCE(SUM(duree), 0) as total_pending 
                                   FROM Conges 
                                   WHERE user_id = ? 
                                   AND type_conge = ? 
                                   AND status = 'pending'
                                   AND YEAR(date_debut) = ?");
            $stmt->execute([$user_id, $type, $current_year]);
            $pending = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate balance
            $balance = $info['acquis'] - $taken['total_taken'];
            
            // Add to results
            $results[] = [
                'type' => $type,
                'acquis' => $info['acquis'],
                'pris' => $taken['total_taken'],
                'pending' => $pending['total_pending'],
                'solde' => $balance
            ];
        }
        
        respondWithSuccess('Stats retrieved successfully', $results);
        
    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets detailed information for a specific leave request
 * 
 * @param int $user_id The user ID
 */
function getLeaveDetails($user_id) {
    global $conn;
    
    // Get leave ID
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
    }
    
    try {
        // Get the leave request details
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
        }
        
        // Format dates for display
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

/**
 * Calculate the number of days between two dates, including both start and end dates
 * 
 * @param string $start_date Start date (YYYY-MM-DD)
 * @param string $end_date End date (YYYY-MM-DD)
 * @return int Number of days
 */
function calculateDateDiff($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include the end date
    
    $interval = $start->diff($end);
    return $interval->days;
}

/**
 * Sends a success response
 * 
 * @param string $message Success message
 * @param array $data Optional data to include in the response
 */
function respondWithSuccess($message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Sends an error response
 * 
 * @param string $message Error message
 */
function respondWithError($message) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}
?>
