<?php
/*
 Plugin Name: Another Wordpress Classifieds Plugin (AWPCP)
 Plugin URI: http://www.awpcp.com
 Description: AWPCP - A plugin that provides the ability to run a free or paid classified ads service on your wordpress blog. <strong>!!!IMPORTANT!!!</strong> Whether updating a previous installation of Another Wordpress Classifieds Plugin or installing Another Wordpress Classifieds Plugin for the first time, please backup your wordpress database before you install/uninstall/activate/deactivate/upgrade Another Wordpress Classifieds Plugin.
 Version: 2.2.1
 Author: D. Rodenbaugh
 License: GPLv2 or any later version
 Author URI: http://www.skylineconsult.com
 */

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * dcfunctions.php and filop.class.php used with permission of Dan Caragea, http://datemill.com
 * AWPCP Classifieds icon set courtesy of http://www.famfamfam.com/lab/icons/silk/
 */

if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) {
	die('You are not allowed to call this page directly.');
}


define('AWPCP_BASENAME', trailingslashit(basename(dirname(__FILE__))));
define('AWPCP_DIR', WP_CONTENT_DIR . '/plugins/' . AWPCP_BASENAME);
define('AWPCP_URL', WP_CONTENT_URL . '/plugins/' . AWPCP_BASENAME);

// TODO: Why do we need a custom error handler?
// Set custom error handler functions
function AWPCPErrorHandler($errno, $errstr, $errfile, $errline){
	$output = '';
	switch ($errno) {
		case E_USER_ERROR:
			if ($errstr == "(SQL)"){
				// handling an sql error
				$output .= "<b>AWPCP SQL Error</b> Errno: [$errno] SQLError:" . SQLMESSAGE . "<br />\n";
				$output .= "Query : " . SQLQUERY . "<br />\n";
				$output .= "Called by line " . SQLERRORLINE . " in file " . SQLERRORFILE . ", error in ".$errfile." at line ".$errline;
				$output .= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
				$output .= "Aborting...<br />\n";
			} else {
				$output .= "<b>AWPCP PHP Error</b> [$errno] $errstr<br />\n";
				$output .= "  Fatal error called by line $errline in file $errfile, error in ".$errfile." at line ".$errline;
				$output .= ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
				$output .= "Aborting...<br />\n";
			}
			//Echo OK here:
			echo $output;
			exit(1);
			break;

		case E_USER_WARNING:
		case E_USER_NOTICE:
	}
	/* true=Don't execute PHP internal error handler */
	return true;
}

if (file_exists(AWPCP_DIR . 'DEBUG')) {
	// let's see some errors
} else {
	set_error_handler("AWPCPErrorHandler");
}


global $wpdb; // XXX: do we need $wpdb this here? --@wvega
global $awpcp_plugin_data;
global $awpcp_db_version;

global $wpcontenturl;
global $wpcontentdir;
global $awpcp_plugin_path;
global $awpcp_plugin_url;
global $imagespath;
global $awpcp_imagesurl;

global $nameofsite;
global $thisadminemail;


// get_plugin_data accounts for about 2% of the cost of
// each request, defining the version manually is a less
// expensive way.
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
$awpcp_plugin_data = get_plugin_data(__FILE__);
$awpcp_db_version = $awpcp_plugin_data['Version'];

$wpcontenturl = WP_CONTENT_URL;
$wpcontentdir = WP_CONTENT_DIR;
$awpcp_plugin_path = AWPCP_DIR;
$awpcp_plugin_url = AWPCP_URL;
$imagespath = $awpcp_plugin_path . 'images';
$awpcp_imagesurl = $awpcp_plugin_url .'images';

$nameofsite = get_option('blogname');
$thisadminemail = get_option('admin_email');



// common
require_once(AWPCP_DIR . "debug.php");
require_once(AWPCP_DIR . "functions.php");
require_once(AWPCP_DIR . "cron.php");

// other resources
require_once(AWPCP_DIR . "dcfunctions.php");
require_once(AWPCP_DIR . "awpcp_search_widget.php");
require_once(AWPCP_DIR . "functions_awpcp.php");
require_once(AWPCP_DIR . "upload_awpcp.php");

// API & Classes
require_once(AWPCP_DIR . "classes/models/ad.php");
require_once(AWPCP_DIR . "classes/models/category.php");
require_once(AWPCP_DIR . "classes/models/image.php");
require_once(AWPCP_DIR . "classes/models/payment-transaction.php");

require_once(AWPCP_DIR . "classes/helpers/list-table.php");

require_once(AWPCP_DIR . "classes/settings-api.php");

require_once(AWPCP_DIR . "widget-latest-ads.php");

// installation functions
require_once(AWPCP_DIR . "install.php");

// admin functions
require_once(AWPCP_DIR . "admin/admin-panel.php");
require_once(AWPCP_DIR . "admin/user-panel.php");

// frontend functions
require_once(AWPCP_DIR . "frontend/payment-functions.php");
require_once(AWPCP_DIR . "frontend/ad-functions.php");
require_once(AWPCP_DIR . "frontend/shortcode.php");

// modules (in development)



class AWPCP {

	// Admin section
	public $admin = null;
	// User Ad Management panel
	public $panel = null;
	// Frontend pages
	public $pages = null;
	// Settings API -- not the one from WP
	public $settings = null;

	public $flush_rewrite_rules = false;

	private $js_data = array();

	// TODO: I want to register all plugin scripts here, enqueue on demand in each page.
	// is that a good idea? -@wvega

	public function __construct() {
		// we need to instatiate this here, because some options are
		// consulted before plugins_loaded...
		$this->settings = new AWPCP_Settings_API();
		$this->installer = AWPCP_Installer::instance();

		$file = WP_CONTENT_DIR . '/plugins/' . basename(dirname(__FILE__)) . '/' . basename(__FILE__);
        register_activation_hook($file, array($this->installer, 'activate'));

        add_action('plugins_loaded', array($this, 'setup'), 10);

        // register rewrite rules when the plugin file is loaded.
		// generate_rewrite_rules or rewrite_rules_array hooks are
		// too late to add rules using add_rewrite_rule function
		add_action('page_rewrite_rules', 'awpcp_add_rewrite_rules');
		add_filter('query_vars', 'awpcp_query_vars');
	}

	/**
	 * Check if AWPCP DB version corresponds to current AWPCP plugin version
	 */
	public function updated() {
		global $awpcp_db_version;
		$installed = get_option('awpcp_db_version', '');
		// if installed version is greater than plugin version
		// not sure what to do. Downgrade is not currently supported.
		return version_compare($installed, $awpcp_db_version) === 0;
	}

	/**
	 * Single entry point for AWPCP plugin.
	 *
	 * This is functional but still a work in progress...
	 */
	public function setup() {
		if (!$this->updated()) {
			$this->installer->install();
			// we can't call flush_rewrite_rules() because
			// $wp_rewrite is not available yet. It is initialized
			// after plugins_load hook is executed.
			$this->flush_rewrite_rules = true;
		}

		if ($this->updated()) {
			$this->settings->setup();
			$this->admin = new AWPCP_Admin();
			$this->panel = new AWPCP_User_Panel();
			$this->pages = new AWPCP_Pages();

			// $this->check_for_premium_modules();

			add_action('init', array($this, 'init'));

			add_action('awpcp_register_settings', array($this, 'register_settings'));

			add_action('wp_print_scripts', array($this, 'print_scripts'));

			// actions and filters from functions_awpcp.php
			add_action('widgets_init', 'widget_awpcp_search_init');
			add_action('phpmailer_init','awpcp_phpmailer_init_smtp');
			add_filter('awpcp-single-ad-layout', 'awpcp_insert_tweet_button', 1, 3);
			add_filter('awpcp-single-ad-layout', 'awpcp_insert_share_button', 2, 3);

			// actions and filters from awpcp.php
			add_action('wp_print_scripts', 'awpcpjs',1);
			add_action('wp_head', 'awpcp_addcss');

			if (!get_awpcp_option('awpcp_thickbox_disabled')) {
				add_action('wp_head', 'awpcp_insert_thickbox', 10);
			}
			if (is_admin() && function_exists('add_thickbox')) {
				add_action('admin_head', 'awpcp_insert_thickbox', 10);
			}

			add_filter('cron_schedules', 'awpcp_cron_schedules');

			add_action("init", "init_awpcpsbarwidget");
			add_action('init', 'awpcp_schedule_activation');
			// add_action('init', 'maybe_redirect_new_ad', 1);

			if (get_awpcp_option('awpcppagefilterswitch') == 1) {
				add_filter('wp_list_pages_excludes', 'exclude_awpcp_child_pages');
			}

			remove_action('wp_head', 'rel_canonical');
			add_action('wp_head', 'awpcp_rel_canonical');

			// we need to dalay insertion of inline JavaScript to avoid problems
			// with wpauotp and wptexturize functions
			add_filter('the_content', 'awpcp_inline_javascript', 1000);
			add_filter('admin_footer', 'awpcp_print_inline_javascript', 1000);
		}
	}


	public function init() {
		$this->initialize_session();

		$installation_complete = get_option('awpcp_installationcomplete', 0);
		if (!$installation_complete) {
			update_option('awpcp_installationcomplete', 1);
			awpcp_create_pages(__('AWPCP', 'AWPCP'));
			$this->flush_rewrite_rules = true;
		}

		if ($this->flush_rewrite_rules) {
			flush_rewrite_rules();
		}

		$this->register_scripts();
	}

	/**
	 * Register other AWPCP settings, normally for private use.
	 */
	public function register_settings() {
		$this->settings->add_setting('private:notices', 'show-quick-start-guide-notice', '', 'checkbox', false, '');
	}

	/**
	 * Conditionally start session if not already active.
	 *
	 * @since  2.1.4
	 */
	public function initialize_session() {
		$session_id = session_id();
		if (empty($session_id)) {
			// if we are in a subdomain, let PHP choose the right domain
			if (strcmp(awpcp_get_current_domain(), awpcp_get_current_domain(false)) == 0) {
				$domain = '';
			// otherwise strip the www part
			} else {
				$domain = awpcp_get_current_domain(false, '.');
			}

			@session_set_cookie_params(0, '/', $domain, false, true);
			@session_start();
		}
	}

	/**
	 * A good place to register all AWPCP standard scripts that can be
	 * used form other sections.
	 */
	public function register_scripts() {
		// had to use false as the version number because otherwise the resulting URLs would
		// throw 404 errors. Not sure why :S -@wvega

		$js = AWPCP_URL . 'js';

		wp_register_script('awpcp', "{$js}/awpcp.js", array('jquery'), '1.0.0', true);

		wp_register_script('awpcp-admin-general', "{$js}/admin-general.js", array('jquery'), '1.0.0', true);
		wp_register_script('awpcp-page-place-ad', "{$js}/page-place-ad.js", array('jquery'), false, true);

		wp_register_style('awpcp-jquery-ui', "{$js}/ui/themes/smoothness/jquery-ui.css", array(), false);

		wp_register_script('awpcp-jquery-ui-core', "{$js}/ui/jquery.ui.core.min.js", array('jquery'), false, true);
		wp_register_script('awpcp-jquery-ui-widget', "{$js}/ui/jquery.ui.widget.min.js", array('jquery'), false, true);
		wp_register_script('awpcp-jquery-ui-position', "{$js}/ui/jquery.ui.position.min.js", array('jquery'), false, true);
		wp_register_script('awpcp-jquery-ui-datepicker', "{$js}/ui/jquery.ui.datepicker.min.js", array('awpcp-jquery-ui-core'), false, true);
		wp_register_script('awpcp-jquery-ui-autocomplete', "{$js}/ui/jquery.ui.autocomplete.min.js", array('awpcp-jquery-ui-core', 'awpcp-jquery-ui-widget', 'awpcp-jquery-ui-position'), false, true);

		if (is_admin()) {
			wp_enqueue_script('awpcp-admin-general');
		} else {

		}
	}


	public function set_js_data($key, $value) {
		$this->js_data[$key] = $value;
	}


	public function print_scripts() {
		$data = array_merge(array('ajaxurl' => admin_url('admin-ajax.php')), $this->js_data);
		wp_localize_script('awpcp', '__awpcp_js_data', $data);

		// TODO: migrate the code below to use set_js_data to pass information
		// to AWPCP scripts.
		$options = array(
			'ajaxurl' => awpcp_ajaxurl()
		);

		if (is_admin()) {
			wp_localize_script('awpcp-admin-general', 'AWPCPAjaxOptions', $options);
		} else {
			//
		}
	}


	public function get_missing_pages() {
		global $awpcp, $wpdb;

		// pages that are registered in the code but no referenced in the DB
		$shortcodes = awpcp_pages();
		$registered = array_keys($shortcodes);
		$referenced = $wpdb->get_col('SELECT page FROM ' . AWPCP_TABLE_PAGES);
		$missing = array_diff($registered, $referenced);

		// pages that are referenced but no longer registered in the code
		$leftovers = array_diff($referenced, $registered);

		$excluded = array_merge(array('view-categories-page-name'), $leftovers);

		$query = 'SELECT pages.page, pages.id, posts.ID post ';
		$query.= 'FROM ' . AWPCP_TABLE_PAGES . ' AS pages ';
		$query.= 'LEFT JOIN ' . $wpdb->posts . ' AS posts ON (posts.ID = pages.id) ';
		$query.= 'WHERE posts.ID IS NULL ';

		if (!empty($excluded)) {
			$query.= " AND pages.page NOT IN ('" . join("','", $excluded) . "')";
		}

		$orphan = $wpdb->get_results($query);

		// if a page is registered in the code but there is no reference
		// of it in the database, create it.
		foreach ($missing as $page) {
			$item = new stdClass();
			$item->page = $page;
			$item->id = -1;
			$item->post = null;
			array_push($orphan, $item);
		}

		return $orphan;
	}


	public function restore_pages() {
		global $wpdb;

		$shortcodes = awpcp_pages();
		$missing = $this->get_missing_pages();
		$pages = awpcp_get_properties($missing, 'page');

		// If we are restoring the main page, let's do it first!
		if (($p = array_search('main-page-name', $pages)) !== FALSE) {
			// put the main page as the first page to restore
			array_splice($missing, 0, 0, array($missing[$p]));
			array_splice($missing, $p + 1, 1);
		}

		foreach($missing as $page) {
			$refname = $page->page;
			$name = get_awpcp_option($refname);
			if (strcmp($refname, 'main-page-name') == 0) {
				awpcp_create_pages($name, $subpages=false);
			} else {
				awpcp_create_subpage($refname, $name, $shortcodes[$refname][1]);
			}
		}

		flush_rewrite_rules();
	}


	/**
	 * As of version 2.0.6 premium module are all separated plugins.
	 * This function check if those new plugins are present and update the
	 * relevant global variables.
	 */
	// public function check_for_premium_modules() {
	// 	global $hasregionsmodule;

	// 	$hasregionsmodule = $hasregionsmodule || defined('AWPCP_REGION_CONTROL_MODULE');
	// }
}

global $awpcp;
$awpcp = new AWPCP();



// l10n MO file can be in the top level directory or inside the languages
// directory. A file inside the languages directory is prefered.
if (get_awpcp_option('activatelanguages')) {
	$basename = dirname(plugin_basename(__FILE__));
	if (!load_plugin_textdomain('AWPCP', false, $basename . '/languages/')) {
		load_plugin_textdomain('AWPCP', false, $basename);
	}
}


$uploadfoldername = get_awpcp_option('uploadfoldername', "uploads");

define('MAINUPLOADURL', $wpcontenturl .'/' .$uploadfoldername);
define('MAINUPLOADDIR', $wpcontentdir .'/' .$uploadfoldername);
define('AWPCPUPLOADURL', $wpcontenturl .'/' .$uploadfoldername .'/awpcp');
define('AWPCPUPLOADDIR', $wpcontentdir .'/' .$uploadfoldername .'/awpcp/');
define('AWPCPTHUMBSUPLOADURL', $wpcontenturl .'/' .$uploadfoldername .'/awpcp/thumbs');
define('AWPCPTHUMBSUPLOADDIR', $wpcontentdir .'/' .$uploadfoldername .'/awpcp/thumbs/');
define('MENUICO', $awpcp_imagesurl .'/menuico.png');

global $awpcpthumbsurl;
global $hascaticonsmodule;
global $hasregionsmodule;
global $haspoweredbyremovalmodule;
global $hasgooglecheckoutmodule;
global $hasextrafieldsmodule;
global $hasrssmodule;
global $hasfeaturedadsmodule;

$hasextrafieldsmodule = $hasextrafieldsmodule ? true : false;
$hasregionsmodule = $hasregionsmodule ? true : false;

$awpcpthumbsurl = AWPCPTHUMBSUPLOADURL;
$hascaticonsmodule = 0;
$haspoweredbyremovalmodule = 0;
$hasgooglecheckoutmodule = 0;
$hasrssmodule = 0;
$hasfeaturedadsmodule = 0;


if (!defined('AWPCP_REGION_CONTROL_MODULE') && file_exists("$awpcp_plugin_path/awpcp_region_control_module.php")) {
	require_once("$awpcp_plugin_path/awpcp_region_control_module.php");
	$hasregionsmodule = 1;
}

if (!defined('AWPCP_EXTRA_FIELDS_MODULE') && file_exists("$awpcp_plugin_path/awpcp_extra_fields_module.php")) {
	require("$awpcp_plugin_path/awpcp_extra_fields_module.php");
	$hasextrafieldsmodule = 1;
}

if ( file_exists("$awpcp_plugin_path/awpcp_category_icons_module.php") )
{
	require("$awpcp_plugin_path/awpcp_category_icons_module.php");
	$hascaticonsmodule=1;
}
if ( file_exists("$awpcp_plugin_path/awpcp_remove_powered_by_module.php") )
{
	require("$awpcp_plugin_path/awpcp_remove_powered_by_module.php");
	$haspoweredbyremovalmodule=1;
}
if ( file_exists("$awpcp_plugin_path/awpcp_google_checkout_module.php") )
{
	require("$awpcp_plugin_path/awpcp_google_checkout_module.php");
	$hasgooglecheckoutmodule=1;
}
if ( file_exists("$awpcp_plugin_path/awpcp_rss_module.php") )
{
	require("$awpcp_plugin_path/awpcp_rss_module.php");
	$hasrssmodule=1;
}
if (file_exists(WP_CONTENT_DIR . "/plugins/awpcp-featured-ads/awpcp_featured_ads.php"))
{
	$hasfeaturedadsmodule=1;
}

// Add css file and jquery codes to header
function awpcpjs() {
	global $awpcp_plugin_url;

	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-form');
	
	if (!get_awpcp_option('awpcp_thickbox_disabled')) {
		wp_enqueue_script('thickbox');
	}

	wp_enqueue_script('jquery-chuch', $awpcp_plugin_url.'js/checkuncheckboxes.js', array('jquery'));
}

function awpcp_insert_thickbox() {
	if (is_admin()) {
		add_thickbox();
	} else {
		$includes = includes_url();

		echo "\n" . '

    <link rel="stylesheet" href="' . $includes . '/js/thickbox/thickbox.css" type="text/css" media="screen" />

    <script type="text/javascript">
    var tb_pathToImage = "' . $includes . '/js/thickbox/loadingAnimation.gif";
    var tb_closeImage = "' . $includes . '/js/thickbox/tb-close.png";
    </script>
    ';
	}
}


/**
 * Redirect to the ad page when a new ad is posted. This prevents posting duplicates when someone clicks reload.
 * This also allows admins to post without going through the checkout process.
 *
 * This is no longer used after the Place Ad workflow changes.
 */
// function maybe_redirect_new_ad() {
// 	global $wp_query;

// 	$a = awpcp_post_param('a', '');
// 	$adid = awpcp_post_param('adid', '');

//     if (( isset($wp_query->query_vars) && 'adpostfinish' == get_query_var('a') && '' != get_query_var('adid') ) ||
// 	 	( 'adpostfinish' == $a && '' != $adid))
//     {
// 		// if ( get_awpcp_option('seofriendlyurls') ) {
// 		// 	wp_redirect( url_showad( intval( $_POST['adid'] ) ).'?adstatus=preview');
// 		// } else {
// 		// 	wp_redirect( url_showad( intval( $_POST['adid'] ) ).'&adstatus=preview');
// 		// }
//     	$url = add_query_arg(array('adstatus' => 'preview'), url_showad(intval($_POST['adid'])));
//     	wp_redirect($url);
// 		die;
// 	}
// }



// if (get_awpcp_option('awpcppagefilterswitch') == 1) {
// 	add_filter('wp_list_pages_excludes', 'exclude_awpcp_child_pages');
// }

/**
 * Returns the IDs of the pages used by the AWPCP plugin.
 */
function exclude_awpcp_child_pages($excluded=array()) {
	global $wpdb, $table_prefix;

	$awpcp_page_id = awpcp_get_page_id_by_ref('main-page-name');

	if (empty($awpcp_page_id)) {
		return array();
	}

	$query = "SELECT ID FROM {$table_prefix}posts ";
	$query.= "WHERE post_parent=$awpcp_page_id AND post_content LIKE '%AWPCP%'";
	$res = awpcp_query($query, __LINE__);

	$awpcpchildpages = array();
	while ($rsrow=mysql_fetch_row($res)) {
		$awpcpchildpages[] = $rsrow[0];
	}

	return array_merge($awpcpchildpages, $excluded);
}



// The function to add the reference to the plugin css style sheet to the header of the index page
function awpcp_addcss() {
	$awpcpstylesheet="awpcpstyle.css";
	$awpcpstylesheetie6="awpcpstyle-ie-6.css";
	echo "\n".'<link rel="stylesheet" type="text/css" media="screen" href="'.AWPCP_URL.'css/'.$awpcpstylesheet.'" />
			 <!--[if lte IE 6]><style type="text/css" media="screen">@import "'.AWPCP_URL.'css/'.$awpcpstylesheetie6.'";</style><![endif]-->
			 ';
	// load custom stylesheet if one exists in the wp-content/plugins directory:
	if (file_exists(WP_PLUGIN_DIR.'/awpcp_custom_stylesheet.css')) {
	    echo "\n".'<link rel="stylesheet" type="text/css" media="screen" href="'.WP_PLUGIN_URL.'/awpcp_custom_stylesheet.css" />';
	}

}
// PROGRAM FUNCTIONS

/**
 * Return an array of refnames for pages associated with one or more
 * rewrite rules.
 *
 * @since 2.1.3
 * @return array Array of page refnames.
 */
function awpcp_pages_with_rewrite_rules() {
	return array(
		'main-page-name',
		'show-ads-page-name',
		'reply-to-ad-page-name',
		'browse-categories-page-name',
		'payment-thankyou-page-name',
		'payment-cancel-page-name'
	);
}

function awpcp_add_rewrite_rules($rules) {
	$pages = awpcp_pages_with_rewrite_rules();
	$patterns = array();

	foreach ($pages as $refname) {
		if ($id = awpcp_get_page_id_by_ref($refname)) {
			if ($page = get_page($id)) {
				$patterns[$refname] = get_page_uri($page->ID);
			}
		}
	}

	$categories_view = sanitize_title(get_awpcp_option('view-categories-page-name'));

	if (isset($patterns['show-ads-page-name'])) {
		add_rewrite_rule('('.$patterns['show-ads-page-name'].')/(.+?)/(.+?)',
						 'index.php?pagename=$matches[1]&id=$matches[2]', 'top');
	}

	if (isset($patterns['reply-to-ad-page-name'])) {
		add_rewrite_rule('('.$patterns['reply-to-ad-page-name'].')/(.+?)/(.+?)',
						 'index.php?pagename=$matches[1]&id=$matches[2]', 'top');
	}

	if (isset($patterns['browse-categories-page-name'])) {
		add_rewrite_rule('('.$patterns['browse-categories-page-name'].')/(.+?)/(.+?)',
						 'index.php?pagename=$matches[1]&cid=$matches[2]&a=browsecat',
						 'top');
	}

	if (isset($patterns['payment-thankyou-page-name'])) {
		add_rewrite_rule('('.$patterns['payment-thankyou-page-name'].')/([a-zA-Z0-9]+)',
						 'index.php?pagename=$matches[1]&awpcp-txn=$matches[2]', 'top');
	}

	if (isset($patterns['payment-cancel-page-name'])) {
		add_rewrite_rule('('.$patterns['payment-cancel-page-name'].')/([a-zA-Z0-9]+)',
						 'index.php?pagename=$matches[1]&awpcp-txn=$matches[2]', 'top');
	}

	if (isset($patterns['main-page-name'])) {
		add_rewrite_rule('('.$patterns['main-page-name'].')/('.$categories_view.')',
						 'index.php?pagename=$matches[1]&layout=2&cid='.$categories_view,
						 'top');
		add_rewrite_rule('('.$patterns['main-page-name'].')/(setregion)/(.+?)/(.+?)',
						 'index.php?pagename=$matches[1]&regionid=$matches[3]&a=setregion',
						 'top');
		add_rewrite_rule('('.$patterns['main-page-name'].')/(classifiedsrss)',
						 'index.php?pagename=$matches[1]&awpcp-action=rss',
						 'top');
	}

	return $rules;
}


/**
 * Register AWPCP query vars
 */
function awpcp_query_vars($query_vars) {
	$query_vars[] = "cid";
	$query_vars[] = "i";
	$query_vars[] = "id";
	$query_vars[] = "layout";
	$query_vars[] = "regionid";
	$query_vars[] = 'awpcp-action';
	return $query_vars;
}


/**
 * Set canonical URL to the Ad URL when in viewing on of AWPCP Ads
 */
function awpcp_rel_canonical() {
	if (!is_singular())
		return;

	global $wp_the_query;
	if (!$page = $wp_the_query->get_queried_object_id()) {
		return;
	}

	if ($page != awpcp_get_page_id_by_ref('show-ads-page-name')) {
		return rel_canonical();
	}

	$ad = intval(awpcp_request_param('id', ''));
	$ad = empty($ad) ? intval(get_query_var('id')) : $ad;

	if (empty($ad)) {
		$link = get_permalink($page);
	} else {
		$link = url_showad($ad);
	}

	echo "<link rel='canonical' href='$link' />\n";
}


/**
 * Overwrittes WP canonicalisation to ensure our rewrite rules
 * work, even when the main AWPCP page is also the front page or
 * when the requested page slug is 'awpcp'.
 *
 * Required for the View Categories and Classifieds RSS rules to work
 * when AWPCP main page is also the front page.
 *
 * http://wordpress.stackexchange.com/questions/51530/rewrite-rules-problem-when-rule-includes-homepage-slug
 */
function awpcp_redirect_canonical($redirect_url, $requested_url) {
	global $wp_query;

	$ids = awpcp_get_page_ids_by_ref(awpcp_pages_with_rewrite_rules());

	// do not redirect requests to AWPCP pages with rewrite rules
	if (is_page() && in_array(awpcp_request_param('page_id', 0), $ids)) {
		$redirect_url = $requested_url;

	// do not redirect requests to the front page, if any of the AWPCP pages
	// with rewrite rules is the front page
	} else if (is_page() && !is_feed() && isset($wp_query->queried_object) &&
			  'page' == get_option('show_on_front') && in_array($wp_query->queried_object->ID, $ids) &&
			   $wp_query->queried_object->ID == get_option('page_on_front'))
	{
		$redirect_url = $requested_url;
	}

	// $id = awpcp_get_page_id_by_ref('main-page-name');

	// // do not redirect direct requests to AWPCP main page
	// if (is_page() && !empty($_GET['page_id']) && $id == $_GET['page_id']) {
	// 	$redirect_url = $requested_url;

	// // do not redirect request to the front page, if AWPCP main page is
	// // the front page
	// } else if (is_page() && !is_feed() && isset($wp_query->queried_object) &&
	// 		  'page' == get_option('show_on_front') && $id == $wp_query->queried_object->ID &&
	// 		   $wp_query->queried_object->ID == get_option('page_on_front'))
	// {
	// 	$redirect_url = $requested_url;
	// }

	return $redirect_url;
}
add_filter('redirect_canonical', 'awpcp_redirect_canonical', 10, 2);
