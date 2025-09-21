<?php
/** @var array $data */
$opts = $data['options'];
?>
<div class="wrap wcsio-wrap">
  <h1><?php echo esc_html__( 'SKU Image Overlay', 'woocommerce-sku-on-images' ); ?></h1>

  <?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="updated notice"><p><?php esc_html_e( 'Settings saved.', 'woocommerce-sku-on-images' ); ?></p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['font_dl'] ) && $_GET['font_dl'] === 'ok' ) : ?>
    <div class="updated notice"><p><?php esc_html_e( 'Google Font downloaded and set successfully.', 'woocommerce-sku-on-images' ); ?></p></div>
  <?php elseif ( isset( $_GET['font_dl'] ) && $_GET['font_dl'] === 'fail' ) : ?>
    <div class="error notice"><p><?php esc_html_e( 'Failed to download Google Font. Please check server connectivity or choose another font.', 'woocommerce-sku-on-images' ); ?></p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['log_cleared'] ) ) : ?>
    <div class="updated notice"><p><?php esc_html_e( 'Log cleared.', 'woocommerce-sku-on-images' ); ?></p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['restored'] ) ) : ?>
    <div class="updated notice"><p><?php echo esc_html( sprintf( __( 'Restored %d originals from backups.', 'woocommerce-sku-on-images' ), (int) $_GET['restored'] ) ); ?></p></div>
  <?php endif; ?>
  <?php if ( isset( $_GET['regenerated'] ) ) : ?>
    <div class="updated notice"><p>
      <?php
        $forced = ! empty( $_GET['forced'] );
        $msg = $forced ? __( 'Regenerated (forced) overlays for %d images.', 'woocommerce-sku-on-images' ) : __( 'Regenerated overlays for %d images.', 'woocommerce-sku-on-images' );
        echo esc_html( sprintf( $msg, (int) $_GET['regenerated'] ) );
      ?>
      <?php if ( $forced ) : ?>
        <span class="wcsio-badge wcsio-badge--forced"><?php esc_html_e( 'Forced', 'woocommerce-sku-on-images' ); ?></span>
      <?php endif; ?>
    </p></div>
  <?php endif; ?>

  <div class="wcsio-status">
    <strong><?php esc_html_e( 'System Status:', 'woocommerce-sku-on-images' ); ?></strong>
    <p>
      <?php echo $data['has_gd'] ? '✅ GD' : '❌ GD'; ?> &nbsp;
      <?php echo $data['has_imagick'] ? '✅ Imagick' : '❌ Imagick'; ?>
    </p>
  </div>

  <div class="wcsio-grid">
    <div class="wcsio-card">
      <h2><?php esc_html_e( 'Overlay Settings', 'woocommerce-sku-on-images' ); ?></h2>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wcsio_save_settings' ); ?>
        <input type="hidden" name="action" value="wcsio_save_settings" />

        <div class="wcsio-field">
          <label>
            <input type="checkbox" name="wcsio[enabled]" value="1" <?php checked( (int) $opts['enabled'], 1 ); ?> />
            <?php esc_html_e( 'Enable overlay processing', 'woocommerce-sku-on-images' ); ?>
          </label>
        </div>
        <div class="wcsio-field">
          <label>
            <input type="checkbox" name="wcsio[backup_original]" value="1" <?php checked( (int) ( $opts['backup_original'] ?? 0 ), 1 ); ?> />
            <?php esc_html_e( 'Backup original before overlay (stores a pristine copy in uploads/wcsio-backups)', 'woocommerce-sku-on-images' ); ?>
          </label>
        </div>

        <div class="wcsio-field">
          <label for="wcsio_position"><?php esc_html_e( 'Overlay position', 'woocommerce-sku-on-images' ); ?></label>
          <select id="wcsio_position" name="wcsio[position]">
            <?php foreach ( [ 'top-left' => 'Top Left', 'top-right' => 'Top Right', 'bottom-left' => 'Bottom Left', 'bottom-right' => 'Bottom Right' ] as $val => $label ) : ?>
              <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['position'], $val ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="wcsio-field">
          <label for="wcsio_font_size"><?php esc_html_e( 'Font size (px)', 'woocommerce-sku-on-images' ); ?></label>
          <input type="number" min="8" max="100" id="wcsio_font_size" name="wcsio[font_size]" value="<?php echo esc_attr( (int) $opts['font_size'] ); ?>" />
        </div>
        <div class="wcsio-field">
          <label for="wcsio_line_height"><?php esc_html_e( 'Line height (em)', 'woocommerce-sku-on-images' ); ?></label>
          <input type="number" step="0.1" min="0.5" max="5" id="wcsio_line_height" name="wcsio[line_height]" value="<?php echo esc_attr( isset( $opts['line_height'] ) ? $opts['line_height'] : 1.2 ); ?>" />
        </div>
        <div class="wcsio-field">
          <label for="wcsio_inner_padding"><?php esc_html_e( 'Inner padding (px)', 'woocommerce-sku-on-images' ); ?></label>
          <input type="number" min="0" max="200" id="wcsio_inner_padding" name="wcsio[inner_padding]" value="<?php echo esc_attr( isset( $opts['inner_padding'] ) ? (int) $opts['inner_padding'] : 6 ); ?>" />
          <div class="wcsio-margin-grid" style="margin-top:8px;">
            <div class="wcsio-mg-top">
              <input type="number" min="0" max="200" name="wcsio[inner_padding_top]" value="<?php echo esc_attr( (int) ( $opts['inner_padding_top'] ?? $opts['inner_padding'] ) ); ?>" placeholder="<?php esc_attr_e('Top','woocommerce-sku-on-images'); ?>" />
            </div>
            <div class="wcsio-mg-left">
              <input type="number" min="0" max="200" name="wcsio[inner_padding_left]" value="<?php echo esc_attr( (int) ( $opts['inner_padding_left'] ?? $opts['inner_padding'] ) ); ?>" placeholder="<?php esc_attr_e('Left','woocommerce-sku-on-images'); ?>" />
            </div>
            <div class="wcsio-mg-center">⇲</div>
            <div class="wcsio-mg-right">
              <input type="number" min="0" max="200" name="wcsio[inner_padding_right]" value="<?php echo esc_attr( (int) ( $opts['inner_padding_right'] ?? $opts['inner_padding'] ) ); ?>" placeholder="<?php esc_attr_e('Right','woocommerce-sku-on-images'); ?>" />
            </div>
            <div class="wcsio-mg-bottom">
              <input type="number" min="0" max="200" name="wcsio[inner_padding_bottom]" value="<?php echo esc_attr( (int) ( $opts['inner_padding_bottom'] ?? $opts['inner_padding'] ) ); ?>" placeholder="<?php esc_attr_e('Bottom','woocommerce-sku-on-images'); ?>" />
            </div>
          </div>
          <p class="description"><?php esc_html_e( 'Set per-side inner padding for the background box (overrides the single value above if provided).', 'woocommerce-sku-on-images' ); ?></p>
        </div>

        <div class="wcsio-field">
          <label for="wcsio_margin"><?php esc_html_e( 'Margin (px)', 'woocommerce-sku-on-images' ); ?></label>
          <input type="number" min="0" max="200" id="wcsio_margin" name="wcsio[margin]" value="<?php echo esc_attr( (int) $opts['margin'] ); ?>" />
        </div>
        <div class="wcsio-field">
          <label><?php esc_html_e( 'Outer margins (px)', 'woocommerce-sku-on-images' ); ?></label>
          <div class="wcsio-margin-grid">
            <div class="wcsio-mg-top">
              <input type="number" min="0" max="200" name="wcsio[margin_top]" value="<?php echo esc_attr( (int) ( $opts['margin_top'] ?? $opts['margin'] ) ); ?>" placeholder="<?php esc_attr_e('Top','woocommerce-sku-on-images'); ?>" />
            </div>
            <div class="wcsio-mg-left">
              <input type="number" min="0" max="200" name="wcsio[margin_left]" value="<?php echo esc_attr( (int) ( $opts['margin_left'] ?? $opts['margin'] ) ); ?>" placeholder="<?php esc_attr_e('Left','woocommerce-sku-on-images'); ?>" />
            </div>
            <div class="wcsio-mg-center">⟲</div>
            <div class="wcsio-mg-right">
              <input type="number" min="0" max="200" name="wcsio[margin_right]" value="<?php echo esc_attr( (int) ( $opts['margin_right'] ?? $opts['margin'] ) ); ?>" placeholder="<?php esc_attr_e('Right','woocommerce-sku-on-images'); ?>" />
            </div>
            <div class="wcsio-mg-bottom">
              <input type="number" min="0" max="200" name="wcsio[margin_bottom]" value="<?php echo esc_attr( (int) ( $opts['margin_bottom'] ?? $opts['margin'] ) ); ?>" placeholder="<?php esc_attr_e('Bottom','woocommerce-sku-on-images'); ?>" />
            </div>
          </div>
          <p class="description"><?php esc_html_e( 'Set top, right, bottom and left margins for overlay placement.', 'woocommerce-sku-on-images' ); ?></p>
        </div>

        <div class="wcsio-field">
          <label for="wcsio_text_color"><?php esc_html_e( 'Text color', 'woocommerce-sku-on-images' ); ?></label>
          <input type="text" class="wcsio-color" id="wcsio_text_color" name="wcsio[text_color]" value="<?php echo esc_attr( $opts['text_color'] ); ?>" />
        </div>

        <div class="wcsio-field">
          <label for="wcsio_bg_color"><?php esc_html_e( 'Background color', 'woocommerce-sku-on-images' ); ?></label>
          <input type="text" class="wcsio-color" id="wcsio_bg_color" name="wcsio[bg_color]" value="<?php echo esc_attr( $opts['bg_color'] ); ?>" />
        </div>

        <div class="wcsio-field">
          <label for="wcsio_bg_opacity"><?php esc_html_e( 'Background opacity (%)', 'woocommerce-sku-on-images' ); ?></label>
          <input type="number" min="0" max="100" id="wcsio_bg_opacity" name="wcsio[bg_opacity]" value="<?php echo esc_attr( (int) $opts['bg_opacity'] ); ?>" />
        </div>

        <div class="wcsio-field">
          <label for="wcsio_text_align"><?php esc_html_e( 'Text alignment', 'woocommerce-sku-on-images' ); ?></label>
          <select id="wcsio_text_align" name="wcsio[text_align]">
            <?php foreach ( [ 'left' => __( 'Left', 'woocommerce-sku-on-images' ), 'center' => __( 'Center', 'woocommerce-sku-on-images' ), 'right' => __( 'Right', 'woocommerce-sku-on-images' ) ] as $val => $label ) : ?>
              <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['text_align'] ?? 'center', $val ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="wcsio-field">
          <label for="wcsio_font_path"><?php esc_html_e( 'Font file (TTF) path', 'woocommerce-sku-on-images' ); ?></label>
          <input type="text" id="wcsio_font_path" name="wcsio[font_path]" value="<?php echo esc_attr( $opts['font_path'] ); ?>" size="80" />
          <p class="description"><?php echo esc_html( sprintf( __( 'Default: %s', 'woocommerce-sku-on-images' ), WCSIO_PLUGIN_DIR . 'assets/fonts/arial.ttf' ) ); ?></p>
        </div>

        <hr />
        <div class="wcsio-field">
          <label>
            <input type="checkbox" name="wcsio[use_google_font]" value="1" <?php checked( (int) $opts['use_google_font'], 1 ); ?> />
            <?php esc_html_e( 'Use Google Font (auto-download TTF)', 'woocommerce-sku-on-images' ); ?>
          </label>
        </div>
        <div class="wcsio-field">
          <label for="wcsio_google_font_family"><?php esc_html_e( 'Google Font family', 'woocommerce-sku-on-images' ); ?></label>
          <select id="wcsio_google_font_family" name="wcsio[google_font_family]">
            <?php foreach ( [ 'Roboto', 'Open Sans', 'Montserrat', 'Lato', 'Poppins', 'Oswald', 'Rubik', 'Inter', 'Nunito', 'Work Sans', 'Raleway', 'Source Sans 3', 'PT Sans', 'Noto Sans', 'Noto Serif', 'Merriweather', 'Playfair Display', 'Ubuntu', 'Roboto Condensed' ] as $family ) : ?>
              <option value="<?php echo esc_attr( $family ); ?>" <?php selected( strtolower( str_replace(' ', '', $opts['google_font_family'] ) ), strtolower( str_replace(' ', '', $family ) ) ); ?>><?php echo esc_html( $family ); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="description"><?php esc_html_e( 'On save, the plugin downloads the selected font TTF into uploads/wcsio-fonts and updates the font path automatically.', 'woocommerce-sku-on-images' ); ?></p>
        </div>

        <div class="wcsio-field">
          <label><?php esc_html_e( 'Current font path status', 'woocommerce-sku-on-images' ); ?></label>
          <p style="margin:4px 0;">
            <?php
              $path = (string) ( $opts['font_path'] ?? '' );
              $ok   = $path && is_readable( $path );
              echo $ok ? '✅ ' : '❌ ';
              echo esc_html( $path ?: __( 'No font path set', 'woocommerce-sku-on-images' ) );
            ?>
          </p>
        </div>

        <div class="wcsio-actions">
          <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'woocommerce-sku-on-images' ); ?></button>
        </div>
      </form>
    </div>

    <div class="wcsio-card">
      <h2><?php esc_html_e( 'Bulk Regenerate', 'woocommerce-sku-on-images' ); ?></h2>
      <p><?php esc_html_e( 'Regenerate SKU overlays for all product images. This can take a while on large catalogs.', 'woocommerce-sku-on-images' ); ?></p>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wcsio_regenerate' ); ?>
        <input type="hidden" name="action" value="wcsio_regenerate" />
        <label style="display:block; margin:8px 0;">
          <input type="checkbox" name="force_overwrite" value="1" />
          <?php esc_html_e( 'Force overwrite existing overlays (re-render originals even if SKU already applied).', 'woocommerce-sku-on-images' ); ?>
        </label>
        <button type="submit" class="button">⚙️ <?php esc_html_e( 'Run Regeneration', 'woocommerce-sku-on-images' ); ?></button>
      </form>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
        <?php wp_nonce_field( 'wcsio_restore_backups' ); ?>
        <input type="hidden" name="action" value="wcsio_restore_backups" />
        <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'This will restore original files from backups where available, overwriting current originals. Continue?', 'woocommerce-sku-on-images' ) ); ?>');">↩️ <?php esc_html_e( 'Restore originals from backups', 'woocommerce-sku-on-images' ); ?></button>
      </form>

      <h2 style="margin-top:24px;"><?php esc_html_e( 'Preview', 'woocommerce-sku-on-images' ); ?></h2>
      <form id="wcsio-preview-form" enctype="multipart/form-data" onsubmit="return false;">
        <div class="wcsio-field">
          <label for="wcsio_preview_sku"><?php esc_html_e( 'Sample SKU', 'woocommerce-sku-on-images' ); ?></label>
          <input type="text" id="wcsio_preview_sku" value="SKU-PREVIEW" />
        </div>
        <div class="wcsio-field">
          <label for="wcsio_preview_file"><?php esc_html_e( 'Upload sample image', 'woocommerce-sku-on-images' ); ?></label>
          <input type="file" id="wcsio_preview_file" accept="image/*" />
          <div style="margin-top:6px;">
            <button type="button" class="button" id="wcsio_pick_media_btn"><?php esc_html_e( 'Choose from Media Library', 'woocommerce-sku-on-images' ); ?></button>
            <input type="hidden" id="wcsio_media_id" value="" />
            <span id="wcsio_media_info" class="description" style="margin-left:8px;"></span>
          </div>
        </div>
        <div class="wcsio-actions">
          <button type="button" class="button button-primary" id="wcsio_preview_btn"><?php esc_html_e( 'Generate Preview', 'woocommerce-sku-on-images' ); ?></button>
          <button type="button" class="button" id="wcsio_preview_reset"><?php esc_html_e( 'Reset Preview', 'woocommerce-sku-on-images' ); ?></button>
          <span class="spinner" style="float:none;"></span>
        </div>
      </form>
      <div class="wcsio-preview" id="wcsio_preview_output">
        <?php esc_html_e( 'Choose an image and click Generate Preview to see overlay with current settings (unsaved changes included).', 'woocommerce-sku-on-images' ); ?>
      </div>
    </div>

    <div class="wcsio-card" id="wcsio_log_card">
      <h2><?php esc_html_e( 'Last Regeneration Log', 'woocommerce-sku-on-images' ); ?></h2>
      <?php if ( ! empty( $data['last_log']['entries'] ) ) : ?>
        <p>
          <?php
            $last_time = isset( $data['last_log']['time'] ) ? (string) $data['last_log']['time'] : '-';
            $forced_run = ! empty( $data['last_log']['forced'] );
            $label = sprintf( __( 'Last run: %s', 'woocommerce-sku-on-images' ), $last_time );
            echo esc_html( $label );
            if ( $forced_run ) {
              echo ' '; echo '<span class="dashicons dashicons-warning"></span> <strong>' . esc_html__( 'Forced', 'woocommerce-sku-on-images' ) . '</strong>';
            }
          ?>
        </p>
        <div class="wcsio-log-intensity" style="margin:8px 0; display:flex; gap:8px; align-items:center;">
          <label for="wcsio_intensity_select" style="margin-right:4px;">
            <?php esc_html_e( 'Highlight intensity', 'woocommerce-sku-on-images' ); ?>
          </label>
          <select id="wcsio_intensity_select">
            <option value="subtle"><?php esc_html_e( 'Subtle', 'woocommerce-sku-on-images' ); ?></option>
            <option value="medium" selected><?php esc_html_e( 'Medium', 'woocommerce-sku-on-images' ); ?></option>
            <option value="strong"><?php esc_html_e( 'Strong', 'woocommerce-sku-on-images' ); ?></option>
          </select>
        </div>
        <div style="display:flex; gap:8px; align-items:center; margin: 8px 0 16px;">
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wcsio_clear_log' ); ?>
            <input type="hidden" name="action" value="wcsio_clear_log" />
            <button type="submit" class="button button-secondary"><?php esc_html_e( 'Clear Log', 'woocommerce-sku-on-images' ); ?></button>
          </form>
          <form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wcsio_export_log" />
            <?php echo wp_nonce_field( 'wcsio_export_log', '_wpnonce', true, false ); ?>
            <input type="hidden" name="format" value="json" />
            <button type="submit" class="button"><?php esc_html_e( 'Export JSON', 'woocommerce-sku-on-images' ); ?></button>
          </form>
          <form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wcsio_export_log" />
            <?php echo wp_nonce_field( 'wcsio_export_log', '_wpnonce', true, false ); ?>
            <input type="hidden" name="format" value="csv" />
            <button type="submit" class="button"><?php esc_html_e( 'Export CSV', 'woocommerce-sku-on-images' ); ?></button>
          </form>
        </div>
        <div class="wcsio-log-filters" style="margin:8px 0; display:flex; gap:12px; align-items:center;">
          <label><input type="checkbox" id="wcsio_filter_ok" checked /> <?php esc_html_e( 'Show OK', 'woocommerce-sku-on-images' ); ?></label>
          <label><input type="checkbox" id="wcsio_filter_skip" checked /> <?php esc_html_e( 'Show Skips', 'woocommerce-sku-on-images' ); ?></label>
          <label><input type="checkbox" id="wcsio_filter_fail" checked /> <?php esc_html_e( 'Show Failures', 'woocommerce-sku-on-images' ); ?></label>
        </div>
        <table class="widefat striped wcsio-log-table" data-initialized="0">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Timestamp', 'woocommerce-sku-on-images' ); ?></th>
              <th><?php esc_html_e( 'Type', 'woocommerce-sku-on-images' ); ?></th>
              <th><?php esc_html_e( 'Post ID', 'woocommerce-sku-on-images' ); ?></th>
              <th><?php esc_html_e( 'Attachment ID', 'woocommerce-sku-on-images' ); ?></th>
              <th><?php esc_html_e( 'SKU', 'woocommerce-sku-on-images' ); ?></th>
              <th><?php esc_html_e( 'Result', 'woocommerce-sku-on-images' ); ?></th>
              <th><?php esc_html_e( 'File', 'woocommerce-sku-on-images' ); ?></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="tablenav bottom" style="display:flex; align-items:center; gap:8px; margin-top:8px;">
          <button type="button" class="button wcsio-log-prev">« <?php esc_html_e( 'Previous', 'woocommerce-sku-on-images' ); ?></button>
          <span class="wcsio-log-page">1</span> / <span class="wcsio-log-pages">1</span>
          <button type="button" class="button wcsio-log-next"><?php esc_html_e( 'Next', 'woocommerce-sku-on-images' ); ?> »</button>
        </div>
        <p class="description"><?php esc_html_e( 'Use the pager to navigate the full log.', 'woocommerce-sku-on-images' ); ?></p>
      <?php else : ?>
        <p><?php esc_html_e( 'No log available yet. Run a regeneration to populate this log.', 'woocommerce-sku-on-images' ); ?></p>
        <div style="display:flex; gap:8px; align-items:center; margin: 8px 0 16px;">
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wcsio_clear_log' ); ?>
            <input type="hidden" name="action" value="wcsio_clear_log" />
            <button type="submit" class="button button-secondary" disabled><?php esc_html_e( 'Clear Log', 'woocommerce-sku-on-images' ); ?></button>
          </form>
          <form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wcsio_export_log" />
            <?php echo wp_nonce_field( 'wcsio_export_log', '_wpnonce', true, false ); ?>
            <input type="hidden" name="format" value="json" />
            <button type="submit" class="button" disabled><?php esc_html_e( 'Export JSON', 'woocommerce-sku-on-images' ); ?></button>
          </form>
          <form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wcsio_export_log" />
            <?php echo wp_nonce_field( 'wcsio_export_log', '_wpnonce', true, false ); ?>
            <input type="hidden" name="format" value="csv" />
            <button type="submit" class="button" disabled><?php esc_html_e( 'Export CSV', 'woocommerce-sku-on-images' ); ?></button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
