<?php
// 1. Session Management & Login Check
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser(); // Get logged-in user details if needed

// 2. DB Connection
require_once 'db-connection.php';

// 3. Fetch users (might be needed for a future modal)
$usersList = [];
try {
    $stmt = $conn->query("SELECT user_id, nom, prenom FROM Users WHERE status = 'Active' ORDER BY nom, prenom");
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users for events page: " . $e->getMessage());
    // Handle error appropriately
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier - Gestion des Ouvriers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Match body background from original file */
        }
        /* Custom scrollbar for the event list */
        .event-list::-webkit-scrollbar {
            width: 5px;
        }
        .event-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .event-list::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 6px;
        }
        .event-list::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        /* Added to ensure the calendar fills the container */
        #calendar-days {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            grid-auto-rows: 1fr; /* Make rows of equal height */
            min-height: 500px; /* Give a minimum height to the calendar */
        }
        .calendar-day {
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <?php include 'navbar.php'; // Include the navigation bar ?>

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto bg-white rounded-2xl shadow-lg flex flex-col lg:flex-row overflow-hidden">

            <main class="w-full lg:w-3/4 p-6">
                <header class="flex flex-wrap items-center justify-between mb-6 gap-4">
                    <div class="flex items-center gap-4">
                        <h1 id="month-year" class="text-3xl font-bold text-gray-800"></h1>
                        <div class="flex items-center gap-2">
                            <button id="prev-month" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                                <i class="fas fa-chevron-left text-gray-600"></i>
                            </button>
                            <button id="today-btn" class="text-sm font-semibold bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">Aujourd'hui</button>
                            <button id="next-month" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                                <i class="fas fa-chevron-right text-gray-600"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center bg-gray-200 rounded-lg p-1 text-sm font-semibold">
                        <button class="px-4 py-1 rounded-md hover:bg-gray-300 transition-colors">Jour</button>
                        <button class="px-4 py-1 rounded-md hover:bg-gray-300 transition-colors">Semaine</button>
                        <button class="px-4 py-1 rounded-md bg-white shadow-sm transition-colors">Mois</button>
                        <button class="px-4 py-1 rounded-md hover:bg-gray-300 transition-colors">Année</button>
                    </div>
                </header>

                <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Dim</div>
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Lun</div>
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Mar</div>
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Mer</div>
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Jeu</div>
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Ven</div>
                    <div class="text-center py-3 font-semibold text-gray-500 text-sm bg-gray-50">Sam</div>

                    <div id="calendar-days" class="grid grid-cols-7 col-span-7 gap-px">
                        </div>
                </div>
            </main>

            <aside class="w-full lg:w-1/4 bg-gray-50 border-l border-gray-200 p-6 flex flex-col">
                <div class="relative mb-4">
                    <input type="text" placeholder="Rechercher..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Événements à venir</h2>
                <div id="event-list" class="event-list flex-grow overflow-y-auto pr-2">
                    </div>
            </aside>

        </div>
    </div>
    
    <div id="loading-spinner" class="hidden fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-[100]">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monthYearEl = document.getElementById('month-year');
            const calendarDaysEl = document.getElementById('calendar-days');
            const eventListEl = document.getElementById('event-list');
            const prevMonthBtn = document.getElementById('prev-month');
            const nextMonthBtn = document.getElementById('next-month');
            const todayBtn = document.getElementById('today-btn');
            const loadingSpinner = document.getElementById('loading-spinner');

            let currentDate = new Date();
            let allEvents = []; // To store fetched events

            // --- DATA FETCHING ---
            const fetchEvents = (startDate, endDate) => {
                loadingSpinner.style.display = 'flex';
                const startStr = startDate.toISOString();
                const endStr = endDate.toISOString();
                
                // Fetch from the existing handler
                fetch(`events_handler.php?action=get_events&start=${startStr}&end=${endStr}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Transform data for the new calendar format
                        allEvents = data.map(event => ({
                            id: event.id,
                            // The date part of the start datetime
                            date: event.start.split('T')[0],
                            start: new Date(event.start),
                            end: new Date(event.end),
                            title: event.title,
                            // Convert hex color to a simple text color for demonstration
                            // A more robust solution would map hex codes to specific Tailwind classes
                            color: 'bg-blue-500', 
                            description: event.extendedProps.description || ''
                        }));
                        renderCalendar();
                        renderEvents();
                    })
                    .catch(error => {
                        console.error('Error fetching events:', error);
                        alert("Erreur lors du chargement des événements.");
                    })
                    .finally(() => {
                         loadingSpinner.style.display = 'none';
                    });
            };
            
            const updateCalendarData = () => {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                const firstDay = new Date(year, month, 1);
                // Go back to the previous Sunday to have a full view
                const calendarStart = new Date(firstDay.setDate(firstDay.getDate() - firstDay.getDay()));
                // Go forward to the next Saturday to complete the 6-week view
                const calendarEnd = new Date(new Date(year, month + 1, 0).setDate(new Date(year, month + 1, 0).getDate() + (6 - new Date(year, month + 1, 0).getDay())));
                fetchEvents(calendarStart, calendarEnd);
            };


            // --- UI RENDERING ---
            const renderCalendar = () => {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                monthYearEl.textContent = `${currentDate.toLocaleString('fr-FR', { month: 'long' })} ${year}`;
                
                const firstDayOfMonth = new Date(year, month, 1).getDay();
                const lastDateOfMonth = new Date(year, month + 1, 0).getDate();
                const lastDateOfPrevMonth = new Date(year, month, 0).getDate();

                calendarDaysEl.innerHTML = '';

                // Previous month's days
                for (let i = firstDayOfMonth; i > 0; i--) {
                    const day = lastDateOfPrevMonth - i + 1;
                    calendarDaysEl.innerHTML += `<div class="bg-gray-50 p-2 text-right text-gray-400"><span class="text-sm">${day}</span></div>`;
                }

                // Current month's days
                const today = new Date();
                for (let i = 1; i <= lastDateOfMonth; i++) {
                    const isToday = i === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                    const dayDate = new Date(year, month, i);
                    const dayString = dayDate.toISOString().split('T')[0];
                    
                    const dayEvents = allEvents.filter(e => e.date === dayString);
                    
                    let dayHtml = `
                        <div class="calendar-day bg-white p-2 text-right relative">
                            <span class="text-sm font-medium ${isToday ? 'bg-blue-600 text-white rounded-full w-7 h-7 flex items-center justify-center' : 'text-gray-700'}">${i}</span>
                            <div class="mt-1 flex-grow overflow-y-auto text-xs text-left">`;

                    dayEvents.forEach(event => {
                        dayHtml += `<div class="text-white ${event.color} rounded px-1 mb-1 truncate cursor-pointer" title="${event.title}">${event.title}</div>`;
                    });

                    dayHtml += `</div></div>`;
                    calendarDaysEl.innerHTML += dayHtml;
                }
                
                // Next month's days to fill the grid
                const totalCells = 42; // 6 rows * 7 columns
                const renderedCells = firstDayOfMonth + lastDateOfMonth;
                const remainingCells = totalCells - renderedCells > 0 ? totalCells - renderedCells : (35 - renderedCells > 0 ? 35 - renderedCells : 0) ;

                for (let i = 1; i <= remainingCells; i++) {
                    calendarDaysEl.innerHTML += `<div class="bg-gray-50 p-2 text-right text-gray-400"><span class="text-sm">${i}</span></div>`;
                }
            };

            const renderEvents = () => {
                eventListEl.innerHTML = '';
                const today = new Date();
                // Set time to 00:00:00 to compare dates only
                today.setHours(0,0,0,0); 

                const upcomingEvents = allEvents
                    .filter(e => e.start >= today)
                    .sort((a, b) => a.start - b.start);

                if (upcomingEvents.length === 0) {
                    eventListEl.innerHTML = '<p class="text-gray-500">Aucun événement à venir.</p>';
                    return;
                }

                let lastMonth = '';
                upcomingEvents.forEach(event => {
                    const eventDate = event.start;
                    const monthName = eventDate.toLocaleString('fr-FR', { month: 'long' });
                    
                    if (monthName !== lastMonth) {
                         eventListEl.innerHTML += `<h3 class="font-semibold text-gray-600 mt-4 mb-2">${monthName}</h3>`;
                         lastMonth = monthName;
                    }
                    
                    const timeFormat = { hour: '2-digit', minute: '2-digit', hour12: false };
                    const timeString = `${event.start.toLocaleTimeString('fr-FR', timeFormat)} - ${event.end.toLocaleTimeString('fr-FR', timeFormat)}`;

                    eventListEl.innerHTML += `
                        <div class="mb-3 p-3 rounded-lg border border-gray-200 bg-white">
                            <p class="font-semibold text-gray-800">${event.title}</p>
                            <p class="text-sm text-gray-500">${eventDate.toLocaleDateString('fr-FR', { weekday: 'long', month: 'long', day: 'numeric' })}</p>
                            <p class="text-xs text-gray-400">${timeString}</p>
                        </div>
                    `;
                });
            };

            // --- EVENT LISTENERS ---
            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateCalendarData();
            });

            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateCalendarData();
            });

            todayBtn.addEventListener('click', () => {
                currentDate = new Date();
                updateCalendarData();
            });

            // --- INITIAL RENDER ---
            updateCalendarData();
        });
    </script>
</body>
</html>
