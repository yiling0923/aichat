// "Translate" button and the "empty box" reminder next to the English and Chinese boxes.
(function () {
  var NAMES = { en: 'English', zh: '中文' };
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
  var urlMeta = document.querySelector('meta[name="translate-url"]');

  function call(text, from, to) {
    return fetch(urlMeta.content, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ text: text, from: from, to: to })
    }).then(function (r) {
      return r.json().then(function (j) {
        var why = j.message || (j.errors && Object.values(j.errors)[0] && Object.values(j.errors)[0][0]) || __t('Could not translate.');
        return r.ok && j.translation ? { ok: true, text: j.translation } : { ok: false, message: why };
      });
    }).catch(function () { return { ok: false, message: __t('Could not translate.') }; });
  }

  // opts: box (the field to fill), from, to ('en' or 'zh'), source (function returning the text to translate),
  //       hint (true: say what customers see while the box is empty), anchor (the row goes after this element; default the box)
  function attach(opts) {
    if (!urlMeta || !opts.box) { return; }
    var box = opts.box, row = document.createElement('div'), btn = document.createElement('button'), msg = document.createElement('small'), hint = null;
    row.className = 'tr-row';
    btn.type = 'button'; btn.className = 'btn small tr-btn'; btn.textContent = '⇄ ' + __t('Translate from :lang', { lang: NAMES[opts.from] });
    btn.title = __t('Uses the AI. Check the result before you save.');
    msg.className = 'tr-msg'; msg.setAttribute('role', 'status');
    row.appendChild(btn); row.appendChild(msg);
    if (opts.hint) {
      hint = document.createElement('small'); hint.className = 'mute tr-hint';
      hint.textContent = __t('Empty: Chinese customers will see the English text.');
      row.appendChild(hint);
    }
    (opts.anchor || box).insertAdjacentElement('afterend', row);

    function sync() { if (hint) { hint.hidden = box.value.trim() !== ''; } }
    box.addEventListener('input', sync);
    box.addEventListener('translate-sync', sync);
    sync();

    btn.addEventListener('click', function () {
      var text = (opts.source() || '').trim();
      msg.className = 'tr-msg'; msg.textContent = '';
      if (text === '') { msg.className = 'tr-msg bad'; msg.textContent = __t('Write the :lang text first.', { lang: NAMES[opts.from] }); return; }
      if (box.value.trim() !== '' && !confirm(__t('Replace what is in this box with the translation?'))) { return; }
      btn.disabled = true; var label = btn.textContent; btn.textContent = __t('Translating...');
      call(text, opts.from, opts.to).then(function (r) {
        btn.disabled = false; btn.textContent = label;
        if (!r.ok) { msg.className = 'tr-msg bad'; msg.textContent = r.message; return; }
        box.value = r.text;
        box.dispatchEvent(new Event('input', { bubbles: true }));
        msg.className = 'tr-msg ok'; msg.textContent = __t('Translated. Please read it before you save.');
      });
    });
  }

  window.Translate = { attach: attach };
})();
