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
    :root {
        --theme-color-violet: #6A0DAD;
    }
    body {
        font-family: 'Inter', sans-serif;
    }
    .site-header {
        background-color: #ffffff;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e5e7eb;
        border-top: 3px solid #374151;
        width: 100%;
        position: sticky;
        top: 0;
        z-index: 1020;
    }
    .site-header .header-left .company-logo {
        height: 55px;
        width: auto;
        display: block;
    }
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
        background-color: var(--theme-color-violet);
        transition: all 0.3s ease-in-out;
        transform: translateX(-50%);
    }
    .site-header .header-center a:hover,
    .site-header .header-center a.active {
        color: var(--theme-color-violet);
    }
    .site-header .header-center a.active:after {
        width: 100%;
    }
    .site-header .header-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .site-header .user-menu-container {
        position: relative;
    }
    .site-header .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #374151;
        font-weight: 500;
        cursor: pointer;
    }
    .site-header .user-avatar {
        background-color: var(--theme-color-violet);
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
        display: none;
        position: absolute;
        top: 110%;
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
        white-space: nowrap;
        transition: all 0.2s ease-in-out;
    }
    .site-header .logout-button:hover {
        border-color: #374151;
        color: #111827;
        background-color: #f9fafb;
    }
    .site-header .user-menu-container:hover .logout-button {
        display: block;
    }
    .site-header .navbar-toggler {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #374151;
        cursor: pointer;
    }
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
        color: var(--theme-color-violet);
    }
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
    @media (max-width: 991.98px) {
        .site-header .header-center,
        .site-header .user-menu-container {
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

<?php if (isUserLoggedIn()): ?>
    <script src="/push-client.js"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggler = document.querySelector('.site-header .navbar-toggler');
    const mobileNav = document.getElementById('mobileNavPanel');
    const closeBtn = document.querySelector('.mobile-nav-panel .close-btn');
    const overlay = document.getElementById('mobileNavOverlay');

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
});
</script>
