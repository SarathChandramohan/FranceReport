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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');
        
        :root {
            --primary-violet: #6A0DAD;
            --dark-background: #1e1e1e;
            --card-background: #ffffff;
            --text-dark: #333333;
            --text-light: #ffffff;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--dark-background);
            color: var(--text-light);
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            text-align: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background-color: var(--card-background);
            color: var(--text-dark);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            margin-top: -60px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            color: var(--primary-violet);
            font-weight: 700;
        }

        p {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .install-button {
            background-color: var(--primary-violet);
            color: var(--text-light);
            padding: 16px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease;
        }

        .install-button:hover {
            background-color: #5d089b;
            transform: translateY(-2px);
        }

        .instructions {
            text-align: left;
            margin-top: 20px;
        }

        .instructions h3 {
            font-size: 1.2rem;
            color: var(--primary-violet);
            margin-bottom: 15px;
            text-align: center;
        }

        .instructions ol {
            list-style: none;
            padding: 0;
            counter-reset: my-counter;
        }
        
        .instructions li {
            position: relative;
            margin-bottom: 25px;
            padding-left: 30px;
        }
        
        .instructions li:before {
            content: counter(my-counter);
            counter-increment: my-counter;
            position: absolute;
            left: 0;
            top: 0;
            background-color: var(--primary-violet);
            color: var(--text-light);
            font-weight: 700;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9rem;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/Logo.png" alt="Aircraft Cabin Leaders Logo">
        </div>
        <h1>Installer l'application</h1>
        
        <div id="android-instructions" class="hidden">
            <p>
                Cliquez sur le bouton ci-dessous pour installer <strong>Aircraft Cabin Leaders</strong> sur votre appareil Android.
            </p>
            <button id="install-button" class="install-button">Installer maintenant</button>
        </div>

        <div id="ios-instructions" class="hidden">
            <p>
                Pour installer l'application sur votre iPhone, suivez ces étapes simples :
            </p>
            <div class="instructions">
                <ol>
                    <li>
                        Appuyez sur l'icône de partage 
                        <br>
                        
                    </li>
                    <li>
                        Faites défiler vers le bas et sélectionnez <strong>"Sur l'écran d'accueil"</strong> 
                        <br>
                        
                    </li>
                    <li>
                        Confirmez l'installation en appuyant sur <strong>"Ajouter"</strong> en haut à droite.
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

        installButton.addEventListener('click', (e) => {
            androidInstructions.classList.add('hidden');
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                } else {
                    console.log('User dismissed the install prompt');
                }
                deferredPrompt = null;
            });
        });

        // Check if the user is on an iOS device
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isSafari = navigator.userAgent.includes('Safari') && !navigator.userAgent.includes('Chrome');

        if (isIOS && isSafari) {
            iosInstructions.classList.remove('hidden');
        } else if (!deferredPrompt) {
            // Optional: Hide the container or show a message if installation is not available
        }
    </script>
</body>
</html>
