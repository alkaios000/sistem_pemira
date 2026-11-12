# Spesifikasi & Dokumentasi Sistem PEMIRA Kampus (BEM & BPM)

Dokumen ini memuat spesifikasi kebutuhan sistem, alur proses inti, arsitektur keamanan QR Code, penanganan masalah teknis, skenario pendeteksian kecurangan (*Fraud Detection*), serta daftar fitur utama aplikasi **Sistem Pemilihan Umum Raya (PEMIRA)** untuk pemilihan **Presiden BEM** dan **Ketua Umum BPM**.

---

## 1. Deskripsi Sistem
Aplikasi **Sistem PEMIRA** adalah platform pemungutan suara elektronik (*e-voting*) tingkat kampus untuk memilih **Presiden BEM** dan **Ketua Umum BPM** secara langsung, umum, bebas, dan rahasia (*Secret Ballot*). Sistem dirancang menggunakan mekanisme **Dynamic QR Code per Bilik Suara** yang terintegrasi dengan penguncian sesi perangkat (*Device Session & Fingerprint Locking*) serta pemantauan keamanan *real-time*.

---

## 2. Core Engine QR Code
1. **Berbasis CDN**: Menggunakan pustaka generator & pemindai QR Code ringan melalui CDN untuk performa cepat.
2. **Dynamic & Auto-Reset Token**:
   - Token QR berotasi secara otomatis (*Rolling Token*) setiap **5–8 detik** (dengan *grace period* 1 siklus token sebelumnya untuk toleransi jaringan) saat bilik berstatus kosong (`IDLE`).
   - Token **langsung di-reset dan dikunci seketika (0 detik)** begitu berhasil dipindai oleh perangkat pemilih.
3. **Real-Time / Low Latency**: Sinkronisasi status bilik, validasi token, dan pembaruan perolehan suara (*Live Count*) berjalan secara *real-time* dengan latensi rendah.
4. **Unique Cryptographic Token**: Setiap token dibangkitkan secara kriptografis (`random_bytes` / HMAC) dan unik per bilik suara.
5. **Device ID & Browser Session Recording**: Mencatat kombinasi *Session Cookie*, *LocalStorage Token*, dan *Device Fingerprint* untuk mencegah 1 perangkat memindai atau memilih lebih dari satu kali.

---

## 3. Alur Proses Inti (Core Voting Flow)

```
[Meja Registrasi (Opsional/Rekomendasi)]
Mahasiswa hadir -> Panitia verifikasi KTM & aktifkan status "Siap Memilih"
       │
       ▼
[Masuk Bilik Suara]
Layar Bilik menampilkan QR Code Dinamis (Status Bilik: IDLE)
       │
       ▼
[Scan QR Code]
Mahasiswa scan QR di layar bilik -> Server mengunci Bilik (Status: IN_USE, QR disembunyikan)
-> Sistem merekam Device Fingerprint, Session Browser, IP, & mengikatnya ke Sesi Bilik
       │
       ▼
[Verifikasi Identitas]
Mahasiswa memasukkan NIM -> Sistem memvalidasi:
1. Apakah NIM terdaftar di DPT?
2. Apakah NIM belum memilih (sudah_memilih == 0)?
3. Apakah Sesi Device & Token Bilik sah?
       │
       ▼
[Pemilihan Kandidat (Client-Side Selection)]
Mahasiswa memilih Calon Presiden BEM & Ketua Umum BPM
(Saat diklik pada UI, pilihan belum dikirim/direkam ke database)
       │
       ▼
[Konfirmasi Suara (Atomic Transaction)]
Mahasiswa menekan tombol "Confirm Vote" -> Sistem menjalankan transaksi atomik:
1. Mengubah status mahasiswa: sudah_memilih = 1 (tanpa menyimpan ID kandidat yang dipilih)
2. Menambahkan counter suara (+1) pada kandidat BEM & kandidat BPM yang dipilih
3. Mencatat aktivitas ke Log Audit (Status: NORMAL / HIJAU)
       │
       ▼
[Selesai & Pembersihan Sesi]
Notifikasi Sukses muncul di HP -> Layar Bilik menampilkan status "Suara Berhasil Masuk"
-> Sesi voting ditutup & Bilik kembali menampilkan QR Code baru (Status: IDLE)
```

> **Proteksi Anti-Curang pada Alur**:
> - Jika mahasiswa memfoto QR Code lalu mencoba memindai ulang, token sudah hangus karena layar bilik langsung terkunci (`IN_USE`) saat pemindaian pertama.
> - Jika perangkat yang sama mencoba memindai QR Code lagi untuk kedua kalinya, sistem langsung menolak akses karena *Device ID / Session Browser* sudah tercatat telah memilih, dan otomatis merekam percobaan tersebut ke **Log Keamanan (Baris Merah)**.

---

## 4. Analisis Masalah & Solusi Arsitektur (Q&A)

### Masalah 1: Bagaimana jika ada mahasiswa iseng memilih menggunakan NIM milik orang lain?
* **Akar Masalah**: NIM bersifat publik di lingkungan kampus dan mudah ditebak (berurutan). Jika mahasiswa A memasukkan NIM mahasiswa B yang belum datang, hak pilih mahasiswa B akan terpakai (`sudah_memilih = 1`).
* **Solusi Berlapis**:
  1. **Sistem Check-In / Aktivasi di Meja Panitia (Paling Direkomendasikan)**:
     - Pada tabel mahasiswa, terdapat kolom `status_hadir` (`0` = Belum Hadir, `1` = Hadir / Siap Memilih).
     - Saat mahasiswa datang ke TPS dan menunjukkan KTM, panitia mengklik tombol **"Aktifkan / Check-In"** pada daftar mahasiswa.
     - Sistem di bilik suara **hanya menerima NIM yang sudah berstatus `status_hadir = 1` dan `sudah_memilih = 0`**. Mahasiswa tidak akan bisa memakai NIM temannya yang belum hadir di TPS.
  2. **PIN Akses Sekali Pakai (4–6 Digit) atau Verifikasi Data Sekunder**:
     - Saat check-in (atau melalui sistem), mahasiswa mendapatkan kode PIN singkat 4 digit untuk dimasukkan bersama NIM di bilik suara.
  3. **Konfirmasi Identitas di Layar & Pembatasan Percobaan (Rate Limiting)**:
     - Maksimal 3 kali salah input NIM per sesi scan. Jika mencoba menebak-nebak NIM orang lain, sesi langsung dikunci dan masuk ke **Log Merah**.

### Masalah 2: Token harus reset setiap detik tidak hanya saat scan, supaya aman dari difoto setelah orang scan. Apakah aman?
* **Analisis Teknis**:
  - Mereset token secara kaku setiap **1 detik (1000 ms)** **TIDAK AMAN untuk pengalaman pengguna (UX)** karena kamera ponsel membutuhkan waktu **1,5 – 4 detik** untuk *auto-focus*, *decoding* QR, membuka browser, hingga mengirim *request* HTTP ke server. Jika di-reset tiap 1 detik, pemilih sah akan terus-menerus gagal scan (*Token Expired*).
* **Solusi Terbaik & Paling Aman (State-Locked QR + Rolling Window)**:
  1. **Sembunyikan QR Seketika Saat Di-scan (State Machine `IN_USE`)**:
     - Keamanan terhadap **"difoto setelah orang scan"** tidak dicapai dengan mereset tiap 1 detik, melainkan dengan **menghilangkan QR Code dari layar bilik detik itu juga (0 ms)** begitu berhasil di-scan!
     - Saat mahasiswa berhasil scan, layar bilik langsung berubah menjadi: *"Bilik Sedang Digunakan — Silakan selesaikan pemilihan di perangkat Anda"*. Tidak ada QR yang bisa difoto setelah scan.
  2. **Rotasi Berkala 5–8 Detik + Grace Window 1 Token**:
     - Saat bilik kosong (`IDLE`), QR berotasi setiap **5–8 detik**. Server menerima token aktif saat ini ($T_{now}$) dan 1 token tepat sebelumnya ($T_{prev}$) agar pemilih yang memindai tepat di detik pergantian tidak gagal masuk.
     - Begitu 1 *request* masuk menggunakan token tersebut, token langsung hangus (*One-Time Use*) secara atomik di database.

### Masalah 3: Bagaimana jika ada 2 bilik atau lebih? Bukankah akan rebutan token?
* **Akar Masalah**: Jika seluruh bilik memakai 1 token global bersama, maka saat Bilik 1 memindai QR, token berubah dan menyebabkan mahasiswa di Bilik 2 gagal memindai (*race condition* antar bilik).
* **Solusi Arsitektur (Multi-Booth Independent Token)**:
  - **Token Terisolasi per Bilik (`booth_id`)**:
    - Buat entitas/tabel **Bilik Suara (`booths`)**, misalnya: `Bilik 1`, `Bilik 2`, `Bilik 3`, dst.
    - Setiap layar bilik membuka URL spesifik biliknya (contoh: `?page=portal_qr&booth=1` dan `?page=portal_qr&booth=2`).
    - Setiap bilik memiliki `current_token`, `previous_token`, `status_bilik` (`IDLE`, `IN_USE`, `CLOSED`), dan waktu kadaluarsa masing-masing.
    - Dengan demikian, aktivitas scan di **Bilik 1 tidak akan pernah mengganggu Bilik 2 maupun Bilik lainnya**, serta memudahkan panitia memantau bilik mana yang sedang aktif atau mengalami percobaan kecurangan.

---

## 5. Spesifikasi Fitur Utama (Core Features)

### 1. Admin Panel & Manajemen Kandidat (Referensi UI: Adminator Clean White)
* Mengikuti standar arsitektur dan desain pada `rule.md` (tampilan bersih berlatar putih, aksen tombol *soft blue*, ikon Font Awesome).
* **Manajemen Calon Presiden BEM** & **Manajemen Calon Ketua Umum BPM**:
  - Nomor urut kandidat, Nama Lengkap, NIM/Jurusan.
  - Upload Foto Kandidat (tervalidasi MIME type & ukuran sesuai `upload_helper.php`).
  - Input **Visi & Misi**.
  - Manajemen **Daftar Tim Sukses (Timses)** untuk masing-masing kandidat.

### 2. Manajemen Bilik Suara & Portal Manajemen Token (Booth & Token Management)
* Halaman khusus di Admin Panel untuk mengelola daftar bilik suara sekaligus mengontrol token QR secara *real-time*:
  - **CRUD Bilik Suara (Tambah, Ubah, Hapus)**:
    - Panitia dapat membuat bilik suara secara dinamis sesuai kebutuhan lapangan (contoh: `Bilik 1`, `Bilik 2`, `Bilik 3`, dst.) beserta keterangan nama/lokasi bilik.
  - **Pintasan Layar Portal QR per Bilik**:
    - Setiap bilik memiliki tombol **"Buka Layar QR"** yang membuka halaman portal QR khusus untuk layar monitor/laptop di bilik tersebut (contoh: `?page=portal_qr&booth=1`).
  - **Kontrol Status Bilik & Token (Per Bilik maupun Global)**:
    - **Buka / Tutup Sementara (*Pause / Resume*)**: Menutup sementara pemungutan suara pada bilik tertentu (misal laptop bilik sedang kendala) atau menutup **seluruh bilik sekaligus** (misal saat jam istirahat/ishoma atau waktu pemilihan selesai).
    - **Monitoring Status Real-Time**: Memantau apakah sebuah bilik sedang **`IDLE`** (Menunggu Pemilih / QR aktif), **`IN_USE`** (Sedang digunakan memilih + indikator durasi sesi), atau **`CLOSED`** (Ditutup sementara).
    - **Force Reset / Lepas Kunci Bilik**: Tombol darurat untuk mereset token dan membuka kunci bilik apabila pemilih mengalami kendala teknis di tengah proses (misal HP mati/sinyal terputus sebelum menekan tombol *Confirm*).
  - **Statistik Aktivitas per Bilik**:
    - Menampilkan jumlah pemilih yang telah berhasil memberikan suara di masing-masing bilik serta jumlah peringatan anomali/kecurangan per bilik.

### 3. Dashboard Ringkas Eksekutif
* Menampilkan ringkasan metrik utama secara cepat:
  - Total Daftar Pemilih Tetap (DPT).
  - Jumlah & persentase mahasiswa yang **Sudah Memilih** (`sudah_memilih = 1`).
  - Jumlah & persentase mahasiswa yang **Belum Memilih** (`sudah_memilih = 0`).
  - Partisipasi suara per Jurusan / Semester / Kelas.
  - Ringkasan jumlah aktivitas normal vs peringatan kecurangan dari Log Keamanan.

### 4. Manajemen Mahasiswa / DPT (Hierarki Kelas & Import Massal)
* **Alur Hierarki Kelas**:
  1. Admin membuat data **Kelas** terlebih dahulu melalui formulir: `Nama Kelas`, `Jurusan`, dan `Semester`.
  2. Admin masuk ke dalam detail Kelas tersebut untuk mengelola daftar mahasiswa di dalamnya.
* **Penambahan & Import Massal Mahasiswa**:
  - Mendukung tambah manual per mahasiswa (`NIM`, `Nama`).
  - Mendukung **Import Massal via File Excel / CSV** maupun **Copy-Paste langsung dari Excel/Spreadsheet** dengan format kolom `NIM` dan `NAMA` (otomatis mewarisi `Kelas`, `Jurusan`, dan `Semester` dari kelas yang sedang dibuka, atau dari kolom CSV jika impor global).
  - **Fitur Preview & Validasi Sebelum Simpan**: Data hasil unggah/copy-paste ditampilkan pada tabel *Review/Preview* terlebih dahulu (mendeteksi NIM duplikat atau format kosong) sebelum benar-benar disimpan ke database.
* **Prinsip Kerahasiaan Suara (Secret Ballot Database Design)**:
  - Tabel mahasiswa memiliki kolom `sudah_memilih` bertipe `BOOLEAN / TINYINT(1)` dengan nilai default `0` (Belum Memilih) dan `1` (Sudah Memilih).
  - **TIDAK ADA kolom atau relasi yang menyimpan pilihan kandidat per mahasiswa**. Saat tombol *Confirm* ditekan, sistem hanya mengubah `sudah_memilih = 1` pada mahasiswa dan menambah kolom `jumlah_suara = jumlah_suara + 1` pada tabel kandidat.

### 5. Log Audit & Pendeteksian Kecurangan (Fraud Detection Log)
* Sistem mencatat setiap interaksi pemindaian QR, verifikasi NIM, hingga pengiriman suara beserta `device_id` (*fingerprint*), `session_id`, `ip_address`, `user_agent`, `booth_id`, `nim`, dan `nama_mahasiswa` (jika NIM telah diinput).
* **Indikator Warna Baris Tabel Log**:
  - **Hijau (`NORMAL`)**: Pemindaian QR sah, input NIM valid, dan konfirmasi suara berhasil tanpa anomali.
  - **Merah (`FRAUD / ANOMALI`)**: Terdeteksi tindakan tidak wajar atau percobaan kecurangan.
* **Daftar Skenario & Pengkondisian Kecurangan yang Dideteksi Sistem**:
  1. `DOUBLE_SCAN_SAME_DEVICE`: Perangkat (`device_id` / *session* / *fingerprint*) yang sudah pernah digunakan memilih mencoba memindai QR Code kembali.
  2. `NIM_ALREADY_VOTED`: Memasukkan NIM yang berstatus `sudah_memilih = 1`.
  3. `NIM_NOT_CHECKED_IN`: Memasukkan NIM mahasiswa yang belum melakukan registrasi/check-in di meja panitia (indikasi memakai NIM orang lain).
  4. `EXPIRED_OR_USED_TOKEN_SCAN`: Memindai QR Code dari hasil tangkapan layar/foto yang tokennya sudah kadaluarsa atau sedang digunakan orang lain.
  5. `DIRECT_URL_BYPASS`: Mencoba mengakses halaman form pemilihan secara langsung melalui URL tanpa melalui pemindaian QR bilik yang sah.
  6. `SESSION_HIJACK_MISMATCH`: Token sesi pemilihan dikirim dari *Device ID* atau *User-Agent* yang berbeda dari perangkat yang pertama kali memindai QR.
  7. `NIM_BRUTEFORCE_ATTEMPT`: Melakukan kesalahan input NIM berulang kali ($\ge 3$ kali) dalam satu sesi bilik untuk menebak NIM aktif.
  8. `RACE_CONDITION_DOUBLE_SUBMIT`: Mengirimkan *request* konfirmasi suara ganda secara bersamaan (spam klik / manipulasi *request*) untuk menggandakan suara.
  9. `PAYLOAD_TAMPERING`: Memanipulasi ID kandidat pada *request* POST (misal mengirim ID yang tidak ada atau menukar ID calon BEM ke slot BPM).

### 6. Portal Perhitungan Suara Terpisah (Live Real Count BEM & BPM)
* Disediakan dua halaman portal perhitungan suara (*Live Count*) yang terpisah dan dapat ditampilkan di layar proyektor publik:
  1. **Portal Live Count Presiden BEM**
  2. **Portal Live Count Ketua Umum BPM**
* Masing-masing portal menampilkan:
  - Perolehan suara *real-time* setiap kandidat beserta persentasenya.
  - Total suara yang telah masuk dibandingkan dengan Total Pemilih (DPT) serta persentase tingkat partisipasi pemilih.
