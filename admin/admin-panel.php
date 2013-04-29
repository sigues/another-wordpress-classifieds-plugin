<?php
/**
 * AWPCP Classifieds Management Panel functions
 */

require_once(AWPCP_DIR . 'admin/admin-panel-csv-importer.php');
require_once(AWPCP_DIR . 'admin/admin-panel-debug.php');
require_once(AWPCP_DIR . 'admin/admin-panel-fee-terms.php');
require_once(AWPCP_DIR . 'admin/admin-panel-settings.php');
require_once(AWPCP_DIR . 'admin/admin-panel-fee-terms.php');
require_once(AWPCP_DIR . 'admin/admin-panel-uninstall.php');

class AWPCP_Admin {

	public $title = 'Classifieds';
	public $settings = null;

	public function AWPCP_Admin() {
		$this->title = __('Classifieds', 'AWPCP');

		$this->settings = new AWPCP_Admin_Settings();
		$this->importer = new AWPCP_Admin_CSV_Importer();
		$this->debug = new AWPCP_Admin_Debug();
		$this->uninstall = new AWPCP_Admin_Uninstall();

		add_action('wp_ajax_disable-quick-start-guide-notice',
                   array($this, 'disable_quick_start_guide_notice'));

		add_action('admin_init', array($this, 'init'));
		add_action('admin_enqueue_scripts', array($this, 'scripts'));
		add_action('admin_menu', array($this, 'menu'));

		add_action('admin_notices', array($this, 'notices'));

		// make sure AWPCP admins (WP Administrators and/or Editors) can edit settings
		add_filter('option_page_capability_awpcp-options', 'awpcp_admin_capability');

		// hook filter to output Admin panel sidebar. To remove the sidebar
		// just remove this action
		add_filter('awpcp-admin-sidebar', 'awpcp_admin_sidebar_output', 10, 2);

		// This functions were executed on plugins_loaded. However,
		// to avoid execution of AWPCP functions without propperly 
		// upgrading the plugin database, we execute them here, only
		// after AWPCP_Admin has been instatiated by AWPCP.
		awpcp_handle_admin_requests();
		awpcp_savefees();
		awpcp_addfees();
	}


	public function notices() {
		if (get_awpcp_option('show-quick-start-guide-notice')) {
			ob_start();
				include(AWPCP_DIR . '/admin/templates/admin-quick-start-guide-notice.tpl.php');
				$html = ob_get_contents();
			ob_end_clean();

			echo $html;
		}
	}


	public function init() {
		// remove_filter('awpcp-admin-sidebar', 'awpcp_admin_sidebar_output');

		wp_register_style('awpcp-admin-style', AWPCP_URL . 'css/awpcp-admin.css', array(), '0.1.0');
		wp_register_script('awpcp-table-ajax-admin', AWPCP_URL . 'js/jquery.table-ajax-admin.js', 
						   array('jquery-form'), '1.0', true);
		wp_register_script('awpcp-admin-script', AWPCP_URL . 'js/admin-panel-listings.js', 
						   array('jquery-form'), '1.0', true);
	}

	public function scripts() {
		wp_enqueue_style('awpcp-admin-style');
		wp_enqueue_script('awpcp-admin-script');
	}

	public function menu() {
		global $hasregionsmodule;
		global $hasextrafieldsmodule;

		$capability = awpcp_admin_capability();

		$slug = 'awpcp.php';
		add_menu_page('AWPCP Classifieds Management System', $this->title, $capability,
					  $slug, 'awpcp_home_screen', MENUICO);

		add_submenu_page($slug, 'Configure General Options ', 'Settings', $capability,
						 'awpcp-admin-settings', array($this->settings, 'dispatch'));
		add_submenu_page($slug, 'Listing Fees Setup', 'Fees', $capability,
						 'Configure2', 'awpcp_opsconfig_fees');
		add_submenu_page($slug, 'Add/Edit Categories', 'Categories', $capability,
						 'Configure3', 'awpcp_opsconfig_categories');
		add_submenu_page($slug, 'View Ad Listings', 'Listings', $capability,
						 'Manage1', 'awpcp_manage_viewlistings');
		// disabled because doesn't seems to be usefull anymore
		// add_submenu_page($slug, 'View Ad Images', 'Images', '7', 'Manage2', 'awpcp_manage_viewimages');
		$hook = add_submenu_page($slug, 'Import Ad', 'Import', $capability, 'awpcp-import', array($this->importer, 'dispatch'));
		add_action("load-{$hook}", array($this->importer, 'scripts'));

		// allow plugins to define additional sub menu entries
		do_action('awpcp_admin_add_submenu_page', $slug, $capability);

		if ($hasextrafieldsmodule) {
			add_submenu_page($slug, 'Manage Extra Fields', 'Extra Fields', $capability,
						 'Configure5', 'awpcp_add_new_field');
		}

		add_submenu_page($slug, 'Debug', 'Debug', $capability, 
						 'awpcp-debug', array($this->debug, 'dispatch'));

		add_submenu_page($slug, 'Uninstall AWPCP', 'Uninstall', $capability, 
						 'awpcp-admin-uninstall', array($this->uninstall, 'dispatch'));

		// allow plugins to define additional menu entries
		do_action('awpcp_add_menu_page');
	}


    public function disable_quick_start_guide_notice() {
        global $awpcp;  
        $awpcp->settings->update_option('show-quick-start-guide-notice', false);
        die('Success!');
    }
}


// // if there's a page name collision remove AWPCP menus so that nothing can be accessed
// add_action('init', 'awpcp_pagename_warning_check', -1);
// function awpcp_pagename_warning_check() { 
// 	if (!get_option('awpcp_pagename_warning', false)) {
// 		return;
// 	}
//     remove_action('admin_menu', 'awpcp_launch');
// }


// // display a warning if necessary
// add_action('admin_notices', 'awpcp_pagename_warning', 10);
// function awpcp_pagename_warning() { 
// 	if (!get_option('awpcp_pagename_warning', false)) {
// 		return;
// 	}
// 	echo '<div id="message" class="error"><p><strong>';	
// 	echo 'WARNING: </strong>A page named AWPCP already exists. You must either delete that page and its subpages, or rename them before continuing with the plugin configuration.';
// 	echo '</p></div>';
// }




// START FUNCTION: Check if the user side classified page exists


function checkifclassifiedpage($pagename) {
	global $wpdb, $table_prefix;

	$id = awpcp_get_page_id_by_ref('main-page-name');
	$query = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE ID = %d';
	$page_id = $wpdb->get_var($wpdb->prepare($query, $id));

	return $page_id === $id;
}

// END FUNCTION


// START FUNCTION: Display the admin home screen

function awpcp_home_screen() {
	global $message, $user_identity, $wpdb, $awpcp_plugin_path, $awpcp_imagesurl, $awpcp_db_version;
	global $haspoweredbyremovalmodule,
		   $hasregionsmodule,
		   $hascaticonsmodule,
		   $hasgooglecheckoutmodule,
		   $hasextrafieldsmodule,
		   $hasrssmodule,
		   $hasfeaturedadsmodule,
		   $extrafieldsversioncompatibility;

	$tbl_ad_settings = $wpdb->prefix . "awpcp_adsettings";
	$output = '';

	// get Admin panel sidebar content
	$sidebar = awpcp_admin_sidebar('none; width: 100% !important');

	$output .= "<div class=\"wrap\"><h2>";
	$output .= __("AWPCP Classifieds Management System", "AWPCP");
	$output .= "</h2><p>";
	$output .= __("You are using version","AWPCP");
	$output .= " <b>$awpcp_db_version</b> </p>$message <div style=\"padding:20px;\">";
	$output .= __("Thank you for using Another Wordpress Classifieds Plugin, the #1 Wordpress Classifieds Plugin.  Please direct support requests, enhancement ideas and bug reports to the ","AWPCP");
	$output .= "<a href='http://forum.awpcp.com'>";
	$output .= __("AWPCP support website", "AWPCP");
	$output .= "</a>";
	$output .= "</div>";

	if ($hasextrafieldsmodule == 1)
	{
		if (!($extrafieldsversioncompatibility == 1))
		{
			$output .= "<div id=\"message\" class=\"updated fade\" style=\"padding:10px;width:92%;\">";
			$output .= __("The version of the extra fields module that you are using is not compatible with this version of Another Wordpress Classifieds Plugin. Please request the updated files for the extra fields module","AWPCP");
			$output .= "<p><a href=\"http://www.awpcp.com/contact\">";
			$output .= __("Request Updated Extra Fields Module files","AWPCP");
			$output .= "</a></p></div>";
		}
	}

	$cpagename_awpcp=get_awpcp_option('main-page-name');
	$awpcppagename = sanitize_title($cpagename_awpcp, $post_ID='');

		$awpcp_classifieds_page_conflict_check=checkforduplicate(add_slashes_recursive($cpagename_awpcp));
		if ( $awpcp_classifieds_page_conflict_check > 1)
		{
			$output .= "<div style=\"border-top:1px solid #dddddd;border-bottom:1px dotted #dddddd;padding:10px;background:#f5f5f5;\"><img src=\"$awpcp_imagesurl/Warning.png\" border=\"0\" alt=\"Alert\" style=\"float:left;margin-right:10px;\"/>";
			$output .= __("It appears you have a potential problem that could result in the malfunctioning of Another Wordpress Classifieds plugin. A check of your database was performed and duplicate entries were found that share the same post_name value as your classifieds page. If for some reason you uninstall and then reinstall this plugin and the duplicate pages remain in your database, it could break the plugin and prevent it from working. To fix this problem you can manually delete the duplicate pages and leave only the page with the ID of your real classifieds page, or you can use the link below to rebuild your classifieds page. The process will include first deleting all existing pages with a post name value identical to your classifieds page. Note that if you recreate the page, it will be assigned a new page ID so if you are referencing the classifieds page ID anywhere outside of the classifieds program you will need to adjust the old ID to the new ID.","AWPCP");
			$output .= "<br/>";
			$output .= __("Number of duplicate pages","AWPCP");
			$output .= ": <b>$awpcp_classifieds_page_conflict_check</b>";
			$output .= "<br/>";
			$output .= __("Duplicated post name","AWPCP");
			$output .= ": <b>$awpcppagename</b>";
			$output .= "<p><a href=\"?page=Configure1&action=recreatepage\">";
			$output .= __("Recreate the classifieds page to fix the conflict","AWPCP");
			$output .= "</a></p></div>";
		}

		// if sidebar content is empty, the left column should take the 
		// whole page width.
		if (empty($sidebar)) {
			$output .= "<div>";	
		} else {
			$output .= "<div style=\"float:left;width:70%;\">";	
		}
		$output .= "<div class=\"postbox\">";
		$output .= "<div style=\"background:#eeeeee; padding:10px;color:#444444;\"><strong>";
		$output .= __("Another Wordpress Classifieds Plugin Stats","AWPCP");
		$output .= "</strong></div>";

		$totallistings=countlistings(1);
		$output .= "<div style=\"padding:5px;\">";
		$output .= __("Number of active listings currently in the system","AWPCP");
		$output .= ": <b>$totallistings</b>";
		$output .= "</div>";
		$totallistings=countlistings(0);
		$output .= "<div style=\"padding:5px;\">";
		$output .= __("Number of inactive/expired/disabled listings currently in the system","AWPCP");
		$output .= ": <b>$totallistings</b>";
		$output .= "</div>";
		

		if (get_awpcp_option('freepay') == 1)
		{
			if (adtermsset())
			{
				$output .= "<div style=\"padding:10px;border-top:1px solid #dddddd;\">";
				$output .= __("You have setup your listing fees. To edit your fees use the 'Manage Listing Fees' option.","AWPCP");
				$output .= "</div>";
			}
			else
			{
				$output .= "<div style=\"padding:10px;border-top:1px solid #dddddd;\">";
				$output .= __("You have not configured your Listing fees. Use the 'Manage Listing Fees' option to set up your listing fees. Once that is completed, if you are running in pay mode, the options will automatically appear on the listing form for users to fill out.","AWPCP");
				$output .= "</div>";
			}
		}
		else
		{
			$output .= "<div style=\"padding:10px;\">";
			$output .= __("You currently have your system configured to run in free mode. To change to 'pay' mode go to 'Manage General Options' and Check the box labeled 'Charge listing fee? (Pay Mode)'","AWPCP");
			$output .= "</div>";
		}
		if (categoriesexist())
		{
			$totalcategories=countcategories();
			$totalparentcategories=countcategoriesparents();
			$totalchildrencategories=countcategorieschildren();

			$output .= "<div style=\"padding:10px;border-top:1px solid #dddddd;\"><ul>";
			$output .= "<li style=\"margin-bottom:6px;list-style:none;\">";
			$output .= __("Total number of categories in the system","AWPCP");
			$output .= ": <b>$totalcategories</b></li>";
			$output .= "<li style=\"margin-bottom:6px;list-style:none;\">";
			$output .= __("Number of Top Level parent categories","AWPCP");
			$output .= ": <b>$totalparentcategories</b></li>";
			$output .= "<li style=\"margin-bottom:6px;list-style:none;\">";
			$output .= __("Number of sub level children categories","AWPCP");
			$output .= ": <b>$totalchildrencategories</b></li>";
			$output .= "</ul><p>";
			$output .= __("Use the 'Manage Categories' option to edit/delete current categories or add new categories.","AWPCP");
			$output .= "</p></div>";
		}
		else
		{
			$output .= "<div style=\"padding:10px;border-top:1px solid #dddddd;\">";
			$output .= __("You have not setup any categories. Use the 'Manage Categories' option to set up your categories.","AWPCP");
			$output .= "</div>";
		}

		if (get_awpcp_option('freepay') == 1)
		{
			$output .= "<div style=\"padding:10px;border-top:1px solid #dddddd;\">";
			$output .= __("You currently have your system configured to run in pay mode. To change to 'free' mode go to 'Manage General Options' and check the box that accompanies the text 'Charge listing fee?'","AWPCP");
			$output .= "</div>";
		}

		$output .= "<div style=\"padding:10px;border-top:1px solid #dddddd;\">";
		$output .= __("Use the buttons on the right to configure your various options","AWPCP");
		$output .= "</div>";
		$output .= "</div>";

		// Configure AWPCP link
		$href = admin_url('admin.php?page=awpcp-admin-settings');
		$output.= '<div class="clearfix" style="padding:8px 0 25px">';
		$output.= 'AWPCP is highly customizable. Please go to the Settings section to fit AWPCP to your needs. ';
		$output.= '<a href="'. $href .'" style="padding: 7px 25px" class="button-primary">';
		$output.= 'Configure AWPCP</a></div>';

// $output .= "
// <ul style=\"margin-bottom: 80px\">
// <li style=\"float:left; background:url(".AWPCP_URL."/images/menulist.gif) 
// no-repeat;width:193px;height:40px;text-align:center;padding-top:10px;margin-right:10px\"><a 
// style=\"font-size:12px;text-decoration:none;\" href=\"?page=Configure1\">";$output .= __("Manage General Options","AWPCP"); $output .= "</a></li>
// <li style=\"float:left; background:url(".AWPCP_URL."/images/menulist.gif) 
// no-repeat;width:193px;height:40px;text-align:center;padding-top:10px;margin-right: 10px;\"><a 
// style=\"font-size:12px;text-decoration:none;\" href=\"?page=Configure2\">";$output .= __("Manage Listing Fees","AWPCP"); $output .= "</a></li>
// <li style=\"float:left; background:url(".AWPCP_URL."/images/menulist.gif) 
// no-repeat;width:193px;height:40px;text-align:center;padding-top:10px;margin-right: 10px;\"><a 
// style=\"font-size:12px;text-decoration:none;\" href=\"?page=Configure3\">";$output .= __("Manage Categories","AWPCP"); $output .= "</a></li>
// <li style=\"float:left; background:url(".AWPCP_URL."/images/menulist.gif) 
// no-repeat;width:193px;height:40px;text-align:center;padding-top:10px;margin-right: 10px;\"><a 
// style=\"font-size:12px;text-decoration:none;\" href=\"?page=Manage1\">";$output .= __("Manage Listings","AWPCP"); $output .= "</a></li>
// <li style=\"float:left; background:url(".AWPCP_URL."/images/menulist.gif) 
// no-repeat;width:193px;height:40px;text-align:center;padding-top:10px;margin-right: 10px;\"><a 
// style=\"font-size:12px;text-decoration:none;\" href=\"?page=Manage2\">";$output .= __("Manage Images","AWPCP"); $output .= "</a></li>
// </ul>";

		if (get_awpcp_option('showlatestawpcpnews'))
		{
			$output .= "<div class=\"postbox\">";
			$output .= "<div style=\"background:#eeeeee; padding:10px;color:#444444;\"><strong>";
			$output .= __("Latest News About Another Wordpress Classifieds Plugin","AWPCP");
			$output .= "</strong></div>";

			$awpcpwidgets = get_option( 'dashboard_widget_options' );
			@extract( @$awpcpwidgets['dashboard_secondary'], EXTR_SKIP );
			$awpcpfeedurl="http://feeds2.feedburner.com/Awpcp";
			$awpcpgetrss = @fetch_feed( $awpcpfeedurl );
			if ( is_wp_error($awpcpgetrss) ) {
				if ( is_admin() || current_user_can('manage_options') ) {
					$output .= '<div class="rss-widget"><p>';
					printf(__('<strong>RSS Error</strong>: %s', 'AWPCP'), $awpcpgetrss->get_error_message());
					$output .= '</p></div>';
				}
			} else {
			    // Figure out how many total items there are, but limit it to 5. 
			    $maxitems = $awpcpgetrss->get_item_quantity(5); 
			    // Build an array of all the items, starting with element 0 (first element).
			    $rss_items = $awpcpgetrss->get_items(0, $maxitems); 
				$output .= '<div style="padding:10px;"><ul>';
				if ($maxitems == 0) {
					$output .= '<li>No news right now.</li>';
				} else {
				    // Loop through each feed item and display each item as a hyperlink.
				    foreach ( $rss_items as $item ) {
				    	$title = 'AWPCP News '.$item->get_date('j F Y | g:i a').': '.$item->get_title();
				    	$excerpt = $item->get_description();
				    	$output .= '<li><a href='.$item->get_permalink().' title='.esc_attr($title).'>'.$title.'</a><br/>'.$excerpt.'<br/><br/></li>';
				    }
				}			    
				$output .= '</ul></div>';
			}
			$output .= "</div>";
		}
		$output .= "</div></div>";

		$output .= "<div style=\"float:left;width:25%;margin:0 0 0 20px;\">";
		$output .= $sidebar;
		$output .= "</div>";

		$output .= "</div>";

	echo $output;
}
// END FUNCTION


/**
 * Check if any of the page names is being changed.
 * 
 * If $send_changes is true, an array with info about the 
 * pages that are being changed will be returned. Otherwise just
 * true or false.
 */
 // XXX: I think this can be deleted
function awpcp_check_for_new_page_names($options, $send_changes = false) {
	global $wpdb;

	$pages = array('main-page-name' => array(get_awpcp_option('main-page-name'), '[AWPCP]'));
	$pages = $pages + awpcp_subpages();

	$changed = array();

	foreach($pages as $page => $data) {
		if (isset($options[$page]) && strcmp($options[$page], $data[0]) != 0) {
			$changed[] = array('key' => $page,
							   'oldname' => $data[0],
							   'newname' => $options[$page]);
		}
	}

	if (!empty($changed)) {
		return $send_changes ? $changed : true;
	}
	return false;
}


// START FUNCTION: Manage categories


function awpcp_opsconfig_categories()
{
	//debug();
	$output = '';
	$cpagename_awpcp=get_awpcp_option('main-page-name');
	$awpcppagename = sanitize_title($cpagename_awpcp, $post_ID='');
	$action='';

		global $wpdb, $message, $awpcp_imagesurl, $clearform,$hascaticonsmodule;

		$tbl_ad_categories = $wpdb->prefix . "awpcp_categories";
		$offset=(isset($_REQUEST['offset'])) ? (clean_field($_REQUEST['offset'])) : ($offset=0);
		$results=(isset($_REQUEST['results']) && !empty($_REQUEST['results'])) ? clean_field($_REQUEST['results']) : ($results=10);
		$cat_ID='';
		$category_name='';
		$aeaction='';
		$category_parent_id='';
		$promptmovetocat='';
		$aeaction='';

		///////////////////
		// Check for existence of a category ID and action

		if ( isset($_REQUEST['editcat']) && !empty($_REQUEST['editcat']) )
		{
			$cat_ID=$_REQUEST['editcat'];
			$action = "edit";
		}
		elseif ( isset($_REQUEST['delcat']) && !empty($_REQUEST['delcat']) )
		{
			$cat_ID=$_REQUEST['delcat'];
			$action = "delcat";
		}
		elseif ( isset($_REQUEST['managecaticon']) && !empty($_REQUEST['managecaticon']) )
		{
			$cat_ID=$_REQUEST['managecaticon'];
			$action = "managecaticon";
		}
		elseif (isset($_REQUEST['cat_ID']) && !empty($_REQUEST['cat_ID']))
		{
			$cat_ID=$_REQUEST['cat_ID'];
		}


		if ( !isset($action)  || empty($action) )
		{
			if ( isset($_REQUEST['action']) && !empty($_REQUEST['action']) )
			{
				$action=$_REQUEST['action'];
			}

		}
		if ( $action == 'edit' )
		{
			$aeaction='edit';
		}

		if ( $action == 'editcat' )
		{
			$aeaction='edit';
		}

		if ( $action == 'delcat' )
		{
			$aeaction='delete';
		}

		if ( $action == 'managecaticon' )
		{

			$output .= "<div class=\"wrap\"><h2>";
			$output .= __("AWPCP Classifieds Management System Categories Management","AWPCP");
			$output .= "</h2>
			";

			global $awpcp_plugin_path;

			if ($hascaticonsmodule == 1 )
			{
				if ( is_installed_category_icon_module() )
				{
					$output .= load_category_icon_management_page($defaultid=$cat_ID,$offset,$results);
				}
			}

			$output .= "</div>";
			return $output;
			//die;
		}

		if ( $action == 'setcategoryicon' )
		{

			global $awpcp_plugin_path;

			if ($hascaticonsmodule == 1 )
			{
				if ( is_installed_category_icon_module() )
				{
					if ( isset($_REQUEST['cat_ID']) && !empty($_REQUEST['cat_ID']) )
					{
						$thecategory_id=$_REQUEST['cat_ID'];
					}

					if ( isset($_REQUEST['category_icon']) && !empty($_REQUEST['category_icon']) )
					{
						$theiconfile=$_REQUEST['category_icon'];
					}

					if ( isset($_REQUEST['offset']) && !empty($_REQUEST['offset']) )
					{
						$offset=$_REQUEST['offset'];
					}

					if ( isset($_REQUEST['results']) && !empty($_REQUEST['results']) )
					{
						$results=$_REQUEST['results'];
					}

					$message=set_category_icon($thecategory_id,$theiconfile,$offset,$results);
					if ( isset($message) && !empty($message) )
					{
						$clearform=1;
					}
				}
			}
		}

		if ( isset($clearform) && ( $clearform == 1) )
		{
			unset($cat_ID,$action, $aeaction);
		}

		$category_name=get_adcatname($cat_ID);
		$category_order=get_adcatorder($cat_ID);
		$category_order = ($category_order != 0 ? $category_order : 0);
		$cat_parent_ID=get_cat_parent_ID($cat_ID);

		if ($aeaction == 'edit')
		{
			$aeword1=__("You are currently editing the category shown below","AWPCP");
			$aeword2=__("Save Category Changes","AWPCP");
			$aeword3=__("Parent Category","AWPCP");
			$aeword4=__("Category List Order","AWPCP");
			$addnewlink="<a href=\"?page=Configure3\">";
			$addnewlink.=__("Add A New Category","AWPCP");
			$addnewlink.="</a>";
		}
		elseif ($aeaction == 'delete')
		{
			if ( $cat_ID != 1)
			{
				$aeword1=__("If you're sure that you want to delete this category please press the delete button","AWPCP");
				$aeword2=__("Delete Category","AWPCP");
				$aeword3=__("Parent Category","AWPCP");
				$aeword4='';
				$addnewlink="<a href=\"?page=Configure3\">";
				$addnewlink.=__("Add A New Category","AWPCP");
				$addnewlink.="</a>";

				if (ads_exist_cat($cat_ID))
				{
					if ( category_is_child($cat_ID) ) {
						$movetocat=get_cat_parent_ID($cat_ID);
					}
					else
					{
						$movetocat=1;
					}

					$movetoname=get_adcatname($movetocat);
					if ( empty($movetoname) )
					{
						$movetoname=__("Untitled","AWPCP");
					}

					$promptmovetocat="<p>";
					$promptmovetocat.=__("The category contains ads. If you do not select a category to move them to the ads will be moved to:","AWPCP");
					$promptmovetocat.="<b>$movetoname</b></p>";

					$defaultcatname=get_adcatname($catid=1);

					if ( empty($defaultcatname) )
					{
						$defaultcatname=__("Untitled","AWPCP");
					}

					if (category_has_children($cat_ID))
					{
						$promptmovetocat.="<p>";
						$promptmovetocat.=__("The category also has children. If you do not specify a move-to category the children will be adopted by","AWPCP");
						$promptmovetocat.="<b>$defaultcatname</b><p><b>";
						$promptmovetocat.=__("Note","AWPCP");
						$promptmovetocat.=":</b>";
						$promptmovetocat.=__("The move-to category specified applies to both ads and categories","AWPCP");
						$promptmovetocat.="</p>";
					}
					$promptmovetocat.="<p align=\"center\"><select name=\"movetocat\"><option value=\"0\">";
					$promptmovetocat.=__("Please select a Move-To category","AWPCP");
					$promptmovetocat.="</option>";
					$categories=  get_categorynameid($cat_ID,$cat_parent_ID,$exclude=$cat_ID);
					$promptmovetocat.="$categories</select>";
				}

				$thecategoryparentname=get_adparentcatname($cat_parent_ID);
			}
			else
			{
				$aeword1=__("Sorry but you cannot delete ","AWPCP");
				$aeword1.="<b>$category_name</b>";
				$aeword1.=__(" It is the default category. The default category cannot be deleted","AWPCP");
				$aeword2='';
				$aeword3='';
				$aeword4='';
				$addnewlink="<a href=\"?page=Configure3\">";
				$addnewlink.=__("Add A New Category","AWPCP");
				$addnewlink.="</a>";
			}
		}
		else
		{
			if ( empty($aeaction) )
			{
				$aeaction="newcategory";
			}

			$aeword1=__("Enter the category name","AWPCP");
			$aeword2=__("Add New Category","AWPCP");
			$aeword3=__("List Category Under","AWPCP");
			$aeword4=__("Category List Order","AWPCP");
			$addnewlink='';
		}
		if ($aeaction == 'delete')
		{
			$orderinput='';
			if ($cat_ID == 1)
			{
				$categorynameinput='';
				$selectinput='';
			}
			else
			{
				$categorynameinput="<p style=\"background:transparent url($awpcp_imagesurl/delete_ico.png) left center no-repeat;padding-left:20px;\">";
				$categorynameinput.=__("Category to Delete","AWPCP");
				$categorynameinput.=": $category_name</p>";
				$selectinput="<p style=\"background:#D54E21;padding:3px;color:#ffffff;\">$thecategoryparentname</p>";
				$submitbuttoncode="<input type=\"submit\" class=\"button\" name=\"createeditadcategory\" value=\"$aeword2\" />";
			}
		}
		elseif ($aeaction == 'edit')
		{
			$categorynameinput="<p style=\"background:transparent url($awpcp_imagesurl/edit_ico.png) left center no-repeat;padding-left:20px;\">";
			$categorynameinput.=__("Category to Edit","AWPCP");
			$categorynameinput.=": $category_name ";
			$categorynamefield = "<input name=\"category_name\" id=\"cat_name\" type=\"text\" class=\"inputbox\" value=\"$category_name\" size=\"40\" style=\"width: 220px\"/>";
			$selectinput="<select name=\"category_parent_id\"><option value=\"0\">";
			$selectinput.=__("Make This a Top Level Category","AWPCP");
			$selectinput.="</option>";
			$orderinput="<input name=\"category_order\" id=\"category_order\" type=\"text\" class=\"inputbox\" value=\"$category_order\" size=\"3\"/>";
			$categories=  get_categorynameid($cat_ID,$cat_parent_ID,$exclude='');
			$selectinput.="$categories
						</select>";
			$submitbuttoncode="<input type=\"submit\" class=\"button\" name=\"createeditadcategory\" value=\"$aeword2\" />";
		}
		else {
			$categorynameinput="<p style=\"background:transparent url($awpcp_imagesurl/post_ico.png) left center no-repeat;padding-left:20px;\">";
			$categorynameinput.=__("Add a New Category","AWPCP");
			$categorynamefield ="<input name=\"category_name\" id=\"cat_name\" type=\"text\" class=\"inputbox\" value=\"$category_name\" size=\"40\" style=\"width: 220px\"/>";
			$selectinput="<select name=\"category_parent_id\"><option value=\"0\">";
			$selectinput.=__("Make This a Top Level Category","AWPCP");
			$selectinput.="</option>";
			$orderinput="<input name=\"category_order\" id=\"category_order\" type=\"text\" class=\"inputbox\" value=\"$category_order\" size=\"3\"/>";
			$categories=  get_categorynameid($cat_ID,$cat_parent_ID,$exclude='');
			$selectinput.="$categories
					</select>";
			$submitbuttoncode="<input type=\"submit\" class=\"button\" name=\"createeditadcategory\" value=\"$aeword2\" />";
		}

		// Start the page display
		$output .= "<div class=\"wrap\"><h2>";
		$output .= __("AWPCP Classifieds Management System Categories Management","AWPCP");
		$output .= "</h2>";
		if (isset($message) && !empty($message))
		{
			$output .= $message;
		}

		$sidebar = awpcp_admin_sidebar();
		$output .= $sidebar;

		if (empty($sidebar)) {
			$output .= "<div style=\"padding:10px;\"><p>";
		} else {
			$output .= "<div style=\"padding:10px; width: 75%\"><p>";
		}
		
		$output .= __("Below you can add and edit your categories. For more information about managing your categories visit the link below.","AWPCP");
		$output .= "</p><p><a href=\"http://www.awpcp.com/about/categories/\">";
		$output .= __("Useful Information for Classifieds Categories Management","AWPCP");
		$output .= "</a></p><b>";
		$output .= __("Icon Meanings","AWPCP");
		$output .= ":</b> &nbsp;&nbsp;&nbsp;<img src=\"$awpcp_imagesurl/edit_ico.png\" alt=\"";
		$output .= __("Edit Category","AWPCP");
		$output .= "\" border=\"0\"/>";
		$output .= __("Edit Category","AWPCP");
		$output .= " &nbsp;&nbsp;&nbsp;<img src=\"$awpcp_imagesurl/delete_ico.png\" alt=\"";
		$output .= __("Delete Category","AWPCP");
		$output .= "\" border=\"0\"/>";
		$output .= __("Delete Category","AWPCP");


		if ($hascaticonsmodule == 1 ) {
			if ( is_installed_category_icon_module() )
			{
				$label = __("Manage Category Icon", "AWPCP");
				$output .= " &nbsp;&nbsp;&nbsp;<img src=\"$awpcp_imagesurl/icon_manage_ico.png\" alt=\"";
				$output .= $label;
				$output .= "\" border=\"0\"/>";
				$output .= $label;
			}
		} else {
			$output .= "<div class=\"fixfloat\"><p style=\"padding-top:25px;\">";
			$output .= __("There is a premium module available that allows you to add icons to your categories. If you are interested in adding icons to your categories ","AWPCP");
			$output .= "<a href=\"http://www.awpcp.com/premium-modules/\">";
			$output .= __("Click here to find out about purchasing the Category Icons Module","AWPCP");
			$output .= "</a></p></div>";
		}

		$output .= "
			 </div>";

		if (empty($sidebar)) {
			$output .= "<div class=\"postbox\" style=\"padding:10px;\"><p>";
		} else {
			$output .= "<div class=\"postbox\" style=\"width:75%;float:left;padding:10px;\">";
		}
			 
		$output .= "<form method=\"post\" id=\"awpcp_launch\">
			 <input type=\"hidden\" name=\"category_id\" value=\"$cat_ID\" />
			  <input type=\"hidden\" name=\"aeaction\" value=\"$aeaction\" />
			  <input type=\"hidden\" name=\"offset\" value=\"$offset\" />
			  <input type=\"hidden\" name=\"results\" value=\"$results\" />

			<p style=\"line-height: 1em\">$aeword1</p>
			<table width=\"75%\" cellpadding=\"5\"><tr>
			<td>$categorynameinput</td>
			<td>$aeword3</td>
			<td>$aeword4</td>
			</tr>
			<tr>
			<td>$categorynamefield</td>
			<td>$selectinput</td>
			<td>$orderinput</td>
			</tr>
			</table>

			$promptmovetocat

			<p style=\"margin-top:5px;\" class=\"submit\">$submitbuttoncode $addnewlink</p>
			 </form>
			 </div>";

		if (empty($sidebar)) {
			$output .= "<div style=\"margin:0;padding:0px 0px 10px 0;\"><p>";
		} else {
			$output .= "<div style=\"margin:0;padding:0px 0px 10px 0;float:left;width:60%\">";
		}

		///////////////////////////
		// Show the paginated categories list for management
		//////////////////////////

		$from="$tbl_ad_categories";
		$where="category_name <> ''";

		$pager1=create_pager($from,$where,$offset,$results,$tpname='');
		$pager2=create_pager($from,$where,$offset,$results,$tpname='');

		$output .= "$pager1 <form name=\"mycats\" id=\"mycats\" method=\"post\">
		 <p><input type=\"submit\" name=\"deletemultiplecategories\" class=\"button\" value=\"";
		$output .= __("Delete Selected Categories","AWPCP");
		$output .= "\" />
		 <input type=\"submit\" name=\"movemultiplecategories\" class=\"button\" value=\"";
		$output .= __("Move Selected Categories","AWPCP");
		$output .= "\" />
		 <select name=\"moveadstocategory\"><option value=\"0\">";
		$output .= __("Select Move-To category","AWPCP");
		$output .= "</option>";
		$movetocategories=  get_categorynameid($cat_id = 0,$cat_parent_id= 0,$exclude);
		$output .= "$movetocategories</select></p>
		<p>";
		$output .= __("If deleting categories","AWPCP");
		$output .= "<input type=\"radio\" name=\"movedeleteads\" value=\"1\" checked='checked' >";
		$output .= __("Move Ads if any","AWPCP");
		$output .= "</input><input type=\"radio\" name=\"movedeleteads\" value=\"2\" >";
		$output .= __("Delete Ads if any","AWPCP");
		$output .= "</input></p>";

		$items=array();
		$query="SELECT category_id,category_name,category_parent_id,category_order FROM $from WHERE $where ORDER BY category_order,category_name ASC LIMIT $offset,$results";
		$res = awpcp_query($query, __LINE__);

		while ($rsrow=mysql_fetch_row($res))
		{
			$thecategoryicon='';

			if ( function_exists('get_category_icon') )
			{
				$category_icon=get_category_icon($rsrow[0]);
			}

			if ( isset($category_icon) && !empty($category_icon) )
			{
				$caticonsurl="$awpcp_imagesurl/caticons/$category_icon";
				$thecategoryicon="<img style=\"vertical-align:middle;margin-right:5px;\" src=\"$caticonsurl\" alt=\"$rsrow[1]\" border=\"0\"/>";
			}

			$thecategory_id=$rsrow[0];
			$thecategory_name="$thecategoryicon<a href=\"?page=Manage1&showadsfromcat_id=".$rsrow[0]."\">".stripslashes($rsrow[1])."</a>";
			$thecategory_parent_id=$rsrow[2];
			$thecategory_order=($rsrow[3] != '' ? $rsrow[3] : 0);
			
			$thecategory_parent_name=stripslashes(get_adparentcatname($thecategory_parent_id));
			$totaladsincat=total_ads_in_cat($thecategory_id);

			if ($hascaticonsmodule == 1 && is_installed_category_icon_module()) {
				$managecaticon = "<a href=\"?page=Configure3&cat_ID=$thecategory_id&action=managecaticon&offset=$offset&results=$results\"><img src=\"$awpcp_imagesurl/icon_manage_ico.png\" alt=\"";
				$managecaticon.= __("Manage Category Icon", "AWPCP");
				$managecaticon.= "\" border=\"0\"/></a>";
			} else {
				$managecaticon = '';
			}

			$awpcpeditcategoryword=__("Edit Category","AWPCP");
			$awpcpdeletecategoryword=__("Delete Category","AWPCP");

			$items[] = "<tr><td style=\"width:40%;padding:5px;border-bottom:1px dotted #dddddd;font-weight:normal;\"><input type=\"checkbox\" name=\"category_to_delete_or_move[]\" value=\"$thecategory_id\" />$thecategory_name ($totaladsincat)</td>
				<td style=\"width:35%;padding:5px;border-bottom:1px dotted #dddddd;font-weight:normal;\">$thecategory_parent_name</td>
				<td style=\"width:5%;padding:5px;border-bottom:1px dotted #dddddd;font-weight:normal;\">$thecategory_order</td>
				<td style=\"padding:5px;border-bottom:1px dotted #dddddd;font-size:smaller;font-weight:normal;\"> <a href=\"?page=Configure3&cat_ID=$thecategory_id&action=editcat&offset=$offset&results=$results\"><img src=\"$awpcp_imagesurl/edit_ico.png\" alt=\"$awpcpeditcategoryword\" border=\"0\"/></a> <a href=\"?page=Configure3&cat_ID=$thecategory_id&action=delcat&offset=$offset&results=$results\"><img src=\"$awpcp_imagesurl/delete_ico.png\" alt=\"$awpcpdeletecategoryword\" border=\"0\"/></a> $managecaticon</td></tr>";
		}

		$opentable="<table class=\"listcatsh\"><tr><td style=\"width:40%;padding:5px;\"><input type=\"checkbox\" onclick=\"CheckAll()\" />";
		$opentable.=__("Category Name (Total Ads)","AWPCP");
		$opentable.="</td><td style=\"width:35%;padding:5px;\">";
		$opentable.=__("Parent","AWPCP");
		$opentable.="</td><td style=\"width:5%;padding:5px;\">";
		$opentable.=__("Order","AWPCP");
		$opentable.="</td><td style=\"width:20%;padding:5px;;\">";
		$opentable.=__("Action","AWPCP");
		$opentable.="</td></tr>";
		$closetable="<tr><td style=\"width:40%;padding:5px;\">";
		$closetable.=__("Category Name (Total Ads)","AWPCP");
		$closetable.="</td><td style=\"width:35%;padding:5px;\">";
		$closetable.=__("Parent","AWPCP");
		$closetable.="</td><td style=\"width:5%;padding:5px;\">";
		$closetable.=__("Order","AWPCP");
		$closetable.="</td><td style=\"width:20%;padding:5px;\">";
		$closetable.=__("Action","AWPCP");
		$closetable.="</td></tr></table>";

		$theitems=smart_table($items,intval($results/$results),$opentable,$closetable);
		$showcategories="$theitems";

		$output .= "
		<style>
		table.listcatsh { width: 100%; padding: 0px; border: none; border: 1px solid #dddddd;}
		table.listcatsh td { width:33%;font-size: 12px; border: none; background-color: #F4F4F4;
		vertical-align: middle; font-weight: bold; }
		table.listcatsh tr.special td { border-bottom: 1px solid #ff0000;  }
		table.listcatsc { width: 100%; padding: 0px; border: none; border: 1px solid #dddddd;}
		table.listcatsc td { width:33%;border: none;
		vertical-align: middle; padding: 5px; font-weight: normal; }
		table.listcatsc tr.special td { border-bottom: 1px solid #ff0000;  }
		</style>
		$showcategories
		</form>$pager2</div>";

	echo $output;
}


// END FUNCTION: Manage categories

// START FUNCTION: Manage view images


// function awpcp_manage_viewimages()
// {
// 	//debug();
// 	$output = '';
// 	$cpagename_awpcp=get_awpcp_option('main-page-name');
// 	$awpcppagename = sanitize_title($cpagename_awpcp, $post_ID='');
// 	$laction='';

// 	$isclassifiedpage = checkifclassifiedpage($cpagename_awpcp);
// 	if ($isclassifiedpage == false)
// 	{
// 		$awpcpsetuptext=display_setup_text();
// 		$output .= $awpcpsetuptext;

// 	} else {

// 		global $message,$wpdb;
// 		$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";
// 		$where='';

// 		$output .= "<div class=\"wrap\"><h2>";
// 		$output .= __("AWPCP Classifieds Management System Manage Images","AWPCP");
// 		$output .= "</h2>";
// 		if (isset($message) && !empty($message))
// 		{
// 			$output .= $message;
// 		}
// 		$output .= awpcp_admin_sidebar();

// 		$output .= "<p style=\"padding:10px;border:1px solid#dddddd; width: 75%\">";
// 		$output .= __("Below you can manage the images users have uploaded. Your options are to delete images, and in the event you are operating with image approval turned on you can approve or disable images","AWPCP");
// 		$output .= "</p>";

// 		$output .= '<p><a href="'.admin_url().'/admin.php?page=Manage1">Return to the Listings page</a></p>';

// 		if (isset($_REQUEST['pdel']) && !empty( $_REQUEST['pdel'] ) )
// 		{
// 			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
// 			$output .= __("The image was deleted successfully","AWPCP");
// 			$output .= "</div>";
// 		}


// 		if (isset($_REQUEST['action']) && !empty($_REQUEST['action']))
// 		{
// 			$laction=$_REQUEST['action'];
// 		}

// 		if (empty($_REQUEST['action']))
// 		{
// 			if (isset($_REQUEST['a']) && !empty($_REQUEST['a']))
// 			{
// 				$laction=$_REQUEST['a'];
// 			}
// 		}

// 		if (isset($_REQUEST['id']) && !empty($_REQUEST['id']))
// 		{
// 			$actonid=$_REQUEST['id'];
// 			$where="ad_id='$actonid'";
// 		}
// 		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid']))
// 		{
// 			$adid=$_REQUEST['adid'];
// 		}
// 		if (isset($_REQUEST['picid']) && !empty($_REQUEST['picid']))
// 		{
// 			$picid=$_REQUEST['picid'];
// 		}
// 		if (isset($_REQUEST['adtermid']) && !empty($_REQUEST['adtermid']))
// 		{
// 			$adtermid=$_REQUEST['adtermid'];
// 		}
// 		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey']))
// 		{
// 			$adkey=$_REQUEST['adkey'];
// 		}
// 		if (isset($_REQUEST['editemail']) && !empty($_REQUEST['editemail']))
// 		{
// 			$editemail=$_REQUEST['editemail'];
// 		}
// 		if (isset($_REQUEST['offset']) && !empty($_REQUEST['offset']))
// 		{
// 			$offset=$_REQUEST['offset'];
// 		}
// 		if (isset($_REQUEST['results']) && !empty($_REQUEST['results']))
// 		{
// 			$editemail=$_REQUEST['results'];
// 		}

// 		if ($laction == 'approvepic')
// 		{

// 			$query="UPDATE  ".$tbl_ad_photos." SET disabled=0 WHERE ad_id='$adid' AND key_id='$picid'";
// 			$res = awpcp_query($query, __LINE__);

// 			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
// 			$output .= __("The image has been enabled and can now be viewed","AWPCP");
// 			$output .= "</div>";

// 		}
// 		elseif ($laction == 'rejectpic')
// 		{

// 			$query="UPDATE  ".$tbl_ad_photos." SET disabled=1 WHERE ad_id='$adid' AND key_id='$picid'";
// 			$res = awpcp_query($query, __LINE__);

// 			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
// 			$output .= __("The image has been disabled and can no longer be viewed","AWPCP");
// 			$output .= "</div>";


// 		}
// 		elseif ($laction == 'deletepic')
// 		{
// 			$message=deletepic($picid,$adid,$adtermid,$adkey,$editemail);
// 			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$message</div>";
// 		}

// 		$output .= viewimages($where);
// 	}
// 	//Echo OK here:
// 	echo $output;
// }


// END FUNCTION: Manage view images

// START FUNCTION: Manage view listings


function awpcp_manage_viewlistings() {
	global $hasextrafieldsmodule, $wpdb;

	$cpagename_awpcp = get_awpcp_option('main-page-name');
	$awpcppagename = sanitize_title($cpagename_awpcp, $post_ID='');
	$laction = '';
	$output = '';

	global $awpcp_imagesurl, $message;

	$output .= "<div class=\"wrap\"><h2>";
	$output .= __("AWPCP Classifieds Management System Manage Ad Listings","AWPCP");
	$output .= "</h2>";
	
	if (isset($message) && !empty($message)) {
		$output .= $message;
	}

	$sidebar = awpcp_admin_sidebar();
	$output .= $sidebar;

	$tbl_ads = AWPCP_TABLE_ADS;
	$tbl_ad_photos = AWPCP_TABLE_ADPHOTOS;

	if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
		$laction=$_REQUEST['action'];
	}

	if (empty($_REQUEST['action']) && isset($_REQUEST['a']) && !empty($_REQUEST['a'])) {
		$laction=$_REQUEST['a'];
	}

	if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
		$actonid=$_REQUEST['id'];
	}
		if ($laction == 'deletead')
		{
			$message=deletead($actonid,$adkey='',$editemail='');
			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$message</div>";
		}
		if ($laction == 'unflagad')
		{	$sql = 'update '.$wpdb->prefix.'awpcp_ads set flagged = 0 where ad_id = "'.$_GET['id'].'"';
			$wpdb->query($sql);
			$message = 'The ad was unflagged';
			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$message</div>";
			do_action('awpcp_unflag_ad');

		} elseif ($laction == 'editad') {
			$editemail=get_adposteremail($actonid);
			$adaccesskey=get_adkey($actonid);
			$awpcppage=get_currentpagename();
			$awpcppagename = sanitize_title($awpcppage, $post_ID='');
			$offset=clean_field($_REQUEST['offset']);
			$results=clean_field($_REQUEST['results']);
				
			$output .= load_ad_post_form($actonid, $action='editad', $awpcppagename,
				$adtermid='', $editemail, $adaccesskey, $adtitle='', $adcontact_name='',
				$adcontact_phone='', $adcontact_email='', $adcategory='', 
				$adcontact_city='', $adcontact_state='', $adcontact_country='',
				$ad_county_village='', $ad_item_price='', $addetails='', $adpaymethod='',
				$offset, $results, $ermsg='', $websiteurl='', $checkhuman='', 
				$numval1='', $numval2='');
		
		} elseif ($laction == 'dopost1') {
			$errors = array();
			$output .= awpcp_place_ad_save_details_step(array(), $errors, true);

		} elseif ($laction == 'approvead') {
			$ad = AWPCP_Ad::find_by_id($actonid);

			$disabled_date = $ad->get_disabled_date();
			if (($ad->disabled && empty($disabled_date)) || $ad->has_expired()) {
				$ad->set_start_date(current_time('mysql'));
				$ad->set_end_date($ad->calculate_end_date());
			}

			// TODO: create enable() method in AWPCP_Ad
			$ad->disabled = 0;
			$ad->save();

		    $sql  = "UPDATE " . AWPCP_TABLE_ADPHOTOS . " SET disabled = 0 WHERE ad_id = %d";
		    $wpdb->query($wpdb->prepare($sql, $actonid));

			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$output .= __("The Ad has been approved", "AWPCP");
			$output .= "</div>";

			do_action('awpcp_approve_ad', $ad);

		} elseif ($laction == 'rejectad') {
			$ad = AWPCP_Ad::find_by_id($actonid);
			$ad->disable();

			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$output .= __("The ad has been disabled","AWPCP");
			$output .= "</div>";
		}
		elseif ($laction == 'makefeatured')
		{
			$query="UPDATE  ".$tbl_ads." SET is_featured_ad=1 WHERE ad_id='$actonid'";
			$res = awpcp_query($query, __LINE__);

			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$output .= __("The ad has been marked as Featured","AWPCP");
			$output .= "</div>";
			do_action('awpcp_make_featured_ad'); 
		}
		elseif ($laction == 'makenonfeatured')
		{
			$query="UPDATE  ".$tbl_ads." SET is_featured_ad=0 WHERE ad_id='$actonid'";
			$res = awpcp_query($query, __LINE__);

			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$output .= __("The ad has been marked as Featured","AWPCP");
			$output .= "</div>";
			do_action('awpcp_make_featured_ad'); 
		}
		elseif ($laction == 'spamad')
		{
			awpcp_submit_spam($actonid);

			$ad = AWCP_Ad::find_by_id($actonid);
			$ad->delete();

			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$output .= __("The Ad has been marked as SPAM and removed.","AWPCP");
			$output .= "</div>";
		}
		elseif ($laction == 'cps')
		{
			if (isset($_REQUEST['changeto']) && !empty($_REQUEST['changeto']))
			{
				$changeto=$_REQUEST['changeto'];
			}

			$query="UPDATE  ".$tbl_ads." SET payment_status='$changeto', disabled=0 WHERE ad_id='$actonid'";
			$res = awpcp_query($query, __LINE__);

			$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$output .= __("The ad payment status has been changed","AWPCP");
			$output .= "</div>";

			do_action('awpcp_approve_ad', AWPCP_Ad::find_by_id($actonid));

		} elseif ($laction == 'viewad') {
			if (isset($actonid) && !empty($actonid)) {

				if (empty($sidebar)) {
					$output .= "<div class=\"postbox\" style=\"padding:20px;\">";
				} else {
					$output .= "<div class=\"postbox\" style=\"padding:20px;width:72%;\">";
				}

				// start insert delete | edit | approve/disable admin links

				$offset=(isset($_REQUEST['offset'])) ? (clean_field($_REQUEST['offset'])) : ($offset=0);
				$results=(isset($_REQUEST['results']) && !empty($_REQUEST['results'])) ? clean_field($_REQUEST['results']) : ($results=10);

				$deletelink=  "<a href=\"?page=Manage1&action=deletead&id=$actonid&offset=$offset&results=$results\">";
				$deletelink.=__("Delete","AWPCP");
				$deletelink.="</a>";
				$editlink=" |  <a href=\"?page=Manage1&action=editad&id=$actonid&offset=$offset&results=$results\">";
				$editlink.=__("Edit","AWPCP");
				$editlink.="</a>";


				$category_id = get_adcategory($actonid);
				$href = add_query_arg(array('page' => 'Manage1', 'showadsfromcat_id' => $category_id), admin_url('admin.php'));

				$output .= "<div style=\"padding:10px 0px;; margin-bottom:20px;\">";
				$output .= "<b>" . __("Category: ","AWPCP") . "</b>";
				$output .= '<a href="' . esc_attr($href) . '">' . get_adcatname($category_id) . "</a> &raquo ";
				$output .= "<b>" . __("Manage Listing: ","AWPCP") . "</b>";
				$output .= "$deletelink $editlink";

				//if (get_awpcp_option('adapprove') == 1 || get_awpcp_option('freepay')  == 1)
				//{
					$adstatusdisabled=check_if_ad_is_disabled($actonid);

					if ($adstatusdisabled)
					{
						$approvelink=" | <a href=\"?page=Manage1&action=approvead&id=$actonid&offset=$offset&results=$results\">";
						$approvelink.=__("Approve","AWPCP");
						$approvelink.="</a> ";
					}
					else
					{
						$approvelink=" | <a href=\"?page=Manage1&action=rejectad&id=$actonid&offset=$offset&results=$results\">";
						$approvelink.=__("Disable","AWPCP");
						$approvelink.="</a> ";
					}
					//Tack on spam control:
					if (get_awpcp_option('useakismet'))
					{
						$approvelink.=" | <a href=\"?page=Manage1&action=spamad&id=$actonid&offset=$offset&results=$results\">";
						$approvelink.=__("Mark as SPAM","AWPCP");
						$approvelink.="</a> ";
					}
					$output .= "$approvelink";
				//}
				if (function_exists('awpcp_featured_ads'))
				    $output .= ' | '.awpcp_make_featured_link($actonid, $offset, $results);

				$output .= "</div>";

				// end insert delete | edit | approve/disable admin links
				$output .= showad($actonid, $omitmenu=1);

				$output .= "</div>";

			} else {
				$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
				$output .= __("No ad ID was supplied","AWPCP");
				$output .= "</div>";
			}

		} elseif (in_array($laction, array('viewimages', 'deletepic', 'rejectpic', 
										   'approvepic', 'set-primary-image'))) 
		{
			$picid = awpcp_request_param('picid');
			$adid = awpcp_request_param('adid');
			$adtermid = awpcp_request_param('adtermid');
			$adtermid = awpcp_request_param('adkey');
			$editemaul = awpcp_request_param('editemail');

			if ($laction == 'deletepic'){
				$message = deletepic($picid,$adid,$adtermid,$adkey,$editemail);
				$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$message</div>";
			}
			elseif ($laction == 'rejectpic') {
				$query="UPDATE  ". AWPCP_TABLE_ADPHOTOS ." SET disabled=1 WHERE ad_id='$adid' AND key_id='$picid'";
				$res = awpcp_query($query, __LINE__);

				$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
				$output .= __("The image has been disabled and can no longer be viewed","AWPCP");
				$output .= "</div>";
			}
			elseif ($laction == 'approvepic') {
				$query="UPDATE  ". AWPCP_TABLE_ADPHOTOS ." SET disabled=0 WHERE ad_id='$adid' AND key_id='$picid'";
				$res = awpcp_query($query, __LINE__);

				$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
				$output .= __("The image has been enabled and can now be viewed","AWPCP");
				$output .= "</div>";
			}
			elseif ($laction == 'set-primary-image') {
				awpcp_set_ad_primary_image($adid, $picid);
			}

			if (isset($_REQUEST['id']) && !empty($_REQUEST['id']))
			{
				$picid=$_REQUEST['id'];
				$where="ad_id='$picid'";
			}
			else
			{
				$where='';
			}

			$output .= viewimages($where);
		}
		elseif ($laction == 'lookupadby')
		{
			if (isset($_REQUEST['lookupadbychoices']) && !empty($_REQUEST['lookupadbychoices']))
			{
				$lookupadbytype=$_REQUEST['lookupadbychoices'];
			}
			else
			{
				$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
				$output .= __("You need to check whether you want to look up the ad by title id or keyword","AWPCP");
				$output .= "</div>";
			}
			if (isset($_REQUEST['lookupadidortitle']) && !empty($_REQUEST['lookupadidortitle']))
			{
				$lookupadbytypevalue=$_REQUEST['lookupadidortitle'];
			}
			else
			{
				$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">You need enter either an ad title or an ad id to look up</div>";
			}
			if ($lookupadbytype == 'adid')
			{
				if (!is_numeric($lookupadbytypevalue))
				{
					$output .= "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">You indicated you wanted to look up the ad by ID but you entered an invalid ID. Please try again</div>";
				}
				else
				{
					$where="ad_id='$lookupadbytypevalue'";
				}
			}
			elseif ($lookupadbytype == 'adtitle')
			{
				$where="ad_title='$lookupadbytypevalue'";
			}
			elseif ($lookupadbytype == 'titdet')
			{
				$where="MATCH (ad_title,ad_details) AGAINST (\"$lookupadbytypevalue\")";
			}
			elseif ($lookupadbytype == 'location')
			{
				$where="ad_city='$lookupadbytypevalue' OR ad_state='$lookupadbytypevalue' OR ad_country='$lookupadbytypevalue' OR ad_county_village='$lookupadbytypevalue'";
			}
		}

		if (isset($_REQUEST['showadsfromcat_id']) && !empty($_REQUEST['showadsfromcat_id'])){
			$thecat_id=$_REQUEST['showadsfromcat_id'];
			$where="ad_title <> '' AND (ad_category_id='$thecat_id' OR ad_category_parent_id='$thecat_id')";
		}

		$sortby='';
		$lookupadidortitle='';
		$from="$tbl_ads";
		if (!isset($where) || empty($where))
		{
			$where="ad_title <> ''";
		}

		if (!ads_exist())
		{
			$showadstomanage="<p style=\"padding:10px\">";
			$showadstomanage.=__("There are currently no ads in the system","AWPCP");
			$showadstomanage.="</p>";
			$pager1='';
			$pager2='';
			$output .= $showadstomanage;
		}
		else
		{
			if (isset($_REQUEST['offset']) && !empty($_REQUEST['offset'])) {
				$offset = clean_field($_REQUEST['offset']);
			} else {
				$offset = 0;
			}

			if ((isset($_REQUEST['results']) && !empty($_REQUEST['results']))) {
				$results =  clean_field($_REQUEST['results']);
			} else {
				 $results = 10;
			}

			if (isset($_REQUEST['sortby']))
			{
				$sortby=$_REQUEST['sortby'];
				if ($sortby == 'titleza')
				{
					$orderby="ad_title DESC";
				}
				elseif ($sortby == 'titleaz')
				{
					$orderby="ad_title ASC";
				}
				elseif ($sortby == 'awaitingapproval')
				{
					$orderby="disabled DESC, ad_key DESC";
				}
				elseif ($sortby == 'paidfirst')
				{
					$orderby="payment_status DESC, ad_key DESC";
				}
				elseif ($sortby == 'mostrecent')
				{
					$orderby="ad_startdate DESC";
				}
				elseif ($sortby == 'oldest')
				{
					$orderby="ad_startdate ASC";
				}
				else if ($sortby == 'renewed')
				{
					$orderby="renewed_date DESC, ad_startdate DESC";
				}
				elseif ($sortby == 'featured')
				{
					$orderby="is_featured_ad DESC, ad_startdate DESC";
				}
				elseif ($sortby == 'flagged')
				{
					$orderby="ad_startdate DESC";
					$where = ' flagged = 1 ';
				}
			}

			if (!isset($sortby) || empty($sortby))
			{
				$orderby="ad_id DESC";
			}

			$sql = 'select count(*) from '.$wpdb->prefix.'awpcp_ads where flagged = 1';
			$flagged_cnt = $wpdb->get_var($sql);

			$items=array();

			$query = "SELECT ad_id, ad_category_id, ad_title, ad_contact_name ";
			$query.= "     , ad_contact_phone, ad_city, ad_state, ad_country ";
			$query.= "     , ad_county_village, ad_details, ad_postdate, disabled ";
			$query.= "     , payment_status, is_featured_ad, ad_startdate, ad_enddate ";
			$query.= "     , adterm_id, renewed_date ";
			$query.= "FROM $from WHERE $where ORDER BY $orderby LIMIT $offset,$results";

			$ads = $wpdb->get_results($query);

			foreach ($ads as $result) {

				$ad_id = $result->ad_id;
				$ad_term_id = $result->adterm_id;
				$category_id = $result->ad_category_id;
				$category_name = get_adcatname($category_id);
				$tcname = $category_name;
				$disabled = $result->disabled;
				$paymentstatus = $result->payment_status;
				$is_featured = $result->is_featured_ad;
				$ad_start = $result->ad_startdate;
				$ad_end = $result->ad_enddate;
				$ad_renewed_date = $result->renewed_date;

				$fee_plan_name = awpcp_get_fee_plan_name($ad_id, $ad_term_id);

				if ('' != $ad_start)
					$ad_start = date('M d Y', strtotime($ad_start));
				if ('' != $ad_end)
					$ad_end = date('M d, Y' ,strtotime($ad_end));
				if ('' != $ad_renewed_date)
					$ad_renewed_date = date('M d, Y' ,strtotime($ad_renewed_date));

				if (!isset($paymentstatus) || empty($paymentstatus)) {
					$paymentstatus="N/A";
				}

				$pager1="<p>".create_pager($from,$where,$offset,$results,$tpname='')."</p>";
				$pager2="<p>".create_pager($from,$where,$offset,$results,$tpname='')."</p>";

				// XXX: this variables are unused. verify and delete them
				$awpcppage=get_currentpagename();
				$awpcppagename = sanitize_title($awpcppage, $post_ID='');
				$awpcpwppostpageid = awpcp_get_page_id_by_ref('main-page-name');

				$ad_checkbox = "<input type=\"checkbox\" name=\"awpcp_ads_to_action[]\" value=\"$ad_id\" />";
				$ad_title = "<a href=\"?page=Manage1&action=viewad&id=$ad_id&offset=$offset&results=$results\">" . stripslashes($result->ad_title) . "</a>";

				$handlelink="<a class=\"trash\" href=\"?page=Manage1&action=deletead&id=$ad_id&offset=$offset&results=$results\">";
				$handlelink.=__("Delete","AWPCP");
				$handlelink.="</a> | <a href=\"?page=Manage1&action=editad&id=$ad_id&offset=$offset&results=$results\">";
				$handlelink.=__("Edit","AWPCP");
				$handlelink.="</a>";

				if ( 'flagged' == $sortby ) {
				    $handlelink .= ' | <a href="?page=Manage1&sortby=flagged&action=unflagad&id='.$ad_id.'&offset='.$offset.'&results='.$results.'">Unflag</a>';
				}

				$approvelink='';
				//Allow approval anytime
				//if (get_awpcp_option('adapprove') == 1 || get_awpcp_option('freepay')  == 1)
				//{
					if ($disabled == 1)
					{
						$approvelink="<a href=\"?page=Manage1&action=approvead&id=$ad_id&offset=$offset&results=$results\">";
						$approvelink.=__("Approve","AWPCP");
						$approvelink.="</a> | ";
					}
					else
					{
						$approvelink="<a href=\"?page=Manage1&action=rejectad&id=$ad_id&offset=$offset&results=$results\">";
						$approvelink.=__("Disable","AWPCP");
						$approvelink.="</a> | ";
					}
					if (get_awpcp_option('useakismet'))
					{
						$approvelink.="<a href=\"?page=Manage1&action=spamad&id=$ad_id&offset=$offset&results=$results\">";
						$approvelink.=__("Mark as SPAM","AWPCP");
						$approvelink.="</a> | ";
					}
					//}


				if (get_awpcp_option('freepay') == 1)
				{
					$paymentstatushead="<th>";
					$paymentstatushead.=__("Pay Status","AWPCP");
					$paymentstatushead.="</th>";

					$changepaystatlink='';

					if ($paymentstatus == 'Pending')
					{
						$changepaystatlink="<a href=\"?page=Manage1&action=cps&id=$ad_id&changeto=Completed&sortby=$sortby\">";
						$changepaystatlink.=__("Complete","AWPCP");
						$changepaystatlink.="</a>";
					}

					$paymentstatus="<td> $paymentstatus <SUP>$changepaystatlink</SUP></td>";
				}
				else
				{
					$paymentstatushead="";
					$paymentstatus="";
				}

				if (get_awpcp_option('imagesallowdisallow') == 1)
				{

					//$imagesnotehead="<th>";
					//$imagesnotehead.=__("Total Images","AWPCP");
					//$imagesnotehead.="</th>";

					$totalimagesuploaded=get_total_imagesuploaded($ad_id);

					if ($totalimagesuploaded >= 1)
					{
						$viewimages="<a href=\"?page=Manage1&action=viewimages&id=$ad_id&sortby=$sortby\">";
						$viewimages.=__("View Images","AWPCP");
						$viewimages.="</a> ($totalimagesuploaded)";
					}
					else
					{
						$viewimages = __("No Images", "AWPCP") . " (<a href=\"?page=Manage1&action=viewimages&id=$ad_id&sortby=$sortby\">";
						$viewimages.=__("Add","AWPCP");
						$viewimages.="</a>)";
					}

					//$imagesnote="<td> $viewimages</td>";
					$imagesnote =  ' | ' . $viewimages;
				}
				else {$imagesnotehead="";$imagesnote="";}

				if (function_exists('awpcp_featured_ads')) {
					$makefeaturedlink = awpcp_make_featured_link($ad_id, $offset, $results); 
				} else {
					$makefeaturedlink = '';
				}

				if (function_exists('awpcp_featured_ads')) { 
				    $featured_head = awpcp_make_featured_head();
				    $featured_note = awpcp_make_featured_note($is_featured);
				} else {
				    $featured_head = '';
				    $featured_note = '';
				}

				$startend_date_head = '<th>Fee Plan</th><th>Start Date</th><th>End Date</th>';
				$startend_date = '<td>'.$fee_plan_name.'</td><td>'.$ad_start.'</td><td>'.$ad_end.'</td>';

				$renewed_date_head = '<th>' . __('Renewed', 'AWPCP') . '</th>';
				$renewed_date = '<td>' . $ad_renewed_date . '</td>';

				$items[]="<tr><th scope=\"row\">$ad_checkbox</th><td class=\"displayadscell\" width=\"200\">$ad_title</td>
					<td> $approvelink $makefeaturedlink $handlelink  $imagesnote  </td>
					$paymentstatus $startend_date $renewed_date $featured_note</tr>";

				$opentable="<table class=\"widefat fixed\"><thead><tr>";
				$opentable.="<th style=\"width:18px\"><input type=\"checkbox\" onclick=\"CheckAllAds()\" /></th>";
				$opentable.="<th>";
				$opentable.=__("Ad Headline","AWPCP");
				$opentable.="</th><th style=\"width:25%\">";
				$opentable.=__("Manage Ad","AWPCP");
				$opentable.="</th>$paymentstatushead $startend_date_head $renewed_date_head $featured_head</tr></thead>";
				$closetable="</table>";


				$theadlistitems=smart_table2($items,intval($results/$results),$opentable,$closetable,false);
				$showadstomanage="$theadlistitems";
				$showadstomanagedeletemultiplesubmitbutton="<input type=\"submit\" name=\"deletemultipleads\" class=\"button\" value=\"";
				$showadstomanagedeletemultiplesubmitbutton.=__("Delete Checked Ads","AWPCP");
				$showadstomanagedeletemultiplesubmitbutton.="\" />&nbsp;&nbsp;<input type=\"submit\" name=\"spammultipleads\" class=\"button\" value=\"";
				$showadstomanagedeletemultiplesubmitbutton.=__("Mark Checked Ads as SPAM","AWPCP");
				$showadstomanagedeletemultiplesubmitbutton.="\" /></p>";

			}
			if (!isset($ad_id) || empty($ad_id) || $ad_id == 0 )
			{
				$showadstomanage="<p style=\"padding:20px;\">";
				$showadstomanage.=__("There were no ads found","AWPCP");
				$showadstomanage.="</p>";
				$showadstomanagedeletemultiplesubmitbutton="";
				$pager1='';
				$pager2='';
			}

		$output .= "
			<style>
			table.listcatsh { width: 75%; padding: 0px; border: none; border: 1px solid #dddddd;}
			table.listcatsh td { width:20%;font-size: 12px; border: none; background-color: #F4F4F4;
			vertical-align: middle; font-weight: normal; }
			table.listcatsh tr.special td { border-bottom: 1px solid #ff0000;  }
			table.listcatsc { width: 100%; padding: 0px; border: none; border: 1px solid #dddddd;}
			table.listcatsc td { width:20%;border: none;
			vertical-align: middle; padding: 5px; font-weight: normal; }
			table.listcatsc tr.special td { border-bottom: 1px solid #ff0000;  }
			#listingsops { padding:10px; }
			#adssort { padding:10px; height:150px;}
			#listingsops .deletechekedbuttom { width:30%; float:left;margin:5px 0px 5px 0px;}
			#listingsops .sortadsby { width:70%; float:left;margin:5px 0px 5px 0px;}
			#listingsops .sortadsby a { 	text-decoration:none; }
			#listingsops .sortadsby a:hover { text-decoration:underline;	}
			#lookupadsby { padding:10px; }
			#lookupadsby .lookupadsbytitle { float:left; margin:4px 20px 0px 0px; }
			#lookupadsby .lookupadsbyform { float:left; margin:0;  }
			</style>
			";

		if (empty($sidebar)) {
			$output .= "<div>";
		} else {
			$output .= "<div style=\"width:75%; float:left\">";
		}

		$output .= "<div id=\"lookupadsby\"><div class=\"lookupadsbytitle\">
			<b>";
		$output .= __("Look Up Ad By","AWPCP");
		$output .= "</b></div>
			<div class=\"lookupadsbyform\">
			<form method=\"post\">
			<input type=\"radio\" name=\"lookupadbychoices\" value=\"adid\">Ad ID</input>
			<input type=\"radio\" name=\"lookupadbychoices\" value=\"adtitle\">Ad Title</input>
			<input type=\"radio\" checked='true' name=\"lookupadbychoices\" value=\"titdet\">Keyword</input>
			<input type=\"radio\" name=\"lookupadbychoices\" value=\"location\">Location</input>
			<input type=\"text\" name=\"lookupadidortitle\" value=\"$lookupadidortitle\"></input>
			<input type=\"hidden\" name=\"action\" value=\"lookupadby\" />
			<input type=\"submit\" class=\"button\" value=\"Look Up Ad\" />
			</form>
			</div>
			</div>
			<div style=\"clear:both;\"></div>

			$pager1
			<form name=\"manageads\" id=\"manageads\" method=\"post\">
			<div id=\"listingsops\">
			<div class=\"deletechekedbuttom\">$showadstomanagedeletemultiplesubmitbutton</div>
			<div class=\"sortadsby\">";
			$output .= __("Sort Ads By","AWPCP");
			$output .= ": ";

			$sorts = array();
			if ($sortby == 'mostrecent') {
				$sorts[] = "<b>" . __("Newest","AWPCP") . "</b>";
			} else {
				$sorts[] = "<a href=\"?page=Manage1&sortby=mostrecent\">" . __("Newest","AWPCP") . "</a>";
			}

			if ($sortby == 'oldest') {
				$sorts[] = "<b>" . __("Oldest","AWPCP") . "</b>";
			} else {
				$sorts[] = "<a href=\"?page=Manage1&sortby=oldest\">" . __("Oldest","AWPCP") . "</a>";
			}

			$label = __("Renewed Date","AWPCP");
			if ($sortby == 'renewed') {
				$sorts[] = "<b>" . $label . "</b>";
			} else {
				$sorts[] = "<a href=\"?page=Manage1&sortby=renewed\">" . $label . "</a>";
			}

			if ($sortby == 'titleza') {
				$sorts[] = "<b>" . __("Title Z-A","AWPCP") . "</b>";
			} else {
				$sorts[] = "<a href=\"?page=Manage1&sortby=titleza\">" . __("Title Z-A","AWPCP") . "</a>";
			}

			if ($sortby == 'titleaz') {
				$sorts[] = "<b>" . __("Title A-Z","AWPCP") . "</b>";
			} else {
				$sorts[] = "<a href=\"?page=Manage1&sortby=titleaz\">" . __("Title A-Z","AWPCP") . "</a>";
			}

			if (get_awpcp_option('adapprove') == 1) {
				if ($sortby == 'awaitingapproval') {
					$sorts[] = "<b>" . __("Awaiting Approval","AWPCP") . "</b>";
				} else {
					$sorts[] = "<a href=\"?page=Manage1&sortby=awaitingapproval\">" . __("Awaiting Approval","AWPCP"). "</a>";
				}
			}

			if (get_awpcp_option('freepay') == 1) {
				if ($sortby == 'paidfirst') {
					$sorts[] = "<b>" . __("Paid Ads First","AWPCP") . "</b>";
				} else {
					$sorts[] = "<a href=\"?page=Manage1&sortby=paidfirst\">" . __("Paid Ads First","AWPCP") . "</a>";
				}
			}

			if ($sortby == 'flagged') {
				$sorts[] = "<b>" . __("Flagged Ads","AWPCP") . "</b>";
			} else {
				$style = $flagged_cnt > 0 ? ' style="color:#cf0000" ' : '';
				$sorts[] = "<a href=\"?page=Manage1&sortby=flagged\" $style>" . __("Flagged Ads","AWPCP") . " ($flagged_cnt)</a>";
			}

			$output .= join(' | ', $sorts);

			if (function_exists('awpcp_featured_ads')) { 
			    $output .= awpcp_make_featured_sort($sortby);
			}

			$output .= "
			</div>
			</div>

			$showadstomanage
		<div id=\"listingsops\">$showadstomanagedeletemultiplesubmitbutton</div>
			</form>
			$pager2";


			$output .= "</div></div>";
		}
	//Echo OK here:
	echo $output;
}


//	END FUNCTION: Manage view listings

//	START FUNCTION: display images for admin view


/**
 * Show view to manage images in AWPCP database.
 *
 * This function is called both from the AWPCP Admin Panel and
 * the AWPCP User Ad Management Panel.
 *
 * @param $where string SQL string to filter shown images.
 * @param $approve boolean Whether the Approve/Disable buttons are shown or not.
 * @param $delete_image_form_action string URL used as the action for the 
 *										   delete form.
 */
function viewimages($where, $approve=true, $delete_image_form_action=null) {

	//debug();
	$output = '';
	global $wpdb;
	$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";
	$thumbnailwidth=get_awpcp_option('imgthumbwidth');
	$thumbnailwidth.="px";

	$from="$tbl_ad_photos";

	if ( '' != $_GET['id'] ) {
		$sql = 'select ad_title from ' . $wpdb->prefix . 'awpcp_ads where ad_id ="'.absint( $_GET['id'] ).'"';
		$ad_title = $wpdb->get_var( $sql );
	}

	if (!isset($where) || empty($where))
	{
		$where="image_name <> ''";
	}

	$upload_result = '';
	if ( isset($_POST['awpcp_action']) && 'add_image' == $_POST['awpcp_action'] && '' != $_GET['id'] )  { 
		$adid = absint( $_GET['id'] );
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'awpcp_upload_image' ) ) {
		    $upload_result = admin_handleimagesupload( $adid );
		}
	}

	if (!images_exist())
	{
		$imagesallowedstatus='';

		if (get_awpcp_option('imagesallowdisallow') == 0)
		{
			$href = add_query_arg(array('page' => 'awpcp-admin-settings'), admin_url());
			$imagesallowedstatus=__("You are not currently allowing users to upload images with their ad. To allow users to upload images please change the related setting in your general options configuration", "AWPCP");
			$imagesallowedstatus.="<p><a href=\"$href\">";
			$imagesallowedstatus.=__("Click here to change allowed images status","AWPCP");
			$imagesallowedstatus.="</a></p>";
		}

		$showimages="<p style=\"padding:10px\">";
		$showimages.=__("There are currently no images in the system","AWPCP");
		$showimages="$imagesallowedstatus</p>";
		$pager1='';
		$pager2='';
	}
	else
	{
		$offset=(isset($_REQUEST['offset'])) ? (clean_field($_REQUEST['offset'])) : ($offset=0);
		$results=(isset($_REQUEST['results']) && !empty($_REQUEST['results'])) ? clean_field($_REQUEST['results']) : ($results=10);

		$items=array();
		$query = "SELECT key_id, ad_id, image_name, disabled, is_primary FROM $from ";
		$query.= "WHERE $where ORDER BY image_name DESC LIMIT $offset, $results";
		$res = awpcp_query($query, __LINE__);

		while ($rsrow=mysql_fetch_row($res)) {
			list($ikey, $adid, $image_name, $disabled, $is_primary)=$rsrow;
			$adtermid=get_adterm_id($adid);
			$editemail=get_adposteremail($adid);
			$adkey=get_adkey($adid);

			if (is_null($delete_image_form_action)) {
				// $delete_image_form_action = '?page=Manage2&sortby=';
				$delete_image_form_action = awpcp_current_url();
			}

			$dellink="<form method=\"post\" action=\"$delete_image_form_action\">";
			$dellink.="<input type=\"hidden\" name=\"adid\" value=\"$adid\" />";
			$dellink.="<input type=\"hidden\" name=\"picid\" value=\"$ikey\" />";
			$dellink.="<input type=\"hidden\" name=\"adtermid\" value=\"$adtermid\" />";
			$dellink.="<input type=\"hidden\" name=\"adkey\" value=\"$adkey\" />";
			$dellink.="<input type=\"hidden\" name=\"editemail\" value=\"$editemail\" />";
			$dellink.="<input type=\"hidden\" name=\"action\" value=\"deletepic\" />";
			$dellink.="<input type=\"submit\" class=\"button\" value=\"";
			$dellink.=__("Delete","AWPCP");
			$dellink.="\" />";
			$dellink.="</form>";
			$transval='';
			if ($disabled == 1){
				$transval="style=\"-moz-opacity:.20; filter:alpha(opacity=20); opacity:.20;\"";
			}

			$approvelink='';

			// show Approve/Disble buttons when needed
			if ($disabled == 1 && $approve) {
				$approvelink="<form method=\"post\" action=\"$delete_image_form_action\">";
				$approvelink.="<input type=\"hidden\" name=\"adid\" value=\"$adid\" />";
				$approvelink.="<input type=\"hidden\" name=\"picid\" value=\"$ikey\" />";
				$approvelink.="<input type=\"hidden\" name=\"adtermid\" value=\"$adtermid\" />";
				$approvelink.="<input type=\"hidden\" name=\"adkey\" value=\"$adkey\" />";
				$approvelink.="<input type=\"hidden\" name=\"editemail\" value=\"$editemail\" />";
				$approvelink.="<input type=\"hidden\" name=\"action\" value=\"approvepic\" />";
				$approvelink.="<input type=\"submit\" class=\"button\" value=\"";
				$approvelink.=__("Approve","AWPCP");
				$approvelink.="\" />";
				$approvelink.="</form>";
			} else if ($approve) {
				$approvelink="<form method=\"post\" action=\"$delete_image_form_action\">";
				$approvelink.="<input type=\"hidden\" name=\"adid\" value=\"$adid\" />";
				$approvelink.="<input type=\"hidden\" name=\"picid\" value=\"$ikey\" />";
				$approvelink.="<input type=\"hidden\" name=\"adtermid\" value=\"$adtermid\" />";
				$approvelink.="<input type=\"hidden\" name=\"adkey\" value=\"$adkey\" />";
				$approvelink.="<input type=\"hidden\" name=\"editemail\" value=\"$editemail\" />";
				$approvelink.="<input type=\"hidden\" name=\"action\" value=\"rejectpic\" />";
				$approvelink.="<input type=\"submit\" class=\"button\" value=\"";
				$approvelink.=__("Disable","AWPCP");
				$approvelink.="\" />";
				$approvelink.="</form>";
			}

			$primary_image_form = '';
			if (!$is_primary) {
				$primary_image_form = "<form method=\"post\" action=\"$delete_image_form_action\">";
				$primary_image_form.= "<input type=\"hidden\" name=\"adid\" value=\"$adid\" />";
				$primary_image_form.= "<input type=\"hidden\" name=\"picid\" value=\"$ikey\" />";
				$primary_image_form.= "<input type=\"hidden\" name=\"adtermid\" value=\"$adtermid\" />";
				$primary_image_form.= "<input type=\"hidden\" name=\"adkey\" value=\"$adkey\" />";
				$primary_image_form.= "<input type=\"hidden\" name=\"editemail\" value=\"$editemail\" />";
				$primary_image_form.= "<input type=\"hidden\" name=\"action\" value=\"set-primary-image\" />";
				$primary_image_form.= "<input type=\"submit\" class=\"button\" value=\"";
				$primary_image_form.= __("Set as primary","AWPCP");
				$primary_image_form.= "\" />";
				$primary_image_form.= "</form>";
			}

			$large_image = awpcp_get_image_url($image_name, 'large');
			$thumbnail = awpcp_get_image_url($image_name, 'thumbnail');
			$theimages = "<a href=\"" . $large_image . "\"><img $transval src=\"" . $thumbnail . "\"/></a><br/>$dellink $approvelink $primary_image_form";

			$pager1=create_pager($from,$where,$offset,$results,$tpname='');
			$pager2=create_pager($from,$where,$offset,$results,$tpname='');

			$items[]="<td class=\"displayadsicell\">$theimages</td>";

		}

			$opentable="<table class=\"listcatsh\"><tr>";
			$closetable="</tr></table>";

			$theitems=smart_table( $items, intval($results/2), $opentable, $closetable );

			$showcategories="$theitems";
		
		if (!isset($ikey) || empty($ikey) || $ikey == 0)
		{
			$showcategories="<p style=\"padding:20px;\">";
			$showcategories.=__("There were no images found","AWPCP");
			$showcategories.="</p>";
			$pager1='';
			$pager2='';
		}
	}

	$output .= "
		<style>
		table.listcatsh { width: 75%; padding: 0px; border: none;}
		table.listcatsh td { text-align:center;width:10%;font-size: 12px; border: none; background-color: #F4F4F4;
		vertical-align: middle; font-weight: normal; }
		table.listcatsh tr.special td { border-bottom: 1px solid #ff0000;  }
		table.listcatsc { width: 100%; padding: 0px; border: none; border: 1px solid #dddddd;}
		table.listcatsc td { text-align:center;width:10%;border: none;
		vertical-align: middle; padding: 5px; font-weight: normal; }
		table.listcatsc tr.special td { border-bottom: 1px solid #ff0000;  }
		</style>
		";

	if ( '' != $upload_result &&  1 != $upload_result ) 
	    $uploader = $upload_result; 
	else
	    $uploader = '';

	if ( 'Manage1' == $_GET['page'] )
	$uploader .= "
	<h3>Images for ad titled: \"".$ad_title."\"</h3>
	<form action='' method='post' enctype='multipart/form-data' style='margin: 15px 0; border: 1px solid #d3d3d3; padding: 10px; width: 74%; background-color: #eeeeee' >
	    Upload an image for this ad: 
	    <input type='file' name='awpcp_add_file' value=''/>
	    <input class='button' type='submit' name='awpcp_submit_file' value='Add File'/>
	    <input type='hidden' name='awpcp_action' value='add_image'/>
	    ".wp_nonce_field('awpcp_upload_image')."
	</form>
	";



	$output .= "
		$uploader
		$showcategories";


		$output .= "</div>";
		return $output;
		//die;
}


//	END FUNCTION




//
// 	Begin processor actions
//





///////
//	Start process of creating | updating  userside classified page
//////

// wvega: here the pages are created and updated

function awpcp_pages() {
	$pages = array('main-page-name' => array(get_awpcp_option('main-page-name'), '[AWPCP]'));
	return $pages + awpcp_subpages();
}

function awpcp_subpages() {
	$pages = array(
		'show-ads-page-name' => array(get_awpcp_option('show-ads-page-name'), '[AWPCPSHOWAD]'),
		'reply-to-ad-page-name' => array(get_awpcp_option('reply-to-ad-page-name'), '[AWPCPREPLYTOAD]'),
		'edit-ad-page-name' => array(get_awpcp_option('edit-ad-page-name'), '[AWPCPEDITAD]'),
		'place-ad-page-name' => array(get_awpcp_option('place-ad-page-name'), '[AWPCPPLACEAD]'),
		'renew-ad-page-name' => array(get_awpcp_option('renew-ad-page-name'), '[AWPCP-RENEW-AD]'),
		'browse-ads-page-name' => array(get_awpcp_option('browse-ads-page-name'), '[AWPCPBROWSEADS]'),
		'browse-categories-page-name' => array(get_awpcp_option('browse-categories-page-name'), '[AWPCPBROWSECATS]'),
		'search-ads-page-name' => array(get_awpcp_option('search-ads-page-name'), '[AWPCPSEARCHADS]'),
		'payment-thankyou-page-name' => array(get_awpcp_option('payment-thankyou-page-name'), '[AWPCPPAYMENTTHANKYOU]'),
		'payment-cancel-page-name' => array(get_awpcp_option('payment-cancel-page-name'), '[AWPCPCANCELPAYMENT]')
	);

	$pages = apply_filters('awpcp_subpages', $pages);

	return $pages;
}

function awpcp_create_pages($awpcp_page_name, $subpages=true) {
	global $wpdb;
	
	$refname = 'main-page-name';
	$date = date("Y-m-d");

	// create AWPCP main page if it does not exist
	if (!awpcp_find_page($refname)) {
		$awpcp_page = array(
			'post_author' => 1,
			'post_date' => $date,
			'post_date_gmt' => $date,
			'post_content' => '[AWPCPCLASSIFIEDSUI]',
			'post_title' => add_slashes_recursive($awpcp_page_name),
			'post_status' => 'publish',
			'post_name' => sanitize_title($awpcp_page_name),
			'post_modified' => $date,
			'comments_status' => 'closed',
			'post_content_filtered' => '[AWPCPCLASSIFIEDSUI]',
			'post_parent' => 0,
			'post_type' => 'page',
			'menu_order' => 0
		);
		$id = wp_insert_post($awpcp_page);

		$previous = awpcp_get_page_id_by_ref($refname);
		if ($previous === false) {
			$wpdb->insert(AWPCP_TABLE_PAGES, array('page' => $refname, 'id' => $id));
		} else {
			$wpdb->update(AWPCP_TABLE_PAGES, array('page' => $refname, 'id' => $id), 
					  array('page' => $refname));	
		}
	} else {
		$id = awpcp_get_page_id_by_ref($refname);
	}

	// create subpages
	if ($subpages) {
		awpcp_create_subpages($id);
	}
}

function awpcp_create_subpages($awpcp_page_id) {
	$pages = awpcp_subpages();

	foreach ($pages as $key => $page) {
		awpcp_create_subpage($key, $page[0], $page[1], $awpcp_page_id);
	}
	
	do_action('awpcp_create_subpage');
}

/**
 * Creates a subpage of the main AWPCP page.
 * 
 * This functions takes care of checking if the main AWPCP
 * page exists, finding its id and verifying that the new
 * page doesn't exist already. Useful for module plugins.
 */
function awpcp_create_subpage($refname, $name, $shortcode, $awpcp_page_id=null) {
	global $wpdb;

	$id = 0;
	if (!empty($name)) {
		// it is possible that the main AWPCP page does not exist, in that case
		// we should create Subpages without a parent.
		if (is_null($awpcp_page_id) && awpcp_find_page('main-page-name')) {
			$awpcp_page_id = awpcp_get_page_id_by_ref('main-page-name');
		} else if (is_null(($awpcp_page_id))) {
			$awpcp_page_id = '';
		}

		if (!awpcp_find_page($refname)) {
			$id = maketheclassifiedsubpage($name, $awpcp_page_id, $shortcode);
		}
	}

	if ($id > 0) {
		$previous = awpcp_get_page_id_by_ref($refname);
		if ($previous === false) {
			$wpdb->insert(AWPCP_TABLE_PAGES, array('page' => $refname, 'id' => $id));
		} else {
			$wpdb->update(AWPCP_TABLE_PAGES, array('page' => $refname, 'id' => $id), 
					  array('page' => $refname));	
		}
	}

	return $id;
}


function maketheclassifiedsubpage($theawpcppagename,$awpcpwppostpageid,$awpcpshortcodex) {
	global $wpdb,$table_prefix,$wp_rewrite;

	$pdate = date("Y-m-d");

	$awpcpwppostpageid = intval($awpcpwppostpageid);

	// First delete any pages already existing with the title and post name of the new page to be created
	//checkfortotalpageswithawpcpname($theawpcppagename);

	$post_name = sanitize_title($theawpcppagename);
	$theawpcppagename = add_slashes_recursive($theawpcppagename);
	$query="INSERT INTO {$table_prefix}posts SET post_author=1, post_date='$pdate', post_date_gmt='$pdate', post_content='$awpcpshortcodex', post_title='$theawpcppagename', post_excerpt='', post_status='publish', comment_status='closed', post_name='$post_name', to_ping='', pinged='', post_modified='$pdate', post_modified_gmt='$pdate', post_content_filtered='$awpcpshortcodex', post_parent=$awpcpwppostpageid, guid='', post_type='page', menu_order=0";
	$res = awpcp_query($query, __LINE__);
	$newawpcpwppostpageid=mysql_insert_id();
	$guid = get_option('home') . "/?page_id=$newawpcpwppostpageid";

	$query="UPDATE {$table_prefix}posts set guid='$guid' WHERE post_title='$theawpcppagename'";
	$res = awpcp_query($query, __LINE__);

	return $newawpcpwppostpageid;
}

/**
 * This function is never called in any of the AWPCP plugin files,
 * perhaps in one of the modules, but most likely not.
 */
// function updatetheclassifiedsubpage($currentsubpagename,$subpagename,$shortcode)
// {
// 	//debug();
// 	global $wpdb,$table_prefix;

// 	$post_name = sanitize_title($subpagename, $post_ID='');
// 	$currentsubpagename = add_slashes_recursive($currentsubpagename);
// 	$subpagename = add_slashes_recursive($subpagename);
// 	$query="UPDATE {$table_prefix}posts set post_title='$subpagename', post_name='$post_name' WHERE post_title='$currentsubpagename' AND post_content LIKE '%$shortcode%'";
// 	$res = awpcp_query($query, __LINE__);

// }

/**
 * Updates the name of the AWPCP main page.
 */
// function updatetheclassifiedpagename($currentuipagename,$newuipagename) {
// 	//debug();
// 	global $wpdb,$table_prefix, $wp_rewrite;
// 	$tbl_pagename = $wpdb->prefix . "awpcp_pagename";

// 	$post_name = sanitize_title($newuipagename, $post_ID='');
// 	$currentuipagename = add_slashes_recursive($currentuipagename);
// 	$newuipagename = add_slashes_recursive($newuipagename);

// 	//debug($currentuipagename);
// 	//debug($newuipagename);
	
// 	$query="UPDATE {$table_prefix}posts set post_title='$newuipagename', post_name='$post_name' WHERE post_title='$currentuipagename'";
// 	$res = awpcp_query($query, __LINE__);

// 	$query="INSERT INTO ".$tbl_pagename." SET userpagename='$newuipagename'";
// 	$res = awpcp_query($query, __LINE__);
// }



//	Start process of updating|deleting|adding new listing fees


//////////////////
// Handle adding a listing fee plan
/////////////////
// add_action('plugins_loaded', 'awpcp_addfees', 1);
function awpcp_sanitize_fee($data) {
	$sanitized = array();
	$sanitized['adterm_id'] = awpcp_request_param('adterm_id');
	$sanitized['adterm_name'] = awpcp_request_param('adterm_name');
	$sanitized['amount'] = (float) awpcp_request_param('amount', 0.0);
	$sanitized['rec_period'] = (int) awpcp_request_param('rec_period', 30);
	$sanitized['rec_increment'] = awpcp_request_param('rec_increment', 'D');
	$sanitized['imagesallowed'] = (int) awpcp_request_param('imagesallowed', 0);
	$sanitized['characters_allowed'] = (int) awpcp_request_param('characters_allowed', 0);

    if (function_exists('awpcp_featured_ads')) {
	    $sanitized['is_featured_ad_pricing'] = (int) awpcp_featured_ad_parms();
    } else {
	    $sanitized['is_featured_ad_pricing'] = 0;
    }

    if (function_exists('awpcp_price_cats')) {
		$sanitized['fee_cats'] = awpcp_price_cats_fees();
    } else {
		$sanitized['fee_cats'] = '';
    }

    return $sanitized;
}

function awpcp_validate_fee($data, &$errors=array()) {
	if (empty($data['adterm_name']))
		$errors['adterm_name'] = _x('You must provide a name for the Fee plan.', 'add/edit fee', 'AWPCP');

	if ($data['amount'] < 0.0)
		$errors['amount'] = _x('Amount must be greater or equal than zero.', 'add/edit fee', 'AWPCP');

	if ($data['rec_period'] < 0)
		$errors['rec_period'] = _x('Term Duration must be a positive integer or zero.', 'add/edit fee', 'AWPCP');

	if ($data['imagesallowed'] < 0)
		$errors['imagesallowed'] = _x('Images Allowed must be a positive integer or zero.', 'add/edit fee', 'AWPCP');

	if ($data['characters_allowed'] < 0)
		$errors['characters_allowed'] = _x('Characters Allowed must be a positive integer or zero.', 'add/edit fee', 'AWPCP');

	return empty($errors);
}


function awpcp_addfees() {
	global $wpdb;
	global $message;

    if (isset($_REQUEST['addnewfeesetting']) && !empty($_REQUEST['addnewfeesetting'])) {
    	$data = awpcp_sanitize_fee($_REQUEST);
    	$errors = array();

    	if (awpcp_validate_fee($data, $errors)) {
    		extract($data);

			if (function_exists('awpcp_price_cats')) {
				$query = "INSERT INTO " . AWPCP_TABLE_ADFEES . " SET adterm_name='$adterm_name',amount='$amount',recurring=1,rec_period='$rec_period',rec_increment='$rec_increment',imagesallowed='$imagesallowed', is_featured_ad_pricing=$is_featured_ad_pricing, categories='$fee_cats', characters_allowed=$characters_allowed";
			} else {
				$query = "INSERT INTO " . AWPCP_TABLE_ADFEES . " SET adterm_name='$adterm_name',amount='$amount',recurring=1,rec_period='$rec_period',rec_increment='$rec_increment',imagesallowed='$imagesallowed', is_featured_ad_pricing=$is_featured_ad_pricing, characters_allowed=$characters_allowed";
			}
			$res = awpcp_query($query, __LINE__);

			$message = awpcp_print_message(__('The item has been added.', 'add fee', 'AWPCP'));
		} else {
			$messages = array();
			foreach ($errors as $error) {
				$messages[] = awpcp_print_message($error, array('error'));
			}
			$message = join('', $messages);
		}
    }
}
//////////////////
// Handle updating of a listing fee plan
/////////////////

// add_action('plugins_loaded', 'awpcp_savefees',1);
function awpcp_savefees() {
	global $wpdb;
	global $message;

    if (isset($_REQUEST['savefeesetting']) && !empty($_REQUEST['savefeesetting'])) {
    	$data = awpcp_sanitize_fee($_REQUEST);
    	$errors = array();

    	if (awpcp_validate_fee($data, $errors)) {
    		extract($data);

		    if (function_exists('awpcp_price_cats')) {
				$query = "UPDATE " . AWPCP_TABLE_ADFEES . " SET adterm_name='$adterm_name',amount='$amount',recurring=1,rec_period='$rec_period',rec_increment='$rec_increment', imagesallowed='$imagesallowed', is_featured_ad_pricing=$is_featured_ad_pricing, characters_allowed=$characters_allowed, categories='$fee_cats' WHERE adterm_id='$adterm_id'";
		    } else {
				$query = "UPDATE " . AWPCP_TABLE_ADFEES . " SET adterm_name='$adterm_name',amount='$amount',recurring=1,rec_period='$rec_period',rec_increment='$rec_increment', imagesallowed='$imagesallowed', is_featured_ad_pricing=$is_featured_ad_pricing, characters_allowed=$characters_allowed WHERE adterm_id='$adterm_id'";
		    }
			$res = awpcp_query($query, __LINE__);

			$message = awpcp_print_message(__('The item has been updated.', 'add fee', 'AWPCP'));
		} else {
			$messages = array();
			foreach ($errors as $error) {
				$messages[] = awpcp_print_message($error, array('error'));
			}
			$message = join('', $messages);
		}
    }
}

/**
 * A function created to wrap code intended to handle
 * Admin Panel requests.
 *
 * The body of this function was in the content of awpcp.php
 * being executed every time the plugin file was read.
 */
// add_action('plugins_loaded', 'awpcp_handle_admin_requests');
function awpcp_handle_admin_requests() {
	global $wpdb;
	global $message;
		
	//////////////////
	// Handle deleting of a listing fee plan
	/////////////////

	if (isset($_REQUEST['deletefeesetting']) && !empty($_REQUEST['deletefeesetting'])) {
		$tbl_ad_fees = $wpdb->prefix . "awpcp_adfees";
		$awpcpfeeplanoptionitem='';
		$adterm_id='';

		if (isset($_REQUEST['adterm_id']) && !empty($_REQUEST['adterm_id'])) {
			$adterm_id = clean_field($_REQUEST['adterm_id']);
		}

		if (empty($adterm_id)) {
			$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$message.=__("No plan ID was provided therefore no action has been taken","AWPCP");
			$message.="!</div>";

		// First make check if there are ads that are saved under this term
		} elseif (adtermidinuse($adterm_id)) {

			$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$message.=__("The plan could not be deleted because there are active ads in the system that are associated with the plan ID. You need to switch the ads to a new plan ID before you can delete the plan.","AWPCP");
			$message.="</div>";

			$awpcpfeechangeadstonewidform="<div style=\"border:5px solid#ff0000;padding:5px;\"><form method=\"post\" id=\"awpcp_launch\">";
			$awpcpfeechangeadstonewidform.="<p>";
			$awpcpfeechangeadstonewidform.=__("Change ads associated with plan ID $adterm_id to this plan ID","AWPCP");
			$awpcpfeechangeadstonewidform.="<br/>";
			$awpcpfeechangeadstonewidform.="<select name=\"awpcpnewplanid\"/>";


			$awpcpfeeplans=$wpdb->get_results("select adterm_id as theadterm_ID, adterm_name as theadterm_name from ".$tbl_ad_fees." WHERE adterm_id != '$adterm_id'");

			foreach($awpcpfeeplans as $awpcpfeeplan) {
				$awpcpfeeplanoptionitem .= "<option value='$awpcpfeeplan->theadterm_ID'>$awpcpfeeplan->theadterm_name</option>";
			}

			$awpcpfeechangeadstonewidform.="$awpcpfeeplanoptionitem";

			$awpcpfeechangeadstonewidform.="</select>";
			$awpcpfeechangeadstonewidform.="<input name=\"adterm_id\" type=\"hidden\" value=\"$adterm_id\" /></p>";
			$awpcpfeechangeadstonewidform.="<input class=\"button\" type=\"submit\" name=\"changeadstonewfeesetting\" value=\"";
			$awpcpfeechangeadstonewidform.=__("Submit","AWPCP");
			$awpcpfeechangeadstonewidform.="\" />";
			$awpcpfeechangeadstonewidform.="</form></div>";

			$message.="<p>$awpcpfeechangeadstonewidform</p>";

		} else {
			$query="DELETE FROM  ".$tbl_ad_fees." WHERE adterm_id='$adterm_id'";
			$res = awpcp_query($query, __LINE__);

			$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$message.=__("The data has been deleted","AWPCP");
			$message.="!</div>";

		}
	}


	if (isset($_REQUEST['changeadstonewfeesetting']) && !empty($_REQUEST['changeadstonewfeesetting']))
	{
		$tbl_ads = $wpdb->prefix . "awpcp_ads";
		$adterm_id='';
		$awpcpnewplanid='';

		if (isset($_REQUEST['adterm_id']) && !empty($_REQUEST['adterm_id']))
		{
			$adterm_id=clean_field($_REQUEST['adterm_id']);
		}
		if (isset($_REQUEST['awpcpnewplanid']) && !empty($_REQUEST['awpcpnewplanid']))
		{
			$awpcpnewplanid=clean_field($_REQUEST['awpcpnewplanid']);
		}


		if (empty($adterm_id))
		{

			$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$message.=__("No plan ID was provided therefore no action has been taken","AWPCP");
			$message.="!</div>";
		}
		else
		{
			$query="UPDATE ".$tbl_ads." SET adterm_id='$awpcpnewplanid' WHERE adterm_id='$adterm_id'";
			$res = awpcp_query($query, __LINE__);

			$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
			$message.=__("All ads with ID $adterm_id have been associated with plan id $awpcpnewplanid. You can now delete plan ID $adterm_id","AWPCP");
			$message.="!</div>";
		}
	}



	//	End process

	//	Start process of adding | editing ad categories


	if (isset($_REQUEST['createeditadcategory']) && !empty($_REQUEST['createeditadcategory']))
	{
		$tbl_ad_categories = $wpdb->prefix . "awpcp_categories";
		$tbl_ads = $wpdb->prefix . "awpcp_ads";

		$category_id=clean_field($_REQUEST['category_id']);


		if (isset($_REQUEST['$movetocat']) && !empty($_REQUEST['$movetocat']))
		{
			$movetocat=clean_field($_REQUEST['movetocat']);
		}
		if (isset($_REQUEST['$deletetheads']) && !empty($_REQUEST['$deletetheads']))
		{
			$deletetheads=$_REQUEST['deletetheads'];
		}

		$aeaction=clean_field($_REQUEST['aeaction']);

		if ($aeaction == 'newcategory')
		{
			$category_name=stripslashes(clean_field($_REQUEST['category_name']));
			$category_name=addslashes(clean_field($_REQUEST['category_name']));
			$category_parent_id=clean_field($_REQUEST['category_parent_id']);
			$category_order=clean_field($_REQUEST['category_order']);
			//Ensure we have something like a number:
			$category_order = ('' != $category_order ? (is_numeric($category_order) ? $category_order : 0) : 0);
			$query="INSERT INTO ".$tbl_ad_categories." SET category_name='".$category_name."',category_parent_id='".$category_parent_id."'".",category_order=".$category_order;
			awpcp_query($query, __LINE__);
			$themessagetoprint=__("The new category has been successfully added","AWPCP");
		}
		elseif ($aeaction == 'delete')
		{
			if (isset($_REQUEST['category_name']) && !empty($_REQUEST['category_name']))
			{
				$category_name=clean_field($_REQUEST['category_name']);
			}
			if (isset($_REQUEST['category_parent_id']) && !empty($_REQUEST['category_parent_id']))
			{
				$category_parent_id=clean_field($_REQUEST['category_parent_id']);
			}

			// Make sure this is not the default category. If it is the default category alert that the default category can only be renamed not deleted
			if ($category_id == 1)
			{
				$themessagetoprint=__("Sorry but you cannot delete the default category. The default category can only be renamed","AWPCP");
			} else {
				//Proceed with the delete instructions

				// Move any ads that the category contains if move-to category value is set and does not equal zero

				if ( isset($movetocat) && !empty($movetocat) && ($movetocat != 0) )
				{

					$movetocatparent=get_cat_parent_ID($movetocat);

					$query="UPDATE ".$tbl_ads." SET ad_category_id='$movetocat' ad_category_parent_id='$movetocatparent' WHERE ad_category_id='$category_id'";
					awpcp_query($query, __LINE__);

					// Must also relocate ads where the main category was a child of the category being deleted
					$query="UPDATE ".$tbl_ads." SET ad_category_parent_id='$movetocat' WHERE ad_category_parent_id='$category_id'";
					awpcp_query($query, __LINE__);

					// Must also relocate any children categories to the the move-to-cat
					$query="UPDATE ".$tbl_ad_categories." SET category_parent_id='$movetocat' WHERE category_parent_id='$category_id'";
					awpcp_query($query, __LINE__);
				}


				// Else if the move-to value is zero move the ads to the parent category if category is a child or the default category if
				// category is not a child

				elseif ( !isset($movetocat) || empty($movetocat) || ($movetocat == 0) )
				{

					// If the category has a parent move the ads to the parent otherwise move the ads to the default

					if ( category_is_child($category_id) )
					{

						$movetocat=get_cat_parent_ID($category_id);
					}
					else
					{
						$movetocat=1;
					}

					$movetocatparent=get_cat_parent_ID($movetocat);

					// Adjust any ads transferred from the main category
					$query="UPDATE ".$tbl_ads." SET ad_category_id='$movetocat', ad_category_parent_id='$movetocatparent' WHERE ad_category_id='$category_id'";
					awpcp_query($query, __LINE__);

					// Must also relocate any children categories to the the move-to-cat
					$query="UPDATE ".$tbl_ad_categories." SET category_parent_id='$movetocat' WHERE category_parent_id='$category_id'";
					awpcp_query($query, __LINE__);

					// Adjust  any ads transferred from children categories
					$query="UPDATE ".$tbl_ads." SET ad_category_parent_id='$movetocat' WHERE ad_category_parent_id='$category_id'";
					$res = awpcp_query($query, __LINE__);
				}

				$query="DELETE FROM  ".$tbl_ad_categories." WHERE category_id='$category_id'";
				awpcp_query($query, __LINE__);

				$themessagetoprint=__("The category has been deleted","AWPCP");
			}
		}
		elseif ($aeaction == 'edit')
		{

			if (isset($_REQUEST['category_name']) && !empty($_REQUEST['category_name']))
			{
				$category_name=clean_field($_REQUEST['category_name']);
			}
			if (isset($_REQUEST['category_parent_id']) && !empty($_REQUEST['category_parent_id']))
			{
				$category_parent_id=clean_field($_REQUEST['category_parent_id']);
			}
			$category_order=clean_field($_REQUEST['category_order']);
			//Ensure we have something like a number:
			$category_order = ('' != $category_order ? (is_numeric($category_order) ? $category_order : 0) : 0);
			
			$query="UPDATE ".$tbl_ad_categories." SET category_name='$category_name',category_parent_id='$category_parent_id',category_order='$category_order' WHERE category_id='$category_id'";
			awpcp_query($query, __LINE__);

			$query="UPDATE ".$tbl_ads." SET ad_category_parent_id='$category_parent_id' WHERE ad_category_id='$category_id'";
			awpcp_query($query, __LINE__);

			$themessagetoprint=__("Your category changes have been saved.","AWPCP");
		}
		else
		{
			$themessagetoprint=__("No changes made to categories.","AWPCP");
		}

		$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$themessagetoprint</div>";
		$clearform=1;
	}

	// Move multiple categories

	if ( isset($_REQUEST['movemultiplecategories']) && !empty($_REQUEST['movemultiplecategories']) )
	{
		$tbl_ad_categories = $wpdb->prefix . "awpcp_categories";
		$tbl_ads = $wpdb->prefix . "awpcp_ads";

		// First get the array of categories to be deleted
		$categoriestomove=clean_field($_REQUEST['category_to_delete_or_move']);

		// Next get the value for where the admin wants to move the ads
		if ( isset($_REQUEST['moveadstocategory']) && !empty($_REQUEST['moveadstocategory'])  && ($_REQUEST['moveadstocategory'] != 0) )
		{
			$moveadstocategory=clean_field($_REQUEST['moveadstocategory']);

			// Next loop through the categories and move them to the new category

			foreach($categoriestomove as $cattomove)
			{

				if ($cattomove != $moveadstocategory)
				{

					// First update all the ads in the category to take on the new parent ID
					$query="UPDATE ".$tbl_ads." SET ad_category_parent_id='$moveadstocategory' WHERE ad_category_id='$cattomove'";
					awpcp_query($query, __LINE__);

					$query="UPDATE ".$tbl_ad_categories." SET category_parent_id='$moveadstocategory' WHERE category_id='$cattomove'";
					awpcp_query($query, __LINE__);
				}

			}

			$themessagetoprint=__("With the exception of any category that was being moved to itself, the categories have been moved","AWPCP");
		}
		else
		{
			$themessagetoprint=__("The categories have not been moved because you did not indicate where you want the categories to be moved to","AWPCP");
		}

		$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$themessagetoprint</div>";
	}

	// Delete multiple categories
	if ( isset($_REQUEST['deletemultiplecategories']) && !empty($_REQUEST['deletemultiplecategories']) )
	{
		$tbl_ad_categories = $wpdb->prefix . "awpcp_categories";
		$tbl_ads = $wpdb->prefix . "awpcp_ads";

		// First get the array of categories to be deleted
		$categoriestodelete=clean_field($_REQUEST['category_to_delete_or_move']);

		// Next get the value of move/delete ads
		if ( isset($_REQUEST['movedeleteads']) && !empty($_REQUEST['movedeleteads']) )
		{
			$movedeleteads=clean_field($_REQUEST['movedeleteads']);
		}
		else
		{
			$movedeleteads=1;
		}

		// Next get the value for where the admin wants to move the ads
		if ( isset($_REQUEST['moveadstocategory']) && !empty($_REQUEST['moveadstocategory'])  && ($_REQUEST['moveadstocategory'] != 0) )
		{
			$moveadstocategory=clean_field($_REQUEST['moveadstocategory']);
		}
		else
		{
			$moveadstocategory=1;
		}

		// Next make sure there is a default category with an ID of 1 because any ads that exist in the
		// categories will need to be moved to a default category if admin has checked move ads but
		// has not selected a move to category

		if ( ($moveadstocategory == 1) && (!(defaultcatexists($defid=1))) )
		{
			createdefaultcategory($idtomake=1,$titletocallit='Untitled');
		}

		// Next loop through the categories and move all their ads
		foreach($categoriestodelete as $cattodel)
		{
			// Make sure this is not the default category which cannot be deleted
			if ($cattodel != 1)
			{
				// If admin has instructed moving ads move the ads
				if ($movedeleteads == 1)
				{
					// Now move the ads if any
					$movetocat=$moveadstocategory;
					$movetocatparent=get_cat_parent_ID($movetocat);

					// Move the ads in the category main
					$query="UPDATE ".$tbl_ads." SET ad_category_id='$movetocat',ad_category_parent_id='$movetocatparent' WHERE ad_category_id='$cattodel'";
					awpcp_query($query, __LINE__);

					// Must also relocate ads where the main category was a child of the category being deleted
					$query="UPDATE ".$tbl_ads." SET ad_category_parent_id='$movetocat' WHERE ad_category_parent_id='$cattodel'";
					awpcp_query($query, __LINE__);

					// Must also relocate any children categories that do not exist in the categories to delete loop to the the move-to-cat
					$query="UPDATE ".$tbl_ad_categories." SET category_parent_id='$movetocat' WHERE category_parent_id='$cattodel' AND category_id NOT IN (".implode(',',$categoriestodelete).")";

					awpcp_query($query, __LINE__);
				}
				elseif ($movedeleteads == 2)
				{

					$movetocat=$moveadstocategory;

					// If the category has children move the ads in the child categories to the default category

					if ( category_has_children($cattodel) )
					{
						//  Relocate the ads ads in any children categories of the category being deleted

						$query="UPDATE ".$tbl_ads." SET ad_category_parent_id='$movetocat' WHERE ad_category_parent_id='$cattodel'";
						awpcp_query($query, __LINE__);

						// Relocate any children categories that exist under the category being deleted
						$query="UPDATE ".$tbl_ad_categories." SET category_parent_id='$movetocat' WHERE category_parent_id='$cattodel'";
						awpcp_query($query, __LINE__);
					}


					// Now delete the ads because the admin has checked Delete ads if any
					massdeleteadsfromcategory($cattodel);
				}

				// Now delete the categories
				$query="DELETE FROM  ".$tbl_ad_categories." WHERE category_id='$cattodel'";
				awpcp_query($query, __LINE__);

				$themessagetoprint=__("The categories have been deleted","AWPCP");
			}

		}

		$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$themessagetoprint</div>";
	}


	//	End process

	//	Start Process of deleting multiple ads


	if (isset($_REQUEST['deletemultipleads']) && !empty($_REQUEST['deletemultipleads']))
	{
		$tbl_ads = $wpdb->prefix . "awpcp_ads";
		$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";

		if (isset($_REQUEST['awpcp_ads_to_action']) && !empty($_REQUEST['awpcp_ads_to_action']))
		{
			$theawpcparrayofadstodelete=$_REQUEST['awpcp_ads_to_action'];
		}

		if (!isset($theawpcparrayofadstodelete) || empty($theawpcparrayofadstodelete) )
		{
			$themessagetoprint=__("No ads have been selected, you must select one or more ads first.","AWPCP");
		}
		else
		{
			foreach ($theawpcparrayofadstodelete as $theawpcpadtodelete)
			{
				$fordeletionid[]=$theawpcpadtodelete;
			}

			$ads = AWPCP_Ad::find(sprintf('WHERE ad_id IN (%s)', join("','", $fordeletionid)));
			foreach ($ads as $ad) {
				$ad->delete();
			}

			// $listofadstodelete=join("','",$fordeletionid);

			// // Delete the ad images
			// $query="SELECT image_name FROM ".$tbl_ad_photos." WHERE ad_id IN ('$listofadstodelete')";
			// $res = awpcp_query($query, __LINE__);

			// for ($i=0;$i<mysql_num_rows($res);$i++)
			// {
			// 	$photo=mysql_result($res,$i,0);

			// 	if (file_exists(AWPCPUPLOADDIR.'/'.$photo))
			// 	{
			// 		@unlink(AWPCPUPLOADDIR.'/'.$photo);
			// 	}
			// 	if (file_exists(AWPCPTHUMBSUPLOADDIR.'/'.$photo))
			// 	{
			// 		@unlink(AWPCPTHUMBSUPLOADDIR.'/'.$photo);
			// 	}
			// }

			// $query="DELETE FROM ".$tbl_ad_photos." WHERE ad_id IN ('$listofadstodelete')";
			// awpcp_query($query, __LINE__);

			// // Delete the ads
			// $query="DELETE FROM ".$tbl_ads." WHERE ad_id IN ('$listofadstodelete')";
			// awpcp_query($query, __LINE__);

			$themessagetoprint=__("The ads have been deleted","AWPCP");

		}

		$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$themessagetoprint</div>";
	}


	//	End Process of deleting multiple ads


	//	Start Process of spamming multiple ads


	if (isset($_REQUEST['spammultipleads']) && !empty($_REQUEST['spammultipleads']))
	{
		$tbl_ads = $wpdb->prefix . "awpcp_ads";
		if (isset($_REQUEST['awpcp_ads_to_action']) && !empty($_REQUEST['awpcp_ads_to_action']))
		{
			$theawpcparrayofadstospam=$_REQUEST['awpcp_ads_to_action'];
		}
		if (!isset($theawpcparrayofadstospam) || empty($theawpcparrayofadstospam) )
		{
			$themessagetoprint=__("No ads have been selected, you must select one or more ads first.","AWPCP");
		}
		else
		{
			foreach ($theawpcparrayofadstospam as $theawpcpadtospam) {
				$forspamid[]=$theawpcpadtospam;
				awpcp_submit_spam($theawpcpadtospam);
			}

			$ads = AWPCP_Ad::find(sprintf('WHERE ad_id IN (%s)', join("','", $forspamid)));
			foreach ($ads as $ad) {
				$ad->delete();
			}
			
			// $listofadstospam=join("','",$forspamid);
			// // Delete the ads
			// $query="DELETE FROM ".$tbl_ads." WHERE ad_id IN ('$listofadstospam')";
			// awpcp_query($query, __LINE__);
			
			$themessagetoprint=__("The selected Ads have been marked as SPAM and removed.","AWPCP");
		}

		$message = "<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">$themessagetoprint</div>";
	}
}
//	End Process of spamming multiple ads



/**
 * Calls awpcp-admin-sidebar filter to output Admin panel sidebar.
 *
 * To remove Admin panel sidebar remove the mentioned filter on init.
 */
function awpcp_admin_sidebar($float='') {
	$html = apply_filters('awpcp-admin-sidebar', '', $float);
	return $html;
}

function awpcp_admin_sidebar_output($html, $float) {
	global $hasregionsmodule, $hascaticonsmodule, $hasgooglecheckoutmodule;
	global $hasextrafieldsmodule, $hasrssmodule, $hasfeaturedadsmodule;
	global $haspoweredbyremovalmodule;

	$apath = get_option('siteurl') . '/wp-admin/images';
	if ('' == $float) $float = 'float:right !important';
	$url = AWPCP_URL;
	$out = <<< AWPCP
<style>
.li_link { margin-left: 10px }
.inside { padding: 5px 10px !important; }
.apostboxes { 
	background-color:#FFFFFF;
	border-color:#DFDFDF;
	-moz-border-radius:6px 6px 6px 6px;
	border-style:solid;
	border-width:1px;
	line-height:1;
	margin-bottom:20px;
	/*min-width:255px;*/
	position:relative;
	width:99.5%;
}
.apostboxes h3 { 
	background:url("$apath/gray-grad.png") repeat-x scroll left top #DFDFDF;
	text-shadow:0 1px 0 #FFFFFF;
}
</style>
<div class="postbox-container1" style="padding-right: 0.5%; $float; width: 20%; ">
    <div class="metabox-holder">	
	<div class="meta-box-sortables">

	    <div class="apostboxes">
		    <h3 class="hndle1"><span>Like this plugin?</span></h3>
		    <div class="inside">
		    <p>Why not do any or all of the following:</p>
			    <ul>
			    <li class="li_link"><a href="http://wordpress.org/extend/plugins/another-wordpress-classifieds-plugin/">Give it a good rating on WordPress.org.</a></li>
			    <li class="li_link"><a href="http://wordpress.org/extend/plugins/another-wordpress-classifieds-plugin/">Let other people know that it works with your WordPress setup.</a></li>
			    <li class="li_link"><a href="http://www.awpcp.com/premium-modules/?ref=panel">Buy a Premium Module</a></li>
			    </ul>
		    </div>
	    </div>

	    <div class="apostboxes" style="border-color:#0EAD00; border-width:3px;">
		    <h3 class="hndle1" style="color:#145200;"><span class="red"><strong>Get a Premium Module!</strong></span></h3>
		    <div class="inside" style="background-color:#FFFFCF">
			<ul>
			<li  class="li_link"><img style="align:left" src="$url/images/new.gif"/><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/coupon-module/?ref=panel" target="_blank">Coupon/Discount Module</a></li>
			<li  class="li_link"><img style="align:left" src="$url/images/new.gif"/><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/subscriptions-module/?ref=panel" target="_blank">Subscription Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/fee-per-category-module/?ref=panel" target="_blank">Fee Per Category Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/featured-ads-module/?ref=panel" target="_blank">Featured Ads Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/extra-fields-module/?ref=panel" target="_blank">Extra 
Fields Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/category-icons-module/?ref=panel" 
target="_blank">Category Icons Premium Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/regions-control-module/?ref=panel" target="_blank">Regions 
Control Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/google-checkout-module/?ref=panel" target="_blank">Google 
Checkout Payment Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/premium-modules/rss-module/?ref=panel" target="_blank">RSS 
Module</a></li>
			<li  class="li_link"><a style="color:#145200;" href="http://www.awpcp.com/donate/?ref=panel" 
target="_blank">Donate to Support AWPCP</a></li>
			</ul>
		    </div>
	    </div>

	    <div class="apostboxes">
		    <h3 class="hndle1"><span>Found a bug? &nbsp; Need Support?</span></h3>
		    <div class="inside">
		    	<ul>
		    		<li>Browse the <a href="http://www.awpcp.com/quick-start-guide" target="_blank">Quick Start Guide</a>.</li>
		    		<li>Read the full <a href="http://awpcp.com/docs" target="_blank">Documentation</a>.</li>
		    		<li>Report bugs or get more help: <a href="http://forum.awpcp.com/" target="_blank">visit the forums!</a>.</li>
		    	</ul>			
		    </div>
	    </div>

	</div>
    </div>
AWPCP;

	$page = awpcp_request_param('page', '');
	if (get_awpcp_option('showlatestawpcpnews') && strcmp($page, 'awpcp.php') == 0) {
		$out .= "<p><a href=\"http://www.awpcp.com/forum\">";
		$out .= __("Plugin Support Site","AWPCP");
		$out .= "</a></p>";
		$out .= "<p><b>";
		$out .= __("Premium Modules","AWPCP"); 
		$out .= "</b></p><em>";
		$out .= __("Installed","AWPCP");
		$out .= "</em><br/><ul>";
		if ( ($hasregionsmodule != 1) && ($hascaticonsmodule != 1) && ($hasgooglecheckoutmodule != 1) 
		    && ($hasextrafieldsmodule != 1) && ($hasrssmodule != 1) && ($hasfeaturedadsmodule != 1) && 
		    ( !function_exists('awpcp_price_cats') ) )
		{
			$out .= "<li>"; $out .= __("No premium modules installed","AWPCP"); $out .= "</li>";
		} else {
			if ( ($hasregionsmodule == 1) ) {
				$out .= "<li>"; $out .= __("Regions Control Module","AWPCP"); $out .= "</li>";
			}

			if ( ($hascaticonsmodule == 1) ) {
				$out .= "<li>"; $out .= __("Category Icons Module","AWPCP"); $out .= "</li>";
			}

			if ( ($hasgooglecheckoutmodule == 1) ) {
				$out .= "<li>"; $out .= __("Google Checkout Module","AWPCP"); $out .= "</li>";
			}

			if ( ($hasextrafieldsmodule == 1) ) {
				$out .= "<li>"; $out .= __("Extra Fields Module","AWPCP"); $out .= "</li>";
			}

			if ( ($hasrssmodule == 1) ) {
				$out .= "<li>"; $out .= __("RSS Module","AWPCP"); $out .= "</li>";
			}

			if ( ($hasfeaturedadsmodule == 1) ) {
				$out .= "<li>"; $out .= __("Featured Ads Module","AWPCP"); $out .= "</li>";
			}

			if ( function_exists('awpcp_price_cats') ) { 
				$out .= "<li>"; $out .= __("Fee per Category Module","AWPCP"); $out .= "</li>";

			}
		}

		$out .= "</ul><em>"; $out .= __("Uninstalled","AWPCP"); $out .= "</em><ul>";

		if ( ($hasregionsmodule != 1) ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/regions-control-module\">"; $out .= __("Regions Control Module","AWPCP"); $out .= "</a></li>";
		}

		if ( ($hascaticonsmodule != 1) ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/category-icons-module/\">"; $out .= __("Category Icons Module","AWPCP"); $out .= "</a></li>";
		}

		if ( ($hasgooglecheckoutmodule != 1) ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/google-checkout-module/\">"; $out .= __("Google Checkout Module","AWPCP"); $out .= "</a></li>";
		}

		if ( ($hasextrafieldsmodule != 1) ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/extra-fields-module/\">"; $out .= __("Extra Fields Module","AWPCP"); $out .= "</a></li>";
		}

		if ( ($hasrssmodule != 1) ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/rss-module/\">"; $out .= __("RSS Module","AWPCP"); $out .= "</a></li>";
		}

		if ( ($hasfeaturedadsmodule != 1) ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/featured-ads-module/\">"; $out .= __("Featured Ads Module","AWPCP"); $out .= "</a></li>";
		}

		if ( !function_exists('awpcp_price_cats') ) {
			$out .= "<li><a href=\"http://www.awpcp.com/premium-modules/fee-per-category-module/\">"; $out .= __("Fee per Category Module","AWPCP"); $out .= "</a></li>";
		}

		if ( ($hasregionsmodule == 1) && ($hascaticonsmodule == 1) && ($hasgooglecheckoutmodule == 1) && ($hasextrafieldsmodule == 1) && ($hasrssmodule == 1) && ($hasfeaturedadsmodule == 1) && function_exists('awpcp_price_cats'))
		{
			$out .= "<li><b>"; $out .= __("All premium modules installed!","AWPCP"); $out .= "</b></li>";
		}

		$out .= "</ul><p><b>"; 
		$out .= __("Other Modules","AWPCP"); 
		$out .= "</b></p><em>"; 
		$out .= __("Installed","AWPCP"); 
		$out .= "</em><br/><ul>";

		if ( ($haspoweredbyremovalmodule != 1) ) {
			$out .= "<li>"; 
			$out .= __("No [Other] modules installed", "AWPCP");
			$out .= "</li>";
		} else {
			if ( ($haspoweredbyremovalmodule == 1) ) {
				// $out .= "<li>"; 
				// $out .= __("Powered By Link Removal Module","AWPCP"); 
				// $out .= "</li>";
			}
		}

		$out .= "</ul><em>"; $out .= __("Uninstalled","AWPCP"); $out .= "</em><ul>";

		if ( ($haspoweredbyremovalmodule != 1) ) {
			// $out .= "<li><a href=\"http://www.awpcp.com/premium-modules/powered-by-link-removal-module/\">"; 
			// $out .= __("Powered By Link Removal Module","AWPCP"); 
			// $out .= "</a></li>";
		} else {
			$out .= __("All [Other] modules installed","AWPCP");
		}
		$out .= "</ul>";
	}

	$out .= '</div>';

	return $out;
}
