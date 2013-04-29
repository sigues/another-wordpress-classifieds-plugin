<?php
/**
 * Ajax file for use with flagging ads 
 *
 */
	require_once('../../../wp-load.php');

	if (!check_ajax_referer('flag_ad', 'n')) { 
		echo 0;
		die;
	}

	if ( '' == $_GET['a'] ) { 
		echo 0;
		die;
	} else { 
		$adid = intval($_GET['a']);
	}

	global $wpdb;

	if ( $adid > 0 ) { 
	    $sql = 'update '.$wpdb->prefix.'awpcp_ads set flagged = 1 where ad_id = "'.$adid.'"';
	    $res = $wpdb->query($sql);
	}
	echo 1;
	die;
?>