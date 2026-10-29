// Staff inbox. Plain JavaScript; refreshes the list every 4 s and the open chat every 3 s.
(function () {
  var root = document.getElementById('inbox');
  var base = root.dataset.base; // e.g. .../admin/api
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var listEl = document.getElementById('conv-list');
  var logEl = document.getElementById('thread-log');
  var textEl = document.getElementById('reply-text');
  var statusSel = document.getElementById('status-filter');
  var jumpBtn = document.getElementById('btn-jump');
  var current = null, seen = {}, lastId = 0, lastDay = '', lastInfoKey = null, mode = 'reply', unseen = 0, lastTemplates = [];
  var curLang = null, curName = '', businessName = root.dataset.business || '', toastTimer = null;
  var CHANNELS = { web: 'Website', whatsapp: 'WhatsApp', messenger: 'Messenger', instagram: 'Instagram' };

  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, opts.headers || {});
    if (opts.body && typeof opts.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }
    return fetch(base + path, opts).then(function (r) { return r.json(); });
  }

  function toast(text, bad) {
    var t = document.getElementById('toast');
    t.textContent = text; t.className = 'toast' + (bad ? ' bad' : ''); t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.hidden = true; }, 3200);
  }

  function ago(iso) {
    if (!iso) { return ''; }
    var s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
    if (s < 60) { return 'now'; }
    if (s < 3600) { return Math.floor(s / 60) + 'm'; }
    if (s < 86400) { return Math.floor(s / 3600) + 'h'; }
    return Math.floor(s / 86400) + 'd';
  }

  function dayLabel(d) {
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var that = new Date(d); that.setHours(0, 0, 0, 0);
    var diff = Math.round((today - that) / 86400000);
    if (diff === 0) { return 'Today'; }
    if (diff === 1) { return 'Yesterday'; }
    return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  // ---------- list ----------
  function loadList() {
    api('/conversations?status=' + encodeURIComponent(statusSel.value)).then(function (data) {
      listEl.innerHTML = '';
      if (!data.conversations.length) {
        listEl.innerHTML = '<div class="empty">No conversations yet.<br>Open the test chat and send a message.</div>';
        return;
      }
      data.conversations.forEach(function (c) {
        var d = document.createElement('div');
        d.className = 'conv' + (current === c.id ? ' on' : '') + (c.needs_reply ? ' urgent' : '');
        d.innerHTML = '<span class="av"></span><div class="cb"><div class="ct"><b></b><time></time></div><p></p><div class="cm"></div></div>';
        d.querySelector('.av').textContent = (c.name || '?').trim().charAt(0).toUpperCase();
        d.querySelector('b').textContent = c.name;
        d.querySelector('time').textContent = ago(c.last_message_at);
        d.querySelector('p').textContent = c.preview || 'No messages yet';
        var meta = d.querySelector('.cm');
        var ch = document.createElement('span'); ch.className = 'chan'; ch.textContent = CHANNELS[c.channel] || c.channel; meta.appendChild(ch);
        if (c.needs_reply) { var n = document.createElement('span'); n.className = 'tag warn'; n.textContent = 'Needs a person'; meta.appendChild(n); }
        else if (c.mode === 'human') { var h = document.createElement('span'); h.className = 'tag'; h.textContent = 'Staff'; meta.appendChild(h); }
        d.addEventListener('click', function () { openConversation(c.id); });
        listEl.appendChild(d);
      });
    });
  }

  // ---------- messages ----------
  function nearBottom() { return logEl.scrollHeight - logEl.scrollTop - logEl.clientHeight < 90; }
  function toBottom() { logEl.scrollTop = logEl.scrollHeight; jumpBtn.classList.add('hidden'); unseen = 0; }

  function addLine(m) {
    if (seen[m.id]) { return; }
    seen[m.id] = true;
    if (m.id > lastId) { lastId = m.id; }

    var when = m.time ? new Date(m.time) : null;
    if (when) {
      var key = when.toDateString();
      if (key !== lastDay) {
        lastDay = key;
        var sep = document.createElement('div'); sep.className = 'daysep'; sep.textContent = dayLabel(when);
        logEl.appendChild(sep);
      }
    }

    if (m.from === 'ai') { addAiCard(m); return; }
    if (m.from === 'customer' && !m.private && m.text) { lastAsk = m.text; askChanged = true; }
    else if (m.from === 'staff' && !m.private) { lastAsk = ''; askChanged = true; }

    var div = document.createElement('div');
    var sys = m.from === 'system' && m.private;
    div.className = 'm ' + (sys ? 'system' : (m.private ? 'private' : m.from));
    var body = document.createElement('div'); body.className = 'mt'; body.textContent = m.text || '';
    if (m.private && !sys) { var lock = document.createElement('span'); lock.className = 'lock'; lock.textContent = 'Private note'; div.appendChild(lock); }
    div.appendChild(body);
    if (!sys) {
      var t = document.createElement('small');
      var who = m.private ? '' : (m.from === 'bot' ? 'Bot' : (m.from === 'staff' ? 'Staff' : ''));
      t.textContent = [who, when ? when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''].filter(Boolean).join(' · ');
      div.appendChild(t);
    }
    if (m.delivery === 'failed') {
      var f = document.createElement('small'); f.className = 'fail';
      f.textContent = 'Not delivered: ' + (m.delivery_error || 'unknown problem');
      div.appendChild(f);
    }
    logEl.appendChild(div);
    if (m.from === 'customer') { unseen++; }
  }

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
        if (d && d.error) { toast(d.error, true); return; }
        card.remove(); refreshThread(); loadList();
      });
    });
    button('Edit first', '', function () { setMode('reply'); textEl.value = m.text; grow(); textEl.focus(); });
    button('Dismiss', '', function () { post('/ai/' + m.id + '/dismiss', {}).then(function () { card.remove(); }); });
    card.appendChild(head); card.appendChild(body); card.appendChild(row);
    logEl.appendChild(card);
  }

  // ---------- header, info bar ----------
  function showMeta(c) {
    document.getElementById('btn-summarize').style.display = c.ai_on ? '' : 'none';
    document.getElementById('thread-title').textContent = c.name;
    document.getElementById('thread-av').textContent = (c.name || '?').trim().charAt(0).toUpperCase();
    document.getElementById('thread-meta').textContent = [c.channel_label || c.channel, c.status === 'open' ? 'open' : 'resolved', c.handover_reason].filter(Boolean).join(' · ');
    var badge = document.getElementById('mode-badge');
    badge.textContent = c.mode === 'human' ? 'You are handling this' : 'Bot is answering';
    badge.className = 'tag ' + (c.mode === 'human' ? 'warn' : 'ok');
    document.getElementById('btn-give-back').style.display = c.mode === 'human' ? '' : 'none';
    var rb = document.getElementById('btn-resolve');
    rb.textContent = c.status === 'resolved' ? 'Reopen' : 'Resolve';
    rb.classList.toggle('primary', c.status !== 'resolved');
    document.getElementById('thread').classList.remove('hidden');
    document.getElementById('thread-empty').style.display = 'none';
  }

  function renderBanner(data) {
    var c = data.conversation, el = document.getElementById('window-bar');
    lastTemplates = data.templates || [];
    if (c.window_open) { el.classList.add('hidden'); return; }
    el.classList.remove('hidden');
    document.getElementById('window-text').textContent = c.can_template
      ? 'The 24-hour window is closed. You can only send an approved template.'
      : 'The 24-hour window is closed. You can reply once the customer messages you again.';
    document.getElementById('btn-template').style.display = c.can_template && lastTemplates.length ? '' : 'none';
  }

  function renderInfo(data) {
    var c = data.conversation;
    renderBanner(data);
    var cust = document.getElementById('info-customer');
    cust.innerHTML = '';
    if (c.customer) {
      var a = document.createElement('a');
      a.href = c.customer.url; a.className = 'cust-link';
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
      chip.title = 'Click to remove'; chip.textContent = t.name + ' ×';
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

  function openConversation(id) {
    current = id; seen = {}; lastId = 0; lastDay = ''; unseen = 0; logEl.innerHTML = ''; lastAsk = ''; suggest();
    root.classList.add('chat-open');
    refreshThread(false, true);
    loadList();
  }

  function refreshThread(force, first) {
    if (!current) { return; }
    var asked = current;
    api('/conversations/' + current + '?after=' + lastId).then(function (data) {
      if (!data.conversation || asked !== current) { return; }
      var stick = first || nearBottom();
      curLang = data.conversation.language || null;
      curName = (data.conversation.customer && data.conversation.customer.name) || (/^Visitor \d+$/.test(data.conversation.name) ? '' : data.conversation.name);
      showMeta(data.conversation);
      var key = JSON.stringify([data.conversation.ai_mood, data.conversation.ai_intent, data.conversation.tags, data.conversation.assigned_to, data.conversation.customer, data.conversation.window_open, current]);
      if (force || key !== lastInfoKey) { lastInfoKey = key; renderInfo(data); }
      var before = unseen; unseen = 0;
      data.messages.forEach(addLine);
      if (askChanged) { askChanged = false; suggest(); }
      if (stick) { toBottom(); }
      else if (unseen > 0) { jumpBtn.textContent = unseen + ' new ' + (unseen === 1 ? 'message' : 'messages') + ' ↓'; jumpBtn.classList.remove('hidden'); }
      unseen = stick ? 0 : unseen + before;
    });
  }

  function post(path, body) {
    if (!current) { return Promise.resolve(); }
    return api('/conversations/' + current + path, { method: 'POST', body: body || {} });
  }

  // ---------- composer ----------
  function grow() { textEl.style.height = 'auto'; textEl.style.height = Math.min(textEl.scrollHeight, 170) + 'px'; }
  function setMode(m) {
    mode = m;
    document.querySelectorAll('.c-tabs button').forEach(function (b) { b.classList.toggle('on', b.dataset.mode === m); });
    document.getElementById('composer').classList.toggle('note-mode', m === 'note');
    document.getElementById('btn-send').textContent = m === 'note' ? 'Save note' : 'Send reply';
    document.getElementById('c-hint').textContent = m === 'note' ? 'Only your team sees private notes. The customer never does.' : 'Replying makes you the owner of the chat, so the bot stays quiet.';
    textEl.placeholder = m === 'note' ? 'Write a private note for your team' : 'Write a reply. Enter sends, Shift+Enter starts a new line.';
  }
  function send() {
    var text = textEl.value.trim();
    if (!text) { return; }
    textEl.value = ''; grow();
    var note = mode === 'note';
    post(note ? '/note' : '/reply', { text: text }).then(function (d) {
      if (d && d.error) { textEl.value = text; grow(); toast(d.error, true); }
      refreshThread(false, true); loadList();
    });
  }
  document.querySelectorAll('.c-tabs button').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.mode); textEl.focus(); }); });
  document.getElementById('btn-send').addEventListener('click', send);
  textEl.addEventListener('input', grow);
  textEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); } });

  jumpBtn.addEventListener('click', toBottom);
  logEl.addEventListener('scroll', function () { if (nearBottom()) { jumpBtn.classList.add('hidden'); unseen = 0; } });
  document.getElementById('btn-back').addEventListener('click', function () { root.classList.remove('chat-open'); });

  // ---------- header buttons ----------
  document.getElementById('btn-give-back').addEventListener('click', function () {
    post('/give-back').then(function () { toast('The bot is answering this chat again.'); refreshThread(); loadList(); });
  });
  document.getElementById('btn-summarize').addEventListener('click', function () {
    var b = this; b.disabled = true; b.textContent = 'Summarising...';
    post('/summarize', {}).then(function (d) {
      b.disabled = false; b.textContent = 'Summarise (AI)';
      if (d && d.error) { toast(d.error, true); return; }
      refreshThread(false, true);
    });
  });
  document.getElementById('btn-resolve').addEventListener('click', function () {
    var resolved = this.textContent === 'Reopen';
    post(resolved ? '/reopen' : '/resolve').then(function () { toast(resolved ? 'Chat reopened.' : 'Chat resolved.'); refreshThread(); loadList(); });
  });
  document.getElementById('tag-add').addEventListener('change', function () {
    if (this.value) { post('/tag', { tag_id: parseInt(this.value, 10), on: true }).then(refreshInfo); }
  });
  document.getElementById('assign').addEventListener('change', function () {
    post('/assign', { user_id: this.value ? parseInt(this.value, 10) : null }).then(function () { toast('Saved.'); loadList(); });
  });

  // ---------- dialogs: task and template ----------
  function wire(dlg) { dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('.dlg-x')) { dlg.close(); } }); return dlg; }
  var taskDlg = wire(document.getElementById('task-dialog'));
  document.getElementById('btn-task').addEventListener('click', function () {
    document.getElementById('tk-title').value = curName ? 'Follow up with ' + curName : '';
    document.getElementById('tk-due').value = '';
    taskDlg.showModal(); document.getElementById('tk-title').focus();
  });
  document.getElementById('tk-save').addEventListener('click', function () {
    var title = document.getElementById('tk-title').value.trim();
    if (!title) { document.getElementById('tk-title').focus(); return; }
    fetch(root.dataset.base.replace('/api', '') + '/tasks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ title: title, due_at: document.getElementById('tk-due').value || null, conversation_id: current })
    }).then(function (r) { taskDlg.close(); toast(r.ok ? 'Task added. You will find it under Tasks.' : 'Could not add the task.', !r.ok); });
  });

  var tplDlg = wire(document.getElementById('tpl-dialog'));
  var tplPick = null;
  function tplPreview() {
    var box = document.getElementById('tpl-preview');
    if (!tplPick) { box.textContent = 'Choose a template.'; return; }
    var vals = Array.from(document.querySelectorAll('#tpl-params input')).map(function (i) { return i.value; });
    var text = tplPick.body.replace(/\{\{(\d+)\}\}/g, function (m, n) { return vals[n - 1] || m; });
    box.textContent = text;
    document.getElementById('tpl-send').disabled = vals.some(function (v) { return v.trim() === ''; });
  }
  document.getElementById('btn-template').addEventListener('click', function () {
    var opts = document.getElementById('tpl-options'); opts.innerHTML = ''; tplPick = null;
    document.getElementById('tpl-params').innerHTML = ''; document.getElementById('tpl-send').disabled = true;
    lastTemplates.forEach(function (t) {
      var lab = document.createElement('label'); lab.className = 'tpl-opt';
      var r = document.createElement('input'); r.type = 'radio'; r.name = 'tpl';
      var b = document.createElement('b'); b.textContent = t.name.replace(/_/g, ' ');
      var s = document.createElement('span'); s.textContent = t.body;
      lab.appendChild(r); lab.appendChild(b); lab.appendChild(s);
      r.addEventListener('change', function () {
        tplPick = t;
        var n = (t.body.match(/\{\{\d+\}\}/g) || []).length, box = document.getElementById('tpl-params'); box.innerHTML = '';
        var hints = ['Customer name', 'Service', 'Date and time'];
        for (var i = 1; i <= n; i++) {
          var f = document.createElement('label'); f.className = 'f';
          var sp = document.createElement('span'); sp.textContent = 'Part ' + i + (hints[i - 1] ? ' (' + hints[i - 1].toLowerCase() + ')' : '');
          var inp = document.createElement('input'); inp.type = 'text'; inp.maxLength = 200;
          if (i === 1 && curName) { inp.value = curName; }
          inp.addEventListener('input', tplPreview);
          f.appendChild(sp); f.appendChild(inp); box.appendChild(f);
        }
        tplPreview();
        if (n === 0) { document.getElementById('tpl-send').disabled = false; }
      });
      opts.appendChild(lab);
    });
    tplDlg.showModal();
  });
  document.getElementById('tpl-send').addEventListener('click', function () {
    if (!tplPick) { return; }
    var params = Array.from(document.querySelectorAll('#tpl-params input')).map(function (i) { return i.value; });
    post('/template', { template_id: tplPick.id, params: params }).then(function (d) {
      if (d && d.error) { toast(d.error, true); return; }
      tplDlg.close(); toast('Template sent.'); refreshThread(false, true); loadList();
    });
  });

  // ---------- saved replies (in the customer's language, {name} and {business} filled in) ----------
  var qr = document.getElementById('qr'), allReplies = [], lastAsk = '', askChanged = false;
  api('/quick-replies').then(function (d) {
    var groups = {};
    (d.replies || []).forEach(function (r) {
      allReplies.push({ id: r.id, title: r.title, en: r.body.en || '', zh: r.body.zh || '' });
      var g = r.category || 'Other';
      if (!groups[g]) { groups[g] = document.createElement('optgroup'); groups[g].label = g; qr.appendChild(groups[g]); }
      var o = new Option(r.title, r.id);
      o.dataset.en = r.body.en || ''; o.dataset.zh = r.body.zh || '';
      groups[g].appendChild(o);
    });
    suggest();
  });
  // Puts a saved reply in the box: the customer's language, {name} and {business} filled in.
  function insertReply(r) {
    var text = (curLang === 'zh' ? (r.zh || r.en) : (r.en || r.zh)) || '';
    text = (curName ? text.replace(/\{name\}/g, curName) : text.replace(/\{name\}\s*/g, curLang === 'zh' ? '' : 'there ').replace(/there ([,.!?])/g, 'there$1').replace(/there\s*$/, 'there')).replace(/\{business\}/g, businessName);
    setMode('reply');
    textEl.value = (textEl.value ? textEl.value + '\n' : '') + text; grow(); textEl.focus();
  }
  qr.addEventListener('change', function () {
    var o = qr.options[qr.selectedIndex];
    if (o && o.value) { insertReply({ en: o.dataset.en, zh: o.dataset.zh }); }
    qr.value = '';
  });

  // Suggested replies: the saved replies that fit what the customer last wrote (simple word matching, no AI).
  var suggEl = document.getElementById('qr-sugg');
  function suggest() {
    suggEl.querySelectorAll('button').forEach(function (b) { b.remove(); });
    var list = (lastAsk && window.aichatSuggest) ? window.aichatSuggest.rank(lastAsk, allReplies, 3) : [];
    suggEl.hidden = list.length === 0;
    list.forEach(function (r) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = r.title; b.title = (r.en || r.zh || '').slice(0, 160);
      b.addEventListener('click', function () { insertReply(r); });
      suggEl.appendChild(b);
    });
  }

  statusSel.addEventListener('change', loadList);

  loadList();
  var wanted = parseInt(new URLSearchParams(location.search).get('c') || '', 10);
  if (wanted) { openConversation(wanted); }
  setInterval(loadList, 4000);
  setInterval(refreshThread, 3000);
})();
