// service-worker.js

// This event is triggered when a push message is received.
self.addEventListener('push', function(event) {
    console.log('[Service Worker] Push Received.');
    
    // The data sent from the server is in event.data.json()
    const data = event.data.json();
    
    const title = data.title || 'New Notification';
    const options = {
        body: data.body || 'Something new happened!',
        icon: data.icon || '/Logo.png', // Default icon
        badge: '/Logo.png' // Icon for Android notification bar
    };

    // This command shows the notification to the user.
    event.waitUntil(self.registration.showNotification(title, options));
});

// This event is triggered when the user clicks on the notification.
self.addEventListener('notificationclick', function(event) {
    console.log('[Service Worker] Notification click Received.');
    event.notification.close();
    
    // This opens the app's URL when the notification is clicked.
    event.waitUntil(
        clients.openWindow('/')
    );
});
