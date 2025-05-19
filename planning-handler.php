<?php
// planning-handler.php (Updated for Grouped Assignments in Calendar)

// ini_set('display_errors', 1); // For debugging
// error_reporting(E_ALL); // For debugging
// ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Ensure this path is writable

require_once 'db-connection.php';
require_once 'session-management.php';

if (!function_exists('respondWithSuccess')) {
    function respondWithSuccess($message, $data = []) {
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line before respondWithSuccess for: $message. Output will be corrupted.");
            return; 
        }
        if (!headers_sent()) { 
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data]);
        exit;
    }
}

if (!function_exists('respondWithError')) {
    function respondWithError($message, $statusCode = 200) { 
        if (headers_sent($file, $line)) {
             error_log("Headers already sent in $file on line $line before respondWithError for: $message. Output will be corrupted.");
            return; 
        }
        if (!headers_sent()) { 
            http_response_code($statusCode); 
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
}

requireLogin();

$currentUser = getCurrentUser();
$user_id = $currentUser['user_id']; 
$user_role = $currentUser['role']; 

global $conn;

if (headers_sent($filename, $linenum)) {
    error_log("Headers already sent in $filename on line $linenum before planning-handler.php main logic execution. JSON response might fail.");
}

try {
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    switch ($action) {
        case 'get_staff_users':
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $stmt = $conn->prepare("SELECT user_id, nom, prenom, email, role FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            respondWithSuccess('Utilisateurs récupérés.', ['users' => $users]);
            break;

        case 'get_teams':
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $stmt = $conn->prepare("
                SELECT pt.team_id, pt.team_name, COUNT(ptm.user_id) as member_count
                FROM Planning_Teams pt LEFT JOIN Planning_Team_Members ptm ON pt.team_id = ptm.team_id
                GROUP BY pt.team_id, pt.team_name ORDER BY pt.team_name
            ");
            $stmt->execute();
            respondWithSuccess('Équipes récupérées.', ['teams' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_team_details':
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $team_id = isset($_REQUEST['team_id']) ? intval($_REQUEST['team_id']) : 0;
            if ($team_id <= 0) {
                respondWithError('ID d\'équipe invalide.');
            }
            
            $stmt_team = $conn->prepare("SELECT team_id, team_name FROM Planning_Teams WHERE team_id = ?");
            $stmt_team->execute([$team_id]);
            $team = $stmt_team->fetch(PDO::FETCH_ASSOC);
            if (!$team) {
                respondWithError('Équipe non trouvée.');
            }

            $stmt_members = $conn->prepare("
                SELECT u.user_id, u.nom, u.prenom, u.role FROM Planning_Team_Members ptm
                JOIN Users u ON ptm.user_id = u.user_id WHERE ptm.team_id = ? ORDER BY u.nom, u.prenom
            ");
            $stmt_members->execute([$team_id]);
            $team['members'] = $stmt_members->fetchAll(PDO::FETCH_ASSOC);
            respondWithSuccess('Détails de l\'équipe récupérés.', ['team' => $team]);
            break;

        case 'save_team':
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;
            $team_name = isset($_POST['team_name']) ? trim($_POST['team_name']) : '';
            $member_ids = isset($_POST['member_ids']) && is_array($_POST['member_ids']) ? $_POST['member_ids'] : [];
            if (empty($team_name)) {
                respondWithError('Le nom de l\'équipe est requis.');
            }

            $conn->beginTransaction();
            try {
                if ($team_id > 0) {
                    $stmt = $conn->prepare("UPDATE Planning_Teams SET team_name = ? WHERE team_id = ?");
                    $stmt->execute([$team_name, $team_id]);
                    $stmt_delete_members = $conn->prepare("DELETE FROM Planning_Team_Members WHERE team_id = ?");
                    $stmt_delete_members->execute([$team_id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO Planning_Teams (team_name, creator_user_id) VALUES (?, ?)");
                    $stmt->execute([$team_name, $user_id]);
                    $team_id = $conn->lastInsertId();
                }
                if ($team_id) {
                    $stmt_add_member = $conn->prepare("INSERT INTO Planning_Team_Members (team_id, user_id) VALUES (?, ?)");
                    foreach ($member_ids as $member_user_id) {
                        if (intval($member_user_id) > 0) {
                            $stmt_add_member->execute([$team_id, intval($member_user_id)]);
                        }
                    }
                }
                $conn->commit();
                respondWithSuccess('Équipe enregistrée avec succès.', ['team_id' => $team_id]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e; 
            }
            break;

        case 'delete_team':
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;
            if ($team_id <= 0) {
                respondWithError('ID d\'équipe invalide.');
            }
            $conn->beginTransaction();
             try {
                $conn->prepare("DELETE FROM Planning_Team_Members WHERE team_id = ?")->execute([$team_id]);
                $conn->prepare("DELETE FROM Planning_Teams WHERE team_id = ?")->execute([$team_id]);
                $conn->commit();
                respondWithSuccess('Équipe supprimée avec succès.');
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;

        case 'get_assignments': // MODIFIED for Grouped Assignments
            if ($user_role !== 'admin') { 
                 respondWithError('Accès refusé pour cette vue du calendrier.', 403);
            }
            $start_date_str = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
            $end_date_str = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
            
            // SQL Server Version using STRING_AGG.
            // For MySQL:
            // - Replace STRING_AGG(CONCAT(u.prenom, ' ', u.nom, CASE WHEN u.role = 'admin' THEN ' (Admin)' ELSE '' END), ', ') WITHIN GROUP (ORDER BY u.prenom, u.nom)
            //   WITH GROUP_CONCAT(DISTINCT CONCAT(u.prenom, ' ', u.nom, CASE WHEN u.role = 'admin' THEN ' (Admin)' ELSE '' END) ORDER BY u.prenom, u.nom SEPARATOR ', ')
            // - Replace STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.prenom, u.nom)
            //   WITH GROUP_CONCAT(DISTINCT CAST(pa.assigned_user_id AS CHAR) ORDER BY u.prenom, u.nom SEPARATOR ',')
            // - Ensure GROUP_CONCAT_MAX_LEN is sufficient in MySQL if many users are on the same mission.
            // - The date conversion for grouping might be simpler in MySQL (just pa.assignment_date).
            $query_sql = "
                SELECT 
                       MIN(pa.assignment_id) as representative_assignment_id, 
                       CONVERT(VARCHAR(10), pa.assignment_date, 120) as start_date_group, 
                       ISNULL(pa.start_time, '') as start_time, -- Handle NULLs for grouping
                       ISNULL(pa.end_time, '') as end_time,     -- Handle NULLs for grouping
                       pa.shift_type, 
                       ISNULL(pa.color, '#1877f2') as color,      -- Handle NULLs
                       ISNULL(pa.mission_text, '') as mission_text, -- Handle NULLs
                       ISNULL(pa.location, '') as location,       -- Handle NULLs
                       COUNT(DISTINCT pa.assigned_user_id) as user_count,
                       STRING_AGG(CONCAT(u.prenom, ' ', u.nom, CASE WHEN u.role = 'admin' THEN ' (Admin)' ELSE '' END), ', ') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_names_list,
                       STRING_AGG(CAST(pa.assigned_user_id AS VARCHAR(10)), ',') WITHIN GROUP (ORDER BY u.prenom, u.nom) as user_ids_list
                FROM Planning_Assignments pa JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assignment_date BETWEEN :start_date AND :end_date
                GROUP BY 
                       CONVERT(VARCHAR(10), pa.assignment_date, 120), 
                       ISNULL(pa.start_time, ''), 
                       ISNULL(pa.end_time, ''), 
                       pa.shift_type, 
                       ISNULL(pa.color, '#1877f2'),
                       ISNULL(pa.mission_text, ''), 
                       ISNULL(pa.location, '')
                ORDER BY start_date_group, start_time
            ";
            
            $params = [':start_date' => $start_date_str, ':end_date' => $end_date_str];
            
            $stmt = $conn->prepare($query_sql);
            $stmt->execute($params);
            $raw_grouped_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fc_events = [];

            foreach ($raw_grouped_assignments as $g_idx => $g) {
                // Create a more robust unique ID for the group for FullCalendar
                $group_event_id = "group_" . md5(
                    $g['start_date_group'] .
                    $g['start_time'] . $g['end_time'] .
                    $g['shift_type'] . $g['mission_text'] . $g['location']
                ); // Using md5 hash of defining characteristics

                $event_display_title = $g['mission_text'] ?: ucfirst($g['shift_type']);

                $event_start = $g['start_date_group'];
                $event_end = $g['start_date_group']; 
                $all_day = true;

                // Use empty strings for time if they were NULL and are now empty strings from ISNULL
                $actual_start_time = ($g['start_time'] !== '') ? $g['start_time'] : null;
                $actual_end_time = ($g['end_time'] !== '') ? $g['end_time'] : null;

                if ($actual_start_time) {
                    $event_start .= 'T' . $actual_start_time;
                    $all_day = false;
                     if ($actual_end_time) { 
                        $event_end .= 'T' . $actual_end_time; 
                    } else { 
                        try {
                            $dt = new DateTime($event_start);
                            $dt->modify('+1 hour');
                            $event_end = $dt->format('Y-m-d\TH:i:s');
                        } catch (Exception $e) {
                            $event_end = $event_start; 
                            error_log("DateTime parsing error for event_start in get_assignments (group): " . $event_start . " - " . $e->getMessage());
                        }
                    }
                }
                
                $fc_events[] = [
                    'id' => $group_event_id, 
                    'title' => $event_display_title, // Fallback title, eventContent will primarily be used
                    'start' => $event_start, 'end' => $event_end,
                    'color' => $g['color'], 'allDay' => $all_day,
                    'extendedProps' => [
                        'is_group' => true,
                        'representative_assignment_id' => $g['representative_assignment_id'],
                        'user_names_list' => $g['user_names_list'] ? explode(', ', $g['user_names_list']) : [],
                        'user_ids_list' => $g['user_ids_list'] ? explode(',', $g['user_ids_list']) : [],
                        'user_count' => intval($g['user_count']),
                        'shift_type' => $g['shift_type'], 
                        'mission_text' => $g['mission_text'] ?: '', // Ensure it's a string
                        'raw_start_time' => $actual_start_time, 
                        'raw_end_time' => $actual_end_time,
                        'raw_assignment_date' => $g['start_date_group'], 
                        'location' => $g['location'] ?: '' // Ensure it's a string
                    ]
                ];
            }
            
            if (!headers_sent()) { header('Content-Type: application/json'); }
            echo json_encode($fc_events);
            exit;
            break;

        case 'get_assignment_details': // This will fetch details for a SINGLE assignment ID
            $assignment_id_param = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : 0;
            if ($assignment_id_param <= 0) {
                respondWithError('ID d\'affectation invalide.');
            }
            $query_detail = "SELECT pa.*, u.nom, u.prenom, u.role as assigned_user_role FROM Planning_Assignments pa JOIN Users u ON pa.assigned_user_id = u.user_id WHERE pa.assignment_id = ?";
            $params_detail_arr = [$assignment_id_param];
            
            if ($user_role !== 'admin') { 
                $query_detail .= " AND pa.assigned_user_id = ?";
                $params_detail_arr[] = $user_id;
            }
            $stmt_detail = $conn->prepare($query_detail);
            $stmt_detail->execute($params_detail_arr);
            $details = $stmt_detail->fetch(PDO::FETCH_ASSOC);

            if (!$details) {
                respondWithError('Affectation non trouvée ou accès refusé.');
            }
            $details['location'] = isset($details['location']) ? $details['location'] : null;
            $details['user_name_display'] = htmlspecialchars($details['prenom'] . ' ' . $details['nom'] . ($details['assigned_user_role'] === 'admin' ? ' (Admin)' : ''));
            respondWithSuccess('Détails de l\'affectation récupérés.', ['assignment' => $details]);
            break;

        case 'save_assignment':
            // IMPORTANT: This action currently saves/updates a SINGLE assignment.
            // If you intend to edit a "group" from the calendar, this backend logic
            // would need to be significantly changed to identify all assignments
            // in the group and update them. The current `assignment_id` would
            // refer to the `representative_assignment_id` of the group.
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $assignment_id_post = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
            $assigned_user_ids_post = isset($_POST['assigned_user_ids']) ? (array)$_POST['assigned_user_ids'] : [];
            $assigned_team_id_post = isset($_POST['assigned_team_id']) ? intval($_POST['assigned_team_id']) : 0;
            $assignment_dates_post = isset($_POST['assignment_dates']) && is_array($_POST['assignment_dates']) ? $_POST['assignment_dates'] : [];

            $start_time_post = (isset($_POST['start_time']) && !empty($_POST['start_time'])) ? $_POST['start_time'] : null;
            $end_time_post = (isset($_POST['end_time']) && !empty($_POST['end_time'])) ? $_POST['end_time'] : null;
            $shift_type_post = isset($_POST['shift_type']) ? trim($_POST['shift_type']) : null;
            $mission_text_post = isset($_POST['mission_text']) ? trim($_POST['mission_text']) : null;
            $color_post = isset($_POST['color']) ? trim($_POST['color']) : '#1877f2';
            $location_post = isset($_POST['location']) ? trim($_POST['location']) : null;

            if (empty($assignment_dates_post)) {
                respondWithError('Date(s) requise(s).');
            }
            foreach($assignment_dates_post as $d_val) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d_val) || !strtotime($d_val)) {
                     respondWithError("Format de date invalide: ".htmlspecialchars($d_val).". Utilisez<y_bin_46>-MM-DD.");
                }
            }

            if (empty($assigned_user_ids_post) && $assigned_team_id_post <= 0) {
                respondWithError('Employé(s)/Admin(s) ou équipe requis.');
            }
            if (empty($shift_type_post)) {
                respondWithError('Type de service requis.');
            }
             
            if ($shift_type_post !== 'repos' && (empty($start_time_post) || empty($end_time_post))) {
                 respondWithError('Heures de début et de fin requises pour ce type de service.');
            }
            if ($shift_type_post === 'repos') {
                $start_time_post = null; $end_time_post = null;
            }

            $final_user_ids_to_assign = [];
            if ($assigned_team_id_post > 0) {
                $stmt_members_team = $conn->prepare("SELECT user_id FROM Planning_Team_Members WHERE team_id = ?");
                $stmt_members_team->execute([$assigned_team_id_post]);
                foreach ($stmt_members_team->fetchAll(PDO::FETCH_ASSOC) as $row_member) {
                    $final_user_ids_to_assign[] = $row_member['user_id'];
                }
            } else {
                foreach ($assigned_user_ids_post as $uid_post) {
                    if (intval($uid_post) > 0) {
                        $final_user_ids_to_assign[] = intval($uid_post);
                    }
                }
            }
            $final_user_ids_to_assign = array_unique($final_user_ids_to_assign);
            if (empty($final_user_ids_to_assign)) {
                respondWithError('Aucun utilisateur valide sélectionné pour l\'affectation.');
            }

            $conn->beginTransaction();
            try {
                if ($assignment_id_post > 0) {
                    // This updates a SINGLE assignment. If this ID is a representative_assignment_id of a group,
                    // only that one specific original assignment record gets updated.
                    // True group editing requires more logic here.
                    if (count($final_user_ids_to_assign) === 1 && count($assignment_dates_post) === 1) {
                        $stmt_update = $conn->prepare("UPDATE Planning_Assignments SET assigned_user_id = ?, assignment_date = ?, start_time = ?, end_time = ?, shift_type = ?, mission_text = ?, color = ?, location = ? WHERE assignment_id = ?");
                        $stmt_update->execute([$final_user_ids_to_assign[0], $assignment_dates_post[0], $start_time_post, $end_time_post, $shift_type_post, $mission_text_post, $color_post, $location_post, $assignment_id_post]);
                    } else {
                        $conn->rollBack(); 
                        respondWithError('La modification d\'affectations multiples ou pour plusieurs dates à la fois via ce formulaire est limitée. Pour affecter plusieurs personnes ou dates, créez une nouvelle affectation groupée.');
                    }
                } else { // Creating new assignments
                    $insert_stmt_new = $conn->prepare("INSERT INTO Planning_Assignments (assigned_user_id, creator_user_id, assignment_date, start_time, end_time, shift_type, mission_text, color, location, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())");
                    foreach ($assignment_dates_post as $date_str_loop) {
                        foreach ($final_user_ids_to_assign as $loop_user_id_assign) {
                            $insert_stmt_new->execute([$loop_user_id_assign, $user_id, $date_str_loop, $start_time_post, $end_time_post, $shift_type_post, $mission_text_post, $color_post, $location_post]);
                        }
                    }
                }
                $conn->commit();
                respondWithSuccess('Affectation(s) enregistrée(s).');
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;

        case 'delete_assignment':
            // IMPORTANT: This action currently deletes a SINGLE assignment by its ID.
            // If `assignment_id` is a `representative_assignment_id` of a group,
            // only that specific record is deleted. True group deletion needs more logic.
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $assignment_id_del = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
            if ($assignment_id_del <= 0) {
                respondWithError('ID d\'affectation invalide.');
            }
            $stmt_del = $conn->prepare("DELETE FROM Planning_Assignments WHERE assignment_id = ?");
            $stmt_del->execute([$assignment_id_del]);
            if ($stmt_del->rowCount() > 0) {
                respondWithSuccess('Affectation supprimée.');
            } else {
                respondWithError('Affectation non trouvée ou accès refusé.');
            }
            break;

        case 'update_assignment_date_time':
            // This action updates a SINGLE assignment. If a grouped event is dragged/resized,
            // this would update the `representative_assignment_id`.
            // True group update would require backend changes.
            if ($user_role !== 'admin') {
                respondWithError('Accès refusé.', 403);
            }
            $assignment_id_drag_param = isset($_POST['id']) ? $_POST['id'] : null; // Can be group_X or just X
            
            // If it's a group ID, we need to extract the representative_assignment_id
            // Or decide how to handle the drag/resize for a group.
            // For now, we'll assume the frontend sends a representative_assignment_id if it's a group.
            // This part needs careful implementation on how group drag/resize translates to individual assignments.
            // The current eventDrop/eventResize in JS just sends event.id. If event.id is "group_...", this won't work.
            // The JS side should send the 'representative_assignment_id' for grouped events.
            
            $assignment_id_drag = 0;
            if (strpos($assignment_id_drag_param, 'group_') === 0) {
                 // This is tricky. The client needs to send the representative_assignment_id or
                 // the backend needs to query all assignments in the group based on the group's defining features.
                 // For simplicity, we'll assume the client sends the representative ID or this won't correctly update groups.
                 // This is a placeholder for what should be a more complex group update.
                 // The current FullCalendar event.id is the group_md5(...) string.
                 // It's better to not allow drag/drop for groups or implement full group update logic.
                 // For now, this will likely fail for groups or update only one assignment if representative_assignment_id is used.
                 respondWithError('La modification par glisser-déposer des groupes n\'est pas encore entièrement prise en charge de cette manière.', 501);
                 // $assignment_id_drag = intval(substr($assignment_id_drag_param, strlen('group_'))); // Not robust
            } else {
                $assignment_id_drag = intval($assignment_id_drag_param);
            }


            $new_start_datetime_str_drag = isset($_POST['start']) ? $_POST['start'] : null;
            $new_end_datetime_str_drag = isset($_POST['end']) ? $_POST['end'] : null;

            if ($assignment_id_drag <= 0) {
                respondWithError('ID d\'affectation invalide pour glisser-déposer.');
            }
            if (!$new_start_datetime_str_drag) {
                respondWithError('Nouvelle date/heure de début requise.');
            }

            try {
                $start_dt_drag = new DateTime($new_start_datetime_str_drag);
                $new_assignment_date_drag = $start_dt_drag->format('Y-m-d');
                $new_start_time_drag = $start_dt_drag->format('H:i:s');
                $new_end_time_drag = null;

                if ($new_end_datetime_str_drag) {
                    $end_dt_drag = new DateTime($new_end_datetime_str_drag);
                    $new_end_time_drag = $end_dt_drag->format('H:i:s');
                }
                if ($new_start_time_drag == '00:00:00' && !$new_end_datetime_str_drag) { 
                    $new_start_time_drag = null;
                    $new_end_time_drag = null;
                }
            } catch (Exception $e) {
                respondWithError('Format date/heure invalide pour la mise à jour: ' . $e->getMessage());
            }
            
            $stmt_check_shift = $conn->prepare("SELECT shift_type FROM Planning_Assignments WHERE assignment_id = ?");
            $stmt_check_shift->execute([$assignment_id_drag]);
            $original_assignment = $stmt_check_shift->fetch(PDO::FETCH_ASSOC);

            if (!$original_assignment) {
                 respondWithError('Affectation originale non trouvée pour glisser-déposer.');
            }

            if ($original_assignment['shift_type'] === 'repos') {
                $stmt_drag_update = $conn->prepare("UPDATE Planning_Assignments SET assignment_date = ? WHERE assignment_id = ?");
                $stmt_drag_update->execute([$new_assignment_date_drag, $assignment_id_drag]);
            } else {
                 $stmt_drag_update = $conn->prepare("UPDATE Planning_Assignments SET assignment_date = ?, start_time = ?, end_time = ? WHERE assignment_id = ?");
                 $stmt_drag_update->execute([$new_assignment_date_drag, $new_start_time_drag, $new_end_time_drag, $assignment_id_drag]);
            }

            if ($stmt_drag_update->rowCount() > 0) {
                respondWithSuccess('Affectation mise à jour.');
            } else {
                 respondWithSuccess('Aucune modification détectée ou affectation déjà à jour.');
            }
            break;

        case 'get_staff_dashboard_data': 
            $today = date('Y-m-d');
            $upcoming_limit = 3;

            $stmt_today_sql = "
                SELECT TOP 1 pa.*, u.nom, u.prenom, u.role as assigned_user_role
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assigned_user_id = :user_id AND pa.assignment_date = :today
                ORDER BY pa.start_time
            ";
            $stmt_today = $conn->prepare($stmt_today_sql);
            $stmt_today->bindParam(':user_id', $user_id, PDO::PARAM_INT); 
            $stmt_today->bindParam(':today', $today, PDO::PARAM_STR);
            $stmt_today->execute();
            $today_assignment = $stmt_today->fetch(PDO::FETCH_ASSOC);
            if ($today_assignment) {
                $today_assignment['location'] = $today_assignment['location'] ?? null;
            }

            $stmt_upcoming_sql = "
                SELECT pa.*, u.nom, u.prenom, u.role as assigned_user_role
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assigned_user_id = :user_id AND pa.assignment_date > :today
                ORDER BY pa.assignment_date ASC, pa.start_time ASC
                OFFSET 0 ROWS FETCH NEXT :limit ROWS ONLY
            "; 
            $stmt_upcoming = $conn->prepare($stmt_upcoming_sql);
            $stmt_upcoming->bindParam(':user_id', $user_id, PDO::PARAM_INT); 
            $stmt_upcoming->bindParam(':today', $today, PDO::PARAM_STR);
            $stmt_upcoming->bindParam(':limit', $upcoming_limit, PDO::PARAM_INT);
            $stmt_upcoming->execute();
            $upcoming_assignments_raw = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);
            $upcoming_assignments = [];
            foreach($upcoming_assignments_raw as $ua_raw){
                $ua_raw['location'] = $ua_raw['location'] ?? null;
                $upcoming_assignments[] = $ua_raw;
            }

            respondWithSuccess('Données du tableau de bord personnel récupérées.', [
                'today_assignment' => $today_assignment,
                'upcoming_assignments' => $upcoming_assignments
            ]);
            break;

        case 'get_staff_list_assignments': 
            if (!isset($_GET['user_id']) || !isset($_GET['period'])) {
                respondWithError('ID utilisateur et période sont requis.');
            }
            $list_view_user_id = intval($_GET['user_id']);
            $period = strtolower(trim($_GET['period']));
            $today_date = date('Y-m-d');
    
            if ($currentUser['user_id'] != $list_view_user_id && $currentUser['role'] !== 'admin') {
                respondWithError('Accès refusé pour consulter ces données de planning.', 403);
            }
    
            $sql_list_view = "
                SELECT pa.assignment_id, pa.assignment_date, pa.start_time, pa.end_time,
                       pa.shift_type, pa.mission_text, pa.location, pa.color,
                       u.nom, u.prenom, u.role as assigned_user_role
                FROM Planning_Assignments pa
                JOIN Users u ON pa.assigned_user_id = u.user_id
                WHERE pa.assigned_user_id = :list_view_user_id";
            
            $params_list_view = [':list_view_user_id' => $list_view_user_id];
    
            switch ($period) {
                case 'past':
                    $sql_list_view .= " AND pa.assignment_date < :today_date ORDER BY pa.assignment_date DESC, pa.start_time DESC";
                    $params_list_view[':today_date'] = $today_date;
                    break;
                case 'future':
                    $sql_list_view .= " AND pa.assignment_date > :today_date ORDER BY pa.assignment_date ASC, pa.start_time ASC";
                    $params_list_view[':today_date'] = $today_date;
                    break;
                case 'current':
                default:
                    $sql_list_view .= " AND pa.assignment_date >= :today_date ORDER BY pa.assignment_date ASC, pa.start_time ASC";
                    $params_list_view[':today_date'] = $today_date;
                    break;
            }
    
            $stmt_list_view = $conn->prepare($sql_list_view);
            $stmt_list_view->execute($params_list_view);
            $assignments_list_raw = $stmt_list_view->fetchAll(PDO::FETCH_ASSOC);
            
            $output_assignments_list = [];
            foreach($assignments_list_raw as $a_item) {
                $a_item['location'] = $a_item['location'] ?? null;
                $output_assignments_list[] = $a_item;
            }
    
            respondWithSuccess('Affectations de la liste du personnel récupérées.', ['assignments' => $output_assignments_list]);
            break;

        default:
            respondWithError('Action invalide spécifiée.');
    }
} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("DB Error (planning-handler): " . $e->getMessage() . " Query: " . (isset($stmt) && $stmt ? $stmt->queryString : "N/A") . " Params: " . (isset($params) ? json_encode($params) : (isset($params_list_view) ? json_encode($params_list_view) : "N/A" )));
    respondWithError('Erreur de base de données. Veuillez réessayer plus tard.', 500);
} catch (Exception $e) {
    error_log("General Error (planning-handler): " . $e->getMessage());
    respondWithError('Une erreur inattendue est survenue: ' . $e->getMessage(), 500);
}

?>
