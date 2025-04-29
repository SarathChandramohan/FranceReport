<?php
// 1. Include session management and check login status
require_once 'session-management.php';
requireLogin();

// 2. Include database connection
require_once 'db-connect.php';

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
    $query = "
        SELECT 
            u.id, 
            u.nom, 
            u.prenom, 
            u.email,
            u.telephone,
            u.role,
            u.date_embauche,
            CASE 
                WHEN p.id IS NOT NULL THEN 'Présent' 
                WHEN c.id IS NOT NULL THEN 'Congé'
                WHEN a.id IS NOT NULL THEN 'Arrêt maladie'
                ELSE 'Absent'
            END AS statut
        FROM 
            utilisateurs u
        LEFT JOIN 
            pointages p ON u.id = p.id_utilisateur AND p.date = CURDATE()
        LEFT JOIN 
            conges c ON u.id = c.id_utilisateur 
                AND CURDATE() BETWEEN c.date_debut AND c.date_fin
                AND c.status = 'approved'
        LEFT JOIN 
            arrets_maladie a ON u.id = a.id_utilisateur 
                AND CURDATE() BETWEEN a.date_debut AND a.date_fin
                AND a.status = 'approved'
        WHERE 
            u.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Employé non trouvé']);
        return;
    }
    
    $employee = $result->fetch_assoc();
    
    // Get additional information - current leave if any
    $leaveQuery = "
        SELECT 
            c.date_debut,
            c.date_fin,
            c.type_conge,
            c.duree
        FROM 
            conges c
        WHERE 
            c.id_utilisateur = ? 
            AND CURDATE() BETWEEN c.date_debut AND c.date_fin
            AND c.status = 'approved'
        ORDER BY 
            c.date_debut DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($leaveQuery);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $leaveResult = $stmt->get_result();
    
    if ($leaveResult->num_rows > 0) {
        $employee['current_leave'] = $leaveResult->fetch_assoc();
    }
    
    // Get additional information - current sick leave if any
    $sickLeaveQuery = "
        SELECT 
            a.date_debut,
            a.date_fin,
            a.motif,
            a.duree
        FROM 
            arrets_maladie a
        WHERE 
            a.id_utilisateur = ? 
            AND CURDATE() BETWEEN a.date_debut AND a.date_fin
            AND a.status = 'approved'
        ORDER BY 
            a.date_debut DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sickLeaveQuery);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $sickLeaveResult = $stmt->get_result();
    
    if ($sickLeaveResult->num_rows > 0) {
        $employee['current_sick_leave'] = $sickLeaveResult->fetch_assoc();
    }
    
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
    $fields = ['nom', 'prenom', 'email', 'telephone', 'role', 'date_embauche'];
    
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
    $query = "UPDATE utilisateurs SET " . implode(", ", $updateFields) . " WHERE id = ?";
    
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
            conges c
        WHERE 
            c.id_utilisateur = ?
        ORDER BY 
            c.date_demande DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaves = [];
    while ($row = $result->fetch_assoc()) {
        $leaves[] = $row;
    }
    
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
    
    // Get the date range (optional parameters)
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    
    // Prepare and execute the query to get attendance history
    $query = "
        SELECT 
            p.id,
            p.date,
            p.heure_arrivee,
            p.heure_depart,
            TIMEDIFF(p.heure_depart, p.heure_arrivee) AS duree_travail,
            p.commentaire
        FROM 
            pointages p
        WHERE 
            p.id_utilisateur = ?
            AND p.date BETWEEN ? AND ?
        ORDER BY 
            p.date DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $employeeId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    
    // Return the attendance history
    echo json_encode(['status' => 'success', 'data' => $attendance]);
}
?>