<?php
/**
 * Created by PhpStorm.
 * User: Dulaj Ariyaratne
 * Date: 1/27/2021
 * Time: 8:47 AM
 */

/*
Plugin Name: Traveler PayHere
Description: This plugin is used for Traveler Theme
Version: 1.0
Author: Dulaj Ariyaratne
Author URI: https://github.com/Dulajdeshan
License: GPLv2 or later
Text Domain: traveler-payhere
*/

class Traveler_Payhere_Payment
{
    public $pluginUrl = '';
    public $pluginPath = '';
    public $customFolder = 'traveler-payhere';

    public function __construct()
    {
        $this->pluginPath = trailingslashit(plugin_dir_path(__FILE__));
        $this->pluginUrl = trailingslashit(plugin_dir_url(__FILE__));

        add_action('plugins_loaded', [$this, '_pluginSetup']);
        add_action('init', [$this, '_pluginLoader'], 20);
        add_action('wp_enqueue_scripts', [$this, '_pluginEnqueue']);
    }

    public function _pluginSetup()
    {
        load_plugin_textdomain('traveler-payhere', false, basename(dirname(__FILE__)) . '/languages');
    }

    public function _pluginLoader()
    {
        if (class_exists('STTravelCode') && class_exists('STAbstactPaymentGateway')) {
            require_once($this->pluginPath . 'inc/payhere.php');
        }
    }

    public function _pluginEnqueue()
    {
        
    }

    public function loadTemplate($name, $data = null)
    {
        if (is_array($data))
            extract($data);

        $template = $this->pluginPath . 'views/' . $name . '.php';

        if (is_file($template)) {
            $templateCustom = locate_template($this->customFolder . '/views/' . $name . '.php');
            if (is_file($templateCustom)) {
                $template = $templateCustom;
            }
            ob_start();

            require($template);

            $html = @ob_get_clean();

            return $html;
        }


    }

    public static function get_inst()
    {
        static $instance;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}

Traveler_Payhere_Payment::get_inst();