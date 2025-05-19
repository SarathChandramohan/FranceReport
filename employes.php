<?php
// 1. Ensure no output before this PHP block
// No spaces, blank lines, or BOM before <?php

// 2. Include session management, which starts the session and defines requireLogin()
require_once 'session-management.php';

// 3. Require login - This will redirect the user to index.php and exit
// if they are not logged in.
requireLogin();

// 4. If the script reaches this point, the user IS logged in.
// Now you can safely output content.
$user = getCurrentUser();

// Get current date in Y-m-d format for database queries
$today = date('Y-m-d');

// Connect to database
require_once 'db-connection.php';

// Function to get all employees with their presence status
function getAllEmployeesWithStatus($conn, $date) {
    $employees = []; // Initialize $employees as an empty array
    try {
        $query = "
            SELECT
                u.user_id as id,
                u.nom,
                u.prenom,
                u.role,
                u.email,
                u.status,
                CASE
                    WHEN c.conge_id IS NOT NULL THEN 'Congé'
                    ELSE 'Présent'
                END AS statut
            FROM
                Users u
            LEFT JOIN
                Conges c ON u.user_id = c.user_id
                    AND ? BETWEEN c.date_debut AND c.date_fin
                    AND c.status = 'approved'
            ORDER BY
                u.nom, u.prenom
        ";

        $stmt = $conn->prepare($query);
        // Bind the parameter - Ensure this line uses bindParam as corrected before
        // If you used bindValue before, keep that. bindParam is shown here:
        $stmt->bindParam(1, $date, PDO::PARAM_STR);

        $stmt->execute();

        // Fetch all results into the employees array
        // If fetchAll fails (unlikely with ERRMODE_EXCEPTION), $employees was already initialized as []
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Handle potential database errors. Log the error instead of just letting it fatal.
        // In a production environment, you might show a generic error message to the user.
        error_log("Database error in getAllEmployeesWithStatus: " . $e->getMessage());
        // $employees is already initialized as [], so no need to set it again unless you
        // wanted to return null on error, which we are trying to avoid for count().
        // For this fix, we ensure it's an empty array on error.
    }

    return $employees; // Always return an array (empty or populated)
}

// Get all employees with status
$employees = getAllEmployeesWithStatus($conn, $today);

// Get counts for dashboard summary
$totalEmployees = count($employees);
$presentCount = 0;
$absentCount = 0;
$congeCount = 0;
$maladieCount = 0;

foreach ($employees as $emp) {
    switch ($emp['statut']) {
        case 'Présent':
            $presentCount++;
            break;
        case 'Absent':
            $absentCount++;
            break;
        case 'Congé':
            $congeCount++;
            break;
        case 'Arrêt maladie':
            $maladieCount++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employés - Gestion des Ouvriers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <!-- Original CSS styles from timesheet.html -->
    <style>
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            /* Apple-like font stack */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body {
            /* Light gray background */
            background-color: #f5f5f7;
            color: #1d1d1f; /* Default dark text */
            -webkit-font-smoothing: antialiased; /* Smoother fonts on WebKit */
            -moz-osx-font-smoothing: grayscale; /* Smoother fonts on Firefox */
        }

        /* Card Styling */
        .card {
            background-color: #ffffff;
            border-radius: 12px; /* More rounded corners */
            /* Subtle shadow */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px; /* Increased padding */
            margin-bottom: 25px; /* Consistent margin */
            border: 1px solid #e5e5e5; /* Very subtle border */
        }

        h2 {
            margin-bottom: 25px; /* Increased margin */
            color: #1d1d1f; /* Dark text */
            font-size: 28px; /* Larger heading */
            font-weight: 600;
        }
        h3 {
             margin-bottom: 20px;
             font-size: 18px;
             font-weight: 600;
             color: #1d1d1f;
        }

        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border: 1px solid #e5e5e5; /* Border around table container */
            border-radius: 8px; /* Rounded corners */
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px; /* Adjusted min-width */
        }

        table th, table td {
            padding: 14px 16px; /* Increased padding */
            text-align: left;
            border-bottom: 1px solid #e5e5e5; /* Lighter border */
            font-size: 14px;
            color: #1d1d1f;
        }
        table td { color: #555; } /* Slightly lighter text for data */

        table th {
            background-color: #f9f9f9; /* Very light gray header */
            font-weight: 600; /* Bolder header */
            color: #333; /* Darker header text */
            border-bottom-width: 2px; /* Thicker bottom border for header */
        }

        table tr:last-child td {
             border-bottom: none; /* Remove border from last row */
        }

        table tr:hover {
            background-color: #f5f5f7; /* Subtle hover */
        }

        /* Status Tags */
        .status-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }
        .status-present {
            background-color: #34c759;
            color: white;
        }
        .status-absent {
            background-color: #ff3b30;
            color: white;
        }
        .status-conge {
            background-color: #007aff;
            color: white;
        }
        .status-maladie {
            background-color: #ff9500;
            color: white;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
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

        .stat-present .stat-value { color: #34c759; }
        .stat-absent .stat-value { color: #ff3b30; }
        .stat-conge .stat-value { color: #007aff; }
        .stat-maladie .stat-value { color: #ff9500; }

        /* Search Bar */
        .search-container {
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d2d2d7;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #007aff;
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
        }

        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            background-color: #f5f5f7;
            border: 1px solid #d2d2d7;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            color: #555;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background-color: #e5e5ea;
        }

        .filter-btn.active {
            background-color: #007aff;
            color: white;
            border-color: #007aff;
        }

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
            margin: 8% auto;
            padding: 30px;
            border-radius: 14px;
            width: 90%;
            max-width: 700px;
            position: relative;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: 300;
            cursor: pointer;
            color: #aaa;
            transition: color 0.2s;
        }

        .close:hover {
            color: #333;
        }

        /* Employee Details */
        .employee-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 15px;
        }

        .detail-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        /* Badge */
        .badge {
            display: inline-block; 
            min-width: 18px; 
            padding: 3px 7px; 
            font-size: 11px; 
            font-weight: 700; 
            line-height: 1; 
            color: #fff; 
            text-align: center; 
            white-space: nowrap; 
            vertical-align: baseline; 
            background-color: #ff3b30; 
            border-radius: 9px; 
            margin-left: 6px;
        }

        /* Button Styling */
        button, .btn-primary, .btn-success, .btn-danger, .btn-warning {
            padding: 12px 24px; /* Generous padding */
            border: none;
            border-radius: 8px; /* Rounded corners */
            cursor: pointer;
            font-weight: 600; /* Bolder font */
            font-size: 15px;
            transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
            margin-bottom: 10px;
            line-height: 1.2; /* Ensure text vertical alignment */
        }

        .btn-primary { background-color: #007aff; color: white; }
        .btn-primary:hover { background-color: #0056b3; } /* Darker blue on hover */

        .btn-success { background-color: #34c759; color: white; } /* Apple green */
        .btn-success:hover { background-color: #2ca048; } /* Darker green */

        .btn-danger { background-color: #ff3b30; color: white; } /* Apple red */
        .btn-danger:hover { background-color: #d63027; } /* Darker red */

        .btn-warning { background-color: #ff9500; color: white; } /* Apple orange */
        .btn-warning:hover { background-color: #d97e00; } /* Darker orange */

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .header-content { flex-direction: column; text-align: center; gap: 10px; }
            .header-content h1 { font-size: 22px; }
            nav ul { justify-content: center; gap: 8px 15px; }
            nav a { font-size: 13px; padding: 5px 10px; }
            h2 { font-size: 24px; }
            .card { padding: 20px; border-radius: 10px; }
            .stats-container { grid-template-columns: 1fr 1fr; }
            .employee-details { grid-template-columns: 1fr; }
            .modal-content { width: 95%; margin: 5% auto; padding: 20px; }
            table th, table td { padding: 12px 14px; font-size: 13px; }
        }

        @media (max-width: 480px) {
            .container { padding: 15px; }
            h2 { font-size: 22px; }
            .card { padding: 15px; }
            .stats-container { grid-template-columns: 1fr; }
            table th, table td { padding: 10px 12px; font-size: 12px; }
            .modal-content { padding: 15px; }
            nav ul { gap: 5px 10px; }
            nav a { font-size: 12px; padding: 4px 8px; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>
    <div class="container-fluid">
        <h2>Employés</h2>

        <!-- Stats summary cards -->
        <div class="stats-container">
            <div class="stat-card stat-present">
                <div class="stat-value"><?php echo $presentCount; ?></div>
                <div class="stat-label">Présents</div>
            </div>
            <div class="stat-card stat-absent">
                <div class="stat-value"><?php echo $absentCount; ?></div>
                <div class="stat-label">Absents</div>
            </div>
            <div class="stat-card stat-conge">
                <div class="stat-value"><?php echo $congeCount; ?></div>
                <div class="stat-label">En congé</div>
            </div>
            <div class="stat-card stat-maladie">
                <div class="stat-value"><?php echo $maladieCount; ?></div>
                <div class="stat-label">En arrêt maladie</div>
            </div>
        </div>

        <div class="card">
            <h3>Liste des employés</h3>

            <!-- Search and filter controls -->
            <div class="search-container">
                <input type="text" id="search-input" class="search-input" placeholder="Rechercher un employé...">
            </div>

            <div class="filter-controls">
                <button class="filter-btn active" data-filter="all">Tous (<?php echo $totalEmployees; ?>)</button>
                <button class="filter-btn" data-filter="présent">Présents (<?php echo $presentCount; ?>)</button>
                <button class="filter-btn" data-filter="absent">Absents (<?php echo $absentCount; ?>)</button>
                <button class="filter-btn" data-filter="congé">En congé (<?php echo $congeCount; ?>)</button>
                <button class="filter-btn" data-filter="arrêt maladie">En arrêt maladie (<?php echo $maladieCount; ?>)</button>
            </div>

            <div class="table-container">
                <table id="employees-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr data-status="<?php echo strtolower($employee['statut']); ?>">
                                <td><?php echo htmlspecialchars($employee['nom']); ?></td>
                                <td><?php echo htmlspecialchars($employee['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($employee['role']); ?></td>
                                <td>
                                    <?php 
                                    // Display status with appropriate styling
                                    $statusClass = '';
                                    switch ($employee['statut']) {
                                        case 'Présent':
                                            $statusClass = 'status-present';
                                            break;
                                        case 'Absent':
                                            $statusClass = 'status-absent';
                                            break;
                                        case 'Congé':
                                            $statusClass = 'status-conge';
                                            break;
                                        case 'Arrêt maladie':
                                            $statusClass = 'status-maladie';
                                            break;
                                    }
                                    echo '<span class="status-tag ' . $statusClass . '">' . htmlspecialchars($employee['statut']) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <button class="btn-primary" onclick="showEmployeeDetails(<?php echo $employee['id']; ?>)">Détails</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Employee Details Modal -->
    <div id="employee-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modal-employee-name">Détails de l'employé</h3>
            <div id="employee-details-content" class="employee-details">
                <!-- Employee details will be inserted here -->
            </div>
        </div>
    </div>
<?php include('footer.php'); ?>
    <script>
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Get filter value
                const filter = this.getAttribute('data-filter');
                
                // Filter table rows
                filterTable(filter);
            });
        });
        
        function filterTable(filter) {
            const rows = document.querySelectorAll('#employees-table tbody tr');
            
            rows.forEach(row => {
                if (filter === 'all') {
                    row.style.display = '';
                } else {
                    const status = row.getAttribute('data-status').toLowerCase();
                    if (status === filter.toLowerCase()) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
        
        // Search functionality
        document.getElementById('search-input').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#employees-table tbody tr');
            
            rows.forEach(row => {
                const nom = row.cells[0].textContent.toLowerCase();
                const prenom = row.cells[1].textContent.toLowerCase();
                const role = row.cells[2].textContent.toLowerCase();
                
                if (nom.includes(searchValue) || prenom.includes(searchValue) || role.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Show employee details
        function showEmployeeDetails(employeeId) {
            // In a real application, you would fetch this data from the server
            // For now, we'll simulate it with a simple AJAX call
            
            // Make an AJAX request to get employee details
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'employee-handler.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            displayEmployeeDetails(response.data);
                        } else {
                            alert('Erreur: ' + response.message);
                        }
                    } catch (e) {
                        alert('Erreur lors du traitement des données');
                    }
                }
            };
            xhr.send('action=get_details&employee_id=' + employeeId);
            
            // For demonstration, let's just show the modal with dummy data
            // This would be replaced by the AJAX response in a real application
            const dummyData = {
                id: employeeId,
                nom: "Nom de l'employé " + employeeId,
                prenom: "Prénom de l'employé",
                email: "employe" + employeeId + "@example.com",
                telephone: "06" + Math.floor(10000000 + Math.random() * 90000000),
                role: "Ouvrier",
                date_embauche: "2023-01-15",
                statut: Math.random() > 0.5 ? "Présent" : "Absent"
            };
            
            displayEmployeeDetails(dummyData);
        }
        
        
function displayEmployeeDetails(employee) {
    // Set employee name in modal title
    document.getElementById('modal-employee-name').textContent = 
        employee.prenom + ' ' + employee.nom;
    
    // Generate HTML for employee details
    let detailsHTML = `
        <div class="detail-item">
            <div class="detail-label">Email</div>
            <div class="detail-value">${employee.email}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Rôle</div>
            <div class="detail-value">${employee.role}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Statut utilisateur</div>
            <div class="detail-value">${employee.status}</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Statut présence</div>
            <div class="detail-value">
                <span class="status-tag ${getStatusClass(employee.statut)}">
                    ${employee.statut}
                </span>
            </div>
        </div>
    `;
    
    // Add current leave information if available
    if (employee.current_leave) {
        detailsHTML += `
            <div class="detail-item" style="grid-column: span 2">
                <div class="detail-label">Congé en cours</div>
                <div class="detail-value">
                    Du ${formatDate(employee.current_leave.date_debut)} au ${formatDate(employee.current_leave.date_fin)}
                    <br>Type: ${employee.current_leave.type_conge}
                    <br>Durée: ${employee.current_leave.duree} jour(s)
                </div>
            </div>
        `;
    }
    
    // Insert HTML into modal
    document.getElementById('employee-details-content').innerHTML = detailsHTML;
    
    // Show the modal
    document.getElementById('employee-modal').style.display = 'block';
}
        function getStatusClass(status) {
    switch (status) {
        case 'Présent': return 'status-present';
        case 'Absent': return 'status-absent';
        case 'Congé': return 'status-conge';
        case 'Arrêt maladie': return 'status-maladie';
        default: return '';
    }
}
        function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}
        
        function closeModal() {
            document.getElementById('employee-modal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('employee-modal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
