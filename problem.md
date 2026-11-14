# Analisis Potensi Gagal Logika & Tabrakan Sistem PEMIRA

Tanggal Audit: 15 November 2026

Dokumen ini merangkum temuan potensi gagal logika (*edge-case logic*) dan tabrakan perilaku antar-komponen berdasarkan peninjauan langsung pada kode sumber saat ini.

---

## 1. False Positive `fingerprint_hash` pada Perangkat Identik *(Prioritas Tinggi)*

- **Lokasi Terkait:**
  - `assets/js/modules/voting_client.js` (baris 25–39, `getOrCreateDeviceIdentity()`)
  - `models/voting_model.php` (baris 290–316, `check_device_already_voted()`)
- **Penyebab:**
  `fingerprint_hash` pada klien hanya dihitung dari 4 atribut umum: `userAgent`, ukuran layar (`screen.width x screen.height`), `language`, dan `timeZone` (`Asia/Jakarta`). Sementara di server, fungsi `check_device_already_voted()` memblokir menggunakan kondisi `OR (ds.fingerprint_hash = :fingerprint_hash AND ds.fingerprint_hash != "" AND ds.fingerprint_hash != "unknown_fp")`.
- **Dampak / Tabrakan:**
  Dua mahasiswa berbeda yang menggunakan tipe ponsel dan browser identik (misalnya model iPhone/Android yang sama dengan bahasa Indonesia dan zona waktu WIB), atau memakai PC laboratorium yang seragam, dapat menghasilkan nilai `fingerprint_hash` yang persis sama sehingga pemilih kedua keliru ditolak sebagai `DOUBLE_SCAN_SAME_DEVICE`.
- **Rekomendasi Perbaikan:**
  Jadikan identifikasi pemblokiran ganda berpusat pada `device_id` (beserta cookie HMAC `pemira_voted_device`) dan `browser_session_id`, atau perkuat *entropy* sinyal klien (seperti `hardwareConcurrency`, `deviceMemory`, `maxTouchPoints`, kedalaman warna/canvas) serta jangan jadikan kecocokan `fingerprint_hash` tunggal sebagai pemblokir keras pada perangkat umum.

---

## 2. `toggle_global_portal` Menimpa Bilik Aktif (`IN_USE`) & Status `CLOSED` Individual *(Prioritas Menengah)*

- **Lokasi Terkait:**
  - `app/controllers/bilik_action.php` (baris 160–178, `toggle_global_portal`)
  - `models/bilik_model.php` (baris 272–311, `set_status_all_biliks()`)
- **Penyebab:**
  Ketika panitia mengubah status portal global (`TUTUP` atau `BUKA`), `set_status_all_biliks()` memilih seluruh baris `SELECT id FROM bilik_suaras` lalu mengosongkan `active_session_token`, `active_device_id`, `active_nim`, dan `locked_until` pada semua bilik tanpa mengecualikan bilik yang sedang `IN_USE`. Selain itu, ketika portal diubah kembali ke `BUKA`, seluruh bilik disetel ke `IDLE` secara serentak.
- **Dampak / Tabrakan:**
  1. Mahasiswa yang sedang aktif melakukan Verifikasi NIM atau memilih kandidat di salah satu bilik akan terputus sesinya seketika saat portal global ditutup sementara.
  2. Bilik yang memang sengaja dinonaktifkan per-bilik (`CLOSED`, misalnya karena perangkat bilik rusak/cadangan) ikut terbuka otomatis menjadi `IDLE` saat portal global dibuka kembali.
- **Rekomendasi Perbaikan:**
  Pisahkan penutupan portal global (`status_portal_global`) dengan status perawatan bilik individual, atau kecualikan bilik `IN_USE` saat penutupan berlangsung agar pemilih yang sudah masuk tetap bisa menuntaskan surat suaranya terlebih dahulu sebelum bilik menjadi `CLOSED`.

---

## 3. Batas Pemulihan Sesi Lintas Aplikasi (*WebView* vs Browser Utama) *(Prioritas Rendah)*

- **Lokasi Terkait:**
  - `models/voting_model.php` (baris 140–285, `restore_active_voting_session()`)
  - `views/pages/bilik_pemilihan.php` (baris 19–29)
- **Penyebab:**
  Pemulihan sesi otomatis saat pemilih menutup lalu membuka kembali browser bekerja dengan memvalidasi cookie `HttpOnly` bertanda tangan HMAC (`pemira_device_id`, `pemira_device_sig`, `pemira_active_vote`) di browser yang sama.
- **Dampak / Tabrakan:**
  Jika pemilih memindai QR melalui *in-app browser* (WebView bawaan aplikasi pihak ketiga) lalu berpindah secara manual membuka browser terpisah (Chrome/Safari utama), cookie persisten tidak ikut terbawa antar-aplikasi karena isolasi sistem operasi, sehingga layar akan meminta pindai ulang sementara bilik sebelumnya masih menunggu *lock timeout* berakhir.
- **Rekomendasi Perbaikan:**
  Arahkan pemilih menggunakan pemindai kamera langsung pada halaman web (yang kini sudah otomatis meminta izin kamera di Tahap 1) atau kamera bawaan sistem yang membuka tautan langsung di browser utama yang sama.

---

## 4. Benturan Uji Coba Simulasi Panitia & Voter pada Satu Profil Browser *(Operasional)*

- **Lokasi Terkait:**
  - `app/controllers/auth_action.php` (baris 24–61, `logout`)
  - `models/voting_model.php` (baris 31–97, cookie `pemira_voted_device`)
- **Penyebab:**
  Proses *logout* panitia hanya menghancurkan sesi panel (`$_SESSION` dan `PHPSESSID`), sedangkan tanda tangan anti-pemilihan ganda (`pemira_device_id`, `pemira_voted_device`, `pemira_voted_sig`) disimpan persisten selama 30 hari pada browser yang sama.
- **Dampak / Tabrakan:**
  Jika panitia melakukan simulasi *voting* sampai selesai pada browser yang dipakai mengelola panel, lalu *logout* dan mencoba menguji *voting* lagi di profil browser yang sama tanpa membersihkan cookie, browser tersebut akan terblokir oleh aturan 1 perangkat = 1 suara (`panelAlreadyVoted` / `DOUBLE_SCAN_SAME_DEVICE`).
- **Rekomendasi Perbaikan:**
  Untuk keperluan uji coba/simulasi panitia, gunakan jendela *Incognito*/*Private* atau profil/perangkat terpisah dari browser operasional panitia.

---

## Ringkasan Komponen yang Sudah Konsisten & Terverifikasi

Di luar 4 catatan di atas, hasil pengujian otomatis dan peninjauan kode menunjukkan mekanisme inti berjalan konsisten tanpa tabrakan:
- **Transaksi Suara Atomik:** `submit_atomic_vote()` menggunakan penguncian baris `SELECT ... FOR UPDATE` dan pembaruan bersyarat `WHERE sudah_memilih = 0` untuk mencegah *race condition* dan *double submit*.
- **Isolasi Layar Bilik (*Kiosk*):** `layar_bilik` + `poll_kiosk_qr` terkunci pada satu `bilik_id` dengan `kiosk_token`, header keamanan (`DENY`, `frame-ancestors 'none'`, `no-referrer`), rotasi kunci instan, serta tanpa akses panel admin.
- **Kontrol Akses (RBAC) & Audit Log:** Pemisahan hak akses 3 peran (`superadmin`, `kprm`, `banwasra`), pemisahan kategori log (`pemilih` vs `admin`), serta pengikatan sesi pemilih terhadap cookie HMAC untuk mencegah *session hijack* dan manipulasi *payload*.
