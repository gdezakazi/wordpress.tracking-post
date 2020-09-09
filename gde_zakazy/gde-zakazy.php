<?php
/*
	Plugin Name: ГДЕЗАКАЗЫ.РФ для WooCommerce
	Plugin URI: http://гдезаказы.рф/
	Description: Трекинг заказов
	Version: 1.0
	Author: гдезаказы.рф
	Author URI: http://гдезаказы.рф/

	Copyright: © гдезаказы.рф
*/

defined('ABSPATH') or die("No script kiddies please!");

if (!function_exists('is_woocommerce_active')) {
    function is_woocommerce_active() {
        $activePlugins = (array)get_option('active_plugins', array());
        if (is_multisite()) {
            $activePlugins = array_merge($activePlugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('woocommerce/woocommerce.php', $activePlugins) || array_key_exists('woocommerce/woocommerce.php', $activePlugins);
    }
}

if (!function_exists('get_order_id')) {
    function get_order_id($order) {
        return (method_exists($order, 'get_id')) ? $order->get_id() : $order->id;
    }
}

if (!function_exists('order_post_meta_getter')) {
    function order_post_meta_getter($order, $attr) {
        $meta = get_post_meta(get_order_id($order), '_'. $attr, true);
        return $meta;
    }
}

if (is_woocommerce_active()) {
    if (!class_exists('GdeZakazy')) {
        require_once('class-gdezakazy.php');
        $GLOBALS['gdeZakazy'] = GdeZakazy::instance();
    }
}