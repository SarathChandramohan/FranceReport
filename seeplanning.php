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
        /* --- Animations --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Base & Layout --- */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevent double scrollbars */
        }

        body {
            background-color: #f4f6f8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }

        .main-container {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            height: calc(100vh - 78px); /* Adjust based on your navbar height */
            max-width: 450px; /* Mobile-first design constraint */
            margin: 0 auto;
            background-color: #1A2541; /* Dark blue background */
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        /* --- Top Calendar Panel (Dark Blue) --- */
        .calendar-container {
            padding: 20px 15px;
            color: white;
            flex-shrink: 0;
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .month-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: white;
        }
        
        .month-header .year-display {
             font-size: 1.2rem;
             font-weight: 300;
             opacity: 0.8;
             margin-right: 10px;
        }

        .nav-arrow {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }

        .nav-arrow:hover {
            color: white;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            text-align: center;
        }

        .calendar-grid .day-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
        }

        .calendar-grid .day-cell {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 0;
            cursor: pointer;
            border-radius: 50%;
            transition: background-color 0.2s, color 0.2s;
            position: relative;
        }
        
        .day-cell.other-month {
            opacity: 0.3;
            pointer-events: none;
        }

        .day-cell.today {
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.5);
        }

        .day-cell.selected {
            background-color: #E91E63; /* Pinkish highlight from image */
            color: white;
            font-weight: 700;
        }

        /* --- Bottom Details Panel (White) --- */
        .details-panel {
            background-color: #ffffff;
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
            box-shadow: 0 -8px 25px rgba(0,0,0,0.1);
            padding: 20px;
            flex-grow: 1;
            margin-top: -20px; /* The overlap effect */
            position: relative;
            z-index: 10;
            overflow-y: auto; /* Allow this part to scroll */
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .details-header h5 {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .today-button {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
        }

        .planning-list {
            list-style: none;
            padding: 0;
        }

        .planning-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
            animation: fadeIn 0.4s ease-out;
        }

        .timeline {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .timeline .time {
            font-size: 0.8rem;
            font-weight: 600;
            color: #333;
        }

        .timeline .duration {
            font-size: 0.7rem;
            color: #999;
        }

        .timeline-bar {
            width: 4px;
            height: 50px;
            margin: 5px 0;
            border-radius: 2px;
        }

        .item-details {
            padding-top: 2px;
        }
        
        .item-details h6 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .item-details p {
            font-size: 0.85rem;
            color: #777;
            margin: 0;
        }
        .item-details i {
            margin-right: 5px;
            color: #aaa;
        }

        .placeholder {
            text-align: center;
            padding: 40px 20px;
        }

        .placeholder i {
            font-size: 2.5rem;
            color: #ced4da;
        }
        
        .placeholder p {
            margin-top: 15px;
            font-size: 1rem;
            font-weight: 500;
            color: #888;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-container">
    <div class="calendar-container">
        <div class="month-header">
            <button id="prevMonthBtn" class="nav-arrow"><i class="fas fa-chevron-left"></i></button>
            <div>
                <span id="yearDisplay" class="year-display"></span>
                <h2 id="monthDisplay"></h2>
            </div>
            <button id="nextMonthBtn" class="nav-arrow"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="calendar-grid" id="calendar-weekdays"></div>
        <div class="calendar-grid" id="calendar-days"></div>
    </div>

    <div class="details-panel">
        <div class="details-header">
            <h5 id="selectedDateHeader"></h5>
            <button class="today-button" id="todayBtn">Aujourd'hui</button>
        </div>
        <ul class="planning-list" id="planning-list">
            </ul>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- STATE & CONFIG ---
    let currentDate = new Date();

    // --- ELEMENT SELECTORS ---
    const yearDisplay = document.getElementById('yearDisplay');
    const monthDisplay = document.getElementById('monthDisplay');
    const weekdaysContainer = document.getElementById('calendar-weekdays');
    const daysContainer = document.getElementById('calendar-days');
    const selectedDateHeader = document.getElementById('selectedDateHeader');
    const planningList = document.getElementById('planning-list');
    const prevMonthBtn = document.getElementById('prevMonthBtn');
    const nextMonthBtn = document.getElementById('nextMonthBtn');
    const todayBtn = document.getElementById('todayBtn');

    // --- CALENDAR GENERATION ---
    function renderCalendar() {
        daysContainer.innerHTML = '';
        weekdaysContainer.innerHTML = '';
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        yearDisplay.textContent = year;
        monthDisplay.textContent = currentDate.toLocaleDateString('fr-FR', { month: 'long' });
        
        // Render weekday headers
        const weekdays = ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa'];
        weekdays.forEach(day => {
            weekdaysContainer.innerHTML += `<div class="day-name">${day}</div>`;
        });

        const firstDayOfMonth = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const startDayIndex = firstDayOfMonth.getDay(); // 0 for Sunday, 1 for Monday...

        // Add blank cells for days of the previous month
        for (let i = 0; i < startDayIndex; i++) {
            daysContainer.innerHTML += `<div class="day-cell other-month"></div>`;
        }

        // Add cells for each day of the current month
        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'day-cell';
            dayCell.textContent = day;
            dayCell.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

            const today = new Date();
            if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayCell.classList.add('today');
            }

            daysContainer.appendChild(dayCell);
        }
        
        updateSelectedDay(currentDate);
        fetchPlanningForDate(currentDate);
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

    // --- DATA FETCHING & RENDERING ---
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
            error: xhr => {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Erreur de communication.';
                showErrorState(errorMsg);
            }
        });
    }

    function renderPlanning(missions) {
        planningList.innerHTML = '';
        if (!missions || missions.length === 0) {
            planningList.innerHTML = `
                <li class="placeholder">
                    <i class="fas fa-calendar-check"></i>
                    <p>Aucune mission planifiée pour ce jour.</p>
                </li>`;
            return;
        }

        missions.forEach(mission => {
            const li = document.createElement('li');
            li.className = 'planning-item';
            
            const startTime = mission.start_time ? mission.start_time.substring(0, 5) : 'N/A';
            const endTime = mission.end_time ? mission.end_time.substring(0, 5) : 'N/A';
            
            let duration = '';
            if (mission.start_time && mission.end_time) {
                const start = new Date(`1970-01-01T${mission.start_time}`);
                const end = new Date(`1970-01-01T${mission.end_time}`);
                const diffMs = end - start;
                const diffHrs = Math.floor(diffMs / 3600000);
                const diffMins = Math.floor((diffMs % 3600000) / 60000);
                duration = `${diffHrs}h ${diffMins > 0 ? `${diffMins}m` : ''}`.trim();
            }

            li.innerHTML = `
                <div class="timeline">
                    <span class="time">${startTime}</span>
                    <div class="timeline-bar" style="background-color: ${escapeHtml(mission.color) || '#3498db'};"></div>
                    <span class="duration">${duration}</span>
                </div>
                <div class="item-details">
                    <h6>${escapeHtml(mission.mission_text)}</h6>
                    ${mission.location ? `<p><i class="fas fa-map-marker-alt"></i>${escapeHtml(mission.location)}</p>` : ''}
                </div>
            `;
            planningList.appendChild(li);
        });
    }

    function showLoadingState() {
        planningList.innerHTML = `<li class="placeholder"><div class="spinner-border text-primary" role="status"></div></li>`;
    }

    function showErrorState(message) {
        planningList.innerHTML = `
            <li class="placeholder">
                <i class="fas fa-exclamation-circle text-danger"></i>
                <p class="text-danger">${escapeHtml(message) || 'Une erreur est survenue.'}</p>
            </li>`;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
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
        if (e.target.classList.contains('day-cell') && !e.target.classList.contains('other-month')) {
            const [year, month, day] = e.target.dataset.date.split('-').map(Number);
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
