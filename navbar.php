<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
$home_page = (isset($user['role']) && $user['role'] === 'admin') ? 'dashboard.php' : 'timesheet.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /*
     * CSS for the new site header.
     * All rules are prefixed with '.site-header' to prevent conflicts with other page styles.
     */
    
    /* Define violet theme color */
    :root {
        --theme-color-violet: #6A0DAD; /* Premium Violet */
    }

    body {
        /* Apply font to the whole page for consistency */
        font-family: 'Inter', sans-serif;
    }

    .site-header {
        background-color: #ffffff;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
        border-top: 3px solid #374151; /* Dark grey top border */
        width: 100%;
        position: sticky;
        top: 0;
        z-index: 1020;
    }

    /* Left Side: Logo */
    .site-header .header-left .company-logo {
        height: 55px;
        width: auto;
        display: block;
    }

    /* Center: Navigation Links */
    .site-header .header-center {
        display: flex;
        align-items: center;
        gap: 2rem;
    }

    .site-header .header-center a {
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        color: #4b5563;
        font-weight: 500;
        font-size: 0.95rem;
        padding: 0.5rem 0;
        transition: color 0.2s ease-in-out;
        position: relative;
    }

    .site-header .header-center a:after {
        content: '';
        position: absolute;
        width: 0;
        height: 2px;
        bottom: 0;
        left: 50%;
        background-color: var(--theme-color-violet); /* Violet underline */
        transition: all 0.3s ease-in-out;
        transform: translateX(-50%);
    }

    .site-header .header-center a:hover,
    .site-header .header-center a.active {
        color: var(--theme-color-violet); /* Violet for hover and active states */
    }

    .site-header .header-center a.active:after {
        width: 100%;
    }

    /* Right Side: User Info & Actions */
    .site-header .header-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    /* NEW: Styles for Notification Bell */
    .site-header .notification-bell-container {
        position: relative;
    }
    .site-header .notification-bell {
        position: relative;
        cursor: pointer;
        color: #4b5563;
        font-size: 1.2rem;
    }
    .site-header .notification-bell:hover {
        color: var(--theme-color-violet);
    }
    .site-header .notification-badge {
        position: absolute;
        top: -5px;
        right: -8px;
        background-color: red;
        color: white;
        border-radius: 50%;
        padding: 0.15em 0.45em;
        font-size: 0.7rem;
        font-weight: bold;
        display: none; /* Hidden by default */
    }
    
    /* NEW: Container for the user menu hover effect */
    .site-header .user-menu-container {
        position: relative; /* Needed for positioning the logout button */
    }

    .site-header .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #374151;
        font-weight: 500;
        cursor: pointer; /* Indicates it's interactive */
    }

    .site-header .user-avatar {
        background-color: var(--theme-color-violet); /* Violet avatar background */
        color: white;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .site-header .logout-button {
        display: none; /* Hide logout button by default */
        position: absolute;
        top: 110%; /* Position below the user info */
        right: 0;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        color: #374151;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        white-space: nowrap; /* Prevent "Déconnexion" from wrapping */
        transition: all 0.2s ease-in-out;
    }
    
    .site-header .logout-button:hover {
        border-color: #374151;
        color: #111827;
        background-color: #f9fafb;
    }
    
    /* NEW: Show logout button on hover of the container */
    .site-header .user-menu-container:hover .logout-button {
        display: block;
    }

    /* Mobile Hamburger Toggler */
    .site-header .navbar-toggler {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #374151;
        cursor: pointer;
    }

    /* Mobile Navigation Panel */
    .mobile-nav-panel {
        position: fixed;
        top: 0;
        left: -300px;
        width: 280px;
        height: 100%;
        background-color: #ffffff;
        z-index: 1030;
        transition: left 0.3s ease-in-out;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        padding: 1.5rem;
    }

    .mobile-nav-panel.show {
        left: 0;
    }
    
    .mobile-nav-panel .close-btn {
        background: none;
        border: none;
        font-size: 1.8rem;
        color: #6b7280;
        position: absolute;
        top: 1rem;
        right: 1.5rem;
        cursor: pointer;
    }

    .mobile-nav-panel .mobile-nav-links {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 3rem;
    }

    .mobile-nav-panel .mobile-nav-links a {
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        font-size: 1.1rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        transition: background-color 0.2s;
    }
    
    .mobile-nav-panel .mobile-nav-links a:hover,
    .mobile-nav-panel .mobile-nav-links a.active {
        background-color: #f3f4f6;
        color: var(--theme-color-violet); /* Violet for mobile active state */
    }
    
    /* Overlay for when mobile menu is open */
    .mobile-nav-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
        z-index: 1025;
        display: none;
    }
    
    .mobile-nav-overlay.show {
        display: block;
    }

    /* NEW: Notification Popup Styles */
    #notification-popup {
        display: none;
        position: fixed;
        bottom: 20px;
        left: 20px;
        background-color: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        z-index: 1050;
        max-width: 350px;
    }
    #notification-popup h5 {
        margin-top: 0;
        font-weight: 600;
    }
    #notification-popup .popup-buttons {
        margin-top: 15px;
        display: flex;
        gap: 10px;
    }
    #notification-popup button {
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
    }
    #notification-popup #enable-notifications {
        background-color: var(--theme-color-violet);
        color: white;
    }
    #notification-popup #disable-notifications {
        background-color: #e5e7eb;
        color: #374151;
    }
    
    /* NEW: Styles for notification dropdown */
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        padding: 0;
        z-index: 1000;
        transform: translateY(10px);
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease-in-out;
    }
    .notification-dropdown.show {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }
    .notification-dropdown h6 {
        margin: 0;
        padding: 15px;
        font-weight: 600;
        border-bottom: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }
    .notification-item {
        display: block;
        padding: 15px;
        text-decoration: none;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
    }
    .notification-item.unread {
        background-color: #f0f8ff;
        font-weight: 600;
    }
    .notification-item:hover {
        background-color: #f3f4f6;
    }
    .notification-message {
        font-size: 0.9rem;
    }
    .notification-date {
        font-size: 0.8rem;
        color: #6b7280;
        margin-top: 5px;
    }
    .notification-item:last-child {
        border-bottom: none;
    }
    .no-notifications {
        padding: 20px;
        text-align: center;
        color: #6b7280;
    }
    
    /* NEW: Styles for Push Notification Controls */
    .push-notification-controls {
        display: flex;
        flex-direction: column;
        padding: 15px;
        border-top: 1px solid #e5e7eb;
        gap: 10px;
    }

    .push-notification-controls .toggle-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .push-notification-controls .toggle-label {
        font-size: 0.9rem;
        color: #374151;
        font-weight: 500;
    }

    .push-notification-controls .toggle-switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 20px;
    }

    .push-notification-controls .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .push-notification-controls .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 20px;
    }

    .push-notification-controls .toggle-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    .push-notification-controls input:checked + .toggle-slider {
        background-color: #34c759;
    }

    .push-notification-controls input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }
    
    .push-notification-controls .unsubscribe-btn {
        width: 100%;
        background-color: #ff3b30;
        color: white;
        border: none;
        padding: 8px;
        border-radius: 5px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .push-notification-controls .unsubscribe-btn:hover {
        background-color: #d63027;
    }


    /* Hide/show elements based on screen size */
    @media (max-width: 991.98px) {
        .site-header .header-center,
        .site-header .user-menu-container,
        .site-header .notification-bell-container { /* Hide new elements on mobile too */
            display: none;
        }
    }
    
    @media (min-width: 992px) {
        .site-header .navbar-toggler {
            display: none;
        }
    }
</style>

<nav class="site-header">
    <div class="header-left">
        <a href="<?php echo $home_page; ?>">
            <img src="Logo.png" alt="Company Logo" class="company-logo">
        </a>
    </div>

    <div class="header-center">
        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">Administrateur</a>
        <?php endif; ?>
        <a href="timesheet.php" class="<?php echo $current_page == 'timesheet.php' ? 'active' : ''; ?>">Pointage</a>
        <a href="conges.php" class="<?php echo $current_page == 'conges.php' ? 'active' : ''; ?>">Congés</a>
        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
            <a href="planning.php" class="<?php echo $current_page == 'planning.php' ? 'active' : ''; ?>">Planning</a>
            <a href="inventory.php" class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">Inventaire</a>
        <?php else: ?>
            <a href="seeplanning.php" class="<?php echo $current_page == 'seeplanning.php' ? 'active' : ''; ?>">Mission</a>
        <?php endif; ?>
        <a href="technician.php" class="<?php echo $current_page == 'technician.php' ? 'active' : ''; ?>">véhicules / outillage</a>
        <a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>">Messages</a>
        <a href="events.php" class="<?php echo $current_page == 'events.php' ? 'active' : ''; ?>">Événements</a>
    </div>

    <div class="header-right">
        <div id="notification-bell-container" class="notification-bell-container">
            <div id="notification-bell" class="notification-bell">
                <i class="fas fa-bell"></i>
                <span id="notification-badge" class="notification-badge"></span>
            </div>
            <div id="notification-dropdown" class="notification-dropdown">
                <h6>Notifications</h6>
                <div id="notification-list">
                    </div>
                 <div class="push-notification-controls">
                    <div class="toggle-container">
                        <span class="toggle-label">Notifications push</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="push-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <button class="unsubscribe-btn" id="unsubscribe-btn">Se désabonner de tout</button>
                </div>
            </div>
        </div>
        
        <div class="user-menu-container">
            <div class="user-info">
                <span class="user-name"><?php echo isset($user) ? htmlspecialchars($user['prenom'] . ' ' . $user['nom']) : ''; ?></span>
                <div class="user-avatar">
                    <?php echo isset($user) ? htmlspecialchars(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1))) : '??'; ?>
                </div>
            </div>
            <a href="logout.php" class="logout-button">Déconnexion</a>
        </div>
        
        <button class="navbar-toggler" type="button" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<div class="mobile-nav-panel" id="mobileNavPanel">
    <button class="close-btn" aria-label="Close navigation">&times;</button>
    <div class="mobile-nav-links">
        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">Administrateur</a>
        <?php endif; ?>
        <a href="timesheet.php" class="<?php echo $current_page == 'timesheet.php' ? 'active' : ''; ?>">Pointage</a>
        <a href="conges.php" class="<?php echo $current_page == 'conges.php' ? 'active' : ''; ?>">Congés</a>
        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
            <a href="planning.php" class="<?php echo $current_page == 'planning.php' ? 'active' : ''; ?>">Planning</a>
            <a href="inventory.php" class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">Inventaire</a>
        <?php else: ?>
            <a href="seeplanning.php" class="<?php echo $current_page == 'seeplanning.php' ? 'active' : ''; ?>">Mission</a>
        <?php endif; ?>
        <a href="technician.php" class="<?php echo $current_page == 'technician.php' ? 'active' : ''; ?>">véhicules / outillage</a>
        <a href="messages.php" class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>">Messages</a>
        <a href="events.php" class="<?php echo $current_page == 'events.php' ? 'active' : ''; ?>">Événements</a>
        <hr>
        <a href="logout.php">Déconnexion</a>
    </div>
</div>

<div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

<div id="notification-popup">
    <h5>Activer les notifications</h5>
    <p>Souhaitez-vous recevoir des notifications pour les nouvelles demandes ?</p>
    <div class="popup-buttons">
        <button id="enable-notifications">Oui, activer</button>
        <button id="disable-notifications">Non, merci</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggler = document.querySelector('.site-header .navbar-toggler');
        const mobileNav = document.getElementById('mobileNavPanel');
        const closeBtn = document.querySelector('.mobile-nav-panel .close-btn');
        const overlay = document.getElementById('mobileNavOverlay');
        const bellIcon = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');

        function openMobileNav() {
            if (mobileNav && overlay) {
                mobileNav.classList.add('show');
                overlay.classList.add('show');
            }
        }

        function closeMobileNav() {
            if (mobileNav && overlay) {
                mobileNav.classList.remove('show');
                overlay.classList.remove('show');
            }
        }

        if (toggler) {
            toggler.addEventListener('click', openMobileNav);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeMobileNav);
        }
        if (overlay) {
            overlay.addEventListener('click', closeMobileNav);
        }
        
        // Notification Bell Toggle
        if (bellIcon) {
            bellIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                } else {
                    loadNotifications();
                    dropdown.classList.add('show');
                }
            });
        }
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target) && !bellIcon.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Function to load notifications via AJAX
        function loadNotifications() {
            const notificationList = $('#notification-list');
            notificationList.html('<div class="no-notifications">Chargement...</div>');

            $.ajax({
                url: 'notifications-handler.php',
                type: 'GET',
                data: { action: 'get_notifications' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        renderNotifications(response.data);
                        updateBadge(response.unread_count);
                        markNotificationsAsRead(); // Mark them as read once the list is loaded
                    } else {
                        notificationList.html('<div class="no-notifications text-danger">Erreur de chargement.</div>');
                    }
                },
                error: function() {
                    notificationList.html('<div class="no-notifications text-danger">Erreur de communication.</div>');
                }
            });
        }
        
        // Function to render notifications in the dropdown
        function renderNotifications(notifications) {
            const notificationList = $('#notification-list');
            notificationList.empty();

            if (notifications.length === 0) {
                notificationList.html('<div class="no-notifications">Aucune notification.</div>');
                return;
            }

            notifications.forEach(function(notif) {
                const itemClass = notif.is_read == 0 ? 'notification-item unread' : 'notification-item';
                const link = notif.link || '#';
                notificationList.append(`
                    <a href="${link}" class="${itemClass}">
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-date">${new Date(notif.created_at).toLocaleString('fr-FR')}</div>
                    </a>
                `);
            });
        }
        
        // Function to update the unread count badge
        function updateBadge(count) {
            const badge = $('#notification-badge');
            if (count > 0) {
                badge.text(count);
                badge.show();
            } else {
                badge.hide();
            }
        }
        
        // Function to mark all notifications as read via AJAX
        function markNotificationsAsRead() {
            $.ajax({
                url: 'notifications-handler.php',
                type: 'POST',
                data: { action: 'mark_as_read' },
                success: function() {
                    updateBadge(0);
                }
            });
        }

        // Initial fetch of the unread count on page load
        function fetchUnreadCount() {
            $.ajax({
                url: 'notifications-handler.php',
                type: 'GET',
                data: { action: 'get_notifications' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        updateBadge(response.unread_count);
                    }
                }
            });
        }
        fetchUnreadCount();
        
        // Poll for new notifications every minute
        setInterval(fetchUnreadCount, 60000);

    });
</script>

<?php if (isset($user)): ?>
    <script src="push-client.js"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pushToggle = document.getElementById('push-toggle');
    const unsubscribeBtn = document.getElementById('unsubscribe-btn');

    // Function to check and update the toggle state
    function updatePushToggleState() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            console.warn("Push notifications not supported.");
            pushToggle.disabled = true;
            return;
        }

        pushToggle.disabled = false;
        if (Notification.permission === 'granted') {
            pushToggle.checked = true;
        } else {
            pushToggle.checked = false;
        }
    }

    // Handle toggle switch change
    pushToggle.addEventListener('change', function() {
        if (this.checked) {
            // User wants to enable notifications
            if (Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        // User granted permission, now subscribe
                        subscribeUserToPush();
                    } else {
                        // User denied or dismissed, revert toggle
                        this.checked = false;
                        alert("Permission refusée. Les notifications push ne seront pas activées.");
                    }
                });
            } else if (Notification.permission === 'denied') {
                this.checked = false;
                alert("La permission de notification a été refusée. Veuillez l'activer dans les paramètres de votre navigateur.");
            } else {
                 // Already granted, just ensure subscription is active
                 subscribeUserToPush();
            }
        } else {
            // User wants to disable notifications
            // Note: This only unsubscribes, it doesn't change browser permission
            unsubscribeFromPush();
        }
    });

    // Handle the "Unsubscribe" button click
    unsubscribeBtn.addEventListener('click', function() {
        unsubscribeFromPush();
        alert("Vous êtes désabonné de toutes les notifications push.");
    });
    
    // Unsubscribe from push notifications and delete from server
    async function unsubscribeFromPush() {
        const registration = await navigator.serviceWorker.getRegistration();
        if (!registration) {
            console.warn('No service worker registration found.');
            return;
        }
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
             // Send a request to the server to delete the subscription
            await fetch('push-subscription-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'unsubscribe', endpoint: subscription.endpoint }),
            });
            // Unsubscribe from the push service
            await subscription.unsubscribe();
        }
        // Always reset the toggle and page permission state
        pushToggle.checked = false;
    }
    
    // Initial check on page load
    updatePushToggleState();
});
</script>
