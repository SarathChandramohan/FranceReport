<?php
// 1. Include session management and database connection
require_once 'session-management.php';
require_once 'db-connection.php';

// 2. Require login - This will redirect the user if not logged in
requireLogin();

// 3. Get current user info
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    // If not admin, redirect to a non-admin page or show an error
    header('Location: timesheet.php'); // Or an appropriate page
    exit;
}

// 4. Get dashboard statistics
function getDashboardStats($conn) {
    $stats = [];
    try {
        $today = date('Y-m-d');
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
            LEFT JOIN Conges c ON u.user_id = c.user_id AND :today BETWEEN c.date_debut AND c.date_fin AND c.status = 'approved'
            WHERE u.status = 'Active' AND t.timesheet_id IS NULL AND c.conge_id IS NULL
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
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return ['employees_present' => 0, 'employees_absent' => 0, 'pending_requests' => 0];
    }
}

// 5. Get recent activities
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

// 6. Get all employees for filter dropdowns
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

// 7. Get statistics, activities, and employees
$stats = getDashboardStats($conn);
$activities = getRecentActivities($conn);
$all_employees = getAllEmployees($conn);

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 20px; 
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #e5e5e5; 
        }
        .stat-card-title {
            font-size: 15px; 
            color: #6e6e73; 
            margin-bottom: 8px; 
            font-weight: 500;
            text-align: center;
        }
        .stat-card-value {
            font-size: 38px; 
            font-weight: 600;
            color: #1d1d1f; 
        }
        .stat-card.present .stat-card-value { color: #34c759; } 
        .stat-card.absent .stat-card-value { color: #ff3b30; } 
        .stat-card.pending .stat-card-value { color: #ff9500; } 

        .content-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            border: 1px solid #e5e5e5;
            margin-bottom: 30px;
        }
        h2 {
            margin-bottom: 20px; 
            color: #1d1d1f; 
            font-size: 22px; 
            font-weight: 600;
        }
        .filter-controls { 
            margin-bottom: 20px;
            display: flex;
            gap: 10px; 
            align-items: center;
            flex-wrap: wrap; 
        }
        .filter-controls label {
            font-weight: 500;
            color: #1d1d1f;
            font-size: 14px;
            margin-right: 5px;
            white-space: nowrap;
        }
        .filter-controls .form-control-sm {
            padding: 0.3rem 0.6rem; /* Slightly more padding */
            font-size: 14px; /* Ensure readability */
            border-radius: 8px; 
            border: 1px solid #d2d2d7; 
            background-color: #f5f5f7; 
            height: auto; 
            line-height: 1.5;
        }
        .filter-controls select.form-control-sm {
            min-width: 220px; /* Increased min-width */
            flex-basis: 220px; 
            flex-grow: 1;   
        }
        .filter-controls input[type="month"].form-control-sm,
        .filter-controls input[type="date"].form-control-sm {
            min-width: 160px; 
            flex-basis: auto; /* Let it size based on content or min-width */
            flex-grow: 0; 
        }
         .filter-controls > * {
            margin-bottom: 5px; 
            margin-right: 10px; /* Space between filter elements */
        }
        .filter-controls .export-button {
            margin-left: auto; /* Pushes button to the right if space allows */
            margin-right: 0; /* Remove right margin for the last item */
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

        @media (max-width: 768px) {
            h1 { font-size: 24px; }
            .stat-card { padding: 15px; }
            .stat-card-value { font-size: 32px; }
            .content-card { padding: 20px; }
            h2 { font-size: 20px; }
            table th, table td { padding: 10px 12px; font-size: 13px; }
            .filter-controls { flex-direction: column; align-items: stretch; }
            .filter-controls select.form-control-sm, 
            .filter-controls input[type="month"].form-control-sm,
            .filter-controls input[type="date"].form-control-sm {
                width: 100%; margin-bottom: 10px; margin-right: 0;
                min-width: 0; /* Override min-width for stacked layout */
            }
             .filter-controls .export-button { margin-left: 0; width: 100%;}

            .shortcut-buttons-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;}
            .shortcut-btn { padding: 15px; font-size: 0.8rem;}
            .shortcut-btn i { font-size: 1.8em;}
            .modal-content { margin: 10% auto; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; } 
            .stat-card-value { font-size: 28px; }
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

    <div class="container">
        <h1>Tableau de bord Administrateur</h1>

        <div class="shortcut-buttons-grid">
            <button class="shortcut-btn" data-toggle="modal" data-target="#congesAdminModal">
                <i class="fas fa-user-shield"></i>Congés Admin
            </button>
            <a href="employes.php" class="shortcut-btn">
                <i class="fas fa-users"></i>Employés
            </a>
            <a href="planning.php" class="shortcut-btn">
                <i class="fas fa-calendar-alt"></i>Planning Admin
            </a>
            <button class="shortcut-btn" data-toggle="modal" data-target="#eventCreationModal">
                <i class="fas fa-plus-circle"></i>Créer Événement
            </button>
            <button class="shortcut-btn" data-toggle="modal" data-target="#feuilleDeTempsModal">
                <i class="fas fa-user-clock"></i>Feuille de Temps
            </button>
            <button class="shortcut-btn" data-toggle="modal" data-target="#listeCongesModal">
                <i class="fas fa-list-alt"></i>Liste Congés
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card present">
                <div class="stat-card-title">Employés présents</div>
                <div class="stat-card-value" id="stats-employees-present"><?php echo htmlspecialchars($stats['employees_present']); ?></div>
            </div>
            <div class="stat-card absent">
                <div class="stat-card-title">Employés absents</div>
                <div class="stat-card-value" id="stats-employees-absent"><?php echo htmlspecialchars($stats['employees_absent']); ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-card-title">Demandes de congé en attente</div>
                <div class="stat-card-value" id="stats-pending-requests"><?php echo htmlspecialchars($stats['pending_requests']); ?></div>
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

    <div id="mapModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeMapModal()">&times;</span>
            <h3 id="map-modal-title">Localisation Pointage</h3>
            <div id="map-modal-content-container"></div>
            <div id="map-modal-details"></div>
        </div>
    </div>

    <div class="modal fade" id="congesAdminModal" tabindex="-1" aria-labelledby="congesAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="congesAdminModalLabel">Administration des Congés - Demandes en Attente</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
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
                        <label for="timesheetEmployeeFilterModal">Employé:</label>
                        <select id="timesheetEmployeeFilterModal" class="form-control form-control-sm">
                            <option value="">Tous</option>
                            <?php foreach ($all_employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                                    <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="timesheetMonthFilterModal">Mois:</label>
                        <input type="month" id="timesheetMonthFilterModal" class="form-control form-control-sm" value="<?php echo date('Y-m'); ?>">
                        <label for="timesheetDayFilterModal">Jour:</label>
                        <input type="date" id="timesheetDayFilterModal" class="form-control form-control-sm">
                        <button class="export-button btn-sm" onclick="exportTableToCSV('timesheetTableModal', 'feuille_de_temps_modal.csv')">Exporter CSV</button>
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

    <div class="modal fade" id="listeCongesModal" tabindex="-1" aria-labelledby="listeCongesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="listeCongesModalLabel">Liste des Congés</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                     <div class="filter-controls">
                        <label for="leaveEmployeeFilterModal">Employé:</label>
                        <select id="leaveEmployeeFilterModal" class="form-control form-control-sm">
                            <option value="">Tous</option>
                             <?php foreach ($all_employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                                    <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="leaveMonthFilterModal">Mois:</label>
                        <input type="month" id="leaveMonthFilterModal" class="form-control form-control-sm" value="<?php echo date('Y-m'); ?>">
                        <label for="leaveDayFilterModal">Jour:</label>
                        <input type="date" id="leaveDayFilterModal" class="form-control form-control-sm">
                        <button class="export-button btn-sm" onclick="exportTableToCSV('leaveTableModal', 'liste_conges_modal.csv')">Exporter CSV</button>
                    </div>
                    <div class="table-container">
                        <table id="leaveTableModal" class="table table-striped table-sm">
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
                            <tbody id="leaveTableBodyModal"></tbody>
                        </table>
                    </div>
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
        // (JavaScript from the previous response, with modifications to how ajaxData is built)
        // ... (all the JS functions: refreshDashboardData, displayGlobalError, displayModalAlert, updateActivitiesTable, etc.)

        let map; 
        let currentMapMarkers = []; 

        function refreshDashboardData() {
            // ... (same as previous version)
        }
        
        function displayGlobalError(message) {
            // ... (same as previous version)
        }

        function displayModalAlert(modalId, message, type = 'danger') {
            // ... (same as previous version)
        }

        function updateActivitiesTable(activities) {
            // ... (same as previous version)
        }
        
        function loadPendingLeaveRequestsForModal() {
            // ... (same as previous version, ensure try...catch)
        }

        function approveLeaveFromModal(leaveId) {
            // ... (same as previous version, ensure try...catch)
        }

        function rejectLeaveFromModal(leaveId) {
            // ... (same as previous version, ensure try...catch)
        }
        
        $('#eventCreationForm').on('submit', function(e) {
            // ... (same as previous version, ensure try...catch)
        });

        // MODIFIED loadTimesheetDataForModal
        function loadTimesheetDataForModal() {
            try {
                const employeeFilter = $('#timesheetEmployeeFilterModal');
                const monthFilter = $('#timesheetMonthFilterModal');
                const dayFilter = $('#timesheetDayFilterModal');
                const tbody = $('#timesheetTableBodyModal');

                if (!employeeFilter.length || !monthFilter.length || !dayFilter.length || !tbody.length) {
                    console.error("Modal filter or table body elements not found for Timesheet modal.");
                    if(tbody.length) tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur: Composants du modal non trouvés.</td></tr>');
                    return;
                }
                const employeeId = employeeFilter.val();
                const monthYearInput = monthFilter.val();
                const specificDay = dayFilter.val();
                tbody.html('<tr><td colspan="6" style="text-align:center;">Chargement...</td></tr>');

                let ajaxData = {
                    action: 'get_monthly_timesheet',
                    employee_id: employeeId
                };

                if (specificDay && specificDay !== '') {
                    ajaxData.specific_day = specificDay;
                    // If a specific day is chosen, we don't need monthYear for filtering,
                    // but the handler might still expect it for context or if day is invalid.
                    // For now, let's keep sending monthYear for simplicity in handler.
                    ajaxData.month_year = monthYearInput; // Or derive from specificDay
                } else if (monthYearInput && monthYearInput !== '') {
                    ajaxData.month_year = monthYearInput;
                } else {
                     tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Veuillez sélectionner un mois.</td></tr>');
                     return;
                }


                $.ajax({
                    url: 'dashboard-handler.php',
                    type: 'GET', 
                    data: ajaxData, // Use the constructed ajaxData
                    dataType: 'json',
                    success: function(response) {
                        tbody.empty();
                        if (response.status === 'success' && response.data && response.data.timesheet && response.data.timesheet.length > 0) {
                            response.data.timesheet.forEach(function(entry) {
                                let mapButtonHTML = (entry.logon_latitude && entry.logon_longitude) || (entry.logoff_latitude && entry.logoff_longitude) ?
                                    \`<button class="action-button btn-sm py-0 px-1" onclick="showTimesheetMapModal(
                                        '\${escapeHtml(entry.employee_name)}', 
                                        '\${escapeHtml(entry.entry_date)}',
                                        '\${entry.logon_latitude}', '\${entry.logon_longitude}', '\${escapeHtml(entry.logon_address)}', '\${escapeHtml(entry.logon_time || '')}',
                                        '\${entry.logoff_latitude}', '\${entry.logoff_longitude}', '\${escapeHtml(entry.logoff_address)}', '\${escapeHtml(entry.logoff_time || '')}'
                                    )">Carte</button>\` : '--';
                                tbody.append(\`
                                    <tr>
                                        <td>\${mapButtonHTML}</td>
                                        <td>\${escapeHtml(entry.employee_name)}</td>
                                        <td>\${escapeHtml(entry.entry_date)}</td>
                                        <td>\${escapeHtml(entry.logon_time) || '--'}</td>
                                        <td>\${escapeHtml(entry.logoff_time) || '--'}</td>
                                        <td>\${escapeHtml(entry.duration) || '--'}</td>
                                    </tr>\`);
                            });
                        } else if (response.status === 'success') {
                            tbody.html('<tr><td colspan="6" style="text-align:center;">Aucune donnée pour cette sélection.</td></tr>');
                        } else {
                            tbody.html(\`<tr><td colspan="6" style="text-align:center; color:red;">Erreur: \${escapeHtml(response.message || 'Impossible de charger les données.')}</td></tr>\`);
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX error loading timesheet for modal:", xhr.responseText);
                        tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur de communication.</td></tr>');
                    }
                });
            } catch (e) {
                console.error("Error in loadTimesheetDataForModal:", e);
                 $('#timesheetTableBodyModal').html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur interne du script.</td></tr>');
            }
        }
        
        // MODIFIED loadLeaveDataForModal
        function loadLeaveDataForModal() {
            try {
                const employeeFilter = $('#leaveEmployeeFilterModal');
                const monthFilter = $('#leaveMonthFilterModal');
                const dayFilter = $('#leaveDayFilterModal');
                const tbody = $('#leaveTableBodyModal');

                if (!employeeFilter.length || !monthFilter.length || !dayFilter.length || !tbody.length) {
                    console.error("Modal filter or table body elements not found for Leave modal.");
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
                    ajaxData.month_year = monthYearInput; // Keep sending month for context if day is chosen
                } else if (monthYearInput && monthYearInput !== '') {
                    ajaxData.month_year = monthYearInput;
                } else {
                     tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Veuillez sélectionner un mois.</td></tr>');
                     return;
                }

                $.ajax({
                    url: 'dashboard-handler.php',
                    type: 'GET',
                    data: ajaxData, // Use the constructed ajaxData
                    dataType: 'json',
                    success: function(response) {
                        tbody.empty();
                        if (response.status === 'success' && response.data && response.data.leaves && response.data.leaves.length > 0) {
                            response.data.leaves.forEach(function(leave) {
                                 tbody.append(\`
                                    <tr>
                                        <td>\${escapeHtml(leave.employee_name)}</td>
                                        <td>\${escapeHtml(leave.type_conge_display)}</td>
                                        <td>\${escapeHtml(leave.date_debut)}</td>
                                        <td>\${escapeHtml(leave.date_fin)}</td>
                                        <td>\${escapeHtml(leave.duree)} jours</td>
                                        <td><span class="status-tag status-\${escapeHtml(leave.status)}">\${escapeHtml(leave.status_display)}</span></td>
                                    </tr>\`);
                            });
                        } else if (response.status === 'success') {
                            tbody.html('<tr><td colspan="6" style="text-align:center;">Aucune donnée pour cette sélection.</td></tr>');
                        } else {
                             tbody.html(\`<tr><td colspan="6" style="text-align:center; color:red;">Erreur: \${escapeHtml(response.message || 'Impossible de charger les données.')}</td></tr>\`);
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX error loading leaves for modal:", xhr.responseText);
                        tbody.html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur de communication.</td></tr>');
                    }
                });
            } catch (e) {
                 console.error("Error in loadLeaveDataForModal:", e);
                 $('#leaveTableBodyModal').html('<tr><td colspan="6" style="text-align:center; color:red;">Erreur interne du script.</td></tr>');
            }
        }

        // ... (showTimesheetMapModal, closeMapModal, escapeHtml, exportTableToCSV functions remain the same as previous response)
        function showTimesheetMapModal(employeeName, entryDate, latEntreeStr, lonEntreeStr, addrEntree, timeEntree, latSortieStr, lonSortieStr, addrSortie, timeSortie) {
            // ... (Implementation from previous response)
        }

        function closeMapModal() {
            // ... (Implementation from previous response)
        }

        function escapeHtml(text) {
            // ... (Implementation from previous response)
        }

        function exportTableToCSV(tableId, filename) {
            // ... (Implementation from previous response)
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            try {
                refreshDashboardData(); 
                
                $('#congesAdminModal').on('show.bs.modal', function () {
                    loadPendingLeaveRequestsForModal();
                });

                $('#feuilleDeTempsModal').on('show.bs.modal', function () {
                    $('#timesheetEmployeeFilterModal').val(''); 
                    $('#timesheetMonthFilterModal').val('<?php echo date('Y-m'); ?>'); 
                    $('#timesheetDayFilterModal').val(''); 
                    loadTimesheetDataForModal();
                });

                $('#listeCongesModal').on('show.bs.modal', function () {
                    $('#leaveEmployeeFilterModal').val(''); 
                    $('#leaveMonthFilterModal').val('<?php echo date('Y-m'); ?>'); 
                    $('#leaveDayFilterModal').val(''); 
                    loadLeaveDataForModal();
                });

                $('#eventCreationModal').on('show.bs.modal', function () {
                    $('#eventCreationForm')[0].reset();
                    $('#eventCreationAlert').hide().removeClass('alert-success alert-danger alert-warning').text('');
                    const now = new Date();
                    const startDateTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() + 1);
                    const endDateTime = new Date(startDateTime.getTime() + 60 * 60 * 1000); 
                    
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
                    $('#eventColorModal').val('#007bff');
                });

                $('#timesheetEmployeeFilterModal, #timesheetMonthFilterModal, #timesheetDayFilterModal').on('change', loadTimesheetDataForModal);
                $('#leaveEmployeeFilterModal, #leaveMonthFilterModal, #leaveDayFilterModal').on('change', loadLeaveDataForModal);

                $('#timesheetMonthFilterModal').on('change', function() {
                    $('#timesheetDayFilterModal').val(''); 
                });
                $('#leaveMonthFilterModal').on('change', function() {
                    $('#leaveDayFilterModal').val(''); 
                });

            } catch (e) {
                console.error("Error in DOMContentLoaded:", e);
                displayGlobalError("Erreur lors de l'initialisation de la page.");
            }
        });

        window.onclick = function(event) {
            const mapModalElement = document.getElementById('mapModal');
            if (event.target == mapModalElement) { 
                closeMapModal(); 
            }
        }
    </script>
</body>
</html>
