/* CaggyShop glass.js (opsional)
   Panel Pterodactyl memakai class CSS yang di-hash, jadi tidak bisa dipilih lewat CSS biasa.
   Script ini hanya MEMBACA warna latar elemen; jika elemen berupa kartu gelap yang cukup besar,
   ia diberi atribut data-cs-glass supaya caggyshop.css bisa membuatnya berkaca.
   Selain itu, kotak ikon kartu statistik (Address, Uptime, CPU, dst.) diberi atribut data-cs-icon
   supaya CSS bisa mengganti ikonnya. Di halaman console server, ia juga menambahkan satu kartu "Masa Aktif"
   (data dari panel sendiri, paket caggyshop-expiry).
   Navbar kiri: ia memberi label teks (atribut data-cs-label) pada menu yang aslinya hanya ikon.
   Widget Discord: dibuat dari window.CaggyTheme.discord (ditulis settings.js oleh Admin > Theme CaggyShop).
   Hanya jika widget diaktifkan, browser pengguna memuat iframe dari discord.com (saat panel dibuka) dan
   membaca jumlah anggota online dari discord.com. Tidak ada data panel yang dikirim ke luar. */
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

  // ---- Navbar kiri: label teks untuk menu ikon ----
  var NAVLABELS = {
    'magnifying-glass': 'Cari', 'search': 'Cari',
    'layer-group': 'Dashboard',
    'gears': 'Admin', 'gear': 'Admin', 'cogs': 'Admin', 'cog': 'Admin',
    'circle-user': 'Akun', 'user-circle': 'Akun',
    'right-from-bracket': 'Keluar', 'sign-out-alt': 'Keluar', 'sign-out': 'Keluar'
  };
  function ensureNav() {
    var logo = document.getElementById('logo');
    var group = logo && logo.nextElementSibling;
    if (!group) return;
    for (var i = 0; i < group.children.length; i++) {
      var it = group.children[i];
      if (it.hasAttribute('data-cs-label')) continue;
      var svg = it.querySelector('svg');
      var label = (svg && NAVLABELS[svg.getAttribute('data-icon')]) || '';
      if (!label) {
        var h = it.getAttribute('href') || '';
        label = h === '/' ? 'Dashboard' : h === '/admin' ? 'Admin' : h === '/account' ? 'Akun' : '';
      }
      if (label) it.setAttribute('data-cs-label', label);
    }
  }

  // ---- Widget Discord ----
  // Config dari settings.js: { enabled, id (Server ID), invite (link undangan, opsional) }
  var DC = (window.CaggyTheme && window.CaggyTheme.discord) || null;
  var dcBuilt = false;
  var DC_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>';

  function mk(tag, props, text) {
    var e = document.createElement(tag);
    if (props) for (var k in props) e.setAttribute(k, props[k]);
    if (text) e.textContent = text;
    return e;
  }

  function buildDiscord() {
    if (dcBuilt || !DC || !DC.enabled || !document.body) return;
    var id = /^\d{15,20}$/.test(String(DC.id || '')) ? String(DC.id) : '';
    var invite = /^https:\/\/(discord\.gg|discord(app)?\.com\/invite)\//.test(DC.invite || '') ? DC.invite : '';
    if (!id && !invite) return;
    dcBuilt = true;

    var btn = mk('button', { id: 'cs-dc-btn', type: 'button', 'aria-label': 'Discord' });
    btn.innerHTML = DC_ICON;
    btn.appendChild(mk('span', { 'class': 'cs-dc-label' }, 'Discord'));
    var btnCount = mk('span', { 'class': 'cs-dc-count' });
    btnCount.style.display = 'none';
    btn.appendChild(btnCount);
    document.body.appendChild(btn);

    // Tanpa Server ID (hanya link undangan): tombol langsung membuka undangan
    if (!id) {
      btn.addEventListener('click', function () { window.open(invite, '_blank', 'noopener'); });
      return;
    }

    var panel = mk('div', { id: 'cs-dc-panel', role: 'dialog', 'aria-label': 'Discord' });
    var head = mk('div', { 'class': 'cs-dc-head' });
    var title = mk('span', { 'class': 'cs-dc-title' }, 'Discord');
    var headCount = mk('span', { 'class': 'cs-dc-count' });
    headCount.style.display = 'none';
    var close = mk('button', { type: 'button', 'class': 'cs-dc-close', 'aria-label': 'Tutup' }, '\u00d7');
    head.appendChild(title); head.appendChild(headCount); head.appendChild(close);
    panel.appendChild(head);
    var holder = mk('div');
    panel.appendChild(holder);
    if (invite) {
      var join = mk('a', { 'class': 'cs-dc-join', href: invite, target: '_blank', rel: 'noopener noreferrer' }, 'Gabung server');
      panel.appendChild(join);
    }
    document.body.appendChild(panel);

    function isOpen() { return panel.hasAttribute('data-open'); }
    function setOpen(v) {
      if (v) {
        if (!holder.firstChild) {   // iframe baru dimuat saat pertama kali dibuka
          var f = mk('iframe', {
            src: 'https://discord.com/widget?id=' + id + '&theme=dark',
            title: 'Discord', width: '350', height: '500', frameborder: '0',
            sandbox: 'allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts'
          });
          holder.appendChild(f);
        }
        panel.setAttribute('data-open', '');
      } else panel.removeAttribute('data-open');
      btn.setAttribute('aria-expanded', v ? 'true' : 'false');
    }
    btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(!isOpen()); });
    close.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen()) setOpen(false); });
    document.addEventListener('click', function (e) {
      if (isOpen() && !panel.contains(e.target) && !btn.contains(e.target)) setOpen(false);
    });

    // Jumlah anggota online (butuh "Aktifkan widget server" di pengaturan server Discord). Gagal = diabaikan.
    try {
      fetch('https://discord.com/api/guilds/' + id + '/widget.json', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || typeof d.presence_count !== 'number') return;
          var txt = d.presence_count + ' online';
          btnCount.textContent = txt; btnCount.style.display = '';
          headCount.textContent = txt; headCount.style.display = '';
          if (d.name) title.textContent = String(d.name);
        })
        .catch(function () {});
    } catch (e) {}
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
    ensureNav();
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
    ensureNav();
    buildDiscord();
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
