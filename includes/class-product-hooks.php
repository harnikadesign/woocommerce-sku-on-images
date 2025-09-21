<?php
namespace WCSIO;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Product_Hooks {
    /** @var Settings */
    private $settings;
    /** @var Image_Processor */
    private $processor;

    public function __construct( Settings $settings, Image_Processor $processor ) {
        $this->settings  = $settings;
        $this->processor = $processor;

        // On upload
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'on_generate_metadata' ], 20, 2 );

        // On product meta updates (featured & gallery)
        add_action( 'added_post_meta', [ $this, 'on_added_post_meta' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'on_updated_post_meta' ], 10, 4 );

        // On product save
        add_action( 'save_post_product', [ $this, 'on_save_product' ], 20, 3 );
        add_action( 'save_post_product_variation', [ $this, 'on_save_product' ], 20, 3 );
    }

    public function on_generate_metadata( $metadata, int $attachment_id ) {
        $mime = get_post_mime_type( $attachment_id );
        if ( ! $mime || strpos( $mime, 'image/' ) !== 0 ) {
            return $metadata;
        }
        $parent_id = (int) get_post_field( 'post_parent', $attachment_id );
        if ( $parent_id ) {
            $ptype = get_post_type( $parent_id );
            if ( in_array( $ptype, [ 'product', 'product_variation' ], true ) ) {
                $product_id = ( 'product_variation' === $ptype ) ? (int) wp_get_post_parent_id( $parent_id ) : $parent_id;
                $sku = $this->get_product_sku( $product_id );
                if ( $sku ) {
                    $this->processor->process_attachment( $attachment_id, $sku );
                }
            }
        }
        return $metadata;
    }

    public function on_added_post_meta( $meta_id, $object_id, $meta_key, $_meta_value ) : void {
        $this->maybe_process_meta_change( (int) $object_id, (string) $meta_key, $_meta_value );
    }

    public function on_updated_post_meta( $meta_id, $object_id, $meta_key, $_meta_value ) : void {
        $this->maybe_process_meta_change( (int) $object_id, (string) $meta_key, $_meta_value );
    }

    private function maybe_process_meta_change( int $post_id, string $meta_key, $meta_value ) : void {
        $ptype = get_post_type( $post_id );
        if ( ! in_array( $ptype, [ 'product', 'product_variation' ], true ) ) {
            return;
        }
        $product_id = ( 'product_variation' === $ptype ) ? (int) wp_get_post_parent_id( $post_id ) : $post_id;
        $sku = $this->get_product_sku( $product_id );
        if ( ! $sku ) { return; }

        if ( '_thumbnail_id' === $meta_key ) {
            $att_id = (int) $meta_value;
            if ( $att_id ) {
                $this->processor->process_attachment( $att_id, $sku );
            }
        }

        if ( '_product_image_gallery' === $meta_key ) {
            $ids = is_array( $meta_value ) ? $meta_value : explode( ',', (string) $meta_value );
            foreach ( $ids as $maybe_id ) {
                $att_id = (int) $maybe_id;
                if ( $att_id ) {
                    $this->processor->process_attachment( $att_id, $sku );
                }
            }
        }
    }

    public function on_save_product( int $post_id, $post, $update ) : void {
        if ( 'product' !== get_post_type( $post_id ) && 'product_variation' !== get_post_type( $post_id ) ) {
            return;
        }
        $product_id = 'product_variation' === get_post_type( $post_id ) ? (int) wp_get_post_parent_id( $post_id ) : $post_id;
        $sku = $this->get_product_sku( $product_id );
        if ( ! $sku ) { return; }

        $thumb_id = (int) get_post_meta( $post_id, '_thumbnail_id', true );
        if ( $thumb_id ) {
            $this->processor->process_attachment( $thumb_id, $sku );
        }
        $gallery = (string) get_post_meta( $post_id, '_product_image_gallery', true );
        if ( $gallery ) {
            foreach ( array_filter( array_map( 'intval', explode( ',', $gallery ) ) ) as $att_id ) {
                $this->processor->process_attachment( (int) $att_id, $sku );
            }
        }
    }

    private function get_product_sku( int $product_id ) : string {
        if ( ! function_exists( 'wc_get_product' ) ) { return ''; }
        $product = \wc_get_product( $product_id );
        if ( ! $product ) { return ''; }
        $sku = (string) $product->get_sku();
        return trim( $sku );
    }
}

