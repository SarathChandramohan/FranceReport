<?php
// 1. Include session management and database connection
require_once 'session-management.php';
require_once 'db-connection.php';

// 2. Require login - This will redirect the user if not logged in
requireLogin();

// 3. Get current user info
$user = getCurrentUser();

// 4. Get dashboard statistics
function getDashboardStats($conn) {
    $stats = [];
    
    try {
        // Get current date in SQL Server format (YYYY-MM-DD)
        $today = date('Y-m-d');
        
        // Count employees present today (those who have logged in today)
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS present_count
            FROM Timesheet
            WHERE entry_date = ? AND logon_time IS NOT NULL AND 
                  (logoff_time IS NULL OR CAST(logoff_time AS DATE) = ?)
        ");
        $stmt->execute([$today, $today]);
        $stats['employees_present'] = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'];
        
        // Count employees absent (active users who haven't logged in today)
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT u.user_id) AS absent_count
            FROM Users u
            LEFT JOIN Timesheet t ON u.user_id = t.user_id AND t.entry_date = ?
            WHERE u.status = 'Active' AND t.timesheet_id IS NULL
        ");
        $stmt->execute([$today]);
        $stats['employees_absent'] = $stmt->fetch(PDO::FETCH_ASSOC)['absent_count'];
        
        // Placeholder for pending requests (this would come from another table)
        // For now, we'll simulate this with a random number
        $stats['pending_requests'] = rand(0, 10);
        
        // Calculate total hours for the current month
        $firstDayOfMonth = date('Y-m-01');
        $lastDayOfMonth = date('Y-m-t');
        
        $stmt = $conn->prepare("
            SELECT SUM(DATEDIFF(MINUTE, logon_time, logoff_time)) AS total_minutes
            FROM Timesheet
            WHERE entry_date BETWEEN ? AND ?
            AND logon_time IS NOT NULL AND logoff_time IS NOT NULL
        ");
        $stmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
        $totalMinutes = $stmt->fetch(PDO::FETCH_ASSOC)['total_minutes'];
        
        // Convert minutes to hours
        $stats['total_hours'] = $totalMinutes ? round($totalMinutes / 60) : 0;
        
        return $stats;
        
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting dashboard stats: " . $e->getMessage());
        
        // Return default values
        return [
            'employees_present' => 0,
            'employees_absent' => 0,
            'pending_requests' => 0,
            'total_hours' => 0
        ];
    }
}

// 5. Get recent activities
function getRecentActivities($conn) {
    $activities = [];
    
    try {
        // Get most recent 5 activities, mixing timesheet entries and simulated requests
        $stmt = $conn->prepare("
            SELECT TOP 5
                u.prenom + ' ' + u.nom AS employee_name,
                CASE 
                    WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NULL THEN 'Entrée'
                    ELSE 'Sortie'
                END AS action,
                CASE 
                    WHEN t.logon_time IS NOT NULL AND t.logoff_time IS NULL THEN t.logon_time
                    ELSE t.logoff_time
                END AS action_time
            FROM Timesheet t
            INNER JOIN Users u ON t.user_id = u.user_id
            WHERE (t.logon_time IS NOT NULL OR t.logoff_time IS NOT NULL)
            ORDER BY action_time DESC
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the activities for display
        foreach ($activities as &$activity) {
            // Format the date to match the screenshot
            $timestamp = strtotime($activity['action_time']);
            $activity['date'] = date('d/m/Y', $timestamp);
            $activity['hour'] = date('H:i', $timestamp);
        }
        
        // Add a few simulated activities for demonstration
        $simulated = [
            [
                'employee_name' => 'Isabelle Blanc',
                'action' => 'Demande CP',
                'date' => date('d/m/Y'),
                'hour' => '14:15'
            ],
            [
                'employee_name' => 'Thomas Petit',
                'action' => 'Arrêt maladie',
                'date' => date('d/m/Y'),
                'hour' => '09:48'
            ]
        ];
        
        // Merge and sort activities
        $allActivities = array_merge($activities, $simulated);
        
        // Sort by date and time (latest first)
        usort($allActivities, function($a, $b) {
            $dateA = strtotime($a['date'] . ' ' . $a['hour']);
            $dateB = strtotime($b['date'] . ' ' . $b['hour']);
            return $dateB - $dateA;
        });
        
        // Return just the first 5
        return array_slice($allActivities, 0, 5);
        
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting recent activities: " . $e->getMessage());
        return [];
    }
}

// 6. Get statistics and activities
$stats = getDashboardStats($conn);
$activities = getRecentActivities($conn);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion des Ouvriers</title>
    <style>
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding-bottom: 30px;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 25px;
        }

        /* Title styling */
        h1 {
            color: #374151;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Stat Card Styling */
        .stat-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #e5e5e5;
        }

        .stat-card-title {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 10px;
            font-weight: 500;
            text-align: center;
        }

        .stat-card-value {
            font-size: 42px;
            font-weight: 600;
            color: #374151;
        }

        /* Activities Card */
        .activities-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            border: 1px solid #e5e5e5;
        }

        h2 {
            margin-bottom: 25px;
            color: #374151;
            font-size: 24px;
            font-weight: 600;
        }

        /* Table Styling */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
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
            color: #374151;
            border-bottom-width: 2px;
        }

        table td {
            color: #4b5563;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover {
            background-color: #f5f5f7;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 24px;
            }
            .stat-card {
                padding: 20px;
            }
            .stat-card-value {
                font-size: 36px;
            }
            .activities-card {
                padding: 20px;
            }
            h2 {
                font-size: 20px;
            }
            table th, table td {
                padding: 12px 14px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stat-card-value {
                font-size: 32px;
            }
            table th, table td {
                padding: 10px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h1>Tableau de bord</h1>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-title">Employés présents</div>
                <div class="stat-card-value"><?php echo $stats['employees_absent']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">Employés absents</div>
                <div class="stat-card-value"><?php echo $stats['employees_present']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">Demandes en attente</div>
                <div class="stat-card-value"><?php echo $stats['pending_requests']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-title">Heures totales ce mois</div>
                <div class="stat-card-value"><?php echo $stats['total_hours']; ?></div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="activities-card">
            <h2>Dernières activités</h2>
            <div class="table-container">
                <table>
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
                                    <td><?php echo htmlspecialchars($activity['employee_name']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['date']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['hour']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Function to refresh dashboard data via AJAX
        function refreshDashboard() {
            fetch('dashboard-handler.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateStatCards(data.stats);
                        if (data.activities) {
                            updateActivitiesTable(data.activities);
                        }
                    }
                })
                .catch(error => console.error('Error refreshing dashboard:', error));
        }

        // Update statistics cards with new data
        function updateStatCards(stats) {
            document.querySelector('.stats-grid div:nth-child(1) .stat-card-value').textContent = stats.employees_present;
            document.querySelector('.stats-grid div:nth-child(2) .stat-card-value').textContent = stats.employees_absent;
            document.querySelector('.stats-grid div:nth-child(3) .stat-card-value').textContent = stats.pending_requests;
            document.querySelector('.stats-grid div:nth-child(4) .stat-card-value').textContent = stats.total_hours;
        }

        // Update activities table with new data
        function updateActivitiesTable(activities) {
            const tbody = document.querySelector('.table-container tbody');
            
            // Clear current table content
            tbody.innerHTML = '';
            
            if (activities.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="4" style="text-align: center;">Aucune activité récente</td>';
                tbody.appendChild(row);
                return;
            }
            
            // Add new rows
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

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Refresh dashboard every 60 seconds
        setInterval(refreshDashboard, 60000);
        
        // Initial load after page is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initial refresh after 2 seconds to ensure page rendering is complete
            setTimeout(refreshDashboard, 2000);
        });
    </script>
</body>
</html>
