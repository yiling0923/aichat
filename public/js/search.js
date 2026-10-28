// Quick search at the top of every staff page. Ctrl+K (or /) focuses it, Esc closes, Enter opens the first result.
(function () {
  var input = document.getElementById('gsearch');
  var box = document.getElementById('gresults');
  if (!input) { return; }
  var timer = null, items = [], active = -1, token = 0;

  function close() { box.hidden = true; active = -1; }

  function render(data) {
    box.innerHTML = '';
    items = [];
    [['Customers', data.customers], ['Chats', data.chats]].forEach(function (g) {
      if (!g[1].length) { return; }
      var h = document.createElement('div'); h.className = 'gh'; h.textContent = g[0]; box.appendChild(h);
      g[1].forEach(function (r) {
        var a = document.createElement('a'); a.href = r.url; a.className = 'gr';
        var t = document.createElement('b'); t.textContent = r.title; a.appendChild(t);
        var s = document.createElement('span'); s.textContent = r.sub || ''; a.appendChild(s);
        box.appendChild(a); items.push(a);
      });
    });
    if (!items.length) { var n = document.createElement('div'); n.className = 'gn'; n.textContent = 'Nothing found'; box.appendChild(n); }
    box.hidden = false; active = -1;
  }

  function mark() { items.forEach(function (a, i) { a.classList.toggle('on', i === active); }); }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { close(); return; }
    timer = setTimeout(function () {
      var mine = ++token;
      fetch(input.dataset.url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); }).then(function (d) { if (mine === token) { render(d); } }).catch(close);
    }, 200);
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(items.length - 1, active + 1); mark(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); mark(); }
    else if (e.key === 'Enter') { var t = items[active >= 0 ? active : 0]; if (t) { e.preventDefault(); location.href = t.href; } }
    else if (e.key === 'Escape') { close(); input.blur(); }
  });

  document.addEventListener('keydown', function (e) {
    var typing = /^(input|textarea|select)$/i.test((e.target.tagName || ''));
    if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (e.key === '/' && !typing)) { e.preventDefault(); input.focus(); input.select(); }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.gsearch')) { close(); } });
})();
