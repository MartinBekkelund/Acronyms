<?php
/**
 * Plugin Name: Acronyms
 * Plugin URI:  https://github.com/MartinBekkelund/Acronyms
 * Description: Automatically wraps the first occurrence of defined acronyms in &lt;abbr&gt; tags with their full meaning.
 * Version:     1.1.0
 * Author:      Martin Koksrud Bekkelund
 * Author URI:  https://www.nivlheim.no/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acronym-tooltips
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 7.4
 *
 * @package Acronyms
 */

defined( 'ABSPATH' ) || exit;

define( 'ACRONYMS_VERSION', '1.1.0' );
define( 'ACRONYMS_DB_VERSION', '1.0.0' );
define( 'ACRONYMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACRONYMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACRONYMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once ACRONYMS_PLUGIN_DIR . 'includes/class-acronyms-db.php';
require_once ACRONYMS_PLUGIN_DIR . 'includes/class-acronyms-central.php';
require_once ACRONYMS_PLUGIN_DIR . 'includes/class-acronyms-filter.php';
require_once ACRONYMS_PLUGIN_DIR . 'includes/class-acronyms-admin.php';
require_once ACRONYMS_PLUGIN_DIR . 'includes/class-acronyms-list-table.php';

register_activation_hook( __FILE__, array( 'Acronyms_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Acronyms_DB', 'deactivate' ) );

Acronyms_Central::init();

add_action( 'admin_init', array( 'Acronyms_DB', 'maybe_upgrade' ) );

if ( is_admin() ) {
	new Acronyms_Admin();
} else {
	new Acronyms_Filter();
}
