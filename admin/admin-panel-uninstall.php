<?php

class AWPCP_Admin_Uninstall {
	
	public function AWPCP_Admin_Uninstall() {
		// global $awpcp;
		// $page = strtolower($awpcp->admin->title) . '_page_' . 'awpcp-admin-settings';
		// add_action('admin_print_styles_' . $page, array($this, 'scripts'));
	}

	public function scripts() {
	}

	public function dispatch() {
		global $awpcp, $message;

		$action = awpcp_request_param('action', 'confirm');
		$url = awpcp_current_url();

		if (strcmp($action, 'uninstall') == 0) {
			$awpcp->installer->uninstall();
		} else {
			$dirname = AWPCPUPLOADDIR;
		}

		ob_start();
			include(AWPCP_DIR . 'admin/templates/admin-panel-uninstall.tpl.php');
			$content = ob_get_contents();
		ob_end_clean();

		echo $content;
	}
}