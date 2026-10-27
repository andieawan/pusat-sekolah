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
