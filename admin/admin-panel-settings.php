<?php

class AWPCP_Admin_Settings {
	
	public function AWPCP_Admin_Settings() {
		$pages = new AWPCP_Classified_Pages_Settings();
		add_action('init', array($this, 'init'));
	}

	public function init() {
		global $awpcp;
		$page = strtolower($awpcp->admin->title) . '_page_' . 'awpcp-admin-settings';
		add_action('admin_print_styles_' . $page, array($this, 'scripts'));
	}

	public function scripts() {
	}

	public function dispatch() {
		global $awpcp;

		$groups = $awpcp->settings->groups;
		$group = $groups[awpcp_request_param('g', 'pages-settings')];

		ob_start();
			include(AWPCP_DIR . 'admin/templates/admin-panel-settings.tpl.php');
			$content = ob_get_contents();
		ob_end_clean();

		echo $content;
	}
}

class AWPCP_Classified_Pages_Settings {

	public function __construct() {
		add_action('awpcp-admin-settings-page--pages-settings', array($this, 'dispatch'));
	}

	public function dispatch() {
		global $awpcp;

		$nonce = awpcp_post_param('_wpnonce');
		$restore = awpcp_post_param('restore-pages', false);
		if ($restore && wp_verify_nonce($nonce, 'awpcp-restore-pages')) {
			$awpcp->restore_pages();
		}

		$missing = $awpcp->get_missing_pages();

		ob_start();
			include(AWPCP_DIR . 'admin/templates/admin-panel-settings-pages-settings.tpl.php');
			$content = ob_get_contents();
		ob_end_clean();

		echo $content;
	}
}