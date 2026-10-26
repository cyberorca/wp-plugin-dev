<?php
/*
Plugin Name: Online Course Plugin
Description: Plugin untuk mengelola kursus online dengan validasi manual oleh admin.
Version: 1.5
Author: Your Name
*/

// Fungsi untuk memeriksa apakah sesi validasi telah dilakukan
function oc_is_session_validated($user_id, $session_number) {
    return get_user_meta($user_id, 'session_' . $session_number . '_validated', true) == '1';
}

// Fungsi untuk menyimpan status validasi sesi
function oc_save_session_validation($user_id, $session_number, $validated) {
    update_user_meta($user_id, 'session_' . $session_number . '_validated', $validated ? '1' : '0');
}

// Fungsi untuk menampilkan tabel user divalidasi dengan checkbox untuk setiap sesi
function oc_admin_validation_table() {
    // Hanya administrator dan editor yang bisa melihat tabel ini
    if (!current_user_can('administrator') && !current_user_can('editor')) {
        return '<p>Anda tidak memiliki akses untuk melihat halaman ini.</p>';
    }

    // Tangani form submit untuk validasi sesi
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['session_number'])) {
        $user_id = intval($_POST['user_id']);
        $session_number = intval($_POST['session_number']);
        $validated = isset($_POST['validated']) ? 1 : 0;
        oc_save_session_validation($user_id, $session_number, $validated);
    }

    // Ambil semua user
    $users = get_users();

    // Mulai output buffer
    ob_start();

    // Tampilkan tabel
    ?>
    <table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Sesi 1</th>
                <th>Sesi 2</th>
                <th>Sesi 3</th>
                <th>Sesi 4</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo esc_html($user->ID); ?></td>
                    <td><?php echo esc_html(get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true)); ?></td>
                    <td><?php echo esc_html($user->user_email); ?></td>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <td>
                            <form method="post">
                                <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                <input type="hidden" name="session_number" value="<?php echo $i; ?>">
                                <input type="checkbox" name="validated" value="1" <?php checked(oc_is_session_validated($user->ID, $i)); ?>>
                                <input type="submit" value="Update">
                            </form>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php

    // Return the output buffer content
    return ob_get_clean();
}

// Tambahkan shortcode [admin-validation] untuk tabel validasi
add_shortcode('admin-validation', 'oc_admin_validation_table');

// Fungsi untuk menampilkan input dan isian user pada setiap sesi
function oc_admin_user_input_check() {
    // Hanya administrator dan editor yang bisa melihat ini
    if (!current_user_can('administrator') && !current_user_can('editor')) {
        return '<p>Anda tidak memiliki akses untuk melihat halaman ini.</p>';
    }

    // Ambil semua user untuk ditampilkan
    $users = get_users();

    // Mulai output buffer
    ob_start();

    ?>
    <table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Sesi 1</th>
                <th>Sesi 2</th>
                <th>Sesi 3</th>
                <th>Sesi 4</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo esc_html($user->ID); ?></td>
                    <td><?php echo esc_html(get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true)); ?></td>
                    <td><?php echo esc_html($user->user_email); ?></td>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <td>
                            <?php if (oc_is_session_validated($user->ID, $i)): ?>
                                <h4>Data Sesi <?php echo $i; ?></h4>
                                <?php if ($i == 1): ?>
                                    <p>Visi Misi Usaha: <?php echo esc_html(get_user_meta($user->ID, 'visi_misi_usaha', true)); ?></p>
                                    <p>Rencana Pengembangan Usaha: <?php echo esc_html(get_user_meta($user->ID, 'rencana_pengembangan_usaha', true)); ?></p>
                                    <p>Surat Pernyataan: <a href="<?php echo esc_url(get_user_meta($user->ID, 'surat_pernyataan', true)); ?>" target="_blank">Download</a></p>
                                <?php elseif ($i == 2): ?>
                                    <p>Keuangan Dasar: <a href="<?php echo esc_url(get_user_meta($user->ID, 'keuangan_dasar', true)); ?>" target="_blank">Download</a></p>
                                    <p>Pemasaran Dasar: <a href="<?php echo esc_url(get_user_meta($user->ID, 'pemasaran_dasar', true)); ?>" target="_blank">Download</a></p>
                                    <!-- Tambahkan tugas-tugas lainnya dari sesi 2 -->
                                <?php elseif ($i == 3): ?>
                                    <p>Omset Awal: <?php echo esc_html(get_user_meta($user->ID, 'omset_awal', true)); ?></p>
                                    <p>Kapasitas Produksi Awal: <?php echo esc_html(get_user_meta($user->ID, 'kapasitas_produksi_awal', true)); ?></p>
                                    <p>Omset Sekarang: <?php echo esc_html(get_user_meta($user->ID, 'omset_sekarang', true)); ?></p>
                                    <p>Laporan Keuangan: <a href="<?php echo esc_url(get_user_meta($user->ID, 'laporan_keuangan', true)); ?>" target="_blank">Download</a></p>
                                <?php elseif ($i == 4): ?>
                                    <p>Bisnis Matching: <?php echo oc_is_session_validated($user->ID, 4) ? 'Ya' : 'Tidak'; ?></p>
                                    <p>Mitra: <?php echo esc_html(get_user_meta($user->ID, 'mitra', true)); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>Belum tervalidasi.</p>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php

    return ob_get_clean();
}

// Shortcode untuk melihat input dan isian user
add_shortcode('admin-user-input', 'oc_admin_user_input_check');

// Fungsi untuk menangani input sesi 1-4 (sama seperti sebelumnya)
// (Tambahkan fungsi untuk setiap sesi di sini sesuai dengan kebutuhan form dan upload file)

// Fungsi untuk menangani input sesi 1
function oc_handle_session1_input($user_id) {
    if (isset($_POST['visi_misi_usaha']) && isset($_POST['rencana_pengembangan_usaha'])) {
        update_user_meta($user_id, 'visi_misi_usaha', sanitize_textarea_field($_POST['visi_misi_usaha']));
        update_user_meta($user_id, 'rencana_pengembangan_usaha', sanitize_textarea_field($_POST['rencana_pengembangan_usaha']));

        // Upload file surat pernyataan
        if (!empty($_FILES['surat_pernyataan']['name'])) {
            $file = wp_handle_upload($_FILES['surat_pernyataan'], array('test_form' => false));
            if (isset($file['url'])) {
                update_user_meta($user_id, 'surat_pernyataan', esc_url($file['url']));
            }
        }
    }
}

// Fungsi untuk menangani input sesi 2
function oc_handle_session2_input($user_id) {
    $fields = ['keuangan_dasar', 'pemasaran_dasar', 'inovasi_usaha', 'social_media_marketing', 'risk_management', 'proposal_usaha', 'pitching', 'kemitraan'];

    foreach ($fields as $field) {
        if (!empty($_FILES[$field]['name'])) {
            $file = wp_handle_upload($_FILES[$field], array('test_form' => false));
            if (isset($file['url'])) {
                update_user_meta($user_id, $field, esc_url($file['url']));
            }
        }
    }
}

// Fungsi untuk menangani input sesi 3
function oc_handle_session3_input($user_id) {
    $fields = ['omset_awal', 'kapasitas_produksi_awal', 'pemasaran_awal', 'hutang_bank', 'omset_sekarang', 'kapasitas_produksi_sekarang'];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_user_meta($user_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Laporan keuangan (upload file)
    if (!empty($_FILES['laporan_keuangan']['name'])) {
        $file = wp_handle_upload($_FILES['laporan_keuangan'], array('test_form' => false));
        if (isset($file['url'])) {
            update_user_meta($user_id, 'laporan_keuangan', esc_url($file['url']));
        }
    }

    // Pendampingan yang diperoleh (checkbox)
    if (isset($_POST['pendampingan'])) {
        update_user_meta($user_id, 'pendampingan', $_POST['pendampingan']);
    }
}

// Fungsi untuk menangani input sesi 4
function oc_handle_session4_input($user_id) {
    if (isset($_POST['bisnis_matching']) && isset($_POST['mitra'])) {
        update_user_meta($user_id, 'bisnis_matching', sanitize_text_field($_POST['bisnis_matching']));
        update_user_meta($user_id, 'mitra', sanitize_text_field($_POST['mitra']));
    }
}

// Fungsi utama untuk menampilkan form berdasarkan sesi
function oc_lesson_form($atts) {
    $user_id = get_current_user_id();
    $atts = shortcode_atts(array(
        'session_number' => 1,
    ), $atts, 'lesson_form');

    $session_number = intval($atts['session_number']);

    // Cek apakah user sudah validasi sesi sebelumnya
    if (!oc_is_session_validated($user_id, $session_number - 1) && $session_number > 1) {
        return '<p>Anda belum divalidasi untuk mengakses sesi ini.</p>';
    }

    ob_start();

    // Tampilkan materi admin jika ada
    $materi = get_option('oc_sesi' . $session_number . '_materi');
    if ($materi) {
        echo '<h3>Materi Sesi ' . $session_number . '</h3>';
        echo '<p>' . esc_html($materi) . '</p>';
    }

    ?>
    <form method="post" enctype="multipart/form-data">
        <h3>Form Sesi <?php echo $session_number; ?></h3>
        <?php if ($session_number == 1): ?>
            <label for="visi_misi_usaha">Visi Misi Usaha:</label>
            <textarea name="visi_misi_usaha" required></textarea><br>
            <label for="rencana_pengembangan_usaha">Rencana Pengembangan Usaha:</label>
            <textarea name="rencana_pengembangan_usaha" required></textarea><br>
            <label for="surat_pernyataan">Upload Surat Pernyataan Komitmen:</label>
            <input type="file" name="surat_pernyataan" required><br>
        <?php elseif ($session_number == 2): ?>
            <h4>Upload Tugas:</h4>
            <label for="keuangan_dasar">Tugas Keuangan Dasar:</label>
            <input type="file" name="keuangan_dasar" required><br>
            <label for="pemasaran_dasar">Tugas Pemasaran Dasar:</label>
            <input type="file" name="pemasaran_dasar" required><br>
            <label for="inovasi_usaha">Tugas Inovasi Usaha:</label>
            <input type="file" name="inovasi_usaha" required><br>
            <label for="social_media_marketing">Tugas Social Media Marketing:</label>
            <input type="file" name="social_media_marketing" required><br>
            <label for="risk_management">Tugas Risk & Operational Manajemen:</label>
            <input type="file" name="risk_management" required><br>
            <label for="proposal_usaha">Tugas Proposal Usaha:</label>
            <input type="file" name="proposal_usaha" required><br>
            <label for="pitching">Tugas Pitching:</label>
            <input type="file" name="pitching" required><br>
            <label for="kemitraan">Tugas Kemitraan:</label>
            <input type="file" name="kemitraan" required><br>
        <?php elseif ($session_number == 3): ?>
            <!-- Kolom tambahan untuk omset awal, kapasitas produksi, dll -->
            <div style="display: flex; flex-direction: row;">
                <div style="flex: 1; padding-right: 20px;">
                    <label for="omset_awal">Omset Awal:</label>
                    <input type="text" name="omset_awal" required><br>
                    <label for="kapasitas_produksi_awal">Kapasitas Produksi Awal:</label>
                    <input type="text" name="kapasitas_produksi_awal" required><br>
                </div>
                <div style="flex: 1; padding-right: 20px;">
                    <label for="laporan_keuangan">Upload Laporan Keuangan:</label>
                    <input type="file" name="laporan_keuangan" required><br>
                    <label for="hutang_bank">Hutang Bank:</label>
                    <input type="text" name="hutang_bank" required><br>
                </div>
                <div style="flex: 1;">
                    <label for="omset_sekarang">Omset Sekarang:</label>
                    <input type="text" name="omset_sekarang" required><br>
                    <label for="kapasitas_produksi_sekarang">Kapasitas Produksi Sekarang:</label>
                    <input type="text" name="kapasitas_produksi_sekarang" required><br>
                </div>
            </div>
        <?php elseif ($session_number == 4): ?>
            <h4>Bisnis Matching:</h4>
            <label><input type="checkbox" name="bisnis_matching" value="1"> Bisnis Matching</label><br>
            <label for="mitra">Sebutkan Mitra:</label>
            <input type="text" name="mitra" required><br>
        <?php endif; ?>
        <input type="submit" value="Submit">
    </form>
    <?php

    return ob_get_clean();
}

// Shortcode untuk menampilkan form input sesi
add_shortcode('lesson_form', 'oc_lesson_form');

// Shortcode untuk menampilkan materi yang diupload oleh admin
function oc_admin_upload_form() {
    if (!current_user_can('administrator') && !current_user_can('editor')) {
        return '<p>Anda tidak memiliki akses untuk mengupload materi.</p>';
    }

    if (isset($_POST['sesi_number']) && isset($_POST['materi'])) {
        update_option('oc_sesi' . intval($_POST['sesi_number']) . '_materi', sanitize_textarea_field($_POST['materi']));
        echo '<p>Materi berhasil disimpan.</p>';
    }

    ob_start();
    ?>
    <form method="post">
        <label for="sesi_number">Sesi:</label>
        <input type="number" name="sesi_number" required><br>
        <label for="materi">Materi:</label>
        <textarea name="materi" required></textarea><br>
        <input type="submit" value="Upload Materi">
    </form>
    <?php

    return ob_get_clean();
}
add_shortcode('admin_upload_form', 'oc_admin_upload_form');


// Fungsi untuk menampilkan laporan statistik
function oc_session_statistic() {
    // Ambil semua user
    $users = get_users();
    $statistic = [
        'sesi1' => 0,
        'sesi2' => 0,
        'sesi3' => 0,
        'sesi4' => 0,
        'lulus_semua_sesi' => 0
    ];

    // Hitung jumlah yang lulus tiap sesi dan yang lulus semua sesi
    foreach ($users as $user) {
        $lulus_semua_sesi = true;
        for ($i = 1; $i <= 4; $i++) {
            if (oc_is_session_validated($user->ID, $i)) {
                $statistic['sesi' . $i]++;
            } else {
                $lulus_semua_sesi = false;
            }
        }
        if ($lulus_semua_sesi) {
            $statistic['lulus_semua_sesi']++;
        }
    }

    // Mulai output buffer
    ob_start();
    ?>
    <table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>Sesi</th>
                <th>Jumlah Lulus</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Sesi 1</td>
                <td><?php echo $statistic['sesi1']; ?></td>
            </tr>
            <tr>
                <td>Sesi 2</td>
                <td><?php echo $statistic['sesi2']; ?></td>
            </tr>
            <tr>
                <td>Sesi 3</td>
                <td><?php echo $statistic['sesi3']; ?></td>
            </tr>
            <tr>
                <td>Sesi 4</td>
                <td><?php echo $statistic['sesi4']; ?></td>
            </tr>
            <tr>
                <td>Lulus Semua Sesi</td>
                <td><?php echo $statistic['lulus_semua_sesi']; ?></td>
            </tr>
        </tbody>
    </table>
    <?php

    return ob_get_clean();
}

// Shortcode untuk laporan statistik
add_shortcode('session-statistic', 'oc_session_statistic');
?>
