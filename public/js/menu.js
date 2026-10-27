// Menu builder. Plain JavaScript. Edits a draft in memory, saves it, previews it.
(function () {
  var root = document.getElementById('menu');
  var base = root.dataset.base;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var flow = null;
  var meta = { languages: ['en', 'zh'], fields: [], types: [] };
  var selected = null;
  var LANG = { en: 'English', zh: '中文' };
  var TYPE = { options: 'Question with buttons', message: 'Message', ask: 'Ask for details', save_lead: 'Save as lead', handover: 'Hand over to staff' };
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

  function field(label, input) { return el('label', { class: 'f' }, [el('span', { text: label }), input]); }

  function textInputs(map, onChange, multiline) {
    var wrap = el('div', { class: 'langs' });
    meta.languages.forEach(function (code) {
      var input = multiline ? el('textarea', { rows: '3' }) : el('input', { type: 'text' });
      input.value = (map && map[code]) || '';
      input.placeholder = LANG[code] || code;
      input.addEventListener('input', function () { onChange(code, input.value); });
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
    tag.textContent = data.unpublished ? 'Unpublished changes' : 'Published';
    tag.className = 'tag ' + (data.unpublished ? 'warn' : 'ok');
  }

  function renderSteps() {
    var list = document.getElementById('step-list');
    list.innerHTML = '';
    Object.keys(flow.nodes).forEach(function (id) {
      var n = flow.nodes[id];
      var d = el('div', { class: 'conv' + (id === selected ? ' on' : '') }, [
        el('b', { text: id }), el('span', { class: 'tag', text: TYPE[n.type] || n.type }),
        el('p', { text: (n.text && (n.text.en || n.text.zh)) || '' })
      ]);
      if (id === flow.start) { d.appendChild(el('span', { class: 'tag ok', text: 'start' })); }
      d.addEventListener('click', function () { selected = id; render(); });
      list.appendChild(d);
    });
  }

  function renderEditor() {
    var box = document.getElementById('editor');
    box.innerHTML = '';
    var n = selected && flow.nodes[selected];
    if (!n) { box.appendChild(el('div', { class: 'empty', text: 'Select a step on the left.' })); return; }

    box.appendChild(el('h3', { text: 'Step: ' + selected }));
    box.appendChild(field('Type', select(meta.types, n.type, function (t) { return TYPE[t] || t; }, function (v) { n.type = v; render(); })));
    n.text = n.text || {};
    box.appendChild(field('What the bot says', textInputs(n.text, function (c, v) { n.text[c] = v; renderSteps(); }, true)));
    box.appendChild(el('p', { class: 'mute small-note', text: 'You can use {name}, {phone}, {interest}, {preferred_time} and {business}.' }));

    if (n.type === 'ask') {
      box.appendChild(field('Collects', select(meta.fields, n.field, function (f) { return FIELD[f] || f; }, function (v) { n.field = v; if (v === 'phone') { n.validate = 'phone'; } else { delete n.validate; } }, 'Choose...')));
    }
    if (n.type === 'save_lead') {
      box.appendChild(field('Lead score', select(['hot', 'warm', 'cold'], n.score || 'warm', null, function (v) { n.score = v; })));
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
    if (n.type !== 'options' && n.type !== 'handover') {
      box.appendChild(field('Then go to', select(stepOptions(), n.goto, null, function (v) { if (v) { n.goto = v; } else { delete n.goto; } }, n.type === 'ask' ? 'Choose...' : 'Main menu')));
    }
    if (n.type === 'options') { renderOptions(box, n); }

    var actions = el('div', { class: 'bar' });
    var start = el('button', { class: 'btn', type: 'button', text: 'Make this the start step' });
    start.addEventListener('click', function () { flow.start = selected; render(); });
    var del = el('button', { class: 'btn danger', type: 'button', text: 'Delete step' });
    del.addEventListener('click', function () {
      if (!confirm('Delete step "' + selected + '"?')) { return; }
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
      var kw = el('input', { type: 'text', placeholder: 'words that also pick this button, separated by commas' });
      kw.value = (o.keywords || []).join(', ');
      kw.addEventListener('input', function () { o.keywords = kw.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean); });
      card.appendChild(field('Typed words', kw));
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

  function render() { renderSteps(); renderEditor(); }

  function load(data) {
    flow = data.flow; meta = data;
    if (!selected || !flow.nodes[selected]) { selected = flow.start; }
    renderProblems(data);
    render();
  }

  function save() { return api('', 'PUT', { flow: flow }).then(function (d) { load(d); return d; }); }

  document.getElementById('btn-save').addEventListener('click', save);
  document.getElementById('btn-publish').addEventListener('click', function () {
    save().then(function () { return api('/publish', 'POST'); }).then(function (d) {
      load(d);
      if (d._status === 422) { window.scrollTo(0, 0); } else { restartPreview(); }
    });
  });
  document.getElementById('btn-reset').addEventListener('click', function () {
    if (confirm('Replace the draft with the sample menu? Your published menu stays live until you publish.')) {
      api('/reset', 'POST').then(function (d) { selected = null; load(d); });
    }
  });
  document.getElementById('btn-add').addEventListener('click', function () {
    var id = prompt('Step id (lower case letters, numbers, underscores):', 'new_step');
    if (!id) { return; }
    id = id.toLowerCase().replace(/[^a-z0-9_]/g, '_').slice(0, 40);
    if (flow.nodes[id]) { alert('That id is already used.'); return; }
    flow.nodes[id] = { type: 'message', text: {}, goto: 'main' };
    selected = id; render();
  });

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
  document.getElementById('btn-restart').addEventListener('click', function () { save().then(restartPreview); });

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
