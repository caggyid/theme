/* CaggyShop glass.js (opsional)
   Panel Pterodactyl memakai class CSS yang di-hash, jadi tidak bisa dipilih lewat CSS biasa.
   Script ini hanya MEMBACA warna latar elemen; jika elemen berupa kartu gelap yang cukup besar,
   ia diberi atribut data-cs-glass supaya caggyshop.css bisa membuatnya berkaca.
   Selain itu, kotak ikon kartu statistik (Address, Uptime, CPU, dst.) diberi atribut data-cs-icon
   supaya CSS bisa mengganti ikonnya. Tidak mengubah DOM selain atribut, tidak mengirim data ke luar. */
(function () {
  if (window.__csGlass) return;
  window.__csGlass = true;

  var ATTR = 'data-cs-glass';
  var TAGS = { DIV: 1, A: 1, SECTION: 1, ASIDE: 1, NAV: 1, HEADER: 1, FOOTER: 1, MAIN: 1, ARTICLE: 1, UL: 1, LI: 1, FORM: 1 };
  var seen = new WeakSet();
  var queue = [];
  var scheduled = false;

  function isDarkOpaque(color) {
    var m = /rgba?\(([^)]+)\)/.exec(color);
    if (!m) return false;
    var p = m[1].split(/[ ,\/]+/).filter(Boolean).map(parseFloat);
    var a = p.length > 3 ? p[3] : 1;
    if (a < 0.9) return false;                      // sudah transparan: biarkan (mis. overlay modal)
    var r = p[0], g = p[1], b = p[2];
    return Math.max(r, g, b) < 115 && (Math.max(r, g, b) - Math.min(r, g, b)) < 70;
  }

  // Kotak ikon kartu statistik: <div><svg/></div> yang induknya memuat label pendek (Address, Uptime, ...)
  var ICONS = [['inbound', 'down'], ['outbound', 'up'], ['address', 'net'], ['uptime', 'time'], ['cpu', 'cpu'], ['memory', 'mem'], ['disk', 'disk']];
  function iconify(el) {
    if (el.tagName !== 'DIV' || el.children.length !== 1 || el.firstElementChild.tagName.toLowerCase() !== 'svg') return false;
    var p = el.parentElement;
    if (!p) return false;
    var t = (p.textContent || '').toLowerCase();
    if (t.length > 90) return false;
    for (var i = 0; i < ICONS.length; i++) {
      if (t.indexOf(ICONS[i][0]) !== -1) { el.setAttribute('data-cs-icon', ICONS[i][1]); return true; }
    }
    return false;
  }

  function inspect(el) {
    if (seen.has(el) || el.nodeType !== 1 || !TAGS[el.tagName]) return;
    seen.add(el);
    if (el.closest('.xterm') || el.id === 'app') return;
    if (iconify(el)) return;

    var rect = el.getBoundingClientRect();
    if (rect.width < 140 || rect.height < 36) return;                           // badge, tombol kecil, ikon
    if (rect.width > innerWidth * 0.94 && rect.height > innerHeight * 0.85) return; // pembungkus satu halaman

    var cs = getComputedStyle(el);
    if (cs.position === 'fixed' && rect.height >= innerHeight * 0.9) return;    // backdrop modal
    if (!isDarkOpaque(cs.backgroundColor)) return;

    var parent = el.parentElement && el.parentElement.closest('[' + ATTR + ']');
    el.setAttribute(ATTR, parent ? 'inner' : 'outer');
    if (!parent && rect.width > innerWidth * 0.94) el.setAttribute('data-cs-bar', '');  // navbar selebar layar: tanpa sudut membulat
  }

  function flush() {
    scheduled = false;
    var n = 0;
    while (queue.length && n < 300) { inspect(queue.shift()); n++; }  // irisan kecil agar UI tidak tersendat
    if (queue.length) schedule();
  }
  function schedule() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(flush);
  }

  function enqueue(node) {
    if (node.nodeType !== 1) return;
    queue.push(node);
    var all = node.querySelectorAll('*');
    for (var i = 0; i < all.length; i++) queue.push(all[i]);
    schedule();
  }

  function start() {
    enqueue(document.body);
    new MutationObserver(function (list) {
      for (var i = 0; i < list.length; i++) {
        var added = list[i].addedNodes;
        for (var j = 0; j < added.length; j++) enqueue(added[j]);
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
})();
