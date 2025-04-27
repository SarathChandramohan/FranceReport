<?php
// timesheet.php - Secure wrapper for the timesheet.html page

// Include session management
require_once 'session_check.php';

// Require login to access this page
requireLogin();

// Get current user data
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
        /* Include all the original CSS from timesheet.html here */
        /* --- Apple Inspired Theme --- */

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
        header {
            /* White header background */
            background-color: #ffffff;
            color: #1d1d1f; /* Dark text */
            padding: 15px 0; /* Adjusted padding */
            margin-bottom: 0; /* Remove bottom margin, nav handles separation */
            border-bottom: 1px solid #d2d2d7; /* Subtle border */
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-content h1 {
            font-size: 24px; /* Slightly larger title */
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px; /* Increased gap */
            font-size: 14px;
            font-weight: 500;
        }

        .user-avatar {
            width: 36px; /* Slightly smaller avatar */
            height: 36px;
            border-radius: 50%;
            background-color: #007aff; /* Apple blue */
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        {
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
        header {
            /* White header background */
            background-color: #ffffff;
            color: #1d1d1f; /* Dark text */
            padding: 15px 0; /* Adjusted padding */
            margin-bottom: 0; /* Remove bottom margin, nav handles separation */
            border-bottom: 1px solid #d2d2d7; /* Subtle border */
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-content h1 {
            font-size: 24px; /* Slightly larger title */
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px; /* Increased gap */
            font-size: 14px;
            font-weight: 500;
        }

        .user-avatar {
            width: 36px; /* Slightly smaller avatar */
            height: 36px;
            border-radius: 50%;
            background-color: #007aff; /* Apple blue */
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        /* Navigation */
        nav {
            /* Darker nav background */
            background-color: #333; /* Dark gray */
            padding: 12px 0;
            margin-bottom: 30px; /* Add margin back here */
        }

        nav ul {
            display: flex;
            flex-wrap: wrap;
            list-style: none;
            gap: 10px 20px; /* Row and column gap */
            padding-left: 0;
            justify-content: flex-start; /* Align items to start */
        }

        nav li {
            margin-bottom: 5px;
        }

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

        /* Rest of the CSS from original file */
        /* ... */
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <h1>Gestion des Ouvriers</h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></span>
                <div class="user-avatar">
                    <?php 
                    // Generate initials from first and last name
                    echo htmlspecialchars(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1))); 
                    ?>
                </div>
            </div>
        </div>
    </header>
    <nav>
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="timesheet.php" class="active">Pointage</a></li>
                <li><a href="conges.php">Congés <span class="badge">2</span></a></li>
                <li><a href="arrets.php">Arrêts Maladie</a></li>
                <li><a href="employes.php">Employés</a></li>
                <li><a href="planning.php">Planning <span class="badge">3</span></a></li>
                <li><a href="chat.php">Chat <span class="badge">5</span></a></li>
                <li><a href="messages.php">Messages RH/Direction <span class="badge">1</span></a></li>
                <li><a href="logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </nav>

    <!-- Rest of the HTML from the original timesheet.html -->
    <!-- Include the original content here -->
    <div class="container">
        <div id="pointage">
            <h2>Pointage</h2>

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
                        <button class="btn-success" id="btn-entree" onclick="enregistrerPointage('entree')">Enregistrer Entrée</button>
                        <button class="btn-danger" id="btn-sortie" onclick="enregistrerPointage('sortie')">Enregistrer Sortie</button>
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
                        <tbody>
                             <tr>
                                <td>20/04/2025</td>
                                <td>08:02</td>
                                <td>Siège Social</td>
                                <td>17:30</td>
                                <td>Siège Social</td>
                                <td>8h28</td>
                                <td><button class="btn-primary" onclick="showMapModal('20/04/2025')">Voir carte</button></td>
                            </tr>
                            <tr>
                                <td>19/04/2025</td>
                                <td>07:55</td>
                                <td>Siège Social</td>
                                <td>17:15</td>
                                <td>Siège Social</td>
                                <td>8h20</td>
                                <td><button class="btn-primary" onclick="showMapModal('19/04/2025')">Voir carte</button></td>
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
                         <img src="https://placehold.co/600x350/e5e5e5/aaaaaa?text=Carte+Indisponible" alt="Carte de localisation" style="max-width: 100%; max-height: 100%;">
                    </div>
                    <div id="map-details">
                        <p><strong>Entrée:</strong> <span id="map-entree-time">--:--</span> - <span id="map-entree-loc">(Lieu)</span></p>
                        <p><strong>Sortie:</strong> <span id="map-sortie-time">--:--</span> - <span id="map-sortie-loc">(Lieu)</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the original JavaScript -->
    <script>
            // Clock Update function - unchanged
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
    
            // Enhanced Mobile-Friendly Geolocation
            function getLocation() {
                const locationStatus = document.getElementById('location-status');
                const locationAddress = document.getElementById('location-address');
    
                if (!locationStatus || !locationAddress) return; // Exit if elements not found
    
                // Update status to pending
                locationStatus.textContent = "Obtention...";
                locationStatus.className = "pending";
                locationAddress.textContent = ""; // Clear previous address
    
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
                            
                            locationStatus.textContent = "Position trouvée";
                            locationStatus.className = "success";
                            
                            // Display coordinates with accuracy information
                            locationAddress.textContent = `Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}`;
                            
                            // Check if location is accurate enough for business use
                            if (accuracy > 100) { // If accuracy is worse than 100 meters
                                locationAddress.textContent += `\n(Précision: ~${Math.round(accuracy)}m)`;
                            }
                            
                            // Store successful coordinates in session storage
                            storeLastLocation(lat, lon);
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
            function storeLastLocation(lat, lon) {
                try {
                    sessionStorage.setItem('lastLat', lat);
                    sessionStorage.setItem('lastLon', lon);
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
                    const lastTime = sessionStorage.getItem('lastLocationTime');
                    
                    if (lastLat && lastLon && lastTime) {
                        const timeDiff = (new Date() - new Date(lastTime)) / (1000 * 60); // minutes
                        if (timeDiff < 30) { // Use cached location if less than 30 minutes old
                            const locationAddress = document.getElementById('location-address');
                            const locationStatus = document.getElementById('location-status');
                            
                            locationStatus.textContent = "Position antérieure";
                            locationStatus.className = "warning"; // You may need to add this class to your CSS
                            
                            locationAddress.textContent = `Lat: ${parseFloat(lastLat).toFixed(6)}, Lon: ${parseFloat(lastLon).toFixed(6)}`;
                            locationAddress.textContent += `\n(Position d'il y a ${Math.round(timeDiff)} minutes)`;
                            
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
    
            // Record Time Entry - with improved mobile handling
            function enregistrerPointage(type) {
                const toggleLocation = document.getElementById('toggle-location');
                const locationStatus = document.getElementById('location-status');
                const locationAddress = document.getElementById('location-address');
                const currentTime = document.getElementById('current-time').textContent;
    
                let message = `${type === 'entree' ? 'Entrée' : 'Sortie'} enregistrée à ${currentTime}`;
                let lieu = "Non enregistré"; // Default lieu
    
                if (toggleLocation && toggleLocation.checked) {
                    if (locationStatus && (locationStatus.className === "success" || locationStatus.className === "warning") && 
                        locationAddress && locationAddress.textContent) {
                        lieu = locationAddress.textContent;
                        message += `\nLieu: ${lieu}`;
                    } else {
                        lieu = `Non disponible (${locationStatus ? locationStatus.textContent : 'Erreur'})`;
                        message += `\nLieu: ${lieu}`;
                        
                        // For mobile, offer tips if location failed
                        if (isMobileDevice()) {
                            message += "\n\nConseil: Si vous êtes sur mobile, vérifiez que le GPS est activé et que l'application a la permission d'accéder à votre position.";
                        }
                    }
                } else {
                    lieu = "Non enregistré (Localisation désactivée)";
                    message += `\nLieu: ${lieu}`;
                }
    
                alert(message);
                
                // Here you would add code to submit the data to your server
            }
    
            // All other existing functions remain the same
            function showMapModal(date) {
                // Existing code...
            }
            
            // Enhanced Event Listeners with mobile detection
            document.addEventListener('DOMContentLoaded', function() {
                const toggleLocation = document.getElementById('toggle-location');
                const statusText = document.getElementById('location-status-text');
                const locationStatusEl = document.getElementById('location-status');
                const locationAddressEl = document.getElementById('location-address');
                
                // Add a CSS class to body if on mobile
                if (isMobileDevice()) {
                    document.body.classList.add('mobile-device');
                }
    
                if (toggleLocation) {
                    toggleLocation.addEventListener('change', function() {
                        if (this.checked) {
                            if (statusText) {
                               statusText.textContent = "Activée";
                               statusText.style.color = "#34c759"; // Use Apple green
                            }
                            
                            // For mobile devices, show instruction alert
                            if (isMobileDevice()) {
                                setTimeout(() => {
                                    alert("Sur mobile, vous devrez peut-être autoriser l'accès à votre position. Vérifiez les paramètres de votre téléphone et les permissions du navigateur.");
                                }, 500);
                            }
                            
                            getLocation(); // Attempt to get location when toggled on
                        } else {
                            if (statusText) {
                                statusText.textContent = "Désactivée";
                                statusText.style.color = "#ff3b30"; // Use Apple red
                            }
                            if (locationStatusEl) {
                                locationStatusEl.textContent = "Désactivée";
                                locationStatusEl.className = ""; // Remove status class
                            }
                            if (locationAddressEl) {
                                locationAddressEl.textContent = ""; // Clear address
                            }
                        }
                    });
    
                    // Set initial text based on checked state
                    if (toggleLocation.checked) {
                        if (statusText) {
                            statusText.textContent = "Activée";
                            statusText.style.color = "#34c759";
                        }
                        
                        // If on a mobile device, add a slight delay before activating geolocation
                        // to allow the page to render completely first
                        if (isMobileDevice()) {
                            setTimeout(getLocation, 1000);
                        } else {
                            getLocation(); // Get initial location immediately on desktop
                        }
                    } else {
                        if (statusText) {
                           statusText.textContent = "Désactivée";
                           statusText.style.color = "#ff3b30";
                        }
                        if (locationStatusEl) locationStatusEl.textContent = "Désactivée";
                    }
                }
    
                // Add a refresh button for mobile
                if (isMobileDevice()) {
                    const locationInfo = document.getElementById('location-info');
                    if (locationInfo) {
                        const refreshBtn = document.createElement('button');
                        refreshBtn.className = 'btn-primary';
                        refreshBtn.style.marginTop = '10px';
                        refreshBtn.style.fontSize = '14px';
                        refreshBtn.style.padding = '8px 15px';
                        refreshBtn.textContent = 'Actualiser la position';
                        refreshBtn.onclick = function() {
                            if (toggleLocation && toggleLocation.checked) {
                                getLocation();
                            }
                        };
                        locationInfo.appendChild(refreshBtn);
                    }
                }
    
                // Modal close functionality - unchanged
                const modal = document.getElementById('map-modal');
                const closeButton = modal ? modal.querySelector('.close') : null;
                if(modal && closeButton) {
                    closeButton.onclick = function() {
                        modal.style.display = "none";
                    };
                    window.onclick = function(event) {
                        if (event.target == modal) {
                            modal.style.display = "none";
                        }
                    };
                }
            });
        </script>
</body>
</html>
