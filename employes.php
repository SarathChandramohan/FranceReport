<?php
// employes.php

require_once 'session-management.php';
requireLogin(); // Ensures user is logged in
$currentUser = getCurrentUser(); // Get current user info to check role
$user_role = $currentUser['role'];

require_once 'db-connection.php'; // For fetching the general list of employees

// Function to get all employees (basic list for display on the page load)
// More detailed status would be part of the stats fetched via AJAX
function getAllEmployeesForList($conn) {
    $employees = [];
    try {
        // Fetches all users, status here refers to account status (Active/Inactive)
        // The dynamic statuses (En activité, En congé, etc.) are handled by the stats call
        $query = "SELECT user_id as id, nom, prenom, email, role, status FROM Users ORDER BY nom, prenom";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getAllEmployeesForList: " . $e->getMessage());
        // Return an empty array or handle error as appropriate
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
        body {
            background-color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            color: #1d1d1f;
            padding-top: 70px; /* Adjusted for fixed navbar */
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
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); /* Adjusted minmax */
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            padding: 20px;
            text-align: center;
            border-left: 5px solid #007aff; /* Default border color */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2.5rem; /* Slightly larger */
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.1;
            color: #333;
        }
        .stat-label {
            font-size: 0.95rem; /* Slightly larger */
            color: #555;
            font-weight: 500;
        }
        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 10px;
            opacity: 0.7;
        }

        /* Color coding and icons for stats */
        .stat-card.total-employees { border-left-color: #007bff; } /* Blue */
        .stat-card.total-employees .stat-icon { color: #007bff; }
        .stat-card.assigned-today { border-left-color: #ff9500; } /* Orange */
        .stat-card.assigned-today .stat-icon { color: #ff9500; }
        .stat-card.active-today { border-left-color: #34c759; } /* Green */
        .stat-card.active-today .stat-icon { color: #34c759; }
        .stat-card.on-leave-today { border-left-color: #5856d6; } /* Purple */
        .stat-card.on-leave-today .stat-icon { color: #5856d6; }
        .stat-card.on-sick-leave-today { border-left-color: #ff3b30; } /* Red */
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
            min-width: 700px; /* Ensure some minimum width */
        }
        table th, table td {
            padding: 12px 15px; /* Adjusted padding */
            text-align: left;
            border-bottom: 1px solid #e0e0e0; /* Lighter border */
            font-size: 14px;
            vertical-align: middle;
        }
        table th {
            background-color: #f8f9fa; /* Very light grey for header */
            font-weight: 600;
            color: #495057; /* Bootstrap's default table head color */
        }
        table tr:hover {
            background-color: #f1f3f5; /* Subtle hover effect */
        }
        .loading-placeholder, .error-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d; /* Bootstrap muted color */
            font-size: 1.1rem;
        }
        .error-placeholder {
            color: #dc3545; /* Bootstrap danger color */
        }
        .alert-custom {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: .35rem; /* Slightly more rounded */
        }
        .alert-danger-custom {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .alert-info-custom {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        /* Navbar fixed top adjustment */
        <?php
            // This PHP block directly in CSS is unusual but works for this simple case.
            // Better to handle this with a body class if more complex logic is needed.
            // Assuming navbar.php makes the navbar fixed or sticky.
            // If navbar.php has its own padding/margin for body, ensure this doesn't conflict.
            // The `padding-top: 70px;` on body is a common fix for fixed navbars.
            // This depends on your navbar's actual height.
        ?>
    </style>
</head>
<body>

<?php include 'navbar.php'; // Assuming navbar.php is correctly set up ?>

<div class="container-fluid mt-4">
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
        <h3>Liste Générale des Employés</h3>
        <div class="table-container">
            <table id="employees-table" class="table table-striped table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut Compte</th>
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
                                <td>
                                    <span class="badge badge-<?php echo $employee['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($employee['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">Aucun employé trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); // Assuming footer.php exists ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetchEmployeeStats();
});

function fetchEmployeeStats() {
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
            return response.text().then(text => { throw new Error('Réponse réseau incorrecte: ' + response.status + ' ' + response.statusText + ' - ' + text) });
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success' && data.stats) {
            renderStats(data.stats, data.role); // data.role should come from the handler
        } else {
            statsContainer.innerHTML = '<div class="error-placeholder">Erreur: ' + (data.message || 'Impossible de charger les statistiques.') + '</div>';
        }
    })
    .catch(error => {
        console.error('Erreur de récupération des statistiques:', error);
        statsContainer.innerHTML = '<div class="error-placeholder">Erreur de communication lors du chargement des statistiques. Veuillez réessayer. (' + error.message + ')</div>';
    });
}

function renderStats(stats, userRole) {
    const statsContainer = document.getElementById('employee-stats-container');
    statsContainer.innerHTML = ''; // Clear loading or previous stats

    let html = '';
    const isAdmin = userRole === 'admin';

    // Feature 1: Total employés (all employees) - visible to admin only
    if (isAdmin && stats.total_employees !== undefined) {
        html += `
        <div class="stat-card total-employees">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value">${stats.total_employees}</div>
            <div class="stat-label">Total Employés Actifs</div>
        </div>`;
    }

    // Feature 2: Assignés Aujourd'hui (today event assigned employees) - visible to admin only
    if (isAdmin && stats.assigned_today !== undefined) {
        html += `
        <div class="stat-card assigned-today">
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="stat-value">${stats.assigned_today}</div>
            <div class="stat-label">Assignés Aujourd'hui</div>
        </div>`;
    }

    // Feature 3: En Activité (people who put timesheet today) - visible to admin & others
    if (stats.active_today !== undefined) {
        html += `
        <div class="stat-card active-today">
            <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="stat-value">${stats.active_today}</div>
            <div class="stat-label">En Activité (Pointage)</div>
        </div>`;
    }

    // Feature 4: En congés (other leave today) - visible to admin only
    if (isAdmin && stats.on_generic_leave_today !== undefined) {
        html += `
        <div class="stat-card on-leave-today">
            <div class="stat-icon"><i class="fas fa-plane-departure"></i></div>
            <div class="stat-value">${stats.on_generic_leave_today}</div>
            <div class="stat-label">En Congé (Autre)</div>
        </div>`;
    }

    // Feature 5: En arrêt maladie (sick leave today) - visible to admin only
    if (isAdmin && stats.on_sick_leave_today !== undefined) {
        html += `
        <div class="stat-card on-sick-leave-today">
            <div class="stat-icon"><i class="fas fa-briefcase-medical"></i></div>
            <div class="stat-value">${stats.on_sick_leave_today}</div>
            <div class="stat-label">En Arrêt Maladie</div>
        </div>`;
    }
    
    if (html === '') {
        html = '<div class="alert alert-info-custom w-100 text-center">Aucune statistique à afficher pour votre rôle ou données non disponibles.</div>';
    }

    statsContainer.innerHTML = html;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

</body>
</html>
