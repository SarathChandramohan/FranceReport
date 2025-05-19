<?php
// employee_handler.php

require_once 'session-management.php';
requireLogin(); // Ensures user is logged in
$currentUser = getCurrentUser(); // Get current user info to check role
$userRole = $currentUser['role'];

require_once 'db-connection.php'; // Your database connection

header('Content-Type: application/json'); // Set response type to JSON

// Determine action from GET or POST
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if (empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune action spécifiée.']);
    exit;
}

switch ($action) {
    case 'get_employee_overview_stats':
        getEmployeeOverviewStats($conn, $userRole);
        break;
    // Add other cases for employee management (CRUD) if needed, e.g.:
    // case 'get_employee_details':
    //     getEmployeeDetails($conn, isset($_GET['user_id']) ? intval($_GET['user_id']) : 0, $userRole);
    //     break;
    // case 'update_employee_details':
    //     // Ensure this is a POST request
    //     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    //         echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    //         exit;
    //     }
    //     updateEmployeeDetails($conn, $_POST, $userRole);
    //     break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Action non reconnue: ' . htmlspecialchars($action)]);
        break;
}

function getEmployeeOverviewStats($conn, $role) {
    // Use SQL Server's GETDATE() for current date
    $today_sql_server_format = date('Y-m-d'); // Current date in YYYY-MM-DD format

    $stats = [];

    try {
        // Stat 3: En Activité (Pointage Aujourd'hui) - Visible to admin & others
        $stmt_active = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS count_active
            FROM Timesheet
            WHERE entry_date = :today AND logon_time IS NOT NULL
        ");
        $stmt_active->bindParam(':today', $today_sql_server_format, PDO::PARAM_STR);
        $stmt_active->execute();
        $stats['active_today'] = (int)($stmt_active->fetchColumn() ?: 0);

        // Admin-only stats
        if ($role === 'admin') {
            // Stat 1: Total employés (Actifs)
            $stmt_total = $conn->prepare("SELECT COUNT(user_id) AS count_total FROM Users WHERE status = 'Active'");
            $stmt_total->execute();
            $stats['total_employees'] = (int)($stmt_total->fetchColumn() ?: 0);

            // Stat 2: Assignés Aujourd'hui (Événements)
            // Counts distinct users assigned to events starting today
            $stmt_assigned = $conn->prepare("
                SELECT COUNT(DISTINCT eau.user_id) AS count_assigned
                FROM Event_AssignedUsers eau
                INNER JOIN Events e ON eau.event_id = e.event_id
                WHERE CONVERT(date, e.start_datetime) = :today
            ");
            $stmt_assigned->bindParam(':today', $today_sql_server_format, PDO::PARAM_STR);
            $stmt_assigned->execute();
            $stats['assigned_today'] = (int)($stmt_assigned->fetchColumn() ?: 0);

            // Stat 4: En Congés (Autres que maladie)
            $stmt_leave = $conn->prepare("
                SELECT COUNT(DISTINCT user_id) AS count_leave
                FROM Conges
                WHERE :today BETWEEN date_debut AND date_fin 
                  AND status = 'approved' 
                  AND type_conge <> 'maladie' 
                  AND type_conge <> 'Arrêt maladie' -- Ensure to cover variations if 'maladie' is not the only sick type
            ");
            $stmt_leave->bindParam(':today', $today_sql_server_format, PDO::PARAM_STR);
            $stmt_leave->execute();
            $stats['on_generic_leave_today'] = (int)($stmt_leave->fetchColumn() ?: 0);

            // Stat 5: En Arrêt Maladie
            $stmt_sick = $conn->prepare("
                SELECT COUNT(DISTINCT user_id) AS count_sick
                FROM Conges
                WHERE :today BETWEEN date_debut AND date_fin 
                  AND status = 'approved' 
                  AND (type_conge = 'maladie' OR type_conge = 'Arrêt maladie') -- Cover variations
            ");
            $stmt_sick->bindParam(':today', $today_sql_server_format, PDO::PARAM_STR);
            $stmt_sick->execute();
            $stats['on_sick_leave_today'] = (int)($stmt_sick->fetchColumn() ?: 0);
        }

        echo json_encode(['status' => 'success', 'stats' => $stats, 'role' => $role]);

    } catch (PDOException $e) {
        error_log("PDOException in getEmployeeOverviewStats: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur de base de données lors de la récupération des statistiques. ' . $e->getCode()
            // 'detail' => $e->getMessage() // Optionally include for debugging, remove for production
        ]);
    } catch (Exception $e) {
        error_log("Exception in getEmployeeOverviewStats: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Une erreur générale est survenue. ' . $e->getCode()
            // 'detail' => $e->getMessage() // Optionally include for debugging, remove for production
        ]);
    }
}

?>
