<?php
namespace WCSIO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
    /** @var Settings */
    private $settings;
    /** @var Image_Processor */
    private $processor;
    /** @var Font_Manager */
    private $fonts;

    /** @var int Items per page for log */
    private $log_page_size = 50;

    private $page_slug = 'wcsio-settings';

    public function __construct( Settings $settings, Image_Processor $processor ) {
        $this->settings  = $settings;
        $this->processor = $processor;
        $this->fonts     = new Font_Manager( $this->settings );

        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        add_action( 'admin_post_wcsio_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_wcsio_regenerate', [ $this, 'handle_regenerate' ] );
        add_action( 'admin_post_wcsio_clear_log', [ $this, 'handle_clear_log' ] );
        add_action( 'admin_post_wcsio_export_log', [ $this, 'handle_export_log' ] );
        add_action( 'admin_post_wcsio_restore_backups', [ $this, 'handle_restore_backups' ] );
        add_action( 'admin_post_wcsio_restore_one', [ $this, 'handle_restore_one' ] );

        // Per-attachment UI
        add_filter( 'media_row_actions', [ $this, 'filter_media_row_actions' ], 10, 2 );
        add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields_to_edit' ], 10, 2 );
        add_filter( 'manage_upload_columns', [ $this, 'add_media_list_column' ] );
        add_action( 'manage_media_custom_column', [ $this, 'render_media_list_column' ], 10, 2 );
        add_action( 'admin_head-upload.php', [ $this, 'upload_screen_styles' ] );
        add_action( 'restrict_manage_posts', [ $this, 'media_library_filters' ] );
        add_action( 'pre_get_posts', [ $this, 'handle_media_library_query' ] );
        add_filter( 'manage_upload_sortable_columns', [ $this, 'sortable_media_column' ] );
        add_filter( 'bulk_actions-upload', [ $this, 'register_bulk_actions' ] );
        add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );
        add_action( 'admin_post_wcsio_restore_visible', [ $this, 'handle_restore_visible' ] );
        add_action( 'admin_post_wcsio_restore_filtered', [ $this, 'handle_restore_filtered' ] );
        add_action( 'admin_notices', [ $this, 'maybe_upload_notice' ] );

        // AJAX: log pagination
        add_action( 'wp_ajax_wcsio_log_page', [ $this, 'handle_ajax_log_page' ] );
        // AJAX: preview overlay
        add_action( 'wp_ajax_wcsio_preview_overlay', [ $this, 'handle_ajax_preview_overlay' ] );
    }

    public function add_menu() : void {
        add_submenu_page(
            'woocommerce',
            __( 'SKU Image Overlay', 'woocommerce-sku-on-images' ),
            __( 'SKU Image Overlay', 'woocommerce-sku-on-images' ),
            'manage_woocommerce',
            $this->page_slug,
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook_suffix ) : void {
        if ( strpos( (string) $hook_suffix, $this->page_slug ) === false ) {
            return;
        }
        // Media library for preview picker
        if ( function_exists( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'wcsio-admin', WCSIO_PLUGIN_URL . 'assets/css/admin.css', [], WCSIO_VERSION );
        wp_enqueue_script( 'wcsio-admin', WCSIO_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'wp-color-picker' ], WCSIO_VERSION, true );
        wp_localize_script( 'wcsio-admin', 'wcsioAdmin', [
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'ajax_nonce'=> wp_create_nonce( 'wcsio_log_nonce' ),
            'preview_nonce'=> wp_create_nonce( 'wcsio_preview_nonce' ),
            'page_size' => $this->log_page_size,
        ] );
    }

    public function render_page() : void {
        $data = [
            'options' => $this->settings->get_all(),
            'has_gd' => extension_loaded( 'gd' ),
            'has_imagick' => extension_loaded( 'imagick' ),
            'last_log' => $this->get_last_log(),
        ];
        $template = WCSIO_PLUGIN_DIR . 'templates/admin-settings.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="wrap"><h1>SKU Image Overlay</h1><p>' . esc_html__( 'Template missing.', 'woocommerce-sku-on-images' ) . '</p></div>';
        }
    }

    public function handle_save_settings() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'wcsio_save_settings' );
        $this->settings->update( $_POST['wcsio'] ?? [] );

        // If Google Font is requested, try to fetch and set the font_path automatically
        $opts = $this->settings->get_all();
        $font_dl = '';
        if ( ! empty( $opts['use_google_font'] ) ) {
            $family = (string) ( $opts['google_font_family'] ?? '' );
            $path   = $this->fonts->ensure_google_font( $family );
            if ( $path && is_readable( $path ) ) {
                $opts['font_path'] = $path;
                $this->settings->update( $opts );
                $font_dl = 'ok';
            } else {
                $font_dl = 'fail';
            }
        }
        $redirect_args = [ 'page' => $this->page_slug, 'updated' => 1 ];
        if ( $font_dl ) { $redirect_args['font_dl'] = $font_dl; }
        wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_clear_log() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'wcsio_clear_log' );
        delete_option( 'wcsio_last_regen_log' );
        wp_safe_redirect( add_query_arg( [ 'page' => $this->page_slug, 'log_cleared' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_export_log() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        // Accept either POST or GET nonce named _wpnonce
        $nonce = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wcsio_export_log' ) ) {
            wp_die( 'Invalid nonce' );
        }

        $format = isset( $_REQUEST['format'] ) ? strtolower( (string) $_REQUEST['format'] ) : 'json';
        $log    = $this->get_last_log();
        $run_ts = isset( $log['time'] ) ? (string) $log['time'] : current_time( 'mysql' );

        if ( $format === 'csv' ) {
            $filename = 'wcsio-log-' . gmdate( 'Ymd-His' ) . '.csv';
            nocache_headers();
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=' . $filename );
            $out = fopen( 'php://output', 'w' );
            // Header row
            fputcsv( $out, [ 'run_time', 'forced', 'ts', 'type', 'post_id', 'attachment_id', 'sku', 'result', 'file' ] );
            $entries = isset( $log['entries'] ) && is_array( $log['entries'] ) ? $log['entries'] : [];
            $forced  = ! empty( $log['forced'] ) ? 1 : 0;
            foreach ( $entries as $row ) {
                fputcsv( $out, [
                    $run_ts,
                    $forced,
                    (string) ( $row['ts'] ?? '' ),
                    (string) ( $row['type'] ?? '' ),
                    (string) ( $row['post_id'] ?? '' ),
                    (string) ( $row['attachment_id'] ?? '' ),
                    (string) ( $row['sku'] ?? '' ),
                    (string) ( $row['result'] ?? '' ),
                    (string) ( $row['file'] ?? '' ),
                ] );
            }
            fclose( $out );
            exit;
        }

        // JSON (default)
        $filename = 'wcsio-log-' . gmdate( 'Ymd-His' ) . '.json';
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        echo wp_json_encode( $log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function handle_restore_backups() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'wcsio_restore_backups' );

        $restored = 0;
        $q = new \WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [ 'key' => '_wcsio_backup_path', 'compare' => 'EXISTS' ] ],
        ]);
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $att_id ) {
                $backup = (string) get_post_meta( (int) $att_id, '_wcsio_backup_path', true );
                if ( ! $backup || ! file_exists( $backup ) ) { continue; }
                $dest = (string) get_attached_file( (int) $att_id );
                if ( ! $dest ) { continue; }
                // Attempt restore
                if ( @copy( $backup, $dest ) ) {
                    // Clear overlay flags so future runs can reapply
                    delete_post_meta( (int) $att_id, '_wcsio_overlay_applied' );
                    delete_post_meta( (int) $att_id, '_wcsio_overlay_sku' );
                    $restored++;
                }
            }
        }
        wp_reset_postdata();
        wp_safe_redirect( add_query_arg( [ 'page' => $this->page_slug, 'restored' => $restored ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_restore_one() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        $att_id = isset( $_REQUEST['attachment_id'] ) ? (int) $_REQUEST['attachment_id'] : 0;
        if ( $att_id <= 0 ) { wp_die( 'Invalid attachment' ); }
        $nonce = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'wcsio_restore_one_' . $att_id ) ) { wp_die( 'Invalid nonce' ); }

        $backup = (string) get_post_meta( $att_id, '_wcsio_backup_path', true );
        $ok = false;
        if ( $backup && file_exists( $backup ) ) {
            $dest = (string) get_attached_file( $att_id );
            if ( $dest ) {
                $ok = @copy( $backup, $dest );
                if ( $ok ) {
                    delete_post_meta( $att_id, '_wcsio_overlay_applied' );
                    delete_post_meta( $att_id, '_wcsio_overlay_sku' );
                }
            }
        }
        $ref = wp_get_referer();
        if ( ! $ref ) {
            $ref = admin_url( 'post.php?post=' . $att_id . '&action=edit' );
        }
        $ref = add_query_arg( [ 'wcsio_restored_one' => (int) $ok ], $ref );
        wp_safe_redirect( $ref );
        exit;
    }

    public function filter_media_row_actions( array $actions, $post ) : array {
        if ( ! $post || $post->post_type !== 'attachment' ) { return $actions; }
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return $actions; }
        $backup = (string) get_post_meta( (int) $post->ID, '_wcsio_backup_path', true );
        if ( $backup && file_exists( $backup ) ) {
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=wcsio_restore_one&attachment_id=' . (int) $post->ID ), 'wcsio_restore_one_' . (int) $post->ID );
            $actions['wcsio_restore'] = '<a href="' . esc_url( $url ) . '" onclick="return confirm(\'' . esc_js( __( 'Restore original from backup for this item?', 'woocommerce-sku-on-images' ) ) . '\');">' . esc_html__( 'Restore Original (WCSIO)', 'woocommerce-sku-on-images' ) . '</a>';
        }
        return $actions;
    }

    public function attachment_fields_to_edit( array $form_fields, $post ) : array {
        if ( ! $post || $post->post_type !== 'attachment' ) { return $form_fields; }
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return $form_fields; }
        $backup = (string) get_post_meta( (int) $post->ID, '_wcsio_backup_path', true );
        $btime  = (string) get_post_meta( (int) $post->ID, '_wcsio_backup_time', true );
        if ( $backup && file_exists( $backup ) ) {
            $url = wp_nonce_url( admin_url( 'admin-post.php?action=wcsio_restore_one&attachment_id=' . (int) $post->ID ), 'wcsio_restore_one_' . (int) $post->ID );
            $html  = '<div>';
            $html .= '<p style="margin:4px 0 8px 0;">' . esc_html__( 'WCSIO backup available.', 'woocommerce-sku-on-images' ) . '</p>';
            if ( $btime ) {
                $html .= '<p style="margin:4px 0; color:#555;">' . esc_html( sprintf( __( 'Backup time: %s', 'woocommerce-sku-on-images' ), $btime ) ) . '</p>';
            }
            $html .= '<p style="margin:8px 0;"><a class="button" href="' . esc_url( $url ) . '" onclick="return confirm(\'' . esc_js( __( 'Restore original from backup for this item?', 'woocommerce-sku-on-images' ) ) . '\');">' . esc_html__( 'Restore Original from Backup', 'woocommerce-sku-on-images' ) . '</a></p>';
            $html .= '</div>';
            $form_fields['wcsio_restore'] = [
                'label' => __( 'SKU Image Overlay', 'woocommerce-sku-on-images' ),
                'input' => 'html',
                'html'  => $html,
            ];
        }
        return $form_fields;
    }

    public function add_media_list_column( array $columns ) : array {
        // Insert after title if possible
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['wcsio_backup'] = __( 'WCSIO Backup', 'woocommerce-sku-on-images' );
            }
        }
        if ( ! isset( $new['wcsio_backup'] ) ) {
            $new['wcsio_backup'] = __( 'WCSIO Backup', 'woocommerce-sku-on-images' );
        }
        return $new;
    }

    public function render_media_list_column( string $column_name, int $post_id ) : void {
        if ( $column_name !== 'wcsio_backup' ) { return; }
        if ( get_post_type( $post_id ) !== 'attachment' ) { return; }
        $backup = (string) get_post_meta( $post_id, '_wcsio_backup_path', true );
        $btime  = (string) get_post_meta( $post_id, '_wcsio_backup_time', true );
        if ( $backup && file_exists( $backup ) ) {
            $check = '<span class="dashicons dashicons-yes" style="color:#198754;"></span>';
            $time  = $btime ? '<span class="description" style="display:block;color:#666;">' . esc_html( $btime ) . '</span>' : '';
            $link  = '';
            if ( current_user_can( 'manage_woocommerce' ) ) {
                $url = wp_nonce_url( admin_url( 'admin-post.php?action=wcsio_restore_one&attachment_id=' . (int) $post_id ), 'wcsio_restore_one_' . (int) $post_id );
                $link = '<a href="' . esc_url( $url ) . '" class="button button-small" onclick="return confirm(\'' . esc_js( __( 'Restore original from backup for this item?', 'woocommerce-sku-on-images' ) ) . '\');">' . esc_html__( 'Restore', 'woocommerce-sku-on-images' ) . '</a>';
            }
            echo $check . ' ' . $link . $time; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<span class="dashicons dashicons-minus"></span>';
        }
    }

    public function upload_screen_styles() : void {
        echo '<style>.fixed .column-wcsio_backup{width:160px}</style>';
    }

    public function media_library_filters( $post_type ) : void {
        if ( 'attachment' !== $post_type ) { return; }
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $current = isset( $_GET['wcsio_backup_filter'] ) ? (string) $_GET['wcsio_backup_filter'] : '';
        echo '<label for="wcsio_backup_filter" class="screen-reader-text">' . esc_html__( 'WCSIO Backup Filter', 'woocommerce-sku-on-images' ) . '</label>';
        echo '<select id="wcsio_backup_filter" name="wcsio_backup_filter">';
        echo '<option value="">' . esc_html__( 'WCSIO Backup (all)', 'woocommerce-sku-on-images' ) . '</option>';
        echo '<option value="with"' . selected( $current, 'with', false ) . '>' . esc_html__( 'With backup', 'woocommerce-sku-on-images' ) . '</option>';
        echo '<option value="without"' . selected( $current, 'without', false ) . '>' . esc_html__( 'Without backup', 'woocommerce-sku-on-images' ) . '</option>';
        echo '</select>';
        // Quick filter button: Restorable only
        $base_url = remove_query_arg( [ 'wcsio_backup_filter', 'paged' ] );
        $with_url = add_query_arg( [ 'wcsio_backup_filter' => 'with' ], $base_url );
        $btn_class = $current === 'with' ? 'button button-primary' : 'button';
        echo ' <a class="' . esc_attr( $btn_class ) . '" href="' . esc_url( $with_url ) . '">' . esc_html__( 'Restorable only', 'woocommerce-sku-on-images' ) . '</a>';

        // Restore all visible button (current page only)
        $paged = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
        $args  = [
            'action' => 'wcsio_restore_visible',
            'scope'  => 'page',
            'paged'  => max( 1, $paged ),
            's'      => isset( $_GET['s'] ) ? (string) $_GET['s'] : '',
            'post_mime_type' => isset( $_GET['post_mime_type'] ) ? (string) $_GET['post_mime_type'] : '',
            'wcsio_backup_filter' => $current,
        ];
        $restore_url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'wcsio_restore_visible' );
        echo ' <a class="button" href="' . esc_url( $restore_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Restore originals for all items visible on this page?', 'woocommerce-sku-on-images' ) ) . '\');">' . esc_html__( 'Restore all visible (WCSIO)', 'woocommerce-sku-on-images' ) . '</a>';

        // Restore all filtered (across all pages)
        $args_all  = [
            'action' => 'wcsio_restore_filtered',
            'scope'  => 'all',
            's'      => isset( $_GET['s'] ) ? (string) $_GET['s'] : '',
            'post_mime_type' => isset( $_GET['post_mime_type'] ) ? (string) $_GET['post_mime_type'] : '',
            'wcsio_backup_filter' => $current,
        ];
        $restore_all_url = wp_nonce_url( add_query_arg( $args_all, admin_url( 'admin-post.php' ) ), 'wcsio_restore_filtered' );
        echo ' <a class="button button-secondary" href="' . esc_url( $restore_all_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Restore originals for ALL items matching current filters? This may take a while.', 'woocommerce-sku-on-images' ) ) . '\');">' . esc_html__( 'Restore all filtered (WCSIO)', 'woocommerce-sku-on-images' ) . '</a>';

        // Dry-run restore filtered (no writes)
        $args_dry = $args_all;
        $args_dry['dry_run'] = 1;
        $restore_dry_url = wp_nonce_url( add_query_arg( $args_dry, admin_url( 'admin-post.php' ) ), 'wcsio_restore_filtered' );
        echo ' <a class="button" href="' . esc_url( $restore_dry_url ) . '">' . esc_html__( 'Dry-run restore filtered', 'woocommerce-sku-on-images' ) . '</a>';

        // Dry-run export CSV (list candidates)
        $args_dry_csv = $args_dry;
        $args_dry_csv['export'] = 'csv';
        $restore_dry_csv_url = wp_nonce_url( add_query_arg( $args_dry_csv, admin_url( 'admin-post.php' ) ), 'wcsio_restore_filtered' );
        echo ' <a class="button button-link" href="' . esc_url( $restore_dry_csv_url ) . '">' . esc_html__( 'Dry-run export CSV', 'woocommerce-sku-on-images' ) . '</a>';
    }

    public function handle_media_library_query( \WP_Query $q ) : void {
        if ( ! is_admin() || ! $q->is_main_query() ) { return; }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'upload' !== $screen->id ) { return; }
        if ( 'attachment' !== $q->get( 'post_type' ) ) { return; }

        // Filter by backup existence
        $filter = isset( $_GET['wcsio_backup_filter'] ) ? (string) $_GET['wcsio_backup_filter'] : '';
        if ( $filter === 'with' ) {
            $q->set( 'meta_query', [ [ 'key' => '_wcsio_backup_path', 'compare' => 'EXISTS' ] ] );
        } elseif ( $filter === 'without' ) {
            $q->set( 'meta_query', [ [ 'key' => '_wcsio_backup_path', 'compare' => 'NOT EXISTS' ] ] );
        }

        // Sorting by backup time via column header
        if ( $q->get( 'orderby' ) === 'wcsio_backup' ) {
            $q->set( 'meta_key', '_wcsio_backup_time' );
            $q->set( 'orderby', 'meta_value' );
            $q->set( 'meta_type', 'DATETIME' );
        }
    }

    public function sortable_media_column( array $columns ) : array {
        $columns['wcsio_backup'] = 'wcsio_backup';
        return $columns;
    }

    public function register_bulk_actions( array $actions ) : array {
        $actions['wcsio_restore_bulk'] = __( 'Restore Original from Backup (WCSIO)', 'woocommerce-sku-on-images' );
        return $actions;
    }

    public function handle_bulk_actions( string $redirect_to, string $doaction, array $post_ids ) : string {
        if ( $doaction !== 'wcsio_restore_bulk' ) { return $redirect_to; }
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return $redirect_to; }
        $count = 0;
        foreach ( $post_ids as $att_id ) {
            $att_id = (int) $att_id;
            if ( get_post_type( $att_id ) !== 'attachment' ) { continue; }
            $backup = (string) get_post_meta( $att_id, '_wcsio_backup_path', true );
            if ( ! $backup || ! file_exists( $backup ) ) { continue; }
            $dest = (string) get_attached_file( $att_id );
            if ( ! $dest ) { continue; }
            if ( @copy( $backup, $dest ) ) {
                delete_post_meta( $att_id, '_wcsio_overlay_applied' );
                delete_post_meta( $att_id, '_wcsio_overlay_sku' );
                $count++;
            }
        }
        $redirect_to = add_query_arg( [ 'wcsio_restored_bulk' => $count ], $redirect_to );
        return $redirect_to;
    }

    public function handle_restore_visible() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'wcsio_restore_visible' );

        $paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $per_page = (int) get_user_option( 'upload_per_page', get_current_user_id() );
        if ( $per_page <= 0 ) { $per_page = 20; }

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        // Preserve filters
        $filter = isset( $_GET['wcsio_backup_filter'] ) ? (string) $_GET['wcsio_backup_filter'] : '';
        if ( $filter === 'with' ) {
            $args['meta_query'] = [ [ 'key' => '_wcsio_backup_path', 'compare' => 'EXISTS' ] ];
        } elseif ( $filter === 'without' ) {
            $args['meta_query'] = [ [ 'key' => '_wcsio_backup_path', 'compare' => 'NOT EXISTS' ] ];
        }
        if ( ! empty( $_GET['s'] ) ) {
            $args['s'] = (string) $_GET['s'];
        }
        if ( ! empty( $_GET['post_mime_type'] ) ) {
            $args['post_mime_type'] = (string) $_GET['post_mime_type'];
        }

        $q = new \WP_Query( $args );
        $count = 0;
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $att_id ) {
                $backup = (string) get_post_meta( (int) $att_id, '_wcsio_backup_path', true );
                if ( ! $backup || ! file_exists( $backup ) ) { continue; }
                $dest = (string) get_attached_file( (int) $att_id );
                if ( ! $dest ) { continue; }
                if ( @copy( $backup, $dest ) ) {
                    delete_post_meta( (int) $att_id, '_wcsio_overlay_applied' );
                    delete_post_meta( (int) $att_id, '_wcsio_overlay_sku' );
                    $count++;
                }
            }
        }
        wp_reset_postdata();
        $ref = wp_get_referer();
        if ( ! $ref ) { $ref = admin_url( 'upload.php' ); }
        $ref = add_query_arg( [ 'wcsio_restored_visible' => $count ], $ref );
        wp_safe_redirect( $ref );
        exit;
    }

    public function handle_restore_filtered() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'wcsio_restore_filtered' );

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        // Preserve filters
        $filter = isset( $_GET['wcsio_backup_filter'] ) ? (string) $_GET['wcsio_backup_filter'] : '';
        if ( $filter === 'with' ) {
            $args['meta_query'] = [ [ 'key' => '_wcsio_backup_path', 'compare' => 'EXISTS' ] ];
        } elseif ( $filter === 'without' ) {
            $args['meta_query'] = [ [ 'key' => '_wcsio_backup_path', 'compare' => 'NOT EXISTS' ] ];
        }
        if ( ! empty( $_GET['s'] ) ) {
            $args['s'] = (string) $_GET['s'];
        }
        if ( ! empty( $_GET['post_mime_type'] ) ) {
            $args['post_mime_type'] = (string) $_GET['post_mime_type'];
        }

        $q = new \WP_Query( $args );
        $count = 0;
        $dry   = ! empty( $_GET['dry_run'] );
        $export = isset( $_GET['export'] ) ? strtolower( (string) $_GET['export'] ) : '';
        // CSV export for dry-run list of candidates
        if ( $dry && $export === 'csv' ) {
            nocache_headers();
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=wcsio-dryrun-restores-' . gmdate( 'Ymd-His' ) . '.csv' );
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, [ 'id', 'title', 'mime_type', 'file', 'backup_path', 'backup_time', 'url' ] );
            if ( $q->have_posts() ) {
                foreach ( $q->posts as $att_id ) {
                    $backup = (string) get_post_meta( (int) $att_id, '_wcsio_backup_path', true );
                    if ( ! $backup || ! file_exists( $backup ) ) { continue; }
                    $file = (string) get_attached_file( (int) $att_id );
                    $btime = (string) get_post_meta( (int) $att_id, '_wcsio_backup_time', true );
                    $title = get_the_title( (int) $att_id );
                    $mime  = get_post_mime_type( (int) $att_id );
                    $url   = wp_get_attachment_url( (int) $att_id );
                    fputcsv( $out, [ $att_id, $title, $mime, $file, $backup, $btime, $url ] );
                }
            }
            fclose( $out );
            exit;
        }
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $att_id ) {
                $backup = (string) get_post_meta( (int) $att_id, '_wcsio_backup_path', true );
                if ( ! $backup || ! file_exists( $backup ) ) { continue; }
                $dest = (string) get_attached_file( (int) $att_id );
                if ( ! $dest ) { continue; }
                if ( $dry ) {
                    $count++;
                } else {
                    if ( @copy( $backup, $dest ) ) {
                        delete_post_meta( (int) $att_id, '_wcsio_overlay_applied' );
                        delete_post_meta( (int) $att_id, '_wcsio_overlay_sku' );
                        $count++;
                    }
                }
            }
        }
        wp_reset_postdata();
        $ref = wp_get_referer();
        if ( ! $ref ) { $ref = admin_url( 'upload.php' ); }
        if ( $dry ) {
            $ref = add_query_arg( [ 'wcsio_dryrun_filtered' => $count ], $ref );
        } else {
            $ref = add_query_arg( [ 'wcsio_restored_filtered' => $count ], $ref );
        }
        wp_safe_redirect( $ref );
        exit;
    }

    public function maybe_upload_notice() : void {
        if ( ! is_admin() ) { return; }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'upload' !== $screen->id ) { return; }
        if ( isset( $_GET['wcsio_restored_visible'] ) ) {
            $n = (int) $_GET['wcsio_restored_visible'];
            echo '<div class="updated notice"><p>' . esc_html( sprintf( __( 'Restored %d items from backups (visible set).', 'woocommerce-sku-on-images' ), $n ) ) . '</p></div>';
        }
        if ( isset( $_GET['wcsio_restored_bulk'] ) ) {
            $n = (int) $_GET['wcsio_restored_bulk'];
            echo '<div class="updated notice"><p>' . esc_html( sprintf( __( 'Restored %d selected items from backups.', 'woocommerce-sku-on-images' ), $n ) ) . '</p></div>';
        }
        if ( isset( $_GET['wcsio_restored_filtered'] ) ) {
            $n = (int) $_GET['wcsio_restored_filtered'];
            echo '<div class="updated notice"><p>' . esc_html( sprintf( __( 'Restored %d items from backups (filtered set).', 'woocommerce-sku-on-images' ), $n ) ) . '</p></div>';
        }
        if ( isset( $_GET['wcsio_dryrun_filtered'] ) ) {
            $n = (int) $_GET['wcsio_dryrun_filtered'];
            echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( 'Dry-run: %d items would be restored (filtered set).', 'woocommerce-sku-on-images' ), $n ) ) . '</p></div>';
        }
        if ( isset( $_GET['wcsio_restored_one'] ) ) {
            $ok = (int) $_GET['wcsio_restored_one'];
            if ( $ok ) {
                echo '<div class="updated notice"><p>' . esc_html__( 'Restored original from backup.', 'woocommerce-sku-on-images' ) . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__( 'Failed to restore original from backup.', 'woocommerce-sku-on-images' ) . '</p></div>';
            }
        }
    }

    public function handle_regenerate() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Unauthorized' ); }
        check_admin_referer( 'wcsio_regenerate' );

        $force = isset( $_POST['force_overwrite'] ) ? true : false;
        $count = $this->do_bulk_regenerate( $force );
        wp_safe_redirect( add_query_arg( [ 'page' => $this->page_slug, 'regenerated' => $count, 'forced' => (int) $force ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function do_bulk_regenerate( bool $force = false ) : int {
        $processed = 0;
        $log = [];

        // Products
        $q_products = new \WP_Query([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if ( $q_products->have_posts() ) {
            foreach ( $q_products->posts as $product_id ) {
                $product = function_exists( 'wc_get_product' ) ? \wc_get_product( (int) $product_id ) : null;
                if ( ! $product ) { continue; }
                $sku = trim( (string) $product->get_sku() );
                if ( '' === $sku ) { continue; }

                $image_id = (int) $product->get_image_id();
                if ( $image_id ) {
                    $skip = ! $force && $this->should_skip_attachment( $image_id, $sku );
                    if ( $skip ) {
                        $log[] = $this->make_log_entry( 'product', (int) $product_id, $image_id, $sku, 'skip' );
                    } else {
                        $result = $this->processor->process_attachment( $image_id, $sku, $force );
                        $processed += $result ? 1 : 0;
                        $log[] = $this->make_log_entry( 'product', (int) $product_id, $image_id, $sku, $result ? 'ok' : 'fail' );
                    }
                }
                $gallery_ids = method_exists( $product, 'get_gallery_image_ids' ) ? (array) $product->get_gallery_image_ids() : [];
                foreach ( array_filter( array_map( 'intval', $gallery_ids ) ) as $att_id ) {
                    $skip = ! $force && $this->should_skip_attachment( (int) $att_id, $sku );
                    if ( $skip ) {
                        $log[] = $this->make_log_entry( 'product-gallery', (int) $product_id, (int) $att_id, $sku, 'skip' );
                    } else {
                        $result = $this->processor->process_attachment( (int) $att_id, $sku, $force );
                        $processed += $result ? 1 : 0;
                        $log[] = $this->make_log_entry( 'product-gallery', (int) $product_id, (int) $att_id, $sku, $result ? 'ok' : 'fail' );
                    }
                }
            }
        }
        wp_reset_postdata();

        // Variations
        $q_vars = new \WP_Query([
            'post_type'      => 'product_variation',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if ( $q_vars->have_posts() ) {
            foreach ( $q_vars->posts as $var_id ) {
                $variation = function_exists( 'wc_get_product' ) ? \wc_get_product( (int) $var_id ) : null;
                if ( ! $variation ) { continue; }
                $sku = trim( (string) $variation->get_sku() );
                if ( '' === $sku ) {
                    $parent_id = (int) wp_get_post_parent_id( (int) $var_id );
                    $parent    = $parent_id ? \wc_get_product( $parent_id ) : null;
                    $sku       = $parent ? trim( (string) $parent->get_sku() ) : '';
                }
                if ( '' === $sku ) { continue; }

                // Variation image
                $image_id = (int) $variation->get_image_id();
                if ( ! $image_id ) {
                    // Fallback: meta directly
                    $image_id = (int) get_post_meta( (int) $var_id, '_thumbnail_id', true );
                }
                if ( $image_id ) {
                    $skip = ! $force && $this->should_skip_attachment( (int) $image_id, $sku );
                    if ( $skip ) {
                        $log[] = $this->make_log_entry( 'variation', (int) $var_id, (int) $image_id, $sku, 'skip' );
                    } else {
                        $result = $this->processor->process_attachment( $image_id, $sku, $force );
                        $processed += $result ? 1 : 0;
                        $log[] = $this->make_log_entry( 'variation', (int) $var_id, (int) $image_id, $sku, $result ? 'ok' : 'fail' );
                    }
                }
            }
        }
        wp_reset_postdata();

        // Persist last log (cap entries to avoid huge option size)
        if ( count( $log ) > 1000 ) {
            $log = array_slice( $log, -1000 );
        }
        update_option( 'wcsio_last_regen_log', [
            'time'    => current_time( 'mysql' ),
            'entries' => $log,
            'forced'  => $force ? 1 : 0,
        ], false );

        return $processed;
    }

    private function make_log_entry( string $type, int $post_id, int $attachment_id, string $sku, $result ) : array {
        $file = $attachment_id ? (string) get_attached_file( $attachment_id ) : '';
        $entry = [
            'type'          => $type,
            'post_id'       => $post_id,
            'attachment_id' => $attachment_id,
            'sku'           => $sku,
            'result'        => is_string( $result ) ? $result : ( $result ? 'ok' : 'fail' ),
            'file'          => $file,
            'ts'            => current_time( 'mysql' ),
        ];
        // Also echo to PHP error log for debugging
        if ( function_exists( 'error_log' ) ) {
            @error_log( sprintf( 'WCSIO bulk: %s post=%d att=%d sku=%s result=%s file=%s ts=%s', $type, $post_id, $attachment_id, $sku, $entry['result'], $file, $entry['ts'] ) );
        }
        return $entry;
    }

    private function should_skip_attachment( int $attachment_id, string $sku ) : bool {
        $applied = (bool) get_post_meta( $attachment_id, '_wcsio_overlay_applied', true );
        $prev_sku = (string) get_post_meta( $attachment_id, '_wcsio_overlay_sku', true );
        return $applied && ( $prev_sku !== '' ) && ( $prev_sku === $sku );
    }

    private function get_last_log() : array {
        $log = get_option( 'wcsio_last_regen_log', [] );
        if ( ! is_array( $log ) ) { return []; }
        $log['entries'] = isset( $log['entries'] ) && is_array( $log['entries'] ) ? $log['entries'] : [];
        return $log;
    }

    public function handle_ajax_log_page() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 ); }
        check_ajax_referer( 'wcsio_log_nonce' );

        $page = isset( $_REQUEST['page'] ) ? max( 1, (int) $_REQUEST['page'] ) : 1;
        $per  = isset( $_REQUEST['per_page'] ) ? max( 1, min( 200, (int) $_REQUEST['per_page'] ) ) : $this->log_page_size;

        $log = $this->get_last_log();
        $entries = $log['entries'];
        $total = count( $entries );
        $pages = max( 1, (int) ceil( $total / $per ) );
        $page  = min( $page, $pages );

        // Latest first
        $entries = array_reverse( $entries );
        $slice = array_slice( $entries, ( $page - 1 ) * $per, $per );

        wp_send_json_success( [
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
            'perPage' => $per,
            'rows'    => array_values( $slice ),
            'forced'  => ! empty( $log['forced'] ) ? 1 : 0,
            'runTime' => isset( $log['time'] ) ? (string) $log['time'] : '',
        ] );
    }

    public function handle_ajax_preview_overlay() : void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 ); }
        check_ajax_referer( 'wcsio_preview_nonce' );

        // SKU
        $sku = isset( $_POST['sku'] ) ? sanitize_text_field( (string) $_POST['sku'] ) : '';
        if ( '' === $sku ) { $sku = 'SKU-PREVIEW'; }

        // Optional override options
        $override = isset( $_POST['wcsio'] ) && is_array( $_POST['wcsio'] ) ? (array) $_POST['wcsio'] : [];

        $src_path = '';
        // Option A: uploaded file
        if ( ! empty( $_FILES['file'] ) && is_array( $_FILES['file'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $overrides = [ 'test_form' => false ];
            $upload = wp_handle_upload( $_FILES['file'], $overrides );
            if ( isset( $upload['error'] ) ) {
                wp_send_json_error( [ 'message' => (string) $upload['error'] ], 400 );
            }
            $src_path = (string) $upload['file'];
            if ( ! file_exists( $src_path ) ) {
                wp_send_json_error( [ 'message' => 'Uploaded file missing' ], 500 );
            }
        }
        // Option B: media library attachment id
        if ( '' === $src_path && ! empty( $_POST['media_id'] ) ) {
            $media_id = (int) $_POST['media_id'];
            if ( $media_id > 0 ) {
                $post = get_post( $media_id );
                if ( $post && $post->post_type === 'attachment' ) {
                    $mime = get_post_mime_type( $media_id );
                    if ( $mime && strpos( $mime, 'image/' ) === 0 ) {
                        $maybe = get_attached_file( $media_id );
                        if ( $maybe && file_exists( $maybe ) ) {
                            $src_path = $maybe;
                        }
                    }
                }
            }
        }
        if ( '' === $src_path ) {
            wp_send_json_error( [ 'message' => 'No image provided. Upload a file or choose from Media Library.' ], 400 );
        }

        // Copy to preview directory so we can modify without affecting original upload
        $upload_dir = wp_upload_dir();
        $preview_dir = trailingslashit( $upload_dir['basedir'] ) . 'wcsio-preview/';
        if ( ! file_exists( $preview_dir ) ) { wp_mkdir_p( $preview_dir ); }
        $ext  = pathinfo( $src_path, PATHINFO_EXTENSION );
        $dest = $preview_dir . 'preview-' . wp_generate_uuid4() . '.' . $ext;
        if ( ! copy( $src_path, $dest ) ) {
            wp_send_json_error( [ 'message' => 'Failed to prepare preview file' ], 500 );
        }
        // Remove original temp upload to avoid cluttering uploads root if it came from direct upload
        if ( ! empty( $_FILES['file'] ) && is_array( $_FILES['file'] ) ) {
            @unlink( $src_path );
        }

        // Apply overlay to copied file using overrides
        $ok = $this->processor->apply_overlay_to_file( $dest, $sku, $override );
        if ( ! $ok ) {
            @unlink( $dest );
            wp_send_json_error( [ 'message' => 'Failed to render overlay (check font path and server libraries).' ], 500 );
        }

        $dest_url = trailingslashit( $upload_dir['baseurl'] ) . 'wcsio-preview/' . basename( $dest );
        $size = @getimagesize( $dest );
        wp_send_json_success( [
            'url'    => $dest_url,
            'width'  => is_array( $size ) ? (int) $size[0] : null,
            'height' => is_array( $size ) ? (int) $size[1] : null,
        ] );
    }

    private function get_product_sku( int $product_id ) : string {
        if ( ! function_exists( 'wc_get_product' ) ) { return ''; }
        $product = \wc_get_product( $product_id );
        if ( ! $product ) { return ''; }
        $sku = (string) $product->get_sku();
        return trim( $sku );
    }
}
