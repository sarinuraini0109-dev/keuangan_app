// Efek muncul saat digulir + penanda menu aktif pada halaman beranda
(function () {
  const kurangiGerak = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const target = document.querySelectorAll("[data-muncul]");

  if (!kurangiGerak && "IntersectionObserver" in window) {
    const pengamat = new IntersectionObserver(function (daftar) {
      daftar.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("tampil");
          pengamat.unobserve(e.target);
        }
      });
    }, { threshold: 0.15 });

    target.forEach(function (el, i) {
      el.classList.add("muncul");
      el.style.transitionDelay = (i % 3) * 90 + "ms";
      pengamat.observe(el);
    });
  }

  // Tutup menu mobile setelah memilih tautan
  const menu = document.getElementById("menu-utama");
  const tombol = document.querySelector(".nav-toggle");
  if (menu && tombol) {
    menu.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        menu.classList.remove("buka");
        tombol.setAttribute("aria-expanded", "false");
      });
    });
  }

  // Tandai menu sesuai bagian yang sedang terlihat
  const bagian = ["beranda", "fitur", "cara", "keamanan", "faq"]
    .map(function (id) { return document.getElementById(id); })
    .filter(Boolean);
  const tautan = {};
  menu && menu.querySelectorAll("a").forEach(function (a) { tautan[a.getAttribute("href").slice(1)] = a; });

  if ("IntersectionObserver" in window && menu) {
    const pantau = new IntersectionObserver(function (daftar) {
      daftar.forEach(function (e) {
        if (e.isIntersecting && tautan[e.target.id]) {
          Object.keys(tautan).forEach(function (k) { tautan[k].classList.toggle("aktif", k === e.target.id); });
        }
      });
    }, { rootMargin: "-45% 0px -50% 0px" });
    bagian.forEach(function (b) { pantau.observe(b); });
  }
})();
