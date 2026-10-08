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
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = 20;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'per_page' => $per_page,
			'page'     => $this->get_pagenum(),
			'orderby'  => isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'acronym', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'    => isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'ASC', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'   => $search,
		);

		$this->items = Acronyms_DB::get_acronyms( $args );
		$total_items = Acronyms_DB::count_acronyms( $search );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
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
	 * Render the acronym column with row actions.
	 *
	 * @param object $item The current acronym row.
	 * @return string Column HTML.
	 */
	public function column_acronym( $item ) {
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
