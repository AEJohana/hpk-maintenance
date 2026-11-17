# HPK Maintenance - Development & Architectural Rules

Panduan dan aturan arsitektur untuk pengembangan proyek **HPK Maintenance** (Karoseri HPK). Aturan ini wajib dipatuhi oleh seluruh agent dan pengembang.

---

## 1. Konvensi Penamaan Tabel Database (Database Table Naming)

1. **Tabel Master (`mstrs_`)**:
   - Seluruh tabel yang menyimpan data referensi/master data **wajib** menggunakan awalan `mstrs_`.
   - Daftar tabel master:
     - `mstrs_branches` (Data cabang)
     - `mstrs_departments` (Data bagian/departemen)
     - `mstrs_locations` (Data penempatan fisik/lokasi/line)
     - `mstrs_machines` (Data mesin)
     - `mstrs_components` (Data sub-komponen mesin/sparepart)
     - `mstrs_checklists` (Template checklist form pemeliharaan)
     - `mstrs_users` (Data pengguna/akun)
     - `mstrs_user_modules` (Pemetaan hak akses modul pengguna)
     - `mstrs_roles` (Peran pengguna legacy)
     - `mstrs_permissions` (Izin akses)
     - `mstrs_role_permissions` (Pivot peran dan izin)

2. **Tabel Transaksi & Log (`trnks_`)**:
   - Seluruh tabel yang mencatat transaksi operasional, jadwal, riwayat, dan aktivitas pengguna **wajib** menggunakan awalan `trnks_`.
   - Daftar tabel transaksi:
     - `trnks_maintenance_schedules` (Jadwal pemeriksaan pemeliharaan)
     - `trnks_inspections` (Hasil dan draft checklist pemeriksaan harian/berkala)
     - `trnks_transfer_requests` (Pengajuan perpindahan penanggung jawab mesin)
     - `trnks_activity_logs` (Audit log riwayat tindakan pengguna)

3. **Tabel Bawaan Framework (System / Framework Tables)**:
   - Tabel bawaan Laravel yang tidak dimodifikasi tetap menggunakan nama default framework:
     - `migrations`, `failed_jobs`, `personal_access_tokens`, `password_reset_tokens`.

---

## 2. Standar Kolom dan Foreign Key

1. **Primary Key**:
   - Setiap tabel master dan transaksi wajib menggunakan `id` (bigIncrements / unsignedBigInteger).

2. **Foreign Key**:
   - Format nama foreign key: `<nama_entitas_singular_tanpa_prefix>_id`.
   - Contoh:
     - `machine_id` merujuk ke `mstrs_machines(id)`
     - `department_id` merujuk ke `mstrs_departments(id)`
     - `location_id` merujuk ke `mstrs_locations(id)`
     - `checklist_id` merujuk ke `mstrs_checklists(id)`
     - `user_id` merujuk ke `mstrs_users(id)`
     - `inspection_id` merujuk ke `trnks_inspections(id)`

3. **Timestamps & Audit**:
   - Semua tabel master dan transaksi menggunakan `created_at` dan `updated_at`.
   - Aksi sensitif dicatat ke `trnks_activity_logs`.

---

## 3. Standar Eloquent Model

1. **Penamaan Model**:
   - Model menggunakan Singular PascalCase (misal `Machine`, `Department`, `Location`, `Inspection`, `Component`).
2. **Deklarasi Properti `$table`**:
   - Karena tabel menggunakan prefix kustom (`mstrs_` dan `trnks_`), **setiap Model wajib mendeklarasikan properti `$table` secara eksplisit**:
     ```php
     class Machine extends Model
     {
         protected $table = 'mstrs_machines';
     }
     ```
3. **Casting & Guarded**:
   - Hindari `$fillable` yang terpotong jika atribut sering bertambah, atau gunakan `$guarded = ['id']` dengan validasi ketat di Controller/Request.

---

## 4. Standar Data Mesin & Format QR Code

1. **Format Nilai QR Code**:
   - Nilai QR Code mesin digenerate dengan format terstandarisasi:
     `"[Nama mesin] / [Nomor Mesin] / ..."`
   - Contoh: `"GMAW / GMAW-003 / ..."`
   - Segmen ke-1: Nama jenis mesin (misal `GMAW`, `Bubut`, `Overhead Crane`).
   - Separator: spasi garis miring spasi (`" / "`).
   - Segmen ke-2: Nomor/kode unik mesin (misal `GMAW-003`, `taichuan 2`).
   - Segmen ke-3 dan seterusnya: fleksibel / `...` untuk data tambahan di masa mendatang.

2. **Mekanisme Pencocokan Scanner (Resilient Lookup)**:
   - Scanner wajib mendukung pencarian langsung berdasarkan kecocokan persis `qr_value`.
   - Sebagai *fallback*, jika tidak ditemukan kecocokan persis dan string mengandung separator `" / "`, sistem memecah string dan mengambil segmen ke-2 (Nomor Mesin) untuk dicocokkan ke kolom `code` pada `mstrs_machines`.

---

## 5. Standar Kode & Pengujian

1. **PHP Version**: PHP 8.2+.
2. **Code Style**: PSR-12, penulisan bersih dan terbaca.
3. **Automated Testing**:
   - Seluruh pengujian dijalankan melalui `php artisan test`.
   - Database testing menggunakan SQLite in-memory (`:memory:`).
   - Pengujian baru harus memvalidasi integritas relasi tabel ber-prefix `mstrs_` dan `trnks_`.
