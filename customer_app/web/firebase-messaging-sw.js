importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey:            'AIzaSyDxrEp_w7lmy2vLRmOmL55yYkcOErp3-V0',
  authDomain:        'esahlan-19f40.firebaseapp.com',
  projectId:         'esahlan-19f40',
  storageBucket:     'esahlan-19f40.firebasestorage.app',
  messagingSenderId: '855727793462',
  appId:             '1:855727793462:web:475eb2f5d77db9380cbf97',
});

const messaging = firebase.messaging();

// Background messages (app is minimized / browser closed)
messaging.onBackgroundMessage(function(payload) {
  const n = payload.notification || {};
  const title = n.title || 'eSahlan';
  const options = {
    body:  n.body  || '',
    icon:  '/icons/Icon-192.png',
    badge: '/icons/Icon-192.png',
    data:  payload.data || {},
    tag:   'esahlan-notification',
    renotify: true,
  };
  return self.registration.showNotification(title, options);
});

// Notification click — focus the PWA tab or open a new one
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const deepLink = event.notification.data && event.notification.data['deep_link'];
  const urlToOpen = deepLink ? ('/' + deepLink.replace(/^\//, '')) : '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (var i = 0; i < clientList.length; i++) {
        var client = clientList[i];
        if ('focus' in client) {
          client.focus();
          if (deepLink) client.navigate(urlToOpen);
          return;
        }
      }
      if (clients.openWindow) return clients.openWindow(urlToOpen);
    })
  );
});
