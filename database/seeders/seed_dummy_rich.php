<?php
/**
 * Seeder Reset & Pengisian Data Dummy Lengkap (Skala Besar)
 * Mengosongkan seluruh tabel terlebih dahulu, lalu mengisi data dummy realistis
 * untuk seluruh modul: users, pengaturan, jurusans, kelas, mahasiswas (160 DPT),
 * kandidats (4 BEM + 4 BPM dengan foto portrait), tim_sukses, bilik_suaras,
 * device_sessions, dan audit_logs.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/models/jurusan_model.php';

/**
 * Mengosongkan semua tabel dan mengisi ulang data dummy lengkap secara konsisten
 */
function run_rich_dummy_seeder(PDO $pdo): array
{
    ensure_jurusans_ready($pdo);

    // 1. Kosongkan seluruh tabel secara aman (TRUNCATE melakukan implicit commit, dijalankan di luar transaksi)
    $tablesToTruncate = [
        'audit_logs',
        'device_sessions',
        'bilik_suaras',
        'tim_sukses',
        'kandidats',
        'mahasiswas',
        'kelas',
        'jurusans',
        'pengaturan_sistem',
        'users',
    ];

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tablesToTruncate as $table) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Bersihkan foto non-dummy yang tersisa agar uploads tetap rapi
    $uploadFotoDir = BASE_PATH . '/uploads/foto_kandidat';
    if (is_dir($uploadFotoDir)) {
        foreach (glob($uploadFotoDir . '/*') ?: [] as $filePath) {
            $baseName = basename($filePath);
            if ($baseName === '.gitkeep' || str_starts_with($baseName, 'dummy_')) {
                continue;
            }
            @unlink($filePath);
        }
    }

    $pdo->beginTransaction();
    try {
        $now = time();

        // 2. Akun Admin & Panitia
        $stmtUser = $pdo->prepare('
            INSERT INTO users (username, nama_lengkap, password_hash, role)
            VALUES (:username, :nama_lengkap, :password_hash, :role)
        ');
        foreach ([
            ['admin', 'Administrator KPU PEMIRA', 'admin123', 'admin'],
            ['panitia', 'Panitia Pengawas TPS 01', 'panitia123', 'panitia'],
            ['panitia2', 'Panitia Verifikasi DPT', 'panitia123', 'panitia'],
        ] as [$uname, $fullName, $plainPass, $role]) {
            $stmtUser->execute([
                ':username'      => $uname,
                ':nama_lengkap'  => $fullName,
                ':password_hash' => password_hash($plainPass, PASSWORD_BCRYPT),
                ':role'          => $role,
            ]);
        }

        // 3. Pengaturan Sistem Global
        $stmtSetting = $pdo->prepare('
            INSERT INTO pengaturan_sistem (kunci, nilai, keterangan)
            VALUES (:kunci, :nilai, :keterangan)
        ');
        foreach ([
            ['status_portal_global', 'BUKA', 'Status global portal pemungutan suara (BUKA / TUTUP)'],
            ['wajib_checkin_panitia', '0', '1 = Wajib diaktifkan panitia sebelum masuk bilik, 0 = Langsung aktif pakai NIM'],
            ['nama_institusi', 'Komisi Pemilihan Umum Raya Mahasiswa (KPURM)', 'Nama penyelenggara PEMIRA pada portal dan laporan'],
            ['periode_pemira', '2026 / 2027', 'Tahun periode pemilihan'],
        ] as [$kunci, $nilai, $ket]) {
            $stmtSetting->execute([
                ':kunci'      => $kunci,
                ':nilai'      => $nilai,
                ':keterangan' => $ket,
            ]);
        }

        // 4. Master Jurusan (8 Program Studi)
        $jurusanCatalog = [
            ['kode' => 'TI',  'nama' => 'Teknik Informatika',      'jenjang' => 'S1'],
            ['kode' => 'SI',  'nama' => 'Sistem Informasi',        'jenjang' => 'S1'],
            ['kode' => 'MI',  'nama' => 'Manajemen Informatika',   'jenjang' => 'D3'],
            ['kode' => 'RPL', 'nama' => 'Rekayasa Perangkat Lunak','jenjang' => 'S1'],
            ['kode' => 'DKV', 'nama' => 'Desain Komunikasi Visual','jenjang' => 'S1'],
            ['kode' => 'BD',  'nama' => 'Bisnis Digital',          'jenjang' => 'S1'],
            ['kode' => 'KA',  'nama' => 'Komputerisasi Akuntansi', 'jenjang' => 'D3'],
            ['kode' => 'TK',  'nama' => 'Teknik Komputer',         'jenjang' => 'S1'],
        ];
        $stmtJur = $pdo->prepare('
            INSERT INTO jurusans (kode_jurusan, nama_jurusan, jenjang)
            VALUES (:kode, :nama, :jenjang)
        ');
        $jurusanIdByKode = [];
        $jurusanIdByNama = [];
        foreach ($jurusanCatalog as $j) {
            $stmtJur->execute([
                ':kode'    => $j['kode'],
                ':nama'    => $j['nama'],
                ':jenjang' => $j['jenjang'],
            ]);
            $jid = (int) $pdo->lastInsertId();
            $jurusanIdByKode[$j['kode']] = $jid;
            $jurusanIdByNama[$j['nama']] = $jid;
        }

        // 5. Kelas (16 Kelas tersebar di 8 Jurusan) + kuota status mahasiswa [sudah_vote, checkin_belum_vote, belum_checkin]
        $kelasCatalog = [
            ['nama' => 'TI-1A',  'kode' => 'TI',  'jurusan' => 'Teknik Informatika',       'sem' => 1, 'prefix' => '250101', 'dist' => [7, 2, 1]],
            ['nama' => 'TI-3A',  'kode' => 'TI',  'jurusan' => 'Teknik Informatika',       'sem' => 3, 'prefix' => '240101', 'dist' => [8, 2, 0]],
            ['nama' => 'TI-5B',  'kode' => 'TI',  'jurusan' => 'Teknik Informatika',       'sem' => 5, 'prefix' => '230102', 'dist' => [6, 3, 1]],
            ['nama' => 'SI-1A',  'kode' => 'SI',  'jurusan' => 'Sistem Informasi',         'sem' => 1, 'prefix' => '250201', 'dist' => [6, 3, 1]],
            ['nama' => 'SI-3A',  'kode' => 'SI',  'jurusan' => 'Sistem Informasi',         'sem' => 3, 'prefix' => '240201', 'dist' => [7, 2, 1]],
            ['nama' => 'SI-5A',  'kode' => 'SI',  'jurusan' => 'Sistem Informasi',         'sem' => 5, 'prefix' => '230201', 'dist' => [5, 4, 1]],
            ['nama' => 'MI-1A',  'kode' => 'MI',  'jurusan' => 'Manajemen Informatika',    'sem' => 1, 'prefix' => '250301', 'dist' => [5, 3, 2]],
            ['nama' => 'MI-3A',  'kode' => 'MI',  'jurusan' => 'Manajemen Informatika',    'sem' => 3, 'prefix' => '240301', 'dist' => [6, 3, 1]],
            ['nama' => 'RPL-3A', 'kode' => 'RPL', 'jurusan' => 'Rekayasa Perangkat Lunak', 'sem' => 3, 'prefix' => '240401', 'dist' => [7, 2, 1]],
            ['nama' => 'RPL-5A', 'kode' => 'RPL', 'jurusan' => 'Rekayasa Perangkat Lunak', 'sem' => 5, 'prefix' => '230401', 'dist' => [6, 3, 1]],
            ['nama' => 'DKV-3A', 'kode' => 'DKV', 'jurusan' => 'Desain Komunikasi Visual', 'sem' => 3, 'prefix' => '240501', 'dist' => [5, 3, 2]],
            ['nama' => 'DKV-5B', 'kode' => 'DKV', 'jurusan' => 'Desain Komunikasi Visual', 'sem' => 5, 'prefix' => '230502', 'dist' => [4, 4, 2]],
            ['nama' => 'BD-1A',  'kode' => 'BD',  'jurusan' => 'Bisnis Digital',           'sem' => 1, 'prefix' => '250601', 'dist' => [6, 3, 1]],
            ['nama' => 'BD-3B',  'kode' => 'BD',  'jurusan' => 'Bisnis Digital',           'sem' => 3, 'prefix' => '240602', 'dist' => [5, 3, 2]],
            ['nama' => 'KA-3A',  'kode' => 'KA',  'jurusan' => 'Komputerisasi Akuntansi',  'sem' => 3, 'prefix' => '240701', 'dist' => [4, 4, 2]],
            ['nama' => 'TK-5A',  'kode' => 'TK',  'jurusan' => 'Teknik Komputer',          'sem' => 5, 'prefix' => '230801', 'dist' => [5, 4, 1]],
        ];

        // 160 Nama Mahasiswa Unik (16 kelas x 10 mahasiswa)
        $studentNames = [
            // TI-1A (0..9)
            'Aditya Pratama Putra', 'Bunga Citra Lestari', 'Cahyo Nugroho', 'Dinda Kirana Putri', 'Eko Prasetyo Wibowo',
            'Fajar Siddiq Alamsyah', 'Gita Savitri Devi', 'Hendra Setiawan', 'Indah Permatasari', 'Joko Anwar Saputra',
            // TI-3A (10..19)
            'Khalid Ramadhan Hakim', 'Larasati Wulandari', 'Muhammad Ilham Akbar', 'Nadia Kusuma Wardani', 'Oki Setiawan Putra',
            'Putri Ayu Lestari', 'Qoriatul Hasanah', 'Rafi Ahmad Fauzan', 'Salsa Bila Khairunnisa', 'Taufik Hidayatulloh',
            // TI-5B (20..29)
            'Umar Abdul Aziz', 'Vina Panduwinata', 'Wahyu Tri Utomo', 'Xaverius Danuarta', 'Yulia Rahmawati',
            'Zaki Mubarak Pratama', 'Aldi Taher Wijaya', 'Bella Shofie Anggraini', 'Chandra Wijaya Kusuma', 'Dewi Persik Kartika',
            // SI-1A (30..39)
            'Erlangga Setya Budi', 'Fitriani Nur Azizah', 'Gilang Dirga Pratama', 'Hana Pertiwi Putri', 'Irfan Hakim Syahputra',
            'Jasmine Aulia Zahra', 'Kurniawan Dwi Yulianto', 'Lina Marlina Sari', 'Miftahul Huda Ramadhan', 'Novianti Dwi Lestari',
            // SI-3A (40..49)
            'Oscar Lawalata Putra', 'Prilly Latuconsina', 'Qomaruddin Al-Fajar', 'Rangga Azof Saputra', 'Shinta Bachir Dewi',
            'Tegar Septian Nugraha', 'Ulfa Dwiyanti Rahma', 'Vicky Shu Maharani', 'Wildan Alamsyah Putra', 'Yuni Shara Melati',
            // SI-5A (50..59)
            'Zulfikar Ali Bhutto', 'Arif Budiman Santoso', 'Bima Arya Sugiarto', 'Citra Scholastika Dewi', 'Dimas Anggara Putra',
            'Elsa Safira Permata', 'Farel Prayoga Nugroho', 'Gisel Anastasia Putri', 'Haris Vriza Mahendra', 'Intan Nuraini Sari',
            // MI-1A (60..69)
            'Jefri Nichol Prasetyo', 'Keisya Levronka Putri', 'Lukman Sardi Wibowo', 'Mawar Eva De Jongh', 'Naufal Samudra Putra',
            'Olivia Jensen Kartika', 'Pevita Pearce Maharani', 'Raffi Ahmad Hidayat', 'Syifa Hadju Permata', 'Tarra Budiman Jaya',
            // MI-3A (70..79)
            'Ussy Sulistiawaty', 'Verrell Bramasta Putra', 'Wulan Guritno Sari', 'Yuki Kato Anggraini', 'Zidny Lathifa Zahra',
            'Abimanyu Surya Pratama', 'Bastian Steel Saputra', 'Cut Meyriska Putri', 'Darius Sinathrya', 'Ersa Mayori Kartika',
            // RPL-3A (80..89)
            'Febby Rastanty Dewi', 'Gading Marten Wijaya', 'Haykal Kamil Putra', 'Irish Bella Pratiwi', 'Joshua Suherman',
            'Kiki Amalia Rahma', 'Lesti Andryani Putri', 'Marcell Siahaan Putra', 'Nabila Syakieb Sari', 'Omesh Ananda Putra',
            // RPL-5A (90..99)
            'Pasha Ungu Ramadhan', 'Raisa Andriana Kusuma', 'Salman Al-Farizi', 'Tissa Biani Azzahra', 'Ucup Klaten Pratama',
            'Vidi Aldiano Saputra', 'Wendi Cagur Santoso', 'Yayan Ruhian Wijaya', 'Zaskia Adya Mecca', 'Alvin Faiz Ramadhan',
            // DKV-3A (100..109)
            'Beby Tsabina Putri', 'Cinta Laura Kiehl', 'Desta Mahendra Putra', 'Enzy Storia Leovarisa', 'Fero Walandouw',
            'Gracia Indri Kartika', 'Hesti Purwadinata', 'Iqbaal Ramadhan Putra', 'Jessica Mila Agnesia', 'Kevin Julio Santoso',
            // DKV-5B (110..119)
            'Luna Maya Sugeng', 'Mikha Tambayong Putri', 'Nadine Chandrawinata', 'Onadio Leonardo Putra', 'Pamela Bowie Sari',
            'Raditya Dika Angkasa', 'Sandra Dewi Kartika', 'Tora Sudiro Wibowo', 'Udin Sedunia Pratama', 'Valerie Thomas Putri',
            // BD-1A (120..129)
            'Widy Vierratale Dewi', 'Yoris Sebastian Putra', 'Zara Adhisty Sundari', 'Adhisty Kyla Putri', 'Bryan Domani Saputra',
            'Caitlin Halderman Sari', 'Devano Danendra Putra', 'Elina Joerg Pratiwi', 'Fajar Alfian Pratama', 'Gloria Emanuelle Widjaja',
            // BD-3B (130..139)
            'Hendy Setiono Putra', 'Iko Uwais Qorni', 'Joko Kendil Nugroho', 'Kaesang Pangarep Putra', 'Lala Karmela Dewi',
            'Morgan Oey Santoso', 'Naysila Mirdad Putri', 'Oka Antara Wijaya', 'Putra Siregar Pratama', 'Raline Shah Rahma',
            // KA-3A (140..149)
            'Surya Saputra Jaya', 'Tamara Bleszynski', 'Uya Kuya Pratama', 'Velove Vexia Putri', 'Wulandari Handayani',
            'Yosep Anggi Noen', 'Zumi Zola Zulkifli', 'Agnes Monica Muljoto', 'Bambang Pamungkas', 'Chicco Jerikho Jarumillind',
            // TK-5A (150..159)
            'Dian Sastrowardoyo', 'Evan Dimas Darmono', 'Fedi Nuril Pratama', 'Ghea Indrawari Putri', 'Hamish Daud Wyllie',
            'Isyana Sarasvati Dewi', 'Joe Taslim Santoso', 'Kimberly Ryder Putri', 'Laura Basuki Kartika', 'Maudy Ayunda Faza',
        ];

        $stmtKelas = $pdo->prepare('
            INSERT INTO kelas (jurusan_id, nama_kelas, jurusan, semester)
            VALUES (:jurusan_id, :nama_kelas, :jurusan, :semester)
        ');
        $stmtMhs = $pdo->prepare('
            INSERT INTO mahasiswas (kelas_id, nim, nama_lengkap, status_hadir, sudah_memilih, waktu_memilih)
            VALUES (:kelas_id, :nim, :nama_lengkap, :status_hadir, :sudah_memilih, :waktu_memilih)
        ');

        $votedStudents = [];
        $readyStudents = [];
        $uncheckedStudents = [];
        $nameIdx = 0;

        foreach ($kelasCatalog as $kl) {
            $stmtKelas->execute([
                ':jurusan_id' => $jurusanIdByKode[$kl['kode']],
                ':nama_kelas' => $kl['nama'],
                ':jurusan'    => $kl['jurusan'],
                ':semester'   => $kl['sem'],
            ]);
            $kelasId = (int) $pdo->lastInsertId();

            [$cntVoted, $cntReady, $cntUnchecked] = $kl['dist'];
            for ($slot = 1; $slot <= 10; $slot++) {
                $nim = $kl['prefix'] . str_pad((string) $slot, 3, '0', STR_PAD_LEFT);
                $nama = $studentNames[$nameIdx++];

                if ($slot <= $cntVoted) {
                    $statusHadir = 1;
                    $sudahMemilih = 1;
                    // Rentang waktu voting bertahap dalam 3.5 jam terakhir
                    $offsetSec = 12600 - (count($votedStudents) * 130) + (($slot % 3) * 19);
                    $waktuMemilih = date('Y-m-d H:i:s', $now - max(45, $offsetSec));
                } elseif ($slot <= ($cntVoted + $cntReady)) {
                    $statusHadir = 1;
                    $sudahMemilih = 0;
                    $waktuMemilih = null;
                } else {
                    $statusHadir = 0;
                    $sudahMemilih = 0;
                    $waktuMemilih = null;
                }

                $stmtMhs->execute([
                    ':kelas_id'      => $kelasId,
                    ':nim'           => $nim,
                    ':nama_lengkap'  => $nama,
                    ':status_hadir'  => $statusHadir,
                    ':sudah_memilih' => $sudahMemilih,
                    ':waktu_memilih' => $waktuMemilih,
                ]);

                $mhsInfo = [
                    'id'           => (int) $pdo->lastInsertId(),
                    'nim'          => $nim,
                    'nama'         => $nama,
                    'kelas'        => $kl['nama'],
                    'waktu'        => $waktuMemilih,
                ];
                if ($sudahMemilih === 1) {
                    $votedStudents[] = $mhsInfo;
                } elseif ($statusHadir === 1) {
                    $readyStudents[] = $mhsInfo;
                } else {
                    $uncheckedStudents[] = $mhsInfo;
                }
            }
        }

        // 6. Kandidat BEM (4 Calon, total suara = 92) & BPM (4 Calon, total suara = 92) + Tim Sukses (36 anggota)
        $kandidatsCatalog = [
            // BEM
            [
                'kategori'      => 'BEM',
                'jurusan_nama'  => 'Teknik Informatika',
                'nomor_urut'    => 1,
                'nama_calon'    => 'Rizky Ramadhan',
                'jurusan_kelas' => 'Teknik Informatika · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bem_01.jpg',
                'visi'          => 'Mewujudkan BEM Kampus yang inklusif, progresif, dan berdampak nyata bagi akselerasi prestasi akademik, riset teknologi, serta kesejahteraan mahasiswa.',
                'misi'          => "1. Mengoptimalkan advokasi kebijakan UKT, beasiswa, dan fasilitas kuliah yang berpihak pada mahasiswa.\n2. Membangun ekosistem inkubasi riset, lomba PKM, dan sertifikasi industri berbasis kolaborasi lintas prodi.\n3. Menyelenggarakan portal transparansi anggaran serta laporan realisasi program kerja secara real-time.\n4. Memperluas jejaring kemitraan magang bersertifikat bersama mitra startup dan korporasi nasional.",
                'jumlah_suara'  => 34,
                'timses'        => [
                    ['nama' => 'Dimas Anggara',   'nim' => '230101011', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Siti Nurhaliza',  'nim' => '230201015', 'jabatan' => 'Wakil Ketua Tim Sukses'],
                    ['nama' => 'Bagas Adi',       'nim' => '240101022', 'jabatan' => 'Koordinator Humas & Kampanye'],
                    ['nama' => 'Rani Kusuma',     'nim' => '240101031', 'jabatan' => 'Koordinator Saksi TPS'],
                    ['nama' => 'Fikri Haikal',    'nim' => '250101042', 'jabatan' => 'Divisi Media Kreatif'],
                ],
            ],
            [
                'kategori'      => 'BEM',
                'jurusan_nama'  => 'Sistem Informasi',
                'nomor_urut'    => 2,
                'nama_calon'    => 'Farhan Maulana Putra',
                'jurusan_kelas' => 'Sistem Informasi · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bem_02.jpg',
                'visi'          => 'Transformasi tata kelola BEM modern berbasis sistem digital terintegrasi guna memperkuat sinergi UKM, himpunan jurusan, dan pengembangan karier mahasiswa.',
                'misi'          => "1. Menghadirkan satu pintu layanan aspirasi dan pendampingan administrasi UKM serta Himpunan Jurusan.\n2. Menggelar bursa karier, mentoring portofolio, dan job fair kampus dua kali setiap semester.\n3. Mendigitalisasi peminjaman ruang sekretariat serta pemantauan dana kegiatan kemahasiswaan.\n4. Mendorong program pengabdian masyarakat berbasis inovasi digital di desa mitra.",
                'jumlah_suara'  => 27,
                'timses'        => [
                    ['nama' => 'Reza Rahadian',   'nim' => '230201019', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Putri Marino',    'nim' => '240201030', 'jabatan' => 'Koordinator Media & Publikasi'],
                    ['nama' => 'Danang Suryo',    'nim' => '240201045', 'jabatan' => 'Koordinator Lapangan'],
                    ['nama' => 'Tiara Andini',    'nim' => '250201014', 'jabatan' => 'Tim Kajian & Strategi'],
                    ['nama' => 'Yoga Pratama',    'nim' => '230201077', 'jabatan' => 'Koordinator Saksi Bilik'],
                ],
            ],
            [
                'kategori'      => 'BEM',
                'jurusan_nama'  => 'Bisnis Digital',
                'nomor_urut'    => 3,
                'nama_calon'    => 'Bagaskara Wicaksono',
                'jurusan_kelas' => 'Bisnis Digital · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bem_03.jpg',
                'visi'          => 'Menjadikan BEM sebagai pusat akselerasi kewirausahaan muda kampus, ekonomi kreatif mahasiswa, dan pemberdayaan komunitas lintas angkatan.',
                'misi'          => "1. Meluncurkan program modal rintisan mikro dan bazar rutin bagi wirausaha mahasiswa setiap bulan.\n2. Menjalin kolaborasi intensif dengan inkubator bisnis daerah dan alumni sukses lintas jurusan.\n3. Mengadakan festival inovasi, seni, olahraga, dan e-sports tahunan bertaraf regional.\n4. Membuka forum diskusi terbuka pimpinan mahasiswa dan birokrasi kampus secara rutin.",
                'jumlah_suara'  => 19,
                'timses'        => [
                    ['nama' => 'Naufal Azhar',    'nim' => '240602011', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Tasya Kamila',    'nim' => '240602018', 'jabatan' => 'Koordinator Ekonomi Kreatif'],
                    ['nama' => 'Ilham Bintang',   'nim' => '250601009', 'jabatan' => 'Koordinator Logistik'],
                    ['nama' => 'Sabrina Putri',   'nim' => '240501015', 'jabatan' => 'Tim Konten Kampanye'],
                ],
            ],
            [
                'kategori'      => 'BEM',
                'jurusan_nama'  => 'Rekayasa Perangkat Lunak',
                'nomor_urut'    => 4,
                'nama_calon'    => 'Andika Pratama Wijaya',
                'jurusan_kelas' => 'Rekayasa Perangkat Lunak · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bem_04.jpg',
                'visi'          => 'Membangun BEM kolaboratif yang berfokus pada pengembangan riset terapan, hak kesehatan mental mahasiswa, dan keterbukaan informasi kampus.',
                'misi'          => "1. Menyediakan layanan konseling sebaya dan pusat informasi kesejahteraan psikologis mahasiswa.\n2. Memperjuangkan akses laboratorium 24 jam dan peningkatan bandwidth internet di seluruh gedung kuliah.\n3. Menginisiasi hackathon terbuka kampus guna menghasilkan aplikasi utilitas bagi sivitas akademika.\n4. Menerapkan audit kinerja bulanan yang dapat diakses secara publik oleh seluruh mahasiswa.",
                'jumlah_suara'  => 12,
                'timses'        => [
                    ['nama' => 'Rizaldo Putra',   'nim' => '230401005', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Ayu Tingting',    'nim' => '240401012', 'jabatan' => 'Koordinator Relawan Kelas'],
                    ['nama' => 'Dicky Chandra',   'nim' => '230401021', 'jabatan' => 'Analis Isu Kampus'],
                    ['nama' => 'Mega Utami',      'nim' => '240401033', 'jabatan' => 'Saksi & Dokumentasi'],
                ],
            ],
            // BPM
            [
                'kategori'      => 'BPM',
                'jurusan_nama'  => 'Teknik Informatika',
                'nomor_urut'    => 1,
                'nama_calon'    => 'Mahendra Kusuma Wijaya',
                'jurusan_kelas' => 'Teknik Informatika · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bpm_01.jpg',
                'visi'          => 'Mewujudkan Badan Perwakilan Mahasiswa (BPM) yang berintegritas, objektif dalam mengawal anggaran, dan konsisten memperjuangkan regulasi berpihak pada mahasiswa.',
                'misi'          => "1. Menjalankan pengawasan berkala terhadap realisasi proker dan penyerapan anggaran BEM serta UKM.\n2. Memperbarui AD/ART dan aturan tata tertib keluarga mahasiswa secara partisipatif.\n3. Mengadakan reses dengar pendapat bersama perwakilan tiap kelas sekurang-kurangnya satu kali per bulan.\n4. Mempublikasikan ringkasan rapat paripurna secara terbuka melalui papan pengumuman digital.",
                'jumlah_suara'  => 29,
                'timses'        => [
                    ['nama' => 'Arif Muhammad',   'nim' => '230101040', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Lesti Kejora',    'nim' => '240101051', 'jabatan' => 'Koordinator Lapangan'],
                    ['nama' => 'Hendro Siswanto', 'nim' => '230102033', 'jabatan' => 'Divisi Hukum & Konstitusi'],
                    ['nama' => 'Dina Lorenza',    'nim' => '240101062', 'jabatan' => 'Humas Parlemen'],
                    ['nama' => 'Budi Santoso',    'nim' => '250101070', 'jabatan' => 'Koordinator Saksi TPS'],
                ],
            ],
            [
                'kategori'      => 'BPM',
                'jurusan_nama'  => 'Sistem Informasi',
                'nomor_urut'    => 2,
                'nama_calon'    => 'Clarissa Devina Putri',
                'jurusan_kelas' => 'Sistem Informasi · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bpm_02.jpg',
                'visi'          => 'Menghadirkan parlemen mahasiswa yang modern, transparan, dan tanggap mengawal aspirasi seluruh program studi hingga ke level pimpinan rektorat.',
                'misi'          => "1. Merancang dashboard pelacakan aspirasi mahasiswa agar setiap aduan memiliki status tindak lanjut.\n2. Memperkuat komisi pengawasan dana kemahasiswaan berbasis audit transparan dan akuntabel.\n3. Mendorong harmonisasi aturan lintas himpunan agar jadwal kegiatan tidak saling berbenturan.\n4. Memberikan pembekalan legal drafting bagi seluruh anggota legislatif mahasiswa.",
                'jumlah_suara'  => 31,
                'timses'        => [
                    ['nama' => 'Kevin Sanjaya',   'nim' => '230201088', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Maudy Ayunda',    'nim' => '230201090', 'jabatan' => 'Koordinator Strategi'],
                    ['nama' => 'Citra Kirana',    'nim' => '240201055', 'jabatan' => 'Koordinator Aspirasi Prodi'],
                    ['nama' => 'Andre Taulany',   'nim' => '240201067', 'jabatan' => 'Tim Publikasi'],
                    ['nama' => 'Nia Ramadhani',   'nim' => '250201081', 'jabatan' => 'Koordinator Saksi'],
                ],
            ],
            [
                'kategori'      => 'BPM',
                'jurusan_nama'  => 'Manajemen Informatika',
                'nomor_urut'    => 3,
                'nama_calon'    => 'Hafizuddin Al-Faruq',
                'jurusan_kelas' => 'Manajemen Informatika · Semester 3',
                'foto_path'     => 'foto_kandidat/dummy_bpm_03.jpg',
                'visi'          => 'Memperkuat representasi mahasiswa diploma dan sarjana secara setara dalam merumuskan kebijakan kemahasiswaan yang adil dan solutif.',
                'misi'          => "1. Membuka desk advokasi khusus terkait permasalahan praktikum, sertifikasi, dan tugas akhir.\n2. Memastikan alokasi pendanaan UKM dan himpunan jurusan terdistribusi proporsional.\n3. Menggelar dialog legislatif triwulanan di selasar kampus agar mudah dihadiri mahasiswa.\n4. Menerapkan mekanisme evaluasi KPI program kerja eksekutif di pertengahan periode.",
                'jumlah_suara'  => 18,
                'timses'        => [
                    ['nama' => 'Fadli Zonny',     'nim' => '240301019', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Salma Salsabil',  'nim' => '250301025', 'jabatan' => 'Koordinator Vokasi'],
                    ['nama' => 'Ridwan Kamil',    'nim' => '240301044', 'jabatan' => 'Logistik Kampanye'],
                    ['nama' => 'Susi Pudji',      'nim' => '240701012', 'jabatan' => 'Saksi Pemilihan'],
                ],
            ],
            [
                'kategori'      => 'BPM',
                'jurusan_nama'  => 'Desain Komunikasi Visual',
                'nomor_urut'    => 4,
                'nama_calon'    => 'Nadira Safitri Lestari',
                'jurusan_kelas' => 'Desain Komunikasi Visual · Semester 5',
                'foto_path'     => 'foto_kandidat/dummy_bpm_04.jpg',
                'visi'          => 'Parlemen mahasiswa yang komunikatif, kreatif dalam menyosialisasikan kebijakan kampus, serta kritis menjaga independensi fungsi legislasi.',
                'misi'          => "1. Menyajikan rangkuman peraturan kemahasiswaan dalam format infografis ringkas yang mudah dipahami.\n2. Memperjuangkan ruang ekspresi seni, pameran karya, dan fasilitas kreatif mahasiswa lintas jurusan.\n3. Mengawasi keterbukaan seleksi delegasi mahasiswa untuk ajang kompetisi nasional.\n4. Membangun kultur parlemen yang ramah, bebas diskriminasi, dan responsif.",
                'jumlah_suara'  => 14,
                'timses'        => [
                    ['nama' => 'Radit Pratama',   'nim' => '230502014', 'jabatan' => 'Ketua Tim Sukses'],
                    ['nama' => 'Kezia Karamoy',   'nim' => '240501028', 'jabatan' => 'Desainer Kampanye'],
                    ['nama' => 'Aldo Rasyid',     'nim' => '240501039', 'jabatan' => 'Koordinator Relawan'],
                    ['nama' => 'Wulan Sari',      'nim' => '230801016', 'jabatan' => 'Saksi & Monitoring'],
                ],
            ],
        ];

        $stmtKandidat = $pdo->prepare('
            INSERT INTO kandidats (kategori, jurusan_id, nomor_urut, nama_calon, nama_wakil, jurusan_kelas, foto_path, visi, misi, jumlah_suara)
            VALUES (:kategori, :jurusan_id, :nomor_urut, :nama_calon, :nama_wakil, :jurusan_kelas, :foto_path, :visi, :misi, :jumlah_suara)
        ');
        $stmtTimses = $pdo->prepare('
            INSERT INTO tim_sukses (kandidat_id, nama_anggota, nim_anggota, jabatan_timses)
            VALUES (:kandidat_id, :nama_anggota, :nim_anggota, :jabatan_timses)
        ');

        $totalTimsesInserted = 0;
        foreach ($kandidatsCatalog as $kd) {
            $stmtKandidat->execute([
                ':kategori'      => $kd['kategori'],
                ':jurusan_id'    => $jurusanIdByNama[$kd['jurusan_nama']] ?? null,
                ':nomor_urut'    => $kd['nomor_urut'],
                ':nama_calon'    => $kd['nama_calon'],
                ':nama_wakil'    => null,
                ':jurusan_kelas' => $kd['jurusan_kelas'],
                ':foto_path'     => $kd['foto_path'],
                ':visi'          => $kd['visi'],
                ':misi'          => $kd['misi'],
                ':jumlah_suara'  => $kd['jumlah_suara'],
            ]);
            $kandidatId = (int) $pdo->lastInsertId();

            foreach ($kd['timses'] as $ts) {
                $stmtTimses->execute([
                    ':kandidat_id'    => $kandidatId,
                    ':nama_anggota'   => $ts['nama'],
                    ':nim_anggota'    => $ts['nim'],
                    ':jabatan_timses' => $ts['jabatan'],
                ]);
                $totalTimsesInserted++;
            }
        }

        // 7. Bilik Suara (6 Bilik: total_suara_bilik = 20+18+16+15+14+9 = 92)
        $bilikCatalog = [
            ['nomor' => 1, 'nama' => 'Bilik Suara 01', 'status' => 'IDLE',   'suara' => 20, 'last_offset' => 140],
            ['nomor' => 2, 'nama' => 'Bilik Suara 02', 'status' => 'IDLE',   'suara' => 18, 'last_offset' => 210],
            ['nomor' => 3, 'nama' => 'Bilik Suara 03', 'status' => 'IDLE',   'suara' => 16, 'last_offset' => 330],
            ['nomor' => 4, 'nama' => 'Bilik Suara 04', 'status' => 'IDLE',   'suara' => 15, 'last_offset' => 410],
            ['nomor' => 5, 'nama' => 'Bilik Suara 05', 'status' => 'IDLE',   'suara' => 14, 'last_offset' => 520],
            ['nomor' => 6, 'nama' => 'Bilik Suara 06', 'status' => 'CLOSED', 'suara' => 9,  'last_offset' => 1850],
        ];

        $stmtBilik = $pdo->prepare('
            INSERT INTO bilik_suaras (nomor_bilik, nama_bilik, lokasi_bilik, status_bilik, current_token, token_generated_at, last_vote_at, total_suara_bilik)
            VALUES (:nomor_bilik, :nama_bilik, :lokasi_bilik, :status_bilik, :current_token, :token_gen, :last_vote_at, :total_suara_bilik)
        ');

        $bilikIds = [];
        $bilikQuotaMap = [];
        foreach ($bilikCatalog as $b) {
            $stmtBilik->execute([
                ':nomor_bilik'       => $b['nomor'],
                ':nama_bilik'        => $b['nama'],
                ':lokasi_bilik'      => 'Aula Utama Kampus',
                ':status_bilik'      => $b['status'],
                ':current_token'     => bin2hex(random_bytes(24)),
                ':token_gen'         => date('Y-m-d H:i:s', $now - 600),
                ':last_vote_at'      => date('Y-m-d H:i:s', $now - $b['last_offset']),
                ':total_suara_bilik' => $b['suara'],
            ]);
            $bid = (int) $pdo->lastInsertId();
            $bilikIds[] = $bid;
            $bilikQuotaMap[] = ['id' => $bid, 'nomor' => $b['nomor'], 'quota' => $b['suara'], 'used' => 0];
        }

        // 8. Device Sessions (92 sesi sah) + Audit Logs (92 NORMAL + 14 FRAUD = 106 logs)
        $userAgents = [
            'Mozilla/5.0 (Linux; Android 14; Samsung SM-S928B) AppleWebKit/537.36 Chrome/129.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 13; Xiaomi 23078PND5G) AppleWebKit/537.36 Chrome/128.0 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 14; OPPO CPH2581) AppleWebKit/537.36 Chrome/127.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
        ];

        $stmtSession = $pdo->prepare('
            INSERT INTO device_sessions (device_id, browser_session_id, fingerprint_hash, ip_address, user_agent, bilik_id, nim_terkait, sudah_digunakan_vote, jumlah_percobaan_nim, created_at)
            VALUES (:device_id, :sess, :fp, :ip, :ua, :bilik_id, :nim, 1, 1, :created_at)
        ');
        $stmtLog = $pdo->prepare('
            INSERT INTO audit_logs (bilik_id, nim, nama_mahasiswa_cache, device_id, fingerprint_hash, browser_session_id, ip_address, user_agent, status_log, kode_event, judul_event, keterangan, created_at)
            VALUES (:bilik_id, :nim, :nama, :device_id, :fp, :sess, :ip, :ua, :status_log, :kode_event, :judul_event, :keterangan, :created_at)
        ');

        $bilikPointer = 0;
        $deviceRecords = [];
        foreach ($votedStudents as $idx => $stu) {
            while ($bilikPointer < count($bilikQuotaMap) - 1 && $bilikQuotaMap[$bilikPointer]['used'] >= $bilikQuotaMap[$bilikPointer]['quota']) {
                $bilikPointer++;
            }
            $assignedBilik = $bilikQuotaMap[$bilikPointer];
            $bilikQuotaMap[$bilikPointer]['used']++;

            $suffix = str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
            $deviceId = 'dev_' . substr(hash('sha256', 'device_' . $stu['nim']), 0, 12) . '_fp' . $suffix;
            $fpHash = 'fp_' . substr(hash('sha256', 'fp_' . $stu['nim']), 0, 16);
            $sessId = 'sess_' . substr(hash('sha256', 'sess_' . $stu['nim']), 0, 14);
            $ipAddr = '192.168.' . (($idx % 2) + 1) . '.' . (101 + ($idx % 120));
            $ua = $userAgents[$idx % count($userAgents)];

            $stmtSession->execute([
                ':device_id'  => $deviceId,
                ':sess'       => $sessId,
                ':fp'         => $fpHash,
                ':ip'         => $ipAddr,
                ':ua'         => $ua,
                ':bilik_id'   => $assignedBilik['id'],
                ':nim'        => $stu['nim'],
                ':created_at' => $stu['waktu'],
            ]);

            $stmtLog->execute([
                ':bilik_id'    => $assignedBilik['id'],
                ':nim'         => $stu['nim'],
                ':nama'        => $stu['nama'],
                ':device_id'   => $deviceId,
                ':fp'          => $fpHash,
                ':sess'        => $sessId,
                ':ip'          => $ipAddr,
                ':ua'          => $ua,
                ':status_log'  => 'NORMAL',
                ':kode_event'  => 'VOTE_SUCCESS',
                ':judul_event' => 'Pemungutan Suara Berhasil',
                ':keterangan'  => "Mahasiswa {$stu['nama']} ({$stu['kelas']}) berhasil memindai QR Bilik {$assignedBilik['nomor']}, memverifikasi NIM, dan mengirimkan suara BEM & BPM.",
                ':created_at'  => $stu['waktu'],
            ]);

            $deviceRecords[] = [
                'bilik_id'  => $assignedBilik['id'],
                'bilik_no'  => $assignedBilik['nomor'],
                'nim'       => $stu['nim'],
                'nama'      => $stu['nama'],
                'device_id' => $deviceId,
                'fp'        => $fpHash,
                'sess'      => $sessId,
                'ip'        => $ipAddr,
                'ua'        => $ua,
            ];
        }

        // 14 Fraud / Anomali Audit Logs realistis mencakup berbagai kode deteksi keamanan
        $fraudEvents = [
            [
                'idx'   => 0,
                'kode'  => 'DOUBLE_SCAN_SAME_DEVICE',
                'judul' => 'Percobaan Scan Ke-2 oleh Device yang Sama',
                'ket'   => 'Perangkat yang sebelumnya telah dipakai memilih mencoba memindai ulang QR Code di Bilik 1.',
                'off'   => 4800,
            ],
            [
                'idx'   => 4,
                'kode'  => 'NIM_ALREADY_VOTED',
                'judul' => 'Percobaan Menggunakan NIM yang Sudah Memilih',
                'ket'   => 'Perangkat baru memasukkan NIM pemilih yang statusnya sudah tercatat memilih pada basis data.',
                'off'   => 4200,
            ],
            [
                'idx'   => 12,
                'kode'  => 'EXPIRED_OR_USED_TOKEN_SCAN',
                'judul' => 'Scan QR Code Kadaluarsa / Bekas Foto Layar',
                'ket'   => 'Token QR Bilik 1 yang sudah dirotasi ulang dipindai kembali (indikasi foto QR lama). Akses ditolak.',
                'off'   => 3750,
            ],
            [
                'idx'   => 22,
                'kode'  => 'DOUBLE_SCAN_SAME_DEVICE',
                'judul' => 'Percobaan Scan Ke-2 oleh Device yang Sama',
                'ket'   => 'Device ID terdaftar mencoba membuka portal voting kedua kalinya pada Bilik 2.',
                'off'   => 3300,
            ],
            [
                'idx'   => 28,
                'kode'  => 'NIM_NOT_CHECKED_IN',
                'judul' => 'Verifikasi NIM Ditolak (Belum Check-In)',
                'ket'   => 'Mahasiswa belum melakukan verifikasi kehadiran/check-in di meja panitia sebelum masuk bilik.',
                'off'   => 2900,
                'mhs'   => $uncheckedStudents[0] ?? null,
            ],
            [
                'idx'   => 35,
                'kode'  => 'NIM_ALREADY_VOTED',
                'judul' => 'Percobaan Menggunakan NIM yang Sudah Memilih',
                'ket'   => 'Deteksi input ulang NIM dari perangkat berbeda pada Bilik 3 untuk NIM yang telah menyelesaikan voting.',
                'off'   => 2450,
            ],
            [
                'idx'   => 42,
                'kode'  => 'EXPIRED_OR_USED_TOKEN_SCAN',
                'judul' => 'Scan QR Code Kadaluarsa / Bekas Foto Layar',
                'ket'   => 'Pemindaian token QR lama yang telah berakhir pada Bilik 3 berhasil diblokir sistem.',
                'off'   => 2100,
            ],
            [
                'idx'   => 50,
                'kode'  => 'SESSION_HIJACK_MISMATCH',
                'judul' => 'Anomali Sesi Perangkat / Fingerprint Tidak Cocok',
                'ket'   => 'Cookie sesi tidak sesuai dengan fingerprint perangkat pengirim request di Bilik 4.',
                'off'   => 1700,
            ],
            [
                'idx'   => 58,
                'kode'  => 'DIRECT_URL_BYPASS',
                'judul' => 'Percobaan Akses Halaman Bilik Tanpa Token QR',
                'ket'   => 'Pengguna mencoba mengakses URL bilik pemilihan secara langsung tanpa melewati pemindaian QR sah.',
                'off'   => 1380,
                'no_nim' => true,
            ],
            [
                'idx'   => 66,
                'kode'  => 'NIM_BRUTEFORCE_ATTEMPT',
                'judul' => 'Percobaan Tebak NIM Berulang (Rate Limit)',
                'ket'   => 'Perangkat melakukan 5 percobaan input NIM tidak valid secara beruntun dalam satu sesi bilik.',
                'off'   => 1020,
                'no_nim' => true,
            ],
            [
                'idx'   => 72,
                'kode'  => 'NIM_NOT_CHECKED_IN',
                'judul' => 'Verifikasi NIM Ditolak (Belum Check-In)',
                'ket'   => 'Mahasiswa mencoba verifikasi NIM di Bilik 5 namun belum dilakukan check-in meja panitia.',
                'off'   => 780,
                'mhs'   => $uncheckedStudents[1] ?? null,
            ],
            [
                'idx'   => 79,
                'kode'  => 'DOUBLE_SCAN_SAME_DEVICE',
                'judul' => 'Percobaan Scan Ke-2 oleh Device yang Sama',
                'ket'   => 'Device yang sudah terpakai mencoba mengulang proses pemindaian QR pada Bilik 5.',
                'off'   => 540,
            ],
            [
                'idx'   => 84,
                'kode'  => 'NIM_ALREADY_VOTED',
                'judul' => 'Percobaan Menggunakan NIM yang Sudah Memilih',
                'ket'   => 'NIM tercatat sudah memberikan suara sebelumnya; percobaan pemilihan kedua dihentikan.',
                'off'   => 310,
            ],
            [
                'idx'   => 89,
                'kode'  => 'PAYLOAD_TAMPERING',
                'judul' => 'Deteksi Manipulasi Payload Pilihan Kandidat',
                'ket'   => 'ID kandidat atau token CSRF yang dikirim tidak sesuai dengan data sah kategori BEM/BPM.',
                'off'   => 120,
            ],
        ];

        foreach ($fraudEvents as $fIdx => $fe) {
            $ref = $deviceRecords[$fe['idx']] ?? $deviceRecords[0];
            $targetNim = !empty($fe['no_nim'])
                ? null
                : (!empty($fe['mhs']) ? $fe['mhs']['nim'] : $ref['nim']);
            $targetNama = !empty($fe['no_nim'])
                ? null
                : (!empty($fe['mhs']) ? $fe['mhs']['nama'] : $ref['nama']);

            $stmtLog->execute([
                ':bilik_id'    => $ref['bilik_id'],
                ':nim'         => $targetNim,
                ':nama'        => $targetNama,
                ':device_id'   => 'dev_fraud_' . substr(md5((string) $fIdx), 0, 10),
                ':fp'          => 'fp_fraud_' . substr(sha1((string) $fIdx), 0, 12),
                ':sess'        => 'sess_fraud_' . ($fIdx + 100),
                ':ip'          => '192.168.1.' . (200 + $fIdx),
                ':ua'          => $userAgents[($fIdx + 2) % count($userAgents)],
                ':status_log'  => 'FRAUD',
                ':kode_event'  => $fe['kode'],
                ':judul_event' => $fe['judul'],
                ':keterangan'  => $fe['ket'],
                ':created_at'  => date('Y-m-d H:i:s', $now - $fe['off']),
            ]);
        }

        $pdo->commit();

        return [
            'users'           => 3,
            'jurusans'        => count($jurusanCatalog),
            'kelas'           => count($kelasCatalog),
            'mahasiswas'      => count($studentNames),
            'sudah_memilih'   => count($votedStudents),
            'belum_memilih'   => count($readyStudents) + count($uncheckedStudents),
            'kandidats'       => count($kandidatsCatalog),
            'tim_sukses'      => $totalTimsesInserted,
            'bilik_suaras'    => count($bilikCatalog),
            'device_sessions' => count($deviceRecords),
            'audit_logs'      => count($votedStudents) + count($fraudEvents),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Jalankan langsung bila dieksekusi lewat CLI (`php database/seeders/seed_dummy_rich.php`)
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $pdo = get_db_connection();
    $stats = run_rich_dummy_seeder($pdo);
    echo "=== RESET & SEED DATA DUMMY LENGKAP BERHASIL ===\n";
    foreach ($stats as $table => $count) {
        echo sprintf(" - %-16s : %d\n", $table, $count);
    }
}

