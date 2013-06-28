<?php

function awpcp_user_can_post_ad() {
	$is_admin = awpcp_current_user_is_admin();

	$place_ad_page_id = awpcp_get_page_id_by_ref('place-ad-page-name');
	$url_place_ad_page = get_permalink($place_ad_page_id);
	$output = '';

	// Handle if only admin can post and non admin user arrives somehow on post ad page
	if (get_awpcp_option('onlyadmincanplaceads') && ($is_admin != 1)) {
		$output .= "<p>";
		$output .= __("You do not have permission to perform the function you are trying to perform. Access to this page has been denied","AWPCP");
		$output .= "</p>";
		return $output;
	}

	// Handle if user must be registered
	if (get_awpcp_option('requireuserregistration') && !is_user_logged_in()) {
		$message = __('Hi, You need to be a registered user to post Ads in this website. Please use the form below to login or click the link to register.', 'AWPCP');
		$output .= awpcp_login_form($message, $url_place_ad_page);
		return $output;
	}

	return true;
}


/**
 * Return an Ad Fee
 *
 * @since 2.0.7
 */
function awpcp_get_fee($id) {
	global $wpdb;

	$sql = 'SELECT * FROM ' . AWPCP_TABLE_ADFEES . ' WHERE adterm_id = %d';
	$result = $wpdb->get_row($wpdb->prepare($sql, $id));

	return is_object($result) ? $result : null;
}


/**
 * Return an array of Ad Fees
 *
 * @since 2.0.7
 */
function awpcp_get_fees() {
	global $wpdb;

	$sql = 'SELECT * FROM ' . AWPCP_TABLE_ADFEES . ' ORDER BY adterm_name ASC';
	$results = $wpdb->get_results($sql);

	return is_array($results) ? $results : array();
}


/**
 * Returns an array of the defined Ad Term Fees if board is paid or a
 * free Ad Term Fee if board is free.
 */
function awpcp_payment_terms_fees() {
	global $wpdb;

	$terms = array();
	$type_slug = 'ad-term-fee';
	$type_name = __('<strong>Ad Fees</strong> - Pay for a single Ad', 'AWPCP');
	$payment_required = get_awpcp_option('freepay') == 1;

	if ($payment_required) {
		$results = awpcp_get_fees();

		foreach ($results as $result) {
			$term = new stdClass();
			$term->id = $result->adterm_id;
			$term->type = $type_slug;
			$term->type_name = $type_name;
			$term->name = $result->adterm_name;
			$term->price = $result->amount;
			$term->ads_allowed = 1;
			$term->images_allowed = $result->imagesallowed;
			$term->duration = $result->rec_period . ' ' . awpcp_humanize_payment_term_duration($result->rec_period, $result->rec_increment);
			$term->description =  '';

			$term->categories = array_filter(split(',', $result->categories), 'intval');
			$term->characters_allowed = $result->characters_allowed;

			$term->recurring = $result->recurring;
			$term->period = $result->rec_period;
			$term->increment = $result->rec_increment;

			$terms[] = $term;
		}
	} else {
		$term = new stdClass();

		$term->recurring = 1;
		$term->period = get_awpcp_option('addurationfreemode');
		$term->increment = 'D';

		$term->id = 0;
		$term->type = $type_slug;
		$term->type_name = $type_name;
		$term->name = 'Free';
		$term->price = 0;
		$term->categories = array_filter(array(), 'intval');
		$term->characters_allowed = get_awpcp_option('maxcharactersallowed');
		$term->ads_allowed = 1;
		$term->images_allowed = get_awpcp_option('imagesallowedfree');
		$term->duration = sprintf("%s %s", $term->period, $term->increment);
		$term->description = '';

		$terms[] = $term;
	}

	return $terms;
}


function awpcp_payment_terms($type=null, $id=null) {
	$terms = awpcp_payment_terms_fees();
	$terms = apply_filters('awpcp-payment-terms', $terms);

	if (!is_null($type) && !is_null($id)) {
		$term = null;
		foreach ($terms as $item) {
			if ($item->type == $type && $item->id == $id) {
				$term = $item;
				break;
			}
		}
		return $term;
	}

	return $terms;
}


/**
 * Returns a list of Payment Terms that can be used to post an Ad
 * on behalf of another user.
 *
 * Admins will choose one of these payment terms to define Ad duration,
 * number of allowed images nad other Ad's attributes.
 */
function awpcp_user_payment_terms($user_id) {
	static $ad_term_fees = array();

	// we return the same payment terms, no matter who is the user
	if (empty($ad_term_fees)) {
		array_splice($ad_term_fees, 0, 0, awpcp_payment_terms_fees());
	}

	$payment_terms = apply_filters('awpcp-user-payment-terms', $ad_term_fees, $user_id);

	return $payment_terms;
}


function awpcp_payment_methods() {
	$methods = apply_filters('awpcp-payment-methods', array());

	if (get_awpcp_option('activatepaypal') == 1) {
		$method = new stdClass();
		$method->slug = 'paypal';
		$method->name = 'PayPal';
		$method->icon = AWPCP_URL . 'images/payments-paypal.gif';
		$method->description = '';
		$methods[] = $method;
	}

	if (get_awpcp_option('activate2checkout') == 1) {
		$method = new stdClass();
		$method->slug = '2checkout';
		$method->name = '2Checkout';
		$method->icon = 'https://www.2checkout.com/images/paymentlogoshorizontal.png';
		$method->icon = AWPCP_URL . 'images/payments-2checkout.png';
		$method->description = '';
		$methods[] = $method;
	}

	return $methods;
}


function awpcp_ad_term_fee_transaction_processed($texts, $txn) {
	global $wpdb;

	$term_id = $txn->get('payment-term-id');
	$term_type = $txn->get('payment-term-type');
	if (strcmp($term_type, 'ad-term-fee') !== 0) {
		return $texts;
	}

	$status = $txn->get('payment-status');
	if ($status === AWPCP_Payment_Transaction::$PAYMENT_STATUS_COMPLETED ||
		$status === AWPCP_Payment_Transaction::$PAYMENT_STATUS_PENDING) {
		$updated = 'buys + 1';
		$condition = '';
	} else { // FAILED
		$updated = 'buys - 1';
		$condition = 'AND buys > 0';
	}

	$sql = 'UPDATE ' . AWPCP_TABLE_ADFEES . " SET buys = $updated ";
	$sql.= 'WHERE adterm_id = %d ' . $condition;
	$wpdb->query($wpdb->prepare($sql, intval($term_id)));

	// TODO: send email?
	return $texts;
}


function awpcp_place_ad_duration($tuple, $txn) {
	if ($txn->get('free') === true) {
		$period = get_awpcp_option('addurationfreemode');
		$period = empty($period) ? 3650 : $period;
		return array($period, 'DAY');
	}

	if (strcmp($txn->get('payment-term-type'), 'ad-term-fee') !== 0) {
		return $tuple;
	}

	if ($txn->get('payment-status') == AWPCP_Payment_Transaction::$PAYMENT_STATUS_FAILED) {
		return array(0, 'DAY');
	}

	global $wpdb;

	$term_id = $txn->get('payment-term-id');
	$duration = awpcp_get_term_duration($term_id);

	if (empty($duration)) {
		return $tuple;
	}

	$increments = array('D' => 'DAY', 'W' => 'WEEK', 'M' => 'MONTH', 'Y' => 'YEAR');
	return array($duration['duration'], $increments[$duration['increment']]);
}
add_filter('awpcp-place-ad-duration', 'awpcp_place_ad_duration', 10, 2);


function awpcp_get_ad_images_information($ad_id) {
	if ($ad_id <= 0) {
		return __('An unexpected error ocurred. No Ad was specified.', 'AWPCP');
	}

	if (get_awpcp_option('imagesallowdisallow') != 1) {
		$images_allowed = 0;
	} else {
		$images_allowed = awpcp_get_ad_number_allowed_images($ad_id);
	}

	if ($images_allowed > 0) {
		$images_uploaded = get_total_imagesuploaded($ad_id);
		$images_left = max($images_allowed - $images_uploaded, 0);
	} else {
		$images_uploaded = 0;
		$images_left = 0;
	}

	return array($images_allowed, $images_uploaded, $images_left);
}


function awpcp_validate_ad_details($form_values=array(), &$form_errors=array(), $edit=false) {
	global $hasextrafieldsmodule;

	// Check for ad title
	if (!isset($form_values['adtitle']) || empty($form_values['adtitle'])) {
		$form_errors[] = __("You did not enter a title for your ad","AWPCP");
		// $error=true;
		// $adtitlemsg="<li class=\"erroralert\">";
		// $adtitlemsg.=
		// $adtitlemsg.="</li>";
	}

	// Check for ad details
	if (!isset($form_values['addetails'] ) || empty($form_values['addetails'] )) {
		$form_errors[] = __("You did not enter any text for your ad. Please enter some text for your ad","AWPCP");
	}

	// Check for ad category
	if (!isset($form_values['adcategory']) || empty($form_values['adcategory']) && 'editad' != $_REQUEST['adaction'] ) {
		$form_errors[] = __("You did not select a category for your ad. Please select a category for your ad","AWPCP");
	}

	$user_id = awpcp_array_data('user_id', 0, $form_values);
	$user_payment_term = awpcp_array_data('user_payment_term', '', $form_values);
	if (get_awpcp_option('freepay') == 1 && $user_id > 0 && empty($user_payment_term) && !$edit) {
		$form_errors[] = __('You did not select a Payment Term. Please select a Payment Term for this Ad.', 'AWPCP');
	}

	// Check for ad poster's name
	if (!isset($form_values['adcontact_name']) || empty($form_values['adcontact_name'])) {
		$form_errors[] = __("You did not enter your name. Your name is required","AWPCP");

	}

	// Check for ad poster's email address
	if (!isset($form_values['adcontact_email']) || empty($form_values['adcontact_email'])) {
		$form_errors[] = __("You did not enter your email. Your email is required","AWPCP");
	}

	// Check if email address entered is in a valid email address format
	if (!isValidEmailAddress($form_values['adcontact_email'])) {
		$form_errors[] = __("The email address you entered was not a valid email address. Please check for errors and try again","AWPCP");
	}

	// If phone field is checked and required make sure phone value was entered
	if ((get_awpcp_option('displayphonefield') == 1) &&
		(get_awpcp_option('displayphonefieldreqop') == 1))
	{
		if (!isset($form_values['adcontact_phone']) || empty($form_values['adcontact_phone'])) {
			$form_errors[] = __("You did not enter your phone number. Your phone number is required","AWPCP");
		}
	}

	// If city field is checked and required make sure city value was entered
	if ((get_awpcp_option('displaycityfield') == 1) &&
		(get_awpcp_option('displaycityfieldreqop') == 1))
	{
		if (!isset($form_values['adcontact_city']) || empty($form_values['adcontact_city'])) {
			$form_errors[] = __("You did not enter your city. Your city is required","AWPCP");
		}
	}

	// If state field is checked and required make sure state value was entered
	if ((get_awpcp_option('displaystatefield') == 1) &&
		(get_awpcp_option('displaystatefieldreqop') == 1))
	{
		if (!isset($form_values['adcontact_state']) || empty($form_values['adcontact_state'])) {
			$form_errors[] = __("You did not enter your state. Your state is required", "AWPCP");
		}
	}

	// If country field is checked and required make sure country value was entered
	if ((get_awpcp_option('displaycountryfield') == 1) &&
		(get_awpcp_option('displaycountryfieldreqop') == 1))
	{
		if (!isset($form_values['adcontact_country']) || empty($form_values['adcontact_country'])) {
			$form_errors[] = __("You did not enter your country. Your country is required","AWPCP");
		}
	}

	// If county/village field is checked and required make sure county/village value was entered
	if ((get_awpcp_option('displaycountyvillagefield') == 1) &&
		(get_awpcp_option('displaycountyvillagefieldreqop') == 1))
	{
		if (!isset($form_values['ad_county_village']) || empty($form_values['ad_county_village'])) {
			$form_errors[] = __("You did not enter your county/village. Your county/village is required","AWPCP");
		}
	}

	if (get_awpcp_option('noadsinparentcat')) {
		if (!category_is_child($form_values['adcategory'])) {
			$awpcpcatname=get_adcatname($form_values['adcategory']);
			$form_errors[] = sprintf(__("You cannot list your ad in top level categories. You need to select a sub category of %s to list your ad under","AWPCP"), $awpcpcatname);
		}
	}

	// Terms of service required and accepted?
	if (!$edit && get_awpcp_option('requiredtos') && !isset($form_values['tos'])) {
		$form_errors[] = __("You did not accept the terms of service","AWPCP");
	}

	// If price field is checked and required make sure a price has been entered
	if ((get_awpcp_option('displaypricefield') == 1) &&
		(get_awpcp_option('displaypricefieldreqop') == 1))
	{
		if (!isset($form_values['ad_item_price']) || empty($form_values['ad_item_price'])) {
			$form_errors[] = __("You did not enter the price of your item. The item price is required.","AWPCP");
		}
	}

	// Make sure the item price is a numerical value
	if (get_awpcp_option('displaypricefield') == 1) {
		if (isset($form_values['ad_item_price']) && !empty($form_values['ad_item_price']) &&
			!is_numeric($form_values['ad_item_price']) )
		{
			$form_errors[] = __("You have entered an invalid item price. Make sure your price contains numbers only. Please do not include currency symbols.","AWPCP");
		}
	}

	// If website field is checked and required make sure website value was entered
	if ((get_awpcp_option('displaywebsitefield') == 1) &&
		(get_awpcp_option('displaywebsitefieldreqop') == 1))
	{
		if (!isset($form_values['websiteurl']) || empty($form_values['websiteurl'])) {
			$form_errors[] = __("You did not enter your website address. Your website address is required.","AWPCP");
		}
	}

	//If they have submitted a website address make sure it is correctly formatted
	if (isset($form_values['websiteurl']) && !empty($form_values['websiteurl']) ) {
		if ( !isValidURL($form_values['websiteurl']) ) {
			$form_errors[] = __("Your website address is not properly formatted. Please make sure you have included the http:// part of your website address","AWPCP");
		}
	}

	$thesum = ($form_values['numval1'] +  $form_values['numval2']);

	if ((get_awpcp_option('contactformcheckhuman') == 1) && !is_admin()) {
		if (!isset($form_values['checkhuman']) || empty($form_values['checkhuman'])) {
			$form_errors[] = __("You did not solve the math problem. Please solve the math problem to proceed.","AWPCP");
		} else if ($form_values['checkhuman'] != $thesum) {
			$form_errors[] = __("Your solution to the math problem was incorrect. Please try again","AWPCP");
		}
	}

	if (get_awpcp_option('useakismet')) {
		// XXX: check why it isn't working so well
		if (awpcp_check_spam($form_values['adcontact_name'], $form_values['websiteurl'], $form_values['adcontact_email'], $form_values['addetails'])) {
			//Spam detected!
			$form_errors[] = __("Your ad was flagged as spam.  Please contact the administrator of this site.","AWPCP");
		}
	}

	if ($hasextrafieldsmodule == 1) {
		//Allow backward compatibility with old extra fields, if they didn't upgrade:
		if (function_exists('validate_extra_fields_form')) {
			$x_field_errors_msg = validate_extra_fields_form($form_values['adcategory']);
		} else if (function_exists('validate_x_form')) {
			$x_field_errors_msg = validate_x_form();
		}

		if (isset($x_field_errors_msg) && !empty($x_field_errors_msg)) {
			$form_errors[] = $x_field_errors_msg;
		}
	}

	$form_errors = array_filter($form_errors);
	return empty($form_errors);
}


function awpcp_place_ad_payment_step($form_values=array(), $form_errors=array()) {
	global $current_user;
	get_currentuserinfo();

	$is_admin = awpcp_current_user_is_admin();

	$result = awpcp_user_can_post_ad();
	if ($result !== true) {
		return $result;
	}

	wp_enqueue_script('awpcp-page-place-ad');

	// Create a Transaction object to pass information through different steps
	$transaction = awpcp_array_data('awpcp-txn', 0, $form_values);
	$transaction = AWPCP_Payment_Transaction::find_or_create($transaction);
	$transaction->set('user-id', $current_user->ID);

	$form_values['awpcp-txn'] = $transaction->id;

	// Modules should add relevant information to the transaction object
	// to later (when saving the Ad) identify the reason why payment wasn't requried
	// $request_payment = adtermsset() && !$is_admin;
	$request_payment = get_awpcp_option('freepay') == 1 && !$is_admin;
	$request_payment = apply_filters('awpcp-should-request-payment', $request_payment, $transaction);

	// No payment required. Skip payment step and go to Ad Details step
	if (!$request_payment) {
		$transaction->set('free', true);
		$transaction->save();
		return awpcp_place_ad_details_step(array(), array(), $transaction);
	}

	$payment_terms = awpcp_payment_terms();

	// // If none of the payment terms require the user to spent money
	// // skip to the next step
	// $prices = awpcp_get_properties($payment_terms, 'price', 0);
	// if (array_sum($prices) <= 0) {
	// 	$transaction->set('free', true);
	// 	$transaction->save();
	// 	return awpcp_place_ad_details_step(array(), array(), $transaction);
	// }

	$transaction->save();

	$categories = awpcp_get_categories();
	$payment_methods = awpcp_payment_methods();
	$header = apply_filters('awpcp-place-ad-payment-step-form-header', array());

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/page-place-ad-payment-step.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	$html = apply_filters('awpcp-place-ad-payment-step-form', $html);
	return $html;
}


function awpcp_place_ad_checkout_step($form_values=array(), $form_errors=array()) {
	$values = empty($form_values) ? $_REQUEST : $form_values;
	$action = $form_values['a'] = awpcp_array_data('a', 'checkout', $values);
        
        $category = $form_values['category'] = intval(awpcp_array_data('category', 0, $values));
	$term = $form_values['payment-term'] = awpcp_array_data('payment-term', '', $values);
	$method = $form_values['payment-method'] = awpcp_array_data('payment-method', '', $values);

	$transaction = $form_values['awpcp-txn'] = awpcp_array_data('awpcp-txn', 0, $values);
	$transaction = AWPCP_Payment_Transaction::find_by_id($transaction);


	// begin validation

	if (is_null($transaction)) {
		return __('There was an error processing your Payment Request. Please try again or contact and Administrator.', 'AWPCP');
	}

	if ($category <= 0) {
		$form_errors['category'] = __('Ad Category field is required', 'AWPCP');
	}

	$category_name = get_adcatname($category);
	if (get_awpcp_option('noadsinparentcat')) {
		if (!category_is_child($category)) {
			$msg = __("You cannot list your Ad in top level categories. You need to select a sub category of %s to list your Ad under", "AWPCP");
			$msg = sprintf($msg, $category_name);
			$form_errors['category'] = $msg;
		}
	}

	$match = preg_match('/([\w_-]+)-(\d+)/', $term, $matches);
	$term = $match ? awpcp_payment_terms($matches[1], $matches[2]) : null;

	if (is_null($term)) {
		$form_errors['payment-term'] = __('You should choose one of the available Payment Terms', 'AWPCP');
	}

	if (!empty($categories) && !in_array($category, $term->categories)) {
		$msg = __('The Payment Term you selected is no valid for the category %s', 'AWPCP');
		$form_errors['payment-term'] = sprintf($msg, $category_name);
	}

	if (empty($method) && $term->price > 0) {
		$form_errors['payment-method'] = __('You should choose one of the available Payment Methods', 'AWPCP');
	}

	if (!empty($form_errors)) {
		return awpcp_place_ad_payment_step($form_values, $form_errors);
	}

	$transaction->set('category', $category);
	$transaction->set('payment-term-type', $term->type);
	$transaction->set('payment-term-id', $term->id);
	$transaction->set('payment-method', $method);
	$transaction->set('original-amount', $term->price);
	$amount = apply_filters('awpcp-place-ad-payment-amount', $term->price, $transaction);
	$transaction->set('amount', $amount);
        
        session_start();
        
        $_SESSION["lineas"]=$values['renglones'];
        $_SESSION["fondo"]=$values['fondo_negro'];
        $_SESSION["marco"]=$values['marco'];
        $texto = "";
        for($x=1;$x<=$values['renglones'];$x++){
            $nombre_renglon = "renglon_".$x;
            $texto.=$values[$nombre_renglon].'\n\n';
        }
        $_SESSION["texto"]=$texto;
        if ($amount <= 0) {
		// A payment term with no ID means the board is free (no Ad Fees are being used).
		// Subscriptions or other payment terms may be enabled, however.
		if ($term->id <= 0) {
			$transaction->set('free', true);
		}
		$transaction->save();
		return awpcp_place_ad_details_step(array(), array(), $transaction);
	}

	// begin checkout step

	$transaction->add_item($term->id, $term->name, $term->period, $term->increment, $values['renglones']);

	$transaction->set('success-redirect', awpcp_current_url());
	$transaction->set('success-form', array('a' => 'post-checkout'));

	$transaction->set('cancel-redirect', awpcp_current_url());
	$transaction->set('cancel-form', $form_values);

	// XXX: no longer used?
	// TODO: fix coupons module
	do_action('awpcp-place-ad-write-payment-information', $transaction);

	$transaction->save();

	$header = apply_filters('awpcp-place-ad-checkout-step-form-header', array(), $form_values, $transaction);
	$text = __("Please click the button below to submit payment. You'll be asked to pay <strong>%0.2f</strong>", 'AWPCP');
	$text = apply_filters('awpcp-place-ad-checkout-step-form-text', $text, $form_values, $transaction);
	$checkout_form = apply_filters('awpcp-checkout-form', '', $transaction);

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/page-place-ad-checkout-step.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}


function awpcp_place_ad_details_step($form_values=array(), $form_errors=array(), $transaction=null) {
	if (is_null($transaction)) {
		$transaction = $form_values['awpcp-txn'] = awpcp_request_param('awpcp-txn');
		$transaction = AWPCP_Payment_Transaction::find_by_id($transaction);
	} else {
		$form_values['awpcp-txn'] = $transaction->id;
	}

	if (is_null($transaction)) {
		return __("Hi, Payment is required for posting Ads in this website and we can't find a Payment transaction asssigned you. You can't post Ads this time. If you think this is an error please contact the website Administrator.", 'AWPCP');
		// $user_can_post_ad = awpcp_user_can_post_ad();
		// if ($user_can_post_ad !== true) {
		// 	return $user_can_post_ad;
		// }

		// $user_can_skip_payment = awpcp_user_can_skip_payment();
		// if ($user_can_skip_payment !== true) {
		// 	return $user_can_skip_payment;
		// }

		// $show_category_field = true;
		// $category = $form_values['adcategory'] = '';
	}

	$completed = $transaction->get('completed', null);
	if (!is_null($completed)) {
		$msg = __("It looks like you're trying to place an Ad with an already processed transaction. This transaction was successfully processed on %s", 'AWPCP');
		return sprintf($msg, $completed);
	}

	// if no category was set, we should ask the user for the category field
	$category = $form_values['adcategory'] = $transaction->get('category');
	$show_category_field = empty($category);

	wp_enqueue_script('awpcp-page-place-ad');

	$html = load_ad_post_form($adid='', $action='', $awpcppagename='',
		$adtermid='', $editemail='', $adaccesskey='', $adtitle='',
		$adcontact_name='', $adcontact_phone='', $adcontact_email='',
		$adcategory=$category, $adcontact_city='', $adcontact_state='',
		$adcontact_country='', $ad_county_village='', $ad_item_price='',
		$addetails='', $adpaymethod='', $offset='', $results='', $ermsg='',
		$websiteurl='', $checkhuman='', $numval1='', $numval2='',
		$post_url='', $show_category_field=$show_category_field,
		$transaction_id=$form_values['awpcp-txn']);

	// TODO: update handlers
	$html = apply_filters('awpcp-place-ad-details-form', $html);

	return $html;
}


function awpcp_place_ad_save_details_step($form_values=array(), &$form_errors=array(), $edit=false) {
	$values = empty($form_values) ? $_REQUEST : $form_values;

	if (!$edit) {
		$transaction = $form_values['awpcp-txn'] = awpcp_array_data('awpcp-txn', 0, $values);
		$transaction = AWPCP_Payment_Transaction::find_by_id($transaction);

		if (is_null($transaction)) {
			return __("Hi, Payment is required for posting Ads in this website and we can't find a Payment transaction asssigned you. You can't post Ads this time. If you think this is an error please contact the website Administrator.", 'AWPCP');
		}
	} else {
		$transaction = null;
	}

	$adid = $form_values['adid'] = clean_field(awpcp_array_data('adid', '', $values));
	$adterm_id = $form_values['adterm_id'] = clean_field(awpcp_array_data('adtermid', '', $values));
	$adkey = $form_values['adkey'] = clean_field(awpcp_array_data('adkey', '', $values));
	$editemail = $form_values['editemail'] = clean_field(awpcp_array_data('editemail', '', $values));
	$adcontact_email = $form_values['adcontact_email'] = clean_field(awpcp_array_data('adcontact_email', '', $values));
	$adcategory = $form_values['adcategory'] = clean_field(awpcp_array_data('adcategory', '', $values));

	$adtitle = $form_values['adtitle'] = clean_field(awpcp_array_data('adtitle', '', $values));
	$adtitle = $form_values['adtitle'] = strip_html_tags($adtitle);

	$adcontact_name = clean_field(awpcp_array_data('adcontact_name', '', $values));
	$adcontact_name = $form_values['adcontact_name'] = strip_html_tags($adcontact_name);

	$adcontact_phone = clean_field(awpcp_array_data('adcontact_phone', '', $values));
	$adcontact_phone = $form_values['adcontact_phone'] = strip_html_tags($adcontact_phone);

	$ad_item_price = clean_field(awpcp_array_data('ad_item_price', '', $values));
	$ad_item_price = $form_values['ad_item_price'] = str_replace(",", '', $ad_item_price);

	$addetails = $form_values['addetails'] = clean_field(awpcp_array_data('addetails', '', $values));
	if (get_awpcp_option('allowhtmlinadtext') == 0){
		$addetails = $form_values['addetails'] = strip_html_tags($addetails);
	}

	$adpaymethod = $form_values['adpaymethod'] = clean_field(awpcp_array_data('adpaymethod', '', $values));
	if (empty($adpaymethod)) {
		$adpaymethod = $form_values['adpaymethod'] = "paypal";
	}

	// TODO: remove unused form fields
	$awpcppagename = $form_values['awpcppagename'] = clean_field(awpcp_array_data('awpcppagename', '', $values));
	$adaction = $form_values['adaction'] = clean_field(awpcp_array_data('adaction', '', $values));
	$offset = $form_values['offset'] = clean_field(awpcp_array_data('offset', '', $values));
	$results = $form_values['results'] = clean_field(awpcp_array_data('results', '', $values));
	$websiteurl = $form_values['websiteurl'] = clean_field(awpcp_array_data('websiteurl', '', $values));
	$checkhuman = $form_values['checkhuman'] = clean_field(awpcp_array_data('checkhuman', '', $values));
	$numval1 = $form_values['numval1'] = clean_field(awpcp_array_data('numval1', '', $values));
	$numval2 = $form_values['numval2'] = clean_field(awpcp_array_data('numval2', '', $values));
	$tos = $form_values['tos'] = clean_field(awpcp_array_data('tos', '', $values));

	$user_id = $form_values['user_id'] = clean_field(awpcp_array_data('user_id', '', $values));
	$user_payment_term = $form_values['user_payment_term'] = clean_field(awpcp_array_data('user_payment_term', '', $values));

	// left empty because Featured Ads module will update its value after the
	// Ad has been posted
    $is_featured_ad = $form_values['is_featured_ad'] = 0;

	// Region data is stored escaped in the db. So the region with name "D'Zoure" is
	// in the database as "D\'Zoure". The values coming from Regions dropdowns is expected
	// to be already escaped. However, the users can also input their own regions.
	// In order to take values from dropdowns as they come and to escape user input,
	// we will first stripslashes and then add them again. Unscaped data should remain the
	// same after the first operation and then become escaped after the second one.
	//
	// Hopefully that makes sense, although something better than addslashes should
	// be used -@wvega
	$adcontact_city = clean_field(stripslashes(awpcp_array_data('adcontact_city', '', $values)));
	$adcontact_city = $form_values['adcontact_city'] = strip_html_tags($adcontact_city);

	$adcontact_state = clean_field(stripslashes(awpcp_array_data('adcontact_state', '', $values)));
	$adcontact_state = $form_values['adcontact_state'] = strip_html_tags($adcontact_state);

	$adcontact_country = clean_field(stripslashes(awpcp_array_data('adcontact_country', '', $values)));
	$adcontact_country = $form_values['adcontact_country'] = strip_html_tags($adcontact_country);

	$ad_county_village = clean_field(stripslashes(awpcp_array_data('adcontact_countyvillage', '', $values)));
	$ad_county_village = $form_values['ad_county_village'] = strip_html_tags($ad_county_village);


	$output = processadstep1($form_values, $form_errors, $transaction, $edit);

	return $output;
}


function awpcp_place_ad_upload_images_step($form_values=array(), $form_errors=array(), $edit=false) {
	$ad_id = intval(awpcp_array_data('ad_id', 0, $form_values));

	list($images_allowed, $images_uploaded, $images_left) = awpcp_get_ad_images_information($ad_id);

	if (($images_left <= 0 && empty($form_errors) && !$edit) || $images_allowed <= 0) {
		return awpcp_place_ad_finish($ad_id);
	}

	$images = awpcp_get_ad_images($ad_id);

	$header = array();
	$max_image_size = get_awpcp_option('maximagesize');

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/page-place-ad-upload-images-step.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	do_action('awpcp_post_ad');

	return $html;
}


function awpcp_place_ad_store_images_step() {
	$form_values = array();
	$form_errors = array();

	$ad_id = $form_values['ad_id'] = intval(awpcp_request_param('ad_id'));

	if (isset($_REQUEST['submit'])) {
		$success = awpcp_handle_uploaded_images($ad_id, $form_errors);
	}

	if (!empty($form_errors)) {
		return awpcp_place_ad_upload_images_step($form_values, $form_errors);
	}

	return awpcp_place_ad_finish($ad_id);
}


function awpcp_place_ad_finish($ad_id, $edit=false) {
	$messages = array();
	$ad = AWPCP_Ad::find_by_id($ad_id);

	if (get_awpcp_option('adapprove') == 1 && $ad->disabled) {
		$messages[] = get_awpcp_option('notice_awaiting_approval_ad');
	}

	if (get_awpcp_option('imagesapprove') == 1) {
		$messages[] = __("If you have uploaded images your images will not show up until an admin has approved them.", "AWPCP");
	}

	$header = array();
	$message = ad_success_email($ad_id, join("\n\n", $messages));

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/page-place-ad-finish.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;

	// $awpcpadpostedmsg=__("Your ad has been submitted","AWPCP");

	// if(get_awpcp_option('adapprove') == 1)
	// {
	// 	$awaitingapprovalmsg=get_awpcp_option('notice_awaiting_approval_ad');
	// 	$awpcpadpostedmsg.="<p>";
	// 	$awpcpadpostedmsg.=$awaitingapprovalmsg;
	// 	$awpcpadpostedmsg.="</p>";
	// }
	// if(get_awpcp_option('imagesapprove') == 1)
	// {
	// 	$imagesawaitingapprovalmsg=__("If you have uploaded images your images will not show up until an admin has approved them.","AWPCP");
	// 	$awpcpadpostedmsg.="<p>";
	// 	$awpcpadpostedmsg.=$imagesawaitingapprovalmsg;
	// 	$awpcpadpostedmsg.="</p>";
	// }

	// $awpcpshowadsample=1;
	// $awpcpsubmissionresultmessage ='';
	// $message='';
	// $awpcpsubmissionresultmessage =ad_success_email($adid,$txn_id='',$adkey,$awpcpadpostedmsg,$gateway='');

	// $output .= "<div id=\"classiwrapper\">";
	// $output .= '<p class="ad_status_msg">';
	// $output .= $awpcpsubmissionresultmessage;
	// $output .= "</p>";
	// $output .= awpcp_menu_items();
	// if($awpcpshowadsample == 1)
	// {
	// 	$output .= '<h2 class="ad-posted">';
	// 	$output .= __("You Ad is posted","AWPCP");
	// 	$output .= "</h2>";
	// 	$output .= showad($adid,$omitmenu=1);
	// }
	// $output .= "</div>";
}



// TODO: do we need this?
function display_awpcp_image_upload_form($ad_id, $adterm_id, $adkey, $adaction, $nextstep, $adpaymethod, $awpcpuperror) {
	$awpcp_image_upload_form='';
	$totalimagesuploaded=0;

	$max_image_size=get_awpcp_option('maximagesize');

	$numimgsallowed = awpcp_get_ad_number_allowed_images($ad_id, $adterm_id);

	if (adidexists($ad_id)) {
		$totalimagesuploaded=get_total_imagesuploaded($ad_id);
	}

	$numimgsleft=($numimgsallowed - $totalimagesuploaded);

	if (!empty($adterm_id)) {
		$awpcp_payment_fee = get_adfee_amount($adterm_id);
		if ($awpcp_payment_fee <= 0) {
			$nextstep = "finish";
		}
	}

	if ($nextstep == 'finishnoform') {
		$showimageuploadform='';
	} elseif ($nextstep == 'paymentnoform') {
		$showimageuploadform='';
	} else {
		global $awpcp_plugin_path;
		if ($numimgsallowed >= 1) {
			$showimageuploadform="<p>";
			$showimageuploadform.=__("Image slots available", "AWPCP");
			$showimageuploadform.=" <b>$numimgsleft</b>";
			$showimageuploadform.="</p>";

			$showimageuploadform.="<p>";
			$showimageuploadform.=__("Max image size", "AWPCP");
			$max_size = ($max_image_size/1000);
			$showimageuploadform.=" <b>$max_size KB</b>";
			$showimageuploadform.="</p>";
		}

		if (get_awpcp_option('imagesapprove') == 1)
		{
			$showimageuploadform.="<p>";
			$showimageuploadform.=__("Image approval is in effect so any new images you upload will not be visible to viewers until an admin has approved it","AWPCP");
			$showimageuploadform.="</p>";
		}

		if (isset($awpcpuperror) && !empty($awpcpuperror))
		{
			$showimageuploadform.="<p>";
			foreach($awpcpuperror as $theawpcpuerror)
			{
				$showimageuploadform.=$theawpcpuerror;
			}
			$showimageuploadform.="</p>";
		}

		if (!isset($adaction) || empty($adaction))
		{
			$adaction="notset";
		}
		if (!isset($adterm_id) || empty($adterm_id))
		{
			$adterm_id=-1;
		}
		if ($numimgsallowed != 0) {
			$showimageuploadform.="<div class=\"theuploaddiv\">
			<form id=\"AWPCPForm1\" name=\"AWPCPForm1\" method=\"post\" ENCTYPE=\"Multipart/form-data\">
			<p id=\"showhideuploadform\">
			<input type=\"hidden\" name=\"adid\" value=\"$ad_id\" />
			<input type=\"hidden\" name=\"adtermid\" value=\"$adterm_id\" />
			<input type=\"hidden\" name=\"nextstep\" value=\"$nextstep\" />
			<input type=\"hidden\" name=\"adpaymethod\" value=\"$adpaymethod\" />
			<input type=\"hidden\" name=\"adaction\" value=\"$adaction\" />
			<input type=\"hidden\" name=\"adkey\" value=\"$adkey\" />
			<input type=\"hidden\" name=\"a\" value=\"awpcpuploadfiles\" />";
			$showimageuploadform.=__("If adding images to your ad, select your image from your local computer","AWPCP");
			$showimageuploadform.=":<br/><br/>";

			for ($i=0;$i<$numimgsleft;$i++)
			{
				$uploadinput="<div class=\"uploadform\"><input type=\"file\" name=\"AWPCPfileToUpload$i\" id=\"AWPCPfileToUpload$i\" size=\"18\" />
				 </div>";
				$showimageuploadform.="$uploadinput";
			}

			$showimageuploadform.="</p><p style=\"clear:both;text-align:center;\"><input type=\"submit\" value=\"";
			$showimageuploadform.=__("Upload Selected Files","AWPCP");
			$showimageuploadform.="\" class=\"button\" id=\"awpcp_buttonForm\" /></p>";
			$showimageuploadform.="</form>";
			$showimageuploadform.="</div>";
		}
	}


	$awpcp_image_upload_form.=$showimageuploadform;


	$awpcp_image_upload_form.="<div class=\"fixfloat\"></div>";
	$awpcp_image_upload_form.="<div class=\"finishbutton\"><div class=\"finishbuttonleft\">";

	if (($nextstep == 'payment') || ($nextstep == 'paymentnoform')) {
		$clicktheword1=__("Go To Next Step", "AWPCP");
		$clicktheword2=__("continue", "AWPCP");
	} elseif (($nextstep == 'finish') || ($nextstep == 'finishnoform')) {
		$clicktheword1=__("Post Ad Without Images", "AWPCP");
		$clicktheword2=__("complete", "AWPCP");
	} else {
		$clicktheword1=__("Post Ad Without Images", "AWPCP");
		$clicktheword2=__("complete", "AWPCP");
	}

	if ($numimgsallowed <= 0){
		$awpcp_image_upload_form.= sprintf( __(' <p>Please click the %1$s button to <b>%2$s</b> this process.</p>','AWPCP'), $clicktheword1, $clicktheword2 );
	} else {
		$awpcp_image_upload_form.= sprintf( __(' <p>If you prefer not to upload any images please click the <b>%1$s</b> button to %2$s this process.</p>','AWPCP'), $clicktheword1, $clicktheword2 );
	}
	$awpcp_image_upload_form.="</div><div class=\"finishbuttonright\">";

	$finishbutton="
				<form method=\"post\" id=\"awpcpui_process\">";
	if (($nextstep == 'payment') || ($nextstep == 'paymentnoform'))
	{
		$finishbutton.="<input type=\"hidden\" name=\"a\" value=\"loadpaymentpage\" />";
		$finishbutton.="<input type=\"hidden\" name=\"adpaymethod\" value=\"$adpaymethod\" />";
	}
	elseif ($nextstep == 'finish')
	{
		$finishbutton.="<input type=\"hidden\" name=\"a\" value=\"adpostfinish\" />";
	}
	else
	{
		$finishbutton.="<input type=\"hidden\" name=\"a\" value=\"adpostfinish\" />";
	}
	$finishbutton.="
				<input type=\"hidden\" name=\"adid\" value=\"$ad_id\" />
				<input type=\"hidden\" name=\"adkey\" value=\"$adkey\" />
				<input type=\"hidden\" name=\"adaction\" value=\"$adaction\" />
				<input type=\"hidden\" name=\"adtermid\" value=\"$adterm_id\" />
				<input type=\"hidden\" name=\"adpaymethod\" value=\"$adpaymethod\" />
				<input type=\"Submit\" class=\"button\" value=\"";
	// if (($nextstep == 'payment') || ($nextstep == 'paymentnoform'))
	// {
	// 	$finishbutton.=__("Go To Next Step","AWPCP");
	// }
	// elseif ($nextstep == 'payment')
	// {
	// 	$finishbutton.=__("Finish","AWPCP");
	// }
	// else
	// {
	// 	$finishbutton.=__("Finish","AWPCP");
	// }
	$finishbutton.=$clicktheword1;

	$finishbutton.="\" name=\"submit\" />
				</form>";
	$awpcp_image_upload_form.="$finishbutton";
	$awpcp_image_upload_form.="</div><div class=\"fixfloat\"></div></div>";



	return $awpcp_image_upload_form;
}





function awpcpui_process_placead($post_url='') {
	global $hasextrafieldsmodule;

	$output = '';

	$action = awpcp_request_param('a', 'placead');

	if ($action == 'placead') {
		$output = awpcp_place_ad_payment_step();
		// $form = load_ad_post_form($adid='', $action='', $awpcppagename='',
		// 	$adtermid='', $editemail='', $adaccesskey='', $adtitle='',
		// 	$adcontact_name='', $adcontact_phone='', $adcontact_email='',
		// 	$adcategory='',$adcontact_city='',$adcontact_state='',
		// 	$adcontact_country='',$ad_county_village='',$ad_item_price='',
		// 	$addetails='',$adpaymethod='',$offset='',$results='',$ermsg='',
		// 	$websiteurl='',$checkhuman='',$numval1='',$numval2='', $post_url=$post_url);
		// $output .= apply_filters('awpcp-place-ad-details-form', $form);

	} elseif ($action == 'checkout') {
		$output = awpcp_place_ad_checkout_step();

	} elseif ($action == 'post-checkout') {
		$output = awpcp_place_ad_details_step();

	// User submitted Ad details and contact information - Place Ad Step 1
	} elseif ($action == 'dopost1') {
		$output = awpcp_place_ad_save_details_step();

	} elseif ($action == 'awpcpuploadfiles' || $action == 'store-images') {
		$output = awpcp_place_ad_store_images_step();

	// TODO: fix
	} elseif ($action == 'loadpaymentpage') {
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid'])){$adid=clean_field($_REQUEST['adid']);} else {$adid='';}
		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey'])){$key=clean_field($_REQUEST['adkey']);} else {$key='';}
		if (isset($_REQUEST['adtermid']) && !empty($_REQUEST['adtermid'])){$adterm_id=clean_field($_REQUEST['adtermid']);} else { $adterm_id='';}
		if (isset($_REQUEST['adpaymethod']) && !empty($_REQUEST['adpaymethod'])){$adpaymethod=clean_field($_REQUEST['adpaymethod']);} else {$adpaymethod='';}

		$output .= processadstep3($adid,$adterm_id,$key,$adpaymethod);

	// TODO: test and fix
	} elseif ($action == 'dp') {
		if (isset($_REQUEST['k']) && !empty($_REQUEST['k'])) {
			$keyids=$_REQUEST['k'];
			$keyidelements = explode("_", $keyids);
			$picid=$keyidelements[0];
			$adid=$keyidelements[1];
			$adtermid=$keyidelements[2];
			$adkey=$keyidelements[3];
			$editemail=$keyidelements[4];
		}

		$output .= deletepic($picid,$adid,$adtermid,$adkey,$editemail);

	// TODO: test and fix
	} elseif ($action == 'adpostfinish') {
		$adaction='';$theadid='';$theadkey='';
		if (isset($_REQUEST['adaction']) && !empty($_REQUEST['adaction']))
		{
			$adaction=$_REQUEST['adaction'];
		}
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid']))
		{
			$theadid=$_REQUEST['adid'];
		}
		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey']))
		{
			$theadkey=$_REQUEST['adkey'];
		}

		if ($adaction == 'editad')
		{
			$output .= showad($theadid,$omitmenu='');
		}

		else
		{

			$awpcpshowadsample=1;
			$awpcpsubmissionresultmessage ='';
			$message='';
			$awpcpsubmissionresultmessage =ad_success_email($theadid,$txn_id='',$theadkey,$message,$gateway='');

			$output .= "<div id=\"classiwrapper\">";
			$output .= '<p class="ad_status_msg">';
			$output .= $awpcpsubmissionresultmessage;
			$output .= "</p>";
			$output .= awpcp_menu_items();
			if ($awpcpshowadsample == 1)
			{
				$output .= "<h2>";
				$output .= __("Your Ad is posted","AWPCP");
				$output .= "</h2>";
				$output .= showad($theadid,$omitmenu=1);
			}
			$output .= "</div>";
		}

	// TODO: test and fix
	} elseif ($action == 'deletead') {
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid']))
		{
			$adid=$_REQUEST['adid'];
		}
		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey']))
		{
			$adkey=$_REQUEST['adkey'];
		}
		if (isset($_REQUEST['editemail']) && !empty($_REQUEST['editemail']))
		{
			$editemail=$_REQUEST['editemail'];
		}

		$output .= deletead($adid,$adkey,$editemail);

	// TODO: this kind of requests should be handled in Region Control's own code
	} elseif (($action == 'setregion') || '' != get_query_var('regionid')) {
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
		$output .= awpcp_display_the_classifieds_page_body($awpcppagename);

	} elseif ( $action == 'cregs' ) {

		if (isset($_SESSION['regioncountryID']) )
		{
			unset($_SESSION['regioncountryID']);
		}
		if (isset($_SESSION['regionstatownID']) )
		{
			unset($_SESSION['regionstatownID']);
		}
		if (isset($_SESSION['regioncityID']) )
		{
			unset($_SESSION['regioncityID']);
		}
		if ( isset($_SESSION['theactiveregionid']) )
		{
			unset($_SESSION['theactiveregionid']);
		}

		$output .= load_ad_post_form($adid,$action,$awpcppagename,$adtermid,$editemail='',$adaccesskey='',$adtitle,$adcontact_name,$adcontact_phone,$adcontact_email,$adcategory,$adcontact_city,$adcontact_state,$adcontact_country,$ad_county_village,$ad_item_price,$addetails,$adpaymethod,$offset='',$results='',$ermsg='',$websieurl='',$checkhuman='',$numval1='',$numval2='');

	// Show Ad details and contact information form
	} else {
		$output .= load_ad_post_form($adid='',$action='',$awpcppagename='',$adtermid='',$editemail='',$adaccesskey='',$adtitle='',$adcontact_name='',$adcontact_phone='',$adcontact_email='',$adcategory='',$adcontact_city='',$adcontact_state='',$adcontact_country='',$ad_county_village='',$ad_item_price='',$addetails='',$adpaymethod='',$offset='',$results='',$ermsg='',$websiteurl='',$checkhuman='',$numval1='',$numval2='');
	}

	return $output;
}


function awpcpui_process_editad() {
	global $hasextrafieldsmodule;

	wp_enqueue_script('awpcp-page-place-ad');

	$action='';
	$output = '';

	// if Ad Management panel is enabled use  that to edit Ads
	if (get_awpcp_option('enable-user-panel') == 1) {
		$panel_url = admin_url('admin.php?page=awpcp-panel');
		$output = __('Please go to the Ad Management panel to edit your Ads.', 'AWPCP');
		$output.= ' <a href="' . $panel_url . '">' . __('Click here', 'AWPCP') . '</a>.';
		return $output;
	}


	if (!isset($awpcppagename) || empty($awpcppagename)) {
		$awpcppage = get_currentpagename();
		$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	}

	if (isset($_REQUEST['a']) && !empty($_REQUEST['a'])) {
		$action=$_REQUEST['a'];
	}

	if ($action == 'editad') {
		$output .= load_ad_edit_form($action,$awpcppagename,$usereditemail,$adaccesskey,$message);
	}
	elseif ($action == 'doadedit1')
	{
		$adaccesskey=clean_field($_REQUEST['adaccesskey']);
		$editemail=clean_field($_REQUEST['editemail']);
		$awpcppagename=clean_field($_REQUEST['awpcppagename']);
		$output .= editadstep1($adaccesskey,$editemail,$awpcppagename);
	}
	elseif ($action == 'resendaccesskey')
	{
		$editemail = awpcp_request_param('editemail');
		$awpcppagename = awpcp_request_param('awpcppagename');
		$output .= resendadaccesskeyform($editemail,$awpcppagename);

	} elseif (in_array($action, array('dp', 'enable-picture', 'disable-picture'))) {
		if (isset($_REQUEST['k']) && !empty($_REQUEST['k'])) {
			$keyids=$_REQUEST['k'];
			$keyidelements = explode("_", $keyids);
			$picid=$keyidelements[0];
			$adid=$keyidelements[1];
			$adtermid=$keyidelements[2];
			$adkey=$keyidelements[3];
			$editemail=$keyidelements[4];
		}

		$is_admin = awpcp_current_user_is_admin();
		$admin_must_approve = get_awpcp_option('imagesapprove');

		if ($action == 'dp') {
			$output .= deletepic($picid, $adid, $adtermid, $adkey, $editemail);
		} else if (in_array($action, array('enable-picture', 'disable-picture'))) {
			$image = AWPCP_Image::find_by_id($picid);
			if (is_object($image) && ($is_admin || !$admin_must_approve)) {
				if ($action == 'enable-picture') {
					$image->disabled = false;
					$image->save();
				} else if ($action == 'disable-picture') {
					$image->disabled = true;
					$image->save();
				}
			}
			$output .= editimages($adtermid, $adid, $adkey, get_adposteremail($adid));
		}

	} elseif ($action == 'dopost1') {
		$errors = array();
		$output .= awpcp_place_ad_save_details_step(array(), $errors, true);

	} elseif ($action == 'awpcpuploadfiles') {
		$adid='';$adtermid='';$adkey='';$adpaymethod='';$nextstep='';$adaction='';

		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid'])){$adid=clean_field($_REQUEST['adid']);}
		if (isset($_REQUEST['adtermid']) && !empty($_REQUEST['adtermid'])){$adtermid=clean_field($_REQUEST['adtermid']);}
		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey'])){$adkey=clean_field($_REQUEST['adkey']);}
		if (isset($_REQUEST['adpaymethod']) && !empty($_REQUEST['adpaymethod'])){$adpaymethod=clean_field($_REQUEST['adpaymethod']);}
		if (isset($_REQUEST['nextstep']) && !empty($_REQUEST['nextstep'])){$nextstep=clean_field($_REQUEST['nextstep']);}
		if (isset($_REQUEST['adaction']) && !empty($_REQUEST['adaction'])){$adaction=clean_field($_REQUEST['adaction']);}
		// $output .= handleimagesupload($adid,$adtermid,$nextstep,$adpaymethod,$adaction,$adkey);

		$form_errors = array();
		$success = awpcp_handle_uploaded_images($adid, $form_errors);

		if (!empty($form_errors)) {
			 $output .= display_awpcp_image_upload_form($adid,$adtermid,$adkey,$adaction,$nextstep,$adpaymethod,$awpcpuerror);
		} else {
			$output = awpcp_place_ad_finish($adid, true);
		}
	}
	elseif ($action == 'adpostfinish')
	{
		if (isset($_REQUEST['adaction']) && !empty($_REQUEST['adaction']))
		{
			$adaction=$_REQUEST['adaction'];
		}
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid']))
		{
			$theadid=$_REQUEST['adid'];
		}
		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey']))
		{
			$theadkey=$_REQUEST['adkey'];
		}

		if ($adaction == 'editad')
		{
			$output .= showad($theadid,$omitmenu='');
		}
		else
		{

			$awpcpshowadsample=1;
			$awpcpsubmissionresultmessage ='';
			$message='';

			$awpcpsubmissionresultmessage =ad_success_email($theadid,$txn_id='',$theadkey,$message,$gateway='', false);

			$output .= "<div id=\"classiwrapper\">";
			$output .= '<p class="ad_status_msg">';
			$output .= $awpcpsubmissionresultmessage;
			$output .= "</p>";
			$output .= awpcp_menu_items();
			if ($awpcpshowadsample == 1)
			{
				$output .= "<h2>";
				$output .= __("Your Ad is posted","AWPCP");
				$output .= "</h2>";
				$output .= showad($theadid,$omitmenu=1);
			}
			$output .= "</div>";
		}
	}
	elseif ($action == 'deletead')
	{
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid']))
		{
			$adid=$_REQUEST['adid'];
		}
		if (isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey']))
		{
			$adkey=$_REQUEST['adkey'];
		}
		if (isset($_REQUEST['editemail']) && !empty($_REQUEST['editemail']))
		{
			$editemail=$_REQUEST['editemail'];
		}

		$output .= deletead($adid,$adkey,$editemail);
	}
	else
	{
		$output .= load_ad_edit_form($action='editad',$awpcppagename,$editemail='',$adaccesskey='',$message='');
	}
	return $output;
}


/**
 * Generic function to calculate an date relative to a given start date.
 *
 * @since 2.0.7
 */
function awpcp_calculate_end_date($increment, $period, $start_date) {
	$periods = array('D' => 'DAY', 'W' => 'WEEK', 'M' => 'MONTH', 'Y' => 'YEAR');
	if (in_array($period, array_keys($periods))) {
		$period = $periods[$period];
	}

	// 0 means no expiration date, we understand that as ten years
	if ($increment == 0 && $period == 'DAY') {
		$increment = 3650;
	} else if ($increment == 0 && $period == 'WEEK') {
		$increment = 5200;
	} else if ($increment == 0 && $period == 'MONTH') {
		$increment = 1200;
	} else if ($increment == 0 && $period == 'YEAR') {
		$increment = 10;
	}

	return date('Y-m-d H:i:s', strtotime("+ $increment $period", $start_date));
}

/**
 * If an Ad was passed, calculates Ad End Date from current End Date.
 * If no Ad was passed, calculates Ad End Date as if Ad would have
 * been posted at the current time.
 *
 * TODO: Use the new $ad->calculate_end_date() method.
 */
function awpcp_calculate_ad_end_date($duration, $interval='DAY', $ad=null) {
	$now = awpcp_time(null, 'timestamp');
	$end_date = is_null($ad) ? $ad->ad_enddate : 0;
	// if the Ad's end date is in the future, use that as starting point 
	// for the new end date, else use current date.
	$start_date = $end_date > $now ? $end_date : $now;
	return awpcp_calculate_end_date($duration, $interval, $start_date);
}


/**
 * ...
 *
 * @param $id	Ad ID.
 * @param $transaction	Payment Transaction associated to the Ad being posted
 *
 * It must be possible to have more than one transaction associated to a single
 * Ad, for example, when an Ad has been posted AND renewed one or more times.
 *
 * TODO: this can be moved into the Ad class. We actually don't need a transaction,
 * because the payment_status is stored in the Ad object. We need, however, to update
 * the payment_status when the Ad is placed AND renewed. ~2012-09-19
 */
function awpcp_calculate_ad_disabled_state($id=null, $transaction=null) {
	if (!is_null($transaction)) {
		$status = $transaction->get('payment-status');
		$is_pending = $status == AWPCP_Payment_Transaction::$PAYMENT_STATUS_PENDING;
	} else {
		// set to false to bypass disablependingads verification
		$is_pending = false;
	}

	if (awpcp_current_user_is_admin()) {
		$disabled = 0;
	} else if (get_awpcp_option('adapprove') == 1) {
		$disabled = 1;
	// if disablependingads == 1, pending Ads should be enabled
	} else if ($is_pending && get_awpcp_option('disablependingads') == 0) {
		$disabled = 1;
	} else {
		$disabled = 0;
	}

	return $disabled;
}


function awpcp_renew_ad_form($content, $ad) {
	// content is initialized to false, if we get something else
	// then the form was generated somewhere else.
	if ($content !== false) {
		return $content;
	}

	if ($ad->adterm_id != 0) {
		$payment_term = awpcp_payment_terms('ad-term-fee', $ad->adterm_id);
	} else {
		$payment_term = null;
	}

	if ($ad->adterm_id != 0 && is_null($payment_term)) {
		return __("The Ad was posted under a Term Fee that no longer exists. The Ad can't be renewed.", 'AWPCP');
	}

	if ($ad->adterm_id == 0 || ($payment_term && $payment_term->price <= 0)) {
		// // the Ad was placed under no plan (Free Board)
		// if ($ad->adterm_id == 0) {
		// 	$ad->ad_enddate = awpcp_calculate_ad_end_date(get_awpcp_option('addurationfreemode'), 'DAY', $ad);
		// // the Ad was placed under a Term Fee with price zero
		// } else {
		// 	$duration = awpcp_get_term_duration($ad->adterm_id);
		// 	$ad->ad_enddate = awpcp_calculate_ad_end_date($duration['duration'], $duration['increment'], $ad);
		// }

		// $ad->renew_email_sent = false;

		// // if Ads is disabled lets see if we can enable it
		// $disabled = $ad->disabled;
		// if ($disabled) {
		// 	$ad->disabled = awpcp_calculate_ad_disabled_state($ad->ad_id);
		// }

		// if ($disabled !== $ad->disabled) {
		// 	$ad->disabled_date = current_time('mysql');
		// }
		$ad->renew();
		$ad->save();

		do_action('awpcp-renew-ad', $ad->ad_id, null);

		return awpcp_renew_ad_success_message($ad);
	}

	// the Ad was placed under a Fee plan
	$payment_methods = awpcp_payment_methods();

	$transaction = awpcp_post_param('awpcp-txn', 0);
	$transaction = AWPCP_Payment_Transaction::find_or_create($transaction);

	$transaction->set('payment-term-type', 'ad-term-fee');
	$transaction->set('payment-term-id', $ad->adterm_id);
	$transaction->save();

	ob_start();
		include(AWPCP_DIR . '/frontend/templates/page-renew-ad-payment-form.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}
// priority set to 100 so modules have a change to act on this hook first
add_filter('awpcp-renew-ad-form', 'awpcp_renew_ad_form', 100, 2);



/**
 * @since 2.1.2
 */
function awpcp_send_ad_renewed_email($ad) {
    global $nameofsite, $thisadminemail;

    $admin_email = get_awpcp_option('awpcpadminemail');
    if (empty($admin_email))
        $admin_email = $thisadminemail;

    $subject = get_awpcp_option('ad-renewed-email-subject');
    $subject = sprintf($subject, $ad->ad_title);
    $introduction = get_awpcp_option('ad-renewed-email-body');

    // send notification to the user
    ob_start();
        include(AWPCP_DIR . '/frontend/templates/email-ad-renewed-success-user.tpl.php');
        $body = ob_get_contents();
    ob_end_clean();

    awpcp_process_mail($admin_email, $ad->ad_contact_email, $subject, $body, $nameofsite, $admin_email);

    // send notification to the admin
    $subject = __('The classifieds listing "%s" has been successfully renewed.', 'AWPCP');
    $subject = sprintf($subject, $ad->ad_title);

    ob_start();
        include(AWPCP_DIR . '/frontend/templates/email-ad-renewed-success-admin.tpl.php');
        $body = ob_get_contents();
    ob_end_clean();

    awpcp_process_mail($admin_email, $admin_email, $subject, $body, $nameofsite, $admin_email);
}

/**
 * @since 2.0.7
 */
function awpcp_renew_ad_success_message($ad, $text=null) {
	if (is_null($text)) {
		$text = __("The Ad has been successfully renewed. New expiration date is %s. ", 'AWPCP');
	}

	$return = '';
	if (is_admin()) {
		$return = sprintf('<a href="%1$s">%2$s</a>', awpcp_get_user_panel_url(), __('Return to Listings', 'AWPCP'));
	}

	awpcp_send_ad_renewed_email($ad);

	return sprintf("%s %s", sprintf($text, $ad->get_end_date()), $return);
}


// TODO: set Ad disabled_date to null when renewing the Ad
function awpcp_renew_ad_page() {
	global $current_user, $hasgooglecheckoutmodule;
	get_currentuserinfo();

	$form_values = array();

	$ad_id = $form_values['ad_id'] = awpcp_request_param('ad_id');
	$ad = AWPCP_Ad::find_by_id($ad_id);

	$step = $form_values['step'] = awpcp_post_param('step', 'renew-ad');
	$payment_method = $form_values['payment-method'] = awpcp_post_param('payment-method', false);

	if (is_null($ad)) {
		$content = __("The specified Ad doesn't exist.", 'AWPCP');
		$step = 'error';
	} else if (!$ad->is_about_to_expire() && !$ad->has_expired()) {
		$content = __("The specified Ad doesn't need to be renewed.", 'AWPCP');
		$step = 'error';
	}

	if ($step == 'renew-ad' || ($step == 'checkout' && empty($payment_method))) {
		$content = apply_filters('awpcp-renew-ad-form', false, $ad);

	} else if ($step == 'checkout') {
		$transaction = $form_values['awpcp-txn'] = awpcp_post_param('awpcp-txn', 0);
		$transaction = AWPCP_Payment_Transaction::find_by_id($transaction);

		if (is_null($transaction)) {
			$content = __('An error ocurred trying to process your payment request.', 'AWPCP');
			$step = 'error';
		}

		$amount = get_adfee_amount($ad->adterm_id);
		$transaction->set('original-amount', $amount);

		$amount = apply_filters('awpcp-renew-ad-payment-amount', $amount, $transaction);
		$transaction->set('amount', $amount);

		if ($amount <= 0) {
			$ad->renew();
			$ad->save();

			$content = awpcp_renew_ad_success_message($ad);
			$step = 'post-checkout';

			do_action('awpcp-renew-ad', $ad->ad_id, $transaction);
		} else {
			$transaction->set('payment-method', $payment_method);

			$term = awpcp_get_term_duration($ad->adterm_id);
			$transaction->add_item($ad->adterm_id, get_adterm_name($ad->adterm_id), $term['duration'], $term['increment']);

			$transaction->set('success-redirect', awpcp_current_url());
			$transaction->set('success-form', array('step' => 'post-checkout', 'ad_id' => $ad_id));

			$transaction->set('cancel-redirect', awpcp_current_url());
			$transaction->set('cancel-form', $form_values);

			$header = apply_filters('awpcp-renew-ad-checkout-step-form-header', array(), $form_values, $transaction);
			$content = apply_filters('awpcp-checkout-form', '', $transaction);
		}

		$transaction->save();

	} else if ($step == 'post-checkout') {
		$transaction = $form_values['awpcp-txn'] = awpcp_post_param('awpcp-txn', 0);
		$transaction = AWPCP_Payment_Transaction::find_by_id($transaction);

		$success = true;
		if (is_null($transaction)) {
			$success = false;
		}

		$payment_status = $transaction->get('payment-status');
		$accepted_status = array(AWPCP_Payment_Transaction::$PAYMENT_STATUS_COMPLETED,
								 AWPCP_Payment_Transaction::$PAYMENT_STATUS_PENDING);

		if ($success && !in_array($payment_status, $accepted_status))  {
			$success = false;
		}

		if ($success) {
			// list($duration, $interval) = apply_filters('awpcp-place-ad-duration', array(30, 'DAY'), $transaction);

			// // TODO: calculate Ad disabled state
			// $ad->ad_enddate = awpcp_calculate_ad_end_date($duration, $interval, $ad);
			// $ad->renew_email_sent = false;
			$ad->renew();
			$ad->save();

			$content = awpcp_renew_ad_success_message($ad);
			$step = 'post-checkout';

			$transaction->set('completed', current_time('mysql'));
			$transaction->save();

			do_action('awpcp-renew-ad', $ad->ad_id, $transaction);
		} else {
			$content = __('An error ocurred trying to process your payment request.', 'AWPCP');
		}
	}

	if ($content === false) {
		$content = __('Ad Renew is disabled at this moment.', 'AWPCP');
	}

	ob_start();
		include(AWPCP_DIR . '/frontend/templates/page-renew-ad.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}

/**
 * Return HTML for the Ad details and contact information form
 */
function load_ad_post_form($adid='', $action='', $awpcppagename='',
			$adtermid='', $editemail='', $adaccesskey='', $adtitle='',
			$adcontact_name='', $adcontact_phone='', $adcontact_email='',
			$adcategory='', $adcontact_city='', $adcontact_state='',
			$adcontact_country='', $ad_county_village='', $ad_item_price='',
			$addetails='', $adpaymethod='', $offset='', $results='',
			$ermsg='', $websiteurl='', $checkhuman='', $numval1='',
			$numval2='', $action_url='', $show_category_field=true,
			$transaction_id='', $user_id='', $user_payment_term='')
{
	global $wpdb, $hasregionsmodule;
	global $hasgooglecheckoutmodule, $hasextrafieldsmodule;

	wp_enqueue_script('awpcp-page-place-ad');

	global $current_user;
	get_currentuserinfo();

	$is_admin_user = awpcp_current_user_is_admin();

	$output = '';
	$cpID = '';
	$tbl_categories = AWPCP_TABLE_CATEGORIES;

	$isadmin = checkifisadmin();

	if (!isset($awpcppagename) || empty($awpcppagename)) {
		$awpcppage = get_currentpagename();
		$awpcppagename = sanitize_title($awpcppage);
	}

	$quers=setup_url_structure($awpcppagename);
	$permastruc=get_option('permalink_structure');

	$placeadpagename = sanitize_title(get_awpcp_option('place-ad-page-name'));
	$placeadpageid = awpcp_get_page_id_by_ref('place-ad-page-name');

	$url_placeadpage = get_permalink($placeadpageid);
	if (!empty($permastruc)) {
		$awpcpquerymark="?";
	} else {
		$awpcpquerymark="&";
	}

	// Handle if only admin can post and non admin user arrives somehow on post ad page
	if (get_awpcp_option('onlyadmincanplaceads') && ($isadmin != 1)) {
		$output .= "<div id=\"classiwrapper\"><p>";
		$output .= __("You do not have permission to perform the function you are trying to perform. Access to this page has been denied","AWPCP");
		$output .= "</p></div>";
	}
	// Handle if user must be registered
	else if (get_awpcp_option('requireuserregistration') && !is_user_logged_in()) {
		$message = __('Hi, You need to be a registered user to post Ads in this website. Please use the form below to login or click the link to register.', 'AWPCP');
		$output .= awpcp_login_form($message, $url_placeadpage);

	// Handle Ad post form
	} else {

		////////////
		// START pre-form configurations
		////////////

		$tbl_ad_fees = $wpdb->prefix . "awpcp_adfees";
		$tbl_ads = $wpdb->prefix . "awpcp_ads";
		$images='';
		$displaydeleteadlink='';

		// gathers Ad's information (Extra Fields list & Ad details)
		if ($action == 'editad') {
			$savedemail=get_adposteremail($adid);

			if ((strcasecmp($editemail, $savedemail) == 0) || ($isadmin == 1)) {
				if ($hasextrafieldsmodule == 1) {
					$x_fields_fetch = "";
					$x_fields_list = "";

					$x_fields_get_thefields = x_fields_fetch_fields();
					$x_fields_fetch_last = end($x_fields_get_thefields);

					foreach($x_fields_get_thefields as $x_fieldsfield) {
						$x_fields_fetch.=$x_fieldsfield;
						if (!($x_fields_fetch_last == $x_fieldsfield)) {
							$x_fields_fetch.=",";
						}

						$x_fields_list.='$';
						$x_fields_list.=$x_fieldsfield;

						if (!($x_fields_fetch_last == $x_fieldsfield)) {
							$x_fields_list.=",";
						}
					}
				} else {
					$x_fields_fetch='';
					$x_fields_list='';
				}

				$query = "SELECT ad_title, ad_contact_name, ad_contact_email ";
				$query.= ", ad_category_id, ad_contact_phone, ad_city, ad_state ";
				$query.= ", ad_country, ad_county_village, ad_item_price ";
				$query.= ", ad_details, ad_key, websiteurl, user_id $x_fields_fetch ";
				$query.= "FROM " . AWPCP_TABLE_ADS . " ";
				$query.= "WHERE ad_id='$adid' AND ad_contact_email='$editemail' AND ad_key='$adaccesskey'";

				$res = awpcp_query($query, __LINE__);

				while ($rsrow=mysql_fetch_row($res)) {
					if (is_array($rsrow)) {
						for($i=0; $i < count($rsrow); $i++) {
							$rsrow[$i] = stripslashes($rsrow[$i]);
						}
					}

					list($adtitle, $adcontact_name, $adcontact_email,
						$adcategory, $adcontact_phone, $adcontact_city,
						$adcontact_state, $adcontact_country, $ad_county_village,
						$ad_item_price, $addetails, $adaccesskey,
						$websiteurl, $user_id, $x_fields_list) = $rsrow;
				}

				$adtitle = stripslashes_deep($adtitle);
				$addetails = stripslashes_deep($addetails);

				if (isset($ad_item_price) && !empty($ad_item_price)) {
					$ad_item_price = ($ad_item_price/100);
				} else {
					$ad_item_price = '';
				}

				$displaydeleteadlink="<div class=\"alert\">";
				$displaydeleteadlink.="<form method=\"post\">";
				$displaydeleteadlink.="<input type=\"hidden\" name=\"adid\" value=\"$adid\"/>";
				$displaydeleteadlink.="<input type=\"hidden\" name=\"adkey\" value=\"$adaccesskey\"/>";
				$displaydeleteadlink.="<input type=\"hidden\" name=\"editemail\" value=\"$editemail\"/>";
				$displaydeleteadlink.="<input type=\"hidden\" name=\"a\" value=\"deletead\"/>";
				$displaydeleteadlink.="<input type=\"submit\" name=\"deletead\" class=\"button\" value=\"";
				$displaydeleteadlink.=__("Delete Ad","AWPCP");
				$displaydeleteadlink.="\" />";
				$displaydeleteadlink.="</form>";
				$displaydeleteadlink.="</div>";

			} else {
				unset($action);
			}
		}
		// End if $action == 'editad'

		/////
		// Retrieve the categories to populate the select list
		/////

		$allcategories = get_categorynameidall($adcategory);

		/////
		// START Setup javascript checkpoints
		/////

		if ((get_awpcp_option('displayphonefield') == 1) && (get_awpcp_option('displayphonefieldreqop') == 1)) {
			$phoneerrortxt=__("You did not fill out a phone number for the ad contact person. The information is required","AWPCP");
			$phonecheck="
			if (the.adcontact_phone.value===''){
			alert('$phoneerrortxt');
			the.adcontact_phone.focus();
			return false;
			}";
		} else {
			$phonecheck='';
		}

		if ((get_awpcp_option('displaycityfield') == 1) && (get_awpcp_option('displaycityfieldreqop') == 1)) {
			$cityerrortxt=__("You did not fill out your city. The information is required","AWPCP");
			$citycheck="
			if (the.adcontact_city.value==='') {
			alert('$cityerrortxt');
			the.adcontact_city.focus();
			return false;
			}";
		} else {
			$citycheck='';
		}

		if ((get_awpcp_option('displaystatefield') == 1) && (get_awpcp_option('displaystatefieldreqop') == 1)) {
			$stateerrortxt=__("You did not fill out your state. The information is required","AWPCP");
			$statecheck="
			if (the.adcontact_state.value==='') {
			alert('$stateerrortxt');
			the.adcontact_state.focus();
			return false;
			}";
		} else {
			$statecheck='';
		}

		if ((get_awpcp_option('displaycountyvillagefield') == 1) && (get_awpcp_option('displaycountyvillagefieldreqop') == 1)) {
			$countyvillageerrortxt=__("You did not fill out your county/village/other. The information is required","AWPCP");
			$countyvillagecheck="
			if (the.adcontact_countyvillage.value==='') {
			alert('$countyvillageerrortxt');
			the.adcontact_countyvillage.focus();
			return false;
			}";
		} else {
			$countyvillagecheck='';
		}

		if ((get_awpcp_option('displaycountryfield') == 1) && (get_awpcp_option('displaycountryfieldreqop') == 1)) {
			$countryerrortxt=__("You did not fill out your country. The information is required","AWPCP");
			$countrycheck="
			if (the.adcontact_country.value==='') {
			alert('$countryerrortxt');
			the.adcontact_country.focus();
			return false;
			}";
		} else {
			$countrycheck='';
		}

		if ((get_awpcp_option('displaywebsitefield') == 1) && (get_awpcp_option('displaywebsitefieldreqop') == 1)) {
			$websiteerrortxt=__("You did not fill out your website address. The information is required","AWPCP");
			$websitecheck="
			if (the.websiteurl.value==='') {
			alert('$websiteerrortxt');
			the.websiteurl.focus();
			return false;
			}";
		} else {
			$websitecheck='';
		}

		if ((get_awpcp_option('displaypricefield') == 1) && (get_awpcp_option('displaypricefieldreqop') == 1)) {
			$itempriceerrortxt=__("You did not enter a value for the item price. The information is required","AWPCP");
			$itempricecheck="
			if (the.ad_item_price.value==='') {
			alert('$itempriceerrortxt');
			the.ad_item_price.focus();
			return false;
			}";
		} else {
			$itempricecheck='';
		}

		if ( (get_awpcp_option('freepay') == 1) && ($action == 'placead') && !is_admin()) {
			$paymethoderrortxt=__("You did not select your payment method. The information is required","AWPCP");
			$paymethodcheck="
			if (!checked(the.adpaymethod)) {
			alert('$paymethoderrortxt');
			the.adpaymethod.focus();
			return false;
			}";
		} else {
			$paymethodcheck='';
		}

		if ( (get_awpcp_option('freepay') == 1) && ($action == 'placead') && !is_admin() ) {
			$adtermerrortxt=__("You did not select your ad term choice. The information is required","AWPCP");
			$adtermcheck="
			if (the.adterm_id.value==='') {
			alert('$adtermerrortxt');
			the.adterm_id.focus();
			return false;
			}";
		} else {
			$adtermcheck='';
		}

		if ((get_awpcp_option('contactformcheckhuman') == 1) && !is_admin()) {
			if (isset($numval1) && !empty($numval1)) { $numval1=$numval1;}
			else { $numval1=rand(1,get_awpcp_option('contactformcheckhumanhighnumval'));}
			if (isset($numval2) && !empty($numval2))	{ $numval2=$numval2;	}
			else { $numval2=rand(1,get_awpcp_option('contactformcheckhumanhighnumval'));}

			$thesum=($numval1 +  $numval2);

			$checkhumanerrortxt1=__("You did not solve the math problem. Please solve the math problem to proceed.","AWPCP");
			$checkhumanerrortxt2=__("Your answer to the math problem was not correct. Please try again.","AWPCP");

			$checkhumancheck="
			if (the.checkhuman.value==='') {
				alert('$checkhumanerrortxt1');
				the.checkhuman.focus();
				return false;
			}
			if (the.checkhuman.value != $thesum){
				alert('$checkhumanerrortxt2');
				the.checkhuman.focus();
				return false;
			}";
		} else {
			$checkhumancheck = '';
		}

		$adtitleerrortxt=__("You did not fill out an ad title. The information is required","AWPCP");
		$adcategoryerrortxt=__("You did not select an ad category. The information is required","AWPCP");
		$adcontactemailerrortxt=__("Either you did not enter your email address or the email address you entered is not valid","AWPCP");
		$adcontactnameerrortxt=__("You did not fill in the name of the ad contact person. The information is required","AWPCP");
		$addetailserrortxt=__("You did not fill in any details for your ad. The information is required","AWPCP");
		$toserrortxt=__("You must accept the terms of service","AWPCP");
		$paymethoderrortxt=__("You did not select a payment method","AWPCP");
		$adtermerrortxt=__("You did not select an ad term","AWPCP");
		$user_payment_term_error_text = __('You did not select a Payment Term for this Ad', 'AWPCP');

		$checktheform="<script type=\"text/javascript\">
			function checkform() {
			    var the=document.adpostform;
			    var checkemj = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

			    if (the.adtitle.value==='') {
				    alert('$adtitleerrortxt');
				    the.adtitle.focus();
				    return false;
			    }
		";

		if ( 'editad' != $action ) {
			$checktheform .= "
			    if (the.adcategory.value==='') {
				    alert('$adcategoryerrortxt');
				    the.adcategory.focus();
				    return false;
			    }
			";
		}

		// JavaScript verificaiton for posting Ads on behalf of another user
		if ($is_admin_user) {
			$checktheform .= "
				if (parseInt(the.user_id.value, 10) > 0 && the.user_payment_term &&
					the.user_payment_term.value==='')
				{
					alert('$user_payment_term_error_text');
					the.user_payment_term.focus();
					return false;
				}
			";
		}

		$checktheform .= "
			    if (the.adcontact_name.value==='') {
				    alert('$adcontactnameerrortxt');
				    the.adcontact_name.focus();
				    return false;
			    }
			    if (checkemj.test(the.adcontact_email.value) == false) {
				    alert('$adcontactemailerrortxt');
				    the.adcontact_email.focus();
				    return false;
			    }
		";

		if ( get_awpcp_option('requiredtos') && $action != 'editad' ) {
			$checktheform .= "
			    if (!the.tos.checked) {
				    alert('$toserrortxt');
				    the.tos.focus();
				    return false;
			    }";
		}

		$checktheform .= "

				$phonecheck;
				$citycheck;
				$statecheck;
				$countrycheck;
				$websitecheck;
				$countyvillagecheck;
				$itempricecheck;
				$paymethodcheck;
				$adtermcheck;
				$checkhumancheck;

				if (the.addetails.value==='') {
					alert('$addetailserrortxt');
					the.addetails.focus();
					return false;
				}

				return true;
			}

			function textCounter(field, countfield, limitfield) {
				// limit = 0 means no limit
				var limit = limitfield.value;

				// if too long... trim it!
				if (limit > 0 && field.value.length > limit) {
					field.value = field.value.substring(0, limit);
				}

				// update 'characters left' counter
				if (limit > 0) {
					countfield.value = limit - field.value.length;
				} else {
					countfield.value = ''
				}
			}


			function awpcp_toggle_visibility(id, item) {

				if ( 0 == jQuery(item).attr('rel') ) {
					jQuery('#showhidepaybutton').hide();
					return;
				}

				 var e = document.getElementById(id);
				 if (e.style.display == 'block')
				 {
				      e.style.display = 'block';
				  }
				 else
				 {
					 e.style.display = 'block';
				}
			}

			function awpcp_toggle_visibility_reverse(id, item) {

			    if ( 0 == jQuery(item).attr('rel') ) {
				    jQuery('#showhidepaybutton').hide();
				    return;
			    }

			    if ( jQuery(id).is(':visible') ) {
					jQuery('#'+id).hide();
			    } else {
					jQuery('#'+id).show();
			    }
			}
		</script>";


		$addetailsmaxlength = false;
		$addetailsremlength = false;
		$transaction = AWPCP_Payment_Transaction::find_or_create($transaction_id);
		if ($transaction && $transaction->get('payment-term-type') == 'ad-term-fee') {
			$fee = awpcp_get_fee($transaction->get('payment-term-id'));
			$addetailsmaxlength = is_null($fee) ? false : $fee->characters_allowed;
			$addetailsremlength = $addetailsmaxlength;
		}

		if ($addetailsmaxlength === false && !empty($adid)) {
			$ad = AWPCP_Ad::find_by_id($adid);
			$addetailsmaxlength = $ad->get_max_characters_allowed();
			$addetailsremlength = $ad->get_remaining_characters_count();
		} else if ($addetailsmaxlength === false) {
			$addetailsmaxlength = get_awpcp_option('maxcharactersallowed');
			$addetailsremlength = $addetailsmaxlength;
		}

		$theformbody='';

		$readonlyacname='';
		$readonlyacem='';

		// if (get_awpcp_option('requireuserregistration') &&
		// 	is_user_logged_in() && !$is_admin_user) {
		if ($action != 'editad' && is_user_logged_in()) {
			$adcontact_name = trim($current_user->user_firstname . " " . $current_user->user_lastname);
			$adcontact_email = trim($current_user->user_email);

			$user = awpcp_get_user($current_user->ID);
			if (empty($adcontact_email) || (false == strpos($adcontact_email,'@'))) {
				$adcontact_email = $user->user_email;
			}

			if (!empty($adcontact_name) && !$is_admin_user) {
				$readonlyacname = "readonly";
			}
			if (!empty($adcontact_email) && !$is_admin_user) {
				$readonlyacem = "readonly";
			}

			$translations = array('adcontact_name' => 'username',
								  'adcontact_email' => 'email',
								  'adcontact_city' => 'city',
							      'adcontact_state' => 'state',
							      'adcontact_phone' => 'phone',
							      'websiteurl' => 'user_url');
			foreach ($translations as $field => $key) {
				if (empty($$field)) {
					$$field = awpcp_get_property($user, $key, '');
				}
			}
		}

		/////
		// END Setup additional variables
		/////


		///////////////////
		// START configuration of dropdown lists used with regions module if regions module exists and pre-set regions exist
		///////////////////

		$region_control_selector = '';
		if ( $hasregionsmodule ==  1 ) {
			if ($action == 'editad') {
				// Do nothing
			} else {
				// XXX: these three variable seem unused
				if (isset($_SESSION['regioncountryID']) ) {
					$thesessionregionidval1=$_SESSION['regioncountryID'];
				}

				if (isset($_SESSION['regionstatownID']) ) {
					$thesessionregionidval2=$_SESSION['regionstatownID'];
				}

				if (isset($_SESSION['regioncityID']) ) {
					$thesessionregionidval3=$_SESSION['regioncityID'];
				}

				// Project leader requested remove the form from Place Ad page
				// $region_control_selector = awpcp_region_control_selector();
			}
		}
		///////////////////
		// END configuration of dropdown lists used with regions module if regions module exists and pre-set regions exist
		///////////////////


		if (!isset($formdisplayvalue) || empty($formdisplayvalue) ) {
			$formdisplayvalue="block";
		}

		if ($action == 'editad' ) {
			$editorposttext= '<p>' . __("Your ad details have been filled out in the form below. Make any changes needed then resubmit the ad to update it","AWPCP") . '</p>';
		} else {
			$editorposttext='';
            if (current_user_can('administrator')) {
				$editorposttext.=__("<p class='awpcp-form-spacer' style='font-weight:bold'><em>You are logged in as an administrator. Any payment steps will be skipped.</em></p> ","AWPCP");
			}
			$editorposttext.= '<p>' . __("Fill out the form below to post your classified ad. ","AWPCP") . '</p>';
		}

		////////////
		// END pre-form configurations
		////////////


		////////////
		// START form display
		////////////

		// Open  div id classiwrapper
		$output .= "<div id=\"classiwrapper\">";

		// XXX: Payment
		// if (function_exists('awpcp_price_cats')) {
		// 	$output .= '
		// 	    <noscript>
		// 		<p id="js_error">
		// 		<em><strong>
		// 		    '.__('You must enable Javascript to post an ad','AWPCP').'
		// 		</strong></em>
		// 		</p>
		// 	    </noscript>
		// 	';
		// }

		if (!is_admin()) {
			$output .= awpcp_menu_items();
		}

		$output .= $region_control_selector;
		$output .= "<div class=\"fixfloat\"></div>";
		$output .= '<h2>' . __('Enter Ad Details', 'AWPCP'). '</h2>';

		$output .= "<div style=\"display:$formdisplayvalue\">";

		// build the Form's action URL
		if (!is_admin()) {
			$theformbody.="$displaydeleteadlink $editorposttext";
			$faction="id=\"awpcpui_process\"";

		} else if (!empty($action_url)) {
			$faction = 'action="' . $action_url . '"';

		} else {
			$page = awpcp_request_param('page');
			if (empty($page) || strcasecmp($page, 'awpcp-panel') === 0) {
				// to make it work in the User Ad Management panel
				$action_url = awpcp_current_url();
			} else {
				// to make it work in the Listings section
				$action_url = remove_query_arg(array('action'), awpcp_current_url());
			}
			$faction = 'action="' . $action_url . '"';
		}

		$checktheform = awpcp_inline_javascript_placeholder('place-ad', $checktheform);

		$theformbody.="$checktheform $ermsg";
		$theformbody.="<form method=\"post\" name=\"adpostform\" id=\"adpostform\" $faction onsubmit=\"return(checkform())\">";
		$theformbody.="<input type=\"hidden\" name=\"adid\" value=\"$adid\" />";
		$theformbody.="<input type=\"hidden\" name=\"adaction\" value=\"$action\" />";
		$theformbody.="<input type=\"hidden\" name=\"a\" value=\"dopost1\" />";
		$theformbody.="<input type=\"hidden\" name=\"awpcp-txn\" value=\"$transaction_id\" />";


		$theformbody.="<input type=\"hidden\" name=\"adtermid\" value=\"$adtermid\" />";
		if ($action == 'editad' && !(is_admin() && $is_admin_user)) {
			$theformbody.="<input type=\"hidden\" name=\"adcat\" value=\"$adcategory\" />";
		}

		$theformbody.="<input type=\"hidden\" name=\"adkey\" value=\"$adaccesskey\" />";
		$theformbody.="<input type=\"hidden\" name=\"editemail\" value=\"$editemail\" />";
		$theformbody.="<input type=\"hidden\" name=\"awpcppagename\" value=\"$awpcppagename\" />";
		$theformbody.="<input type=\"hidden\" name=\"results\" value=\"$results\" />";
		$theformbody.="<input type=\"hidden\" name=\"offset\" value=\"$offset\" />";
		$theformbody.="<input type=\"hidden\" name=\"numval1\" value=\"$numval1\" />";
		$theformbody.="<input type=\"hidden\" name=\"numval2\" value=\"$numval2\" />";
		$theformbody.="<br/>";
		$theformbody.="<h3>";
		$theformbody.=__("Ad Details and Contact Information","AWPCP");
		$theformbody.="</h3><p class='awpcp-form-spacer'>";
		$theformbody.=__("Ad Title","AWPCP");
		$theformbody.="<br/><input type=\"text\" class=\"inputbox\" size=\"50\" name=\"adtitle\" value=\"" . awpcp_esc_attr($adtitle) . "\" /></p>";

		if (($show_category_field && $action != 'editad') || (is_admin() && $is_admin_user)) {
		    $theformbody.="<p class='awpcp-form-spacer'>";
		    $theformbody.=__("Ad Category","AWPCP");
		    $theformbody.="<br/><select name=\"adcategory\" id='add_new_ad_cat'><option value=\"\">";
		    $theformbody.=__("Select your ad category","AWPCP");
		    $theformbody.="</option>$allcategories</select></p>";
		} else {
			$theformbody.='<input type="hidden" name="adcategory" id="add_new_ad_cat" value="' . $adcategory . '"/>';
		}

		if ($is_admin_user && $action == 'editad') {
			// it is not allowed to change the payment term of an Ad... yet
			$theformbody .= awpcp_render_users_dropdown($user_id == 0 ? false : $user_id, false);
		} else if ($is_admin_user && get_awpcp_option('freepay') == 1) {
			$theformbody .= awpcp_render_users_dropdown($user_id, $user_payment_term);
		} else if ($is_admin_user) {
			$theformbody .= awpcp_render_users_dropdown($user_id, false);
		}

		if (get_awpcp_option('displaywebsitefield') == 1) {
			$label = _x('Website URL', 'Ad Details form', 'AWPCP');
			$theformbody.="<p class='awpcp-form-spacer'>" . $label . "<br/><input type=\"text\" class=\"inputbox\" size=\"50\" name=\"websiteurl\" value=\"" . awpcp_esc_attr($websiteurl) . "\" /></p>";
		}

		$theformbody.="<p class='awpcp-form-spacer'>";
		$theformbody.=__("Name of person to contact","AWPCP");
		$theformbody.="<br/><input size=\"50\" type=\"text\" class=\"inputbox\" name=\"adcontact_name\" value=\"" . awpcp_esc_attr($adcontact_name) . "\" $readonlyacname /></p>";
		$theformbody.="<p class='awpcp-form-spacer'>";
		$theformbody.=__("Contact Person's Email [Please enter a valid email. The codes needed to edit your ad will be sent to your email address]","AWPCP");
		$theformbody.="<br/><input size=\"50\" type=\"text\" class=\"inputbox\" name=\"adcontact_email\" value=\"" . awpcp_esc_attr($adcontact_email) . "\" $readonlyacem /></p>";

		if (get_awpcp_option('displayphonefield') == 1) {
			$theformbody.="<p class='awpcp-form-spacer'>";
			$theformbody.=__("Contact Person's Phone Number","AWPCP");
			$theformbody.="<br/><input size=\"50\" type=\"text\" class=\"inputbox\" name=\"adcontact_phone\" value=\"" . awpcp_esc_attr($adcontact_phone) . "\" /></p>";
		}

		$region_control_query = array(
			'country' => $adcontact_country,
			'state' => $adcontact_state,
			'city' => $adcontact_city,
			'county' => $ad_county_village
		);
		$translations = array(
			'country' => 'adcontact_country',
			'state' => 'adcontact_state',
			'city' => 'adcontact_city',
			'county' => 'adcontact_countyvillage'
		);
		if ($hasregionsmodule) {
			// render Region Control form fields
			$theformbody .=	awpcp_region_control_form_fields($region_control_query, $translations);
		} else {
			// render Region form fields
			$theformbody .=	awpcp_region_form_fields($region_control_query, $translations);
		}


		if (get_awpcp_option('displaypricefield') == 1) {
			$theformbody.="<p class='awpcp-form-spacer'>";
			$theformbody.=__("Item Price","AWPCP");
			$theformbody.="<br/><input size=\"10\" type=\"text\" class=\"inputboxprice\" maxlength=\"10\" name=\"ad_item_price\" value=\"" . awpcp_esc_attr($ad_item_price) . "\" /></p>";
		}

		$addetails = preg_replace("/(\r\n)+|(\n|\r)+/", "\n\n", $addetails);
		$htmlstatus = nl2br(get_awpcp_option('htmlstatustext'));

		$theformbody.="<p class='awpcp-form-spacer'>";
		$theformbody.=__("Ad Details","AWPCP");
		$theformbody.="<br/><input readonly type=\"text\" name=\"remLen\" size=\"10\" maxlength=\"5\" class=\"inputboxmini\" value=\"$addetailsremlength\" />";
		$theformbody.= '<input readonly type="hidden" name="characters_allowed" value="' . $addetailsmaxlength . '"/>';
		$theformbody.=__("characters left","AWPCP");
                //.$_SESSION["texto"]
                $texto = ($addetails != "" || !isset($addetails)) ? $_SESSION["texto"] : awpcp_esc_textarea($addetails);
		$theformbody.="<br/><br/>$htmlstatus<br/><textarea name=\"addetails\" rows=\"10\" cols=\"50\" class=\"textareainput\" onKeyDown=\"textCounter(this.form.addetails, this.form.remLen, this.form.characters_allowed);\" onKeyUp=\"textCounter(this.form.addetails,this.form.remLen, this.form.characters_allowed);\">" . $texto . "</textarea></p>";

		$output .= $theformbody;

		if ($hasextrafieldsmodule == 1) {
			$output .= build_extra_field_form($action, $adid, $ermsg);
		}

		// XXX: Payment
		// if (get_awpcp_option('freepay') == 1) {
		// 	$output .= "<br/>";
		// 	$output .= "$adtermscode";
		// 	$output .= "<br/>";
		// 	$output .= "$paymethod";
		// }

		if (get_awpcp_option('requiredtos') && $action != 'editad') {
			$tostext = get_awpcp_option('tos');

			if (string_starts_with($tostext, 'http://', false) ||
				string_starts_with($tostext, 'https://', false)) {
				//Output as HTML link:
				$output .=
				    '<p id="awpcp-form-spacer"><a href="'.$tostext.'" target="_blank">'.__("Read our Terms of Service","AWPCP").'</a><br/>';

			} else {
				//Output text as block
				$output .=
				    '<p id="awpcp-form-spacer">'.__("Terms of service:","AWPCP").'<br/>'.
				    '<textarea readonly="readonly" rows="5" cols="50" id="tos_box" name="tos_details">'.
				    $tostext.
				    '</textarea>';
			}

			$output .= '<br/><input type="checkbox" name="tos" value="ok" /> ';
			$output .= __('I agree to the terms of service','AWPCP') . '</p>';
		}

		if ((get_awpcp_option('contactformcheckhuman') == 1) && !is_admin()) {
			$output .= "<p class='awpcp-form-spacer'>";
			$output .= __("Enter the value of the following sum","AWPCP");
			$output .= ": <b>$numval1 + $numval2</b>";
			$output .= "<br/>";
			$output .= "<input type=\"text\" name=\"checkhuman\" value=\"$checkhuman\" size=\"5\" class='inputboxmini'/>";
			$output .= "</p>";
		}


		$continuebuttontxt=__("Continue","AWPCP");
		$output .= "<input type=\"submit\" class=\"button\" value=\"$continuebuttontxt\" />";
		$output .= "</form>";

		$output .= "</div>";
		// Close div style display:$formdisplayvalue

		$output .= "</div>";
		// Close div id classiwrapper

		////////////
		// END form display
		////////////
	}

	// End Handle ad post form
	return $output;
	//End function load_ad_post_form
}


//	END FUNCTION


///////////////////////////////
//	START FUNCTION: display a form to the user when edit existing ad is clicked


function load_ad_edit_form($action,$awpcppagename,$usereditemail,$adaccesskey,$message) {
	$output = '';
	$isadmin=checkifisadmin();

	$editadpagename = sanitize_title(get_awpcp_option('edit-ad-page-name'));
	$editadpageid = awpcp_get_page_id_by_ref('edit-ad-page-name');

	$permastruc=get_option('permalink_structure');
	$url_editpage = get_permalink($editadpageid);
	if (!empty($permastruc)) {
		$awpcpquerymark="?";
	} else {
		$awpcpquerymark="&";
	}

	if (get_awpcp_option('onlyadmincanplaceads') && ($isadmin != 1))
	{
		$output .= "<div id=\"classiwrapper\"><p>";
		$output .= __("You do not have permission to perform the function you are trying to perform. Access to this page has been denied","AWPCP");
		$output .= "</p></div>";
	}
	else
	{

		$checktheform="<script type=\"text/javascript\">
				function checkform() {
					var the=document.myform;
			   		var checkemj = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

					if (checkemj.test(the.editemail.value) == false) {
						alert('Either you did not enter your email address or the email address you entered is not valid.');
						the.editemail.focus();
						return false;
					}

					if (the.adaccesskey.value==='') {
						alert('You did not enter the access key. The access key was emailed to you when you first submitted your ad. You need this key in order to edit your ad.');
						the.adaccesskey.focus();
						return false;
					}

					return true;
				}

			</script>";

		if (!isset($message) || empty($message))
		{
			$message="<p>";
			$message.=__("Please enter the email address you used when you created your ad in addition to the ad access key that was emailed to you after your ad was submitted","AWPCP");
			$message.="</p>";
		}

		$output .= "<div id=\"classiwrapper\">";
		$output .= awpcp_menu_items();

		if (isset($message) && !empty($message)) {
			$output .= $message;
		}

		$checktheform = awpcp_inline_javascript_placeholder('edit-ad', $checktheform);

		$output .= $checktheform;
		$output .= "<form method=\"post\" name=\"myform\" id=\"awpcpui_process\" onsubmit=\"return(checkform())\">";
		$output .= "<input type=\"hidden\" name=\"awpcppagename\" value=\"$awpcppagename\" />";
		$output .= "<input type=\"hidden\" name=\"a\" value=\"doadedit1\" />";
		$output .= "<p>";
		$output .= __("Enter your Email address","AWPCP");
		$output .= "<br/>";
		$output .= "<input type=\"text\" name=\"editemail\" value=\"$usereditemail\" class=\"inputbox\" /></p>";
		$output .= "<p>";
		$output .= __("Enter your ad access key","AWPCP");
		$output .= "<br/>";
		$output .= "<input type=\"text\" name=\"adaccesskey\" value=\"$adaccesskey\" class=\"inputbox\" /></p>";
		$output .= "<input type=\"submit\" class=\"button\" value=\"";
		$output .= __("Continue","AWPCP");
		$output .= "\" /><br/><a href=\"$url_editpage".$awpcpquerymark."a=resendaccesskey\">";
		$output .= __("Resend Ad Access Key","AWPCP");
		$output .= "</a>";
		$output .= "<br/>";
		$output .= "</form>";
		$output .= "</div>";

	}
	return $output;
}


//	END FUNCTION


///////////////////////////////
//	START FUNCTION: display a form to the user for resend access key request


function resendadaccesskeyform($editemail, $awpcppagename) {
    global $wpdb, $nameofsite, $thisadminemail, $message;

    $adminemailoverride = get_awpcp_option('awpcpadminemail');
    if (!empty($adminemailoverride) && strcasecmp($thisadminemail, $adminemailoverride) !== 0) {
        $thisadminemail = $adminemailoverride;
    }

    if (!isset($awpcppagename) || empty($awpcppagename)) {
        $awpcppage = get_currentpagename();
        $awpcppagename = sanitize_title($awpcppage);
    }

    $awpcpresendemailerrortxt=__("Either you did not enter your email address or the email address you entered is not valid","AWPCP");

    $checktheform="<script type=\"text/javascript\">
                function checkform() {
                    var the=document.myform;
                    var checkemj = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

                    if (checkemj.test(the.editemail.value) == false) {
                        alert('$awpcpresendemailerrortxt');
                        the.editemail.focus();
                        return false;
                    }

                    return true;
                }
            </script>";

    $home_url = home_url();

    if (!isset($message) || empty($message)) {
        $message="<p>";
        $message.=__("Please enter the email address you used when you created your ad. Your access key will be sent to that email account. The email address you enter must match up with the email address we have on file","AWPCP");
        $message.="</p>";
    }

    if (isset($editemail) && !empty($editemail)) {
    	// Get the ad titles and access keys in the database that are associated with the email address
        $ads = AWPCP_Ad::find($wpdb->prepare('ad_contact_email=%s', $editemail), 'titleaz');

        $keys = array();
        $max_length = 0;

        foreach ($ads as $ad) {
            if (empty($ad->ad_key)) {
                $ad->ad_key = AWPCP_Ad::generate_key();
                $ad->save();
            }
            $keys[$ad->ad_key] = $ad->ad_title;
            $max_length = max($max_length, strlen($ad->ad_title));
        }

		$subject = get_awpcp_option('resendakeyformsubjectline');
		$introduction = get_awpcp_option('resendakeyformbodymessage');

        if (!empty($keys)) {
        	ob_start();
        		include(AWPCP_DIR . '/frontend/templates/email-resend-ad-key.tpl.php');
        		$body = ob_get_contents();
        	ob_end_clean();

            //email the access key
            if (awpcp_process_mail($thisadminemail, $editemail, $subject, $body, $nameofsite, $thisadminemail)) {
                $awpcpresendprocessresponse = __("Your access key has been emailed to","AWPCP") . " $editemail.";
            } else {
                $awpcpresendprocessresponse=__("There was a problem encountered during the attempt to resend your access key. We apologize. Please try again and if the problem persists, please contact the system administrator","AWPCP");
            }
        } else {
            $awpcpresendprocessresponse=__("There were no ads found registered with the email address provided","AWPCP");
        }

    } else {
        $checktheform = awpcp_inline_javascript_placeholder('resend-access-key', $checktheform);

        $awpcpresendprocessresponse="$checktheform";
        $awpcpresendprocessresponse.="$message";
        $awpcpresendprocessresponse.="<form method=\"post\" name=\"myform\" id=\"awpcpui_process\" onsubmit=\"return(checkform())\">";
        $awpcpresendprocessresponse.="<input type=\"hidden\" name=\"awpcppagename\" value=\"$awpcppagename\" />";
        $awpcpresendprocessresponse.="<input type=\"hidden\" name=\"a\" value=\"resendaccesskey\" />";
        $awpcpresendprocessresponse.="<p>";
        $awpcpresendprocessresponse.=__("Enter your Email address","AWPCP");
        $awpcpresendprocessresponse.="<br/>";
        $awpcpresendprocessresponse.="<input type=\"text\" name=\"editemail\" value=\"$editemail\" class=\"inputbox\" /></p>";
        $awpcpresendprocessresponse.="<input type=\"submit\" class=\"button\" value=\"";
        $awpcpresendprocessresponse.=__("Continue","AWPCP");
        $awpcpresendprocessresponse.="\" /><br/></form>";

    }

    $output = '';
    $output .= "<div id=\"classiwrapper\">";
    $output .= awpcp_menu_items();
    $output .= $awpcpresendprocessresponse;
    $output .= "</div>";

    return $output;
}

//	START FUNCTION: Display a form to be filled out in order to contact the ad poster


function load_ad_contact_form($adid,$sendersname,$checkhuman,$numval1,$numval2,$sendersemail,$contactmessage,$message)
{
	$output = '';
	$awpcppage=get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage, $post_ID='');

	$quers=setup_url_structure($awpcppagename);

	$contactformcheckhumanhighnumval=get_awpcp_option('contactformcheckhumanhighnumval');

	$numval1=rand(1,$contactformcheckhumanhighnumval);
	$numval2=rand(1,$contactformcheckhumanhighnumval);

	$thesum=($numval1 + $numval2);

	if (get_awpcp_option('contactformcheckhuman') == 1)
	{
		$nosumvalueerror=__("You did not enter the solution to the Math problem","AWPCP");
		$wrongsumvalueerror=__("The solution you submitted for the Math problem was not correct","AWPCP");

		$conditionscheckhuman="

				if (the.checkhuman.value==='') {
					alert('$nosumvalueerror');
					the.checkhuman.focus();
					return false;
				}
				if (the.checkhuman.value != $thesum) {
					alert('$wrongsumvalueerror');
					the.checkhuman.focus();
					return false;
				}

			";
	}
	else
	{
		$conditionscheckhuman ="";
	}

	$awpcpusernamemissing=__("You did not enter your name. Please enter your name","AWPCP");
	$awpcpemailinvalid=__("Either you did not enter your email address or the email address you entered is not valid","AWPCP");
	$awpcpmessagebodymissing=__("You did not enter any message. Please enter a message","AWPCP");

	$checktheform="<script type=\"text/javascript\">
	function checkform() {
		var the=document.myform;
		var checkemj = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;

		if (the.sendersname.value==='') {
			alert('$awpcpusernamemissing');
			the.sendersname.focus();
			return false;
		}

		if (checkemj.test(the.sendersemail.value) == false) {
			alert('$awpcpemailinvalid');
			the.sendersemail.focus();
			return false;
		}
		if (the.contactmessage.value==='') {
			alert('$awpcpmessagebodymissing');
			the.contactmessage.focus();
			return false;
		}

		$conditionscheckhuman;

		return true;
	}</script>";

		$output .= "<div id=\"classiwrapper\">";
		$output .= awpcp_menu_items();
		$isadmin=checkifisadmin();

		$theadtitle=get_adtitle($adid);
		// $modtitle = sanitize_title($theadtitle);

		$permastruc=get_option('permalink_structure');
		$showadspagename=sanitize_title(get_awpcp_option('show-ads-page-name'), $post_ID='');

		$url_showad = url_showad($adid);
		$thead="<a href=\"$url_showad\">$theadtitle</a>";


		$output .= "<p>";
		$output .= __("You are responding to ","AWPCP");
		$output .= "$thead</p>";

		if (isset($message) && !empty($message)) {
			$output .= "$message";
		}

		$checktheform = awpcp_inline_javascript_placeholder('ad-contact', $checktheform);

		$output .= $checktheform;
		$output .= "<form method=\"post\" name=\"myform\" id=\"awpcpui_process\" onsubmit=\"return(checkform())\">";
		$output .= "<input type=\"hidden\" name=\"adid\" value=\"$adid\" />";
		$output .= "<input type=\"hidden\" name=\"a\" value=\"docontact1\" />";
		$output .= "<input type=\"hidden\" name=\"numval1\" value=\"$numval1\" />";
		$output .= "<input type=\"hidden\" name=\"numval2\" value=\"$numval2\" />";
		$output .= "<p>";
		$output .= __("Your Name","AWPCP");
		$output .= "<br/>";
		$output .= "<input type=\"text\" name=\"sendersname\" value=\"$sendersname\" class=\"inputbox\" /></p>";
		$output .= "<p>";
		$output .= __("Enter your Email address","AWPCP");
		$output .= "<br/>";
		$output .= "<input type=\"text\" name=\"sendersemail\" value=\"$sendersemail\" class=\"inputbox\" /></p>";
		$output .= "<p>";
		$output .= __("Enter your message below","AWPCP");
		$output .= "<br/>";
		$output .= "<textarea name=\"contactmessage\" rows=\"5\" cols=\"90%\" class=\"textareainput\">$contactmessage</textarea></p>";

		if (get_awpcp_option('contactformcheckhuman') == 1)
		{
			$output .= "<p>";
			$output .= __("Enter the value of the following sum","AWPCP");
			$output .= ": <b>$numval1 + $numval2</b><br>";
			$output .= "<input type=\"text\" name=\"checkhuman\" value=\"$checkhuman\" size=\"5\" class='inputboxmini'/></p>";
		}

		$output .= "<input type=\"submit\" class=\"button\" value=\"";
		$output .= __("Continue","AWPCP");
		$output .= "\" />";
		$output .= "<br/></form></div>";
	return $output;
}


//	END FUNCTION



//	START FUNCTION: Process the request to contact the poster of the ad


function processadcontact($adid, $sendersname, $checkhuman, $numval1,
						  $numval2, $sendersemail, $contactmessage, $ermsg) {

	global $nameofsite, $thisadminemail;

	$output = '';
	$adminemailoverride = get_awpcp_option('awpcpadminemail');

	if (isset($adminemailoverride) && !empty($adminemailoverride)
		&& !(strcasecmp($thisadminemail, $adminemailoverride) == 0)) {
		$thisadminemail=$adminemailoverride;
	}

	$error=false;
	$adidmsg='';
	$sendersnamemsg='';
	$checkhumanmsg='';
	$sendersemailmsg='';
	$contactmessagemsg='';
	$sumwrongmsg='';
	$sendersemailwrongmsg='';

	$thesum=($numval1 +  $numval2);

	if (!isset($adid) || empty($adid)) {
		$error=true;
		$adidmsg="<li>";
		$adidmsg.=__("The ad could not be identified due to a missing ad identification number","AWPCP");
		$adidmsg.="</li>";
	}

	if (!isset($sendersname) || empty($sendersname)) {
		$error=true;
		$sendersnamemsg="<li>";
		$sendersnamemsg.=__("You did not enter your name. You must include a name for this message to be relayed on your behalf","AWPCP");
		$sendersnamemsg.="</li>";
	}

	if (get_awpcp_option('contactformcheckhuman') == 1) {
		if (!isset($checkhuman) || empty($checkhuman)) {
			$error=true;
			$checkhumanmsg="<li>";
			$checkhumanmsg.=__("You did not solve the Math Problem","AWPCP");
			$checkhumanmsg.="</li>";
		}

		if ($checkhuman != $thesum) {
			$error=true;
			$sumwrongmsg="<li>";
			$sumwrongmsg.=__("Your solution to the Math problem was incorrect","AWPCP");
			$sumwrongmsg.="</li>";
		}
	}

	if (!isset($contactmessage) || empty($contactmessage)) {
		$error=true;
		$contactmessagemsg="<li>";
		$contactmessagemsg.=__("There was no text entered for your message","AWPCP");
		$contactmessagemsg.="</li>";
	}

	if (!isset($sendersemail) || empty($sendersemail)) {
		$error=true;
		$sendersemailmsg="<li>";
		$sendersemailmsg.=__("You did not enter your name. You must include a name for this message to be relayed on your behalf","AWPCP");
		$sendersemailmsg.="</li>";
	}

	if (!isValidEmailAddress($sendersemail)) {
		$error=true;
		$sendersemailwrongmsg="<li>";
		$sendersemailwrongmsg.=__("The email address you entered was not a valid email address. Please check for errors and try again","AWPCP");
		$sendersemailwrongmsg.="</li>";
	}

	if (get_awpcp_option('useakismet')) {
		if (awpcp_check_spam($sendersname, '', $sendersemail, $contactmessage)) {
			//Spam detected!
			$error=true;
			$spammsg="<li>";
			$spammsg.=__("Your contact was flagged as spam.  Please contact the administrator of this site.","AWPCP");
			$spammsg.="</li>";
		}
	}

	if ($error) {
		$ermsg="<p>";
		$ermsg.=__("There has been an error found. Your message has not been sent. Please review the list of problems, correct them then try to send your message again","AWPCP");
		$ermsg.="</p>";
		$ermsg.="<b>";
		$ermsg.=__("The errors","AWPCP");
		$ermsg.=":</b><br/>";
		$ermsg.="<ul>$adidmsg $sendersnamemsg $checkhumanmsg $contactmessagemsg $sumwrongmsg $sendersemailmsg $sendersemailwrongmsg $spammsg</ul>";

		$output .= load_ad_contact_form($adid,$sendersname,$checkhuman,$numval1,$numval2,$sendersemail,$contactmessage,$ermsg);

	} else {
		// strip slashes added by WP and AWPCP functions
		$contactmessage = stripslashes(strip_html_tags($contactmessage));
		$contactmessage = stripslashes($contactmessage);
		$theadtitle = stripslashes(stripslashes(get_adtitle($adid)));
		$sendersname = stripslashes(stripslashes($sendersname));
		$url_showad = url_showad($adid);

		// build the message's body
		ob_start();
			include(AWPCP_DIR . 'frontend/templates/email-reply-to-ad.tpl.php');
			$body = ob_get_contents();
		ob_end_clean();

		// email the buyer
		$subject = sprintf("%s %s: %s", get_awpcp_option('contactformsubjectline'),
										_x('Regarding', 'reply email', 'AWPCP'),
										$theadtitle);
		$subject = trim($subject);

		if (get_awpcp_option('usesenderemailinsteadofadmin')) {
			$sender = strip_html_tags($sendersname);
			$from = $sendersemail;
		} else {
			$sender = $nameofsite;
			$from = $thisadminemail;
		}
		$sendtoemail = get_adposteremail($adid);

		if (awpcp_process_mail($from, $sendtoemail, $subject, $body, $sender, $sendersemail)) {
			$response =__("Your message has been sent","AWPCP");
		} else {
			$response =__("There was a problem encountered during the attempt to send your message. Please try again and if the problem persists, please contact the system administrator","AWPCP");
		}
	}

	$output .= "<div id=\"classiwrapper\">";
	$output .= awpcp_menu_items();
	$output .= $response ;
	$output .= "</div>";

	return $output;
}


//	END FUNCTION




//	START FUNCTION: display the ad search form




//	END FUNCTION


/**
 * Search for Ads
 */
function dosearch() {
	$output = '';
	global $wpdb,$hasextrafieldsmodule;
	$tbl_ads = $wpdb->prefix . "awpcp_ads";
	
	$keywordphrase=clean_field(urldecode(awpcp_request_param('keywordphrase')));
	$searchname=clean_field(awpcp_request_param('searchname'));
	$searchcategory=clean_field(awpcp_request_param('searchcategory'));
	$searchpricemin=clean_field(awpcp_request_param('searchpricemin'));
	$searchpricemax=clean_field(awpcp_request_param('searchpricemax'));
	$searchcity=clean_field(stripslashes(awpcp_request_param('searchcity')));
	$searchstate=clean_field(stripslashes(awpcp_request_param('searchstate')));
	$searchcountry=clean_field(stripslashes(awpcp_request_param('searchcountry')));
	$searchcountyvillage=clean_field(stripslashes(awpcp_request_param('searchcountyvillage')));

	$message='';

	$error=false;
	$theerrorslist="<h3>";
	$theerrorslist.=__("Cannot process your request due to the following error","AWPCP");
	$theerrorslist.=":</h3><ul>";
	if (!isset($keywordphrase) && empty($keywordphrase) &&
	!isset($searchname) && empty($searchname) &&
	!isset($searchcity) && empty($searchcity) &&
	!isset($searchstate) && empty($searchstate) &&
	!isset($searchcountry) && empty($searchcountry) &&
	!isset($searchpricemin) && empty($searchpricemin) &&
	!isset($searchpricemax) && empty($searchpricemax) &&
	!isset($searchcategory) && empty ($searchcategory) &&
	!isset($searchcountyvillage) && empty ($searchcountyvillage)) {
		$error=true;
		$theerrorslist.="<li>";
		$theerrorslist.=__("You did not enter a keyword or phrase to search for. You must at the very least provide a keyword or phrase to search for","AWPCP");
		$theerrorslist.="</li>";
	}

	if ( !empty($searchpricemin) )
	{
		if ( !is_numeric($searchpricemin) )
		{
			$error=true;
			$theerrorslist.="<li>";
			$theerrorslist.=__("You have entered an invalid minimum price. Make sure your price contains numbers only. Please do not include currency symbols","AWPCP");
			$theerrorslist.="</li>";
		}
	}

	if ( !empty($searchpricemax) )
	{
		if (	!is_numeric($searchpricemax) )
		{
			$error=true;
			$theerrorslist.="<li>";
			$theerrorslist.=__("You have entered an invalid maximum price. Make sure your price contains numbers only. Please do not include currency symbols","AWPCP");
			$theerrorslist.="</li>";
		}
	}

	if ( empty($searchpricemin) && !empty($searchpricemax) ) {
		$searchpricemin=1;
	}

	if ( !empty($keywordphrase) ) {
		if (strlen($keywordphrase) < 4) {
			$error=true;
			$theerrorslist.="<li>";
			$theerrorslist.=__("You have entered a keyword that is too short to search on.  Search keywords must be at least 4 letters in length.  Please try another term","AWPCP");
			$theerrorslist.="</li>";
		}
	}
	$theerrorslist.="</ul>";
	$message="<p>$theerrorslist</p>";

	if ($error){
		$output .= load_ad_search_form($keywordphrase,$searchname,$searchcity,$searchstate,$searchcountry,$searchcountyvillage,$searchcategory,$searchpricemin,$searchpricemax,$message);
	}

	else
	{
		$where="disabled =0";

		if (isset($keywordphrase) && !empty($keywordphrase))
		{
			$where.=" AND MATCH (ad_title,ad_details) AGAINST (\"$keywordphrase\" IN BOOLEAN MODE)";
		}

		if (isset($searchname) && !empty($searchname))
		{
			$where.=" AND ad_contact_name = '$searchname'";
		}

		if (isset($searchcity) && !empty($searchcity))
		{

			if (is_array( $searchcity ) )
			{

				$cities=explode(",",$searchcity);
				$city=array();

				for ($i=0;isset($cities[$i]);++$i) {
					$city[]=$cities[$i];
					$citieslist=join("','",$city);
				}

				$where.=" AND ad_city IN ('$citieslist')";
			}
			else
			{
				$where.=" AND ad_city ='$searchcity'";
			}
		}

		if (isset($searchstate) && !empty($searchstate))
		{
			if (is_array( $searchstate ) )
			{

				$states=explode(",",$searchstate);
				$state=array();

				for ($i=0;isset($states[$i]);++$i) {
					$state[]=$states[$i];
					$stateslist=join("','",$state);
				}
				$where.=" AND ad_state IN ('$stateslist')";
			}
			else
			{
				$where.=" AND ad_state ='$searchstate'";
			}
		}

		if (isset($searchcountry) && !empty($searchcountry))
		{
			if (is_array( $searchcountry ) )
			{
				$countries=explode(",",$searchcountry);
				$country=array();

				for ($i=0;isset($countries[$i]);++$i) {
					$country[]=$countries[$i];
					$countrieslist=join("','",$country);
				}
				$where.=" AND ad_country IN ('$countrieslist')";
			}
			else
			{
				$where.=" AND ad_country ='$searchcountry'";
			}
		}

		if (isset($searchcountyvillage) && !empty($searchcountyvillage)){

			if (is_array( $searchcountyvillage ) )
			{
				$counties=explode(",",$searchcountyvillage);
				$county=array();

				for ($i=0;isset($counties[$i]);++$i) {
					$county[]=$counties[$i];
					$countieslist=join("','",$county);
				}
				$where.=" AND ad_county_village IN ('$countieslist')";
			}
			else
			{
				$where.=" AND ad_county_village ='$searchcountyvillage'";
			}

		}

		if (isset($searchcategory) && !empty($searchcategory))
		{
			$where.=" AND (ad_category_id = '$searchcategory' OR ad_category_parent_id = '$searchcategory')";
		}

		if (isset($searchpricemin) && !empty($searchpricemin))
		{
			$searchpricemincents=($searchpricemin * 100);
			$where.=" AND ad_item_price >= '$searchpricemincents'";
		}

		if (isset($searchpricemax) && !empty($searchpricemax))
		{
			$searchpricemaxcents=($searchpricemax * 100);
			$where.=" AND ad_item_price <= '$searchpricemaxcents'";
		}

		if ($hasextrafieldsmodule == 1) {
			// Is the extra fields module present with the required search builder function?
			// If so call the "where clause" builder function
			if (function_exists('build_extra_fields_search_where')) {
			    $where .=  build_extra_fields_search_where();
			}
		}

		$grouporderby=get_group_orderby();

		$output .= awpcp_display_ads($where,$byl='',$hidepager='',$grouporderby,$adorcat='ad');

	}
	return $output;
}


//	START FUNCTION: process first step of edit ad request



function editadstep1($adaccesskey, $editemail, $awpcppagename) {
	global $wpdb, $hasextrafieldsmodule;

	$tbl_ads = $wpdb->prefix . "awpcp_ads";
	$output = '';

	$offset=(isset($_REQUEST['offset'])) ? (clean_field($_REQUEST['offset'])) : ($offset=0);
	$results=(isset($_REQUEST['results']) && !empty($_REQUEST['results'])) ? clean_field($_REQUEST['results']) : ($results=10);

	$query="SELECT ad_id,adterm_id FROM ".$tbl_ads." WHERE ad_key='$adaccesskey' AND ad_contact_email='$editemail'";
	$res = awpcp_query($query, __LINE__);

	while ($rsrow=mysql_fetch_row($res)) {
		list($adid,$adtermid)=$rsrow;
	}

	if (isset($adid) && !empty($adid)) {
		$output .= load_ad_post_form($adid,$action='editad',$awpcppagename,$adtermid,$editemail,$adaccesskey,$adtitle='',$adcontact_name='',$adcontact_phone='',$adcontact_email='',$adcategory='',$adcontact_city='',$adcontact_state='',$adcontact_country='',$ad_county_village='',$ad_item_price='',$addetails='',$adpaymethod='',$offset,$results,$ermsg='',$websiteurl='',$checkhuman='',$numval1='',$numval2='');

	} else {
		$message="<p class=\"messagealert\">";
		$message.=__("The information you have entered does not match the information on file. Please make sure you are using the same email address you used to post your ad and the exact access key that was emailed to you when you posted your ad","AWPCP");
		$message.="</p>";

		$output .= load_ad_edit_form($action='editad',$awpcppagename,$editemail,$adaccesskey,$message);
	}

	return $output;
}


//	END FUNCTION


// TODO: fix other calls to this function
/**
 * Handles Place Ad - Step 1
 */
// function processadstep1($adid, $adterm_id, $adkey, $editemail, $adtitle,
// 	$adcontact_name, $adcontact_phone, $adcontact_email, $adcategory,
// 	$adcontact_city, $adcontact_state, $adcontact_country, $ad_county_village,
// 	$ad_item_price, $addetails, $adpaymethod, $adaction, $awpcppagename,
// 	$offset, $results, $ermsg, $websiteurl, $checkhuman, $numval1, $numval2,
// 	$is_featured_ad, $transaction=null)
function processadstep1($form_values=array(), &$form_errors=array(), $transaction=null, $edit=false) {

	global $wpdb, $awpcp_imagesurl, $hasextrafieldsmodule;
	global $current_user;
	get_currentuserinfo();

	$adid = awpcp_array_data('adid', '', $form_values);
	$adterm_id = awpcp_array_data('adterm_id', '', $form_values);
	$adkey = awpcp_array_data('adkey', '', $form_values);
	$editemail = awpcp_array_data('editemail', '', $form_values);
	$adtitle = awpcp_array_data('adtitle', '', $form_values);

	$adcontact_name = awpcp_array_data('adcontact_name', '', $form_values);
	$adcontact_phone = awpcp_array_data('adcontact_phone', '', $form_values);
	$adcontact_email = awpcp_array_data('adcontact_email', '', $form_values);
	$adcategory = awpcp_array_data('adcategory', '', $form_values);

	$adcontact_city = awpcp_array_data('adcontact_city', '', $form_values);
	$adcontact_state = awpcp_array_data('adcontact_state', '', $form_values);
	$adcontact_country = awpcp_array_data('adcontact_country', '', $form_values);
	$ad_county_village = awpcp_array_data('ad_county_village', '', $form_values);

	$ad_item_price = awpcp_array_data('ad_item_price', '', $form_values);
	$addetails = awpcp_array_data('addetails', '', $form_values);
	$adpaymethod = awpcp_array_data('adpaymethod', '', $form_values);
	$adaction = awpcp_array_data('adaction', '', $form_values);
	$awpcppagename = awpcp_array_data('awpcppagename', '', $form_values);

	$offset = awpcp_array_data('offset', '', $form_values);
	$results = awpcp_array_data('results', '', $form_values);
	$ermsg = awpcp_array_data('ermsg', '', $form_values);
	$websiteurl = awpcp_array_data('websiteurl', '', $form_values);
	$checkhuman = awpcp_array_data('checkhuman', '', $form_values);
	$numval1 = awpcp_array_data('numval1', '', $form_values);
	$numval2 = awpcp_array_data('numval2', '', $form_values);

	$user_id = awpcp_array_data('user_id', 0, $form_values);
	$user_payment_term = awpcp_array_data('user_payment_term', 0, $form_values);

	$is_featured_ad = intval(awpcp_array_data('is_featured_ad', 0, $form_values));

	if (!is_null($transaction)) {
		$category = $transaction->get('category');
	} else {
		$category = $adcategory;
	}
	$show_category_field = empty($category);


	$tbl_ad_fees = AWPCP_TABLE_ADFEES;
	$tbl_ads = AWPCP_TABLE_ADS;
	$tbl_ad_photos = AWPCP_TABLE_ADPHOTOS;

	$permastruc=get_option('permalink_structure');
	$output = '';

	// Check the form to make sure no required information is missing

	$is_admin = awpcp_current_user_is_admin();

	// TODO: what is this for?
	if ('' == $adcategory) {
		$adcategory = absint(awpcp_array_data('adcat', 0, $_POST));
	}

	if (!awpcp_validate_ad_details($form_values, $form_errors, $adaction == 'editad')) {
		$ermsg="<p><img src=\"$awpcp_imagesurl/Warning.png\" border=\"0\" alt=\"Alert\" style=\"float:left;margin-right:10px;\"/>";
		$ermsg.=__("There has been an error found. Please review the list of problems, correct them then try again","AWPCP");
		$ermsg.="</p><b>";
		$ermsg.=__("The errors","AWPCP");
		$ermsg.=":</b><br/><ul>";

		$ermsg.= '<li class="errorleft">' . join('</li></li class="errorleft">', $form_errors) . '</li>';
		$ermsg.="</ul>";

		$transaction_id = $transaction ? $transaction->id : '';

		// TODO: call ad_details_step...
		return load_ad_post_form($adid, $action=$adaction, $awpcppagename, $adterm_id,
			$editemail, $adkey, $adtitle, $adcontact_name, $adcontact_phone,
			$adcontact_email, $adcategory, $adcontact_city, $adcontact_state,
			$adcontact_country, $ad_county_village, $ad_item_price,
			$addetails, $adpaymethod, $offset, $results, $ermsg, $websiteurl,
			$checkhuman, $numval1, $numval2, '', $show_category_field, $transaction_id,
			$user_id, $user_payment_term);
	}

	// TODO: does it works?
	if ($adaction == 'delete') {
		$output .= deletead($adid, $adkey, $editemail);

	} else if ($adaction == 'editad') {
		$qdisabled='';

		if (!is_admin()) {
			$disabled = awpcp_calculate_ad_disabled_state($adid, null);
			$qdisabled="disabled=$disabled,";
		}

		// make sure we use integers
		$adcategory = intval($adcategory);
		$adcategory_parent_id = intval(get_cat_parent_ID($adcategory));

		// This line is a hack to store decimal prices using
		// an INT column. It attempts to store 99.95 as 9995.
		$itempriceincents = ($ad_item_price * 100);

		$update_x_fields = "";
		if ($hasextrafieldsmodule == 1) {
			$update_x_fields = do_x_fields_update();
		}

		$user_id = trim($user_id);
		if ($user_id === 0 || empty($user_id)) {
			$user_id = 'user_id'; // do not change
		}

		$ad = AWPCP_Ad::find_by_id($adid);
		do_action('awpcp_before_edit_ad', $ad);

		$query = "UPDATE " . AWPCP_TABLE_ADS . " ";
		$query.= "SET ad_category_id=$adcategory, ad_category_parent_id=$adcategory_parent_id, ";
		$query.= "ad_title='$adtitle', ad_details='$addetails', websiteurl='$websiteurl', ";
		$query.= "ad_contact_phone='$adcontact_phone', ad_contact_name='$adcontact_name', ";
		$query.= "ad_contact_email='$adcontact_email', ad_city='$adcontact_city', ad_state='$adcontact_state', ";
		$query.= "ad_country='$adcontact_country', ad_county_village='$ad_county_village', ";
		$query.= "ad_item_price='$itempriceincents', is_featured_ad=$is_featured_ad, ";
		$query.= "$qdisabled $update_x_fields ad_last_updated=now(), ";
		$query.= "user_id=$user_id WHERE ad_id='$adid' AND ad_key='$adkey'";

		$res = awpcp_query($query, __LINE__);

		if ($is_admin == 1 && is_admin()) {
			$message=__("The ad has been edited successfully.", 'AWPCP');
			$message.=" <a href=\"?page=Manage1&offset=$offset&results=$results\">";
			$message.=__("Back to view listings", 'AWPCP');
			$message.="</a>";

			$output .= $message;

		} else {
			// Step 2 of Editing an Ad process
			// return awpcp_place_ad_upload_images_step(array('ad_id' => $ad_id));

			if (is_admin()) {
				// do not show additional output in Admin screen
				$output = '';

			} else if (get_awpcp_option('imagesallowdisallow')) {
				$totalimagesallowed = awpcp_get_ad_number_allowed_images($adid, $adterm_id);

				if ($totalimagesallowed > 0) {
					$output .= editimages($adterm_id,$adid,$adkey,$editemail);
				} else {
					$output = awpcp_place_ad_finish($adid, true);
				}

			} else {
				$output = awpcp_place_ad_finish($adid, true);
			}
		}

		$ad = AWPCP_Ad::find_by_id($adid);
		do_action('awpcp_edit_ad', $ad);

	} else {
		// Begin processing new ad

		// if an ad-id is set, the user probably submitted the page again
		// let's skip the Ad creation step and proceed with the upload
		// images step.
		$ad_id = $transaction->get('ad-id', 0);
		if ($ad_id <= 0) {
			$key = AWPCP_Ad::generate_key();
			$adcategory_parent_id = get_cat_parent_ID($adcategory);
			$itempriceincents = ($ad_item_price * 100);
			$posterip = awpcp_getip();

			$update_x_fields = '';
			if ($hasextrafieldsmodule == 1) {
				$update_x_fields = do_x_fields_update();
			}

			if ($user_id === 0 && isset($current_user->ID) && !empty($current_user)) {
				$user_id = $current_user->ID;
			} else if ($user_id === 0) {
				$user_id = 'NULL';
			}

			if (!empty($user_payment_term)) {
				$p = strrpos($user_payment_term, '-');
				$payment_term_type = substr($user_payment_term, 0, $p);
				$payment_term_id = substr($user_payment_term, $p+1);
				if ($payment_term_id == 0) {
					$transaction->set('free', true);
				} else {
					$transaction->set('payment-term-type', $payment_term_type);
					$transaction->set('payment-term-id', $payment_term_id);
					// $transaction->set('payment-status', )
					$transaction->set('free', false);
				}
			}

			if (strcmp($transaction->get('payment-term-type'), 'ad-term-fee') === 0) {
				$adtermid = $transaction->get('payment-term-id');
				$fee = awpcp_get_fee($adtermid);

				if ($fee && $fee->characters_allowed > 0) {
					$addetails = substr($addetails, 0, $fee->characters_allowed);
				}

			} else {
				$adtermid = 0;
			}

			list($duration, $interval) = apply_filters('awpcp-place-ad-duration', array(30, 'DAY'), $transaction);

			$payment_status = $transaction->get('payment-status');
			$is_pending_payment = $payment_status == AWPCP_Payment_Transaction::$PAYMENT_STATUS_PENDING;

			$disabled = awpcp_calculate_ad_disabled_state(null, $transaction);

			$txn_id = $transaction->get('txn-id', '');
			$amount = $transaction->get('amount', 0);

                        
// TODO: handle all transaction attributes, remove unused attributes from form_values
			$query = "INSERT INTO " . AWPCP_TABLE_ADS . " SET ";
			$query.= "ad_category_id='$adcategory', ad_category_parent_id='$adcategory_parent_id', ";
			$query.= "ad_title='$adtitle', ad_details='$addetails', ad_contact_phone='$adcontact_phone', ";
			$query.= "ad_contact_name='$adcontact_name', ad_contact_email='$adcontact_email',  ";
			$query.= "ad_city='$adcontact_city', ad_state='$adcontact_state', ad_country='$adcontact_country', ";
			$query.= "ad_county_village='$ad_county_village', ad_item_price='$itempriceincents', ";
			$query.= "websiteurl='$websiteurl', is_featured_ad=$is_featured_ad, posterip='$posterip',";
			$query.= "adterm_id=$adtermid, payment_status='$payment_status', ";
			$query.= "ad_startdate=NOW(), ad_enddate=NOW()+INTERVAL $duration $interval, ";
			$query.= "disabled='$disabled', ad_key='$key', ad_transaction_id='$txn_id', ad_fee_paid=$amount, ";
			$query.= "ad_last_updated=now(), $update_x_fields ad_postdate=now(), user_id=$user_id";

                        $query.= ",lineas='".$_SESSION["lineas"]."'";
                        $query.= ",fondo='".$_SESSION["fondo"]."'";
                        $query.= ",marco='".$_SESSION["marco"]."'";
                        $query.= ",texto='".$_SESSION["texto"]."'";
                        
			$res = awpcp_query($query, __LINE__);
			$ad_id = mysql_insert_id();

			$transaction->set('ad-id', $ad_id);
			$transaction->set('completed', current_time('mysql'));
			$transaction->save();

			// TODO: update hooked handlers
			// Notify plugins an Ad has been placed
			do_action('awpcp-place-ad', AWPCP_Ad::find_by_id($ad_id), $transaction);
		}

		return awpcp_place_ad_upload_images_step(array('ad_id' => $ad_id));
	}

	return $output;
}



// function processadstep2_paymode($ad_id,$adterm_id,$adkey,$awpcpuerror,$adcontact_name,$adcontact_phone,$adcontact_city,$adcontact_state,$adcontact_country,$adtitle,$addetails,$adpaymethod,$adaction) {
// 	//debug();
// 	$output = '';
// 	if (get_awpcp_option('imagesallowdisallow') == 1) {
// 		$numimgsallowed = get_numimgsallowed($adterm_id);
// 		$numimgsallowed = apply_filters('awpcp_number_images_allowed', $numimgsallowed, $ad_id, $adterm_id);

// 		if ( $numimgsallowed <= 0 ) {
// 			$output .= "<h2>";
// 			$output .= __("Step 2 Finalize","AWPCP");
// 			$output .= "</h2>";
// 		} else {
// 			$output .= "<h2>";
// 			$output .= __("Step 2 Upload Images","AWPCP");
// 			$output .= "</h2>";
// 		}

// 		$totalimagesuploaded=get_total_imagesuploaded($ad_id);

// 		if ($totalimagesuploaded < $numimgsallowed) {
// 			//debug('more images');
// 			$showimageuploadform=display_awpcp_image_upload_form($ad_id,$adterm_id,$adkey,$adaction,$nextstep='payment',$adpaymethod,$awpcpuperror='');
// 		} else {
// 			//debug('no images');
// 			$showimageuploadform=display_awpcp_image_upload_form($ad_id,$adterm_id,$adkey,$adaction,$nextstep='paymentnoform',$adpaymethod,$awpcpuperror='');
// 		}

// 		$classicontent=$showimageuploadform;
// 		$output .= "$classicontent";
// 	} else {
// 		$output .= processadstep3($ad_id,$adterm_id,$adkey,$adpaymethod);
// 	}

// 	do_action('awpcp_post_ad');

// 	return $output;
// }

// function processadstep2_freemode($ad_id,$adterm_id,$adkey,$awpcpuerror,$adcontact_name,$adcontact_phone,$adcontact_city,$adcontact_state,$adcontact_country,$adtitle,$addetails,$adpaymethod)
// {
// 	//debug();
// 	$output = '';
// 	$totalimagesuploaded=get_total_imagesuploaded($ad_id);

// 	if (isset($adaction) && !empty($adaction)) {
// 		$adaction=$adaction;
// 	} else {
// 		$adaction='';
// 	}

// 	if (!isset($totalimagesuploaded) || empty($totalimagesuploaded))
// 	{
// 		$totalimagesuploaded=0;
// 	}

// 	if ( (get_awpcp_option('imagesallowdisallow') == 1) && ( get_awpcp_option('imagesallowedfree') > 0))
// 	{

// 		$output .= "<h2>";
// 		$output .= __("Step 2 Upload Images","AWPCP");
// 		$output .= "</h2>";

// 		$imagesforfree=get_awpcp_option('imagesallowedfree');

// 		if ($totalimagesuploaded < $imagesforfree)
// 		{
// 			$showimageuploadform=display_awpcp_image_upload_form($ad_id,$adterm_id,$adkey,$adaction,$nextstep='finish',$adpaymethod,$awpcpuperror='');
// 		}
// 		else
// 		{
// 			$showimageuploadform=display_awpcp_image_upload_form($ad_id,$adterm_id,$adkey,$adaction,$nextstep='finishnoform',$adpaymethod,$awpcpuperror='');
// 		}

// 		$classicontent="$showimageuploadform";
// 		$output .= "$classicontent";
// 	}
// 	else
// 	{
// 		$awpcpadpostedmsg=__("Your ad has been submitted","AWPCP");

// 		if (get_awpcp_option('adapprove') == 1)
// 		{
// 			$awaitingapprovalmsg=get_awpcp_option('notice_awaiting_approval_ad');
// 			$awpcpadpostedmsg.="<p>";
// 			$awpcpadpostedmsg.=$awaitingapprovalmsg;
// 			$awpcpadpostedmsg.="</p>";
// 		}
// 		if (get_awpcp_option('imagesapprove') == 1)
// 		{
// 			$imagesawaitingapprovalmsg=__("If you have uploaded images your images will not show up until an admin has approved them.","AWPCP");
// 			$awpcpadpostedmsg.="<p>";
// 			$awpcpadpostedmsg.=$imagesawaitingapprovalmsg;
// 			$awpcpadpostedmsg.="</p>";
// 		}



// 		// The code below was outside of this IF block and was
// 		// being executed twice, with images or without them
// 		// as stated in the comment. However, that was making the plugin
// 		// sent two success emails to the user and two emails to the admin.
// 		// When no images are posted the users is redirected to the Show Ad
// 		// page which sends the confirmation email. When images are posted the
// 		// functions handling the uploadas will send the email.
// 		//
// 		// I moved the code inside the IF block.

// 		//Images or not, send the email:
// 		$message=$awpcpadpostedmsg;
// 		$awpcpsubmissionresultmessage = ad_success_email($ad_id,$txn_id='',$adkey,$awpcpadpostedmsg,$gateway='');

// 		$output .= "<div id=\"classiwrapper\">";
// 		$output .= '<p class="ad_status_msg">';
// 		$output .= $awpcpsubmissionresultmessage;
// 		$output .= "</p>";
// 		$output .= awpcp_menu_items();
// 		$output .= "<h2>";
// 		$output .= __("Your Ad is posted","AWPCP");
// 		$output .= "</h2>";
// 		$output .= showad($ad_id,$omitmenu=1);
// 		$output .= "</div>";
// 	}

// 	do_action('awpcp_post_ad');

// 	return $output;
// }

function processadstep3($adid, $adterm_id, $key, $adpaymethod) {
	global $wpdb;

	//Shortcut payment for admin only (allows admin to post ads without paying)
	if ( current_user_can('administrator') ) {
		if ( get_awpcp_option('seofriendlyurls') ) {
			$preview = ( url_showad( intval( $_POST['adid'] ) ).'?adstatus=preview');
		} else {
			$preview = ( url_showad( intval( $_POST['adid'] ) ).'&adstatus=preview');
		}
		$out ="<h2>" . __("Step 3 Payment","AWPCP") . '</h2>';
		$out .= '<p>' . __("You're logged in as an adminstrator, so there's no payment required.","AWPCP") . '</p>';
		$out .= '<p><a href="'.$preview.'">' . __("Click here to preview your ad","AWPCP") . '</a></p>';
		return $out;
	}

	$output = '';
	$tbl_ad_fees = $wpdb->prefix . "awpcp_adfees";

	$permastruc=get_option('permalink_structure');
	$awpcppage=get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	$quers=setup_url_structure($awpcppagename);
	$amount=0;

	$paymentthankyoupagename=sanitize_title(get_awpcp_option('payment-thankyou-page-name'));
	$paymentthankyoupageid=awpcp_get_page_id_by_ref('payment-thankyou-page-name');

	$paymentcancelpagename=sanitize_title(get_awpcp_option('payment-cancel-page-name'));
	$paymentcancelpageid=awpcp_get_page_id_by_ref('payment-cancel-page-name');

	if (isset($adpaymethod) && !empty($adpaymethod)) {
		if ($adpaymethod == 'paypal') {
			$custadpcde="PP";
		} elseif ($adpaymethod == '2checkout') {
			$custadpcde="2CH";
		} elseif ($adpaymethod == 'googlecheckout') {
			$custadpcde="GCH";
		}
	}

	$base=get_option('siteurl');

	////////////
	// Step:3 Create/Display payment page
	////////////

	$query = "SELECT adterm_name,amount,rec_period FROM ".$tbl_ad_fees." WHERE adterm_id='$adterm_id'";
	$res = awpcp_query($query, __LINE__);
	while ($rsrow=mysql_fetch_row($res)) {
		list($adterm_name,$amount,$recperiod) = $rsrow;
	}

	$original_amount = $amount;
	$amount = apply_filters('awpcp_ad_payment_amount', $amount, $adid, $adterm_id);

	$custom = "$adid";
	$custom.= "_";
	$custom.= "$key";
	$custom.= "_";
	$custom.= "$custadpcde";

	$info = array('ad_id' => $adid,
				  'ad_key' => $key,
				  'term_id' => $adterm_id,
				  'original_amount' => $original_amount,
				  'payment_method' => $custadpcde,
				  'payment_amount' => $amount);

	// needs to be called after 'awpcp_ad_payment_amount' since Coupons plugin
	// set some necessary variables when executing that filter. Other plugins
	// may (or should) do the same
	$custom = apply_filters('awpcp_write_ad_payment_information', $custom, $info);

	if ($amount <= 0) {
		$showpaybutton='';
	} else {
		$showpaybutton = "<h2>" . __("Step 3 Payment","AWPCP") . "</h2>";

		// plugins can add forms and other information at this point.
		$showpaybutton .= '<!-- placeholder -->';

		$text = __("Please click the button below to submit payment for your ad listing. You'll be asked to pay <b>%0.2f</b>.","AWPCP");
		$showpaybutton.= "<p>";
		$showpaybutton.= sprintf($text, $amount);
		$showpaybutton.= "</p>";
		////////////
		// Print the paypal button option if paypal is activated
		////////////
		if ($adpaymethod == 'paypal') {
			$awpcppaypalpaybutton=awpcp_displaypaymentbutton_paypal($adid,$custom,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base);

			$showpaybutton.="$awpcppaypalpaybutton";

		} // End if ad payment is paypal

		/////////////
		// Print the  2Checkout button option if 2Checkout is activated
		/////////////

		elseif ($adpaymethod == '2checkout') {
			$awpcptwocheckoutpaybutton=awpcp_displaypaymentbutton_twocheckout($adid,$custom,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base);
			$showpaybutton.="$awpcptwocheckoutpaybutton";
		} // End if ad payment is 2checkout

		//////////////////
		// Print the  Google Checkout button option if module exists and GC is activated
		//////////////////
		elseif ($adpaymethod == 'googlecheckout') {
			global $hasgooglecheckoutmodule;
			if ($hasgooglecheckoutmodule == 1) {
				$awpcpgooglecheckoutpaybutton=awpcp_displaypaymentbutton_googlecheckout($adid,$custom,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base);
				$showpaybutton.="$awpcpgooglecheckoutpaybutton";
			}
		}
	} // End if the fee amount is not a zero value

	// Show page based on if amount is zero or payment needs to be made
	if ( $amount <= 0 ) {
		$finishbutton="<p>";
		$finishbutton.=__("Please click the finish button to complete the process of submitting your listing","AWPCP");
		$finishbutton.="</p>
		<form method=\"post\" id=\"awpcpui_process\">
		<input type=\"hidden\" name=\"a\" value=\"adpostfinish\" />
		<input type=\"hidden\" name=\"adid\" value=\"$adid\" />
		<input type=\"hidden\" name=\"adkey\" value=\"$key\" />
		<input type=\"hidden\" name=\"adtermid\" value=\"$adterm_id\" />
		<input type=\"Submit\" value=\"";
		$finishbutton.=__("Finish","AWPCP");
		$finishbutton.="\"/></form>";
		$displaypaymentform="$finishbutton";
	} else {
		$displaypaymentform="$showpaybutton";
	}

	//debug($_POST);

	// filter in case admins want to track ad submission traffic to another database or seperate tracking application
	$displaypaymentform = apply_filters('awpcp_paybutton', $displaypaymentform, $amount, $adid);

	////////////
	// Display the content
	////////////

	$adpostform_content = $displaypaymentform;
	$output .= "$adpostform_content";
	return $output;
}




/**
 * Deletes an image
 *
 * @param $picid int The id of the image to delete.
 * @param $adid int The id of the Ad the image belongs to.
 * @param $force boolean True if image should be deleted even if curent
 * 						 user is not admin.
 */
function deletepic($picid,$adid,$adtermid,$adkey,$editemail,$force=false)
{
	$output = '';
	$isadmin=checkifisadmin() || $force;
	$savedemail=get_adposteremail($adid);
	// TODO: this won't work for email address like jhon-doe@gmail.com
	$editemail = str_replace('-', '@', $editemail);

	// XXX: an user with the same email as the user who posted the Ad
	// can delete an image. This is how Ads and Users are associated.
	if ((strcasecmp($editemail, $savedemail) == 0) || ($isadmin == 1 ))
	{
		global $wpdb;
		$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";

		$output .= "<div id=\"classiwrapper\">";

		$images = AWPCP_Image::find(array('id' => (int) $picid, 'ad_id' => (int) $adid));
		if (!empty($images)) {;
			if ($images[0]->delete() && $isadmin == 1 && ($force || is_admin())) {
				$message = __("The image has been deleted","AWPCP");
				return $message;
			}
		}

		$output .= editimages($adtermid,$adid,$adkey,$editemail);

	} else {
		$output .= __("Unable to delete you image, please contact the administrator.","AWPCP");
	}

	$output .= "</div>";
	return $output;
}



//	START FUNCTION: delete ad by specified ad ID


function deletead($adid, $adkey, $editemail, $force=false, &$errors=array()) {
	$output = '';
	$awpcppage = get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	$quers = setup_url_structure($awpcppagename);

	$isadmin = checkifisadmin() || $force;

	if (get_awpcp_option('onlyadmincanplaceads') && ($isadmin != 1)) {
		$message = __("You do not have permission to perform the function you are trying to perform. Access to this page has been denied","AWPCP");
		$errors[] = $message;

	} else {
		global $wpdb, $nameofsite;

		$tbl_ads = $wpdb->prefix . "awpcp_ads";
		$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";
		$savedemail=get_adposteremail($adid);

		if ((strcasecmp($editemail, $savedemail) == 0) || ($isadmin == 1 )) {
			$ad = AWPCP_Ad::find_by_id($adid);
			$ad->delete();

			if (($isadmin == 1) && is_admin()) {
				$message=__("The Ad has been deleted","AWPCP");
				return $message;
			} else {
				$message=__("Your Ad details and any photos you have uploaded have been deleted from the system","AWPCP");
				$errors[] = $message;
			}
		} else {
			$message=__("Problem encountered. Cannot complete  request","AWPCP");
			$errors[] = $message;
		}
	}

	$output .= "<div id=\"classiwrapper\">";
	$output .= awpcp_menu_items();
	$output .= "<p>";
	$output .= $message;
	$output .= "</p>";
	$output .= "</div>";

	return $output;
}

// TODO: restore all email messages

//	START FUNCTION: Send out notifications that listing has been successfully posted

/**
 * Email the administrator and the user to notify that the payment process was aborted.
 */
// function ad_paystatus_change_email($ad_id,$transactionid,$key,$message,$gateway) {
// 	global $nameofsite, $thisadminemail;

// 	$home_url = home_url();

// 	$adminemailoverride=get_awpcp_option('awpcpadminemail');
// 	if (isset($adminemailoverride) && !empty($adminemailoverride) && !(strcasecmp($thisadminemail, $adminemailoverride) == 0))
// 	{
// 		$thisadminemail=$adminemailoverride;
// 	}
// 	$awpcppage=get_currentpagename();
// 	$awpcppagename = sanitize_title($awpcppage, $post_ID='');
// 	$permastruc=get_option(permalink_structure);
// 	$quers=setup_url_structure($awpcppagename);
// 	if (!isset($message) || empty($message)){ $message='';}

// 	// $modtitle=cleanstring($listingtitle);
// 	// $modtitle=add_dashes($modtitle);

// 	$url_showad=url_showad($ad_id);
// 	$adlink="$url_showad";

// 	$adposteremail=get_adposteremail($ad_id);
// 	$admostername=get_adpostername($ad_id);
// 	$listingtitle=get_adtitle($ad_id);
// 	$awpcpabortemailsubjectuser=get_awpcp_option('paymentabortedsubjectline');

// 	$subjectadmin=__("Listing payment status change notification","AWPCP");
// 	$awpcpabortemailbodyadditionadets=__("Additional Details","AWPCP");
// 	$awpcpabortemailbodytransid.=__("Transaction ID","AWPCP");



// 	$mailbodyadmindearadmin=__("Dear Administrator","AWPCP");
// 	$mailbodyadminproblemencountered.=__("A listing in the system has been updated with a payment status change","AWPCP");

// 	$mailbodyadmin="
// 	$mailbodyadmindearadmin

// 	$mailbodyadminproblemencountered

// 	$awpcpabortemailbodyadditionadets
// ";

// 	$mailbodyadmin.="
// ";
// 	$mailbodyadmin.=$message;
// 	$mailbodyadmin.="
// ";
// 	$mailbodyadmin.=__("Listing Title","AWPCP");
// 	$mailbodyadmin.=": $listingtitle";
// 	$mailbodyadmin.="
// ";
// 	$mailbodyadmin.=__("Listing ID","AWPCP");
// 	$mailbodyadmin.="$ad_id";
// 	$mailbodyadmin.="
// ";
// 	$mailbodyadmin.=__("Listing URL","AWPCP");
// 	$mailbodyadmin.=": $adlink";
// 	$mailbodyadmin.="
// ";
// 	if (isset($transactionid) && !empty($transactionid))
// 	{
// 		$mailbodyadmin.=__("Payment transaction ID","AWPCP");
// 		$mailbodyadmin.=": $transactionid";
// 		$mailbodyadmin.="
// ";
// 	}
// 	$mailbodyadmin.="
// ";
// 	$mailbodyadmin.="
// 	$nameofsite
// 	$home_url
// ";

// 	// email admin
// 	@awpcp_process_mail($awpcpsenderemail=$thisadminemail,$awpcpreceiveremail=$thisadminemail,$awpcpemailsubject=$subjectadmin, $awpcpemailbody=$mailbodyadmin, $awpcpsendername=$nameofsite,$awpcpreplytoemail=$thisadminemail);

// 	do_action('awpcp_edit_ad');
// 	return $message;

// }


// TODO: update other ad_success_email calls
function ad_success_email($ad_id, $message, $notify_admin = true) {
	global $nameofsite, $thisadminemail;

	$adminemailoverride = get_awpcp_option('awpcpadminemail');
	if (!empty($adminemailoverride) && !(strcasecmp($thisadminemail, $adminemailoverride) == 0)) {
		$thisadminemail = $adminemailoverride;
	}

	$ad = AWPCP_Ad::find_by_id($ad_id);

	if (is_null($ad)) {
		return __('An un expected error occurred while trying to send a notification email about your Ad being posted. Please contact an Administrator if your Ad is not being properly listed.', 'AWPCP');
	}

	$adposteremail = $ad->ad_contact_email;
	$adpostername = $ad->ad_contact_name;
	$listingtitle = $ad->ad_title;
	$transaction_id = $ad->ad_transaction_id;
	$key = $ad->ad_key;

	$url_showad = url_showad($ad_id);
	$adlink = $url_showad;

	$listingaddedsubject = get_awpcp_option('listingaddedsubject');
	$mailbodyuser = get_awpcp_option('listingaddedbody');
	$subjectadmin = __("New classified ad listing posted","AWPCP");
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT lineas, marco,fondo, texto 
                        FROM wp_awpcp_ads WHERE ad_id = %s LIMIT 1", $ad_id ) );
	// emails are sent in plain text, blank lines in templates are required
	ob_start();
		include(AWPCP_DIR . 'frontend/templates/email-place-ad-success-user.tpl.php');
		$user_email_body = ob_get_contents();
	ob_end_clean();
                $user_email_body .= "
                    
                    Lineas:".$row->lineas."
                    Fondo:".$row->fondo."
                    Marco:".$row->marco."
                    Texto:
";
                $texto = explode("\n\n",$row->texto);
                foreach($texto as $c=>$v){
                    $user_email_body .= "
                        $v";
                
                }
                
//                echo $user_email_body ;
//                die();
	ob_start();
		include(AWPCP_DIR . 'frontend/templates/email-place-ad-success-admin.tpl.php');
		$admin_email_body = ob_get_contents();
	ob_end_clean();


	//email the buyer
	$send_success_notification = get_awpcp_option('send-user-ad-posted-notification', true);
	if ($send_success_notification) {
		$messagetouser = __("Your ad has been submitted and an email has been sent to the email address you provided with information you will need to edit your listing.","AWPCP");
		$awpcpdosuccessemail = awpcp_process_mail($awpcpsenderemail=$thisadminemail,
								$awpcpreceiveremail=$adposteremail, $awpcpemailsubject=$listingaddedsubject,
								$awpcpemailbody=$user_email_body, $awpcpsendername=$nameofsite,
								$awpcpreplytoemail=$thisadminemail);
	} else {
		$messagetouser = __("Your ad has been submitted.","AWPCP");
		$awpcpdosuccessemail = true;
	}

	if (get_awpcp_option('adapprove') == 1 && $ad->disabled) {
		$awaitingapprovalmsg = get_awpcp_option('notice_awaiting_approval_ad');
		$messagetouser .= "<br/><br/>$awaitingapprovalmsg";
	}

	//email the administrator if the admin has this option set
	if (get_awpcp_option( 'notifyofadposted' ) && $notify_admin) {
		awpcp_process_mail($awpcpsenderemail=$thisadminemail,$awpcpreceiveremail=$thisadminemail,
			$awpcpemailsubject=$subjectadmin, $awpcpemailbody=$admin_email_body,
			$awpcpsendername=$nameofsite,$awpcpreplytoemail=$thisadminemail);
	}

	if ($awpcpdosuccessemail) {
		$printmessagetouser = "$messagetouser";
	} else {
		$printmessagetouser = __("Although your ad has been submitted, there was a problem encountered while attempting to email your ad details to the email address you provided.","AWPCP");
	}

	return $printmessagetouser;
}

//	START FUNCTION: display listing of ad titles when browse ads is clicked

/**
 * TODO: create a render function to render all pages, and allow modules or
 * other parts of the plugin to insert custom content. Remove $before_content
 * when this is done.
 *
 * @param  [type] $where          [description]
 * @param  [type] $byl            [description]
 * @param  [type] $hidepager      [description]
 * @param  [type] $grouporderby   [description]
 * @param  [type] $adorcat        [description]
 * @param  string $before_content [description]
 * @return [type]                 [description]
 */
function awpcp_display_ads($where, $byl, $hidepager, $grouporderby, $adorcat, $before_content='') {
	global $wpdb;
	global $awpcp_imagesurl, $awpcp_plugin_path;
	global $hasregionsmodule, $hasextrafieldsmodule;

	$output = '';
	$awpcppage=get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage);
	$quers=setup_url_structure($awpcppagename);
	$permastruc=get_option('permalink_structure');

	$showadspagename=sanitize_title(get_awpcp_option('show-ads-page-name'));
	$browseadspagename = sanitize_title(get_awpcp_option('browse-ads-page-name'));
	$browsecatspagename=sanitize_title(get_awpcp_option('browse-categories-page-name'));

	$awpcp_browsecats_pageid=awpcp_get_page_id_by_ref('browse-categories-page-name');
	$awpcpwppostpageid=awpcp_get_page_id_by_ref('main-page-name');
	$browseadspageid=awpcp_get_page_id_by_ref('browse-ads-page-name');

	$displayadthumbwidth = get_awpcp_option('displayadthumbwidth');

	$url_browsecats='';
	__("*** NOTE:  The next two strings are for currency formatting:  1,000.00 where comma is used for currency place holders and the period for decimal separation.  Change the next two strings for your preferred price formatting.  (this string is just a note)***","AWPCP");
	$currencySep = __(",", "AWPCP");
	$decimalPlace = __(".","AWPCP");

	// filters to provide alternative method of storing custom layouts (e.g. can be outside of this plugin's directory)
	if ( has_action('awpcp_browse_ads_template_action') || has_filter('awpcp_browse_ads_template_filter') ) {
		do_action('awpcp_browse_ads_template_action');
		$output = apply_filters('awpcp_browse_ads_template_filter');
		return;

	} else if (file_exists("$awpcp_plugin_path/awpcp_display_ads_my_layout.php") &&
			   get_awpcp_option('activatemylayoutdisplayads'))
	{
		include("$awpcp_plugin_path/awpcp_display_ads_my_layout.php");

	} else {
		$output .= "<div id=\"classiwrapper\">";

		$isadmin = checkifisadmin();
		$uiwelcome=stripslashes_deep(get_awpcp_option('uiwelcome'));

		$output .= "<div class=\"uiwelcome\">$uiwelcome</div>";
		$output .= awpcp_menu_items();

		if ($hasregionsmodule ==  1) {
			if (isset($_SESSION['theactiveregionid'])) {
				$theactiveregionid=$_SESSION['theactiveregionid'];
				$theactiveregionname = addslashes(get_theawpcpregionname($theactiveregionid));
			}

			// Do not show Region Control form when showing Search Ads page
			// search result. Changing the current location will redirect the user
			// to the form instead of a filterd version of the form and that's confusing
			global $post;
			// this is a poor test to see if we are in Search Ads page
			if ($post->post_name == sanitize_title(get_awpcp_option('search-ads-page-name')) &&
				isset($_POST['a']) && $_POST['a'] == 'dosearch') {
				// do nothing
			} else {
				$output .= awpcp_region_control_selector();
			}
		}

		$output .= $before_content;

		$tbl_ads = $wpdb->prefix . "awpcp_ads";
		$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";

		$from="$tbl_ads";

		if (!isset($where) || empty($where)) {
			$where="disabled =0";
		} else {
			$where="$where";
		}

		// this overrides Search Ads region form fields
		if ($hasregionsmodule == 1 && isset($theactiveregionname) && !empty($theactiveregionname)) {
	        if (function_exists('awpcp_regions_api')) {
	        	$regions = awpcp_regions_api();
	        	$where.= ' AND ' . $regions->sql_where($theactiveregionid);
	        } else {
				$where.=" AND (ad_city ='$theactiveregionname' OR ad_state='$theactiveregionname' OR ad_country='$theactiveregionname' OR ad_county_village='$theactiveregionname')";
			}
		}

		// disablependingads is shown to the user with the label:
		// "Enable pending Ads that are pending payment"
		// if the value is 1 we should allow pending payment Ads
		// if value is 0 (unchecked) we shouldn't allow pending payment Ads
		// TODO: change the name of this setting to something that makes sense
		if (get_awpcp_option('disablependingads') == 0 &&  get_awpcp_option('freepay') == 1) {
			$where .= " AND (payment_status != 'Pending' AND payment_status != 'Unpaid') ";
		}/* else {
			// never allow Unpaid Ads
			$where .= " AND payment_status != 'Unpaid' ";
		}*/

		$ads_exist = ads_exist();
		if (!$ads_exist) {
			$showcategories="<p style=\"padding:10px\">";
			$showcategories.=__("There are currently no ads in the system","AWPCP");
			$showcategories.="</p>";
			$pager1='';
			$pager2='';

		} else {
			$awpcp_image_display_list=array();

			if ($adorcat == 'cat') {
				$tpname = get_permalink($awpcp_browsecats_pageid);
			} else {
				$tpname = get_permalink($browseadspageid);
			}

			$awpcpmyresults=get_awpcp_option('adresultsperpage');
			if (!isset($awpcpmyresults) || empty($awpcpmyresults)) {
				$awpcpmyresults=10;
			}

			$offset=(isset($_REQUEST['offset'])) ? (clean_field($_REQUEST['offset'])) : ($offset=0);
			$results=(isset($_REQUEST['results']) && !empty($_REQUEST['results'])) ? clean_field($_REQUEST['results']) : ($results=$awpcpmyresults);

			if (!isset($hidepager) || empty($hidepager) ) {
				//Unset the page and action here...these do the wrong thing on display ad
				unset($_GET['page_id']);
				unset($_POST['page_id']);
				//unset($params['page_id']);
				$pager1=create_pager($from,$where,$offset,$results,$tpname);
				$pager2=create_pager($from,$where,$offset,$results,$tpname);
			} else {
				$pager1='';
				$pager2='';
			}

			if (isset($grouporderby) && !empty($grouporderby)) {
			    if (function_exists('awpcp_featured_ads')) {
					$grouporderby = str_replace('ORDER BY','', strtoupper($grouporderby));
					$grouporder = 'ORDER BY is_featured_ad DESC, '.$grouporderby;
			    } else {
					$grouporder = $grouporderby;
			    }

			} else {
			    if (function_exists('awpcp_featured_ads')) {
					$grouporder = "ORDER BY is_featured_ad DESC, ad_postdate DESC, ad_title ASC";
			    } else {
					$grouporder="ORDER BY ad_postdate DESC, ad_title ASC";
			    }
			}

			$query = "SELECT ad_id, ad_category_id, ad_title, ad_contact_name, ";
			$query.= "ad_contact_phone, ad_city, ad_state, ad_country, ";
			$query.= "ad_details, ad_postdate, ad_enddate, ad_views, ad_fee_paid, ";
			$query.= "IF(ad_fee_paid > 0, 1, 0) as ad_is_paid, ad_item_price, flagged ";
			$query.= "FROM $from WHERE $where $grouporder LIMIT $offset,$results";

			$items=array();
			$res = awpcp_query($query, __LINE__);

			while ($rsrow=mysql_fetch_row($res)) {
				// Change:  Allow flagged ads to show
				// if ($rsrow[15]) continue;

				if (is_array($rsrow)) {
					for ($i=0; $i < count($rsrow); $i++) {
						$rsrow[$i] = stripslashes($rsrow[$i]);
					}
				}

				$ad_id=$rsrow[0];
				$awpcppage=get_currentpagename();
				$awpcppagename = sanitize_title($awpcppage, $post_ID='');

				$modtitle = awpcp_esc_attr($rsrow[2]);
				$modcontactname = stripslashes($rsrow[3]);
				$tcname = get_adcatname($rsrow[1]);
				// $modcatname=cleanstring($tcname);
				// $modcatname=add_dashes($modcatname);
				$category_id=$rsrow[1];
				$category_name=get_adcatname($category_id);
				$addetailssummary = stripslashes_deep(wp_trim_words($rsrow[8], 20, ''));
				$awpcpadcity=get_adcityvalue($ad_id);
				$awpcpadstate=get_adstatevalue($ad_id);
				$awpcpadcountry=get_adcountryvalue($ad_id);
				$awpcpadcountyvillage=get_adcountyvillagevalue($ad_id);

				$url_showad=url_showad($ad_id);
				if (isset($permastruc) && !empty($permastruc)) {
					// $url_browsecats = "$quers/$browsecatspagename/$category_id/";
					$base_url = trim(get_permalink($awpcp_browsecats_pageid), '/');
					$url_browsecats = sprintf("%s/%s/", $base_url, $category_id);
				} else {
					// $url_browsecats = "$quers/?page_id=$awpcp_browsecats_pageid&amp;a=browsecat&amp;category_id=$category_id";
					$base_url = trim(get_permalink($awpcp_browsecats_pageid), '/');
					$params = array('a' => 'browsecat', 'category_id' => $category_id);
					$url_browsecats = add_query_arg($params, $base_url);
				}

				$ad_title="<a href=\"$url_showad\">".$rsrow[2]."</a>";
				$categorylink="<a href=\"$url_browsecats\">$category_name</a><br/>";

				$awpcpcity=$rsrow[5];
				$awpcpstate=$rsrow[6];
				$awpcpcountry=$rsrow[7];

				if (isset($awpcpcity) && !empty($awpcpcity)) {
					$awpcp_city_display = "$awpcpcity<br/>";
				} else {
					$awpcp_city_display = "";
				}

				if (isset($awpcpstate) && !empty($awpcpstate)) {
					$awpcp_state_display = "$awpcpstate<br/>";
				} else {
					$awpcp_state_display = "";
				}

				if ( isset($awpcpcountry) && !empty($awpcpcountry) ) {
					$awpcp_country_display="$awpcpcountry<br/>";
				} else {
					$awpcp_country_display='';
				}

				$awpcp_image_display="<a href=\"$url_showad\">";
				if (get_awpcp_option('imagesallowdisallow')) {
					$totalimagesuploaded=get_total_imagesuploaded($ad_id);
					if ($totalimagesuploaded >=1) {
						$image = awpcp_get_ad_primary_image($ad_id);
						if (!is_null($image)) {
							$awpcp_image_name_srccode="<img src=\"". awpcp_get_image_url($image, 'thumbnail') . "\" border=\"0\" style=\"float:left;margin-right:25px;\" width=\"$displayadthumbwidth\" alt=\"$modtitle\"/>";
						} else {
							$awpcp_image_name_srccode="<img src=\"$awpcp_imagesurl/adhasnoimage.gif\" style=\"float:left;margin-right:25px;\" width=\"$displayadthumbwidth\" border=\"0\" alt=\"$modtitle\"/>";
						}
					} else {
						$awpcp_image_name_srccode="<img src=\"$awpcp_imagesurl/adhasnoimage.gif\" width=\"$displayadthumbwidth\" border=\"0\" alt=\"$modtitle\"/>";
					}
				} else {
					$awpcp_image_name_srccode="<img src=\"$awpcp_imagesurl/adhasnoimage.gif\" width=\"$displayadthumbwidth\" border=\"0\" alt=\"$modtitle\"/>";
				}

				$awpcp_image_display.="$awpcp_image_name_srccode</a>";

				if ( get_awpcp_option('displayadviews') ) {
					$awpcp_display_adviews=__("Total views","AWPCP");
					$awpcp_display_adviews.=": $rsrow[11]<br/>";
				} else {
					$awpcp_display_adviews='';
				}

				if (get_awpcp_option('displaypricefield')) {
					if (isset($rsrow[14]) && !empty($rsrow[14])) {
						$awpcptheprice=$rsrow[14];
						$itempricereconverted=($awpcptheprice/100);
						$itempricereconverted=number_format($itempricereconverted, 2, $decimalPlace, $currencySep);
						if ($itempricereconverted >=1 ) {
							$awpcpthecurrencysymbol=awpcp_get_currency_code();
							$awpcp_display_price=__("Price","AWPCP");
							$awpcp_display_price.=": $awpcpthecurrencysymbol $itempricereconverted<br/>";
						} else {
							$awpcp_display_price='';
						}
					} else {
						$awpcp_display_price='';
					}
				} else {
					$awpcp_display_price='';
				}

				$awpcpextrafields='';
				if ($hasextrafieldsmodule == 1) {
					$awpcpextrafields=display_x_fields_data($ad_id, false);
				}

				$awpcpdateformat=__("m/d/Y","AWPCP");
				$awpcpadpostdate=date($awpcpdateformat, strtotime($rsrow[9]))."<br/>";

				$imgblockwidth="$displayadthumbwidth";
				$imgblockwidth.="px";

				$ad_title=stripslashes_deep($ad_title);
				$addetailssummary=stripslashes_deep($addetailssummary);
				$awpcpdisplaylayoutcode=get_awpcp_option('displayadlayoutcode');

				if ( isset($awpcpdisplaylayoutcode) && !empty($awpcpdisplaylayoutcode)) {
					//$awpcpdisplaylayoutcode=str_replace("\$awpcpdisplayaditems","${awpcpdisplayaditems}",$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$imgblockwidth",$imgblockwidth,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcp_image_name_srccode",$awpcp_image_display,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$addetailssummary",$addetailssummary,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$ad_title",$ad_title,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcpadpostdate",$awpcpadpostdate,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcp_state_display",$awpcp_state_display,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcp_display_adviews",$awpcp_display_adviews,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcp_city_display",$awpcp_city_display,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcp_display_price",$awpcp_display_price,$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$awpcpextrafields","$awpcpextrafields",$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$ad_categoryname","$tcname",$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$ad_contactname","$modcontactname",$awpcpdisplaylayoutcode);
					$awpcpdisplaylayoutcode=str_replace("\$url_showad","$url_showad",$awpcpdisplaylayoutcode);

					if (function_exists('awpcp_featured_ads')) {
					    $awpcpdisplaylayoutcode = awpcp_featured_ad_class($ad_id, $awpcpdisplaylayoutcode);
					}

					$items[]="$awpcpdisplaylayoutcode";

				} else {
					$items[]="
							<div class=\"\$awpcpdisplayaditems awpcp_featured_ad_wrapper\">
							<div style=\"width:$imgblockwidth;padding:5px;float:left;margin-right:20px;\">$awpcp_image_name_srccode</div>
							<div style=\"width:50%;padding:5px;float:left;\"><h4>$ad_title </h4> " . $addetailssummary . "...</div>
							<div style=\"padding:5px;float:left;\"> $awpcpadpostdate $awpcp_city_display $awpcp_state_display $awpcp_display_adviews $awpcp_display_price $awpcpextrafields</div>
							<span class=\"fixfloat\">$tweetbtn $sharebtn $flagad</span>
							</div>
							<div class=\"fixfloat\"></div>
							";
				}

				$opentable="";
				$closetable="";

				$theitems=smart_table($items,intval($results/$results),$opentable,$closetable);
				$showcategories="$theitems";
			}

			if (!isset($ad_id) || empty($ad_id) || $ad_id == 0) {
				$showcategories="<p style=\"padding:20px;\">";
				$showcategories.=__("There were no ads found","AWPCP");
				$showcategories.="</p>";
				$pager1='';
				$pager2='';
			}
		}

		if (isset($_REQUEST['category_id']) && !empty($_REQUEST['category_id'])) {
			$show_category_id=$_REQUEST['category_id'];
		} else {
			$show_category_id='';
		}

		if (!isset($url_browsecatselect) || empty($url_browsecatselect)) {
			$url_browsecatselect = get_permalink($awpcp_browsecats_pageid);
		}

		if ($ads_exist) {
			$output .= "<div class=\"fixfloat\"></div><div class=\"pager\">$pager1</div>";
			$output .= "<div class=\"changecategoryselect\"><form method=\"post\" action=\"$url_browsecatselect\"><select style='float:left' name=\"category_id\"><option value=\"-1\">";
			$output .= __("Select Category","AWPCP");
			$output .= "</option>";
			$allcategories=get_categorynameidall($show_category_id='');
			$output .= "$allcategories";
			$output .= "</select><input type=\"hidden\" name=\"a\" value=\"browsecat\" />&nbsp;<input class=\"button\" type=\"submit\" value=\"";
			$output .= __("Change Category","AWPCP");
			$output .= "\" /></form></div><div id='awpcpcatname' class=\"fixfloat\">";

			$category_id = (int) awpcp_request_param('category_id', -1);
			$category_id = $category_id === -1 ? (int) get_query_var('cid') : $category_id;
			if ($category_id > 0) {
				$output .= "<h3>" . __("Category: ", "AWPCP") . get_adcatname($category_id) . "</h3>";
			}

			$output .= "</div>";
		}

		$output .= apply_filters('awpcp-display-ads-before-list', '');
		$output .= "$showcategories";

		if ($ads_exist) {
			$output .= "&nbsp;<div class=\"pager\">$pager2</div>";
		}

		if ($byl) {
			if (field_exists($field='removepoweredbysign') && !(get_awpcp_option('removepoweredbysign'))) {
				$output .= "<p><font style=\"font-size:smaller\">";
				$output .= __("Powered by ","AWPCP");
				$output .= "<a href=\"http://www.awpcp.com\">Another Wordpress Classifieds Plugin</a> </font></p>";

			} elseif (field_exists($field='removepoweredbysign') && (get_awpcp_option('removepoweredbysign'))) {
				// ...

			} else {
				// $output .= "<p><font style=\"font-size:smaller\">";
				// $output .= __("Powered by ","AWPCP");
				// $output .= "<a href=\"http://www.awpcp.com\">Another Wordpress Classifieds Plugin</a> </font></p>";
			}
		}

		$output .= "</div>";

	}
	return $output;
}
//	END FUNCTION



/**
 * Handles AWPCPSHOWAD shortcode.
 *
 * @param $adid An Ad ID.
 * @param $omitmenu
 * @param $preview true if the function is used to show an ad just after
 *				   it was posted to the website.
 * @param $send_email if true and $preview=true, a success email will be send
 * 					  to the admin and poster user.
 *
 * @return Show Ad page content.
 */
function showad($adid, $omitmenu, $preview=false, $send_email=true) {
	global $wpdb, $awpcp_plugin_path, $hasextrafieldsmodule;

	$preview = $preview === true || 'preview' == awpcp_array_data('adstatus', '', $_GET);

	$seoFriendlyUrls = get_awpcp_option('seofriendlyurls');
	$permastruc = get_option('permalink_structure');

	if (!isset($adid) || empty($adid)) {
		//_log("Ad ID not set, looking in URL");
		if (isset($_REQUEST['adid']) && !empty($_REQUEST['adid'])) {
			$adid = intval($_REQUEST['adid']);
			//_log("Found ad id in request");
		} elseif (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
			$adid = intval($_REQUEST['id']);
			//_log("Found ad id (as just id) in request");
		} else {
			//_log("SEO friendly urls detected, looking for ad id in url");
			if (isset($permastruc) && !empty($permastruc)) {
				$adid = get_query_var('id');
			}
		}
	}

	$output = '';

	$tbl_ads = $wpdb->prefix . "awpcp_ads";
	$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";

	$awpcppage=get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage);
	$quers=setup_url_structure($awpcppagename);

	$replytoadpagename=sanitize_title(get_awpcp_option('reply-to-ad-page-name'));
	$replytoadpageid=awpcp_get_page_id_by_ref('reply-to-ad-page-name');

	$showadspagename=sanitize_title(get_awpcp_option('show-ads-page-name'));
	// delete:
	//$pathvalueshowad=get_awpcp_option('pathvalueshowad');
	$currencySep = _x(",", 'Used for currency placeholders: (The comman in 1,000.00).', "AWPCP");
	$decimalPlace = __(".", 'Used for decimal separation: (The dot in 1,000.00).', "AWPCP");

	//_log("Displaying ad: " . $adid);
	if (isset($adid) && !empty($adid)) {
		// filters to provide alternative method of storing custom layouts (e.g. can be outside of this plugin's directory)
		if ( has_action('awpcp_single_ad_template_action') || has_filter('awpcp_single_ad_template_filter') ) {
			do_action('awpcp_single_ad_template_action');
			$output = apply_filters('awpcp_single_ad_template_filter');
			$output = $output;
			return;
		}
		else if ( file_exists("$awpcp_plugin_path/awpcp_showad_my_layout.php") && get_awpcp_option('activatemylayoutshowad') )
		{
			include("$awpcp_plugin_path/awpcp_showad_my_layout.php");
		}
		else
		{
			$output .= "<div id=\"classiwrapper\">";

			$isadmin=checkifisadmin();

			if (!$omitmenu) {
				$output .= awpcp_menu_items();
			}

			if (isset($awpcpadpostedmsg) && !empty($awpcpadpostedmsg))
			{
				$output .= "$awpcpadpostedmsg";
			}

			//update the ad views
			$query="UPDATE ".$tbl_ads." SET ad_views=(ad_views + 1) WHERE ad_id='$adid'";
			$res = awpcp_query($query, __LINE__);
			$showadsense='';
			if (get_awpcp_option('useadsense') == 1) {
				$adsensecode=get_awpcp_option('adsense');
				$showadsense="<div class=\"cl-adsense\">$adsensecode</div>";
			}



			//Only display ads that aren't disabled, unless you're the admin:
			$display_disabled = " and disabled=0";
			if ($isadmin) {
				$display_disabled = '';
			}

			// Preview mode - for after someone posts an ad and moderation is turned on
			if ( $preview ) {
				$display_disabled = '';
			}

			$query="SELECT ad_title,ad_contact_name,ad_contact_phone,ad_city,ad_state,ad_country,ad_county_village,ad_item_price,ad_details,websiteurl,ad_postdate,ad_startdate, disabled from ".$tbl_ads." WHERE ad_id='$adid' $display_disabled";

			$res = awpcp_query($query, __LINE__);
			if( mysql_num_rows($res) == 0 )
			{
				$output .= __("Sorry, that ad is no longer valid.  Try browsing ads or searching for one instead.", "AWPCP");
				$output .= "</div><!--close classiwrapper-->";
				return $output;
			}
			while ($rsrow=mysql_fetch_row($res))
			{
				if ( is_array($rsrow) ) for($i=0; $i < count($rsrow); $i++) $rsrow[$i] = stripslashes($rsrow[$i]);
				list($ad_title,$adcontact_name,$adcontact_phone,$adcontact_city,$adcontact_state,$adcontact_country,$ad_county_village,$ad_item_price,$addetails,$websiteurl,$ad_postdate,$ad_startdate,$disabled)=$rsrow;
			}

			// Preview mode - for after someone posts an ad and moderation is turned on
			if ( !$isadmin && 1 == $disabled && !$preview )
			{
				$output .= __("Sorry, that ad is no longer valid.  Try browsing ads or searching for one instead.", "AWPCP");
				$output .= "</div><!--close classiwrapper-->";
				return $output;
			}

			// Step:2 Show a sample of how the ad is going to look
			$ad_title=stripslashes_deep($ad_title);
			$addetails=stripslashes_deep($addetails);
			$adcontact_city=stripslashes_deep($adcontact_city);
			$ad_county_village=stripslashes_deep($ad_county_village);
			$adcontact_state=stripslashes_deep($adcontact_state);

			if (!isset($adcontact_name) || empty($adcontact_name)){$adcontact_name="";}
			if (!isset($adcontact_phone) || empty($adcontact_phone))
			{
				$adcontactphone="";
			}
			else
			{
				$adcontactphone="<br/>";
				$adcontactphone.=__("Phone","AWPCP");
				$adcontactphone.=": $adcontact_phone";
			}

			if ( empty($adcontact_city) && empty($adcontact_state) && empty($adcontact_country) && empty($ad_county_village))
			{
				$location="";
			}
			else
			{
				$location="<br/>";
				$location.=__("Location ","AWPCP");
				$location.=": ";
				$first = true;
				if ( isset($adcontact_city) && !empty($adcontact_city) )
				{
					//Add city
					$location.=$adcontact_city;
					$first = false;
				}
				if ( isset($ad_county_village) && !empty($ad_county_village) )
				{
					//Add county/village
					if (!$first) {
						$location.=", ";
					}
					$location.=$ad_county_village;
					$first = false;
				}
				if ( isset($adcontact_state) && !empty($adcontact_state) )
				{
					//Add state
					if (!$first) {
						$location.=", ";
					}
					$location.=$adcontact_state;
					$first = false;
				}
				if ( isset($adcontact_country) && !empty($adcontact_country) )
				{
					//Add country
					if (!$first) {
						$location.=", ";
					}
					$location.=$adcontact_country;
					$first = false;
				}
			}

			$modtitle = sanitize_title($ad_title);

			$base_url = trailingslashit(get_permalink($replytoadpageid));
			if ( $seoFriendlyUrls ) {
				if (isset($permastruc) && !empty($permastruc)) {
					$codecontact = sprintf("%s%s/%s", $base_url, $adid, $modtitle);
				} else {
					$codecontact = add_query_arg(array('i' => $adid), $base_url);
				}
			} else {
				$codecontact = add_query_arg(array('i' => $adid), $base_url);
			}
			$codecontact = user_trailingslashit($codecontact);

			$aditemprice='';

			if ( get_awpcp_option('displaypricefield') == 1)
			{
				if ( !empty($ad_item_price) )
				{
					$itempricereconverted=($ad_item_price/100);
					$itempricereconverted=number_format($itempricereconverted, 2, $decimalPlace, $currencySep);
					if ($itempricereconverted >=1 )
					{
						$awpcpthecurrencysymbol=awpcp_get_currency_code();
						$aditemprice="<div class=\"showawpcpadpage\"><label>";
						$aditemprice.=__("Price","AWPCP");
						$aditemprice.="</label><br/>";
						$aditemprice.="<b class=\"price\">$awpcpthecurrencysymbol $itempricereconverted</b></div>";
					}
				}
			}

			$awpcpadviews='';
			if ( get_awpcp_option('displayadviews') )
			{
				$awpcpadviews_total=get_numtimesadviewd($adid);
				$awpcpadviews="<div class=\"adviewed\">";
				$awpcpadviews.=__("This ad has been viewed ","AWPCP");
				$awpcpadviews.="$awpcpadviews_total";
				$awpcpadviews.=__(" times","AWPCP");
				$awpcpadviews.="</div>";
			}
			if (get_awpcp_option('visitwebsitelinknofollow'))
			{
				$awpcprelnofollow="rel=\"nofollow\" ";
			}
			else
			{
				$awpcprelnofollow="";
			}
			$awpcpvisitwebsite='';
			if (isset($websiteurl) && !empty($websiteurl))
			{
				$awpcpvisitwebsite="<br/><a $awpcprelnofollow href=\"$websiteurl\" target='_blank'>";
				$awpcpvisitwebsite.=__("Visit Website","AWPCP");
				$awpcpvisitwebsite.="</a>";
			}

			$featureimg='';
			$allowImages = get_awpcp_option('imagesallowdisallow');

			if ($allowImages == 1) {
				$totalimagesuploaded = get_total_imagesuploaded($adid);

				$image = awpcp_get_ad_primary_image($adid);
				if ($image) {
					$large_image = awpcp_get_image_url($image, 'large');
					$thumbnail = awpcp_get_image_url($image, 'primary');

					if (get_awpcp_option('show-click-to-enlarge-link', 1)) {
						$link = sprintf('<a class="thickbox enlarge" href="%s">%s</a>', $large_image, __('Click to Enlarge Image', 'AWPCP'));
					}
					$featureimg = "<div class=\"awpcp-ad-primary-image\" style=\"float:right;\"><a class=\"thickbox thumbnail\" href=\"$large_image\"><img class=\"thumbshow\" src=\"$thumbnail\"/></a>$link</div>";
				}

				$theimage='';
				$awpcpshowadotherimages='';

				if ($totalimagesuploaded >= 1) {
					$query = "SELECT image_name FROM " . AWPCP_TABLE_ADPHOTOS . " ";
					$query.= "WHERE ad_id='$adid' AND disabled=0 AND key_id !='{$image->key_id}' ";
					$query.= "ORDER BY image_name ASC";

					$res = awpcp_query($query, __LINE__);

					$count = mysql_num_rows($res);
					$columns = get_awpcp_option('display-thumbnails-in-columns', 0);
					$rows = $columns > 0 ? ceil($count / $columns) : 0;
					$shown = 0;

					while ($rsrow=mysql_fetch_row($res)) {
						$image_name = $rsrow[0];
						$large_image = awpcp_get_image_url($image_name, 'large');
						$thumbnail = awpcp_get_image_url($image_name, 'thumbnail');

						if ($columns > 0) {
							$css = join(' ', awpcp_get_grid_item_css_class(array(), $shown, $columns, $rows));
						} else {
							$css = '';
						}

						$awpcpshowadotherimages .= "<li class=\"$css\"><a class=\"thickbox\" href=\"$large_image\"><img class=\"thumbshow\" src=\"$thumbnail\"/></a></li>";

						$shown++;
					}
				}
			} else {
				$awpcpshowadotherimages = '';
			}
			$adsensePosition = get_awpcp_option('adsenseposition');
			$showadsense1="";
			$showadsense2="";
			$showadsense3="";
			if ($adsensePosition == 1)
			{
				$showadsense1="$showadsense";
			}
			else if ($adsensePosition == 2)
			{
				$showadsense2="$showadsense";
			}
			else if ($adsensePosition == 3)
			{
				$showadsense3="$showadsense";
			}

			$awpcpextrafields = '';
			if ($hasextrafieldsmodule == 1) {
				$awpcpextrafields = display_x_fields_data($adid);
			}

			// allow plugins and modules to modify the Ad details to, for example,
			// add oEmbed support.
			$addetails = apply_filters('awpcp-ad-details', $addetails);

			if (get_awpcp_option('hyperlinkurlsinadtext')) {
				$replacement = '<a ' . $awpcprelnofollow . 'href="$1">$1</a>';
				$addetails = preg_replace('#(?<!")(http://[^\s]+)(?!")#', $replacement, $addetails);
			}

			$flagad = '<a id="flag_ad_link" href="#" onclick="return flag_ad('.$adid.')">'.__('Flag ad', 'AWPCP').'</a>';

			$flag_script = "
				<script>
				 function flag_ad(a) {
					var flagit = confirm('".__('Are you sure you want to flag this ad?','AWPCP')."');
					if ( flagit == true) {
					    do_flag_ad(a);
					    jQuery('#flag_ad_link').hide();
					}
					return false;
				 }
				 function do_flag_ad(a) {
				    var url = '".AWPCP_URL."flag_ad.php';
				    var n = '".wp_create_nonce('flag_ad')."';
				    jQuery.get( url, {'a': a, 'n': n }, function(data) {
					if ( 1 == data ) {
						alert('".__('This ad has been flagged','AWPCP')."');
					} else {
						alert('".__('An error occurred while trying to flag the ad','AWPCP')."');
					}
				    });
				    return false;
				}
				</script>";

			$flag_script = awpcp_inline_javascript_placeholder('flag-script', $flag_script);

			$addetails = nl2br($addetails);
			$layout = get_awpcp_option('awpcpshowtheadlayout');

			if (empty($layout)) {
				$layout = '
					<div id="showawpcpadpage">
						<div class="awpcp-title">$ad_title</div><br/>
						<div class="showawpcpadpage">
							$featureimg
							<label>' . __("Contact Information","AWPCP"). '</label><br/>
							<a href="$codecontact">' . __("Contact","AWPCP") . '$adcontact_name</a>
							$adcontactphone
							$location
							$awpcpvisitwebsite
						</div>
						$aditemprice
						$awpcpextrafields
						<div class="fixfloat"></div>
						$showadsense1
						<div class="showawpcpadpage">
							<label>' . __("More Information", "AWPCP"). '</label><br/>
							$addetails
						</div>
						$showadsense2
						<div class="fixfloat"></div>
						<div id="displayimagethumbswrapper">
							<div id="displayimagethumbs">
								<ul>
									$awpcpshowadotherimages
								</ul>
							</div>
						</div>
						<span class="fixfloat">$tweetbtn $sharebtn $flagad</span>
						$awpcpadviews
						$showadsense3
					</div>';
			}

			$layout = apply_filters('awpcp-pre-single-ad-layout', $layout, $adid, $ad_title);

			$ad_cat_id = get_adcategory($adid);
			$ad_categoryname = get_adcatname($ad_cat_id);
			$ad_categoryurl = url_browsecategory($ad_cat_id);

			$awpcpdateformat=_x("m/d/Y", "*** NOTE the next string is the date format used for the field codes displayed in the ad.  Change to your local format.", "AWPCP");

			$layout = str_replace("\$ad_startdate","".date($awpcpdateformat, strtotime($ad_startdate)),$layout);
			$layout = str_replace("\$ad_postdate","".date($awpcpdateformat, strtotime($ad_postdate)),$layout);
			$layout = str_replace("\$ad_categoryurl","$ad_categoryurl",$layout);
			$layout = str_replace("\$ad_categoryname","$ad_categoryname",$layout);
			$layout = str_replace("\$ad_title","$ad_title",$layout);
			$layout = str_replace("\$featureimg","$featureimg",$layout);
			$layout = str_replace("\$quers/","",$layout);
			$layout = str_replace("\$codecontact","$codecontact",$layout);
			$layout = str_replace("\$adcontact_name","$adcontact_name",$layout);
			$layout = str_replace("\$adcontactphone","$adcontactphone",$layout);
			$layout = str_replace("\$city","$adcontact_city",$layout);
			$layout = str_replace("\$state","$adcontact_state",$layout);
			$layout = str_replace("\$village","$ad_county_village",$layout);
			$layout = str_replace("\$country","$adcontact_country",$layout);
			$layout = str_replace("\$location","$location",$layout);
			$layout = str_replace("\$aditemprice","$aditemprice",$layout);
			$layout = str_replace("\$awpcpextrafields","$awpcpextrafields",$layout);
			$layout = str_replace("\$awpcpvisitwebsite","$awpcpvisitwebsite",$layout);
			$layout = str_replace("\$showadsense1","$showadsense1",$layout);
			$layout = str_replace("\$addetails","$addetails",$layout);
			$layout = str_replace("\$showadsense2","$showadsense2",$layout);
			$layout = str_replace("\$awpcpshowadotherimages","$awpcpshowadotherimages",$layout);
			$layout = str_replace("\$awpcpadviews","$awpcpadviews",$layout);
			$layout = str_replace("\$showadsense3","$showadsense3",$layout);
			$layout = str_replace("\$flagad","$flagad",$layout);

			// generic filter to add content into the body of add content (e.g. "Tweet This" button, etc)
			if (has_filter('awpcp-single-ad-layout')) {
			    $layout = apply_filters('awpcp-single-ad-layout', $layout, $adid, $ad_title);
			}

			$output = apply_filters('awpcp-show-ad', $output . $flag_script . $layout, $adid);

			$output .= "</div><!--close classiwrapper-->";
		}

	} else {
		$grouporderby=get_group_orderby();
		$output .= awpcp_display_ads($where='',$byl='',$hidepager='',$grouporderby,$adocat='');
	}
	
	return $output;
}

/**
 * Generates HTML to display login form when user is not registered.
 */
function awpcp_login_form($message=null, $redirect_to='/') {
	$action_url = get_awpcp_option('postloginformto');
	$register_url = get_awpcp_option('registrationurl');

	if (empty($action_url)) {
		$action_url = site_url('/wp-login.php');
	}
	if (empty($register_url)) {
		$register_url = site_url('/wp-login.php?action=register');
	}

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/login_form.tpl.php');
		$content = ob_get_contents();
	ob_end_clean();

	return $content;
}


function awpcp_user_payment_terms_sort($a, $b) {
	$result = strcasecmp($a->type, $b->type);
	if ($result == 0) {
		$result = strcasecmp($a->name, $b->name);
	}
	return $result;
}


/**
 * Render the users dropdown used to post an Ad on behalf of another user.
 *
 * @param $user_id 		ID of selected user. Set to false to select none
 * 						of the users in the dropdown.
 */
function awpcp_render_users_dropdown($user_id='', $payment_term='') {
	global $current_user;
	get_currentuserinfo();

	$users = awpcp_get_users();
	$json = json_encode($users);
	$payment_terms = array();

	if ($user_id !== false && empty($user_id) && $current_user) {
		$user_id = $current_user->ID;
	}

	foreach ($users as $k => $user) {
		$terms = awpcp_user_payment_terms($user->ID);
		$ids = array();
		foreach ($terms as $term) {
			$id = "{$term->type}-{$term->id}";
			if (!isset($payment_terms[$id])) {
				$payment_terms[$id] = $term;
			}
			$ids[] = $id;
		}
		$users[$k]->payment_terms = join(',', $ids);
	}

	usort($payment_terms, 'awpcp_user_payment_terms_sort');

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/page-place-ad-users-dropdown.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}