<?php
// planning.php (Updated for Grouped Assignments, Modal, Predefined Colors, and Mobile Admin Toolbar)

require_once 'session-management.php';
require_once 'db-connection.php'; // Ensure this path is correct
requireLogin();

$user = getCurrentUser();
$user_id_logged_in = $user['user_id'];
$user_role = $user['role'];

// Define predefined colors
$predefined_colors = [
    '#1877f2', '#34c759', '#ff9500', '#5856d6', '#ff3b30',
    '#007aff', '#ffcc00', '#8e8e93', '#ff2d55', '#00a096'
];
$default_color = $predefined_colors[0];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Planning - <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* Common Styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f0f2f5;
            color: #1c1e21;
        }
        .body-with-staff-nav {
            padding-bottom: 70px;
        }
        .main-container {
            padding-top: 15px;
            padding-bottom: 20px; /* Default padding */
        }
        .main-container-with-staff-nav {
             padding-bottom: 80px !important;
        }

        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background-color: #f7f7f7;
            font-weight: 600;
            border-bottom: 1px solid #dddfe2;
            padding: 0.75rem 1.25rem;
            font-size: 1.1rem;
        }
        .btn-primary { background-color: #1877f2; border-color: #1877f2; }
        .btn-primary:hover { background-color: #166fe5; border-color: #166fe5; }
        .btn-sm { padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem; }
        .form-control-sm { font-size: .875rem; }

        /* FullCalendar Customizations (Primarily for Admin View) */
        #calendar { font-size: 0.9em; }
        .fc-event { cursor: pointer; padding: 3px 5px; border-radius: 4px; }
        .fc-toolbar-title { font-size: 1.25em !important; }
        .fc .fc-button { text-transform: capitalize; box-shadow: none !important; }
        .fc-daygrid-day.fc-day-today { background-color: rgba(24, 119, 242, 0.1); }
        .fc-event-main-custom {
            padding: 2px;
            line-height: 1.3;
            font-size: 0.85em; 
            overflow: hidden; 
        }
        .fc-event-main-custom div {
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        .modal-body .list-unstyled li { padding: 2px 0; }


        /* Admin View Specific Styles */
        .admin-controls .form-group label { font-weight: 500; margin-bottom: .3rem; }
        .admin-controls .form-control, .admin-controls .custom-select { font-size: 0.9rem; border-radius: 6px; }
        .list-group-item { border-radius: 0; font-size: 0.9rem; cursor: pointer;}
        .list-group-item.active { background-color: #1877f2; border-color: #1877f2; color: white; }
        .user-list-container { max-height: 150px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; }
        .tags-container .badge { font-size: 0.85em; padding: 0.4em 0.6em; }
        .tags-container .close { font-size: 1.2em; opacity: 0.7; line-height: 1; vertical-align: middle; }
        #shift_buttons .btn { margin-right: 5px; margin-bottom: 5px; }
        
        .page-title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        /* Staff View Specific Styles (also for Admin's Personal Planning View) */
        .staff-view-container, .admin-personal-planning-view { margin-top: 0; }
        
        .staff-bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background-color: #fff; border-top: 1px solid #dddfe2;
            display: flex; justify-content: space-around;
            padding: 5px 0; z-index: 1020;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }
        .staff-bottom-nav .nav-item {
            flex-grow: 1; text-align: center; padding: 8px 0;
            color: #606770; text-decoration: none; border-radius: 8px; margin: 0 5px;
        }
        .staff-bottom-nav .nav-item.active { color: #1877f2; background-color: rgba(24, 119, 242, 0.1); }
        .staff-bottom-nav .nav-item i { font-size: 1.3em; margin-bottom: 3px; display: block; }
        .staff-bottom-nav .nav-item span { font-size: 0.75em; }
        .staff-content-tab { display: none; }
        .staff-content-tab.active { display: block; }
        
        .assignment-card-staff {
            background-color: #fff; border-radius: 8px; padding: 15px; margin-bottom: 15px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); border-left: 5px solid #1877f2;
        }
        .assignment-card-staff.shift-matin { border-left-color: #34c759; }
        .assignment-card-staff.shift-apres-midi { border-left-color: #ff9500; }
        .assignment-card-staff.shift-nuit { border-left-color: #5856d6; }
        .assignment-card-staff.shift-repos { border-left-color: #8e8e93; }
        .assignment-card-staff h5 { font-size: 1.1rem; font-weight: 600; margin-bottom: 5px; }
        .assignment-card-staff .meta-info { font-size: 0.9rem; color: #606770; margin-bottom: 10px; }
        .assignment-card-staff .mission-text-staff { font-size: 0.95rem; margin-bottom: 0; white-space: pre-wrap; }
        
        #staffListViewAssignments .list-group-item {
            border-left: 5px solid #1877f2;
            margin-bottom: 10px;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        #staffListViewAssignments .list-group-item .text-muted.d-block {
             white-space: normal; 
            overflow: visible;
            text-overflow: clip;
            line-height: 1.4;
        }
        #staffListViewAssignments .list-group-item.shift-matin { border-left-color: #34c759; }
        #staffListViewAssignments .list-group-item.shift-apres-midi { border-left-color: #ff9500; }
        #staffListViewAssignments .list-group-item.shift-nuit { border-left-color: #5856d6; }
        #staffListViewAssignments .list-group-item.shift-repos { border-left-color: #8e8e93; }


        .badge-shift { font-size: 0.8em; padding: 0.3em 0.6em; }

        .modal { z-index: 1050; }
        .modal-header { background-color: #1877f2; color: white; }
        .modal-header .close { color: white; opacity: 0.9; text-shadow: none; }
        .modal-body p strong { color: #333; }
        #detail_mission_text { background-color: #f0f2f5; padding: 10px; border-radius: 5px; font-size: 0.95rem; word-break: break-word; }

        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.8); z-index: 1060;
            display: flex; justify-content: center; align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }

        .navbar.sticky-top { z-index: 1030; }
        .admin-controls.sticky-top { z-index: 1025; top: 70px; }
        .admin-controls .card-body { max-height: calc(100vh - 140px); overflow-y: auto; }

        /* Styles for predefined color swatches */
        .color-swatches-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px; /* Spacing between swatches */
            margin-top: 5px;
        }
        .color-swatch {
            width: 28px;
            height: 28px;
            border-radius: 50%; /* Circular swatches */
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s ease-in-out, transform 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .color-swatch:hover {
            transform: scale(1.1);
        }
        .color-swatch.selected {
            border-color: #333; /* Darker border for selected swatch */
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #333; /* Inner and outer ring for selected */
        }


        @media (max-width: 991px) {
            .admin-main-layout > .col-lg-4 { margin-bottom: 20px; }
            .admin-controls.sticky-top { position: static; top: auto; max-height: none; }
            .admin-controls .card-body { max-height: none; }
        }
        @media (max-width: 767px) { /* Mobile breakpoint */
            #calendar { min-height: 450px; }
            /* FullCalendar toolbar adjustments for mobile are handled by JS for dynamic updates */
            .main-container { padding-top: 5px; }
            h1, h2, h3 { font-size: 1.5rem; }
             .page-title-container { flex-direction: column; align-items: flex-start;}
             .page-title-container h1 { margin-bottom: 0.5rem;}
             .color-swatch { width: 24px; height: 24px; } /* Slightly smaller on mobile */
        }
        @media (max-width: 575px) {
            #staffListTab .btn-group.w-100 .btn {
                font-size: 0.8rem;
                padding: .25rem .4rem;
            }
             .assignment-card-staff .mission-text-staff, 
            #staffListViewAssignments .list-group-item .text-muted.d-block {
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
        }
    </style>
</head>
<body class="<?php echo ($user_role !== 'admin') ? 'body-with-staff-nav' : ''; ?>"> <?php include 'navbar.php'; ?>

    <div class="container-fluid main-container <?php echo ($user_role !== 'admin') ? 'main-container-with-staff-nav' : ''; ?>">
        <?php if ($user_role == 'admin'): ?>
            <div id="adminManagementView">
                <div class="page-title-container">
                    <h1><i class="fas fa-user-shield mr-2"></i>Espace Admin - Gestion Planning</h1>
                    <button class="btn btn-outline-info btn-sm" id="toggleAdminViewBtn">
                        <i class="fas fa-user-clock mr-1"></i>Voir Mon Planning Personnel
                    </button>
                </div>
                <div class="row admin-main-layout">
                    <div class="col-lg-4">
                        <div class="card admin-controls sticky-top">
                            <div class="card-header"><i class="fas fa-plus-circle mr-2"></i>Créer / Modifier Affectation</div>
                            <div class="card-body">
                                <form id="assignmentForm">
                                    <input type="hidden" id="assignment_id" name="assignment_id">
                                    <input type="hidden" id="is_group_event_form" name="is_group_event_form" value="0">
                                    <div class="form-group">
                                        <label for="assignment_dates_input"><i class="fas fa-calendar-alt mr-1"></i>Date(s) (YYYY-MM-DD, séparées par virgule ou sélection)</label>
                                        <input type="text" class="form-control form-control-sm" id="assignment_dates_input" placeholder="Ex: 2024-12-25, 2024-12-26">
                                        <input type="hidden" id="assignment_dates_hidden" name="assignment_dates_hidden_input">
                                        <div id="selected_dates_tags" class="mt-2 tags-container"></div>
                                        <small class="form-text text-muted">Cliquez sur le calendrier pour sélectionner ou entrez les dates manuellement.</small>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-users mr-1"></i>Assigner à</label>
                                        <ul class="nav nav-pills nav-fill nav-sm mb-2" id="assigneeTypeTab" role="tablist">
                                            <li class="nav-item"><a class="nav-link active" id="individual-tab" data-toggle="tab" href="#individualPane" role="tab" aria-controls="individualPane" aria-selected="true">Individuel</a></li>
                                            <li class="nav-item"><a class="nav-link" id="team-tab-nav" data-toggle="tab" href="#teamPane" role="tab" aria-controls="teamPane" aria-selected="false">Équipe</a></li>
                                        </ul>
                                        <div class="tab-content" id="assigneeTypeTabContent">
                                            <div class="tab-pane fade show active" id="individualPane" role="tabpanel" aria-labelledby="individual-tab">
                                                <input type="text" id="workerSearch" class="form-control form-control-sm mb-2" placeholder="Rechercher employé/admin...">
                                                <div id="workerList" class="list-group list-group-flush user-list-container"></div>
                                                <div id="selected_workers_tags" class="mt-2 tags-container"></div>
                                            </div>
                                            <div class="tab-pane fade" id="teamPane" role="tabpanel" aria-labelledby="team-tab-nav">
                                                <input type="text" id="teamSearch" class="form-control form-control-sm mb-2" placeholder="Rechercher équipe...">
                                                <div id="teamList" class="list-group list-group-flush user-list-container"></div>
                                                <div id="selected_team_tag" class="mt-2 tags-container"></div>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-block" id="manageTeamsBtn" data-toggle="modal" data-target="#teamManagementModal"><i class="fas fa-users-cog mr-1"></i>Gérer les équipes</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-clock mr-1"></i>Type de service</label>
                                        <div id="shift_buttons" class="mb-2 d-flex flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-success shift-btn" data-shift="matin" data-start="07:00" data-end="15:00">Matin</button>
                                            <button type="button" class="btn btn-sm btn-outline-info shift-btn" data-shift="apres-midi" data-start="15:00" data-end="23:00">Après-midi</button>
                                            <button type="button" class="btn btn-sm btn-outline-purple shift-btn" data-shift="nuit" data-start="23:00" data-end="07:00">Nuit</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary shift-btn" data-shift="repos">Repos</button>
                                            <button type="button" class="btn btn-sm btn-outline-dark shift-btn" data-shift="custom">Personnalisé</button>
                                        </div>
                                        <input type="hidden" id="shift_type" name="shift_type">
                                    </div>
                                    <div class="row">
                                        <div class="col-6 form-group">
                                            <label for="start_time">Début</label>
                                            <input type="time" class="form-control form-control-sm" id="start_time" name="start_time" disabled>
                                        </div>
                                        <div class="col-6 form-group">
                                            <label for="end_time">Fin</label>
                                            <input type="time" class="form-control form-control-sm" id="end_time" name="end_time" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="mission_text"><i class="fas fa-tasks mr-1"></i>Mission</label>
                                        <textarea class="form-control form-control-sm" id="mission_text" name="mission_text" rows="2"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="location"><i class="fas fa-map-marker-alt mr-1"></i>Lieu</label>
                                        <input type="text" class="form-control form-control-sm" id="location" name="location" placeholder="Entrez le lieu de l'événement">
                                    </div>
                                    <div class="form-group">
                                        <label for="event_color_swatches"><i class="fas fa-palette mr-1"></i>Couleur</label>
                                        <div id="event_color_swatches" class="color-swatches-container">
                                            <?php foreach ($predefined_colors as $color_hex): ?>
                                                <div class="color-swatch" style="background-color: <?php echo $color_hex; ?>;" data-color="<?php echo $color_hex; ?>" title="<?php echo $color_hex; ?>"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" id="event_color" name="color" value="<?php echo $default_color; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block mt-3"><i class="fas fa-save mr-1"></i>Enregistrer</button>
                                    <button type="button" class="btn btn-light btn-block mt-2" id="clearAssignmentForm"><i class="fas fa-eraser mr-1"></i>Effacer</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header"><i class="fas fa-calendar-check mr-2"></i>Calendrier Général des Affectations</div>
                            <div class="card-body">
                                <div id='calendar'></div> </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="adminPersonalPlanningView" style="display:none;"> <div class="page-title-container">
                     <h1><i class="fas fa-user-clock mr-2"></i>Mon Planning Personnel</h1>
                     <button class="btn btn-outline-secondary btn-sm" id="toggleAdminViewBtnBack">
                        <i class="fas fa-cog mr-1"></i>Retour à la Gestion Planning
                    </button>
                </div>
                <div class="staff-view-content"> 
                    <div id="staffTodayTab" class="staff-content-tab"> <h2 class="mb-3"><i class="fas fa-bullhorn mr-2"></i>Mon Planning du Jour</h2>
                        <div id="todayAssignmentCard">
                             <div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>
                        </div>
                        <h3 class="mt-4 mb-3"><i class="fas fa-fast-forward mr-2"></i>Mes Prochains Jours</h3>
                        <div id="upcomingAssignmentsList" class="list-group">
                            <div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>
                        </div>
                    </div>
                    <div id="staffListTab" class="staff-content-tab">
                        <h2 class="mb-3"><i class="fas fa-list-ul mr-2"></i>Mon Planning (Liste)</h2>
                        <div class="btn-group mb-3 w-100" role="group" aria-label="Planning Period">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-period="current">Actuel/Prochain</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="past">Passé</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="future">Futur</button>
                        </div>
                        <div id="staffListViewAssignments" class="list-group">
                            <div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>
                        </div>
                    </div>
                </div>
                <nav class="staff-bottom-nav">
                    <a href="#" class="nav-item active" data-tab="staffTodayTab"><i class="fas fa-bullhorn"></i><span>Aujourd'hui</span></a>
                    <a href="#" class="nav-item" data-tab="staffListTab"><i class="fas fa-list-ul"></i><span>Liste</span></a>
                </nav>
            </div>

        <?php else: // Standard Staff View (for non-admins) ?>
            <div class="staff-view-container">
                 <div class="page-title-container">
                    <h1><i class="fas fa-user-clock mr-2"></i>Mon Planning</h1> </div>
                <div id="staffTodayTab" class="staff-content-tab active">
                    <h2 class="mb-3"><i class="fas fa-bullhorn mr-2"></i>Mon Planning du Jour</h2>
                    <div id="todayAssignmentCard">
                        <div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>
                    </div>
                    <h3 class="mt-4 mb-3"><i class="fas fa-fast-forward mr-2"></i>Mes Prochains Jours</h3>
                    <div id="upcomingAssignmentsList" class="list-group">
                        <div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>
                    </div>
                </div>
                <div id="staffListTab" class="staff-content-tab">
                    <h2 class="mb-3"><i class="fas fa-list-ul mr-2"></i>Mon Planning (Liste)</h2>
                    <div class="btn-group mb-3 w-100" role="group" aria-label="Planning Period">
                        <button type="button" class="btn btn-sm btn-outline-primary active" data-period="current">Actuel/Prochain</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-period="past">Passé</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-period="future">Futur</button>
                    </div>
                    <div id="staffListViewAssignments" class="list-group">
                         <div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>
                    </div>
                </div>
                <nav class="staff-bottom-nav">
                    <a href="#" class="nav-item active" data-tab="staffTodayTab"><i class="fas fa-bullhorn"></i><span>Aujourd'hui</span></a>
                    <a href="#" class="nav-item" data-tab="staffListTab"><i class="fas fa-list-ul"></i><span>Liste</span></a>  </nav>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="assignmentDetailModal" tabindex="-1" aria-labelledby="assignmentDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignmentDetailModalLabel">Détails de l'affectation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p><strong><i class="fas fa-users mr-2"></i>Employé(s):</strong> <span id="detail_user_name"></span></p>
                    <p><strong><i class="fas fa-calendar-day mr-2"></i>Date:</strong> <span id="detail_assignment_date"></span></p>
                    <p><strong><i class="fas fa-map-marker-alt mr-2"></i>Lieu:</strong> <span id="detail_location"></span></p>
                    <p><strong><i class="fas fa-tags mr-2"></i>Service:</strong> <span id="detail_shift_type_badge"></span></p>
                    <p><strong><i class="fas fa-clock mr-2"></i>Horaires:</strong> <span id="detail_times"></span></p>
                    <p class="mt-3"><strong><i class="fas fa-clipboard-list mr-2"></i>Mission:</strong></p>
                    <div id="detail_mission_text" style="white-space: pre-wrap;"></div>
                </div>
                <div class="modal-footer">
                    <?php if ($user_role == 'admin'): ?>
                    <button type="button" class="btn btn-warning btn-sm" id="editAssignmentBtn"><i class="fas fa-edit mr-1"></i>Modifier</button>
                    <button type="button" class="btn btn-danger btn-sm" id="deleteAssignmentBtn"><i class="fas fa-trash-alt mr-1"></i>Supprimer</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user_role == 'admin'): ?>
    <div class="modal fade" id="teamManagementModal" tabindex="-1" aria-labelledby="teamManagementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered"> <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="teamManagementModalLabel"><i class="fas fa-users-cog mr-2"></i>Gestion des Équipes</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <h6><i class="fas fa-list-ul mr-1"></i>Équipes Existantes</h6>
                            <div id="existingTeamsList" class="list-group user-list-container" style="max-height: 300px;"></div>
                            <button type="button" class="btn btn-success btn-sm btn-block mt-3" id="addNewTeamBtnModal"><i class="fas fa-plus mr-1"></i>Nouvelle Équipe</button>
                        </div>
                        <div class="col-md-8">
                            <h6 id="teamFormTitle">Créer Équipe</h6>
                            <form id="teamForm">
                                <input type="hidden" id="team_id_modal" name="team_id">
                                <div class="form-group">
                                    <label for="team_name_modal">Nom de l'équipe</label>
                                    <input type="text" class="form-control form-control-sm" id="team_name_modal" name="team_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Membres</label>
                                    <input type="text" id="teamMemberSearchModal" class="form-control form-control-sm mb-2" placeholder="Rechercher employé/admin à ajouter...">
                                    <div id="teamMemberListModal" class="list-group list-group-flush user-list-container" style="max-height: 180px;"></div>
                                    <div id="selected_team_members_tags_modal" class="mt-2 tags-container"></div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Enregistrer Équipe</button>
                                <button type="button" class="btn btn-danger btn-sm" id="deleteTeamBtnModal" style="display:none;"><i class="fas fa-trash mr-1"></i>Supprimer Équipe</button>
                                <button type="button" class="btn btn-light btn-sm" id="clearTeamFormModal"><i class="fas fa-eraser mr-1"></i>Effacer Formulaire</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="loadingOverlay" style="display: none;">
        <div class="spinner-border text-primary" role="status"><span class="sr-only">Chargement...</span></div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>

    <script>
        let calendarAdmin;
        let staffUsers = [];
        let teamsData = [];
        let selectedWorkerIds = [];
        let selectedTeamId = null;
        let selectedDatesFC = [];
        let currentListPeriod = 'current';

        const loggedInUserRole = '<?php echo $user_role; ?>';
        const loggedInUserId = <?php echo $user_id_logged_in; ?>;
        let adminViewingPersonal = false;
        const defaultEventColor = '<?php echo $default_color; ?>';
        const predefinedColors = <?php echo json_encode($predefined_colors); ?>;

        // Define toolbar configurations for different screen sizes
        const desktopAdminToolbar = { 
            left: 'prev,next today', 
            center: 'title', 
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' 
        };
        const mobileAdminToolbar = { 
            left: 'prev,next today', 
            center: 'title', 
            right: 'listWeek'  // Only list view for mobile
        };


        // --- Utility Functions ---
        function showLoading(show) { $('#loadingOverlay').toggle(show); }
        function showToast(message, type = 'info') { console.log(`Toast (${type}): ${message}`); alert(`${type.toUpperCase()}: ${message}`); }
        function formatDateToYMD(dateInput) {
            if (!dateInput) return '';
            const d = new Date(dateInput);
            if (isNaN(d.getTime())) { console.warn("Invalid date to formatDateToYMD:", dateInput); return '';}
            const year = d.getFullYear();
            const month = ('0' + (d.getMonth() + 1)).slice(-2);
            const day = ('0' + d.getDate()).slice(-2);
            return `${year}-${month}-${day}`;
        }
        function formatTimeHM(timeStr) { return timeStr ? String(timeStr).substring(0, 5) : ''; }
        function getShiftBadge(shiftType) {
            let badgeClass = 'badge-secondary';
            let shiftText = shiftType ? String(shiftType).charAt(0).toUpperCase() + String(shiftType).slice(1) : 'N/A';
            if (shiftType === 'matin') { badgeClass = 'badge-success'; }
            else if (shiftType === 'apres-midi') { badgeClass = 'badge-info'; }
            else if (shiftType === 'nuit') { badgeClass = 'badge-purple-soft'; }
            else if (shiftType === 'repos') { badgeClass = 'badge-secondary'; }
            else if (shiftType === 'custom') { badgeClass = 'badge-dark'; }
            return `<span class="badge badge-shift ${badgeClass}">${shiftText}</span>`;
        }
        function isValidDateString(dateString) { return /^\d{4}-\d{2}-\d{2}$/.test(dateString) && !isNaN(new Date(dateString)); }
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return '';
            return unsafe
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }


        document.addEventListener('DOMContentLoaded', function() {
            if (!$('style:contains(".badge-purple-soft")').length) {
                $('<style>.badge-purple-soft { color: #fff; background-color: #6f42c1; } .btn-outline-purple { color: #6f42c1; border-color: #6f42c1; } .btn-outline-purple:hover { color: #fff; background-color: #6f42c1; border-color: #6f42c1; }</style>').appendTo('head');
            }

            if (loggedInUserRole === 'admin') {
                $('#adminManagementView').show();
                $('#adminPersonalPlanningView').hide();
                $('body').removeClass('body-with-staff-nav');
                $('.main-container').removeClass('main-container-with-staff-nav');

                const calendarElAdmin = document.getElementById('calendar');
                if (calendarElAdmin) {
                    calendarAdmin = new FullCalendar.Calendar(calendarElAdmin, {
                        locale: 'fr', 
                        initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth', // Set initial view based on screen size
                        headerToolbar: window.innerWidth < 768 ? mobileAdminToolbar : desktopAdminToolbar, // Set initial toolbar
                        buttonText: { today: "Auj.", month: "Mois", week: "Semaine", day: "Jour", list: "Liste" },
                        editable: true, selectable: true, selectMirror: true,
                        events: { url: 'planning-handler.php?action=get_assignments&view=admin_main', failure: () => showToast('Erreur chargement calendrier admin.', 'error') },
                        eventClick: handleEventClick, 
                        select: handleDateSelectForAdminForm,
                        eventDrop: function(info) { 
                            if (info.event.extendedProps.is_group) {
                                showToast('Glisser-déposer non supporté pour les groupes. Modifiez via le formulaire si besoin.', 'warning');
                                info.revert(); 
                                return;
                            }
                            let assignmentIdToUpdate = info.event.extendedProps.representative_assignment_id || info.event.id;
                            if (typeof assignmentIdToUpdate === 'string' && assignmentIdToUpdate.startsWith('group_')) {
                                assignmentIdToUpdate = info.event.extendedProps.representative_assignment_id;
                            }
                            updateAssignmentDateTime(info.event, 'admin', assignmentIdToUpdate);
                        },
                        eventResize: function(info) { 
                             if (info.event.extendedProps.is_group) {
                                showToast('Redimensionnement non supporté pour les groupes. Modifiez via le formulaire si besoin.', 'warning');
                                info.revert();
                                return;
                            }
                            let assignmentIdToUpdate = info.event.extendedProps.representative_assignment_id || info.event.id;
                             if (typeof assignmentIdToUpdate === 'string' && assignmentIdToUpdate.startsWith('group_')) {
                                assignmentIdToUpdate = info.event.extendedProps.representative_assignment_id;
                            }
                            updateAssignmentDateTime(info.event, 'admin', assignmentIdToUpdate);
                        },
                        loading: isLoading => showLoading(isLoading),
                        eventContent: function(arg) { 
                            let eventHtml = '';
                            let props = arg.event.extendedProps;
                            let missionText = props.mission_text || '';
                            let shiftType = props.shift_type || '';
                            let displayTitle = missionText ? missionText : (shiftType ? ucfirst(shiftType) : 'Affectation');
                            let userCount = props.user_count || 0;

                            if (userCount > 1) {
                                displayTitle += ` (${userCount} pers.)`;
                            }

                            if (arg.view.type === 'dayGridMonth' || arg.view.type === 'timeGridWeek' || arg.view.type === 'timeGridDay') {
                                eventHtml = `
                                    <div class="fc-event-main-custom">
                                        <div>${escapeHtml(displayTitle.substring(0,30))}${displayTitle.length > 30 ? '...' : ''}</div>
                                    </div>`;
                            } else { // For listWeek and other views
                                 eventHtml = `
                                    <div class="fc-event-main-custom">
                                        ${escapeHtml(displayTitle)}
                                    </div>`;
                            }
                            return { html: eventHtml };
                        },
                        windowResize: function(arg) {
                            const currentToolbarRight = calendarAdmin.getOption('headerToolbar').right;
                            const isMobile = window.innerWidth < 768;

                            if (isMobile) {
                                if (currentToolbarRight !== mobileAdminToolbar.right) {
                                    calendarAdmin.setOption('headerToolbar', mobileAdminToolbar);
                                }
                                if (calendarAdmin.view.type !== 'listWeek') { // Always ensure listWeek on mobile resize
                                    calendarAdmin.changeView('listWeek');
                                }
                            } else { // Desktop
                                if (currentToolbarRight !== desktopAdminToolbar.right) {
                                    calendarAdmin.setOption('headerToolbar', desktopAdminToolbar);
                                    // Optional: if coming from mobile list view, maybe switch to month view
                                    // if (arg.oldView && arg.oldView.type === 'listWeek' && calendarAdmin.view.type === 'listWeek') {
                                    //    calendarAdmin.changeView('dayGridMonth');
                                    // }
                                }
                            }
                        }
                    });
                    calendarAdmin.render();
                }
                loadAdminData();
                initializeAdminForm();
                initializeTeamManagementModal();
                
                $('#toggleAdminViewBtn, #toggleAdminViewBtnBack').on('click', toggleAdminPersonalView);
                
            } else { // Standard Staff User
                $('#adminManagementView').hide();
                $('#adminPersonalPlanningView').hide();
                $('.staff-view-container').show();
                $('body').addClass('body-with-staff-nav');
                $('.main-container').addClass('main-container-with-staff-nav');
                
                initializeStaffView();
                
                $('.staff-view-container #staffTodayTab').addClass('active');
                $('.staff-view-container .staff-bottom-nav .nav-item[data-tab="staffTodayTab"]').first().addClass('active');
                
                 if ($('.staff-view-container .staff-bottom-nav .nav-item.active').data('tab') === 'staffListTab') {
                    loadStaffListAssignments();
                } else {
                    loadStaffDashboardData();
                }
            }
        });
        
        function ucfirst(str) {
            if (typeof str !== 'string' || str.length === 0) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function toggleAdminPersonalView() {
            adminViewingPersonal = !adminViewingPersonal;
            if (adminViewingPersonal) {
                $('#adminManagementView').hide();
                $('#adminPersonalPlanningView').show();
                $('body').addClass('body-with-staff-nav');
                $('.main-container').addClass('main-container-with-staff-nav');

                initializeStaffView(); 

                 if (!$('#adminPersonalPlanningView .staff-bottom-nav .nav-item.active').length) {
                    $('#adminPersonalPlanningView .staff-bottom-nav .nav-item[data-tab="staffTodayTab"]').addClass('active');
                    $('#adminPersonalPlanningView #staffTodayTab').addClass('active');
                 }
                
                const activeAdminPersonalTab = $('#adminPersonalPlanningView .staff-bottom-nav .nav-item.active').data('tab');
                if (activeAdminPersonalTab === 'staffListTab') {
                    const listContainer = $('#adminPersonalPlanningView #staffListViewAssignments');
                    if (!listContainer.children().not('.text-muted').length) { 
                       loadStaffListAssignments();
                    }
                } else if (activeAdminPersonalTab === 'staffTodayTab') {
                    loadStaffDashboardData();
                }

            } else { // Switching back to Admin Management View
                $('#adminManagementView').show();
                $('#adminPersonalPlanningView').hide();
                $('body').removeClass('body-with-staff-nav');
                $('.main-container').removeClass('main-container-with-staff-nav');
                if (calendarAdmin) {
                    calendarAdmin.updateSize();
                    // Ensure correct toolbar and view on returning to admin management view
                    const isMobile = window.innerWidth < 768;
                    const targetToolbar = isMobile ? mobileAdminToolbar : desktopAdminToolbar;
                    const targetView = isMobile ? 'listWeek' : (calendarAdmin.getOption('initialView') || 'dayGridMonth');

                    if (JSON.stringify(calendarAdmin.getOption('headerToolbar')) !== JSON.stringify(targetToolbar)) {
                        calendarAdmin.setOption('headerToolbar', targetToolbar);
                    }
                    if (calendarAdmin.view.type !== targetView) {
                         calendarAdmin.changeView(targetView);
                    }
                }
            }
        }

        function handleEventClick(info) { 
            const props = info.event.extendedProps;
            $('#is_group_event_form').val(props.is_group ? '1' : '0'); 

            $('#detail_assignment_date').text(props.raw_assignment_date ? formatDateToYMD(props.raw_assignment_date) : formatDateToYMD(info.event.start));
            $('#detail_location').text(props.location || 'N/A');
            $('#detail_shift_type_badge').html(getShiftBadge(props.shift_type));
            $('#detail_mission_text').html(props.mission_text ? String(props.mission_text).replace(/\n/g, '<br>') : '<i>Aucune description.</i>');

            let times = 'N/A';
            if (props.raw_start_time) {
                times = formatTimeHM(props.raw_start_time) + (props.raw_end_time ? ' - ' + formatTimeHM(props.raw_end_time) : '');
            } else if (info.event.allDay && props.shift_type !== 'repos') { 
                times = 'Toute la journée';
            } else if (!props.raw_start_time && props.shift_type !== 'repos' && (info.event.allDay === false || info.event.allDay === undefined)) {
                 times = 'Horaires non définis';
            } else if (props.shift_type === 'repos') {
                times = 'Jour de repos';
            }
             $('#detail_times').text(times);

            let assignmentIdForButtons = props.is_group ? props.representative_assignment_id : info.event.id;
            
            if (props.is_group && Array.isArray(props.user_names_list) && props.user_names_list.length > 0) {
                let userListHtml = '<ul class="list-unstyled mb-0">';
                props.user_names_list.forEach(name => {
                    userListHtml += `<li>${escapeHtml(name)}</li>`;
                });
                userListHtml += '</ul>';
                $('#detail_user_name').html(userListHtml);
                $('#editAssignmentBtn').data('assignment_id', assignmentIdForButtons).data('is_group', true).show();
                $('#deleteAssignmentBtn').data('assignment_id', assignmentIdForButtons).data('is_group', true).show();
            } else if (props.user_name) { 
                 $('#detail_user_name').text(props.user_name);
                 $('#editAssignmentBtn').data('assignment_id', assignmentIdForButtons).data('is_group', false).show();
                 $('#deleteAssignmentBtn').data('assignment_id', assignmentIdForButtons).data('is_group', false).show();
            } else {
                 const singleAssignDetailId = info.event.id.startsWith('group_') ? props.representative_assignment_id : info.event.id;
                 $.getJSON('planning-handler.php?action=get_assignment_details&assignment_id=' + singleAssignDetailId, function(response) {
                    if (response.status === 'success' && response.data.assignment) {
                        $('#detail_user_name').text(response.data.assignment.user_name_display || 'N/A');
                    } else {
                        $('#detail_user_name').text('Utilisateur non trouvé.');
                    }
                 });
                 $('#editAssignmentBtn').data('assignment_id', singleAssignDetailId).data('is_group', false).show();
                 $('#deleteAssignmentBtn').data('assignment_id', singleAssignDetailId).data('is_group', false).show();
            }

            if (loggedInUserRole !== 'admin') { 
                $('#editAssignmentBtn, #deleteAssignmentBtn').hide();
            }
            $('#assignmentDetailModal').modal('show');
        }


        function handleDateSelectForAdminForm(info) {
            selectedDatesFC = [];
            let currentDate = new Date(info.start);
            let endDate = new Date(info.end);
            while (currentDate < endDate) {
                selectedDatesFC.push(formatDateToYMD(currentDate));
                currentDate.setDate(currentDate.getDate() + 1);
            }
            if (selectedDatesFC.length === 0 && info.startStr === info.endStr.substring(0,10) ) {
                 selectedDatesFC.push(formatDateToYMD(info.start));
            }
            selectedDatesFC = [...new Set(selectedDatesFC)].sort();
            updateDateInputAndTagsFromSelectedDatesFC();
        }
        function updateDateInputAndTagsFromSelectedDatesFC() {
             $('#assignment_dates_input').val(selectedDatesFC.join(', '));
            renderSelectedDatesTags(selectedDatesFC);
            $('#assignment_dates_hidden').val(selectedDatesFC.join(','));
        }
        function parseDatesFromTypedInput() {
            const inputVal = $('#assignment_dates_input').val();
            const potentialDates = inputVal.split(',').map(d => d.trim()).filter(d => d);
            
            selectedDatesFC = [];
            potentialDates.forEach(dateStr => {
                if (isValidDateString(dateStr)) {
                    selectedDatesFC.push(dateStr);
                } else if (dateStr) {
                    showToast(`Format de date invalide: ${dateStr}. Utilisez YYYY-MM-DD.`, 'warning');
                }
            });
            selectedDatesFC = [...new Set(selectedDatesFC)].sort();
            updateDateInputAndTagsFromSelectedDatesFC();
            if (calendarAdmin) calendarAdmin.unselect();
        }

        function updateAssignmentDateTime(event, role, eventIdToUpdate) {
            if (role !== 'admin') { event.revert(); return; }
            
            let idForRequest = eventIdToUpdate || event.id; 
            if (typeof idForRequest === 'string' && idForRequest.startsWith('group_')) {
                console.warn("Attempting to update a group via drag/drop, using representative ID:", event.extendedProps.representative_assignment_id);
                idForRequest = event.extendedProps.representative_assignment_id;
                if (!idForRequest) {
                    showToast('ID de groupe invalide pour la mise à jour.', 'error');
                    event.revert();
                    return;
                }
            }

            showLoading(true);
            $.ajax({
                url: 'planning-handler.php', type: 'POST',
                data: {
                    action: 'update_assignment_date_time', 
                    id: idForRequest, 
                    start: event.start.toISOString(),
                    end: event.end ? event.end.toISOString() : null,
                },
                dataType: 'json',
                success: response => {
                    if (response.status === 'success') {
                        showToast(response.message, 'success');
                        calendarAdmin.refetchEvents(); 
                    } else {
                        showToast(response.message || 'Erreur mise à jour.', 'error'); event.revert();
                    }
                },
                error: (xhr) => {
                    showToast('Erreur communication: ' + xhr.statusText, 'error'); event.revert();
                    console.error("AJAX Error:", xhr.responseText);
                },
                complete: () => showLoading(false)
            });
        }

        <?php if ($user_role == 'admin'): ?>
        // --- Admin Specific Functions (for assignment form, etc.) ---
        function loadAdminData() { loadStaffUsers(); loadTeams(); }
        function initializeAdminForm() {
            $('#assignment_dates_input').on('blur', parseDatesFromTypedInput);
            flatpickr("#assignment_dates_input", {
                mode: "multiple", dateFormat: "Y-m-d", locale: "fr", conjunction: ", ",
                onChange: function(selectedDates, dateStr, instance) {
                    selectedDatesFC = selectedDates.map(date => formatDateToYMD(date));
                    selectedDatesFC = [...new Set(selectedDatesFC)].sort();
                    updateDateInputAndTagsFromSelectedDatesFC();
                    if (calendarAdmin) calendarAdmin.unselect();
                }
            });

            // Initialize color swatches
            $('#event_color_swatches .color-swatch').on('click', function() {
                $('#event_color_swatches .color-swatch').removeClass('selected');
                $(this).addClass('selected');
                $('#event_color').val($(this).data('color'));
            });
            // Set default selected color swatch
            $(`#event_color_swatches .color-swatch[data-color="${defaultEventColor}"]`).addClass('selected');


            $('.shift-btn').on('click', function() {
                $('.shift-btn').removeClass('active btn-success btn-info btn-purple-soft btn-secondary btn-dark').addClass('btn-outline-secondary');
                $(this).removeClass('btn-outline-secondary');
                const shift = $(this).data('shift');
                $('#shift_type').val(shift);

                if (shift === 'matin') $(this).addClass('active btn-success');
                else if (shift === 'apres-midi') $(this).addClass('active btn-info');
                else if (shift === 'nuit') $(this).addClass('active btn-purple-soft');
                else if (shift === 'repos') $(this).addClass('active btn-secondary');
                else if (shift === 'custom') $(this).addClass('active btn-dark');

                if (shift === 'repos') {
                    $('#start_time, #end_time').val('').prop('disabled', true);
                    $('#mission_text').val('Repos').prop('readonly', true);
                    $('#location').val('').prop('readonly', true);
                } else if (shift === 'custom') {
                    $('#start_time').val('').prop('disabled', false);
                    $('#end_time').val('').prop('disabled', false);
                    if ($('#mission_text').val() === 'Repos') $('#mission_text').val('');
                    $('#mission_text').prop('readonly', false);
                    $('#location').prop('readonly', false);
                } else { 
                    $('#start_time').val($(this).data('start') || '').prop('disabled', false); 
                    $('#end_time').val($(this).data('end') || '').prop('disabled', false);   
                    if ($('#mission_text').val() === 'Repos') $('#mission_text').val('');
                    $('#mission_text').prop('readonly', false);
                    $('#location').prop('readonly', false);
                }
            });


            $('#assignmentForm').on('submit', function(e) {
                e.preventDefault();
                parseDatesFromTypedInput();
                showLoading(true);
                let assigned_ids_to_submit = [];
                let team_id_to_submit = null;
                const activeAssigneeTab = $('#assigneeTypeTab .nav-link.active').attr('href');

                if (activeAssigneeTab === '#individualPane') { assigned_ids_to_submit = [...selectedWorkerIds]; }
                else if (activeAssigneeTab === '#teamPane') { team_id_to_submit = selectedTeamId; }
                
                if (assigned_ids_to_submit.length === 0 && !team_id_to_submit) { showToast('Veuillez sélectionner employé/admin ou équipe.', 'warning'); showLoading(false); return; }
                if (selectedDatesFC.length === 0) { showToast('Veuillez sélectionner/entrer des dates.', 'warning'); showLoading(false); return; }
                
                const currentShiftType = $('#shift_type').val();
                if (!currentShiftType) { showToast('Veuillez sélectionner un type de service.', 'warning'); showLoading(false); return; }
                
                if (currentShiftType !== 'repos' && ($('#start_time').val() === '' || $('#end_time').val() === '')) {
                     showToast('Heures de début/fin requises pour ce type de service.', 'warning'); showLoading(false); return;
                }
                
                let assignmentIdToSubmit = $('#assignment_id').val();
                
                const formData = {
                    action: 'save_assignment', 
                    assignment_id: assignmentIdToSubmit, 
                    is_group_edit: $('#is_group_event_form').val() === '1' && assignmentIdToSubmit, 
                    assignment_dates: selectedDatesFC,
                    assigned_user_ids: assigned_ids_to_submit, 
                    assigned_team_id: team_id_to_submit,
                    shift_type: currentShiftType, 
                    start_time: $('#start_time').val(), 
                    end_time: $('#end_time').val(),
                    mission_text: $('#mission_text').val(), 
                    location: $('#location').val(), 
                    color: $('#event_color').val() // Get color from hidden input
                };

                $.ajax({
                    url: 'planning-handler.php', type: 'POST', data: formData, dataType: 'json',
                    success: response => {
                        if (response.status === 'success') {
                            showToast(response.message, 'success');
                            if(calendarAdmin) calendarAdmin.refetchEvents();
                            clearAssignmentForm();
                        } else showToast(response.message || 'Erreur enregistrement.', 'error');
                    },
                    error: (xhr) => { showToast('Erreur communication: ' + xhr.statusText, 'error'); console.error("AJAX Error:", xhr.responseText); },
                    complete: () => showLoading(false)
                });
            });
            $('#clearAssignmentForm').on('click', clearAssignmentForm);
            
            $('#editAssignmentBtn').on('click', function() {
                const assignmentId = $(this).data('assignment_id'); 
                const isGroup = $(this).data('is_group');
                $('#is_group_event_form').val(isGroup ? '1' : '0');
                $('#assignment_id').val(assignmentId);

                if (!assignmentId) return;
                showLoading(true);

                $.getJSON(`planning-handler.php?action=get_assignment_details&assignment_id=${assignmentId}`, response => {
                    if (response.status === 'success' && response.data.assignment) {
                        const a = response.data.assignment; 
                        
                        selectedDatesFC = [formatDateToYMD(a.assignment_date)];
                        updateDateInputAndTagsFromSelectedDatesFC();
                        
                        if (isGroup) {
                            selectedWorkerIds = [parseInt(a.assigned_user_id, 10)]; 
                            renderSelectedWorkersTags();
                            $('#assigneeTypeTab a[href="#individualPane"]').tab('show');
                             const worker = staffUsers.find(u => u.user_id === selectedWorkerIds[0]);
                            if(worker)$('#workerSearch').val(`${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : ''));
                            showToast('Modification des détails communs du groupe. Les utilisateurs assignés ne sont pas modifiables via ce formulaire pour un groupe.', 'info');
                        } else { 
                            selectedWorkerIds = a.assigned_user_id ? [parseInt(a.assigned_user_id, 10)] : [];
                            $('#assigneeTypeTab a[href="#individualPane"]').tab('show');
                            renderSelectedWorkersTags();
                            if(selectedWorkerIds.length > 0 && staffUsers.length > 0){
                                const worker = staffUsers.find(u => u.user_id === selectedWorkerIds[0]);
                                if(worker)$('#workerSearch').val(`${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : ''));
                            }
                        }
                        selectedTeamId = null; renderSelectedTeamTag(); $('#teamSearch').val('');
                        
                        const shiftButton = $(`.shift-btn[data-shift="${a.shift_type}"]`);
                        if(shiftButton.length) {
                            shiftButton.trigger('click'); 
                        } else { 
                            $('#shift_type').val(a.shift_type);
                            $('.shift-btn').removeClass('active btn-success btn-info btn-purple-soft btn-secondary btn-dark').addClass('btn-outline-secondary');
                        }
                        
                        $('#start_time').val(formatTimeHM(a.start_time));
                        $('#end_time').val(formatTimeHM(a.end_time));

                        if (a.shift_type !== 'repos') {
                             $('#start_time, #end_time').prop('disabled', false);
                        }

                        $('#mission_text').val(a.mission_text);
                        $('#location').val(a.location || '');
                        
                        // Set color from swatches
                        const eventColor = a.color || defaultEventColor;
                        $('#event_color').val(eventColor);
                        $('#event_color_swatches .color-swatch').removeClass('selected');
                        $(`#event_color_swatches .color-swatch[data-color="${eventColor}"]`).addClass('selected');
                        // If the color is not in predefined, select the default one
                        if (!$(`#event_color_swatches .color-swatch[data-color="${eventColor}"]`).length) {
                            $(`#event_color_swatches .color-swatch[data-color="${defaultEventColor}"]`).addClass('selected');
                             $('#event_color').val(defaultEventColor);
                        }


                        $('#assignmentDetailModal').modal('hide');
                        if(adminViewingPersonal) { toggleAdminPersonalView(); } // Switch back to admin view if needed
                        $('html, body').animate({ scrollTop: $("#assignmentForm").offset().top - 80 }, 500);
                    } else showToast(response.message || 'Détails non trouvés.', 'error');
                }).fail((xhr)=>{ showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error:", xhr.responseText);
                }).always(()=>showLoading(false));
            });

            $('#deleteAssignmentBtn').on('click', function() {
                const assignmentId = $(this).data('assignment_id'); 
                const isGroup = $(this).data('is_group');
                if (!assignmentId) return;

                let confirmMessage = 'Supprimer cette affectation ?';
                if (isGroup) {
                    confirmMessage = 'Supprimer cette mission pour tous les utilisateurs assignés (groupe) ? Cette action est irréversible pour le groupe.';
                }

                if (confirm(confirmMessage)) {
                    showLoading(true);
                    $.post('planning-handler.php', { 
                        action: 'delete_assignment', 
                        assignment_id: assignmentId,
                        is_group_delete: isGroup 
                    }, response => {
                        if (response.status === 'success') {
                            showToast(response.message, 'success');
                            if(calendarAdmin) calendarAdmin.refetchEvents();
                            $('#assignmentDetailModal').modal('hide');
                        } else showToast(response.message || 'Erreur suppression.', 'error');
                    }, 'json').fail((xhr)=>{showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error:", xhr.responseText);
                    }).always(()=>showLoading(false));
                }
            });
            $('#workerSearch').on('input', function() { renderWorkerList($(this).val()); });
            $('#teamSearch').on('input', function() { renderTeamList($(this).val()); });
            $('#workerList').on('click', '.list-group-item[data-worker-id]', function() { toggleWorkerSelection($(this).data('worker-id')); });
            $('#teamList').on('click', '.list-group-item[data-team-id]', function() { selectTeam($(this).data('team-id')); });
            $('#selected_dates_tags').on('click', '.remove-date-tag', function() {
                const dateToRemove = $(this).data('date');
                selectedDatesFC = selectedDatesFC.filter(d => d !== dateToRemove);
                updateDateInputAndTagsFromSelectedDatesFC();
                if (calendarAdmin) calendarAdmin.unselect();
            });
            $('#selected_workers_tags').on('click', '.remove-worker-tag', function() {
                const workerIdToRemove = parseInt($(this).data('id'), 10);
                const worker = staffUsers.find(u => u.user_id === workerIdToRemove);
                selectedWorkerIds = selectedWorkerIds.filter(id => id !== workerIdToRemove);
                renderSelectedWorkersTags();
                $(`#workerList .list-group-item[data-worker-id="${workerIdToRemove}"]`).removeClass('active');
                const displayName = worker ? `${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : '') : '';
                if (worker && $('#workerSearch').val() === displayName) {
                    $('#workerSearch').val(''); renderWorkerList('');
                }
            });
            $('#selected_team_tag').on('click', '#removeSelectedTeamTag', function() {
                 const team = teamsData.find(t => t.team_id === selectedTeamId);
                selectedTeamId = null; renderSelectedTeamTag();
                $('#teamList .list-group-item.active').removeClass('active');
                if (team && $('#teamSearch').val() === team.team_name) {
                     $('#teamSearch').val(''); renderTeamList('');
                }
            });
        }
        function renderSelectedDatesTags(datesArray) {
            const cont = $('#selected_dates_tags').empty();
            datesArray.forEach(dateStr => {
                if (!isValidDateString(dateStr)) return;
                const d = new Date(dateStr + 'T00:00:00'); // Ensure correct date parsing
                const fDate = d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
                cont.append(`<span class="badge badge-info mr-1 mb-1 p-2">${fDate} <button type="button" class="close ml-1 remove-date-tag" data-date="${dateStr}" aria-label="Remove">&times;</button></span>`);
            });
        }
        function loadStaffUsers() {
            showLoading(true);
            $.getJSON('planning-handler.php?action=get_staff_users')
                .done(response => {
                    if (response.status === 'success' && response.data && Array.isArray(response.data.users)) {
                        staffUsers = response.data.users.map(u => ({...u, user_id: parseInt(u.user_id, 10)}));
                        renderWorkerList();
                        if ($('#teamManagementModal').data('bs.modal') && $('#teamManagementModal').data('bs.modal')._isShown) {
                             renderTeamMemberListModal();
                        }
                    } else { showToast(response.message || 'Erreur chargement utilisateurs.', 'error'); staffUsers = []; }
                })
                .fail((xhr) => { showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error (get_staff_users):", xhr.responseText); staffUsers = []; })
                .always(() => {
                    showLoading(false); renderWorkerList($('#workerSearch').val());
                    if ($('#teamManagementModal').data('bs.modal') && $('#teamManagementModal').data('bs.modal')._isShown) {
                        renderTeamMemberListModal($('#teamMemberSearchModal').val());
                    }
                });
        }
        function renderWorkerList(searchTerm = '') {
            const listEl = $('#workerList').empty();
            if (!Array.isArray(staffUsers) || staffUsers.length === 0) { listEl.append('<p class="text-muted p-2 small">Aucun utilisateur.</p>'); return; }
            const lowerSearchTerm = searchTerm.toLowerCase();
            const filteredUsers = staffUsers.filter(u => {
                const dName = `${u.prenom} ${u.nom} ${u.role === 'admin' ? '(admin)' : ''}`.toLowerCase();
                return dName.includes(lowerSearchTerm);
            });
            if(filteredUsers.length === 0) { listEl.append(`<p class="text-muted p-2 small">${searchTerm ? 'Aucun utilisateur.' : 'Rechercher...'}</p>`);
            } else {
                filteredUsers.forEach(user => {
                    const displayName = `${user.prenom} ${user.nom}` + (user.role === 'admin' ? ' (Admin)' : '');
                    const item = $(`<a href="#" class="list-group-item list-group-item-action list-group-item-sm py-1 px-2" data-worker-id="${user.user_id}">${displayName}</a>`);
                    if (selectedWorkerIds.includes(user.user_id)) item.addClass('active');
                    listEl.append(item);
                });
            }
        }
        function toggleWorkerSelection(workerId) {
            const workerIdNum = parseInt(workerId, 10);
            const worker = staffUsers.find(u => u.user_id === workerIdNum);
            if (!worker) { console.error("Worker not found:", workerIdNum); return; }
            const index = selectedWorkerIds.indexOf(workerIdNum);
            const displayName = `${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : '');
            if (index > -1) {
                selectedWorkerIds.splice(index, 1);
                if ($('#workerSearch').val() === displayName) { $('#workerSearch').val(''); renderWorkerList('');}
            } else {
                selectedWorkerIds.push(workerIdNum); $('#workerSearch').val(displayName);
            }
            $(`#workerList .list-group-item[data-worker-id="${workerIdNum}"]`).toggleClass('active', index === -1);
            renderSelectedWorkersTags();
            if (selectedTeamId) { selectedTeamId = null; renderSelectedTeamTag(); $('#teamList .list-group-item.active').removeClass('active'); $('#teamSearch').val('');}
        }
        function renderSelectedWorkersTags() {
            const cont = $('#selected_workers_tags').empty();
            selectedWorkerIds.forEach(id => {
                const worker = staffUsers.find(u => u.user_id === id);
                if (worker) {
                    const displayName = `${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : '');
                    cont.append(`<span class="badge badge-primary mr-1 mb-1 p-2">${displayName} <button type="button" class="close ml-1 remove-worker-tag" data-id="${id}" aria-label="Remove">&times;</button></span>`);
                }
            });
            $('#workerList .list-group-item').each(function() { $(this).toggleClass('active', selectedWorkerIds.includes(parseInt($(this).data('worker-id'),10))); });
        }
        function loadTeams() {
            showLoading(true);
            $.getJSON('planning-handler.php?action=get_teams')
                .done(response => {
                    if (response.status === 'success' && response.data && Array.isArray(response.data.teams)) {
                        teamsData = response.data.teams.map(t => ({...t, team_id: parseInt(t.team_id, 10)}));
                        renderTeamList(); renderExistingTeamsListModal();
                    } else { showToast(response.message || 'Erreur chargement équipes.', 'error'); teamsData = []; }
                })
                .fail((xhr)=>{ showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error (get_teams):", xhr.responseText); teamsData = []; })
                .always(() => { showLoading(false); renderTeamList($('#teamSearch').val()); renderExistingTeamsListModal(); });
        }
        function renderTeamList(searchTerm = '') {
             const listEl = $('#teamList').empty();
            if (!Array.isArray(teamsData) || teamsData.length === 0) { listEl.append('<p class="text-muted p-2 small">Aucune équipe.</p>'); return; }
            const lowerSearchTerm = searchTerm.toLowerCase();
            const filteredTeams = teamsData.filter(t => String(t.team_name).toLowerCase().includes(lowerSearchTerm));
            if(filteredTeams.length === 0) { listEl.append(`<p class="text-muted p-2 small">${searchTerm ? 'Aucune équipe.' : 'Rechercher...'}</p>`);
            } else {
                filteredTeams.forEach(team => {
                    const item = $(`<a href="#" class="list-group-item list-group-item-action list-group-item-sm py-1 px-2" data-team-id="${team.team_id}">${team.team_name} <span class="badge badge-light float-right">${team.member_count}</span></a>`);
                    if (selectedTeamId === team.team_id) item.addClass('active');
                    listEl.append(item);
                });
            }
        }
        function selectTeam(teamId) {
            const teamIdNum = parseInt(teamId, 10);
            const team = teamsData.find(t => t.team_id === teamIdNum); if (!team) return;
            selectedTeamId = teamIdNum; $('#teamSearch').val(team.team_name);
            renderTeamList(team.team_name); renderSelectedTeamTag();
            if (selectedWorkerIds.length > 0) { selectedWorkerIds = []; renderSelectedWorkersTags(); $('#workerList .list-group-item.active').removeClass('active'); $('#workerSearch').val('');}
        }
        function renderSelectedTeamTag() {
            const cont = $('#selected_team_tag').empty();
            if (selectedTeamId) {
                const team = teamsData.find(t => t.team_id === selectedTeamId);
                if (team) cont.append(`<span class="badge badge-success mr-1 mb-1 p-2">${team.team_name} <button type="button" class="close ml-1" id="removeSelectedTeamTag">&times;</button></span>`);
            }
        }
        function clearAssignmentForm() {
            $('#assignmentForm')[0].reset(); 
            $('#assignment_id').val('');
            $('#is_group_event_form').val('0'); 
            selectedWorkerIds = []; selectedTeamId = null; selectedDatesFC = [];
            renderSelectedWorkersTags(); renderSelectedTeamTag();
            $('#assignment_dates_input').val('');
            const fp = document.querySelector("#assignment_dates_input")._flatpickr; if (fp) fp.clear();
            updateDateInputAndTagsFromSelectedDatesFC();
            $('#workerSearch').val(''); $('#teamSearch').val(''); renderWorkerList(''); renderTeamList('');
            $('.shift-btn').removeClass('active btn-success btn-info btn-purple-soft btn-secondary btn-dark').addClass('btn-outline-secondary');
            $('#shift_type').val(''); $('#start_time, #end_time').val('').prop('disabled',true); 
            $('#mission_text').prop('readonly', false).val(''); 
            $('#location').val('').prop('readonly', true); // Should be readonly, not true
            
            // Reset color swatches to default
            $('#event_color').val(defaultEventColor);
            $('#event_color_swatches .color-swatch').removeClass('selected');
            $(`#event_color_swatches .color-swatch[data-color="${defaultEventColor}"]`).addClass('selected');

            if(calendarAdmin) calendarAdmin.unselect();
        }
        
        let selectedTeamMembersModal = [];
        function initializeTeamManagementModal() {
            $('#teamManagementModal').on('show.bs.modal', function () {
                if (staffUsers.length > 0) renderTeamMemberListModal($('#teamMemberSearchModal').val(''));
                else loadStaffUsers(); 
                clearTeamFormModal();
            });
            $('#addNewTeamBtnModal').on('click', () => { clearTeamFormModal(); $('#teamFormTitle').text('Créer Nouvelle Équipe'); });
            $('#clearTeamFormModal').on('click', clearTeamFormModal);
            $('#teamForm').on('submit', function(e){
                e.preventDefault(); showLoading(true);
                const teamId = $('#team_id_modal').val(); const teamName = $('#team_name_modal').val().trim();
                if (!teamName) { showToast('Nom équipe requis.', 'warning'); showLoading(false); return; }
                if (selectedTeamMembersModal.length === 0) { showToast('Membres requis.', 'warning'); showLoading(false); return; }
                $.post('planning-handler.php', { action: 'save_team', team_id: teamId, team_name: teamName, member_ids: selectedTeamMembersModal })
                .done(response => {
                    if (response.status === 'success') { showToast(response.message, 'success'); loadTeams(); $('#teamManagementModal').modal('hide'); }
                    else showToast(response.message || 'Erreur sauvegarde équipe.', 'error');
                }).fail((xhr)=>{showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error:", xhr.responseText);
                }).always(()=>showLoading(false));
            });
            $('#deleteTeamBtnModal').on('click', function(){
                const teamId = $('#team_id_modal').val(); if (!teamId) return;
                if (confirm('Supprimer cette équipe ?')) {
                    showLoading(true);
                    $.post('planning-handler.php', { action: 'delete_team', team_id: teamId })
                    .done(response => {
                        if (response.status === 'success') { showToast(response.message, 'success'); loadTeams(); $('#teamManagementModal').modal('hide'); }
                        else showToast(response.message || 'Erreur suppression équipe.', 'error');
                    }).fail((xhr)=>{showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error:", xhr.responseText);
                    }).always(()=>showLoading(false));
                }
            });
            $('#teamMemberSearchModal').on('input', function() { renderTeamMemberListModal($(this).val()); });
            $('#teamMemberListModal').on('click', '.team-member-list-item-modal', function() { toggleTeamMemberSelectionModal($(this).data('id')); });
            $('#selected_team_members_tags_modal').on('click', '.remove-team-member-tag-modal', function() {
                const workerIdToRemove = parseInt($(this).data('id'), 10);
                const worker = staffUsers.find(u => u.user_id === workerIdToRemove);
                selectedTeamMembersModal = selectedTeamMembersModal.filter(id => id !== workerIdToRemove);
                renderSelectedTeamMembersTagsModal();
                $(`#teamMemberListModal .team-member-list-item-modal[data-id="${workerIdToRemove}"]`).removeClass('active');
                const displayName = worker ? `${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : '') : '';
                if (worker && $('#teamMemberSearchModal').val() === displayName) {
                    $('#teamMemberSearchModal').val(''); renderTeamMemberListModal('');
                }
            });
        }
        function renderExistingTeamsListModal() {
            const listEl = $('#existingTeamsList').empty();
            if(!Array.isArray(teamsData) || teamsData.length === 0) { listEl.append('<p class="text-muted p-2 small">Aucune équipe.</p>'); return; }
            teamsData.forEach(team => {
                const item = $(`<a href="#" class="list-group-item list-group-item-action list-group-item-sm py-1 px-2" data-id="${team.team_id}">${team.team_name} <span class="badge badge-light float-right">${team.member_count}</span></a>`);
                item.on('click', e => { e.preventDefault(); loadTeamForEditingModal(team.team_id); });
                listEl.append(item);
            });
        }
        function loadTeamForEditingModal(teamId) {
            showLoading(true);
            $.getJSON(`planning-handler.php?action=get_team_details&team_id=${teamId}`)
            .done(response => {
                if (response.status === 'success' && response.data && response.data.team) {
                    const t = response.data.team;
                    $('#teamFormTitle').text(`Modifier: ${t.team_name}`); $('#team_id_modal').val(t.team_id); $('#team_name_modal').val(t.team_name);
                    selectedTeamMembersModal = t.members ? t.members.map(m => parseInt(m.user_id, 10)) : [];
                    renderTeamMemberListModal(); renderSelectedTeamMembersTagsModal(); $('#deleteTeamBtnModal').show();
                } else showToast(response.message || 'Erreur chargement détails équipe.', 'error');
            }).fail((xhr)=>{showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error:", xhr.responseText);
            }).always(()=>showLoading(false));
        }
        function renderTeamMemberListModal(searchTerm = '') {
            const listEl = $('#teamMemberListModal').empty();
            if (!Array.isArray(staffUsers) || staffUsers.length === 0) { listEl.append('<p class="text-muted p-2 small">Aucun utilisateur.</p>'); return; }
            const lowerSearchTerm = searchTerm.toLowerCase();
            const filteredUsers = staffUsers.filter(u => { const dName = `${u.prenom} ${u.nom} ${u.role === 'admin' ? '(admin)' : ''}`.toLowerCase(); return dName.includes(lowerSearchTerm); });
            if(filteredUsers.length === 0) { listEl.append(`<p class="text-muted p-2 small">${searchTerm ? 'Aucun utilisateur.' : 'Rechercher...'}</p>`);
            } else {
                filteredUsers.forEach(user => {
                    const displayName = `${user.prenom} ${user.nom}` + (user.role === 'admin' ? ' (Admin)' : '');
                    const item = $(`<a href="#" class="list-group-item list-group-item-action list-group-item-sm py-1 px-2 team-member-list-item-modal" data-id="${user.user_id}">${displayName}</a>`);
                    if (selectedTeamMembersModal.includes(user.user_id)) item.addClass('active');
                    listEl.append(item);
                });
            }
        }
        function toggleTeamMemberSelectionModal(workerId) {
            const workerIdNum = parseInt(workerId, 10);
            const worker = staffUsers.find(u => u.user_id === workerIdNum); if (!worker) return;
            const index = selectedTeamMembersModal.indexOf(workerIdNum);
            const displayName = `${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : '');
            if (index > -1) {
                selectedTeamMembersModal.splice(index, 1);
                if ($('#teamMemberSearchModal').val() === displayName) { $('#teamMemberSearchModal').val(''); renderTeamMemberListModal(''); }
            } else { selectedTeamMembersModal.push(workerIdNum); $('#teamMemberSearchModal').val(displayName); }
            $(`#teamMemberListModal .team-member-list-item-modal[data-id="${workerIdNum}"]`).toggleClass('active', index === -1);
            renderSelectedTeamMembersTagsModal();
        }
        function renderSelectedTeamMembersTagsModal() {
            const cont = $('#selected_team_members_tags_modal').empty();
            selectedTeamMembersModal.forEach(id => {
                const worker = staffUsers.find(u => u.user_id === id);
                if (worker) {
                    const displayName = `${worker.prenom} ${worker.nom}` + (worker.role === 'admin' ? ' (Admin)' : '');
                    cont.append(`<span class="badge badge-info mr-1 mb-1 p-2">${displayName} <button type="button" class="close ml-1 remove-team-member-tag-modal" data-id="${id}" aria-label="Remove">&times;</button></span>`);
                }
            });
            $('#teamMemberListModal .list-group-item').each(function() { $(this).toggleClass('active', selectedTeamMembersModal.includes(parseInt($(this).data('id'),10))); });
        }
        function clearTeamFormModal() {
            $('#teamForm')[0].reset(); $('#team_id_modal').val(''); selectedTeamMembersModal = [];
            $('#teamMemberSearchModal').val(''); renderTeamMemberListModal(''); renderSelectedTeamMembersTagsModal();
            $('#teamFormTitle').text('Créer Nouvelle Équipe'); $('#deleteTeamBtnModal').hide();
        }
        <?php endif; ?>

        // --- Staff View JS (Common for standard staff and Admin's Personal Planning View) ---
        function initializeStaffView() {
            const staffViewContext = loggedInUserRole === 'admin' && adminViewingPersonal ? '#adminPersonalPlanningView' : '.staff-view-container';
            
            $(staffViewContext + ' .staff-bottom-nav .nav-item').off('click').on('click', function(e) {
                e.preventDefault();
                const tabId = $(this).data('tab');
                $(staffViewContext + ' .staff-bottom-nav .nav-item').removeClass('active');
                $(this).addClass('active');
                $(staffViewContext + ' .staff-content-tab').removeClass('active');
                $(staffViewContext + ' #' + tabId).addClass('active');
                
                if (tabId === 'staffListTab') {
                    const listContainer = $(staffViewContext + ' #staffListViewAssignments');
                    if (!listContainer.children().not('.text-muted').length) { // Load only if empty
                       loadStaffListAssignments();
                    }
                } else if (tabId === 'staffTodayTab') {
                    loadStaffDashboardData();
                }
            });
            
            initializeStaffListView(); // Initialize list view filters
        }

        function initializeStaffListView() {
            const staffListTabContext = loggedInUserRole === 'admin' && adminViewingPersonal ?
                                        '#adminPersonalPlanningView #staffListTab' :
                                        '.staff-view-container #staffListTab';

            $(staffListTabContext + ' .btn-group .btn').off('click').on('click', function() {
                $(this).addClass('active').siblings().removeClass('active');
                currentListPeriod = $(this).data('period');
                loadStaffListAssignments();
            });
        }


        function loadStaffDashboardData() {
            showLoading(true);
            const dashboardContext = loggedInUserRole === 'admin' && adminViewingPersonal ? '#adminPersonalPlanningView' : '.staff-view-container';
            const todayCardContainer = $(dashboardContext + ' #todayAssignmentCard');
            const upcomingListContainer = $(dashboardContext + ' #upcomingAssignmentsList');

            todayCardContainer.html('<div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>');
            upcomingListContainer.html('<div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>');


            $.getJSON('planning-handler.php?action=get_staff_dashboard_data')
            .done(response => {
                if (response.status === 'success' && response.data) {
                    renderTodayAssignment(response.data.today_assignment, todayCardContainer);
                    renderUpcomingAssignments(response.data.upcoming_assignments, upcomingListContainer);
                } else {
                    const errorMsg = '<div class="alert alert-warning small p-2">Impossible de charger le planning.</div>';
                    todayCardContainer.html(errorMsg);
                    upcomingListContainer.html(errorMsg);
                    showToast(response.message || 'Erreur chargement planning personnel.', 'error');
                }
            })
            .fail((xhr) => {
                showToast('Erreur communication: ' + xhr.statusText, 'error');
                const errorCommMsg = '<div class="alert alert-danger small p-2">Erreur de communication.</div>';
                 todayCardContainer.html(errorCommMsg);
                 upcomingListContainer.html(errorCommMsg);
                console.error("AJAX Error (staff_dashboard):", xhr.responseText);
            })
            .always(() => showLoading(false));
        }
        
        function loadStaffListAssignments() {
            const listContainerContext = loggedInUserRole === 'admin' && adminViewingPersonal ?
                                         '#adminPersonalPlanningView #staffListViewAssignments' :
                                         '.staff-view-container #staffListViewAssignments';
            const listContainer = $(listContainerContext);

            if (!listContainer.length) {
                console.error("Staff list view container not found for selector: " + listContainerContext);
                return;
            }
            
            listContainer.html('<div class="text-center p-3 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div>Chargement...</div>');
            showLoading(true);

            $.getJSON('planning-handler.php', {
                action: 'get_staff_list_assignments',
                period: currentListPeriod,
                user_id: loggedInUserId // This is crucial for fetching the correct user's data
            })
            .done(response => {
                if (response.status === 'success' && response.data && response.data.assignments) {
                    renderStaffListAssignments(response.data.assignments, listContainer);
                } else {
                    listContainer.html('<div class="list-group-item text-danger small p-2">Erreur de chargement du planning.</div>');
                    showToast(response.message || 'Erreur chargement planning (liste).', 'error');
                }
            })
            .fail(xhr => {
                listContainer.html('<div class="list-group-item text-danger small p-2">Erreur de communication.</div>');
                showToast('Erreur communication: ' + xhr.statusText, 'error');
                console.error("AJAX Error (get_staff_list_assignments):", xhr.responseText);
            })
            .always(() => showLoading(false));
        }

        function renderStaffListAssignments(assignments, container) {
            container.empty();
            if (!Array.isArray(assignments) || assignments.length === 0) {
                let message = 'Aucune affectation trouvée pour cette période.';
                if (currentListPeriod === 'current') message = 'Aucune affectation actuelle ou à venir.';
                else if (currentListPeriod === 'past') message = 'Aucune affectation passée.';
                else if (currentListPeriod === 'future') message = 'Aucune affectation future.';
                container.append(`<div class="list-group-item text-muted text-center small p-2">${message}</div>`);
                return;
            }
            assignments.forEach(a => {
                let times = a.start_time ? formatTimeHM(a.start_time) + (a.end_time ? ' - ' + formatTimeHM(a.end_time) : '') : 'Toute la journée';
                if (a.shift_type === 'repos') times = 'Repos';
                
                let shiftClass = 'shift-' + (String(a.shift_type) || 'custom').toLowerCase().replace(/[\s_]+/g, '-');

                const itemHtml = `
                    <a href="#" class="list-group-item list-group-item-action view-details-staff p-2 ${escapeHtml(shiftClass)}" data-assignment-id="${a.assignment_id}">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 small font-weight-bold">${formatDateToYMD(new Date(a.assignment_date + "T00:00:00Z"))}</h6>
                            <small class="text-muted">${getShiftBadge(a.shift_type)}</small>
                        </div>
                        <p class="mb-1 small text-muted">
                            <i class="fas fa-clock fa-fw"></i> ${escapeHtml(times)}
                            ${a.shift_type !== 'repos' && a.location ? ` <i class="fas fa-map-marker-alt fa-fw ml-2"></i> ${escapeHtml(a.location)}` : ''}
                        </p>
                        ${a.shift_type !== 'repos' && a.mission_text ? `<small class="text-muted d-block text-truncate">${escapeHtml(String(a.mission_text).replace(/\n/g, ' ')) || 'Aucune mission.'}</small>` : (a.shift_type !== 'repos' ? '<small class="text-muted d-block">Aucune mission.</small>' : '')}
                    </a>`;
                container.append(itemHtml);
            });
        }


        function renderTodayAssignment(assignment, container) {
            container.empty();
            if (!assignment) {
                container.append(`<div class="assignment-card-staff text-center"><i class="fas fa-coffee fa-2x text-muted mb-2"></i><h6>Aucune affectation aujourd'hui.</h6></div>`);
                return;
            }
            let shiftClass = 'shift-' + (String(assignment.shift_type) || 'custom').toLowerCase().replace(/[\s_]+/g, '-');
            let times = assignment.start_time ? formatTimeHM(assignment.start_time) + (assignment.end_time ? ' - ' + formatTimeHM(assignment.end_time) : '') : 'Toute la journée';
            if (assignment.shift_type === 'repos') times = 'Jour de repos';
            container.append(`
                <div class="assignment-card-staff ${shiftClass}">
                    <div class="d-flex justify-content-between align-items-start"><h6>Aujourd'hui (${formatDateToYMD(new Date(assignment.assignment_date + "T00:00:00Z"))})</h6>${getShiftBadge(assignment.shift_type)}</div>
                    <p class="meta-info mb-1"><i class="fas fa-clock fa-fw"></i> ${escapeHtml(times)}</p>
                    ${assignment.shift_type !== 'repos' ? `
                        ${assignment.location ? `<p class="meta-info mb-2 small"><i class="fas fa-map-marker-alt fa-fw"></i> ${escapeHtml(assignment.location)}</p>` : ''}
                        <p class="font-weight-bold small mb-1">Mission:</p>
                        <p class="mission-text-staff small">${escapeHtml(String(assignment.mission_text || '')).replace(/\n/g, '<br>') || '<i>Aucune.</i>'}</p>
                        <button class="btn btn-sm btn-outline-primary mt-2 btn-block view-details-staff" data-assignment-id="${assignment.assignment_id}">Détails</button>
                    ` : '<p class="mission-text-staff small mt-2">Profitez !</p>'}
                </div>`);
        }
        function renderUpcomingAssignments(assignments, container) {
            container.empty();
            if (!Array.isArray(assignments) || assignments.length === 0) {
                container.append('<div class="list-group-item text-muted text-center small p-2">Aucune affectation à venir.</div>'); return;
            }
            assignments.forEach(a => {
                let times = a.start_time ? formatTimeHM(a.start_time) + (a.end_time ? ' - ' + formatTimeHM(a.end_time) : '') : 'Toute la journée';
                if (a.shift_type === 'repos') times = 'Repos';
                
                let shiftClass = 'shift-' + (String(a.shift_type) || 'custom').toLowerCase().replace(/[\s_]+/g, '-');

                container.append(`
                    <a href="#" class="list-group-item list-group-item-action view-details-staff p-2 ${escapeHtml(shiftClass)}" data-assignment-id="${a.assignment_id}">
                        <div class="d-flex w-100 justify-content-between"><h6 class="mb-1 small font-weight-bold">${formatDateToYMD(new Date(a.assignment_date + "T00:00:00Z"))}</h6><small class="text-muted">${getShiftBadge(a.shift_type)}</small></div>
                        <p class="mb-1 small text-muted"><i class="fas fa-clock fa-fw"></i> ${escapeHtml(times)} ${a.shift_type !== 'repos' && a.location ? ` <i class="fas fa-map-marker-alt fa-fw ml-2"></i> ${escapeHtml(a.location)}` : ''}</p>
                        ${a.shift_type !== 'repos' && a.mission_text ? `<small class="text-muted d-block text-truncate">${escapeHtml(String(a.mission_text).replace(/\n/g, ' ')) || 'Aucune mission.'}</small>` : (a.shift_type !== 'repos' ? '<small class="text-muted d-block">Aucune mission.</small>' : '')}
                    </a>`);
            });
        }

        $(document).on('click', '.view-details-staff', function(e){ 
            e.preventDefault();
            const assignmentId = $(this).data('assignment-id');
            showLoading(true);
            $.getJSON(`planning-handler.php?action=get_assignment_details&assignment_id=${assignmentId}`)
            .done(response => {
                 if (response.status === 'success' && response.data.assignment) {
                    const a = response.data.assignment;
                    $('#detail_user_name').text(a.user_name_display || (a.prenom + ' ' + a.nom));
                    $('#detail_assignment_date').text(formatDateToYMD(a.assignment_date));
                    $('#detail_location').text(a.location || 'N/A');
                    $('#detail_shift_type_badge').html(getShiftBadge(a.shift_type));
                    let times = 'N/A';
                    if (a.start_time) { times = formatTimeHM(a.start_time) + (a.end_time ? ' - ' + formatTimeHM(a.end_time) : ''); }
                    else if (a.shift_type !== 'repos') { times = 'Toute la journée'; }
                    if (a.shift_type === 'repos') times = 'Jour de repos';
                    $('#detail_times').text(times);
                    $('#detail_mission_text').html(a.mission_text ? escapeHtml(String(a.mission_text || '')).replace(/\n/g, '<br>') : '<i>Aucune.</i>');
                    
                    $('#editAssignmentBtn, #deleteAssignmentBtn').hide();
                    $('#assignmentDetailModal').modal('show');
                } else showToast(response.message || 'Détails non trouvés.', 'error');
            })
            .fail((xhr)=>{ showToast('Erreur communication: ' + xhr.statusText,'error'); console.error("AJAX Error:", xhr.responseText); })
            .always(()=>showLoading(false));
        });
    </script>
</body>
</html>
