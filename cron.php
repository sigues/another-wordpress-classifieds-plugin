<?php 

function awpcp_cron_schedules($schedules) {
	$schedules['monthly'] = array(
		'interval'=> 2592000,
		'display'=>  __('Once Every 30 Days')
	);
	return $schedules;
}

// ensure we get the expiration hooks scheduled properly:
function awpcp_schedule_activation() {
	if (!wp_next_scheduled('doadexpirations_hook')) {
		wp_schedule_event(time(), 'hourly', 'doadexpirations_hook');
	}

	if (!wp_next_scheduled('doadcleanup_hook')) {
		wp_schedule_event(time(), 'monthly', 'doadcleanup_hook');
	}
	
	if (!wp_next_scheduled('awpcp_ad_renewal_email_hook')) {
		wp_schedule_event(time(), 'daily', 'awpcp_ad_renewal_email_hook');
	}

	if (!wp_next_scheduled('awpcp-clean-up-payment-transactions')) {
		wp_schedule_event(time(), 'daily', 'awpcp-clean-up-payment-transactions');
	}

	add_action('doadexpirations_hook', 'doadexpirations');
	add_action('doadcleanup_hook', 'doadcleanup');
	add_action('awpcp_ad_renewal_email_hook', 'awpcp_ad_renewal_email');
	add_action('awpcp-clean-up-payment-transactions', 'awpcp_clean_up_payment_transactions');
	
	// wp_schedule_event(time() + 60, 'hourly', 'doadexpirations_hook');
	// wp_schedule_event(time() + 10, 'monthly', 'doadcleanup_hook');
	// wp_schedule_event(time(), 'daily', 'awpcp_ad_renewal_email_hook');
	// wp_schedule_event(time(), 'daily', 'awpcp-clean-up-payment-transactions');

	// debug('System date is: ' . date('d-m-Y H:i:s'),
	// 	  'Ad Expiration: ' . date('d-m-Y H:i:s', wp_next_scheduled('doadexpirations_hook')),
	// 	  'Ad Cleanup: ' . date('d-m-Y H:i:s', wp_next_scheduled('doadcleanup_hook')),
	// 	  'Ad Renewal Email: ' . date('d-m-Y H:i:s', wp_next_scheduled('awpcp_ad_renewal_email_hook')),
	// 	  'Payment transactions: ' . date('d-m-Y H:i:s', wp_next_scheduled('awpcp-clean-up-payment-transactions')));
}


/*
 * Function to disable ads run hourly
 */
function doadexpirations() {
	global $wpdb, $nameofsite, $thisadminemail;

	$adexpireafter = get_awpcp_option('addurationfreemode');
	$notify_admin = get_awpcp_option('notifyofadexpired');

	// disable the ads or delete the ads?
	// 1 = disable, 0 = delete
	$disable_ads = get_awpcp_option('autoexpiredisabledelete');

	$expiredid = array();
	$adstodelete = '';

	// allow users to use %s placeholder for the website name in the subject line
	$subject = get_awpcp_option('adexpiredsubjectline');
	$subject = sprintf($subject, $nameofsite);
	$bodybase = get_awpcp_option('adexpiredbodymessage');

	$admin_email = get_option('admin_email');

	$sql = 'select ad_id from ' . AWPCP_TABLE_ADS . ' where ad_enddate <= NOW() and disabled != 1';
	$ads = $wpdb->get_results($sql, ARRAY_A);

	foreach ($ads as $ad) {
		$expiredid[] = $ad['ad_id'];
		$adid = $ad['ad_id'];

		if(get_awpcp_option('notifyofadexpiring') == 1 && $disable_ads) {
			$user_email = get_adposteremail($adid);

			if ('' == $user_email) continue; // no email, can't send a message without it.

			$adtitle = get_adtitle($adid);
			$adcontact = get_adpostername($adid);
			$adstartdate = date("D M j Y G:i:s", strtotime(get_adstartdate($adid)));

			$body = $bodybase;
			$body.= "\n\n";
			$body.= __("Listing Details", "AWPCP");
			$body.= "\n\n";
			$body.= __("Ad Title:", "AWPCP");
			$body.= " $adtitle";
			$body.= "\n\n";
			$body.= __("Posted:", "AWPCP");
			$body.= " $adstartdate";
			$body.= "\n\n";
			$body.= __("Renew your ad by visiting:", "AWPCP");
			$body.= " " . awpcp_get_renew_ad_url($adid);
			$body.= "\n\n";

			awpcp_process_mail($admin_email, $user_email, $subject, $body, $nameofsite, $admin_email);

			if ( $notify_admin ) {
				awpcp_process_mail($admin_email, $admin_email, $subject, $body, $nameofsite, $admin_email);
			}
		}
	}

	$ads = AWPCP_Ad::find(sprintf('ad_id IN (%s)', join(',' , $expiredid)));
	foreach ($ads as $ad) {
		$ad->disable();
	}
	
	$adstodelete = join(',' , $expiredid);

	if ('' != $adstodelete) {

		// disable images
		$query = 'update ' . AWPCP_TABLE_ADPHOTOS . " set disabled=1 WHERE ad_id IN ($adstodelete)";
		$res = awpcp_query($query, __LINE__);
	  
		// Disable the ads
		$query="UPDATE " . AWPCP_TABLE_ADS . " set disabled=1, disabled_date = NOW() WHERE ad_id IN ($adstodelete)";
		$res = awpcp_query($query, __LINE__);
	}
}


/*
 * Function run once per month to cleanup disabled / deleted ads.
 */
function doadcleanup() {
	global $wpdb;

	//If they set the 'disable instead of delete' flag, we just return and don't do anything here.
	if (get_awpcp_option('autoexpiredisabledelete') == 1) return;

	// Get the IDs of the ads to be deleted (those that are disabled more than 30 days ago)
	$query="SELECT ad_id FROM " . AWPCP_TABLE_ADS . " WHERE disabled=1 and (disabled_date + INTERVAL 30 DAY) < CURDATE()";
	$res = awpcp_query($query, __LINE__);

	$expiredid=array();
	if (mysql_num_rows($res)) {
		while ($rsrow=mysql_fetch_row($res)) {
			$expiredid[]=$rsrow[0];
		}
	}

	$ads = AWPCP_Ad::find(sprintf('WHERE ad_id IN (%s)', join("','", $expiredid)));
	foreach ($ads as $ad) {
		$ad->delete();
	}
}


/**
 * Check if any Ad is about to expire and send an email to the poster.
 *
 * This functions runs daily.
 */
function awpcp_ad_renewal_email() {
	global $wpdb, $nameofsite, $thisadminemail;

	if (!(get_awpcp_option('sent-ad-renew-email') == 1)) {
		return;
	}

	$threshold = intval(get_awpcp_option('ad-renew-email-threshold'));

	$query = 'ad_enddate <= ADDDATE(NOW(), INTERVAL %d DAY) AND ';
	$query.= 'disabled != 1 AND renew_email_sent != 1';
	$ads = AWPCP_Ad::find($wpdb->prepare($query, $threshold));

	$subject = get_awpcp_option('renew-ad-email-subject');
	$subject = sprintf($subject, $threshold);

	foreach ($ads as $ad) {
		$href = awpcp_get_renew_ad_url($ad->ad_id);	

		// awpcp_process_mail doesn't support HTML
		$body = get_awpcp_option('renew-ad-email-body');
		$body = sprintf($body, $threshold) . "\n\n";
		$body.= __('Listing Details are below:', 'AWPCP') . "\n\n";
		$body.= __('Title', 'AWPCP') . ": " . $ad->ad_title . "\n";
		$body.= __('Posted on', 'AWPCP') . ": " . $ad->get_start_date() . "\n";
		$body.= __('Expires on', 'AWPCP') . ": " . $ad->get_end_date() . "\n\n";
		$text = __('You can renew your Ad visiting this link: %s', 'AWPCP');
		$body.= sprintf($text, $href);

		$result = awpcp_process_mail($thisadminemail, $ad->ad_contact_email, $subject, 
						   $body, $nameofsite, $thisadminemail);

		if ($result == 1) {
			$ad->renew_email_sent = true;
			$ad->save();
		}
	}
}


/**
 * Remove incomplete payment transactions
 */
function awpcp_clean_up_payment_transactions() {
	global $wpdb;

	$sql = 'SELECT option_name, option_value FROM ' . $wpdb->options . ' ';
	$sql.= "WHERE option_name LIKE 'awpcp-payment-transaction-%%' ";
	$sql.= 'ORDER BY option_id';

	$results = $wpdb->get_results($sql);

	$threshold = current_time('mysql') - 2592000;
	$threshold = current_time('mysql') - 6*60*60;

	foreach ((array) $results as $row) {
		$name = $row->option_name;
		$attributes = maybe_unserialize($row->option_value);

		$created = strtotime(awpcp_array_data('__created__', false, $attributes));
		$updated = strtotime(awpcp_array_data('__updated__', false, $attributes));
		$completed = strtotime(awpcp_array_data('completed', false, $attributes));

		if ($created && $completed) {
			// debug('completed', date('Y-m-d', $created), date('Y-m-d', $threshold), $name, $attributes);
		}

		if ((!$created || $created < $threshold) && !$completed) {
			// debug('delete', date('Y-m-d', $created), date('Y-m-d', $threshold), $name, $attributes);
			delete_option($name);
		}
	}
}