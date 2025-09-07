<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Aircraft Cabin Leaders</title>
    
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#6A0DAD">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/Logo.png">
    <link rel="apple-touch-startup-image" href="/splashscreen.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #6A0DAD;     /* Your specified primary purple */
            --primary-hover: #520a86;     /* Your specified darker purple for hover */
            --light-bg: #f9fafb;
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #d1d5db;
            /* Error and success colors as per your theme */
            --error-bg: rgba(239, 68, 68, 0.1);
            --error-border: rgba(239, 68, 68, 0.3);
            --error-text: #b91c1c;
            --success-bg: rgba(34, 197, 94, 0.1);
            --success-border: rgba(34, 197, 94, 0.3);
            --success-text: #15803d;

            --shadow-color: rgba(0, 0, 0, 0.1); /* Soft, professional shadow */
        }

        /* --- Base & Body Styles --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-secondary); /* Using your text-secondary for general body text */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            text-align: center;
            /* Professional aviation-themed background */
            background-image: url('https://images.unsplash.com/photo-1542296332-9e5433553492?q=80&w=2070&auto=format&fit=crop'); /* High-res aviation background */
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }
        
        /* Add a subtle overlay to ensure text readability and hint at purple */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(106, 13, 173, 0.4), rgba(106, 13, 173, 0.2)); /* Purple gradient overlay */
            backdrop-filter: blur(3px); /* Slightly stronger blur for depth */
            -webkit-backdrop-filter: blur(3px);
        }

        /* --- Animations --- */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* --- Main Install Card --- */
        .install-card {
            background: var(--card-bg); /* Using your card-bg variable */
            padding: 40px;
            border-radius: 20px;
            border: 1px solid var(--border-color); /* Using your border-color variable */
            box-shadow: 0 10px 40px var(--shadow-color);
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.6s ease-out forwards;
            position: relative; /* Ensure card is on top of the overlay */
            z-index: 1;
        }

        /* --- Logo & Typography --- */
        .logo img {
            width: 80px;
            height: 80px;
            border-radius: 16px; /* Squircle shape is more modern */
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.5s 0.2s ease-out forwards;
            opacity: 0;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary); /* Using your text-primary for headings */
            animation: fadeInUp 0.5s 0.4s ease-out forwards;
            opacity: 0;
        }

        p {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            color: var(--text-secondary); /* Using your text-secondary for paragraphs */
            animation: fadeInUp 0.5s 0.6s ease-out forwards;
            opacity: 0;
        }

        /* --- Install Button --- */
        .install-button {
            background: var(--primary-color); /* Your primary purple color */
            color: var(--card-bg); /* White text on button */
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            box-shadow: 0 4px 20px rgba(106, 13, 173, 0.4); /* Purple shadow for button */
            animation: fadeInUp 0.5s 0.8s ease-out forwards;
            opacity: 0;
        }

        .install-button:hover {
            transform: translateY(-3px);
            background-color: var(--primary-hover); /* Darker purple on hover */
            box-shadow: 0 8px 25px rgba(106, 13, 173, 0.5);
        }

        /* --- iOS Instructions --- */
        .instructions ol {
            list-style: none;
            padding: 0;
            text-align: left;
            margin-top: 20px;
        }
        
        .instructions li {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeInUp 0.5s ease-out forwards;
        }
        
        /* Staggered animation for list items */
        .instructions li:nth-child(1) { animation-delay: 0.8s; }
        .instructions li:nth-child(2) { animation-delay: 1.0s; }
        .instructions li:nth-child(3) { animation-delay: 1.2s; }

        .step-icon {
            background-color: var(--light-bg); /* Using your light-bg for step icons */
            border-radius: 8px;
            padding: 10px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .step-icon svg {
            width: 24px;
            height: 24px;
            fill: var(--primary-color); /* Purple icons */
        }
        
        .instructions strong {
            color: var(--text-primary); /* Primary text color for strong tags */
            font-weight: 500;
        }

        /* --- Utility & Responsive --- */
        .hidden {
            display: none;
        }

        @media (max-width: 576px) {
            .install-card {
                padding: 30px 25px;
            }
            h1 {
                font-size: 1.8rem;
            }
            p {
                font-size: 0.95rem;
            }
            .instructions li {
                align-items: flex-start; /* Better alignment on small screens */
            }
        }
    </style>
</head>
<body>
    
    <div class="install-card">
        <div class="logo">
            <img src="/Logo.png" alt="Aircraft Cabin Leaders Logo">
        </div>
        <h1>Install the App</h1>
        
        <div id="android-instructions" class="hidden">
            <p>
                Get the full experience. Install the <strong>Aircraft Cabin Leaders</strong> app on your device with a single click.
            </p>
            <button id="install-button" class="install-button">Install Now</button>
        </div>

        <div id="ios-instructions" class="hidden">
            <p>
                To install the app on your iPhone, please follow these simple steps:
            </p>
            <div class="instructions">
                <ol>
                    <li>
                        <div class="step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M13 14h-2a8.999 8.999 0 0 0-7.968 4.81A10.004 10.004 0 0 1 12 2c2.79 0 5.337.89 7.468 2.395-.783-.862-1.875-1.395-3.068-1.395A4.4 4.4 0 0 0 12 5.4a4.4 4.4 0 0 0-4.4 4.4c0 1.193.533 2.285 1.395 3.068A8.995 8.995 0 0 0 13 14zm-1 2c-3.309 0-6 2.691-6 6h12c0-3.309-2.691-6-6-6zm-6.18-7.968A6.402 6.402 0 0 1 12 7.4a6.402 6.402 0 0 1 6.18 4.632A8.99 8.99 0 0 0 19 12a9 9 0 1 0-14.18-6.968z"/></svg>
                        </div>
                        <span>Tap the <strong>Share</strong> icon in your browser's menu.</span>
                    </li>
                    <li>
                        <div class="step-icon">
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>
                        </div>
                        <span>Scroll down and select <strong>"Add to Home Screen"</strong>.</span>
                    </li>
                    <li>
                        <div class="step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"/></svg>
                        </div>
                        <span>Confirm by tapping <strong>"Add"</strong> in the top-right corner.</span>
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        let deferredPrompt;
        const installButton = document.getElementById('install-button');
        const androidInstructions = document.getElementById('android-instructions');
        const iosInstructions = document.getElementById('ios-instructions');

        // Check for iOS
        const isIOS = () => {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent) && !window.MSStream;
        }
        
        // Check if the app is running in standalone mode (already installed)
        const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);

        // --- Redirect if already installed ---
        if (isInStandaloneMode()) {
            console.log('App is already installed and running in standalone mode. Redirecting to /index.php');
            window.location.replace('/index.php');
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            // Only show android instructions if not on iOS AND not already installed
            if (!isIOS() && !isInStandaloneMode()) {
                androidInstructions.classList.remove('hidden');
            }
        });

        if (installButton) {
            installButton.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`User response to the install prompt: ${outcome}`);
                    if (outcome === 'accepted') {
                        // User accepted the prompt, likely installing. You might want to redirect after a short delay.
                        console.log('User accepted the PWA installation prompt.');
                        // Optional: Redirect after successful install, though browser handles the app launch.
                        // setTimeout(() => window.location.replace('/index.php'), 1000); 
                    }
                    deferredPrompt = null;
                }
            });
        }
        
        // Show the iOS instructions if on iOS and not in standalone mode
        if (isIOS() && !isInStandaloneMode()) {
            iosInstructions.classList.remove('hidden');
            // Hide Android instructions just in case (e.g., if beforeinstallprompt fired then iOS check runs)
            androidInstructions.classList.add('hidden'); 
        }

        // Fallback or additional logic if needed
        window.addEventListener('load', () => {
            // If neither Android nor iOS instructions are shown, and not in standalone mode,
            // it means the device might not support PWA installation or it's a desktop browser.
            // You could add a message for desktop users here, e.g., "Access on desktop, install on mobile."
            if (androidInstructions.classList.contains('hidden') && iosInstructions.classList.contains('hidden') && !isInStandaloneMode()) {
                console.log('No specific install instructions shown. Possibly desktop browser or PWA not supported.');
                // Example: document.querySelector('.install-card p').innerText = "Access the full experience on your desktop, or install the app on a compatible mobile device.";
            }
        });
    </script>
</body>
</html>
