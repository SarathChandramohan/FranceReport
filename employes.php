<?php
// employes.php

require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
$user_role = $currentUser['role'];

require_once 'db-connection.php';

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

$initial_employee_list = getInitialEmployeeList($conn);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employés - Gestion</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%; 
        }
        body {
            background-color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: #1d1d1f;
            padding-top: 20px; /* Adjusted padding-top since navbar is removed */
            display: flex;
            flex-direction: column; 
        }
        .container-fluid {
            flex-grow: 1; 
            padding-bottom: 20px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e5e5e5;
        }
        h2, h3 {
            color: #1d1d1f;
            font-weight: 600;
        }
        h2 { margin-bottom: 25px; font-size: 28px; }
        h3 { margin-bottom: 20px; font-size: 22px; }

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

        .table-container { overflow-x: auto; border: 1px solid #e5e5e5; border-radius: 8px; margin-top: 15px; background-color: #fff; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 14px; vertical-align: middle; }
        table th { background-color: #f8f9fa; font-weight: 600; color: #495057; }
        table tr:hover { background-color: #f1f3f5; }
        .loading-placeholder, .error-placeholder, .info-placeholder { text-align: center; padding: 40px 20px; color: #6c757d; font-size: 1.1rem; }
        .error-placeholder { color: #dc3545; }
        .alert-custom { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: .35rem; }
        .alert-danger-custom { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-info-custom { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }

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
        footer {
            width: 100%;
            background-color: #333; 
            color: white; 
            text-align: center;
            padding: 10px 0;
            margin-top: auto; 
        }
         @media (max-width: 576px) {
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

    </style>
</head>
<body>

<div class="container-fluid mt-4" id="main-content-area">
    <?php
        if (isset($GLOBALS['initial_employee_list_error'])) {
            echo '<div class="alert alert-danger-custom text-center">' . htmlspecialchars($GLOBALS['initial_employee_list_error']) . '</div>';
        }
    ?>
    <div class="card">
        <h3>Statistiques du Jour</h3>
        <div class="stats-container" id="employee-stats-container">
            <div class="loading-placeholder">
                <div class="spinner-border spinner-border-sm" role="status"><span class="sr-only">Chargement...</span></div>
                Chargement des statistiques...
            </div>
        </div>
    </div>

    <div class="card" id="employeeListCard">
        <div id="employeeListCardHeader">
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

<?php
    if (file_exists('footer.php')) {
        include 'footer.php';
    }
?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

<script>
const initialEmployeeData = <?php echo json_encode($initial_employee_list); ?>;
const currentUserRole = '<?php echo $user_role; ?>';

document.addEventListener('DOMContentLoaded', function() {
    fetchEmployeeStats();
    showInitialEmployeeList(); 
});

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
        { key: 'assigned_today', label: 'Assignés Aujourd\'hui (Planning)', icon: 'fas fa-calendar-check', adminOnly: true, cssClass: 'assigned-today', clickableIfZero: true }, // label contains a single quote
        { key: 'active_today', label: 'En Activité (Pointage)', icon: 'fas fa-clipboard-check', adminOnly: false, cssClass: 'active-today', clickableIfZero: true },
        { key: 'on_generic_leave_today', label: 'En Congé (Autre)', icon: 'fas fa-plane-departure', adminOnly: true, cssClass: 'on-generic-leave-today', clickableIfZero: true },
        { key: 'on_sick_leave_today', label: 'En Arrêt Maladie', icon: 'fas fa-briefcase-medical', adminOnly: true, cssClass: 'on-sick-leave-today', clickableIfZero: true }
    ];

    statTypes.forEach(type => {
        if (stats[type.key] !== undefined && (!type.adminOnly || isAdmin)) {
            const count = parseInt(stats[type.key], 10) || 0;
            const canBeClickedByRole = type.adminOnly ? isAdmin : true;
            const isClickable = canBeClickedByRole && (count > 0 || type.clickableIfZero);

            // Escape the label for use within a JavaScript string literal
            // This will replace ' with \'
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
    console.log(`[loadFilteredEmployeeList] Attempting to load list for: ${statType}, Title: ${title}`); // Debug
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
    if (!tableBody) {
        console.error("[loadFilteredEmployeeList] Table body not found for list rendering."); // Debug
        return;
    }

    let colspan = 4; 
    const dynamicColspanTypes = ['assigned_today', 'on_generic_leave_today', 'on_sick_leave_today'];
    if (dynamicColspanTypes.includes(statType)) {
        colspan = 5; 
    }
    
    setTableHeaders(statType); 
    tableBody.innerHTML = `<tr><td colspan="${colspan}" class="loading-placeholder"><div class="spinner-border spinner-border-sm"></div> Chargement de la liste...</td></tr>`;
    
    console.log(`[loadFilteredEmployeeList] Fetching: employee_handler.php?action=get_employee_list_for_stat&type=${statType}`); // Debug
    fetch(`employee_handler.php?action=get_employee_list_for_stat&type=${statType}`)
        .then(response => {
            console.log(`[loadFilteredEmployeeList] Response status for ${statType}: ${response.status}`); // Debug
            if (!response.ok) {
                return response.text().then(text => { 
                    console.error(`[loadFilteredEmployeeList] Network error text for ${statType}:`, text); // Debug
                    throw new Error('Network error: ' + response.status + ' ' + text.substring(0,200));
                });
            }
            return response.json();
        })
        .then(data => {
            console.log(`[loadFilteredEmployeeList] Data received for ${statType}:`, data); // IMPORTANT DEBUG: Check this output
            if (data.status === 'success' && data.employees) {
                renderEmployeeTable(data.employees, statType);
            } else {
                console.error(`[loadFilteredEmployeeList] Error in data for ${statType}: ${data.message}`); // Debug
                tableBody.innerHTML = `<tr><td colspan="${colspan}" class="error-placeholder">Erreur: ${data.message || 'Impossible de charger la liste.'}</td></tr>`;
            }
        })
        .catch(error => {
            console.error(`[loadFilteredEmployeeList] Fetch catch error for ${statType}:`, error); // Debug
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="error-placeholder">Erreur de communication: ${error.message}</td></tr>`;
        });
}

function setTableHeaders(statType) {
    const tableHead = document.getElementById('employees-table-head');
    if (!tableHead) {
        console.error("[setTableHeaders] Table head not found."); // Debug
        return;
    }
    let headers = '<tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th>';
    if (statType === 'assigned_today') {
        headers += '<th>Mission (Planning)</th>';
    } else if (statType === 'on_generic_leave_today' || statType === 'on_sick_leave_today') {
        headers += '<th>Type de Congé</th>';
    }
    headers += '</tr>';
    tableHead.innerHTML = headers;
    // console.log(`[setTableHeaders] Headers set for ${statType}: ${headers}`); // Optional Debug
}

function renderEmployeeTable(employees, statType) {
    console.log(`[renderEmployeeTable] Rendering table for statType: ${statType}, with ${employees ? employees.length : 0} employees.`); // Debug
    const tableBody = document.getElementById('employees-table-body');
    if (!tableBody) {
        console.error("[renderEmployeeTable] Cannot render table: employees-table-body not found."); // Debug
        return;
    }
    
    tableBody.innerHTML = ''; 
    setTableHeaders(statType); 

    let colspan = 4;
    const dynamicColspanTypes = ['assigned_today', 'on_generic_leave_today', 'on_sick_leave_today'];
    if (dynamicColspanTypes.includes(statType)) {
        colspan = 5;
    }

    if (!employees || employees.length === 0) {
        console.log(`[renderEmployeeTable] No employees to render for ${statType}.`); // Debug
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
            // The SQL query filters out 'repos', so emp.shift_type === 'repos' will be false here.
            // It will display emp.mission if available, otherwise 'N/A'.
            rowHtml += `<td>${emp.mission ? escapeHtml(emp.mission) : 'N/A'}</td>`;
        } else if (statType === 'on_generic_leave_today' || statType === 'on_sick_leave_today') {
            rowHtml += `<td>${emp.type_conge_display ? escapeHtml(emp.type_conge_display) : (emp.type_conge ? escapeHtml(ucfirst(emp.type_conge)) : 'N/A')}</td>`;
        }
        rowHtml += `</tr>`;
        rowsHtml += rowHtml;
    });
    tableBody.innerHTML = rowsHtml;
    // console.log(`[renderEmployeeTable] Table rendered for ${statType}.`); // Optional Debug
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const str = String(text); 
    var map = {
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function ucfirst(str) {
    if (typeof str !== 'string' || str.length === 0) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

</script>
</body>
</html>
