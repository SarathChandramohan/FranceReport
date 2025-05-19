<?php
// employes.php

require_once 'session-management.php';
requireLogin();
$currentUser = getCurrentUser();
$user_role = $currentUser['role'];

require_once 'db-connection.php';

function getAllEmployeesForList($conn) {
    $employees = [];
    try {
        $query = "SELECT user_id as id, nom, prenom, email, role, status FROM Users WHERE status = 'Active' ORDER BY nom, prenom";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getAllEmployeesForList: " . $e->getMessage());
    }
    return $employees;
}

$all_employees_list = getAllEmployeesForList($conn);

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
        /* Ensure no default margin/padding interfering */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            /* height: 100%; /* Usually not needed unless specific full-height layout */
        }

        body {
            background-color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: #1d1d1f;
            /* This padding-top is crucial if your navbar is fixed or sticky and has a known height.
               Adjust this value to the exact height of your rendered navbar. */
            /* padding-top: 60px; /* Example value, will be set by JS */
        }

        /* Navbar specific fix: If navbar.php makes it fixed/sticky */
        /* This script will dynamically set padding-top after navbar.php loads */

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
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.1;
            color: #333;
        }
        .stat-label {
            font-size: 0.95rem;
            color: #555;
            font-weight: 500;
        }
        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            opacity: 0.7;
        }

        .stat-card.total-employees { border-left-color: #007bff; }
        .stat-card.total-employees .stat-icon { color: #007bff; }
        .stat-card.assigned-today { border-left-color: #ff9500; }
        .stat-card.assigned-today .stat-icon { color: #ff9500; }
        .stat-card.active-today { border-left-color: #34c759; }
        .stat-card.active-today .stat-icon { color: #34c759; }
        .stat-card.on-leave-today { border-left-color: #5856d6; }
        .stat-card.on-leave-today .stat-icon { color: #5856d6; }
        .stat-card.on-sick-leave-today { border-left-color: #ff3b30; }
        .stat-card.on-sick-leave-today .stat-icon { color: #ff3b30; }

        .table-container {
            overflow-x: auto;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            margin-top: 15px;
            background-color: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
            vertical-align: middle;
        }
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        table tr:hover {
            background-color: #f1f3f5;
        }
        .loading-placeholder, .error-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-size: 1.1rem;
        }
        .error-placeholder { color: #dc3545; }
        .alert-custom {
            padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: .35rem;
        }
        .alert-danger-custom { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-info-custom { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }

        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .modal-title { font-weight: 600; }
        .modal-body { max-height: 70vh; overflow-y: auto; }
        .modal-footer { border-top: 1px solid #dee2e6; }
        #employeeListModalTable th, #employeeListModalTable td { font-size: 0.9rem; padding: 0.5rem 0.75rem; }

    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4" id="main-content-area">
    <h2>Tableau de Bord des Employés</h2>

    <div class="card">
        <h3>Statistiques du Jour</h3>
        <div class="stats-container" id="employee-stats-container">
            <div class="loading-placeholder">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="sr-only">Chargement...</span>
                </div>
                Chargement des statistiques...
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Liste Générale des Employés Actifs</h3>
        <div class="table-container">
            <table id="employees-table" class="table table-striped table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_employees_list)): ?>
                        <?php foreach ($all_employees_list as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['nom']); ?></td>
                                <td><?php echo htmlspecialchars($employee['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($employee['role'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted p-4">Aucun employé actif trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="employeeListModal" tabindex="-1" aria-labelledby="employeeListModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered"> <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="employeeListModalLabel">Liste des Employés</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="employeeListModalContent" class="table-responsive">
            <div class="loading-placeholder">Chargement de la liste...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>


<?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attempt to fix navbar spacing dynamically
    const navbar = document.querySelector('.navbar.sticky-top'); // Use a selector for your specific navbar
    if (navbar) {
        const navbarHeight = navbar.offsetHeight;
        document.body.style.paddingTop = navbarHeight + 'px';
    } else {
        // Fallback if navbar isn't found or not sticky-top
        // You might already have a default padding in CSS
        console.warn("Navbar element for dynamic padding adjustment not found or not sticky.");
    }

    fetchEmployeeStats();
});

function fetchEmployeeStats() {
    // ... (fetchEmployeeStats function remains the same as previous version)
    const statsContainer = document.getElementById('employee-stats-container');
    statsContainer.innerHTML = `
        <div class="loading-placeholder">
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="sr-only">Chargement...</span>
            </div>
            Chargement des statistiques...
        </div>`;

    fetch('employee_handler.php?action=get_employee_overview_stats', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error('Réponse réseau incorrecte: ' + response.status + ' ' + response.statusText + ' - ' + text.substring(0, 200)) });
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success' && data.stats) {
            renderStats(data.stats, data.role);
        } else {
            statsContainer.innerHTML = '<div class="error-placeholder">Erreur: ' + (data.message || 'Impossible de charger les statistiques.') + '</div>';
        }
    })
    .catch(error => {
        console.error('Erreur de récupération des statistiques:', error);
        statsContainer.innerHTML = '<div class="error-placeholder">Erreur de communication: ' + error.message + '</div>';
    });
}

function renderStats(stats, userRole) {
    // ... (renderStats function remains the same as previous version, ensure click handlers are correct)
    const statsContainer = document.getElementById('employee-stats-container');
    statsContainer.innerHTML = '';
    const isAdmin = userRole === 'admin';
    let html = '';

    const statTypes = [
        { key: 'total_employees', label: 'Total Employés Actifs', icon: 'fas fa-users', adminOnly: true, cssClass: 'total-employees' },
        { key: 'assigned_today', label: 'Assignés Aujourd\'hui', icon: 'fas fa-user-check', adminOnly: true, cssClass: 'assigned-today' },
        { key: 'active_today', label: 'En Activité (Pointage)', icon: 'fas fa-clipboard-check', adminOnly: false, cssClass: 'active-today' },
        { key: 'on_generic_leave_today', label: 'En Congé (Autre)', icon: 'fas fa-plane-departure', adminOnly: true, cssClass: 'on-leave-today' },
        { key: 'on_sick_leave_today', label: 'En Arrêt Maladie', icon: 'fas fa-briefcase-medical', adminOnly: true, cssClass: 'on-sick-leave-today' }
    ];

    statTypes.forEach(type => {
        if (stats[type.key] !== undefined && (!type.adminOnly || isAdmin)) {
            const isClickable = stats[type.key] > 0 && type.key !== 'total_employees';
            const clickHandler = isClickable ? `showEmployeeListModal('${type.key}', '${type.label}')` : '';
            const cursorStyle = isClickable ? 'cursor: pointer;' : 'cursor: default;';

            html += `
            <div class="stat-card ${type.cssClass}" ${clickHandler ? `onclick="${clickHandler}"` : ''} style="${cursorStyle}">
                <div class="stat-icon"><i class="${type.icon}"></i></div>
                <div class="stat-value">${stats[type.key]}</div>
                <div class="stat-label">${type.label}</div>
            </div>`;
        }
    });
    
    if (html === '') {
        html = '<div class="alert alert-info-custom w-100 text-center">Aucune statistique à afficher pour votre rôle ou données non disponibles.</div>';
    }
    statsContainer.innerHTML = html;
}

function showEmployeeListModal(statType, modalTitleText) {
    const modal = $('#employeeListModal');
    const modalContent = $('#employeeListModalContent');
    const modalLabel = $('#employeeListModalLabel');

    modalLabel.text(modalTitleText);
    modalContent.html(`
        <div class="loading-placeholder">
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="sr-only">Chargement...</span>
            </div>
            Chargement de la liste...
        </div>`);
    modal.modal('show');

    fetch(`employee_handler.php?action=get_employee_list_for_stat&type=${statType}`)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText + ' - ' + text.substring(0, 200)) });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' && data.employees) {
                if (data.employees.length > 0) {
                    let tableHtml = '<table class="table table-sm table-hover" id="employeeListModalTable"><thead><tr><th>Nom</th><th>Prénom</th>';
                    if (statType === 'assigned_today') {
                        tableHtml += '<th>Événement</th>'; // Add Event Name column
                    }
                    tableHtml += '<th>Email</th><th>Rôle</th></tr></thead><tbody>';

                    data.employees.forEach(emp => {
                        tableHtml += `<tr>
                                        <td>${emp.nom ? escapeHtml(emp.nom) : 'N/A'}</td>
                                        <td>${emp.prenom ? escapeHtml(emp.prenom) : 'N/A'}</td>`;
                        if (statType === 'assigned_today') {
                            tableHtml += `<td>${emp.event_title ? escapeHtml(emp.event_title) : 'N/A'}</td>`;
                        }
                        tableHtml += `<td>${emp.email ? escapeHtml(emp.email) : 'N/A'}</td>
                                      <td>${emp.role ? escapeHtml(ucfirst(emp.role)) : 'N/A'}</td>
                                      </tr>`;
                    });
                    tableHtml += '</tbody></table>';
                    modalContent.html(tableHtml);
                } else {
                    modalContent.html('<div class="alert alert-info-custom text-center">Aucun employé ne correspond à ce critère.</div>');
                }
            } else {
                modalContent.html('<div class="error-placeholder">Erreur: ' + (data.message || 'Impossible de charger la liste des employés.') + '</div>');
            }
        })
        .catch(error => {
            console.error('Erreur de récupération de la liste des employés:', error);
            modalContent.html('<div class="error-placeholder">Erreur de communication: ' + error.message + '</div>');
        });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const str = String(text);
    return str.replace(/[&<>"']/g, function (match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}

function ucfirst(str) {
    if (typeof str !== 'string' || str.length === 0) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

</script>
</body>
</html>
