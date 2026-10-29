// Filters apply by themselves: change a list or pick a date and the page updates; type in a search box and it updates
// after a short pause. The Search / Filter / Apply buttons are hidden because nothing needs pressing any more.
(function () {
  var KEY = 'aichat-af-focus';
  var forms = document.querySelectorAll('main form[method="get"], .content form[method="get"], form.filters[method="get"]');
  var seen = [];

  function isFilter(f) {
    if (seen.indexOf(f) > -1 || f.hasAttribute('data-no-auto') || f.closest('dialog')) { return false; }
    return f.querySelector('select, input[type=search], input[type=text], input[type=date]') !== null;
  }

  forms.forEach(function (f) {
    if (!isFilter(f)) { return; }
    seen.push(f);
    var timer = null;

    function go(field) {
      clearTimeout(timer);
      try { sessionStorage.setItem(KEY, JSON.stringify({ name: field.name, form: seen.indexOf(f), pos: field.selectionStart })); } catch (e) { /* storage may be blocked */ }
      f.submit();
    }

    f.querySelectorAll('select, input[type=date], input[type=checkbox], input[type=radio]').forEach(function (el) {
      el.addEventListener('change', function () {
        // "Custom dates" waits until both dates are chosen.
        if (el.name === 'period' && el.value === 'custom') { return; }
        var from = f.querySelector('input[name=from]'), to = f.querySelector('input[name=to]');
        if (el.type === 'date' && (!from || !to || !from.value || !to.value)) { return; }
        go(el);
      });
    });

    f.querySelectorAll('input[type=search], input[type=text]').forEach(function (el) {
      var last = el.value;
      el.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { if (el.value !== last) { last = el.value; go(el); } }, 450);
      });
    });

    f.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (b) { b.classList.add('af-hide'); });
  });

  // After the page reloads, put the cursor back where the person was typing.
  try {
    var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
    sessionStorage.removeItem(KEY);
    if (saved && seen[saved.form]) {
      var el = seen[saved.form].querySelector('[name="' + saved.name + '"]');
      if (el && (el.type === 'search' || el.type === 'text')) {
        el.focus();
        var p = typeof saved.pos === 'number' ? saved.pos : el.value.length;
        try { el.setSelectionRange(p, p); } catch (e) { /* not supported on this input */ }
      }
    }
  } catch (e) { /* ignore */ }
})();
