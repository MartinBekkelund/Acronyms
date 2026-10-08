<?php
/**
 * Central acronym list for the Acronyms plugin.
 *
 * @package Acronyms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the central acronym list: the copy bundled with the plugin,
 * optional fetching from a URL, and per-site exclusions.
 */
class Acronyms_Central {

	/**
	 * Default URL for the central list.
	 *
	 * @var string
	 */
	const DEFAULT_URL = 'https://raw.githubusercontent.com/MartinBekkelund/Acronyms/main/data/central-acronyms.json'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- A JSON data file, not a script or asset. Fetching is off by default and documented in readme.txt under External services.

	/**
	 * WP-Cron hook for the daily fetch.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'acronyms_central_refresh';

	/**
	 * Supported version of the JSON format.
	 *
	 * @var int
	 */
	const FORMAT_VERSION = 1;

	/**
	 * Maximum response size in bytes (1 MB).
	 *
	 * @var int
	 */
	const MAX_BYTES = 1048576;

	/**
	 * Maximum number of acronyms in a central list.
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 5000;

	/**
	 * Option names.
	 */
	const OPTION_ENABLED  = 'acronyms_central_remote_enabled';
	const OPTION_URL      = 'acronyms_central_url';
	const OPTION_LIST     = 'acronyms_central_list';
	const OPTION_STATUS   = 'acronyms_central_status';
	const OPTION_EXCLUDED = 'acronyms_central_excluded';

	/**
	 * Parsed bundled list, kept for the current request.
	 *
	 * @var array|null
	 */
	private static $bundled = null;

	/**
	 * Register hooks. Runs on every request, since WP-Cron can fire on any of them.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'fetch' ) );

		add_action( 'add_option_' . self::OPTION_ENABLED, array( __CLASS__, 'on_enabled_changed' ) );
		add_action( 'update_option_' . self::OPTION_ENABLED, array( __CLASS__, 'on_enabled_changed' ) );
		add_action( 'add_option_' . self::OPTION_URL, array( __CLASS__, 'on_url_changed' ) );
		add_action( 'update_option_' . self::OPTION_URL, array( __CLASS__, 'on_url_changed' ) );
	}

	/**
	 * Get the URL in use: the site's own URL if set, otherwise the default.
	 *
	 * @return string
	 */
	public static function get_url() {
		$url = get_option( self::OPTION_URL, '' );
		return '' !== $url ? $url : self::DEFAULT_URL;
	}

	/**
	 * Whether the site uses its own URL instead of the default.
	 *
	 * @return bool
	 */
	public static function has_custom_url() {
		return '' !== get_option( self::OPTION_URL, '' );
	}

	/**
	 * Whether fetching the central list from the URL is turned on.
	 *
	 * @return bool
	 */
	public static function is_remote_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Whether the fetched list is the one in use (as opposed to the bundled copy).
	 *
	 * @return bool
	 */
	public static function uses_fetched_list() {
		$list = get_option( self::OPTION_LIST, array() );
		return self::is_remote_enabled() && is_array( $list ) && ! empty( $list );
	}

	/**
	 * Get all central acronyms in use, including excluded ones.
	 *
	 * @return array List of arrays with acronym, title and case_sensitive.
	 */
	public static function get_entries() {
		if ( self::uses_fetched_list() ) {
			return get_option( self::OPTION_LIST, array() );
		}

		return self::get_bundled_entries();
	}

	/**
	 * Get the central acronyms that are not excluded on this site.
	 *
	 * @return array List of arrays with acronym, title and case_sensitive.
	 */
	public static function get_active_entries() {
		$excluded = self::get_excluded();

		return array_values(
			array_filter(
				self::get_entries(),
				function ( $entry ) use ( $excluded ) {
					return ! in_array( self::key( $entry['acronym'] ), $excluded, true );
				}
			)
		);
	}

	/**
	 * Get the list bundled with the plugin.
	 *
	 * @return array List of arrays with acronym, title and case_sensitive.
	 */
	public static function get_bundled_entries() {
		if ( null !== self::$bundled ) {
			return self::$bundled;
		}

		self::$bundled = array();
		$file          = ACRONYMS_PLUGIN_DIR . 'data/central-acronyms.json';

		if ( is_readable( $file ) ) {
			$entries = self::parse( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in the plugin folder.
			if ( ! is_wp_error( $entries ) ) {
				self::$bundled = $entries;
			}
		}

		return self::$bundled;
	}

	/**
	 * Parse and validate a central list in JSON format.
	 *
	 * Invalid entries are skipped. Duplicates (ignoring case) keep the first entry.
	 *
	 * @param string $json Raw JSON.
	 * @return array|WP_Error List of entries, or an error if the file as a whole is unusable.
	 */
	public static function parse( $json ) {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || ! isset( $data['acronyms'] ) || ! is_array( $data['acronyms'] ) ) {
			return new WP_Error( 'acronyms_central_invalid', __( 'The file is not a valid central acronym list.', 'acronym-tooltips' ) );
		}

		if ( isset( $data['version'] ) && self::FORMAT_VERSION !== (int) $data['version'] ) {
			return new WP_Error( 'acronyms_central_version', __( 'The file uses a format version this plugin does not support.', 'acronym-tooltips' ) );
		}

		if ( count( $data['acronyms'] ) > self::MAX_ENTRIES ) {
			return new WP_Error(
				'acronyms_central_too_many',
				/* translators: %d: maximum number of acronyms. */
				sprintf( __( 'The file has more than %d acronyms.', 'acronym-tooltips' ), self::MAX_ENTRIES )
			);
		}

		$entries = array();

		foreach ( $data['acronyms'] as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['acronym'], $item['title'] ) || ! is_string( $item['acronym'] ) || ! is_string( $item['title'] ) ) {
				continue;
			}

			$acronym = sanitize_text_field( $item['acronym'] );
			$title   = sanitize_text_field( $item['title'] );

			if ( '' === $acronym || '' === $title || mb_strlen( $acronym ) > 100 || mb_strlen( $title ) > 500 ) {
				continue;
			}

			$key = self::key( $acronym );
			if ( isset( $entries[ $key ] ) ) {
				continue;
			}

			$entries[ $key ] = array(
				'acronym'        => $acronym,
				'title'          => $title,
				'case_sensitive' => isset( $item['case_sensitive'] ) ? (bool) $item['case_sensitive'] : true,
			);
		}

		if ( empty( $entries ) ) {
			return new WP_Error( 'acronyms_central_empty', __( 'The file has no valid acronyms.', 'acronym-tooltips' ) );
		}

		return array_values( $entries );
	}

	/**
	 * Fetch the central list from the URL in use.
	 *
	 * On success the list and status are stored. On failure the last good list
	 * is kept and the error is stored in the status.
	 *
	 * @return true|WP_Error
	 */
	public static function fetch() {
		$url    = self::get_url();
		$status = self::get_status();

		$status['last_attempt'] = time();
		$status['url']          = $url;

		$result = self::download( $url );

		if ( is_wp_error( $result ) ) {
			$status['error'] = $result->get_error_message();
			update_option( self::OPTION_STATUS, $status, false );
			return $result;
		}

		update_option( self::OPTION_LIST, $result, false );

		$status['last_success'] = $status['last_attempt'];
		$status['count']        = count( $result );
		$status['error']        = '';
		update_option( self::OPTION_STATUS, $status, false );

		Acronyms_DB::invalidate_cache();

		return true;
	}

	/**
	 * Download and parse a central list.
	 *
	 * @param string $url URL to fetch.
	 * @return array|WP_Error
	 */
	private static function download( $url ) {
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return new WP_Error( 'acronyms_central_https', __( 'The URL must start with https://.', 'acronym-tooltips' ) );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_BYTES,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'acronyms_central_http',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'The server answered with HTTP status %d.', 'acronym-tooltips' ), $code )
			);
		}

		return self::parse( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Get the status of the last fetch.
	 *
	 * @return array {
	 *     @type int    $last_attempt Unix time of the last attempt, 0 if never.
	 *     @type int    $last_success Unix time of the last successful fetch, 0 if never.
	 *     @type int    $count        Number of acronyms in the last successful fetch.
	 *     @type string $error        Error message from the last attempt, empty if it succeeded.
	 *     @type string $url          URL used in the last attempt.
	 * }
	 */
	public static function get_status() {
		$status = get_option( self::OPTION_STATUS, array() );

		return wp_parse_args(
			is_array( $status ) ? $status : array(),
			array(
				'last_attempt' => 0,
				'last_success' => 0,
				'count'        => 0,
				'error'        => '',
				'url'          => '',
			)
		);
	}

	/**
	 * Normalized key for comparing acronyms, matching Acronyms_DB::acronym_exists().
	 *
	 * @param string $acronym Acronym text.
	 * @return string
	 */
	public static function key( $acronym ) {
		return mb_strtolower( $acronym );
	}

	/**
	 * Get the keys of central acronyms excluded on this site.
	 *
	 * @return string[]
	 */
	public static function get_excluded() {
		$excluded = get_option( self::OPTION_EXCLUDED, array() );
		return is_array( $excluded ) ? $excluded : array();
	}

	/**
	 * Whether a central acronym is excluded on this site.
	 *
	 * @param string $acronym Acronym text.
	 * @return bool
	 */
	public static function is_excluded( $acronym ) {
		return in_array( self::key( $acronym ), self::get_excluded(), true );
	}

	/**
	 * Exclude or include a central acronym on this site.
	 *
	 * @param string $acronym  Acronym text.
	 * @param bool   $excluded True to turn the acronym off, false to turn it on.
	 */
	public static function set_excluded( $acronym, $excluded ) {
		$key  = self::key( $acronym );
		$list = array_diff( self::get_excluded(), array( $key ) );

		if ( $excluded ) {
			$list[] = $key;
		}

		update_option( self::OPTION_EXCLUDED, array_values( $list ), false );
		Acronyms_DB::invalidate_cache();
	}

	/**
	 * Schedule or unschedule the daily fetch to match the setting.
	 */
	public static function sync_schedule() {
		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( self::is_remote_enabled() && ! $scheduled ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		} elseif ( ! self::is_remote_enabled() && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Remove the daily fetch.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * When fetching is turned on or off: update the schedule, and fetch right away when turned on.
	 */
	public static function on_enabled_changed() {
		self::sync_schedule();
		Acronyms_DB::invalidate_cache();

		if ( self::is_remote_enabled() ) {
			self::fetch();
		}
	}

	/**
	 * When the URL changes: drop the list fetched from the old URL, and fetch from the new one.
	 *
	 * If the new fetch fails, the bundled copy is used until a fetch succeeds.
	 */
	public static function on_url_changed() {
		delete_option( self::OPTION_LIST );
		delete_option( self::OPTION_STATUS );
		Acronyms_DB::invalidate_cache();

		if ( self::is_remote_enabled() ) {
			self::fetch();
		}
	}
}
