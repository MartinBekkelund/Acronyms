<?php
/**
 * Admin interface for the Acronyms plugin.
 *
 * @package Acronyms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the admin pages, settings, and form submissions.
 */
class Acronyms_Admin {

	/**
	 * Hook suffix for the main admin page.
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Constructor: register admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the Acronyms page under Settings.
	 */
	public function add_menu_page() {
		$this->page_hook = add_options_page(
			__( 'Acronyms', 'acronym-tooltips' ),
			__( 'Acronyms', 'acronym-tooltips' ),
			'manage_options',
			'acronyms',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the post types setting.
	 */
	public function register_settings() {
		register_setting(
			'acronyms_settings',
			'acronyms_post_types',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array( 'post', 'page' ),
			)
		);

		add_settings_section(
			'acronyms_content_filtering',
			__( 'Content Filtering', 'acronym-tooltips' ),
			array( $this, 'render_settings_section' ),
			'acronyms_settings'
		);

		add_settings_field(
			'acronyms_post_types',
			__( 'Post Types', 'acronym-tooltips' ),
			array( $this, 'render_post_types_field' ),
			'acronyms_settings',
			'acronyms_content_filtering'
		);
	}

	/**
	 * Sanitize the post types setting.
	 *
	 * @param mixed $value Raw input value.
	 * @return array Sanitized array of post type slugs.
	 */
	public function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_key', $value );
	}

	/**
	 * Render the settings section description.
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Choose which post types the acronym replacement should apply to.', 'acronym-tooltips' ) . '</p>';
	}

	/**
	 * Render the post types checkboxes field.
	 */
	public function render_post_types_field() {
		$selected   = get_option( 'acronyms_post_types', array( 'post', 'page' ) );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			printf(
				'<label style="display: block; margin-bottom: 6px;"><input type="checkbox" name="acronyms_post_types[]" value="%s" %s /> %s</label>',
				esc_attr( $post_type->name ),
				checked( in_array( $post_type->name, $selected, true ), true, false ),
				esc_html( $post_type->labels->name )
			);
		}
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_script(
			'acronyms-admin',
			ACRONYMS_PLUGIN_URL . 'js/admin.js',
			array(),
			ACRONYMS_VERSION,
			true
		);

		wp_localize_script(
			'acronyms-admin',
			'acronymsAdmin',
			array(
				'confirmDelete' => __( 'Are you sure you want to delete this acronym?', 'acronym-tooltips' ),
			)
		);
	}

	/**
	 * Handle add, edit, and delete actions.
	 */
	public function handle_actions() {
		if ( ! isset( $_REQUEST['page'] ) || 'acronyms' !== $_REQUEST['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only routes to handlers, which verify their own nonces.
			return;
		}

		$this->handle_add();
		$this->handle_edit();
		$this->handle_delete();
	}

	/**
	 * Handle adding a new acronym.
	 */
	private function handle_add() {
		if ( ! isset( $_POST['acronyms_action'] ) || 'add' !== $_POST['acronyms_action'] ) {
			return;
		}

		check_admin_referer( 'acronyms_add', 'acronyms_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'acronym-tooltips' ) );
		}

		$acronym        = sanitize_text_field( wp_unslash( $_POST['acronym'] ?? '' ) );
		$title          = sanitize_text_field( wp_unslash( $_POST['acronym_title'] ?? '' ) );
		$case_sensitive = isset( $_POST['case_sensitive'] );

		$error = $this->validate_acronym( $acronym, $title );
		if ( $error ) {
			add_settings_error( 'acronyms', 'acronyms_error', $error, 'error' );
			return;
		}

		if ( Acronyms_DB::acronym_exists( $acronym ) ) {
			add_settings_error(
				'acronyms',
				'acronyms_duplicate',
				__( 'An acronym with this text already exists.', 'acronym-tooltips' ),
				'error'
			);
			return;
		}

		$result = Acronyms_DB::add_acronym( $acronym, $title, $case_sensitive );

		if ( false !== $result ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'acronyms',
						'message' => 'added',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		add_settings_error(
			'acronyms',
			'acronyms_error',
			__( 'Failed to add the acronym. Please try again.', 'acronym-tooltips' ),
			'error'
		);
	}

	/**
	 * Handle editing an existing acronym.
	 */
	private function handle_edit() {
		if ( ! isset( $_POST['acronyms_action'] ) || 'edit' !== $_POST['acronyms_action'] ) {
			return;
		}

		check_admin_referer( 'acronyms_edit', 'acronyms_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'acronym-tooltips' ) );
		}

		$id             = absint( $_POST['acronym_id'] ?? 0 );
		$acronym        = sanitize_text_field( wp_unslash( $_POST['acronym'] ?? '' ) );
		$title          = sanitize_text_field( wp_unslash( $_POST['acronym_title'] ?? '' ) );
		$case_sensitive = isset( $_POST['case_sensitive'] );

		if ( 0 === $id ) {
			add_settings_error( 'acronyms', 'acronyms_error', __( 'Invalid acronym ID.', 'acronym-tooltips' ), 'error' );
			return;
		}

		$existing = Acronyms_DB::get_acronym( $id );
		if ( ! $existing ) {
			add_settings_error( 'acronyms', 'acronyms_error', __( 'Acronym not found.', 'acronym-tooltips' ), 'error' );
			return;
		}

		$error = $this->validate_acronym( $acronym, $title );
		if ( $error ) {
			add_settings_error( 'acronyms', 'acronyms_error', $error, 'error' );
			return;
		}

		if ( Acronyms_DB::acronym_exists( $acronym, $id ) ) {
			add_settings_error(
				'acronyms',
				'acronyms_duplicate',
				__( 'An acronym with this text already exists.', 'acronym-tooltips' ),
				'error'
			);
			return;
		}

		$result = Acronyms_DB::update_acronym( $id, $acronym, $title, $case_sensitive );

		if ( $result ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'acronyms',
						'message' => 'updated',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		add_settings_error(
			'acronyms',
			'acronyms_error',
			__( 'Failed to update the acronym. Please try again.', 'acronym-tooltips' ),
			'error'
		);
	}

	/**
	 * Handle deleting an acronym.
	 */
	private function handle_delete() {
		if ( ! isset( $_GET['action'] ) || 'delete' !== $_GET['action'] ) {
			return;
		}

		$id = absint( $_GET['id'] ?? 0 );
		if ( 0 === $id ) {
			return;
		}

		check_admin_referer( 'acronyms_delete_' . $id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'acronym-tooltips' ) );
		}

		Acronyms_DB::delete_acronym( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'acronyms',
					'message' => 'deleted',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Validate acronym and title input.
	 *
	 * @param string $acronym Acronym text.
	 * @param string $title   Full meaning.
	 * @return string|null Error message or null if valid.
	 */
	private function validate_acronym( $acronym, $title ) {
		if ( empty( $acronym ) ) {
			return __( 'Acronym text is required.', 'acronym-tooltips' );
		}

		if ( empty( $title ) ) {
			return __( 'Full meaning is required.', 'acronym-tooltips' );
		}

		if ( mb_strlen( $acronym ) > 100 ) {
			return __( 'Acronym text must be 100 characters or fewer.', 'acronym-tooltips' );
		}

		if ( mb_strlen( $title ) > 500 ) {
			return __( 'Full meaning must be 500 characters or fewer.', 'acronym-tooltips' );
		}

		return null;
	}

	/**
	 * Render the main admin page with tabs.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'manage'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->display_admin_notices();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Acronyms', 'acronym-tooltips' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=acronyms&tab=manage' ) ); ?>"
					class="nav-tab <?php echo 'manage' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Manage Acronyms', 'acronym-tooltips' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=acronyms&tab=settings' ) ); ?>"
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'acronym-tooltips' ); ?>
				</a>
			</h2>

			<?php
			if ( 'settings' === $active_tab ) {
				$this->render_settings_tab();
			} else {
				$this->render_manage_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Display admin notices for completed actions.
	 */
	private function display_admin_notices() {
		if ( ! isset( $_GET['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET['message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'added'   => __( 'Acronym added successfully.', 'acronym-tooltips' ),
			'updated' => __( 'Acronym updated successfully.', 'acronym-tooltips' ),
			'deleted' => __( 'Acronym deleted successfully.', 'acronym-tooltips' ),
		);

		if ( isset( $messages[ $message ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $message ] )
			);
		}

		settings_errors( 'acronyms' );
	}

	/**
	 * Render the Manage Acronyms tab.
	 */
	private function render_manage_tab() {
		$editing   = false;
		$edit_item = null;

		if ( isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$edit_id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $edit_id > 0 ) {
				$edit_item = Acronyms_DB::get_acronym( $edit_id );
				if ( $edit_item ) {
					$editing = true;
				}
			}
		}

		?>
		<div style="margin-top: 20px;">
			<h3><?php echo $editing ? esc_html__( 'Edit Acronym', 'acronym-tooltips' ) : esc_html__( 'Add New Acronym', 'acronym-tooltips' ); ?></h3>

			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=acronyms' ) ); ?>">
				<?php
				if ( $editing ) {
					wp_nonce_field( 'acronyms_edit', 'acronyms_nonce' );
				} else {
					wp_nonce_field( 'acronyms_add', 'acronyms_nonce' );
				}
				?>
				<input type="hidden" name="acronyms_action" value="<?php echo $editing ? 'edit' : 'add'; ?>" />
				<?php if ( $editing ) : ?>
					<input type="hidden" name="acronym_id" value="<?php echo esc_attr( $edit_item->id ); ?>" />
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="acronym"><?php esc_html_e( 'Acronym', 'acronym-tooltips' ); ?></label>
						</th>
						<td>
							<input type="text" id="acronym" name="acronym" class="regular-text"
								value="<?php echo $editing ? esc_attr( $edit_item->acronym ) : ''; ?>"
								maxlength="100" required autofocus />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="acronym_title"><?php esc_html_e( 'Full Meaning', 'acronym-tooltips' ); ?></label>
						</th>
						<td>
							<input type="text" id="acronym_title" name="acronym_title" class="regular-text"
								value="<?php echo $editing ? esc_attr( $edit_item->title ) : ''; ?>"
								maxlength="500" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Case Sensitive', 'acronym-tooltips' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="case_sensitive" value="1"
									<?php echo ( ! $editing || $edit_item->case_sensitive ) ? 'checked' : ''; ?> />
								<?php esc_html_e( 'Match exact case only', 'acronym-tooltips' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php
				submit_button(
					$editing ? __( 'Update Acronym', 'acronym-tooltips' ) : __( 'Add Acronym', 'acronym-tooltips' )
				);
				?>

				<?php if ( $editing ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=acronyms' ) ); ?>">
						<?php esc_html_e( 'Cancel', 'acronym-tooltips' ); ?>
					</a>
				<?php endif; ?>
			</form>
		</div>

		<hr />

		<?php
		$list_table = new Acronyms_List_Table();
		$list_table->prepare_items();
		?>

		<form method="get">
			<input type="hidden" name="page" value="acronyms" />
			<?php
			$list_table->search_box( __( 'Search Acronyms', 'acronym-tooltips' ), 'acronyms-search' );
			$list_table->display();
			?>
		</form>
		<?php
	}

	/**
	 * Render the Settings tab.
	 */
	private function render_settings_tab() {
		?>
		<div style="margin-top: 20px;">
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acronyms_settings' );
				do_settings_sections( 'acronyms_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
