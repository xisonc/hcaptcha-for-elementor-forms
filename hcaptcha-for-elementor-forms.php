<?php
/**
 * Plugin Name: hCaptcha for Elementor Forms
 * Description: Add hCaptcha to Elementor Forms
 * Author: xisonc
 * Author URI: https://github.com/xisonc/
 * Version: 1.0.1
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

// exit if accessed directly
if( !defined('ABSPATH') )
	exit;

// include hcaptcha handler
require dirname(__FILE__).'/classes/hcaptcha-handler.php';

// initialize it
do_action('elementor_pro/forms/add_component', new \ElementorPro\Modules\Forms\Classes\Hcaptcha_Handler());


// display error if elementor_pro is not active
function h2ef_is_elementorpro_active()
{
	if( !is_plugin_active('elementor-pro/elementor-pro.php') )
	{
		echo '<div class="error"><p><strong>hCaptcha for Elementor Forms</strong> requires <strong>Elementor Pro plugin</strong>.</p></div>';
	}
}
add_action('admin_notices', 'h2ef_is_elementorpro_active');
