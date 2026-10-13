# LaundryKu — Frontend Starter Multi-Halaman

Prototipe sistem antrean & status order laundry kiloan menggunakan PHP Native, konsep OOP sederhana, Bootstrap 5.3.8, CSS, dan JavaScript.

## Halaman
- `index.php` — dashboard ringkasan dan antrean terdekat
- `tambah.php` — halaman tersendiri untuk tambah transaksi
- `antrean.php` — daftar order, pencarian, filter, pagination (5 order/halaman), dan tombol edit
- `edit.php` — edit pelanggan, paket, berat, tanggal masuk, status, dan pembayaran
- `notifikasi.php` — cucian siap diambil, deadline terlewat, aktivitas order
- `nota-hilang.php` — cari arsip struk digital dengan kode transaksi/nama/nomor, lalu cetak ulang

## Menjalankan di XAMPP
1. Ekstrak folder `laundry_dashboard_v2` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dari XAMPP.
3. Buka `http://localhost/laundry_dashboard_v2/`.

## Catatan penting
- Data awal adalah data contoh dan disimpan di PHP session untuk demonstrasi; belum permanen seperti database MySQL/MariaDB.
- Tarif demo: Reguler Rp7.000/kg (48 jam), Express Rp14.000/kg (12 jam). Sesuaikan dengan keputusan tim.
- Notifikasi saat ini tampil di dashboard internal; belum mengirim WhatsApp.
- Untuk pemakaian sungguhan, backend perlu menyimpan pelanggan, order, status, pembayaran, dan data struk di database agar arsip tetap tersedia lintas sesi/perangkat.
