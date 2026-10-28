// Customer test chat. Plain JavaScript, polls for new messages every 3 seconds.
(function () {
  var root = document.getElementById('chat');
  var log = document.getElementById('chat-log');
  var form = document.getElementById('chat-form');
  var input = document.getElementById('chat-input');
  var notice = document.getElementById('chat-notice');
  var sendUrl = root.dataset.sendUrl;
  var pollUrl = root.dataset.pollUrl;
  var businessName = root.dataset.name || '';
  var welcomeRow = null;
  var busy = false;
  var typing = null;
  var storageKey = 'aichat-token-' + root.dataset.slug;
  var token = null;
  var seen = {};
  var lastId = 0;

  try { token = localStorage.getItem(storageKey); } catch (e) { token = null; }

  // Plain text with clickable links. Built with the DOM, never as HTML.
  function appendText(el, text) {
    var re = /(https?:\/\/[^\s<]+[^\s<.,;:!?)])/g, last = 0, m;
    while ((m = re.exec(text)) !== null) {
      if (m.index > last) { el.appendChild(document.createTextNode(text.slice(last, m.index))); }
      var a = document.createElement('a');
      a.href = m[0]; a.textContent = m[0]; a.target = '_blank'; a.rel = 'noopener noreferrer';
      el.appendChild(a);
      last = m.index + m[0].length;
    }
    if (last < text.length) { el.appendChild(document.createTextNode(text.slice(last))); }
  }

  function line(from, text, options) {
    var div = document.createElement('div');
    div.className = 'bubble ' + from;
    if (from === 'staff') {
      var who = document.createElement('span');
      who.className = 'who';
      who.textContent = 'Staff';
      div.appendChild(who);
    }
    appendText(div, text || '');
    var stamp = document.createElement('time');
    stamp.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    div.appendChild(stamp);
    log.appendChild(div);

    if (options && options.length) {
      var row = document.createElement('div');
      row.className = 'option-row';
      options.forEach(function (o) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = o.label || o;
        if (o.id === '__back' || o.id === 'back') { b.className = 'back'; }
        b.addEventListener('click', function () { send(o.text || o.label || o, o.id); });
        row.appendChild(b);
      });
      log.appendChild(row);
    }
    log.scrollTop = log.scrollHeight;
    return div;
  }

  function showTyping() {
    if (typing) { return; }
    typing = document.createElement('div');
    typing.className = 'bubble bot typing';
    typing.setAttribute('aria-label', 'Typing');
    typing.innerHTML = '<i></i><i></i><i></i>';
    log.appendChild(typing);
    log.scrollTop = log.scrollHeight;
  }

  function hideTyping() {
    if (typing && typing.parentNode) { typing.parentNode.removeChild(typing); }
    typing = null;
  }

  function render(messages) {
    messages.forEach(function (m) {
      if (seen[m.id]) { return; }
      seen[m.id] = true;
      if (m.id > lastId) { lastId = m.id; }
      line(m.from, m.text, m.options);
    });
  }

  var status = document.getElementById('chat-status');
  var sendBtn = document.getElementById('chat-send');
  function setMode(mode) {
    notice.textContent = '';
    status.textContent = mode === 'human' ? 'A team member is replying' : 'Replies instantly';
    document.getElementById('human-bar').hidden = mode !== 'human';
  }
  function syncSend() { sendBtn.disabled = busy || !input.value.trim(); }
  input.addEventListener('input', syncSend);

  function send(text, optionId) {
    text = (text || '').trim();
    if (!text || busy) { return; }
    busy = true;
    input.value = '';
    syncSend();
    notice.textContent = '';
    if (welcomeRow && welcomeRow.parentNode) { welcomeRow.parentNode.removeChild(welcomeRow); welcomeRow = null; }
    log.querySelectorAll('.option-row').forEach(function (r) { r.parentNode.removeChild(r); });
    showTyping();
    fetch(sendUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ text: text, option_id: optionId || null, token: token })
    }).then(function (r) {
      if (r.status === 429) { throw new Error('slow'); }
      if (!r.ok) { throw new Error('failed'); }
      return r.json();
    }).then(function (data) {
      hideTyping();
      busy = false;
      syncSend();
      if (data.token) {
        token = data.token;
        try { localStorage.setItem(storageKey, token); } catch (e) { /* storage may be blocked */ }
      }
      setMode(data.mode);
      render(data.messages || []);
    }).catch(function (e) {
      hideTyping();
      busy = false;
      input.value = text;
      syncSend();
      notice.textContent = e.message === 'slow'
        ? 'You are sending messages very fast. Please wait a moment. 请稍等片刻。'
        : 'Could not send. Check your connection and try again. 发送失败，请检查网络。';
    });
  }

  function poll() {
    if (!token) { return; }
    fetch(pollUrl + '?token=' + encodeURIComponent(token) + '&after=' + lastId, { headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (r.status === 404) { token = null; try { localStorage.removeItem(storageKey); } catch (e) { /* ignore */ } return null; }
        return r.json();
      })
      .then(function (data) {
        if (!data) { return; }
        setMode(data.mode);
        render(data.messages || []);
      })
      .catch(function () { /* try again on the next tick */ });
  }

  document.getElementById('chat-restart').addEventListener('click', function () {
    if (!window.confirm('Start a new chat? 开始新的对话？')) { return; }
    try { localStorage.removeItem(storageKey); } catch (e) { /* ignore */ }
    token = null; seen = {}; lastId = 0; log.innerHTML = ''; notice.textContent = ''; setMode('bot'); greet(); input.focus();
  });

  document.getElementById('human-menu').addEventListener('click', function () { send('menu', '__menu'); });

  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });

  // A first-time visitor sees a greeting instead of an empty box. Nothing is saved until they answer.
  function greet() {
    line('bot', 'Hi! Welcome to ' + businessName + '.\n您好！欢迎来到' + businessName + '。', [{ label: 'Start / 开始', id: null, text: 'start' }]);
    welcomeRow = log.lastChild;
  }
  if (token) { poll(); } else { greet(); }
  setInterval(poll, 3000);
})();
