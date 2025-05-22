<?php
// 1. Include session management and database connection
require_once 'session-management.php';
require_once 'db-connection.php';

// 2. Require login - This will redirect the user if not logged in
requireLogin();

// 3. Get current user info
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: timesheet.php');
    exit;
}

// Functions from original dashboard.php
function getDashboardStats($conn) {
    $stats = [];
    try {
        $today = date('Y-m-d');
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
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return ['employees_present' => 0, 'employees_absent' => 0, 'pending_requests' => 0];
    }
}

function getRecentActivities($conn) {
    $activities = [];
    try {
        $stmt = $conn->prepare("
            WITH CombinedActivities AS (
                SELECT
                    u.prenom + ' ' + u.nom AS employee_name,
                    CASE
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NULL THEN 'Entrée de pointage'
                        WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NOT NULL THEN 'Sortie de pointage'
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
        ");
        $stmt->execute();
        $activities_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedActivities = [];
        if ($activities_raw) {
            foreach ($activities_raw as $activity) {
                $timestamp = strtotime($activity['action_time']);
                $formattedActivities[] = [
                    'employee_name' => htmlspecialchars($activity['employee_name']),
                    'action' => htmlspecialchars($activity['action']),
                    'date' => date('d/m/Y', $timestamp),
                    'hour' => date('H:i', $timestamp)
                ];
            }
        }
        return $formattedActivities;

    } catch (PDOException $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
        return [];
    }
}

function getAllEmployees($conn) {
    $employees = [];
    try {
        $stmt = $conn->prepare("SELECT user_id, prenom, nom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting employees: " . $e->getMessage());
    }
    return $employees;
}

// Function from employes.php
function getInitialEmployeeList($conn) {
    $employees = [];
    try {
        $query = "SELECT user_id as id, nom, prenom, email, role, status FROM Users WHERE status = 'Active' ORDER BY nom, prenom";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getInitialEmployeeList: " . $e->getMessage());
        $GLOBALS['initial_employee_list_error'] = "Could not load employee list.";
    }
    return $employees;
}

$stats = getDashboardStats($conn);
$activities = getRecentActivities($conn);
$all_employees = getAllEmployees($conn); // Used for modals
$initial_employee_list = getInitialEmployeeList($conn); // For the integrated employes section

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding-bottom: 30px;
        }

        .container {
            width: 100%;
            margin: 0;
            padding: 25px;
        }

        h1 {
            color: #1d1d1f;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .shortcut-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .shortcut-btn {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            color: #333;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            min-height: 120px;
            cursor: pointer; /* Add cursor pointer for buttons */
        }
        .shortcut-btn:hover {
            background-color: #f0f2f5;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
            color: #007bff;
        }
        .shortcut-btn i {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 2.2em;
        }
        .shortcut-btn:hover i {
            color: #0056b3;
        }

        .content-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            border: 1px solid #e5e5e5;
            margin-bottom: 30px;
        }
        h2 { /* General h2 for content cards */
            margin-bottom: 20px;
            color: #1d1d1f;
            font-size: 22px;
            font-weight: 600;
        }

        /* Styles from employes.php, adapted for dashboard integration */
        h3 { /* Specific h3 for integrated employes section titles */
            color: #1d1d1f;
            font-weight: 600;
             margin-bottom: 20px;
             font-size: 20px; /* Slightly smaller than main card h2 */
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            padding: 20px;
            text-align: center;
            border-left: 5px solid #007aff;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            cursor: default;
        }
        .stat-card.clickable:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .stat-value { font-size: 2.5rem; font-weight: 700; margin-bottom: 8px; line-height: 1.1; color: #333; }
        .stat-label { font-size: 0.95rem; color: #555; font-weight: 500; }
        .stat-icon { font-size: 1.8rem; margin-bottom: 10px; opacity: 0.7; }

        .stat-card.total-employees { border-left-color: #007bff; }
        .stat-card.total-employees .stat-icon { color: #007bff; }
        .stat-card.assigned-today { border-left-color: #ff9500; }
        .stat-card.assigned-today .stat-icon { color: #ff9500; }
        .stat-card.active-today { border-left-color: #34c759; }
        .stat-card.active-today .stat-icon { color: #34c759; }
        .stat-card.on-generic-leave-today { border-left-color: #5856d6; }
        .stat-card.on-generic-leave-today .stat-icon { color: #5856d6; }
        .stat-card.on-sick-leave-today { border-left-color: #ff3b30; }
        .stat-card.on-sick-leave-today .stat-icon { color: #ff3b30; }

        #employeeListCardHeader {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        #employeeListCardHeader h3 {
            margin-bottom: 0.5rem;
        }
        #backToListButton {
            display: none;
            margin-bottom: 0.5rem;
        }
         /* End of employes.php specific styles */


        .filter-controls {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-controls label {
            font-weight: 500;
            color: #1d1d1f;
            font-size: 14px;
            white-space: nowrap;
        }
        .filter-controls .form-control-sm {
            padding: 0.3rem 0.6rem;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #d2d2d7;
            background-color: #f5f5f7;
            height: auto;
            line-height: 1.5;
        }
        .filter-item select.form-control-sm {
            min-width: 200px;
            flex-grow: 1;
        }
        .filter-item input[type="month"].form-control-sm,
        .filter-item input[type="date"].form-control-sm {
            min-width: 150px;
        }
        .filter-controls .filter-item.export-button-group {
            margin-left: auto;
        }


        .export-button {
            padding: 8px 15px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
            background-color: #34c759;
            color: white;
        }
        .export-button:hover { background-color: #2ca048; }

        .table-container {
            overflow-x: auto;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            font-size: 14px;
            color: #1d1d1f;
            vertical-align: middle;
        }
        table td { color: #555; }
        table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #333;
            border-bottom-width: 2px;
        }
        table tr:last-child td { border-bottom: none; }
        table tr:hover { background-color: #f0f0f0; }

        .action-button {
            padding: 5px 10px;
            border-radius: 6px;
            border: none;
            background-color: #007aff;
            color: white;
            font-size: 13px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-right: 5px;
            margin-bottom: 3px;
            display: inline-block;
        }
        .action-button:hover { background-color: #0056b3; }
        .btn-sm { padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem;}

        .status-tag {
            display: inline-block; padding: 4px 10px; border-radius: 12px;
            font-size: 12px; font-weight: 600; text-align: center; white-space: nowrap; color: white;
        }
        .status-tag.status-pending { background-color: #ff9500; }
        .status-tag.status-approved { background-color: #34c759; }
        .status-tag.status-rejected { background-color: #ff3b30; }
        .status-tag.status-cancelled { background-color: #8e8e93; }

        .modal {
            display: none; position: fixed; z-index: 1050;
            left: 0; top: 0; width: 100%; height: 100%; overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        #mapModal.modal {
            z-index: 1070; /* Ensures map modal is on top of other modals */
        }
        .modal-content {
            background-color: #ffffff; margin: 5% auto;
            padding: 25px; border: none; width: 90%;
            border-radius: 14px; position: relative;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .modal-lg { max-width: 800px; }
        .modal-xl { max-width: 1140px; }

        .modal-header .close {
            padding: 1rem 1rem;
            margin: -1rem -1rem -1rem auto;
        }

        #map-modal-content-container { height: 350px; width: 100%; margin-bottom: 15px; }
        #map-modal-title { margin-bottom: 15px; font-size: 18px; font-weight: 600; }
        #map-modal-details p { margin-bottom: 5px; font-size: 14px; color: #333;}
        #map-modal-details strong { color: #1d1d1f; }

        .modal-alert { display: none; margin-bottom: 15px; }

        #congesAdminModal .nav-tabs .nav-link {
            border-radius: 0.25rem 0.25rem 0 0;
            color: #007bff;
        }
        #congesAdminModal .nav-tabs .nav-link.active {
            color: #495057;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            font-weight: bold;
        }
        #congesAdminModal .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 15px;
            border-radius: 0 0 0.25rem 0.25rem;
        }
        #leaveDetailsModal .modal-body p {
            font-size: 1rem;
            margin-bottom: 0.8rem;
        }
        #leaveDetailsModal .modal-body strong {
            font-weight: 600;
            color: #333;
        }
        #leaveDetailsModal .document-link-modal {
            display: inline-block;
            margin-top: 5px;
            padding: 5px 10px;
            background-color: #f0f2f5;
            border-radius: 5px;
            color: #007bff;
            text-decoration: none;
        }
         #leaveDetailsModal .document-link-modal:hover {
            background-color: #e9ecef;
        }
        #leaveDetailsModal .status-tag-modal {
             padding: 0.25em 0.6em;
             font-size: 0.9em;
        }
         .loading-placeholder, .error-placeholder, .info-placeholder { text-align: center; padding: 40px 20px; color: #6c757d; font-size: 1.1rem; }
        .error-placeholder { color: #dc3545; }
        .alert-custom { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: .35rem; }
        .alert-danger-custom { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-info-custom { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }


        @media (max-width: 768px) {
            h1 { font-size: 24px; }
            .content-card { padding: 20px; }
            h2 { font-size: 20px; } /* General h2 */
            h3 { font-size: 18px; } /* employes.php h3 */
            table th, table td { padding: 10px 12px; font-size: 13px; }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .filter-item {
                width: 100%;
                gap: 5px;
                justify-content: space-between;
            }
            .filter-item label {
                 flex-shrink: 0;
            }
            .filter-controls .form-control-sm {
                flex-grow: 1;
                min-width: 100px;
            }
            .filter-controls .export-button,
            .filter-item.export-button-group {
                margin-left: 0;
                width: 100%;
            }

            .shortcut-buttons-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;}
            .shortcut-btn { padding: 15px; font-size: 0.8rem;}
            .shortcut-btn i { font-size: 1.8em;}
            .modal-content { margin: 10% auto; }

             #employeeListCardHeader {
                flex-direction: column;
                align-items: flex-start;
            }
            #employeeListCardHeader h3 {
                margin-bottom: 10px;
            }
            #backToListButton {
                 width: 100%;
                 text-align: center;
            }
        }
        @media (max-width: 480px) {
            table th, table td { padding: 8px 10px; font-size: 12px; }
            .modal-content { margin: 5% auto; width: 95%; padding: 15px;}
            #map-modal-content-container { height: 300px; }
            .shortcut-buttons-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php
        if (file_exists('navbar.php')) {
            include 'navbar.php';
        }
    ?>

    <div class="container-fluid">
        <h1>Administrateur</h1>

        <div class="shortcut-buttons-grid">
            <button class="shortcut-btn" data-toggle="modal" data-target="#congesAdminModal">
                <i class="fas fa-user-shield"></i>Congés Admin
            </button>
            <a href="planning.php" class="shortcut-btn">
                <i class="fas fa-calendar-alt"></i>Planning Admin
            </a>
            <button class="shortcut-btn" data-toggle="modal" data-target="#eventCreationModal">
                <i class="fas fa-plus-circle"></i>Créer Événement
            </button>
            <button class="shortcut-btn" data-toggle="modal" data-target="#feuilleDeTempsModal">
                <i class="fas fa-user-clock"></i>Feuille de Temps
            </button>
        </div>

        <div class="content-card" id="integrated-employes-section">
             <?php
                if (isset($GLOBALS['initial_employee_list_error'])) {
                    echo '<div class="alert alert-danger-custom text-center">' . htmlspecialchars($GLOBALS['initial_employee_list_error']) . '</div>';
                }
            ?>
            <div class="card" style="border: none; box-shadow: none; padding: 0;"> <h3>Statistiques du Jour</h3>
                <div class="stats-container" id="employee-stats-container">
                    <div class="loading-placeholder">
                        <div class="spinner-border spinner-border-sm" role="status"><span class="sr-only">Chargement...</span></div>
                        Chargement des statistiques...
                    </div>
                </div>
            </div>

            <div class="card" id="employeeListCard" style="border: none; box-shadow: none; padding: 0; margin-top: 20px;"> <div id="employeeListCardHeader">
                    <h3 id="employeeListTitle">Liste Générale des Employés Actifs</h3>
                    <button id="backToListButton" class="btn btn-sm btn-outline-secondary" onclick="showInitialEmployeeList()">
                        <i class="fas fa-arrow-left"></i> Retour à la liste générale
                    </button>
                </div>
                <div class="table-container">
                    <table id="employees-table" class="table table-striped table-hover">
                        <thead class="thead-light" id="employees-table-head">
                            </thead>
                        <tbody id="employees-table-body">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="content-card">
            <h2>Dernières activités</h2>
            <div class="table-container">
                <table id="activities-table">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Action</th>
                            <th>Date</th>
                            <th>Heure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">Aucune activité récente</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?php echo $activity['employee_name']; ?></td>
                                    <td><?php echo $activity['action']; ?></td>
                                    <td><?php echo $activity['date']; ?></td>
                                    <td><?php echo $activity['hour']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="congesAdminModal" tabindex="-1" aria-labelledby="congesAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="congesAdminModalLabel">Gestion des Congés</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="congesAdminTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="approval-tab-link" data-toggle="tab" href="#leaveApprovalContent" role="tab" aria-controls="leaveApprovalContent" aria-selected="true">Approbation des Congés</a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="list-all-leaves-tab-link" data-toggle="tab" href="#listAllLeavesContent" role="tab" aria-controls="listAllLeavesContent" aria-selected="false">Liste des Congés (Tous)</a>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="congesAdminTabsContent">
                        <div class="tab-pane fade show active" id="leaveApprovalContent" role="tabpanel" aria-labelledby="approval-tab-link">
                            <h6 class="mt-2">Demandes en Attente</h6>
                            <div id="congesAdminAlert" class="alert modal-alert" role="alert" style="display:none;"></div>
                            <div class="table-container">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Employé</th>
                                            <th>Dates</th>
                                            <th>Type</th>
                                            <th>Durée</th>
                                            <th>Document</th>
                                            <th>Demandé le</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="congesAdminTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="listAllLeavesContent" role="tabpanel" aria-labelledby="list-all-leaves-tab-link">
                            <h6 class="mt-2">Consulter la Liste des Congés</h6>
                            <div class="filter-controls">
                                <div class="filter-item">
                                    <label for="caLeaveEmployeeFilter">Employé:</label>
                                    <select id="caLeaveEmployeeFilter" class="form-control form-control-sm">
                                        <option value="">Tous</option>
                                        <?php foreach ($all_employees as $emp): ?>
                                            <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                                                <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label for="caLeaveMonthFilter">Mois:</label>
                                    <input type="month" id="caLeaveMonthFilter" class="form-control form-control-sm" value="<?php echo date('Y-m'); ?>">
                                </div>
                                <div class="filter-item">
                                    <label for="caLeaveDayFilter">Jour:</label>
                                    <input type="date" id="caLeaveDayFilter" class="form-control form-control-sm">
                                </div>
                                <div class="filter-item export-button-group">
                                    <button class="export-button btn-sm" onclick="exportTableToCSV('caListeCongesTable', 'liste_conges_admin_modal.csv')">Exporter CSV</button>
                                </div>
                            </div>
                            <div class="table-container">
                                <table id="caListeCongesTable" class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Employé</th>
                                            <th>Type</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Durée</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody id="caListeCongesTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" aria-labelledby="leaveDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveDetailsModalLabel">Détails de la Demande de Congé</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="leaveDetailsModalBody">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="eventCreationModal" tabindex="-1" aria-labelledby="eventCreationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventCreationModalLabel">Créer un Nouvel Événement</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="eventCreationForm">
                    <div class="modal-body">
                        <div id="eventCreationAlert" class="alert modal-alert" role="alert" style="display:none;"></div>
                        <div class="form-group">
                            <label for="eventTitleModal">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="eventTitleModal" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="eventDescriptionModal">Description</label>
                            <textarea class="form-control form-control-sm" id="eventDescriptionModal" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="eventStartModal">Début <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control form-control-sm" id="eventStartModal" name="start_datetime" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="eventEndModal">Fin <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control form-control-sm" id="eventEndModal" name="end_datetime" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="eventAssignedUsersModal">Assigner à <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" id="eventAssignedUsersModal" name="assigned_users[]" multiple required>
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                                        <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs.</small>
                        </div>
                        <div class="form-group">
                            <label for="eventColorModal">Couleur</label>
                            <input type="color" class="form-control form-control-sm" id="eventColorModal" name="color" value="#007bff">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="feuilleDeTempsModal" tabindex="-1" aria-labelledby="feuilleDeTempsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feuilleDeTempsModalLabel">Feuille de Temps</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="filter-controls">
                        <div class="filter-item">
                            <label for="timesheetEmployeeFilterModal">Employé:</label>
                            <select id="timesheetEmployeeFilterModal" class="form-control form-control-sm">
                                <option value="">Tous</option>
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                                        <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label for="timesheetMonthFilterModal">Mois:</label>
                            <input type="month" id="timesheetMonthFilterModal" class="form-control form-control-sm" value="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="filter-item">
                            <label for="timesheetDayFilterModal">Jour:</label>
                            <input type="date" id="timesheetDayFilterModal" class="form-control form-control-sm">
                        </div>
                        <div class="filter-item export-button-group">
                            <button class="export-button btn-sm" onclick="exportTableToCSV('timesheetTableModal', 'feuille_de_temps_modal.csv')">Exporter CSV</button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table id="timesheetTableModal" class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Employé</th>
                                    <th>Date</th>
                                    <th>Entrée</th>
                                    <th>Sortie</th>
                                    <th>Pause prise</th>
                                    <th>Durée</th>
                                </tr>
                            </thead>
                            <tbody id="timesheetTableBodyModal"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

     <div id="mapModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="mapModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="map-modal-title-main">Localisation Pointage</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="map-modal-content-container-main" style="height: 350px; width: 100%; margin-bottom: 15px;"></div>
                    <div id="map-modal-details-main" class="mt-2"></div>
                </div>
                 <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>


    <?php
        if (file_exists('footer.php')) {
            include 'footer.php';
        }
    ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let map; // For the main map modal in dashboard.php
        let currentMapMarkers = []; // For the main map modal

        // Original dashboard.php scripts (modified paths/IDs if necessary for integrated section)
        function escapeHtml(text) {
            if (text === null || typeof text === 'undefined') return '';
            const strText = String(text);
            const div = document.createElement('div');
            div.textContent = strText;
            return div.innerHTML;
        }

        function refreshDashboardData() { // This will now also trigger employes.php data refresh
            try {
                fetch('dashboard-handler.php?action=get_dashboard_all_data') // Fetches activities
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                try {
                                    const errData = JSON.parse(text);
                                    throw new Error(errData.message || "HTTP error! status: " + response.status);
                                } catch (e) {
                                    throw new Error("HTTP error! status: " + response.status + ". Response: " + text.substring(0,100) + "...");
                                }
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success' && data.data) {
                            if (data.data.activities) {
                                updateActivitiesTable(data.data.activities);
                            }
                        } else {
                            console.error('Error from dashboard-handler or missing data:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error refreshing dashboard (fetch/parse activities):', error);
                        displayGlobalError("Erreur de communication pour rafraîchir les activités: " + error.message);
                    });

                fetchEmployeeStats(); // Fetch stats for the integrated employes section
                // If a filtered list is shown in integrated employes, decide if it should be refreshed or reset to initial
                // For simplicity, we can reset to initial list or just refresh stats.
                // To keep current filter: you'd need to store current statType and call loadFilteredEmployeeList again.
                // For now, just refreshing stats. The user can click a stat card to reload a list.

            } catch (e) {
                console.error("Error in refreshDashboardData:", e);
                displayGlobalError("Une erreur s'est produite lors du rafraîchissement des données du tableau de bord.");
            }
        }


        function displayGlobalError(message) {
            console.error("Global Error:", message);
            alert("ERREUR: " + message);
        }

        function displayModalAlert(modalId, message, type = 'danger') {
            try {
                const alertSelector = '#' + modalId + ' .modal-alert';
                const alertElement = $(alertSelector);
                if (alertElement.length) {
                    alertElement.removeClass('alert-success alert-danger alert-warning alert-info').addClass('alert-' + type);
                    alertElement.html(message);
                    alertElement.show();
                     setTimeout(() => { alertElement.hide(); }, 5000);
                } else {
                    console.warn("Alert element not found for modal: " + modalId + ". Falling back to global alert.");
                    alert(type.toUpperCase() + ": " + message);
                }
            } catch (e) {
                console.error("Error in displayModalAlert:", e);
                alert("Notification: " + message);
            }
        }

        function updateActivitiesTable(activities) {
            try {
                const tbody = document.querySelector('#activities-table tbody');
                if (!tbody) { console.error("Activities table body not found."); return; }
                tbody.innerHTML = '';

                if (!activities || activities.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="4" style="text-align: center;">Aucune activité récente</td>';
                    tbody.appendChild(row);
                    return;
                }

                activities.forEach(activity => {
                    const row = document.createElement('tr');
                    row.innerHTML = "<td>" + escapeHtml(activity.employee_name) + "</td>" +
                                    "<td>" + escapeHtml(activity.action) + "</td>" +
                                    "<td>" + escapeHtml(activity.date) + "</td>" +
                                    "<td>" + escapeHtml(activity.hour) + "</td>";
                    tbody.appendChild(row);
                });
            } catch (e) {
                console.error("Error in updateActivitiesTable:", e);
            }
        }

        function loadPendingLeaveRequestsForModal() {
            try {
                const tbody = $('#congesAdminTableBody');
                if (!tbody.length) { console.error("Conges Admin table body not found."); return; }
                tbody.html('<tr><td colspan="7" style="text-align:center;">Chargement des demandes...</td></tr>');
                $('#congesAdminAlert').hide();

                $.ajax({
                    url: 'conges-handler.php',
                    type: 'POST',
                    data: { action: 'get_pending_requests' },
                    dataType: 'json',
                    success: function(response) {
                        tbody.empty();
                        if (response.status === 'success' && response.data && response.data.length > 0) {
                            response.data.forEach(function(req) {
                                let docLink = req.document ? '<a href="' + escapeHtml(String(req.document)) + '" target="_blank" class="btn btn-sm btn-outline-info py-0 px-1">Voir</a>' : 'Aucun';
                                let rowHtml = '<tr>' +
                                    '<td>' + escapeHtml(String(req.employee_name)) + '</td>' +
                                    '<td>' + escapeHtml(String(req.date_debut)) + ' - ' + escapeHtml(String(req.date_fin)) + '</td>' +
                                    '<td>' + escapeHtml(getLeaveTypeName(req.type_conge)) + '</td>' +
                                    '<td>' + escapeHtml(String(req.duree)) + 'j</td>' +
                                    '<td>' + docLink + '</td>' +
                                    '<td>' + escapeHtml(String(req.date_demande)) + '</td>' +
                                    '<td>' +
                                        '<button class="btn btn-success btn-sm py-0 px-1 action-button" onclick="approveLeaveFromModal(' + req.id + ')">Approuver</button>' +
                                        '<button class="btn btn-danger btn-sm ml-1 py-0 px-1 action-button" onclick="rejectLeaveFromModal(' + req.id + ')">Refuser</button>' +
                                        '<button class="btn btn-info btn-sm ml-1 py-0 px-1 action-button" onclick="showLeaveDetailsModal(' + req.id + ')">Détails</button>' +
                                    '</td>' +
                                '</tr>';
                                tbody.append(rowHtml);
                            });
                        } else if (response.status === 'success') {
                            tbody.html('<tr><td colspan="7" style="text-align:center;">Aucune demande en attente.</td></tr>');
                        } else {
                            displayModalAlert('congesAdminModal', response.message || 'Erreur lors du chargement des demandes.', 'danger');
                            tbody.html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur de chargement.</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX error loading pending leaves:", xhr.responseText);
                        displayModalAlert('congesAdminModal', 'Erreur de communication avec le serveur.', 'danger');
                        tbody.html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur de communication.</td></tr>');
                    }
                });
            } catch (e) {
                console.error("Error in loadPendingLeaveRequestsForModal:", e);
                $('#congesAdminTableBody').html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur interne du script.</td></tr>');
            }
        }
         function getLeaveTypeName(typeKey) {
            const types = {
                'cp': 'Congés Payés', 'rtt': 'RTT', 'sans-solde': 'Congé Sans Solde',
                'special': 'Congé Spécial', 'maladie': 'arrêt maladie'
            };
            return types[typeKey] || typeKey;
        }

        function showLeaveDetailsModal(leaveId) {
             $.ajax({
                url: 'conges-handler.php',
                type: 'POST',
                data: { action: 'get_details_for_admin', leave_id: leaveId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.data) {
                        const leave = response.data;
                        let detailsHtml = `
                            <p><strong>Employé:</strong> ${escapeHtml(leave.employee_name || 'N/A')}</p>
                            <p><strong>Dates:</strong> ${escapeHtml(leave.date_debut)} - ${escapeHtml(leave.date_fin)}</p>
                            <p><strong>Type de congé:</strong> ${escapeHtml(getLeaveTypeName(leave.type_conge))}</p>
                            <p><strong>Durée:</strong> ${escapeHtml(leave.duree)} jour(s)</p>
                            <p><strong>Date de demande:</strong> ${escapeHtml(leave.date_demande)}</p>
                            <p><strong>Statut:</strong> <span class="status-tag status-tag-modal status-${escapeHtml(leave.status)}">${escapeHtml(leave.status_display || leave.status)}</span></p>
                            <p><strong>Commentaire:</strong> ${escapeHtml(leave.commentaire || 'Aucun')}</p>
                            <p><strong>Document:</strong> ${leave.document ? `<a href="${escapeHtml(leave.document)}" target="_blank" class="document-link-modal">Voir Document</a>` : 'Aucun'}</p>
                        `;
                        if (leave.status !== 'pending') {
                            detailsHtml += `<p><strong>Date de réponse:</strong> ${escapeHtml(leave.date_reponse || 'N/A')}</p>`;
                            detailsHtml += `<p><strong>Commentaire de réponse:</strong> ${escapeHtml(leave.reponse_commentaire || 'Aucun')}</p>`;
                        }
                        $('#leaveDetailsModalBody').html(detailsHtml);
                        $('#leaveDetailsModal').modal('show');
                    } else {
                        displayModalAlert('congesAdminModal', response.message || "Erreur lors de la récupération des détails.", 'danger');
                    }
                },
                error: function() {
                     displayModalAlert('congesAdminModal', 'Erreur de communication pour les détails.', 'danger');
                }
            });
        }

        function approveLeaveFromModal(leaveId) {
             try {
                const commentaire = prompt("Commentaire pour l'approbation (optionnel):");
                $.ajax({
                    url: 'conges-handler.php',
                    type: 'POST',
                    data: { action: 'approve_request', leave_id: leaveId, commentaire: commentaire || '' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            displayModalAlert('congesAdminModal', response.message, 'success');
                            loadPendingLeaveRequestsForModal();
                            refreshDashboardData();
                        } else {
                            displayModalAlert('congesAdminModal', response.message || 'Erreur lors de l\'approbation.', 'danger');
                        }
                    },
                    error: function() { displayModalAlert('congesAdminModal', 'Erreur de communication.', 'danger'); }
                });
            } catch (e) {
                console.error("Error in approveLeaveFromModal:", e);
                displayModalAlert('congesAdminModal', 'Erreur interne du script.', 'danger');
            }
        }

        function rejectLeaveFromModal(leaveId) {
            try {
                const commentaire = prompt("Motif du refus (obligatoire):");
                if (commentaire === null) return;
                if (!commentaire.trim()) {
                    displayModalAlert('congesAdminModal', 'Un motif de refus est requis.', 'warning');
                    return;
                }
                $.ajax({
                    url: 'conges-handler.php',
                    type: 'POST',
                    data: { action: 'reject_request', leave_id: leaveId, commentaire: commentaire },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            displayModalAlert('congesAdminModal', response.message, 'success');
                            loadPendingLeaveRequestsForModal();
                            refreshDashboardData();
                        } else {
                            displayModalAlert('congesAdminModal', response.message || 'Erreur lors du refus.', 'danger');
                        }
                    },
                    error: function() { displayModalAlert('congesAdminModal', 'Erreur de communication.', 'danger'); }
                });
            } catch (e) {
                console.error("Error in rejectLeaveFromModal:", e);
                displayModalAlert('congesAdminModal', 'Erreur interne du script.', 'danger');
            }
        }

        $('#eventCreationForm').on('submit', function(e) {
            e.preventDefault();
            try {
                $('#eventCreationAlert').hide().removeClass('alert-success alert-danger alert-warning').text('');
                const formData = new FormData(this);
                formData.append('action', 'create_event');

                if (!formData.get('title') || !formData.get('start_datetime') || !formData.get('end_datetime') || formData.getAll('assigned_users[]').length === 0) {
                    displayModalAlert('eventCreationModal', 'Veuillez remplir tous les champs obligatoires (*).', 'warning');
                    return;
                }
                if (new Date(formData.get('end_datetime')) <= new Date(formData.get('start_datetime'))) {
                    displayModalAlert('eventCreationModal', 'La date de fin doit être postérieure à la date de début.', 'warning');
                    return;
                }

                $.ajax({
                    url: 'events_handler.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            displayModalAlert('eventCreationModal', response.message + (response.event_id ? ' (ID: ' + response.event_id + ')' : ''), 'success');
                            $('#eventCreationForm')[0].reset();
                            setTimeout(() => { $('#eventCreationModal').modal('hide'); }, 2000);
                        } else {
                            displayModalAlert('eventCreationModal', response.message || 'Erreur lors de la création de l\'événement.', 'danger');
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX error creating event:", xhr.responseText);
                        displayModalAlert('eventCreationModal', 'Erreur de communication avec le serveur.', 'danger');
                    }
                });
            } catch (e) {
                console.error("Error in eventCreationForm submit handler:", e);
                displayModalAlert('eventCreationModal', 'Erreur interne du script.', 'danger');
            }
        });

        function loadTimesheetDataForModal() {
            try {
                const employeeFilter = $('#timesheetEmployeeFilterModal');
                const monthFilter = $('#timesheetMonthFilterModal');
                const dayFilter = $('#timesheetDayFilterModal');
                const tbody = $('#timesheetTableBodyModal');

                if (!employeeFilter.length || !monthFilter.length || !dayFilter.length || !tbody.length) {
                    console.error("Modal filter or table body elements not found for Timesheet modal.");
                    if(tbody.length) tbody.html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur: Composants du modal non trouvés.</td></tr>');
                    return;
                }
                const employeeId = employeeFilter.val();
                const monthYearInput = monthFilter.val();
                const specificDay = dayFilter.val();
                tbody.html('<tr><td colspan="7" style="text-align:center;">Chargement...</td></tr>');

                let ajaxData = {
                    action: 'get_monthly_timesheet',
                    employee_id: employeeId
                };

                if (specificDay && specificDay !== '') {
                    ajaxData.specific_day = specificDay;
                } else if (monthYearInput && monthYearInput !== '') {
                    ajaxData.month_year = monthYearInput;
                } else {
                     tbody.html('<tr><td colspan="7" style="text-align:center; color:red;">Veuillez sélectionner un mois ou un jour.</td></tr>');
                     return;
                }

                $.ajax({
                    url: 'dashboard-handler.php',
                    type: 'GET',
                    data: ajaxData,
                    dataType: 'json',
                    success: function(response) {
                        tbody.empty();
                        if (response.status === 'success' && response.data && response.data.timesheet && response.data.timesheet.length > 0) {
                            response.data.timesheet.forEach(function(entry) {
                                let mapButtonHTML = '--';
                                if ((entry.logon_latitude && entry.logon_longitude) || (entry.logoff_latitude && entry.logoff_longitude)) {
                                     mapButtonHTML = '<button class="action-button btn-sm py-0 px-1" onclick="showTimesheetMapInDashboardModal(' + // Changed function name
                                        '\'' + escapeHtml(String(entry.employee_name !== undefined && entry.employee_name !== null ? entry.employee_name : '')) + '\',' +
                                        '\'' + escapeHtml(String(entry.entry_date !== undefined && entry.entry_date !== null ? entry.entry_date : '')) + '\',' +
                                        '\'' + String(entry.logon_latitude !== undefined && entry.logon_latitude !== null ? entry.logon_latitude : '') + '\',' +
                                        '\'' + String(entry.logon_longitude !== undefined && entry.logon_longitude !== null ? entry.logon_longitude : '') + '\',' +
                                        '\'' + escapeHtml(String(entry.logon_address || '')) + '\',' +
                                        '\'' + escapeHtml(String(entry.logon_time || '')) + '\',' +
                                        '\'' + String(entry.logoff_latitude !== undefined && entry.logoff_latitude !== null ? entry.logoff_latitude : '') + '\',' +
                                        '\'' + String(entry.logoff_longitude !== undefined && entry.logoff_longitude !== null ? entry.logoff_longitude : '') + '\',' +
                                        '\'' + escapeHtml(String(entry.logoff_address || '')) + '\',' +
                                        '\'' + escapeHtml(String(entry.logoff_time || '')) + '\'' +
                                    ')">Carte</button>';
                                }
                                let rowHTML = '<tr>' +
                                    '<td>' + mapButtonHTML + '</td>' +
                                    '<td>' + escapeHtml(String(entry.employee_name)) + '</td>' +
                                    '<td>' + escapeHtml(String(entry.entry_date)) + '</td>' +
                                    '<td>' + (escapeHtml(String(entry.logon_time || '--'))) + '</td>' +
                                    '<td>' + (escapeHtml(String(entry.logoff_time || '--'))) + '</td>' +
                                    '<td>' + (escapeHtml(String(entry.break_minutes || '0'))) + ' min</td>' +
                                    '<td>' + (escapeHtml(String(entry.duration || '--'))) + '</td>' +
                                '</tr>';
                                tbody.append(rowHTML);
                            });
                        } else if (response.status === 'success') {
                            tbody.html('<tr><td colspan="7" style="text-align:center;">Aucune donnée pour cette sélection.</td></tr>');
                        } else {
                            tbody.html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur: ' + escapeHtml(response.message || 'Impossible de charger les données.') + '</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX error loading timesheet for modal:", xhr.responseText);
                        tbody.html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur de communication.</td></tr>');
                    }
                });
            } catch (e) {
                console.error("Error in loadTimesheetDataForModal:", e);
                 $('#timesheetTableBodyModal').html('<tr><td colspan="7" style="text-align:center; color:red;">Erreur interne du script.</td></tr>');
            }
        }

        function loadAllLeavesForCongesAdminModal() {
            try {
                const employeeFilter = $('#caLeaveEmployeeFilter');
                const monthFilter = $('#caLeaveMonthFilter');
                const dayFilter = $('#caLeaveDayFilter');
                const tbody = $('#caListeCongesTableBody');

                if (!employeeFilter.length || !monthFilter.length || !dayFilter.length || !tbody.length) {
                    console.error("Filter or table body elements not found for 'Liste des Congés (Tous)' tab.");
                    if(tbody.length) tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur: Composants du modal non trouvés.</td></tr>');
                    return;
                }
                const employeeId = employeeFilter.val();
                const monthYearInput = monthFilter.val();
                const specificDay = dayFilter.val();
                tbody.html('<tr><td colspan="6" style="text-align:center;">Chargement...</td></tr>');

                let ajaxData = {
                    action: 'get_monthly_leaves',
                    employee_id: employeeId
                };

                if (specificDay && specificDay !== '') {
                    ajaxData.specific_day = specificDay;
                } else if (monthYearInput && monthYearInput !== '') {
                    ajaxData.month_year = monthYearInput;
                } else {
                     tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Veuillez sélectionner un mois ou un jour.</td></tr>');
                     return;
                }

                $.ajax({
                    url: 'dashboard-handler.php',
                    type: 'GET',
                    data: ajaxData,
                    dataType: 'json',
                    success: function(response) {
                        tbody.empty();
                        if (response.status === 'success' && response.data && response.data.leaves && response.data.leaves.length > 0) {
                            response.data.leaves.forEach(function(leave) {
                                 let rowHTML = '<tr>' +
                                    '<td>' + escapeHtml(String(leave.employee_name)) + '</td>' +
                                    '<td>' + escapeHtml(String(leave.type_conge_display)) + '</td>' +
                                    '<td>' + escapeHtml(String(leave.date_debut)) + '</td>' +
                                    '<td>' + escapeHtml(String(leave.date_fin)) + '</td>' +
                                    '<td>' + escapeHtml(String(leave.duree)) + ' jours</td>' +
                                    '<td><span class="status-tag status-' + escapeHtml(String(leave.status)) + '">' + escapeHtml(String(leave.status_display)) + '</span></td>' +
                                 '</tr>';
                                 tbody.append(rowHTML);
                            });
                        } else if (response.status === 'success') {
                            tbody.html('<tr><td colspan="6" style="text-align:center;">Aucune donnée pour cette sélection.</td></tr>');
                        } else {
                             tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur: ' + escapeHtml(response.message || 'Impossible de charger les données.') + '</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX error loading all leaves for Conges Admin modal:", xhr.responseText);
                        tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur de communication.</td></tr>');
                    }
                });
            } catch (e) {
                 console.error("Error in loadAllLeavesForCongesAdminModal:", e);
                 $('#caListeCongesTableBody').html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur interne du script.</td></tr>');
            }
        }

        // Renamed function for clarity, specific to dashboard's main map modal
        function showTimesheetMapInDashboardModal(employeeName, entryDate, latEntreeStr, lonEntreeStr, addrEntree, timeEntree, latSortieStr, lonSortieStr, addrSortie, timeSortie) {
            try {
                const modal = $('#mapModal');
                const mapContainer = document.getElementById('map-modal-content-container-main'); // Use new ID
                const mapTitleElem = document.getElementById('map-modal-title-main'); // Use new ID
                const mapDetailsElem = document.getElementById('map-modal-details-main'); // Use new ID


                if(!mapContainer || !mapTitleElem || !mapDetailsElem) {
                    console.error("Main map modal elements not found in dashboard."); return;
                }

                mapTitleElem.textContent = 'Localisation pour ' + escapeHtml(employeeName) + ' - ' + escapeHtml(entryDate);

                currentMapMarkers.forEach(marker => marker.remove());
                currentMapMarkers = [];
                if (map) { map.remove(); map = null; } // Use the global 'map' variable for this modal

                mapContainer.innerHTML = '';

                if (typeof L === 'undefined') {
                    mapContainer.innerHTML = "Erreur: La bibliothèque de cartographie (Leaflet) n'a pas pu être chargée.";
                    modal.modal('show');
                    return;
                }

                map = L.map(mapContainer); // Initialize the global 'map' variable
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                let latEntree = parseFloat(latEntreeStr);
                let lonEntree = parseFloat(lonEntreeStr);
                let latSortie = parseFloat(latSortieStr);
                let lonSortie = parseFloat(lonSortieStr);

                const validEntryCoords = !isNaN(latEntree) && !isNaN(lonEntree) && (latEntree !== 0 || lonEntree !== 0);
                const validExitCoords = !isNaN(latSortie) && !isNaN(lonSortie) && (latSortie !== 0 || lonSortie !== 0);
                let bounds = [];
                let detailsHTML = "";

                if (validEntryCoords) {
                    const entryMarker = L.marker([latEntree, lonEntree]).addTo(map)
                        .bindPopup('<b>Entrée:</b> ' + (escapeHtml(String(timeEntree)) || 'N/A') + '<br>' + (escapeHtml(String(addrEntree)) || 'Adresse non enregistrée'));
                    currentMapMarkers.push(entryMarker);
                    bounds.push([latEntree, lonEntree]);
                    detailsHTML += '<p><strong>Entrée ('+(escapeHtml(String(timeEntree)) || 'N/A')+'):</strong> ' + (escapeHtml(String(addrEntree)) || 'Lat: ' + latEntree.toFixed(5) + ', Lon: ' + lonEntree.toFixed(5)) + '</p>';
                } else {
                     detailsHTML += '<p><strong>Entrée ('+(escapeHtml(String(timeEntree)) || 'N/A')+'):</strong> Localisation non enregistrée.</p>';
                }

                if (validExitCoords) {
                    const exitMarker = L.marker([latSortie, lonSortie]).addTo(map)
                        .bindPopup('<b>Sortie:</b> ' + (escapeHtml(String(timeSortie)) || 'N/A') + '<br>' + (escapeHtml(String(addrSortie)) || 'Adresse non enregistrée'));
                    currentMapMarkers.push(exitMarker);
                    bounds.push([latSortie, lonSortie]);
                    detailsHTML += '<p><strong>Sortie ('+(escapeHtml(String(timeSortie)) || 'N/A')+'):</strong> ' + (escapeHtml(String(addrSortie)) || 'Lat: ' + latSortie.toFixed(5) + ', Lon: ' + lonSortie.toFixed(5)) + '</p>';
                } else {
                    detailsHTML += '<p><strong>Sortie ('+(escapeHtml(String(timeSortie)) || 'N/A')+'):</strong> Localisation non enregistrée.</p>';
                }

                mapDetailsElem.innerHTML = detailsHTML;

                if (validEntryCoords && validExitCoords && !(latEntree === latSortie && lonEntree === lonSortie)) {
                    L.polyline(bounds, {color: 'blue'}).addTo(map);
                }

                if (bounds.length > 0) {
                    if (bounds.length === 1) {
                        map.setView(bounds[0], 13);
                    } else {
                        map.fitBounds(bounds, { padding: [50, 50] });
                    }
                } else {
                    map.setView([48.8566, 2.3522], 5); // Default to Paris if no coords
                    mapDetailsElem.innerHTML = "<p>Aucune localisation GPS enregistrée pour cette entrée.</p>";
                }

                modal.modal('show');
                modal.on('shown.bs.modal', function () {
                    if (map) {
                        map.invalidateSize();
                    }
                });

            } catch (e) {
                console.error("Error in showTimesheetMapInDashboardModal:", e);
                 if(document.getElementById('map-modal-details-main')) document.getElementById('map-modal-details-main').innerHTML = "<p>Erreur lors de l'affichage de la carte.</p>";
                 $('#mapModal').modal('show');
            }
        }


        function cleanupMapResources() { // For the main map modal
             try {
                if (currentMapMarkers && currentMapMarkers.length > 0) {
                    currentMapMarkers.forEach(marker => marker.remove());
                    currentMapMarkers = [];
                }
                if (map) { // Use the global 'map' variable
                    map.off();
                    map.remove();
                    map = null;
                }
            } catch(e) {
                console.error("Error during map resource cleanup:", e);
            }
        }

        function exportTableToCSV(tableId, filename) {
            try {
                const table = document.getElementById(tableId);
                if (!table) { displayGlobalError("Erreur d'exportation: Table #" + tableId + " non trouvée."); return; }
                let csv = [];
                const rows = table.querySelectorAll("tr");

                for (const row of rows) {
                    const rowData = [];
                    const cols = row.querySelectorAll("td, th");
                    for (const col of cols) {
                        let cellContent = col.cloneNode(true);
                        // Remove buttons before extracting text
                        cellContent.querySelectorAll('button.action-button, button.btn, a.btn').forEach(el => el.remove());
                        // Handle status tags - extract text content
                        cellContent.querySelectorAll('.status-tag').forEach(el => {
                            const statusText = el.textContent || el.innerText;
                            el.parentNode.insertBefore(document.createTextNode(statusText), el);
                            el.remove();
                        });
                        let text = cellContent.innerText.trim().replace(/"/g, '""'); // Escape double quotes
                        rowData.push('"' + text + '"');
                    }
                    csv.push(rowData.join(","));
                }
                if (csv.length === 0 || (csv.length === 1 && rows[0].querySelectorAll("th").length > 0 && rows.length === 1) ) { // Check if only header or empty
                    displayGlobalError("Aucune donnée à exporter de la table #" + tableId + ".");
                    return;
                }
                const csvFile = new Blob(["\uFEFF" + csv.join("\n")], { type: "text/csv;charset=utf-8;" }); // Add BOM for Excel
                const downloadLink = document.createElement("a");
                downloadLink.download = filename;
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = "none";
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            } catch (e) {
                console.error("Error in exportTableToCSV:", e);
                displayGlobalError("Erreur lors de la préparation de l'exportation CSV.");
            }
        }


        // Scripts from employes.php, adapted for dashboard integration
        const initialEmployeeData = <?php echo json_encode($initial_employee_list); ?>;
        const currentUserRole = '<?php echo $user['role']; // Use the user role from dashboard's PHP scope ?>';

        document.addEventListener('DOMContentLoaded', function() {
            try {
                refreshDashboardData(); // This will now call fetchEmployeeStats as well

                 // Event listeners for modals from original dashboard.php
                $('#congesAdminModal').on('show.bs.modal', function () {
                    loadPendingLeaveRequestsForModal();
                    $('#caLeaveEmployeeFilter').val('');
                    $('#caLeaveMonthFilter').val('<?php echo date('Y-m'); ?>');
                    $('#caLeaveDayFilter').val('');
                    $('#caListeCongesTableBody').html('<tr><td colspan="6" style="text-align:center;">Sélectionnez les filtres pour afficher la liste.</td></tr>');
                });

                $('a[data-toggle="tab"][href="#listAllLeavesContent"]').on('shown.bs.tab', function (e) {
                    loadAllLeavesForCongesAdminModal();
                });

                $('#caLeaveEmployeeFilter, #caLeaveMonthFilter, #caLeaveDayFilter').on('change', loadAllLeavesForCongesAdminModal);
                 $('#caLeaveMonthFilter').on('change', function() { // If month changes, clear specific day
                    $('#caLeaveDayFilter').val('');
                });


                $('#feuilleDeTempsModal').on('show.bs.modal', function () {
                    $('#timesheetEmployeeFilterModal').val('');
                    $('#timesheetMonthFilterModal').val('<?php echo date('Y-m'); ?>');
                    $('#timesheetDayFilterModal').val('');
                    loadTimesheetDataForModal();
                });

                 $('#eventCreationModal').on('show.bs.modal', function () {
                    $('#eventCreationForm')[0].reset();
                    $('#eventCreationAlert').hide().removeClass('alert-success alert-danger alert-warning').text('');
                    // Set default start and end times for event creation
                    const now = new Date();
                    // Default start: next hour, rounded to 00 minutes
                    const startDateTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() + 1);
                    // Default end: 1 hour after start
                    const endDateTime = new Date(startDateTime.getTime() + (60 * 60 * 1000));

                    function formatDateTimeLocal(date) {
                        const year = date.getFullYear();
                        const month = (date.getMonth() + 1).toString().padStart(2, '0');
                        const day = date.getDate().toString().padStart(2, '0');
                        const hours = date.getHours().toString().padStart(2, '0');
                        const minutes = date.getMinutes().toString().padStart(2, '0');
                        return `${year}-${month}-${day}T${hours}:${minutes}`;
                    }

                    $('#eventStartModal').val(formatDateTimeLocal(startDateTime));
                    $('#eventEndModal').val(formatDateTimeLocal(endDateTime));
                    $('#eventColorModal').val('#007bff'); // Default color
                });


                $('#timesheetEmployeeFilterModal, #timesheetMonthFilterModal, #timesheetDayFilterModal').on('change', loadTimesheetDataForModal);
                $('#timesheetMonthFilterModal').on('change', function() { // If month changes, clear specific day
                    $('#timesheetDayFilterModal').val('');
                });

                // Map modal close handling (for the main map modal)
                $('#mapModal').on('hidden.bs.modal', function () {
                    cleanupMapResources();
                });


                // Initialization for integrated employes section
                fetchEmployeeStats();
                showInitialEmployeeList(); // Display the general list by default in the integrated section

            } catch (e) {
                console.error("Error in DOMContentLoaded (Dashboard):", e);
                displayGlobalError("Erreur lors de l'initialisation de la page du tableau de bord.");
            }
        });

        // Functions for integrated employes section (from employes.php)
        function fetchEmployeeStats() {
            const statsContainer = document.getElementById('employee-stats-container');
            if (!statsContainer) return;
            statsContainer.innerHTML = `<div class="loading-placeholder"><div class="spinner-border spinner-border-sm"></div> Chargement...</div>`;

            fetch('employee_handler.php?action=get_employee_overview_stats')
            .then(response => {
                if (!response.ok) return response.text().then(text => {
                    console.error("Fetch stats raw error response:", text);
                    throw new Error('Network error: ' + response.status + ' ' + text.substring(0,100))
                });
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.stats) {
                    renderStats(data.stats, data.role || currentUserRole);
                } else {
                    statsContainer.innerHTML = '<div class="error-placeholder">Erreur: ' + (data.message || 'Impossible de charger les stats.') + '</div>';
                }
            })
            .catch(error => {
                console.error('Fetch stats error:', error);
                statsContainer.innerHTML = '<div class="error-placeholder">Erreur de communication: ' + error.message + '</div>';
            });
        }

        function renderStats(stats, userRoleForStats) {
            const statsContainer = document.getElementById('employee-stats-container');
            if (!statsContainer) return;
            statsContainer.innerHTML = '';
            const isAdmin = userRoleForStats === 'admin';
            let html = '';

            const statTypes = [
                { key: 'total_employees', label: 'Total Employés Actifs', icon: 'fas fa-users', adminOnly: true, cssClass: 'total-employees', clickableIfZero: true },
                { key: 'assigned_today', label: "Assignés Aujourd'hui (Planning)", icon: 'fas fa-calendar-check', adminOnly: true, cssClass: 'assigned-today', clickableIfZero: true },
                { key: 'active_today', label: "En Activité (Pointage)", icon: 'fas fa-clipboard-check', adminOnly: false, cssClass: 'active-today', clickableIfZero: true },
                { key: 'on_generic_leave_today', label: "En Congé (Autre)", icon: 'fas fa-plane-departure', adminOnly: true, cssClass: 'on-generic-leave-today', clickableIfZero: true },
                { key: 'on_sick_leave_today', label: "En Arrêt Maladie", icon: 'fas fa-briefcase-medical', adminOnly: true, cssClass: 'on-sick-leave-today', clickableIfZero: true }
            ];

            statTypes.forEach(type => {
                if (stats[type.key] !== undefined && (!type.adminOnly || isAdmin)) {
                    const count = parseInt(stats[type.key], 10) || 0;
                    const canBeClickedByRole = type.adminOnly ? isAdmin : true;
                    const isClickable = canBeClickedByRole && (count > 0 || type.clickableIfZero);
                    const escapedLabelForJSString = type.label.replace(/'/g, "\\'");
                    const clickHandler = isClickable ? `loadFilteredEmployeeList('${type.key}', '${escapedLabelForJSString}')` : (type.key === 'total_employees' && isAdmin ? `showInitialEmployeeList()` : '');
                    const cardClass = `stat-card ${type.cssClass} ${(isClickable || (type.key === 'total_employees' && isAdmin)) ? 'clickable' : ''}`;

                    html += `<div class="${cardClass}" ${clickHandler ? `onclick="${clickHandler}"` : ''}>
                                <div class="stat-icon"><i class="${type.icon}"></i></div>
                                <div class="stat-value">${count}</div>
                                <div class="stat-label">${type.label}</div>
                             </div>`;
                }
            });
            if (html === '') {
                html = '<div class="info-placeholder w-100 text-center">Aucune statistique à afficher pour votre rôle.</div>';
            }
            statsContainer.innerHTML = html;
        }

        function showInitialEmployeeList() {
            const titleEl = document.getElementById('employeeListTitle');
            const backButton = document.getElementById('backToListButton');
            if (titleEl) titleEl.textContent = 'Liste Générale des Employés Actifs';
            if (backButton) backButton.style.display = 'none';
            renderEmployeeTable(initialEmployeeData, 'total_employees');
        }

        function loadFilteredEmployeeList(statType, title) {
            const titleEl = document.getElementById('employeeListTitle');
            const backButton = document.getElementById('backToListButton');
            if (titleEl) titleEl.textContent = title;
            if (backButton) {
                if (statType !== 'total_employees') {
                    backButton.style.display = 'inline-block';
                } else {
                    backButton.style.display = 'none';
                }
            }

            const tableBody = document.getElementById('employees-table-body');
            if (!tableBody) return;

            let colspan = 4;
            const dynamicColspanTypes = ['assigned_today', 'on_generic_leave_today', 'on_sick_leave_today'];
            if (dynamicColspanTypes.includes(statType)) {
                colspan = 5;
            }

            setTableHeaders(statType);
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="loading-placeholder"><div class="spinner-border spinner-border-sm"></div> Chargement de la liste...</td></tr>`;

            fetch(`employee_handler.php?action=get_employee_list_for_stat&type=${statType}`)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error('Network error: ' + response.status + ' ' + text.substring(0,200));
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.employees) {
                        renderEmployeeTable(data.employees, statType);
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="${colspan}" class="error-placeholder">Erreur: ${data.message || 'Impossible de charger la liste.'}</td></tr>`;
                    }
                })
                .catch(error => {
                    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="error-placeholder">Erreur de communication: ${error.message}</td></tr>`;
                });
        }

        function setTableHeaders(statType) {
            const tableHead = document.getElementById('employees-table-head');
            if (!tableHead) return;
            let headers = '<tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th>';
            if (statType === 'assigned_today') {
                headers += '<th>Mission (Planning)</th>';
            } else if (statType === 'on_generic_leave_today' || statType === 'on_sick_leave_today') {
                headers += '<th>Type de Congé</th>';
            }
            headers += '</tr>';
            tableHead.innerHTML = headers;
        }

        function renderEmployeeTable(employees, statType) {
            const tableBody = document.getElementById('employees-table-body');
            if (!tableBody) return;

            tableBody.innerHTML = '';
            setTableHeaders(statType);

            let colspan = 4;
            const dynamicColspanTypes = ['assigned_today', 'on_generic_leave_today', 'on_sick_leave_today'];
            if (dynamicColspanTypes.includes(statType)) {
                colspan = 5;
            }

            if (!employees || employees.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${colspan}" class="info-placeholder">Aucun employé ne correspond à ce critère.</td></tr>`;
                return;
            }

            let rowsHtml = '';
            employees.forEach(emp => {
                let rowHtml = `<tr>
                                <td>${emp.nom ? escapeHtml(emp.nom) : 'N/A'}</td>
                                <td>${emp.prenom ? escapeHtml(emp.prenom) : 'N/A'}</td>
                                <td>${emp.email ? escapeHtml(emp.email) : 'N/A'}</td>
                                <td>${emp.role ? escapeHtml(ucfirst(emp.role)) : 'N/A'}</td>`;
                if (statType === 'assigned_today') {
                    rowHtml += `<td>${emp.mission ? escapeHtml(emp.mission) : 'N/A'}</td>`;
                } else if (statType === 'on_generic_leave_today' || statType === 'on_sick_leave_today') {
                    rowHtml += `<td>${emp.type_conge_display ? escapeHtml(emp.type_conge_display) : (emp.type_conge ? escapeHtml(ucfirst(emp.type_conge)) : 'N/A')}</td>`;
                }
                rowHtml += `</tr>`;
                rowsHtml += rowHtml;
            });
            tableBody.innerHTML = rowsHtml;
        }

        function ucfirst(str) {
            if (typeof str !== 'string' || str.length === 0) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }

    </script>
</body>
</html>
