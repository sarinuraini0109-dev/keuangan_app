// Formulir transaksi: kategori mengikuti jenis, subkategori mengikuti kategori,
// validasi sederhana, dan pencarian di tabel riwayat.
(function () {
  const form = document.getElementById("form-transaksi");
  if (form) {
    const radioJenis = form.querySelectorAll('input[name="jenis"]');
    const selectKategori = document.getElementById("id_kategori");
    const selectSub = document.getElementById("id_subkategori");
    const semuaKategori = Array.from(selectKategori.options);
    const semuaSub = Array.from(selectSub.options).filter(function (o) { return o.value !== ""; });
    const opsiKosong = selectSub.querySelector('option[value=""]');

    function tampilkanSub() {
      selectSub.innerHTML = "";
      selectSub.appendChild(opsiKosong);
      semuaSub
        .filter(function (o) { return o.dataset.kategori === selectKategori.value; })
        .forEach(function (o) { selectSub.appendChild(o); });
      selectSub.value = "";
    }

    function tampilkanKategori() {
      const jenis = form.querySelector('input[name="jenis"]:checked').value;
      selectKategori.innerHTML = "";
      semuaKategori
        .filter(function (o) { return o.dataset.jenis === jenis; })
        .forEach(function (o) { selectKategori.appendChild(o); });
      selectKategori.selectedIndex = 0;
      tampilkanSub();
    }

    radioJenis.forEach(function (r) { r.addEventListener("change", tampilkanKategori); });
    selectKategori.addEventListener("change", tampilkanSub);

    // Nilai awal (untuk halaman edit)
    tampilkanKategori();
    if (form.dataset.kategori && form.dataset.kategori !== "0") {
      selectKategori.value = form.dataset.kategori;
      tampilkanSub();
      if (form.dataset.sub && form.dataset.sub !== "0") selectSub.value = form.dataset.sub;
    }

    // Validasi (server tetap memeriksa ulang)
    const galat = document.getElementById("galat");
    form.addEventListener("submit", function (e) {
      const nominal = Number(document.getElementById("nominal").value.replace(/\D/g, ""));
      let pesan = "";
      if (selectKategori.value === "") pesan = "Pilih kategori terlebih dahulu.";
      else if (document.getElementById("tanggal").value === "") pesan = "Isi tanggal transaksi.";
      else if (nominal <= 0) pesan = "Nominal harus lebih dari 0.";

      if (pesan !== "") {
        e.preventDefault();
        galat.textContent = pesan;
        galat.hidden = false;
      } else {
        galat.hidden = true;
      }
    });
  }

  // Pencarian di tabel
  const inputCari = document.getElementById("cari");
  const tabel = document.getElementById("tabel");
  if (inputCari && tabel) {
    inputCari.addEventListener("input", function () {
      const kata = inputCari.value.toLowerCase();
      tabel.querySelectorAll("tbody tr").forEach(function (baris) {
        baris.style.display = baris.textContent.toLowerCase().includes(kata) ? "" : "none";
      });
    });
  }
})();
