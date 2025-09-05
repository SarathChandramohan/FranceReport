<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer l'application - FranceReport</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#6A0DAD">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="/icon.png">
    <link rel="apple-touch-startup-image" href="/splashscreen.png">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            background-color: #222122;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            text-align: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            color: #1d1d1f;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            margin-top: -60px; /* Adjust to center content on screen */
        }
        h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .install-button {
            background-color: #007aff;
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease-in-out;
        }
        .install-button:hover {
            background-color: #0056b3;
        }
        .instructions {
            text-align: left;
            margin-top: 20px;
        }
        .instructions ol {
            padding-left: 20px;
        }
        .instructions li {
            margin-bottom: 10px;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Installer l'application</h1>
        
        <div id="android-instructions" class="hidden">
            <p>Cliquez sur le bouton ci-dessous pour installer l'application sur votre appareil Android.</p>
            <button id="install-button" class="install-button">Installer FranceReport</button>
        </div>

        <div id="ios-instructions" class="hidden">
            <p>
                Pour installer l'application sur votre iPhone, suivez ces étapes simples :
            </p>
            <div class="instructions">
                <ol>
                    <li>Appuyez sur l'icône de partage <br> </li>
                    <li>Faites défiler vers le bas et sélectionnez **"Sur l'écran d'accueil"** <br> </li>
                    <li>Confirmez l'installation en appuyant sur **"Ajouter"** en haut à droite.</li>
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
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later.
            deferredPrompt = e;
            // Show the Android instructions
            androidInstructions.classList.remove('hidden');
        });

        installButton.addEventListener('click', (e) => {
            // Hide the instructions for a cleaner UI
            androidInstructions.classList.add('hidden');
            // Show the browser's install prompt
            deferredPrompt.prompt();
            // Optional: Log the user's choice
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                } else {
                    console.log('User dismissed the install prompt');
                }
                deferredPrompt = null;
            });
        });

        // Check if the user is on an iOS device to show iOS-specific instructions
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isSafari = navigator.userAgent.includes('Safari') && !navigator.userAgent.includes('Chrome');

        if (isIOS && isSafari) {
            iosInstructions.classList.remove('hidden');
        } else if (!deferredPrompt) {
            // If not on iOS and the beforeinstallprompt event hasn't fired
            // (e.g., on a desktop browser or an unsupported browser),
            // you might want to show a message or hide the button.
        }
    </script>
</body>
</html>
