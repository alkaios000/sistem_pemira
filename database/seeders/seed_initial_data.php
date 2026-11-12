<?php
/**
 * Seeder Data Awal Sistem PEMIRA
 * Mengisi akun admin/panitia, pengaturan default, bilik suara, kelas, contoh mahasiswa,
 * kandidat BEM & BPM beserta tim sukses, dan contoh log audit.
 */

function run_initial_seeder(PDO $pdo): void
{
    // 1. Seed Pengguna Admin & Panitia
    $stmt_check_user = $pdo->query('SELECT COUNT(*) FROM users');
    if ((int) $stmt_check_user->fetchColumn() === 0) {
        $stmt_user = $pdo->prepare('
            INSERT INTO users (username, nama_lengkap, password_hash, role)
            VALUES (:username, :nama_lengkap, :password_hash, :role)
        ');

        $stmt_user->execute([
            ':username'      => 'admin',
            ':nama_lengkap'  => 'Administrator KPU PEMIRA',
            ':password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            ':role'          => 'admin',
        ]);

        $stmt_user->execute([
            ':username'      => 'panitia',
            ':nama_lengkap'  => 'Panitia Pengawas TPS',
            ':password_hash' => password_hash('panitia123', PASSWORD_BCRYPT),
            ':role'          => 'panitia',
        ]);
    }

    // 2. Seed Pengaturan Sistem Global
    $default_settings = [
        [
            'kunci'      => 'status_portal_global',
            'nilai'      => 'BUKA',
            'keterangan' => 'Status global portal pemungutan suara (BUKA / TUTUP)',
        ],
        [
            'kunci'      => 'wajib_checkin_panitia',
            'nilai'      => '0',
            'keterangan' => '1 = Wajib diaktifkan panitia sebelum masuk bilik, 0 = Langsung aktif pakai NIM',
        ],
        [
            'kunci'      => 'nama_institusi',
            'nilai'      => 'Komisi Pemilihan Umum Raya Mahasiswa (KPURM)',
            'keterangan' => 'Nama penyelenggara PEMIRA pada portal dan laporan',
        ],
        [
            'kunci'      => 'periode_pemira',
            'nilai'      => '2026 / 2027',
            'keterangan' => 'Tahun periode pemilihan',
        ],
    ];

    $stmt_setting = $pdo->prepare('
        INSERT IGNORE INTO pengaturan_sistem (kunci, nilai, keterangan)
        VALUES (:kunci, :nilai, :keterangan)
    ');
    foreach ($default_settings as $setting) {
        $stmt_setting->execute([
            ':kunci'      => $setting['kunci'],
            ':nilai'      => $setting['nilai'],
            ':keterangan' => $setting['keterangan'],
        ]);
    }

    // 3. Seed Bilik Suara (Multi-Booth)
    $stmt_check_bilik = $pdo->query('SELECT COUNT(*) FROM bilik_suaras');
    if ((int) $stmt_check_bilik->fetchColumn() === 0) {
        $stmt_bilik = $pdo->prepare('
            INSERT INTO bilik_suaras (nomor_bilik, nama_bilik, lokasi_bilik, status_bilik, current_token)
            VALUES (:nomor_bilik, :nama_bilik, :lokasi_bilik, :status_bilik, :current_token)
        ');

        $biliks = [
            ['nomor' => 1, 'nama' => 'Bilik Suara 01', 'lokasi' => 'Aula Utama Kampus - Sisi Barat'],
            ['nomor' => 2, 'nama' => 'Bilik Suara 02', 'lokasi' => 'Aula Utama Kampus - Sisi Tengah'],
            ['nomor' => 3, 'nama' => 'Bilik Suara 03', 'lokasi' => 'Aula Utama Kampus - Sisi Timur'],
        ];

        foreach ($biliks as $b) {
            $stmt_bilik->execute([
                ':nomor_bilik'   => $b['nomor'],
                ':nama_bilik'    => $b['nama'],
                ':lokasi_bilik'  => $b['lokasi'],
                ':status_bilik'  => 'IDLE',
                ':current_token' => bin2hex(random_bytes(24)),
            ]);
        }
    }

    // 4. Seed Kelas & Mahasiswa (DPT Contoh)
    $stmt_check_kelas = $pdo->query('SELECT COUNT(*) FROM kelas');
    if ((int) $stmt_check_kelas->fetchColumn() === 0) {
        $stmt_kelas = $pdo->prepare('
            INSERT INTO kelas (nama_kelas, jurusan, semester)
            VALUES (:nama_kelas, :jurusan, :semester)
        ');

        $daftar_kelas = [
            ['nama_kelas' => 'TI-3A', 'jurusan' => 'Teknik Informatika', 'semester' => 3],
            ['nama_kelas' => 'TI-5B', 'jurusan' => 'Teknik Informatika', 'semester' => 5],
            ['nama_kelas' => 'SI-3A', 'jurusan' => 'Sistem Informasi', 'semester' => 3],
            ['nama_kelas' => 'MI-1A', 'jurusan' => 'Manajemen Informatika', 'semester' => 1],
        ];

        $kelas_ids = [];
        foreach ($daftar_kelas as $k) {
            $stmt_kelas->execute([
                ':nama_kelas' => $k['nama_kelas'],
                ':jurusan'    => $k['jurusan'],
                ':semester'   => $k['semester'],
            ]);
            $kelas_ids[$k['nama_kelas']] = (int) $pdo->lastInsertId();
        }

        $stmt_mhs = $pdo->prepare('
            INSERT INTO mahasiswas (kelas_id, nim, nama_lengkap, status_hadir, sudah_memilih, waktu_memilih)
            VALUES (:kelas_id, :nim, :nama_lengkap, :status_hadir, :sudah_memilih, :waktu_memilih)
        ');

        $daftar_mhs = [
            ['kelas' => 'TI-3A', 'nim' => '240101001', 'nama' => 'Aditya Pratama Putra', 'sudah' => 1, 'waktu' => date('Y-m-d H:i:s', time() - 1800)],
            ['kelas' => 'TI-3A', 'nim' => '240101002', 'nama' => 'Bunga Citra Lestari', 'sudah' => 1, 'waktu' => date('Y-m-d H:i:s', time() - 1500)],
            ['kelas' => 'TI-3A', 'nim' => '240101003', 'nama' => 'Cahyo Nugroho', 'sudah' => 0, 'waktu' => null],
            ['kelas' => 'TI-3A', 'nim' => '240101004', 'nama' => 'Dinda Kirana Putri', 'sudah' => 0, 'waktu' => null],
            ['kelas' => 'TI-5B', 'nim' => '230102001', 'nama' => 'Eko Prasetyo Wibowo', 'sudah' => 1, 'waktu' => date('Y-m-d H:i:s', time() - 900)],
            ['kelas' => 'TI-5B', 'nim' => '230102002', 'nama' => 'Fajar Siddiq', 'sudah' => 0, 'waktu' => null],
            ['kelas' => 'TI-5B', 'nim' => '230102003', 'nama' => 'Gita Savitri Devi', 'sudah' => 0, 'waktu' => null],
            ['kelas' => 'SI-3A', 'nim' => '240201001', 'nama' => 'Hendra Setiawan', 'sudah' => 0, 'waktu' => null],
            ['kelas' => 'SI-3A', 'nim' => '240201002', 'nama' => 'Indah Permatasari', 'sudah' => 0, 'waktu' => null],
            ['kelas' => 'MI-1A', 'nim' => '250301001', 'nama' => 'Joko Anwar Saputra', 'sudah' => 0, 'waktu' => null],
        ];

        foreach ($daftar_mhs as $m) {
            $stmt_mhs->execute([
                ':kelas_id'      => $kelas_ids[$m['kelas']],
                ':nim'           => $m['nim'],
                ':nama_lengkap'  => $m['nama'],
                ':status_hadir'  => 1,
                ':sudah_memilih' => $m['sudah'],
                ':waktu_memilih' => $m['waktu'],
            ]);
        }
    }

    // 5. Seed Kandidat BEM & BPM beserta Tim Sukses
    $stmt_check_kandidat = $pdo->query('SELECT COUNT(*) FROM kandidats');
    if ((int) $stmt_check_kandidat->fetchColumn() === 0) {
        $stmt_kandidat = $pdo->prepare('
            INSERT INTO kandidats (kategori, nomor_urut, nama_calon, nama_wakil, jurusan_kelas, foto_path, visi, misi, jumlah_suara)
            VALUES (:kategori, :nomor_urut, :nama_calon, :nama_wakil, :jurusan_kelas, :foto_path, :visi, :misi, :jumlah_suara)
        ');

        $stmt_timses = $pdo->prepare('
            INSERT INTO tim_sukses (kandidat_id, nama_anggota, nim_anggota, jabatan_timses)
            VALUES (:kandidat_id, :nama_anggota, :nim_anggota, :jabatan_timses)
        ');

        $kandidat_data = [
            [
                'kategori'      => 'BEM',
                'nomor_urut'    => 1,
                'nama_calon'    => 'Rizky Ramadhan',
                'nama_wakil'    => 'Nabila Azzahra',
                'jurusan_kelas' => 'Teknik Informatika / Sistem Informasi',
                'visi'          => 'Mewujudkan BEM Kampus yang inklusif, kolaboratif, dan berdampak nyata bagi pengembangan prestasi serta kesejahteraan mahasiswa.',
                'misi'          => "1. Mengoptimalkan advokasi kebijakan kampus yang berpihak pada mahasiswa.\n2. Membangun ekosistem inkubasi riset, kompetisi, dan kewirausahaan.\n3. Menyelenggarakan transparansi anggaran dan program kerja berbasis digital.",
                'jumlah_suara'  => 2,
                'timses'        => [
                    ['nama' => 'Dimas Anggara', 'nim' => '230101011', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Siti Nurhaliza', 'nim' => '230201015', 'jabatan' => 'Koordinator Humas & Kampanye'],
                    ['nama' => 'Bagas Adi', 'nim' => '240101022', 'jabatan' => 'Koordinator Saksi TPS'],
                ],
            ],
            [
                'kategori'      => 'BEM',
                'nomor_urut'    => 2,
                'nama_calon'    => 'Farhan Maulana',
                'nama_wakil'    => 'Alya Putri Maharani',
                'jurusan_kelas' => 'Sistem Informasi / Teknik Informatika',
                'visi'          => 'Transformasi BEM sebagai poros pergerakan mahasiswa yang progresif, responsif, dan berintegritas tinggi.',
                'misi'          => "1. Memperkuat sinergi antar UKM dan Himpunan Mahasiswa Jurusan.\n2. Membuka kanal aspirasi mahasiswa terpadu 24 jam.\n3. Memperluas jejaring kemitraan karier dan magang industri bagi mahasiswa.",
                'jumlah_suara'  => 1,
                'timses'        => [
                    ['nama' => 'Reza Rahadian', 'nim' => '230102019', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Putri Marino', 'nim' => '240201030', 'jabatan' => 'Koordinator Media & Publikasi'],
                ],
            ],
            [
                'kategori'      => 'BPM',
                'nomor_urut'    => 1,
                'nama_calon'    => 'Mahendra Wijaya',
                'nama_wakil'    => null,
                'jurusan_kelas' => 'Teknik Informatika - Semester 5',
                'visi'          => 'Mewujudkan Badan Perwakilan Mahasiswa (BPM) yang aspiratif, tegas dalam pengawasan, dan transparan dalam legislasi kampus.',
                'misi'          => "1. Melaksanakan fungsi pengawasan kinerja BEM secara objektif dan berkala.\n2. Menyusun produk hukum kemahasiswaan yang adaptif dan demokratis.\n3. Menyerap aspirasi seluruh kelas melalui forum dengar pendapat rutin.",
                'jumlah_suara'  => 2,
                'timses'        => [
                    ['nama' => 'Arif Muhammad', 'nim' => '230101040', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Lesti Kejora', 'nim' => '240101051', 'jabatan' => 'Koordinator Lapangan'],
                ],
            ],
            [
                'kategori'      => 'BPM',
                'nomor_urut'    => 2,
                'nama_calon'    => 'Clarissa Devina',
                'nama_wakil'    => null,
                'jurusan_kelas' => 'Sistem Informasi - Semester 5',
                'visi'          => 'Menjadikan BPM sebagai parlemen kampus yang modern, akuntabel, dan dekat dengan seluruh elemen mahasiswa.',
                'misi'          => "1. Digitalisasi sistem pelaporan legislasi dan transparansi dana kemahasiswaan.\n2. Memperkuat kapasitas komisi pengawasan dan anggaran.\n3. Mengawal setiap rekomendasi mahasiswa hingga tingkat pimpinan kampus.",
                'jumlah_suara'  => 1,
                'timses'        => [
                    ['nama' => 'Kevin Sanjaya', 'nim' => '230201088', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Maudy Ayunda', 'nim' => '230201090', 'jabatan' => 'Koordinator Strategi'],
                ],
            ],
        ];

        foreach ($kandidat_data as $kd) {
            $stmt_kandidat->execute([
                ':kategori'      => $kd['kategori'],
                ':nomor_urut'    => $kd['nomor_urut'],
                ':nama_calon'    => $kd['nama_calon'],
                ':nama_wakil'    => $kd['nama_wakil'],
                ':jurusan_kelas' => $kd['jurusan_kelas'],
                ':foto_path'     => null,
                ':visi'          => $kd['visi'],
                ':misi'          => $kd['misi'],
                ':jumlah_suara'  => $kd['jumlah_suara'],
            ]);
            $kandidat_id = (int) $pdo->lastInsertId();

            foreach ($kd['timses'] as $ts) {
                $stmt_timses->execute([
                    ':kandidat_id'    => $kandidat_id,
                    ':nama_anggota'   => $ts['nama'],
                    ':nim_anggota'    => $ts['nim'],
                    ':jabatan_timses' => $ts['jabatan'],
                ]);
            }
        }
    }

    // 6. Seed Contoh Audit Log (Hijau = NORMAL & Merah = FRAUD)
    $stmt_check_log = $pdo->query('SELECT COUNT(*) FROM audit_logs');
    if ((int) $stmt_check_log->fetchColumn() === 0) {
        $stmt_log = $pdo->prepare('
            INSERT INTO audit_logs (bilik_id, nim, nama_mahasiswa_cache, device_id, fingerprint_hash, browser_session_id, ip_address, user_agent, status_log, kode_event, judul_event, keterangan, created_at)
            VALUES (:bilik_id, :nim, :nama, :device_id, :fp, :sess, :ip, :ua, :status_log, :kode_event, :judul_event, :keterangan, :created_at)
        ');

        $sample_logs = [
            [
                'bilik_id'    => 1,
                'nim'         => '240101001',
                'nama'        => 'Aditya Pratama Putra',
                'device_id'   => 'dev_9a8b7c6d5e4f_fp101',
                'fp'          => 'fp_a1b2c3d4e5f60001',
                'sess'        => 'sess_8812391823',
                'ip'          => '192.168.1.101',
                'ua'          => 'Mozilla/5.0 (Linux; Android 14; Samsung SM-S918B) Chrome/128.0',
                'status_log'  => 'NORMAL',
                'kode_event'  => 'VOTE_SUCCESS',
                'judul_event' => 'Pemungutan Suara Berhasil',
                'keterangan'  => 'Mahasiswa berhasil memindai QR Bilik 1, memverifikasi NIM, dan mengonfirmasi pilihan BEM & BPM secara sah.',
                'created_at'  => date('Y-m-d H:i:s', time() - 1800),
            ],
            [
                'bilik_id'    => 2,
                'nim'         => '240101002',
                'nama'        => 'Bunga Citra Lestari',
                'device_id'   => 'dev_1f2e3d4c5b6a_fp102',
                'fp'          => 'fp_b2c3d4e5f6a70002',
                'sess'        => 'sess_9918273645',
                'ip'          => '192.168.1.104',
                'ua'          => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Safari/604.1',
                'status_log'  => 'NORMAL',
                'kode_event'  => 'VOTE_SUCCESS',
                'judul_event' => 'Pemungutan Suara Berhasil',
                'keterangan'  => 'Mahasiswa berhasil memindai QR Bilik 2, memverifikasi NIM, dan mengonfirmasi pilihan BEM & BPM secara sah.',
                'created_at'  => date('Y-m-d H:i:s', time() - 1500),
            ],
            [
                'bilik_id'    => 1,
                'nim'         => '240101001',
                'nama'        => 'Aditya Pratama Putra',
                'device_id'   => 'dev_9a8b7c6d5e4f_fp101',
                'fp'          => 'fp_a1b2c3d4e5f60001',
                'sess'        => 'sess_8812391823',
                'ip'          => '192.168.1.101',
                'ua'          => 'Mozilla/5.0 (Linux; Android 14; Samsung SM-S918B) Chrome/128.0',
                'status_log'  => 'FRAUD',
                'kode_event'  => 'DOUBLE_SCAN_SAME_DEVICE',
                'judul_event' => 'Percobaan Scan Ke-2 oleh Device yang Sama',
                'keterangan'  => 'Perangkat dengan Device ID dev_9a8b7c6d5e4f_fp101 (terkait NIM 240101001 - Aditya Pratama Putra) mencoba memindai ulang QR Code di Bilik 1 setelah selesai memilih.',
                'created_at'  => date('Y-m-d H:i:s', time() - 1200),
            ],
            [
                'bilik_id'    => 3,
                'nim'         => '230102001',
                'nama'        => 'Eko Prasetyo Wibowo',
                'device_id'   => 'dev_7c8d9e0f1a2b_fp103',
                'fp'          => 'fp_c3d4e5f6a7b80003',
                'sess'        => 'sess_7716253443',
                'ip'          => '192.168.1.112',
                'ua'          => 'Mozilla/5.0 (Linux; Android 13; Xiaomi 22101316G) Chrome/127.0',
                'status_log'  => 'NORMAL',
                'kode_event'  => 'VOTE_SUCCESS',
                'judul_event' => 'Pemungutan Suara Berhasil',
                'keterangan'  => 'Mahasiswa berhasil memindai QR Bilik 3, memverifikasi NIM, dan mengonfirmasi pilihan BEM & BPM secara sah.',
                'created_at'  => date('Y-m-d H:i:s', time() - 900),
            ],
            [
                'bilik_id'    => 2,
                'nim'         => '240101002',
                'nama'        => 'Bunga Citra Lestari',
                'device_id'   => 'dev_5x6y7z8w9v0u_fp999',
                'fp'          => 'fp_x9y8z7w6v5u49999',
                'sess'        => 'sess_1122334455',
                'ip'          => '192.168.1.150',
                'ua'          => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0',
                'status_log'  => 'FRAUD',
                'kode_event'  => 'NIM_ALREADY_VOTED',
                'judul_event' => 'Percobaan Menggunakan NIM yang Sudah Memilih',
                'keterangan'  => 'Perangkat baru di Bilik 2 mencoba memasukkan NIM 240101002 (Bunga Citra Lestari) yang tercatat sudah menggunakan hak pilihnya.',
                'created_at'  => date('Y-m-d H:i:s', time() - 420),
            ],
        ];

        foreach ($sample_logs as $log) {
            $stmt_log->execute([
                ':bilik_id'    => $log['bilik_id'],
                ':nim'         => $log['nim'],
                ':nama'        => $log['nama'],
                ':device_id'   => $log['device_id'],
                ':fp'          => $log['fp'],
                ':sess'        => $log['sess'],
                ':ip'          => $log['ip'],
                ':ua'          => $log['ua'],
                ':status_log'  => $log['status_log'],
                ':kode_event'  => $log['kode_event'],
                ':judul_event' => $log['judul_event'],
                ':keterangan'  => $log['keterangan'],
                ':created_at'  => $log['created_at'],
            ]);
        }

        // Update total suara bilik sesuai contoh vote
        $pdo->exec('UPDATE bilik_suaras SET total_suara_bilik = 1 WHERE nomor_bilik IN (1, 2, 3)');
    }
}

