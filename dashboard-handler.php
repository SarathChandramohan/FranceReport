<?php
// dashboard-handler.php - AJAX handler for dashboard statistics and data

// Enable error logging to a file, disable direct display of errors to avoid breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // DO NOT display errors to browser
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional: Specify a log file path

// Include database connection and session management
require_once 'db-connection.php'; // This already has a try-catch for connection
require_once 'session-management.php';

// --- Critical: Ensure this script ALWAYS returns JSON ---
// Set content type to JSON at the very beginning
header('Content-Type: application/json');

// --- Session and Authorization Check ---
if (!isLoggedIn()) {
    respondWithError('Session expirée ou non authentifié. Veuillez vous reconnecter.');
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    respondWithError('Accès non autorisé. Cette section est réservée aux administrateurs.');
    exit;
}
// --- End Session and Authorization Check ---


// Get the action from the GET request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
// Wrap the main switch in a try-catch to catch any unexpected errors
try {
    switch ($action) {
        case 'get_dashboard_all_data':
            getDashboardAllData();
            break;
        case 'get_monthly_timesheet':
            getMonthlyTimesheetData();
            break;
        case 'get_monthly_leaves':
            getMonthlyLeaveData();
            break;
        default:
            respondWithError('Action non valide spécifiée: ' . htmlspecialchars($action));
    }
} catch (Throwable $e) { // Catch all throwables (PHP 7+)
    error_log("Unhandled error in dashboard-handler.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    respondWithError('Une erreur interne du serveur est survenue. Veuillez réessayer plus tard.');
}


/**
 * Gets all dashboard data: stats and recent activities
 */
function getDashboardAllData() {
    global $conn; // $conn is from db-connection.php
    
    // No need for an additional try-catch here if individual functions handle their DB errors
    $stats = getDashboardStats($conn);
    $activities = getRecentActivities($conn);
    
    respondWithSuccess('Données du tableau de bord récupérées avec succès.', [
        'stats' => $stats,
        'activities' => $activities
    ]);
}


/**
 * Gets dashboard statistics
 * @param PDO $conn Database connection
 * @return array Array of statistics
 */
function getDashboardStats($conn) {
    $stats = [];
    $today = date('Y-m-d');
    
    try {
        // Employees present today
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS present_count
            FROM Timesheet
            WHERE entry_date = :today AND logon_time IS NOT NULL AND 
                  (logoff_time IS NULL OR CAST(logoff_time AS DATE) = :today_alt)
        ");
        $stmt->execute([':today' => $today, ':today_alt' => $today]);
        $stats['employees_present'] = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'] ?? 0;
        
        // Employees absent today
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT u.user_id) AS absent_count
            FROM Users u
            LEFT JOIN Timesheet t ON u.user_id = t.user_id AND t.entry_date = :today
            WHERE u.status = 'Active' AND t.timesheet_id IS NULL
        ");
        $stmt->execute([':today' => $today]);
        $stats['employees_absent'] = $stmt->fetch(PDO::FETCH_ASSOC)['absent_count'] ?? 0;
        
        // Pending leave requests for the current month
        $stmt = $conn->prepare("
            SELECT COUNT(conge_id) AS pending_requests_count
            FROM Conges
            WHERE status = 'pending' AND MONTH(date_demande) = MONTH(GETDATE()) AND YEAR(date_demande) = YEAR(GETDATE())
        ");
        $stmt->execute();
        $stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests_count'] ?? 0;
        
        // Heures totales ce mois card is removed, so no need for this stat calculation anymore
        // $stats['total_hours_month'] = 0; // Or remove entirely if not used anywhere else

        return $stats;
        
    } catch (PDOException $e) {
        error_log("PDO Error in getDashboardStats: " . $e->getMessage());
        // Return default/empty stats on error to prevent breaking the caller
        return ['employees_present' => 0, 'employees_absent' => 0, 'pending_requests' => 0, 'error' => 'Database error in stats'];
    }
}

/**
 * Gets recent activities for the dashboard
 * @param PDO $conn Database connection
 * @return array Array of recent activities
 */
function getRecentActivities($conn) {
    try {
        // Using a CTE for a cleaner way to combine and then select TOP N
        $sql = "
            WITH CombinedActivities AS (
                SELECT 
                    u.prenom + ' ' + u.nom AS employee_name,
                    CASE 
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NULL THEN 'Entrée de pointage'
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NOT NULL THEN 'Sortie de pointage'
                        ELSE 'Activité de pointage' 
                    END AS action,
                    COALESCE(t.logoff_time, t.logon_time) AS action_time
                FROM Timesheet t
                INNER JOIN Users u ON t.user_id = u.user_id
                WHERE t.logon_time IS NOT NULL OR t.logoff_time IS NOT NULL
                
                UNION ALL

                SELECT 
                    u.prenom + ' ' + u.nom AS employee_name,
                    'Demande de congé (' + c.type_conge + ')' AS action,
                    c.date_demande AS action_time
                FROM Conges c
                INNER JOIN Users u ON c.user_id = u.user_id
            )
            SELECT TOP 5 employee_name, action, action_time 
            FROM CombinedActivities
            ORDER BY action_time DESC;
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedActivities = [];
        foreach ($activities as $activity) {
            $timestamp = strtotime($activity['action_time']);
            $formattedActivities[] = [
                'employee_name' => $activity['employee_name'], // Already fetched as combined
                'action' => $activity['action'],
                'date' => date('d/m/Y', $timestamp),
                'hour' => date('H:i', $timestamp)
            ];
        }
        return $formattedActivities;
        
    } catch (PDOException $e) {
        error_log("PDO Error in getRecentActivities: " . $e->getMessage());
        return [['error' => 'Database error in activities']]; // Return empty or error structure
    }
}

/**
 * Gets monthly timesheet data, filterable by employee and month/year
 */
function getMonthlyTimesheetData() {
    global $conn;
    $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
    $monthYear = isset($_GET['month_year']) ? $_GET['month_year'] : date('Y-m'); 

    if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
        respondWithError("Format de mois invalide. Utilisez YYYY-MM.");
        return;
    }
    list($year, $month) = explode('-', $monthYear);

    try {
        $sql = "SELECT 
                    t.entry_date, 
                    t.logon_time, 
                    t.logoff_time,
                    t.logon_latitude, t.logon_longitude, t.logon_address,
                    t.logoff_latitude, t.logoff_longitude, t.logoff_address,
                    u.prenom + ' ' + u.nom AS employee_name,
                    CASE 
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NOT NULL AND DATEDIFF(MINUTE, t.logon_time, t.logoff_time) >= 0
                        THEN CAST(DATEDIFF(MINUTE, t.logon_time, t.logoff_time) / 60 AS VARCHAR) + 'h ' + 
                             RIGHT('0' + CAST(DATEDIFF(MINUTE, t.logon_time, t.logoff_time) % 60 AS VARCHAR), 2) -- Removed 'm' to match previous format if that was intended. Add 'm' if needed.
                        ELSE NULL 
                    END AS duration
                FROM Timesheet t
                JOIN Users u ON t.user_id = u.user_id
                WHERE MONTH(t.entry_date) = :month_val AND YEAR(t.entry_date) = :year_val";
        
        $params = [':month_val' => $month, ':year_val' => $year];

        if (!empty($employeeId)) {
            $sql .= " AND t.user_id = :employee_id";
            $params[':employee_id'] = $employeeId;
        }
        $sql .= " ORDER BY t.entry_date DESC, u.nom, u.prenom, t.logon_time DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $timesheetData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedData = [];
        foreach($timesheetData as $entry) {
            $formattedData[] = [
                'employee_name' => $entry['employee_name'],
                'entry_date' => date('d/m/Y', strtotime($entry['entry_date'])),
                'logon_time' => $entry['logon_time'] ? date('H:i', strtotime($entry['logon_time'])) : null,
                'logoff_time' => $entry['logoff_time'] ? date('H:i', strtotime($entry['logoff_time'])) : null,
                'duration' => $entry['duration'],
                'logon_latitude' => $entry['logon_latitude'],
                'logon_longitude' => $entry['logon_longitude'],
                'logon_address' => $entry['logon_address'],
                'logoff_latitude' => $entry['logoff_latitude'],
                'logoff_longitude' => $entry['logoff_longitude'],
                'logoff_address' => $entry['logoff_address'],
            ];
        }
        respondWithSuccess('Données de pointage récupérées.', ['timesheet' => $formattedData]);

    } catch (PDOException $e) {
        error_log("PDO Error in getMonthlyTimesheetData: " . $e->getMessage());
        respondWithError('Erreur de base de données lors de la récupération des pointages: ' . $e->getMessage());
    }
}

/**
 * Gets monthly leave data, filterable by employee and month/year
 */
function getMonthlyLeaveData() {
    global $conn;
    $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
    $monthYear = isset($_GET['month_year']) ? $_GET['month_year'] : date('Y-m');

    if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
        respondWithError("Format de mois invalide. Utilisez YYYY-MM.");
        return;
    }
    list($year, $month) = explode('-', $monthYear);
    $firstDayOfMonth = "$year-$month-01";
    $lastDayOfMonth = date("Y-m-t", strtotime($firstDayOfMonth));

    try {
        $sql = "SELECT 
                    c.date_debut, 
                    c.date_fin, 
                    c.type_conge, 
                    c.duree, 
                    c.status, 
                    c.commentaire,
                    u.prenom + ' ' + u.nom AS employee_name
                FROM Conges c
                JOIN Users u ON c.user_id = u.user_id
                WHERE 
                    ((MONTH(c.date_debut) = :month_val AND YEAR(c.date_debut) = :year_val) OR 
                     (MONTH(c.date_fin) = :month_alt AND YEAR(c.date_fin) = :year_alt) OR
                     (c.date_debut <= :last_day AND c.date_fin >= :first_day))"; 

        $params = [
            ':month_val' => $month, ':year_val' => $year,
            ':month_alt' => $month, ':year_alt' => $year, 
            ':last_day' => $lastDayOfMonth, ':first_day' => $firstDayOfMonth
        ];

        if (!empty($employeeId)) {
            $sql .= " AND c.user_id = :employee_id";
            $params[':employee_id'] = $employeeId;
        }
        $sql .= " ORDER BY c.date_debut DESC, u.nom, u.prenom";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $leaveData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedLeaves = [];
        foreach ($leaveData as $leave) {
            $formattedLeaves[] = [
                'employee_name' => $leave['employee_name'],
                'type_conge_display' => getTypeCongeDisplayName($leave['type_conge']),
                'date_debut' => date('d/m/Y', strtotime($leave['date_debut'])),
                'date_fin' => date('d/m/Y', strtotime($leave['date_fin'])),
                'duree' => $leave['duree'],
                'status' => $leave['status'],
                'status_display' => getStatusDisplayName($leave['status']),
                'commentaire' => $leave['commentaire']
            ];
        }
        respondWithSuccess('Données de congé récupérées.', ['leaves' => $formattedLeaves]);

    } catch (PDOException $e) {
        error_log("PDO Error in getMonthlyLeaveData: " . $e->getMessage());
        respondWithError('Erreur de base de données lors de la récupération des congés: ' . $e->getMessage());
    }
}

/** Helper function to get display name for leave type */
function getTypeCongeDisplayName($typeKey) {
    $types = [
        'cp' => 'Congés Payés', 'rtt' => 'RTT', 'sans-solde' => 'Congé Sans Solde',
        'special' => 'Congé Spécial', 'maladie' => 'Congé Maladie'
    ];
    return $types[$typeKey] ?? ucfirst($typeKey);
}

/** Helper function to get display name for status */
function getStatusDisplayName($statusKey) {
    $statuses = [
        'pending' => 'En attente', 'approved' => 'Approuvé',
        'rejected' => 'Refusé', 'cancelled' => 'Annulé'
    ];
    return $statuses[$statusKey] ?? ucfirst($statusKey);
}

/**
 * Sends a success response with JSON
 * @param string $message Success message
 * @param array $data Optional data to include in the response
 */
function respondWithSuccess($message, $data = []) {
    // header('Content-Type: application/json'); // Already set at the top
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => $data 
    ]);
    exit;
}

/**
 * Sends an error response with JSON
 * @param string $message Error message
 */
function respondWithError($message) {
    // header('Content-Type: application/json'); // Already set at the top
    error_log("Responding with error: " . $message);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}
?>
