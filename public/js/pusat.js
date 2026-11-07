/* Interaksi kecil tanpa inline script (kompatibel dengan CSP script-src 'self'). */
(function () {
  'use strict';

  // <form data-konfirmasi="Yakin?"> : tanya dulu sebelum dikirim
  document.addEventListener('submit', function (e) {
    var pesan = e.target && e.target.getAttribute && e.target.getAttribute('data-konfirmasi');
    if (pesan && !window.confirm(pesan)) {
      e.preventDefault();
    }
  });

  // Menu akun (<details>) tertutup saat klik di luar; Esc menutup menu akun & menu samping.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.akun-menu[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('details.akun-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
    document.body.classList.remove('menu-buka');
  });

  // Notifikasi admin (mis. "Jurnal selesai sinkron"): dicek tiap 15 detik, tampil sebagai kotak kecil di pojok layar.
  (function () {
    var b = document.body, url = b.getAttribute('data-notif-url');
    if (!url || !window.fetch) return;
    var kunci = 'pusat-notif-akhir', sejak = parseInt(b.getAttribute('data-notif-akhir') || '0', 10);
    try { var s = parseInt(localStorage.getItem(kunci) || '', 10); if (s >= 0 && s <= sejak) sejak = s; } catch (x) {}
    var wadah = document.createElement('div');
    wadah.className = 'toast-wadah'; wadah.setAttribute('role', 'status'); wadah.setAttribute('aria-live', 'polite');
    document.body.appendChild(wadah);
    function baca(id) {
      var f = new FormData(); f.append('_token', b.getAttribute('data-csrf') || ''); if (id) f.append('id', id);
      fetch(b.getAttribute('data-notif-baca'), { method: 'POST', body: f, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
    }
    function tampil(n) {
      var t = document.createElement('div');
      t.className = 'toast ' + (n.jenis === 'galat' ? 'galat' : 'ok');
      var j = document.createElement('strong'); j.textContent = (n.jenis === 'galat' ? '✗ ' : '✓ ') + n.judul;
      var p = document.createElement('p'); p.textContent = n.isi || '';
      var a = document.createElement('a'); a.href = b.getAttribute('data-notif-daftar'); a.textContent = 'Semua notifikasi';
      var x = document.createElement('button'); x.type = 'button'; x.className = 'toast-tutup'; x.setAttribute('aria-label', 'Tutup dan tandai dibaca'); x.textContent = '×';
      x.onclick = function () { baca(n.id); t.remove(); };
      t.appendChild(x); t.appendChild(j); t.appendChild(p); t.appendChild(a);
      wadah.appendChild(t);
    }
    function label(jumlah) {
      document.querySelectorAll('.sisi a[href="' + b.getAttribute('data-notif-daftar') + '"]').forEach(function (l) {
        l.textContent = jumlah ? 'Notifikasi (' + jumlah + ')' : 'Notifikasi';
      });
    }
    function cek() {
      if (document.visibilityState === 'hidden') return;
      fetch(url + '?sejak=' + sejak, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          (d.baru || []).forEach(tampil);
          sejak = Math.max(sejak, d.akhir || 0);
          try { localStorage.setItem(kunci, String(sejak)); } catch (x) {}
          label(d.jumlah);
          if ((d.baru || []).length && document.querySelector('[data-segarkan-laporan]')) setTimeout(function () { location.reload(); }, 4000);
        }).catch(function () {});
    }
    cek();
    setInterval(cek, 15000);
    // Halaman Aplikasi yang menunggu laporan sinkron: muat ulang tiap 10 detik sampai laporan datang (maks. 10 menit).
    var tunggu = document.querySelector('[data-segarkan-laporan]');
    if (tunggu) setTimeout(function () { if (!document.querySelector('details[open]')) location.reload(); }, 10000);
  })();

  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-aksi]') : null;
    if (!el) return;
    var aksi = el.getAttribute('data-aksi');
    var target = el.getAttribute('data-target');
    var input = target ? document.querySelector(target) : null;

    // <button data-aksi="salin" data-target="#id">
    if (aksi === 'salin' && input) {
      var teks = input.value !== undefined ? input.value : input.textContent;
      var selesai = function () { el.textContent = 'Tersalin'; setTimeout(function () { el.textContent = 'Salin'; }, 1500); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(teks).then(selesai);
      } else {
        input.select && input.select();
        document.execCommand && document.execCommand('copy');
        selesai();
      }
    }

    // <button data-aksi="menu"> : buka/tutup menu samping (layar sempit)
    if (aksi === 'menu') {
      var buka = document.body.classList.toggle('menu-buka');
      document.querySelectorAll('.tombol-menu').forEach(function (b) { b.setAttribute('aria-expanded', buka ? 'true' : 'false'); });
    }

    // <button data-aksi="cetak"> : cetak halaman (daftar password awal)
    if (aksi === 'cetak') {
      window.print();
    }

    // <button data-aksi="tampilkan" data-target="#id"> : tampil/sembunyi nilai rahasia
    if (aksi === 'tampilkan' && input) {
      var sembunyi = input.type === 'password';
      input.type = sembunyi ? 'text' : 'password';
      el.textContent = sembunyi ? 'Sembunyikan' : 'Tampilkan';
    }

    // <button data-aksi="acak-password" data-target="#a,#b"> : isi password acak yang kuat
    if (aksi === 'acak-password') {
      var abjad = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
      var buf = new Uint32Array(16);
      window.crypto.getRandomValues(buf);
      var pw = '';
      for (var i = 0; i < buf.length; i++) pw += abjad[buf[i] % abjad.length];
      pw += '7'; // pastikan ada angka
      (target || '').split(',').forEach(function (s) {
        var f = document.querySelector(s.trim());
        if (f) { f.value = pw; f.type = 'text'; }
      });
    }
  });
})();
