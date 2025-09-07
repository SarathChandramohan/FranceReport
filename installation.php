<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Aircraft Cabin Leaders</title>
    
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#8A2BE2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="/Logo.png">
    <link rel="apple-touch-startup-image" href="/splashscreen.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-violet: #8A2BE2; /* BlueViolet */
            --primary-violet-dark: #7B24CB;
            --text-light: #f0f0f0;
            --text-dark: #1e1e1e;
            --card-background: rgba(30, 30, 30, 0.6);
            --border-color: rgba(255, 255, 255, 0.2);
        }

        /* --- Base & Body Styles --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            text-align: center;
            background: linear-gradient(135deg, #1d1d2d, #3c1e5a, #1d1d2d, #6a0dad);
            background-size: 400% 400%;
            animation: gradient-animation 15s ease infinite;
        }

        /* --- Animations --- */
        @keyframes gradient-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

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

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* --- Main Install Card --- */
        .install-card {
            background: var(--card-background);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* --- Logo & Typography --- */
        .logo img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            margin-bottom: 20px;
            box-shadow: 0 0 25px rgba(138, 43, 226, 0.5);
            animation: fadeInUp 0.5s 0.2s ease-out forwards;
            opacity: 0; /* Initially hidden for animation */
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
            animation: fadeInUp 0.5s 0.4s ease-out forwards;
            opacity: 0;
        }

        p {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            color: var(--text-light);
            animation: fadeInUp 0.5s 0.6s ease-out forwards;
            opacity: 0;
        }

        /* --- Install Button --- */
        .install-button {
            background: linear-gradient(45deg, var(--primary-violet), var(--primary-violet-dark));
            color: var(--text-light);
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            animation: pulse 2s infinite ease-in-out, fadeInUp 0.5s 0.8s ease-out forwards;
            opacity: 0;
        }

        .install-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(138, 43, 226, 0.6);
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
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 8px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .step-icon svg {
            width: 24px;
            height: 24px;
            fill: var(--text-light);
        }
        
        .instructions strong {
            color: white;
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
                font-size: 0.9rem;
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
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 2H7C5.89543 2 5 2.89543 5 4V20C5 21.1046 5.89543 22 7 22H17C18.1046 22 19 21.1046 19 20V4C19 2.89543 18.1046 2 17 2ZM12 18.5C11.313 18.5 10.75 17.937 10.75 17.25C10.75 16.563 11.313 16 12 16C12.687 16 13.25 16.563 13.25 17.25C13.25 17.937 12.687 18.5 12 18.5ZM12.75 13.25H11.25V7.75H9.5V6.25H14.5V7.75H12.75V13.25Z" transform="translate(-2 -2) scale(1.2)"/></svg>
                        </div>
                        <span>Tap the <strong>Share</strong> icon in the browser menu.</span>
                    </li>
                    <li>
                        <div class="step-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/></svg>
                        </div>
                        <span>Scroll down and select <strong>"Add to Home Screen"</strong>.</span>
                    </li>
                    <li>
                        <div class="step-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
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

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            androidInstructions.classList.remove('hidden');
        });

        if (installButton) {
            installButton.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`User response to the install prompt: ${outcome}`);
                    deferredPrompt = null;
                }
            });
        }
        
        // Check for iOS
        const isIOS = () => {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent);
        }
        
        // Check if the app is running in standalone mode (already installed)
        const isInStandaloneMode = () => ('standalone' in window.navigator) && (window.navigator.standalone);
        
        // Show the iOS instructions if on iOS and not in standalone mode
        if (isIOS() && !isInStandaloneMode()) {
            iosInstructions.classList.remove('hidden');
        }
    </script>
</body>
</html>
