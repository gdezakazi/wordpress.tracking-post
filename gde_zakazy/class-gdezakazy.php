<?php

final class GdeZakazy
{
    const VERSION = '1.0';

    protected static $instance = null;

    public static function instance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        include_once('class-gdezakazy-api.php');
        include_once('class-gdezakazy-order.php');
        include_once('class-gdezakazy-settings.php');
        register_activation_hook(__DIR__.'/gde-zakazy.php', array($this, 'install'));
        register_deactivation_hook(__DIR__.'/gde-zakazy.php', array($this, 'uninstall'));
    }

    public function install()
    {
        wp_clear_scheduled_hook('gdezakazy_hourly_event');
        wp_schedule_event(time(), 'hourly', 'gdezakazy_hourly_event');
    }

    public function uninstall()
    {
        wp_clear_scheduled_hook('gdezakazy_hourly_event');
    }

    protected $api = null;

    public function getApi()
    {
        if (empty($this->api)) {
            $this->api = new GdeZakazy_Api();
        }
        return $this->api;
    }


}
