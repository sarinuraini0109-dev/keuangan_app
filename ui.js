// Perilaku antarmuka bersama (navigasi, dropdown, efek, format input)
(function () {
  const kurangiGerak = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Bayangan navbar saat halaman digulir
  const nav = document.querySelector(".nav");
  if (nav) {
    const cek = function () { nav.classList.toggle("tergulir", window.scrollY > 6); };
    cek();
    window.addEventListener("scroll", cek, { passive: true });
  }

  // Menu mobile
  const tombolMenu = document.querySelector(".nav-toggle");
  const menu = document.getElementById("menu-utama");
  function tutupMenu() {
    if (menu && tombolMenu) {
      menu.classList.remove("buka");
      tombolMenu.setAttribute("aria-expanded", "false");
    }
  }
  if (tombolMenu && menu) {
    tombolMenu.addEventListener("click", function () {
      const buka = menu.classList.toggle("buka");
      tombolMenu.setAttribute("aria-expanded", buka ? "true" : "false");
    });
  }

  // Dropdown akun: tutup saat klik di luar atau tekan Esc
  function tutupDropdown() {
    document.querySelectorAll("details.menu-pengguna[open]").forEach(function (d) { d.removeAttribute("open"); });
  }
  document.addEventListener("click", function (e) {
    document.querySelectorAll("details.menu-pengguna[open]").forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute("open");
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") { tutupDropdown(); tutupMenu(); }
  });

  // Tombol "Lihat" password
  document.querySelectorAll(".lihat").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      const input = document.getElementById(tombol.dataset.target);
      const tampil = input.type === "password";
      input.type = tampil ? "text" : "password";
      tombol.textContent = tampil ? "Sembunyi" : "Lihat";
      tombol.setAttribute("aria-label", tampil ? "Sembunyikan password" : "Tampilkan password");
    });
  });

  // Format angka dengan titik ribuan
  document.querySelectorAll("input.uang").forEach(function (input) {
    function format() {
      const angka = input.value.replace(/\D/g, "");
      input.value = angka === "" ? "" : Number(angka).toLocaleString("id-ID");
    }
    input.addEventListener("input", format);
    format();
  });

  // Efek miring 3D mengikuti kursor
  if (!kurangiGerak && window.matchMedia("(hover: hover)").matches) {
    document.querySelectorAll(".tilt").forEach(function (el) {
      el.addEventListener("pointermove", function (e) {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = "perspective(800px) rotateX(" + (-y * 9).toFixed(2) + "deg) rotateY(" + (x * 11).toFixed(2) + "deg) translateY(-4px)";
      });
      el.addEventListener("pointerleave", function () { el.style.transform = ""; });
    });
  }

  // Konfirmasi sebelum aksi berbahaya: <form data-konfirmasi="Yakin?">
  document.querySelectorAll("form[data-konfirmasi]").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (!window.confirm(f.dataset.konfirmasi)) e.preventDefault();
    });
  });
})();
