<?php
// 1. Ensure no output before this PHP block
// No spaces, blank lines, or BOM before <?php

// 2. Include session management, which starts the session and defines requireLogin()
require_once 'session-management.php';

// 3. Require login - This will redirect the user to index.php and exit
// if they are not logged in.
requireLogin();

// 4. If the script reaches this point, the user IS logged in.
// Now you can safely output content.
$user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pointage - Gestion des Ouvriers</title>
    <!-- Original CSS styles from timesheet.html -->
    <style>
        /* Basic Reset and Font */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            /* Apple-like font stack */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        }

        body {
            /* Light gray background */
            background-color: #f5f5f7;
            color: #1d1d1f; /* Default dark text */
            -webkit-font-smoothing: antialiased; /* Smoother fonts on WebKit */
            -moz-osx-font-smoothing: grayscale; /* Smoother fonts on Firefox */
        }

        /* Container */
        .container {
            max-width: 1100px; /* Slightly adjusted max-width */
            margin: 0 auto;
            padding: 25px; /* Slightly increased padding */
        }

        /* Header */
       

        /* Navigation */
       /* --- New Navigation Styles --- */
nav {
    background-color: #333; /* Dark gray */
    padding: 12px 0;
    margin-bottom: 30px;
    position: relative; /* Needed for absolute positioning of the mobile menu */
}

.nav-content {
    display: flex;
    justify-content: space-between; /* Distribute space between items */
    align-items: center; /* Vertically align items */
    flex-wrap: wrap; /* Allow items to wrap on smaller screens */
}

.nav-left, .nav-center, .nav-right {
    display: flex; /* Use flex for internal alignment */
    align-items: center;
    padding: 0 10px; /* Add some padding around the sections */
}

.nav-left {
     /* Adjust width or flex-basis if needed for your logo */
     flex-grow: 0; /* Don't grow */
}

.nav-center {
    flex-grow: 1; /* Allow nav links to take up available space */
    justify-content: center; /* Center the links within the center div */
    order: 2; /* Set order for desktop layout */
}

.nav-right {
    flex-grow: 0; /* Don't grow */
    order: 3; /* Set order for desktop layout */
}

.nav-links {
    display: flex; /* Display list items in a row */
    list-style: none;
    gap: 10px 20px; /* Row and column gap */
    padding: 0; /* Remove default padding */
    margin: 0; /* Remove default margin */
}

/* Adjust existing nav a styles for consistency */
nav a {
    color: #f5f5f7; /* Lighter text for dark background */
    text-decoration: none;
    padding: 6px 12px; /* Adjusted padding */
    border-radius: 6px; /* Slightly more rounded */
    transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
    display: inline-block;
    font-size: 14px;
    font-weight: 500;
}

nav a:hover {
    background-color: #555; /* Slightly lighter gray on hover */
    color: #ffffff;
}
nav a.active {
    background-color: #007aff; /* Apple blue for active */
    color: #ffffff;
}

/* Style for the user info on the right */
.user-info-nav {
     display: flex;
     align-items: center;
     gap: 8px; /* Smaller gap than the old header */
     font-size: 14px;
     font-weight: 500;
     color: #f5f5f7; /* Match nav link color */
}

.user-info-nav .user-avatar {
     width: 28px; /* Smaller avatar in nav */
     height: 28px;
     border-radius: 50%;
     background-color: #007aff; /* Apple blue */
     display: flex;
     align-items: center;
     justify-content: center;
     color: white;
     font-weight: 600;
     font-size: 12px; /* Smaller font for initials */
}

/* Hamburger Menu Button (Hidden by default on desktop) */
.hamburger-menu {
    display: none; /* Hide on desktop */
    background: none;
    border: none;
    color: #f5f5f7; /* White icon */
    font-size: 24px;
    cursor: pointer;
    padding: 0 10px;
    order: 1; /* Set order for mobile layout */
}

 /* --- Responsive Adjustments for Mobile Navigation --- */
 @media (max-width: 768px) {
     .nav-content {
         flex-direction: row; /* Keep row layout for logo/hamburger/user */
         justify-content: space-between;
         align-items: center;
     }

     .nav-left {
         order: 1; /* Logo on the left */
         flex-grow: 0;
     }

     .nav-right {
         order: 3; /* User info on the right */
         flex-grow: 0;
     }

     .nav-center {
         order: 4; /* Nav links below others */
         flex-basis: 100%; /* Take full width */
         justify-content: flex-start; /* Align links to the left */
         margin-top: 10px; /* Space above links */
         display: none; /* Hide links by default on mobile */
         flex-direction: column; /* Stack links vertically */
         align-items: flex-start; /* Align links to the left */
     }

     .nav-links {
         flex-direction: column; /* Stack links vertically */
         gap: 5px 0; /* Adjust gap for vertical list */
         width: 100%; /* Links take full width */
     }

     nav li {
         width: 100%; /* Make list items take full width */
     }

     nav a {
         display: block; /* Make links block elements for better clicking */
         padding: 8px 15px; /* Adjust padding for block links */
     }

     .hamburger-menu {
         display: block; /* Show hamburger menu on mobile */
         order: 2; /* Hamburger between logo and user */
     }

     /* Class to show the mobile menu when hamburger is clicked */
     .nav-center.show {
         display: flex; /* Show the navigation links */
     }
 }

 /* Adjust padding for small screens to ensure nav content is not against edges */
 @media (max-width: 480px) {
      .nav-left, .nav-right, .hamburger-menu {
           padding: 0 8px; /* Reduce padding on smaller screens */
      }
      .user-info-nav {
           font-size: 13px;
      }
       .user-info-nav .user-avatar {
          width: 24px;
          height: 24px;
           font-size: 11px;
      }
      .hamburger-menu {
           font-size: 22px;
      }
 }

        /* Card Styling */
        .card {
            background-color: #ffffff;
            border-radius: 12px; /* More rounded corners */
            /* Subtle shadow */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 25px; /* Increased padding */
            margin-bottom: 25px; /* Consistent margin */
            border: 1px solid #e5e5e5; /* Very subtle border */
        }

        h2 {
            margin-bottom: 25px; /* Increased margin */
            color: #1d1d1f; /* Dark text */
            font-size: 28px; /* Larger heading */
            font-weight: 600;
        }
        h3 {
             margin-bottom: 20px;
             font-size: 18px;
             font-weight: 600;
             color: #1d1d1f;
        }

        /* Clock Section */
        .clock-section {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .clock-card {
            text-align: center;
            width: 100%;
            max-width: 450px; /* Slightly wider */
        }

        .clock-display {
            font-size: 56px; /* Larger clock display */
            font-weight: 300; /* Lighter font weight */
            margin-bottom: 25px;
            color: #1d1d1f;
            letter-spacing: 1px;
        }

        .clock-buttons {
            display: flex;
            justify-content: center;
            gap: 15px; /* Increased gap */
            flex-wrap: wrap;
        }

        /* Button Styling */
        button, .btn-primary, .btn-success, .btn-danger, .btn-warning {
            padding: 12px 24px; /* Generous padding */
            border: none;
            border-radius: 8px; /* Rounded corners */
            cursor: pointer;
            font-weight: 600; /* Bolder font */
            font-size: 15px;
            transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
            margin-bottom: 10px;
            line-height: 1.2; /* Ensure text vertical alignment */
        }

        .btn-primary { background-color: #007aff; color: white; }
        .btn-primary:hover { background-color: #0056b3; } /* Darker blue on hover */

        .btn-success { background-color: #34c759; color: white; } /* Apple green */
        .btn-success:hover { background-color: #2ca048; } /* Darker green */

        .btn-danger { background-color: #ff3b30; color: white; } /* Apple red */
        .btn-danger:hover { background-color: #d63027; } /* Darker red */

        .btn-warning { background-color: #ff9500; color: white; } /* Apple orange */
        .btn-warning:hover { background-color: #d97e00; } /* Darker orange */

        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border: 1px solid #e5e5e5; /* Border around table container */
            border-radius: 8px; /* Rounded corners */
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px; /* Adjusted min-width */
        }

        table th, table td {
            padding: 14px 16px; /* Increased padding */
            text-align: left;
            border-bottom: 1px solid #e5e5e5; /* Lighter border */
            font-size: 14px;
            color: #1d1d1f;
        }
        table td { color: #555; } /* Slightly lighter text for data */

        table th {
            background-color: #f9f9f9; /* Very light gray header */
            font-weight: 600; /* Bolder header */
            color: #333; /* Darker header text */
            border-bottom-width: 2px; /* Thicker bottom border for header */
        }

        table tr:last-child td {
             border-bottom: none; /* Remove border from last row */
        }

        table tr:hover {
            background-color: #f5f5f7; /* Subtle hover */
        }

        /* Location Switch */
        .switch {
            position: relative; display: inline-block; width: 50px; height: 28px; /* Slightly taller */ vertical-align: middle;
        }
        .switch input { display: none; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s;
        }
        .slider:before {
            position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); /* Subtle shadow on handle */
        }
        input:checked + .slider { background-color: #34c759; } /* Apple green */
        input:checked + .slider:before { transform: translateX(22px); } /* Adjusted translation */
        .slider.round { border-radius: 28px; } /* Fully rounded */
        .slider.round:before { border-radius: 50%; }

        /* Location Info */
        #location-info {
            background-color: #f0f0f0; /* Slightly different gray */
            padding: 12px 15px; /* Adjusted padding */
            border-radius: 8px;
            margin-bottom: 20px; /* Increased margin */
            border: 1px solid #e0e0e0;
            text-align: center;
            color: #6e6e73; /* Secondary text color */
            font-size: 13px;
        }
        #location-status { font-weight: 600; }
        #location-status.success { color: #34c759; }
        #location-status.error { color: #ff3b30; }
        #location-status.pending { color: #ff9500; } /* Apple orange for pending */
        #location-address { font-size: 14px; margin-top: 5px; font-weight: 500; color: #1d1d1f; }

        /* Location Switch Container */
        .location-toggle-container {
            margin-bottom: 20px; /* Increased margin */
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px; /* Increased gap */
            padding: 12px;
            background-color: #f0f0f0; /* Match location info bg */
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .location-toggle-container span:first-of-type { font-weight: 600; color: #1d1d1f; }
        #location-status-text { font-weight: 600; font-size: 14px; }

        /* Modal Styling */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); /* Darker overlay */
        }
        .modal-content {
            background-color: #ffffff; margin: 8% auto; /* Adjusted margin */ padding: 30px; /* Increased padding */ border-radius: 14px; /* More rounded */ width: 90%; max-width: 700px; /* Adjusted max-width */ position: relative; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        .close {
            position: absolute; top: 15px; right: 20px; font-size: 30px; font-weight: 300; /* Lighter close button */ cursor: pointer; color: #aaa; transition: color 0.2s;
        }
        .close:hover { color: #333; }
        #map-container {
            height: 350px; background-color: #e5e5e5; /* Lighter placeholder bg */ margin-top: 25px; border-radius: 10px; display: flex; justify-content: center; align-items: center; overflow: hidden; /* Hide overflow */
        }
        #map-container img { max-width: 100%; max-height: 100%; object-fit: cover; } /* Ensure image covers */
        #map-details { margin-top: 25px; font-size: 15px; line-height: 1.6; color: #333; }
        #map-details strong { color: #1d1d1f; font-weight: 600; }

        /* Badge */
        .badge {
            display: inline-block; min-width: 18px; /* Slightly wider */ padding: 3px 7px; font-size: 11px; font-weight: 700; line-height: 1; color: #fff; text-align: center; white-space: nowrap; vertical-align: baseline; /* Better alignment */ background-color: #ff3b30; /* Apple red badge */ border-radius: 9px; /* Pill shape */ margin-left: 6px;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: 14px;
            text-align: center;
        }
        .alert-success {
            background-color: rgba(52, 199, 89, 0.1);
            border-color: rgba(52, 199, 89, 0.3);
            color: #2ca048;
        }
        .alert-error {
            background-color: rgba(255, 59, 48, 0.1);
            border-color: rgba(255, 59, 48, 0.3);
            color: #d63027;
        }
        .alert-info {
            background-color: rgba(0, 122, 255, 0.1);
            border-color: rgba(0, 122, 255, 0.3);
            color: #0056b3;
        }

         /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .header-content { flex-direction: column; text-align: center; gap: 10px; }
            .header-content h1 { font-size: 22px; }
            nav ul { justify-content: center; gap: 8px 15px; }
            nav a { font-size: 13px; padding: 5px 10px; }
            h2 { font-size: 24px; }
            .card { padding: 20px; border-radius: 10px; }
            .clock-display { font-size: 48px; }
            .clock-buttons button { width: calc(50% - 10px); font-size: 14px; padding: 10px 18px; }
            .modal-content { width: 95%; margin: 5% auto; padding: 20px; }
            #map-container { height: 300px; }
            table th, table td { padding: 12px 14px; font-size: 13px; }
        }

        @media (max-width: 480px) {
             .container { padding: 15px; }
             h2 { font-size: 22px; }
             .clock-display { font-size: 40px; }
             .clock-buttons button { width: 100%; }
             table th, table td { padding: 10px 12px; font-size: 12px; }
             .modal-content { padding: 15px; }
             #map-container { height: 250px; }
             #map-details { font-size: 14px; }
             nav ul { gap: 5px 10px; }
             nav a { font-size: 12px; padding: 4px 8px; }
        }
    </style>
</head>
<body>
    <nav>
    <div class="container nav-content"> <div class="nav-left">
    <img src="Logo.png" alt="Company Logo" class="company-logo">
</div>
        <div class="nav-center">
             <ul class="nav-links"> <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="timesheet.php" class="active">Pointage</a></li>
                <li><a href="conges.php">Congés </a></li>
                <li><a href="employes.php">Employés</a></li>
                <li><a href="planning.php">Planning </a></li>
                <li><a href="chat.php">Chat </a></li>
                <li><a href="messages.php">Messages RH/Direction</a></li>
                <li><a href="logout.php">Déconnexion</a></li>
             </ul>
        </div>
         <div class="nav-right">
             <div class="user-info-nav"> <span><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom'] . ' SC'); ?></span> <div class="user-avatar">
                    <?php
                    // Generate initials from first and last name (assuming you want SC after the name)
                    // If you need initials like 'ACS', you'd modify this logic
                    echo htmlspecialchars(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)));
                    ?>
                </div>
             </div>
         </div>
         <button class="hamburger-menu" aria-label="Toggle navigation">
            &#9776; </button>
    </div>
</nav>

    <div class="container">
        <div id="pointage">
            <h2>Pointage</h2>
            
            <!-- Status messages area -->
            <div id="status-message" style="display: none;"></div>

            <div class="clock-section">
                <div class="card clock-card">
                    <div class="clock-display" id="current-time">--:--:--</div>

                    <div class="location-toggle-container">
                        <span>Localisation:</span>
                        <label class="switch">
                            <input type="checkbox" id="toggle-location" checked>
                            <span class="slider round"></span>
                        </label>
                        <span id="location-status-text" style="color: #34c759;">Activée</span>
                    </div>

                    <div id="location-info">
                        <div id="current-location">Statut: <span id="location-status" class="pending">Obtention...</span></div>
                        <div id="location-address"></div>
                    </div>

                    <div class="clock-buttons">
                        <button class="btn-success" id="btn-entree" onclick="enregistrerPointage('record_entry')">Enregistrer Entrée</button>
                        <button class="btn-danger" id="btn-sortie" onclick="enregistrerPointage('record_exit')">Enregistrer Sortie</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <h3>Historique des pointages</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Entrée</th>
                                <th>Lieu Entrée</th>
                                <th>Sortie</th>
                                <th>Lieu Sortie</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="timesheet-history">
                            <!-- History data will be loaded here via JavaScript -->
                            <tr>
                                <td colspan="7" style="text-align: center;">Chargement des données...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="map-modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('map-modal').style.display='none'">&times;</span>
                    <h3 id="map-modal-title">Localisation des pointages</h3>
                    <div id="map-container">
                    <div id="map" style="width:100%; height:350px;"></div>
                    </div>
                    <div id="map-details">
                        <p><strong>Entrée:</strong> <span id="map-entree-time">--:--</span> - <span id="map-entree-loc">(Lieu)</span></p>
                        <p><strong>Sortie:</strong> <span id="map-sortie-time">--:--</span> - <span id="map-sortie-loc">(Lieu)</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Leaflet.js OpenStreetMap library -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Global variables for location data
        let currentLatitude = null;
        let currentLongitude = null;
        let currentLocationAddress = null;
        
        
        // Clock Update function
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = `${hours}:${minutes}:${seconds}`;
            }
        }
        const clockInterval = setInterval(updateClock, 1000);
        updateClock(); // Initial call
        
        // Show status message function
        function showStatusMessage(message, type = 'info') {
            const statusDiv = document.getElementById('status-message');
            if (!statusDiv) return;
            
            // Set message and class
            statusDiv.innerHTML = message;
            statusDiv.className = `alert alert-${type}`;
            statusDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }
        
        // Enhanced Mobile-Friendly Geolocation
        function getLocation() {
            const locationStatus = document.getElementById('location-status');
            const locationAddress = document.getElementById('location-address');
            
            if (!locationStatus || !locationAddress) return; // Exit if elements not found
            
            // Update status to pending
            locationStatus.textContent = "Obtention...";
            locationStatus.className = "pending";
            locationAddress.textContent = ""; // Clear previous address
            
            // Reset global location variables
            currentLatitude = null;
            currentLongitude = null;
            currentLocationAddress = null;
            
            // Check if geolocation is available
            if (!navigator.geolocation) {
                locationStatus.textContent = "Non supportée";
                locationStatus.className = "error";
                locationAddress.textContent = "La géolocalisation n'est pas supportée par ce navigateur.";
                return;
            }
            
            // Set options with longer timeout for mobile
            const geoOptions = {
                enableHighAccuracy: true,
                timeout: 15000, // Longer timeout (15 seconds) for mobile devices
                maximumAge: 0 // Don't use cached position
            };
            
            // Get current position with retry mechanism
            let retryCount = 0;
            const maxRetries = 2;
            
            function tryGetPosition() {
                navigator.geolocation.getCurrentPosition(
                    // Success callback
                    (position) => {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        const accuracy = position.coords.accuracy;
                        
                        // Set global variables
                        currentLatitude = lat;
                        currentLongitude = lon;
                        
                        locationStatus.textContent = "Position trouvée";
                        locationStatus.className = "success";
                        
                        // Display coordinates with accuracy information
                        const locationText = `Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}`;
                        locationAddress.textContent = locationText;
                        currentLocationAddress = locationText;
                        
                        // Check if location is accurate enough for business use
                        if (accuracy > 100) { // If accuracy is worse than 100 meters
                            locationAddress.textContent += ` (Précision: ~${Math.round(accuracy)}m)`;
                            currentLocationAddress += ` (Précision: ~${Math.round(accuracy)}m)`;
                        }
                        
                        // Store successful coordinates in session storage
                        storeLastLocation(lat, lon, locationText);
                    },
                    // Error callback
                    (error) => {
                        // Try again if under max retries
                        if (retryCount < maxRetries) {
                            retryCount++;
                            locationStatus.textContent = `Nouvelle tentative (${retryCount})...`;
                            setTimeout(tryGetPosition, 1000); // Wait 1 second before retry
                            return;
                        }
                        
                        // Handle error after all retries
                        locationStatus.textContent = "Erreur Géo.";
                        locationStatus.className = "error";
                        
                        // Get specific error message
                        let errorMsg = getGeolocationErrorMessage(error);
                        locationAddress.textContent = errorMsg;
                        
                        // Fallback to last known position if available
                        tryFallbackLocation();
                    },
                    geoOptions
                );
            }
            
            // Start first attempt
            tryGetPosition();
        }
        
        // Store successful location for fallback
        function storeLastLocation(lat, lon, address) {
            try {
                sessionStorage.setItem('lastLat', lat);
                sessionStorage.setItem('lastLon', lon);
                sessionStorage.setItem('lastAddress', address);
                sessionStorage.setItem('lastLocationTime', new Date().toISOString());
            } catch (e) {
                console.log('Could not store location in session storage');
            }
        }
        
        // Try to use last known location as fallback
        function tryFallbackLocation() {
            try {
                const lastLat = sessionStorage.getItem('lastLat');
                const lastLon = sessionStorage.getItem('lastLon');
                const lastAddress = sessionStorage.getItem('lastAddress');
                const lastTime = sessionStorage.getItem('lastLocationTime');
                
                if (lastLat && lastLon && lastTime) {
                    const timeDiff = (new Date() - new Date(lastTime)) / (1000 * 60); // minutes
                    if (timeDiff < 30) { // Use cached location if less than 30 minutes old
                        const locationAddress = document.getElementById('location-address');
                        const locationStatus = document.getElementById('location-status');
                        
                        // Set global variables
                        currentLatitude = parseFloat(lastLat);
                        currentLongitude = parseFloat(lastLon);
                        currentLocationAddress = lastAddress + ` (Position d'il y a ${Math.round(timeDiff)} minutes)`;
                        
                        locationStatus.textContent = "Position antérieure";
                        locationStatus.className = "warning";
                        
                        locationAddress.textContent = currentLocationAddress;
                        
                        return true;
                    }
                }
            } catch (e) {
                console.log('Could not retrieve fallback location');
            }
            return false;
        }
        
        // Mobile check
        function isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
        
        function getGeolocationErrorMessage(error) {
            // For permission denied errors on mobile, give more specific guidance
            if (error.code === error.PERMISSION_DENIED && isMobileDevice()) {
                return "Accès refusé. Vérifiez les paramètres de localisation de votre téléphone et autorisez ce site à accéder à votre position.";
            }
            
            switch(error.code) {
                case error.PERMISSION_DENIED: 
                    return "Accès localisation refusé. Vérifiez les autorisations dans votre navigateur.";
                case error.POSITION_UNAVAILABLE: 
                    return "Position indisponible. Vérifiez que le GPS est activé ou essayez dehors pour un meilleur signal.";
                case error.TIMEOUT: 
                    return "Délai d'attente dépassé. Le GPS peut prendre plus de temps à l'intérieur des bâtiments.";
                default: 
                    return `Erreur localisation inconnue (${error.code}).`;
            }
        }
        
        // Function to make AJAX requests
        function makeAjaxRequest(action, data, callback) {
            // Create FormData object
            const formData = new FormData();
            formData.append('action', action);
            
            // Add other data to FormData
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }
            
            // Create and send the request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'timesheet-handler.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            callback(null, response);
                        } catch (e) {
                            callback('Erreur de parsing JSON: ' + e.message);
                        }
                    } else {
                        callback('Erreur réseau: ' + xhr.status);
                    }
                }
            };
            xhr.send(formData);
        }
        
        // Record Time Entry
        function enregistrerPointage(action) {
            const toggleLocation = document.getElementById('toggle-location');
            const locationStatus = document.getElementById('location-status');
            
            let latitude = null;
            let longitude = null;
            let address = null;
            
            // Check if location is enabled and available
            if (toggleLocation && toggleLocation.checked && locationStatus) {
                // Location is enabled, use the global variables
                latitude = currentLatitude;
                longitude = currentLongitude;
                address = currentLocationAddress;
                
                // Check if we have valid coordinates
                if (!latitude || !longitude) {
                    showStatusMessage("Position non disponible. Veuillez activer et autoriser la localisation.", "error");
                    return;
                }
            }
            
            // Prepare data object
            const data = {
                latitude: latitude,
                longitude: longitude,
                address: address
            };
            
            // Show pending status
            showStatusMessage("Envoi en cours...", "info");
            
            // Make AJAX request
            makeAjaxRequest(action, data, function(error, response) {
                if (error) {
                    showStatusMessage("Erreur: " + error, "error");
                    return;
                }
                
                if (response.status === "success") {
                    showStatusMessage(response.message, "success");
                    
                    // Refresh the history table
                    loadTimesheetHistory();
                } else {
                    showStatusMessage("Erreur: " + response.message, "error");
                }
            });
        }
        
        // Load timesheet history
        function loadTimesheetHistory() {
            const tableBody = document.getElementById('timesheet-history');
            if (!tableBody) return;
            
            // Show loading state
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Chargement des données...</td></tr>';
            
            // Make AJAX request to get history
            makeAjaxRequest('get_history', {}, function(error, response) {
                if (error) {
                    tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: red;">Erreur: ' + error + '</td></tr>';
                    return;
                }
                
                if (response.status === "success" && Array.isArray(response.data)) {
                    if (response.data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Aucun pointage trouvé</td></tr>';
                        return;
                    }
                    
                    // Clear the table
                    tableBody.innerHTML = '';
                    
                    // Add rows for each entry
                    response.data.forEach(function(entry) {
                        const row = document.createElement('tr');
                        
                        // Format the HTML content for the row
                        row.innerHTML = `
                            <td>${entry.date}</td>
                            <td>${entry.logon_time}</td>
                            <td>${formatLocation(entry.logon_location)}</td>
                            <td>${entry.logoff_time}</td>
                            <td>${formatLocation(entry.logoff_location)}</td>
                            <td>${entry.duration || '--'}</td>
                            <td>
                                <button class="btn-primary" onclick="showMap(${entry.id}, '${entry.date}', '${entry.logon_time}', '${entry.logon_location}', '${entry.logoff_time}', '${entry.logoff_location}')">
                                    Voir carte
                                </button>
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: red;">Erreur: ' + response.message + '</td></tr>';
                }
            });
        }
        
        // Format location text to avoid overly long displays
        function formatLocation(location) {
            if (!location || location === 'Non enregistré') return 'Non enregistré';
            
            // If it contains coordinates, shorten them
            if (location.includes('Lat:')) {
                return 'Position GPS enregistrée';
            }
            
            // Otherwise return the first 20 chars with ellipsis if needed
            return location.length > 20 ? location.substring(0, 20) + '...' : location;
        }
        
        // Show map modal with location details
        function showMap(id, date, entreeTime, entreeLoc, sortieTime, sortieLoc) {
            // Update modal content
            document.getElementById('map-modal-title').textContent = 'Pointages du ' + date;
            document.getElementById('map-entree-time').textContent = entreeTime;
            document.getElementById('map-entree-loc').textContent = entreeLoc;
            document.getElementById('map-sortie-time').textContent = sortieTime;
            document.getElementById('map-sortie-loc').textContent = sortieLoc;
            
            // Show the modal
            document.getElementById('map-modal').style.display = 'block';
        }
        
        // Location toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleLocation = document.getElementById('toggle-location');
            const locationStatusText = document.getElementById('location-status-text');
            
            if (toggleLocation && locationStatusText) {
                toggleLocation.addEventListener('change', function() {
                    if (this.checked) {
                        locationStatusText.textContent = 'Activée';
                        locationStatusText.style.color = '#34c759'; // Green
                        getLocation(); // Get location immediately
                    } else {
                        locationStatusText.textContent = 'Désactivée';
                        locationStatusText.style.color = '#ff3b30'; // Red
                        
                        // Reset location display
                        const locationStatus = document.getElementById('location-status');
                        const locationAddress = document.getElementById('location-address');
                        
                        if (locationStatus) locationStatus.textContent = 'Désactivée';
                        if (locationStatus) locationStatus.className = 'error';
                        if (locationAddress) locationAddress.textContent = '';
                        
                        // Reset global variables
                        currentLatitude = null;
                        currentLongitude = null;
                        currentLocationAddress = null;
                    }
                });
            }
            
            // Initial location check if toggle is on
            if (toggleLocation && toggleLocation.checked) {
                getLocation();
            }
            
            // Load initial timesheet history
            loadTimesheetHistory();
        });
        
        // Check location every 5 minutes if enabled
        setInterval(function() {
            const toggleLocation = document.getElementById('toggle-location');
            if (toggleLocation && toggleLocation.checked) {
                getLocation();
            }
        }, 300000); // 5 minutes = 300000ms
        
let mapInitialized = false;
let mapInstance;

function showMap(id, startDate, startTime, startLocation, endTime, endLocation) {
    document.getElementById('map-modal').style.display = 'block';

    const startCoords = extractCoordinates(startLocation);
    const endCoords = extractCoordinates(endLocation);

    document.getElementById('map-modal-title').textContent = "Pointages du " + startDate;
    document.getElementById('map-entree-time').textContent = startTime;
    document.getElementById('map-entree-loc').textContent = startLocation;
    document.getElementById('map-sortie-time').textContent = endTime;
    document.getElementById('map-sortie-loc').textContent = endLocation;

    if (!mapInitialized) {
        mapInstance = L.map('map').setView(startCoords, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(mapInstance);
        mapInitialized = true;
    } else {
        mapInstance.setView(startCoords, 13);
        mapInstance.eachLayer(function (layer) {
            if (layer instanceof L.Marker || layer instanceof L.Polyline) {
                mapInstance.removeLayer(layer);
            }
        });
    }

    L.marker(startCoords).addTo(mapInstance).bindPopup(`Entrée: ${startTime}`).openPopup();
    L.marker(endCoords).addTo(mapInstance).bindPopup(`Sortie: ${endTime}`);
    L.polyline([startCoords, endCoords], { color: 'blue' }).addTo(mapInstance);
}

function extractCoordinates(locationString) {
    const match = locationString.match(/Lat:\s*(-?\d+\.\d+),\s*Lon:\s*(-?\d+\.\d+)/);
    if (match) {
        return [parseFloat(match[1]), parseFloat(match[2])];
    }
    return [0, 0]; // fallback if parsing fails
}
        document.addEventListener('DOMContentLoaded', function () {
    const hamburgerButton = document.querySelector('.hamburger-menu');
    const navLinks = document.querySelector('.nav-center'); // Select the container of the links

    if (hamburgerButton && navLinks) {
        hamburgerButton.addEventListener('click', function () {
            navLinks.classList.toggle('show'); // Toggle the 'show' class
        });
    }
});
</script>
</body>
</html>
            
