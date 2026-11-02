// Staff inbox. Plain JavaScript; refreshes the list every 4 s and the open chat every 3 s.
(function () {
  var root = document.getElementById('inbox');
  var base = root.dataset.base; // e.g. .../admin/api
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var listEl = document.getElementById('conv-list');
  var logEl = document.getElementById('thread-log');
  var textEl = document.getElementById('reply-text');
  var statusSel = document.getElementById('status-filter');
  var channelSel = document.getElementById('channel-filter');
  try { var savedCh = sessionStorage.getItem('inbox-channel'); if (savedCh !== null && channelSel) { channelSel.value = savedCh; } } catch (e) { /* storage blocked: fine */ }
  var jumpBtn = document.getElementById('btn-jump');
  var current = null, seen = {}, lastId = 0, lastDay = '', lastInfoKey = null, mode = 'reply', unseen = 0, lastTemplates = [];
  var curLang = null, curName = '', businessName = root.dataset.business || '', toastTimer = null;
  var CHANNELS = { web: __t('Website'), whatsapp: 'WhatsApp', messenger: 'Messenger', instagram: 'Instagram' };

  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, opts.headers || {});
    if (opts.body && typeof opts.body !== 'string' && !(window.FormData && opts.body instanceof FormData)) {
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
    if (s < 60) { return __t('now'); }
    if (s < 3600) { return __t(':n m', { n: Math.floor(s / 60) }); }
    if (s < 86400) { return __t(':n h', { n: Math.floor(s / 3600) }); }
    return __t(':n d', { n: Math.floor(s / 86400) });
  }

  function dayLabel(d) {
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var that = new Date(d); that.setHours(0, 0, 0, 0);
    var diff = Math.round((today - that) / 86400000);
    if (diff === 0) { return __t('Today'); }
    if (diff === 1) { return __t('Yesterday'); }
    return d.toLocaleDateString(window.LOCALE === 'zh' ? 'zh-CN' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  // ---------- list ----------
  function loadList() {
    api('/conversations?status=' + encodeURIComponent(statusSel.value) + '&channel=' + encodeURIComponent(channelSel ? channelSel.value : '')).then(function (data) {
      listEl.innerHTML = '';
      if (!data.conversations.length) {
        listEl.innerHTML = '<div class="empty">' + __t('No conversations yet.') + '<br>' + __t('Open the test chat and send a message.') + '</div>';
        return;
      }
      data.conversations.forEach(function (c) {
        var d = document.createElement('div');
        d.className = 'conv ch-' + c.channel + (current === c.id ? ' on' : '') + (c.needs_reply ? ' urgent' : '') + (c.unread && current !== c.id ? ' unread' : '');
        d.innerHTML = '<span class="av"></span><div class="cb"><div class="ct"><span class="nm"><b></b></span><time></time></div><p></p><div class="cm"></div></div>';
        d.querySelector('.av').textContent = (c.name || '?').trim().charAt(0).toUpperCase();
        d.querySelector('b').textContent = c.name;
        d.querySelector('time').textContent = ago(c.last_message_at);
        if (c.unread && current !== c.id) { var dot = document.createElement('i'); dot.className = 'udot'; dot.title = __t('Unread'); dot.setAttribute('aria-label', __t('Unread')); d.querySelector('.ct').appendChild(dot); }
        d.querySelector('p').textContent = c.preview || __t('No messages yet');
        var meta = d.querySelector('.cm');
        var ch = document.createElement('span'); ch.className = 'chan ch-' + c.channel; ch.textContent = CHANNELS[c.channel] || c.channel; meta.appendChild(ch);
        // The status sits beside the customer's name so it is seen first.
        var nm = d.querySelector('.nm');
        if (c.needs_reply) { var n = document.createElement('span'); n.className = 'tag warn'; n.textContent = __t('Needs a person'); nm.appendChild(n); }
        else if (c.mode === 'human') { var h = document.createElement('span'); h.className = 'tag'; h.textContent = __t('Staff'); nm.appendChild(h); }
        d.addEventListener('click', function () { openConversation(c.id); });
        listEl.appendChild(d);
      });
    });
  }

  // ---------- messages ----------
  function nearBottom() { return logEl.scrollHeight - logEl.scrollTop - logEl.clientHeight < 90; }
  function toBottom() { logEl.scrollTop = logEl.scrollHeight; jumpBtn.classList.add('hidden'); unseen = 0; }

  // A photo, video, voice note, file or location pin in the thread. The file itself comes through the media address (staff only).
  function mediaEl(m) {
    var x = m.media, src = x.url || (base + '/messages/' + m.id + '/media'), box = document.createElement('div'); box.className = 'media';
    function link(text, href) { var a = document.createElement('a'); a.href = href; a.target = '_blank'; a.rel = 'noopener'; a.className = 'media-link'; a.textContent = text; return a; }
    if (x.type === 'location') {
      var maps = 'https://www.google.com/maps?q=' + encodeURIComponent(x.lat + ',' + x.lng);
      box.appendChild(link('\uD83D\uDCCD ' + (x.place || __t('Location')) + ' (' + __t('open in Maps') + ')', maps));
    } else if (x.type === 'image' || x.type === 'sticker') {
      var img = document.createElement('img'); img.src = src; img.alt = __t('Photo'); img.loading = 'lazy';
      var a = link('', src); a.appendChild(img); box.appendChild(a);
    } else if (x.type === 'audio') {
      var au = document.createElement('audio'); au.controls = true; au.preload = 'none'; au.src = src; box.appendChild(au);
    } else if (x.type === 'video') {
      var v = document.createElement('video'); v.controls = true; v.preload = 'metadata'; v.src = src; box.appendChild(v);
    } else {
      box.appendChild(link('\uD83D\uDCC4 ' + (x.filename || __t('Open the file')), src));
    }
    return box;
  }

  // Sent, delivered, read, or "Not delivered" with the reason. WhatsApp reports this after the message was accepted, so older bubbles are updated too.
  function applyReceipts(list) {
    (list || []).forEach(function (r) {
      var el = logEl.querySelector('[data-mid="' + r.id + '"]');
      if (!el) { return; }
      var f = el.querySelector('.fail'), rc = el.querySelector('.rc');
      if (r.delivery === 'failed') {
        if (rc) { rc.remove(); }
        if (!f) { f = document.createElement('small'); f.className = 'fail'; el.appendChild(f); }
        f.textContent = __t('Not delivered:') + ' ' + (r.delivery_error || __t('unknown problem'));
      } else if (r.receipt && el.classList.contains('bot') + el.classList.contains('staff') > 0) {
        if (f) { f.remove(); }
        if (!rc) { rc = document.createElement('small'); rc.className = 'rc'; el.appendChild(rc); }
        rc.className = 'rc ' + r.receipt;
        rc.textContent = r.receipt === 'read' ? '\u2713\u2713 ' + __t('Read') : (r.receipt === 'delivered' ? '\u2713\u2713 ' + __t('Delivered') : '\u2713 ' + __t('Sent'));
      }
    });
  }

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
    div.dataset.mid = m.id;
    var body = document.createElement('div'); body.className = 'mt'; body.textContent = m.text || '';
    if (m.private && !sys) { var lock = document.createElement('span'); lock.className = 'lock'; lock.textContent = __t('Private note'); div.appendChild(lock); }
    if (m.media) {
      body.textContent = m.media.caption || '';
      div.appendChild(mediaEl(m));
      if (m.media.caption) { div.appendChild(body); }
    } else {
      div.appendChild(body);
    }
    if (!sys) {
      var t = document.createElement('small');
      var who = m.private ? '' : (m.from === 'bot' ? __t('Bot') : (m.from === 'staff' ? __t('Staff') : ''));
      t.textContent = [who, when ? when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''].filter(Boolean).join(' · ');
      div.appendChild(t);
    }
    logEl.appendChild(div);
    applyReceipts([m]);
    if (m.from === 'customer') { unseen++; }
  }

  function addAiCard(m) {
    var card = document.createElement('div');
    card.className = 'm ai-card';
    var head = document.createElement('b');
    head.textContent = __t('AI suggestion') + (m.ai && m.ai.intent ? ' (' + m.ai.intent + (m.ai.mood ? ', ' + m.ai.mood : '') + ')' : '');
    var body = document.createElement('div');
    body.style.margin = '6px 0 8px'; body.textContent = m.text;
    var row = document.createElement('div');
    row.className = 'bar'; row.style.margin = '0';
    function button(label, cls, fn) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'btn small ' + cls; b.textContent = label;
      b.addEventListener('click', fn); row.appendChild(b);
    }
    button(__t('Send now'), 'primary', function () {
      post('/ai/' + m.id + '/send', {}).then(function (d) {
        if (d && d.error) { toast(d.error, true); return; }
        card.remove(); refreshThread(); loadList();
      });
    });
    button(__t('Edit first'), '', function () { setMode('reply'); textEl.value = m.text; grow(); textEl.focus(); });
    button(__t('Dismiss'), '', function () { post('/ai/' + m.id + '/dismiss', {}).then(function () { card.remove(); }); });
    card.appendChild(head); card.appendChild(body); card.appendChild(row);
    logEl.appendChild(card);
  }

  // ---------- header, info bar ----------
  function showMeta(c) {
    document.getElementById('btn-summarize').style.display = c.ai_on ? '' : 'none';
    document.getElementById('thread-title').textContent = c.name;
    document.getElementById('thread-av').textContent = (c.name || '?').trim().charAt(0).toUpperCase();
    var tm = document.getElementById('thread-meta');
    tm.textContent = '';
    var tch = document.createElement('span'); tch.className = 'chan ch-' + c.channel; tch.textContent = c.channel_label || c.channel; tm.appendChild(tch);
    tm.appendChild(document.createTextNode(' ' + (c.status === 'open' ? __t('open') : __t('resolved'))));
    var tav = document.getElementById('thread-av'); tav.className = (tav.className || '').replace(/\bch-\w+/g, '').trim() + ' ch-' + c.channel;
    var badge = document.getElementById('mode-badge');
    badge.textContent = c.mode === 'human' ? __t('You are handling this') : __t('Bot is answering');
    badge.className = 'tag ' + (c.mode === 'human' ? 'warn' : 'ok');
    badge.title = c.handover_reason || '';
    document.getElementById('btn-give-back').style.display = c.mode === 'human' ? '' : 'none';
    document.getElementById('more').style.display = (c.ai_on || c.mode === 'human') ? '' : 'none';
    var rb = document.getElementById('btn-resolve');
    rb.dataset.state = c.status; rb.textContent = c.status === 'resolved' ? __t('Reopen') : __t('Resolve');
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
      ? __t('The 24-hour window is closed. You can only send an approved template.')
      : __t('The 24-hour window is closed. You can reply once the customer messages you again.');
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
      a.textContent = (c.customer.name || __t('Customer')) + (c.customer.phone ? ' · ' + c.customer.phone : '');
      cust.appendChild(a);
    } else { cust.textContent = __t('No customer details yet'); }
    var tagsEl = document.getElementById('info-tags');
    tagsEl.innerHTML = '';
    // One label for an upset customer (the AI's reading); everything else the AI notices is already a tag.
    if (['angry', 'upset', 'frustrated', 'annoyed', 'negative'].indexOf(String(c.ai_mood || '').toLowerCase()) !== -1) {
      var up = document.createElement('span'); up.className = 'tag hot'; up.textContent = __t('Upset customer'); up.title = __t('The AI thinks the customer is upset'); tagsEl.appendChild(up);
    }
    var have = {};
    c.tags.forEach(function (t) {
      have[t.id] = true;
      var chip = document.createElement('span');
      chip.className = 'chip'; chip.style.background = t.color; chip.style.cursor = 'pointer';
      chip.title = __t('Click to remove'); chip.textContent = __t(t.name) + ' ×';
      chip.addEventListener('click', function () { post('/tag', { tag_id: t.id, on: false }).then(refreshInfo); });
      tagsEl.appendChild(chip);
    });
    var sel = document.getElementById('tag-add');
    sel.innerHTML = ''; sel.appendChild(new Option(__t('+ Tag'), ''));
    (data.all_tags || []).forEach(function (t) { if (!have[t.id]) { sel.appendChild(new Option(__t(t.name), t.id)); } });
    sel.style.display = (data.all_tags || []).length ? '' : 'none';

    var as = document.getElementById('assign');
    as.innerHTML = ''; as.appendChild(new Option(__t('Unassigned'), ''));
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
      applyReceipts(data.receipts);
      if (askChanged) { askChanged = false; suggest(); }
      if (stick) { toBottom(); }
      else if (unseen > 0) { jumpBtn.textContent = (unseen === 1 ? __t('1 new message') : __t(':n new messages', { n: unseen })) + ' ↓'; jumpBtn.classList.remove('hidden'); }
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
    document.getElementById('btn-send').textContent = m === 'note' ? __t('Save note') : __t('Send reply');
    document.getElementById('c-hint').textContent = m === 'note' ? __t('Only your team sees private notes. The customer never does.') : __t('Replying makes you the owner of the chat, so the bot stays quiet.');
    textEl.placeholder = m === 'note' ? __t('Write a private note for your team') : __t('Write a reply. Enter sends, Shift+Enter starts a new line.');
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
  // Attach a photo, video, voice note or file: the text in the box becomes its caption.
  var fileEl = document.getElementById('file-pick'), attachBtn = document.getElementById('btn-attach'), attachLabel = document.getElementById('attach-label');
  attachBtn.addEventListener('click', function () { if (mode === 'note') { setMode('reply'); } fileEl.click(); });
  fileEl.addEventListener('change', function () {
    var f = fileEl.files[0]; fileEl.value = '';
    if (!f || !current) { return; }
    if (f.size > 16 * 1024 * 1024) { toast(__t('That file is larger than 16 MB.'), true); return; }
    var fd = new FormData(); fd.append('file', f); fd.append('caption', textEl.value.trim());
    attachBtn.disabled = true; attachLabel.textContent = __t('Sending...');
    api('/conversations/' + current + '/attach', { method: 'POST', body: fd }).then(function (d) {
      if (d && (d.error || d.message === undefined && d.errors)) { toast(d.error || (d.errors && d.errors.file && d.errors.file[0]) || __t('Could not send the file.'), true); } else { textEl.value = ''; grow(); }
      refreshThread(false, true); loadList();
    }).catch(function () { toast(__t('Could not send the file.'), true); }).then(function () { attachBtn.disabled = false; attachLabel.textContent = __t('Attach'); });
  });
  document.getElementById('btn-send').addEventListener('click', send);
  textEl.addEventListener('input', grow);
  textEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); } });

  jumpBtn.addEventListener('click', toBottom);
  logEl.addEventListener('scroll', function () { if (nearBottom()) { jumpBtn.classList.add('hidden'); unseen = 0; } });
  document.getElementById('btn-back').addEventListener('click', function () { root.classList.remove('chat-open'); });

  // ---------- header buttons ----------
  var moreMenu = document.getElementById('more-menu');
  document.getElementById('more-btn').addEventListener('click', function (e) { e.stopPropagation(); moreMenu.hidden = !moreMenu.hidden; });
  document.addEventListener('click', function () { moreMenu.hidden = true; });
  moreMenu.addEventListener('click', function () { moreMenu.hidden = true; });
  document.getElementById('btn-give-back').addEventListener('click', function () {
    post('/give-back').then(function () { toast(__t('The bot is answering this chat again.')); refreshThread(); loadList(); });
  });
  document.getElementById('btn-summarize').addEventListener('click', function () {
    var b = this; b.disabled = true; b.textContent = __t('Summarising...');
    post('/summarize', {}).then(function (d) {
      b.disabled = false; b.textContent = __t('Summarise with AI');
      if (d && d.error) { toast(d.error, true); return; }
      refreshThread(false, true);
    });
  });
  document.getElementById('btn-resolve').addEventListener('click', function () {
    var resolved = this.dataset.state === 'resolved';
    post(resolved ? '/reopen' : '/resolve').then(function () { toast(resolved ? __t('Chat reopened.') : __t('Chat resolved.')); refreshThread(); loadList(); });
  });
  document.getElementById('tag-add').addEventListener('change', function () {
    if (this.value) { post('/tag', { tag_id: parseInt(this.value, 10), on: true }).then(refreshInfo); }
  });
  document.getElementById('assign').addEventListener('change', function () {
    post('/assign', { user_id: this.value ? parseInt(this.value, 10) : null }).then(function () { toast(__t('Saved.')); loadList(); });
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
    }).then(function (r) { taskDlg.close(); toast(r.ok ? __t('Task added. You will find it under Tasks.') : __t('Could not add the task.'), !r.ok); });
  });

  var tplDlg = wire(document.getElementById('tpl-dialog'));
  var tplPick = null;
  function tplPreview() {
    var box = document.getElementById('tpl-preview');
    if (!tplPick) { box.textContent = __t('Choose a template.'); return; }
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
        var hints = [__t('Customer name'), __t('Service'), __t('Date and time')];
        for (var i = 1; i <= n; i++) {
          var f = document.createElement('label'); f.className = 'f';
          var sp = document.createElement('span'); sp.textContent = __t('Part :n', { n: i }) + (hints[i - 1] ? ' (' + hints[i - 1].toLowerCase() + ')' : '');
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
      tplDlg.close(); toast(__t('Template sent.')); refreshThread(false, true); loadList();
    });
  });

  // ---------- saved replies (in the customer's language, {name} and {business} filled in) ----------
  var qr = document.getElementById('qr'), allReplies = [], lastAsk = '', askChanged = false;
  api('/quick-replies').then(function (d) {
    var groups = {};
    (d.replies || []).forEach(function (r) {
      allReplies.push({ id: r.id, title: r.title, en: r.body.en || '', zh: r.body.zh || '' });
      var g = r.category || __t('Other');
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
  if (channelSel) { channelSel.addEventListener('change', function () { try { sessionStorage.setItem('inbox-channel', channelSel.value); } catch (e) { /* fine */ } loadList(); }); }

  loadList();
  var wanted = parseInt(new URLSearchParams(location.search).get('c') || '', 10);
  if (wanted) { openConversation(wanted); }
  setInterval(loadList, 4000);
  setInterval(refreshThread, 3000);
})();
