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

// **Functions are now streamlined for what this page needs for initial load**

function getRecentActivities($conn) {
    $activities = [];
    try {
        // This query now includes location names for a better user experience
        $stmt = $conn->prepare("
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

// Data fetching for initial page load
$activities = getRecentActivities($conn);
$all_employees = getAllEmployees($conn);
$initial_employee_list = getInitialEmployeeList($conn);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; }
        body { background-color: #f5f5f7; color: #1d1d1f; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; padding-bottom: 30px; }
        .container-fluid { padding: 25px; }
        h1 { color: #1d1d1f; font-size: 28px; font-weight: 600; margin-bottom: 25px; }
        .shortcut-buttons-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .shortcut-btn { background-color: #ffffff; border: 1px solid #e0e0e0; color: #333; padding: 20px; text-align: center; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.07); transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; font-size: 0.9rem; font-weight: 500; min-height: 120px; cursor: pointer; }
        .shortcut-btn:hover { background-color: #f0f2f5; transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); color: #6A0DAD; }
        .shortcut-btn i { color: #6A0DAD; margin-bottom: 10px; font-size: 2.2em; }
        .shortcut-btn:hover i { color: #0056b3; }
        .content-card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); padding: 25px; border: 1px solid #e5e5e5; margin-bottom: 30px; }
        h2 { margin-bottom: 20px; color: #1d1d1f; font-size: 22px; font-weight: 600; }
        h3 { color: #1d1d1f; font-weight: 600; margin-bottom: 20px; font-size: 20px; }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); padding: 20px; text-align: center; border-left: 5px solid #007aff; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; cursor: default; }
        .stat-card.clickable:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(0,0,0,0.1); cursor: pointer; }
        .stat-value { font-size: 2.5rem; font-weight: 700; margin-bottom: 8px; line-height: 1.1; color: #333; }
        .stat-label { font-size: 0.95rem; color: #555; font-weight: 500; }
        .stat-icon { font-size: 1.8rem; margin-bottom: 10px; opacity: 0.7; }
        .stat-card.total-employees { border-left-color: #6A0DAD; } .stat-card.total-employees .stat-icon { color: #6A0DAD; }
        .stat-card.assigned-today { border-left-color: #ff9500; } .stat-card.assigned-today .stat-icon { color: #ff9500; }
        .stat-card.active-today { border-left-color: #34c759; } .stat-card.active-today .stat-icon { color: #34c759; }
        .stat-card.on-generic-leave-today { border-left-color: #5856d6; } .stat-card.on-generic-leave-today .stat-icon { color: #5856d6; }
        .stat-card.on-sick-leave-today { border-left-color: #ff3b30; } .stat-card.on-sick-leave-today .stat-icon { color: #ff3b30; }
        .table-container { overflow-x: auto; border-radius: 8px; margin-top: 15px; }
        .modal-alert { display: none; margin-bottom: 15px; }
        .loading-placeholder, .error-placeholder, .info-placeholder { text-align: center; padding: 40px 20px; color: #6c757d; font-size: 1.1rem; }
        .error-placeholder { color: #dc3545; }
        .status-tag { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-align: center; white-space: nowrap; color: white; }
        .status-tag.status-pending { background-color: #ff9500; } .status-tag.status-approved { background-color: #34c759; } .status-tag.status-rejected { background-color: #ff3b30; } .status-tag.status-cancelled { background-color: #8e8e93; }

    </style>
</head>
<body>
    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>
    <div class="container-fluid">
        <h1>Administrateur</h1>

        <div class="shortcut-buttons-grid">
            <button class="shortcut-btn" data-toggle="modal" data-target="#congesAdminModal"><i class="fas fa-user-shield"></i>Congés Admin</button>
            <a href="planning.php" class="shortcut-btn"><i class="fas fa-calendar-alt"></i>Planning Admin</a>
            <a href="events.php" class="shortcut-btn"><i class="fas fa-plus-circle"></i>Créer Événement</a>
            <button class="shortcut-btn" data-toggle="modal" data-target="#feuilleDeTempsModal"><i class="fas fa-user-clock"></i>Feuille de Temps</button>
        </div>

        <div class="content-card">
            <div class="card border-0 shadow-none p-0"> <h3>Statistiques du Jour</h3>
                <div class="stats-container" id="employee-stats-container">
                    <div class="loading-placeholder"><div class="spinner-border spinner-border-sm" role="status"></div> Chargement...</div>
                </div>
            </div>
            <div class="card border-0 shadow-none p-0 mt-4" id="employeeListCard">
                <div class="d-flex justify-content-between align-items-center" id="employeeListCardHeader">
                    <h3 id="employeeListTitle" class="mb-0">Liste Générale des Employés Actifs</h3>
                    <button id="backToListButton" class="btn btn-sm btn-outline-secondary" style="display: none;" onclick="showInitialEmployeeList()"><i class="fas fa-arrow-left"></i> Retour</button>
                </div>
                <div class="table-responsive mt-3">
                    <table id="employees-table" class="table table-striped table-hover">
                        <thead class="thead-light" id="employees-table-head"></thead>
                        <tbody id="employees-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="content-card">
            <h2>Dernières activités</h2>
            <div class="table-responsive">
                <table id="activities-table" class="table table-hover">
                    <thead class="thead-light"><tr><th>Employé</th><th>Action</th><th>Date</th><th>Heure</th></tr></thead>
                    <tbody id="activities-table-body">
                        <?php if (empty($activities)): ?>
                            <tr><td colspan="4" class="text-center">Aucune activité récente</td></tr>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><?= $activity['employee_name']; ?></td>
                                    <td><?= $activity['action']; ?></td>
                                    <td><?= $activity['date']; ?></td>
                                    <td><?= $activity['hour']; ?></td>
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
                <div class="modal-header"><h5 class="modal-title" id="congesAdminModalLabel">Gestion des Congés</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="congesAdminTabs" role="tablist">
                        <li class="nav-item" role="presentation"><a class="nav-link active" id="approval-tab-link" data-toggle="tab" href="#leaveApprovalContent" role="tab" aria-controls="leaveApprovalContent" aria-selected="true">Approbation</a></li>
                        <li class="nav-item" role="presentation"><a class="nav-link" id="list-all-leaves-tab-link" data-toggle="tab" href="#listAllLeavesContent" role="tab" aria-controls="listAllLeavesContent" aria-selected="false">Liste Complète</a></li>
                    </ul>
                    <div class="tab-content pt-3" id="congesAdminTabsContent">
                        <div class="tab-pane fade show active" id="leaveApprovalContent" role="tabpanel">
                            <h6 class="mt-2">Demandes en Attente</h6><div id="congesAdminAlert" class="modal-alert" role="alert"></div>
                            <div class="table-responsive"><table class="table table-striped table-sm"><thead><tr><th>Employé</th><th>Dates</th><th>Type</th><th>Durée</th><th>Demandé le</th><th>Actions</th></tr></thead><tbody id="congesAdminTableBody"></tbody></table></div>
                        </div>
                        <div class="tab-pane fade" id="listAllLeavesContent" role="tabpanel">
                            <h6 class="mt-2">Consulter la Liste des Congés</h6>
                            <div class="form-row align-items-center mb-3">
                                <div class="col-auto"><select id="caLeaveEmployeeFilter" class="form-control form-control-sm"><option value="">Tous les employés</option><?php foreach ($all_employees as $emp): ?><option value="<?= htmlspecialchars($emp['user_id']); ?>"><?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?></option><?php endforeach; ?></select></div>
                                <div class="col-auto"><input type="month" id="caLeaveMonthFilter" class="form-control form-control-sm" value="<?= date('Y-m'); ?>"></div>
                                <div class="col-auto"><input type="date" id="caLeaveDayFilter" class="form-control form-control-sm"></div>
                            </div>
                            <div class="table-responsive"><table id="caListeCongesTable" class="table table-striped table-sm"><thead><tr><th>Employé</th><th>Type</th><th>Début</th><th>Fin</th><th>Durée</th><th>Statut</th></tr></thead><tbody id="caListeCongesTableBody"></tbody></table></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button></div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Détails de la Demande</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body" id="leaveDetailsModalBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button></div></div></div></div>

   

    <div class="modal fade" id="feuilleDeTempsModal" tabindex="-1" aria-labelledby="feuilleDeTempsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="feuilleDeTempsModalLabel">Feuille de Temps</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-row align-items-center mb-3">
                        <div class="col-auto"><select id="timesheetEmployeeFilterModal" class="form-control form-control-sm"><option value="">Tous les employés</option><?php foreach ($all_employees as $emp): ?><option value="<?= htmlspecialchars($emp['user_id']); ?>"><?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom']); ?></option><?php endforeach; ?></select></div>
                        <div class="col-auto"><input type="month" id="timesheetMonthFilterModal" class="form-control form-control-sm" value="<?= date('Y-m'); ?>"></div>
                        <div class="col-auto"><input type="date" id="timesheetDayFilterModal" class="form-control form-control-sm"></div>
                        <div class="col-auto ml-auto">
                            <button class="btn btn-sm btn-success" onclick="exportTableToCSV('timesheetTableModal', 'feuille-de-temps.csv')"><i class="fas fa-download"></i> Exporter en CSV</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="timesheetTableModal" class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Employé</th>
                                    <th>Date</th>
                                    <th>Mission</th>
                                    <th>Entrée</th>
                                    <th>Lieu (Entrée)</th>
                                    <th>Sortie</th>
                                    <th>Lieu (Sortie)</th>
                                    <th>Pause</th>
                                    <th>Durée</th>
                                    <th>Commentaires</th>
                                </tr>
                            </thead>
                            <tbody id="timesheetTableBodyModal"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button></div>
            </div>
        </div>
    </div>
    
    <?php if (file_exists('footer.php')) include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const currentUserRole = '<?= $user['role']; ?>';

    function escapeHtml(text) {
        if (text === null || typeof text === 'undefined') return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function refreshDashboardData() {
        try {
            fetch('dashboard-handler.php?action=get_dashboard_all_data')
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { throw new Error('Erreur de communication pour rafraîchir les activités: HTTP error! status: ' + response.status + ". Response: " + text.substring(0, 200)) });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        if (data.data.activities) {
                            updateActivitiesTable(data.data.activities);
                        }
                    } else {
                        displayGlobalError('Erreur du handler: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing dashboard (fetch/parse activities):', error);
                    displayGlobalError(error.message);
                });

            fetchEmployeeStats();

        } catch (e) {
            console.error("Error in refreshDashboardData:", e);
            displayGlobalError("Une erreur s'est produite lors du rafraîchissement.");
        }
    }

    function updateActivitiesTable(activities) {
        const tbody = document.getElementById('activities-table-body');
        if (!tbody) { console.error("Activities table body not found."); return; }
        tbody.innerHTML = '';
        if (!activities || activities.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="info-placeholder">Aucune activité récente</td></tr>';
            return;
        }
        activities.forEach(activity => {
            const row = document.createElement('tr');
            row.innerHTML = `<td>${escapeHtml(activity.employee_name)}</td>
                             <td>${escapeHtml(activity.action)}</td>
                             <td>${escapeHtml(activity.date)}</td>
                             <td>${escapeHtml(activity.hour)}</td>`;
            tbody.appendChild(row);
        });
    }

    function loadTimesheetDataForModal() {
        try {
            const employeeId = $('#timesheetEmployeeFilterModal').val();
            const monthYear = $('#timesheetMonthFilterModal').val();
            const specificDay = $('#timesheetDayFilterModal').val();
            const tbody = $('#timesheetTableBodyModal');
            const colspan = 10;

            tbody.html(`<tr><td colspan="${colspan}" class="loading-placeholder">Chargement...</td></tr>`);

            let ajaxData = { action: 'get_monthly_timesheet', employee_id: employeeId };
            if (specificDay) {
                ajaxData.specific_day = specificDay;
            } else if (monthYear) {
                ajaxData.month_year = monthYear;
            } else {
                tbody.html(`<tr><td colspan="${colspan}" class="error-placeholder">Veuillez sélectionner un mois ou un jour.</td></tr>`);
                return;
            }

            $.ajax({
                url: 'dashboard-handler.php',
                type: 'GET',
                data: ajaxData,
                dataType: 'json',
                success: function(response) {
                    tbody.empty();
                    if (response.status === 'success' && response.data.timesheet && response.data.timesheet.length > 0) {
                        response.data.timesheet.forEach(function(entry) {
                            let rowHTML = `<tr>
                                <td>${escapeHtml(entry.employee_name)}</td>
                                <td>${escapeHtml(entry.entry_date)}</td>
                                <td>${escapeHtml(entry.mission)}</td>
                                <td>${entry.logon_time || '--'}</td>
                                <td>${escapeHtml(entry.logon_location_name)}</td>
                                <td>${entry.logoff_time || '--'}</td>
                                <td>${escapeHtml(entry.logoff_location_name)}</td>
                                <td>${entry.break_minutes || '0'} min</td>
                                <td><strong>${entry.duration || '--'}</strong></td>
                                <td>${escapeHtml(entry.commentaire)}</td>
                            </tr>`;
                            tbody.append(rowHTML);
                        });
                    } else if (response.status === 'success') {
                        tbody.html(`<tr><td colspan="${colspan}" class="info-placeholder">Aucune donnée pour cette sélection.</td></tr>`);
                    } else {
                        tbody.html(`<tr><td colspan="${colspan}" class="error-placeholder">Erreur: ${escapeHtml(response.message || 'Impossible de charger les données.')}</td></tr>`);
                    }
                },
                error: function(xhr) {
                    tbody.html(`<tr><td colspan="${colspan}" class="error-placeholder">Erreur de communication.</td></tr>`);
                }
            });
        } catch (e) {
            $('#timesheetTableBodyModal').html(`<tr><td colspan="10" class="error-placeholder">Erreur interne du script.</td></tr>`);
        }
    }

    function displayGlobalError(message) {
        console.error("Global Error:", message);
        alert("ERREUR: " + message);
    }

    function displayModalAlert(modalId, message, type = 'danger') {
        const alertElement = $(`#${modalId} .modal-alert`);
        if (alertElement.length) {
            alertElement.removeClass('alert-success alert-danger alert-warning alert-info').addClass('alert-' + type).html(message).show();
            setTimeout(() => { alertElement.hide(); }, 5000);
        } else {
            alert(type.toUpperCase() + ": " + message);
        }
    }

    function loadPendingLeaveRequestsForModal() {
        $('#congesAdminTableBody').html('<tr><td colspan="7" class="loading-placeholder">Chargement...</td></tr>');
        $.ajax({
            url: 'conges-handler.php', type: 'POST', data: { action: 'get_pending_requests' }, dataType: 'json',
            success: function(response) {
                const tbody = $('#congesAdminTableBody');
                tbody.empty();
                if (response.status === 'success' && response.data && response.data.length > 0) {
                    response.data.forEach(function(req) {
                        
                        tbody.append(`<tr><td>${escapeHtml(req.employee_name)}</td><td>${escapeHtml(req.date_debut)} - ${escapeHtml(req.date_fin)}</td><td>${escapeHtml(getLeaveTypeName(req.type_conge))}</td><td>${escapeHtml(req.duree)}j</td><td>${escapeHtml(req.date_demande)}</td><td><button class="btn btn-success btn-sm py-0 px-1 action-button" onclick="approveLeaveFromModal(${req.id})">Approuver</button><button class="btn btn-danger btn-sm ml-1 py-0 px-1 action-button" onclick="rejectLeaveFromModal(${req.id})">Refuser</button><button class="btn btn-info btn-sm ml-1 py-0 px-1 action-button" onclick="showLeaveDetailsModal(${req.id})">Détails</button></td></tr>`);
                    });
                } else {
                    tbody.html('<tr><td colspan="7" class="info-placeholder">Aucune demande en attente.</td></tr>');
                }
            },
            error: function() { $('#congesAdminTableBody').html('<tr><td colspan="7" class="error-placeholder">Erreur de communication.</td></tr>'); }
        });
    }
    
    function getLeaveTypeName(typeKey) {
        const types = {'cp': 'Congés Payés', 'rtt': 'RTT', 'sans-solde': 'Congé Sans Solde', 'special': 'Congé Spécial', 'maladie': 'arrêt maladie'};
        return types[typeKey] || typeKey;
    }

    function showLeaveDetailsModal(leaveId) {
         $.ajax({
            url: 'conges-handler.php', type: 'POST', data: { action: 'get_details_for_admin', leave_id: leaveId }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    const leave = response.data;
                    let detailsHtml = `<p><strong>Employé:</strong> ${escapeHtml(leave.employee_name)}</p><p><strong>Dates:</strong> ${escapeHtml(leave.date_debut)} - ${escapeHtml(leave.date_fin)}</p><p><strong>Type:</strong> ${escapeHtml(getLeaveTypeName(leave.type_conge))}</p><p><strong>Durée:</strong> ${escapeHtml(leave.duree)} jour(s)</p><p><strong>Statut:</strong> <span class="status-tag status-tag-modal status-${escapeHtml(leave.status)}">${escapeHtml(leave.status_display)}</span></p><p><strong>Commentaire:</strong> ${escapeHtml(leave.commentaire || 'Aucun')}</p><p><strong>Document:</strong> ${leave.document ? `<a href="${escapeHtml(leave.document)}" target="_blank" class="document-link-modal">Voir</a>` : 'Aucun'}</p>`;
                    $('#leaveDetailsModalBody').html(detailsHtml);
                    $('#leaveDetailsModal').modal('show');
                } else { displayModalAlert('congesAdminModal', response.message || "Erreur.", 'danger'); }
            }, error: function() { displayModalAlert('congesAdminModal', 'Erreur de communication.', 'danger'); }
        });
    }

    function approveLeaveFromModal(leaveId) {
        const commentaire = prompt("Commentaire pour l'approbation (optionnel):");
        if (commentaire === null) return;
        $.ajax({
            url: 'conges-handler.php', type: 'POST', data: { action: 'approve_request', leave_id: leaveId, commentaire: commentaire }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') { displayModalAlert('congesAdminModal', response.message, 'success'); loadPendingLeaveRequestsForModal(); refreshDashboardData(); }
                else { displayModalAlert('congesAdminModal', response.message, 'danger'); }
            }, error: function() { displayModalAlert('congesAdminModal', 'Erreur.', 'danger'); }
        });
    }

    function rejectLeaveFromModal(leaveId) {
        const commentaire = prompt("Motif du refus (obligatoire):");
        if (commentaire === null) return;
        if (!commentaire.trim()) { displayModalAlert('congesAdminModal', 'Un motif est requis.', 'warning'); return; }
        $.ajax({
            url: 'conges-handler.php', type: 'POST', data: { action: 'reject_request', leave_id: leaveId, commentaire: commentaire }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') { displayModalAlert('congesAdminModal', response.message, 'success'); loadPendingLeaveRequestsForModal(); refreshDashboardData(); }
                else { displayModalAlert('congesAdminModal', response.message, 'danger'); }
            }, error: function() { displayModalAlert('congesAdminModal', 'Erreur.', 'danger'); }
        });
    }
    
    $('#eventCreationForm').on('submit', function(e) { 
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_event');
        
        $.ajax({
            url: 'planning-handler.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    displayModalAlert('eventCreationModal', response.message, 'success');
                    $('#eventCreationForm')[0].reset();
                    // Optionally close modal after a delay
                    setTimeout(() => $('#eventCreationModal').modal('hide'), 2000);
                } else {
                    displayModalAlert('eventCreationModal', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                displayModalAlert('eventCreationModal', 'Erreur de communication avec le serveur.', 'danger');
            }
        });
    });
    
    function loadAllLeavesForCongesAdminModal() {
        $('#caListeCongesTableBody').html('<tr><td colspan="6" class="loading-placeholder">Chargement...</td></tr>');
        $.ajax({
            url: 'dashboard-handler.php', type: 'GET',
            data: { action: 'get_monthly_leaves', employee_id: $('#caLeaveEmployeeFilter').val(), month_year: $('#caLeaveMonthFilter').val(), specific_day: $('#caLeaveDayFilter').val() },
            dataType: 'json',
            success: function(response) {
                const tbody = $('#caListeCongesTableBody');
                tbody.empty();
                if (response.status === 'success' && response.data.leaves && response.data.leaves.length > 0) {
                    response.data.leaves.forEach(function(leave) {
                        tbody.append(`<tr><td>${escapeHtml(leave.employee_name)}</td><td>${escapeHtml(leave.type_conge_display)}</td><td>${escapeHtml(leave.date_debut)}</td><td>${escapeHtml(leave.date_fin)}</td><td>${escapeHtml(leave.duree)} jours</td><td><span class="status-tag status-${escapeHtml(leave.status)}">${escapeHtml(leave.status_display)}</span></td></tr>`);
                    });
                } else { tbody.html('<tr><td colspan="6" class="info-placeholder">Aucune donnée.</td></tr>'); }
            },
            error: function() { $('#caListeCongesTableBody').html('<tr><td colspan="6" class="error-placeholder">Erreur de communication.</td></tr>'); }
        });
    }
    
    function exportTableToCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;
        let csv = [];
        table.querySelectorAll("tr").forEach(row => {
            let rowData = [];
            row.querySelectorAll("td, th").forEach(col => {
                let text = col.innerText.replace(/"/g, '""');
                rowData.push('"' + text + '"');
            });
            csv.push(rowData.join(","));
        });
        const blob = new Blob(["\uFEFF" + csv.join("\n")], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = "none";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    const initialEmployeeData = <?= json_encode($initial_employee_list); ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        refreshDashboardData();
        $('#congesAdminModal').on('show.bs.modal', function () { loadPendingLeaveRequestsForModal(); $('#caLeaveEmployeeFilter, #caLeaveDayFilter').val(''); $('#caLeaveMonthFilter').val('<?= date('Y-m'); ?>'); });
        $('a[data-toggle="tab"][href="#listAllLeavesContent"]').on('shown.bs.tab', loadAllLeavesForCongesAdminModal);
        $('#caLeaveEmployeeFilter, #caLeaveMonthFilter, #caLeaveDayFilter').on('change', loadAllLeavesForCongesAdminModal);
        $('#feuilleDeTempsModal').on('show.bs.modal', function () { $('#timesheetEmployeeFilterModal, #timesheetDayFilterModal').val(''); $('#timesheetMonthFilterModal').val('<?= date('Y-m'); ?>'); loadTimesheetDataForModal(); });
        $('#eventCreationModal').on('show.bs.modal', function () { $('#eventCreationForm')[0].reset(); $('#eventCreationAlert').hide(); });
        $('#timesheetEmployeeFilterModal, #timesheetMonthFilterModal, #timesheetDayFilterModal').on('change', function() {
            if ($(this).is('#timesheetMonthFilterModal') && $(this).val()) $('#timesheetDayFilterModal').val('');
            if ($(this).is('#timesheetDayFilterModal') && $(this).val()) $('#timesheetMonthFilterModal').val('');
            loadTimesheetDataForModal();
        });
        fetchEmployeeStats();
        showInitialEmployeeList();
    });

    function fetchEmployeeStats() {
        $('#employee-stats-container').html(`<div class="loading-placeholder"><div class="spinner-border spinner-border-sm"></div></div>`);
        fetch('employee_handler.php?action=get_employee_overview_stats').then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.stats) renderStats(data.stats, currentUserRole);
            else $('#employee-stats-container').html('<div class="error-placeholder">Erreur stats.</div>');
        }).catch(() => $('#employee-stats-container').html('<div class="error-placeholder">Erreur comm. stats.</div>'));
    }

    function renderStats(stats, userRoleForStats) {
        const container = $('#employee-stats-container');
        container.empty();
        let html = '';
        const statTypes = [
            { key: 'total_employees', label: 'Total Employés Actifs', icon: 'fas fa-users', adminOnly: true, cssClass: 'total-employees', clickableIfZero: true },
            { key: 'assigned_today', label: "Assignés Aujourd'hui", icon: 'fas fa-calendar-check', adminOnly: true, cssClass: 'assigned-today', clickableIfZero: true },
            { key: 'active_today', label: "En Activité", icon: 'fas fa-clipboard-check', adminOnly: false, cssClass: 'active-today', clickableIfZero: true },
            { key: 'on_generic_leave_today', label: "En Congé", icon: 'fas fa-plane-departure', adminOnly: true, cssClass: 'on-generic-leave-today', clickableIfZero: true },
            { key: 'on_sick_leave_today', label: "En Arrêt Maladie", icon: 'fas fa-briefcase-medical', adminOnly: true, cssClass: 'on-sick-leave-today', clickableIfZero: true }
        ];
        statTypes.forEach(type => {
            if (stats[type.key] !== undefined && (!type.adminOnly || userRoleForStats === 'admin')) {
                const count = parseInt(stats[type.key], 10) || 0;
                const clickHandler = `loadFilteredEmployeeList('${type.key}', '${type.label.replace(/'/g, "\\'")}')`;
                html += `<div class="stat-card ${type.cssClass} clickable" onclick="${clickHandler}"><div class="stat-icon"><i class="${type.icon}"></i></div><div class="stat-value">${count}</div><div class="stat-label">${type.label}</div></div>`;
            }
        });
        container.html(html || '<div class="info-placeholder">Aucune statistique.</div>');
    }

    function showInitialEmployeeList() {
        $('#employeeListTitle').text('Liste Générale des Employés Actifs');
        $('#backToListButton').hide();
        renderEmployeeTable(initialEmployeeData, 'total_employees');
    }

    function loadFilteredEmployeeList(statType, title) {
        $('#employeeListTitle').text(title);
        if (statType !== 'total_employees') $('#backToListButton').show(); else $('#backToListButton').hide();
        const tableBody = $('#employees-table-body');
        setTableHeaders(statType);
        tableBody.html(`<tr><td colspan="5" class="loading-placeholder"><div class="spinner-border spinner-border-sm"></div></div>`);
        fetch(`employee_handler.php?action=get_employee_list_for_stat&type=${statType}`).then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.employees) renderEmployeeTable(data.employees, statType);
            else tableBody.html(`<tr><td colspan="5" class="error-placeholder">${data.message || 'Erreur.'}</td></tr>`);
        }).catch(() => tableBody.html(`<tr><td colspan="5" class="error-placeholder">Erreur de comm.</td></tr>`));
    }

    function setTableHeaders(statType) {
        let headers = '<tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th>';
        if (statType === 'assigned_today') headers += '<th>Mission (Planning)</th>';
        else if (statType === 'on_generic_leave_today' || statType === 'on_sick_leave_today') headers += '<th>Type de Congé</th>';
        headers += '</tr>';
        $('#employees-table-head').html(headers);
    }

    function renderEmployeeTable(employees, statType) {
        const tableBody = $('#employees-table-body');
        tableBody.empty();
        let colspan = 4;
        if (['assigned_today', 'on_generic_leave_today', 'on_sick_leave_today'].includes(statType)) colspan = 5;
        if (!employees || employees.length === 0) {
            tableBody.html(`<tr><td colspan="${colspan}" class="info-placeholder">Aucun employé.</td></tr>`);
            return;
        }
        employees.forEach(emp => {
            let rowHtml = `<tr><td>${escapeHtml(emp.nom)}</td><td>${escapeHtml(emp.prenom)}</td><td>${escapeHtml(emp.email)}</td><td>${escapeHtml(ucfirst(emp.role))}</td>`;
            if (statType === 'assigned_today') rowHtml += `<td>${escapeHtml(emp.mission || 'N/A')}</td>`;
            else if (statType === 'on_generic_leave_today' || statType === 'on_sick_leave_today') rowHtml += `<td>${escapeHtml(emp.type_conge_display || 'N/A')}</td>`;
            rowHtml += `</tr>`;
            tableBody.append(rowHtml);
        });
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }
    </script> 
</body>
</html>
