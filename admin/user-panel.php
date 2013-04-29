<?php
/**
 * User Ad Management Panel functions
 */

class AWPCP_User_Panel {

	public function AWPCP_User_Panel() {
		$this->listings = new AWPCP_User_Panel_Listings();
		$this->profile = new AWPCP_User_Panel_Profile_Data();
		add_action('awpcp_add_menu_page', array($this, 'menu'));
	}

	/**
	 * Register Ad Management Panel menu
	 */
	public function menu() {
		if (get_awpcp_option('enable-user-panel') != 1) {
			return;
		}

		$slug = 'awpcp-panel';
		$blog_title = get_bloginfo();
		add_menu_page($blog_title . ' Ad Management Panel', 'Ad Management', 'read', 
					  $slug, array($this->listings, 'dispatch'), MENUICO);

		// Listings
		$page = add_submenu_page($slug, 'Manage Ad Listings', 'Listings', 'read',
						 		 'awpcp-panel', array($this->listings, 'dispatch'));
		add_action('admin_print_styles-' . $page, array($this->listings, 'scripts'));

		do_action('awpcp_panel_add_submenu_page', $slug);
	}
}

class AWPCP_User_Panel_Listings {

	public function AWPCP_User_Panel_Listings() {
		$this->container_id = 'awpcp-ad-management-panel';
		$blog_title = get_bloginfo();
		$this->page_title = $blog_title . __(" User Ad Management Panel - Listings","AWPCP");

		add_action('init', array($this, 'init'));
		add_action('wp_ajax_awpcp-panel-delete-ad', array($this, 'ajax'));
	}

	public function init() {
		wp_register_script('awpcp-panel-listings', AWPCP_URL . 'js/user-panel-listings.js', 
						   array('awpcp-table-ajax-admin'), '1.0', true);
	}

	public function scripts() {
		wp_enqueue_script('awpcp-panel-listings');
	}

	public function redirect($action) {
		$_REQUEST['action'] = $action;
		return $this->dispatch();
	}

	public function dispatch() {
		global $current_user, $wpdb;
		get_currentuserinfo();

		$this->current_user = $current_user;
		$this->is_admin_user = awpcp_current_user_is_admin();
		$this->url = awpcp_current_url();

		$action = awpcp_request_param('action', 'list');
		$id = awpcp_request_param('id', null);

		$offset = awpcp_request_param('offset', 0);
		$results = awpcp_request_param('results', 10);
		$sortby = awpcp_request_param('sortby', 'mostrecent');
		$filter = awpcp_request_param('lookupadbychoices', '');
		$query =  $wpdb->escape(awpcp_request_param('lookupadidortitle', ''));

		// if user can't modify this ad, do nothing and show list of ads
		if (!$this->is_admin_user && !is_null($id) && 
			!AWPCP_Ad::belongs_to_user($id, $current_user->ID)) {
			$content = $this->index($filter, $query, $sortby, $offset, $results);
		}

		if ($action == 'delete-ad') { // handled by ajax
			$this->url = remove_query_arg('action', $this->url);
			$content = $this->redirect('list');

		} else if ($action == 'delete-selected') {
			$ids = array_filter(awpcp_post_param('selected', array()), 'intval');
			$content = $this->delete_ads($ids);

		} else if ($action == 'renew-ad') {
			$content = $this->renew_ad($id);

		} else if ($action == 'place-ad') {
			$content = $this->place_ad();

		} else if ($action == 'edit-ad') {
			$content = $this->edit_ad($id);

		} else if (in_array($action, array('manage-images', 'add-image', 'deletepic', 'set-primary-image', 'approvepic', 'rejectpic'))) {
			$content = $this->manage_images($action);

		} else if (in_array($action, array('list', 'lookupadby'))) {
			$content = $this->index($filter, $query, $sortby, $offset, $results);
		}

		echo $content;
	}

	public function ajax() {
	 	global $current_user;
	 	get_currentuserinfo();

		$id = awpcp_post_param('id', 0);

		$is_admin_user = awpcp_current_user_is_admin();

		// if user can't modify this ad, do nothing and show list of ads
		if (!AWPCP_Ad::belongs_to_user($id, $current_user->ID) && !$is_admin_user) {
			return false;
		}

		$errors = array();

		if (isset($_POST['remove'])) {
			$result = deletead($id, $adkey='', $editemail='', $force=true, $errors);
		    if (empty($errors)) {
		        $response = json_encode(array('status' => 'success'));
		    } else {
		        $response = json_encode(array('status' => 'error', 'message' => join('<br/>', $errors)));
		    }
		} else {
			$columns = $is_admin_user ? 8 : 7;
	        ob_start();
	            include(AWPCP_DIR . '/admin/templates/delete_form.tpl.php');
	            $html = ob_get_contents();
	        ob_end_clean();
	        $response = json_encode(array('html' => $html));
		}

		header('Content-Type: application/json');
		echo $response;
		exit();
	}

	public function index($filter='', $query='', $sortby='mostrecent', $offset=0, $results=10) {
	 	global $current_user, $wpdb;
	 	get_currentuserinfo();

		wp_parse_str($_SERVER['QUERY_STRING'], $query_args);
		$query_args = array_merge($query_args, array('offset'=> $offset, 
													 'results'=> $results, 
													 'sortby'=> $sortby));

		// filter ads by...

		if (!empty($filter) && empty($query)) {
			$this->message = __('You need enter either an Ad title or an Ad id to look up.', 'AWPCP');
			$where = '1=1';
		} else {
			switch ($filter) {
				case 'adid':
					$id = intval($query);
					if ($id > 0) {
						$where = "ad_id=$id";
					} else {
						$this->message = __('You indicated you wanted to look up the ad by ID but you entered an invalid ID. Please try again.', 'AWPCP');
						$where = '1=1';
					}
					break;
				case 'adtitle':
					$where = "ad_title LIKE '%$query%'";
					break;
				case 'titdet':
					$where = "MATCH (ad_title,ad_details) AGAINST (\"$query\")";
					break;
				case 'location':
					$where = "ad_city='%s' OR ad_state='%s' OR ad_country='%s' OR ad_county_village='%s'";
					$where = sprintf($where, $query, $query, $query, $query);
					break;
				case 'user':
					$sql = "SELECT DISTINCT ID FROM wp_users ";
					$sql.= "LEFT JOIN wp_usermeta ON (ID = user_id) ";
					$sql.= "WHERE (user_login LIKE '%%%s%%') OR ";
					$sql.= "(meta_key = 'last_name' AND meta_value LIKE '%%%s%%') ";
					$sql.= "OR (meta_key = 'first_name' AND meta_value LIKE '%%%s%%')";

					$users = $wpdb->get_col($wpdb->prepare($sql, $query, $query, $query));

					if (!empty($users)) {
						$where = 'user_id IN (' . join(',', $users) . ')';
					} else {
						$where = '1=1';
					}
					break;
				default:
					$where = '1=1';
			}
		}

		$filter_by = array('adid' => __('Ad ID', 'AWPCP'),
						   'adtitle' => __('Ad Title', 'AWPCP'), 
						   'titdet' => __('Keyword', 'AWPCP'), 
						   'location' => __('Location', 'AWPCP'));

		// sort ads by...
		$sort_modes = array('mostrecent' => false, 
							'oldest' => false,
							'renewed' => false,
					   		'titleaz' => false, 
					   		'titleza' => false, 
					   		'awaitingapproval' => false, 
					   		'flagged' => false);
		$sort_names = array('mostrecent' => __('Newest', 'AWPCP'), 
							 'oldest' => __('Oldest', 'AWPCP'),
							 'renewed' => __('Renewed Date', 'AWPCP'),
					   		 'titleaz' => __('Title A-Z', 'AWPCP'), 
					   		 'titleza' => __('Title Z-A', 'AWPCP'), 
					   		 'awaitingapproval' => __('Awaiting Approval', 'AWPCP'), 
					   		 'flagged' => __('Flagged Ads', 'AWPCP'));
		$sort_modes[$sortby] = true;

		$is_admin_user = awpcp_current_user_is_admin();
		
		// Admin can see all Ads, normal user can only see Ads he has posted
		if (!$is_admin_user) {
			$where = "$where AND user_id = " . $current_user->ID;
		} else {
			$filter_by['user'] = __('User', 'AWPCP');
		}

		$items = AWPCP_Ad::find($where=$where, $order=$sortby, $offset=$offset, $results=$results);
		$row_count = AWPCP_Ad::count($where=$where);
		$num_pages = ceil($row_count/$results);

		$pager = create_pager(AWPCP_TABLE_ADS, $where, $offset, $results, '');
		$pager = preg_replace('/<form/', '<form class="pager"', $pager);

		// TODO: do not show disabled ads
		$charge_listing_fee = get_awpcp_option('freepay') == 1;
		$allow_images = get_awpcp_option('imagesallowdisallow') == 1;

		$controller = $this;

		ob_start();
			include(AWPCP_DIR . 'admin/templates/user-panel-listings.tpl.php');
			$content = ob_get_contents();
		ob_clean();

		return $content;
	}

	public function delete_ads($ids) {
		$deleted = 0;

		foreach ($ids as $id) {
			if (AWPCP_Ad::belongs_to_user($id, $this->current_user->ID) || $this->is_admin_user) {
				$errors = array();
				deletead($id, $adkey='', $editemail='', $force=true, $errors);
				if (empty($errors)) {
					$deleted += 1;
				};
			}
		}

		$this->message = sprintf(__('%d Ads were deleted.', 'AWPCP'), $deleted);

		return $this->redirect('list');
	}

	public function renew_ad($id) {
		$_REQUEST['ad_id'] = $id;
		$content = awpcp_renew_ad_page();
		$content = '<div class="wrap"><div class="page-content"><br/>' . $content . '</div></div>';
		return $content;
	}

	public function place_ad() {
		$_REQUEST['a'] = awpcp_request_param('a', 'placead');
		$content = awpcpui_process_placead($post_url=$this->url);
		return $content;
	}

	public function edit_ad($id) {
		$editemail = get_adposteremail($id);
		$adaccesskey = get_adkey($id);
		$awpcppage = get_currentpagename();
		$awpcppagename = sanitize_title($awpcppage, $post_ID='');
		$offset = clean_field($_REQUEST['offset']);
		$results = clean_field($_REQUEST['results']);

		$step = awpcp_post_param('a', 'edit-ad');

		// TODO: the code below was pasted from admin_panel.php
		// should be wrapped in a controller class an use that 
		// controller's methods instead.
		if ($step == 'edit-ad') {
			$content = load_ad_post_form($id, $action='editad', $awpcppagename, 
							$adtermid='', $editemail, $adaccesskey, $adtitle='', 
							$adcontact_name='', $adcontact_phone='', $adcontact_email='',
							$adcategory='', $adcontact_city='', $adcontact_state='',
							$adcontact_country='', $ad_county_village='', $ad_item_price='', 
							$addetails='', $adpaymethod='', $offset, $results,$ermsg='', 
							$websiteurl='', $checkhuman='', $numval1='', $numval2='', 
							$post_url=$this->url);

		} else if ($step == 'dopost1') {
			$errors = array();
			$content = awpcp_place_ad_save_details_step(array(), $errors, true);

			if (empty($errors)) {
				return $this->redirect('list');
			}
		}

		return $content;
	}

	public function manage_images($action='') {
		$is_admin = awpcp_current_user_is_admin();
		$admin_must_approve = get_awpcp_option('imagesapprove');
		$approve = ($is_admin || !$admin_must_approve);

		$picid = awpcp_request_param('picid');

		if ($action == 'deletepic') {
			$adid = awpcp_request_param('adid');
			$adtermid = awpcp_request_param('adtermid');
			$adtermid = awpcp_request_param('adkey');
			$editemaul = awpcp_request_param('editemail');

			$this->message = deletepic($picid, $adid, $adtermid, $adkey, $editemail, $force=true);

		} elseif ($action == 'set-primary-image') {
			$adid = awpcp_request_param('adid');
			awpcp_set_ad_primary_image($adid, $picid);

		} elseif ($approve && in_array($action, array('approvepic', 'rejectpic'))) {
			$image = AWPCP_Image::find_by_id($picid);
			if (is_object($image)) {
				if ($action == 'approvepic') {
					$image->disabled = false;
					$image->save();
				} else if ($action == 'rejectpic') {
					$image->disabled = true;
					$image->save();
				}
			}
		}

		$blog_title = get_bloginfo();
		$this->page_title = $blog_title . __(" User Ad Management Panel - Manage Images","AWPCP");

		$_GET['page'] = 'Manage1'; // viewimages() test for this query arg
		$content = viewimages('ad_id=' . $_REQUEST['id'], $approve, $delete_image_form_action=$this->url);

		return $content;
	}
}

class AWPCP_User_Panel_Profile_Data {

	public function AWPCP_User_Panel_Profile_Data() {
		add_action('show_user_profile', array($this, 'render'));
		add_action('edit_user_profile', array($this, 'render'));
		add_action('personal_options_update', array($this, 'save'));
		add_action('edit_user_profile_update', array($this, 'save'));
	}

	public function render($user) {
		$profile = (array) 	get_user_meta($user->ID, 'awpcp-profile', true);

		ob_start();
			include(AWPCP_DIR . '/admin/templates/profile-fields.tpl.php');
			$content = ob_get_contents();
		ob_end_clean();

		echo $content;
	}

	public function save($user_id) {
		global $current_user;
		get_currentuserinfo();
		
		if (!current_user_can('edit_user', $user_id)) {
			return;
		}

		$profile = (array) get_user_meta($user_id, 'awpcp-profile', true);
		// get username and email from WP
		$profile['username'] = $current_user->user_login;
		$profile['email'] = $_POST['email'];
		$profile['website'] = $_POST['url'];
		$profile = array_merge($profile, $_POST['awpcp-profile']);
		update_user_meta($user_id, 'awpcp-profile', $profile);
	}
}