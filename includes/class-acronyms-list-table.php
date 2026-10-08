<?php
/**
 * List table for displaying acronyms in the admin.
 *
 * @package Acronyms
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom list table for managing acronyms.
 */
class Acronyms_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'acronym',
				'plural'   => 'acronyms',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define the table columns.
	 *
	 * @return array Column slug => label.
	 */
	public function get_columns() {
		return array(
			'acronym'        => __( 'Acronym', 'acronym-tooltips' ),
			'title'          => __( 'Full Meaning', 'acronym-tooltips' ),
			'case_sensitive' => __( 'Case Sensitive', 'acronym-tooltips' ),
			'source'         => __( 'Source', 'acronym-tooltips' ),
			'created_at'     => __( 'Date Added', 'acronym-tooltips' ),
		);
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array Column slug => array( orderby value, default descending ).
	 */
	public function get_sortable_columns() {
		return array(
			'acronym'    => array( 'acronym', false ),
			'title'      => array( 'title', false ),
			'source'     => array( 'source', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * Local and central acronyms are merged, then searched, sorted and paginated in PHP.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = 20;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'acronym'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = isset( $_REQUEST['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'desc' : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $orderby, array( 'acronym', 'title', 'source', 'created_at' ), true ) ) {
			$orderby = 'acronym';
		}

		$items = $this->get_merged_items();

		if ( '' !== $search ) {
			$needle = mb_strtolower( $search );
			$items  = array_filter(
				$items,
				function ( $item ) use ( $needle ) {
					return false !== mb_strpos( mb_strtolower( $item->acronym ), $needle )
						|| false !== mb_strpos( mb_strtolower( $item->title ), $needle );
				}
			);
		}

		usort(
			$items,
			function ( $a, $b ) use ( $orderby, $order ) {
				$result = strnatcasecmp( (string) $a->$orderby, (string) $b->$orderby );
				if ( 0 === $result ) {
					$result = strnatcasecmp( $a->acronym, $b->acronym );
				}
				return 'desc' === $order ? -$result : $result;
			}
		);

		$total_items = count( $items );
		$offset      = ( $this->get_pagenum() - 1 ) * $per_page;
		$this->items = array_slice( $items, $offset, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Build one list of local and central acronyms.
	 *
	 * Each item has acronym, title, case_sensitive, source ('local' or 'central')
	 * and created_at. Local items also have id. Central items also have
	 * central_status: 'active', 'disabled' or 'overridden'.
	 *
	 * @return array Array of objects.
	 */
	private function get_merged_items() {
		$items      = array();
		$local_keys = array();

		foreach ( Acronyms_DB::get_all_acronyms() as $row ) {
			$row->source = 'local';
			$items[]     = $row;

			$local_keys[ Acronyms_Central::key( $row->acronym ) ] = true;
		}

		foreach ( Acronyms_Central::get_entries() as $entry ) {
			$key = Acronyms_Central::key( $entry['acronym'] );

			if ( isset( $local_keys[ $key ] ) ) {
				$status = 'overridden';
			} elseif ( Acronyms_Central::is_excluded( $entry['acronym'] ) ) {
				$status = 'disabled';
			} else {
				$status = 'active';
			}

			$items[] = (object) array(
				'acronym'        => $entry['acronym'],
				'title'          => $entry['title'],
				'case_sensitive' => $entry['case_sensitive'],
				'source'         => 'central',
				'central_status' => $status,
				'created_at'     => '',
			);
		}

		return $items;
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item        The current acronym row.
	 * @param string $column_name Column slug.
	 * @return string Column value.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return esc_html( $item->title );
			case 'created_at':
				if ( '' === $item->created_at ) {
					return '&mdash;';
				}
				return esc_html(
					date_i18n(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $item->created_at )
					)
				);
			default:
				return '';
		}
	}

	/**
	 * Render the source column: local, or central with its status on this site.
	 *
	 * @param object $item The current acronym row.
	 * @return string Column HTML.
	 */
	public function column_source( $item ) {
		if ( 'local' === $item->source ) {
			return esc_html__( 'Local', 'acronym-tooltips' );
		}

		$statuses = array(
			'active'     => __( 'Active', 'acronym-tooltips' ),
			'disabled'   => __( 'Turned off', 'acronym-tooltips' ),
			'overridden' => __( 'Overridden by local', 'acronym-tooltips' ),
		);

		return sprintf(
			'%s<br /><span class="description">%s</span>',
			esc_html__( 'Central', 'acronym-tooltips' ),
			esc_html( $statuses[ $item->central_status ] )
		);
	}

	/**
	 * Render the acronym column with row actions.
	 *
	 * Local acronyms can be edited and deleted. Central acronyms can only be
	 * turned off or on for this site.
	 *
	 * @param object $item The current acronym row.
	 * @return string Column HTML.
	 */
	public function column_acronym( $item ) {
		if ( 'central' === $item->source ) {
			return sprintf(
				'<strong>%s</strong>%s',
				esc_html( $item->acronym ),
				$this->row_actions( $this->central_actions( $item ) )
			);
		}

		return $this->local_column_acronym( $item );
	}

	/**
	 * Row actions for a central acronym.
	 *
	 * @param object $item The current acronym row.
	 * @return array Action slug => link HTML.
	 */
	private function central_actions( $item ) {
		$disabled = Acronyms_Central::is_excluded( $item->acronym );

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'page'    => 'acronyms',
					'action'  => $disabled ? 'central_enable' : 'central_disable',
					'central' => rawurlencode( $item->acronym ),
				),
				admin_url( 'options-general.php' )
			),
			'acronyms_central_toggle_' . Acronyms_Central::key( $item->acronym )
		);

		return array(
			'toggle' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				$disabled ? esc_html__( 'Turn on', 'acronym-tooltips' ) : esc_html__( 'Turn off', 'acronym-tooltips' )
			),
		);
	}

	/**
	 * Render the acronym column for a local acronym, with edit and delete actions.
	 *
	 * @param object $item The current acronym row.
	 * @return string Column HTML.
	 */
	private function local_column_acronym( $item ) {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : 'acronyms'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$edit_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => $page,
					'action' => 'edit',
					'id'     => $item->id,
				),
				admin_url( 'options-general.php' )
			),
			'acronyms_edit_' . $item->id
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => $page,
					'action' => 'delete',
					'id'     => $item->id,
				),
				admin_url( 'options-general.php' )
			),
			'acronyms_delete_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'acronym-tooltips' )
			),
			'delete' => sprintf(
				'<a href="%s" class="acronyms-delete-link">%s</a>',
				esc_url( $delete_url ),
				esc_html__( 'Delete', 'acronym-tooltips' )
			),
		);

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $item->acronym ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render the case-sensitive column.
	 *
	 * @param object $item The current acronym row.
	 * @return string Column HTML.
	 */
	public function column_case_sensitive( $item ) {
		return $item->case_sensitive
			? esc_html__( 'Yes', 'acronym-tooltips' )
			: esc_html__( 'No', 'acronym-tooltips' );
	}

	/**
	 * Message displayed when no acronyms are found.
	 */
	public function no_items() {
		esc_html_e( 'No acronyms found.', 'acronym-tooltips' );
	}
}
