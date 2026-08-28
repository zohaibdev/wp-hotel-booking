<?php
/**
 * Plugin Name: Hotel Booking
 * Plugin URI:  https://webnday.com/hotel-booking
 * Description: A scalable hotel room booking plugin with room management, availability checking, multi-step popup booking, Stripe payments, invoices, and email notifications.
 * Version:     1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      Webnday
 * Author URI:  https://webnday.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hotel-booking
 * Domain Path: /languages
 */

namespace HotelBooking;

defined('ABSPATH') || exit;

define('HB_VERSION', '1.0.0');
define('HB_DB_VERSION', '1.1.0');
define('HB_AUTHOR', 'Webnday');
define('HB_AUTHOR_URL', 'https://webnday.com');
define('HB_PLUGIN_FILE', __FILE__);
define('HB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HB_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once HB_PLUGIN_DIR . 'includes/class-autoloader.php';
require_once HB_PLUGIN_DIR . 'includes/functions.php';

Autoloader::register();

register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Plugin::get_instance()->init();
});
