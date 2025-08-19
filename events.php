<?php
// events.php
require_once 'session-management.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --bg-color: #f6f6f6;
            --main-content-bg: #ffffff;
            --primary-text-color: #2c2c2e;
            --secondary-text-color: #8a8a8e;
            --border-color: #e5e5ea;
            --today-highlight-bg: #6A0DAD; /* Main purple from your theme */
            --today-highlight-text: #ffffff;
            --selected-day-bg: #e9e9eb;
            --event-dot-color: #34c759; /* A distinct color for event dots */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        body {
            background-color: var(--bg-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            overflow: hidden; /* Prevent body scroll */
        }

        .main-container {
            display: flex;
            height: calc(100vh - 78px); /* Adjust based on your navbar's height */
            padding: 15px;
            box-sizing: border-box;
        }

        /* Left Panel: Calendar */
        .calendar-container {
            min-width: 320px;
            max-width: 320px;
            background-color: var(--main-content-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .calendar-header h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-text-color);
            text-transform: capitalize;
        }

        .calendar-nav button {
            background: none;
            border: none;
            color: var(--secondary-text-color);
            font-size: 1rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .calendar-nav button:hover { color: var(--primary-text-color); }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
        }

        .calendar-weekdays {
            margin-bottom: 10px;
        }

        .calendar-weekdays .weekday {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--secondary-text-color);
        }

        .day-cell {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 40px;
            cursor: pointer;
            position: relative;
        }

        .day-number {
            width: 28px;
            height: 28px;
            line-height: 28px;
            border-radius: 50%;
            font-size: 0.9rem;
            transition: background-color 0.2s, color 0.2s;
        }

        .day-cell.other-month .day-number {
            color: #ccc;
            pointer-events: none;
        }
        
        .day-cell.today .day-number {
            background-color: var(--today-highlight-bg);
            color: var(--today-highlight-text);
            font-weight: 600;
        }
        
        .day-cell.selected .day-number {
            background-color: var(--selected-day-bg);
            color: var(--primary-text-color);
            font-weight: 600;
        }
        
        .day-cell.today.selected .day-number {
            background-color: var(--today-highlight-bg);
            color: var(--today-highlight-text);
        }
        
        .event-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background-color: var(--event-dot-color);
            position: absolute;
            bottom: 4px;
            display: none; /* Hidden by default */
        }
        
        .day-cell.has-event .event-dot {
            display: block;
        }
        
        /* Right Panel: Event Details */
        .details-container {
            flex-grow: 1;
            margin-left: 15px;
            background-color: var(--main-content-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .details-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .details-header h5 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-text-color);
        }

        .event-list-container {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .event-card {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            animation: fadeIn 0.4s ease-out;
            padding-left: 15px; /* Space for the color bar */
            border-left: 4px solid; /* The colored bar */
            border-radius: 4px; /* Slight rounding on the card */
        }

        .event-details {
            display: flex;
            flex-direction: column;
        }

        .event-time {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--secondary-text-color);
            margin-bottom: 4px;
        }
        
        .event-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--primary-text-color);
            margin: 0;
        }
        
        .event-description {
            font-size: 0.9rem;
            color: #555;
            margin: 4px 0 0 0;
        }
        
        .placeholder {
            text-align: center;
            padding-top: 80px;
            color: var(--secondary-text-color);
        }
        .placeholder p { font-size: 1rem; margin-top: 10px; }

    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-container">
    <div class="calendar-container">
        <div class="calendar-header">
            <h4 id="monthDisplay"></h4>
            <div class="calendar-nav">
                <button id="prevMonthBtn"><i class="fas fa-chevron-left"></i></button>
                <button id="nextMonthBtn"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="calendar-grid calendar-weekdays" id="calendar-weekdays"></div>
        <div class="calendar-grid calendar-body" id="calendar-days"></div>
    </div>

    <div class="details-container">
        <div class="details-header">
            <h5 id="selectedDateHeader"></h5>
        </div>
        <div class="event-list-container" id="event-list">
            </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentDate = new Date();
    let eventDates = {}; 

    const monthDisplay = document.getElementById('monthDisplay');
    const weekdaysContainer = document.getElementById('calendar-weekdays');
    const daysContainer = document.getElementById('calendar-days');
    const selectedDateHeader = document.getElementById('selectedDateHeader');
    const eventList = document.getElementById('event-list');

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        monthDisplay.textContent = `${currentDate.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })}`;
        
        if (weekdaysContainer.children.length === 0) {
            const weekdays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
            weekdays.forEach(day => {
                const dayEl = document.createElement('div');
                dayEl.className = 'weekday';
                dayEl.textContent = day;
                weekdaysContainer.appendChild(dayEl);
            });
        }
        
        daysContainer.innerHTML = '';
        fetchEventIndicators(year, month + 1).then(() => {
            const firstDayOfMonth = new Date(year, month, 1);
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            // Adjust startDayIndex for Monday start: (day + 6) % 7
            const startDayIndex = (firstDayOfMonth.getDay() + 6) % 7;

            for (let i = 0; i < startDayIndex; i++) {
                daysContainer.innerHTML += `<div class="day-cell other-month"></div>`;
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'day-cell';
                
                const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                dayCell.dataset.date = dateString;
                
                dayCell.innerHTML = `<span class="day-number">${day}</span><span class="event-dot"></span>`;
                
                if (eventDates[dateString]) {
                    dayCell.classList.add('has-event');
                }
                
                const today = new Date();
                if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    dayCell.classList.add('today');
                }
                daysContainer.appendChild(dayCell);
            }
            updateSelectedDay(currentDate);
            fetchEventsForDate(currentDate);
        });
    }

    function updateSelectedDay(date) {
        document.querySelectorAll('.day-cell.selected').forEach(cell => cell.classList.remove('selected'));
        const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const targetCell = document.querySelector(`.day-cell[data-date='${dateString}']`);
        if (targetCell) {
            targetCell.classList.add('selected');
        }
        selectedDateHeader.textContent = date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    }

    function fetchEventIndicators(year, month) {
        return $.getJSON('events_handler.php', { action: 'get_event_dates', month: month, year: year })
            .done(response => {
                eventDates = {};
                if (response.status === 'success' && response.data) {
                    response.data.forEach(date => eventDates[date] = true);
                }
            });
    }

    function fetchEventsForDate(date) {
        showLoadingState();
        const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        $.getJSON('events_handler.php', { action: 'get_events_for_date', date: dateString })
            .done(response => {
                if (response.status === 'success') {
                    renderEvents(response.data);
                } else {
                    showErrorState(response.message);
                }
            })
            .fail(() => showErrorState('Erreur de communication avec le serveur.'));
    }

    function renderEvents(events) {
        eventList.innerHTML = '';
        if (!events || events.length === 0) {
            eventList.innerHTML = `<div class="placeholder"><p>Aucun événement pour ce jour.</p></div>`;
            return;
        }
        events.forEach(event => {
            const card = document.createElement('div');
            card.className = 'event-card';
            card.style.borderColor = escapeHtml(event.color) || 'var(--today-highlight-bg)';

            let timeText = event.start_time + (event.end_time ? ' - ' + event.end_time : '');
            if (event.start_time === '00:00' && event.end_time === '23:59') {
                timeText = 'Toute la journée';
            }

            card.innerHTML = `
                <div class="event-details">
                    <div class="event-time">${timeText}</div>
                    <h6 class="event-title">${escapeHtml(event.title)}</h6>
                    ${event.description ? `<p class="event-description">${escapeHtml(event.description)}</p>` : ''}
                </div>
            `;
            eventList.appendChild(card);
        });
    }

    function showLoadingState() { eventList.innerHTML = `<div class="placeholder"><p>Chargement...</p></div>`; }
    function showErrorState(message) { eventList.innerHTML = `<div class="placeholder"><p class="text-danger">${escapeHtml(message)}</p></div>`; }
    function escapeHtml(text) {
        return text ? new Option(text).innerHTML : '';
    }

    document.getElementById('prevMonthBtn').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
    document.getElementById('nextMonthBtn').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
    daysContainer.addEventListener('click', (e) => {
        const cell = e.target.closest('.day-cell');
        if (cell && cell.dataset.date) {
            const [year, month, day] = cell.dataset.date.split('-').map(Number);
            currentDate = new Date(year, month - 1, day);
            updateSelectedDay(currentDate);
            fetchEventsForDate(currentDate);
        }
    });

    renderCalendar();
});
</script>

</body>
</html>
