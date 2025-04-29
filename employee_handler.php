<?php
// 1. Include session management and check login status
require_once 'session-management.php';
requireLogin();

// 2. Include database connection
require_once 'db-connection.php';

// 3. Set the content type to JSON
header('Content-Type: application/json');

// 4. Check if action is set
if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Aucune action spécifiée']);
    exit;
}

// 5. Handle different actions
$action = $_POST['action'];

switch ($action) {
    case 'get_details':
        getEmployeeDetails($conn);
        break;
    case 'update_employee':
        updateEmployee($conn);
        break;
    case 'get_leave_history':
        getLeaveHistory($conn);
        break;
    case 'get_attendance_history':
        getAttendanceHistory($conn);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Action non reconnue']);
        break;
}

// Function to get employee details
function getEmployeeDetails($conn) {
    // Check if employee_id is set
    if (!isset($_POST['employee_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID employé manquant']);
        return;
    }
    
    $employeeId = (int)$_POST['employee_id'];
    
    // Prepare and execute the query to get employee details
    // Modified to use Users table instead of utilisateurs
    $query = "
        SELECT 
            u.user_id as id, 
            u.nom, 
            u.prenom, 
            u.email,
            u.role,
            u.status
        FROM 
            Users u
        WHERE 
            u.user_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $employeeId, PDO::PARAM_INT); // Correct for PDO
    $stmt->execute();
   $employee = $stmt->fetch(PDO::FETCH_ASSOC); // Correct for PDO

if (!$employee) { // Check if fetch returned false (no row found)
    echo json_encode(['status' => 'error', 'message' => 'Employé non trouvé']);
    return;
}
    
    $employee = $result->fetch_assoc();
    
    // Set a default status since we don't have attendance data
    $employee['statut'] = 'Présent'; // Default status
    
    // Get current leave if any
    $leaveQuery = "
        SELECT 
            c.date_debut,
            c.date_fin,
            c.type_conge,
            c.duree
        FROM 
            Conges c
        WHERE 
            c.user_id = ? 
            AND CURDATE() BETWEEN c.date_debut AND c.date_fin
            AND c.status = 'approved'
        ORDER BY 
            c.date_debut DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($leaveQuery);
    $stmt->bindParam(1, $employeeId, PDO::PARAM_INT); // Correct for PDO
    $stmt->execute();
    $currentLeave = $stmt->fetch(PDO::FETCH_ASSOC); // Correct for PDO

if ($currentLeave) { // Check if fetch returned a row
    $employee['current_leave'] = $currentLeave;
    $employee['statut'] = 'Congé'; // Update status if on leave
}
    
    // Since we don't have sick leave table, we'll skip that query
    
    // Return the employee data
    echo json_encode(['status' => 'success', 'data' => $employee]);
}

// Function to update employee information
function updateEmployee($conn) {
    // Check necessary parameters
    if (!isset($_POST['employee_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID employé manquant']);
        return;
    }
    
    $employeeId = (int)$_POST['employee_id'];
    $currentUser = getCurrentUser();
    
    // Check permissions (only admin or HR can update)
    if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'RH') {
        echo json_encode(['status' => 'error', 'message' => 'Permissions insuffisantes']);
        return;
    }
    
    // Build the SQL based on provided fields
    $updateFields = [];
    $paramTypes = "i"; // First param is always the employee ID (integer)
    $paramValues = [$employeeId];
    
    // Check for each possible field to update
    $fields = ['nom', 'prenom', 'email', 'role', 'status'];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $updateFields[] = "$field = ?";
            $paramTypes .= "s"; // All these fields are strings
            $paramValues[] = $_POST[$field];
        }
    }
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        echo json_encode(['status' => 'error', 'message' => 'Aucun champ à mettre à jour']);
        return;
    }
    
    // Build and execute the update query
    $query = "UPDATE Users SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    
    // Create the array of references that bind_param needs
    $params = array_merge([$paramTypes], $paramValues);
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    
    call_user_func_array([$stmt, 'bind_param'], $refs);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Employé mis à jour avec succès']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour: ' . $stmt->error]);
    }
}

// Function to get leave history for an employee
function getLeaveHistory($conn) {
    // Check if employee_id is set
    if (!isset($_POST['employee_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID employé manquant']);
        return;
    }
    
    $employeeId = (int)$_POST['employee_id'];
    
    // Prepare and execute the query to get leave history
    $query = "
        SELECT 
            c.conge_id,
            c.date_debut,
            c.date_fin,
            c.type_conge,
            c.duree,
            c.commentaire,
            c.status,
            c.date_demande,
            c.date_reponse,
            c.reponse_commentaire
        FROM 
            Conges c
        WHERE 
            c.user_id = ?
        ORDER BY 
            c.date_demande DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(1, $employeeId, PDO::PARAM_INT);
    $stmt->execute();
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC); // Correct for PDO
    
    // Return the leave history
    echo json_encode(['status' => 'success', 'data' => $leaves]);
}

// Function to get attendance history for an employee
function getAttendanceHistory($conn) {
    // Check if employee_id is set
    if (!isset($_POST['employee_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID employé manquant']);
        return;
    }
    
    $employeeId = (int)$_POST['employee_id'];
    
    // Since we don't have the pointages table, return an empty array
    echo json_encode(['status' => 'success', 'data' => []]);
}
?>
