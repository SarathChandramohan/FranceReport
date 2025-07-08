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
        body {
            background-color: #f5f5f7;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .planning-container {
            padding: 15px;
        }
        .date-navigator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background-color: #ffffff;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .date-navigator h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .mission-card {
            background-color: #ffffff;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 5px solid #007bff;
        }
        .mission-card .card-body {
            padding: 1.25rem;
        }
        .mission-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .mission-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .mission-meta i {
            margin-right: 8px;
        }
        .mission-details-list {
            padding-left: 0;
            list-style: none;
            font-size: 0.95rem;
        }
        .mission-details-list li {
            margin-bottom: 8px;
            display: flex;
            align-items: baseline;
        }
        .mission-details-list .icon {
            width: 25px;
            text-align: center;
            color: #007bff;
        }
        .placeholder {
            text-align: center;
            padding: 50px 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .placeholder i {
            font-size: 3rem;
            color: #e0e0e0;
        }
        .placeholder p {
            margin-top: 15px;
            font-size: 1.1rem;
            color: #6c757d;
        }
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="planning-container">
    <div class="date-navigator">
        <button class="btn btn-outline-secondary" id="prevDayBtn"><i class="fas fa-chevron-left"></i></button>
        <h4 id="currentDateDisplay"></h4>
        <button class="btn btn-outline-secondary" id="nextDayBtn"><i class="fas fa-chevron-right"></i></button>
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
    const dateDisplay = document.getElementById('currentDateDisplay');
    const dateInput = document.getElementById('currentDateInput');
    const planningList = document.getElementById('planning-list');
    const prevDayBtn = document.getElementById('prevDayBtn');
    const nextDayBtn = document.getElementById('nextDayBtn');

    const fp = flatpickr(dateDisplay, {
        locale: 'fr',
        dateFormat: "d M Y",
        defaultDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            const selectedDate = selectedDates[0];
            updateCurrentDate(selectedDate);
            fetchPlanningForDate(getFormattedDate(selectedDate));
        }
    });

    function getFormattedDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function updateCurrentDate(date) {
        fp.setDate(date, false); // Update flatpickr without triggering onChange
        dateDisplay.textContent = date.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        dateInput.value = getFormattedDate(date);
    }

    function changeDay(offset) {
        const currentDate = new Date(dateInput.value);
        currentDate.setDate(currentDate.getDate() + offset);
        updateCurrentDate(currentDate);
        fetchPlanningForDate(getFormattedDate(currentDate));
    }

    prevDayBtn.addEventListener('click', () => changeDay(-1));
    nextDayBtn.addEventListener('click', () => changeDay(1));

    function fetchPlanningForDate(date) {
        showLoadingState();
        $.ajax({
            url: 'planning-handler.php',
            type: 'GET',
            data: {
                action: 'get_user_planning', // This new action needs to be created
                date: date
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderPlanning(response.data);
                } else {
                    showErrorState(response.message);
                }
            },
            error: function() {
                showErrorState('Erreur de communication avec le serveur.');
            }
        });
    }

    function showLoadingState() {
        planningList.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Chargement...</span>
                </div>
            </div>`;
    }

    function showErrorState(message) {
        planningList.innerHTML = `
            <div class="placeholder">
                <i class="fas fa-exclamation-circle text-danger"></i>
                <p class="text-danger">${message || 'Une erreur est survenue.'}</p>
            </div>`;
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
            card.style.borderLeftColor = mission.color || '#007bff';

            const teamHtml = mission.assigned_user_names ?
                `<li><span class="icon"><i class="fas fa-users"></i></span> <span><strong>Équipe:</strong> ${mission.assigned_user_names}</span></li>` : '';

            const assetsHtml = mission.assigned_asset_names ?
                `<li><span class="icon"><i class="fas fa-tools"></i></span> <span><strong>Matériel:</strong> ${mission.assigned_asset_names}</span></li>` : '';
            
            const commentsHtml = mission.comments ?
                `<div class="mt-3"><p class="mb-1"><strong>Commentaires:</strong></p><p class="text-muted">${mission.comments.replace(/\n/g, '<br>')}</p></div>` : '';


            card.innerHTML = `
                <div class="card-body">
                    <h5 class="mission-title">${mission.mission_text}</h5>
                    <div class="mission-meta">
                        <span><i class="fas fa-clock"></i> ${mission.start_time ? mission.start_time.substring(0,5) : 'N/A'} - ${mission.end_time ? mission.end_time.substring(0,5) : 'N/A'}</span>
                        <span class="ml-3"><i class="fas fa-map-marker-alt"></i> ${mission.location || 'Non spécifié'}</span>
                    </div>
                    <ul class="mission-details-list">
                        ${teamHtml}
                        ${assetsHtml}
                    </ul>
                    ${commentsHtml}
                </div>
            `;
            planningList.appendChild(card);
        });
    }

    // Initial load
    const today = new Date();
    updateCurrentDate(today);
    fetchPlanningForDate(getFormattedDate(today));
});
</script>

</body>
</html>
