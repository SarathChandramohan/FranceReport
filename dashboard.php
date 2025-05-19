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
        // Get current date in SQL Server format (YYYY-MM-DD)
        $today = date('Y-m-d');
        
        // Count employees present today
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS present_count
            FROM Timesheet
            WHERE entry_date = :today AND logon_time IS NOT NULL AND 
                  (logoff_time IS NULL OR CAST(logoff_time AS DATE) = :today_alt)
        ");
        $stmt->execute([':today' => $today, ':today_alt' => $today]);
        $stats['employees_present'] = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'] ?? 0;
        
        // Count employees absent
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT u.user_id) AS absent_count
            FROM Users u
            LEFT JOIN Timesheet t ON u.user_id = t.user_id AND t.entry_date = :today
            WHERE u.status = 'Active' AND t.timesheet_id IS NULL
        ");
        $stmt->execute([':today' => $today]);
        $stats['employees_absent'] = $stmt->fetch(PDO::FETCH_ASSOC)['absent_count'] ?? 0;
        
        // Count pending leave requests for the current month
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
        // More robust query for recent activities from both tables
        $stmt = $conn->prepare("
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
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedActivities = [];
        foreach ($activities as $activity) {
            $timestamp = strtotime($activity['action_time']);
            $formattedActivities[] = [
                'employee_name' => htmlspecialchars($activity['employee_name']),
                'action' => htmlspecialchars($activity['action']),
                'date' => date('d/m/Y', $timestamp),
                'hour' => date('H:i', $timestamp)
            ];
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
    <style>
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body {
            background-color: #f5f5f7; /* Light gray background */
            color: #1d1d1f; /* Default dark text */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding-bottom: 30px;
        }

        /* Container - Modified for fluid layout */
        .container {
            width: 100%;          /* Make it take the full available width */
            margin: 0;            /* Remove auto margins */
            padding: 25px;        /* Keep padding for content spacing from edges */
        }


        /* Title styling */
        h1 {
            color: #1d1d1f; /* Darker text for main title */
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Stat Card Styling */
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


        /* Card for tables and other content */
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
        }

        .filter-controls select, .filter-controls input[type="month"] {
            padding: 8px 12px;
            border-radius: 8px; 
            border: 1px solid #d2d2d7; 
            font-size: 14px;
            background-color: #f5f5f7; 
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
            margin-left: 5px; 
        }
        
        .export-button:hover {
            background-color: #2ca048; 
        }


        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border: 1px solid #e5e5e5; 
            border-radius: 8px; 
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px; /* Adjusted due to removed columns */
        }

        table th, table td {
            padding: 12px 15px; 
            text-align: left;
            border-bottom: 1px solid #e5e5e5; 
            font-size: 14px;
            color: #1d1d1f;
        }
        table td { color: #555; } 


        table th {
            background-color: #f9f9f9; 
            font-weight: 600; 
            color: #333; 
            border-bottom-width: 2px; 
        }

        table tr:last-child td {
            border-bottom: none; 
        }

        table tr:hover {
            background-color: #f0f0f0; 
        }
        
        .action-button { 
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            background-color: #007aff; 
            color: white;
            font-size: 13px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 3px; 
            display: inline-block; 
        }
        .action-button:hover {
            background-color: #0056b3;
        }

        /* Status Tags for Leave Table */
        .status-tag {
            display: inline-block;
            padding: 4px 10px; 
            border-radius: 12px; 
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            color: white; 
        }
        .status-tag.status-pending { background-color: #ff9500; } 
        .status-tag.status-approved { background-color: #34c759; } 
        .status-tag.status-rejected { background-color: #ff3b30; } 
        .status-tag.status-cancelled { background-color: #8e8e93; } 


        /* Modal Styling */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
        }

        .modal-content {
            background-color: #ffffff; 
            margin: 10% auto; 
            padding: 25px; 
            border: none; 
            width: 90%;
            max-width: 700px; /* Increased width for map details */
            border-radius: 14px; 
            position: relative;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15); 
        }

        .close-button {
            color: #aaa;
            font-size: 28px;
            font-weight: 300; 
            position: absolute;
            top: 15px; 
            right: 20px; 
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-button:hover,
        .close-button:focus {
            color: #333; 
        }
        #map-modal-content-container { /* Container for map */
             height: 350px; 
             width: 100%;
             margin-bottom: 15px;
        }
        #map-modal-title { margin-bottom: 15px; font-size: 18px; font-weight: 600; }
        #map-modal-details p { margin-bottom: 5px; font-size: 14px; color: #333;}
        #map-modal-details strong { color: #1d1d1f; }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            /* .container padding already adjusted in main rule if needed */
            h1 { font-size: 24px; }
            .stat-card { padding: 15px; }
            .stat-card-value { font-size: 32px; }
            .content-card { padding: 20px; }
            h2 { font-size: 20px; }
            table th, table td { padding: 10px 12px; font-size: 13px; }
            .filter-controls { flex-direction: column; align-items: stretch; }
            .filter-controls select, .filter-controls input[type="month"], .export-button {
                width: 100%;
                margin-bottom: 10px; 
            }
            .export-button { margin-left: 0; }
        }

        @media (max-width: 480px) {
            /* .container padding already adjusted in main rule if needed */
            .stats-grid { grid-template-columns: 1fr; } 
            .stat-card-value { font-size: 28px; }
            table th, table td { padding: 8px 10px; font-size: 12px; }
            .modal-content { margin: 5% auto; width: 95%; padding: 20px;}
            #map-modal-content-container { height: 300px; }
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
            <h2>Feuille de temps du mois</h2>
            <div class="filter-controls">
                <label for="timesheet-employee-filter">Employé:</label>
                <select id="timesheet-employee-filter">
                    <option value="">Tous les employés</option>
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                            <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="timesheet-month-filter">Mois:</label>
                <input type="month" id="timesheet-month-filter" value="<?php echo date('Y-m'); ?>">
                <button class="export-button" onclick="exportTableToCSV('timesheet-table', 'feuille_de_temps.csv')">Exporter CSV</button>
            </div>
            <div class="table-container">
                <table id="timesheet-table">
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
                    <tbody>
                        <tr><td colspan="6" style="text-align:center;">Chargement...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card">
            <h2>Liste des congés du mois</h2>
            <div class="filter-controls">
                <label for="leave-employee-filter">Employé:</label>
                <select id="leave-employee-filter">
                    <option value="">Tous les employés</option>
                     <?php foreach ($all_employees as $emp): ?>
                        <option value="<?php echo htmlspecialchars($emp['user_id']); ?>">
                            <?php echo htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="leave-month-filter">Mois:</label>
                <input type="month" id="leave-month-filter" value="<?php echo date('Y-m'); ?>">
                 <button class="export-button" onclick="exportTableToCSV('leave-table', 'liste_conges.csv')">Exporter CSV</button>
            </div>
            <div class="table-container">
                <table id="leave-table">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Type de congé</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Durée</th>
                            <th>Statut</th>
                            </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" style="text-align:center;">Chargement...</td></tr>
                    </tbody>
                </table>
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
            <div id="map-modal-details">
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

    <script>
        let map; 
        let currentMapMarkers = []; // To keep track of markers on the map

        function refreshDashboardData() {
            fetch('dashboard-handler.php?action=get_dashboard_all_data')
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            try {
                                const errData = JSON.parse(text);
                                throw new Error(errData.message || `HTTP error! status: ${response.status}`);
                            } catch (e) {
                                throw new Error(`HTTP error! status: ${response.status}. Response: ${text.substring(0,100)}...`);
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('stats-employees-present').textContent = data.data.stats.employees_present;
                        document.getElementById('stats-employees-absent').textContent = data.data.stats.employees_absent;
                        document.getElementById('stats-pending-requests').textContent = data.data.stats.pending_requests;
                        updateActivitiesTable(data.data.activities);
                        loadTimesheetData(); 
                        loadLeaveData();
                    } else {
                        console.error('Error from handler (status not success):', data.message);
                        displayGlobalError("Impossible de rafraîchir les données du tableau de bord: " + (data.message || "Erreur inconnue"));
                    }
                })
                .catch(error => {
                    console.error('Error refreshing dashboard (fetch/parse):', error);
                    displayGlobalError("Erreur de communication pour rafraîchir le tableau de bord: " + error.message);
                });
        }
        
        function displayGlobalError(message) {
            let errorDiv = document.getElementById('global-error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'global-error-message';
                errorDiv.style.backgroundColor = '#ff3b30'; 
                errorDiv.style.color = 'white';
                errorDiv.style.padding = '10px 15px';
                errorDiv.style.textAlign = 'center';
                errorDiv.style.position = 'fixed';
                errorDiv.style.top = '0';
                errorDiv.style.left = '0';
                errorDiv.style.width = '100%';
                errorDiv.style.zIndex = '2000';
                errorDiv.style.fontSize = '14px';
                errorDiv.style.fontWeight = '500';
                document.body.prepend(errorDiv);
            }
            errorDiv.textContent = message;
            setTimeout(() => {
                if (errorDiv) errorDiv.remove();
            }, 7000); 
        }


        function updateActivitiesTable(activities) {
            const tbody = document.querySelector('#activities-table tbody');
            tbody.innerHTML = ''; 
            
            if (!activities || activities.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="4" style="text-align: center;">Aucune activité récente</td>';
                tbody.appendChild(row);
                return;
            }
            
            activities.forEach(activity => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(activity.employee_name)}</td>
                    <td>${escapeHtml(activity.action)}</td>
                    <td>${escapeHtml(activity.date)}</td>
                    <td>${escapeHtml(activity.hour)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function loadTimesheetData() {
            const employeeId = document.getElementById('timesheet-employee-filter').value;
            const monthYear = document.getElementById('timesheet-month-filter').value;
            const tbody = document.querySelector('#timesheet-table tbody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Chargement des données...</td></tr>'; // Adjusted colspan

            fetch(`dashboard-handler.php?action=get_monthly_timesheet&employee_id=${employeeId}&month_year=${monthYear}`)
                .then(response => {
                    if (!response.ok) {
                         return response.text().then(text => {
                            try { const errData = JSON.parse(text); throw new Error(errData.message || `HTTP error! status: ${response.status}`); } 
                            catch (e) { throw new Error(`HTTP error! status: ${response.status}. Response: ${text.substring(0,100)}...`);}
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    tbody.innerHTML = ''; 
                    if (data.status === 'success' && data.data.timesheet.length > 0) {
                        data.data.timesheet.forEach(entry => {
                            const row = document.createElement('tr');
                            let mapButtonHTML = '';
                            
                            const hasLogonLocation = entry.logon_latitude && entry.logon_longitude;
                            const hasLogoffLocation = entry.logoff_latitude && entry.logoff_longitude;

                            if (hasLogonLocation || hasLogoffLocation) {
                                mapButtonHTML = `<button class="action-button" onclick="showTimesheetMapModal(
                                    '${escapeHtml(entry.employee_name)}', 
                                    '${escapeHtml(entry.entry_date)}',
                                    '${entry.logon_latitude}', '${entry.logon_longitude}', '${escapeHtml(entry.logon_address)}', '${escapeHtml(entry.logon_time || '')}',
                                    '${entry.logoff_latitude}', '${entry.logoff_longitude}', '${escapeHtml(entry.logoff_address)}', '${escapeHtml(entry.logoff_time || '')}'
                                )">Voir Carte</button>`;
                            }
                            
                            row.innerHTML = `
                                <td>${mapButtonHTML || '--'}</td>
                                <td>${escapeHtml(entry.employee_name)}</td>
                                <td>${escapeHtml(entry.entry_date)}</td>
                                <td>${escapeHtml(entry.logon_time) || '--'}</td>
                                <td>${escapeHtml(entry.logoff_time) || '--'}</td>
                                <td>${escapeHtml(entry.duration) || '--'}</td>
                            `;
                            tbody.appendChild(row);
                        });
                    } else if (data.status === 'success' && data.data.timesheet.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Aucune donnée de feuille de temps pour la sélection.</td></tr>'; // Adjusted colspan
                    } else {
                         tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Erreur: ${escapeHtml(data.message || 'Impossible de charger les données de pointage.')}</td></tr>`; // Adjusted colspan
                    }
                })
                .catch(error => {
                    console.error('Error loading timesheet data:', error);
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Erreur de chargement des pointages: ${escapeHtml(error.message)}</td></tr>`; // Adjusted colspan
                });
        }

        function loadLeaveData() {
            const employeeId = document.getElementById('leave-employee-filter').value;
            const monthYear = document.getElementById('leave-month-filter').value;
            const tbody = document.querySelector('#leave-table tbody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Chargement des données...</td></tr>'; // Adjusted colspan

            fetch(`dashboard-handler.php?action=get_monthly_leaves&employee_id=${employeeId}&month_year=${monthYear}`)
                .then(response => {
                     if (!response.ok) {
                         return response.text().then(text => {
                            try { const errData = JSON.parse(text); throw new Error(errData.message || `HTTP error! status: ${response.status}`); } 
                            catch (e) { throw new Error(`HTTP error! status: ${response.status}. Response: ${text.substring(0,100)}...`);}
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    tbody.innerHTML = ''; 
                    if (data.status === 'success' && data.data.leaves.length > 0) {
                        data.data.leaves.forEach(leave => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${escapeHtml(leave.employee_name)}</td>
                                <td>${escapeHtml(leave.type_conge_display)}</td>
                                <td>${escapeHtml(leave.date_debut)}</td>
                                <td>${escapeHtml(leave.date_fin)}</td>
                                <td>${escapeHtml(leave.duree)} jours</td>
                                <td><span class="status-tag status-${escapeHtml(leave.status)}">${escapeHtml(leave.status_display)}</span></td>
                            `; // Commentaire column removed
                            tbody.appendChild(row);
                        });
                    } else if (data.status === 'success' && data.data.leaves.length === 0) {
                         tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Aucune donnée de congé pour la sélection.</td></tr>'; // Adjusted colspan
                    } else {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Erreur: ${escapeHtml(data.message || 'Impossible de charger les données de congé.')}</td></tr>`; // Adjusted colspan
                    }
                })
                .catch(error => {
                    console.error('Error loading leave data:', error);
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Erreur de chargement des congés: ${escapeHtml(error.message)}</td></tr>`; // Adjusted colspan
                });
        }
        
       function showTimesheetMapModal(employeeName, entryDate, latEntreeStr, lonEntreeStr, addrEntree, timeEntree, latSortieStr, lonSortieStr, addrSortie, timeSortie) {
            const modal = document.getElementById('mapModal');
            const mapContainer = document.getElementById('map-modal-content-container');
            const mapTitleElem = document.getElementById('map-modal-title');
            const mapDetailsElem = document.getElementById('map-modal-details');

            mapTitleElem.textContent = `Localisation pour ${employeeName} - ${entryDate}`;
            mapDetailsElem.innerHTML = ''; // Clear previous details

            // Clear previous markers and map instance
            currentMapMarkers.forEach(marker => marker.remove());
            currentMapMarkers = [];
            if (map) {
                map.remove();
                map = null;
            }
            
            mapContainer.innerHTML = ''; // Clear previous map canvas if any

            if (typeof L === 'undefined') {
                mapContainer.innerHTML = "Erreur: La bibliothèque de cartographie n'a pas pu être chargée.";
                modal.style.display = "block";
                return;
            }
            
            map = L.map(mapContainer);
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
                    .bindPopup(`<b>Entrée:</b> ${timeEntree || 'N/A'}<br>${addrEntree || 'Adresse non enregistrée'}`);
                currentMapMarkers.push(entryMarker);
                bounds.push([latEntree, lonEntree]);
                detailsHTML += `<p><strong>Entrée (${timeEntree || 'N/A'}):</strong> ${addrEntree || `Lat: ${latEntree.toFixed(5)}, Lon: ${lonEntree.toFixed(5)}`}</p>`;
            } else {
                 detailsHTML += `<p><strong>Entrée (${timeEntree || 'N/A'}):</strong> Localisation non enregistrée.</p>`;
            }

            if (validExitCoords) {
                const exitMarker = L.marker([latSortie, lonSortie]).addTo(map)
                    .bindPopup(`<b>Sortie:</b> ${timeSortie || 'N/A'}<br>${addrSortie || 'Adresse non enregistrée'}`);
                currentMapMarkers.push(exitMarker);
                bounds.push([latSortie, lonSortie]);
                detailsHTML += `<p><strong>Sortie (${timeSortie || 'N/A'}):</strong> ${addrSortie || `Lat: ${latSortie.toFixed(5)}, Lon: ${lonSortie.toFixed(5)}`}</p>`;
            } else {
                detailsHTML += `<p><strong>Sortie (${timeSortie || 'N/A'}):</strong> Localisation non enregistrée.</p>`;
            }
            
            mapDetailsElem.innerHTML = detailsHTML;

            if (validEntryCoords && validExitCoords) {
                L.polyline(bounds, {color: 'blue'}).addTo(map);
            }

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] }); // Fit map to bounds with padding
            } else {
                map.setView([48.8566, 2.3522], 5); // Default view (e.g., Paris) if no coords
                mapDetailsElem.innerHTML = "<p>Aucune localisation enregistrée pour cette entrée.</p>";
            }
            
            modal.style.display = "block";
            
            setTimeout(() => { // Ensure map resizes correctly after modal display
                if (map) map.invalidateSize();
            }, 150);
        }


        function closeMapModal() {
            const modal = document.getElementById('mapModal');
            modal.style.display = "none";
            currentMapMarkers.forEach(marker => marker.remove());
            currentMapMarkers = [];
            if (map) {
                map.remove(); 
                map = null;
            }
        }

        function escapeHtml(text) {
            if (text === null || typeof text === 'undefined') {
                return '';
            }
            const div = document.createElement('div');
            div.textContent = String(text); 
            return div.innerHTML;
        }

        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            if (!table) {
                console.error(`Table with id "${tableId}" not found.`);
                displayGlobalError(`Erreur d'exportation: Table non trouvée.`);
                return;
            }
            let csv = [];
            const rows = table.querySelectorAll("tr");
            
            for (const row of rows) {
                const rowData = [];
                const cols = row.querySelectorAll("td, th");
                
                for (const col of cols) {
                    let cellContent = col.cloneNode(true);
                    cellContent.querySelectorAll('button.action-button').forEach(el => el.remove());
                    cellContent.querySelectorAll('.status-tag').forEach(el => {
                        const statusText = el.textContent || el.innerText;
                        el.parentNode.insertBefore(document.createTextNode(statusText), el);
                        el.remove();
                    });

                    let text = cellContent.innerText.trim().replace(/"/g, '""'); 
                    rowData.push(`"${text}"`); 
                }
                csv.push(rowData.join(","));
            }

            if (csv.length === 0) {
                displayGlobalError("Aucune donnée à exporter.");
                return;
            }

            const csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
            const downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            refreshDashboardData(); 

            // Add event listeners for automatic filtering
            document.getElementById('timesheet-employee-filter').addEventListener('change', loadTimesheetData);
            document.getElementById('timesheet-month-filter').addEventListener('change', loadTimesheetData);
            
            document.getElementById('leave-employee-filter').addEventListener('change', loadLeaveData);
            document.getElementById('leave-month-filter').addEventListener('change', loadLeaveData);
        });

        window.onclick = function(event) {
            const mapModal = document.getElementById('mapModal');
            if (event.target == mapModal) {
                closeMapModal();
            }
        }
    </script>
</body>
</html>
