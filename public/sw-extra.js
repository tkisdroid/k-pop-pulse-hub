/* Extra service-worker handlers merged into the generated Workbox SW. */
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || "/";
  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        const u = new URL(client.url);
        if (u.pathname === url || client.url.endsWith(url)) {
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    }),
  );
});

// Future hook for real Web Push: payload should be { title, body, url, icon }.
self.addEventListener("push", (event) => {
  if (!event.data) return;
  let payload = {};
  try {
    payload = event.data.json();
  } catch {
    payload = { title: "KpopBlog", body: event.data.text() };
  }
  const { title = "KpopBlog", body, url = "/", icon = "/icon-192.png" } = payload;
  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon,
      badge: "/icon-192.png",
      data: { url },
    }),
  );
});
