# HPK Maintenance — Daily Check

Aplikasi Laravel untuk pemeriksaan harian mesin oleh akun bagian, melalui QR atau kode mesin. Tampilan responsif untuk PC dan HP. Aset scanner jsQR 1.4.0 disimpan lokal beserta lisensi Apache-2.0; tidak memerlukan CDN saat digunakan (https://github.com/cozmo/jsQR).

## Instalasi dan akses pertama

1. Jalankan `composer install` bila dependency belum tersedia.
2. Salin `.env.example` ke `.env` hanya untuk instalasi baru. Atur koneksi MySQL, APP_URL dan APP_TIMEZONE sesuai pabrik (default Asia/Jakarta).
3. Jalankan `php artisan key:generate` hanya jika APP_KEY belum tersedia.
4. Jalankan `php artisan migrate`.
5. Buat admin: `php artisan maintenance:admin admin@perusahaan.co.id`. Masukkan kata sandi minimal 10 karakter melalui prompt tersembunyi. Tidak tersedia akun atau kata sandi bawaan.
6. Arahkan document root web server ke direktori `public`, kemudian buka `/login`. Untuk uji lokal bisa memakai `php artisan serve`.

Tidak membutuhkan build Node/Vite untuk halaman maintenance ini.

## Urutan konfigurasi

Masuk sebagai admin, buka Administrasi dan tambahkan:

1. Bagian, misalnya Produksi A, Produksi B, dan Utility.
2. Lokasi: area/gedung dan nama line/ruangan.
3. Akun dengan peran Bagian, lalu pilih bagian yang diwakili; buat juga akun Maintenance untuk verifikator.
4. Mesin: kode unik, lokasi, bagian penanggung jawab, dan nilai QR unik.
5. Template checklist: item OK/NG atau angka dengan satuan.
6. Jadwal: pilih mesin dan template, periode daily, tanggal mulai, hari aktif, status aktif.

Nilai QR disimpan persis seperti teks yang dibaca dari QR, termasuk jika berupa URL; aplikasi mencocokkannya dan tidak membuka URL tersebut. Format QR nyata dapat dipetakan setelah contoh diberikan. Input manual mencari kode mesin, bukan nilai QR.

## Alur penggunaan

- Akun bagian membuka Scan Daily Check, mengizinkan kamera lalu memindai QR. Kode mesin dapat diketik jika kamera tidak tersedia.
- Satu mesin hanya memiliki satu pemeriksaan daily per tanggal. Scan membuat atau membuka draft; mesin nonaktif atau tanpa jadwal yang berlaku tidak menerima draft baru.
- Simpan draft untuk melanjutkan nanti. Semua hasil wajib diisi saat dikirim; NG wajib disertai catatan. Foto opsional JPG/PNG/WebP maksimal 5 MB per item.
- Draft dimiliki bagian pertama yang memindai dan tidak dapat diambil alih bagian lain. Draft belum dikirim hanya dapat dikirim pada tanggal pemeriksaan. Hasil yang dikembalikan untuk revisi boleh diperbaiki setelah tanggal tersebut.
- Maintenance membuka Verifikasi lalu memeriksa hasil. Hasil bisa diverifikasi atau dikembalikan dengan alasan. Persetujuan hasil NG tidak berarti perbaikan kerusakan telah selesai.
- Scan lintas bagian tetap dapat mengisi jika belum ada draft. Saat hasil dikirim, permintaan perpindahan otomatis diajukan; pada hasil yang sudah ada tersedia pengajuan terpisah.
- Penanggung jawab berubah hanya setelah permintaan disetujui. Lokasi fisik tidak ikut berubah. Jika penanggung jawab sudah berubah sejak pengajuan, permintaan lama harus ditolak lalu diajukan kembali.
- Template, jawaban setiap pengiriman, pemilik saat pemeriksaan, foto, dan tindakan verifikasi dicatat dalam snapshot/riwayat. Hasil yang sudah dikirim tidak dapat diedit tanpa dikembalikan.

## Pengoperasian di LAN

Gunakan HTTPS dengan sertifikat yang dipercaya HP agar browser dapat mengakses kamera. HTTP localhost bisa digunakan untuk pengembangan pada perangkat itu sendiri. Tidak diperlukan scheduler untuk daily: dashboard menghitung kewajiban dari jadwal, sedangkan draft dibuat saat scan.

Atur batas `upload_max_filesize` dan `post_max_size` PHP untuk total foto yang akan dikirim. Lampiran disimpan privat di `storage/app/inspection-photos`, diakses melalui route yang memeriksa login dan hak akses; tidak memerlukan storage symlink publik.

Cadangkan database MySQL dan direktori `storage/app` secara berpasangan. Pertahankan APP_KEY secara aman untuk pemulihan. Uji pemulihan pada database terpisah sebelum dipakai operasional. Gunakan APP_DEBUG=false saat operasional dan session cookie secure saat HTTPS.

## Batas rilis pertama

- Pengisian dan monitoring hanya daily. Konfigurasi monthly, 3 bulan, dan 6 bulan dapat disimpan, tetapi belum dieksekusi.
- Akun bersama mencatat bagian, bukan identitas orang. Satu konfigurasi jadwal per mesin/periode.
- Angka dicatat tanpa penentuan batas normal otomatis. Belum ada work order perbaikan, offline, notifikasi WhatsApp, atau sparepart.
- Daftar kewajiban pada filter tanggal lampau menggunakan konfigurasi jadwal dan kepemilikan mesin terkini; hasil pemeriksaan tetap menyimpan tanggal, bagian pengisi, dan penanggung jawab historis.
- Validasi kamera dan QR fisik dari HP tetap perlu dilakukan pada HTTPS LAN dengan contoh QR sebenarnya.

## Pengujian

Jalankan `php artisan test`. PHPUnit memakai SQLite in-memory terisolasi, tidak menghapus data MySQL aplikasi. Pengujian mencakup login, akses peran, pemindaian, jadwal, duplikasi, NG, revisi, transfer, snapshot, lampiran privat, dan rendering halaman.
