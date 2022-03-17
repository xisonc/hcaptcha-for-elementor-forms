<?php
namespace ElementorPro\Modules\Forms\Classes;

// import elementor & elementor pro classes
use Elementor\Settings;
use Elementor\Widget_Base;
use ElementorPro\Core\Utils;
use ElementorPro\Plugin;

// exit if accessed directly
if( !defined('ABSPATH') )
	exit;

/**
 * Integration with hCaptcha
 */
class Hcaptcha_Handler
{

	protected static function get_hcaptcha_name()
	{
		return 'hcaptcha';
	}

	public static function get_site_key()
	{
		return get_option('elementor_pro_hcaptcha_site_key');
	}

	public static function get_secret_key()
	{
		return get_option('elementor_pro_hcaptcha_secret_key');
	}

	public static function is_enabled()
	{
		return static::get_site_key() && static::get_secret_key();
	}

	public static function get_setup_message()
	{
		return esc_html__('To use hCaptcha, you need to add the API Key and complete the setup process in Dashboard > Elementor > Settings > Integrations > hCaptcha.', 'elementor-pro');
	}

	public function register_admin_fields( Settings $settings )
	{
		$settings->add_section( Settings::TAB_INTEGRATIONS, static::get_hcaptcha_name(), [
			'label'		=>	esc_html__('hCaptcha', 'elementor-pro'),
			'callback'	=>	function()
							{
								echo sprintf(
									/* translators: 1: Link open tag, 2: Link closing tag. */
									esc_html__('%1$shCaptcha%2$s is an anti-bot solution that protects user privacy and rewards websites. It is the most popular reCAPTCHA alternative.', 'elementor-pro'),
									'<a href="https://www.hcaptcha.com/" target="_blank">',
									'</a>'
								);
							},
			'fields'	=>	[
								'pro_hcaptcha_site_key' => [
									'label' => esc_html__('Site Key', 'elementor-pro'),
									'field_args' => [
										'type' => 'text',
									],
								],
								'pro_hcaptcha_secret_key' => [
									'label' => esc_html__('Secret Key', 'elementor-pro'),
									'field_args' => [
										'type' => 'text',
									],
								],
							],
		] );
	}

	public function localize_settings( $settings )
	{
		$settings = array_replace_recursive( $settings, [
			'forms' => [
				static::get_hcaptcha_name() => [
					'enabled' => static::is_enabled(),
					'site_key' => static::get_site_key(),
					'setup_message' => static::get_setup_message(),
				],
			],
		] );

		return $settings;
	}

	protected static function get_script_name()
	{
		return 'elementor-' . static::get_hcaptcha_name() . '-api';
	}

	public function register_scripts()
	{
		// register the JS API in WP
		$script_name = static::get_script_name();
		$src = 'https://js.hcaptcha.com/1/api.js';
		wp_register_script( $script_name, $src, [], ELEMENTOR_PRO_VERSION, true );
	}

	public function enqueue_scripts()
	{
		// ignore if preview mode
		if( Plugin::elementor()->preview->is_preview_mode() )
		{
			return;
		}

		// enqueue script
		$script_name = static::get_script_name();
		wp_enqueue_script( $script_name );
	}

	/**
	 * @param Form_Record  $record
	 * @param Ajax_Handler $ajax_handler
	 */
	public function validation( $record, $ajax_handler )
	{
		$fields = $record->get_field( ['type' => static::get_hcaptcha_name()] );

		// return nothing if no fields
		if( empty( $fields ) )
		{
			return;
		}

		// get current field
		$field = current( $fields );

		// check for empty POST var
		if( empty($_POST['h-captcha-response']) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		{
			$ajax_handler->add_error( $field['id'], esc_html__( 'The Captcha field cannot be blank. Please enter a value.', 'elementor-pro' ) );
			return;
		}

		// define errors
		$hcaptcha_errors = [
			'missing-input-secret' => esc_html__( 'The secret parameter is missing.', 'elementor-pro' ),
			'invalid-input-secret' => esc_html__( 'The secret parameter is invalid or malformed.', 'elementor-pro' ),
			'missing-input-response' => esc_html__( 'The response parameter is missing.', 'elementor-pro' ),
			'invalid-input-response' => esc_html__( 'The response parameter is invalid or malformed.', 'elementor-pro' ),
		];

		// PHPCS - response protected by hcaptcha secret
		$hcaptcha_response = $_POST['h-captcha-response']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$hcaptcha_sitekey = static::get_site_key();
		$hcaptcha_secret = static::get_secret_key();
		$client_ip = Utils::get_client_ip();

		// build request
		$request = [
			'body' => [
				'secret' => $hcaptcha_secret,
				'response' => $hcaptcha_response,
				'remoteip' => $client_ip,
				'sitekey' => $hcaptcha_sitekey
			],
		];

		// send request
		$response = wp_remote_post( 'https://hcaptcha.com/siteverify', $request );

		// get HTTP response code
		$response_code = wp_remote_retrieve_response_code( $response );

		// cannot connect to server, throw error
		if( 200 !== (int) $response_code ) {
			/* translators: %d: Response code. */
			$ajax_handler->add_error( $field['id'], sprintf( esc_html__( 'Can not connect to the hCaptcha server (%d).', 'elementor-pro' ), $response_code ) );
			return;
		}

		// get response body
		$response_body = wp_remote_retrieve_body( $response );
		$result = json_decode( $response_body, true );

		// validate the result, catch errors
		if( !$this->validate_result( $result, $field ) )
		{
			$message = esc_html__( 'Invalid Form - hCaptcha validation failed', 'elementor-pro' );

			if( isset( $result['error-codes'] ) )
			{
				$result_errors = array_flip( $result['error-codes'] );

				foreach( $hcaptcha_errors as $error_key => $error_desc )
				{
					if( isset( $result_errors[ $error_key ] ) )
					{
						$message = $hcaptcha_errors[ $error_key ];
						break;
					}
				}
			}

			$this->add_error( $ajax_handler, $field, $message );
		}

		// if success - remove the field form list (don't send it in emails and etc )
		$record->remove_field( $field['id'] );
	}

	/**
	 * @param Ajax_Handler $ajax_handler
	 * @param $field
	 * @param $message
	 */
	protected function add_error( $ajax_handler, $field, $message )
	{
		$ajax_handler->add_error( $field['id'], $message );
	}

	protected function validate_result( $result, $field )
	{
		// check for success
		if( array_key_exists('success', $result) && $result['success'] )
		{
			return true;
		}

		// fail
		return false;
	}

	/**
	 * @param $item
	 * @param $item_index
	 * @param $widget Widget_Base
	 */
	public function render_field( $item, $item_index, $widget )
	{
		$html = '<div class="elementor-field" id="form-field-' . $item['custom_id'] . '">';

		$name = static::get_hcaptcha_name();

		if( static::is_enabled() )
		{
			$this->enqueue_scripts();
			$this->add_render_attributes( $item, $item_index, $widget );
			$html .= '<div ' . $widget->get_render_attribute_string( $name . $item_index ) . '></div>';
		}
		elseif( current_user_can( 'manage_options' ) )
		{
			$html .= '<div class="elementor-alert elementor-alert-info">';
			$html .= static::get_setup_message();
			$html .= '</div>';
		}

		$html .= '</div>';

		// PHPCS - It's all escaped
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param $item
	 * @param $item_index
	 * @param $widget Widget_Base
	 */
	protected function add_render_attributes( $item, $item_index, $widget )
	{
		$hcaptcha_name = static::get_hcaptcha_name();
		$widget->add_render_attribute( [
			$hcaptcha_name . $item_index => [
				'class' => 'h-captcha',
				'data-sitekey' => static::get_site_key()
			],
		] );
	}

	public function add_field_type( $field_types )
	{
		$field_types['hcaptcha'] = esc_html__( 'hCaptcha', 'elementor-pro' );
		return $field_types;
	}

	public function filter_field_item( $item )
	{
		if( static::get_hcaptcha_name() === $item['field_type'] )
		{
			$item['field_label'] = false;
			$item['field_required'] = false;
		}
		return $item;
	}

	public function __construct()
	{
		// register the JS API in WP
		$this->register_scripts();

		// set everything up
		add_filter( 'elementor_pro/forms/field_types', [ $this, 'add_field_type' ] );
		add_action( 'elementor_pro/forms/render_field/' . static::get_hcaptcha_name(), [ $this, 'render_field' ], 10, 3 );
		add_filter( 'elementor_pro/forms/render/item', [ $this, 'filter_field_item' ] );
		add_filter( 'elementor_pro/editor/localize_settings', [ $this, 'localize_settings' ] );

		// register validation and scripts if enabled
		if( static::is_enabled() )
		{
			add_action( 'elementor_pro/forms/validation', [ $this, 'validation' ], 10, 2 );
			add_action( 'elementor/preview/enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		}

		// register settings in admin
		if( is_admin() )
		{
			add_action( 'elementor/admin/after_create_settings/' . Settings::PAGE_ID, [ $this, 'register_admin_fields' ] );
		}
	}
}