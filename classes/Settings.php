<?php

namespace JoelMelon\Plugins\LNURLAuth\Plugin;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;

/**
 * Plugin Settings
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */
class Settings {

	public $parent_slug      = '';
	public $menu_slug        = '';
	public $prefix           = '';
	public $display_settings = array();
	public $settings_group   = '';

	public function __construct() {
		$this->parent_slug    = 'options-general.php';
		$this->menu_slug      = lnurl_auth()->prefix;
		$this->options_prefix = lnurl_auth()->prefix . '-settings';
		$this->settings_group = lnurl_auth()->prefix . '-settings-group';

		$this->display_settings = array(
			lnurl_auth()->prefix . '-login-options'       => array(
				'legend'  => esc_html( _x( 'Choose which options your users can use to login.', 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Login Options', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'select',
				'default' => 'prio-wp',
				'options' => array(
					'prio-wp'        => esc_html( _x( 'WordPress & Bitcoin Lightning', 'Settings page setting label', 'lnurl-auth' ) ),
					'prio-lightning' => esc_html( _x( 'Bitcoin Lightning & WordPress', 'Settings page setting label', 'lnurl-auth' ) ),
					'wordpress-only' => esc_html( _x( 'WordPress Only', 'Settings page setting label', 'lnurl-auth' ) ),
					'lightning-only' => esc_html( _x( 'Lightning Only', 'Settings page setting label', 'lnurl-auth' ) ),
				),
			),
			lnurl_auth()->prefix . '-redirect-url'        => array(
				'legend'  => esc_html( _x( 'Default redirect URL after sucessfull LNURL auth.', 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Redirect URL', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'url',
				'default' => esc_html( get_site_url() ),
			),
			lnurl_auth()->prefix . '-callback-url'        => array(
				'legend'  => esc_html( _x( 'Wallets will respond to this URL.', 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Callback URL', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'url',
				'default' => esc_html( wp_login_url() ),
			),
			lnurl_auth()->prefix . '-node-banlist'        => array(
				'legend'  => esc_html( _x( "Comma separated list of Node ID's. Nodes from this list will be blocked from using LNURL auth on this website.", 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Node Banlist', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'strings-comma-separated',
				'default' => array(),
			),
			lnurl_auth()->prefix . '-node-allowlist'      => array(
				'legend'  => esc_html( _x( "Comma separated list of Node ID's. Nodes from this list will be allowed to use LNURL auth on this website.", 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Node Allowlist', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'strings-comma-separated',
				'default' => array(),
			),
			lnurl_auth()->prefix . '-usercreation'        => array(
				'legend'  => esc_html( _x( 'If a node tries to login to your website and no exisiting user can be found, a new user will be created for this node.', 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Enable Registrations', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'boolean',
				'default' => esc_html( get_option( 'users_can_register' ) ),
			),
			lnurl_auth()->prefix . '-usercreation-prefix' => array(
				'legend'  => esc_html( _x( 'If a new user account is created, this prefix gets suffixed by the next available number to create the user_login. E.g. LN-1.', 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Usercreation prefix', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'string',
				'default' => 'LN-',
			),
			lnurl_auth()->prefix . '-usercreation-roles'  => array(
				'legend'  => esc_html( _x( 'Usercreation roles', 'Settings page setting label', 'lnurl-auth' ) ),
				'label'   => esc_html( _x( 'Usercreation roles', 'Settings page setting label', 'lnurl-auth' ) ),
				'type'    => 'multiselect',
				'default' => array( get_option( 'default_role' ) ),
				'options' => wp_roles()->get_names(),
			),
		);
	}

	/**
	 * Execution function which is called after the class has been initialized.
	 * This contains hook and filter assignments, etc.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Add the settings link to the plugin list
		add_filter( 'plugin_action_links_' . basename( lnurl_auth()->base_path ) . '/' . basename( lnurl_auth()->file ), array( $this, 'add_settings_link' ) );

		// Add settings page
		add_action( 'admin_menu', array( $this, 'register_options_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add post state on user list
		add_filter( 'manage_users_columns', array( $this, 'user_list_add_columns' ) );
		add_action( 'manage_users_custom_column', array( $this, 'user_list_fill_columns' ), 10, 3 );

		// Add lnurl auth settings field on user edit
		add_action( 'show_user_profile', array( $this, 'user_edit_add_settings_field' ) );
		add_action( 'edit_user_profile', array( $this, 'user_edit_add_settings_field' ) );
		add_action( 'personal_options_update', array( $this, 'user_edit_update_settings_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_edit_update_settings_field' ) );

	}

	/**
	 * Add a Link to Plugin Settings Page in The WordPress Plugin List
	 *
	 * @since 1.0.0
	 */
	public function add_settings_link( $links ) {
		// Build and escape the URL.
		$url = admin_url( $this->parent_slug . '?page=' . lnurl_auth()->prefix );
		// Create the link.
		$settings_link = "<a href='$url'>" . esc_html( _x( 'Plugin Settings', 'Settings link in WordPress plugin list', 'lnurl-auth' ) ) . '</a>';
		// Adds the link to the end of the array.
		array_push(
			$links,
			$settings_link
		);
		return $links;
	}

	/**
	 * Registers the options page to manage the plugin settings and icon collection
	 *
	 * @since 1.0.0
	 */
	public function register_options_page() {
		add_submenu_page(
			$this->parent_slug,
			esc_html( _x( 'LNURL Auth', 'Plugins settings page title', 'lnurl-auth' ) ),
			esc_html( _x( 'LNURL Auth', 'Plugins settings menu title', 'lnurl-auth' ) ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers the settings and set sanitation callback by type or key
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		foreach ( $this->display_settings as $name => $data ) {
			add_option( $name, $data['default'] );

			$args = array(
				'type'    => $data['type'],
				'default' => $data['default'],
			);

			if ( 'boolean' === $data['type'] ) {
				$args['sanitize_callback'] = array( $this, 'sanitize_checkbox' );
			}

			if ( 'number' === $data['type'] ) {
				$args['sanitize_callback'] = array( $this, 'sanitize_number' );
			}

			if ( 'select' === $data['type'] ) {
				$args['sanitize_callback'] = array( $this, 'sanitize_select' );
			}

			if ( 'strings-comma-separated' === $data['type'] ) {
				$args['sanitize_callback'] = array( $this, 'sanitize_strings_comma_separated' );
			}

			if ( 'multiselect' === $data['type'] ) {
				$args['sanitize_callback'] = array( $this, 'sanitize_multiselect' );
			}

			if ( 'string' === $data['type'] ) {
				if ( lnurl_auth()->prefix . '-usercreation-prefix' === $name ) {
					$args['sanitize_callback'] = array( $this, 'sanitize_usercreation_prefix' );
				} else {
					$args['sanitize_callback'] = array( $this, 'sanitize_text_field' );
				}
			}

			if ( 'url' === $data['type'] ) {
				$args['sanitize_callback'] = array( $this, 'sanitize_url' );
			}

			register_setting( $this->settings_group, $name, $args );
		}
	}

	/**
	 * Main function to render the settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		lnurl_auth()->Plugin->Assets->lnurl_auth_enqueue_admin_scripts_styles();

		echo '<div class="wrap">';
		echo '<div class="lnurl-auth">';
		$this->render_settings_head();

		echo '<div class="lnurl-auth-admin-columns">';

		echo '<div class="lnurl-auth-admin-column-left">';
		// echo '<div class="card">';

		echo '<form method="post" action="options.php" class="' . esc_attr( $this->settings_group ) . '">';
		settings_fields( $this->settings_group );

		echo '<table class="form-table" role="presentation">';

		foreach ( $this->display_settings as $name => $data ) {
			$this->render_settings( $name, $data );
		}

		echo '</table>';

		submit_button();
		echo '</form>';

		// echo '</div>';
		echo '</div>';

		echo '<div class="lnurl-auth-admin-column-right">';
		echo '<div class="card">';

		echo '<div class="lnurl-auth-admin-shortcode">';
		echo '<h4>' . esc_html( _x( 'Shortcode Usage', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h4>';
		echo '<code>[lnurl_auth]</code>';
		echo '<h4>' . esc_html( _x( 'Shortcode Options', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h4>';
		echo '<h5>' . esc_html( _x( 'Redirect to after login', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<code>redirect="https://example.com"</code>';
		echo '<h5>' . esc_html( _x( 'Show/Hide Label', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<code>label="true|false"</code>';
		echo '<h5>' . esc_html( _x( 'Set Foreground Color (Label, Logo, QR, Link, Timer)', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<code>foreground="#F7931A"</code>';
		echo '<h5>' . esc_html( _x( 'Set Logo Foreground Color', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<code>logo-foreground="#F7931A"</code>';
		echo '<h5>' . esc_html( _x( 'Set Permalink Foreground Color', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<code>permalink-foreground="#F7931A"</code>';
		echo '<h5>' . esc_html( _x( 'Set Timer Foreground Color', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<code>timer-foreground="#F7931A"</code>';
		echo '<h4>' . esc_html( _x( 'Note on LNURL Auth Coloring', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</h4>';
		echo '<p><i>' . esc_html( _x( 'The QR Code inherits foreground- and background-color if no color is specified.', 'Settings Page Shortcode Panel', 'lnurl-auth' ) ) . '</p></i>';
		echo '</div>';

		echo '</div>';

		echo '<div class="card">';
		echo '<div class="lnurl-auth-admin-donate">';

		echo '<h4>' . esc_html( _x( 'Donate', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</h4>';
		echo '<h5>' . esc_html( _x( 'Bitcoin Lightning LNURL', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</h5>';

		echo '<div class="lnurl-auth-admin-donate-grid">';
		echo '<div class="lnurl-auth-admin-donate-left">';
		$donate_lnurl = 'lnurl1dp68gurn8ghj7cm0d9hxxmmjdejhytnfduhkcmn4wfkz7urp0yhkycenvgmrvvnz95ervdmp956xgctr943rvcmy95crzvfjxuerwdm9xq6kv5fka9c';
		$qr           = QrCode::create( $donate_lnurl )
		// correction level
		->setErrorCorrectionLevel( new ErrorCorrectionLevelHigh() )
		->setRoundBlockSizeMode( new RoundBlockSizeModeNone() )
		// size & margin
		->setSize( 350 )
		->setMargin( 0 )
		// colors
		->setForegroundColor( new Color( 0, 0, 0 ) )
		->setBackgroundColor( new Color( 0, 0, 0, 127 ) );
		// attach logo
		$logo = Logo::create( lnurl_auth()->plugin_dir . '/assets/qr-logo-placeholder.png' )
		->setPunchoutBackground( true )
		->setResizeToWidth( 270 / 100 * 16 * 2 );

		$writer = new PngWriter();
		$result = $writer->write( $qr, $logo );

		echo '<div class="lnurl-auth-admin-donate-qrcode">';
		echo '<span>🧡</span>';
		echo '<img src="' . esc_attr( $result->getDataUri() ) . '" alt="QR Code" width="100%" height="100%">';
		echo '</div>';
		echo '</div>';

		echo '<div class="lnurl-auth-admin-donate-right">';
		echo '<p style="word-break: break-all;"><i>' . esc_html( $donate_lnurl ) . '</i></p>';

		echo '<h5>' . esc_html( _x( 'Other Options', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</h5>';
		echo '<p><a target="_blank" href="lightning:' . esc_html( $donate_lnurl ) . '">' . esc_html( _x( 'CoinCorner', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</a> | <a target="_blank" href="https://checkout.opennode.com/p/9a52a597-64bc-4302-9b9e-23faac7a414c">' . esc_html( _x( 'Opennode', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</a> | <a target="_blank" href="https://commerce.coinbase.com/checkout/99bafc19-737b-4b16-aaec-962f81a17a5d">' . esc_html( _x( 'Coinbase', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</a> | <a target="_blank" href="https://www.paypal.com/donate/?hosted_button_id=F3KWZLKR2YKNW">' . esc_html( _x( 'Paypal', 'Settings Page Donate Panel', 'lnurl-auth' ) ) . '</a></p>';
		echo '</div>';
		echo '</div>';

		echo '</div>';
		echo '</div>';

		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the settings page head
	 *
	 * @since 1.0.0
	 */
	public function render_settings_head() {
		echo '<h1 class="wp-heading-inline">' . esc_html( _x( 'Settings › LNURL Auth', 'Settings page heading', 'lnurl-auth' ) ) . '</h1>';
		echo '<hr class="wp-header-end">';
	}

	/**
	 * Render the input element for a setting
	 *
	 * @since 1.0.0
	 */
	public function render_settings( $name, $data ) {
		switch ( $data['type'] ) {
			case 'boolean':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<fieldset>';
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<input name="' . esc_attr( $name ) . '" type="checkbox" id="' . esc_attr( $name ) . '" ' . checked( 1, 'on' === get_option( $name ), false ) . '> ' . esc_html( $data['label'] );
				echo '</label>';
				echo '</fieldset>';
				echo '</td>';
				echo '</tr>';
				break;
			case 'number':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<input name="' . esc_attr( $name ) . '" type="number" step="0.01" id="' . esc_attr( $name ) . '" value="' . esc_attr( get_option( $name ) ) . '"> ' . esc_html( $data['label'] );
				echo '</label>';
				echo '</td>';
				echo '</tr>';
				break;
			case 'select':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
				foreach ( $data['options'] as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"';
					if ( get_option( $name ) === $value ) {
						echo ' selected';
					}
					echo '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</td>';
				echo '</tr>';
				break;
			case 'multiselect':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<select name="' . esc_attr( $name ) . '[]" id="' . esc_attr( $name ) . '" multiple="multiple">';

				foreach ( $data['options'] as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"';
					if ( in_array( $value, get_option( $name ), true ) ) {
						echo ' selected';
					}
					echo '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '</label>';
				echo '</td>';
				echo '</tr>';
				break;
			case 'strings-comma-separated':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" rows="6">';
				echo esc_html( implode( ', ', (array) get_option( $name ) ) );
				echo '</textarea>';
				echo '</label>';
				echo '</td>';
				echo '</tr>';
				break;
			case 'string':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<input name="' . esc_attr( $name ) . '" type="text" id="' . esc_attr( $name ) . '" value="' . esc_attr( get_option( $name ) ) . '">';
				echo '</label>';
				echo '</td>';
				echo '</tr>';
				break;
			case 'url':
				echo '<tr valign="top">';
				echo '<th scope="row">';
				echo esc_html( $data['label'] );
				echo '</th>';
				echo '<td>';
				if ( ! empty( $data['legend'] ) ) {
					echo '<div class="' . esc_attr( $this->settings_group ) . '-legend' . '">' . esc_html( $data['legend'] ) . '</div>';
				}
				echo '<legend class="screen-reader-text">';
				echo '<span>' . esc_html( $data['label'] ) . '</span>';
				echo '</legend>';
				echo '<label for="' . esc_attr( $name ) . '">';
				echo '<input name="' . esc_attr( $name ) . '" type="url" id="' . esc_attr( $name ) . '" value="' . esc_attr( get_option( $name ) ) . '">';
				echo '</label>';
				echo '</td>';
				echo '</tr>';
				break;
		}
	}

	/**
	 * Sanitize checkbox value
	 *
	 * @since 1.0.0
	 */
	public function sanitize_checkbox( $value ) {
		if ( ! empty( $value ) && 'on' === $value ) {
			$value = 'on';
		} else {
			$value = 'off';
		}

		return $value;
	}

	/**
	 * Sanitize number value
	 *
	 * @since 1.0.0
	 */
	public function sanitize_number( $value ) {
		$value = floatval( $value );
		return $value;
	}

	/**
	 * Sanitize text field value
	 *
	 * @since 1.0.0
	 */
	public function sanitize_text_field( $value ) {
		$value = sanitize_text_field( $value );
		return $value;
	}

	/**
	 * Sanitize usercration prefix
	 *
	 * @since 1.0.0
	 */
	public function sanitize_usercreation_prefix( $value ) {
		$value = sanitize_text_field( $value );
		if ( ! validate_username( $value ) ) {
			$value = 'LN-';
		}
		return $value;
	}

	/**
	 * Sanitize select value
	 *
	 * @since 1.0.0
	 */
	public function sanitize_select( $value ) {
		$value = sanitize_key( $value );
		return $value;
	}

	/**
	 * Sanitize multiselect value
	 *
	 * @since 1.0.0
	 */
	public function sanitize_multiselect( $value ) {
		if ( empty( $value ) || 'array' !== gettype( $value ) ) {
			return array( get_option( 'default_role' ) );
		}

		foreach ( $value as $key => $v ) {
			$value[ $key ] = sanitize_key( $v );
		}

		return $value;
	}

	/**
	 * Sanitize url value
	 *
	 * @since 1.0.0
	 */
	public function sanitize_url( $value ) {
		// Remove URL Parameters
		// $value = strtok( $value, '?' );
		// Sanitize URL
		$value = sanitize_url( $value );
		return $value;
	}

	/**
	 * Sanitize textinput comma separated strings
	 *
	 * @since 1.0.0
	 */
	public function sanitize_strings_comma_separated( $value ) {
		// return empty array if value is empty
		if ( empty( $value ) ) {
			return array();
		}

		// return array if value is array
		if ( 'array' === gettype( $value ) ) {
			return $value;
		}

		// replace comma
		$arr = str_replace( ', ', ',', $value );
		$arr = explode( ',', (string) $arr );

		$value = array();
		foreach ( $arr as $key => $v ) {
			if ( ! empty( $v ) ) {
				$value[ $key ] = sanitize_text_field( $v );
			}
		}

		return $value;
	}

	/**
	 * Add column to user list to see which user is LN user
	 *
	 * @since 1.0.0
	 */
	public function user_list_add_columns( $column_headers ) {
		$updated_headers = array();
		foreach ( $column_headers as $k => $v ) {
			$updated_headers[ $k ] = $v;
			if ( 'username' === $k ) {
				$updated_headers['lnurl-auth'] = esc_html( _x( 'LNURL Auth', 'Admin User Columns Custom Column Name', 'lnurl-auth' ) );
			}
		}
		return $updated_headers;
	}

	/**
	 * Add content to column to see which user is LN user
	 *
	 * @since 1.0.0
	 */
	public function user_list_fill_columns( $value, $column_name, $user_id ) {
		if ( 'lnurl-auth' === $column_name ) {
			$lnurl_field_value = get_user_meta( $user_id, lnurl_auth()->Plugin->Login->user_wallet_identifier, true );

			if ( ! empty( $lnurl_field_value ) ) {
				$value = '✅';
			}
		}
		return $value;
	}

	/**
	 * Add settings field to user settings for administrators to manage wallet identifiers.
	 *
	 * @since 1.0.0
	 */
	public function user_edit_add_settings_field( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) || ! current_user_can( 'administrator' ) ) {
			return false;
		}
		echo '<h2 style="margin-top:2.6em;">' . esc_html( _x( 'LNURL Auth', 'Admin User Edit Custom Settings', 'lnurl-auth' ) ) . '</h2>';
		echo '<table class="form-table"><tr>';
		echo '<th><label for="' . lnurl_auth()->Plugin->Login->user_wallet_identifier . '">' . esc_html( _x( 'Public Key', 'Admin User Edit Custom Settings', 'lnurl-auth' ) ) . '</label></th>';
		echo '<td><input class="regular-text ltr" type="text" name="' . lnurl_auth()->Plugin->Login->user_wallet_identifier . '" value="' . esc_attr( get_the_author_meta( lnurl_auth()->Plugin->Login->user_wallet_identifier, $user->ID ) ) . '" /></td>';
		echo '</tr></table>';
	}

	/**
	 * Safe wallet identifiers settings field value.
	 *
	 * @since 1.0.0
	 */
	public function user_edit_update_settings_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}
		if ( isset( $_POST[ lnurl_auth()->Plugin->Login->user_wallet_identifier ] ) ) {
			update_user_meta( $user_id, lnurl_auth()->Plugin->Login->user_wallet_identifier, sanitize_key( $_POST[ lnurl_auth()->Plugin->Login->user_wallet_identifier ] ) );
		}
	}
}
