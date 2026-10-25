(function () {
  'use strict';
  var phones = Array.prototype.slice.call(document.querySelectorAll('.phone'));
  var narrow = function () { return window.matchMedia('(max-width:700px)').matches; };
  var role = 'pemohon';

  function show(name) {
    if (!narrow()) {
      // layar lebar: semua layar tampil; arahkan perhatian ke layar tujuan
      var t = phones.filter(function (p) { return p.dataset.screen === name; })[0];
      phones.forEach(function (p) { p.style.outline = ''; });
      if (t) {
        t.hidden = false;
        t.style.outline = '4px solid #0E9F6E';
        t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        setTimeout(function () { t.style.outline = ''; if (name === 'done') t.hidden = true; }, 1600);
      }
      return;
    }
    phones.forEach(function (p) {
      var on = p.dataset.screen === name;
      p.classList.toggle('active', on);
      p.hidden = !on;
    });
    window.scrollTo(0, 0);
  }
  function applyRole() {
    phones.forEach(function (p) {
      var only = p.dataset.roleOnly;
      if (!narrow()) p.hidden = (only && only !== role) || p.dataset.screen === 'done';
    });
    document.querySelectorAll('.roles button').forEach(function (b) { b.classList.toggle('on', b.dataset.role === role); });
  }

  document.addEventListener('click', function (e) {
    var r = e.target.closest('[data-role]');
    if (r) { role = r.dataset.role; applyRole(); show(role === 'opd' ? 'verify' : 'home'); return; }
    var g = e.target.closest('[data-go]');
    if (g) { show(g.dataset.go); }
  });

  // Unggah: tombol ajukan aktif setelah dokumen terakhir "difoto"
  var cam = document.getElementById('cam'), sub = document.getElementById('submitBtn'), hint = document.getElementById('hint');
  if (sub) sub.disabled = true;
  if (cam) cam.addEventListener('click', function () {
    var miss = document.querySelector('.doc.miss'), bad = document.querySelector('.doc.bad');
    [miss, bad].forEach(function (d) {
      if (d) { d.className = 'doc ok'; d.querySelector('span').textContent = '✔'; d.querySelector('em').textContent = 'Terunggah'; var s = d.querySelector('small'); if (s) s.textContent = 'foto-dokumen.jpg · baru'; }
    });
    sub.disabled = false; hint.textContent = 'Seluruh dokumen wajib terpenuhi — usulan siap diajukan.';
  });
  if (sub) sub.addEventListener('click', function () { if (!sub.disabled) show('done'); });

  // Verifikasi: tombol Lulus aktif bila semua kriteria dicentang
  var pass = document.getElementById('pass'), vh = document.getElementById('vhint');
  function check() {
    var all = Array.prototype.every.call(document.querySelectorAll('[data-screen=verify] .chk input'), function (c) { return c.checked; });
    var bad = document.querySelector('[data-screen=verify] .doc.bad');
    pass.disabled = !all || !!bad;
    vh.textContent = pass.disabled ? 'Lulus aktif setelah semua kriteria dan dokumen sesuai.' : 'Siap diteruskan ke Tim Verifikasi (teknis).';
  }
  if (pass) {
    document.querySelectorAll('[data-screen=verify] .chk input').forEach(function (c) { c.addEventListener('change', check); });
    pass.addEventListener('click', function () { if (!pass.disabled) show('done'); });
    check();
  }

  window.addEventListener('resize', function () { location.reload(); });
  applyRole();
  if (narrow()) show('home');
})();
