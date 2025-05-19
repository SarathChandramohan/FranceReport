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
    <style>
        @media (max-width: 991px) {
    .navbar-toggler {
        margin-right: 0;
        z-index: 1035;
    }
}
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
            /* Ensure no top margin/padding on body and hide horizontal overflow */
            padding-top: 0 !important;
            margin-top: 0 !important;
            overflow-x: hidden; /* Hide the part of the menu that is off-screen */
        }

        /* Container */
        .container-fluid {
             /* Ensure no top margin/padding is pushing down content after navbar */
            margin-top: 0 !important;
            /* Adjust padding below the navbar as needed */
            padding-top: 20px;
        }

        .container { /* Assuming you might also use a standard .container */
            max-width: 1100px; /* Slightly adjusted max-width */
            margin: 0 auto;
            padding: 25px; /* Slightly increased padding */
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
            line-height: 1.2; /* Ensure text vertical alignment */
            flex-grow: 1; /* Allow buttons to grow */
            flex-basis: 0; /* Allow buttons to shrink */
            text-align: center; /* Center text in buttons */
            min-width: 120px; /* Minimum width to prevent squishing */
        }

        .btn-primary { background-color: #007aff; color: white; }
        .btn-primary:hover { background-color: #0056b3; } /* Darker blue on hover */

        .btn-success { background-color: #34c759; color: white; } /* Apple green */
        .btn-success:hover { background-color: #2ca048; } /* Darker green */

        .btn-danger { background-color: #ff3b30; color: white; } /* Apple red */
        .btn-danger:hover { background-color: #d63027; } /* Darker red */

        /* Changed from orange to #333333 */
        .btn-warning { background-color: #333333; color: white; } /* Dark gray */
        .btn-warning:hover { background-color: #555555; } /* Slightly lighter gray on hover */

         /* Disabled Button Styling */
        button:disabled, button[disabled] {
            opacity: 0.6; /* Greyed out */
            cursor: not-allowed; /* Indicate not clickable */
        }

        /* Added CSS for disabled warning button */
        .btn-warning:disabled, .btn-warning[disabled] {
            background-color: #333333 !important; /* Set background to dark gray */
            color: white !important; /* Ensure text color remains white */
            opacity: 0.6; /* Keep the greyed out effect */
        }


        /* Adjust button margins within the flex container */
        .clock-buttons button {
             margin-bottom: 10px; /* Add bottom margin back to buttons */
        }


        /* Dropdown for Break */
        .dropdown {
            position: relative;
            display: inline-block; /* Keep inline-block for dropdown container */
            width: 100%; /* Make dropdown take full width of its button */
        }

        /* Style the button inside the dropdown */
        .dropdown .btn-warning {
             width: 100%; /* Make the button inside the dropdown take full width */
             margin-bottom: 0; /* Remove margin from the button inside the dropdown */
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 100%; /* Dropdown content takes width of button */
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
            overflow: hidden;
            top: 100%; /* Position below the button */
            left: 0;
        }

        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
        }

        .dropdown-content a:hover { background-color: #f1f1f1; }

        /* Use a class to show dropdown instead of :hover */
        .dropdown.show .dropdown-content {
            display: block;
        }


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
            /* Adjust button width for medium screens */
             .clock-buttons button {
                 width: calc(50% - 10px); /* Two buttons per row with gap */
                 min-width: unset; /* Allow shrinking */
             }
              /* Ensure consistent bottom margin for buttons on the same row */
            .clock-buttons button:nth-child(1),
            .clock-buttons button:nth-child(2) {
                margin-bottom: 10px;
            }
             /* Style for the third button (Sortie) when it wraps */
             .clock-buttons button:nth-child(3) {
                margin-top: 0; /* Remove extra top margin if it wraps */
             }

            .modal-content { width: 95%; margin: 5% auto; padding: 20px; }
            #map-container { height: 300px; }
            table th, table td { padding: 12px 14px; font-size: 13px; }
        }

        @media (max-width: 480px) {
             .container { padding: 15px; }
             h2 { font-size: 22px; }
             .clock-display { font-size: 40px; }
              /* Make all buttons take full width on small screens */
             .clock-buttons button {
                 width: 100%;
                 margin-bottom: 10px;
             }
             .clock-buttons button:last-child {
                margin-bottom: 0; /* No bottom margin on the last button */
             }
             table th, table td { padding: 10px 12px; font-size: 12px; }
             .modal-content { padding: 15px; }
             #map-container { height: 250px; }
             #map-details { font-size: 14px; }
             nav ul { gap: 5px 10px; }
             nav a { font-size: 12px; padding: 4px 8px; }
        }

        /* REMOVED THE "More Assertive CSS for navbar mobile positioning" BLOCK */

    </style>
</head>
<body class="timesheet-page">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid">
        <div id="pointage">
            <h2>Pointage</h2>

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

                         <div class="dropdown" id="break-dropdown">
                            <button class="btn-warning" id="btn-break">Ajouter Pause</button>
                            <div class="dropdown-content">
                                <a href="#" onclick="addBreak(30)">30 min</a>
                                <a href="#" onclick="addBreak(60)">1 heure</a>
                            </div>
                        </div>

                        <button class="btn-danger" id="btn-sortie" onclick="enregistrerPointage('record_exit')" disabled>Enregistrer Sortie</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <h3>Historique des pointages</h3>
                <div class="table-container">
                    <table>
                        <thead>
                        <tr>
                            <th>Actions</th>
                            <th>Date</th>
                            <th>Entrée</th>
                            <th>Lieu Entrée</th>
                            <th>Sortie</th>
                            <th>Pause prise</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                        <tbody id="timesheet-history">
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
<?php include('footer.php'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
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
            return /Android|webOS|iPhone|iPad|Ipod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
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
            const btnEntree = document.getElementById('btn-entree');
            const btnSortie = document.getElementById('btn-sortie');


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

                    // Update button states after successful action
                    if (action === 'record_entry') {
                        btnEntree.disabled = true;
                        btnSortie.disabled = false;
                         document.getElementById('btn-break').disabled = false; // Enable break button on entry
                    } else if (action === 'record_exit') {
                        btnEntree.disabled = true;
                        btnSortie.disabled = true;
                         document.getElementById('btn-break').disabled = true; // Disable break button on exit
                    }

                    // Refresh the history table
                    loadTimesheetHistory();
                } else {
                    showStatusMessage("Erreur: " + response.message, "error");
                }
            });
        }

         // Function to add break
        function addBreak(minutes) {
             // Check if an entry has been recorded today
            makeAjaxRequest('get_latest_entry_status', {}, function(error, response) {
                if (error) {
                    showStatusMessage("Erreur lors de la vérification du statut: " + error, "error");
                    return;
                }

                if (response.status === "success" && response.data && response.data.has_entry && !response.data.has_exit) {
                     // Entry recorded, no exit yet - safe to add break
                    const data = { break_minutes: minutes };
                    makeAjaxRequest('add_break', data, function(error, response) {
                        if (error) {
                            showStatusMessage("Erreur lors de l'ajout de la pause: " + error, "error");
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

                } else {
                    // No entry or exit already recorded
                    showStatusMessage("Impossible d'ajouter une pause sans une entrée préalable pour aujourd'hui ou si la sortie est déjà enregistrée.", "error");
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
                        updateButtonStates(false, false); // No entries, disable both
                         document.getElementById('btn-break').disabled = true; // Disable break button
                        return;
                    }

                    // Clear the table
                    tableBody.innerHTML = '';

                    // Add rows for each entry
                    response.data.forEach(function(entry) {
                        const row = document.createElement('tr');

                         // Calculate duration and subtract break
                         let totalDuration = '--';
                         if (entry.logon_time && entry.logoff_time) {
                             const logonTime = new Date(`1970-01-01T${entry.logon_time}:00Z`);
                             const logoffTime = new Date(`1970-01-01T${entry.logoff_time}:00Z`);
                             let diffMs = logoffTime - logonTime;

                             // Subtract break time if recorded
                             if (entry.break_minutes > 0) {
                                 diffMs -= entry.break_minutes * 60 * 1000; // Subtract break in milliseconds
                             }

                              if (diffMs < 0) diffMs = 0; // Ensure total time is not negative
const totalHours = Math.floor(diffMs / (1000 * 60 * 60));
                             const totalMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
                             totalDuration = `${totalHours}h${String(totalMinutes).padStart(2, '0')}`;
                         }

                        // Format the HTML content for the row
                       row.innerHTML = `
                         <td>
                            <button class="btn-primary" onclick="showMap(${entry.id}, '${entry.date}', '${entry.logon_time}', '${entry.logon_location}', '${entry.logoff_time}', '${entry.logoff_location}')">
                                Voir carte
                            </button>
                        </td>
                        <td>${entry.date}</td>
                        <td>${entry.logon_time}</td>
                        <td>${formatLocation(entry.logon_location)}</td>
                        <td>${entry.logoff_time}</td>
                        <td>${entry.break_minutes > 0 ? entry.break_minutes + ' min' : '--'}</td>
                        <td>${totalDuration}</td>
                    `;

                        tableBody.appendChild(row);
                    });

                     // After loading history, update button states based on the most recent entry (today's)
                     checkLatestEntryStatus();

                } else {
                    tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: red;">Erreur: ' + response.message + '</td></tr>';
                     updateButtonStates(false, false); // Assume error means no valid state, disable both
                     document.getElementById('btn-break').disabled = true; // Disable break button
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
            const breakDropdown = document.getElementById('break-dropdown');
            const btnBreak = document.getElementById('btn-break');

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

            // Toggle dropdown visibility on break button click
            if (btnBreak && breakDropdown) {
                btnBreak.addEventListener('click', function(event) {
                    breakDropdown.classList.toggle('show');
                    event.stopPropagation(); // Prevent click from closing immediately
                });

                // Close the dropdown if the user clicks outside of it
                window.addEventListener('click', function(event) {
                    if (!event.target.matches('#btn-break') && !event.target.closest('.dropdown-content')) {
                         if (breakDropdown.classList.contains('show')) {
                            breakDropdown.classList.remove('show');
                        }
                    }
                });
            }


            // Initial location check if toggle is on
            if (toggleLocation && toggleLocation.checked) {
                getLocation();
            }

            // Load initial timesheet history and update button states
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

    // Default coordinates if location is 'Non enregistré' or parsing fails
    const defaultCoords = [0, 0]; // You might want to set a default central location

    const startCoords = extractCoordinates(startLocation) || defaultCoords;
    const endCoords = extractCoordinates(endLocation) || defaultCoords;

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
        // Clear existing layers (markers, polylines)
        mapInstance.eachLayer(function (layer) {
            if (layer instanceof L.Marker || layer instanceof L.Polyline) {
                mapInstance.removeLayer(layer);
            }
        });

        // Set view to the entry location if available, otherwise use exit location
        if (startCoords !== defaultCoords) {
             mapInstance.setView(startCoords, 13);
        } else if (endCoords !== defaultCoords) {
             mapInstance.setView(endCoords, 13);
        } else {
             // If neither are valid, set view to a default or previous view
             mapInstance.setView([0,0], 2); // Example: World view
        }
    }

    // Add entry marker if coordinates are valid
    if (startCoords !== defaultCoords) {
        L.marker(startCoords).addTo(mapInstance).bindPopup(`Entrée: ${startTime}`).openPopup();
    }

    // Add exit marker if coordinates are valid
    if (endCoords !== defaultCoords) {
         L.marker(endCoords).addTo(mapInstance).bindPopup(`Sortie: ${endTime}`).openPopup(); // Open exit popup by default
    }


    // Add polyline if both coordinates are valid
    if (startCoords !== defaultCoords && endCoords !== defaultCoords) {
        L.polyline([startCoords, endCoords], { color: 'blue' }).addTo(mapInstance);
    }

    // Invalidate size to fix map rendering issues in modal
    setTimeout(() => {
        if (mapInstance) {
            mapInstance.invalidateSize();
        }
    }, 100); // Small delay to allow modal to be visible


}

function extractCoordinates(locationString) {
     if (!locationString || locationString === 'Non enregistré') return null; // Return null if location is not recorded

    const match = locationString.match(/Lat:\s*(-?\d+\.\d+),\s*Lon:\s*(-?\d+\.\d+)/);
    if (match) {
        return [parseFloat(match[1]), parseFloat(match[2])];
    }
    return null; // Return null if parsing fails
}

// Function to check the latest entry status and update button states
function checkLatestEntryStatus() {
     makeAjaxRequest('get_latest_entry_status', {}, function(error, response) {
        const btnEntree = document.getElementById('btn-entree');
        const btnSortie = document.getElementById('btn-sortie');
         const btnBreak = document.getElementById('btn-break');

        if (error) {
            console.error("Error checking latest entry status:", error);
            // On error, default to disabling buttons for safety
            updateButtonStates(false, false);
             btnBreak.disabled = true;
            return;
        }

        if (response.status === "success" && response.data) {
            const hasEntry = response.data.has_entry;
            const hasExit = response.data.has_exit;

            updateButtonStates(hasEntry, hasExit);

             // Enable break button only if entry exists and no exit recorded
            if (hasEntry && !hasExit) {
                 btnBreak.disabled = false;
             } else {
                 btnBreak.disabled = true;
             }

        } else {
            console.error("Error in response data for latest entry status:", response.message);
            // On error, default to disabling buttons for safety
            updateButtonStates(false, false);
            btnBreak.disabled = true;
        }
     });
}

// Function to update the disabled state of buttons
function updateButtonStates(hasEntry, hasExit) {
    const btnEntree = document.getElementById('btn-entree');
    const btnSortie = document.getElementById('btn-sortie');
    const btnBreak = document.getElementById('btn-break');


    if (!btnEntree || !btnSortie || !btnBreak) return; // Exit if buttons not found

    if (hasEntry && hasExit) {
        // Both entry and exit recorded
        btnEntree.disabled = true;
        btnSortie.disabled = true;
        btnBreak.disabled = true; // Disable break button
    } else if (hasEntry && !hasExit) {
        // Entry recorded, but not exit
        btnEntree.disabled = true;
        btnSortie.disabled = false;
         btnBreak.disabled = false; // Enable break button
    } else {
        // No entry recorded for today
        btnEntree.disabled = false;
        btnSortie.disabled = true;
        btnBreak.disabled = true; // Disable break button
    }
}


        document.addEventListener('DOMContentLoaded', function () {
    // Corrected selector to target the Bootstrap navbar toggler button
    const hamburgerButton = document.querySelector('.navbar-toggler');
    // Corrected selector to target the collapsible navbar content
    const navLinks = document.getElementById('navbarNav');

    if (hamburgerButton && navLinks) {
        hamburgerButton.addEventListener('click', function () {
            // Bootstrap 4's JS handles the 'show' class toggle on the target element (#navbarNav)
            // when the button with data-toggle="collapse" and data-target="#navbarNav" is clicked.
            // You generally don't need to manually toggle the class here if Bootstrap JS is working.
            // If Bootstrap JS is NOT being used for collapse, uncomment the line below:
            // navLinks.classList.toggle('show');
        });
    }

    // Added event listener to close the menu when a link is clicked in mobile view
     const navLinkItems = navLinks.querySelectorAll('.nav-link');
     navLinkItems.forEach(link => {
         link.addEventListener('click', function() {
             // Check if the navbar is currently expanded (has the 'show' class)
             if (navLinks.classList.contains('show')) {
                 // Trigger Bootstrap's collapse hide method if Bootstrap JS is available
                 // This is the standard way to close the collapsible menu programmatically
                 if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                      const collapseElement = document.getElementById('navbarNav');
                      const collapse = new bootstrap.Collapse(collapseElement, { toggle: false });
                      collapse.hide();
                 } else if (typeof $ !== 'undefined' && $.fn.collapse) {
                      // Fallback for older jQuery/Bootstrap 4 setups
                     $('#navbarNav').collapse('hide');
                 } else {
                      // Manual class removal if Bootstrap JS is not available
                      navLinks.classList.remove('show');
                 }
             }
         });
     });
});
</script>
</body>
</html>
