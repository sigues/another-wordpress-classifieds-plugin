<?php

class AWPCP_Settings_API {

	public $option = 'awpcp-options';
	public $options = array();
	public $defaults = array();
	public $groups = array();

	public function AWPCP_Settings_API() {
		$this->load();
		$this->register_settings();
	}

	/**
	 * Hook actions and filters required by AWPCP Settings
	 * to work.
	 */
	public function setup() {
		add_action('init', array($this, 'init'), 9999);
		add_action('admin_init', array($this, 'register'));

		// setup validate functions
		add_filter('awpcp_validate_settings_general-settings',
				   array($this, 'validate_general_settings'), 10, 2);
		add_filter('awpcp_validate_settings_pages-settings',
				   array($this, 'validate_classifieds_pages_settings'), 10, 2);
		add_filter('awpcp_validate_settings_payment-settings',
				   array($this, 'validate_payment_settings'), 10, 2);
		add_filter('awpcp_validate_settings_registration-settings',
				   array($this, 'validate_registration_settings'), 10, 2);
		add_filter('awpcp_validate_settings_smtp-settings',
				   array($this, 'validate_smtp_settings'), 10, 2);
	}

	public function register_settings() {

		register_setting($this->option, $this->option, array($this, 'validate'));


		// Group: General

		$group = $this->add_group('General', 'general-settings', 10);

		// Section: General - Default

		$key = $this->add_section($group, 'General Settings', 'default', 10, array($this, 'section'));

		$this->add_setting($key, 'activatelanguages', 'Turn on transalation file (POT)',
						   'checkbox', 0, 'Enable translations.');
		$this->add_setting($key, 'main_page_display', 'Show Ad listings on main page',
						   'checkbox', 0,
						   'If unchecked only categories will be displayed');
		$this->add_setting($key, 'view-categories-columns', 'Category columns in View Categories page',
						   'select', 2, '', array('options' => array(1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5)));
		$this->add_setting($key, 'uiwelcome', 'Welcome message in Classified page',
						   'textarea', 'Looking for a job? Trying to find a date? Looking for an apartment? Browse our classifieds. Have a job to advertise? An apartment to rent? Post a classified ad.',
						   'The welcome text for your classified page on the user side');

        $options = array('admin' => 'Administrator', 'admin,editor' => 'Administrator & Editor');
        $this->add_setting($key, 'awpcpadminaccesslevel', 'Role of admin users',
                           'radio', 'admin',
                           'Role of WordPress users who can have admin access to Classifieds.',
                           array('options' => $options));
		$this->add_setting($key, 'awpcppagefilterswitch', 'Enable page filter',
						   'checkbox', 1,
						   'Uncheck this if you need to turn off the AWPCP page filter that prevents AWPCP classifieds children pages from showing up in your wp pages menu (You might need to do this if for example the AWPCP page filter is messing up your page menu. It means you will have to manually exclude the AWPCP children pages from showing in your page list. Some of the pages really should not be visible to your users by default).');
		$this->add_setting($key, 'showlatestawpcpnews', 'Show latest AWPCP news',
						   'checkbox', 1, 'Allow AWPCP RSS.');

		// Section: General - Terms of Service

		$key = $this->add_section($group, 'Terms of Service', 'terms-of-service', 10, array($this, 'section'));

		$this->add_setting($key, 'requiredtos', 'Display and require Terms of Service',
						   'checkbox', 1, 'Display and require Terms of Service');
		$this->add_setting($key, 'tos', 'Terms of Service',
						   'textarea', 'Terms of service go here...',
						   'Terms of Service for posting Ads. Put in text or an URL starting with http. If you use an URL, the text box will be replaced by a link to the appropriate Terms of Service page');

		// Section: General - Anti-SPAM

		$key = $this->add_section($group, 'Anti-SPAM', 'anti-spam', 10, array($this, 'section'));

		$this->add_setting($key, 'useakismet', 'Use Akismet',
						   'checkbox', 1,
						   'Use Akismet for Posting Ads/Contact Responses (strong anti-spam)');
		$this->add_setting($key, 'contactformcheckhuman', 'Enable Math captcha',
						   'checkbox', 1,
						   'Activate Math ad post and contact form validation');
		$this->add_setting($key, 'contactformcheckhumanhighnumval', 'Max number used in Math captcha',
						   'textfield', 10, 'Math validation highest number');

		// Section: General - Window Title

		$key = $this->add_section($group, 'Window Title', 'window-title', 10, array($this, 'section'));

		$this->add_setting($key, 'awpcptitleseparator', 'Window title separator',
						   'textfield', '-',
						   'The character to use to separate ad details used in browser page title. Example: | / -');
		$this->add_setting($key, 'showcityinpagetitle', 'Show city in window title',
						   'checkbox', 1,
						   'Show city in browser page title when viewing individual ad');
		$this->add_setting($key, 'showstateinpagetitle', 'Show state in window title',
						   'checkbox', 1,
						   'Show state in browser page title when viewing individual ad');
		$this->add_setting($key, 'showcountryinpagetitle', 'Show country in window title',
						   'checkbox', 1,
						   'Show country in browser page title when viewing individual ad');
		$this->add_setting($key, 'showcountyvillageinpagetitle', 'Show county village in window title',
						   'checkbox', 1, 'Show county/village/other setting in browser page title when viewing individual ad');
		$this->add_setting($key, 'showcategoryinpagetitle', 'Show category in title',
						   'checkbox', 1,
						   'Show category in browser page title when viewing individual ad');

		// Section: General - Widget

		$key = $this->add_section($group, 'Widget', 'widget', 10, array($this, 'section'));

		$this->add_setting($key, 'sidebarwidgetbeforetitle', 'Code before Widget title',
						   'textfield', '<h3 class="widgettitle">',
						   'Code to appear before widget title');
		$this->add_setting($key, 'sidebarwidgetaftertitle', 'Code after Widget title',
						   'textfield', '</h3>', 'Code to appear after widget title');
		$this->add_setting($key, 'sidebarwidgetbeforecontent', 'Code before Widget content',
						   'textfield', '<div class="widget">',
						   'Code to appear before widget content');
		$this->add_setting($key, 'sidebarwidgetaftercontent', 'Code after Widget content',
						   'textfield', '</div>',
						   'Code to appear after widget content');

		// Section: SEO Settings

		$key = $this->add_section($group, __('SEO Settings', 'AWPCP'), 'seo-settings', 10, array($this, 'section'));

		$this->add_setting($key, 'seofriendlyurls', 'Turn on Search Engine Friendly URLs',
							'checkbox', 0,
							'Turn on Search Engine Friendly URLs? (SEO Mode)');

		// Section: General - Terms of Service

		$key = $this->add_section($group, 'User Ad Management Panel', 'user-panel',
								  10, array($this, 'section'));

		$this->add_setting($key, 'enable-user-panel', 'Enable User Ad Management Panel',
						   'checkbox', 0, '');


		// Group: Classified Pages

		$group = $this->add_group(__('Classifieds Pages', 'AWPCP'), 'pages-settings', 20);

		// Section: Classifieds Pages - Default

		$key = $this->add_section($group, __('Classifieds Pages', 'AWPCP'), 'default', 10, array($this, 'section'));

		$this->add_setting($key, 'main-page-name', 'AWPCP Main page',
						   'textfield', 'AWPCP', 'Name for Classifieds page.');
		$this->add_setting($key, 'show-ads-page-name', 'Show Ad page',
						   'textfield', 'Show Ad', 'Name for Show Ads page.');
		$this->add_setting($key, 'place-ad-page-name', 'Place Ad page',
						   'textfield', 'Place Ad', 'Name for Place Ads page.');
		$this->add_setting($key, 'edit-ad-page-name', 'Edit Ad page',
						   'textfield', 'Edit Ad', 'Name for edit ad page.');
		$this->add_setting($key, 'renew-ad-page-name', 'Renew Ad page',
						   'textfield', 'Renew Ad', 'Name for Renew Ad page.');
		$this->add_setting($key, 'reply-to-ad-page-name', 'Reply to Ad page',
						   'textfield', 'Reply To Ad', 'Name for Reply to Ad page.');
		$this->add_setting($key, 'browse-ads-page-name', 'Browse Ads page',
						   'textfield', 'Browse Ads', 'Name for Browse Ads page.');
		$this->add_setting($key, 'search-ads-page-name', 'Search Ads page',
						   'textfield', 'Search Ads', 'Name for Search Ads page.');
		$this->add_setting($key, 'browse-categories-page-name', 'Browse Categories page',
						   'textfield', 'Browse Categories', 'Name for Browse Categories page.');
		$this->add_setting($key, 'view-categories-page-name', 'View Categories page',
						   'textfield', 'View Categories', 'Name for categories view page. (Dynamic Page)');
		$this->add_setting($key, 'payment-thankyou-page-name', 'Payment Thank You',
						   'textfield', 'Payment Thank You', 'Name for Payment Thank You page.');
		$this->add_setting($key, 'payment-cancel-page-name', 'Payment Cancel page',
						   'textfield', 'Cancel Payment', 'Name for Payment Cancel page.');


		// Group: Ad/Listings

		$group = $this->add_group(__('Ad/Listings', 'AWPCP'), 'listings-settings', 30);

		// Section: Ad/Listings - Notifications

		$key = $this->add_section($group, __('Notifications', 'AWPCP'), 'notifications', 10, array($this, 'section'));
		$this->add_setting($key, 'notifyofadposted', 'Notify admin of posted Ads?',
						   'checkbox', 1, 'An email will be sent when an Ad is posted.');
		$this->add_setting($key, 'notifyofadexpired', 'Notify admin of expired Ads?',
						   'checkbox', 1, 'An email will be sent when the Ad expires.');
		$this->add_setting($key, 'send-user-ad-posted-notification', 'Notify user of posted Ads?',
						   'checkbox', 1, 'An email will be sent when an Ad is posted.');
		$this->add_setting($key, 'notifyofadexpiring', 'Notify user of expired Ads?',
						   'checkbox', 1, 'An email will be sent when the Ad expires.');
		$this->add_setting($key, 'sent-ad-renew-email', 'Notify user of expiring Ads?',
						   'checkbox', 1, 'An email will be sent to remind the user to Renew the Ad when the Ad is about to expire.');
		$this->add_setting($key, 'ad-renew-email-threshold', 'Ad Renew email threshold',
						   'textfield', 5, 'The email is sent the specified number of days before the Ad expires.');

		// Section: Ad/Listings - Moderation

		$key = $this->add_section($group, __('Moderation', 'AWPCP'),
								  'moderation', 10, array($this, 'section'));

		$this->add_setting($key, 'onlyadmincanplaceads', 'Only admin can post Ads',
							'checkbox', 0, '');
		$this->add_setting($key, 'adapprove', 'Disable Ad until admin approves',
						   'checkbox', 0, '');
		$this->add_setting($key, 'notice_awaiting_approval_ad', 'Waiting approval message',
							'textarea', 'All ads must first be approved by the administrator before they are activated in the system. As soon as an admin has approved your ad it will become visible in the system. Thank you for your business.',
							'Text for message to notify user that ad is awaiting approval');
		$this->add_setting($key, 'noadsinparentcat', 'Prevent ads from being posted to top level categories?',
							'checkbox', 0, '');
		$this->add_setting($key, 'disablependingads', 'Enable paid ads that are pending payment.',
						   'checkbox', 1, 'Enable paid ads that are pending payment.');
		$this->add_setting($key, 'addurationfreemode', 'Free Ads expiration threshold',
						   'textfield', 0, 'Expire free ads after how many days? (0 for no expiration).');
		$this->add_setting($key, 'autoexpiredisabledelete', 'Disable expired ads instead of deleting them?',
						   'checkbox', 0, 'Check to disable.');

		// Section: Ad/Listings - Layout and Presentation

		$key = $this->add_section($group, __('Layout and Presentation', 'AWPCP'),
								  'layout', 10, array($this, 'section'));

		$this->add_setting($key, 'allowhtmlinadtext', 'Allow HTML in Ad text',
							'checkbox', 0, 'Allow HTML in ad text (Not recommended).');
		$this->add_setting($key, 'htmlstatustext', 'Display this text above ad detail text input box on ad post page',
							'textarea', 'No HTML Allowed', '');
		$this->add_setting($key, 'maxcharactersallowed', 'Maximun Ad length',
							'textfield', 750, 'Maximum Free Ad length in characters');
		$this->add_setting($key, 'displayadlayoutcode', 'Ad Listings page layout',
							'textarea', '<div class=\"\$awpcpdisplayaditems\"><div style=\"width:\$imgblockwidth;padding:5px;float:left;margin-right:20px;\">\$awpcp_image_name_srccode</div><div style=\"width:50%;padding:5px;float:left;\"><h4>\$ad_title</h4> \$addetailssummary...</div><div style=\"padding:5px;float:left;\"> \$awpcpadpostdate \$awpcp_city_display \$awpcp_state_display \$awpcp_display_adviews \$awpcp_display_price </div><div class=\"fixfloat\"></div></div><div class=\"fixfloat\"></div>',
							'Modify as needed to control layout of ad listings page. Maintain code formatted as \$somecodetitle. Changing the code keys will prevent the elements they represent from displaying.');
		$this->add_setting($key, 'awpcpshowtheadlayout', 'Single Ad page layout',
							'textarea', '<div id=\"showawpcpadpage\"><div class=\"awpcp-title\">\$ad_title</div><br/><div class=\"showawpcpadpage\">\$featureimg<label>Contact Information</label><br/><a href=\"\$codecontact\">Contact \$adcontact_name</a>\$adcontactphone \$location \$awpcpvisitwebsite</div>\$aditemprice \$awpcpextrafields \$showadsense1<div class=\"showawpcpadpage\"><label>More Information</label><br/>\$addetails</div>\$showadsense2 <div class=\"fixfloat\"></div><div id=\"displayimagethumbswrapper\"><div id=\"displayimagethumbs\"><ul>\$awpcpshowadotherimages</ul></div></div><span class=\"fixfloat\">\$tweetbtn \$sharebtn \$flagad</span>\$awpcpadviews \$showadsense3</div>',
							'Modify as needed to control layout of single ad view page. Maintain code formatted as \$somecodetitle. Changing the code keys will prevent the elements they represent from displaying.');

		$radio_options = array('1' => __("Newest","AWPCP"),
							   '2' => __("Title","AWPCP"),
							   '3' => __("Paid first then most recent","AWPCP"),
							   '4' => __("Paid first then title","AWPCP"),
							   '5' => __("Most viewed then title","AWPCP"),
							   '6' => __("Most viewed then most recent","AWPCP"));
		$this->add_setting($key, 'groupbrowseadsby', 'Group Ad Listings by',
						   'radio', 1, '', array('options' => $radio_options));
		$this->add_setting($key, 'groupsearchresultsby', 'Group Ad Listings search results by',
							'radio', 1, '', array('options' => $radio_options));
		$this->add_setting($key, 'adresultsperpage', 'Default number of Ads per page',
						   'textfield', 10, '');
		$this->add_setting($key, 'buildsearchdropdownlists', 'Limits search to available locations.',
							'checkbox', 0, 'The search form can attempt to build drop down country, state, city and county lists if data is available in the system. Note that with the regions module installed the value for this option is overridden.');
		$this->add_setting($key, 'showadcount', 'Show Ad count in Categories',
							'checkbox', 1, 'Show how many ads a category contains.');
		$this->add_setting($key, 'displayadviews', 'Show Ad views',
							'checkbox', 1, 'Show Ad views');
		$this->add_setting($key, 'hyperlinkurlsinadtext', 'Make URLs in ad text clickable',
							'checkbox', 0, '');
		$this->add_setting($key, 'visitwebsitelinknofollow', 'Add no follow to links in Ads',
							'checkbox', 1, '');

		// Section: Ad/Listings - Menu Items

		$key = $this->add_section($group, __('Menu Items', 'AWPCP'), 'menu-items', 20, array($this, 'section'));

		$this->add_setting($key, 'show-menu-item-place-ad', 'Show Place Ad menu item',
							'checkbox', 1, '');
		$this->add_setting($key, 'show-menu-item-edit-ad', 'Show Edit Ad menu item',
							'checkbox', 1, '');
		$this->add_setting($key, 'show-menu-item-browse-ads', 'Show Browse Ads menu item',
							'checkbox', 1, '');
		$this->add_setting($key, 'show-menu-item-search-ads', 'Show Search Ads menu item',
							'checkbox', 1, '');


		// Group: Payment Settings

		$group = $this->add_group('Payment', 'payment-settings', 40);

		// Section: Payment Settings - Default

		$key = $this->add_section($group, __('Payment Settings', 'AWPCP'), 'default', 10, array($this, 'section'));

		$this->add_setting($key, 'freepay', 'Charge Listing Fee?',
							'checkbox', 0, 'Charge Listing Fee? (Pay Mode)');
		$this->add_setting($key, 'displaycurrencycode', 'Currency used in payment pages',
							'textfield', 'USD',
							'The display currency for your payment pages');
		$this->add_setting($key, 'paylivetestmode', 'Put payment gateways in test mode?',
							'checkbox', 0, '');

		// Section: Payment Settings - Default

		$key = $this->add_section($group, __('PayPal Settings', 'AWPCP'), 'paypal', 20, array($this, 'section'));
		$this->add_setting($key, 'activatepaypal', 'Activate PayPal?',
							'checkbox', 1, 'Activate PayPal?');
		$this->add_setting($key, 'paypalemail', 'PayPal receiver email',
							'textfield', 'xxx@xxxxxx.xxx',
							'Email address for PayPal payments (if running in pay mode and if PayPal is activated).');
		$this->add_setting($key, 'paypalcurrencycode', 'PayPal currency code',
							'textfield', 'USD',
							'The currency in which you would like to receive your PayPal payments');
		// $this->add_setting($key, 'paypalpaymentsrecurring', 'Use PayPal recurring payments?',
		// 					'checkbox', 0,
		// 					'Use recurring payments PayPal (this feature is not fully automated or fully integrated. For more reliable results do not use recurring).');

		// Section: Payment Settings - Default

		$key = $this->add_section($group, __('2Checkout Settings', 'AWPCP'), '2checkout', 30, array($this, 'section'));

		$this->add_setting($key, 'activate2checkout', 'Activate 2Checkout',
							'checkbox', 1, 'Activate 2Checkout?');
		$this->add_setting($key, '2checkout', '2Checkout account',
							'textfield', 'xxxxxxx',
							'Account for 2Checkout payments (if running in pay mode and if 2Checkout is activated)');
		// $this->add_setting($key, 'twocheckoutpaymentsrecurring', 'Use 2Checkout recurring payments?',
		// 					'checkbox', 0,
		// 					'Use recurring payments 2Checkout (this feature is not fully automated or fully integrated. For more reliable results do not use recurring).');


		// Group: Image

		$group = $this->add_group('Image', 'image-settings', 50);

		// Section: Image Settings - Default

		$key = $this->add_section($group, __('Image Settings', 'AWPCP'), 'default', 10, array($this, 'section'));

		$this->add_setting($key, 'imagesallowdisallow', 'Allow images in Ads?',
							'checkbox', 1, 'Allow images in ads? (affects both free and pay mode)');
		$this->add_setting($key, 'imagesapprove', 'Hide images until admin approves them',
							'checkbox', 0, '');
		$this->add_setting($key, 'awpcp_thickbox_disabled', 'Turn off thickbox/lightbox?',
							'checkbox', 0,
							'Turn off the thickbox/lightbox if it conflicts with other elements of your site');
		$this->add_setting($key, 'show-click-to-enlarge-link', 'Show click to enlarge link?',
							'checkbox', 1, '');
		$this->add_setting($key, 'imagesallowedfree', 'Number of images allowed in Free mode',
							'textfield', 4,
							'Number of Image Uploads Allowed (Free Mode)');

		// Section: Image Settings - File Settings

		$key = $this->add_section($group, __('Image File Settings', 'AWPCP'), 'image-file', 10, array($this, 'section'));

		$this->add_setting($key, 'uploadfoldername', 'Uploads folder name',
							'textfield', 'uploads',
							'Upload folder name. (Folder must exist and be located in your wp-content directory)');
		$this->add_setting($key, 'maximagesize', 'Maximum file size per image',
							'textfield', '150000',
							'Maximum file size per image user can upload to system.');
		$this->add_setting($key, 'minimagesize', 'Minimum file size per image',
							'textfield', '300',
							'Minimum file size per image user can upload to system');
		$this->add_setting($key, 'imgminwidth', 'Minimum image width',
							'textfield', '640',
							'Minimum width for images.');
		$this->add_setting($key, 'imgminheight', 'Minimum image height',
							'textfield', '480',
							'Minimum height for images.');
		$this->add_setting($key, 'imgmaxwidth', 'Maximun image width',
							'textfield', '640',
							'Maximun width for images. Images wider than this are automatically resized upon upload.');
		$this->add_setting($key, 'imgmaxheight', 'Maximun image height',
							'textfield', '480',
							'Maximun height for images. Images taller than this are automatically resized upon upload.');

		// Section: Image Settings - Primary Images

		$key = $this->add_section($group, __('Primary Image Settings', 'AWPCP'), 'primary-image', 10, array($this, 'section'));

		$this->add_setting($key, 'displayadthumbwidth', 'Thumbnail width (Ad Listings page)',
						   'textfield', '80',
						   'Width of the thumbnail for the primary image shown in Ad Listings view.');
		$this->add_setting($key, 'primary-image-thumbnail-width', 'Thumbnail width (Primary Image)',
							'textfield', '200',
							'Width of the thumbnail for the primary image shown in Single Ad view.');
		$this->add_setting($key, 'primary-image-thumbnail-height', 'Thumbnail height (Primary Image)',
							'textfield', '200',
							'Height of the thumbnail for the primary image shown in Single Ad view.');
		$this->add_setting($key, 'crop-primary-image-thumbnails', 'Crop primary image thumbnails?',
							'checkbox', 1,
							_x('If you decide to crop thumbnails, images will match exactly the dimensions in the settings above but part of the image may be cropped out. If you decide to resize, image thumbnails will be resized to match the specified width and their height will be adjusted proportionally; depending on the uploaded images, thumbnails may have differnt heights.', 'settings', 'AWPCP'));

		// Section: Image Settings - Thumbnails

		$key = $this->add_section($group, __('Thumbnails Settings', 'AWPCP'), 'thumbnails', 10, array($this, 'section'));



		$options = array(0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4);
		$this->add_setting($key, 'display-thumbnails-in-columns',
							__('Number of columns of thumbnails to show in Show Ad page.', 'AWPCP'),
							'select', 0,
							__('Zero means there will be as many thumbnails as possible per row.', 'AWPCP'),
							array('options' => $options));
		$this->add_setting($key, 'imgthumbwidth', 'Thumbnail width',
							'textfield', '125',
							__('Width of the thumbnail images.', 'AWPCP'));
		$this->add_setting($key, 'imgthumbheight', 'Thumbnail height',
							'textfield', '125',
							__('Height of the thumbnail images.', 'AWPCP'));
		$this->add_setting($key, 'crop-thumbnails', 'Crop thumbnails images?',
							'checkbox', 1,
							_x('If you decide to crop thumbnails, images will match exactly the dimensions in the settings above but part of the image may be cropped out. If you decide to resize, image thumbnails will be resized to match the specified width and their height will be adjusted proportionally; depending on the uploaded images, thumbnails may have differnt heights.', 'settings', 'AWPCP'));


		// Group: AdSense

		$group = $this->add_group('AdSense', 'adsense-settings', 60);

		// Section: AdSense Settings

		$key = $this->add_section($group, __('AdSense Settings', 'AWPCP'), 'default', 10, array($this, 'section'));

		$this->add_setting($key, 'useadsense', 'Activate AdSense',
							'checkbox', 1, '');
		$this->add_setting($key, 'adsense', 'AdSense code',
							'textarea', 'AdSense code',
							'Your AdSense code (Best if 468x60 text or banner.)');
		$this->add_setting($key, 'adsenseposition', 'Show AdSense at position',
							'textfield', '2',
							'Show AdSense at position: 1 - above ad text body, 2 - under ad text body, 3 - below Ad images.');


		// Group: Form Field

		$group = $this->add_group('Form Field', 'form-field-settings', 70);

		// Section: Phone Field

		$key = $this->add_section($group, __('Phone Field', 'AWPCP'), 'phone', 10, array($this, 'section'));

		$this->add_setting($key, 'displayphonefield', 'Show Phone field',
							'checkbox', 1, 'Show phone field?');
		$this->add_setting($key, 'displayphonefieldreqop', 'Require Phone',
							'checkbox', 0, 'Require phone?');

		// Section: City Field

		$key = $this->add_section($group, __('City Field', 'AWPCP'), 'city', 10, array($this, 'section'));
		$this->add_setting($key, 'displaycityfield', 'Show City field',
							'checkbox', 1, 'Show city field?');
		$this->add_setting($key, 'displaycityfieldreqop', 'Require City',
							'checkbox', 0, 'Require city?');

		// Section: State Field

		$key = $this->add_section($group, __('State Field', 'AWPCP'), 'state', 10, array($this, 'section'));
		$this->add_setting($key, 'displaystatefield', 'Show State field',
							'checkbox', 1, 'Show State field?');
		$this->add_setting($key, 'displaystatefieldreqop', 'Require State',
							'checkbox', 0, 'Require state?');

		// Section: Country Field

		$key = $this->add_section($group, __('Country Field', 'AWPCP'), 'country', 10, array($this, 'section'));
		$this->add_setting($key, 'displaycountryfield', 'Show Country field',
							'checkbox', 1, 'Show country field?');
		$this->add_setting($key, 'displaycountryfieldreqop', 'Require Country',
							'checkbox', 0, 'Require country?');

		// Section: County Field

		$key = $this->add_section($group, __('County Field', 'AWPCP'), 'county', 10, array($this, 'section'));
		$this->add_setting($key, 'displaycountyvillagefield', 'Show County/Village/other',
							'checkbox', 0, 'Show County/village/other?');
		$this->add_setting($key, 'displaycountyvillagefieldreqop', 'Require County/Village/other',
							'checkbox', 0, 'Require county/village/other?');

		// Section: Price Field

		$key = $this->add_section($group, __('Price Field', 'AWPCP'), 'price', 10, array($this, 'section'));
		$this->add_setting($key, 'displaypricefield', 'Show Price field',
							'checkbox', 1, 'Show price field?');
		$this->add_setting($key, 'displaypricefieldreqop', 'Require Price',
							'checkbox', 0, 'Require price?');

		// Section: Website Field

		$key = $this->add_section($group, __('Website Field', 'AWPCP'), 'website', 10, array($this, 'section'));
		$this->add_setting($key, 'displaywebsitefield', 'Show Website field',
							'checkbox', 1, 'Show website field?');
		$this->add_setting($key, 'displaywebsitefieldreqop', 'Require Website',
							'checkbox', 0, 'Require website?');

		// Section: Posted By Field

		$key = $this->add_section($group, __('Posted By Field', 'AWPCP'), 'posted-by', 10, array($this, 'section'));
		$this->add_setting($key, 'displaypostedbyfield', 'Show Posted By field',
							'checkbox', 1, 'Show Posted By field?');


		// Group: User Registration

		$group = $this->add_group('Registration', 'registration-settings', 80);

		// Section: User Registration

		$key = $this->add_section($group, __('Registration Settings', 'AWPCP'), 'default', 10, array($this, 'section'));

		$this->add_setting($key, 'requireuserregistration', 'Require user registration',
							'checkbox', 0, 'Require user registration?');
		$this->add_setting($key, 'reply-to-ad-requires-registration', 'Reply to Ad requires user registration',
							'checkbox', 0, 'Require user registration for replying to an Ad?');
		$this->add_setting($key, 'postloginformto', 'Post login form to',
							'textfield', '',
							'Post login form to this URL. Value should be the full URL to the wordpress login script (e.g. http://www.awpcp.com/wp-login.php). <br/>**Only needed if registration is required and your login url is mod-rewritten.');
		$this->add_setting($key, 'registrationurl', 'Location of registration page',
							'textfield', '',
							'Location of registraiton page. Value should be the full URL to the wordpress registration page (e.g. http://www.awpcp.com/wp-login.php?action=register). <br/>**Only needed if registration is required and your login url is mod-rewritten.');


		// Group: Email

		$group = $this->add_group('Email', 'email-text-settings', 90);

		// Section: General Email Settings

		$key = $this->add_section($group, __('General Email Settings', 'AWPCP'),
								  'default', 10, array($this, 'section'));

		$this->add_setting($key, 'usesenderemailinsteadofadmin', 'Use sender email for reply messages',
						   'checkbox', 0,
						   'Check this to use the name and email of the sender in the FROM field when someone replies to an ad. When unchecked the messages go out with the website name and WP admin email address in the from field. Some servers will not process outgoing emails that have an email address from gmail, yahoo, hotmail and other free email services in the FROM field. Some servers will also not process emails that have an email address that is different from the email address associated with your hosting account in the FROM field. If you are with such a webhost you need to leave this option unchecked and make sure your WordPress admin email address is tied to your hosting account.');
		$this->add_setting($key, 'awpcpadminemail', 'FROM email address for outgoing emails',
						   'textfield', '',
						   'Emails go out using your WordPress admin email. If you prefer to use a different email enter it here.');

		// Section: Ad Posted Message

		$key = $this->add_section($group, __('Ad Posted Message', 'AWPCP'), 'ad-posted-message', 10, array($this, 'section'));

		$this->add_setting($key, 'listingaddedsubject', 'Subject for Ad posted notification email',
							'textfield', 'Your classified ad listing has been submitted',
							'Subject line for email sent out when someone posts an ad');
		$this->add_setting($key, 'listingaddedbody', 'Body for Ad posted notification email',
							'textarea', 'Thank you for submitting your classified ad. The details of your ad are shown below.',
							'Message body text for email sent out when someone posts an ad');

		// Section: Reply to Ad Message

		$key = $this->add_section($group, __('Reply to Ad Message', 'AWPCP'), 'reply-to-ad-message', 10, array($this, 'section'));

		$this->add_setting($key, 'contactformsubjectline', 'Subject for Reply to Ad email',
							'textfield', 'Response to your AWPCP Demo Ad',
							'Subject line for email sent out when someone replies to ad');
		$this->add_setting($key, 'contactformbodymessage', 'Body for Reply to Ad email',
							'textarea', 'Someone has responded to your AWPCP Demo Ad',
							'Message body text for email sent out when someone replies to ad');

		// Section: Request Ad Message

		$key = $this->add_section($group, __('Request Ad Message', 'AWPCP'), 'request-ad-message', 10, array($this, 'section'));

		$this->add_setting($key, 'resendakeyformsubjectline', 'Subject for Request Ad Access Key email',
							'textfield', 'The classified ad ad access key you requested',
							'Subject line for email sent out when someone requests their ad access key resent');
		$this->add_setting($key, 'resendakeyformbodymessage', 'Body for Request Ad Access Key email',
							'textarea', 'You asked to have your classified ad ad access key resent. Below are all the ad access keys in the system that are tied to the email address you provided',
							'Message body text for email sent out when someone requests their ad access key resent');

		// Section: Incomplete Payment Message

		$key = $this->add_section($group, __('Incomplete Payment Message', 'AWPCP'), 'incomplete-payment-message', 10, array($this, 'section'));

		$this->add_setting($key, 'paymentabortedsubjectline', 'Subject for Incomplete Payment email',
							'textfield', 'There was a problem processing your payment',
							'Subject line for email sent out when the payment processing does not complete');
		$this->add_setting($key, 'paymentabortedbodymessage', 'Body for Incomplete Payment email',
							'textarea', 'There was a problem encountered during your attempt to submit payment. If funds were removed from the account you tried to use to make a payment please contact the website admin or the payment website customer service for assistance.',
							'Message body text for email sent out when the payment processing does not complete');

		// Section: Renew Ad Message

		$key = $this->add_section($group, __('Renew Ad Message', 'AWPCP'), 'renew-ad-message', 10, array($this, 'section'));

		$this->add_setting($key, 'renew-ad-email-subject', 'Subject for Renew Ad email',
							'textfield', 'Your classifieds listing Ad will expire in %d days.',
							'Subject line for email sent out when an Ad is about to expire.');
		$this->add_setting($key, 'renew-ad-email-body', 'Body for Renew Ad email',
							'textarea', 'This is an automated notification that your classified Ad will expire in %d days.',
							'Message body text for email sent out when an Ad is about to expire. Use %d as placeholder for the number of days before the Ad expires.');

		// Section: Ad Renewed Message

		$key = $this->add_section($group, __('Ad Renewed Message', 'AWPCP'), 'ad-renewed-message', 10, array($this, 'section'));

		$this->add_setting($key, 'ad-renewed-email-subject', 'Subject for Ad Renewed email',
							'textfield', 'Your classifieds listing "%s" has been successfully renewed.',
							'Subject line for email sent out when an Ad is successfully renewed.');
		$this->add_setting($key, 'ad-renewed-email-body', 'Body for Renew Ad email',
							'textarea', 'Your classifieds listing Ad has been successfully renewed. More information below:',
							'Message body text for email sent out when an Ad is successfully renewed. ');

		// Section: Ad Expired Message

		$key = $this->add_section($group, __('Ad Expired Message', 'AWPCP'), 'ad-expired-message', 10, array($this, 'section'));

		$this->add_setting($key, 'adexpiredsubjectline', 'Subject for Ad Expired email',
							'textfield', 'Your classifieds listing at %s has expired',
							'Subject line for email sent out when an ad has auto-expired');
		$this->add_setting($key, 'adexpiredbodymessage', 'Body for Ad Expired email',
							'textarea', 'This is an automated notification that your classified ad has expired.',
							'Message body text for email sent out when an ad has auto-expired');

		// Section: Advanced Email Configuration

		$key = $this->add_section($group, __('Advanced Email Configuration', 'AWPCP'),
								  'advanced', 10, array($this, 'section'));

		$this->add_setting($key, 'usesmtp', 'Enable external SMTP server',
							'checkbox', 0,
							'Enabled external SMTP server (if emails not processing normally).');
		$this->add_setting($key, 'smtphost', 'SMTP host',
							'textfield', 'mail.example.com',
							'SMTP host (if emails not processing normally).');
		$this->add_setting($key, 'smtpport', 'SMTP port',
							'textfield', '25',
							'SMTP port (if emails not processing normally).');
		$this->add_setting($key, 'smtpusername', 'SMTP username',
							'textfield', 'smtp_username',
							'SMTP username (if emails not processing normally).');
		$this->add_setting($key, 'smtppassword', 'SMTP password',
							'password', '',
							'SMTP password (if emails not processing normally).');

		// (\('(.*?)',(.*?),(.*?),.*?$)
		// //\2\n\t\t$this->add_setting($key, '\2', , , \3, \4);\n\t\t\1

		// save settings to database
		$this->skip = true;
		update_option($this->option, $this->options);
		$this->skip = false;
	}

	public function init() {
		do_action('awpcp_register_settings');

		// save settings to database
		$this->skip = true;
		update_option($this->option, $this->options);
		$this->skip = false;
	}

	public function add_group($name, $slug, $priority) {
		$group = new stdClass();
		$group->name = $name;
		$group->slug = $slug;
		$group->priority = $priority;
		$group->sections = array();

		$this->groups[$slug] = $group;

		return $slug;
	}

	public function add_section($group, $name, $slug, $priority, $callback) {
		$section = new stdClass();
		$section->name = $name;
		$section->slug = $slug;
		$section->priority = $priority;
		$section->callback = $callback;
		$section->settings = array();

		$this->groups[$group]->sections[$slug] = $section;

		return "$group:$slug";
	}

	public function add_setting($key, $name, $label, $type, $default, $helptext, $args=array()) {
		// add the setting to the right section and group

		list($group, $section) = explode(':', $key);

		if (empty($group) || empty($section)) {
			return false;
		}

		if (isset($this->groups[$group]) &&
			isset($this->groups[$group]->sections[$section])) {
			$setting = new stdClass();
			$setting->name = $name;
			$setting->label = $label;
			$setting->helptext = $helptext;
			$setting->default = $default;
			$setting->type = $type;
			$setting->args = $args;

			$this->groups[$group]->sections[$section]->settings[$name] = $setting;
		}

		// make sure the setting is available to other components in the plugin
		if (!isset($this->options[$name])) {
			$this->options[$name] = $default;
		}

		// store the default value
		$this->defaults[$name] = $default;

		return true;
	}

	public function register() {
		foreach ($this->groups as $group) {
			foreach ($group->sections as $section) {
				add_settings_section($section->slug, $section->name, $section->callback, $group->slug);
				foreach ($section->settings as $setting) {
					$callback = array($this, $setting->type);
					$args = array('label_for' => $setting->name, 'setting' => $setting);
					$args = array_merge($args, $setting->args);

					add_settings_field($setting->name, $setting->label, $callback,
									   $group->slug, $section->slug, $args);
				}
			}
		}
	}

	/**
	 * Validates AWPCP settings before being saved.
	 */
	public function validate($options) {
		if ($this->skip) { return $options; }

		$group = awpcp_post_param('group', '');

		// populate array with all plugin options before attempt validation
		$this->load();
		$options = array_merge($this->options, $options);

		$filters = array('awpcp_validate_settings_' . $group, 'awpcp_validate_settings');

		foreach ($filters as $filter) {
			$_options = apply_filters($filter, $options, $group);
			$options = is_array($_options) ? $_options : $options;
		}

		// make sure we have the updated and validated options
		$this->options = $options;

		return $this->options;
	}

	public function load() {
		$options = get_option($this->option);
		$this->options = is_array($options) ? $options : array();
	}

	public function textfield($args, $type='text') {
		$setting = $args['setting'];

		$value = esc_html(stripslashes($this->get_option($setting->name)));

		$html = '<input id="'. $setting->name . '" class="regular-text" ';
		$html.= 'value="' . $value . '" type="' . $type . '" ';
		$html.= 'name="awpcp-options[' . $setting->name . ']" />';
		$html.= strlen($setting->helptext) > 60 ? '<br/>' : '';
		$html.= '<span class="description">' . $setting->helptext . '</span>';

		echo $html;
	}

	public function password($args) {
		return $this->textfield($args, 'password');
	}

	public function checkbox($args) {
		$setting = $args['setting'];

		$value = intval($this->get_option($setting->name));

		$html = '<input type="hidden" value="0" name="awpcp-options['. $setting->name .']" />';
		$html.= '<input id="'. $setting->name . '" value="1" ';
		$html.= 'type="checkbox" name="awpcp-options[' . $setting->name . ']" ';
		$html.= $value ? 'checked="checked" />' : ' />';
		$html.= '<label for="'. $setting->name . '">';
		$html.= '&nbsp;<span class="description">' . $setting->helptext . '</span>';
		$html.= '</label>';

		echo $html;
	}

	public function textarea($args) {
		$setting = $args['setting'];

		$value = esc_html(stripslashes($this->get_option($setting->name)));

		$html = '<textarea id="'. $setting->name . '" class="all-options" ';
		$html.= 'name="awpcp-options['. $setting->name .']">';
		$html.= $value;
		$html.= '</textarea><br/>';
		$html.= '<span class="description">' . $setting->helptext . '</span>';

		echo $html;
	}

	public function select($args) {
		$setting = $args['setting'];
		$options = $args['options'];

		$current = esc_html(stripslashes($this->get_option($setting->name)));

		$html = '<select name="awpcp-options['. $setting->name .']">';
		foreach ($options as $value => $label) {
			if ($value == $current) {
				$html.= '<option value="' . $value . '" selected="selected">' . $label . '</option>';
			} else {
				$html.= '<option value="' . $value . '">' . $label . '</option>';
			}
		}
		$html.= '</select><br/>';
		$html.= '<span class="description">' . $setting->helptext . '</span>';

		echo $html;
	}

	public function radio($args) {
		$setting = $args['setting'];
		$options = $args['options'];

		$current = esc_html(stripslashes($this->get_option($setting->name)));

		$html = '';
		foreach ($options as $value => $label) {
			$id = "{$setting->name}-$value";
			$label = ' <label for="' . $id . '">' . $label . '</label>';

			$html.= '<input id="' . $id . '"type="radio" value="' . $value . '" ';
			$html.= 'name="awpcp-options['. $setting->name .']" ';
			if ($value == $current) {
				$html.= 'checked="checked" />' . $label;
			} else {
				$html.= '>' . $label;
			}
			$html.= '<br/>';
		}
		$html.= '<span class="description">' . $setting->helptext . '</span>';

		echo $html;
	}

	/**
	 * Dummy function to render an (empty) introduction
	 * for each settings section.
	 */
	public function section($args) {
	}

	public function get_option($name, $default='', $reload=false) {
		// reload options
		if ($reload) { $this->load(); }

		if (isset($this->options[$name])) {
			$value = $this->options[$name];
		} else {
			$value = $default;
		}

		// TODO: provide a method for filtering options and move there the code below.
		$strip_slashes_from = array('awpcpshowtheadlayout',
								    'sidebarwidgetaftertitle',
								    'sidebarwidgetbeforetitle',
								    'sidebarwidgetaftercontent',
								    'sidebarwidgetbeforecontent',
								    'adsense',
								    'displayadlayoutcode');
		if (in_array($name, $strip_slashes_from)) {
			$value = strip_slashes_recursive($value);
		}

		return $value;
	}

	public function get_option_default_value($name) {
		if (isset($this->defaults[$name])) {
			return $this->defaults[$name];
		}
		return null;
	}

	/**
	 * @param $force boolean - true to update unregistered options
	 */
	public function update_option($name, $value, $force=false) {
		if (isset($this->options[$name]) || $force) {
			$this->options[$name] = $value;
			update_option($this->option, $this->options);
			return true;
		}
		return false;
	}

	/**
	 * General Settings checks
	 */
	public function validate_general_settings($options, $group) {
		// Check Akismet if they enabled/configured it:
		$setting = 'useakismet';
		if (isset($options[$setting])) {
			$wpcom_api_key = get_option('wordpress_api_key');
			if ($options[$setting] == 1 && !function_exists('akismet_init')) {
				awpcp_flash(__("You cannot enable Akismet SPAM control because you do not have Akismet installed/activated","AWPCP"));
				$options[$setting] = 0;
			} else if ($options[$setting] == 1 && empty($wpcom_api_key)) {
				awpcp_flash(__("You cannot enable Akismet SPAM control because you have not configured Akismet properly","AWPCP"));
				$options[$setting] = 0;
			}
		}

		// Enabling User Ad Management Panel will automatically enable
		// require Registration, if it isn’t enabled. Disabling this feature
		// will not disable Require Registration.
		$setting = 'enable-user-panel';
		if (isset($options[$setting]) && $options[$setting] == 1 && !$options['requireuserregistration']) {
			awpcp_flash(__('Require Registration setting was enabled automatically because you activated the User Ad Management panel.', 'AWPCP'));
			$options['requireuserregistration'] = 1;
		}

		return $options;
	}

	/**
	 * Registration Settings checks
	 */
	public function validate_registration_settings($options, $group) {
		// if Require Registration is disabled, User Ad Management Panel should be
		// disabled as well.
		$setting = 'requireuserregistration';
		if (isset($options[$setting]) && $options[$setting] == 0 && $options['enable-user-panel']) {
			awpcp_flash(__('User Ad Management panel was automatically deactivated because you disabled Require Registration setting.', 'AWPCP'));
			$options['enable-user-panel'] = 0;
		}

		return $options;
	}

	/**
	 * Payment Settings checks
	 */
	public function validate_payment_settings($options, $group) {
		$currency_codes = array('AUD','BRL','CAD','CZK','DKK','EUR',
								'HKD','HUF','ILS','JPY','MYR','MXN',
								'NOK','NZD','PHP','PLN','GBP','SGD',
								'SEK','CHF','TWD','THB','USD');

		$setting = 'paypalcurrencycode';
		if (isset($options[$setting]) &&
			!in_array($options[$setting], $currency_codes)) {

			$message = __("There is a problem with the currency code you have entered. It does not match any of the codes in the list of available currencies provided by PayPal.","AWPCP");
			$message.= "<br/>" . __("The available currency codes are","AWPCP");
			$message.= ":<br/>" . join(' | ', $currency_codes);
			awpcp_flash($message);

			$options[$setting] = 'USD';
		}

		$setting = 'displaycurrencycode';
		if (isset($options[$setting]) &&
			!in_array($options[$setting], $currency_codes)) {

			$message = __("There is a problem with the currency code you have entered. It does not match any of the codes in the list of available currencies provided by PayPal.","AWPCP");
			$message.= "<br/>" . __("The available currency codes are","AWPCP");
			$message.= ":<br/>" . join(' | ', $currency_codes);
			awpcp_flash($message);

			$options[$setting] = 'USD';
		}

		return $options;
	}

	/**
	 * SMTP Settings checks
	 */
	public function validate_smtp_settings($options, $group) {
		// Not sure if this works, but that's what the old code did
		$setting = 'smtppassword';
		if (isset($options[$setting])) {
			$options[$setting] = md5($options[$setting]);
		}

		return $options;
	}

	/**
	 * Classifieds Pages Settings checks
	 */
	public function validate_classifieds_pages_settings($options, $group) {
		global $wpdb, $wp_rewrite;

		$pages = awpcp_pages();
		$pageids = $wpdb->get_results('SELECT page, id FROM ' . AWPCP_TABLE_PAGES, OBJECT_K);

		foreach ($pages as $key => $data) {
			$id = intval($pageids[$key]->id);

			if ($id <= 0 || is_null(get_post($id))) {
				continue;
			}

			$title = add_slashes_recursive($options[$key]);
			$page = array(
				'ID' => $id,
				'post_title' => $title,
				'post_name' => sanitize_title($options[$key]));

			wp_update_post($page);
		}

		flush_rewrite_rules();

		return $options;
	}
}