// Customer test chat. Plain JavaScript, polls for new messages every 3 seconds.
(function () {
  var root = document.getElementById('chat');
  var log = document.getElementById('chat-log');
  var form = document.getElementById('chat-form');
  var input = document.getElementById('chat-input');
  var notice = document.getElementById('chat-notice');
  var sendUrl = root.dataset.sendUrl;
  var pollUrl = root.dataset.pollUrl;
  var storageKey = 'aichat-token-' + root.dataset.slug;
  var token = null;
  var seen = {};
  var lastId = 0;

  try { token = localStorage.getItem(storageKey); } catch (e) { token = null; }

  function line(from, text, options) {
    var div = document.createElement('div');
    div.className = 'bubble ' + from;
    if (from === 'staff') {
      var who = document.createElement('span');
      who.className = 'who';
      who.textContent = 'Staff';
      div.appendChild(who);
    }
    div.appendChild(document.createTextNode(text || ''));
    log.appendChild(div);

    if (options && options.length) {
      var row = document.createElement('div');
      row.className = 'option-row';
      options.forEach(function (o) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = o.label || o;
        b.addEventListener('click', function () { send(o.label || o, o.id); });
        row.appendChild(b);
      });
      log.appendChild(row);
    }
    log.scrollTop = log.scrollHeight;
  }

  function render(messages) {
    messages.forEach(function (m) {
      if (seen[m.id]) { return; }
      seen[m.id] = true;
      if (m.id > lastId) { lastId = m.id; }
      line(m.from, m.text, m.options);
    });
  }

  function setMode(mode) {
    notice.textContent = mode === 'human' ? 'A team member is replying to you.' : '';
  }

  function send(text, optionId) {
    text = (text || '').trim();
    if (!text) { return; }
    input.value = '';
    fetch(sendUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ text: text, option_id: optionId || null, token: token })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.token) {
        token = data.token;
        try { localStorage.setItem(storageKey, token); } catch (e) { /* storage may be blocked */ }
      }
      setMode(data.mode);
      render(data.messages || []);
    }).catch(function () { notice.textContent = 'Could not send. Please try again.'; });
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

  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });

  if (token) { poll(); }
  setInterval(poll, 3000);
})();
