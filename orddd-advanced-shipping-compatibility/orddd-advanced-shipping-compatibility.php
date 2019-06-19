<?php

/*
* Plugin Name: Orddd Pro - Compatibility with WooCommerce Advanced Shipping Packages
* Description: This plugin allows customers to add different Custom Delivery Settings for different shipping packages from the  
WooCommerce Advanced Shipping Packages plugin by  Jeroen Sormani. 
* Author: Tyche Softwares
* Version: 1.0
* Author URI: https://www.tychesoftwares.com/about
* Contributor: Tyche Softwares, https://www.tychesoftwares.com/
* Copyright: © 2009-2019 Tyche Softwares.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Order Delivery Date Pro for WooCommerce
 *
 * Compatibility with WooCommerce Advanced Shipping Packages plugin
 *
 * @author      Tyche Softwares
 * @package     Order-Delivery-Date-Pro-for-WooCommerce/Advance-Shipping-Packages-Compatibility
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


/**
 * Compatibility with WooCommerce Advance Shipping Packages plugin
 *
 * @class orddd_advance_shipping_compatibility
 */
class orddd_advance_shipping_compatibility {
    /**
     * Default Constructor
     *
     * @since 1.0
     */

    public function __construct() {
        add_filter( 'orddd_custom_setting_shipping_methods', array( &$this, 'orddd_custom_setting_shipping_methods_callback' ), 10, 2 );

        add_filter( 'orddd_hidden_variables', array( &$this, 'orddd_hidden_variables_callback' ) );
        add_action( 'orddd_include_front_scripts', array( &$this, 'orddd_include_front_scripts_callback' ) ); 
    }

    /**
     * Adds the Shipping Methods for different Shipping packages under the Shipping Methods dropdown
     * on Add / Edit Custom Delivery Settings page. 
     * 
     * @param array $shipping_methods Shipping methods displayed under the Add Custom Delivery Settings page.
     * @param string $view Page on which the hook is being called. By default, it is on Edit Custom Delivery Settings page.
     * 
     * @return array
     * @since 1.0
     */

    public static function orddd_custom_setting_shipping_methods_callback( $shipping_methods, $view = 'edit_settings' ) {
        global $wpdb;
      
        $args = array(
            'post_type'   => 'shipping_package',
            'post_status' => 'publish',
            'hide_empty'  => 0
        );

        $shipping_packages = get_posts( $args );

        $shipping_zones = array();

        if ( defined( 'WOOCOMMERCE_VERSION' ) && 
            version_compare( WOOCOMMERCE_VERSION, "2.6", '>=' ) > 0 &&
            class_exists( 'WC_Shipping_Zones' ) ) {
                
            $shipping_zone_class = new WC_Shipping_Zones();
            if( method_exists ( $shipping_zone_class , 'get_zones' ) ) {
                $shipping_zones = $shipping_zone_class->get_zones();
            }
        
            $raw_shipping_method = "SELECT instance_id, method_id FROM `" . $wpdb->prefix . "woocommerce_shipping_zone_methods` WHERE zone_id = 0";
            $results = $wpdb->get_results( $raw_shipping_method );

            $wc_shipping         = WC_Shipping::instance();
            $allowed_classes     = $wc_shipping->get_shipping_method_class_names();

            $i = 1;
            foreach( $results as $result_key => $result_value ) {          
                if ( ! empty( $results ) && 
                    in_array( $result_value->method_id, array_keys( $allowed_classes ) ) &&
                    isset( $allowed_classes[ $result_value->method_id ] ) ) {
                        
                    $class_name = $allowed_classes[ $result_value->method_id ];
                    if ( is_object( $class_name ) ) {
                        $class_name = get_class( $class_name );
                    }

                    $default_shipping_method = new $class_name( $result_value->instance_id );
                    if( $default_shipping_method != "" && 
                        'table_rate' != $default_shipping_method->id && 
                        'flexible_shipping_ups' != $default_shipping_method->id && 
                        is_array( $shipping_zones ) ) {
                        
                        $key = count( $shipping_zones ) + 1;
                        $shipping_zones[ $key ][ 'id' ] = "0";
                        $shipping_zones[ $key ][ 'zone_name' ] = "Rest of the World";
                        $shipping_zones[ $key ][ 'shipping_methods' ][ $i ] = new stdClass;
                        $shipping_zones[ $key ][ 'shipping_methods' ][ $i ]->id = $default_shipping_method->id;
                        $shipping_zones[ $key ][ 'shipping_methods' ][ $i ]->instance_id = $default_shipping_method->instance_id;
                        $shipping_zones[ $key ][ 'shipping_methods' ][ $i ]->title = $default_shipping_method->title;

                        $i++;
                    }                          
                }
            }
        }  
        
        foreach( $shipping_packages as $sk => $sv ) {

            foreach( $shipping_zones as $shipping_default_key => $shipping_default_value ) {

                if( isset( $shipping_default_value[ 'shipping_methods' ] ) ) {

                    foreach( $shipping_default_value[ 'shipping_methods' ] as $key => $value ) {
                        
                        $title = $sv->post_title . " -> " . $shipping_default_value[ 'zone_name' ] . " -> " . $value->title;
                        $id = $value->id . ":" . $value->instance_id . ":" . $sv->ID;

                        if( $view == 'edit_settings' ) {
                            $shipping_methods[] = array(  "title" => $title,
                                "method_key" => $id
                            );    
                        } else if( $view == 'view_settings' ) {
                            $shipping_methods[] = array(  "shipping_default_zone_title" => $title,
                                "shipping_default_zone_id" => $id
                            ); 
                        }
                    }
                }
            }
        }

        return $shipping_methods;
    }

    /**
     * Returns the hidden variable with the shipping package id for which custom delivery settings have to be loaded
     * on the checkout page. 
     * 
     * @param string $var All other hidden variables. 
     * @since 1.0
     */

    public static function orddd_hidden_variables_callback( $var ) {

        $shipping_package = self::orddd_get_shipping_package();

        if( isset(  $shipping_package[ 0 ]->ID ) ) {
            $var .= '<input type="hidden" name="orddd_shipping_package_to_load" id="orddd_shipping_package_to_load" value="' . $shipping_package[ 0 ]->ID . '">';    
        }

        return $var;
    }

    /**
     * Includes the js files to be loaded for this feature on the checkout page or any other page where required.
     * 
     * @since 1.0 
     */
    public static function orddd_include_front_scripts_callback() {
        $shipping_package = self::orddd_get_shipping_package();

        wp_enqueue_script( 'orddd-advanced-shipping-compatibility', plugins_url( '/js/orddd-advanced-shipping-compatibility.js', __FILE__ ), '',  '1.0', false );

        if( isset(  $shipping_package[ 0 ]->ID ) ) {
            wp_localize_script( 'orddd-advanced-shipping-compatibility', 'orddd_advanced_shipping_compatibility_params', array( 'shipping_package_id' => $shipping_package[ 0 ]->ID ) );
        }
    }

    /** 
     * Return the shipping package for which custom delivery settings has to be loaded.
     * 
     * @since 1.0
     */
    public static function orddd_get_shipping_package() {
        /** Return the Shipping package title from the filter. If by default you have load custom settings for
         * that shipping package. Else settings for first shipping package will be loaded.
         *
         * Example for the filter
         * add_filter( 'orddd_shipping_package_to_load', 'orddd_shipping_package_to_load' );
         * function orddd_shipping_package_to_load() {
         *       return 'Maharashtra';
         * }
         * 
         * Where Maharastra is Shipping package name.
         */

        $method_title = '';
        if( has_filter( 'orddd_shipping_package_to_load' ) ) {
            $method_title = apply_filters( 'orddd_shipping_package_to_load', '' );
        }

        if( $method_title != '' ) {
            $args = array(
                'post_type'   => 'shipping_package',
                'title'  => $method_title
            );
        } else {
            $args = array(
                'post_type'   => 'shipping_package',
                'post_status' => 'publish',
                'hide_empty'  => 0,
                'menu_order'  => 0,
                'order'    => 'ASC'
            );
        }

        $shipping_package = get_posts( $args );       
        return $shipping_package;
    }

}

$orddd_advance_shipping_compatibility = new orddd_advance_shipping_compatibility();