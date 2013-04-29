<?php 

class AWPCP_Place_Ad_Page {

	public $error = false;
	public $active = false;

	public function AWPCP_Place_Ad_Page() {
		add_action('init', array($this, 'init'));
		add_action('wp_footer', array($this, 'print_scripts'));
		add_action('admin_footer', array($this, 'print_scripts'));
	}

	public function init() {
		$src = AWPCP_URL . 'js/extra-fields.js';
		wp_register_script('awpcp-extra-fields', $src, array('jquery'), '1.0', true);
	}

	public function print_scripts() {
		if (!$this->active) {
			return;
		}
		wp_print_scripts('awpcp-extra-fields');
	}

	public function dispatch() {
		return awpcpui_process_placead();
	}

	// public function _dispatch($action='place-ad') {
	// 	$action = awpcp_post_param('action', $action);

	// 	switch ($action) {
	// 		case 'place-ad':
	// 			$content = $this->place();
	// 			break;
	// 		case 'edit-ad':
	// 			$content = $this->edit();
	// 			break;
	// 		case 'save-ad':
	// 		case 'update-ad':
	// 			$content = $this->save();
	// 			break;
	// 		case 'upload-images':
	// 			break;
	// 		case 'payment':
	// 			break;
	// 	}

	// 	if ($this->error) {
	// 		ob_start();
	// 			include(AWPCP_DIR . 'frontend/templates/page-error.tpl.php');
	// 			$content = ob_get_contents();
	// 		ob_end_clean();
	// 	}

	// 	return $content;
	// }
}