/* E-Hibah — interaksi ringan tanpa framework (CSP: hanya script dari origin sendiri). */
(function () {
  'use strict';

  // ---- navigasi mobile
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle-nav]');
    if (t) { document.body.classList.toggle('nav-open'); return; }
    if (e.target.classList.contains('scrim')) document.body.classList.remove('nav-open');
  });

  // ---- ciutkan / lebarkan sidebar (ikon saja); status disimpan di peramban
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-collapse-nav]')) return;
    var on = document.documentElement.classList.toggle('nav-collapsed');
    try { localStorage.setItem('ehibah-nav', on ? '1' : '0'); } catch (err) {}
  });

  // ---- menu publik: tandai bagian yang sedang dilihat saat menggulir
  (function () {
    var links = document.querySelectorAll('.pub-nav a[data-spy]');
    if (!links.length || !('IntersectionObserver' in window)) return;
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('data-spy')] = a; });
    var ids = Object.keys(map).filter(function (k) { return k !== 'top' && document.getElementById(k); });
    if (!ids.length) return;
    function mark(id) { links.forEach(function (a) { a.classList.toggle('on', a === map[id]); }); }
    var seen = {};
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { seen[en.target.id] = en.isIntersecting; });
      var cur = ids.filter(function (k) { return seen[k]; })[0];
      mark(cur || 'top');
    }, { rootMargin: '-35% 0px -55% 0px' });
    ids.forEach(function (k) { io.observe(document.getElementById(k)); });
  })();

  // ---- tab
  document.querySelectorAll('[data-tabs]').forEach(function (root) {
    var btns = root.querySelectorAll('[data-tab]');
    function open(id) {
      btns.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === id); });
      document.querySelectorAll('.tab-pane').forEach(function (p) { p.classList.toggle('active', p.id === 'tab-' + id); });
      try { history.replaceState(null, '', '#' + id); } catch (e) {}
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { open(b.dataset.tab); }); });
    var h = location.hash.replace('#', '');
    open(h && root.querySelector('[data-tab="' + h + '"]') ? h : (btns[0] && btns[0].dataset.tab));
  });

  // ---- konfirmasi
  document.addEventListener('submit', function (e) {
    var f = e.target, msg = f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // cegah klik ganda (idempotensi sisi klien; server tetap menolak duplikat)
    var b = f.querySelector('button[type=submit]:not([data-multi])');
    if (b && !f.hasAttribute('data-multi')) { setTimeout(function () { b.disabled = true; }, 0); setTimeout(function () { b.disabled = false; }, 6000); }
  });

  // ---- kirim formulir filter otomatis saat pilihan berubah (pengganti onchange inline; CSP)
  document.addEventListener('change', function (e) {
    var el = e.target.closest('[data-autosubmit]');
    if (el && el.form) el.form.submit();
  });

  // ---- format rupiah
  function rp(n) { return 'Rp ' + Math.round(n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

  // ---- baris berulang (RAB, panitia, realisasi, barang)
  document.querySelectorAll('[data-repeat]').forEach(function (box) {
    var list = box.querySelector('[data-list]');
    var tpl = box.querySelector('template');
    var totalEl = box.querySelector('[data-total]');

    function reindex() {
      Array.prototype.forEach.call(list.children, function (row, i) {
        row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/\[\d+\]/, '[' + i + ']'); });
      });
    }
    function recalc() {
      if (!totalEl) return;
      var sum = 0;
      Array.prototype.forEach.call(list.children, function (row) {
        var q = parseFloat((row.querySelector('[data-q]') || {}).value) || 0;
        var p = parseFloat((row.querySelector('[data-p]') || {}).value) || 0;
        var amt = row.querySelector('[data-amt]');
        var line = amt && !row.querySelector('[data-q]') ? parseFloat(amt.value) || 0 : Math.round(q * p);
        var sub = row.querySelector('[data-sub]'); if (sub) sub.textContent = rp(line);
        sum += line;
      });
      totalEl.textContent = rp(sum);
    }
    function add() {
      var node = tpl.content.firstElementChild.cloneNode(true);
      list.appendChild(node); reindex(); recalc();
    }
    box.querySelector('[data-add]').addEventListener('click', add);
    list.addEventListener('click', function (e) {
      var r = e.target.closest('[data-remove]');
      if (r && list.children.length > (box.dataset.min ? +box.dataset.min : 1)) { r.closest('.repeat-row').remove(); reindex(); recalc(); }
    });
    list.addEventListener('input', recalc);
    if (!list.children.length) add(); else recalc();
  });

  // ---- idempotency key per formulir penyaluran (dibuat saat halaman dimuat)
  document.querySelectorAll('input[data-uuid]').forEach(function (i) {
    i.value = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
  });

  // ---- tampilkan alasan wajib untuk aksi bercatatan
  document.querySelectorAll('[data-note-toggle]').forEach(function (sel) {
    sel.addEventListener('click', function () {
      var f = document.getElementById(sel.dataset.noteToggle);
      if (f) { f.hidden = !f.hidden; if (!f.hidden) { var t = f.querySelector('textarea'); t && t.focus(); } }
    });
  });

  // ---- nama berkas terpilih + peringatan ukuran
  document.querySelectorAll('input[type=file][data-max]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var f = inp.files[0]; var warn = inp.parentNode.querySelector('.err.js');
      if (warn) warn.remove();
      if (f && f.size > +inp.dataset.max) {
        var d = document.createElement('div'); d.className = 'err js';
        d.textContent = 'Ukuran berkas ' + (f.size / 1048576).toFixed(1) + ' MB melebihi batas.';
        inp.parentNode.appendChild(d); inp.value = '';
      }
    });
  });
})();
