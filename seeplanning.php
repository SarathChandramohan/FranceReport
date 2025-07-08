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
            background-color: #f4f6f8; /* Lighter, cleaner background */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #333;
        }
        .planning-container {
            padding: 15px;
            max-width: 800px; /* Set a max-width for better readability on desktop */
            margin: 0 auto;
        }

        /* Date Navigator Styles */
        .date-navigator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background-color: #ffffff;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        }
        .date-navigator .btn {
            background-color: #eef2f7;
            border: none;
            color: #555;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }
        .date-navigator .btn:hover {
            background-color: #007bff;
            color: white;
            transform: scale(1.05);
        }
        .date-navigator h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            color: #007bff;
        }

        /* Mission Card Styles */
        .mission-card {
            background-color: #ffffff;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
            /* Animation applied here */
            animation: fadeIn 0.5s ease-out forwards;
        }
        .mission-card-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            border-left: 5px solid; /* Color will be set inline */
        }
        .mission-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
        }
        .mission-time {
            font-size: 0.9rem;
            font-weight: 500;
            color: #6c757d;
            margin-top: 5px;
        }
        .mission-card-body {
            padding: 20px;
        }
        .mission-detail-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        .mission-detail-item .icon {
            font-size: 1.1rem;
            color: #007bff;
            width: 35px;
            flex-shrink: 0;
            padding-top: 3px;
        }
        .mission-detail-item span {
            color: #555;
        }
        .mission-detail-item strong {
            color: #333;
            display: block;
            margin-bottom: 2px;
        }

        .mission-comments {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #e0e0e0;
        }
        .mission-comments h6 {
            font-weight: 600;
            color: #333;
        }
        .mission-comments p {
            color: #666;
            font-size: 0.9rem;
            white-space: pre-wrap; /* Respects line breaks */
        }

        /* Placeholder and Loading Styles */
        .placeholder, .loading-spinner {
            text-align: center;
            padding: 50px 20px;
            background-color: transparent;
            border: 2px dashed #dbe1e8;
            border-radius: 12px;
        }
        .placeholder i {
            font-size: 3rem;
            color: #ced4da;
        }
        .placeholder p {
            margin-top: 15px;
            font-size: 1.1rem;
            font-weight: 500;
            color: #6c757d;
        }
        .loading-spinner .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 576px) {
            .planning-container {
                padding: 10px;
            }
            .date-navigator {
                padding: 10px;
            }
            .date-navigator h4 {
                font-size: 1rem;
            }
            .date-navigator .btn {
                padding: 0.375rem 0.75rem;
            }
            .mission-card-header, .mission-card-body {
                padding: 15px;
            }
            .mission-title {
                font-size: 1.05rem;
            }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="planning-container">
    <div class="date-navigator">
        <button class="btn btn-sm" id="prevDayBtn"><i class="fas fa-chevron-left"></i></button>
        <h4 id="currentDateDisplay"></h4>
        <button class="btn btn-sm" id="nextDayBtn"><i class="fas fa-chevron-right"></i></button>
    </div>
    <input type="hidden" id="currentDateInput">

    <div id="planning-list">
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
    const dateInput = document.getElementById('currentDateInput');
    const planningList = document.getElementById('planning-list');
    const prevDayBtn = document.getElementById('prevDayBtn');
    const nextDayBtn = document.getElementById('nextDayBtn');

    // --- INITIALIZE DATE PICKER ---
    const fp = flatpickr(dateDisplay, {
        locale: 'fr',
        dateFormat: "d M Y",
        defaultDate: "today",
        onChange: function(selectedDates) {
            updateCurrentDate(selectedDates[0]);
            fetchPlanningForDate(getFormattedDate(selectedDates[0]));
        }
    });

    // --- DATE HELPER FUNCTIONS ---
    function getFormattedDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function updateCurrentDate(date) {
        fp.setDate(date, false);
        dateDisplay.textContent = date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
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

            const locationHtml = mission.location ? `
                <div class="mission-detail-item">
                    <i class="fas fa-map-marker-alt icon"></i>
                    <span><strong>Lieu :</strong> ${escapeHtml(mission.location)}</span>
                </div>` : '';

            const teamHtml = mission.assigned_user_names ? `
                <div class="mission-detail-item">
                    <i class="fas fa-users icon"></i>
                    <span><strong>Équipe :</strong> ${escapeHtml(mission.assigned_user_names)}</span>
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
                <div class="mission-card-header" style="border-left-color: ${escapeHtml(mission.color) || '#007bff'};">
                    <h5 class="mission-title">${escapeHtml(mission.mission_text)}</h5>
                    <div class="mission-time">
                        <i class="far fa-clock"></i>
                        ${mission.start_time ? mission.start_time.substring(0,5) : 'N/A'} - 
                        ${mission.end_time ? mission.end_time.substring(0,5) : 'N/A'}
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
