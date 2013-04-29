<?php

require_once(AWPCP_DIR . "frontend/meta.php");
require_once(AWPCP_DIR . "frontend/shortcode-raw.php");

require_once(AWPCP_DIR . 'frontend/page-place-ad.php');
require_once(AWPCP_DIR . 'frontend/page-show-ad.php');
require_once(AWPCP_DIR . 'frontend/page-search-ads.php');
require_once(AWPCP_DIR . 'frontend/page-browse-ads.php');
require_once(AWPCP_DIR . 'frontend/page-browse-cat.php');
require_once(AWPCP_DIR . 'frontend/page-payment-thank-you.php');
require_once(AWPCP_DIR . 'frontend/page-cancel-payment.php');


class AWPCP_Pages {
	public $search_ads = null;

	public function AWPCP_Pages() {
		$this->meta = AWPCP_Meta::instance();

		$this->place_ad = new AWPCP_Place_Ad_Page();
		$this->show_ad = new AWPCP_Show_Ad_Page();
		$this->search_ads = new AWPCP_Search_Ads_Page();
		$this->browse_ads = new AWPCP_BrowseAdsPage();
		$this->browse_cat = new AWPCP_BrowseCatPage();
		$this->payment_thank_you = new AWPCP_Payment_ThankYou_Page();
		$this->cancel_payment = new AWPCP_Cancel_Payment_Page();

		// fix for theme conflict with ThemeForest themes.
		new AWPCP_RawShortcode();

		add_action('init', array($this, 'init'));
	}

	public function init() {
		add_shortcode('AWPCPPLACEAD', array($this->place_ad, 'dispatch'));
		add_shortcode('AWPCPSEARCHADS', array($this->search_ads, 'dispatch'));
		add_shortcode('AWPCPPAYMENTTHANKYOU', array($this->payment_thank_you, 'dispatch'));
		add_shortcode('AWPCPCANCELPAYMENT', array($this->cancel_payment, 'dispatch'));
		add_shortcode('AWPCPBROWSEADS', array($this->browse_ads, 'dispatch'));
		add_shortcode('AWPCPBROWSECATS', array($this->browse_cat, 'dispatch'));

		add_shortcode('AWPCPCLASSIFIEDSUI', 'awpcpui_homescreen');
		add_shortcode('AWPCPSHOWAD','showad');
		// TODO: write an update script to update this shortcode
		add_shortcode('AWPCP-RENEW-AD','awpcp_renew_ad_page');
		add_shortcode('AWPCPEDITAD','awpcpui_editformscreen');
		add_shortcode('AWPCPREPLYTOAD','awpcpui_contactformscreen');

		add_shortcode('AWPCPSHOWCAT', array($this->browse_cat, 'shortcode'));

		do_action('awpcp_setup_shortcode');
	}
}



// Set Home Screen

function awpcpui_homescreen() {
	//debug();
	global $classicontent;
	if (!isset($awpcppagename) || empty($awpcppagename)) {
		$awpcppage=get_currentpagename();
		$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	}
	if (!isset($classicontent) || empty($classicontent)) {
		$classicontent=awpcpui_process($awpcppagename);	
	}
	return $classicontent;
}

/**
 * Handle Place Ad page content.
 */
function awpcpui_postformscreen() {
	//debug();
	global $adpostform_content;
	if (!isset($adpostform_content) || empty($adpostform_content)) {
		$adpostform_content=awpcpui_process_placead();
	}
	return $adpostform_content;
}

// Set Edit Form Screen

function awpcpui_editformscreen()
{
	global $editpostform_content;
	if (!isset($editpostform_content) || empty($editpostform_content)){$editpostform_content=awpcpui_process_editad();}
	return $editpostform_content;
}

// Set Contact Form Screen Configure

function awpcpui_contactformscreen() {
	global $contactpostform_content;
	if (!isset($contactpostform_content) || empty($contactpostform_content)){
		$contactpostform_content = awpcpui_process_contact();
	}
	return $contactpostform_content;
}

// Set Payment Thank you screen Configure

// function awpcpui_paymentthankyouscreen()
// {
// 	//debug();
// 	global $paymentthankyou_content;
// 	if (!isset($paymentthankyou_content) || empty($paymentthankyou_content)){$paymentthankyou_content=paymentthankyou();}
// 	return $paymentthankyou_content;
// }


function awpcpui_process($awpcppagename) {
	global $hasrssmodule, $hasregionsmodule, $awpcp_plugin_url;

	$output = '';
	$action = '';

	$awpcppage = get_currentpagename();
	if (!isset($awpcppagename) || empty($awpcppagename)) {
		$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	}

	if (isset($_REQUEST['a']) && !empty($_REQUEST['a'])) {
		$action=$_REQUEST['a'];
	}

	// TODO: this kind of requests should be handled in Region Control's own code
	if (($action == 'setregion') || '' != get_query_var('regionid')) {
		if ($hasregionsmodule ==  1) {
			if (isset($_REQUEST['regionid']) && !empty($_REQUEST['regionid'])) {
				$region_id = $_REQUEST['regionid'];
			} else {
				$region_id = get_query_var('regionid');
			}

			// double check module existence :\
			if (method_exists('AWPCP_Region_Control_Module', 'set_location')) {
				$region = awpcp_region_control_get_entry(array('id' => $region_id));
				$regions = AWPCP_Region_Control_Module::instance();
				$regions->set_location($region);
			}
		}

	} elseif ($action == 'unsetregion') {
		if (isset($_SESSION['theactiveregionid'])) {
			unset($_SESSION['theactiveregionid']);
		}
	}


	$categoriesviewpagename = sanitize_title(get_awpcp_option('view-categories-page-name'));
	$browsestat='';

	$browsestat = get_query_var('cid');
	$layout = get_query_var('layout');

	$isadmin=checkifisadmin();

	$isclassifiedpage = checkifclassifiedpage($awpcppage);
	if (($isclassifiedpage == false) && ($isadmin == 1)) {
		$output .= __("Hi admin, you need to go to your dashboard and setup your classifieds.","AWPCP");

	} elseif (($isclassifiedpage == false) && ($isadmin != 1)) {
		$output .= __("You currently have no classifieds","AWPCP");

	} elseif ($browsestat == $categoriesviewpagename) {
		$output .= awpcp_display_the_classifieds_page_body($awpcppagename);

	} elseif ($layout == 2) {
		$output .= awpcp_display_the_classifieds_page_body($awpcppagename);

	} else {
		$output .= awpcp_load_classifieds($awpcppagename);
	}

	return $output;
}


/**
 * Handles Contact or Reply to Ad page
 */
function awpcpui_process_contact() {
	//debug();
	$output ='';
	$action='';
	$permastruc=get_option('permalink_structure');

	$pathvaluecontact=get_awpcp_option('pathvaluecontact');

	if (isset($_REQUEST['a']) && !empty($_REQUEST['a'])) {
		$action=$_REQUEST['a'];
	}

	if (isset($_REQUEST['i']) && !empty($_REQUEST['i'])) {
		$adid=$_REQUEST['i'];
	}

	if (!isset($adid) || empty($adid)) {
		if ( get_awpcp_option('seofriendlyurls') ) {
			if (isset($permastruc) && !empty($permastruc)) {

				$adid = get_query_var('id');

				$awpcpreplytoad_requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
				$awpcpreplytoad_requested_url .= $_SERVER['HTTP_HOST'];
				$awpcpreplytoad_requested_url .= $_SERVER['REQUEST_URI'];
			}
		}
	}

	if (get_awpcp_option('reply-to-ad-requires-registration') && !is_user_logged_in()) {
		$message = __('Only registered users can reply to Ads. If you are already registered, please login below in order to reply to the Ad.', 'AWPCP');
		return awpcp_login_form($message, awpcp_current_url());
	}

	if ($action == 'contact') {
		$output .= load_ad_contact_form($adid,$sendersname,$checkhuman,$numval1,$numval2,$sendersemail,$contactmessage,$ermsg);

	} elseif ($action == 'docontact1') {
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid'])){$adid=clean_field($_REQUEST['adid']);} else {$adid='';}
		if (isset($_REQUEST['sendersname']) && !empty($_REQUEST['sendersname'])){$sendersname=clean_field($_REQUEST['sendersname']);} else {$sendersname='';}
		if (isset($_REQUEST['checkhuman']) && !empty($_REQUEST['checkhuman'])){$checkhuman=clean_field($_REQUEST['checkhuman']);} else {$checkhuman='';}
		if (isset($_REQUEST['numval1']) && !empty($_REQUEST['numval1'])){$numval1=clean_field($_REQUEST['numval1']);} else {$numval1='';}
		if (isset($_REQUEST['numval2']) && !empty($_REQUEST['numval2'])){$numval2=clean_field($_REQUEST['numval2']);} else {$numval2='';}
		if (isset($_REQUEST['sendersemail']) && !empty($_REQUEST['sendersemail'])){$sendersemail=clean_field($_REQUEST['sendersemail']);} else {$sendersemail='';}
		if (isset($_REQUEST['contactmessage']) && !empty($_REQUEST['contactmessage'])){$contactmessage=clean_field($_REQUEST['contactmessage']);} else {$contactmessage='';}

		$output .= processadcontact($adid,$sendersname,$checkhuman,$numval1,$numval2,$sendersemail,$contactmessage,$ermsg='');

	} else {
		$output .= load_ad_contact_form($adid,$sendersname='',$checkhuman='',$numval1='',$numval2='',$sendersemail='',$contactmessage='',$ermsg='');
	}

	return $output;
}


function awpcp_load_classifieds($awpcppagename) {
	$output = '';
	if (get_awpcp_option('main_page_display') == 1) {
		// display latest ads on mainpage
		$grouporderby = get_group_orderby();
		$output .= awpcp_display_ads($where='',$byl=1,$hidepager='',$grouporderby,$adorcat='ad');
	} else {
		$output .= awpcp_display_the_classifieds_page_body($awpcppagename);
	}

	return $output;
}


//	START FUNCTION: show the classifieds page body

function awpcp_display_the_classifieds_page_body($awpcppagename) {
	global $hasregionsmodule;

	$output = '';

	if (!isset($awpcppagename) || empty($awpcppagename)) {
		$awpcppage=get_currentpagename();
		$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	}

	$output .= "<div id=\"classiwrapper\">";
	$uiwelcome=strip_slashes_recursive(get_awpcp_option('uiwelcome'));
	$output .= "<div class=\"uiwelcome\">$uiwelcome</div>";

	// Place the menu items
	$output .= awpcp_menu_items();

	if ($hasregionsmodule ==  1) {
		if (isset($_SESSION['theactiveregionid'])) {
			$theactiveregionid = $_SESSION['theactiveregionid'];
			$theactiveregionname = get_theawpcpregionname($theactiveregionid);
		}
		$output .= awpcp_region_control_selector();
	}

	$output .= "<div class=\"classifiedcats\">";

	//Display the categories
	$output .= awpcp_display_the_classifieds_category($awpcppagename);

	$output .= "</div>";
	$removeLink = get_awpcp_option('removepoweredbysign');

	if ( field_exists($field='removepoweredbysign') && !($removeLink) ) {
		$output .= "<p><font style=\"font-size:smaller\">";
		$output .= __("Powered by ","AWPCP");
		$output .= "<a href=\"http://www.awpcp.com\">Another Wordpress Classifieds Plugin</a> </font></p>";
	} elseif ( field_exists($field='removepoweredbysign') && ($removeLink) ) {

	} else {
//		$output .= "<p><font style=\"font-size:smaller\">";
//		$output .= __("Powered by ","AWPCP");
//		$output .= "<a href=\"http://www.awpcp.com\">Another Wordpress Classifieds Plugin</a> </font></p>";
	}

	$output .= "</div>";

	return $output;
}



//	End function display the home screen



//	START FUNCTION: configure the menu place ad edit exisiting ad browse ads search ads

function awpcp_menu_items() {
	global $awpcp_imagesurl, $hasrssmodule;

	$action='';
	$output = '';

	$awpcppage=get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	$quers=setup_url_structure($awpcppagename);
	$permastruc=get_option('permalink_structure');

	$placeadpagenameunsani=get_awpcp_option('place-ad-page-name');
	$placeadpagename=sanitize_title(get_awpcp_option('place-ad-page-name'));

	$editadpagenameunsani=get_awpcp_option('edit-ad-page-name');
	$editadpagename=sanitize_title(get_awpcp_option('edit-ad-page-name'));

	$searchadspagenameunsani=get_awpcp_option('search-ads-page-name');
	$searchadspagename=sanitize_title(get_awpcp_option('search-ads-page-name'));

	$browseadspagenameunsani=get_awpcp_option('browse-ads-page-name');
	$browseadspagename=sanitize_title(get_awpcp_option('browse-ads-page-name'));

	$browsecatspagenameunsani=get_awpcp_option('browse-categories-page-name');
	$browsecatspagename=sanitize_title(get_awpcp_option('browse-categories-page-name'));
	
	$categoriesviewpagename=sanitize_title(get_awpcp_option('view-categories-page-name'));
	$categoriesviewpagenameunsani=get_awpcp_option('view-categories-page-name');
	
	$awpcp_page_id=awpcp_get_page_id_by_ref('main-page-name');
	$awpcp_placead_pageid=awpcp_get_page_id_by_ref('place-ad-page-name');
	$awpcp_editad_pageid=awpcp_get_page_id_by_ref('edit-ad-page-name');
	$awpcp_browseads_pageid=awpcp_get_page_id_by_ref('browse-ads-page-name');
	$awpcp_searchads_pageid=awpcp_get_page_id_by_ref('search-ads-page-name');
	$awpcp_browsecats_pageid=awpcp_get_page_id_by_ref('browse-categories-page-name');

	// we don't use get_permalink because it will return the homepage URL
	// if the main AWPCP page happens to be also the front page, and that 
	// will break our rewrite rules
	if (!empty($permastruc)) {
		$base_url = home_url(get_page_uri($awpcp_page_id));
	} else {
		$base_url = add_query_arg('page_id', $awpcp_page_id, home_url());
	}

	if ($hasrssmodule == 1) {
		$url_rss_feed = add_query_arg(array('a' => 'rss'), $base_url);

		$rsstitle = __("RSS Feed for Classifieds", 'AWPCP');
		$output .= "<div style=\"float:left;margin-right:10px;\"><a href=\"$url_rss_feed\"><img style=\"border:none;\" title='".$rsstitle."' alt='.$rsstitle.' src=\"$awpcp_imagesurl/rssicon.png\"/></a></div>";
	}

	if (!isset($action) || empty ($action)) {
		if (isset($_REQUEST['a']) && !empty($_REQUEST['a'])) {
			$action=$_REQUEST['a'];
		}
	}

	$url_placead = get_permalink($awpcp_placead_pageid);
	$url_browseads = get_permalink($awpcp_browseads_pageid);
	$url_searchads = get_permalink($awpcp_searchads_pageid);
	$url_editad = get_permalink($awpcp_editad_pageid);

	if (isset($permastruc) && !empty($permastruc)) {
		$url_browsecats = sprintf('%s/%s', trim($base_url, '/'), $categoriesviewpagename);
	} else {
		$url_browsecats = add_query_arg(array('layout' => 2), $base_url);
	}

	// if Ad Management panel is enabled Edit Ad links
	// should use that instead
	if (get_awpcp_option('enable-user-panel') == 1) {
		$panel_url = admin_url('admin.php?page=awpcp-panel');
		//$url_placead = add_query_arg(array('action' => 'place-ad'), $panel_url);
		$url_editad = $panel_url;
	}

	if ($action == 'placead') {
		$liplacead="<li class=\"postad\"><b>$placeadpagenameunsani";
		$liplacead.=__(" Step 1","AWPCP");
		$liplacead.="</b></li>";
	} else {
		$liplacead="<li class=\"postad\"><a href=\"$url_placead\">$placeadpagenameunsani";
		$liplacead.="</a></li>";
	}

	if ($action== 'editad') {
		$lieditad="<li class=\"edit\"><b>$editadpagenameunsani";
		$lieditad.=__(" Step 2","AWPCP");
		$lieditad.="</b></li>";
	} else {
		$lieditad="<li class=\"edit\"><a href=\"$url_editad\">$editadpagenameunsani";
		$lieditad.="</a></li>";
	}

	wp_reset_query();
	

	/* delete: 
	$pathvalueviewcategories=get_awpcp_option('pathvalueviewcategories');
	$catviewpagecheck='';

	$awpcpviewcategories_requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	$awpcpviewcategories_requested_url .= $_SERVER['HTTP_HOST'];
	$awpcpviewcategories_requested_url .= $_SERVER['REQUEST_URI'];

	$awpcpparsedviewcategoriesURL = parse_url ($awpcpviewcategories_requested_url);
	$awpcpsplitviewcategoriesPath = preg_split ('/\//', $awpcpparsedviewcategoriesURL['path'], 0, PREG_SPLIT_NO_EMPTY);


	if (isset($awpcpsplitviewcategoriesPath[$pathvalueviewcategories]) && !empty($awpcpsplitviewcategoriesPath[$pathvalueviewcategories]))
	{
		$catviewpagecheck=$awpcpsplitviewcategoriesPath[$pathvalueviewcategories];
	}
	*/

	$catviewpagecheck = get_query_var('cid');

	if (is_page($browseadspagename)) {
		$browseads_browsecats="<li class=\"browse\"><a href=\"$url_browsecats\">$categoriesviewpagenameunsani";
		$browseads_browsecats.="</a></li>";

	} elseif (is_page($browsecatspagename) || ($catviewpagecheck == $categoriesviewpagename)) {
		$browseads_browsecats="<li class=\"browse\"><a href=\"$url_browseads\">$browseadspagenameunsani";
		$browseads_browsecats.="</a></li>";

	} elseif ((get_awpcp_option('main_page_display') == 1) && ($catviewpagecheck != $categoriesviewpagename)) {
		if (is_page($awpcp_page_id) && ($action != 'unsetregion')) {
			$browseads_browsecats="<li class=\"browse\"><a href=\"$url_browsecats\">$categoriesviewpagenameunsani";
			$browseads_browsecats.="</a></li>";
		} else {
			$browseads_browsecats="<li class=\"browse\"><a href=\"$url_browseads\">$browseadspagenameunsani";
			$browseads_browsecats.="</a></li>";
			$browseads_browsecats.="<li class=\"browse\"><a href=\"$url_browsecats\">$categoriesviewpagenameunsani";
			$browseads_browsecats.="</a></li>";
		}

	} else {
		$browseads_browsecats="<li class=\"browse\"><a href=\"$url_browseads\">$browseadspagenameunsani";
		$browseads_browsecats.="</a></li>";
	}

	$li_search_ads = "<li class=\"searchcads\"><a href=\"$url_searchads\">";
	$li_search_ads.= "$searchadspagenameunsani</a></li>";
		
	$output .= "<ul id=\"postsearchads\">";

	$items = array();

	$isadmin=checkifisadmin();
	$adminplaceads = get_awpcp_option('onlyadmincanplaceads');

	if (!($adminplaceads)) {
		
		if (get_awpcp_option('show-menu-item-place-ad')) {
			$items['place-ad'] = $liplacead;
		}
		if (get_awpcp_option('show-menu-item-edit-ad')) {
			$items['edit-ad'] = $lieditad;
		}
		if (get_awpcp_option('show-menu-item-browse-ads')) {
			$items['browse-ads'] = $browseads_browsecats;
		}
		if (get_awpcp_option('show-menu-item-search-ads')) {
			$items['search-ads'] = $li_search_ads;
		}

		//$output .= "$liplacead";
		//$output .= "$lieditad";
		//$output .= "$browseads_browsecats";
		//$output .= "<li class=\"searchcads\"><a href=\"$url_searchads\">$searchadspagenameunsani";
		//$output .= "</a></li>";
	} elseif ($adminplaceads && ($isadmin == 1)) {
		//$output .= "$liplacead";
		//$output .= "$lieditad";
		//$output .= "$browseads_browsecats";
		//$output .= "<li class=\"searchcads\"><a href=\"$url_searchads\">$searchadspagenameunsani";
		//$output .= "</a></li>";

		if (get_awpcp_option('show-menu-item-place-ad')) {
			$items['place-ad'] = $liplacead;
		}
		if (get_awpcp_option('show-menu-item-edit-ad')) {
			$items['edit-ad'] = $lieditad;
		}
		if (get_awpcp_option('show-menu-item-browse-ads')) {
			$items['browse-ads'] = $browseads_browsecats;
		}
		if (get_awpcp_option('show-menu-item-search-ads')) {
			$items['search-ads'] = $li_search_ads;
		}
	} else {
		//$output .= "$browseads_browsecats";
		//$output .= "<li class=\"searchcads\"><a href=\"$url_searchads\">$searchadspagenameunsani";
		//$output .= "</a></li>";

		if (get_awpcp_option('show-menu-item-browse-ads')) {
			$items['browse-ads'] = $browseads_browsecats;
		}
		if (get_awpcp_option('show-menu-item-search-ads')) {
			$items['search-ads'] = $li_search_ads;
		}
	}

	$items = apply_filters('awpcp_menu_items', $items);
		
	$output .= join('', $items) .  "</ul><div class=\"fixfloat\"></div>";
	return $output;
}


//	END FUNCTION: configure the menu place ad edit exisiting ad browse ads search ads

/**
 * Renders the HTML content for a Category Item to be inserted inside a
 * LI or P element.
 */
function awpcp_display_classifieds_category_item($category, $class='toplevelitem') {
	global $awpcp_imagesurl;

	// $permastruc = get_option('permalink_structure');
	// $awpcp_browsecats_pageid=awpcp_get_page_id_by_ref('browse-categories-page-name');

	// // Category URL
	// $modcatname1=cleanstring($category[1]);
	// $modcatname1=add_dashes($modcatname1);

	// $base_url = get_permalink($awpcp_browsecats_pageid);
	// if (get_awpcp_option('seofriendlyurls')) {
	// 	if (isset($permastruc) && !empty($permastruc)) {
	// 		$url_browsecats = sprintf('%s/%s/%s', trim($base_url, '/'), $category[0], $modcatname1);
	// 	} else {
	// 		$params = array('a' => 'browsecat', 'category_id' => $category[0]);
	// 		$url_browsecats = add_query_arg($params, $base_url);
	// 	}
	// } else {
	// 	if (isset($permastruc) && !empty($permastruc)) {
	// 		$params = array('category_id' => "$category[0]/$modcatname1");
	// 		$url_browsecats = add_query_arg($params, $base_url);
	// 	} else {
	// 		$params = array('a' => 'browsecat', 'category_id' => $category[0]);
	// 		$url_browsecats = add_query_arg($params, $base_url);
	// 	}
	// }
	$url_browsecats = url_browsecategory($category[0]);

	// Category icon
	if (function_exists('get_category_icon')) {
		$category_icon = get_category_icon($category[0]);
	}

	// Ads count
	if (get_awpcp_option('showadcount') == 1) {
		$ads_in_cat = '(' . total_ads_in_cat($category[0]) . ')';
	} else {
		$ads_in_cat = '';
	}

	if (isset($category_icon) && !empty($category_icon)) {
		$cat_icon = "<img class=\"categoryicon\" src=\"$awpcp_imagesurl/caticons/$category_icon\" alt=\"$category[1]\" border=\"0\"/>";
	} else {
		$cat_icon = '';
	}

	return $cat_icon . '<a class="' . $class . '" href="' . $url_browsecats . '">' . $category[1] . '</a> ' . $ads_in_cat;
}

/**
 * TODO: separate categories layout in a function that can be used in other places
 * without all the noise.
 * @param  [type]  $awpcppagename [description]
 * @param  integer $parent        [description]
 * @return [type]                 [description]
 */
function awpcp_display_the_classifieds_category($awpcppagename, $parent=0, $sidebar=true) {
	global $wpdb;
	global $awpcp_imagesurl;
	global $hasregionsmodule;

	$tbl_ad_categories = $wpdb->prefix . "awpcp_categories";

	$usingsidelist=0;

	if (!isset($awpcppagename) || empty($awpcppagename)) {
		$awpcppage=get_currentpagename();
		$awpcppagename = sanitize_title($awpcppage);
	}

	$quers=setup_url_structure($awpcppagename);

	$awpcp_page_id=awpcp_get_page_id_by_ref('main-page-name');
	$browsecatspagename=sanitize_title(get_awpcp_option('browse-categories-page-name'));

	$table_cols = 1;
	$query = "SELECT category_id,category_name FROM ". AWPCP_TABLE_CATEGORIES ." ";
	$query.= "WHERE category_parent_id=%d AND category_name <> '' ";
	$query.= "ORDER BY category_order,category_name ASC";
	$query = $wpdb->prepare($query, $parent);

	$res = awpcp_query($query, __LINE__);

	$columns = get_awpcp_option('view-categories-columns', 2);

	if (mysql_num_rows($res)) {
		$i = 0;

		// For use with regions module if sidelist is enabled
		if ($sidebar && $hasregionsmodule ==  1) {
			if (get_awpcp_option('showregionssidelist')) {
				$awpcpregions_sidepanel = awpcp_region_control_render_sidelist();
				$usingsidelist=1;
			}
		}

		$myreturn = '<div id="awpcpcatlayout">';// Open the container division

		if ($usingsidelist) {
			$myreturn.="$awpcpregions_sidepanel<div class=\"awpcpcatlayoutleft\">";
		}

		while ($rsrow=mysql_fetch_row($res)) {
			if ($i > 0 && $i % $columns == 0) {
				$myreturn .= '</ul>';
			}
			if ($i == 0 || $i % $columns == 0) {
				$myreturn .= '<ul class="showcategoriesmainlist clearfix">';
			}

			$myreturn .= '<li class="columns-' . $columns . '">';
			$myreturn .= '<p class="maincategoryclass">';
			$myreturn .= awpcp_display_classifieds_category_item($rsrow);
			$myreturn .= '</p>';

			$mcid = $rsrow[0];

			$query = "SELECT category_id,category_name FROM ". AWPCP_TABLE_CATEGORIES ." ";
			$query.= "WHERE category_parent_id='$mcid' AND category_name <> '' ";
			$query.= "ORDER BY category_order,category_name ASC";
			$res2 = awpcp_query($query, __LINE__);

			if (mysql_num_rows($res2)) {
				$myreturn .= "<ul class=\"showcategoriessublist\">";
				while ($rsrow2=mysql_fetch_row($res2)) {
					$myreturn .= "<li>";
					$myreturn .= awpcp_display_classifieds_category_item($rsrow2, '');
					$myreturn .= "</li>";
				} 
				$myreturn .= "</ul>";
			}

			$myreturn .= "</li>";
			$i++;
		}

		$myreturn .= "</ul>";

		if ($usingsidelist) {
			$myreturn.='</div>'; // To close div class awpcplayoutleft
		}

		$myreturn .= '</div>';// Close the container division
		$myreturn .= "<div class=\"fixfloat\"></div>";
	}
	
	return $myreturn;
}

//	END FUNCTION: show the categories



//	FUNCTION: display the ad post form










//	End process



//	START FUNCTION: configure the page to display to user for purpose of editing images during ad editing process


function editimages($adtermid, $adid, $adkey, $editemail) {
	global $wpdb;

	$output = '';

	$imgstat = '';
	$awpcpuperror = '';

	if (strcasecmp($editemail, get_adposteremail($adid)) == 0) {

		$imagecode = '<h2>' . __('Manage your Ad images','AWPCP') . '</h2>';

		if (!isset($adid) || empty($adid)) {
			$imagecode.=__("There has been a problem encountered. The system is unable to continue processing the task in progress. Please start over and if you encounter the problem again, please contact a system administrator.","AWPCP");

		} else {
			// First make sure images are allowed
			if (get_awpcp_option('imagesallowdisallow') == 1) {
				// Next figure out how many images user is allowed to upload
				$numimgsallowed = awpcp_get_ad_number_allowed_images($adid, $adtermid);

				// Next figure out how many (if any) images the user has previously uploaded
				$totalimagesuploaded = get_total_imagesuploaded($adid);

				// Next determine if the user has reached their image quota and act accordingly
				if ($totalimagesuploaded >= 1) {
					$imagecode.="<p>";
					$imagecode.=__("Your images are displayed below. The total number of images you are allowed is","AWPCP");
					$imagecode.=": $numimgsallowed</p>";

					if (($numimgsallowed - $totalimagesuploaded) == 0) {
						$imagecode.="<p>";
						$imagecode.=__("If you want to change your images you will first need to delete the current images","AWPCP");
						$imagecode.="</p>";
					}

					$admin_must_approve = get_awpcp_option('imagesapprove');
					if ($admin_must_approve == 1) {
						$imagecode.="<p>";
						$imagecode.=__("Image approval is in effect so any new images you upload will not be visible to viewers until an admin has approved it","AWPCP");
						$imagecode.="</p>";
					}

					// Display the current images
					$imagecode .= "<div id=\"displayimagethumbswrapper\"><div id=\"displayimagethumbs\"><ul>";
					$theimage = '';

					$query = "SELECT key_id,image_name,disabled FROM " . AWPCP_TABLE_ADPHOTOS . " ";
					$query.= "WHERE ad_id='$adid' ORDER BY image_name ASC";

					$res = awpcp_query($query, __LINE__);

					while ($rsrow=mysql_fetch_row($res)) {
						list($ikey,$image_name,$disabled) = $rsrow;

						$ikey = sprintf(join('_', array($ikey, $adid, $adtermid, $adkey, $editemail)));
						$ikey = str_replace('@', '-', $ikey);
						$actions = array();

						$editadpageid = awpcp_get_page_id_by_ref('edit-ad-page-name');
						$url_editpage = get_permalink($editadpageid);

						$href = add_query_arg(array('a' => 'dp', 'k' => str_replace('@','-',$ikey)), $url_editpage);
						$actions[] = sprintf('<a href="%s">%s</a>', $href, _x('Delete', 'edit ad', 'AWPCP'));

						$transval = '';
						if ((awpcp_current_user_is_admin() || !$admin_must_approve) && $disabled == 1) {
							$transval = 'class="imgtransparency"';
							$href = add_query_arg(array('a' => 'enable-picture', 'k' => $ikey), $url_editpage);
							$actions[] = sprintf('<a href="%s">%s</a>', $href, _x('Enable', 'edit ad', 'AWPCP'));
						} else if (awpcp_current_user_is_admin() || !$admin_must_approve) {
							$href = add_query_arg(array('a' => 'disable-picture', 'k' => $ikey), $url_editpage);
							$actions[] = sprintf('<a href="%s">%s</a>', $href, _x('Disable', 'edit ad', 'AWPCP'));
						} else if ($disabled == 1) {
							$transval = 'class="imgtransparency"';
							$actions[] = '<font style="font-size:smaller;">' . __('Disabled','AWPCP') . '</font>';
						}

						// XXX: unused
						// if (!isset($awpcppagename) || empty($awpcppagename) ) {
						// 	$awpcppage=get_currentpagename();
						// 	$awpcppagename = sanitize_title($awpcppage);
						// }

						$large_image = awpcp_get_image_url($image_name, 'large');
						$thumbnail = awpcp_get_image_url($image_name, 'thumbnail');

						$theimage .= "<li>";
						$theimage .= "<a class=\"thickbox\" href=\"" . $large_image . "\">";
						$theimage .= "<img $transval src=\"" . $thumbnail . "\"/>";
						$theimage .= "</a>";
						$theimage .= sprintf("<br/>%s", join(' | ', $actions));
						$theimage .= "</li>";
					}

					$imagecode.=$theimage;
					$imagecode.="</ul></div></div>";
					$imagecode.="<div class=\"fixfloat\"></div>";

				} elseif ($totalimagesuploaded < 1) {
					$imagecode.=__("You do not currently have any images uploaded. Use the upload form below to upload your images. If you do not wish to upload any images simply click the finish button. If uploading images, be careful not to click the finish button until after you've uploaded all your images","AWPCP");
				}

				if ($totalimagesuploaded < $numimgsallowed) {
					$max_image_size=get_awpcp_option('maximagesize');
					$showimageuploadform=display_awpcp_image_upload_form($adid,$adtermid,$adkey,$adaction='editad',$nextstep='finish',$adpaymethod='',$awpcpuperror);
				} else {
					$showimageuploadform=display_awpcp_image_upload_form($adid,$adtermid,$adkey,$adaction='editad',$nextstep='finishnoform',$adpaymethod='',$awpcpuperror);
				}

			}

			$imagecode.=$showimageuploadform;
			$imagecode.="<div class=\"fixfloat\"></div>";
		}

		$output .= "<div id=\"classiwrapper\">$imagecode</div>";
	}
	return $output;
}


//	END FUNCTION




//	END FUNCTION





//	End process




//	End process




//	END FUNCTION





function awpcp_append_title($title, $separator, $seplocation) {
	$awpcpiscat='';
	$permastruc=get_option('permalink_structure');
	$awpcpshowadpagename=sanitize_title(get_awpcp_option('show-ads-page-name'));
	$awpcpbrowsecatspagename=sanitize_title(get_awpcp_option('browse-categories-page-name'));

	$awpcptitleseparator = get_awpcp_option('awpcptitleseparator');
	if (empty($awpcptitleseparator)) {
		$awpcptitleseparator = $separator;
	}

	//$pathvalueshowad=get_awpcp_option('pathvalueshowad');
	//$pathvaluebrowsecats=get_awpcp_option('pathvaluebrowsecats');

	wp_reset_query();

	if (is_page($awpcpshowadpagename) || is_page($awpcpbrowsecatspagename))
	{
		if (isset($_REQUEST['category_id']) && !empty($_REQUEST['category_id']))
		{
			$category_id=$_REQUEST['category_id'];
		}

		if (!isset($adid) || empty($adid))
		{
			if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid']))
			{
				$adid=$_REQUEST['adid'];
			}
			elseif (isset($_REQUEST['id']) && !empty($_REQUEST['id']))
			{
				$adid=$_REQUEST['id'];
			}
			else
			{
				if (isset($permastruc) && !empty($permastruc))
				{
					$awpcpshowad_requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
					$awpcpshowad_requested_url .= $_SERVER['HTTP_HOST'];
					$awpcpshowad_requested_url .= $_SERVER['REQUEST_URI'];

					/* delete: 
					$awpcpparsedshowadURL = parse_url ($awpcpshowad_requested_url);
					$awpcpsplitshowadPath = preg_split ('/\//', $awpcpparsedshowadURL['path'], 0, PREG_SPLIT_NO_EMPTY);

					foreach ($awpcpsplitshowadPath as $awpcpsplitshowadPathitem)
					{
						if ( $awpcpsplitshowadPathitem == $awpcpbrowsecatspagename )
						{
							$awpcpiscat=1;
							$adcategoryid=$awpcpsplitshowadPath[$pathvaluebrowsecats];
						}
					}
					*/


					//$adid=$awpcpsplitshowadPath[$pathvalueshowad];

					$adcategoryid = get_query_var('cid');
					$adid = get_query_var('id');
				} else {
					$adid = 0;
				}
			}
		}

		if ( $awpcpiscat == 1 )
		{
			$awpcp_ad_cat_title=get_adcatname($adcategoryid);

			$title.=" $awpcptitleseparator $awpcp_ad_cat_title";
		}
		elseif ( isset($category_id) && !empty($category_id) )
		{
			$awpcp_ad_cat_title=get_adcatname($category_id);

			$title.=" $awpcptitleseparator $awpcp_ad_cat_title";
		}
		else
		{
			$awpcp_ad_title=get_adtitle($adid);

			$awpcpadcity=get_adcityvalue($adid);
			$awpcpadstate=get_adstatevalue($adid);
			$awpcpadcountry=get_adcountryvalue($adid);
			$awpcpadcountyvillage=get_adcountyvillagevalue($adid);

			if ( get_awpcp_option('showcityinpagetitle') && !empty($awpcpadcity) )
			{
				$awpcp_ad_title.=" $awpcptitleseparator ";
				$awpcp_ad_title.=$awpcpadcity;
			}
			if ( get_awpcp_option('showstateinpagetitle') && !empty($awpcpadstate) )
			{
				$awpcp_ad_title.=" $awpcptitleseparator ";
				$awpcp_ad_title.=$awpcpadstate;
			}
			if ( get_awpcp_option('showcountryinpagetitle') && !empty($awpcpadcountry) )
			{
				$awpcp_ad_title.=" $awpcptitleseparator ";
				$awpcp_ad_title.=$awpcpadcountry;
			}
			if ( get_awpcp_option('showcountyvillageinpagetitle') && !empty($awpcpadcountyvillage) )
			{
				$awpcp_ad_title.=" $awpcptitleseparator ";
				$awpcp_ad_title.=$awpcpadcountyvillage;
			}
			if ( get_awpcp_option('showcategoryinpagetitle') )
			{
				$awpcp_ad_category_name=get_adcatname(get_adcategory($adid));

				$awpcp_ad_title.=" $awpcptitleseparator ";
				$awpcp_ad_title.=$awpcp_ad_category_name;
			}
			$title.=" $awpcptitleseparator $awpcp_ad_title";
		}
	}

	return stripslashes($title);
}
