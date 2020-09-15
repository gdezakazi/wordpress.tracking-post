<?php
/*
	Plugin Name: GDEZAKAZI.RU
	Plugin URI: https://xn--80aahefmcw9m.xn--p1ai/plugins
	Description: Трекинг заказов
	Version: 1.0
	Author: гдезаказы.рф
	Author URI: https://github.com/gdezakazi/wordpress.tracking-post

	Copyright: © гдезаказы.рф
*/

defined('ABSPATH') or die("No script kiddies please!");

if (!function_exists('gdezakazy_is_woocommerce_active')) {
    function gdezakazy_is_woocommerce_active() {
        $activePlugins = (array)get_option('active_plugins', array());
        if (is_multisite()) {
            $activePlugins = array_merge($activePlugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('woocommerce/woocommerce.php', $activePlugins) || array_key_exists('woocommerce/woocommerce.php', $activePlugins);
    }
}

if (gdezakazy_is_woocommerce_active()) {
    if (!class_exists('GdeZakazy')) {
        require_once('class-gdezakazy.php');
        $GLOBALS['gdeZakazy'] = GdeZakazy::instance();
    }
}