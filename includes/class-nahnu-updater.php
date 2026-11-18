<?php
/**
 * Nahnu_Updater: bundled update class for public / standalone plugins.
 *
 * Use this when you want a plugin to self-update WITHOUT the Nahnu Updater
 * plugin installed on the client site (e.g. public or free plugins).
 *
 * USAGE: add these lines to your main plugin file:
 * ─────────────────────────────────────────────────
 *   if ( ! defined( 'NAHNU_UPDATER_WORKER_URL' ) ) {
 *       define( 'NAHNU_UPDATER_WORKER_URL', 'https://your-worker.workers.dev' );
 *   }
 *   require_once __DIR__ . '/includes/class-nahnu-updater.php';
 *   Nahnu_Updater::register( __FILE__ );        // public plugin (no key)
 *   Nahnu_Updater::register( __FILE__, $key );  // private plugin (with key)
 *
 * The plugin folder name is used as the slug automatically.
 * It must match the slug registered in Nahnu Update Master.
 *
 * NOTE: If the client site has the Nahnu Updater plugin installed, that plugin
 * handles updates automatically  via bulk check, so you do NOT need this class.
 * This class is only needed for standalone / public plugin distribution.
 *
 * Compatible with PHP 7.4+ because it runs inside other plugins.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Several plugins may bundle this file. The first copy loaded defines the class;
// later copies are skipped. (Re-declaring a class is a fatal error in PHP, so
// "newest copy wins" is not possible without renaming the class.)
if ( ! class_exists( 'Nahnu_Updater', false ) ) {

	if ( ! defined( 'NAHNU_UPDATER_CLASS_VERSION' ) ) {
		define( 'NAHNU_UPDATER_CLASS_VERSION', '1.5.0' );
	}

	class Nahnu_Updater {

		/** @var string */  private $slug;
		/** @var string */  private $version;
		/** @var string */  private $file;
		/** @var string */  private $worker_url;
		/** @var ?string */ private $api_key;
		/** @var string */  private $cache_key;

		/** @var int */ public $cache_ttl       = 43200; // 12h after a good response
		/** @var int */ public $error_cache_ttl = 3600;  // 1h after a failed response

		// ─── Static factory ───────────────────────────────────────────────

		/**
		 * Register a plugin for self-managed updates.
		 * Uses the plugin folder name as the slug.
		 *
		 * @param string      $plugin_file  Your main plugin file path (__FILE__)
		 * @param string|null $api_key      For private plugins. Null for public.
		 */
		public static function register( $plugin_file, $api_key = null ) {
			$worker_url = defined( 'NAHNU_UPDATER_WORKER_URL' ) ? (string) NAHNU_UPDATER_WORKER_URL : '';
			if ( $worker_url === '' ) return;

			// Derive slug from plugin folder name; it must match registered slug
			$relative = plugin_basename( $plugin_file );
			$parts    = explode( '/', $relative );
			$slug     = count( $parts ) > 1
				? sanitize_key( $parts[0] )
				: sanitize_key( pathinfo( $parts[0], PATHINFO_FILENAME ) );

			if ( $slug === '' ) return;

			// Read version from plugin header
			$data    = get_file_data( $plugin_file, [ 'version' => 'Version' ] );
			$version = ! empty( $data['version'] ) ? $data['version'] : '0.0.0';

			if ( $api_key === null && defined( 'NAHNU_UPDATER_API_KEY' ) ) {
				$api_key = (string) NAHNU_UPDATER_API_KEY;
			}

			new self( [
				'slug'       => $slug,
				'version'    => $version,
				'file'       => $plugin_file,
				'worker_url' => $worker_url,
				'api_key'    => $api_key ? (string) $api_key : null,
			] );
		}

		// ─── Constructor ─────────────────────────────────────────────────

		public function __construct( array $args ) {
			$this->slug       = sanitize_key( isset( $args['slug'] ) ? $args['slug'] : '' );
			$this->version    = isset( $args['version'] ) ? (string) $args['version'] : '0.0.0';
			$this->file       = isset( $args['file'] ) ? (string) $args['file'] : '';
			$this->api_key    = isset( $args['api_key'] ) ? $args['api_key'] : null;
			$this->worker_url = rtrim( isset( $args['worker_url'] ) ? (string) $args['worker_url'] : '', '/' );
			$this->cache_key  = 'nahnu_upd_' . md5( $this->slug );

			if ( $this->slug === '' || $this->file === '' || $this->worker_url === '' ) return;

			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
			// Reads of the update list, including early answers some tools give (MainWP Child)
			add_filter( 'site_transient_update_plugins',         [ $this, 'inject' ] );
			add_filter( 'pre_site_transient_update_plugins',     [ $this, 'inject_early' ], 100 );
			// MainWP: ask fresh before a sync reads updates and before MainWP runs updates
			add_filter( 'mwp_premium_update_check',              [ $this, 'mainwp_register' ] );
			add_action( 'mainwp_child_before_update_plugin_theme', [ $this, 'refresh_before_update' ] );
			add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 10, 3 );
			add_action( 'upgrader_process_complete',             [ $this, 'clear_cache' ], 10, 2 );
			// Dashboard → Updates "Check Again" deletes WP's transient, so refresh ours too
			add_action( 'delete_site_transient_update_plugins',  [ $this, 'flush' ] );
			// Private downloads send the API key in a header, so it never appears in a link
			add_filter( 'upgrader_pre_download',                 [ $this, 'pre_download' ], 10, 4 );
		}

		/** True when $url is on the update server's scheme + host + port. */
		private function is_server_url( $url ) {
			$a = wp_parse_url( (string) $url );
			$b = wp_parse_url( $this->worker_url );
			if ( empty( $a['host'] ) || empty( $b['host'] ) ) return false;
			return strtolower( $a['host'] ) === strtolower( $b['host'] )
				&& ( isset( $a['scheme'] ) ? $a['scheme'] : '' ) === ( isset( $b['scheme'] ) ? $b['scheme'] : '' )
				&& ( isset( $a['port'] ) ? $a['port'] : null ) === ( isset( $b['port'] ) ? $b['port'] : null );
		}

		private function headers() {
			$h = [ 'X-WP-Site' => home_url(), 'X-Nahnu-Client' => '2' ];
			if ( $this->api_key ) $h['X-Api-Key'] = $this->api_key;
			return $h;
		}

		/** Icons array WordPress shows in Dashboard → Updates. */
		private function icons( $data ) {
			$url = ( is_array( $data ) && ! empty( $data['icon_url'] ) ) ? esc_url_raw( (string) $data['icon_url'] ) : '';
			if ( $url === '' || ! $this->is_server_url( $url ) ) return [];
			return [ '1x' => $url, '2x' => $url, 'default' => $url ];
		}

		// ─── Hooks ───────────────────────────────────────────────────────

		public function check_for_update( $transient ) {
			if ( ! is_object( $transient ) || empty( $transient->checked ) ) return $transient;
			// wp_update_plugins() stamps last_checked just before it checks; if WordPress has
			// checked since we last asked our server, ask again so new uploads show up.
			$wp_checked = isset( $transient->last_checked ) ? (int) $transient->last_checked : 0;
			$data = $this->fetch( $wp_checked > 0 && $wp_checked > $this->fetched_at() );
			return $this->apply( $transient, $data );
		}

		/** Our offer replaces any older one; an offer for what's installed is removed. */
		private function apply( $transient, $data ) {
			$plugin_file = plugin_basename( $this->file );
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = [];
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = [];
			$sv = ( $data && isset( $data['version'] ) ) ? (string) $data['version'] : '';

			if ( $sv !== '' && version_compare( $sv, $this->version, '>' ) ) {
				$transient->response[ $plugin_file ] = (object) [
					'slug'         => $this->slug,
					'plugin'       => $plugin_file,
					'new_version'  => $sv,
					'url'          => '',
					'package'      => isset( $data['download_url'] ) ? $data['download_url'] : '',
					'tested'       => isset( $data['tested'] ) ? $data['tested'] : '',
					'requires'     => isset( $data['requires'] ) ? $data['requires'] : '',
					'requires_php' => isset( $data['requires_php'] ) ? $data['requires_php'] : '',
					'icons'        => $this->icons( $data ),
				];
				unset( $transient->no_update[ $plugin_file ] );
				return $transient;
			}

			if ( isset( $transient->response[ $plugin_file ] ) ) {
				$old = $transient->response[ $plugin_file ];
				$pkg = is_object( $old ) && isset( $old->package ) ? (string) $old->package : '';
				$ver = is_object( $old ) && isset( $old->new_version ) ? (string) $old->new_version : '';
				if ( ! $this->is_server_url( $pkg ) && $ver !== '' && version_compare( $ver, $this->version, '>' ) ) return $transient;
				unset( $transient->response[ $plugin_file ] );
			}
			// no_update entry also enables WordPress's "Enable auto-updates" link
			$transient->no_update[ $plugin_file ] = (object) [
				'slug'        => $this->slug,
				'plugin'      => $plugin_file,
				'new_version' => $this->version,
				'url'         => '',
				'package'     => '',
			];
			return $transient;
		}

		public function inject( $transient ) {
			if ( ! is_object( $transient ) ) return $transient;
			return $this->apply( $transient, $this->fetch() );
		}

		public function inject_early( $pre ) {
			return is_object( $pre ) ? $this->inject( $pre ) : $pre;
		}

		public function mainwp_register( $checks ) {
			if ( ! is_array( $checks ) ) $checks = [];
			$checks[] = [ 'callback' => [ $this, 'refresh' ] ];
			return $checks;
		}

		/** Ask our server now, unless we did in the last 30 seconds. */
		public function refresh() {
			if ( $this->fetched_at() > time() - 30 ) return;
			$this->fetch( true );
		}

		/** Right before MainWP runs updates: always ask fresh. */
		public function refresh_before_update() {
			$this->fetch( true );
		}

		private function fetched_at() {
			$c = get_transient( $this->cache_key );
			return ( is_array( $c ) && isset( $c['t'] ) ) ? (int) $c['t'] : 0;
		}

		public function plugin_info( $result, $action, $args ) {
			if ( $action !== 'plugin_information' || ! is_object( $args ) || ( isset( $args->slug ) ? $args->slug : '' ) !== $this->slug ) {
				return $result;
			}
			$data = $this->fetch();
			if ( ! $data ) return $result;

			return (object) [
				'name'          => isset( $data['name'] ) ? $data['name'] : $this->slug,
				'slug'          => $this->slug,
				'version'       => isset( $data['version'] ) ? $data['version'] : $this->version,
				'tested'        => isset( $data['tested'] ) ? $data['tested'] : '',
				'requires'      => isset( $data['requires'] ) ? $data['requires'] : '',
				'requires_php'  => isset( $data['requires_php'] ) ? $data['requires_php'] : '',
				'last_updated'  => isset( $data['last_updated'] ) ? $data['last_updated'] : '',
				'sections'      => [ 'changelog' => nl2br( esc_html( isset( $data['changelog'] ) ? $data['changelog'] : '' ) ) ],
				'download_link' => isset( $data['download_url'] ) ? $data['download_url'] : '',
			];
		}

		public function clear_cache( $upgrader, $options ) {
			if (
				is_array( $options ) &&
				( isset( $options['action'] ) ? $options['action'] : '' ) === 'update' &&
				( isset( $options['type'] ) ? $options['type'] : '' ) === 'plugin' &&
				in_array( plugin_basename( $this->file ), (array) ( isset( $options['plugins'] ) ? $options['plugins'] : [] ), true )
			) {
				$this->flush();
			}
		}

		public function flush() {
			delete_transient( $this->cache_key );
		}

		/**
		 * Download our own package with the API key in a header. Other packages, and requests a
		 * different handler already answered, are left alone.
		 */
		public function pre_download( $reply, $package, $upgrader = null, $hook_extra = [] ) {
			if ( $reply !== false || ! is_string( $package ) || ! $this->is_server_url( $package ) ) return $reply;
			$path = (string) wp_parse_url( $package, PHP_URL_PATH );
			if ( strpos( $path, '/api/download/' . $this->slug . '/' ) !== 0 ) return $reply;

			if ( ! function_exists( 'wp_tempnam' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp = wp_tempnam( $this->slug . '.zip' );
			if ( ! $tmp ) return new WP_Error( 'nahnu_tmp', 'Could not create a temporary file for the download.' );

			// Drop any ?api_key= an older server put in the link
			$parts = wp_parse_url( $package );
			$url   = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $path;

			$res = wp_remote_get( $url, [ 'timeout' => 300, 'sslverify' => true, 'stream' => true, 'filename' => $tmp, 'headers' => $this->headers() ] );
			if ( is_wp_error( $res ) ) {
				@unlink( $tmp ); // phpcs:ignore
				return new WP_Error( 'nahnu_download_failed', 'Download failed: ' . $res->get_error_message() );
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$fh   = @fopen( $tmp, 'rb' ); // phpcs:ignore
			$head = $fh ? (string) fread( $fh, 200 ) : '';
			if ( $fh ) fclose( $fh );
			if ( $code !== 200 || substr( $head, 0, 4 ) !== "PK\x03\x04" ) {
				@unlink( $tmp ); // phpcs:ignore
				$why = $code !== 200 ? trim( wp_strip_all_tags( $head ) ) : 'the server did not send a ZIP file';
				return new WP_Error( 'nahnu_download_failed', sprintf( 'Download failed (HTTP %d)%s', $code, $why !== '' ? ': ' . $why : '' ) );
			}
			return $tmp;
		}

		// ─── Fetch ───────────────────────────────────────────────────────

		private function fetch( $force = false ) {
			$cached = get_transient( $this->cache_key );
			if ( ! $force && $cached !== false ) {
				if ( is_array( $cached ) && array_key_exists( 'd', $cached ) ) return empty( $cached['d'] ) ? null : $cached['d'];
				return empty( $cached ) ? null : $cached; // written by an older copy of this class
			}

			$response = wp_remote_get( $this->worker_url . '/api/check/' . rawurlencode( $this->slug ) . '?v=' . rawurlencode( $this->version ), [
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => $this->headers(),
			] );

			if ( is_wp_error( $response ) ) {
				$this->store( null, $this->error_cache_ttl );
				return null;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code === 200 && is_array( $body ) && ! empty( $body['no_update'] ) ) {
				$this->store( null, $this->cache_ttl ); // server answered: no update right now
				return null;
			}
			if ( $code !== 200 || ! is_array( $body ) || empty( $body['version'] ) ) {
				// Error / unexpected reply: retry sooner so a blip doesn't hide updates for 12h
				$this->store( null, $this->error_cache_ttl );
				return null;
			}
			$this->store( $body, $this->cache_ttl );
			return $body;
		}

		private function store( $data, $ttl ) {
			set_transient( $this->cache_key, [ 't' => time(), 'd' => $data ], $ttl );
		}
	}
}
