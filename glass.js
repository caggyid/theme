/* CaggyShop glass.js (opsional)
   Panel Pterodactyl memakai class CSS yang di-hash, jadi tidak bisa dipilih lewat CSS biasa.
   Script ini hanya MEMBACA warna latar elemen; jika elemen berupa kartu gelap yang cukup besar,
   ia diberi atribut data-cs-glass supaya caggyshop.css bisa membuatnya berkaca.
   Selain itu, kotak ikon kartu statistik (Address, Uptime, CPU, dst.) diberi atribut data-cs-icon
   supaya CSS bisa mengganti ikonnya. Di halaman console server, ia juga menambahkan satu kartu "Masa Aktif"
   (data dari panel sendiri, paket caggyshop-expiry). Tidak ada data yang dikirim ke luar panel. */
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
  var ICONS = [['inbound', 'down'], ['outbound', 'up'], ['address', 'net'], ['uptime', 'time'], ['cpu', 'cpu'], ['memory', 'mem'], ['disk', 'disk'], ['masa aktif', 'exp']];
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

  // ---- Kartu "Masa Aktif" di console server ----
  // Mengambil masa expired dari /api/client/servers/{id}/caggy-expiry (paket caggyshop-expiry),
  // lalu menyalin satu kartu statistik bawaan dan mengganti teksnya. Jika endpoint tidak ada /
  // server tidak punya expired, tidak ada yang ditambahkan.
  var exp = { id: null, data: undefined, at: 0, pending: false };
  var expTimer = 0;

  function serverId() {
    var m = /^\/server\/([0-9a-f]{8})(\/|$)/i.exec(location.pathname);
    return m ? m[1] : null;
  }

  function fmtLeft(ms) {
    if (ms <= 0) return { text: 'Expired', state: 'danger' };
    var min = Math.floor(ms / 60000);
    if (min < 60) return { text: Math.max(1, min) + ' menit lagi', state: 'danger' };
    var hr = Math.floor(min / 60);
    if (hr < 48) return { text: hr + ' jam lagi', state: 'warn' };
    var d = Math.ceil(ms / 86400000);
    return { text: d + ' hari lagi', state: d <= 7 ? 'warn' : 'ok' };
  }

  function renderExpiry(id, grid, src, d) {
    var at = new Date(d.expires_at);
    if (isNaN(at.getTime())) return;
    var left = fmtLeft(at.getTime() - Date.now());
    var clone = src.cloneNode(true);
    clone.setAttribute('data-cs-expiry', id);
    clone.setAttribute('data-cs-state', left.state);
    var icon = clone.querySelector('[data-cs-icon]');
    if (icon) icon.setAttribute('data-cs-icon', 'exp');
    var leaves = [], all = clone.querySelectorAll('*');
    for (var i = 0; i < all.length; i++) {
      var n = all[i];
      if (!n.children.length && n.textContent.trim() && !n.closest('svg')) leaves.push(n);
    }
    if (leaves.length < 2) return;
    var dateTxt;
    try { dateTxt = at.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); } catch (e) { dateTxt = at.toDateString(); }
    leaves[0].textContent = 'Expired \u00b7 ' + dateTxt;
    leaves[1].textContent = left.text;
    leaves[1].setAttribute('data-cs-expiry-value', '');
    try { clone.title = 'Expired: ' + at.toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }); } catch (e) {}
    grid.appendChild(clone);
  }

  function ensureExpiry() {
    var id = serverId();
    if (!id) return;
    var src = (document.querySelector('[data-cs-icon="down"]') || document.querySelector('[data-cs-icon="up"]') || {}).parentElement;
    if (!src || !src.parentElement) return;
    var grid = src.parentElement;

    var have = grid.querySelector('[data-cs-expiry]');
    if (have) {
      if (have.getAttribute('data-cs-expiry') === id) return;   // sudah benar
      have.parentNode.removeChild(have);                         // pindah server: buang kartu lama
    }
    if (exp.id === id && exp.data !== undefined && Date.now() - exp.at < 60000) {
      if (exp.data) renderExpiry(id, grid, src, exp.data);
      return;
    }
    if (exp.pending) return;
    exp.pending = true;
    fetch('/api/client/servers/' + id + '/caggy-expiry', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { exp = { id: id, data: (d && d.expires_at) ? d : null, at: Date.now(), pending: false }; scheduleExpiry(); })
      .catch(function () { exp = { id: id, data: null, at: Date.now(), pending: false }; });
  }

  function scheduleExpiry() {
    if (expTimer) return;
    expTimer = setTimeout(function () { expTimer = 0; ensureExpiry(); }, 400);
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
    if (queue.length) schedule(); else scheduleExpiry();
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
    scheduleExpiry();
    new MutationObserver(function (list) {
      for (var i = 0; i < list.length; i++) {
        var added = list[i].addedNodes;
        for (var j = 0; j < added.length; j++) enqueue(added[j]);
      }
      scheduleExpiry();
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
})();
