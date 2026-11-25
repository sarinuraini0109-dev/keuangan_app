<?php
require "koneksi.php";

// Keluar hanya lewat POST + token CSRF (supaya tidak bisa dipicu dari situs lain)
if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_cek()) {
    keluar_akun($koneksi);
    header("Location: login.php");
    exit;
}
header("Location: index.php");
exit;
