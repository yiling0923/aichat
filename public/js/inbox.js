// Staff inbox. Plain JavaScript; refreshes the list every 4 s and the open chat every 3 s.
(function () {
  var root = document.getElementById('inbox');
  var base = root.dataset.base; // e.g. .../admin/api
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var listEl = document.getElementById('conv-list');
  var logEl = document.getElementById('thread-log');
  var headEl = document.getElementById('thread-title');
  var metaEl = document.getElementById('thread-meta');
  var textEl = document.getElementById('reply-text');
  var statusSel = document.getElementById('status-filter');
  var current = null;
  var seen = {};
  var lastId = 0;

  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, opts.headers || {});
    if (opts.body && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    return fetch(base + path, opts).then(function (r) { return r.json(); });
  }

  function loadList() {
    api('/conversations?status=' + encodeURIComponent(statusSel.value)).then(function (data) {
      listEl.innerHTML = '';
      if (!data.conversations.length) {
        listEl.innerHTML = '<div class="empty">No conversations yet.<br>Open the test chat and send a message.</div>';
        return;
      }
      data.conversations.forEach(function (c) {
        var d = document.createElement('div');
        d.className = 'conv' + (current === c.id ? ' on' : '');
        var tag = c.mode === 'human' ? '<span class="tag warn">staff</span>' : '<span class="tag ok">bot</span>';
        d.innerHTML = '<b></b> ' + tag + '<p></p>';
        d.querySelector('b').textContent = c.name;
        d.querySelector('p').textContent = c.preview || '';
        d.addEventListener('click', function () { openConversation(c.id); });
        listEl.appendChild(d);
      });
    });
  }

  function addLine(m) {
    if (seen[m.id]) { return; }
    seen[m.id] = true;
    if (m.id > lastId) { lastId = m.id; }
    var div = document.createElement('div');
    var cls = m.private ? 'private' : m.from;
    div.className = 'm ' + cls;
    var prefix = m.private ? (m.from === 'system' ? '' : 'Private note: ') : '';
    div.appendChild(document.createTextNode(prefix + (m.text || '')));
    var t = document.createElement('small');
    t.textContent = m.from + (m.time ? ' · ' + new Date(m.time).toLocaleTimeString() : '');
    div.appendChild(t);
    logEl.appendChild(div);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function showMeta(c) {
    headEl.textContent = c.name;
    var mode = c.mode === 'human' ? 'Staff are handling this chat. The bot is silent.' : 'The bot is answering this chat.';
    metaEl.textContent = c.channel + ' · ' + c.status + ' · ' + mode + (c.handover_reason ? ' (' + c.handover_reason + ')' : '');
    document.getElementById('btn-give-back').style.display = c.mode === 'human' ? '' : 'none';
    document.getElementById('thread').classList.remove('hidden');
    document.getElementById('thread-empty').style.display = 'none';
  }

  function openConversation(id) {
    current = id; seen = {}; lastId = 0; logEl.innerHTML = '';
    refreshThread();
    loadList();
  }

  function refreshThread() {
    if (!current) { return; }
    api('/conversations/' + current + '?after=' + lastId).then(function (data) {
      if (!data.conversation) { return; }
      showMeta(data.conversation);
      data.messages.forEach(addLine);
    });
  }

  function post(path, body) {
    if (!current) { return Promise.resolve(); }
    return api('/conversations/' + current + path, { method: 'POST', body: body || {} });
  }

  document.getElementById('btn-reply').addEventListener('click', function () {
    var text = textEl.value.trim();
    if (!text) { return; }
    textEl.value = '';
    post('/reply', { text: text }).then(function () { refreshThread(); loadList(); });
  });
  document.getElementById('btn-note').addEventListener('click', function () {
    var text = textEl.value.trim();
    if (!text) { return; }
    textEl.value = '';
    post('/note', { text: text }).then(refreshThread);
  });
  document.getElementById('btn-give-back').addEventListener('click', function () {
    post('/give-back').then(function () { refreshThread(); loadList(); });
  });
  document.getElementById('btn-resolve').addEventListener('click', function () {
    post('/resolve').then(function () { refreshThread(); loadList(); });
  });
  statusSel.addEventListener('change', loadList);

  loadList();
  setInterval(loadList, 4000);
  setInterval(refreshThread, 3000);
})();
