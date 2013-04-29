<?php


function awpcp_humanize_payment_term_duration($period, $increment) {
	switch ($increment) {
		case 'D':
			return _n('Day', 'Days', $period, 'AWPCP');
		case 'W':
			return _n('Week', 'Weeks', $period, 'AWPCP');
		case 'M':
			return _n('Month', 'Months', $period, 'AWPCP');
		case 'Y':
			return _n('Year', 'Years', $period, 'AWPCP');
	}
}


function awpcp_payment_urls($transaction) {
	$thank_you_id = awpcp_get_page_id_by_ref('payment-thankyou-page-name');
	$thank_you_url = get_permalink($thank_you_id);
	$cancel_id = awpcp_get_page_id_by_ref('payment-cancel-page-name');
	$cancel_url = get_permalink($cancel_id);

	$permalink_structure = get_option('permalink_structure');
	if (!empty($permalink_structure)) {
		$return_url = trailingslashit($thank_you_url) . $transaction->id;
		$notify_url = trailingslashit($thank_you_url) . $transaction->id;
		$cancel_url = trailingslashit($cancel_url) . $transaction->id;
	} else {
		$return_url = add_query_arg(array('awpcp-txn' => $transaction->id), $thank_you_url);
		$notify_url = add_query_arg(array('awpcp-txn' => $transaction->id), $thank_you_url);
		$cancel_url = add_query_arg(array('awpcp-txn' => $transaction->id), $cancel_url);
	}

	return array($return_url, $notify_url, $cancel_url);
}


function awpcp_payments_methods_form($selected=null, $heading=null) {
	$heading = !is_null($heading) ? $heading : __('Payment Methods', 'AWPCP');
	$methods = awpcp_payment_methods();

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/payment-payment-methods-options.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}


function awpcp_paypal_checkout_form($form, $transaction) {
	if ($transaction->get('payment-method') != 'paypal') {
		return $form;
	}

	global $awpcp_imagesurl;

	$is_recurring = get_awpcp_option('paypalpaymentsrecurring');
	$is_test_mode_enabled = get_awpcp_option('paylivetestmode') == 1;

	$amount = $transaction->get('amount');
	$currency = get_awpcp_option('paypalcurrencycode');
	$custom = $transaction->id;

	$item = $transaction->get_item(0); // no support for multiple items
	if (is_null($item)) {
		return __('There was an error processing your payment.', 'AWPCP');
	}

	list($return_url, $notify_url, $cancel_url) = awpcp_payment_urls($transaction);

	if (get_awpcp_option('paylivetestmode') == 1) {
		$paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
	} else {
		$paypal_url = "https://www.paypal.com/cgi-bin/webscr";
	}

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/checkout-form-paypal.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}


function awpcp_2checkout_checkout_form($form, $transaction) {
	if ($transaction->get('payment-method') != '2checkout') {
		return $form;
	}

	global $awpcp_imagesurl;

	$is_recurring = get_awpcp_option('twocheckoutpaymentsrecurring');
	$is_test_mode_enabled = get_awpcp_option('paylivetestmode') == 1;
	$x_login = get_awpcp_option('2checkout');

	$amount = $transaction->get('amount');
	$custom = $transaction->id;

	$item = $transaction->get_item(0); // no support for multiple items
	if (is_null($item)) {
		return __('There was an error processing your payment.', 'AWPCP');
	}

	list($return_url, $notify_url, $cancel_url) = awpcp_payment_urls($transaction);

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/checkout-form-2checkout.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}


/**
 * Verify data received from PayPal IPN notifications using cURL and
 * returns PayPal's response.
 *
 * Request errors, if any, are returned by reference.
 *
 * @since 2.1.1
 */
function awpcp_paypal_verify_recevied_data_with_curl($postfields='', $cainfo=true, &$errors=array()) {
	if (get_awpcp_option('paylivetestmode') == 1) {
		$paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
	} else {
		$paypal_url = "https://www.paypal.com/cgi-bin/webscr";
	}

    $ch = curl_init($paypal_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);

	if ($cainfo)
		curl_setopt($ch, CURLOPT_CAINFO, AWPCP_DIR . 'cacert.pem');

	$result = curl_exec($ch);
	if (in_array($result, array('VERIFIED', 'INVALID'))) {
		$response = $result;
	} else {
		$response = 'ERROR';
	}

	if (curl_errno($ch)) {
		$errors[] = sprintf('%d: %s', curl_errno($ch), curl_error($ch));
	}

	curl_close($ch);

	return $response;
}


/**
 * Verify data received from PayPal IPN notifications using fsockopen and
 * returns PayPal's response.
 *
 * Request errors, if any, are returned by reference.
 *
 * @since 2.1.1
 */
function awpcp_paypal_verify_received_data_with_fsockopen($content, &$errors=array()) {
    if (get_awpcp_option('paylivetestmode') == 1) {
        $host = "www.sandbox.paypal.com";
    } else {
        $host = "www.paypal.com";
    }

	$response = 'ERROR';

    // post back to PayPal system to validate
    $header = "POST /cgi-bin/webscr HTTP/1.1\r\n";
    $header.= "Host: $host\r\n";
    $header.= "Connection: close\r\n";
    $header.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $header.= "Content-Length: " . strlen($content) . "\r\n\r\n";
    $fp = fsockopen("ssl://$host", 443, $errno, $errstr, 30);

	if ($fp) {
	    fputs ($fp, $header . $content);

	    while(!feof($fp)) {
	        $line = fgets($fp, 1024);
	        if (strcasecmp($line, "VERIFIED") == 0 || strcasecmp($line, "INVALID") == 0) {
	        	$response = $line;
	        	break;
			}
	    }

	    fclose($fp);
	} else {
		$errors[] = sprintf('%d: %s', $errno, $errstr);
	}

	return $response;
}


/**
 * Verify data received from PayPal IPN notifications and returns PayPal's
 * response.
 *
 * Request errors, if any, are returned by reference.
 *
 * @since 2.0.7
 */
function awpcp_paypal_verify_received_data($data=array(), &$errors=array()) {
	$content = 'cmd=_notify-validate';
	foreach ($data as $key => $value) {
		$value = urlencode(stripslashes($value));
		$content .= "&$key=$value";
	}

	$response = 'ERROR';
	if (in_array('curl', get_loaded_extensions())) {
		// try using custom CA information -- included with the plugin
		$response = awpcp_paypal_verify_recevied_data_with_curl($content, true, $errors);

		// try using default CA information -- installed in the server
		if (strcmp($response, 'ERROR') === 0)
			$response = awpcp_paypal_verify_recevied_data_with_curl($content, false, $errors);
	}

	if (strcmp($response, 'ERROR') === 0)
		$response = awpcp_paypal_verify_received_data_with_fsockopen($content, $errors);

	return $response;
}


function awpcp_paypal_verify_transaction($verified, $transaction) {
	if ($verified || $transaction->get('payment-method') != 'paypal') {
		return $verified;
	}

	// PayPal can redirect users using a GET request and issuing
	// a POST request in the background. If the transaction was
	// already verified during the POST transaction the result
	// should be stored in the transaction's verified attribute
	if (!empty($_POST)) {
		$response = awpcp_paypal_verify_received_data($_POST);
		$verified = strcasecmp($response, 'VERIFIED') === 0;
	} else {
		$verified = $transaction->get('verified', false);
	}

	if (!$verified) {
		$variables = count($_POST);
		$url = awpcp_current_url();

		if ($variables <= 0) {
			$msg = '<p>' . __("We haven't received your payment information from PayPal yet and we are unable to verify your transaction. Please reload this page or visit <a href=\"%s\">%s</a> in 30 seconds to continue placing your Ad.", 'AWPCP') . '</p>';
			$msg = sprintf($msg, $url, $url);
		} else {
			$msg = '<p>' . __("PayPal returned the following status from your payment: %s. %d payment variables were posted.",'AWPCP') . '</p>';
			$msg = sprintf($msg, $response, count($_POST));
			$msg.= '<p>' . __("If this status is not COMPLETED or VERIFIED, then you may need to wait a bit before your payment is approved, or contact PayPal directly as to the reason the payment is having a problem.",'AWPCP').'</p>';
		}

		$msg.= '<p>' . __("If you have any further questions, please contact this site administrator.",'AWPCP').'</p>';

		$transaction->errors[] = $msg;
	} else {
		// clean up previous errors
		$transaction->errors = array();
	}

	$transaction->set('txn-id', awpcp_post_param('txn_id'));

	return $verified;
}


function awpcp_2checkout_verify_transaction($verified, $transaction) {
	if ($verified || $transaction->get('payment-method') != '2checkout') {
		return $verified;
	}

	$x_response_code = awpcp_post_param('x_response_code');
	$x_twocorec = awpcp_post_param('x_twocorec');

	$transaction->set('txn-id', awpcp_post_param('x_trans_id'));

	if ($x_response_code == 1 || $x_twocorec == 1) {
		$transaction->errors = array();
		return true;

	} else {
		$msg=__("There appears to be a problem. Please contact customer service if you are viewing this message after having made a payment via 2Checkout. If you have not tried to make a payment and you are viewing this message, it means this message has been sent in error and can be disregarded.","AWPCP");
		$transaction->errors[] = $msg;
		// TODO: fix email function
		// $output .= abort_payment_no_email($msg,$ad_id,$txn_id,$gateway);

		return false;
	}
}


// IPN Transaction Variables
// =========================
// mc_gross:9.99
// item_mpn1:
// protection_eligibility:Ineligible
// item_count_unit1:0
// item_number1:1
// payer_id:R3ASW6QJ8SQQ4
// tax:0.00
// payment_date:14:10:17 Sep 14, 2012 PDT
// item_tax_rate1:0
// payment_status:Pending
// charset:windows-1252
// item_tax_rate_double1:0.00
// mc_shipping:0.00
// mc_handling:0.00
// first_name:Test
// notify_version:3.6
// custom:cb6af2bcf28e839ef86815fa6b94aa40
// payer_status:verified
// business:seller_1322798280_biz@wvega.com
// num_cart_items:1
// mc_handling1:0.00
// payer_email:buyer_1322798237_per@wvega.com
// verify_sign:ACUe-E7Hjxmeel8FjYAtjnx-yjHAAIHUJHpSXwaTb7k46pAQD8LmuEuX
// mc_shipping1:0.00
// tax1:0.00
// item_style_number1:
// item_plu1:
// txn_id:9HW512285G673932D
// payment_type:echeck
// last_name:User
// item_isbn1:
// receiver_email:seller_1322798280_biz@wvega.com
// item_name1:30 Day Listing
// quantity1:1
// pending_reason:echeck
// receiver_id:ZGR5CTRDFHXVA
// txn_type:cart
// item_model_number1:
// mc_currency:USD
// mc_gross_1:9.99
// item_taxable1:N
// residence_country:US
// test_ipn:1
// transaction_subject:cb6af2bcf28e839ef86815fa6b94aa40
// payment_gross:9.99
// form_charset:UTF-8

function awpcp_paypal_validate_transaction($valid, $transaction) {
	if ($valid || $transaction->get('payment-method') != 'paypal') {
		return $valid;
	}

	// PayPal can redirect users using a GET request and issuing
	// a POST request in the background. If the transaction was
	// already verified during the POST transaction the result
	// should be stored in the transaction's validated attribute
	if (empty($_POST)) {
		return $transaction->get('validated', false);
	}

	$business = awpcp_post_param('business');
	$mc_gross = $mcgross = number_format(awpcp_post_param('mc_gross'), 2);
	$payment_gross = number_format(awpcp_post_param('payment_gross'), 2);
	$txn_id = awpcp_post_param('txn_id');
	$txn_type = awpcp_post_param('txn_type');
	$custom = awpcp_post_param('custom');
	$receiver_email = awpcp_post_param('receiver_email');

	// this variables are not used for verification purposes
	$item_name = awpcp_post_param('item_name');
	$item_number = awpcp_post_param('item_number');
	$quantity = awpcp_post_param('quantity');
	$mc_fee = awpcp_post_param('mc_fee');
	$tax = awpcp_post_param('tax');
	$payment_currency = awpcp_post_param('mc_currency');
	$exchange_rate = awpcp_post_param('exchange_rate');
	$payment_status = awpcp_post_param('payment_status');
	$payment_type = awpcp_post_param('payment_type');
	$payment_date = awpcp_post_param('payment_date');
	$first_name = awpcp_post_param('first_name');
	$last_name = awpcp_post_param('last_name');
	$payer_email = awpcp_post_param('payer_email');
	$address_street = awpcp_post_param('address_street');
	$address_zip = awpcp_post_param('address_zip');
	$address_city = awpcp_post_param('address_city');
	$address_state = awpcp_post_param('address_state');
	$address_country = awpcp_post_param('address_country');
	$address_country_code = awpcp_post_param('address_country_code');
	$residence_country = awpcp_post_param('residence_country');


	// handle Subscription (PayPal Subscriptions) payments

	// TODO: handle other Subscription related transaction types (out of the scope)
	if (strcasecmp($txn_type, 'subscr-cancel') === 0) {
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_SUBSCRIPTION_CANCELED);
		return true;
	}

	// handle regular payments

	$amount = number_format($transaction->get('amount'), 2);
	if ($amount != $mc_gross && $amount != $payment_gross) {
		$msg = __("The amount you have paid does not match any of our Payment Terms amounts. Please contact us to clarify the problem.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_INVALID);
		awpcp_abort_payment($msg, $transaction);
		return false;
	}

	$paypal_email = get_awpcp_option('paypalemail');
	if (strcasecmp($receiver_email, $paypal_email) !== 0 && strcasecmp($business, $paypal_email) !== 0) {
		$msg = __("There was an error processing your transaction. If funds have been deducted from your account they have not been processed to our account. You will need to contact PayPal about the matter.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_INVALID);
		awpcp_abort_payment($msg, $transaction);
		return false;
	}

	// TODO: handle this filter for Ads and Subscriptions
	$duplicated = apply_filters('awpcp-payments-is-duplicated-transaction', false, $txn_id);
	if ($duplicated) {
		$msg = __("It appears this transaction has already been processed. If you do not see your ad in the system please contact the site adminstrator for assistance.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_INVALID);
		awpcp_abort_payment($msg, $transaction);
		return false;
	}

	if (strcasecmp($payment_status, 'Completed') === 0) {
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_COMPLETED);

	} else if (strcasecmp($payment_status, 'Pending') === 0) {
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_PENDING);

	} else if (strcasecmp($payment_status, 'Refunded') === 0 ||
			   strcasecmp($payment_status, "Reversed") == 0 ||
			   strcasecmp($payment_status, "Partially-Refunded") == 0 ||
			   strcasecmp($payment_status, "Canceled_Reversal") == 0 ||
			   strcasecmp($payment_status, "Denied") == 0 ||
			   strcasecmp($payment_status, "Expired") == 0 ||
			   strcasecmp($payment_status, "Failed") == 0 ||
			   strcasecmp($payment_status, "Voided") == 0)
	{
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_FAILED);

	} else {
		$msg = __("There appears to be a problem. Please contact customer service if you are viewing this message after having made a payment. If you have not tried to make a payment and you are viewing this message, it means this message is being shown in error and can be disregarded.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_UNKNOWN);
		awpcp_abort_payment($msg, $transaction);
	}

	return empty($transaction->errors);
}


function awpcp_2checkout_validate_transaction($valid, $transaction) {
	if ($transaction->get('payment-method') != '2checkout') {
		return $valid;
	}

	$x_2checked = awpcp_post_param('x_2checked');
	$x_MD5_Hash = awpcp_post_param('x_MD5_Hash');
	$x_trans_id = awpcp_post_param('x_trans_id');
	$card_holder_name = awpcp_post_param('card_holder_name');
	$x_Country = awpcp_post_param('x_Country');
	$x_City = awpcp_post_param('x_City');
	$x_State = awpcp_post_param('x_State');
	$x_Zip = awpcp_post_param('x_Zip');
	$x_Address = awpcp_post_param('x_Address');
	$x_Email = awpcp_post_param('x_Email');
	$x_Phone = awpcp_post_param('x_Phone');
	$x_Login = awpcp_post_param('x_login');
	$demo = awpcp_post_param('demo');
	$x_response_code= awpcp_post_param('x_response_code');
	$x_response_reason_code = awpcp_post_param('x_response_reason_code');
	$x_response_reason_text = awpcp_post_param('x_response_reason_text');
	$x_item_number = awpcp_post_param('x_item_number');
	$x_custom = awpcp_post_param('x_custom');
	$x_buyer_mail = awpcp_post_param('email');
	$x_twocorec = awpcp_post_param('x_twocorec');
	$x_order_number = awpcp_post_param('order_number');
	$x_sid = awpcp_post_param('sid');
	$x_amount = number_format(awpcp_post_param('x_amount'), 2);

	$amount = number_format($transaction->get('amount'), 2);
	if ($amount !== $x_amount) {
		$msg = __("The amount you have paid does not match any of our Payment Terms amounts. Please contact us to clarify the problem.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_INVALID);
		awpcp_abort_payment($msg, $transaction);
		return false;
	}

	if (strcasecmp($x_Login, get_awpcp_option('2checkout')) !== 0) {
		$msg = __("There was an error processing your transaction. If funds have been deducted from your account they have not been processed to our account. You will need to contact PayPal about the matter.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_INVALID);
		awpcp_abort_payment($msg, $transaction);
		return false;
	}

	// TODO: handle this filter for Ads and Subscriptions
	$duplicated = apply_filters('awpcp-payments-is-duplicated-transaction', false, $txn_id);
	if ($duplicated) {
		$msg = __("It appears this transaction has already been processed. If you do not see your ad in the system please contact the site adminstrator for assistance.", "AWPCP");
		$transaction->errors[] = $msg;
		$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_INVALID);
		awpcp_abort_payment($msg, $transaction);
		return false;
	}

	$transaction->set('payment-status', AWPCP_Payment_Transaction::$PAYMENT_STATUS_COMPLETED);

	return true;
}



























// function awpcp_payment_encode_params($params) {
// 	$encoded = '';
// 	foreach($params as $name => $value) {
// 		$encoded .= "$name=" . urlencode($value) . "&";
// 	}
// 	return trim($encoded, '&');
// }


// function awpcp_payment_decode_params($encoded) {
// 	$params = array();
// 	$parts = explode('&', $encoded);
// 	foreach ($parts as $part) {
// 		$param = explode('=', $part);
// 		$params[$param[0]] = $param[1];
// 	}
// 	return $params;
// }


// /**
//  * Returns HTML code to display a PayPal Payments button.
//  *
//  * @param $payment_period Time period for recurring payments in days [1,90].
//  */
// // TODO: what to do with recurring payments?
// function awpcp_paypal_payment_button($item_id, $item_name, $amount,
// 					$payment_period='', $context='', $params=array())
// {
// 	global $awpcp_imagesurl;

// 	$is_recurring = get_awpcp_option('paypalpaymentsrecurring');
// 	$is_test_mode_enabled = get_awpcp_option('paylivetestmode') == 1;
// 	$curreny = get_awpcp_option('paypalcurrencycode');

// 	$params = array_merge($params, array('handler' => 'paypal', 'context' => $context));
// 	$custom = awpcp_payment_encode_params($params);

// 	// setup URLS
// 	list($return_url, $notify_url, $cancel_url) = awpcp_payment_urls($context, $params);

// 	if (get_awpcp_option('paylivetestmode') == 1) {
// 		$paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
// 	} else {
// 		$paypal_url = "https://www.paypal.com/cgi-bin/webscr";
// 	}

// 	ob_start();
// 		include(AWPCP_DIR . 'frontend/templates/paypal_payment_button.tpl.php');
// 		$content = ob_get_contents();
// 	ob_end_clean();

// 	return $content;
// }

// function awpcp_2checkout_payment_button($product_id, $c_prod, $c_name, $c_description, $amount, $x_item_number, $params=array()) {
// 	global $awpcp_imagesurl;

// 	$is_recurring = get_awpcp_option('twocheckoutpaymentsrecurring');
// 	$is_test_mode_enabled = get_awpcp_option('paylivetestmode') == 1;
// 	$x_login = get_awpcp_option('2checkout');

// 	$params = array_merge($params, array('handler' => '2checkout', 'context' => $context));
// 	$custom = awpcp_payment_encode_params($params);

// 	// setup URLS
// 	list($return_url, $notify_url, $cancel_url) = awpcp_payment_urls($context, $params);

// 	//debug($custom);

// 	ob_start();
// 		include(AWPCP_DIR . 'frontend/templates/2checkout_payment_button.tpl.php');
// 		$content = ob_get_contents();
// 	ob_end_clean();

// 	return $content;
// }

/**
 * TODO: make this function call awpcp_paypal_payments_button()
 * I would have done it but I don't want to risk breaking anything,
 * maybe later when we have more time. --wvega.
 */
function awpcp_displaypaymentbutton_paypal($adid,$custom,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base)
{
	global $awpcp_imagesurl;

	$showpaybuttonpaypal="";

	if ( get_awpcp_option('seofriendlyurls') )
	{
		if (isset($permastruc) && !empty($permastruc))
		{
			$codepaymentthankyou="<input type=\"hidden\" name=\"return\" value=\"$quers/$paymentthankyoupagename/$custom\" />";
			$codepaymentnotifyurl="<input type=\"hidden\" name=\"notify_url\" value=\"$quers/$paymentthankyoupagename\" />";
			$codepaymentcancel="<input type=\"hidden\" name=\"cancel_return\" value=\"$quers/$paymentcancelpagename/$custom\" />";
		}
		else
		{
			$codepaymentthankyou="<input type=\"hidden\" name=\"return\" value=\"$quers/?page_id=$paymentthankyoupageid&i=$custom\" />";
			$codepaymentnotifyurl="<input type=\"hidden\" name=\"notify_url\" value=\"$quers/?page_id=$paymentthankyoupageid\" />";
			$codepaymentcancel="<input type=\"hidden\" name=\"cancel_return\" value=\"$quers/?page_id=$paymentcancelpageid&i=$custom\" />";
		}
	}
	elseif (!( get_awpcp_option('seofriendlyurls') ) )
	{
		if (isset($permastruc) && !empty($permastruc))
		{
			$codepaymentthankyou="<input type=\"hidden\" name=\"return\" value=\"$quers/$paymentthankyoupagename/$custom\" />";
			$codepaymentnotifyurl="<input type=\"hidden\" name=\"notify_url\" value=\"$quers/$paymentthankyoupagename\" />";
			$codepaymentcancel="<input type=\"hidden\" name=\"cancel_return\" value=\"$quers/$paymentcancelpagename/$custom\" />";
		}
		else
		{
			$codepaymentthankyou="<input type=\"hidden\" name=\"return\" value=\"$quers/?page_id=$paymentthankyoupageid&i=$custom\" />";
			$codepaymentnotifyurl="<input type=\"hidden\" name=\"notify_url\" value=\"$quers/?page_id=$paymentthankyoupageid\" />";
			$codepaymentcancel="<input type=\"hidden\" name=\"cancel_return\" value=\"$quers/?page_id=$paymentcancelpageid&i=$custom\" />";
		}
	}

	if (get_awpcp_option('paylivetestmode') == 1)
	{
		$paypalurl="https://www.sandbox.paypal.com/cgi-bin/webscr";
	}
	else
	{
		$paypalurl="https://www.paypal.com/cgi-bin/webscr";
	}

	$showpaybuttonpaypal.="<form action=\"$paypalurl\" method=\"post\">";

	if (get_awpcp_option('paypalpaymentsrecurring'))
	{
		$paypalcmdvalue="<input type=\"hidden\" name=\"cmd\" value=\"_xclick-subscriptions\" />";
	}
	else
	{
		$paypalcmdvalue="<input type=\"hidden\" name=\"cmd\" value=\"_xclick\" />";
	}

	$showpaybuttonpaypal.="$paypalcmdvalue";

	if (get_awpcp_option('paylivetestmode') == 1)
	{
		$showpaybuttonpaypal.="<input type=\"hidden\" name=\"test_ipn\" value=\"1\" />";
	}

	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"business\" value=\"".get_awpcp_option('paypalemail')."\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"no_shipping\" value=\"1\" />";
	$showpaybuttonpaypal.="$codepaymentthankyou";
	$showpaybuttonpaypal.="$codepaymentcancel";
	$showpaybuttonpaypal.="$codepaymentnotifyurl";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"no_note\" value=\"1\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"quantity\" value=\"1\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"no_shipping\" value=\"1\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"rm\" value=\"2\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"item_name\" value=\"$adterm_name\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"item_number\" value=\"$adterm_id\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"amount\" value=\"$amount\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"currency_code\" value=\"".get_awpcp_option('paypalcurrencycode')."\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"custom\" value=\"$custom\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"src\" value=\"1\" />";
	$showpaybuttonpaypal.="<input type=\"hidden\" name=\"sra\" value=\"1\" />";
	if (get_awpcp_option('paypalpaymentsrecurring'))
	{
		$showpaybuttonpaypal.="<input type=\"hidden\" name=\"a3\" value=\"$amount\" />";
		$showpaybuttonpaypal.="<input type=\"hidden\" name=\"p3\" value=\"$recperiod\" />";
		$showpaybuttonpaypal.="<input type=\"hidden\" name=\"t3\" value=\"D\" />";
	}
	//$showpaybuttonpaypal.="<input class=\"button\" type=\"submit\" value=\"";
	//$showpaybuttonpaypal.=__("Pay With PayPal","AWPCP");
	//$showpaybuttonpaypal.="\" />";
	$showpaybuttonpaypal.="<input type=\"image\" src=\"$awpcp_imagesurl/paypalbuynow.gif\" border=\"0\" name=\"submit\" alt=\"";
	$showpaybuttonpaypal.=__("Make payments with PayPal - it's fast, free and secure!","AWPCP");
	$showpaybuttonpaypal.="\" />";
	$showpaybuttonpaypal.="</form>";

	return $showpaybuttonpaypal;

}

function awpcp_displaypaymentbutton_twocheckout($adid,$custom,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base)
{

	global $awpcp_imagesurl;
	$showpaybuttontwocheckout="";

	if ( get_awpcp_option('seofriendlyurls') )
	{
		if (isset($permastruc) && !empty($permastruc))
		{
			$x_receipt_link_url="$quers/$paymentthankyoupagename/$custom";
		}
		else
		{
			$x_receipt_link_url="$quers/?page_id=$paymentthankyoupageid&i=$custom";
		}
	}
	elseif (!( get_awpcp_option('seofriendlyurls') ) )
	{
		if (isset($permastruc) && !empty($permastruc))
		{
			$x_receipt_link_url="$quers/$paymentthankyoupagename/$custom";
		}
		else
		{
			$x_receipt_link_url="$quers/?page_id=$paymentthankyoupageid&i=$custom";
		}
	}

	if (get_awpcp_option('twocheckoutpaymentsrecurring'))
	{
		$x_login_sid="<input type='hidden' name=\"sid\" value=\"".get_awpcp_option('2checkout')."\" />";
	}
	else
	{
		$x_login_sid="<input type=\"hidden\" name=\"x_login\" value=\"".get_awpcp_option('2checkout')."\" />";
	}

	$showpaybuttontwocheckout.="<form action=\"https://www2.2checkout.com/2co/buyer/purchase\" method=\"post\">";
	$showpaybuttontwocheckout.="$x_login_sid";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"id_type\" value=\"1\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"fixed\" value=\"Y\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"pay_method\" value=\"CC\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"x_Receipt_Link_URL\" value=\"$x_receipt_link_url\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"x_invoice_num\" value=\"1\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"x_amount\" value=\"$amount\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"c_prod\" value=\"$adterm_id\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"c_name\" value=\"$adterm_name\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"c_description\" value=\"$adterm_name\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"c_tangible\" value=\"N\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"x_item_number\" value=\"$adterm_id\" />";
	$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"x_custom\" value=\"$custom\" />";

	if (get_awpcp_option('twocheckoutpaymentsrecurring'))
	{
		$showpaybuttontwocheckout.="<input type='hidden' name=\"quantity\" value=1 />";
		$showpaybuttontwocheckout.="<input type='hidden' name=\"product_id\" value=\"".get_2co_prodid($adterm_id)."\" />";
		$showpaybuttontwocheckout.="<input type='hidden' name=\"x_twocorec\" value=\"1\" />";
	}

	if (get_awpcp_option('paylivetestmode') == 1)
	{
		$showpaybuttontwocheckout.="<input type=\"hidden\" name=\"demo\" value=\"Y\" />";
	}
	//$showpaybuttontwocheckout.="<input name=\"submit\" class=\"button\" type=\"submit\" value=\"";
	//$showpaybuttontwocheckout.=__("Pay With 2Checkout","AWPCP");
	$showpaybuttontwocheckout.="<input type=\"image\" src=\"$awpcp_imagesurl/buybow2checkout.gif\" border=\"0\" name=\"submit\" alt=\"";
	$showpaybuttontwocheckout.=__("Pay With 2Checkout","AWPCP");
	$showpaybuttontwocheckout.="\" /></form>";

	return $showpaybuttontwocheckout;
}

//	Process PayPal Payment



// function do_paypal($payment_status, $item_name, $item_number, $receiver_email,
// 				   $quantity, $mcgross, $payment_gross, $txn_id, $custom, $txn_type)
// {

// 	$output = '';
// 	global $wpdb;
// 	$tbl_ads = $wpdb->prefix . "awpcp_ads";
// 	$tbl_ad_fees = $wpdb->prefix . "awpcp_adfees";
// 	$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";
// 	$gateway = "Paypal";
// 	$pbizid = get_awpcp_option('paypalemail');

// 	// Configure the data that will be needed for use depending on conditions met

// 	// Split the data returned in $custom

// 	$adidkey = $custom;
// 	$adkeyelements = explode("_", $adidkey);
// 	$ad_id=$adkeyelements[0];
// 	$key=$adkeyelements[1];
// 	$pproc=$adkeyelements[2];
// 	$ad_id=clean_field($ad_id);
// 	$key=clean_field($key);


// 	// Get the item ID in order to calculate length of term


// 	$adtermid=$item_number;


// 	// Set the value of field: premiumstart


// 	$ad_startdate=mktime();


// 	// Determine when ad term ends based on start time and term length

// 	//addurationfreemode
// 	$days = get_num_days_in_term($adtermid);
// 	$term_duration = awpcp_get_term_duration($adtermid);
// 	$mysql_periods = array('D' => 'DAY', 'W' => 'WEEK', 'M' => 'MONTH', 'Y' => 'YEAR');

// 	$duration = $term_duration['duration'];
// 	$increment = $mysql_periods[$term_duration['increment']];

// 	// Bypass amount email dupeid checks if this is a cancellation notification

// 	$awpcp_ipn_is_cancellation = false;
// 	$awpcp_subscr_cancel="subscr-cancel";
// 	if (strcasecmp($txn_type, $awpcp_subscr_cancel) == 0)
// 	{
// 		// this is a cancellation notification so no need to run validation check on amount transaction id etc
// 		$awpcp_ipn_is_cancellation = 1;
// 		do_action('awpcp_disable_ad');
// 	}
// 	else
// 	{

// 		// Make sure the incoming payment amount received matches at least one of the payment ids in the system

// 		$myamounts=array();

// 		$query="SELECT amount FROM ".$tbl_ad_fees."";
// 		$res = awpcp_query($query, __LINE__);

// 		while ($rsrow=mysql_fetch_row($res))
// 		{
// 			$myamounts[]=number_format($rsrow[0],2);
// 		}
// 		//
// 		// If the incoming payment amount does not match the system amounts
// 		//
// 		$amount_matches = in_array(number_format($mcgross,2),$myamounts) ||
// 						  in_array(number_format($payment_gross,2),$myamounts);
// 		$amount_matches = apply_filters('awpcp_payment_amount_matches', $amount_matches, $mcgross, 'paypal');

// 		if (!$amount_matches) {
// 			$message=__("The amount you have paid does not match any of our listing fee amounts. Please contact us to clarify the problem.","AWPCP");
// 			$awpcpshowadsample = 0;
// 			$awpcppaymentresultmessage = abort_payment($message,$ad_id,$txn_id,$gateway);

// 			do_action('awpcp_disable_ad');
// 		}
// 		// If the amount matches
// 		////////
// 		// Compare the incoming receiver email with the system receiver email
// 		/////////

// 		/////////
// 		// If the emails do not match
// 		/////////

// 		if (!(strcasecmp($receiver_email, $pbizid) == 0)) {
// 			$message=__("There was an error processing your transaction. If funds have been deducted from your account they have not been processed to our account. You will need to contact PayPal about the matter.","AWPCP");
// 			$awpcpshowadsample=0;
// 			$awpcppaymentresultmessage=abort_payment_no_email($message,$ad_id,$txn_id,$gateway);
// 		}

// 		/////////
// 		// If the emails do match
// 		/////////


// 		//////////////////////////
// 		// Check for duplicate transaction ID
// 		//////////////////////////

// 		//////////
// 		// If the transaction ID is a duplicate of an ID already in the system
// 		/////////

// 		if (isdupetransid($txn_id)) {
// 			$message=__("It appears this transaction has already been processed. If you do not see your ad in the system please contact the site adminstrator for assistance.","AWPCP");
// 			$awpcpshowadsample=0;
// 			$awpcppaymentresultmessage=abort_payment_no_email($message,$ad_id,$txn_id,$gateway);
// 		}

// 		///////////
// 		// If the transaction ID is not a duplicate proceed with processing the transaction
// 		///////////

// 	}

// 	///////////////////////////
// 	// Begin updating based on payment status
// 	///////////////////////////

// 	if (strcasecmp($payment_status, "Completed") == 0)
// 	{
// 		///////////
// 		//Set the ad start and end date and save the transaction ID (this will be changed reset upon manual admin approval if ad approval is in effect)
// 		///////////

// 		if (get_awpcp_option('adapprove') == 1)
// 		{
// 			$disabled=1;
// 		}
// 		else
// 		{
// 			$disabled=0;
// 		}

// 		if ($awpcp_ipn_is_cancellation == 1)
// 		{
// 			$query="UPDATE  ".$tbl_ads." SET payment_status='$payment_status' WHERE ad_id='$ad_id' AND ad_key='$key'";
// 		}
// 		else
// 		{
// 			$query = "UPDATE  ".$tbl_ads." SET adterm_id='".clean_field($item_number)."',";
// 			$query.= "ad_startdate=NOW(), ad_enddate=NOW()+INTERVAL $duration $increment, ";
// 			$query.= "ad_transaction_id='$txn_id', payment_status='$payment_status', ";
// 			$query.= "payment_gateway='Paypal', disabled='$disabled', ";
// 			$query.= "ad_fee_paid='".clean_field($mcgross)."', renew_email_sent=0 ";
// 			$query.= "WHERE ad_id='$ad_id' AND ad_key='$key'";
// 		}
// 		$res = awpcp_query($query, __LINE__);
// 		//Enable the images, if they were previously disabled
// 		$query="UPDATE ".$tbl_ad_photos." set disabled=0 WHERE ad_id='$ad_id'";
// 		$res2 = awpcp_query($query, __LINE__);

// 		if (isset($item_number) && !empty($item_number))
// 		{
// 			$query="UPDATE ".$tbl_ad_fees." SET buys=buys+1 WHERE adterm_id='".clean_field($item_number)."'";
// 			$res = awpcp_query($query, __LINE__);
// 		}

// 		if ($awpcp_ipn_is_cancellation == 1)
// 		{
// 			$message=__("Payment status has been changed to cancelled","AWPCP");
// 			$awpcpshowadsample=0;
// 			$awpcppaymentresultmessage=ad_paystatus_change_email($ad_id,$txn_id,$key,$message,$gateway);
// 		}
// 		else
// 		{
// 			$message=__("Payment has been completed","AWPCP");
// 			$awpcpshowadsample=1;
// 			$awpcppaymentresultmessage=ad_success_email($ad_id,$txn_id,$key,$message,$gateway);
// 		}

// 		do_action('awpcp_edit_ad');

// 	}
// 	elseif (strcasecmp($payment_status, "Refunded") == 0 ||
// 		strcasecmp($payment_status, "Reversed") == 0 ||
// 		strcasecmp($payment_status, "Partially-Refunded") == 0 ||
// 		strcasecmp($payment_status, "Canceled_Reversal") == 0 ||
// 		strcasecmp($payment_status, "Denied") == 0 ||
// 		strcasecmp($payment_status, "Expired") == 0 ||
// 		strcasecmp($payment_status, "Failed") == 0 ||
// 		strcasecmp($payment_status, "Voided") == 0 )
// 	{
// 		///////////
// 		// Disable the ad since the payment has been refunded
// 		///////////
// 		if (get_awpcp_option(freepay) == 1)
// 		{
// 			$query="UPDATE  ".$tbl_ads." SET disabled=1,payment_status='$payment_status', WHERE ad_id='$ad_id' AND ad_key='$key'";
// 			$res = awpcp_query($query, __LINE__);

// 			if (isset($item_number) && !empty($item_number))
// 			{
// 				$query="UPDATE ".$tbl_ad_fees." SET buys=buys-1 WHERE adterm_id='".clean_field($item_number)."'";
// 				$res = awpcp_query($query, __LINE__);
// 			}
// 		}

// 		$message=__("Payment status has been changed to refunded","AWPCP");
// 		$awpcpshowadsample=0;
// 		$awpcppaymentresultmessage=ad_paystatus_change_email($ad_id,$txn_id,$key,$message,$gateway);

// 		do_action('awpcp_disable_ad');

// 	}
// 	elseif (strcasecmp ($payment_status, "Pending") == 0 )
// 	{
// 		///////////
// 		//Set the ad start and end date and save the transaction ID (this will be changed reset upon manual admin approval if ad approval is in effect)
// 		///////////
// 		if (get_awpcp_option('disablependingads') == 0)
// 		{
// 			$disabled=1;
// 		}
// 		else
// 		{
// 			$disabled=0;
// 		}

// 		if ($awpcp_ipn_is_cancellation == 1)
// 		{
// 			$query="UPDATE  ".$tbl_ads." SET payment_status='$payment_status' WHERE ad_id='$ad_id' AND ad_key='$key'";
// 		}
// 		else
// 		{
// 			$query = "UPDATE  ".$tbl_ads." SET adterm_id='".clean_field($item_number)."',";
// 			$query.= "ad_startdate=NOW(), ad_enddate=NOW()+INTERVAL $duration $increment, ";
// 			$query.= "ad_transaction_id='$txn_id', payment_status='$payment_status', ";
// 			$query.= "payment_gateway='Paypal', disabled='$disabled', ";
// 			$query.= "ad_fee_paid='".clean_field($mcgross)."', renew_email_sent=0 ";
// 			$query.= "WHERE ad_id='$ad_id' AND ad_key='$key'";
// 		}
// 		$res = awpcp_query($query, __LINE__);
// 		//Dis/enable the images, if they were previously disabled
// 		$query="UPDATE ".$tbl_ad_photos." set disabled='$disabled' WHERE ad_id='$ad_id'";
// 		$res2 = awpcp_query($query, __LINE__);

// 		if (isset($item_number) && !empty($item_number))
// 		{
// 			$query="UPDATE ".$tbl_ad_fees." SET buys=buys+1 WHERE adterm_id='".clean_field($item_number)."'";
// 			$res = awpcp_query($query, __LINE__);
// 		}
// 		$message=__("Payment is pending","AWPCP");
// 		$awpcpshowadsample=1;
// 		$awpcppaymentresultmessage=ad_success_email($ad_id,$txn_id,$key,$message,$gateway);

// 		do_action('awpcp_edit_ad');
// 	}
// 	else
// 	{
// 		$message=__("There appears to be a problem. Please contact customer service if you are viewing this message after having made a payment. If you have not tried to make a payment and you are viewing this message, it means this message is being shown in error and can be disregarded.","AWPCP");
// 		$awpcpshowadsample=0;
// 		$awpcppaymentresultmessage=abort_payment($message,$ad_id,$txn_id,$gateway);

// 		do_action('awpcp_disable_ad');
// 	}

// 	$output .= "<div id=\"classiwrapper\">";
// 	$output .= '<p class="ad_status_msg">';
// 	$output .= $awpcppaymentresultmessage;
// 	$output .= "</p>";
// 	$output .= awpcp_menu_items();
// 	if ($awpcpshowadsample == 1)
// 	{
// 		$output .= '<h2 class="ad-posted">';
// 		$output .= __("You Ad is posted","AWPCP");
// 		$output .= "</h2>";
// 		$output .= showad($ad_id, $omitmenu=1);
// 	}
// 	$output .= "</div>";
// 	return $output;
// }


//	End process


function do_2checkout($custom,$x_amount,$x_item_number,$x_trans_id,$x_Login)
{
	$output = '';
	global $wpdb;
	$tbl_ads = $wpdb->prefix . "awpcp_ads";
	$tbl_ad_fees = $wpdb->prefix . "awpcp_adfees";
	$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";
	$gateway="2checkout";
	$pbizid=get_awpcp_option('2checkout');

	// Configure the data that will be needed for use depending on conditions met

	// Split the data returned in $custom
	$adidkey = $custom;
	$adkeyelements = explode("_", $adidkey);
	$ad_id=$adkeyelements[0];
	$key=$adkeyelements[1];
	$pproc=$adkeyelements[2];


	$ad_id=clean_field($ad_id);
	$key=clean_field($key);


	// Get the item ID in order to calculate length of term
	$adtermid=$x_item_number;

	// Set the value of field: premiumstart
	$ad_startdate=mktime();


	// Determine when ad term ends based on start time and term length
	$days = get_num_days_in_term($adtermid);
	$term_duration = awpcp_get_term_duration($adtermid);
	$mysql_periods = array('D' => 'DAY', 'W' => 'WEEK', 'M' => 'MONTH', 'Y' => 'YEAR');

	$duration = $term_duration['duration'];
	$increment = $mysql_periods[$term_duration['increment']];

	// Make sure the incoming payment amount received matches at least one of the payment ids in the system
	$myamounts=array();

	$query="SELECT amount FROM ".$tbl_ad_fees."";
	$res = awpcp_query($query, __LINE__);

	while ($rsrow=mysql_fetch_row($res)) {
		$myamounts[]=number_format($rsrow[0],2);
	}


	//
	// If the incoming payment amount does not match the system amounts
	//

	$amount_matches = in_array(number_format($x_amount,2),$myamounts);
	$amount_matches = apply_filters('awpcp_payment_amount_matches', $amount_matches, $x_amount, '2checkout');
	if (!$amount_matches) {
		$message=__("The amount you have paid does not match any of our listing fee amounts. Please contact us to clarify the problem","AWPCP");
		$awpcpshowadsample=0;
		$awpcppaymentresultmessage=abort_payment($message,$ad_id,$x_trans_id,$gateway);
		do_action('awpcp_edit_ad');
	}


	// If the amount matches


	////////
	// Compare the incoming receiver ID with the system receiver ID
	/////////

	/////////
	// If the vendor IDs do not match
	/////////

	if (!(strcasecmp($x_Login, $pbizid) == 0))
	{
		$message=__("There was an error process your transaction. If funds have been deducted from your account they have not been processed to our account. You will need to contact 2Checkout about the matter","AWPCP");
		$awpcpshowadsample=0;
		$awpcppaymentresultmessage=abort_payment($message,$ad_id,$x_trans_id,$gateway);
		do_action('awpcp_edit_ad');
	}

	/////////
	// If the vendor IDs do match
	/////////

	//////////////////////////
	// Check for duplicate transaction ID
	//////////////////////////

	//////////
	// If the transaction ID is a duplicate of an ID already in the system
	/////////

	if (isdupetransid($x_trans_id)) {
		$message=__("It appears this transaction has already been processed. If you do not see your ad in the system please contact the site adminstrator for assistance","AWPCP");
		$awpcpshowadsample=0;
		$awpcppaymentresultmessage=abort_payment($message,$ad_id,$x_trans_id,$gateway);
	}

	///////////
	// If the transaction ID is not a duplicate proceed with processing the transaction
	///////////


	///////////////////////////
	// Begin updating based on payment status
	///////////////////////////

	///////////
	//Set the ad start and end date and save the transaction ID (this will be changed reset upon manual admin approval if ad approval is in effect)
	///////////
	if ( (get_awpcp_option('adapprove') == 1) || (get_awpcp_option('disablependingads') == 0)) {
		$disabled=1;
	} else {
		$disabled=0;
	}

	$query = "UPDATE  ".$tbl_ads." SET adterm_id='".clean_field($x_item_number)."',";
	$query.= "ad_startdate=NOW(), ad_enddate=NOW()+INTERVAL $duration $increment, ";
	$query.= "ad_transaction_id='$x_trans_id', payment_status='Completed', ";
	$query.= "payment_gateway='2Checkout', disabled='$disabled', ";
	$query.= "ad_fee_paid='".clean_field($x_amount)."', renew_email_sent=0 ";
	$query.= "WHERE ad_id='$ad_id' AND ad_key='$key'";

	$res = awpcp_query($query, __LINE__);
	//Enable the images, if they were previously disabled
	$query="UPDATE ".$tbl_ad_photos." set disabled=0 WHERE ad_id='$ad_id'";
	$res2 = awpcp_query($query, __LINE__);

	$ad = AWPCP_Ad::find_by_id($ad_id);
	if ($disabled) {
		do_action('awpcp_disablead', $ad);
	} else {
		do_action('awpcp_approve_ad', $ad);
	}

	// let plugins know an ad was successfully posted
	// TODO: no matter what, the Ad is always posted with payment_status set
	// to Complete. This surely is a bug...
	do_action('awpcp_edit_ad');

	if (isset($item_number) && !empty($item_number))
	{
		$query="UPDATE ".$tbl_ad_fees." SET buys=buys+1 WHERE adterm_id='".clean_field($x_item_number)."'";
		$res = awpcp_query($query, __LINE__);
	}


	$message=__("Payment Status","AWPCP");
	$message.=":";
	$message.=__("Completed","AWPCP");
	$awpcpshowadsample=1;
	$awpcppaymentresultmessage=ad_success_email($ad_id,$x_trans_id,$key,$message,$gateway);

	$output .= "<div id=\"classiwrapper\">";
	$output .= '<p class="ad_status_msg">';
	$output .= $awpcppaymentresultmessage;
	$output .= "</p>";
	$output .= awpcp_menu_items();
	if ($awpcpshowadsample == 1)
	{
		$output .= "<h2>";
		$output .= __("Your Ad is posted","AWPCP");
		$output .= "</h2>";
		$output .= showad($ad_id,$omitmenu=1);
	}
	$output .= "</div>";
	return $output;
}


//	START FUNCTION: email adminstrator and ad poster if there was a problem encountered when paypal payment procedure was attempted


/**
 * email the administrator and the user to notify that the payment process was aborted
 */
function awpcp_abort_payment($message='', $transaction=null) {
	global $nameofsite, $thisadminemail;

	$adminemailoverride = get_awpcp_option('awpcpadminemail');
	if (isset($adminemailoverride) && !empty($adminemailoverride) && !(strcasecmp($thisadminemail, $adminemailoverride) == 0)) {
		$thisadminemail = $adminemailoverride;
	}

	if ($transaction) {
		$user = $transaction->get('user-id', 0);
		$user = get_userdata($user);
	} else {
		$user = null;
	}

	if (!is_null($user)) {
		$adposteremail = $user->user_email;
		$admostername = $user->display_name;

		$awpcpabortemailsubjectuser = get_awpcp_option('paymentabortedsubjectline');

		ob_start();
			include(AWPCP_DIR . 'frontend/templates/email-abort-payment-user.tpl.php');
			$awpcpabortemailbody = ob_get_contents();
		ob_end_clean();

		@awpcp_process_mail($awpcpsenderemail=$thisadminemail,
			$awpcpreceiveremail=$adposteremail, $awpcpemailsubject=$awpcpabortemailsubjectuser,
			$awpcpemailbody=$awpcpabortemailbody, $awpcpsendername=$nameofsite,
			$awpcpreplytoemail=$thisadminemail);
	}

	$subjectadmin = __("Customer attempt to pay has failed", "AWPCP");

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/email-abort-payment-admin.tpl.php');
		$mailbodyadmin = ob_get_contents();
	ob_end_clean();

	@awpcp_process_mail($awpcpsenderemail=$thisadminemail,
		$awpcpreceiveremail=$thisadminemail, $awpcpemailsubject=$subjectadmin,
		$awpcpemailbody=$mailbodyadmin, $awpcpsendername=$nameofsite,
		$awpcpreplytoemail=$thisadminemail);

	// do_action('awpcp_disable_ad');

	return $message;

}



/**
 * Old function to send emails when payment failed. Will be removed in pending code
 * cleanup.
 */
// TODO: remove any reference to this function in plugin and modules
function abort_payment($message, $ad_id, $transactionid, $gateway) {
	//email the administrator and the user to notify that the payment process was aborted

	global $nameofsite, $thisadminemail;

	$adminemailoverride = get_awpcp_option('awpcpadminemail');
	if (isset($adminemailoverride) && !empty($adminemailoverride) && !(strcasecmp($thisadminemail, $adminemailoverride) == 0)) {
		$thisadminemail = $adminemailoverride;
	}

	$awpcppage = get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	$permastruc = get_option(permalink_structure);
	$quers = setup_url_structure($awpcppagename);

	if (!isset($message) || empty($message)){
		$message='';
	}

	// $modtitle = cleanstring($listingtitle);
	// $modtitle = add_dashes($modtitle);

	$url_showad = url_showad($ad_id);
	$adlink = "$url_showad";

	$adposteremail = get_adposteremail($ad_id);
	$admostername = get_adpostername($ad_id);
	$listingtitle = get_adtitle($ad_id);
	$awpcpabortemailsubjectuser = get_awpcp_option('paymentabortedsubjectline');

	$subjectadmin = __("Customer attempt to pay for classified ad listing has failed","AWPCP");
	$awpcpabortemailbodystart = get_awpcp_option('paymentabortedmessage');
	$awpcpabortemailbodyadditionadets = __("Additional Details","AWPCP");
	$awpcpabortemailbodytransid.= __("Transaction ID","AWPCP");

	$awpcpabortemailbody.= "
	$awpcpabortemailbodystart

	$awpcpabortemailbodyadditionadets

	$message

";

	if (isset($transactionid) && !empty($transactionid)) {
		$awpcpabortemailbody.= "$awpcpabortemailbodytransid: $transactionid";
		$awpcpabortemailbody.= "

";
	}

	$awpcpabortemailbody.= "$nameofsite";
	$awpcpabortemailbody.= "
";
	$awpcpabortemailbody.= home_url();

	$mailbodyadmindearadmin = __("Dear Administrator","AWPCP");
	$mailbodyadminproblemencountered.= __("There was a problem encountered during a customer's attempt to submit payment for a classified ad listing","AWPCP");

	$mailbodyadmin = "
	$mailbodyadmindearadmin

	$mailbodyadminproblemencountered

	$awpcpabortemailbodyadditionadets
";

	$mailbodyadmin.= "
";
	$mailbodyadmin.= $message;
	$mailbodyadmin.= "
";
	$mailbodyadmin.= __("Listing Title","AWPCP");
	$mailbodyadmin.= ": $listingtitle";
	$mailbodyadmin.= "
";
	$mailbodyadmin.= __("Listing ID","AWPCP");
	$mailbodyadmin.= "$ad_id";
	$mailbodyadmin.= "
";
	$mailbodyadmin.= __("Listing URL","AWPCP");
	$mailbodyadmin.= ": $adlink";
	$mailbodyadmin.= "
";
	if (isset($transactionid) && !empty($transactionid))
	{
		$mailbodyadmin.= __("Payment transaction ID","AWPCP");
		$mailbodyadmin.= ": $transactionid";
		$mailbodyadmin.= "
	";
	}

	@awpcp_process_mail($awpcpsenderemail=$thisadminemail,$awpcpreceiveremail=$adposteremail,$awpcpemailsubject=$awpcpabortemailsubjectuser,$awpcpemailbody=$awpcpabortemailbody,$awpcpsendername=$nameofsite,$awpcpreplytoemail=$thisadminemail);

	@awpcp_process_mail($awpcpsenderemail=$thisadminemail,$awpcpreceiveremail=$thisadminemail,$awpcpemailsubject=$subjectadmin, $awpcpemailbody=$mailbodyadmin, $awpcpsendername=$nameofsite,$awpcpreplytoemail=$thisadminemail);

	// do_action('awpcp_disable_ad');

	return $message;

}


function abort_payment_no_email($message,$ad_id,$txn_id,$gateway)
{
	return $message;
}




//	START FUNCTION: If user decides not to go through with paying for ad via paypal and clicks on cancel on the paypal website
function awpcp_cancelpayment() {
	$filter = 'awpcp_cancel_payment_notification_' . $_REQUEST['context'];
	$args = array(false, $_REQUEST['handler'], $_REQUEST);
	$payment_output = apply_filters_ref_array($filter, $args);

	// Somebody else already took care of this cancelation
	if ($payment_output !== false) {
		return $payment_output;
	}

	$output = '';
	$base=get_option('siteurl');
	$permastruc=get_option(permalink_structure);
	$awpcppage=get_currentpagename();
	$awpcppagename = sanitize_title($awpcppage, $post_ID='');
	$quers=setup_url_structure($awpcppagename);

	// delete:
	// $pathvaluecancelpayment=get_awpcp_option('pathvaluecancelpayment');

	$output .= "<div id=\"classiwrapper\">";

	if (isset($_REQUEST['i']) && !empty($_REQUEST['i'])) {
		$showawpcpadpage=$_REQUEST['i'];
	}

	$adkeyelements = explode("_", $showawpcpadpage);
	$ad_id=$adkeyelements[0];
	$key=$adkeyelements[1];
	$pproc=$adkeyelements[2];


	if (!isset($ad_id) || empty($ad_id))
	{
		if (isset($permastruc) && !empty($permastruc))
		{

			/* delete:
			$awpcpcancelpayment_requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
			$awpcpcancelpayment_requested_url .= $_SERVER['HTTP_HOST'];
			$awpcpcancelpayment_requested_url .= $_SERVER['REQUEST_URI'];

			$awpcpparsedcancelpaymentURL = parse_url ($awpcpcancelpayment_requested_url);
			$awpcpsplitcancelpaymentPath = preg_split ('/\//', $awpcpparsedcancelpaymentURL['path'], 0, PREG_SPLIT_NO_EMPTY);

			$ad_id_key=$awpcpsplitcancelpaymentPath[$pathvaluecancelpayment];
			*/

			$ad_id_key = get_query_var('i');

			$adkeyelements = explode("_", $ad_id_key);
			$ad_id=$adkeyelements[0];
			$key=$adkeyelements[1];
			$pproc=$adkeyelements[2];


		}


		if (!isset($key) || empty($key))
		{
			if (isset($ad_id) && !empty($ad_id))
			{
				$key=get_adkey($ad_id);
			}
		}
	}

	$adterm_id=get_adterm_id($ad_id);
	$adterm_name=get_adterm_name($adterm_id);
	$amount=get_adfee_amount($adterm_id);
	$recperiod=get_fee_recperiod($adterm_id);
	$base=get_option('siteurl');


	$placeadpagename=sanitize_title(get_awpcp_option('place-ad-page-name'), $post_ID='');
	$placeadpageid=awpcp_get_page_id_by_ref('place-ad-page-name');

	$paymentthankyoupagename=sanitize_title(get_awpcp_option('payment-thankyou-page-name'), $post_ID='');
	$paymentthankyoupageid=awpcp_get_page_id_by_ref('payment-thankyou-page-name');

	$paymentcancelpagename=sanitize_title(get_awpcp_option('payment-cancel-page-name'), $post_ID='');
	$paymentcancelpageid=awpcp_get_page_id_by_ref('payment-cancel-page-name');


	$custom="$ad_id";
	$custom.="_";
	$custom.="$key";

	$custompp="$custom";
	$custompp.="_PP";
	$custom2ch="$custom";
	$custom2ch.="_2CH";
	$customgch="$custom";
	$customgch.="_GCH";

	$showpaybuttonpaypal=awpcp_displaypaymentbutton_paypal($ad_id,$custompp,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base);
	$showpaybutton2checkout=awpcp_displaypaymentbutton_twocheckout($ad_id,$custom2ch,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base);

	global $hasgooglecheckoutmodule;
	if ($hasgooglecheckoutmodule == 1) {
		$showpaybuttongooglecheckout=awpcp_displaypaymentbutton_googlecheckout($ad_id,$customgch,$adterm_name,$adterm_id,$key,$amount,$recperiod,$permastruc,$quers,$paymentthankyoupageid,$paymentcancelpageid,$paymentthankyoupagename,$paymentcancelpagename,$base);
	}

	$output .= __("You have chosen to cancel the payment process. Your ad cannot be activated until you pay the listing fee. You can click the link below to delete your ad information, or you can click the button to make your payment now","AWPCP");


	$savedemail = get_adposteremail($ad_id);
	$ikey="$ad_id";
	$ikey.="_";
	$ikey.="$key";
	$ikey.="_";
	$ikey.="$savedemail";

	$url_deletead = get_permalink($placeadpageid);
	$url_deletead = add_query_arg(array('a' => 'deletead', 'k' => $ikey), $url_deletead);

	$output .= "<p><a href=\"$url_deletead\">";
	$output .= __("Delete Ad Details","AWPCP");
	$output .= "</a></p>";
	if ( get_awpcp_option('activatepaypal') && (get_awpcp_option('freepay') == 1))
	{
		$output .= "<p>";
		$output .= "<h2 class=\"buywith\">";
		$output .= __("Buy With PayPal", "AWPCP");
		$output .= "</h2>";
		$output .= "$showpaybuttonpaypal</p>";
	}
	if ( get_awpcp_option('activate2checkout') && (get_awpcp_option('freepay') == 1))
	{
		$output .= "<p>";
		$output .= "<h2 class=\"buywith\">";
		$output .= __("Buy With 2Checkout", "AWPCP");
		$output .= "</h2>";
		$output .= "$showpaybutton2checkout</p></div>";
	}
	if ( get_awpcp_option('activategooglecheckout') && (get_awpcp_option('freepay') == 1) && ($hasgooglecheckoutmodule == 1))
	{
		$output .= "<p>";
		$output .= "<h2 class=\"buywith\">";
		$output .= __("Buy With Google Checkout", "AWPCP");
		$output .= "</h2>";
		$output .= "$showpaybuttongooglecheckout</p></div>";
	}

	// do_action('awpcp_disable_ad');

	return $output;
}


//	END FUNCTION



//	START FUNCTION: Thank you page to display to user after successfully completing payment via paypal

/**
 * Handles Payment Thank You page.
 *
 * On success, the users is redirected to this page. The payment gateway also send
 * notifications to this page.
 */
function paymentthankyou() {
	$output = '';

	// Was commented and set to be deleted. However this variables is needed
	// in the code below in order to extract payment information from URL.
	// Also is very unsafe to extract such info from URL, but that's all we
	// have.
	$pathvaluepaymentthankyou=get_awpcp_option('pathvaluepaymentthankyou');

	$permastruc=get_option('permalink_structure');
	if (isset($_REQUEST['i']) && !empty($_REQUEST['i']))
	{
		$showawpcpadpage=$_REQUEST['i'];
		$adkeyelements = explode("_", $showawpcpadpage);
		$ad_id=$adkeyelements[0];
		$key=$adkeyelements[1];
		$pproc=$adkeyelements[2];

	}

	//debug($_REQUEST);

	if (!isset($ad_id) || empty($ad_id))
	{
		if (isset($permastruc) && !empty($permastruc))
		{
			/* delete:
			$awpcppaymentthankyou_requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
			$awpcppaymentthankyou_requested_url .= $_SERVER['HTTP_HOST'];
			$awpcppaymentthankyou_requested_url .= $_SERVER['REQUEST_URI'];

			$awpcpparsedpaymentthankyouURL = parse_url ($awpcppaymentthankyou_requested_url);
			$awpcpsplitpaymentthankyouPath = preg_split ('/\//', $awpcpparsedpaymentthankyouURL['path'], 0, PREG_SPLIT_NO_EMPTY);
			$ad_id_key=$awpcpsplitpaymentthankyouPath[$pathvaluepaymentthankyou];

			*/

			$ad_id_key = get_query_var('i');

			$adkeyelements = explode("_", $ad_id_key);
			$ad_id=$adkeyelements[0];
			if (isset($adkeyelements[1]) && !empty($adkeyelements[1])){$awpcpadkey=$adkeyelements[1];} else {$awpcpadkey='';}
			if (isset($adkeyelements[2]) && !empty($adkeyelements[2])){$pproc=$adkeyelements[2];} else {$pproc='';}
			if (!isset($key) || empty($key)){$key=$awpcpadkey;}

		}
	}

	// identify payments handler
	if ((isset($_POST['x_response_code']) && !empty($_POST['x_response_code'])) ||
		(isset($_POST['x_twocorec']) && !empty($_POST['x_twocorec']))) {
		$awpcpayhandler="twocheckout";
	}
	if ((isset($_POST['custom']) && !empty($_POST['custom'])) &&
	    (isset($_POST['txn_type']) && !empty($_POST['txn_type'])) &&
	    (isset($_POST['txn_id']) && !empty($_POST['txn_id']))) {
		$awpcpayhandler="paypal";
	}

	// attempt to identify payments handler from return URL, only if it hasn't
	// been identified at this point.
	if (($awpcpayhandler != 'paypal') || ($awpcpayhandler != 'twocheckout')) {
		if (isset($permastruc) && !empty($permastruc)) {
			$awpcppaymentthankyou_requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
			$awpcppaymentthankyou_requested_url .= $_SERVER['HTTP_HOST'];
			$awpcppaymentthankyou_requested_url .= $_SERVER['REQUEST_URI'];

			$awpcpparsedpaymentthankyouURL = parse_url ($awpcppaymentthankyou_requested_url);
			$awpcpsplitpaymentthankyouPath = preg_split ('/\//', $awpcpparsedpaymentthankyouURL['path'], 0, PREG_SPLIT_NO_EMPTY);

			$ad_id_key=$awpcpsplitpaymentthankyouPath[$pathvaluepaymentthankyou];

			$adkeyelements = explode("_", $ad_id_key);

			$ad_id = $adkeyelements[0];
			if (isset($adkeyelements[1]) && !empty($adkeyelements[1])) {
				$awpcpadkey=$adkeyelements[1];
			} else {
				$awpcpadkey='';
			}
			if (isset($adkeyelements[2]) && !empty($adkeyelements[2])) {
				$pproc=$adkeyelements[2];
			} else {
				$pproc='';
			}
			if (!isset($key) || empty($key)){
				$key=$awpcpadkey;
			}
		}
		if (isset($pproc) && !empty($pproc) && ($pproc == 'GCH')) {
			$awpcpayhandler="googlecheckout";
		} elseif (isset($pproc) && !empty($pproc) && ($pproc == 'PP')) {
			$awpcpayhandler="paypal";
		}
		if (isset($pproc) && !empty($pproc) && ($pproc == '2CH')) {
			$awpcpayhandler="twocheckout";
		}
	}

	if ($awpcpayhandler == 'paypal') {
		//Handle PayPal
		// read the post from PayPal system and add 'cmd'
		$req = 'cmd=_notify-validate';



		foreach ($_POST as $key => $value) {
			$value = urlencode(stripslashes($value));
			if ('cmd' != $key) {
				$req .= "&$key=$value";
			}
		}

		if (get_awpcp_option('paylivetestmode') == 1) {
			$paypallink="ssl://www.sandbox.paypal.com";
			$paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
		} else {
			$paypallink="ssl://www.paypal.com";
			$paypal_url = "https://www.paypal.com/cgi-bin/webscr";
		}

		$ch = curl_init($paypal_url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		$payment_verified = strcmp($response, 'VERIFIED') === 0;

		// post back to PayPal system to validate
		/*$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
		$fp = fsockopen($paypallink, 443, $errno, $errstr, 30);

		// Handle the postback and verification
		if ($fp) {
			fputs($fp, $header . $req);
			$reply='';
			$headerdone=false;
			while(!feof($fp)) {
				$line=fgets($fp);
				if (strcmp($line,"\r\n")==0) {
					// read the header
					$headerdone=true;
				} elseif ($headerdone) {
					// header has been read. now read the contents
					$reply.=$line;
				}
			}

			fclose($fp);
			$reply=trim($reply);

			if (strcasecmp($reply,'VERIFIED')==0) {
				$payment_verified = true;
			}
		}*/

		// assign posted variables to local variables
		$item_name = awpcp_post_param('item_name');
		$item_name = awpcp_post_param('item_name');
		$item_number = awpcp_post_param('item_number');
		$receiver_email = awpcp_post_param('receiver_email');
		$quantity = awpcp_post_param('quantity');
		$business = awpcp_post_param('business');
		$mcgross = awpcp_post_param('mc_gross');
		$payment_gross = awpcp_post_param('payment_gross');
		$mc_fee = awpcp_post_param('mc_fee');
		$tax = awpcp_post_param('tax');
		$payment_currency = awpcp_post_param('mc_currency');
		$exchange_rate = awpcp_post_param('exchange_rate');
		$payment_status = awpcp_post_param('payment_status');
		$payment_type = awpcp_post_param('payment_type');
		$payment_date = awpcp_post_param('payment_date');
		$txn_id = awpcp_post_param('txn_id');
		$txn_type = awpcp_post_param('txn_type');
		$first_name = awpcp_post_param('first_name');
		$last_name = awpcp_post_param('last_name');
		$payer_email = awpcp_post_param('payer_email');
		$address_street = awpcp_post_param('address_street');
		$address_zip = awpcp_post_param('address_zip');
		$address_city = awpcp_post_param('address_city');
		$address_state = awpcp_post_param('address_state');
		$address_country = awpcp_post_param('address_country');
		$address_country_code = awpcp_post_param('address_country_code');
		$residence_country = awpcp_post_param('residence_country');
		$custom = awpcp_post_param('custom');


		// If payment verified proceed
		if ($payment_verified) {
			$payment_output = false;

			// newer paypal transactions include structured informatino in custom field
			// let's find if that's the case
			$__params = awpcp_payment_decode_params($custom);
			if (isset($__params['context']) && !empty($__params['context'])) {
				$filter = 'awpcp_payment_notification_' . $__params['context'];
				// give plugins opportunity to handle this transaction
				$payment_output = apply_filters($filter, false, 'paypal', $__params);
			}

			// if no plugin processed the transaction follow the normal
			// workflow (for posting Ads)
			if ($payment_output === false) {
				$output .= do_paypal($payment_status,$item_name,$item_number,
									 $receiver_email,$quantity,$mcgross,
									 $payment_gross,$txn_id,$custom,$txn_type);
			} else {
				$output .= $payment_output;
			}
		} else {
			$message = __("PayPal returned the following status from your payment:",'AWPCP');
			$message .= '<p>'.$response.'</p>';
			$message .= '<p>'.__("If this status is not Completed or Verified, then you may need to wait a bit before your payment is approved, or contact PayPal directly as to the reason the payment is having a problem.",'AWPCP').'</p>';
			$message .= '<p>'.__("If you have any further questions, contact this site administrator.",'AWPCP').'</p>';

			$output .= abort_payment_no_email($message,$ad_id,$txn_id,$gateway);
		}
	} elseif ($awpcpayhandler == 'twocheckout') {
		$payment_verified=false;

		$x_2checked = awpcp_post_param('x_2checked');
		$x_MD5_Hash = awpcp_post_param('x_MD5_Hash');
		$x_trans_id = awpcp_post_param('x_trans_id');
		$card_holder_name = awpcp_post_param('card_holder_name');
		$x_Country = awpcp_post_param('x_Country');
		$x_City = awpcp_post_param('x_City');
		$x_State = awpcp_post_param('x_State');
		$x_Zip = awpcp_post_param('x_Zip');
		$x_Address = awpcp_post_param('x_Address');
		$x_Email = awpcp_post_param('x_Email');
		$x_Phone = awpcp_post_param('x_Phone');
		$x_Login = awpcp_post_param('x_Phone');
		$demo = awpcp_post_param('demo');
		$x_response_code= awpcp_post_param('x_response_code');
		$x_response_reason_code = awpcp_post_param('x_response_reason_code');
		$x_response_reason_text = awpcp_post_param('x_response_reason_text');
		$x_item_number = awpcp_post_param('x_item_number');
		$x_custom = awpcp_post_param('x_custom');
		$x_buyer_mail = awpcp_post_param('email');
		$x_twocorec = awpcp_post_param('x_twocorec');
		$x_order_number = awpcp_post_param('order_number');
		$x_sid = awpcp_post_param('sid');


		if ($x_response_code == 1)
		{
			$payment_verified=true;
		}
		elseif (isset($x_twocorec) && !empty($x_twocorec) && ($x_twocorec == 1))
		{
			$payment_verified=true;
		}

		if ($payment_verified) {
			// newer 2Checkout transactions include structured information in custom field
			// let's find if that's the case
			$__params = awpcp_payment_decode_params($x_custom);
			if (isset($__params['context']) && !empty($__params['context'])) {
				$filter = 'awpcp_payment_notification_' . $__params['context'];
				// give plugins opportunity to handle this transaction
				$payment_output = apply_filters($filter, false, 'paypal', $__params);
			}

			// if no plugin processed the transaction follow the normal
			// workflow (for posting Ads)
			if ($payment_output === false) {
				$output .= do_2checkout($x_custom, $x_amount, $x_item_number, $x_trans_id, $x_Login);
			} else {
				$output .= $payment_output;
			}
		} else {
			$message=__("There appears to be a problem. Please contact customer service if you are viewing this message after having made a payment via 2Checkout. If you have not tried to make a payment and you are viewing this message, it means this message has been sent in error and can be disregarded.","AWPCP");
			$output .= abort_payment_no_email($message,$ad_id,$txn_id,$gateway);
		}

	} elseif ($awpcpayhandler == 'googlecheckout') {
		//Handle Google Checkout
		$payment_verified=true;

		if (isset($adkeyelements[3])) {
			$filter = 'awpcp_payment_notification_' . $adkeyelements[3];
			$payment_output = apply_filters($filter, false, 'google-checkout', $adkeyelements);
		}

		// if no plugin processed the transaction follow the normal
		// workflow (for posting Ads)
		if ($payment_output === false) {
			$output .= do_googlecheckout($ad_id,$key);
		} else {
			$output .= $payment_output;
		}
	} else {
		$message=__("There appears to be a problem. Please contact customer service if you are viewing this message after having made a payment. If you have not tried to make a payment and you are viewing this message, it means this message is being shown in error and can be disregarded.","AWPCP");
		$output .= abort_payment_no_email($message,$ad_id,$txn_id,$gateway);
	}
	return $output;
}