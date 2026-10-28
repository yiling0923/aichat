// Menu builder. Plain JavaScript. Edits a draft in memory, saves it, previews it.
(function () {
  var root = document.getElementById('menu');
  var base = root.dataset.base;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var flow = null;
  var meta = { languages: ['en', 'zh'], fields: [], types: [] };
  var selected = null;
  var LANG = { en: 'English', zh: '中文' };
  var TYPE = { options: 'Question with buttons', message: 'Message', ask: 'Ask for details', save_lead: 'Save as lead', handover: 'Hand over to staff', booking: 'Booking (service, day, time)', my_booking: 'My booking (view or cancel)' };
  var HELP = {
    options: 'Asks a question and shows buttons. Customers tap one, or type a word you set for it.',
    message: 'Shows some text (prices, hours, an address), then moves on.',
    ask: 'Asks for one detail, such as a name or phone number, and remembers the answer.',
    save_lead: 'Saves the customer as a lead with what the bot has collected so far.',
    handover: 'Passes the chat to your team. The bot goes quiet until you give it back.',
    booking: 'The built-in booking: service, day, time, name, phone. Uses Booking setup.',
    my_booking: 'Lets the customer see or cancel their booking.'
  };
  var snapshot = '', toastTimer = null, lastText = null;
  var FIELD = { name: 'Name', phone: 'Phone', preferred_time: 'Preferred time', interest: 'Interest', note: 'Note' };

  function api(path, method, body) {
    return fetch(base + path, {
      method: method || 'GET',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }

  function el(tag, attrs, children) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { e.textContent = attrs[k]; } else if (k === 'class') { e.className = attrs[k]; } else { e.setAttribute(k, attrs[k]); }
    });
    (children || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }

  function toast(text, bad) {
    var t = document.getElementById('toast');
    t.textContent = text; t.className = 'toast' + (bad ? ' bad' : ''); t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.hidden = true; }, 2600);
  }

  function dirty() { return flow !== null && JSON.stringify(flow) !== snapshot; }

  function refreshState() {
    var tag = document.getElementById('state-tag'), d = dirty();
    if (d) { tag.textContent = 'Unsaved changes'; tag.className = 'tag warn'; }
    else if (tag.dataset.published === '0') { tag.textContent = 'Saved, not published'; tag.className = 'tag warn'; }
    else { tag.textContent = 'Published'; tag.className = 'tag ok'; }
    document.getElementById('btn-save').disabled = !d;
  }

  function references(id) {
    var by = [];
    Object.keys(flow.nodes).forEach(function (k) {
      var n = flow.nodes[k];
      if (k === id) { return; }
      if (n.goto === id || (n.options || []).some(function (o) { return o.goto === id; })) { by.push(k); }
    });
    return by;
  }

  function field(label, input) { return el('label', { class: 'f' }, [el('span', { text: label }), input]); }

  function textInputs(map, onChange, multiline) {
    var wrap = el('div', { class: 'langs' });
    meta.languages.forEach(function (code) {
      var input = multiline ? el('textarea', { rows: '3' }) : el('input', { type: 'text' });
      input.value = (map && map[code]) || '';
      input.placeholder = LANG[code] || code;
      input.addEventListener('input', function () { onChange(code, input.value); });
      input.addEventListener('focus', function () { lastText = input; });
      wrap.appendChild(el('div', { class: 'lang' }, [el('i', { text: code.toUpperCase() }), input]));
    });
    return wrap;
  }

  function stepOptions(except) {
    return Object.keys(flow.nodes).filter(function (id) { return id !== except; });
  }

  function select(values, current, labelFn, onChange, blank) {
    var s = document.createElement('select');
    if (blank) { s.appendChild(new Option(blank, '')); }
    values.forEach(function (v) { s.appendChild(new Option(labelFn ? labelFn(v) : v, v)); });
    s.value = current || '';
    s.addEventListener('change', function () { onChange(s.value); });
    return s;
  }

  function renderProblems(data) {
    var box = document.getElementById('problems');
    box.innerHTML = '';
    (data.errors || []).forEach(function (m) { box.appendChild(el('div', { class: 'note err', text: m })); });
    (data.warnings || []).forEach(function (m) { box.appendChild(el('div', { class: 'note warn', text: m })); });
    var tag = document.getElementById('state-tag');
    tag.dataset.published = data.unpublished ? '0' : '1';
  }

  function renderSteps() {
    var list = document.getElementById('step-list');
    list.innerHTML = '';
    Object.keys(flow.nodes).forEach(function (id) {
      var n = flow.nodes[id];
      var d = el('div', { class: 'conv step' + (id === selected ? ' on' : '') }, [
        el('div', { class: 'ct' }, [el('b', { text: id.replace(/_/g, ' ') }), el('span', { class: 'tag' + (id === flow.start ? ' ok' : ''), text: id === flow.start ? 'start' : (TYPE[n.type] || n.type) })]),
        el('p', { text: (n.text && (n.text.en || n.text.zh)) || 'No text yet' })
      ]);
      if (id === flow.start) { d.appendChild(el('small', { class: 'mute', text: TYPE[n.type] || n.type })); }
      d.addEventListener('click', function () { selected = id; render(); });
      list.appendChild(d);
    });
  }

  function renderEditor() {
    var box = document.getElementById('editor');
    box.innerHTML = '';
    var n = selected && flow.nodes[selected];
    if (!n) { box.appendChild(el('div', { class: 'empty', text: 'Select a step on the left.' })); return; }

    box.appendChild(el('div', { class: 'ed-head' }, [el('h3', { text: selected.replace(/_/g, ' ') }), el('span', { class: 'mute', text: 'step id: ' + selected })]));
    box.appendChild(field('What this step does', select(meta.types, n.type, function (t) { return TYPE[t] || t; }, function (v) { n.type = v; render(); })));
    box.appendChild(el('p', { class: 'mute small-note', style: 'padding:0;margin-top:-6px', text: HELP[n.type] || '' }));
    n.text = n.text || {};
    box.appendChild(field('What the bot says', textInputs(n.text, function (c, v) { n.text[c] = v; renderSteps(); }, true)));
    var chips = el('div', { class: 'var-row' }, [el('span', { class: 'mute', text: 'Insert:' })]);
    ['{name}', '{business}', '{phone}', '{interest}', '{preferred_time}'].forEach(function (v) {
      var b = el('button', { type: 'button', class: 'btn small', text: v });
      b.addEventListener('click', function () {
        var area = box.querySelector('textarea:focus') || lastText || box.querySelector('textarea');
        if (!area) { return; }
        var st = area.selectionStart == null ? area.value.length : area.selectionStart, en = area.selectionEnd == null ? st : area.selectionEnd;
        area.value = area.value.slice(0, st) + v + area.value.slice(en); area.focus();
        area.dispatchEvent(new Event('input', { bubbles: true }));
      });
      chips.appendChild(b);
    });
    box.appendChild(chips);

    if (n.type === 'ask') {
      box.appendChild(field('Collects', select(meta.fields, n.field, function (f) { return FIELD[f] || f; }, function (v) { n.field = v; if (v === 'phone') { n.validate = 'phone'; } else { delete n.validate; } }, 'Choose...')));
    }
    if (n.type === 'save_lead') {
      box.appendChild(field('Lead level', select(['hot', 'warm', 'cold'], n.score || 'warm', function (v) { return { hot: 'Ready to book', warm: 'Interested', cold: 'Just browsing' }[v]; }, function (v) { n.score = v; })));
      var cb = el('input', { type: 'checkbox' });
      cb.checked = n.notify !== false;
      cb.addEventListener('change', function () { n.notify = cb.checked; });
      box.appendChild(el('label', { class: 'f check' }, [cb, el('span', { text: 'Alert the team (n8n) when a lead is saved' })]));
    }
    if (n.type === 'handover') {
      var r = el('input', { type: 'text' });
      r.value = n.reason || '';
      r.addEventListener('input', function () { n.reason = r.value; });
      box.appendChild(field('Reason shown to staff', r));
    }
    if (n.type === 'booking' || n.type === 'my_booking') {
      box.appendChild(el('p', { class: 'mute small-note', text: 'Services, days and times come from Booking setup. No text is needed here.' }));
    }
    if (n.type !== 'options' && n.type !== 'handover') {
      box.appendChild(field('Then go to', select(stepOptions(), n.goto, null, function (v) { if (v) { n.goto = v; } else { delete n.goto; } }, n.type === 'ask' ? 'Choose...' : 'Main menu')));
    }
    if (n.type === 'options') { renderOptions(box, n); }

    var actions = el('div', { class: 'bar' });
    var start = el('button', { class: 'btn', type: 'button', text: 'Make this the start step' });
    start.addEventListener('click', function () { flow.start = selected; render(); });
    var del = el('button', { class: 'btn danger', type: 'button', text: 'Delete step' });
    del.addEventListener('click', function () {
      var used = references(selected);
      if (!confirm('Delete step "' + selected + '"?' + (used.length ? '\n\nThese steps link to it: ' + used.join(', ') + '. Those links will be empty and you must fix them before publishing.' : ''))) { return; }
      delete flow.nodes[selected]; selected = Object.keys(flow.nodes)[0] || null; render();
    });
    actions.appendChild(start); actions.appendChild(del);
    box.appendChild(actions);
  }

  function renderOptions(box, n) {
    n.options = n.options || [];
    box.appendChild(el('h4', { text: 'Buttons' }));
    n.options.forEach(function (o, i) {
      var card = el('div', { class: 'opt' });
      o.label = o.label || {};
      card.appendChild(el('div', { class: 'opt-head' }, [el('b', { text: 'Button ' + (i + 1) }), el('span', { class: 'mute', text: o.label.en || o.label.zh || 'no label yet' })]));
      card.appendChild(textInputs(o.label, function (c, v) { o.label[c] = v; }));
      var dest = o.action === 'handover' ? '__handover' : (o.goto || '');
      card.appendChild(field('Goes to', (function () {
        var s = document.createElement('select');
        s.appendChild(new Option('Choose...', ''));
        s.appendChild(new Option('Talk to staff (hand over)', '__handover'));
        stepOptions().forEach(function (id) { s.appendChild(new Option('Step: ' + id, id)); });
        s.value = dest;
        s.addEventListener('change', function () {
          if (s.value === '__handover') { o.action = 'handover'; delete o.goto; } else { delete o.action; if (s.value) { o.goto = s.value; } else { delete o.goto; } }
        });
        return s;
      })()));
      var kw = el('input', { type: 'text', placeholder: 'price, how much, 价格 (separate with commas)' });
      kw.value = (o.keywords || []).join(', ');
      kw.addEventListener('input', function () { o.keywords = kw.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean); });
      var more = el('details', { class: 'opt-more' }, [el('summary', { text: 'Typed words' + ((o.keywords || []).length ? ' (' + o.keywords.length + ')' : '') }), field('Words that also pick this button', kw)]);
      card.appendChild(more);
      var row = el('div', { class: 'bar' });
      [['Up', -1], ['Down', 1]].forEach(function (p) {
        var b = el('button', { class: 'btn small', type: 'button', text: p[0] });
        b.addEventListener('click', function () { var j = i + p[1]; if (j < 0 || j >= n.options.length) { return; } var t = n.options[i]; n.options[i] = n.options[j]; n.options[j] = t; render(); });
        row.appendChild(b);
      });
      var rm = el('button', { class: 'btn small danger', type: 'button', text: 'Remove' });
      rm.addEventListener('click', function () { n.options.splice(i, 1); render(); });
      row.appendChild(rm);
      card.appendChild(row);
      box.appendChild(card);
    });
    var add = el('button', { class: 'btn', type: 'button', text: '+ Add button' });
    add.addEventListener('click', function () {
      var k = 1; var ids = n.options.map(function (o) { return o.id; });
      while (ids.indexOf('opt_' + k) !== -1) { k++; }
      n.options.push({ id: 'opt_' + k, label: {}, goto: '' });
      render();
    });
    box.appendChild(add);
  }

  function render() { renderSteps(); renderEditor(); refreshState(); }

  function load(data) {
    flow = data.flow; meta = data; snapshot = JSON.stringify(flow);
    if (!selected || !flow.nodes[selected]) { selected = flow.start; }
    renderProblems(data);
    render();
  }

  function save(quiet) {
    return api('', 'PUT', { flow: flow }).then(function (d) {
      if (d._status && d._status >= 400 && !d.flow) { toast(d.message || 'Could not save. Check the red messages above.', true); return d; }
      load(d);
      if (quiet !== true) { toast('Draft saved'); }
      return d;
    });
  }

  document.getElementById('btn-save').addEventListener('click', function () { save(); });
  document.getElementById('btn-publish').addEventListener('click', function () {
    save(true).then(function () { return api('/publish', 'POST'); }).then(function (d) {
      load(d);
      if (d._status === 422) { window.scrollTo(0, 0); toast('Not published: fix the red messages first.', true); } else { restartPreview(); toast('Published. Customers see the new menu now.'); }
    });
  });
  document.getElementById('btn-reset').addEventListener('click', function () {
    if (confirm('Replace the draft with the sample menu? Your published menu stays live until you publish.')) {
      api('/reset', 'POST').then(function (d) { selected = null; load(d); });
    }
  });
  var addDlg = document.getElementById('add-dialog'), addType = document.getElementById('add-type');
  function addHelp() { document.getElementById('add-help').textContent = HELP[addType.value] || ''; }
  document.getElementById('btn-add').addEventListener('click', function () {
    addType.innerHTML = '';
    (meta.types || []).forEach(function (t) { addType.appendChild(new Option(TYPE[t] || t, t)); });
    addType.value = 'message'; addHelp();
    document.getElementById('add-name').value = '';
    addDlg.showModal(); document.getElementById('add-name').focus();
  });
  addType.addEventListener('change', addHelp);
  addDlg.addEventListener('click', function (e) { if (e.target === addDlg || e.target.closest('.dlg-x')) { addDlg.close(); } });
  function addStep() {
    var name = document.getElementById('add-name').value.trim();
    if (!name) { document.getElementById('add-name').focus(); return; }
    var base = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 36) || 'step', id = base, k = 2;
    while (flow.nodes[id]) { id = base + '_' + k++; }
    var type = addType.value;
    var node = { type: type, text: {} };
    if (type === 'options') { node.options = []; } else if (type !== 'handover') { node.goto = 'main'; }
    if (type === 'ask') { delete node.goto; }
    flow.nodes[id] = node;
    selected = id; addDlg.close(); render();
    toast('Step added. Link a button to it so customers can reach it.');
  }
  document.getElementById('add-ok').addEventListener('click', addStep);
  document.getElementById('add-name').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addStep(); } });

  // Keep edits safe: Ctrl+S saves, leaving with unsaved changes asks first.
  document.getElementById('editor').addEventListener('input', refreshState);
  document.getElementById('editor').addEventListener('change', refreshState);
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (dirty()) { save(); } } });
  window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });

  // Preview chat
  var pvLog = document.getElementById('pv-log');
  function pvLine(from, text, options) {
    pvLog.appendChild(el('div', { class: 'bubble ' + from, text: text }));
    if (options && options.length) {
      var row = el('div', { class: 'option-row' });
      options.forEach(function (o) {
        var b = el('button', { type: 'button', text: o.label });
        b.addEventListener('click', function () { pvSend(o.label, o.id); });
        row.appendChild(b);
      });
      pvLog.appendChild(row);
    }
    pvLog.scrollTop = pvLog.scrollHeight;
  }
  function pvSend(text, optionId, restart) {
    if (text) { pvLine('customer', text); }
    api('/preview', 'POST', { text: text || '', option_id: optionId || null, restart: !!restart }).then(function (d) {
      (d.messages || []).forEach(function (m) { pvLine(m.from, m.text, m.options); });
    });
  }
  function restartPreview() { pvLog.innerHTML = ''; pvSend('hi', null, true); }
  document.getElementById('pv-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var i = document.getElementById('pv-input'); var t = i.value.trim(); i.value = '';
    if (t) { pvSend(t); }
  });
  document.getElementById('btn-restart').addEventListener('click', function () { save(true).then(restartPreview); });

  function loadStats() {
    api('/stats').then(function (s) {
      var box = document.getElementById('stats');
      box.innerHTML = '';
      [['Chats', s.chats], ['Leads', s.leads], ['Handovers', s.handovers]].forEach(function (p) {
        box.appendChild(el('div', { class: 'stat' }, [el('b', { text: String(p[1]) }), el('span', { text: p[0] + ' (last ' + s.days + ' days)' })]));
      });
      var top = (s.top_options || []).map(function (o) { return o.option_id + ' (' + o.taps + ')'; }).join(', ');
      box.appendChild(el('div', { class: 'stat wide' }, [el('b', { text: top || '-' }), el('span', { text: 'Most tapped buttons' })]));
    });
  }

  api('').then(function (d) { load(d); restartPreview(); loadStats(); });
})();
