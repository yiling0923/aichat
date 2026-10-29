// Picks the saved replies that best fit what the customer just wrote. Plain word matching: no AI, no outside service.
(function () {
  // Words customers use, mapped to words saved replies use (English, Malay and Chinese).
  var SYN = {
    price: ['price', 'cost', 'how much', 'fee', 'fees', 'charge', 'rate', 'rates', 'harga', 'berapa', '价', '价格', '价钱', '多少钱', '收费', '费用'],
    hours: ['hours', 'open', 'opening', 'close', 'closing', 'time', 'when', 'buka', 'tutup', '营业', '几点', '开门', '关门', '时间'],
    location: ['where', 'location', 'address', 'direction', 'directions', 'map', 'parking', 'park', 'alamat', 'lokasi', '地址', '哪里', '在哪', '停车', '怎么去'],
    book: ['book', 'booking', 'appointment', 'reserve', 'slot', 'available', 'tempah', '预约', '预订', '订位', '有空'],
    aftercare: ['aftercare', 'after', 'care', 'swelling', 'redness', 'sun', 'makeup', 'wash', '术后', '护理', '注意', '红肿'],
    cancel: ['cancel', 'reschedule', 'change', 'postpone', 'batal', '取消', '改期', '更改'],
    pay: ['pay', 'payment', 'deposit', 'card', 'transfer', 'bayar', '付款', '定金', '转账'],
    thanks: ['thanks', 'thank', 'thx', 'terima', '谢谢', '多谢'],
    hello: ['hi', 'hello', 'hey', 'hai', '你好', '您好', '在吗']
  };
  var STOP = { the: 1, and: 1, you: 1, for: 1, are: 1, can: 1, with: 1, this: 1, that: 1, have: 1, what: 1, your: 1, please: 1, our: 1, will: 1, from: 1, not: 1, any: 1, there: 1 };

  Object.keys(SYN).forEach(function (k) { SYN[k] = SYN[k].map(function (w) { return /^[a-z]+$/.test(w) && w.length > 3 ? w.replace(/s$/, '') : w; }); });

  function tokens(text) {
    var t = String(text || '').toLowerCase().replace(/\{[a-z]+\}/g, ' '), out = [];
    (t.match(/[a-z0-9]{2,}/g) || []).forEach(function (w) { if (!STOP[w]) { out.push(w.length > 3 ? w.replace(/s$/, '') : w); } });
    var han = t.match(/[一-鿿]+/g) || [];
    han.forEach(function (run) { for (var i = 0; i < run.length - 1; i++) { out.push(run.substr(i, 2)); } if (run.length === 1) { out.push(run); } });
    return out;
  }
  function concepts(toks, raw) {
    var hit = {}, lower = String(raw || '').toLowerCase();
    Object.keys(SYN).forEach(function (k) {
      SYN[k].forEach(function (w) { if (toks.indexOf(w) !== -1 || ((/[一-鿿]/.test(w) || w.indexOf(' ') !== -1) && lower.indexOf(w) !== -1)) { hit[k] = true; } });
    });
    if (/(rm|\$|usd|myr|sgd)\s?\d/.test(lower)) { hit.price = true; }
    return hit;
  }

  // replies: [{id, title, en, zh}]. Returns up to `max` best, most likely first, only when something really matches.
  function rank(text, replies, max) {
    var qt = tokens(text); if (!qt.length) { return []; }
    var qc = concepts(qt, text), scored = [];
    replies.forEach(function (r) {
      var title = tokens(r.title), body = tokens((r.en || '') + ' ' + (r.zh || '')), s = 0;
      var tc = concepts(title, r.title), bc = concepts(body, (r.en || '') + ' ' + (r.zh || ''));
      qt.forEach(function (w) { if (title.indexOf(w) !== -1) { s += 3; } else if (body.indexOf(w) !== -1) { s += 1; } });
      Object.keys(qc).forEach(function (k) { if (tc[k]) { s += 5; } else if (bc[k]) { s += 2; } });
      if (s >= 4) { scored.push({ reply: r, score: s }); }
    });
    scored.sort(function (a, b) { return b.score - a.score; });
    return scored.slice(0, max || 3).map(function (x) { return x.reply; });
  }
  var api = { rank: rank };
  if (typeof window !== 'undefined') { window.aichatSuggest = api; }
  if (typeof module !== 'undefined') { module.exports = api; }
})();
