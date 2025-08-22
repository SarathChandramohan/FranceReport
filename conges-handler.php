<?php
// conges-handler.php - Handles all AJAX requests for leave management operations

// Include database connection and session management
require_once 'db-connection.php';
require_once 'session-management.php';

// Ensure user is logged in
requireLogin();

// Get the current user details
$user = getCurrentUser();
$user_id = $user['user_id'];

// Get the action from the POST request, or GET for flexibility
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle different actions based on the request
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
    case 'get_details': // For the user's own leave details
        getLeaveDetails($user_id);
        break;
    case 'get_details_for_admin': // For the admin to view any leave details
        getLeaveDetailsForAdmin();
        break;
    case 'get_pending_requests':
        getPendingRequests();
        break;
    case 'approve_request':
        approveLeaveRequest();
        break;
    case 'reject_request':
        rejectLeaveRequest();
        break;
    default:
        // Respond with an error if the action is not recognized
        respondWithError('Invalid action specified: ' . htmlspecialchars($action));
}

/**
 * Gets detailed information for a specific leave request (Admin version).
 */
function getLeaveDetailsForAdmin() {
    global $conn;

    // Ensure the current user is an admin
    if (getCurrentUser()['role'] !== 'admin') {
        respondWithError('Accès refusé. Cette action est réservée aux administrateurs.');
        return;
    }

    // Get and validate the leave ID from the POST request
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return;
    }

    try {
        // Prepare the SQL statement to get leave details along with employee information
        $stmt = $conn->prepare("
            SELECT
                c.conge_id as id,
                c.user_id as employee_user_id,
                u.prenom as employee_firstname,
                u.nom as employee_lastname,
                c.date_debut,
                c.date_fin,
                c.type_conge,
                c.duree,
                c.commentaire,
                c.status,
                c.date_demande,
                c.date_reponse,
                c.reponse_commentaire
            FROM Conges c
            JOIN Users u ON c.user_id = u.user_id
            WHERE c.conge_id = ?
        ");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
            return;
        }

        // Format data for display
        $leave['employee_name'] = $leave['employee_firstname'] . ' ' . $leave['employee_lastname'];
        $leave['date_debut_formatted'] = date('d/m/Y', strtotime($leave['date_debut']));
        $leave['date_fin_formatted'] = date('d/m/Y', strtotime($leave['date_fin']));
        $leave['date_demande_formatted'] = date('d/m/Y H:i', strtotime($leave['date_demande']));
        $leave['status_display'] = getStatusDisplayName($leave['status']);
        $leave['date_reponse_formatted'] = $leave['date_reponse'] ? date('d/m/Y H:i', strtotime($leave['date_reponse'])) : null;

        respondWithSuccess('Détails récupérés avec succès pour l\'administrateur.', $leave);

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets all pending leave requests (for admin only).
 */
function getPendingRequests() {
    global $conn;

    // Ensure the current user is an admin
    if (getCurrentUser()['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
        return;
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                c.conge_id as id,
                c.user_id,
                c.date_debut,
                c.date_fin,
                c.type_conge,
                c.duree,
                c.date_demande,
                u.prenom as employee_firstname,
                u.nom as employee_lastname
            FROM Conges c
            JOIN Users u ON c.user_id = u.user_id
            WHERE c.status = 'pending'
            ORDER BY c.date_demande ASC
        ");
        $stmt->execute();
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data for each pending request
        foreach ($pending as &$entry) {
            $entry['date_debut_formatted'] = date('d/m/Y', strtotime($entry['date_debut']));
            $entry['date_fin_formatted'] = date('d/m/Y', strtotime($entry['date_fin']));
            $entry['employee_name'] = $entry['employee_firstname'] . ' ' . $entry['employee_lastname'];
        }

        respondWithSuccess('Demandes en attente récupérées avec succès.', $pending);

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Approves a leave request (admin only).
 */
function approveLeaveRequest() {
    global $conn;

    // Ensure the current user is an admin
    if (getCurrentUser()['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
        return;
    }

    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return;
    }

    try {
        // Check if the request is pending before approving
        $stmt_check = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ?");
        $stmt_check->execute([$leave_id]);
        $leave = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
            return;
        }
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être approuvées.');
            return;
        }

        // Update the status to 'approved'
        $stmt = $conn->prepare("
            UPDATE Conges
            SET status = 'approved',
                date_reponse = NOW(),
                reponse_commentaire = ?
            WHERE conge_id = ?
        ");
        $stmt->execute([$commentaire, $leave_id]);

        respondWithSuccess('Demande de congé approuvée avec succès.');

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Rejects a leave request (admin only).
 */
function rejectLeaveRequest() {
    global $conn;

    // Ensure the current user is an admin
    if (getCurrentUser()['role'] !== 'admin') {
        respondWithError('Accès refusé. Vous devez être administrateur.');
        return;
    }

    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return;
    }
    if (empty($commentaire)) {
        respondWithError('Un motif de refus est requis.');
        return;
    }

    try {
        // Check if the request is pending before rejecting
        $stmt_check = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ?");
        $stmt_check->execute([$leave_id]);
        $leave = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            respondWithError('Demande de congé non trouvée.');
            return;
        }
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être refusées.');
            return;
        }

        // Update the status to 'rejected'
        $stmt = $conn->prepare("
            UPDATE Conges
            SET status = 'rejected',
                date_reponse = NOW(),
                reponse_commentaire = ?
            WHERE conge_id = ?
        ");
        $stmt->execute([$commentaire, $leave_id]);

        respondWithSuccess('Demande de congé refusée avec succès.');

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Submits a new leave request for the current user.
 * @param int $user_id The user ID of the person submitting the request.
 */
function submitLeaveRequest($user_id) {
    global $conn;

    $date_debut = $_POST['date_debut'] ?? null;
    $date_fin = $_POST['date_fin'] ?? null;
    $type_conge = $_POST['type_conge'] ?? null;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

    if (!$date_debut || !$date_fin || !$type_conge) {
        respondWithError('Tous les champs obligatoires doivent être remplis.');
        return;
    }

    if (strtotime($date_fin) < strtotime($date_debut)) {
        respondWithError('La date de fin ne peut pas être antérieure à la date de début.');
        return;
    }

    // Calculate duration (excluding weekends)
    $duration = 0;
    $start = new DateTime($date_debut);
    $end = new DateTime($date_fin);
    $end->modify('+1 day'); // Include the end date in the interval
    $interval = new DatePeriod($start, new DateInterval('P1D'), $end);

    foreach ($interval as $date) {
        // Count only weekdays (Monday=1 to Friday=5)
        if ($date->format('N') < 6) {
            $duration++;
        }
    }
    
    if ($duration <= 0) {
        respondWithError('La durée du congé doit être d\'au moins un jour ouvrable.');
        return;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO Conges
                (user_id, date_debut, date_fin, type_conge, duree, commentaire, status, date_demande)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $date_debut, $date_fin, $type_conge, $duration, $commentaire]);

        $conge_id = $conn->lastInsertId();
        respondWithSuccess('Demande de congé soumise avec succès.', ['conge_id' => $conge_id]);

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Cancels a leave request for the current user.
 * @param int $user_id The user ID.
 */
function cancelLeaveRequest($user_id) {
    global $conn;

    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return;
    }

    try {
        // Verify the request belongs to the user and is pending
        $stmt_check = $conn->prepare("SELECT status FROM Conges WHERE conge_id = ? AND user_id = ?");
        $stmt_check->execute([$leave_id, $user_id]);
        $leave = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            respondWithError('Demande de congé non trouvée ou non autorisée.');
            return;
        }
        if ($leave['status'] !== 'pending') {
            respondWithError('Seules les demandes en attente peuvent être annulées.');
            return;
        }

        // Update status to 'cancelled'
        $stmt = $conn->prepare("
            UPDATE Conges
            SET status = 'cancelled', date_reponse = NOW()
            WHERE conge_id = ?
        ");
        $stmt->execute([$leave_id]);

        respondWithSuccess('Demande de congé annulée avec succès.');

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets the leave history for the current user.
 * @param int $user_id The user ID.
 */
function getLeaveHistory($user_id) {
    global $conn;

    try {
        $stmt = $conn->prepare("
            SELECT
                conge_id as id,
                date_debut,
                date_fin,
                type_conge,
                duree,
                status,
                date_demande
            FROM Conges
            WHERE user_id = ?
            ORDER BY date_demande DESC
        ");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format data for display
        foreach ($history as &$entry) {
            $entry['date_debut_formatted'] = date('d/m/Y', strtotime($entry['date_debut']));
            $entry['date_fin_formatted'] = date('d/m/Y', strtotime($entry['date_fin']));
            $entry['status_display'] = getStatusDisplayName($entry['status']);
        }

        respondWithSuccess('Historique récupéré avec succès.', $history);

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets the leave statistics for the current user.
 * @param int $user_id The user ID.
 */
function getLeaveStats($user_id) {
    global $conn;

    try {
        $current_year = date('Y');
        // Define leave types and their yearly allowance
        $leave_types = [
            'cp'         => ['acquis' => 25, 'name' => 'Congés Payés'],
            'rtt'        => ['acquis' => 12, 'name' => 'RTT'],
            'sans-solde' => ['acquis' => 0, 'name' => 'Sans Solde'],
            'special'    => ['acquis' => 5, 'name' => 'Congé Spécial'],
            'maladie'    => ['acquis' => 0, 'name' => 'Congé Maladie']
        ];
        $results = [];

        // Prepare statements outside the loop for efficiency
        $stmt_taken = $conn->prepare("
            SELECT COALESCE(SUM(duree), 0) as total
            FROM Conges
            WHERE user_id = ? AND type_conge = ? AND status = 'approved' AND YEAR(date_debut) = ?
        ");
        $stmt_pending = $conn->prepare("
            SELECT COALESCE(SUM(duree), 0) as total
            FROM Conges
            WHERE user_id = ? AND type_conge = ? AND status = 'pending' AND YEAR(date_debut) = ?
        ");

        foreach ($leave_types as $type => $info) {
            // Get total days taken
            $stmt_taken->execute([$user_id, $type, $current_year]);
            $taken = $stmt_taken->fetch(PDO::FETCH_ASSOC)['total'];

            // Get total days pending
            $stmt_pending->execute([$user_id, $type, $current_year]);
            $pending = $stmt_pending->fetch(PDO::FETCH_ASSOC)['total'];

            $balance = $info['acquis'] - $taken;

            $results[] = [
                'type' => $info['name'],
                'acquis' => $info['acquis'],
                'pris' => $taken,
                'pending' => $pending,
                'solde' => $balance
            ];
        }

        respondWithSuccess('Statistiques récupérées avec succès.', $results);

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

/**
 * Gets detailed information for a specific leave request for the current user.
 * @param int $user_id The user ID.
 */
function getLeaveDetails($user_id) {
    global $conn;

    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    if ($leave_id <= 0) {
        respondWithError('ID de congé invalide.');
        return;
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                conge_id as id,
                date_debut,
                date_fin,
                type_conge,
                duree,
                commentaire,
                status,
                date_demande,
                date_reponse,
                reponse_commentaire
            FROM Conges
            WHERE conge_id = ? AND user_id = ?
        ");
        $stmt->execute([$leave_id, $user_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            respondWithError('Demande de congé non trouvée ou non autorisée.');
            return;
        }

        // Format data for display
        $leave['date_debut_formatted'] = date('d/m/Y', strtotime($leave['date_debut']));
        $leave['date_fin_formatted'] = date('d/m/Y', strtotime($leave['date_fin']));
        $leave['date_demande_formatted'] = date('d/m/Y H:i', strtotime($leave['date_demande']));
        $leave['status_display'] = getStatusDisplayName($leave['status']);
        $leave['date_reponse_formatted'] = $leave['date_reponse'] ? date('d/m/Y H:i', strtotime($leave['date_reponse'])) : null;

        respondWithSuccess('Détails récupérés avec succès.', $leave);

    } catch(PDOException $e) {
        respondWithError('Erreur de base de données: ' . $e->getMessage());
    }
}

// --- HELPER FUNCTIONS ---

/**
 * Gets a user-friendly display name for a status key.
 * @param string $statusKey The status key (e.g., 'pending').
 * @return string The display name (e.g., 'En attente').
 */
function getStatusDisplayName($statusKey) {
    $statuses = [
        'pending'   => 'En attente',
        'approved'  => 'Approuvé',
        'rejected'  => 'Refusé',
        'cancelled' => 'Annulé'
    ];
    return $statuses[$statusKey] ?? ucfirst($statusKey);
}

/**
 * Sends a successful JSON response.
 * @param string $message The success message.
 * @param array $data Optional data to include in the response.
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
 * Sends an error JSON response.
 * @param string $message The error message.
 */
function respondWithError($message) {
    header('Content-Type: application/json');
    // In a production environment, you might want to log the error instead of displaying it.
    // error_log($message);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}
?>
