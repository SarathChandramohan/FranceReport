<?php
// dashboard-handler.php - AJAX handler for dashboard statistics and data

// Include database connection and session management
require_once 'db-connection.php';
require_once 'session-management.php';

// Ensure user is logged in
requireLogin();

// Get the current user
$user = getCurrentUser();

// Check the requested action
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'get_stats':
        getDashboardData();
        break;
    default:
        respondWithError('Invalid action specified');
}

/**
 * Gets dashboard statistics and recent activities
 */
function getDashboardData() {
    global $conn;
    
    try {
        // Get dashboard statistics
        $stats = getDashboardStats($conn);
        
        // Get recent activities
        $activities = getRecentActivities($conn);
        
        // Respond with success and data
        respondWithSuccess('Dashboard data retrieved successfully', [
            'stats' => $stats,
            'activities' => $activities
        ]);
        
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting dashboard data: " . $e->getMessage());
        
        // Return error response
        respondWithError('Database error: ' . $e->getMessage());
    }
}

/**
 * Gets dashboard statistics
 * 
 * @param PDO $conn Database connection
 * @return array Array of statistics
 */
function getDashboardStats($conn) {
    $stats = [];
    
    try {
        // Get current date in SQL Server format (YYYY-MM-DD)
        $today = date('Y-m-d');
        
        // Count employees present today (those who have logged in today)
        // Uses the proper column names from the Timesheet table
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) AS present_count
            FROM Timesheet
            WHERE entry_date = ? AND logon_time IS NOT NULL AND 
                  (logoff_time IS NULL OR CAST(logoff_time AS DATE) = ?)
        ");
        $stmt->execute([$today, $today]);
        $stats['employees_present'] = $stmt->fetch(PDO::FETCH_ASSOC)['present_count'];
        
        // Count employees absent (active users who haven't logged in today)
        // Uses the proper table structure from Users and Timesheet
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT u.user_id) AS absent_count
            FROM Users u
            LEFT JOIN Timesheet t ON u.user_id = t.user_id AND t.entry_date = ?
            WHERE u.status = 'Active' AND t.timesheet_id IS NULL
        ");
        $stmt->execute([$today]);
        $stats['employees_absent'] = $stmt->fetch(PDO::FETCH_ASSOC)['absent_count'];
        
        // Get pending leave requests from a separate function
        // This would be implemented once you have a leave request table
        $stats['pending_requests'] = getPendingRequestsCount($conn);
        
        // Calculate total hours for the current month
        // Uses DATEDIFF to calculate the difference in minutes between logon and logoff times
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

/**
 * Gets the count of pending requests
 * In a real implementation, this would query a leave requests table
 * For now, we'll return a placeholder number
 *
 * @param PDO $conn Database connection
 * @return int Number of pending requests
 */
function getPendingRequestsCount($conn) {
    // TODO: Replace this with actual query to a leave requests table when available
    return rand(0, 10); // Placeholder for demo purposes
}

/**
 * Gets recent activities for the dashboard
 * 
 * @param PDO $conn Database connection
 * @return array Array of recent activities
 */
function getRecentActivities($conn) {
    try {
        // Get most recent 5 timesheet entries (clock in/out)
        // Uses proper concatenation of first and last name from Users table
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
            // Format the date to dd/mm/yyyy format
            $timestamp = strtotime($activity['action_time']);
            $activity['date'] = date('d/m/Y', $timestamp);
            $activity['hour'] = date('H:i', $timestamp);
            unset($activity['action_time']); // Remove the raw timestamp
        }
        
        // In a real implementation, you would merge activities from other tables
        // like leave requests or sick days.
        // For demonstration, we'll add a few simulated entries
        $additionalActivities = getAdditionalActivities();
        
        // Merge and sort activities
        $allActivities = array_merge($activities, $additionalActivities);
        
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

/**
 * Gets additional simulated activities (leave requests, sick days)
 * In a real implementation, this would query other relevant tables
 *
 * @return array Array of additional activities
 */
function getAdditionalActivities() {
    // TODO: Replace with actual queries to relevant tables when available
    return [
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
}

/**
 * Sends a success response with JSON
 * 
 * @param string $message Success message
 * @param array $data Optional data to include in the response
 */
function respondWithSuccess($message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Sends an error response with JSON
 * 
 * @param string $message Error message
 */
function respondWithError($message) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}