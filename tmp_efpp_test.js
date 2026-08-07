// Load the rendered plugin script (docker temp generation is fragile; we inline the logic).
const fs = require('fs');
let code = fs.readFileSync('/var/folders/jb/lpqbm1_s0ql_0693mn49kkf80000gn/T/opencode/efpp_node.test', 'utf8');

// Append the functional part of pages.php (paraphrased from the page source).
code += `
function efppCustomBadge(page) {
    var ta = page === 'login' ? '#efpp_login_page' : '#efpp_change_pw_page';
    var badge = page === 'login' ? '#efpp_badge_login' : '#efpp_badge_change';
    var current = String($(ta).val()).replace(/\\r\\n/g, "\\n").trim();
    var original = String(EFPP_TPL[page]).replace(/\\r\\n/g, "\\n").trim();
    var custom = current !== original;
    $(badge).text(custom ? 'Customized' : 'Default').attr('class', 'efpp-custom-badge ' + (custom ? 'efpp-custom' : 'efpp-default'));
}
var efppPages = {};
window.efppPages = efppPages;
`;

// Build a tiny DOM-ish stub
function makeEl(text) {
  const el = {
    _val: text,
    val: function () { return this._val; },
    text: function () { return this; },
    attr: function () { return this; },
    removeClass: function () { return this; },
    addClass: function () { return this; },
  };
  return el;
}
let currentVal = ''; // set below
global.window = { location: { hostname: 'x', port: '' }, bootstrap: {} };
global.document = { querySelectorAll: () => [] };
global._txtarea = { val: () => currentVal };
global.$ = function (sel) {
  // textarea or badge, both just need val/text/attr stub
  return { val: () => currentVal, text: () => this, attr: () => this, removeClass: () => this, addClass: () => this };
};
// For DOM ready, we call the badge fns manually.
new Function('$', 'document', code)(global.$, global.document);

console.log('EFPP_TPL keys:', Object.keys(EFPP_TPL));
console.log('login len', EFPP_TPL.login.length, 'change len', EFPP_TPL.change.length);
console.log('efppPages defined:', typeof efppPages);

// Test: current == template -> Default
currentVal = EFPP_TPL['login'];
global.efCustom = null;
// run badge manually by calling it via added function? we didn't add switch but check EFPP_TPL decode round-trip
// Eval comparison manually:
function cmp(page) {
  const cur = String(EFPP_TPL[page]).replace(/\r\n/g, "\n").trim();
  const orig = String(EFPP_TPL[page]).replace(/\r\n/g, "\n").trim();
  return cur === orig ? 'Default' : 'Customized';
}
console.log('self-compare (must be Default):', t => cmp('login'));
`;