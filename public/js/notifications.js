// The bell: polls every 10 seconds, shows unread count, lists the latest, plays a sound for new ones, optional desktop alerts.
(function () {
  var root = document.getElementById('bell');
  if (!root) { return; }
  var url = root.dataset.url;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var btn = document.getElementById('bell-btn'), menu = document.getElementById('bell-menu'), badge = document.getElementById('bell-badge');
  var list = document.getElementById('bell-list'), desk = document.getElementById('bell-desktop');
  var snd = document.getElementById('bell-sound'), ctx = null, soundOn = true;
  try { soundOn = localStorage.getItem('aichat-sound') !== 'off'; } catch (e) { /* storage blocked: keep the default */ }

  // A short chime made in the browser, so there is no sound file to load. Browsers only allow sound after the person has
  // clicked or typed on the page once, so the first click or key press unlocks it.
  function audio() {
    if (!ctx) { var AC = window.AudioContext || window.webkitAudioContext; if (!AC) { return null; } ctx = new AC(); }
    if (ctx.state === 'suspended') { ctx.resume(); }
    return ctx;
  }
  ['pointerdown', 'keydown'].forEach(function (ev) { document.addEventListener(ev, function () { audio(); }, { once: true, passive: true }); });
  function chime(urgent) {
    var c = audio(); if (!c || c.state !== 'running') { return false; }
    var notes = urgent ? [880, 1175, 880, 1175] : [880, 1175];
    notes.forEach(function (f, i) {
      var t = c.currentTime + i * 0.16, o = c.createOscillator(), g = c.createGain();
      o.type = 'sine'; o.frequency.value = f;
      g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.28);
      o.connect(g); g.connect(c.destination); o.start(t); o.stop(t + 0.3);
    });
    return true;
  }
  function soundLabel() { snd.textContent = soundOn ? 'Sound is on' : 'Sound is off'; snd.classList.toggle('off', !soundOn); }
  snd.addEventListener('click', function () {
    soundOn = !soundOn; try { localStorage.setItem('aichat-sound', soundOn ? 'on' : 'off'); } catch (e) { /* ignore */ }
    soundLabel(); if (soundOn) { chime(false); }
  });
  soundLabel();

  var baseTitle = document.title, known = null, icons = { handover: '!', message: '\u2709', booking: '\u25A6', survey: '\u2605', plan: '$', task: '\u2713' };

  function ago(iso) {
    var s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
    if (s < 60) { return 'now'; } if (s < 3600) { return Math.floor(s / 60) + 'm'; }
    if (s < 86400) { return Math.floor(s / 3600) + 'h'; } return Math.floor(s / 86400) + 'd';
  }

  function post(path) { return fetch(url + path, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } }); }

  function render(d) {
    badge.hidden = d.unread === 0;
    badge.textContent = d.unread > 9 ? '9+' : d.unread;
    document.title = (d.unread ? '(' + d.unread + ') ' : '') + baseTitle.replace(/^\(\d+\) /, '');
    list.innerHTML = '';
    if (!d.items.length) { list.innerHTML = '<p class="bell-empty">Nothing yet. New chats, bookings and tasks show up here.</p>'; }
    d.items.forEach(function (n) {
      var a = document.createElement('a');
      a.href = n.url || '#'; a.className = 'bell-item' + (n.read ? '' : ' unread');
      a.innerHTML = '<span class="bi"></span><span class="bt"><b></b><small></small></span><time></time>';
      a.querySelector('.bi').textContent = icons[n.type] || '\u2022';
      a.querySelector('b').textContent = n.title;
      a.querySelector('small').textContent = n.body || '';
      a.querySelector('time').textContent = ago(n.time);
      a.addEventListener('click', function (e) {
        e.preventDefault();
        post('/' + n.id + '/read').finally(function () { if (n.url) { location.href = n.url; } else { load(); } });
      });
      list.appendChild(a);
    });
    // Desktop alert for anything new since the last check (not on the first load).
    var ids = d.items.filter(function (n) { return !n.read; }).map(function (n) { return n.id + ':' + n.time; });
    if (known !== null) {
      var fresh = d.items.filter(function (n) { return !n.read && known.indexOf(n.id + ':' + n.time) === -1; });
      if (fresh.length) {
        if (soundOn) { chime(fresh.some(function (n) { return n.type === 'handover'; })); }
        btn.classList.add('ring'); setTimeout(function () { btn.classList.remove('ring'); }, 1500);
      }
      if ('Notification' in window && Notification.permission === 'granted') {
        fresh.slice(0, 3).forEach(function (n) {
          try { new Notification(n.title, { body: n.body || '' }); } catch (e) { /* not allowed here */ }
        });
      }
    }
    known = ids;
  }

  function load() { fetch(url, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) { render(d); } }).catch(function () {}); }

  // Place the menu under the bell but always inside the screen, whatever column the bell is in (the Inbox list is narrow).
  function place() {
    var r = btn.getBoundingClientRect(), w = Math.min(380, window.innerWidth - 16);
    var left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8));
    menu.style.position = 'fixed'; menu.style.width = w + 'px'; menu.style.left = left + 'px'; menu.style.right = 'auto';
    menu.style.top = (r.bottom + 8) + 'px';
    menu.querySelector('.bell-list').style.maxHeight = Math.max(160, window.innerHeight - r.bottom - 130) + 'px';
  }
  window.addEventListener('resize', function () { if (!menu.hidden) { place(); } });

  btn.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; if (!menu.hidden) { place(); load(); } });
  document.addEventListener('click', function (e) { if (!e.target.closest('#bell')) { menu.hidden = true; } });
  document.getElementById('bell-all').addEventListener('click', function () { post('/read-all').then(load); });

  // With phone alerts set up on the server the button turns them on for this device; otherwise it is the old desktop-only alert.
  var P = window.aichatPush;
  function deskLabel() {
    if (P && P.configured && P.supported()) {
      P.state().then(function (st) {
        desk.hidden = false;
        desk.textContent = { on: 'Phone alerts are on (turn off)', off: 'Turn on phone alerts', blocked: 'Alerts are blocked in your browser', unsupported: 'Alerts not supported here', error: 'Turn on phone alerts' }[st] || 'Turn on phone alerts';
        desk.disabled = st === 'blocked' || st === 'unsupported';
        desk.dataset.state = st;
      });
      return;
    }
    if (!('Notification' in window)) { desk.hidden = true; return; }
    desk.textContent = Notification.permission === 'granted' ? 'Desktop alerts are on' : (Notification.permission === 'denied' ? 'Desktop alerts are blocked in your browser' : 'Turn on desktop alerts');
    desk.disabled = Notification.permission !== 'default';
  }
  desk.addEventListener('click', function () {
    if (P && P.configured && P.supported()) { (desk.dataset.state === 'on' ? P.disable() : P.enable()).then(deskLabel); return; }
    Notification.requestPermission().then(deskLabel);
  });
  deskLabel();

  load();
  setInterval(load, 10000);
})();
