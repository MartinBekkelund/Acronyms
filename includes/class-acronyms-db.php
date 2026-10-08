<?php
/**
 * Database operations for the Acronyms plugin.
 *
 * @package Acronyms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles all database interactions: schema creation, CRUD, and caching.
 */
class Acronyms_DB {

	/**
	 * Cache key for the acronym list transient.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'acronyms_list';

	/**
	 * Cache TTL in seconds (24 hours).
	 *
	 * @var int
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Get the custom table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'acronyms';
	}

	/**
	 * Plugin activation: create or update the custom table and set default options.
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			acronym varchar(100) NOT NULL,
			title varchar(500) NOT NULL,
			case_sensitive tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY acronym_index (acronym)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( false === get_option( 'acronyms_post_types' ) ) {
			update_option( 'acronyms_post_types', array( 'post', 'page' ) );
		}

		update_option( 'acronyms_db_version', ACRONYMS_DB_VERSION );
	}

	/**
	 * Plugin deactivation: clear the transient cache but preserve data.
	 */
	public static function deactivate() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Plugin uninstall: remove all traces.
	 */
	public static function uninstall() {
		global $wpdb;

		$table_name = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'acronyms_post_types' );
		delete_option( 'acronyms_db_version' );
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Check if the DB schema needs upgrading and run dbDelta if so.
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'acronyms_db_version', '0.0.0' );

		if ( version_compare( $installed_version, ACRONYMS_DB_VERSION, '<' ) ) {
			self::activate();
		}
	}

	/**
	 * Get all acronyms for front-end replacement, sorted longest-first.
	 * Uses transient caching.
	 *
	 * @return array Array of objects with acronym, title, case_sensitive properties.
	 */
	public static function get_acronyms_for_replacement() {
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = self::table_name();

		$results = $wpdb->get_results(
			"SELECT acronym, title, case_sensitive FROM {$table} ORDER BY CHAR_LENGTH(acronym) DESC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! is_array( $results ) ) {
			$results = array();
		}

		set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Get paginated acronyms for the admin list table.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $per_page Number of items per page. Default 20.
	 *     @type int    $page     Current page number. Default 1.
	 *     @type string $orderby  Column to order by. Default 'acronym'.
	 *     @type string $order    Sort direction (ASC or DESC). Default 'ASC'.
	 *     @type string $search   Search term. Default empty.
	 * }
	 * @return array Array of acronym objects.
	 */
	public static function get_acronyms( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'acronym',
			'order'    => 'ASC',
			'search'   => '',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::table_name();

		$allowed_orderby = array( 'acronym', 'title', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'acronym';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$per_page        = absint( $args['per_page'] );
		$offset          = absint( ( $args['page'] - 1 ) * $per_page );

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE acronym LIKE %s OR title LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like,
					$like,
					$per_page,
					$offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count total acronyms, optionally filtered by search term.
	 *
	 * @param string $search Search term. Default empty.
	 * @return int Total count.
	 */
	public static function count_acronyms( $search = '' ) {
		global $wpdb;
		$table = self::table_name();

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE acronym LIKE %s OR title LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$like,
					$like
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get a single acronym by ID.
	 *
	 * @param int $id Acronym ID.
	 * @return object|null Acronym object or null.
	 */
	public static function get_acronym( $id ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Check if an acronym text already exists in the database.
	 *
	 * @param string   $acronym    Acronym text to check.
	 * @param int|null $exclude_id Optional ID to exclude (for edit operations).
	 * @return bool True if a duplicate exists.
	 */
	public static function acronym_exists( $acronym, $exclude_id = null ) {
		global $wpdb;
		$table = self::table_name();

		if ( null !== $exclude_id ) {
			return (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE LOWER(acronym) = LOWER(%s) AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$acronym,
					$exclude_id
				)
			);
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE LOWER(acronym) = LOWER(%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$acronym
			)
		);
	}

	/**
	 * Add a new acronym.
	 *
	 * @param string $acronym       Acronym text.
	 * @param string $title         Full meaning.
	 * @param bool   $case_sensitive Whether matching is case-sensitive.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public static function add_acronym( $acronym, $title, $case_sensitive ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::table_name(),
			array(
				'acronym'        => $acronym,
				'title'          => $title,
				'case_sensitive' => $case_sensitive ? 1 : 0,
			),
			array( '%s', '%s', '%d' )
		);

		if ( false !== $result ) {
			self::invalidate_cache();
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update an existing acronym.
	 *
	 * @param int    $id             Acronym ID.
	 * @param string $acronym        Acronym text.
	 * @param string $title          Full meaning.
	 * @param bool   $case_sensitive Whether matching is case-sensitive.
	 * @return bool True on success, false on failure.
	 */
	public static function update_acronym( $id, $acronym, $title, $case_sensitive ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table_name(),
			array(
				'acronym'        => $acronym,
				'title'          => $title,
				'case_sensitive' => $case_sensitive ? 1 : 0,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			self::invalidate_cache();
			return true;
		}

		return false;
	}

	/**
	 * Delete an acronym by ID.
	 *
	 * @param int $id Acronym ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_acronym( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			self::table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false !== $result ) {
			self::invalidate_cache();
			return true;
		}

		return false;
	}

	/**
	 * Invalidate the acronym list transient cache.
	 */
	public static function invalidate_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
