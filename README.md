# Sistem PEMIRA Kampus (Pemilihan Umum Raya Presiden BEM & Ketua Umum BPM)

Aplikasi pemungutan suara elektronik (*e-voting*) tingkat kampus untuk memilih **Presiden BEM** dan **Ketua Umum BPM** dengan arsitektur **Modern Native (Single Entry Point & Clean MVC-Hybrid)** serta **Dynamic Multi-Booth QR Code Engine**.

---

## Fitur Utama

1. **Core Engine Dynamic QR Code per Bilik (`portal_qr`)**
   - Menggunakan pustaka QRCode berbasis CDN.
   - Setiap bilik memiliki token kriptografis independen agar tidak terjadi perebutan token antar bilik (*Multi-Booth Token*).
   - Saat bilik kosong (`IDLE`), token berotasi otomatis setiap 8 detik dengan *grace window* 1 token sebelumnya.
   - Begitu dipindai oleh perangkat mahasiswa, token langsung di-reset detik itu juga dan layar bilik menyembunyikan kode QR (`IN_USE`) agar tidak dapat difoto setelah scan.
   - Merekam `device_id`, `browser_session_id`, dan `fingerprint_hash` perangkat untuk mencegah 1 perangkat melakukan scan/pemilihan lebih dari satu kali.

2. **Panel Administrasi & Manajemen Kandidat (`manajemen_kandidat`)**
   - Mengelola Calon Presiden BEM dan Calon Ketua Umum BPM.
   - Unggah foto resmi kandidat, pengisian Visi & Misi, serta manajemen daftar anggota Tim Sukses (Timses).

3. **Manajemen Bilik Suara & Kontrol Token (`manajemen_bilik`)**
   - Tambah, ubah, dan hapus bilik suara secara dinamis.
   - Kontrol Buka / Tutup Sementara (*Pause / Resume*) portal QR secara global maupun per bilik.
   - Tombol **Reset Token** (*Force Reset*) untuk membuka kunci bilik jika perangkat pemilih mengalami kendala teknis sebelum menekan tombol *Confirm*.
   - Mode opsional **Wajib Check-In Meja Panitia** untuk mencegah mahasiswa memilih menggunakan NIM orang lain.

4. **Dashboard Statistik Ringkas (`dashboard`)**
   - Memantau jumlah dan persentase mahasiswa yang **Sudah Memilih (`1`)** dan **Belum Memilih (`0`)** secara *real-time*.

5. **Manajemen Kelas & Mahasiswa / DPT (`manajemen_mahasiswa`)**
   - Alur terstruktur: Tambah **Kelas** terlebih dahulu (`Nama Kelas`, `Jurusan`, `Semester`), lalu masuk ke dalam kelas untuk mengelola mahasiswa.
   - Mendukung **Import Massal menggunakan Excel / CSV** maupun **Copy-Paste langsung dari Spreadsheet** dengan **Tabel Review / Preview** terlebih dahulu sebelum disimpan ke database.
   - Menerapkan prinsip **Secret Ballot**: Tabel mahasiswa hanya menyimpan status `sudah_memilih` (`0` atau `1`) tanpa menyimpan ID kandidat yang dipilih.

6. **Log Audit Keamanan & Deteksi Kecurangan (`log_keamanan`)**
   - Mencatat setiap aktivitas pemindaian dan pemilihan lengkap dengan `device_id`, `fingerprint_hash`, `nim`, dan `nama_mahasiswa`.
   - Indikator baris tabel berwarna **Hijau (`NORMAL`)** untuk pemilihan sah dan **Merah (`FRAUD`)** untuk tindakan tidak wajar / percobaan kecurangan (scan 2x oleh device yang sama, pemakaian NIM yang sudah memilih, scan dari foto token kadaluarsa, bypass URL langsung, brute-force NIM, hingga double submit).

7. **Portal Perhitungan Suara Terpisah (`live_count_bem` & `live_count_bpm`)**
   - Halaman *Live Real Count* terpisah untuk **Presiden BEM** dan **Ketua Umum BPM** beserta total suara masuk dibandingkan dengan total pemilih (DPT).

---

## Instalasi & Menjalankan Sistem

1. Pastikan layanan **Apache/Nginx** dan **MySQL** pada Laragon telah berjalan.
2. Sesuaikan konfigurasi basis data pada berkas `.env` (default database: `db_sistem_pemira`).
3. Jalankan skrip migrasi & seeder melalui terminal atau buka langsung aplikasi di browser (sistem mendukung auto-bootstrap skema saat pertama kali diakses):
   ```powershell
   php database/migrate.php
   ```
4. Akses aplikasi melalui browser:
   - **Panel Admin**: `http://localhost/sistem_pemira/`
   - **Akun Default Admin**: Username `admin` | Password `admin123`  
   - **Akun Default Panitia**: Username `panitia` | Password `panitia123`

