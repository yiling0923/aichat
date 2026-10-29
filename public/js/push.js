// Phone and desktop alerts (Web Push): register the service worker, ask permission, and tell the server about this device.
(function () {
  function meta(n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; }
  var key = meta('push-key'), base = meta('push-url'), swUrl = meta('push-sw'), csrf = meta('csrf-token');

  function supported() { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window; }
  function toBytes(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4), raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, function (c) { return c.charCodeAt(0); });
  }
  function post(path, body) {
    return fetch(base + path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); });
  }
  function registration() { return navigator.serviceWorker.register(swUrl); }

  function state() {
    if (!key) { return Promise.resolve('unconfigured'); }
    if (!supported()) { return Promise.resolve('unsupported'); }
    if (Notification.permission === 'denied') { return Promise.resolve('blocked'); }
    return navigator.serviceWorker.getRegistration(swUrl).then(function (r) { return r ? r.pushManager.getSubscription() : null; }).then(function (s) { return s ? 'on' : 'off'; }).catch(function () { return 'off'; });
  }

  function enable() {
    return Notification.requestPermission().then(function (perm) {
      if (perm !== 'granted') { return 'blocked'; }
      return registration().then(function () { return navigator.serviceWorker.ready; }).then(function (reg) {
        return reg.pushManager.getSubscription().then(function (s) { return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toBytes(key) }); });
      }).then(function (sub) { return post('/subscribe', sub.toJSON()); }).then(function (r) { return r && r.ok === false ? 'error' : 'on'; });
    }).catch(function () { return 'error'; });
  }

  function disable() {
    return navigator.serviceWorker.getRegistration(swUrl).then(function (r) { return r ? r.pushManager.getSubscription() : null; }).then(function (s) {
      if (!s) { return 'off'; }
      var endpoint = s.endpoint;
      return s.unsubscribe().then(function () { return post('/unsubscribe', { endpoint: endpoint }); }).then(function () { return 'off'; });
    }).catch(function () { return 'error'; });
  }

  window.aichatPush = { supported: supported, state: state, enable: enable, disable: disable, test: function () { return post('/test'); }, configured: !!key };
})();
