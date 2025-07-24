<?php
// seeplanning.php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Planning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Define Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* General Body Styles */
        body {
            background: linear-gradient(to bottom, #1E2C4A 0%, #1E2C4A 40%, #f4f6f8 40%, #f4f6f8 100%); /* Dark top, light bottom */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #333;
            min-height: 100vh; /* Ensure full height */
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        .planning-container {
            padding: 0 15px; /* Adjust padding for phone-like appearance */
            max-width: 450px; /* Set a max-width for phone-like feel */
            margin: 0 auto; /* Center the container */
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* Date Navigator Styles (Top Calendar Header) */
        .date-navigator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 15px; /* Increased padding */
            background-color: #1E2C4A; /* Dark blue background */
            border-radius: 0; /* No rounding at top */
            margin-bottom: 0; /* Remove bottom margin */
            color: white; /* White text */
            position: relative;
            z-index: 2; /* Above the content card */
        }
        .date-navigator .btn {
            background-color: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7); /* Lighter arrows */
            font-weight: 600;
            font-size: 1.2rem; /* Larger arrows */
            transition: all 0.2s ease-in-out;
            padding: 5px 10px;
        }
        .date-navigator .btn:hover {
            color: white;
            transform: scale(1.1);
        }
        .date-navigator h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            color: #C0C0C0; /* Slightly greyed out for year */
            display: flex;
            flex-direction: column; /* Stack year and month */
            align-items: center;
        }
        .date-navigator h4 .year-display {
            font-size: 0.9rem; /* Smaller year font */
            font-weight: 400;
            opacity: 0.8;
            margin-bottom: 2px;
        }
        .date-navigator h4 .month-display {
            font-size: 1.8rem; /* Larger month font */
            font-weight: 700;
            color: white; /* White for month */
            text-transform: uppercase;
        }
        .today-button {
            background-color: rgba(255, 255, 255, 0.15); /* Semi-transparent white */
            color: white;
            border-radius: 20px; /* Pill shape */
            padding: 5px 15px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        .today-button:hover {
            background-color: rgba(255, 255, 255, 0.25);
        }

        /* Content Card (White section below calendar header) */
        #content-card {
            background-color: #ffffff;
            border-top-left-radius: 30px; /* Large rounded top corners */
            border-top-right-radius: 30px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1); /* Shadow for overlap effect */
            padding: 25px;
            margin-top: -20px; /* Overlap effect */
            position: relative;
            z-index: 1; /* Below date navigator */
            flex-grow: 1; /* Take remaining space */
            display: flex;
            flex-direction: column;
        }

        /* Daily Calendar Grid (within date navigator - if needed, currently not used as per strict interpretation) */
        /* If a full calendar grid were implemented, styles similar to this would apply */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            color: white;
            padding: 10px 0;
        }
        .calendar-grid .day-label {
            font-size: 0.8rem;
            font-weight: 500;
            opacity: 0.7;
        }
        .calendar-grid .day-number {
            font-size: 1.1rem;
            font-weight: 600;
            padding: 5px 0;
            cursor: pointer;
        }
        .calendar-grid .day-number.selected {
            background-color: #FF5C5C; /* Highlight color for selected day */
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        /* Mission Card Styles */
        .mission-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .mission-header h5 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1d1d1f;
        }

        .mission-card {
            background-color: #ffffff;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); /* Lighter shadow */
            border: 1px solid #e9ecef;
            border-left: 5px solid; /* Color will be set inline */
            overflow: hidden;
            animation: fadeIn 0.5s ease-out forwards;
        }
        .mission-card-header-inner {
            padding: 15px 20px;
            background-color: #f8f9fa; /* Light background for header part of card */
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mission-title {
            font-size: 1.1rem; /* Slightly smaller title */
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        .mission-time {
            font-size: 0.85rem;
            font-weight: 500;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 8px; /* Space between clock icon and time */
        }
        .mission-time i {
            color: #007bff;
        }
        .mission-card-body {
            padding: 15px 20px;
        }
        .mission-detail-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px; /* Reduced margin */
            font-size: 0.9rem;
            color: #555;
        }
        .mission-detail-item:last-child {
            margin-bottom: 0;
        }
        .mission-detail-item .icon {
            font-size: 1rem;
            color: #007bff;
            width: 30px; /* Fixed width for alignment */
            flex-shrink: 0;
            padding-top: 2px;
            text-align: center;
        }
        .mission-detail-item span strong {
            color: #333;
            display: block; /* Ensure strong is on its own line */
            margin-bottom: 2px; /* Small space below label */
        }

        .mission-comments {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed #e0e0e0;
        }
        .mission-comments h6 {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        .mission-comments h6 i {
            color: #007bff;
            margin-right: 5px;
        }
        .mission-comments p {
            color: #666;
            font-size: 0.85rem;
            white-space: pre-wrap; /* Respects line breaks */
        }

        /* Placeholder and Loading Styles */
        .placeholder, .loading-spinner {
            text-align: center;
            padding: 40px 20px;
            background-color: transparent;
            border: 2px dashed #dbe1e8;
            border-radius: 12px;
            margin-top: 20px;
        }
        .placeholder i {
            font-size: 2.5rem; /* Slightly smaller icon for balance */
            color: #ced4da;
        }
        .placeholder p {
            margin-top: 12px;
            font-size: 1rem;
            font-weight: 500;
            color: #6c757d;
        }
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 150px;
        }
        .loading-spinner .spinner-border {
            width: 2.5rem; /* Slightly smaller spinner */
            height: 2.5rem;
        }

        /* Navbar adjustment for responsive behavior */
        .navbar {
            position: sticky;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 576px) {
            .planning-container {
                padding: 0 10px;
            }
            .date-navigator {
                padding: 15px 10px;
            }
            .date-navigator h4 .month-display {
                font-size: 1.5rem;
            }
            .date-navigator .btn {
                font-size: 1rem;
            }
            #content-card {
                padding: 20px 15px;
            }
            .mission-card-header-inner, .mission-card-body {
                padding: 12px 15px;
            }
            .mission-title {
                font-size: 1rem;
            }
            .mission-time {
                font-size: 0.8rem;
            }
            .mission-detail-item {
                font-size: 0.85rem;
            }
            .mission-detail-item .icon {
                width: 25px;
                font-size: 0.9rem;
            }
            .placeholder, .loading-spinner {
                padding: 30px 15px;
            }
            .placeholder i {
                font-size: 2rem;
            }
            .placeholder p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="planning-container">
    <div class="date-navigator">
        <button class="btn" id="prevDayBtn"><i class="fas fa-chevron-left"></i></button>
        <h4 id="currentDateDisplay">
            <span class="year-display"></span>
            <span class="month-display"></span>
        </h4>
        <button class="btn" id="nextDayBtn"><i class="fas fa-chevron-right"></i></button>
        <button class="today-button" id="todayBtn">Aujourd'hui</button>
    </div>
    <input type="hidden" id="currentDateInput">

    <div id="content-card">
        <div class="mission-header">
            <h5 id="selectedDayName"></h5> </div>
        <div id="planning-list">
            </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- ELEMENT SELECTORS ---
    const dateDisplay = document.getElementById('currentDateDisplay');
    const yearDisplay = dateDisplay.querySelector('.year-display');
    const monthDisplay = dateDisplay.querySelector('.month-display');
    const selectedDayName = document.getElementById('selectedDayName'); // New element for "Monday, 28, October"
    const dateInput = document.getElementById('currentDateInput');
    const planningList = document.getElementById('planning-list');
    const prevDayBtn = document.getElementById('prevDayBtn');
    const nextDayBtn = document.getElementById('nextDayBtn');
    const todayBtn = document.getElementById('todayBtn'); // New Today button

    // --- INITIALIZE DATE PICKER ---
    const fp = flatpickr(dateDisplay, {
        locale: 'fr',
        dateFormat: "Y-m-d", // Internal format
        defaultDate: "today",
        onChange: function(selectedDates) {
            updateCurrentDate(selectedDates[0]);
            fetchPlanningForDate(getFormattedDate(selectedDates[0]));
        },
        // To only show month and year in the picker, but still allow navigation
        // This won't show a full calendar grid like in the image, but allows selecting month/year
        // from the flatpickr interface.
        // If a full grid is strictly needed, a library like FullCalendar would be required.
        altInput: true, // Use a hidden input for the actual date, and display a formatted date
        altFormat: "Y F", // Display format: e.g., "2025 July"
        allowInput: true // Allow direct typing in the alt input if needed
    });

    // --- DATE HELPER FUNCTIONS ---
    function getFormattedDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function updateCurrentDate(date) {
        fp.setDate(date, false); // Update flatpickr's internal date without triggering onChange
        
        // Update year and month display in header
        yearDisplay.textContent = date.getFullYear();
        monthDisplay.textContent = date.toLocaleDateString('fr-FR', { month: 'long' });

        // Update the day name display (e.g., "Monday, 28, October")
        selectedDayName.textContent = date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

        dateInput.value = getFormattedDate(date);
    }

    function changeDay(offset) {
        const currentDate = new Date(dateInput.value + 'T12:00:00'); // Use noon to avoid timezone shift issues
        currentDate.setDate(currentDate.getDate() + offset);
        updateCurrentDate(currentDate);
        fetchPlanningForDate(getFormattedDate(currentDate));
    }

    // --- EVENT LISTENERS ---
    prevDayBtn.addEventListener('click', () => changeDay(-1));
    nextDayBtn.addEventListener('click', () => changeDay(1));
    todayBtn.addEventListener('click', () => {
        const today = new Date();
        updateCurrentDate(today);
        fetchPlanningForDate(getFormattedDate(today));
    });

    // --- DATA FETCHING ---
    function fetchPlanningForDate(date) {
        showLoadingState();
        $.ajax({
            url: 'seeplanning-handler.php',
            type: 'GET',
            data: { action: 'get_user_planning', date: date },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderPlanning(response.data);
                } else {
                    showErrorState(response.message);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Erreur de communication.';
                showErrorState(errorMsg);
            }
        });
    }

    // --- UI RENDERING FUNCTIONS ---
    function showLoadingState() {
        planningList.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Chargement du planning...</p>
            </div>`;
    }

    function showErrorState(message) {
        planningList.innerHTML = `
            <div class="placeholder">
                <i class="fas fa-exclamation-circle text-danger"></i>
                <p class="text-danger">${escapeHtml(message) || 'Une erreur est survenue.'}</p>
            </div>`;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function renderPlanning(missions) {
        if (!missions || missions.length === 0) {
            planningList.innerHTML = `
                <div class="placeholder">
                    <i class="fas fa-calendar-check"></i>
                    <p>Aucune mission planifiée pour ce jour.</p>
                </div>`;
            return;
        }

        planningList.innerHTML = ''; // Clear previous content
        missions.forEach(mission => {
            const card = document.createElement('div');
            card.className = 'mission-card';

            let uniqueTeamNames = '';
            if (mission.assigned_user_names) {
                const namesArray = mission.assigned_user_names.split(',').map(name => name.trim()).filter(Boolean);
                uniqueTeamNames = [...new Set(namesArray)].join(', ');
            }

            const locationHtml = mission.location ? `
                <div class="mission-detail-item">
                    <i class="fas fa-map-marker-alt icon"></i>
                    <span><strong>Lieu :</strong> ${escapeHtml(mission.location)}</span>
                </div>` : '';

            const teamHtml = uniqueTeamNames ? `
                <div class="mission-detail-item">
                    <i class="fas fa-users icon"></i>
                    <span><strong>Équipe :</strong> ${escapeHtml(uniqueTeamNames)}</span>
                </div>` : '';

            const assetsHtml = mission.assigned_asset_names ? `
                <div class="mission-detail-item">
                    <i class="fas fa-tools icon"></i>
                    <span><strong>Matériel :</strong> ${escapeHtml(mission.assigned_asset_names)}</span>
                </div>` : '';
            
            const commentsHtml = mission.comments ? `
                <div class="mission-comments">
                    <h6><i class="far fa-comment-dots"></i> Commentaires</h6>
                    <p>${escapeHtml(mission.comments).replace(/\n/g, '<br>')}</p>
                </div>` : '';

            card.innerHTML = `
                <div class="mission-card-header-inner" style="border-left-color: ${escapeHtml(mission.color) || '#007bff'};">
                    <h5 class="mission-title">${escapeHtml(mission.mission_text)}</h5>
                    <div class="mission-time">
                        <i class="far fa-clock"></i>
                        <span>${mission.start_time ? mission.start_time.substring(0,5) : 'N/A'} - 
                        ${mission.end_time ? mission.end_time.substring(0,5) : 'N/A'}</span>
                    </div>
                </div>
                <div class="mission-card-body">
                    ${locationHtml}
                    ${teamHtml}
                    ${assetsHtml}
                    ${commentsHtml}
                </div>
            `;
            planningList.appendChild(card);
        });
    }

    // --- INITIAL LOAD ---
    const today = new Date();
    updateCurrentDate(today);
    fetchPlanningForDate(getFormattedDate(today));
});
</script>

</body>
</html>
