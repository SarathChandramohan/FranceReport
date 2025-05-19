<?php
require_once 'session-management.php';
requireLogin();
$user = getCurrentUser();

// Get current page filename to set active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Poppins', sans-serif;
    }

    .company-logo {
        height: 50px;
    }

    .navbar{
        background-color: #333333;
    }

    .nav-links a {
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    /* Remove border-radius to make it rectangular */
    border-radius: 0;
    margin: 0 5px;
    font-weight: 500;
    transition: background-color 0.3s;
}

.nav-links a.active,
.nav-links a:hover {
    background-color: #007bff;
    color: white;
    /* Remove transform to eliminate the lift effect */
    transform: none;
}

    .user-info-nav {
        color: white;
        display: flex;
        align-items: center;
        margin-left: 30px;
    }

    .user-avatar {
        background-color: #007bff;
        color: white;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: bold;
        margin-left: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .user-info-nav span {
        font-size: 16px;
    }
 

    .navbar-toggler {
        /*border-color: rgba(255,255,255,0.5);
        background-color: rgba(0,0,0,0.5);  Added background to make it visible */
    }

    /* Making the navbar collapse from left side */
    @media (max-width: 991px) {
        /* Mobile navbar layout adjustments */
        .navbar {
            padding: 10px 15px;
            justify-content: space-between;
        }
        
        .navbar-brand {
            margin: 0;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        /* User avatar on right side */
        .user-info-nav {
            order: 3;
            margin-left: 0;
            margin-right: 0;
        }
        
        .user-info-nav span {
            display: none;
        }
        
        /* Hamburger menu sliding from left */
        #navbarNav {
            position: fixed;
            top: 0;
            left: -300px; /* Changed from right to left */
            height: 100vh;
            width: 250px;
            background-color: #333;
            z-index: 1031;
            transition: left 0.3s ease; /* Changed from right to left */
            padding-top: 70px;
            box-shadow: 5px 0 15px rgba(0,0,0,0.2); /* Adjusted shadow direction */
            overflow-y: auto;
        }
        
        #navbarNav.show {
            left: 0; /* Changed from right to left */
        }
        
        .navbar-collapse.collapsing {
            height: 100vh !important;
            transition: left 0.3s ease; /* Changed from right to left */
            left: -300px; /* Changed from right to left */
        }
        
        .navbar-nav {
            padding-left: 15px;
        }
        
        .close-menu {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 1032;
        }
        
        /* User name at top of sidebar */
        .user-name-mobile {
            position: absolute;
            top: 15px;
            left: 0;
            width: 100%;
            padding: 10px 15px;
            font-size: 16px;
            color: white;
            font-weight: 500;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
    }
</style>

<nav class="navbar navbar-expand-lg px-3 py-3 sticky-top">
    <button class="navbar-toggler d-lg-none" type="button" data-toggle="collapse" data-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span style="font-size: 24px; color: white;">☰</span>
    </button>
    
    <a class="navbar-brand mx-auto mx-lg-0" href="#">
        <img src="Logo.png" alt="Company Logo" class="company-logo">
    </a>

    <div class="collapse navbar-collapse" id="navbarNav">
        <div class="user-name-mobile d-lg-none">
            <?php echo isset($user) ? htmlspecialchars($user['prenom'] . ' ' . $user['nom']) : ''; ?>
        </div>
        
        <span class="close-menu d-lg-none">&times;</span>
        <ul class="navbar-nav ml-auto nav-links">
            <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">Tableau de bord</a></li>
            <?php endif; ?>
            <li class="nav-item"><a href="timesheet.php" class="nav-link <?php echo $current_page == 'timesheet.php' ? 'active' : ''; ?>">Pointage</a></li>
            <li class="nav-item"><a href="conges.php" class="nav-link <?php echo $current_page == 'conges.php' ? 'active' : ''; ?>">Congés</a></li>
            <li class="nav-item"><a href="employes.php" class="nav-link <?php echo $current_page == 'employes.php' ? 'active' : ''; ?>">Employés</a></li>
            <li class="nav-item"><a href="planning.php" class="nav-link <?php echo $current_page == 'planning.php' ? 'active' : ''; ?>">Planning</a></li>
            <li class="nav-item"><a href="events.php" class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>">Événements</a></li>
            <li class="nav-item"><a href="messages.php" class="nav-link <?php echo $current_page == 'messages.php' ? 'active' : ''; ?>">Messages RH/Direction</a></li>
            <li class="nav-item"><a href="logout.php" class="nav-link <?php echo $current_page == 'logout.php' ? 'active' : ''; ?>">Déconnexion</a></li>
        </ul>
    </div>
    
    <div class="user-info-nav d-flex">
        <span class="d-none d-lg-block">
            <?php echo isset($user) ? htmlspecialchars($user['prenom'] . ' ' . $user['nom']) : ''; ?>
        </span>
        <div class="user-avatar">
            <?php
            if (isset($user)) {
                echo htmlspecialchars(strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)));
            } else {
                echo "??";
            }
            ?>
        </div>
    </div>
</nav>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Close menu when close button is clicked
    $('.close-menu').click(function() {
        $('#navbarNav').collapse('hide');
    });
    
    // Close menu when clicking outside
    $(document).click(function(event) {
        var clickover = $(event.target);
        var $navbar = $("#navbarNav");
        var _opened = $navbar.hasClass("show");
        
        if (_opened && !clickover.hasClass("navbar-toggler") && !clickover.closest("#navbarNav").length) {
            $navbar.collapse('hide');
        }
    });
});
</script>
