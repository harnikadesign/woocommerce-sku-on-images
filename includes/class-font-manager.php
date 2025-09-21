<?php
namespace WCSIO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Font_Manager {
    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function ensure_google_font( string $family ) : string {
        $family = trim( $family );
        if ( '' === $family ) { return ''; }

        $map = $this->font_map();
        $key = strtolower( str_replace( ' ', '', $family ) );
        if ( ! isset( $map[ $key ] ) ) {
            return '';
        }

        $rel_path = $map[ $key ]; // e.g., apache/roboto/Roboto-Regular.ttf
        $url = 'https://raw.githubusercontent.com/google/fonts/main/' . ltrim( $rel_path, '/' );

        $upload_dir = wp_upload_dir();
        if ( empty( $upload_dir['basedir'] ) ) { return ''; }
        $fonts_dir = trailingslashit( $upload_dir['basedir'] ) . 'wcsio-fonts/';
        if ( ! file_exists( $fonts_dir ) ) {
            wp_mkdir_p( $fonts_dir );
        }
        $dest = $fonts_dir . basename( $rel_path );

        if ( file_exists( $dest ) && filesize( $dest ) > 0 ) {
            return $dest;
        }

        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) { return ''; }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) { return ''; }
        $body = (string) wp_remote_retrieve_body( $response );
        if ( '' === $body ) { return ''; }

        $written = @file_put_contents( $dest, $body );
        if ( false === $written ) {
            return '';
        }
        return $dest;
    }

    private function font_map() : array {
        return [
            // Common Google Fonts mapped to their raw TTF path in google/fonts repo
            'roboto'     => 'apache/roboto/Roboto-Regular.ttf',
            'opensans'   => 'apache/opensans/OpenSans-Regular.ttf',
            'montserrat' => 'ofl/montserrat/Montserrat-Regular.ttf',
            'lato'       => 'ofl/lato/Lato-Regular.ttf',
            'poppins'    => 'ofl/poppins/Poppins-Regular.ttf',
            'oswald'     => 'ofl/oswald/Oswald-Regular.ttf',
            'rubik'      => 'ofl/rubik/Rubik-Regular.ttf',
            'inter'      => 'ofl/inter/Inter-Regular.ttf',
            'nunito'     => 'ofl/nunito/Nunito-Regular.ttf',
            'worksans'   => 'ofl/worksans/WorkSans-Regular.ttf',
            // Additional families
            'raleway'            => 'ofl/raleway/Raleway-Regular.ttf',
            'sourcesans3'        => 'ofl/sourcesans3/SourceSans3-Regular.ttf',
            'ptsans'             => 'ofl/ptsans/PTSans-Regular.ttf',
            'notosans'           => 'ofl/notosans/NotoSans-Regular.ttf',
            'notoserif'          => 'ofl/notoserif/NotoSerif-Regular.ttf',
            'merriweather'       => 'ofl/merriweather/Merriweather-Regular.ttf',
            'playfairdisplay'    => 'ofl/playfairdisplay/PlayfairDisplay-Regular.ttf',
            'ubuntu'             => 'ufl/ubuntu/Ubuntu-Regular.ttf',
            'robotocondensed'    => 'apache/robotocondensed/RobotoCondensed-Regular.ttf',
        ];
    }
}
