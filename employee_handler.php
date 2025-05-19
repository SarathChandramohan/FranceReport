<?php
// employee_handler.php

require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

require_once 'db-connection.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if (empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune action spécifiée.']);
    exit;
}

switch ($action) {
    case 'get_employee_overview_stats':
        getEmployeeOverviewStats($conn, $userRole);
        break;
    case 'get_employee_list_for_stat': // New action for fetching lists for modals
        getEmployeeListForStat($conn, $userRole, isset($_GET['type']) ? $_GET['type'] : '');
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Action non reconnue: ' . htmlspecialchars($action)]);
        break;
}

function getEmployeeOverviewStats($conn, $role) {
    $today_sql_server_format = date('Y-m-d');
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

        if ($role === 'admin') {
            // Stat 1: Total employés (Actifs)
            $stmt_total = $conn->prepare("SELECT COUNT(user_id) AS count_total FROM Users WHERE status = 'Active'");
            $stmt_total->execute();
            $stats['total_employees'] = (int)($stmt_total->fetchColumn() ?: 0);

            // Stat 2: Assignés Aujourd'hui (Événements)
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
                  AND type_conge <> 'maladie' AND type_conge <> 'Arrêt maladie'
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
                  AND (type_conge = 'maladie' OR type_conge = 'Arrêt maladie')
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
            'message' => 'Erreur de base de données lors de la récupération des statistiques. Code: ' . $e->getCode()
        ]);
    } catch (Exception $e) {
        error_log("Exception in getEmployeeOverviewStats: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Une erreur générale est survenue. Code: ' . $e->getCode()
        ]);
    }
}

function getEmployeeListForStat($conn, $role, $statType) {
    $today_sql_server_format = date('Y-m-d');
    $employees = [];
    $sql = "";
    $params = [':today' => $today_sql_server_format];

    // Basic security check for statType
    $allowedStatTypes = ['assigned_today', 'active_today', 'on_generic_leave_today', 'on_sick_leave_today'];
    if (!in_array($statType, $allowedStatTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Type de statistique non valide.']);
        exit;
    }

    // Permission check: some lists are admin-only
    if ($role !== 'admin' && in_array($statType, ['assigned_today', 'on_generic_leave_today', 'on_sick_leave_today'])) {
        echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé à cette liste.']);
        exit;
    }

    $baseSelect = "SELECT DISTINCT u.user_id, u.nom, u.prenom, u.email, u.role FROM Users u ";

    switch ($statType) {
        case 'assigned_today':
            $sql = $baseSelect . "JOIN Event_AssignedUsers eau ON u.user_id = eau.user_id " .
                                 "JOIN Events e ON eau.event_id = e.event_id " .
                                 "WHERE CONVERT(date, e.start_datetime) = :today AND u.status = 'Active'";
            break;
        case 'active_today':
            $sql = $baseSelect . "JOIN Timesheet t ON u.user_id = t.user_id " .
                                 "WHERE t.entry_date = :today AND t.logon_time IS NOT NULL AND u.status = 'Active'";
            break;
        case 'on_generic_leave_today':
            $sql = $baseSelect . "JOIN Conges c ON u.user_id = c.user_id " .
                                 "WHERE :today BETWEEN c.date_debut AND c.date_fin AND c.status = 'approved' " .
                                 "AND c.type_conge <> 'maladie' AND c.type_conge <> 'Arrêt maladie' AND u.status = 'Active'";
            break;
        case 'on_sick_leave_today':
            $sql = $baseSelect . "JOIN Conges c ON u.user_id = c.user_id " .
                                 "WHERE :today BETWEEN c.date_debut AND c.date_fin AND c.status = 'approved' " .
                                 "AND (c.type_conge = 'maladie' OR c.type_conge = 'Arrêt maladie') AND u.status = 'Active'";
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Type de statistique non géré pour la liste.']);
            exit;
    }
    $sql .= " ORDER BY u.nom, u.prenom";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'employees' => $employees]);
    } catch (PDOException $e) {
        error_log("PDOException in getEmployeeListForStat ($statType): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données lors de la récupération de la liste. Code: ' . $e->getCode()]);
    } catch (Exception $e) {
        error_log("Exception in getEmployeeListForStat ($statType): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Une erreur générale est survenue. Code: ' . $e->getCode()]);
    }
}

?>
