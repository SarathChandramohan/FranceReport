<?php
// planning.php (Fixed)

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
            padding-right: 80px; /* Space for buttons */
        }
        .mission-card .mission-meta {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 5px;
        }
        .mission-card .mission-meta i {
            margin-right: 5px;
        }
         .mission-card .mission-comment {
            font-size: 0.8em;
            color: #555;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
            white-space: pre-wrap; /* Preserve line breaks */
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
            transition: color 0.2s;
        }
        .mission-actions button:hover { color: #333; }
        .mission-actions .validate-btn.active { color: #28a745; }
        .mission-actions .edit-btn:hover { color: #007bff; }
        .mission-actions .delete-btn:hover { color: #d9534f; }


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
        #missionFormModal .modal-header .close { color: white; opacity: 0.8; font-size: 1.5rem; }
        #missionFormModal .color-swatch {
            width: 30px; height: 30px; border-radius: 50%;
            cursor: pointer; border: 2px solid transparent;
            transition: all 0.2s ease; margin-right: 8px;
        }
        #missionFormModal .color-swatch.selected {
            border-color: #333;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px #333;
        }
        #missionFormModal .btn-group-sm .btn { font-size: 0.85rem; padding: .3rem .6rem; border-radius: 5px; }
        #missionFormModal .btn-outline-purple { color: #6f42c1; border-color: #6f42c1; }
        #missionFormModal .btn-outline-purple:hover { background-color: #6f42c1; color: white; }

        /* Loading Overlay */
        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.8); z-index: 1060;
            display: flex; justify-content: center; align-items: center;
        }
        .spinner-border { width: 3rem; height: 3rem; }

        /* Media Queries for Responsiveness */
        @media (max-width: 991px) {
            .main-container { flex-direction: column; padding-top: 0; }
            .workers-list-col { width: 100%; position: static; margin-right: 0; margin-bottom: 20px; max-height: none; }
            .workers-list-card .card-body { max-height: 300px; }
            .daily-planning-col { padding-right: 0; }
            .daily-planning-container { flex-wrap: wrap; justify-content: center; }
            .day-column { width: 95%; max-width: 350px; margin-bottom: 15px; }
        }
        @media (max-width: 575px) {
            .container-fluid { padding-left: 10px; padding-right: 10px; }
            .card { padding: 15px; }
            .worker-item { font-size: 0.9em; }
            .day-column { width: 100%; }
        }
    </style>
</head>
<body class="body-with-staff-nav">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid main-container">
        <!-- Left Column: Workers List -->
        <div class="workers-list-col">
            <div class="card workers-list-card">
                <div class="card-header bg-primary text-white"><i class="fas fa-users mr-2"></i>Ouvriers Disponibles</div>
                <div class="card-body">
                    <div id="workerList" class="list-group list-group-flush">
                        <div class="text-center text-muted p-3">Chargement des ouvriers...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Weekly Planning -->
        <div class="daily-planning-col">
            <div class="card">
                <div class="card-header"><i class="fas fa-calendar-alt mr-2"></i>Planning Hebdomadaire</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button class="btn btn-outline-secondary btn-sm" id="prevWeekBtn"><i class="fas fa-arrow-left"></i> Semaine Précédente</button>
                        <h2 class="h4 mb-0" id="currentWeekRange"></h2>
                        <button class="btn btn-outline-secondary btn-sm" id="nextWeekBtn">Semaine Prochaine <i class="fas fa-arrow-right"></i></button>
                    </div>
                    <div id="dailyPlanningContainer" class="daily-planning-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mission Form Modal -->
    <div class="modal fade" id="missionFormModal" tabindex="-1" aria-labelledby="missionFormModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="missionFormModalLabel">Nouvelle Mission</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="missionForm">
                    <input type="hidden" id="mission_date_form" name="mission_date">
                    <input type="hidden" id="original_mission_id_form" name="original_mission_id">
                    <input type="hidden" id="is_group_mission_edit" name="is_group_mission_edit" value="0">
                    <div class="modal-body">
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
                                <label class="btn btn-sm btn-outline-success"><input type="radio" name="shift_type" value="matin" autocomplete="off"> Matin</label>
                                <label class="btn btn-sm btn-outline-info"><input type="radio" name="shift_type" value="apres-midi" autocomplete="off"> Après-midi</label>
                                <label class="btn btn-sm btn-outline-purple"><input type="radio" name="shift_type" value="nuit" autocomplete="off"> Nuit</label>
                                <label class="btn btn-sm btn-outline-secondary"><input type="radio" name="shift_type" value="repos" autocomplete="off"> Repos</label>
                                <label class="btn btn-sm btn-outline-dark"><input type="radio" name="shift_type" value="custom" autocomplete="off"> Personnalisé</label>
                            </div>
                            <input type="hidden" id="selected_shift_type" name="selected_shift_type">
                        </div>
                        <div class="form-group">
                            <label for="mission_color">Couleur</label>
                            <div id="mission_color_swatches" class="d-flex flex-wrap"></div>
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

    <!-- Loading Overlay -->
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
        const loggedInUserId = <?php echo $user_id_logged_in; ?>;

        // --- UTILITY FUNCTIONS ---
        function showLoading(show) { $('#loadingOverlay').toggle(show); }
        function showModalError(message) {
            $('#modal_error_message').text(message).show();
            setTimeout(() => $('#modal_error_message').fadeOut(500), 5000);
        }
        function formatDateToYMD(date) { return date.toISOString().split('T')[0]; }
        function formatTimeHM(timeStr) { return timeStr ? String(timeStr).substring(0, 5) : ''; }
        function getShiftLabel(shiftType) {
            const labels = { matin: 'Matin', 'apres-midi': 'Après-midi', nuit: 'Nuit', repos: 'Repos', custom: 'Personnalisé' };
            return labels[shiftType] || shiftType;
        }

        // --- INITIALIZATION ---
        $(document).ready(function() {
            setWeekRange(new Date());
            loadStaffUsers();
            attachEventListeners();
            renderColorSwatches();
        });

        function attachEventListeners() {
            $('#prevWeekBtn').on('click', () => changeWeek(-7));
            $('#nextWeekBtn').on('click', () => changeWeek(7));

            $('#missionFormModal').on('hidden.bs.modal', resetMissionForm);
            $('#missionForm').on('submit', handleMissionFormSubmit);
            $('#deleteMissionBtn').on('click', handleDeleteMissionFromModal);

            $('#shift_type_buttons input[type="radio"]').on('change', function() {
                const selectedShift = $(this).val();
                $('#selected_shift_type').val(selectedShift);
                const isTimeDisabled = (selectedShift === 'repos');
                $('#mission_start_time, #mission_end_time').prop('disabled', isTimeDisabled).val(isTimeDisabled ? '' : $('#mission_start_time').val());
            });

            $('#dailyPlanningContainer').on('click', '.add-mission-btn', function() {
                const dateKey = $(this).data('date');
                openMissionFormForCreate(dateKey);
            });

            // --- FIXED: Event delegation for mission card actions ---
            $('#dailyPlanningContainer').on('click', '.edit-btn', function(e) {
                e.stopPropagation();
                const card = $(this).closest('.mission-card');
                openMissionFormForEdit(card.data('date'), card.data('original-mission-id'), true);
            });

            $('#dailyPlanningContainer').on('click', '.delete-btn', function(e) {
                e.stopPropagation();
                const card = $(this).closest('.mission-card');
                handleDeleteMissionFromCard(card.data('date'), card.data('original-mission-id'));
            });

            $('#dailyPlanningContainer').on('click', '.remove-worker-btn', function(e) {
                e.stopPropagation();
                const workerId = $(this).data('worker-id');
                const card = $(this).closest('.mission-card');
                removeWorkerFromMission(workerId, card.data('date'), card.data('original-mission-id'));
            });

            $('#dailyPlanningContainer').on('click', '.validate-btn', function(e) {
                e.stopPropagation();
                const card = $(this).closest('.mission-card');
                toggleMissionValidation(card.data('date'), card.data('original-mission-id'));
            });

             $('#mission_color_swatches').on('click', '.color-swatch', function() {
                $('#mission_color_swatches .color-swatch').removeClass('selected');
                $(this).addClass('selected');
                $('#mission_color').val($(this).data('color'));
            });
        }

        function renderColorSwatches() {
            const swatchesContainer = $('#mission_color_swatches');
            swatchesContainer.empty();
            predefinedColors.forEach(color => {
                swatchesContainer.append(`<div class="color-swatch" style="background-color: ${color};" data-color="${color}" title="${color}"></div>`);
            });
            $(`.color-swatch[data-color="${defaultMissionColor}"]`).addClass('selected');
        }

        // --- WEEK NAVIGATION ---
        function setWeekRange(date) {
            currentWeekStart = new Date(date);
            currentWeekStart.setDate(currentWeekStart.getDate() - (currentWeekStart.getDay() === 0 ? 6 : currentWeekStart.getDay() - 1)); // Monday as start of week
            const weekEnd = new Date(currentWeekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);
            const options = { day: 'numeric', month: 'long', year: 'numeric' };
            $('#currentWeekRange').text(`${currentWeekStart.toLocaleDateString('fr-FR', options)} - ${weekEnd.toLocaleDateString('fr-FR', options)}`);
            loadPlanningData();
        }

        function changeWeek(days) {
            currentWeekStart.setDate(currentWeekStart.getDate() + days);
            setWeekRange(currentWeekStart);
        }

        // --- DATA LOADING ---
        async function loadPlanningData() {
            showLoading(true);
            const endDate = new Date(currentWeekStart);
            endDate.setDate(endDate.getDate() + 6);

            try {
                const missionsData = await apiCall('get_assignments', 'GET', { start: formatDateToYMD(currentWeekStart), end: formatDateToYMD(endDate) });
                missions = {};
                (missionsData.data.missions || []).forEach(mission => {
                    const dateKey = mission.start_date_group;
                    if (!missions[dateKey]) missions[dateKey] = [];
                    missions[dateKey].push(mission);
                });

                const allAssignments = await apiCall('get_all_assignments_for_workers');
                const workerAssignments = {};
                (allAssignments.data.assignments || []).forEach(a => {
                    const dateKey = formatDateToYMD(new Date(a.assignment_date));
                    if (!workerAssignments[a.assigned_user_id]) workerAssignments[a.assigned_user_id] = {};
                    workerAssignments[a.assigned_user_id][dateKey] = a.title || getShiftLabel(a.shift_type);
                });
                staffUsers.forEach(user => { user.assignedMissions = workerAssignments[user.user_id] || {}; });

                renderPlanning();
                renderWorkerList();
            } catch (error) {
                alert('Erreur lors du chargement des données du planning.');
            } finally {
                showLoading(false);
            }
        }
        
        async function loadStaffUsers() {
            try {
                const response = await apiCall('get_staff_users');
                staffUsers = response.data.users || [];
                renderWorkerList();
            } catch (error) {
                alert('Erreur lors du chargement des ouvriers.');
            }
        }

        // --- RENDERING ---
        function renderWorkerList() {
            const listEl = $('#workerList');
            listEl.empty();
            if (staffUsers.length === 0) {
                listEl.append('<div class="text-center text-muted p-3">Aucun ouvrier disponible.</div>');
                return;
            }
            const displayedDates = Object.keys(missions);

            staffUsers.forEach(ouvrier => {
                const assignedOnDisplayedWeek = Object.keys(ouvrier.assignedMissions).some(dateKey => displayedDates.includes(dateKey));
                const item = $(`
                    <div class="worker-item ${assignedOnDisplayedWeek ? 'assigned' : ''}" draggable="${!assignedOnDisplayedWeek}" data-id="${ouvrier.user_id}" data-nom="${ouvrier.nom}" data-prenom="${ouvrier.prenom}">
                        <div class="font-weight-bold">${ouvrier.prenom} ${ouvrier.nom}</div>
                        ${assignedOnDisplayedWeek ? `<div class="assigned-info">🚫 Déjà affecté cette semaine</div>` : ''}
                    </div>
                `);
                if (!assignedOnDisplayedWeek) {
                    item.on('dragstart', handleDragStart);
                }
                listEl.append(item);
            });
        }

        function renderPlanning() {
            const container = $('#dailyPlanningContainer');
            container.empty();
            let currentDate = new Date(currentWeekStart);
            for (let i = 0; i < 7; i++) {
                const dateKey = formatDateToYMD(currentDate);
                const dayColumn = $(`
                    <div class="day-column">
                        <div class="day-header">
                            <h3>${currentDate.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'short' })}</h3>
                            <button class="btn btn-sm btn-success add-mission-btn" data-date="${dateKey}"><i class="fas fa-plus"></i> Nouvelle Mission</button>
                        </div>
                        <div class="day-content" data-date="${dateKey}"></div>
                    </div>
                `);
                container.append(dayColumn);
                renderMissionsForDay(dateKey);
                dayColumn.find('.day-content').on('dragover', handleDragOver).on('drop', handleDayDrop);
                currentDate.setDate(currentDate.getDate() + 1);
            }
        }

        function renderMissionsForDay(dateKey) {
            const dayContent = $(`.day-content[data-date="${dateKey}"]`);
            dayContent.empty();
            const missionsForDay = missions[dateKey] || [];

            if (missionsForDay.length === 0) {
                 dayContent.append('<div class="text-center text-muted p-5">Aucune mission pour ce jour.</div>');
                 return;
            }

            missionsForDay.sort((a,b) => (a.start_time || '99:99').localeCompare(b.start_time || '99:99')).forEach(mission => {
                const missionCard = $(`
                    <div class="mission-card ${mission.is_validated ? 'validated' : ''}" data-date="${dateKey}" data-original-mission-id="${mission.representative_assignment_id}" style="border-left-color: ${mission.color || defaultMissionColor};">
                        <div class="mission-actions">
                            <button class="validate-btn ${mission.is_validated ? 'active' : ''}" title="${mission.is_validated ? 'Dévalider' : 'Valider'}"><i class="fas fa-check"></i></button>
                            <button class="edit-btn" title="Modifier"><i class="fas fa-edit"></i></button>
                            <button class="delete-btn" title="Supprimer"><i class="fas fa-trash-alt"></i></button>
                        </div>
                        <div class="mission-title">${mission.title}</div>
                        <div class="mission-meta">
                            ${mission.start_time || mission.end_time ? `<i class="fas fa-clock"></i> ${formatTimeHM(mission.start_time)} - ${formatTimeHM(mission.end_time)}` : `<i class="fas fa-tags"></i> ${getShiftLabel(mission.shift_type)}`}
                            ${mission.location ? `<br><i class="fas fa-map-marker-alt"></i> ${mission.location}` : ''}
                        </div>
                        ${mission.comment ? `<div class="mission-comment"><i class="fas fa-info-circle"></i> ${mission.comment}</div>` : ''}
                        <ul class="worker-assigned-list"></ul>
                        ${(!mission.user_ids_list || mission.user_ids_list.length === 0) ? '<div class="drag-worker-placeholder">Glissez un ouvrier ici</div>' : ''}
                    </div>
                `);

                const workerList = missionCard.find('.worker-assigned-list');
                if (mission.user_ids_list) {
                    (mission.user_names_list.split(', ') || []).forEach((name, index) => {
                        const workerId = mission.user_ids_list.split(',')[index];
                        workerList.append(`<li><span>${name}</span><button class="remove-worker-btn" data-worker-id="${workerId}" title="Retirer"><i class="fas fa-times-circle"></i></button></li>`);
                    });
                }
                dayContent.append(missionCard);
            });
        }

        // --- DRAG AND DROP HANDLERS ---
        function handleDragStart(e) {
            draggedOuvrier = $(e.currentTarget).data();
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        }

        function handleDragOver(e) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'move'; }

        async function handleDayDrop(e) {
            e.preventDefault();
            if (!draggedOuvrier) return;

            const dateKey = $(e.currentTarget).data('date');
            const targetMissionCard = $(e.target).closest('.mission-card');
            
            showLoading(true);
            try {
                if (targetMissionCard.length) { // Dropped on existing mission
                    await apiCall('assign_worker_to_mission', 'POST', {
                        worker_id: draggedOuvrier.id,
                        mission_date: dateKey,
                        original_mission_id: targetMissionCard.data('original-mission-id')
                    });
                } else { // --- FIXED: Dropped on empty day, create new mission for the worker ---
                    const newMissionData = {
                        title: 'Nouvelle Mission',
                        mission_date: dateKey,
                        shift_type: 'custom',
                        start_time: '08:00',
                        end_time: '17:00',
                        color: defaultMissionColor,
                        assigned_user_ids: [draggedOuvrier.id] // CRITICAL FIX
                    };
                    await apiCall('save_assignment', 'POST', newMissionData);
                }
                await loadPlanningData();
            } catch (error) {
                alert(error.message || `Erreur lors de l'affectation de ${draggedOuvrier.prenom}.`);
            } finally {
                draggedOuvrier = null;
                showLoading(false);
            }
        }

        // --- MISSION FORM & ACTIONS ---
        function openMissionFormForCreate(dateKey) {
            resetMissionForm();
            $('#missionFormModalLabel').text('Nouvelle Mission');
            $('#mission_date_form').val(dateKey);
            $('#saveMissionBtn').text('Créer Mission');
            $('#deleteMissionBtn').hide();
            $('#missionFormModal').modal('show');
        }

        async function openMissionFormForEdit(dateKey, originalMissionId) {
            resetMissionForm();
            showLoading(true);
            try {
                const response = await apiCall('get_mission_details_for_edit', 'GET', { mission_date: dateKey, original_mission_id: originalMissionId });
                const mission = response.data.mission;
                if (mission) {
                    $('#missionFormModalLabel').text('Modifier Mission');
                    $('#mission_date_form').val(dateKey);
                    $('#original_mission_id_form').val(originalMissionId);
                    $('#is_group_mission_edit').val('1');
                    $('#mission_title').val(mission.title);
                    $('#mission_start_time').val(formatTimeHM(mission.start_time));
                    $('#mission_end_time').val(formatTimeHM(mission.end_time));
                    $('#mission_location').val(mission.location);
                    $('#mission_comment').val(mission.comment); // Changed from mission_text

                    $(`#shift_type_buttons input[value="${mission.shift_type}"]`).prop('checked', true).closest('label').addClass('active');
                    $('#selected_shift_type').val(mission.shift_type);
                    $('#mission_start_time, #mission_end_time').prop('disabled', mission.shift_type === 'repos');

                    $('#mission_color_swatches .color-swatch').removeClass('selected');
                    $(`.color-swatch[data-color="${mission.color}"]`).addClass('selected').trigger('click');

                    $('#saveMissionBtn').text('Modifier Mission');
                    $('#deleteMissionBtn').show();
                    $('#missionFormModal').modal('show');
                }
            } catch (error) { alert('Impossible de charger les détails de la mission.'); } finally { showLoading(false); }
        }
        
        async function handleMissionFormSubmit(e) {
            e.preventDefault();
            showLoading(true);
            
            const formData = {
                title: $('#mission_title').val(),
                mission_date: $('#mission_date_form').val(),
                start_time: $('#mission_start_time').val() || null,
                end_time: $('#mission_end_time').val() || null,
                location: $('#mission_location').val() || null,
                comment: $('#mission_comment').val() || null,
                shift_type: $('#selected_shift_type').val(),
                color: $('#mission_color').val(),
                original_mission_id: $('#original_mission_id_form').val() || null,
                is_group_edit: $('#is_group_mission_edit').val() === '1'
            };

            if (!formData.title || !formData.shift_type) {
                showModalError('Titre et type de service sont requis.');
                showLoading(false);
                return;
            }

            try {
                const response = await apiCall('save_assignment', 'POST', formData);
                alert(response.message);
                $('#missionFormModal').modal('hide');
                await loadPlanningData();
            } catch (error) { showModalError(error.message); } finally { showLoading(false); }
        }

        function handleDeleteMissionFromCard(missionDate, originalMissionId) {
             if (!confirm('Supprimer cette mission pour tous les ouvriers affectés?')) return;
             deleteMissionGroup(missionDate, originalMissionId);
        }

        function handleDeleteMissionFromModal() {
            if (!confirm('Supprimer cette mission pour tous les ouvriers affectés? Cette action est irréversible.')) return;
            const missionDate = $('#mission_date_form').val();
            const originalMissionId = $('#original_mission_id_form').val();
            deleteMissionGroup(missionDate, originalMissionId);
            $('#missionFormModal').modal('hide');
        }

        async function deleteMissionGroup(missionDate, originalMissionId) {
            showLoading(true);
            try {
                const response = await apiCall('delete_assignment', 'POST', { mission_date: missionDate, original_mission_id: originalMissionId, is_group_delete: true });
                alert(response.message);
                await loadPlanningData();
            } catch (error) { alert(error.message); } finally { showLoading(false); }
        }

        async function removeWorkerFromMission(workerId, missionDate, originalMissionId) {
            if (!confirm('Retirer cet ouvrier de la mission?')) return;
            showLoading(true);
            try {
                const response = await apiCall('remove_worker_from_assignment', 'POST', { worker_id: workerId, mission_date: missionDate, original_mission_id: originalMissionId });
                alert(response.message);
                await loadPlanningData();
            } catch (error) { alert(error.message); } finally { showLoading(false); }
        }

        async function toggleMissionValidation(missionDate, originalMissionId) {
            showLoading(true);
            try {
                const response = await apiCall('toggle_mission_validation', 'POST', { mission_date: missionDate, original_mission_id: originalMissionId });
                await loadPlanningData();
            } catch (error) { alert(error.message); } finally { showLoading(false); }
        }

        function resetMissionForm() {
            $('#missionForm')[0].reset();
            $('#missionFormModalLabel').text('Nouvelle Mission');
            $('#original_mission_id_form, #mission_date_form, #selected_shift_type').val('');
            $('#is_group_mission_edit').val('0');
            $('#modal_error_message').hide();
            $('#saveMissionBtn').text('Créer Mission');
            $('#deleteMissionBtn').hide();
            $('#shift_type_buttons label').removeClass('active');
            $('#mission_start_time, #mission_end_time').prop('disabled', false);
            $('#mission_color_swatches .color-swatch').removeClass('selected');
            $(`.color-swatch[data-color="${defaultMissionColor}"]`).addClass('selected');
            $('#mission_color').val(defaultMissionColor);
        }

        // --- API COMMUNICATION HELPER ---
        async function apiCall(action, method = 'GET', data = null) {
            const options = { method };
            let url = `${HANDLER_URL}?action=${action}`;

            if (data) {
                if (method === 'GET') {
                    url += '&' + new URLSearchParams(data).toString();
                } else {
                    options.headers = { 'Content-Type': 'application/json' };
                    options.body = JSON.stringify(data);
                }
            }

            try {
                const response = await fetch(url, options);
                const responseData = await response.json();
                if (!response.ok || responseData.status === 'error') {
                    throw new Error(responseData.message || `Server error: ${response.status}`);
                }
                return responseData;
            } catch (error) {
                console.error(`API call to ${action} failed:`, error);
                throw error;
            }
        }
    </script>
</body>
</html>
