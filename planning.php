<?php
// planning.php (New Reworked Style)

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* Common Styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f0f2f5;
            color: #1c1e21;
        }
        .main-container {
            display: flex;
            min-height: calc(100vh - 70px); /* Adjust for navbar height */
            padding-top: 15px;
            padding-bottom: 20px;
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

        /* Left Column: Workers List */
        .workers-list-col {
            width: 280px; /* Fixed width */
            flex-shrink: 0;
            margin-right: 20px;
            position: sticky; /* Keep it visible when scrolling main content */
            top: 70px; /* Below the navbar */
            max-height: calc(100vh - 90px); /* Max height, with scroll if content exceeds */
        }
        .workers-list-card .card-body {
            max-height: calc(100vh - 200px); /* Adjusted for card header and padding */
            overflow-y: auto;
            padding: 15px;
        }
        .worker-item {
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 8px;
            background-color: #fcfdff;
            cursor: grab;
            transition: all 0.2s ease;
        }
        .worker-item:hover {
            background-color: #e6f7ff;
            border-color: #91d5ff;
        }
        .worker-item.assigned {
            background-color: #f0f0f0;
            border-color: #d0d0d0;
            color: #808080;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .worker-item.assigned .assigned-info {
            font-size: 0.85em;
            color: #d9534f;
            margin-top: 5px;
        }

        /* Right Column: Daily Planning */
        .daily-planning-col {
            flex-grow: 1; /* Takes remaining space */
            overflow-x: auto; /* Enable horizontal scrolling */
            padding-right: 15px; /* Padding for horizontal scrollbar */
        }
        .daily-planning-container {
            display: flex; /* Horizontal layout for days */
            min-width: 100%; /* Ensure it spans full width to allow horizontal scroll */
            width: fit-content; /* Adjust width to content */
            gap: 15px; /* Space between day columns */
            padding-bottom: 10px; /* Space for horizontal scrollbar */
        }
        .day-column {
            flex-shrink: 0; /* Prevent columns from shrinking */
            width: 300px; /* Fixed width for each day column */
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e5e5;
            min-height: 600px; /* Minimum height for scroll effect */
        }
        .day-header {
            padding: 15px;
            background-color: #f7f7f7;
            border-bottom: 1px solid #e0e0e0;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }
        .day-header h3 {
            font-size: 1.1em;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 5px;
        }
        .day-content {
            padding: 15px;
            min-height: 500px; /* Space for missions */
            background-color: #f9f9f9;
            border-radius: 0 0 12px 12px;
        }
        .add-mission-btn {
            width: 100%;
            padding: 8px 15px;
            margin-top: 10px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .mission-card {
            background-color: #fff;
            border-left: 5px solid; /* Dynamic color */
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .mission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .mission-card.validated {
            opacity: 0.7;
            border-color: #28a745 !important;
            background-color: #e6ffe6;
        }
        .mission-card .mission-title {
            font-weight: 600;
            font-size: 1em;
            color: #333;
            margin-bottom: 5px;
            padding-right: 30px; /* Space for buttons */
        }
        .mission-card .mission-meta {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 5px;
        }
        .mission-card .mission-meta i {
            margin-right: 5px;
        }
        .mission-card .worker-assigned-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 80px; /* Limit height for long lists */
            overflow-y: auto;
            border-top: 1px dashed #eee;
            padding-top: 5px;
            margin-top: 5px;
        }
        .mission-card .worker-assigned-list li {
            font-size: 0.75em;
            background-color: #e9f5ff;
            padding: 3px 6px;
            border-radius: 4px;
            margin-bottom: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mission-card .worker-assigned-list li .remove-worker-btn {
            background: none;
            border: none;
            color: #d9534f;
            font-size: 1.1em;
            cursor: pointer;
            padding: 0 3px;
        }
        .mission-card .drag-worker-placeholder {
            font-size: 0.8em;
            color: #999;
            text-align: center;
            padding: 10px 0;
        }
        .mission-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
        }
        .mission-actions button {
            background: none;
            border: none;
            color: #999;
            font-size: 1em;
            cursor: pointer;
            padding: 3px;
        }
        .mission-actions button:hover {
            color: #333;
        }
        .mission-actions .validate-btn.active {
            color: #28a745; /* Green when validated */
        }

        /* Mission Form Modal */
        #missionFormModal .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        #missionFormModal .modal-header {
            background-color: #007bff;
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        #missionFormModal .modal-header .close {
            color: white;
            opacity: 0.8;
            font-size: 1.5rem;
        }
        #missionFormModal .color-swatch {
            width: 30px; height: 30px; border-radius: 50%;
            cursor: pointer; border: 2px solid transparent;
            transition: all 0.2s ease; margin-right: 8px;
        }
        #missionFormModal .color-swatch.selected {
            border-color: #333;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #333;
        }
        #missionFormModal .btn-group-sm .btn {
            font-size: 0.85rem;
            padding: .3rem .6rem;
            border-radius: 5px;
        }
        #missionFormModal .btn-outline-purple {
            color: #6f42c1; border-color: #6f42c1;
        }
        #missionFormModal .btn-outline-purple:hover {
            background-color: #6f42c1; color: white;
        }

        /* Loading Overlay */
        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.8); z-index: 1060;
            display: flex; justify-content: center; align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }

        /* Media Queries for Responsiveness */
        @media (max-width: 991px) { /* Tablet and smaller */
            .main-container {
                flex-direction: column;
                padding-top: 0;
            }
            .workers-list-col {
                width: 100%;
                position: static;
                margin-right: 0;
                margin-bottom: 20px;
                max-height: none;
            }
            .workers-list-card .card-body {
                max-height: 300px; /* Shorter list on smaller screens */
            }
            .daily-planning-col {
                padding-right: 0;
            }
            .daily-planning-container {
                flex-wrap: wrap; /* Allow days to wrap to next line */
                justify-content: center; /* Center day columns */
            }
            .day-column {
                width: 95%; /* Adjust width to fit more on smaller screens */
                max-width: 350px; /* Max width to prevent too wide columns on larger tablets */
                margin-bottom: 15px; /* Space between wrapped day columns */
            }
        }

        @media (max-width: 575px) { /* Mobile */
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            .card {
                padding: 15px;
            }
            .worker-item {
                font-size: 0.9em;
            }
            .day-column {
                width: 100%; /* Full width on very small screens */
            }
        }
    </style>
</head>
<body class="body-with-staff-nav">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid main-container">
        <div class="workers-list-col">
            <div class="card workers-list-card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-users mr-2"></i>Ouvriers Disponibles
                </div>
                <div class="card-body">
                    <div id="workerList" class="list-group list-group-flush">
                        <div class="text-center text-muted p-3">Chargement des ouvriers...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="daily-planning-col">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt mr-2"></i>Planning Hebdomadaire
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button class="btn btn-outline-secondary btn-sm" id="prevWeekBtn"><i class="fas fa-arrow-left"></i> Semaine Précédente</button>
                        <h2 class="h4 mb-0" id="currentWeekRange"></h2>
                        <button class="btn btn-outline-secondary btn-sm" id="nextWeekBtn">Semaine Prochaine <i class="fas fa-arrow-right"></i></button>
                    </div>

                    <div id="dailyPlanningContainer" class="daily-planning-container">
                        </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="missionFormModal" tabindex="-1" aria-labelledby="missionFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="missionFormModalLabel">Nouvelle Mission</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="missionForm">
                    <input type="hidden" id="mission_date_form" name="mission_date">
                    <input type="hidden" id="original_mission_id_form" name="original_mission_id"> <input type="hidden" id="is_group_mission_edit" name="is_group_mission_edit" value="0"> <div class="modal-body">
                        <div class="form-group">
                            <label for="mission_title">Titre de la mission *</label>
                            <input type="text" class="form-control form-control-sm" id="mission_title" name="title" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="mission_start_time">Heure début</label>
                                <input type="time" class="form-control form-control-sm" id="mission_start_time" name="start_time">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="mission_end_time">Heure fin</label>
                                <input type="time" class="form-control form-control-sm" id="mission_end_time" name="end_time">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="mission_location">Lieu de mission</label>
                            <input type="text" class="form-control form-control-sm" id="mission_location" name="location" placeholder="Ex: 123 Rue de la Paix, Paris">
                        </div>
                        <div class="form-group">
                            <label for="mission_comment">Commentaire</label>
                            <textarea class="form-control form-control-sm" id="mission_comment" name="comment" rows="3" placeholder="Détails sur la mission, instructions spéciales..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Type de service</label>
                            <div class="btn-group btn-group-toggle d-flex flex-wrap" data-toggle="buttons" id="shift_type_buttons">
                                <label class="btn btn-sm btn-outline-success">
                                    <input type="radio" name="shift_type" value="matin" autocomplete="off"> Matin
                                </label>
                                <label class="btn btn-sm btn-outline-info">
                                    <input type="radio" name="shift_type" value="apres-midi" autocomplete="off"> Après-midi
                                </label>
                                <label class="btn btn-sm btn-outline-purple">
                                    <input type="radio" name="shift_type" value="nuit" autocomplete="off"> Nuit
                                </label>
                                <label class="btn btn-sm btn-outline-secondary">
                                    <input type="radio" name="shift_type" value="repos" autocomplete="off"> Repos
                                </label>
                                <label class="btn btn-sm btn-outline-dark">
                                    <input type="radio" name="shift_type" value="custom" autocomplete="off"> Personnalisé
                                </label>
                            </div>
                            <input type="hidden" id="selected_shift_type" name="selected_shift_type">
                        </div>
                        <div class="form-group">
                            <label for="mission_color">Couleur</label>
                            <div id="mission_color_swatches" class="d-flex flex-wrap">
                                <?php foreach ($predefined_colors as $color_hex): ?>
                                    <div class="color-swatch" style="background-color: <?php echo $color_hex; ?>;" data-color="<?php echo $color_hex; ?>" title="<?php echo $color_hex; ?>"></div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="mission_color" name="color" value="<?php echo $default_color; ?>">
                        </div>
                        <div id="modal_error_message" class="alert alert-danger mt-3" style="display: none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-danger" id="deleteMissionBtn" style="display: none;">Supprimer Mission</button>
                        <button type="submit" class="btn btn-primary" id="saveMissionBtn">Créer Mission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loadingOverlay" style="display: none;">
        <div class="spinner-border text-primary" role="status"><span class="sr-only">Chargement...</span></div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>

    <script>
        // --- GLOBAL STATE ---
        const HANDLER_URL = 'planning-handler.php';
        let staffUsers = [];
        let missions = {}; // Stores missions grouped by date (dateKey: [missionData])
        let draggedOuvrier = null;
        let currentWeekStart = new Date(); // Start of the currently displayed week
        const defaultMissionColor = '<?php echo $default_color; ?>';
        const predefinedColors = <?php echo json_encode($predefined_colors); ?>;
        const loggedInUserRole = '<?php echo $user_role; ?>';

        // --- UTILITY FUNCTIONS ---
        function showLoading(show) { $('#loadingOverlay').toggle(show); }
        function showModalError(message) {
            $('#modal_error_message').text(message).show();
            setTimeout(() => $('#modal_error_message').fadeOut(), 5000);
        }
        function ucfirst(str) { return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase(); }
        function formatDateToYMD(date) { return date.toISOString().split('T')[0]; }
        function formatTimeHM(timeStr) { return timeStr ? String(timeStr).substring(0, 5) : ''; }
        function getShiftLabel(shiftType) {
            switch (shiftType) {
                case 'matin': return 'Matin';
                case 'apres-midi': return 'Après-midi';
                case 'nuit': return 'Nuit';
                case 'repos': return 'Repos';
                case 'custom': return 'Personnalisé';
                default: return shiftType;
            }
        }

        // --- INITIALIZATION ---
        $(document).ready(function() {
            setWeekRange(new Date()); // Initialize with current week
            loadStaffUsers();
            attachEventListeners();
            initializeFlatpickr();
            renderColorSwatches();
        });

        function attachEventListeners() {
            $('#prevWeekBtn').on('click', () => changeWeek(-7));
            $('#nextWeekBtn').on('click', () => changeWeek(7));

            // Mission form
            $('#missionFormModal').on('hidden.bs.modal', resetMissionForm);
            $('#missionForm').on('submit', handleMissionFormSubmit);
            $('#deleteMissionBtn').on('click', handleDeleteMission);

            // Shift type buttons in modal
            $('#shift_type_buttons input[type="radio"]').on('change', function() {
                const selectedShift = $(this).val();
                $('#selected_shift_type').val(selectedShift);
                const isTimeDisabled = (selectedShift === 'repos');
                $('#mission_start_time, #mission_end_time').prop('disabled', isTimeDisabled);
                if (isTimeDisabled) {
                    $('#mission_start_time, #mission_end_time').val('');
                }
            });

            // Day column click for new mission (desktop/tablet)
            $('#dailyPlanningContainer').on('click', '.day-content .add-mission-btn', function() {
                const dateKey = $(this).data('date');
                openMissionFormForCreate(dateKey);
            });

            // Mission card click for edit
            $('#dailyPlanningContainer').on('click', '.mission-card', function() {
                const dateKey = $(this).data('date');
                const originalMissionId = $(this).data('original-mission-id');
                const isGroupMission = $(this).data('is-group-mission');
                openMissionFormForEdit(dateKey, originalMissionId, isGroupMission);
            });

            // Remove worker from mission
            $('#dailyPlanningContainer').on('click', '.remove-worker-btn', function(e) {
                e.stopPropagation(); // Prevent mission card click
                const workerId = $(this).data('worker-id');
                const missionDate = $(this).closest('.mission-card').data('date');
                const originalMissionId = $(this).closest('.mission-card').data('original-mission-id');
                removeWorkerFromMission(workerId, missionDate, originalMissionId);
            });

            // Validate mission
            $('#dailyPlanningContainer').on('click', '.validate-btn', function(e) {
                e.stopPropagation(); // Prevent mission card click
                const missionDate = $(this).closest('.mission-card').data('date');
                const originalMissionId = $(this).closest('.mission-card').data('original-mission-id');
                toggleMissionValidation(missionDate, originalMissionId);
            });

             // Color swatches
             $('#mission_color_swatches').on('click', '.color-swatch', function() {
                $('#mission_color_swatches .color-swatch').removeClass('selected');
                $(this).addClass('selected');
                $('#mission_color').val($(this).data('color'));
            });
        }

        function initializeFlatpickr() {
            // No direct flatpickr input, dates are selected dynamically.
            // This function is kept for potential future use or if a date picker is added directly.
        }

        function renderColorSwatches() {
            const swatchesContainer = $('#mission_color_swatches');
            swatchesContainer.empty();
            predefinedColors.forEach(color => {
                swatchesContainer.append(
                    `<div class="color-swatch" style="background-color: ${color};" data-color="${color}"></div>`
                );
            });
            $(`.color-swatch[data-color="${defaultMissionColor}"]`).addClass('selected');
        }

        // --- WEEK NAVIGATION ---
        function setWeekRange(date) {
            currentWeekStart = new Date(date);
            currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay()); // Start of week (Sunday)

            const weekEnd = new Date(currentWeekStart);
            weekEnd.setDate(weekEnd.getDate() + 6); // End of week (Saturday)

            const options = { day: 'numeric', month: 'long', year: 'numeric' };
            $('#currentWeekRange').text(
                `Semaine du ${currentWeekStart.toLocaleDateString('fr-FR', options)} au ${weekEnd.toLocaleDateString('fr-FR', options)}`
            );
            loadPlanningData();
        }

        function changeWeek(days) {
            currentWeekStart.setDate(currentWeekStart.getDate() + days);
            setWeekRange(currentWeekStart);
        }

        // --- DATA LOADING ---
        async function loadStaffUsers() {
            showLoading(true);
            try {
                const response = await apiCall('get_staff_users');
                staffUsers = response.data.users || [];
                renderWorkerList();
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }

        async function loadPlanningData() {
            showLoading(true);
            const endDate = new Date(currentWeekStart);
            endDate.setDate(endDate.getDate() + 6); // End of current week

            try {
                // Fetch grouped missions (planning entries)
                const missionsData = await apiCall('get_assignments', 'GET', {
                    start: formatDateToYMD(currentWeekStart),
                    end: formatDateToYMD(endDate)
                });
                // missionsData.data.missions is now an array of grouped missions
                missions = {};
                (missionsData.data.missions || []).forEach(mission => {
                    const dateKey = mission.start_date_group;
                    if (!missions[dateKey]) missions[dateKey] = [];
                    missions[dateKey].push(mission);
                });

                // Fetch all assignments for workers to determine 'assigned' status
                const allAssignments = await apiCall('get_all_assignments_for_workers');
                const workerAssignments = {}; // { workerId: { dateKey: missionTitle, ... } }
                (allAssignments.data.assignments || []).forEach(assignment => {
                    const dateKey = formatDateToYMD(new Date(assignment.assignment_date));
                    if (!workerAssignments[assignment.assigned_user_id]) workerAssignments[assignment.assigned_user_id] = {};
                    workerAssignments[assignment.assigned_user_id][dateKey] = assignment.mission_text || getShiftLabel(assignment.shift_type);
                });
                staffUsers.forEach(user => {
                    user.assignedMissions = workerAssignments[user.user_id] || {};
                });

                renderPlanning();
                renderWorkerList(); // Re-render workers to show assigned status
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }


        // --- RENDERING ---
        function renderWorkerList() {
            const listEl = $('#workerList');
            listEl.empty();
            if (staffUsers.length === 0) {
                listEl.append('<div class="text-center text-muted p-3">Aucun ouvrier disponible.</div>');
                return;
            }

            staffUsers.forEach(ouvrier => {
                const assignedOnDisplayedDays = Object.keys(missions).some(dateKey => ouvrier.assignedMissions[dateKey]);
                const assignedInfo = assignedOnDisplayedDays ?
                    `<div class="assigned-info">🚫 Affecté: ${Object.entries(ouvrier.assignedMissions)
                        .filter(([dateKey, title]) => missions[dateKey]) // Only show if on currently displayed days
                        .map(([dateKey, title]) => `${title} (${new Date(dateKey).toLocaleDateString('fr-FR', { weekday: 'short' })})`)
                        .join(', ')}</div>` : '';

                const item = $(`
                    <div class="worker-item ${assignedOnDisplayedDays ? 'assigned' : ''}"
                         draggable="${!assignedOnDisplayedDays}"
                         data-id="${ouvrier.user_id}"
                         data-nom="${ouvrier.nom}"
                         data-prenom="${ouvrier.prenom}">
                        <div class="font-weight-bold">${ouvrier.prenom} ${ouvrier.nom}</div>
                        ${assignedInfo}
                    </div>
                `);
                if (!assignedOnDisplayedDays) {
                    item.on('dragstart', handleDragStart);
                }
                listEl.append(item);
            });
        }

        function renderPlanning() {
            const container = $('#dailyPlanningContainer');
            container.empty();
            const days = [];
            let currentDate = new Date(currentWeekStart);
            for (let i = 0; i < 7; i++) {
                days.push({
                    date: new Date(currentDate), // Clone date object
                    dateKey: formatDateToYMD(currentDate)
                });
                currentDate.setDate(currentDate.getDate() + 1);
            }

            days.forEach(day => {
                const dayColumn = $(`
                    <div class="day-column">
                        <div class="day-header">
                            <h3>${day.date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'short' })}</h3>
                            <button class="btn btn-sm btn-success add-mission-btn" data-date="${day.dateKey}">
                                <i class="fas fa-plus"></i> Nouvelle Mission
                            </button>
                        </div>
                        <div class="day-content" data-date="${day.dateKey}">
                            </div>
                    </div>
                `);
                container.append(dayColumn);
                renderMissionsForDay(day.dateKey);

                // Add drag/drop listeners to the day content area
                dayColumn.find('.day-content')
                    .on('dragover', handleDragOver)
                    .on('drop', handleDayDrop);
            });
        }

        function renderMissionsForDay(dateKey) {
            const dayContent = $(`.day-content[data-date="${dateKey}"]`);
            dayContent.empty();
            const missionsForDay = missions[dateKey] || [];

            if (missionsForDay.length === 0) {
                 dayContent.append('<div class="text-center text-muted p-5">Aucune mission pour ce jour.</div>');
                 return;
            }

            missionsForDay.forEach(mission => {
                const missionCard = $(`
                    <div class="mission-card ${mission.is_validated ? 'validated' : ''}"
                         data-date="${dateKey}"
                         data-original-mission-id="${mission.representative_assignment_id}"
                         data-is-group-mission="true"
                         style="border-left-color: ${mission.color || defaultMissionColor};">
                        <div class="mission-actions">
                            <button class="validate-btn ${mission.is_validated ? 'active' : ''}" title="${mission.is_validated ? 'Dévalider la mission' : 'Valider la mission'}">
                                <i class="fas fa-check"></i>
                            </button>
                             <button class="edit-btn" title="Modifier la mission"><i class="fas fa-edit"></i></button>
                             <button class="delete-btn" title="Supprimer la mission"><i class="fas fa-trash-alt"></i></button>
                        </div>
                        <div class="mission-title">${mission.title}</div>
                        <div class="mission-meta">
                            ${mission.start_time || mission.end_time ? `<i class="fas fa-clock"></i> ${formatTimeHM(mission.start_time)} - ${formatTimeHM(mission.end_time)}` : `<i class="fas fa-tags"></i> ${getShiftLabel(mission.shift_type)}`}
                            ${mission.location ? `<br><i class="fas fa-map-marker-alt"></i> ${mission.location}` : ''}
                        </div>
                        <ul class="worker-assigned-list">
                            </ul>
                         ${(mission.user_names_list && mission.user_names_list.length > 0) ? '' : '<div class="drag-worker-placeholder">Glissez un ouvrier ici</div>'}
                    </div>
                `);

                const workerList = missionCard.find('.worker-assigned-list');
                (mission.user_ids_list || []).forEach(workerId => {
                    const worker = staffUsers.find(u => u.user_id == workerId); // Use == for type coercion
                    if (worker) {
                        workerList.append(`
                            <li>
                                <span>${worker.prenom} ${worker.nom}</span>
                                <button class="remove-worker-btn" data-worker-id="${worker.user_id}" title="Retirer l'ouvrier">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </li>
                        `);
                    }
                });

                dayContent.append(missionCard);
            });
        }

        // --- DRAG AND DROP HANDLERS ---
        function handleDragStart(e) {
            draggedOuvrier = {
                id: $(e.currentTarget).data('id'),
                nom: $(e.currentTarget).data('nom'),
                prenom: $(e.currentTarget).data('prenom')
            };
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
        }

        async function handleDayDrop(e) {
            e.preventDefault();
            const dateKey = $(e.currentTarget).data('date');

            if (!draggedOuvrier) return;

            const targetMissionCard = $(e.target).closest('.mission-card');
            let targetMission = null;

            if (targetMissionCard.length) {
                 // Dropped on a specific mission
                const originalMissionId = targetMissionCard.data('original-mission-id');
                // Find the mission object in our local state
                targetMission = missions[dateKey].find(m => m.representative_assignment_id == originalMissionId);
            } else {
                // Dropped on the day column directly. If no missions exist, this should create a new default mission.
                // Or, if there are missions, assign to a default/first mission.
                // For now, let's make it create a new default mission if no mission card is clicked.
                if ((missions[dateKey] || []).length === 0) {
                     // Create a new default mission for this day and assign the worker to it
                    showLoading(true);
                    try {
                        const newMissionData = {
                            title: 'Nouvelle Mission',
                            date: dateKey,
                            start_time: null,
                            end_time: null,
                            location: null,
                            comment: null,
                            shift_type: 'custom',
                            color: defaultMissionColor,
                            assigned_user_ids: [draggedOuvrier.id]
                        };
                        const response = await apiCall('save_assignment', 'POST', newMissionData);
                        if (response.status === 'success') {
                            // Reload planning to reflect the new mission and assignment
                            await loadPlanningData();
                            alert(`Ouvrier ${draggedOuvrier.prenom} ${draggedOuvrier.nom} a été affecté à une nouvelle mission.`);
                        } else {
                            alert(response.message);
                        }
                    } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
                    draggedOuvrier = null; // Reset dragged worker
                    return;
                } else {
                    // If dropped on day content without a specific mission, assign to the first mission of the day.
                    targetMission = missions[dateKey][0];
                }
            }

            if (targetMission) {
                // Check if worker is already assigned to this mission or any other mission for this day
                const isAlreadyAssignedToThisMission = targetMission.user_ids_list.includes(draggedOuvrier.id.toString());
                const isAssignedElsewhereToday = staffUsers.find(u => u.user_id === draggedOuvrier.id)?.assignedMissions[dateKey];

                if (isAlreadyAssignedToThisMission) {
                    alert('Cet ouvrier est déjà affecté à cette mission pour ce jour.');
                    draggedOuvrier = null;
                    return;
                }

                if (isAssignedElsewhereToday) {
                    if (!confirm(`Ouvrier ${draggedOuvrier.prenom} ${draggedOuvrier.nom} est déjà affecté à "${isAssignedElsewhereToday}" pour ce jour. Voulez-vous déplacer cet ouvrier à cette nouvelle mission?`)) {
                        draggedOuvrier = null;
                        return;
                    }
                }

                // Call backend to assign worker
                showLoading(true);
                try {
                    const response = await apiCall('assign_worker_to_mission', 'POST', {
                        worker_id: draggedOuvrier.id,
                        mission_date: dateKey,
                        original_mission_id: targetMission.representative_assignment_id // Reference the mission group
                    });

                    if (response.status === 'success') {
                        await loadPlanningData(); // Re-fetch all data to ensure UI consistency
                        alert(response.message);
                    } else {
                        alert(response.message);
                    }
                } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
            }
            draggedOuvrier = null; // Reset dragged worker
        }


        // --- MISSION FORM & ACTIONS (CREATE/EDIT/DELETE) ---
        function openMissionFormForCreate(dateKey) {
            resetMissionForm();
            $('#missionFormModalLabel').text('Nouvelle Mission');
            $('#mission_date_form').val(dateKey);
            $('#original_mission_id_form').val('');
            $('#is_group_mission_edit').val('0');
            $('#saveMissionBtn').text('Créer Mission');
            $('#deleteMissionBtn').hide();
            $('#missionFormModal').modal('show');
             // Set default start/end times if applicable
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            $('#mission_start_time').val(`${hours}:${minutes}`);
            const endHour = String(now.getHours() + 1).padStart(2, '0');
            $('#mission_end_time').val(`${endHour}:${minutes}`);
            $('#mission_start_time, #mission_end_time').prop('disabled', false); // Ensure fields are enabled for new mission
        }

        async function openMissionFormForEdit(dateKey, originalMissionId, isGroupMission) {
            resetMissionForm();
            showLoading(true);
            try {
                const response = await apiCall('get_mission_details_for_edit', 'GET', {
                    mission_date: dateKey,
                    original_mission_id: originalMissionId
                });

                if (response.status === 'success' && response.data.mission) {
                    const mission = response.data.mission;
                    $('#missionFormModalLabel').text('Modifier Mission');
                    $('#mission_date_form').val(dateKey);
                    $('#original_mission_id_form').val(originalMissionId);
                    $('#is_group_mission_edit').val(isGroupMission ? '1' : '0');
                    $('#mission_title').val(mission.title);
                    $('#mission_start_time').val(formatTimeHM(mission.start_time));
                    $('#mission_end_time').val(formatTimeHM(mission.end_time));
                    $('#mission_location').val(mission.location);
                    $('#mission_comment').val(mission.mission_text);

                    // Set shift type radio button
                    $(`#shift_type_buttons input[value="${mission.shift_type}"]`).prop('checked', true).closest('label').addClass('active');
                    $('#selected_shift_type').val(mission.shift_type);
                    const isTimeDisabled = (mission.shift_type === 'repos');
                    $('#mission_start_time, #mission_end_time').prop('disabled', isTimeDisabled);

                    // Set color swatch
                    $('#mission_color_swatches .color-swatch').removeClass('selected');
                    $(`.color-swatch[data-color="${mission.color}"]`).addClass('selected');
                    $('#mission_color').val(mission.color);

                    $('#saveMissionBtn').text('Modifier Mission');
                    $('#deleteMissionBtn').show();
                    $('#missionFormModal').modal('show');
                } else {
                    alert(response.message || 'Impossible de charger les détails de la mission.');
                }
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }

        async function handleMissionFormSubmit(e) {
            e.preventDefault();
            showLoading(true);
            const isEdit = $('#original_mission_id_form').val() !== '';
            const isGroupMissionEdit = $('#is_group_mission_edit').val() === '1';

            const formData = {
                action: 'save_assignment', // This action saves/updates assignments
                mission_date: $('#mission_date_form').val(),
                title: $('#mission_title').val(),
                start_time: $('#mission_start_time').val() || null,
                end_time: $('#mission_end_time').val() || null,
                location: $('#mission_location').val() || null,
                comment: $('#mission_comment').val() || null,
                shift_type: $('#selected_shift_type').val(),
                color: $('#mission_color').val(),
            };

            if (isEdit) {
                formData.original_mission_id = $('#original_mission_id_form').val();
                formData.is_group_edit = isGroupMissionEdit; // Flag for backend to update all related assignments
            } else {
                // For new missions, we need to explicitly assign users.
                // Since the React UI's "Nouvelle Mission" doesn't have an explicit user selector,
                // this implies it's either for the current dragged user, or for a default set.
                // Given the current setup, when creating a new mission from the day column, no user is pre-selected.
                // For simplicity, let's assume a new mission needs at least one user if not being edited.
                // Or, the creation from the 'day-column' is just a template, and users are added via drag-and-drop.
                // The React UI shows mission creation independently from worker assignment.
                // Let's assume a new mission creates an "empty" mission, and users are added via drag/drop.
                // If this is for new, single assignment:
                // formData.assigned_user_ids = [some_user_id]; // This is problematic without a selector.
                // The current backend saves multiple `Planning_Assignments` if multiple users/dates are given.
                // The problem statement says "create multiple planning assignments for different employees and dates for this mission." (in my internal docs)
                // This implies this form needs an employee picker if it's creating from scratch.
                // However, the React UI snippet doesn't show one in the mission form.

                // Let's interpret "Créer Mission" as creating the *mission definition*.
                // If it's a new mission, it will only create the template data, and users must be assigned by dragging.
                // So, for now, if it's a new mission AND no users are "selected" (not in this form),
                // the backend will just create the mission, but it won't be assigned to anyone.
                // If created from drag-drop (handleDayDrop), it's already creating for a specific user.
                // For the modal form, it must be the admin creating a "group mission" as a template.
                // Let's adjust the backend to allow saving a mission without users if it's a new "group" definition.
                // Or: assume that 'save_assignment' from the modal will try to apply it to `selectedWorkerIds` from the left panel.
                // This is a big interpretation gap.

                // Given the React UI for `creerMission` just saves the mission details (title, time, location),
                // and then users are dragged onto it, this implies the backend 'save_assignment'
                // needs to handle creating a "mission template" without initial users, or a new assignment
                // for the currently dragged user.

                // Let's simplify: the modal is for editing/creating *mission definitions*.
                // For creation, it creates one default assignment if no users are dragged.
                // If creating a new mission from the button, it's a template.
                // The `planning-handler.php` `save_assignment` handles `assigned_user_ids` and `assignment_dates`.

                // For the React UI's "Nouvelle Mission", it implicitly ties to the `jour` it was clicked on.
                // It also creates the mission, and THEN allows drag and drop.
                // My `save_assignment` current implementation takes `assigned_user_ids`.

                // Crucial decision: When "Créer Mission" is clicked in the modal, for *new* missions,
                // should it create an assignment for the current admin user? Or require selecting users?
                // The React UI has no user selection in the "Nouvelle Mission" modal.
                // It just creates an "empty" mission to which workers are dragged.

                // Let's refine `save_assignment` in handler:
                // If `original_mission_id` is empty AND `assigned_user_ids` is also empty, this is a new "template mission".
                // In this case, `save_assignment` will just insert ONE `Planning_Assignment` entry with dummy `assigned_user_id`
                // (e.g., the creator's ID, but this breaks "assignment" meaning) or it will need to be `NULL`.
                // But `assigned_user_id` is `NOT NULL` in schema.

                // This means the "Nouvelle Mission" must *always* assign to *someone*.
                // The React UI is simpler and assumes post-creation drag-drop.
                // The simplest path for adherence to DB and React UI's *drag-drop* nature:
                // 1. "Nouvelle Mission" button will open a form. If submitted, it will create
                //    a `Planning_Assignment` for the *admin user itself* for that date as a placeholder/template.
                //    Then the admin can drag others. This is a workaround.

                // Let's stick to the current backend `save_assignment` expecting `assigned_user_ids` or `assigned_team_id`.
                // For a new mission from the modal, we need *some* user.
                // Given the React UI doesn't have it, I'll implicitly assign to the creating admin for now,
                // for the sake of making it function. This needs a clear design choice.
                // Or, just create the mission *without* assigned_user_ids in the data for the API call,
                // and the backend decides how to handle that. But the schema is NOT NULL.

                // New approach: The "Creer Mission" button in the modal will implicitly assume
                // it is creating a mission that the admin intends to assign to users later,
                // but for DB compliance, it needs an assigned_user_id.
                // This means the primary use case of this form is for editing existing multi-user missions.
                // And new missions are created by drag-and-drop on the daily column.

                // Let's use the provided `planning-handler.php`'s `save_assignment` that supports
                // `assigned_user_ids` (array) or `assigned_team_id`.
                // The React UI's "Nouvelle Mission" doesn't have this.
                // I will add a temporary hidden field `assigned_user_ids` to the modal form,
                // and for new missions, it will default to `[loggedInUserId]` for creation.
                // This is a pragmatic workaround.

                formData.assigned_user_ids = [loggedInUserId]; // Default to admin for new missions from modal

            }

            if (!formData.title) { showModalError('Le titre de la mission est requis.'); showLoading(false); return; }
            if (formData.shift_type !== 'repos' && (!formData.start_time || !formData.end_time)) { showModalError('Les heures de début et de fin sont requises pour ce type de service.'); showLoading(false); return; }

            try {
                const response = await apiCall('save_assignment', 'POST', formData);
                if (response.status === 'success') {
                    alert(response.message);
                    $('#missionFormModal').modal('hide');
                    await loadPlanningData(); // Refresh all planning data
                } else {
                    showModalError(response.message);
                }
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }

        async function handleDeleteMission() {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette mission pour tous les utilisateurs assignés ce jour-là? Cette action est irréversible.')) return;
            showLoading(true);
            const missionDate = $('#mission_date_form').val();
            const originalMissionId = $('#original_mission_id_form').val();

            try {
                const response = await apiCall('delete_assignment', 'POST', {
                    mission_date: missionDate,
                    original_mission_id: originalMissionId,
                    is_group_delete: true
                });
                if (response.status === 'success') {
                    alert(response.message);
                    $('#missionFormModal').modal('hide');
                    await loadPlanningData();
                } else {
                    alert(response.message);
                }
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }

        async function removeWorkerFromMission(workerId, missionDate, originalMissionId) {
            if (!confirm('Êtes-vous sûr de vouloir retirer cet ouvrier de cette mission?')) return;
            showLoading(true);
            try {
                const response = await apiCall('remove_worker_from_assignment', 'POST', {
                    worker_id: workerId,
                    mission_date: missionDate,
                    original_mission_id: originalMissionId
                });
                if (response.status === 'success') {
                    alert(response.message);
                    await loadPlanningData();
                } else {
                    alert(response.message);
                }
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }

        async function toggleMissionValidation(missionDate, originalMissionId) {
            showLoading(true);
            try {
                const response = await apiCall('toggle_mission_validation', 'POST', {
                    mission_date: missionDate,
                    original_mission_id: originalMissionId
                });
                if (response.status === 'success') {
                    alert(response.message);
                    await loadPlanningData();
                } else {
                    alert(response.message);
                }
            } catch (error) { /* Handled by apiCall */ } finally { showLoading(false); }
        }


        function resetMissionForm() {
            $('#missionForm')[0].reset();
            $('#missionFormModalLabel').text('Nouvelle Mission');
            $('#mission_date_form').val('');
            $('#original_mission_id_form').val('');
            $('#is_group_mission_edit').val('0');
            $('#modal_error_message').hide();
            $('#saveMissionBtn').text('Créer Mission');
            $('#deleteMissionBtn').hide();
            $('#shift_type_buttons input[type="radio"]').prop('checked', false).closest('label').removeClass('active');
            $('#selected_shift_type').val('');
            $('#mission_start_time, #mission_end_time').prop('disabled', false); // Enable by default
            $('#mission_color_swatches .color-swatch').removeClass('selected');
            $(`.color-swatch[data-color="${defaultMissionColor}"]`).addClass('selected');
            $('#mission_color').val(defaultMissionColor);
        }

        // --- API COMMUNICATION HELPER ---
        async function apiCall(action, method = 'GET', data = null) {
            const options = { method };
            if (data && method !== 'GET') {
                options.headers = { 'Content-Type': 'application/json' };
                options.body = JSON.stringify(data);
            }

            let url = `${HANDLER_URL}?action=${action}`;
            if (method === 'GET' && data) {
                url += '&' + new URLSearchParams(data).toString();
            }

            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server error: ${response.status} - ${errorText}`);
                }
                const responseData = await response.json();
                if (responseData.status === 'error') {
                    throw new Error(responseData.message || 'Unknown API error.');
                }
                return responseData;
            } catch (error) {
                console.error(`Error during API call to ${action}:`, error);
                throw error; // Re-throw to be caught by the calling async function
            }
        }
    </script>
</body>
</html>
