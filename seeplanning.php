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
    <style>
        /* --- General Theme & Animations --- */
        :root {
            --primary-bg: #3c8dbc;  /* Matching navbar blue */
            --secondary-bg: #ecf0f5;/* Light grey page background */
            --content-bg: #ffffff; /* White content panels */
            --primary-text: #ffffff;
            --secondary-text: #333333;
            --accent-color: #367fa9; /* A slightly darker blue for highlights */
            --border-color: #ddd;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Base & Layout --- */
        body {
            background-color: var(--secondary-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .content-wrapper {
            padding: 15px;
            animation: fadeIn 0.5s ease-out;
        }
        
        .planning-container {
            display: flex;
            flex-direction: column;
            background-color: var(--content-bg);
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            height: calc(100vh - 100px); /* Adjust based on navbar/footer height */
            min-height: 500px;
        }

        /* --- Calendar Panel --- */
        .calendar-panel {
            background-color: var(--primary-bg);
            color: var(--primary-text);
            padding: 20px 15px;
            flex-shrink: 0;
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .month-header h4 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .month-header .year-display {
             font-weight: 300;
             opacity: 0.9;
        }

        .nav-arrow {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.3rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .nav-arrow:hover { color: white; }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            text-align: center;
        }

        .calendar-grid .day-name {
            font-size: 0.8rem;
            font-weight: 600;
            opacity: 0.7;
            text-transform: uppercase;
        }

        .calendar-grid .day-cell {
            font-size: 0.9rem;
            padding: 8px 0;
            cursor: pointer;
            border-radius: 50%;
            transition: background-color 0.2s, color 0.2s;
            position: relative;
        }
        .day-cell.other-month { opacity: 0.3; pointer-events: none; }
        .day-cell.today { font-weight: 700; border: 1px solid rgba(255,255,255,0.6); }
        .day-cell.selected { background-color: var(--content-bg); color: var(--secondary-text); font-weight: 700; }
        .day-cell.has-mission::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background-color: var(--accent-color);
        }
        .day-cell.selected.has-mission::after { background-color: var(--primary-bg); }

        /* --- Details Panel (Missions) --- */
        .details-panel {
            background-color: var(--content-bg);
            padding: 20px;
            flex-grow: 1;
            overflow-y: auto;
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .details-header h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary-text);
            margin: 0;
        }

        .planning-list { list-style: none; padding: 0; }
        .planning-item { display: flex; align-items: flex-start; margin-bottom: 25px; animation: fadeIn 0.4s ease-out; }

        .timeline { display: flex; flex-direction: column; align-items: center; margin-right: 20px; flex-shrink: 0; width: 60px; }
        .timeline .time { font-size: 0.85rem; font-weight: 600; color: #555; }
        .timeline .duration { font-size: 0.75rem; color: #999; }
        .timeline-bar { width: 4px; height: 50px; margin: 5px 0; border-radius: 2px; }

        .item-details { padding-top: 2px; }
        .item-details h6 { font-size: 1rem; font-weight: 600; margin: 0 0 5px 0; color: var(--secondary-text); }
        .item-details p { font-size: 0.9rem; color: #666; margin: 0; }
        .item-details i { margin-right: 8px; color: #aaa; width: 15px; text-align: center; }

        .placeholder { text-align: center; padding: 50px 20px; }
        .placeholder i { font-size: 3rem; color: #ced4da; }
        .placeholder p { margin-top: 15px; font-size: 1.1rem; font-weight: 500; color: #888; }
        
        /* --- Desktop Responsive Design --- */
        @media (min-width: 992px) {
            .planning-container {
                flex-direction: row;
            }
            .calendar-panel {
                flex: 0 0 400px; /* Fixed width for calendar on desktop */
                border-right: 1px solid var(--border-color);
            }
            .details-panel {
                 height: 100%;
            }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="content-wrapper">
    <div class="planning-container">
        <div class="calendar-panel">
            <div class="month-header">
                <button id="prevMonthBtn" class="nav-arrow"><i class="fas fa-chevron-left"></i></button>
                <h4 id="monthDisplay"></h4>
                <button id="nextMonthBtn" class="nav-arrow"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="calendar-grid" id="calendar-weekdays"></div>
            <div class="calendar-grid" id="calendar-days"></div>
        </div>

        <div class="details-panel">
            <div class="details-header">
                <h5 id="selectedDateHeader"></h5>
                <button class="btn btn-sm btn-default" id="todayBtn">Aujourd'hui</button>
            </div>
            <ul class="planning-list" id="planning-list">
                </ul>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentDate = new Date();
    let missionDates = {}; // To store dates with missions

    // --- ELEMENT SELECTORS ---
    const monthDisplay = document.getElementById('monthDisplay');
    const weekdaysContainer = document.getElementById('calendar-weekdays');
    const daysContainer = document.getElementById('calendar-days');
    const selectedDateHeader = document.getElementById('selectedDateHeader');
    const planningList = document.getElementById('planning-list');
    const prevMonthBtn = document.getElementById('prevMonthBtn');
    const nextMonthBtn = document.getElementById('nextMonthBtn');
    const todayBtn = document.getElementById('todayBtn');

    function renderCalendar() {
        daysContainer.innerHTML = '';
        weekdaysContainer.innerHTML = '';
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        monthDisplay.innerHTML = `${currentDate.toLocaleDateString('fr-FR', { month: 'long' })} <span class="year-display">${year}</span>`;
        
        const weekdays = ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'];
        weekdays.forEach(day => {
            weekdaysContainer.innerHTML += `<div class="day-name">${day}</div>`;
        });

        const firstDayOfMonth = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const startDayIndex = firstDayOfMonth.getDay();

        // Fetch missions for the entire visible month to show indicators
        fetchMissionIndicators(year, month + 1).then(() => {
            for (let i = 0; i < startDayIndex; i++) {
                daysContainer.innerHTML += `<div class="day-cell other-month"></div>`;
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'day-cell';
                dayCell.textContent = day;
                const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                dayCell.dataset.date = dateString;

                if (missionDates[dateString]) {
                    dayCell.classList.add('has-mission');
                }

                const today = new Date();
                if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    dayCell.classList.add('today');
                }

                daysContainer.appendChild(dayCell);
            }
            
            updateSelectedDay(currentDate);
            fetchPlanningForDate(currentDate);
        });
    }
    
    function updateSelectedDay(date) {
        document.querySelectorAll('.day-cell.selected').forEach(cell => cell.classList.remove('selected'));
        
        const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const targetCell = document.querySelector(`.day-cell[data-date='${dateString}']`);
        if (targetCell) {
            targetCell.classList.add('selected');
        }

        selectedDateHeader.textContent = date.toLocaleDateString('fr-FR', {
            weekday: 'long', day: 'numeric', month: 'long'
        });
    }

    function fetchMissionIndicators(year, month) {
        return $.ajax({
            url: 'seeplanning-handler.php',
            type: 'GET',
            data: { action: 'get_mission_dates', month: month, year: year },
            dataType: 'json'
        }).done(response => {
            missionDates = {};
            if (response.status === 'success' && response.data) {
                response.data.forEach(date => missionDates[date] = true);
            }
        });
    }

    function fetchPlanningForDate(date) {
        showLoadingState();
        const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        
        $.ajax({
            url: 'seeplanning-handler.php',
            type: 'GET',
            data: { action: 'get_user_planning', date: dateString },
            dataType: 'json',
            success: response => {
                if (response.status === 'success') {
                    renderPlanning(response.data);
                } else {
                    showErrorState(response.message);
                }
            },
            error: () => showErrorState('Erreur de communication avec le serveur.')
        });
    }

    function renderPlanning(missions) {
        planningList.innerHTML = '';
        if (!missions || missions.length === 0) {
            planningList.innerHTML = `
                <li class="placeholder">
                    <i class="far fa-calendar-check"></i>
                    <p>Aucune mission planifiée.</p>
                </li>`;
            return;
        }

        missions.forEach(mission => {
            const li = document.createElement('li');
            li.className = 'planning-item';
            
            const startTime = mission.start_time ? mission.start_time.substring(0, 5) : '';
            const endTime = mission.end_time ? mission.end_time.substring(0, 5) : '';
            
            let duration = '';
            if (startTime && endTime) {
                const start = new Date(`1970-01-01T${startTime}`);
                const end = new Date(`1970-01-01T${endTime}`);
                const diffMs = end - start;
                const diffHrs = Math.floor(diffMs / 3600000);
                const diffMins = Math.floor((diffMs % 3600000) / 60000);
                if (diffHrs > 0 || diffMins > 0) {
                    duration = `${diffHrs}h ${diffMins > 0 ? `${diffMins}m` : ''}`.trim();
                }
            }

            li.innerHTML = `
                <div class="timeline">
                    <span class="time">${startTime}</span>
                    <div class="timeline-bar" style="background-color: ${escapeHtml(mission.color) || 'var(--primary-bg)'};"></div>
                    <span class="duration">${duration}</span>
                </div>
                <div class="item-details">
                    <h6>${escapeHtml(mission.mission_text)}</h6>
                    ${mission.location ? `<p><i class="fas fa-map-marker-alt"></i>${escapeHtml(mission.location)}</p>` : ''}
                    ${mission.details ? `<p><i class="fas fa-info-circle"></i>${escapeHtml(mission.details)}</p>` : ''}
                </div>
            `;
            planningList.appendChild(li);
        });
    }

    function showLoadingState() {
        planningList.innerHTML = `<li class="placeholder"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></li>`;
    }

    function showErrorState(message) {
        planningList.innerHTML = `
            <li class="placeholder">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <p class="text-danger">${escapeHtml(message) || 'Une erreur est survenue.'}</p>
            </li>`;
    }
    
    function escapeHtml(text) {
        if (text === null || typeof text === 'undefined') return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // --- EVENT LISTENERS ---
    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    todayBtn.addEventListener('click', () => {
        currentDate = new Date();
        renderCalendar();
    });

    daysContainer.addEventListener('click', (e) => {
        const cell = e.target.closest('.day-cell');
        if (cell && !cell.classList.contains('other-month')) {
            const [year, month, day] = cell.dataset.date.split('-').map(Number);
            currentDate = new Date(year, month - 1, day);
            updateSelectedDay(currentDate);
            fetchPlanningForDate(currentDate);
        }
    });

    // --- INITIAL LOAD ---
    renderCalendar();
});
</script>

</body>
</html>
