<?php
// employee_handler.php

require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

require_once 'db-connection.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune action spécifiée']);
    exit;
}

switch ($action) {
    case 'get_employee_overview_stats': // New action for the overview stats
        getEmployeeOverviewStats($conn, $userRole);
        break;
    // Keep other existing actions if they are still needed and don't conflict
    // For example, get_details, update_employee etc.
    // The request mentioned "remove current features" - if that means removing
    // all old handler functions, then only the new one above should remain.
    // For this example, I'm focusing on the new feature.
    default:
        echo json_encode(['status' => 'error', 'message' => 'Action non reconnue: ' . htmlspecialchars($action)]);
        break;
}

function getEmployeeOverviewStats($conn, $role) {
    $today = date('Y-m-d');
    $stats = [];

    try {
        // Feature 3: En Activité (people who put timesheet today) - visible to admin & others
        $stmt_active = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS count
            FROM Timesheet
            WHERE entry_date = ? AND logon_time IS NOT NULL
        ");
        $stmt_active->execute([$today]);
        $stats['active_today'] = $stmt_active->fetchColumn() ?: 0;

        if ($role === 'admin') {
            // Feature 1: Total employés (all employees) - visible to admin only
            $stmt_total = $conn->prepare("SELECT COUNT(user_id) AS count FROM Users WHERE status = 'Active'");
            $stmt_total->execute();
            $stats['total_employees'] = $stmt_total->fetchColumn() ?: 0;

            // Feature 2: Assignés Aujourd'hui (today event assigned employees) - visible to admin only
            // Using Event_AssignedUsers table as it's designed for multiple assignments per event.
            // And Events table for start_datetime.
            $stmt_assigned = $conn->prepare("
                SELECT COUNT(DISTINCT eau.user_id) AS count
                FROM Event_AssignedUsers eau
                JOIN Events e ON eau.event_id = e.event_id
                WHERE CONVERT(date, e.start_datetime) = ? 
            ");
            $stmt_assigned->execute([$today]);
            $stats['assigned_today'] = $stmt_assigned->fetchColumn() ?: 0;

            // Feature 4: En congés (other leave today) - visible to admin only
            $stmt_leave = $conn->prepare("
                SELECT COUNT(DISTINCT user_id) AS count
                FROM Conges
                WHERE ? BETWEEN date_debut AND date_fin AND status = 'approved' AND type_conge <> 'maladie'
            ");
            $stmt_leave->execute([$today]);
            $stats['on_generic_leave_today'] = $stmt_leave->fetchColumn() ?: 0;

            // Feature 5: En arrêt maladie (sick leave today) - visible to admin only
            $stmt_sick = $conn->prepare("
                SELECT COUNT(DISTINCT user_id) AS count
                FROM Conges
                WHERE ? BETWEEN date_debut AND date_fin AND status = 'approved' AND type_conge = 'maladie'
            ");
            $stmt_sick->execute([$today]);
            $stats['on_sick_leave_today'] = $stmt_sick->fetchColumn() ?: 0;
        }

        echo json_encode(['status' => 'success', 'stats' => $stats, 'role' => $role]);

    } catch (PDOException $e) {
        error_log("Error in getEmployeeOverviewStats: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données lors de la récupération des statistiques.']);
    }
}

?>
