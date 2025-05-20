<?php
// employee_handler.php

require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
$userRole = $currentUser['role'];

require_once 'db-connection.php';

header('Content-Type: application/json');

// Action can be from GET or POST
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';


if (empty($action)) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune action spécifiée.']);
    exit;
}

switch ($action) {
    case 'get_employee_overview_stats':
        getEmployeeOverviewStats($conn, $userRole);
        break;
    case 'get_employee_list_for_stat':
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
        // En Activité (Pointage)
        $stmt_active = $conn->prepare("
            SELECT COUNT(DISTINCT ts.user_id) AS count_active
            FROM Timesheet ts
            JOIN Users u ON ts.user_id = u.user_id
            WHERE ts.entry_date = :today 
              AND ts.logon_time IS NOT NULL 
              AND u.status = 'Active'
              AND (ts.logoff_time IS NULL OR CAST(ts.logoff_time AS DATE) = :today_alt)
        ");
        $stmt_active->execute([':today' => $today_sql_server_format, ':today_alt' => $today_sql_server_format]);
        $stats['active_today'] = (int)($stmt_active->fetchColumn() ?: 0);


        if ($role === 'admin') {
            // Total Employés Actifs
            $stmt_total = $conn->prepare("SELECT COUNT(user_id) AS count_total FROM Users WHERE status = 'Active'");
            $stmt_total->execute();
            $stats['total_employees'] = (int)($stmt_total->fetchColumn() ?: 0);

            // Assignés Aujourd'hui (using Planning_Assignments table)
            $stmt_assigned = $conn->prepare("
                SELECT COUNT(DISTINCT pa.assigned_user_id) AS count_assigned
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assignment_date = :today AND u.status = 'Active' AND (pa.shift_type IS NULL OR pa.shift_type <> 'repos')
            ");
            $stmt_assigned->bindParam(':today', $today_sql_server_format, PDO::PARAM_STR);
            $stmt_assigned->execute();
            $stats['assigned_today'] = (int)($stmt_assigned->fetchColumn() ?: 0);

            // En Congé (Autre)
            $stmt_leave = $conn->prepare("
                SELECT COUNT(DISTINCT c.user_id) AS count_leave
                FROM Conges c
                JOIN Users u ON c.user_id = u.user_id
                WHERE :today BETWEEN c.date_debut AND c.date_fin 
                  AND c.status = 'approved' 
                  AND u.status = 'Active'
                  AND c.type_conge <> 'maladie' AND c.type_conge <> 'Arrêt maladie' 
            ");
            $stmt_leave->bindParam(':today', $today_sql_server_format, PDO::PARAM_STR);
            $stmt_leave->execute();
            $stats['on_generic_leave_today'] = (int)($stmt_leave->fetchColumn() ?: 0);

            // En Arrêt Maladie
            $stmt_sick = $conn->prepare("
                SELECT COUNT(DISTINCT c.user_id) AS count_sick
                FROM Conges c
                JOIN Users u ON c.user_id = u.user_id
                WHERE :today BETWEEN c.date_debut AND c.date_fin 
                  AND c.status = 'approved' 
                  AND u.status = 'Active'
                  AND (c.type_conge = 'maladie' OR c.type_conge = 'Arrêt maladie')
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
    $params = [':today_param' => $today_sql_server_format];


    $allowedStatTypesForAdmin = ['total_employees', 'assigned_today', 'active_today', 'on_generic_leave_today', 'on_sick_leave_today'];
    // For non-admin users, only allow 'active_today' for now, or define more granular permissions if needed.
    $allowedStatTypesForUser = ['active_today']; 

    if ($role === 'admin') {
        if (!in_array($statType, $allowedStatTypesForAdmin)) {
            echo json_encode(['status' => 'error', 'message' => 'Type de statistique non valide pour admin: ' . htmlspecialchars($statType) ]);
            exit;
        }
    } else { 
        if (!in_array($statType, $allowedStatTypesForUser)) {
             echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé à cette liste de statistiques.']);
             exit;
        }
    }

    $baseSelect = "SELECT DISTINCT u.user_id, u.nom, u.prenom, u.email, u.role";
    
    if ($statType === 'assigned_today') {
        $baseSelect .= ", pa.mission_text AS mission, pa.shift_type ";
    } elseif ($statType === 'on_generic_leave_today' || $statType === 'on_sick_leave_today') {
        $baseSelect .= ", c.type_conge ";
    }
    
    $baseSelect .= " FROM Users u ";

    switch ($statType) {
        case 'total_employees':
            // This case is for admins to see all active employees.
            // Non-admins are blocked by the $allowedStatTypesForUser check earlier if they try to access this.
            $sql = $baseSelect . "WHERE u.status = 'Active'";
            $params = []; // No :today_param needed for listing all active users
            break;
        case 'assigned_today':
            $sql = $baseSelect .
                   "JOIN Planning_Assignments pa ON u.user_id = pa.assigned_user_id " .
                   "WHERE pa.assignment_date = :today_param AND u.status = 'Active' AND (pa.shift_type IS NULL OR pa.shift_type <> 'repos')";
            break;
        case 'active_today':
            $sql = $baseSelect .
                   "JOIN Timesheet t ON u.user_id = t.user_id " .
                   "WHERE t.entry_date = :today_param AND t.logon_time IS NOT NULL AND u.status = 'Active' AND (t.logoff_time IS NULL OR CAST(t.logoff_time AS DATE) = :today_param_alt)";
            // Add the second parameter for the date comparison, even if it's the same value
            $params[':today_param_alt'] = $today_sql_server_format; 
            break;
        case 'on_generic_leave_today':
            $sql = $baseSelect .
                   "JOIN Conges c ON u.user_id = c.user_id " .
                   "WHERE :today_param BETWEEN c.date_debut AND c.date_fin AND c.status = 'approved' " .
                   "AND c.type_conge <> 'maladie' AND c.type_conge <> 'Arrêt maladie' AND u.status = 'Active'";
            break;
        case 'on_sick_leave_today':
            $sql = $baseSelect .
                   "JOIN Conges c ON u.user_id = c.user_id " .
                   "WHERE :today_param BETWEEN c.date_debut AND c.date_fin AND c.status = 'approved' " .
                   "AND (c.type_conge = 'maladie' OR c.type_conge = 'Arrêt maladie') AND u.status = 'Active'";
            break;
        default:
            // This should not be reached due to the $allowedStatTypes checks, but as a fallback:
            echo json_encode(['status' => 'error', 'message' => 'Type de statistique interne non géré pour la liste: ' . htmlspecialchars($statType)]);
            exit;
    }
    
    $sql .= " ORDER BY u.nom, u.prenom";
    if ($statType === 'assigned_today') {
        $sql .= ", mission"; 
    } elseif ($statType === 'on_generic_leave_today' || $statType === 'on_sick_leave_today') {
        $sql .= ", c.type_conge";
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($statType === 'on_generic_leave_today' || $statType === 'on_sick_leave_today') {
            foreach ($employees as &$emp) { // Use reference to modify array directly
                if (isset($emp['type_conge'])) {
                    $emp['type_conge_display'] = getTypeCongeDisplayName($emp['type_conge']);
                }
            }
        }
        echo json_encode(['status' => 'success', 'employees' => $employees]);
    } catch (PDOException $e) {
        error_log("PDOException in getEmployeeListForStat (Type: $statType): " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
        echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données lors de la récupération de la liste. Code: ' . $e->getCode() .". Query: " . $sql]);
    } catch (Exception $e) {
        error_log("Exception in getEmployeeListForStat (Type: $statType): " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Une erreur générale est survenue. Code: ' . $e->getCode()]);
    }
}

function getTypeCongeDisplayName($typeKey) {
    $types = [
        'cp' => 'Congés Payés',
        'rtt' => 'RTT',
        'sans-solde' => 'Congé Sans Solde',
        'special' => 'Congé Spécial',
        'maladie' => 'Arrêt maladie' 
    ];
    return $types[$typeKey] ?? ucfirst(str_replace('_', ' ', $typeKey));
}

if (!function_exists('respondWithError')) {
    function respondWithError($message) {
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
}
if (!function_exists('respondWithSuccess')) {
    function respondWithSuccess($message, $data = []) { // Added $message parameter for consistency
        echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
        exit;
    }
}
?>
