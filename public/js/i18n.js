// Page scripts say __t('English words'); in Chinese the layout fills window.I18N with the translations.
window.__t = function (text, vars) {
  var dict = window.I18N || {}, out = Object.prototype.hasOwnProperty.call(dict, text) ? dict[text] : text;
  if (vars) { Object.keys(vars).forEach(function (k) { out = out.split(':' + k).join(vars[k]); }); }
  return out;
};
