<?php
// employes.php

require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();
$user_role = $user['role']; // 'admin' or other

require_once 'db-connection.php';

// Initialize stats variables
$stats = [
    'total_employees' => 0,
    'assigned_today' => 0,
    'active_today' => 0,
    'on_leave_today' => 0,
    'on_sick_leave_today' => 0
];

// Fetch stats - In a real scenario, this data would come from an AJAX call to employee_handler.php
// For this example, we'll include the logic directly or via a function for simplicity.
// However, the proper way is to use JavaScript and AJAX to call employee_handler.php.

// For demonstration, we'll simulate fetching data that employee_handler.php would provide.
// The actual fetching logic is in employee_handler.php.
// In a production setup, employes.php would use JavaScript to call employee_handler.php
// and then populate the stats.

// Function to get all employees (basic list for display)
function getAllEmployeesBasic($conn) {
    $employees = [];
    try {
        $query = "SELECT user_id as id, nom, prenom, email, role, status FROM Users ORDER BY nom, prenom";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getAllEmployeesBasic: " . $e->getMessage());
    }
    return $employees;
}

$all_employees_list = getAllEmployeesBasic($conn);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employés - Gestion</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1d1d1f;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            border: 1px solid #e5e5e5;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            line-height: 1;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        /* Color coding for stats */
        .stat-total { color: #007aff; } /* Blue */
        .stat-assigned { color: #ff9500; } /* Orange */
        .stat-active { color: #34c759; } /* Green */
        .stat-leave { color: #5856d6; } /* Purple */
        .stat-sick { color: #ff3b30; } /* Red */

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
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
            font-size: 14px;
        }
        table th {
            background-color: #f9f9f9;
            font-weight: 600;
            color: #333;
        }
        .loading-placeholder {
            text-align: center;
            padding: 20px;
            color: #888;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4">
    <h2>Gestion des Employés</h2>

    <div class="card">
        <h3>Statistiques du Jour</h3>
        <div class="stats-container" id="employee-stats-container">
            <div class="loading-placeholder">Chargement des statistiques...</div>
        </div>
    </div>

    <div class="card">
        <h3>Liste des Employés</h3>
        <div class="table-container">
            <table id="employees-table">
                <thead>
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
                                <td><?php echo htmlspecialchars($employee['role']); ?></td>
                                <td><?php echo htmlspecialchars($employee['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Aucun employé trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetchEmployeeStats();
});

function fetchEmployeeStats() {
    const statsContainer = document.getElementById('employee-stats-container');
    statsContainer.innerHTML = '<div class="loading-placeholder">Chargement des statistiques...</div>';

    fetch('employee_handler.php?action=get_employee_overview_stats', { // Changed action name
        method: 'GET', // Using GET for fetching data
        headers: {
            'Content-Type': 'application/json',
            // Add any other necessary headers, like CSRF tokens if you use them
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText);
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success' && data.stats) {
            renderStats(data.stats, data.role);
        } else {
            statsContainer.innerHTML = '<div class="alert alert-danger">Erreur: ' + (data.message || 'Impossible de charger les statistiques.') + '</div>';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        statsContainer.innerHTML = '<div class="alert alert-danger">Erreur de communication lors du chargement des statistiques. ' + error.message + '</div>';
    });
}

function renderStats(stats, role) {
    const statsContainer = document.getElementById('employee-stats-container');
    statsContainer.innerHTML = ''; // Clear loading or previous stats

    let html = '';

    // Feature 1: Total employés (all employees) - visible to admin only
    if (role === 'admin' && stats.total_employees !== undefined) {
        html += `
        <div class="stat-card">
            <div class="stat-value stat-total">${stats.total_employees}</div>
            <div class="stat-label">Total Employés</div>
        </div>`;
    }

    // Feature 2: Assignés Aujourd'hui (today event assigned employees) - visible to admin only
    if (role === 'admin' && stats.assigned_today !== undefined) {
        html += `
        <div class="stat-card">
            <div class="stat-value stat-assigned">${stats.assigned_today}</div>
            <div class="stat-label">Assignés Aujourd'hui</div>
        </div>`;
    }

    // Feature 3: En Activité (people who put timesheet today) - visible to admin & others
    if (stats.active_today !== undefined) {
        html += `
        <div class="stat-card">
            <div class="stat-value stat-active">${stats.active_today}</div>
            <div class="stat-label">En Activité (Pointage)</div>
        </div>`;
    }

    // Feature 4: En congés (other leave today) - visible to admin only
    if (role === 'admin' && stats.on_generic_leave_today !== undefined) { // Renamed for clarity
        html += `
        <div class="stat-card">
            <div class="stat-value stat-leave">${stats.on_generic_leave_today}</div>
            <div class="stat-label">En Congé (Autre)</div>
        </div>`;
    }

    // Feature 5: En arrêt maladie (sick leave today) - visible to admin only
    if (role === 'admin' && stats.on_sick_leave_today !== undefined) {
        html += `
        <div class="stat-card">
            <div class="stat-value stat-sick">${stats.on_sick_leave_today}</div>
            <div class="stat-label">En Arrêt Maladie</div>
        </div>`;
    }
    
    if (html === '') {
        html = '<div class="alert alert-info">Aucune statistique disponible pour votre rôle.</div>';
    }

    statsContainer.innerHTML = html;
}
</script>

</body>
</html>
