<?php

class AWPCP_Ad {

	static function from_object($object) {
		$ad = new AWPCP_Ad;

		$ad->ad_id = $object->ad_id;
		$ad->adterm_id = $object->adterm_id; // fee plan id
		$ad->ad_fee_paid = $object->ad_fee_paid;
		$ad->ad_category_id = $object->ad_category_id;
		$ad->ad_category_parent_id = $object->ad_category_parent_id;
		$ad->ad_title = $object->ad_title;
		$ad->ad_details = $object->ad_details;
		$ad->ad_contact_name = $object->ad_contact_name;
		$ad->ad_contact_phone = $object->ad_contact_phone;
		$ad->ad_contact_email = $object->ad_contact_email;
		$ad->ad_city = $object->ad_city;
		$ad->ad_state = $object->ad_state;
		$ad->ad_country = $object->ad_country;
		$ad->ad_county_village = $object->ad_county_village;
		$ad->ad_item_price = $object->ad_item_price;
		$ad->ad_views = $object->ad_views;
		$ad->ad_postdate = $object->ad_postdate;
		$ad->ad_last_updated = $object->ad_last_updated;
		$ad->ad_startdate = $object->ad_startdate;
		$ad->ad_enddate = $object->ad_enddate;
		$ad->ad_key = $object->ad_key;
		$ad->ad_transaction_id = $object->ad_transaction_id;
		$ad->user_id = $object->user_id;

		$ad->payment_gateway = $object->payment_gateway;
		$ad->payment_status = $object->payment_status;

		$ad->is_featured_ad = $object->is_featured_ad;
		$ad->flagged = $object->flagged;

		$ad->disabled = $object->disabled;
		$ad->disabled_date = $object->disabled_date;

		$ad->renewed_date = $object->renewed_date;
		$ad->renew_email_sent = $object->renew_email_sent;

		$ad->websiteurl = $object->websiteurl;
		$ad->posterip = $object->posterip;

		return $ad;
	}

	public static function find_by_category_id($id) {
		return self::find(sprintf('ad_category_id = %d', (int) $id));
	}

	public static function find_by_user_id($id) {
		return AWPCP_Ad::find_by("user_id = " . intval($id));
	}

	public static function find_by_id($id) {
		return AWPCP_Ad::find_by("ad_id = " . intval($id));
	}

	public static function find_by($where) {
		$results = AWPCP_Ad::find($where);
		if (!empty($results)) {
			return $results[0];
		}
		return null;
	}

	/**
	 * @since unknown
	 */
	public static function find($where='1 = 1', $order='id', $offset=false, $results=false) {
		global $wpdb;

		switch ($order) {
			case 'titleza':
				$order = "ad_title DESC";
				break;
			case 'titleaz':
				$order = "ad_title ASC";
				break;
			case 'awaitingapproval':
				$order = "disabled DESC, ad_key DESC";
				break;
			case 'paidfirst':
				$order = "payment_status DESC, ad_key DESC";
				break;
			case 'mostrecent':
				$order = "ad_startdate DESC";
				break;
			case 'oldest':
				$order = "ad_startdate ASC";
				break;
			case 'renewed':
				$order = 'renewed_date DESC, ad_startdate DESC';
				break;
			case 'featured':
				$order = "is_featured_ad DESC, ad_startdate DESC";
				break;
			case 'flagged':
				$order = "ad_startdate DESC";
				$where .= ' AND flagged = 1 ';
				break;
			default:
				$order = 'ad_id DESC';
				break;
		}

		$query = "SELECT * FROM " . AWPCP_TABLE_ADS . " WHERE $where ";
		$query.= "ORDER BY $order ";

		if ($offset !== false && $results !== false)
			$query.= "LIMIT $offset,$results";

		$items = $wpdb->get_results($query);
		$results = array();

		foreach($items as $item) {
			$results[] = AWPCP_Ad::from_object($item);
		}

		return $results;
	}

	public static function count($where='1=1') {
		global $wpdb;

		$query = "SELECT COUNT(*) FROM " . AWPCP_TABLE_ADS . " WHERE $where";
		$n = $wpdb->get_var($query);

		return $n !== FALSE ? $n : 0;
	}

	public static function generate_key() {
		return md5(sprintf('%s%s%d', AUTH_KEY, uniqid('', true), rand(1, 1000)));
	}

	/**
	 * Finds out if the Ad identified by $id belongs to the user
	 * whose information is stored in $user.
	 *
	 * @param $id int Ad id
	 * @param $user array See get_currentuserinfo()
	 */
	static function belongs_to_user($id, $user_id) {
		global $wpdb;

		if (empty($id) && empty($user_id)) {
			return false;
		}

		$where = $wpdb->prepare("ad_id = %d AND user_id = %d", $id, $user_id);
		$ad = AWPCP_Ad::count($where);

		return $ad > 0;
	}

	protected function sanitize($data) {
		$sanitized = $data;

		// make sure dates are dates or NULL, MySQL Strict mode does not allow empty strings
		$columns = array('ad_postdate', 'ad_last_updated', 'ad_startdate', 'ad_enddate',
						 'disabled_date', 'renewed_date');
		$regexp = '/^\d{4}-\d{1,2}-\d{1,2}(\s\d{1,2}:\d{1,2}(:\d{1,2})?)?$/';
		foreach ($columns as $column) {
			$value = trim($sanitized[$column]);
			if (preg_match($regexp, $value) !== 1) {
				// Remove this column. Not a valid date or datetime and
				// WordPress does not handle NULL values very well:
				// http://core.trac.wordpress.org/ticket/15158
				unset($sanitized[$column]);
			} else {
				$sanitized[$column] = $value;
			}
		}

		// make sure values for int/tinyint columns are int
		$columns = array('ad_id', 'adterm_id', 'ad_category_id', 'ad_category_parent_id',
						 'ad_views', 'disabled', 'is_featured_ad', 'flagged', 'renew_email_sent');
		foreach ($columns as $column) {
			$sanitized[$column] = intval(trim($sanitized[$column]));
		}

		return $sanitized;
	}

	public function save() {
		global $wpdb;

		$data = array('ad_id' => $this->ad_id,
					'adterm_id' => $this->adterm_id,
					'ad_fee_paid' => $this->ad_fee_paid,
					'ad_category_id' => $this->ad_category_id,
					'ad_category_parent_id' => $this->ad_category_parent_id,
					'ad_title' => $this->ad_title,
					'ad_details' => $this->ad_details,
					'ad_contact_name' => $this->ad_contact_name,
					'ad_contact_phone' => $this->ad_contact_phone,
					'ad_contact_email' => $this->ad_contact_email,
					'ad_city' => $this->ad_city,
					'ad_state' => $this->ad_state,
					'ad_country' => $this->ad_country,
					'ad_county_village' => $this->ad_county_village,
					'ad_item_price' => $this->ad_item_price,
					'ad_views' => $this->ad_views,
					'ad_postdate' => $this->ad_postdate,
					'ad_last_updated' => $this->ad_last_updated,
					'ad_startdate' => $this->ad_startdate,
					'ad_enddate' => $this->ad_enddate,
					'ad_key' => $this->ad_key,
					'ad_transaction_id' => $this->ad_transaction_id,
					'user_id' => $this->user_id,

					'payment_gateway' => $this->payment_gateway,
					'payment_status' => $this->payment_status,

					'is_featured_ad' => $this->is_featured_ad,
					'flagged' => $this->flagged,
					'disabled' => $this->disabled,
					'disabled_date' => $this->disabled_date,

					'renew_email_sent' => $this->renew_email_sent,
					'renewed_date' => $this->renewed_date,

					'websiteurl' => $this->websiteurl,
					'posterip' => $this->posterip);

		$data = $this->sanitize($data);

		if (empty($this->ad_id)) {
			$result = $wpdb->insert(AWPCP_TABLE_ADS, $data);
			$this->ad_id = $wpdb->insert_id;
		} else {
			$result = $wpdb->update(AWPCP_TABLE_ADS, $data, array('ad_id' => $this->ad_id));
		}

		return $result;
	}

	public function delete() {
		global $wpdb;

		do_action('awpcp_before_delete_ad', $this);

		$images = AWPCP_Image::find_by_ad_id($this->ad_id);
		foreach ($images as $image) {
			$image->delete();
		}

		$query = 'DELETE FROM ' . AWPCP_TABLE_ADS . ' WHERE ad_id = %d';
		$result = $wpdb->query($wpdb->prepare($query, $this->ad_id));

		do_action('awpcp_delete_ad', $this);

		return $result === false ? false : true;
	}

	public function disable() {
		$images = AWPCP_Image::find_by_ad_id($this->ad_id);
		foreach ($images as $image) {
			$image->disable();
		}

		$this->disabled = 1;
		$this->disabled_date = current_time('mysql');
		$this->save();

		do_action('awpcp_disable_ad', $this);

		return true;
	}

	public function enable() {
		$images = AWPCP_Image::find_by_ad_id($this->ad_id);
		foreach ($images as $image) {
			$image->enable();
		}

		$this->disabled = 0;
		$this->save();

		do_action('awpcp_approve_ad', $this);

		return true;
	}

	function get_category_name() {
		if (!isset($this->category_name))
			$this->category_name = get_adcatname($object->category_id);
		return $this->category_name;
	}

	function get_fee_plan_name() {
		return awpcp_get_fee_plan_name($this->ad_id, $this->adterm_id);
	}

	/**
	 * @since 2.0.7
	 */
	function set_start_date($start_date) {
		$this->ad_startdate = awpcp_time($start_date, 'mysql');
	}

	function get_start_date() {
		if (!empty($this->ad_startdate))
			return date('M d Y', strtotime($this->ad_startdate));
		return '';
	}

	/**
	 * @since 2.0.7
	 */
	function set_end_date($end_date) {
		$this->ad_enddate = awpcp_time($end_date, 'mysql');
	}

	function get_end_date() {
		if (!empty($this->ad_enddate))
			return date('M d Y', strtotime($this->ad_enddate));
		return '';
	}

	function get_disabled_date() {
		if (!empty($this->disabled_date))
			return date('M d Y', strtotime($this->disabled_date));
		return '';
	}

	function get_renewed_date() {
		if (!empty($this->renewed_date))
			return date('M d Y', strtotime($this->renewed_date));
		return '';
	}

	function has_expired($date=null) {
		$end_date = strtotime(date('Y-m-d', strtotime($this->ad_enddate)));
		$date = is_null($date) ? strtotime(date('Y-m-d', time())) : $date;
		return $end_date < $date;
	}

	function is_about_to_expire() {
		$threshold = get_awpcp_option('ad-renew-email-threshold');
		$date = strtotime(date('Y-m-d', strtotime(sprintf('today + %d days', $threshold))));
		return $this->has_expired($date);
	}

	/**
	 * Calculates Ad's end date based on Ad's payment term.
	 *
	 * Ad's end date will be calculated using $start_date as starting point. If
	 * no $start_date is provided, Ad's start date will be used.
	 *
	 * @param $start_date	string or timestamp
	 * @since 2.0.7
	 */
	function calculate_end_date($start_date=null) {
		if ($this->adterm_id > 0) {
			$payment_term = awpcp_payment_terms('ad-term-fee', $this->adterm_id);
		} else {
			$payment_term = null;
		}

		if (is_null($payment_term)) {
			$duration = get_awpcp_option('addurationfreemode');
			$interval = 'DAY';
		} else {
			$duration = $payment_term->period;
			$interval = $payment_term->increment;
		}

		if (is_null($start_date) || empty($start_date)) {
			$start_date = strtotime($this->ad_startdate);
		} else if (is_string($start_date)) {
			$start_date = strtotime($start_date);
		} else {
			// we asume a timestamp
		}

		$end_date = awpcp_calculate_end_date($duration, $interval, $start_date);

		return apply_filters('awpcp-ad-calculate-end-date', $end_date, $start_date, $this);
	}

	function renew($end_date=false) {
		if ($end_date === false) {
			$now = awpcp_time(null, 'timestamp');
			// if the Ad's end date is in the future, use that as starting point
			// for the new end date, else use current date.
			$start_date = $this->ad_enddate > $now ? $this->ad_enddate : $now;
			$this->set_end_date($this->calculate_end_date($start_date));
		} else {
			$this->set_end_date($end_date);
		}

		$this->renew_email_sent = false;
		$this->renewed_date = current_time('mysql');

		// if Ad is disabled lets see if we can enable it
		if ($this->disabled || ! awpcp_calculate_ad_disabled_state($this->ad_id)) {
			$this->enable();
		}

		return true;
	}

	function get_payment_status() {
		if (!empty($this->payment_status))
			return $this->payment_status;
		return 'N/A';
	}

	function get_total_images_uploaded() {
		return get_total_imagesuploaded($this->ad_id);
	}

	/**
	 * Returns the number of characters allowed for this Ad.
	 *
	 * @since 2.1.2
	 */
	function get_max_characters_allowed() {
		if (intval($this->adterm_id) > 0) {
			$fee = awpcp_get_fee($this->adterm_id);
			$chars = is_null($fee) ? $chars : $fee->characters_allowed;
		} else {
			$chars = get_awpcp_option('maxcharactersallowed');
		}
		return apply_filters('awpcp-ad-max-characters-allowed', $chars, $this);
	}

	function get_remaining_characters_count() {
		return max($this->get_max_characters_allowed() - strlen($this->ad_details), 0);
	}
}

function awpcp_get_fee_plan_name($id, $ad_term_id) {
	global $wpdb;
	if (!empty($ad_term_id)) {
		$query = 'SELECT adterm_name FROM ' . AWPCP_TABLE_ADFEES . ' ';
		$query.= 'WHERE adterm_id = ' . $ad_term_id;
		return $wpdb->get_var($query);
	} else {
		return apply_filters('awpcp-ad-payment-term-name', '', $id);
	}
}