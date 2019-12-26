<?php
/**
 * WP_Proxy
 *
 * @package wp-proxy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Proxy class
 */
class WP_Proxy {
	/**
	 * The single instance of the class
	 *
	 * @var wp_proxy
	 */
	protected static $instance = null;

	/**
	 * The proxy options
	 *
	 * @var wp_proxy_option
	 */
	protected $options = array();

	/**
	 * WP_Proxy Construct
	 *
	 * @var wp_proxy
	 */
	public function __construct() {
		$this->load_plugin_textdomain();
		$options = get_option( 'wp_proxy_options' );
		if ( $options ) {
			$this->options = $options;
			if ( $options['enable'] ) {
				add_filter( 'pre_http_send_through_proxy', array( $this, 'send_through_proxy' ), 10, 4 );
				defined( 'WP_PROXY_HOST' ) ? '' : define( 'WP_PROXY_HOST', $options['proxy_host'] );
				defined( 'WP_PROXY_PORT' ) ? '' : define( 'WP_PROXY_PORT', $options['proxy_port'] );
				if ( ! empty( $options['username'] ) ) {
					defined( 'WP_PROXY_USERNAME' ) ? '' : define( 'WP_PROXY_USERNAME', $options['username'] );
				}
				if ( ! empty( $options['password'] ) ) {
					defined( 'WP_PROXY_PASSWORD' ) ? '' : define( 'WP_PROXY_PASSWORD', $options['password'] );
				}
			}
		} else {
			add_option( 'wp_proxy_options', $this->defualt_options() );
		}
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_details_links' ), 10, 2 );
	}

	/**
	 * Main WP_Proxy Instance
	 *
	 * @since 1.0
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * I18n
	 *
	 * @since 1.0
	 */
	protected function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'wp-proxy' );

		load_plugin_textdomain( 'wp-proxy', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Default options
	 *
	 * @since 1.0
	 */
	protected function defualt_options() {
		$options               = array();
		$options['domains']    = '*.wordpress.org';
		$options['proxy_host'] = '127.0.0.1';
		$options['proxy_port'] = '1080';
		$options['username']   = '';
		$options['password']   = '';
		$options['enable']     = false;
		return $options;
	}

	/**
	 * Add options page, update options
	 *
	 * @since 1.0
	 */
	public function options_page() {
		add_options_page( 'WP Proxy', esc_html__( 'WP Proxy', 'wp-proxy' ), 'manage_options', 'wp_proxy', array( $this, 'wp_proxy_option' ) );
		if ( isset( $_POST['option_page'] ) && 'wp_proxy' === sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) && isset( $_POST['_wpnonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wp_proxy-options' ) ) {
				if ( isset( $_POST['proxy_host'] ) ) {
					$wp_proxy_options['proxy_host'] = sanitize_text_field( wp_unslash( $_POST['proxy_host'] ) );
				}
				if ( isset( $_POST['proxy_port'] ) ) {
					$port = abs( sanitize_text_field( wp_unslash( $_POST['proxy_port'] ) ) );
					if ( 0 === $port || 65535 < $port ) {
						add_settings_error( 'wp_proxy', 500, esc_html__( 'Wrong port', 'wp-proxy' ), 'error' );
						$wp_proxy_options['proxy_port'] = $this->options['proxy_port'];
					} else {
						$wp_proxy_options['proxy_port'] = intval( wp_unslash( $_POST['proxy_port'] ) );
					}
				}
				if ( isset( $_POST['username'] ) ) {
					$wp_proxy_options['username'] = sanitize_text_field( wp_unslash( $_POST['username'] ) );
				}
				if ( isset( $_POST['password'] ) ) {
					$wp_proxy_options['password'] = sanitize_text_field( wp_unslash( $_POST['password'] ) );
				}
				if ( isset( $_POST['domains'] ) ) {
					$wp_proxy_options['domains'] = str_replace( ' ', "\n", sanitize_text_field( wp_unslash( $_POST['domains'] ) ) );
				}
				if ( isset( $_POST['enable'] ) ) {
					if ( 'yes' === sanitize_text_field( wp_unslash( $_POST['enable'] ) ) ) {
						$wp_proxy_options['enable'] = true;
					} else {
						$wp_proxy_options['enable'] = false;
					}
				}
				update_option( 'wp_proxy_options', $wp_proxy_options );
				$this->options = get_option( 'wp_proxy_options' );
			}
		}
	}

	/**
	 * In plugins page show some links
	 *
	 * @param   array  $links links.
	 * @param   string $file file.
	 * @since 1.3.2
	 */
	public function plugin_details_links( $links, $file ) {
		if ( WP_PROXY_PLUGIN_NAME === $file ) {
			$links[] = sprintf( '<a href="https://translate.wordpress.org/projects/wp-plugins/wp-proxy" target="_blank" rel="noopener">%s</a>', __( 'Translations' ) );
		}
		return $links;
	}

	/**
	 * In plugins page show some links
	 *
	 * @param   array  $links links.
	 * @param   string $file file.
	 * @since 1.3.2
	 */
	public function plugin_action_links( $links, $file ) {
		if ( current_user_can( 'manage_options' ) ) {
			if ( WP_PROXY_PLUGIN_NAME === $file ) {
				$url           = admin_url( 'options-general.php?page=wp_proxy' );
				$settings_link = sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Settings' ) );
				$links[]       = $settings_link;
			}
		}
		return $links;
	}

	/**
	 * Check URL
	 *
	 * @param   string $null null.
	 * @param   string $url url.
	 * @param   bool   $check check result.
	 * @param   string $home site home.
	 * @since 1.0
	 */
	public function send_through_proxy( $null, $url, $check, $home ) {
		$rules = explode( '\n', $this->options['domains'] );
		$host  = false;
		if ( ! is_array( $check ) ) {
			$check = wp_parse_url( $check );
		}
		if ( isset( $check['host'] ) ) {
			$host = $check['host'];
		}
		foreach ( $rules as $rule ) {
			$rule = str_replace( '*.', '(.*)\.', $rule );
			if ( $rule === $host ) {
				return true;
			} elseif ( preg_match( '#' . $rule . '#i', $host ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Settings
	 *
	 * @since 1.0
	 */
	public function register_settings() {
		register_setting( 'wp_proxy', 'proxy_config' );
		add_settings_section(
			'wp_proxy_config',
			'',
			array(),
			'wp_proxy'
		);
		add_settings_field(
			'proxy_host',
			esc_html__( 'Proxy Host', 'wp-proxy' ),
			array( $this, 'proxy_host_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'proxy_port',
			esc_html__( 'Proxy Port', 'wp-proxy' ),
			array( $this, 'proxy_port_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'Username',
			esc_html__( 'Proxy Username', 'wp-proxy' ),
			array( $this, 'proxy_username_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'password',
			esc_html__( 'Proxy Password', 'wp-proxy' ),
			array( $this, 'proxy_password_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'domains',
			esc_html__( 'Proxy Domains', 'wp-proxy' ),
			array( $this, 'proxy_domains_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'enable',
			esc_html__( 'Enable', 'wp-proxy' ),
			array( $this, 'proxy_enable_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
	}

	/**
	 * Show options
	 *
	 * @since 1.0
	 */
	public function wp_proxy_option() {
		$wp_proxy_options = get_option( 'wp_proxy_options', $this->defualt_options() );
		$this->options    = $wp_proxy_options; ?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Proxy', 'wp-proxy' ); ?></h1>
			<form action="options.php" method="post" autocomplete="off">
				<?php
				settings_fields( 'wp_proxy' );
				do_settings_sections( 'wp_proxy' );
				?>
				<?php
					submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Show proxy host field
	 *
	 * @since 1.0
	 */
	public function proxy_host_callback() {
		?>
			<input id="proxy_host" name="proxy_host" type="text" placeholder="<?php esc_html_e( 'proxy host', 'wp-proxy' ); ?>" value="<?php echo esc_html( $this->options['proxy_host'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy port field
	 *
	 * @since 1.0
	 */
	public function proxy_port_callback() {
		?>
			<input id="proxy_port" name="proxy_port" type="number" placeholder="<?php esc_html_e( 'proxy port', 'wp-proxy' ); ?>" value="<?php echo esc_html( $this->options['proxy_port'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy username field
	 *
	 * @since 1.0
	 */
	public function proxy_username_callback() {
		?>
			<input id="username" name="username" type="text" placeholder="<?php esc_html_e( 'username', 'wp-proxy' ); ?>" value="<?php echo esc_html( $this->options['username'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy password field
	 *
	 * @since 1.0
	 */
	public function proxy_password_callback() {
		?>
			<input id="password" name="password" type="password" placeholder="<?php esc_html_e( 'password', 'wp-proxy' ); ?>" value="<?php echo esc_html( $this->options['password'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show domains field
	 *
	 * @since 1.0
	 */
	public function proxy_domains_callback() {
		?>
			<textarea name="domains" id="domains" cols="40" rows="5" autocomplete="off"><?php echo esc_attr( $this->options['domains'] ); ?></textarea>
		<?php
	}

	/**
	 * Show proxy enable field
	 *
	 * @since 1.0
	 */
	public function proxy_enable_callback() {
		?>
			<select name="enable" id="enable">
			<?php if ( $this->options['enable'] ) { ?>
				<option value="yes" selected="selected"><?php esc_html_e( 'yes', 'wp-proxy' ); ?></option>
				<option value="no"><?php esc_html_e( 'no', 'wp-proxy' ); ?></option>
			<?php } else { ?>
				<option value="yes"><?php esc_html_e( 'yes', 'wp-proxy' ); ?></option>
				<option value="no" selected="selected"><?php esc_html_e( 'no', 'wp-proxy' ); ?></option>
			<?php } ?>
			</select>
		<?php
	}
}
