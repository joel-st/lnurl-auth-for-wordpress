<?php

namespace JoelMelon\Plugins\LNURLAuth;

/**
 * Plugin Class
 *
 * Each request to login with LNURL auth generates a random key $k1
 * This key will be signed by the node trying to login
 * The node then will send a response to our callback url with the signed $k1
 *
 * LNURL auth (js_initialize_lnurl_auth) creates transient with $k1 which expires in 300s
 * LNURL auth (lnurl_auth_callback) does verification and usercreation
 * LNURL auth (js_await_lnurl_auth) login checks if transient is signed
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */
class Plugin {

	private static $instance;
	public $plugin_header = '';
	public $domain_path   = '';
	public $name          = '';
	public $prefix        = '';
	public $version       = '';
	public $file          = '';
	public $plugin_url    = '';
	public $plugin_dir    = '';
	public $base_path     = '';
	public $text_domain   = '';
	public $debug         = '';

	/**
	 * Creates an instance if one isn't already available,
	 * then return the current instance.
	 *
	 * @param string $file The file from which the class is being instantiated.
	 * @return object The class instance.
	 * @since 1.0.0
	 */
	public static function get_instance( $file ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin;

			if ( ! function_exists( 'get_plugin_data' ) ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			self::$instance->plugin_header = get_plugin_data( $file );
			self::$instance->name          = self::$instance->plugin_header['Name'];
			self::$instance->domain_path   = basename( dirname( __DIR__ ) ) . self::$instance->plugin_header['DomainPath'];
			self::$instance->prefix        = 'lnurl-auth';
			self::$instance->version       = self::$instance->plugin_header['Version'];
			self::$instance->file          = $file;
			self::$instance->plugin_url    = plugins_url( '', dirname( __FILE__ ) );
			self::$instance->plugin_dir    = dirname( __DIR__ );
			self::$instance->base_path     = self::$instance->prefix;
			self::$instance->text_domain   = self::$instance->plugin_header['TextDomain'];
			self::$instance->debug         = true;

			if ( ! isset( $_SERVER['HTTP_HOST'] ) || strpos( $_SERVER['HTTP_HOST'], '.local' ) === false && ! in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) ) {
				self::$instance->debug = false;
			}

			self::$instance->run();
		}

		return self::$instance;
	}

	/**
	 * Execution function which is called after the class has been initialized.
	 * This contains hook and filter assignments, etc.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// load classes
		$this->load_classes(
			array(
				\JoelMelon\Plugins\LNURLAuth\Plugin\Helpers::class,
				\JoelMelon\Plugins\LNURLAuth\Plugin\Assets::class,
				\JoelMelon\Plugins\LNURLAuth\Plugin\Transients::class,
				\JoelMelon\Plugins\LNURLAuth\Plugin\Login::class,
				\JoelMelon\Plugins\LNURLAuth\Plugin\Settings::class,
			)
		);

		// Load the textdomain
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );

		// Shortcode
		add_shortcode( 'lnurl_auth', array( $this, 'lnurl_auth_shortcode' ) );
	}

	/**
	 * Loads and initializes the provided classes.
	 *
	 * @param array of classes
	 * @since 1.0.0
	 */
	private function load_classes( $classes ) {
		foreach ( $classes as $class ) {
			$class_parts = explode( '\\', $class );
			$class_short = end( $class_parts );
			$class_set   = $class_parts[ count( $class_parts ) - 2 ];

			if ( ! isset( lnurl_auth()->{$class_set} ) || ! is_object( lnurl_auth()->{$class_set} ) ) {
				lnurl_auth()->{$class_set} = new \stdClass();
			}

			if ( property_exists( lnurl_auth()->{$class_set}, $class_short ) ) {
				/* translators: %1$s = already used class name, %2$s = plugin class */
				wp_die( sprintf( esc_html( _x( 'There was a problem with the Plugin. Only one class with name “%1$s” can be use used in “%2$s”.', 'Theme instance load_classes() error message', 'lnurl-auth' ) ), $class_short, $class_set ), 500 );
			}

			lnurl_auth()->{$class_set}->{$class_short} = new $class();

			if ( method_exists( lnurl_auth()->{$class_set}->{$class_short}, 'run' ) ) {
				lnurl_auth()->{$class_set}->{$class_short}->run();
			}
		}
	}

	/**
	 * Load the plugins textdomain
	 *
	 * @since 1.0.0
	 */
	public function load_text_domain() {
		load_plugin_textdomain( lnurl_auth()->text_domain, false, $this->domain_path );
	}

	/**
	 * LNURL Shortcode
	 *
	 * @since 1.0.0
	 */
	function lnurl_auth_shortcode( $atts ) {
		ob_start();
		lnurl_auth()->Plugin->Login->lnurl_auth_markup( $atts );
		$lnurl_url_auth = ob_get_clean();

		lnurl_auth()->Plugin->Assets->lnurl_auth_enqueue_scripts_styles();

		return $lnurl_url_auth;
	}

}
