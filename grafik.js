// Semua grafik digambar dengan SVG murni (tanpa library). Data dibaca dari atribut data-*.
const NS = "http://www.w3.org/2000/svg";
const formatRp = new Intl.NumberFormat("id-ID");

function el(nama, atribut, induk, teks) {
  const e = document.createElementNS(NS, nama);
  for (const k in atribut) e.setAttribute(k, atribut[k]);
  if (teks !== undefined) e.textContent = teks;
  if (induk) induk.appendChild(e);
  return e;
}

function ringkas(n) {
  const a = Math.abs(n);
  const f = function (x, s) { return x.toLocaleString("id-ID", { maximumFractionDigits: 1 }) + " " + s; };
  if (a >= 1e9) return f(n / 1e9, "M");
  if (a >= 1e6) return f(n / 1e6, "jt");
  if (a >= 1e3) return f(n / 1e3, "rb");
  return String(Math.round(n));
}

const BIRU = "#0e8bd6", CYAN = "#12b3cf", HIJAU = "#12a574", MERAH = "#e5484d", KUNING = "#f5a524", INK = "#12304a";

// ---------- Gauge setengah lingkaran ----------
function gambarGauge(wadah) {
  const d = JSON.parse(wadah.dataset.gauge);
  const svg = el("svg", { viewBox: "0 0 200 112", role: "img", "aria-label": d.judul }, wadah);
  const cx = 100, cy = 100, r = 78;
  const warna = d.arah === "turun" ? [HIJAU, KUNING, MERAH] : [MERAH, KUNING, HIJAU];

  function titik(sudut, jari) {
    const rad = (sudut * Math.PI) / 180;
    return [cx + jari * Math.cos(rad), cy - jari * Math.sin(rad)];
  }
  for (let i = 0; i < 3; i++) {
    const a1 = 180 - 60 * i - (i === 0 ? 0 : 1.5);
    const a2 = 180 - 60 * (i + 1) + (i === 2 ? 0 : 1.5);
    const p1 = titik(a1, r), p2 = titik(a2, r);
    el("path", { d: "M " + p1[0] + " " + p1[1] + " A " + r + " " + r + " 0 0 1 " + p2[0] + " " + p2[1],
      fill: "none", stroke: warna[i], "stroke-width": 16 }, svg);
  }
  const f = Math.min(1, Math.max(0, d.f));
  const ujung = titik(180 - 180 * f, 60);
  el("line", { x1: cx, y1: cy, x2: ujung[0], y2: ujung[1], stroke: INK, "stroke-width": 3, "stroke-linecap": "round" }, svg);
  el("circle", { cx: cx, cy: cy, r: 5.5, fill: INK }, svg);
}

// ---------- Grafik garis tren saldo ----------
function gambarTren(wadah) {
  const d = JSON.parse(wadah.dataset.tren);
  const W = 600, H = 250, kiri = 62, kanan = 14, atas = 14, bawah = 28;
  const n = d.label.length;
  const ada = d.nilai.filter(function (v) { return v !== null; });
  if (ada.length === 0) return;

  let min = Math.min.apply(null, ada), max = Math.max.apply(null, ada);
  if (min === max) { const pad = max === 0 ? 1 : Math.abs(max) * 0.1; min -= pad; max += pad; }
  else { const pad = (max - min) * 0.12; min -= pad; max += pad; }

  const lebar = W - kiri - kanan, tinggi = H - atas - bawah;
  const x = function (i) { return kiri + (n === 1 ? 0 : (i * lebar) / (n - 1)); };
  const y = function (v) { return atas + ((max - v) / (max - min)) * tinggi; };
  const svg = el("svg", { viewBox: "0 0 " + W + " " + H, class: "grafik", role: "img", "aria-label": "Tren saldo harian" }, wadah);

  for (let i = 0; i <= 4; i++) {
    const nilai = min + ((max - min) * i) / 4, yy = y(nilai);
    el("line", { x1: kiri, x2: W - kanan, y1: yy, y2: yy, stroke: "#dbe8f2", "stroke-dasharray": "4 4" }, svg);
    el("text", { x: kiri - 8, y: yy + 4, "text-anchor": "end" }, svg, ringkas(nilai));
  }
  d.label.forEach(function (tgl, i) {
    if (i % 4 === 0 || (i === n - 1 && (n - 1) % 4 > 1)) el("text", { x: x(i), y: H - 8, "text-anchor": "middle" }, svg, String(tgl));
  });

  let garis = "", terakhir = -1;
  d.nilai.forEach(function (v, i) {
    if (v === null) return;
    garis += (garis === "" ? "M " : " L ") + x(i) + " " + y(v);
    terakhir = i;
  });
  const dasar = y(min);
  el("path", { d: garis + " L " + x(terakhir) + " " + dasar + " L " + x(0) + " " + dasar + " Z", fill: BIRU, "fill-opacity": "0.12" }, svg);
  el("path", { d: garis, fill: "none", stroke: BIRU, "stroke-width": 3, "stroke-linejoin": "round", "stroke-linecap": "round" }, svg);

  const penanda = el("g", { visibility: "hidden" }, svg);
  const garisV = el("line", { y1: atas, y2: atas + tinggi, stroke: BIRU, "stroke-opacity": "0.4" }, penanda);
  const bulat = el("circle", { r: 5.5, fill: "#fff", stroke: BIRU, "stroke-width": 3 }, penanda);
  const kotak = el("rect", { height: 26, rx: 13, fill: INK }, penanda);
  const teks = el("text", { class: "tip-teks" }, penanda, "");
  const area = el("rect", { x: kiri, y: atas, width: lebar, height: tinggi, fill: "transparent" }, svg);

  function sorot(e) {
    const kt = svg.getBoundingClientRect();
    const px = ((e.clientX - kt.left) / kt.width) * W;
    let i = Math.round(((px - kiri) / lebar) * (n - 1));
    i = Math.max(0, Math.min(terakhir, i));
    const v = d.nilai[i], xx = x(i), yy = y(v);
    const label = "Tgl " + d.label[i] + ": Rp " + formatRp.format(Math.round(v));
    const lb = label.length * 6.6 + 20;
    const kx = Math.max(kiri, Math.min(W - kanan - lb, xx - lb / 2));
    const ky = Math.max(2, yy - 40);
    garisV.setAttribute("x1", xx); garisV.setAttribute("x2", xx);
    bulat.setAttribute("cx", xx); bulat.setAttribute("cy", yy);
    kotak.setAttribute("x", kx); kotak.setAttribute("y", ky); kotak.setAttribute("width", lb);
    teks.setAttribute("x", kx + 10); teks.setAttribute("y", ky + 17);
    teks.textContent = label;
    penanda.setAttribute("visibility", "visible");
  }
  area.addEventListener("pointermove", sorot);
  area.addEventListener("pointerdown", sorot);
  area.addEventListener("pointerleave", function () { penanda.setAttribute("visibility", "hidden"); });
}

// ---------- Donut struktur pengeluaran ----------
const PALET = [BIRU, CYAN, HIJAU, KUNING, MERAH, "#7c8cf8", "#9fb3c8"];

function gambarDonut(wadah) {
  const d = JSON.parse(wadah.dataset.donut);
  if (d.item.length === 0) {
    const p = document.createElement("p");
    p.className = "kosong";
    p.textContent = "Belum ada pengeluaran pada periode ini.";
    wadah.appendChild(p);
    return;
  }
  const jumlah = d.item.reduce(function (a, b) { return a + b.total; }, 0);
  const svg = el("svg", { viewBox: "0 0 200 200", role: "img", "aria-label": "Struktur pengeluaran" }, wadah);
  const r = 70, keliling = 2 * Math.PI * r;
  let geser = 0;
  d.item.forEach(function (it, i) {
    const panjang = (it.total / jumlah) * keliling;
    const celah = d.item.length > 1 && panjang > 4 ? 2 : 0;
    const seg = el("circle", { cx: 100, cy: 100, r: r, fill: "none", stroke: PALET[i % PALET.length], "stroke-width": 28,
      "stroke-dasharray": (panjang - celah) + " " + (keliling - panjang + celah), "stroke-dashoffset": -geser,
      transform: "rotate(-90 100 100)" }, svg);
    el("title", {}, seg, it.nama + ": Rp " + formatRp.format(Math.round(it.total)));
    geser += panjang;
  });
  el("text", { x: 100, y: 96, "text-anchor": "middle", class: "pusat-label" }, svg, "Total");
  el("text", { x: 100, y: 114, "text-anchor": "middle", class: "pusat-angka" }, svg, "Rp " + ringkas(jumlah));

  const ul = document.createElement("ul");
  ul.className = "legenda";
  d.item.forEach(function (it, i) {
    const li = document.createElement("li");
    const titik = document.createElement("span");
    titik.className = "titik";
    titik.style.background = PALET[i % PALET.length];
    const nama = document.createElement("span");
    nama.textContent = it.nama;
    const persen = document.createElement("span");
    persen.className = "persen";
    persen.textContent = Math.round((it.total / jumlah) * 100) + "%";
    li.appendChild(titik); li.appendChild(nama); li.appendChild(persen);
    ul.appendChild(li);
  });
  wadah.appendChild(ul);
}

// ---------- Grafik batang pemasukan vs pengeluaran ----------
function gambarBatang(wadah) {
  const d = JSON.parse(wadah.dataset.batang);
  const W = 600, H = 270, kiri = 62, kanan = 14, atas = 14, bawah = 32;
  const n = d.label.length;
  const maks = Math.max.apply(null, d.masuk.concat(d.keluar).concat([1]));
  const lebar = W - kiri - kanan, tinggi = H - atas - bawah;
  const y = function (v) { return atas + (1 - v / maks) * tinggi; };
  const svg = el("svg", { viewBox: "0 0 " + W + " " + H, class: "batang-grafik", role: "img", "aria-label": "Pemasukan dan pengeluaran per bulan" }, wadah);

  for (let i = 0; i <= 4; i++) {
    const nilai = (maks * i) / 4, yy = y(nilai);
    el("line", { x1: kiri, x2: W - kanan, y1: yy, y2: yy, stroke: "#dbe8f2", "stroke-dasharray": i === 0 ? "0" : "4 4" }, svg);
    el("text", { x: kiri - 8, y: yy + 4, "text-anchor": "end" }, svg, ringkas(nilai));
  }
  const lebarGrup = lebar / n, lebarBar = Math.min(34, lebarGrup * 0.3);
  d.label.forEach(function (lb, i) {
    const tengah = kiri + lebarGrup * i + lebarGrup / 2;
    [["masuk", HIJAU, -lebarBar - 2], ["keluar", MERAH, 2]].forEach(function (s) {
      const v = d[s[0]][i], yy = y(v);
      const bar = el("rect", { class: "bar", x: tengah + s[2], y: yy, width: lebarBar, height: Math.max(0, atas + tinggi - yy), rx: 6, fill: s[1] }, svg);
      el("title", {}, bar, lb + " " + (s[0] === "masuk" ? "pemasukan" : "pengeluaran") + ": Rp " + formatRp.format(Math.round(v)));
    });
    el("text", { x: tengah, y: H - 10, "text-anchor": "middle" }, svg, lb);
  });
}

document.querySelectorAll("[data-gauge]").forEach(gambarGauge);
document.querySelectorAll("[data-tren]").forEach(gambarTren);
document.querySelectorAll("[data-donut]").forEach(gambarDonut);
document.querySelectorAll("[data-batang]").forEach(gambarBatang);
