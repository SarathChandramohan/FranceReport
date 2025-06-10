<?php
// dashboard-handler.php - AJAX handler for dashboard statistics and data

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'db-connection.php';
require_once 'session-management.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    respondWithError('Session expirée ou non authentifié. Veuillez vous reconnecter.');
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    respondWithError('Accès non autorisé. Cette section est réservée aux administrateurs.');
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        // **FIXED**: Added the missing case for the dashboard's main data fetch
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
} catch (Throwable $e) {
    error_log("Unhandled error in dashboard-handler.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    respondWithError('Une erreur interne du serveur est survenue. Veuillez réessayer plus tard.');
}

// **FIXED**: Added the function to handle the main data request
function getDashboardAllData() {
    global $conn;
    // This function now calls the other helpers to gather all necessary data
    $stats = getDashboardStats($conn);
    $activities = getRecentActivities($conn);
    respondWithSuccess('Données du tableau de bord récupérées avec succès.', [
        'stats' => $stats,
        'activities' => $activities
    ]);
}

// This function was present in your dashboard.php but is now correctly placed here
function getDashboardStats($conn) {
    $stats = [];
    $today = date('Y-m-d');

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS present_count
            FROM Timesheet
            WHERE entry_date = :today AND logon_time IS NOT NULL AND
                  (logoff_time IS NULL OR CAST(logoff_time AS DATE) = :today_alt)
        ");
        $stmt->execute([':today' => $today, ':today_alt' => $today]);
        $stats['employees_present'] = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'] ?? 0;

        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT u.user_id) AS absent_count
            FROM Users u
            LEFT JOIN Timesheet t ON u.user_id = t.user_id AND t.entry_date = :today
            LEFT JOIN Conges c ON u.user_id = c.user_id AND :today BETWEEN c.date_debut AND c.date_fin AND c.status = 'approved'
            WHERE u.status = 'Active' AND t.timesheet_id IS NULL AND c.conge_id IS NULL
        ");
        $stmt->execute([':today' => $today]);
        $stats['employees_absent'] = $stmt->fetch(PDO::FETCH_ASSOC)['absent_count'] ?? 0;

        $stmt = $conn->prepare("
            SELECT COUNT(conge_id) AS pending_requests_count
            FROM Conges
            WHERE status = 'pending' AND MONTH(date_demande) = MONTH(GETDATE()) AND YEAR(date_demande) = YEAR(GETDATE())
        ");
        $stmt->execute();
        $stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests_count'] ?? 0;

        return $stats;

    } catch (PDOException $e) {
        error_log("PDO Error in getDashboardStats: " . $e->getMessage());
        return ['employees_present' => 0, 'employees_absent' => 0, 'pending_requests' => 0, 'error' => 'Database error in stats'];
    }
}

// This function was present in your dashboard.php but is now correctly placed here
function getRecentActivities($conn) {
    try {
        // This query is now aligned with your latest database changes
        $sql = "
            WITH CombinedActivities AS (
                SELECT
                    u.prenom + ' ' + u.nom AS employee_name,
                    CASE
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NULL THEN 'Entrée: ' + ISNULL(t.logon_location_name, 'Lieu N/A')
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NOT NULL THEN 'Sortie: ' + ISNULL(t.logoff_location_name, 'Lieu N/A')
                        ELSE 'Activité de pointage'
                    END AS action,
                    COALESCE(t.logoff_time, t.logon_time) AS action_time,
                    1 AS sort_priority
                FROM Timesheet t
                INNER JOIN Users u ON t.user_id = u.user_id
                WHERE t.logon_time IS NOT NULL OR t.logoff_time IS NOT NULL

                UNION ALL

                SELECT
                    u.prenom + ' ' + u.nom AS employee_name,
                    'Demande de congé (' + c.type_conge + ')' AS action,
                    c.date_demande AS action_time,
                    2 AS sort_priority
                FROM Conges c
                INNER JOIN Users u ON c.user_id = u.user_id
            )
            SELECT TOP 5 employee_name, action, action_time
            FROM CombinedActivities
            ORDER BY action_time DESC, sort_priority ASC;
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedActivities = [];
        if ($activities) {
            foreach ($activities as $activity) {
                $timestamp = strtotime($activity['action_time']);
                $formattedActivities[] = [
                    'employee_name' => $activity['employee_name'],
                    'action' => $activity['action'],
                    'date' => date('d/m/Y', $timestamp),
                    'hour' => date('H:i', $timestamp)
                ];
            }
        }
        return $formattedActivities;

    } catch (PDOException $e) {
        error_log("PDO Error in getRecentActivities: " . $e->getMessage());
        return [['error' => 'Database error in activities']];
    }
}

// This function is for the "Feuille de Temps" modal and has been corrected
function getMonthlyTimesheetData() {
    global $conn;
    $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
    $monthYear = (isset($_GET['month_year']) && !empty($_GET['month_year'])) ? $_GET['month_year'] : date('Y-m');
    $specificDay = isset($_GET['specific_day']) && !empty($_GET['specific_day']) ? $_GET['specific_day'] : null;

    if ($specificDay && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDay)) {
        respondWithError("Format de jour invalide. Utilisez yyyy-MM-dd.");
        return;
    }
    if (!$specificDay && ($monthYear && !preg_match('/^\d{4}-\d{2}$/', $monthYear))) {
        respondWithError("Format de mois invalide. Utilisez yyyy-MM.");
        return;
    }

    try {
        $sql = "SELECT
                    t.entry_date, t.logon_time, t.logoff_time,
                    t.logon_location_name, t.logoff_location_name,
                    t.break_minutes, u.prenom + ' ' + u.nom AS employee_name
                FROM Timesheet t
                JOIN Users u ON t.user_id = u.user_id
                WHERE 1=1";
        $params = [];

        if ($specificDay) {
            $sql .= " AND t.entry_date = :specific_day";
            $params[':specific_day'] = $specificDay;
        } elseif ($monthYear) {
            list($year, $month) = explode('-', $monthYear);
            $sql .= " AND MONTH(t.entry_date) = :month_val AND YEAR(t.entry_date) = :year_val";
            $params[':month_val'] = $month;
            $params[':year_val'] = $year;
        }

        if (!empty($employeeId)) {
            $sql .= " AND t.user_id = :employee_id";
            $params[':employee_id'] = $employeeId;
        }
        $sql .= " ORDER BY t.entry_date DESC, u.nom, u.prenom, t.logon_time DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $timesheetData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedData = [];
        if ($timesheetData) {
            foreach($timesheetData as $entry) {
                $durationDisplay = '--';
                if ($entry['logon_time'] && $entry['logoff_time']) {
                    $logon = new DateTime($entry['logon_time']);
                    $logoff = new DateTime($entry['logoff_time']);
                    $diffMinutes = ($logoff->getTimestamp() - $logon->getTimestamp()) / 60;
                    $breakMinutes = (int)($entry['break_minutes'] ?? 0);
                    $effectiveMinutes = $diffMinutes - $breakMinutes;
                    if ($effectiveMinutes < 0) $effectiveMinutes = 0;
                    $hours = floor($effectiveMinutes / 60);
                    $minutes = $effectiveMinutes % 60;
                    $durationDisplay = $hours . 'h' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
                }

                $formattedData[] = [
                    'employee_name' => $entry['employee_name'],
                    'entry_date' => date('d/m/Y', strtotime($entry['entry_date'])),
                    'logon_time' => $entry['logon_time'] ? date('H:i', strtotime($entry['logon_time'])) : null,
                    'logoff_time' => $entry['logoff_time'] ? date('H:i', strtotime($entry['logoff_time'])) : null,
                    'duration' => $durationDisplay,
                    'logon_location_name' => $entry['logon_location_name'] ?? 'N/A',
                    'logoff_location_name' => $entry['logoff_location_name'] ?? 'N/A',
                    'break_minutes' => $entry['break_minutes']
                ];
            }
        }
        respondWithSuccess('Données de pointage récupérées.', ['timesheet' => $formattedData]);

    } catch (PDOException $e) {
        error_log("PDO Error in getMonthlyTimesheetData: " . $e->getMessage());
        respondWithError('Erreur de base de données lors de la récupération des pointages.');
    }
}

// Your other existing functions like getMonthlyLeaveData, helpers, etc. remain unchanged.
function getMonthlyLeaveData() {
    global $conn;
    $employeeId = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
    $monthYear = (isset($_GET['month_year']) && !empty($_GET['month_year'])) ? $_GET['month_year'] : date('Y-m');
    $specificDay = isset($_GET['specific_day']) && !empty($_GET['specific_day']) ? $_GET['specific_day'] : null;

    if ($specificDay && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $specificDay)) { respondWithError("Format de jour invalide."); return; }
    if (!$specificDay && ($monthYear && !preg_match('/^\d{4}-\d{2}$/', $monthYear))) { respondWithError("Format de mois invalide."); return; }
    if (!$specificDay && !$monthYear) { respondWithError("Veuillez sélectionner un mois ou un jour."); return; }

    try {
        $sql = "SELECT c.date_debut, c.date_fin, c.type_conge, c.duree, c.status, c.commentaire, u.prenom + ' ' + u.nom AS employee_name
                FROM Conges c JOIN Users u ON c.user_id = u.user_id WHERE 1=1";
        $params = [];
        if ($specificDay) {
            $sql .= " AND :specific_day BETWEEN c.date_debut AND c.date_fin";
            $params[':specific_day'] = $specificDay;
        } elseif ($monthYear) {
            list($year, $month) = explode('-', $monthYear);
            $firstDayOfMonth = "$year-$month-01";
            $lastDayOfMonth = date("Y-m-t", strtotime($firstDayOfMonth));
            $sql .= " AND ((c.date_debut <= :last_day_of_month AND c.date_fin >= :first_day_of_month))";
            $params[':first_day_of_month'] = $firstDayOfMonth;
            $params[':last_day_of_month'] = $lastDayOfMonth;
        }
        if (!empty($employeeId)) {
            $sql .= " AND c.user_id = :employee_id";
            $params[':employee_id'] = $employeeId;
        }
        $sql .= " ORDER BY c.date_debut DESC, u.nom, u.prenom";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $leaveData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedLeaves = [];
        if($leaveData){
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
        }
        respondWithSuccess('Données de congé récupérées.', ['leaves' => $formattedLeaves]);
    } catch (PDOException $e) {
        error_log("PDO Error in getMonthlyLeaveData: " . $e->getMessage());
        respondWithError('Erreur de base de données lors de la récupération des congés.');
    }
}

function getTypeCongeDisplayName($typeKey) {
    $types = ['cp' => 'Congés Payés', 'rtt' => 'RTT', 'sans-solde' => 'Congé Sans Solde', 'special' => 'Congé Spécial', 'maladie' => 'arrêt maladie'];
    return $types[$typeKey] ?? ucfirst(str_replace('_', ' ', $typeKey));
}

function getStatusDisplayName($statusKey) {
    $statuses = ['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Refusé', 'cancelled' => 'Annulé'];
    return $statuses[$statusKey] ?? ucfirst($statusKey);
}

function respondWithSuccess($message, $data = []) {
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data ]);
    exit;
}

function respondWithError($message) {
    error_log("Responding with error (dashboard-handler): " . $message);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}
?>
