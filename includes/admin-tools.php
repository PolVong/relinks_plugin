<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register hidden tools page ────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    add_submenu_page(
        null,
        'Relinks — Інструменти',
        'Relinks Інструменти',
        'manage_options',
        'relinks-tools',
        'relinks_tools_page'
    );
} );

// ── Handle log clear ─────────────────────────────────────────────────────────
add_action( 'admin_post_relinks_clear_log', function() {
    check_admin_referer( 'relinks_clear_log' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $log_file = RELINKS_DIR . 'data/relinks.log';
    if ( file_exists( $log_file ) ) {
        file_put_contents( $log_file, '' );
    }

    wp_redirect( admin_url( 'admin.php?page=relinks-tools#log' ) );
    exit;
} );

// ── Handle Google Sheets sync ─────────────────────────────────────────────────
add_action( 'admin_post_relinks_sync_gsheets', function() {
    check_admin_referer( 'relinks_sync_gsheets' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    $result = relinks_sync_gsheets();
    $status = $result['success'] ? 'success' : 'error';
    $msg    = urlencode( $result['message'] );

    wp_redirect( admin_url( 'admin.php?page=relinks-tools&status=' . $status . '&msg=' . $msg ) );
    exit;
} );

// ── Handle import via file upload ─────────────────────────────────────────────
add_action( 'admin_post_relinks_import_upload', function() {
    check_admin_referer( 'relinks_import_upload' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Недостатньо прав.' );

    if ( empty( $_FILES['anchors_file']['tmp_name'] ) ) {
        $status = 'error';
        $msg    = urlencode( 'Файл не вибрано.' );
        wp_redirect( admin_url( 'admin.php?page=relinks-tools&status=' . $status . '&msg=' . $msg ) );
        exit;
    }

    $tmp  = $_FILES['anchors_file']['tmp_name'];
    $dest = RELINKS_DIR . 'anchors.txt';

    if ( ! move_uploaded_file( $tmp, $dest ) ) {
        $status = 'error';
        $msg    = urlencode( 'Не вдалося зберегти файл. Перевірте права доступу.' );
        wp_redirect( admin_url( 'admin.php?page=relinks-tools&status=' . $status . '&msg=' . $msg ) );
        exit;
    }

    $result = relinks_import_txt();
    $status = $result['success'] ? 'success' : 'error';
    $msg    = urlencode( $result['message'] );

    wp_redirect( admin_url( 'admin.php?page=relinks-tools&status=' . $status . '&msg=' . $msg ) );
    exit;
} );

// ── Tools page HTML ───────────────────────────────────────────────────────────
function relinks_tools_page() {
    $json_file   = RELINKS_DIR . 'data/relinking.json';
    $json_exists = file_exists( $json_file );
    $json_count  = 0;
    $last_mod    = '';

    if ( $json_exists ) {
        $data       = json_decode( file_get_contents( $json_file ), true );
        $json_count = is_array( $data ) ? count( $data ) : 0;
        $last_mod   = date_i18n( 'd.m.Y H:i', filemtime( $json_file ) );
    }

    $gsheets_url = get_field( 'relinks_gsheets_url', 'option' );
    $status      = $_GET['status'] ?? '';
    $msg         = isset( $_GET['msg'] ) ? urldecode( sanitize_text_field( $_GET['msg'] ) ) : '';
    ?>
    <div class="wrap">
        <h1>Relinks — Інструменти</h1>

        <p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=relinks-options' ) ); ?>">← Налаштування</a></p>

        <?php if ( $status === 'success' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php elseif ( $status === 'error' ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php endif; ?>

        <h2>Стан бази анкорів</h2>
        <p>
            <?php if ( $json_exists ) : ?>
                <span style="color:green">✅ Файл існує</span> —
                <strong><?php echo (int) $json_count; ?> сторінок</strong> з анкорами.
                Оновлено: <?php echo esc_html( $last_mod ); ?>
            <?php else : ?>
                <span style="color:red">❌ Файл не знайдено.</span>
                Синхронізуйте з Google Sheets або імпортуйте anchors.txt.
            <?php endif; ?>
        </p>

        <h2>Синхронізація з Google Sheets</h2>
        <?php if ( $gsheets_url ) : ?>
            <p><a href="<?php echo esc_url( $gsheets_url ); ?>" target="_blank"><?php echo esc_html( $gsheets_url ); ?></a></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="relinks_sync_gsheets">
                <?php wp_nonce_field( 'relinks_sync_gsheets' ); ?>
                <?php submit_button( '↻ Синхронізувати з Google Sheets', 'primary', 'submit', false ); ?>
            </form>
        <?php else : ?>
            <p style="color:#999;">
                URL не вказано.
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=relinks-options' ) ); ?>">Додати в налаштуваннях →</a>
            </p>
        <?php endif; ?>

        <hr>

        <h2>Імпорт з anchors.txt</h2>
        <p>Завантажте файл <code>anchors.txt</code> з локального комп'ютера.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="relinks_import_upload">
            <?php wp_nonce_field( 'relinks_import_upload' ); ?>
            <input type="file" name="anchors_file" accept=".txt" style="margin-right:8px;">
            <?php submit_button( 'Завантажити та імпортувати', 'secondary', 'submit', false ); ?>
        </form>
        <hr>

        <h2 id="log">Лог подій</h2>
        <?php
        $log_file = RELINKS_DIR . 'data/relinks.log';
        $log_lines = [];
        if ( file_exists( $log_file ) ) {
            $all_lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            $log_lines = array_slice( $all_lines, -100 );
            $log_lines = array_reverse( $log_lines );
        }
        ?>
        <?php if ( ! empty( $log_lines ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
                <input type="hidden" name="action" value="relinks_clear_log">
                <?php wp_nonce_field( 'relinks_clear_log' ); ?>
                <?php submit_button( 'Очистити лог', 'delete small', 'submit', false ); ?>
            </form>
            <textarea readonly style="width:100%;height:300px;font-family:monospace;font-size:12px;background:#1e1e1e;color:#d4d4d4;border:none;padding:10px;resize:vertical;"><?php echo esc_textarea( implode( "\n", $log_lines ) ); ?></textarea>
        <?php else : ?>
            <p style="color:#999;">Лог порожній.</p>
        <?php endif; ?>

        <hr>

        <?php $stats = relinks_get_anchor_stats(); ?>
        <?php if ( ! empty( $stats ) ) : ?>
            <h2>Статистика анкорів</h2>
            <p>Анкори відсортовані від найчастіше до найрідше використовуваних.</p>
            <table class="widefat striped" style="max-width:600px;">
                <thead>
                    <tr>
                        <th>Анкор</th>
                        <th style="width:120px;text-align:center;">Використань</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats as $anchor => $count ) : ?>
                        <tr>
                            <td><?php echo esc_html( $anchor ); ?></td>
                            <td style="text-align:center;"><?php echo (int) $count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p style="color:#999;">Статистика з'явиться після першої генерації посилань.</p>
        <?php endif; ?>
    </div>
    <?php
}
