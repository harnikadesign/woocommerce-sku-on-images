<?php
namespace WCSIO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Image_Processor {
    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function is_library_available() : bool {
        return extension_loaded( 'gd' ) || extension_loaded( 'imagick' );
    }

    public function process_attachment( int $attachment_id, string $sku, bool $force = false ) : bool {
        if ( empty( $sku ) ) {
            return false;
        }
        $enabled = (int) $this->settings->get( 'enabled' );
        if ( ! $enabled && ! $force ) {
            return false;
        }
        if ( get_post_meta( $attachment_id, '_wcsio_overlay_applied', true ) && ! $force ) {
            // Already applied
            return true;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }

        $opts = $this->settings->get_all();
        // Backup original if enabled and backup not already created
        if ( ! empty( $opts['backup_original'] ) ) {
            $existing_backup = (string) get_post_meta( $attachment_id, '_wcsio_backup_path', true );
            if ( empty( $existing_backup ) || ! file_exists( $existing_backup ) ) {
                $backup = $this->create_backup_copy( $attachment_id, $file );
                if ( $backup ) {
                    update_post_meta( $attachment_id, '_wcsio_backup_path', $backup );
                    update_post_meta( $attachment_id, '_wcsio_backup_time', current_time( 'mysql' ) );
                }
            }
        }
        // Only process original (full) image; do not touch generated sizes
        $success = $this->overlay_file( $file, $sku, $opts );

        if ( $success ) {
            update_post_meta( $attachment_id, '_wcsio_overlay_applied', 1 );
            update_post_meta( $attachment_id, '_wcsio_overlay_sku', sanitize_text_field( $sku ) );
        }
        return $success;
    }

    private function create_backup_copy( int $attachment_id, string $src_path ) : string {
        $uploads = wp_upload_dir();
        $base = trailingslashit( $uploads['basedir'] ) . 'wcsio-backups/';
        if ( ! file_exists( $base ) ) {
            wp_mkdir_p( $base );
        }
        $ext = pathinfo( $src_path, PATHINFO_EXTENSION );
        $name = basename( $src_path );
        $dest = $base . $attachment_id . '-' . $name;
        // Avoid overwriting existing backup
        if ( file_exists( $dest ) ) {
            return $dest;
        }
        if ( @copy( $src_path, $dest ) ) {
            return $dest;
        }
        return '';
    }

    /**
     * Apply overlay to an arbitrary file path using optional override options.
     */
    public function apply_overlay_to_file( string $path, string $sku, ?array $override_opts = null ) : bool {
        if ( ! $path || ! file_exists( $path ) ) {
            return false;
        }
        $opts = $this->build_options( $override_opts );
        return $this->overlay_file( $path, $sku, $opts );
    }

    private function build_options( ?array $override_opts ) : array {
        $opts = $this->settings->get_all();
        if ( is_array( $override_opts ) && ! empty( $override_opts ) ) {
            // Sanitize overrides and merge
            $san = $this->settings->sanitize( $override_opts );
            $opts = wp_parse_args( $san, $opts );
        }
        return $opts;
    }

    private function overlay_file( string $path, string $sku, array $opts ) : bool {
        // Prefer Imagick if available for better text rendering
        if ( extension_loaded( 'imagick' ) ) {
            try {
                $ok = $this->overlay_with_imagick( $path, $sku, $opts );
                if ( $ok ) {
                    return true;
                }
                // If Imagick is present but failed (e.g., unsupported format), try GD as fallback
            } catch ( \Throwable $e ) {
                // Continue to GD fallback
            }
        }
        if ( extension_loaded( 'gd' ) ) {
            return $this->overlay_with_gd( $path, $sku, $opts );
        }
        return false;
    }

    private function parse_hex_color( string $hex ) : array {
        $hex = ltrim( trim( $hex ), '#' );
        return [
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
        ];
    }

    private function overlay_with_imagick( string $path, string $sku, array $opts ) : bool {
        $img = new \Imagick( $path );

        $drawText = new \ImagickDraw();
        $drawBg   = new \ImagickDraw();

        $font_size  = (int) ( $opts['font_size'] ?? 18 );
        $margin     = (int) ( $opts['margin'] ?? 10 );
        $text_color = (string) ( $opts['text_color'] ?? '#FFFFFF' );
        $bg_color   = (string) ( $opts['bg_color'] ?? '#000000' );
        $bg_opacity = max( 0, min( 100, (int) ( $opts['bg_opacity'] ?? 50 ) ) );
        $position   = (string) ( $opts['position'] ?? 'bottom-right' );
        $font_path  = (string) ( $opts['font_path'] ?? '' );
        $text_align = (string) ( $opts['text_align'] ?? 'center' );

        if ( is_readable( $font_path ) ) {
            $drawText->setFont( $font_path );
        }
        $drawText->setFontSize( $font_size );
        $drawText->setFillColor( new \ImagickPixel( $text_color ) );
        $drawText->setTextAntialias( true );

        $metrics = $img->queryFontMetrics( $drawText, $sku );
        $tw = (int) ceil( $metrics['textWidth'] );
        $th = (int) ceil( $metrics['textHeight'] );
        $line_h = (float) ( $opts['line_height'] ?? 1.2 );
        $line_h = max( 0.5, min( 5.0, $line_h ) );
        $th_eff = (int) ceil( $th * $line_h );
        // Inner padding (per-side)
        $base_pad = isset( $opts['inner_padding'] ) ? max( 0, (int) $opts['inner_padding'] ) : (int) max( 4, round( $font_size * 0.35 ) );
        $pad_top    = isset( $opts['inner_padding_top'] ) ? max( 0, (int) $opts['inner_padding_top'] ) : $base_pad;
        $pad_right  = isset( $opts['inner_padding_right'] ) ? max( 0, (int) $opts['inner_padding_right'] ) : $base_pad;
        $pad_bottom = isset( $opts['inner_padding_bottom'] ) ? max( 0, (int) $opts['inner_padding_bottom'] ) : $base_pad;
        $pad_left   = isset( $opts['inner_padding_left'] ) ? max( 0, (int) $opts['inner_padding_left'] ) : $base_pad;

        // Compute origin based on position
        $iw = $img->getImageWidth();
        $ih = $img->getImageHeight();
        $box_w = $tw + $pad_left + $pad_right;
        $box_h = $th_eff + $pad_top + $pad_bottom;

        $ml = isset( $opts['margin_left'] ) ? (int) $opts['margin_left'] : $margin;
        $mr = isset( $opts['margin_right'] ) ? (int) $opts['margin_right'] : $margin;
        $mt = isset( $opts['margin_top'] ) ? (int) $opts['margin_top'] : $margin;
        $mb = isset( $opts['margin_bottom'] ) ? (int) $opts['margin_bottom'] : $margin;
        $x = $ml;
        $y = $mt + $pad_top + $th; // baseline y for annotate

        if ( $position === 'top-right' ) {
            $x = $iw - $box_w - $mr;
            $y = $mt + $pad_top + $th;
        } elseif ( $position === 'bottom-left' ) {
            $x = $ml;
            $y = $ih - $mb - $pad_bottom;
        } elseif ( $position === 'bottom-right' ) {
            $x = $iw - $box_w - $mr;
            $y = $ih - $mb - $pad_bottom;
        }

        // Draw background rectangle with opacity
        [$br, $bg, $bb] = $this->parse_hex_color( $bg_color );
        $alpha          = max( 0.0, min( 1.0, $bg_opacity / 100 ) );
        $drawBg->setFillColor( new \ImagickPixel( sprintf( 'rgba(%d,%d,%d,%.3f)', $br, $bg, $bb, $alpha ) ) );
        $drawBg->rectangle( $x, $y - $th_eff - $pad_top, $x + $box_w, $y + $pad_bottom );
        $img->drawImage( $drawBg );

        // Draw text with horizontal alignment inside the background box
        $tx = $x + $pad_left;
        if ( $text_align === 'center' ) {
            $tx = $x + (int) round( ( $box_w - $tw ) / 2 );
        } elseif ( $text_align === 'right' ) {
            $tx = $x + $box_w - $pad_right - $tw;
        }
        $img->annotateImage( $drawText, $tx, $y, 0, $sku );

        $result = $img->writeImage( $path );
        $img->clear();
        $img->destroy();
        return (bool) $result;
    }

    private function overlay_with_gd( string $path, string $sku, array $opts ) : bool {
        $info = @getimagesize( $path );
        if ( ! is_array( $info ) ) {
            return false;
        }
        $type = $info[2];
        switch ( $type ) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg( $path );
                $save = function( $res, $p ) { return imagejpeg( $res, $p, 90 ); };
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng( $path );
                $save = function( $res, $p ) { return imagepng( $res, $p ); };
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif( $path );
                $save = function( $res, $p ) { return imagegif( $res, $p ); };
                break;
            default:
                // WebP support (if GD was compiled with it)
                if ( defined( 'IMAGETYPE_WEBP' ) && $type === IMAGETYPE_WEBP && function_exists( 'imagecreatefromwebp' ) && function_exists( 'imagewebp' ) ) {
                    $img = @imagecreatefromwebp( $path );
                    $save = function( $res, $p ) { return imagewebp( $res, $p, 90 ); };
                    break;
                }
                return false;
        }
        if ( ! $img ) { return false; }

        $font_size  = (int) ( $opts['font_size'] ?? 18 );
        $margin     = (int) ( $opts['margin'] ?? 10 );
        $text_color = (string) ( $opts['text_color'] ?? '#FFFFFF' );
        $bg_color   = (string) ( $opts['bg_color'] ?? '#000000' );
        $bg_opacity = max( 0, min( 100, (int) ( $opts['bg_opacity'] ?? 50 ) ) );
        $position   = (string) ( $opts['position'] ?? 'bottom-right' );
        $font_path  = (string) ( $opts['font_path'] ?? '' );
        $text_align = (string) ( $opts['text_align'] ?? 'center' );

        $iw = imagesx( $img );
        $ih = imagesy( $img );

        // Enable alpha blending for PNG
        imagealphablending( $img, true );
        imagesavealpha( $img, true );

        [$tr, $tg, $tb] = $this->parse_hex_color( $text_color );
        [$br, $bg, $bb] = $this->parse_hex_color( $bg_color );
        $alpha = 127 - (int) round( ( $bg_opacity / 100 ) * 127 ); // GD alpha: 0 opaque, 127 transparent

        $text_col = imagecolorallocate( $img, $tr, $tg, $tb );
        $bg_col   = imagecolorallocatealpha( $img, $br, $bg, $bb, $alpha );

        // Inner padding (per-side)
        $base_pad   = isset( $opts['inner_padding'] ) ? max( 0, (int) $opts['inner_padding'] ) : (int) max( 4, round( $font_size * 0.35 ) );
        $pad_top    = isset( $opts['inner_padding_top'] ) ? max( 0, (int) $opts['inner_padding_top'] ) : $base_pad;
        $pad_right  = isset( $opts['inner_padding_right'] ) ? max( 0, (int) $opts['inner_padding_right'] ) : $base_pad;
        $pad_bottom = isset( $opts['inner_padding_bottom'] ) ? max( 0, (int) $opts['inner_padding_bottom'] ) : $base_pad;
        $pad_left   = isset( $opts['inner_padding_left'] ) ? max( 0, (int) $opts['inner_padding_left'] ) : $base_pad;

        $use_ttf = function_exists( 'imagettfbbox' ) && is_readable( $font_path );
        if ( $use_ttf ) {
            $bbox = @imagettfbbox( $font_size, 0, $font_path, $sku );
        } else {
            $bbox = false;
        }

        if ( $bbox ) {
            // TTF path
            $tw = abs( $bbox[2] - $bbox[0] );
            $th = abs( $bbox[7] - $bbox[1] );
            $line_h = (float) ( $opts['line_height'] ?? 1.2 );
            $line_h = max( 0.5, min( 5.0, $line_h ) );
            $th_eff = (int) ceil( $th * $line_h );
            $box_w = $tw + $pad_left + $pad_right;
            $box_h = $th_eff + $pad_top + $pad_bottom;
            $ml = isset( $opts['margin_left'] ) ? (int) $opts['margin_left'] : $margin;
            $mr = isset( $opts['margin_right'] ) ? (int) $opts['margin_right'] : $margin;
            $mt = isset( $opts['margin_top'] ) ? (int) $opts['margin_top'] : $margin;
            $mb = isset( $opts['margin_bottom'] ) ? (int) $opts['margin_bottom'] : $margin;
            $x = $ml;
            $y = $mt + $pad_top + $th; // baseline remains based on actual text height
            if ( $position === 'top-right' ) {
                $x = $iw - $box_w - $mr;
                $y = $mt + $pad_top + $th;
            } elseif ( $position === 'bottom-left' ) {
                $x = $ml;
                $y = $ih - $mb - $pad_bottom;
            } elseif ( $position === 'bottom-right' ) {
                $x = $iw - $box_w - $mr;
                $y = $ih - $mb - $pad_bottom;
            }
            // Background
            imagefilledrectangle( $img, $x, $y - $th_eff - $pad_top, $x + $box_w, $y + $pad_bottom, $bg_col );
            // Text
            $tx = $x + $pad_left;
            if ( $text_align === 'center' ) {
                $tx = $x + (int) round( ( $box_w - $tw ) / 2 );
            } elseif ( $text_align === 'right' ) {
                $tx = $x + $box_w - $pad_right - $tw;
            }
            @imagettftext( $img, $font_size, 0, $tx, $y, $text_col, $font_path, $sku );
        } else {
            // Fallback to built-in GD font
            $font = 5; // built-in font size
            $tw = imagefontwidth( $font ) * strlen( $sku );
            $th = imagefontheight( $font );
            $line_h = (float) ( $opts['line_height'] ?? 1.2 );
            $line_h = max( 0.5, min( 5.0, $line_h ) );
            $th_eff = (int) ceil( $th * $line_h );
            $box_w = $tw + $pad_left + $pad_right;
            $box_h = $th_eff + $pad_top + $pad_bottom;
            $ml = isset( $opts['margin_left'] ) ? (int) $opts['margin_left'] : $margin;
            $mr = isset( $opts['margin_right'] ) ? (int) $opts['margin_right'] : $margin;
            $mt = isset( $opts['margin_top'] ) ? (int) $opts['margin_top'] : $margin;
            $mb = isset( $opts['margin_bottom'] ) ? (int) $opts['margin_bottom'] : $margin;
            $x = $ml;
            $y = $mt; // top-left of rectangle
            if ( $position === 'top-right' ) {
                $x = $iw - $box_w - $mr;
                $y = $mt;
            } elseif ( $position === 'bottom-left' ) {
                $x = $ml;
                $y = $ih - $box_h - $mb;
            } elseif ( $position === 'bottom-right' ) {
                $x = $iw - $box_w - $mr;
                $y = $ih - $box_h - $mb;
            }
            imagefilledrectangle( $img, $x, $y, $x + $box_w, $y + $box_h, $bg_col );
            $tx = $x + $pad_left;
            if ( $text_align === 'center' ) {
                $tx = $x + (int) round( ( $box_w - $tw ) / 2 );
            } elseif ( $text_align === 'right' ) {
                $tx = $x + $box_w - $pad_right - $tw;
            }
            imagestring( $img, $font, $tx, $y + $pad_top, $sku, $text_col );
        }

        // Save back
        // Save back (uses appropriate saver selected above)
        $saved = isset( $save ) ? (bool) $save( $img, $path ) : false;
        imagedestroy( $img );
        return (bool) $saved;
    }
}
