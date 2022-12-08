<?php

namespace JoelMelon\Plugins\LNURLAuth\Plugin;

// Props to eza https://github.com/eza/lnurl-php 🏆
use eza\lnurl;
// Props to endroid https://github.com/endroid/qr-code 🏆
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
 * Login with lnurl auth
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */
class Login {

	public $parent_slug            = '';
	public $callback_url           = '';
	public $redirect_url           = '';
	public $user_wallet_identifier = '';

	public function __construct() {
		$this->parent_slug            = 'options-general.php';
		$this->callback_url           = get_option( lnurl_auth()->prefix . '-callback-url' );
		$this->redirect_url           = get_option( lnurl_auth()->prefix . '-redirect-url' );
		$this->user_wallet_identifier = 'lnurl-auth-bjm-id';
	}

	/**
	 * Execution function which is called after the class has been initialized.
	 * This contains hook and filter assignments, etc.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		add_filter( 'login_body_class', array( $this, 'lnurl_auth_loginform_bodyclass' ) );
		add_action( 'wp_loaded', array( $this, 'lnurl_auth_callback' ) );
		add_action( 'login_form', array( $this, 'login_form_add_lnurl_auth_markup' ), 9001 );

		// ajax callback function for lnurl auth on login page
		add_action( 'wp_ajax_nopriv_js_initialize_lnurl_auth', array( $this, 'js_initialize_lnurl_auth' ) );
		add_action( 'wp_ajax_js_initialize_lnurl_auth', array( $this, 'js_initialize_lnurl_auth' ) );

		// ajax callback function for validate authentication
		add_action( 'wp_ajax_nopriv_js_await_lnurl_auth', array( $this, 'js_await_lnurl_auth' ) );
		add_action( 'wp_ajax_js_await_lnurl_auth', array( $this, 'js_await_lnurl_auth' ) );
	}

	/**
	 * Add a body class based on lnurl auth settings on login page
	 *
	 * @since 1.0.0
	 */
	public function lnurl_auth_loginform_bodyclass( $classes ) {
		global $errors;
		$o = get_option( lnurl_auth()->prefix . '-login-options' );

		if ( empty( $errors->errors ) ) {
			if ( 'prio-lightning' === $o ) {
				$classes[] = '⚡️';
			}

			if ( 'lightning-only' === $o ) {
				$classes[] = '⚡️';
			}

			$classes[] = 'lnurl-auth-' . $o;
		}

		return $classes;
	}

	/**
	 * Catches the lnurl auth callback sent from a node after scanning the QRCode
	 *
	 * @since 1.0.0
	 */
	public function lnurl_auth_callback() {
		$current_url  = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https://' : 'http://';
		$current_url .= $_SERVER['HTTP_HOST'];

		if ( isset( $_SERVER['PHP_SELF'] ) ) {
			$current_url .= $_SERVER['PHP_SELF'];
		}

		if ( isset( $_SERVER['REDIRECT_URL'] ) ) {
			$current_url .= $_SERVER['REDIRECT_URL'];
		}

		if ( $this->callback_url === $current_url && // is callback url
		'GET' === $_SERVER['REQUEST_METHOD'] && // is GET request
		isset( $_GET ) &&
		isset( $_GET['tag'] ) &&
		'login' === $_GET['tag'] && // sent with lnurl auth
		isset( $_GET['action'] ) &&
		'login' === $_GET['action'] && // sent with lnurl auth
		isset( $_GET['k1'] ) &&
		isset( $_GET['sig'] ) &&
		isset( $_GET['key'] )
		) {
			$k1               = $_GET['k1'];
			$signed_k1        = $_GET['sig'];
			$node_linking_key = $_GET['key'];

			// remove expired transients proactively
			delete_expired_transients();

			// set response headers
			header( 'Content-Type: application/json; charset=utf-8' );

			// success & error response according to
			// https://github.com/lnurl/luds/blob/luds/04.md#wallet-to-service-interaction-flow
			$signed = false;

			try {
				$signed = lnurl\auth( $k1, $signed_k1, $node_linking_key );
			} catch ( \Throwable $th ) {
				$message = '"' . $th->getMessage() . '": ' . _x( 'Verifying signature failed. Please reload the page and try again.', 'lnurl_auth_callback error', 'lnurl-auth' );
				lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
				error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
				echo json_encode(
					array(
						'status' => 'ERROR',
						'reason' => $message,
					)
				);
				die;
			};

			if ( ! empty( $signed ) ) {
				// if no transient is set or transient already in use for this k1
				$transient = lnurl_auth()->Plugin->Transients->get( $k1 );

				if ( empty( $transient ) || ( isset( $transient['user_id'] ) && ! empty( $transient['user_id'] ) ) ) {
					$message = _x( 'No session for this k1. Please reload the page and try again.', 'lnurl_auth_callback error', 'lnurl-auth' );
					lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
					error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
					echo wp_json_encode(
						array(
							'status' => 'ERROR',
							'reason' => $message,
						)
					);
					die;
				}

				// if $node_linking_key is in banlist
				$banlist = get_option( lnurl_auth()->prefix . '-node-banlist' );

				if ( in_array( $node_linking_key, $banlist, true ) ) {
					$message = _x( 'Sorry, your node is banned from this site.', 'lnurl_auth_callback error', 'lnurl-auth' );
					lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
					error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
					echo wp_json_encode(
						array(
							'status' => 'ERROR',
							'reason' => $message,
						)
					);
					die;
				}

				// if allowlist and $node_linking_key is not is allowlist
				$allowlist = get_option( lnurl_auth()->prefix . '-node-allowlist' );

				if ( ! empty( $allowlist ) && ! in_array( $node_linking_key, $allowlist, true ) ) {
					$message = _x( 'Sorry, your node has no access to this site.', 'lnurl_auth_callback error', 'lnurl-auth' );
					lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
					error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
					echo wp_json_encode(
						array(
							'status' => 'ERROR',
							'reason' => $message,
						)
					);
					die;
				}

				// check if a user exists for this $node_linking_key
				$user_exists = false;

				$args = array(
					'meta_key'   => $this->user_wallet_identifier,
					'meta_value' => $node_linking_key,
				);

				$user_exists = get_users( $args );
				$user_id     = false;
				$user        = false;

				if ( empty( $user_exists ) ) {

					// if no user exists and usercreation is off
					if ( 'on' !== get_option( lnurl_auth()->prefix . '-usercreation' ) ) {
						$message = _x( 'Registrations are disabled. We are not able to create an account for you.', 'lnurl_auth_callback error', 'lnurl-auth' );
						lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
						error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
						echo wp_json_encode(
							array(
								'status' => 'ERROR',
								'reason' => $message,
							)
						);
						die;
					}

					// if user does not exist an usercreation is on
					// create new user
					// generate and check if username already taken
					// if already taken, increment number
					$prefix        = get_option( lnurl_auth()->prefix . '-usercreation-prefix' );
					$username      = ! empty( $prefix ) ? $prefix : 'LN-';
					$original_name = $username;
					$number        = 1;

					while ( username_exists( (string) $username . $number ) ) {
						$number++;
					}

					$username = $username . $number;

					$user_id = wp_create_user( $username, bin2hex( random_bytes( 16 ) ), strtolower( $username ) . '@' . $_SERVER['HTTP_HOST'] );

					// if usercreation failed
					if ( is_wp_error( $user_id ) ) {
						$message = _x( 'We failed to create a user for you. Please try again later.', 'lnurl_auth_callback error', 'lnurl-auth' );
						lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
						error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
						echo wp_json_encode(
							array(
								'status' => 'ERROR',
								'reason' => $message,
							)
						);
						die;
					}

					// save $node_linking_key to user
					$user = get_user_by( 'id', $user_id );
					// TODO: encrypt/decrypt?
					update_user_meta( $user_id, $this->user_wallet_identifier, $node_linking_key );
					// add usercreation roles
					$usercration_roles = get_option( lnurl_auth()->prefix . '-usercreation-roles' );
					$user->remove_role( get_option( 'default_role' ) );
					foreach ( $usercration_roles as $role ) {
						$user->add_role( $role );
					}
				} else {
					// if user exists, set user
					$user_id = (int) $user_exists[0]->data->ID;

					if ( $user_id ) {
						$user = get_user_by( 'id', $user_id );
					} else {
						$message = _x( 'We failed searching for your user account. Please try again later.', 'lnurl_auth_callback error', 'lnurl-auth' );
						lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
						error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
						echo wp_json_encode(
							array(
								'status' => 'ERROR',
								'reason' => $message,
							)
						);
						die;
					}
				}

				// set transient to signal lnurl auth ok
				lnurl_auth()->Plugin->Transients->set( $k1, true, $user_id );

				echo json_encode( array( 'status' => 'OK' ) );
				die;
			}

			// why are you down here?
			$message = _x( 'Something went wrong. Please reload the page and try again.', 'lnurl_auth_callback error', 'lnurl-auth' );
			lnurl_auth()->Plugin->Transients->set( $k1, false, false, $message );
			error_log( json_encode( array( $k1, $signed_k1, $node_linking_key, $message ) ) );
			echo json_encode(
				array(
					'status' => 'ERROR',
					'reason' => $message,
				)
			);
			die;
		}
	}

	/**
	 * Generates "random" 32 bytes string
	 *
	 * @since 1.0.0
	 */
	public function get_random_string( $length = 32 ) {
		$chars       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$char_length = strlen( $chars );
		$string      = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$string .= $chars[ rand( 0, $char_length - 1 ) ];
		}
		return $string;
	}

	public function get_new_key() {
		$login_key = $this->get_random_string();
		$k1        = bin2hex( $login_key );
		return $k1;
	}

	/**
	 * Creates a QRCode for the given input with a Logo
	 *
	 * @since 1.0.0
	 */
	public function create_lnurl( $lnurl = false ) {
		// prepare respone object
		$response = (object) array(
			'status'  => 'Empty',
			'message' => '',
			'lnurl'   => '',
			'qrcode'  => '',
			'html'    => (object) array(
				'qrcode'    => '',
				'permalink' => '',
			),
			'k1'      => '',
		);

		if ( empty( $lnurl ) ) {
			// check for duplicate max 100 times
			for ( $i = 0; $i <= 99; $i++ ) {

				// create and set transient $k1
				$k1 = $this->get_new_key();
				if ( empty( lnurl_auth()->Plugin->Transients->get( $k1 ) ) ) {
					break;
				}

				// if ckecked 100 times and always duplicate, return
				if ( 99 === $i ) {
					$response->status  = 'Failed';
					$response->message = _x( 'We failed generating a new unique key for you. Maybe try again later.', 'create_lnurl error', 'lnurl-auth' );
					return $response;
				}
			}

			// set transient for $k1
			lnurl_auth()->Plugin->Transients->set( $k1 );

			// create lnurl
			$url   = $this->callback_url . '?tag=login&k1=' . $k1 . '&action=login';
			$lnurl = lnurl\encodeUrl( $url );
		}

		// create qrcode
		$qrcode_width = isset( $_POST['qrcode_width'] ) && is_int( intval( $_POST['qrcode_width'] ) ) ? intval( $_POST['qrcode_width'] ) : 270;
		$qrcode_width = $qrcode_width * 2;
		$foreground   = new Color( 0, 0, 0 );
		$background   = new Color( 0, 0, 0, 127 );

		if ( isset( $_POST['foreground'] ) ) {
			$rgba = lnurl_auth()->Plugin->Helpers->validate_color_to_rgba( $_POST['foreground'] );
			if ( $rgba && isset( $rgba['r'] ) && isset( $rgba['g'] ) && isset( $rgba['b'] ) && isset( $rgba['a'] ) ) {
				$foreground = new Color( $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a'] );
			}
		}
		if ( isset( $_POST['background'] ) ) {
			$rgba = lnurl_auth()->Plugin->Helpers->validate_color_to_rgba( $_POST['background'] );
			if ( $rgba && isset( $rgba['r'] ) && isset( $rgba['g'] ) && isset( $rgba['b'] ) && isset( $rgba['a'] ) ) {
				$background = new Color( $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a'] );
			}
		}

		$response->message = array( $_POST );

		$qr = QrCode::create( $lnurl )
		// correction level
		->setErrorCorrectionLevel( new ErrorCorrectionLevelHigh() )
		->setRoundBlockSizeMode( new RoundBlockSizeModeNone() )
		// size & margin
		->setSize( $qrcode_width )
		->setMargin( 0 )
		// colors
		->setForegroundColor( $foreground )
		->setBackgroundColor( $background );
		// attach logo
		$logo = Logo::create( lnurl_auth()->plugin_dir . '/assets/qr-logo-placeholder.png' )
		->setPunchoutBackground( true )
		->setResizeToWidth( $qrcode_width / 100 * 16 * 2 );

		$writer = new PngWriter();
		$result = $writer->write( $qr, $logo );

		$html_qrcode    = '<img src="' . $result->getDataUri() . '" alt="QR Code" width="100%" height="100%">';
		$html_permalink = '<a href="lightning:' . $lnurl . '">' . _x( 'Open Wallet', 'QR Code permalink label', 'lnurl-auth' ) . '</a>';

		$response->lnurl           = $lnurl;
		$response->qrcode          = $result;
		$response->html->qrcode    = $html_qrcode;
		$response->html->permalink = $html_permalink;
		$response->k1              = isset( $k1 ) ? $k1 : '';
		$response->status          = 'Success';

		return $response;
	}

	/**
	 * Add LNURL auth option to login form
	 *
	 * @since 1.0.0
	 */
	public function login_form_add_lnurl_auth_markup() {
		$o = get_option( lnurl_auth()->prefix . '-login-options' );

		if ( 'wordpress-only' !== $o ) {
			echo '<div class="lnurl-auth-loginform">';

			// qrcode
			echo do_shortcode( '[lnurl_auth label="true" foreground="#000000" redirect="' . $this->redirect_url . '"]' );

			// buttons
			echo '<button onclick="document.body.classList.toggle(`⚡️`)" class="lnurl-auth-loginform-lightning-button button button-primary button-large"type="button">' . _x( '⚡️ Login with Bitcoin Lightning', 'Loginform button label', 'lnurl-auth' ) . '</button>';
			echo '<div class="lnurl-auth-loginform-divider"><hr class="lnurl-auth-loginform-hr"><span class="lnurl-auth-loginform-divider-label">' . _x( 'or', 'Loginform option divider', 'lnurl-auth' ) . '</span></div>';
			echo '<button onclick="document.body.classList.toggle(`⚡️`)" class="lnurl-auth-loginform-wordpress-button button button-primary button-large" type="button">' . _x( 'Login with E-Mail', 'Loginform button label', 'lnurl-auth' ) . '</button>';

			echo '</div>';
		}
	}

	/**
	 * LNURL auth markup
	 *
	 * @since 1.0.0
	 */
	public function lnurl_auth_markup( $atts = false ) {
		echo '<div class="lnurl-auth"';
		if ( ( ! empty( $atts ) && isset( $atts['redirect'] ) ) ) {
			echo ' data-redirect="' . $atts['redirect'] . '"';
		}
		if ( ( ! empty( $atts ) && isset( $atts['foreground'] ) ) ) {
			echo ' data-foreground="' . $atts['foreground'] . '"';
		}
		if ( ( ! empty( $atts ) && isset( $atts['background'] ) ) ) {
			echo ' data-background="' . $atts['background'] . '"';
		}
		if ( ( ! empty( $atts ) && isset( $atts['logo-foreground'] ) ) ) {
			echo ' data-logo-foreground="' . $atts['logo-foreground'] . '"';
		}
		if ( ( ! empty( $atts ) && isset( $atts['permalink-foreground'] ) ) ) {
			echo ' data-permalink-foreground="' . $atts['permalink-foreground'] . '"';
		}
		if ( ( ! empty( $atts ) && isset( $atts['timer-foreground'] ) ) ) {
			echo ' data-timer-foreground="' . $atts['timer-foreground'] . '"';
		}
		if ( ( ! empty( $atts ) && isset( $atts['foreground'] ) ) ) {
			echo ' style="color: ' . $atts['foreground'] . '"';
		}
		echo '>';

		if ( empty( $atts ) || ( ! empty( $atts ) && isset( $atts['label'] ) && 'true' === $atts['label'] ) ) {
			echo '<label class="lnurl-auth-label" for="lnurl-auth">' . _x( '⚡️ Login with Bitcoin Lightning', 'QR Code label', 'lnurl-auth' ) . '</label>';
		}

		echo '<div class="lnurl-auth-qrcode-wrapper"';
		if ( ( ! empty( $atts ) && isset( $atts['logo-foreground'] ) ) ) {
			echo ' style="color: ' . $atts['logo-foreground'] . '"';
		}
		echo '>';
		echo '<div class="lnurl-auth-qrcode"></div>';
		echo '<svg class="lnurl-auth-qrcode-logo" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg"><g id="btc-lightning-lnurl" fill="none" fill-rule="evenodd" stroke="none" stroke-width="1"><path fill="currentColor" fill-rule="nonzero" d="M338.029 85.355c10.7 2.249 20.982 9.737 26.75 19.557 4.557 7.823 4.757 6.747 4.765 76.887v16.876l-.001 2.534c-.02 40.393-.26 73.17-.5 73.888-1.387 3.522-.293 3.734-68.656 3.745h-102.22v22.72c0 12.485.418 25.798.836 29.627 2.005 15.894 10.282 29.044 23.742 37.7 15.883 10.235 38.623 10.235 54.506 0 17.223-11.151 24.578-26.713 24.578-52.43 0-7.658.25-9.321 1.921-12.235 5.434-9.57 18.225-11.234 26.165-3.33 4.014 4.078 5.35 8.49 5.35 18.143-.083 28.13-8.026 48.684-25.413 65.992-11.368 11.233-24.244 18.558-39.458 22.47-9.026 2.246-22.904 3.16-31.094 1.996-12.541-1.746-26.5-7.072-37.036-14.147-6.853-4.66-17.807-15.562-22.489-22.388-4.682-6.989-9.53-17.311-11.619-25.049-2.593-9.487-3.428-19.806-3.428-45.52V278.84h-14.21c-14.88 0-18.308-.665-19.561-3.745-.243-.726-.486-34.417-.5-75.63v-14.473c.01-64.088.157-67.923 1.586-72.508 4.18-13.482 16.05-24.217 30.095-27.211 3.973-.84 42.73-1.257 82.89-1.27h5.024c41.893.022 83.796.48 87.977 1.353Zm-109.41 36.385c-1.126 1.119-1.126 2.237-1.126 3.356l23.632 50.328v4.474h-47.263v4.473l57.39 62.63h3.377c-5.627-14.538-11.253-30.196-18.005-45.854v-.027c.01-.252.145-2.21 2.25-2.21h46.138s0-1.118 1.126-2.236l-67.52-74.934Z" transform="matrix(1 0 0 -1 0 494.004)"/></g></svg>';
		echo '</div>';
		echo '<p class="lnurl-auth-permalink"';
		if ( ( ! empty( $atts ) && isset( $atts['permalink-foreground'] ) ) ) {
			echo ' style="color: ' . $atts['permalink-foreground'] . '"';
		}
		echo '></p>';
		echo '<p class="lnurl-auth-timer"';
		if ( ( ! empty( $atts ) && isset( $atts['timer-foreground'] ) ) ) {
			echo ' style="color: ' . $atts['timer-foreground'] . '"';
		}
		echo '><span class="lnurl-auth-timer-clock">🕛</span> <span class="lnurl-auth-timer-minutes">' . _x( 'M', 'QR Code timer short minutes', 'lnurl-auth' ) . '</span><span class="lnurl-auth-timer-separator">:</span><span class="lnurl-auth-timer-seconds">' . _x( 'SS', 'QR Code timer short seconds', 'lnurl-auth' ) . '</span></p>';

		echo '<div class="lnurl-auth-message-wrapper">';
		echo '<div class="lnurl-auth-message-scroll-wrapper">';
		echo '<div class="lnurl-auth-message"></div>';
		echo '<button class="lnurl-auth-reinit" type="button">' . _x( 'Try Again', 'QR Code reinit button label', 'lnurl-auth' ) . '</button>';
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Ajax function to get new lnurl auth
	 *
	 * @since 1.0.0
	 */
	public function js_initialize_lnurl_auth() {
		// remove expired transients proactively
		delete_expired_transients();
		echo wp_json_encode( $this->create_lnurl() );
		die;
	}

	/**
	 * Ajax function awaiting lnurl auth
	 *
	 * @since 1.0.0
	 */
	public function js_await_lnurl_auth() {
		$k1 = isset( $_POST['k1'] ) ? $_POST['k1'] : false;

		// if no k1 is provided in request
		if ( empty( $k1 ) ) {
			echo wp_json_encode(
				array(
					'status' => 'Error',
					'reason' => _x( 'No k1 in request. Please reload and try again.', 'js_await_lnurl_auth error', 'lnurl-auth' ),
				)
			);
			die;
		}

		$transient = lnurl_auth()->Plugin->Transients->get( $k1 );

		if ( empty( $transient ) ) {
			echo wp_json_encode(
				array(
					'status' => 'Timedout',
					'reason' => _x( 'Session has timed out. Please reload and try again.', 'js_await_lnurl_auth error', 'lnurl-auth' ),
				)
			);
			die;
		}

		if ( ! empty( $transient['message'] ) ) {
			echo wp_json_encode(
				array(
					'status' => 'Error',
					'reason' => $transient['message'],
				)
			);
			die;
		}

		if ( empty( $transient['signed'] ) ) {
			echo wp_json_encode(
				array(
					'status' => 'Waiting',
					'reason' => _x( 'Not yet signed.', 'js_await_lnurl_auth error', 'lnurl-auth' ),
				)
			);
			die;
		}

		$user_id = $transient['user_id'];
		$user    = get_user_by( 'id', $user_id );

		if ( empty( $user ) ) {
			echo wp_json_encode(
				array(
					'status' => 'Error',
					'reason' => _x( 'We failed searching for your user account. Please try again later.', 'js_await_lnurl_auth error', 'lnurl-auth' ),
				)
			);
			die;
		}

		// authenticate user
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', get_userdata( $user_id )->user_login );

		// delete transient
		lnurl_auth()->Plugin->Transients->delete( $k1 );

		echo wp_json_encode(
			array(
				'status'   => 'Signed',
				'redirect' => $this->redirect_url,
			)
		);
		die;
	}
}
