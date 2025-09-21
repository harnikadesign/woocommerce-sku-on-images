<?php
namespace WCSIO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Settings {
    private $option_key = 'wcsio_options';
    private $options    = [];

    public function __construct() {
        $this->options = wp_parse_args( (array) get_option( $this->option_key, [] ), $this->defaults() );
    }

    public function defaults() : array {
        return [
            'enabled'    => 1,
            'position'   => 'bottom-right',
            'font_size'  => 18,
            'line_height'=> 1.2,
            'inner_padding' => 6,
            'inner_padding_top'    => 6,
            'inner_padding_right'  => 6,
            'inner_padding_bottom' => 6,
            'inner_padding_left'   => 6,
            'text_align' => 'center',
            'backup_original' => 1,
            'text_color' => '#FFFFFF',
            'bg_color'   => '#000000',
            'bg_opacity' => 50,
            'margin'     => 10,
            'margin_top'    => 10,
            'margin_right'  => 10,
            'margin_bottom' => 10,
            'margin_left'   => 10,
            'font_path'  => WCSIO_PLUGIN_DIR . 'assets/fonts/arial.ttf',
            'use_google_font'   => 0,
            'google_font_family' => 'Roboto',
        ];
    }

    public function get_all() : array {
        return $this->options;
    }

    public function get( string $key ) {
        $defaults = $this->defaults();
        return $this->options[ $key ] ?? ( $defaults[ $key ] ?? null );
    }

    public function update( array $data ) : void {
        $san = $this->sanitize( $data );
        $this->options = wp_parse_args( $san, $this->defaults() );
        update_option( $this->option_key, $this->options );
    }

    public function sanitize( array $data ) : array {
        $out = [];
        $out['enabled']    = isset( $data['enabled'] ) ? 1 : 0;
        $pos               = isset( $data['position'] ) ? sanitize_text_field( (string) $data['position'] ) : 'bottom-right';
        $out['position']   = in_array( $pos, [ 'top-left', 'top-right', 'bottom-left', 'bottom-right' ], true ) ? $pos : 'bottom-right';
        $out['font_size']  = max( 8, min( 100, (int) ( $data['font_size'] ?? 18 ) ) );
        $out['margin']     = max( 0, min( 200, (int) ( $data['margin'] ?? 10 ) ) );
        $out['margin_top']    = max( 0, min( 200, (int) ( $data['margin_top'] ?? $out['margin'] ) ) );
        $out['margin_right']  = max( 0, min( 200, (int) ( $data['margin_right'] ?? $out['margin'] ) ) );
        $out['margin_bottom'] = max( 0, min( 200, (int) ( $data['margin_bottom'] ?? $out['margin'] ) ) );
        $out['margin_left']   = max( 0, min( 200, (int) ( $data['margin_left'] ?? $out['margin'] ) ) );
        $lh                = isset( $data['line_height'] ) ? (float) $data['line_height'] : 1.2;
        $out['line_height']= max( 0.5, min( 5.0, $lh ) );
        $pad               = isset( $data['inner_padding'] ) ? (int) $data['inner_padding'] : 6;
        $out['inner_padding'] = max( 0, min( 200, $pad ) );
        $out['inner_padding_top']    = max( 0, min( 200, (int) ( $data['inner_padding_top'] ?? $out['inner_padding'] ) ) );
        $out['inner_padding_right']  = max( 0, min( 200, (int) ( $data['inner_padding_right'] ?? $out['inner_padding'] ) ) );
        $out['inner_padding_bottom'] = max( 0, min( 200, (int) ( $data['inner_padding_bottom'] ?? $out['inner_padding'] ) ) );
        $out['inner_padding_left']   = max( 0, min( 200, (int) ( $data['inner_padding_left'] ?? $out['inner_padding'] ) ) );
        $out['bg_opacity'] = max( 0, min( 100, (int) ( $data['bg_opacity'] ?? 50 ) ) );
        $out['text_color'] = $this->sanitize_color( $data['text_color'] ?? '#FFFFFF' );
        $out['bg_color']   = $this->sanitize_color( $data['bg_color'] ?? '#000000' );
        $align = isset( $data['text_align'] ) ? sanitize_text_field( (string) $data['text_align'] ) : 'center';
        $out['text_align'] = in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : 'center';
        $out['backup_original'] = isset( $data['backup_original'] ) ? 1 : 0;

        $font_path = isset( $data['font_path'] ) ? (string) $data['font_path'] : '';
        $font_path = $font_path ?: ( WCSIO_PLUGIN_DIR . 'assets/fonts/arial.ttf' );
        $out['font_path'] = $font_path;

        $out['use_google_font']    = isset( $data['use_google_font'] ) ? 1 : 0;
        $out['google_font_family'] = sanitize_text_field( (string) ( $data['google_font_family'] ?? 'Roboto' ) );
        return $out;
    }

    private function sanitize_color( string $hex ) : string {
        $hex = trim( $hex );
        if ( ! preg_match( '/^#?[0-9a-fA-F]{6}$/', $hex ) ) {
            return '#000000';
        }
        if ( $hex[0] !== '#' ) {
            $hex = '#' . $hex;
        }
        return strtoupper( $hex );
    }
}
