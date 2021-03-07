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
	 */
	public function __construct() {
		$this->load_plugin_textdomain();
		$options = get_option( 'wp_proxy_options', false );
		if ( $options ) {
			$this->options = wp_parse_args( $options, $this->defualt_options() );
			if ( $options['enable'] ) {
				add_filter( 'http_request_args', array( $this, 'http_request_args' ), 100, 2 );
				add_filter( 'pre_http_send_through_proxy', array( $this, 'send_through_proxy' ), 10, 4 );
				defined( 'WP_PROXY_HOST' ) ? '' : define( 'WP_PROXY_HOST', $options['proxy_host'] );
				defined( 'WP_PROXY_PORT' ) ? '' : define( 'WP_PROXY_PORT', $options['proxy_port'] );
				if ( ! empty( $options['username'] ) ) {
					defined( 'WP_PROXY_USERNAME' ) ? '' : define( 'WP_PROXY_USERNAME', $options['username'] );
				}
				if ( ! empty( $options['password'] ) ) {
					defined( 'WP_PROXY_PASSWORD' ) ? '' : define( 'WP_PROXY_PASSWORD', $options['password'] );
				}
				add_action( 'http_api_curl', array( $this, 'curl_before_send' ), 100, 3 );
			}
		} else {
			add_option( 'wp_proxy_options', $this->defualt_options() );
			$this->options = $this->defualt_options();
		}
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'options_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_init', array( $this, 'wp_proxy_enable_or_disable' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 1000 );
			add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
		}
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
	 * Magic call static
	 *
	 * @since 1.3.9
	 */
	public static function __callStatic ( $name, $args ) {
		return call_user_func_array( array( new WP_Proxy, $name ), $args );
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
		$options                = array();
		$options['domains']     = '*.wordpress.org';
		$options['proxy_host']  = '127.0.0.1';
		$options['proxy_port']  = '1080';
		$options['username']    = '';
		$options['password']    = '';
		$options['type']        = '';
		$options['global_mode'] = false;
		$options['enable']      = false;
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
				$wp_proxy_options = $this->options;
				if ( isset( $_POST['proxy_host'] ) ) {
					$wp_proxy_options['proxy_host'] = sanitize_text_field( wp_unslash( $_POST['proxy_host'] ) );
				}
				if ( isset( $_POST['proxy_port'] ) ) {
					$port = abs( sanitize_text_field( wp_unslash( $_POST['proxy_port'] ) ) );
					if ( 0 === $port || 65535 < $port ) {
						add_settings_error( 'wp_proxy', 500, esc_html__( 'Wrong port', 'wp-proxy' ), 'error' );
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
				if ( isset( $_POST['type'] ) ) {
					$wp_proxy_options['type'] = sanitize_text_field( wp_unslash( $_POST['type'] ) );
				}
				if ( isset( $_POST['domains'] ) ) {
					$wp_proxy_options['domains'] = str_replace( ' ', "\n", sanitize_text_field( wp_unslash( $_POST['domains'] ) ) );
				}
				if ( isset( $_POST['global_mode'] ) ) {
					if ( 'yes' === sanitize_text_field( wp_unslash( $_POST['global_mode'] ) ) ) {
						$wp_proxy_options['global_mode'] = true;
					} else {
						$wp_proxy_options['global_mode'] = false;
					}
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
	 * Enable or disable
	 *
	 * @since 1.3.4
	 */
	public function wp_proxy_enable_or_disable() {
		// avoid invalid nonce.
		if ( isset( $_GET['wp_proxy'] ) && check_admin_referer( 'wp-proxy-quick-set', 'wp-proxy-quick-set' ) ) {
			$wp_proxy_options = $this->options;
			$val              = sanitize_text_field( wp_unslash( $_GET['wp_proxy'] ) );
			if ( 'enable' === $val ) {
				$wp_proxy_options['enable'] = true;
			} else if( 'disable' === $val ) {
				$wp_proxy_options['enable'] = false;
			} else if( 'enable_in_global_mode' === $val ) {
				$wp_proxy_options['global_mode'] = true;
				$wp_proxy_options['enable'] = true;
			} else if( 'disable_in_global_mode' === $val ) {
				$wp_proxy_options['global_mode'] = false;
				$wp_proxy_options['enable'] = false;
			}
			update_option( 'wp_proxy_options', $wp_proxy_options );
		}
	}

	/**
	 * In plugins page show some links
	 *
	 * @param array  $links
	 * @param string $file
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
	 * @param array  $links
	 * @param string $file
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
	 * Admin bar menu
	 *
	 * @param mixed $wp_admin_bar
	 * @since 1.3.4
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if ( is_user_logged_in() && is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			$options = get_option( 'wp_proxy_options' );
			$url     = admin_url( 'options-general.php?page=wp_proxy' );
			$wp_admin_bar->add_node(
				array(
					'id'    => 'wp_proxy',
					'title' => __( 'WP Proxy' ),
					'href'  => $url,
				)
			);
			if ( $options['enable'] ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'disable_wp_proxy',
						'parent' => 'wp_proxy',
						'title'  => __( 'Disabled' ),
						'href'   => wp_nonce_url( add_query_arg( 'wp_proxy', 'disable' ), 'wp-proxy-quick-set', 'wp-proxy-quick-set' ),
					)
				);
			} else {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'enable_wp_proxy',
						'parent' => 'wp_proxy',
						'title'  => __( 'Enabled' ),
						'href'   => wp_nonce_url( add_query_arg( 'wp_proxy', 'enable' ), 'wp-proxy-quick-set', 'wp-proxy-quick-set' ),
					)
				);
			}
			if ( $options['global_mode'] ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'disable_wp_proxy_gloabl_mode',
						'parent' => 'wp_proxy',
						'title'  => __( 'Disabled' ) . ' ' . esc_html__( 'Global mode', 'wp-proxy' ),
						'href'   => wp_nonce_url( add_query_arg( 'wp_proxy', 'disable_in_global_mode' ), 'wp-proxy-quick-set', 'wp-proxy-quick-set' ),
					)
				);
			} else {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'enable_wp_proxy_global_mode',
						'parent' => 'wp_proxy',
						'title'  => __( 'Enabled' ) . ' ' . esc_html__( 'Global mode', 'wp-proxy' ),
						'href'   => wp_nonce_url( add_query_arg( 'wp_proxy', 'enable_in_global_mode' ), 'wp-proxy-quick-set', 'wp-proxy-quick-set' ),
					)
				);
			}
		}
	}

	/**
	 * Set request arg
	 *
	 * @param array  $parsed_args
	 * @param string $url
	 */
	public function http_request_args( $parsed_args, $url ) {
		if ( $this->send_through_proxy( null, $url, $url, '' ) ) {
			$parsed_args['timeout'] = $parsed_args['timeout'] + 1200;
			@set_time_limit( $parsed_args['timeout'] + 60 );
		}
		return $parsed_args;
	}

	/**
	 * Set proxy type
	 *
	 * @param resource $handle
	 * @param array    $request
	 * @param string   $url
	 * @since 1.3.8
	 */
	public function curl_before_send( $handle, $request, $url ) {
		if ( $this->send_through_proxy( null, $url, $url, '' ) ) {
			if ( 'SOCKS5' === $this->options['type'] ) {
				curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5 );
			} elseif ( 'SOCKS4' === $this->options['type'] ) {
				curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4 );
			} elseif ( 'SOCKS4A' === $this->options['type'] ) {
				curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4A );
			}
		}
	}

	/**
	 * Check URL
	 *
	 * @param string $null
	 * @param string $url
	 * @param bool   $check
	 * @param string $home
	 * @since 1.0
	 */
	public function send_through_proxy( $null, $url, $check, $home ) {
		if ( $this->options['global_mode'] ) {
			return true;
		}
		$rules = explode( "\n", $this->options['domains'] );
		$host  = false;
		if ( ! is_array( $check ) ) {
			$check = wp_parse_url( $check );
		}
		if ( isset( $check['host'] ) ) {
			$host = $check['host'];
		}
		$regex = array();
		foreach ( $rules as $rule ) {
			if ( $rule === $host ) {
				return true;
			} else {
				$regex[] = str_replace( '\*', '.+', preg_quote( $rule, '/' ) );
			}
		}
		if ( ! empty( $regex ) ) {
			$regex = '^(' . implode( '|', $regex ) . ')$';
			return preg_match( '#' . $regex . '#i', $host );
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
			__( 'Hostname' ),
			array( $this, 'proxy_host_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'proxy_port',
			__( 'Port' ),
			array( $this, 'proxy_port_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'Username',
			__( 'Username' ),
			array( $this, 'proxy_username_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'password',
			__( 'Password' ),
			array( $this, 'proxy_password_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'type',
			__( 'Type' ),
			array( $this, 'proxy_type_callback' ),
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
			'global_mode',
			esc_html__( 'Global mode', 'wp-proxy' ),
			array( $this, 'proxy_global_mode_callback' ),
			'wp_proxy',
			'wp_proxy_config'
		);
		add_settings_field(
			'enable',
			__( 'Enabled' ),
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
		$this->options = wp_parse_args( get_option( 'wp_proxy_options', [] ), $this->defualt_options() ); ?>
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
			<input id="proxy_host" name="proxy_host" type="text" placeholder="<?php esc_html_e( 'Hostname' ); ?>" value="<?php echo esc_html( $this->options['proxy_host'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy port field
	 *
	 * @since 1.0
	 */
	public function proxy_port_callback() {
		?>
			<input id="proxy_port" name="proxy_port" type="number" placeholder="<?php esc_html_e( 'Port' ); ?>" value="<?php echo esc_html( $this->options['proxy_port'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy username field
	 *
	 * @since 1.0
	 */
	public function proxy_username_callback() {
		?>
			<input id="username" name="username" type="text" placeholder="<?php esc_html_e( 'Username' ); ?>" value="<?php echo esc_html( $this->options['username'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy password field
	 *
	 * @since 1.0
	 */
	public function proxy_password_callback() {
		?>
			<input id="password" name="password" type="password" placeholder="<?php esc_html_e( 'Password' ); ?>" value="<?php echo esc_html( $this->options['password'] ); ?>" autocomplete="off">
		<?php
	}

	/**
	 * Show proxy type field
	 *
	 * @since 1.3.8
	 */
	public function proxy_type_callback() {
		?>
			<select name="type" id="type" autocomplete="off">
				<option value="" <?php selected( $this->options['type'], '', true ); ?>>http</option>
				<option value="SOCKS5" <?php selected( $this->options['type'], 'SOCKS5', true ); ?>>socks5</option>
				<option value="SOCKS4" <?php selected( $this->options['type'], 'SOCKS4', true ); ?>>socks4</option>
				<option value="SOCKS4A" <?php selected( $this->options['type'], 'SOCKS4A', true ); ?>>socks4a</option>
			</select>
		<?php
	}

	/**
	 * Show domains field
	 *
	 * @since 1.0
	 */
	public function proxy_domains_callback() {
		?>
			<textarea name="domains" id="domains" cols="40" rows="5" autocomplete="off" <?php echo $this->options['global_mode'] ? 'disabled="disabled"' : '' ?>><?php echo esc_attr( $this->options['domains'] ); ?></textarea>
		<?php
	}

	/**
	 * Show proxy global_mode field
	 *
	 * @since 1.3.9
	 */
	public function proxy_global_mode_callback() {
		?>
			<select name="global_mode" id="global_mode">
			<?php if ( $this->options['global_mode'] ) { ?>
				<option value="yes" selected="selected"><?php esc_html_e( 'Yes' ); ?></option>
				<option value="no"><?php esc_html_e( 'No' ); ?></option>
			<?php } else { ?>
				<option value="yes"><?php esc_html_e( 'Yes' ); ?></option>
				<option value="no" selected="selected"><?php esc_html_e( 'No' ); ?></option>
			<?php } ?>
			</select>
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
				<option value="yes" selected="selected"><?php esc_html_e( 'Yes' ); ?></option>
				<option value="no"><?php esc_html_e( 'No' ); ?></option>
			<?php } else { ?>
				<option value="yes"><?php esc_html_e( 'Yes' ); ?></option>
				<option value="no" selected="selected"><?php esc_html_e( 'No' ); ?></option>
			<?php } ?>
			</select>
		<?php
	}
}
