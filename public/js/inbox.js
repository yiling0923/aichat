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
  var curLang = null, curName = '';
  var businessName = root.dataset.business || '';
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
      var labels = { web: 'Website', whatsapp: 'WhatsApp', messenger: 'Messenger', instagram: 'Instagram' };
      data.conversations.forEach(function (c) {
        var d = document.createElement('div');
        d.className = 'conv' + (current === c.id ? ' on' : '') + (c.needs_reply ? ' urgent' : '');
        d.innerHTML = '<span class="av"></span><div class="cb"><div class="ct"><b></b><time></time></div><p></p><div class="cm"></div></div>';
        d.querySelector('.av').textContent = (c.name || '?').trim().charAt(0).toUpperCase();
        d.querySelector('b').textContent = c.name;
        d.querySelector('time').textContent = ago(c.last_message_at);
        d.querySelector('p').textContent = c.preview || 'No messages yet';
        var meta = d.querySelector('.cm');
        var ch = document.createElement('span'); ch.className = 'chan'; ch.textContent = labels[c.channel] || c.channel; meta.appendChild(ch);
        if (c.needs_reply) { var n = document.createElement('span'); n.className = 'tag warn'; n.textContent = 'Needs a person'; meta.appendChild(n); }
        else if (c.mode === 'human') { var h = document.createElement('span'); h.className = 'tag'; h.textContent = 'Staff'; meta.appendChild(h); }
        d.addEventListener('click', function () { openConversation(c.id); });
        listEl.appendChild(d);
      });
    });
  }

  function ago(iso) {
    if (!iso) { return ''; }
    var s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
    if (s < 60) { return 'now'; }
    if (s < 3600) { return Math.floor(s / 60) + 'm'; }
    if (s < 86400) { return Math.floor(s / 3600) + 'h'; }
    return Math.floor(s / 86400) + 'd';
  }

  function addLine(m) {
    if (seen[m.id]) { return; }
    seen[m.id] = true;
    if (m.id > lastId) { lastId = m.id; }
    if (m.from === 'ai') { addAiCard(m); return; }
    var div = document.createElement('div');
    var cls = m.private ? 'private' : m.from;
    div.className = 'm ' + cls;
    var prefix = m.private ? (m.from === 'system' ? '' : 'Private note: ') : '';
    div.appendChild(document.createTextNode(prefix + (m.text || '')));
    var t = document.createElement('small');
    t.textContent = m.from + (m.time ? ' · ' + new Date(m.time).toLocaleTimeString() : '');
    div.appendChild(t);
    if (m.delivery === 'failed') {
      var f = document.createElement('small');
      f.style.color = '#b42318'; f.style.opacity = '1'; f.style.fontWeight = '600';
      f.textContent = 'Not delivered: ' + (m.delivery_error || 'unknown problem');
      div.appendChild(f);
    }
    logEl.appendChild(div);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function renderBanner(data) {
    var c = data.conversation;
    var el = document.getElementById('window-bar');
    if (c.window_open) { el.classList.add('hidden'); return; }
    el.classList.remove('hidden');
    var tpl = (data.templates || []);
    document.getElementById('window-text').textContent = c.can_template
      ? 'The 24-hour window is closed. You can only send an approved template.'
      : 'The 24-hour window is closed. You can reply once the customer messages you again.';
    var btn = document.getElementById('btn-template');
    btn.style.display = c.can_template && tpl.length ? '' : 'none';
    btn.onclick = function () {
      var list = tpl.map(function (t, i) { return (i + 1) + '. ' + t.name + ': ' + t.body; }).join('\n');
      var pick = parseInt(prompt('Choose a template number:\n\n' + list, '1'), 10);
      var t = tpl[pick - 1];
      if (!t) { return; }
      var count = (t.body.match(/\{\{\d+\}\}/g) || []).length, params = [];
      for (var i = 1; i <= count; i++) { var v = prompt('Value for {{' + i + '}} in: ' + t.body, ''); if (v === null) { return; } params.push(v); }
      post('/template', { template_id: t.id, params: params }).then(function (d) { if (d && d.error) { alert(d.error); } refreshThread(); loadList(); });
    };
  }

  function renderInfo(data) {
    var c = data.conversation;
    renderBanner(data);
    var cust = document.getElementById('info-customer');
    cust.innerHTML = '';
    if (c.customer) {
      var a = document.createElement('a');
      a.href = c.customer.url;
      a.textContent = (c.customer.name || 'Customer') + (c.customer.phone ? ' · ' + c.customer.phone : '');
      cust.appendChild(a);
    } else { cust.textContent = 'No customer details yet'; }
    if (c.ai_mood || c.ai_intent) {
      var mood = document.createElement('span');
      mood.className = 'tag ' + (c.ai_mood === 'angry' ? 'hot' : 'ok');
      mood.style.marginLeft = '8px';
      mood.textContent = [c.ai_intent, c.ai_mood].filter(Boolean).join(' · ');
      mood.title = 'What the AI understood from the last messages';
      cust.appendChild(mood);
    }

    var tagsEl = document.getElementById('info-tags');
    tagsEl.innerHTML = '';
    var have = {};
    c.tags.forEach(function (t) {
      have[t.id] = true;
      var chip = document.createElement('span');
      chip.className = 'chip'; chip.style.background = t.color; chip.style.cursor = 'pointer';
      chip.title = 'Click to remove'; chip.textContent = t.name + ' x';
      chip.addEventListener('click', function () { post('/tag', { tag_id: t.id, on: false }).then(refreshInfo); });
      tagsEl.appendChild(chip);
    });
    var sel = document.getElementById('tag-add');
    sel.innerHTML = '<option value="">+ Tag</option>';
    (data.all_tags || []).forEach(function (t) { if (!have[t.id]) { sel.appendChild(new Option(t.name, t.id)); } });
    sel.style.display = (data.all_tags || []).length ? '' : 'none';

    var as = document.getElementById('assign');
    as.innerHTML = '<option value="">Anyone</option>';
    (data.team || []).forEach(function (u) { as.appendChild(new Option(u.name, u.id)); });
    as.value = c.assigned_to || '';
  }

  function refreshInfo() { lastInfoKey = null; refreshThread(true); }
  var lastInfoKey = null;

  function addAiCard(m) {
    var card = document.createElement('div');
    card.className = 'm ai-card';
    var head = document.createElement('b');
    head.textContent = 'AI suggestion' + (m.ai && m.ai.intent ? ' (' + m.ai.intent + (m.ai.mood ? ', ' + m.ai.mood : '') + ')' : '');
    var body = document.createElement('div');
    body.style.margin = '6px 0 8px'; body.textContent = m.text;
    var row = document.createElement('div');
    row.className = 'bar'; row.style.margin = '0';
    function button(label, cls, fn) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn small ' + cls; b.textContent = label;
      b.addEventListener('click', fn); row.appendChild(b);
    }
    button('Send now', 'primary', function () {
      post('/ai/' + m.id + '/send', {}).then(function (d) {
        if (d && d.error) { alert(d.error); return; }
        card.remove(); refreshThread(); loadList();
      });
    });
    button('Edit first', '', function () { textEl.value = m.text; textEl.focus(); });
    button('Dismiss', '', function () { post('/ai/' + m.id + '/dismiss', {}).then(function () { card.remove(); }); });
    card.appendChild(head); card.appendChild(body); card.appendChild(row);
    logEl.appendChild(card);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function showMeta(c) {
    document.getElementById('btn-summarize').style.display = c.ai_on ? '' : 'none';
    headEl.textContent = c.name;
    var mode = c.mode === 'human' ? 'Staff are handling this chat. The bot is silent.' : 'The bot is answering this chat.';
    metaEl.textContent = (c.channel_label || c.channel) + ' · ' + c.status + ' · ' + mode + (c.handover_reason ? ' (' + c.handover_reason + ')' : '');
    document.getElementById('btn-give-back').style.display = c.mode === 'human' ? '' : 'none';
    document.getElementById('thread').classList.remove('hidden');
    document.getElementById('thread-empty').style.display = 'none';
  }

  function openConversation(id) {
    current = id; seen = {}; lastId = 0; logEl.innerHTML = '';
    refreshThread();
    loadList();
  }

  function refreshThread(force) {
    if (!current) { return; }
    api('/conversations/' + current + '?after=' + lastId).then(function (data) {
      if (!data.conversation) { return; }
      curLang = data.conversation.language || null;
      curName = (data.conversation.customer && data.conversation.customer.name) || (/^Visitor \d+$/.test(data.conversation.name) ? '' : data.conversation.name);
      showMeta(data.conversation);
      var key = JSON.stringify([data.conversation.ai_mood, data.conversation.ai_intent, data.conversation.tags, data.conversation.assigned_to, data.conversation.customer, data.conversation.window_open, current]);
      if (force || key !== lastInfoKey) { lastInfoKey = key; renderInfo(data); }
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
    post('/reply', { text: text }).then(function (d) {
      if (d && d.error) { textEl.value = text; alert(d.error); }
      refreshThread(); loadList();
    });
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
  document.getElementById('btn-summarize').addEventListener('click', function () {
    var b = this; b.disabled = true; b.textContent = 'Summarising...';
    post('/summarize', {}).then(function (d) {
      b.disabled = false; b.textContent = 'Summarise (AI)';
      if (d && d.error) { alert(d.error); return; }
      refreshThread();
    });
  });
  document.getElementById('btn-resolve').addEventListener('click', function () {
    post('/resolve').then(function () { refreshThread(); loadList(); });
  });
  document.getElementById('tag-add').addEventListener('change', function () {
    if (this.value) { post('/tag', { tag_id: parseInt(this.value, 10), on: true }).then(refreshInfo); }
  });
  document.getElementById('assign').addEventListener('change', function () {
    post('/assign', { user_id: this.value ? parseInt(this.value, 10) : null }).then(loadList);
  });
  document.getElementById('btn-task').addEventListener('click', function () {
    var title = prompt('Task for this chat (for example: call the customer back):');
    if (!title) { return; }
    var due = prompt('Due date and time (optional), for example 2026-11-05 15:00:', '');
    fetch(root.dataset.base.replace('/api', '') + '/tasks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ title: title, due_at: due || null, conversation_id: current })
    }).then(function (r) { alert(r.ok ? 'Task added. See it under Tasks.' : 'Could not add the task. Check the date format.'); });
  });

  var qr = document.getElementById('qr');
  api('/quick-replies').then(function (d) {
    var groups = {};
    (d.replies || []).forEach(function (r) {
      var g = r.category || 'Other';
      if (!groups[g]) { groups[g] = document.createElement('optgroup'); groups[g].label = g; qr.appendChild(groups[g]); }
      var o = new Option(r.title, r.id);
      o.dataset.en = r.body.en || ''; o.dataset.zh = r.body.zh || '';
      groups[g].appendChild(o);
    });
  });
  // The reply is inserted in the customer's language (when written), and {name} and {business} are filled in.
  qr.addEventListener('change', function () {
    var o = qr.options[qr.selectedIndex];
    if (o && o.value) {
      var text = (curLang === 'zh' ? (o.dataset.zh || o.dataset.en) : (o.dataset.en || o.dataset.zh));
      text = (curName ? text.replace(/\{name\}/g, curName) : text.replace(/\{name\}\s*/g, curLang === 'zh' ? '' : 'there ').replace(/there ([,.!?])/g, 'there$1').replace(/there\s*$/, 'there')).replace(/\{business\}/g, businessName);
      textEl.value = (textEl.value ? textEl.value + '\n' : '') + text;
      textEl.focus();
    }
    qr.value = '';
  });

  statusSel.addEventListener('change', loadList);

  loadList();
  var wanted = parseInt(new URLSearchParams(location.search).get('c') || '', 10);
  if (wanted) { openConversation(wanted); }
  setInterval(loadList, 4000);
  setInterval(refreshThread, 3000);
})();
