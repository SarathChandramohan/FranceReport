// push-client.js

// --- IMPORTANT: PASTE YOUR VAPID PUBLIC KEY HERE ---
const VAPID_PUBLIC_KEY = 'BAoBqkEtJlTETgmJptadY56XGZpwxxlvf1R9N1ZYsy8em8FJkriA1HDmqGrpQTwg7OaVY51n7szFW1wv0zcoKjM';
// -------------------------------------------------

/**
 * Converts a base64 string to a Uint8Array.
 * This is a required step for the subscription process.
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Asks the user for permission and subscribes them to push notifications.
 */
async function subscribeUserToPush() {
    try {
        const registration = await navigator.serviceWorker.getRegistration();
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true, // This is required
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });

        console.log('User is subscribed:', subscription);
        
        // Send the complete subscription object to our PHP server.
        await sendSubscriptionToServer(subscription);

    } catch (error) {
        if (Notification.permission === 'denied') {
            console.warn('Notification permission was denied.');
        } else {
            console.error('Failed to subscribe the user: ', error);
        }
    }
}

/**
 * Sends the subscription object to our PHP backend to be saved.
 */
async function sendSubscriptionToServer(subscription) {
    try {
        const response = await fetch('/push-subscription-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(subscription),
        });
        if (!response.ok) {
            throw new Error('Bad response from server.');
        }
    } catch (error) {
        console.error('Error sending subscription to server: ', error);
    }
}

/**
 * Main function to initialize the entire push notification process.
 */
function initializePushNotifications() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn('Push messaging is not supported by this browser.');
        return;
    }

    // Register the service worker
    navigator.serviceWorker.register('/service-worker.js')
        .then(registration => {
            console.log('Service Worker registered successfully.');
            // Check if the user is already subscribed
            registration.pushManager.getSubscription().then(subscription => {
                if (subscription === null) {
                    // User is not subscribed yet.
                    console.log('User is not subscribed. Ready to subscribe.');
                    // You could optionally show a button here to let the user subscribe.
                    // For this app, we will subscribe them as soon as they grant permission.
                } else {
                    // User is already subscribed.
                    console.log('User is already subscribed.');
                }
            });
        })
        .catch(error => {
            console.error('Service Worker registration failed:', error);
        });

    // Ask for permission as soon as the page loads.
    // In a real app, you might want to delay this until after a user interaction.
    Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
            console.log('Notification permission granted.');
            subscribeUserToPush();
        }
    });
}

// Start the process
initializePushNotifications();
