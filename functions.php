<?php

// for PHP4 users, even though it's not technically supported:
if (!function_exists('array_walk_recursive')) {
	function array_walk_recursive(&$input, $funcname, $userdata = "") {
	    if (!is_callable($funcname)) {
	        return false;
	    }
	    if (!is_array($input)) {
	        return false;
	    }

	    foreach ($input AS $key => $value) {
	        if (is_array($input[$key])) {
	            array_walk_recursive($input[$key], $funcname, $userdata);
	        } else {
	            $saved_value = $value;
	            if (!empty($userdata)) {
	                $funcname($value, $key, $userdata);
	            } else {
	                $funcname($value, $key);
	            }
	            if ($value != $saved_value) {
	                $input[$key] = $value;
	            }
	        }
	    }
	    return true;
	}
}


if (!function_exists('wp_strip_all_tags')) {
	/**
	 * Properly strip all HTML tags including script and style
	 *
	 * @since 2.9.0
	 *
	 * @param string $string String containing HTML tags
	 * @param bool $remove_breaks optional Whether to remove left over line breaks and white space chars
	 * @return string The processed string.
	 */
	function wp_strip_all_tags($string, $remove_breaks = false) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags($string);

		if ( $remove_breaks )
			$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

		return trim($string);
	}
}


if (!function_exists('esc_textarea')) {
	/**
	 * Escaping for textarea values.
	 *
	 * @since 3.1
	 *
	 * @param string $text
	 * @return string
	 */
	function esc_textarea( $text ) {
		$safe_text = htmlspecialchars( $text, ENT_QUOTES );
		return apply_filters( 'esc_textarea', $safe_text, $text );
	}
}


if (!function_exists('wp_trim_words')) {
	/**
	 * Trims text to a certain number of words.
	 *
	 * @since 3.3.0
	 *
	 * @param string $text Text to trim.
	 * @param int $num_words Number of words. Default 55.
	 * @param string $more What to append if $text needs to be trimmed. Default '&hellip;'.
	 * @return string Trimmed text.
	 */
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		if ( null === $more )
			$more = __( '&hellip;' );
		$original_text = $text;
		$text = wp_strip_all_tags( $text );
		$words_array = preg_split( "/[\n\r\t ]+/", $text, $num_words + 1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words_array ) > $num_words ) {
			array_pop( $words_array );
			$text = implode( ' ', $words_array );
			$text = $text . $more;
		} else {
			$text = implode( ' ', $words_array );
		}
		return apply_filters( 'wp_trim_words', $text, $num_words, $more, $original_text );
	}
}


function awpcp_esc_attr($text) {
	// WP adds slashes to all request variables
	$text = stripslashes($text);
	// AWPCP adds more slashes
	$text = stripslashes($text);
	$text = esc_attr($text);
	return $text;
}

function awpcp_esc_textarea($text) {
	$text = stripslashes($text);
	$text = stripslashes($text);
	$text = esc_textarea($text);
	return $text;
}

/**
 * Returns the given date as MySQL date string, Unix timestamp or
 * using a custom format.
 *
 * @since 2.0.7
 * @param $format 	'mysql', 'timestamp', or first arguemnt for date() function.
 */
function awpcp_time($date=null, $format='mysql') {
	if (is_null($date) || empty($date)) {
		$date = current_time('timestamp');
	} else if (is_string($date)) {
		$date = strtotime($date);
	} // else, we asume a timestamp

	if ($format === 'mysql' || $format === 'timestamp')
		return $format === 'mysql' ? date('Y-m-d H:i:s', $date) : $date;
	return date($format, $date);
}


/**
 * Get a WP User. See awpcp_get_users for details.
 *
 * @param $id int 	User ID
 */
function awpcp_get_user($id) {
	$users = awpcp_get_users('WHERE ID = ' . intval($id));
	if (!empty($users)) {
		return array_shift($users);
	}
	return null;
}


/**
 * Get list of WP registered users, adding special attributes to
 * each User object, as needed by AWPCP.
 *
 * Attributes added are:
 * - username
 * - address
 * - city
 * - state
 *
 * @param $where string 	SQL Where clause to filter users
 */
function awpcp_get_users($where='') {
	global $wpdb;

	$users = $wpdb->get_results("SELECT ID, display_name FROM $wpdb->users $where");

	foreach ($users as $k => $user) {
		$data = get_userdata($user->ID);
		// extracts AWPCP profile data
		$profile = get_user_meta($user->ID, 'awpcp-profile', true);

		$users[$k] = new stdClass();
		$users[$k]->ID = $user->ID;
		$users[$k]->user_email = empty($profile['email']) ? $data->user_email : $profile['email'];
		$users[$k]->user_login = awpcp_get_property($data, 'user_login', '');
		$users[$k]->display_name = awpcp_get_property($data, 'display_name', '');
		$users[$k]->first_name = awpcp_get_property($data, 'first_name', '');
		$users[$k]->last_name = awpcp_get_property($data, 'last_name', '');
		$users[$k]->username = awpcp_array_data('username', '', $profile);
		$users[$k]->address = awpcp_array_data('address', '', $profile);
		$users[$k]->phone = awpcp_array_data('phone', '', $profile);
		$users[$k]->city = awpcp_array_data('city', '', $profile);
		$users[$k]->state = awpcp_array_data('state', '', $profile);
		$users[$k]->user_url = awpcp_get_property($data, 'user_url', '');
	}

	return $users;
}


/**
 * Returns a WP capability required to be considered an AWPCP admin.
 *
 * http://codex.wordpress.org/Roles_and_Capabilities#Capability_vs._Role_Table
 *
 * @since 2.0.7
 */
function awpcp_admin_capability() {
	$roles = explode(',', get_awpcp_option('awpcpadminaccesslevel'));
	if (in_array('editor', $roles))
		return 'edit_pages';
	// default to: only WP administrator users are AWPCP admins
	return 'install_plugins';
}


/**
 * Check if current user is an Administrator according to
 * AWPCP settings.
 */
function awpcp_current_user_is_admin() {
	$capability = awpcp_admin_capability();
	return current_user_can($capability);
}


function awpcp_user_is_admin($id) {
	$capability = awpcp_admin_capability();
	return user_can($id, $capability);
}


function awpcp_get_grid_item_css_class($classes, $pos, $columns, $rows) {
	if ($pos < $columns)
		$classes[] = 'first-row';
	if ($pos >= (($rows - 1) * $columns))
		$classes[] = 'last-row';
	if ($pos == 0 || $pos % $columns == 0)
		$classes[] = 'first-column';
	if (($pos + 1) % $columns == 0)
		$classes[] = 'last-column';
	return $classes;
}


function awpcp_get_categories() {
	global $wpdb;

	$sql = 'SELECT * FROM ' . AWPCP_TABLE_CATEGORIES;
	$results = $wpdb->get_results($sql);

	return $results;
}


/**
 * Returns an array of Region fields. Only those enabled
 * in the settings will be returned.
 *
 * @param $translations array 	Allow developers to change the name
 * 								attribute of the form field associated
 *								to this Region Field.
 */
function awpcp_region_fields($translations) {
	$fields = array();

	if (get_awpcp_option('displaycountryfield')) {
		$fields['country'] = array(
			'class' => 'country-field',
			'name' => $translations['country'],
			'label' => __('Country', 'AWPCP'),
			'help' => __('separate countries by commas', 'AWPCP'));
	}
	if (get_awpcp_option('displaystatefield')) {
		$fields['state'] = array(
			'class' => 'state-field',
			'name' => $translations['state'],
			'label' => __('State/Province', 'AWPCP'),
			'help' => __('separate states by commas', 'AWPCP'));
	}
	if (get_awpcp_option('displaycityfield')) {
		$fields['city'] = array(
			'class' => 'city-field',
			'name' => $translations['city'],
			'label' => __('City', 'AWPCP'),
			'help' => __('separate cities by commas', 'AWPCP'));
	}
	if (get_awpcp_option('displaycountyvillagefield')) {
		$fields['county'] = array(
			'class' => 'county-field',
			'name' => $translations['county'],
			'label' => __('County/Village/Other', 'AWPCP'),
			'help' => __('separate counties by commas', 'AWPCP'));
	}

	return $fields;
}


/**
 * Generates HTML for Region fields. Only those enabled
 * in the settings will be returned.
 *
 * @param $query array 			Default or selected values for form fields.
 * @param $translations array 	Allow developers to change the name
 * 								attribute of the form field associated
 *								to this Region Field.
 */
function awpcp_region_form_fields($query, $translations) {
	if (is_null($translations)) {
		$translations = array('country', 'state', 'city', 'county');
		$translations = array_combine($translations, $translations);
	}

	$fields = array();
	foreach (awpcp_region_fields($translations) as $key => $field) {
		$fields[$key] = array_merge($field, array('value' => awpcp_array_data($key, '', $query),
												  'entries' => array(),
												  'options' => ''));
	}

	$ordered = array('country', 'state', 'city', 'county');

	ob_start();
		include(AWPCP_DIR . 'frontend/templates/region-control-form-fields.tpl.php');
		$html = ob_get_contents();
	ob_end_clean();

	return $html;
}


function awpcp_country_list_options() {
	return '<option value="">-- Choose a Country --</option>
	<option value="Afganistan">Afghanistan</option>
	<option value="Albania">Albania</option>
	<option value="Algeria">Algeria</option>
	<option value="American Samoa">American Samoa</option>
	<option value="Andorra">Andorra</option>
	<option value="Angola">Angola</option>
	<option value="Anguilla">Anguilla</option>
	<option value="Antigua &amp; Barbuda">Antigua &amp; Barbuda</option>
	<option value="Argentina">Argentina</option>
	<option value="Armenia">Armenia</option>
	<option value="Aruba">Aruba</option>
	<option value="Australia">Australia</option>
	<option value="Austria">Austria</option>
	<option value="Azerbaijan">Azerbaijan</option>
	<option value="Bahamas">Bahamas</option>
	<option value="Bahrain">Bahrain</option>
	<option value="Bangladesh">Bangladesh</option>
	<option value="Barbados">Barbados</option>
	<option value="Belarus">Belarus</option>
	<option value="Belgium">Belgium</option>
	<option value="Belize">Belize</option>
	<option value="Benin">Benin</option>
	<option value="Bermuda">Bermuda</option>
	<option value="Bhutan">Bhutan</option>
	<option value="Bolivia">Bolivia</option>
	<option value="Bonaire">Bonaire</option>
	<option value="Bosnia &amp; Herzegovina">Bosnia &amp; Herzegovina</option>
	<option value="Botswana">Botswana</option>
	<option value="Brazil">Brazil</option>
	<option value="British Indian Ocean Ter">British Indian Ocean Ter</option>
	<option value="Brunei">Brunei</option>
	<option value="Bulgaria">Bulgaria</option>
	<option value="Burkina Faso">Burkina Faso</option>
	<option value="Burundi">Burundi</option>
	<option value="Cambodia">Cambodia</option>
	<option value="Cameroon">Cameroon</option>
	<option value="Canada">Canada</option>
	<option value="Canary Islands">Canary Islands</option>
	<option value="Cape Verde">Cape Verde</option>
	<option value="Cayman Islands">Cayman Islands</option>
	<option value="Central African Republic">Central African Republic</option>
	<option value="Chad">Chad</option>
	<option value="Channel Islands">Channel Islands</option>
	<option value="Chile">Chile</option>
	<option value="China">China</option>
	<option value="Christmas Island">Christmas Island</option>
	<option value="Cocos Island">Cocos Island</option>
	<option value="Colombia">Colombia</option>
	<option value="Comoros">Comoros</option>
	<option value="Congo">Congo</option>
	<option value="Cook Islands">Cook Islands</option>
	<option value="Costa Rica">Costa Rica</option>
	<option value="Cote D\'Ivoire">Cote D\'Ivoire</option>
	<option value="Croatia">Croatia</option>
	<option value="Cuba">Cuba</option>
	<option value="Curaco">Curacao</option>
	<option value="Cyprus">Cyprus</option>
	<option value="Czech Republic">Czech Republic</option>
	<option value="Denmark">Denmark</option>
	<option value="Djibouti">Djibouti</option>
	<option value="Dominica">Dominica</option>
	<option value="Dominican Republic">Dominican Republic</option>
	<option value="East Timor">East Timor</option>
	<option value="Ecuador">Ecuador</option>
	<option value="Egypt">Egypt</option>
	<option value="El Salvador">El Salvador</option>
	<option value="Equatorial Guinea">Equatorial Guinea</option>
	<option value="Eritrea">Eritrea</option>
	<option value="Estonia">Estonia</option>
	<option value="Ethiopia">Ethiopia</option>
	<option value="Falkland Islands">Falkland Islands</option>
	<option value="Faroe Islands">Faroe Islands</option>
	<option value="Fiji">Fiji</option>
	<option value="Finland">Finland</option>
	<option value="France">France</option>
	<option value="French Guiana">French Guiana</option>
	<option value="French Polynesia">French Polynesia</option>
	<option value="French Southern Ter">French Southern Ter</option>
	<option value="Gabon">Gabon</option>
	<option value="Gambia">Gambia</option>
	<option value="Georgia">Georgia</option>
	<option value="Germany">Germany</option>
	<option value="Ghana">Ghana</option>
	<option value="Gibraltar">Gibraltar</option>
	<option value="Great Britain">Great Britain</option>
	<option value="Greece">Greece</option>
	<option value="Greenland">Greenland</option>
	<option value="Grenada">Grenada</option>
	<option value="Guadeloupe">Guadeloupe</option>
	<option value="Guam">Guam</option>
	<option value="Guatemala">Guatemala</option>
	<option value="Guinea">Guinea</option>
	<option value="Guyana">Guyana</option>
	<option value="Haiti">Haiti</option>
	<option value="Hawaii">Hawaii</option>
	<option value="Honduras">Honduras</option>
	<option value="Hong Kong">Hong Kong</option>
	<option value="Hungary">Hungary</option>
	<option value="Iceland">Iceland</option>
	<option value="India">India</option>
	<option value="Indonesia">Indonesia</option>
	<option value="Iran">Iran</option>
	<option value="Iraq">Iraq</option>
	<option value="Ireland">Ireland</option>
	<option value="Isle of Man">Isle of Man</option>
	<option value="Israel">Israel</option>
	<option value="Italy">Italy</option>
	<option value="Jamaica">Jamaica</option>
	<option value="Japan">Japan</option>
	<option value="Jordan">Jordan</option>
	<option value="Kazakhstan">Kazakhstan</option>
	<option value="Kenya">Kenya</option>
	<option value="Kiribati">Kiribati</option>
	<option value="Korea North">Korea North</option>
	<option value="Korea Sout">Korea South</option>
	<option value="Kuwait">Kuwait</option>
	<option value="Kyrgyzstan">Kyrgyzstan</option>
	<option value="Laos">Laos</option>
	<option value="Latvia">Latvia</option>
	<option value="Lebanon">Lebanon</option>
	<option value="Lesotho">Lesotho</option>
	<option value="Liberia">Liberia</option>
	<option value="Libya">Libya</option>
	<option value="Liechtenstein">Liechtenstein</option>
	<option value="Lithuania">Lithuania</option>
	<option value="Luxembourg">Luxembourg</option>
	<option value="Macau">Macau</option>
	<option value="Macedonia">Macedonia</option>
	<option value="Madagascar">Madagascar</option>
	<option value="Malaysia">Malaysia</option>
	<option value="Malawi">Malawi</option>
	<option value="Maldives">Maldives</option>
	<option value="Mali">Mali</option>
	<option value="Malta">Malta</option>
	<option value="Marshall Islands">Marshall Islands</option>
	<option value="Martinique">Martinique</option>
	<option value="Mauritania">Mauritania</option>
	<option value="Mauritius">Mauritius</option>
	<option value="Mayotte">Mayotte</option>
	<option value="Mexico">Mexico</option>
	<option value="Midway Islands">Midway Islands</option>
	<option value="Moldova">Moldova</option>
	<option value="Monaco">Monaco</option>
	<option value="Mongolia">Mongolia</option>
	<option value="Montserrat">Montserrat</option>
	<option value="Morocco">Morocco</option>
	<option value="Mozambique">Mozambique</option>
	<option value="Myanmar">Myanmar</option>
	<option value="Nambia">Nambia</option>
	<option value="Nauru">Nauru</option>
	<option value="Nepal">Nepal</option>
	<option value="Netherland Antilles">Netherland Antilles</option>
	<option value="Netherlands">Netherlands (Holland, Europe)</option>
	<option value="Nevis">Nevis</option>
	<option value="New Caledonia">New Caledonia</option>
	<option value="New Zealand">New Zealand</option>
	<option value="Nicaragua">Nicaragua</option>
	<option value="Niger">Niger</option>
	<option value="Nigeria">Nigeria</option>
	<option value="Niue">Niue</option>
	<option value="Norfolk Island">Norfolk Island</option>
	<option value="Norway">Norway</option>
	<option value="Oman">Oman</option>
	<option value="Pakistan">Pakistan</option>
	<option value="Palau Island">Palau Island</option>
	<option value="Palestine">Palestine</option>
	<option value="Panama">Panama</option>
	<option value="Papua New Guinea">Papua New Guinea</option>
	<option value="Paraguay">Paraguay</option>
	<option value="Peru">Peru</option>
	<option value="Phillipines">Philippines</option>
	<option value="Pitcairn Island">Pitcairn Island</option>
	<option value="Poland">Poland</option>
	<option value="Portugal">Portugal</option>
	<option value="Puerto Rico">Puerto Rico</option>
	<option value="Qatar">Qatar</option>
	<option value="Republic of Montenegro">Republic of Montenegro</option>
	<option value="Republic of Serbia">Republic of Serbia</option>
	<option value="Reunion">Reunion</option>
	<option value="Romania">Romania</option>
	<option value="Russia">Russia</option>
	<option value="Rwanda">Rwanda</option>
	<option value="St Barthelemy">St Barthelemy</option>
	<option value="St Eustatius">St Eustatius</option>
	<option value="St Helena">St Helena</option>
	<option value="St Kitts-Nevis">St Kitts-Nevis</option>
	<option value="St Lucia">St Lucia</option>
	<option value="St Maarten">St Maarten</option>
	<option value="St Pierre &amp; Miquelon">St Pierre &amp; Miquelon</option>
	<option value="St Vincent &amp; Grenadines">St Vincent &amp; Grenadines</option>
	<option value="Saipan">Saipan</option>
	<option value="Samoa">Samoa</option>
	<option value="Samoa American">Samoa American</option>
	<option value="San Marino">San Marino</option>
	<option value="Sao Tome & Principe">Sao Tome &amp; Principe</option>
	<option value="Saudi Arabia">Saudi Arabia</option>
	<option value="Senegal">Senegal</option>
	<option value="Seychelles">Seychelles</option>
	<option value="Sierra Leone">Sierra Leone</option>
	<option value="Singapore">Singapore</option>
	<option value="Slovakia">Slovakia</option>
	<option value="Slovenia">Slovenia</option>
	<option value="Solomon Islands">Solomon Islands</option>
	<option value="Somalia">Somalia</option>
	<option value="South Africa">South Africa</option>
	<option value="Spain">Spain</option>
	<option value="Sri Lanka">Sri Lanka</option>
	<option value="Sudan">Sudan</option>
	<option value="Suriname">Suriname</option>
	<option value="Swaziland">Swaziland</option>
	<option value="Sweden">Sweden</option>
	<option value="Switzerland">Switzerland</option>
	<option value="Syria">Syria</option>
	<option value="Tahiti">Tahiti</option>
	<option value="Taiwan">Taiwan</option>
	<option value="Tajikistan">Tajikistan</option>
	<option value="Tanzania">Tanzania</option>
	<option value="Thailand">Thailand</option>
	<option value="Togo">Togo</option>
	<option value="Tokelau">Tokelau</option>
	<option value="Tonga">Tonga</option>
	<option value="Trinidad &amp; Tobago">Trinidad &amp; Tobago</option>
	<option value="Tunisia">Tunisia</option>
	<option value="Turkey">Turkey</option>
	<option value="Turkmenistan">Turkmenistan</option>
	<option value="Turks &amp; Caicos Is">Turks &amp; Caicos Is</option>
	<option value="Tuvalu">Tuvalu</option>
	<option value="Uganda">Uganda</option>
	<option value="Ukraine">Ukraine</option>
	<option value="United Arab Erimates">United Arab Emirates</option>
	<option value="United Kingdom">United Kingdom</option>
	<option value="United States of America">United States of America</option>
	<option value="Uraguay">Uruguay</option>
	<option value="Uzbekistan">Uzbekistan</option>
	<option value="Vanuatu">Vanuatu</option>
	<option value="Vatican City State">Vatican City State</option>
	<option value="Venezuela">Venezuela</option>
	<option value="Vietnam">Vietnam</option>
	<option value="Virgin Islands (Brit)">Virgin Islands (Brit)</option>
	<option value="Virgin Islands (USA)">Virgin Islands (USA)</option>
	<option value="Wake Island">Wake Island</option>
	<option value="Wallis &amp; Futana Is">Wallis &amp; Futana Is</option>
	<option value="Yemen">Yemen</option>
	<option value="Zaire">Zaire</option>
	<option value="Zambia">Zambia</option>
	<option value="Zimbabwe">Zimbabwe</option>';
}

function awpcp_state_list_options() {
	return '<option selected value="">-- Choose a State --</option>
	<option value="ZZ">None</option>
	<optgroup label="United States">
	<option value="AL">Alabama</option>
	<option value="AK">Alaska</option>
	<option value="AZ">Arizona</option>
	<option value="AR">Arkansas</option>
	<option value="CA">California</option>
	<option value="CO">Colorado</option>
	<option value="CT">Connecticut</option>
	<option value="DE">Delaware</option>
	<option value="FL">Florida</option>
	<option value="GA">Georgia</option>
	<option value="HI">Hawaii</option>
	<option value="ID">Idaho</option>
	<option value="IL">Illinois</option>
	<option value="IN">Indiana</option>
	<option value="IA">Iowa</option>
	<option value="KS">Kansas</option>
	<option value="KY">Kentucky</option>
	<option value="LA">Louisiana</option>
	<option value="ME">Maine</option>
	<option value="MD">Maryland</option>
	<option value="MA">Massachusetts</option>
	<option value="MI">Michigan</option>
	<option value="MN">Minnesota</option>
	<option value="MS">Mississippi</option>
	<option value="MO">Missouri</option>
	<option value="MT">Montana</option>
	<option value="NE">Nebraska</option>
	<option value="NV">Nevada</option>
	<option value="NH">New Hampshire</option>
	<option value="NJ">New Jersey</option>
	<option value="NM">New Mexico</option>
	<option value="NY">New York</option>
	<option value="NC">North Carolina</option>
	<option value="ND">North Dakota</option>
	<option value="OH">Ohio</option>
	<option value="OK">Oklahoma</option>
	<option value="OR">Oregon</option>
	<option value="PA">Pennsylvania</option>
	<option value="RI">Rhode Island</option>
	<option value="SC">South Carolina</option>
	<option value="SD">South Dakota</option>
	<option value="TN">Tennessee</option>
	<option value="TX">Texas</option>
	<option value="UT">Utah</option>
	<option value="VT">Vermont</option>
	<option value="VA">Virginia</option>
	<option value="WA">Washington</option>
	<option value="WV">West Virginia</option>
	<option value="WI">Wisconsin</option>
	<option value="WY">Wyoming</option>
	</optgroup>
	<optgroup label="Canada">
	<option value="AB">Alberta</option>
	<option value="BC">British Columbia</option>
	<option value="MB">Manitoba</option>
	<option value="NB">New Brunswick</option>
	<option value="NF">Newfoundland and Labrador</option>
	<option value="NT">Northwest Territories</option>
	<option value="NS">Nova Scotia</option>
	<option value="NU">Nunavut</option>
	<option value="ON">Ontario</option>
	<option value="PE">Prince Edward Island</option>
	<option value="PQ">Quebec</option>
	<option value="SK">Saskatchewan</option>
	<option value="YT">Yukon Territory</option>
	</optgroup>
	<optgroup label="Australia">
	<option value="AC">Australian Capital Territory</option>
	<option value="NW">New South Wales</option>
	<option value="NO">Northern Territory</option>
	<option value="QL">Queensland</option>
	<option value="SA">South Australia</option>
	<option value="TS">Tasmania</option>
	<option value="VC">Victoria</option>
	<option value="WS">Western Australia</option>
	</optgroup>';
}


/**
 * AWPCP misc functions
 *
 * TODO: merge content from functions_awpcp.php,
 * fileop.class.php, dcfunctions.php, upload_awpcp.php
 * as needed.
 */


/**
 * Return number of allowed images for an Ad, according to its
 * Ad ID or Fee Term ID.
 *
 * @param $ad_id 		int 	Ad ID.
 * @param $ad_term_id 	int 	Ad Term ID.
 */
function awpcp_get_ad_number_allowed_images($ad_id) {
	$ad = AWPCP_Ad::find_by_id($ad_id);

	if (is_null($ad)) {
		return 0;
	}

	$ad_term_id = $ad->adterm_id;
	if (!empty($ad_term_id)) {
		$allowed = get_numimgsallowed($ad_term_id);
	} else {
		$allowed = get_awpcp_option('imagesallowedfree');
	}

	return apply_filters('awpcp_number_images_allowed', $allowed, $ad_id);
}


/**
 */
function awpcp_get_ad_images($ad_id) {
	global $wpdb;

	$query = "SELECT * FROM " . AWPCP_TABLE_ADPHOTOS . " ";
	$query.= "WHERE ad_id=%d ORDER BY image_name ASC";

	return $wpdb->get_results($wpdb->prepare($query, $ad_id));
}

/**
 *
 */
function awpcp_get_image_url($image, $suffix='') {
	static $uploads = array();

	if (empty($uploads))
		$uploads = array_shift(awpcp_setup_uploads_dir());

	$images = trailingslashit(AWPCPUPLOADURL);
	$thumbnails = trailingslashit(AWPCPTHUMBSUPLOADURL);

	if (is_object($image))
		$basename = $image->image_name;
	if (is_string($image))
		$basename = $image;

	$original = $images . $basename;
	$thumbnail = $thumbnails . $basename;
	$part = empty($suffix) ? '.' : "-$suffix.";

	$info = pathinfo($original);

	if ($suffix == 'original') {
		$alternatives = array($original);
	} else if ($suffix == 'large') {
		$alternatives = array(
			str_replace(".{$info['extension']}", "$part{$info['extension']}", $original),
			$original
		);
	} else {
		$alternatives = array(
			str_replace(".{$info['extension']}", "$part{$info['extension']}", $thumbnail),
			$thumbnail,
			$original
		);
	}

	foreach ($alternatives as $imagepath) {
		if (file_exists(str_replace(AWPCPUPLOADURL, $uploads, $imagepath))) {
			return $imagepath;
		}
	}

	return false;
}


/**
 *
 */
function awpcp_set_ad_primary_image($ad_id, $image_id) {
	global $wpdb;

	$query = 'UPDATE ' . AWPCP_TABLE_ADPHOTOS . ' ';
	$query.= "SET is_primary = 0 WHERE ad_id = %d";

	if ($wpdb->query($wpdb->prepare($query, $ad_id)) === false)
		return false;

	$query = 'UPDATE ' . AWPCP_TABLE_ADPHOTOS . ' ';
	$query.= 'SET is_primary = 1 WHERE ad_id = %d AND key_id = %d';
	$query = $wpdb->prepare($query, $ad_id, $image_id);

	return $wpdb->query($query) !== false;
}


/**
 * Get the primary image of the given Ad.
 *
 * @param  int	$ad_id	Ad's ID
 * @return object	an StdClass object representing an image
 */
// TODO: eventually move this to AWPCP_Ad
function awpcp_get_ad_primary_image($ad_id) {
	global $wpdb;

	$query = 'SELECT * FROM ' . AWPCP_TABLE_ADPHOTOS . ' ';
	$query.= 'WHERE ad_id = %d AND is_primary = 1 AND disabled = 0';

	$results = $wpdb->get_results($wpdb->prepare($query, $ad_id));

	if (!empty($results)) return $results[0];

	$query = 'SELECT * FROM ' . AWPCP_TABLE_ADPHOTOS . ' ';
	$query.= 'WHERE ad_id = %d AND disabled = 0 ORDER BY key_id LIMIT 0,1';

	$results = $wpdb->get_results($wpdb->prepare($query, $ad_id));

	return empty($results) ? null : $results[0];
}


function awpcp_array_insert($array, $index, $key, $item, $where='before') {
	$all = array_merge($array, array($key => $item));
	$keys = array_keys($array);
	$p = array_search($index, $keys);

	if ($p !== FALSE) {
		if ($where === 'before')
			array_splice($keys, max($p, 0), 0, $key);
		else if ($where === 'after')
			array_splice($keys, min($p+1, count($keys)), 0, $key);

		$array = array();
		// create items array in proper order.
		// the code below was the only way I find to insert an
		// item in an arbitrary position of an array preserving
		// keys. array_splice dropped the key of the inserted
		// value.
		foreach($keys as $key) {
			$array[$key] = $all[$key];
		}
	}

	return $array;
}

function awpcp_array_insert_before($array, $index, $key, $item) {
	return awpcp_array_insert($array, $index, $key, $item, 'before');
}

function awpcp_array_insert_after($array, $index, $key, $item) {
	return awpcp_array_insert($array, $index, $key, $item, 'after');
}

/**
 * Inserts a menu item after one of the existing items.
 *
 * This function should be used by plugins when handling
 * the awpcp_menu_items filter.
 *
 * @param $items 	array 	Existing items
 * @param $after 	string 	key of item we want to place the new
 * 							item after
 * @param $key 		string 	New item's key
 * @param $item 	array 	New item's description
 */
function awpcp_insert_menu_item($items, $after, $key, $item) {
	return awpcp_array_insert_after($items, $after, $key, $item);
}


/**
 * Insert a submenu item in a WordPress admin menu, after an
 * existing item.
 *
 * Menu item should have already been added using add_submenu_page
 * or a similar function.
 *
 * @param $slug		string	Slug for the item to insert.
 * @param $after	string	Slug of the item to insert after.
 */
function awpcp_insert_submenu_item_after($menu, $slug, $after) {
    global $submenu;

    $items = isset($submenu[$menu]) ? $submenu[$menu] : array();
    $to = -1; $from = -1;

    foreach ($items as $k => $item) {
        // insert after Fees
        if (strcmp($item[2], $after) === 0)
            $to = $k;
        if (strcmp($item[2], $slug) === 0)
            $from = $k;
    }

    if ($to >= 0 && $from >= 0) {
        array_splice($items, $to + 1, 0, array($items[$from]));
        // current was added at the end of the array using add_submenu_page
        unset($items[$from + 1]);
        // use array_filter to restore array keys
        $submenu[$menu] = array_filter($items);
    }
}


/**
 * Check if the page identified by $refname exists.
 */
function awpcp_find_page($refname) {
	global $wpdb;

	$query = 'SELECT posts.ID, page FROM ' . $wpdb->posts . ' AS posts ';
	$query.= 'LEFT JOIN ' . AWPCP_TABLE_PAGES . ' AS pages ';
	$query.= 'ON (posts.ID = pages.id) WHERE pages.page = %s';

	$query = $wpdb->prepare($query, $refname);
	$pages = $wpdb->get_results($query);

	return $pages !== false && !empty($pages);
}


/**
 * Return name of current AWPCP page.
 *
 * This is part of an effor to put all AWPCP functions under
 * the same namespace.
 */
function awpcp_get_main_page_name() {
	return get_awpcp_option('main-page-name');
}


/**
 * Always return the full URL, even if AWPCP main page
 * is also the home page.
 */
function awpcp_get_main_page_url() {
	$id = awpcp_get_page_id_by_ref('main-page-name');

	if (get_option('permalink_structure')) {
		$url = home_url(get_page_uri($id));
	} else {
		$url = add_query_arg('page_id', $id, home_url());
	}

	return user_trailingslashit($url);
}


/**
 * Returns a link to an AWPCP page identified by $pagename.
 *
 * Always return the full URL, even if the page is set as
 * the homepage.
 *
 * The returned URL has no trailing slash.
 *
 * @since 2.0.7
 */
function awpcp_get_page_url($pagename) {
	$id = awpcp_get_page_id_by_ref($pagename);

	if (get_option('permalink_structure')) {
		$url = home_url(get_page_uri($id));
	} else {
		$url = add_query_arg('page_id', $id, home_url());
	}

	return rtrim($url, '/');
}


/**
 * Returns a link that can be used to initiate the Ad Renewal process.
 *
 * @since 2.0.7
 */
function awpcp_get_renew_ad_url($ad_id) {
	if (get_awpcp_option('enable-user-panel') == 1) {
		$url = awpcp_get_user_panel_url();
		$url = add_query_arg(array('id' => $ad_id, 'action' => 'renew-ad'), $url);
	} else {
		$url = awpcp_get_page_url('renew-ad-page-name');
		$url = add_query_arg(array('ad_id' => $ad_id), $url);
	}

	return $url;
}

/**
 * Returns a link to Ad Management (a.k.a User Panel).
 *
 * @since 2.0.7
 */
function awpcp_get_user_panel_url() {
	return admin_url('admin.php?page=awpcp-panel');
}


function awpcp_current_url() {
	return (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Returns the domain used in the current request, optionally stripping
 * the www part of the domain.
 *
 * @since 2.0.6
 * @param $www 	boolean		true to include the 'www' part,
 *							false to attempt to strip it.
 */
function awpcp_get_current_domain($www=true, $prefix='') {
	$domain = awpcp_array_data('HTTP_HOST', '', $_SERVER);
	if (empty($domain)) {
		$domain = awpcp_array_data('SERVER_NAME', '', $_SERVER);
	}

	if (!$www && substr($domain, 0, 4) === 'www.') {
		$domain = $prefix . substr($domain, 4);
	}

	return $domain;
}

/**
 * Bulds WordPress ajax URL using the same domain used in the current request.
 *
 * @since 2.0.6
 */
function awpcp_ajaxurl($overwrite=false) {
	static $ajaxurl = false;

	if ($overwrite || $ajaxurl === false) {
		$url = admin_url('admin-ajax.php');
		$parts = parse_url($url);
		$ajaxurl = str_replace($parts['host'], awpcp_get_current_domain(), $url);
	}

	return $ajaxurl;
}


function awpcp_post_param($name, $default='') {
	return awpcp_array_data($name, $default, $_POST);
}


function awpcp_request_param($name, $default='', $from=null) {
	return awpcp_array_data($name, $default, is_null($from) ? $_REQUEST : $from);
}


function awpcp_array_data($name, $default, $from=array()) {
	$value = isset($from[$name]) ? $from[$name] : null;

	if (is_array($value) && count($value) > 0) {
		return $value;
	} else if (!empty($value)) {
		return $value;
	}

	return $default;
}


function awpcp_get_property($object, $property, $default='') {
    if (is_object($object) && (isset($object->$property) ||
    	array_key_exists($property, get_object_vars($object)))) {
        return $object->$property;
    }
    return $default;
}


function awpcp_get_properties($objects, $property, $default='') {
	$results = array();
	foreach ($objects as $object) {
		$results[] = awpcp_get_property($object, $property, $default);
	}
	return $results;
}

/**
 * Parses 'yes', 'true', 'no', 'false', 0, 1 into bool values.
 *
 * @since  2.1.3
 * @param  mixed	$value	value to parse
 * @return bool
 */
function awpcp_parse_bool($value) {
	$lower = strtolower($value);
	if ($lower === 'true' || $lower === 'yes')
		return true;
	if ($lower === 'false' || $lower === 'no')
		return false;
	return $value ? true : false;
}


function awpcp_flash($message) {
	$messages = get_option('awpcp-messages', array());
	$messages[] = $message;
	update_option('awpcp-messages', $messages);
}


function awpcp_print_message($message, $class=array('updated')) {
	$class = array_merge(array('awpcp-message'), $class);
	return '<div class="' . join(' ', $class) . '"><p>' . $message . '</p></div>';
}


function awpcp_print_messages() {
	$messages = get_option('awpcp-messages', array());

	$html = '';
	foreach ($messages as $message) {
		$html .= awpcp_print_message($message);
	}

	update_option('awpcp-messages', array());

	echo $html;
}
add_action('admin_notices', 'awpcp_print_messages');


function awpcp_validate_error($field, $errors) {
	$error = awpcp_array_data($field, '', $errors);
	if (empty($error))
		return '';
	return '<label for="' . $field . '" generated="true" class="error" style="">' . $error . '</label>';
}

function awpcp_form_error($field, $errors) {
	$error = awpcp_array_data($field, '', $errors);
	return empty($error) ? '' : '<span class="awpcp-error">' . $error . '</span>';
}


function awpcp_uploaded_file_error($file) {
	$upload_errors = array(
		UPLOAD_ERR_OK        	=> __("No errors.", 'AWPCP'),
		UPLOAD_ERR_INI_SIZE    	=> __("The file is larger than upload_max_filesize.", 'AWPCP'),
		UPLOAD_ERR_FORM_SIZE    => __("The file is larger than form MAX_FILE_SIZE.", 'AWPCP'),
		UPLOAD_ERR_PARTIAL    	=> __("The file was only partially uploaded.", 'AWPCP'),
		UPLOAD_ERR_NO_FILE      => __("No file was uploaded.", 'AWPCP'),
		UPLOAD_ERR_NO_TMP_DIR   => __("Missing temporary directory.", 'AWPCP'),
		UPLOAD_ERR_CANT_WRITE   => __("Can't write file to disk.", 'AWPCP'),
		UPLOAD_ERR_EXTENSION    => __("The file upload was stopped by extension.", 'AWPCP')
	);

	return array($file['error'], $upload_errors[$file['error']]);
}

/**
 * @since 2.0.7
 */
function awpcp_table_exists($table) {
    global $wpdb;
    $result = $wpdb->get_var("SHOW TABLES LIKE '" . $table . "'");
    return strcasecmp($result, $table) === 0;
}


/**
 * @since  2.1.4
 */
function awpcp_column_exists($table, $column) {
    global $wpdb;
    $wpdb->hide_errors();
    $result = $wpdb->query("SELECT `$column` FROM $table");
    $wpdb->show_errors();
    return $result !== false;
}


/** Table Helper related functions
 ---------------------------------------------------------------------------- */

function awpcp_register_column_headers($screen, $columns, $sortable=array()) {
	$wp_list_table = new AWPCP_List_Table($screen, $columns, $sortable);
}


function awpcp_print_column_headers($screen, $id = true, $sortable=array()) {
	$wp_list_table = new AWPCP_List_Table($screen, array(), $sortable);
	$wp_list_table->print_column_headers($id);
}


/** Temporary solution to avoid breaking inline scripts due to wpauotp and wptexturize
 ---------------------------------------------------------------------------- */

/**
 * @since  2.1.2
 */
function awpcp_inline_javascript_placeholder($name, $script) {
	global $awpcp;

	if (!isset($awpcp->inline_scripts) || !is_array($awpcp->inline_scripts))
		$awpcp->inline_scripts = array();

	$awpcp->inline_scripts[$name] = $script;

	return "<AWPCPScript style='display:none'>$name</AWPCPScript>";
}

/**
 * @since  2.1.2
 */
function awpcp_inline_javascript($content) {
	global $awpcp;

	if (!isset($awpcp->inline_scripts) || !is_array($awpcp->inline_scripts))
		return $content;

	foreach ($awpcp->inline_scripts as $name => $script) {
		$content = preg_replace("{<AWPCPScript style='display:none'>$name</AWPCPScript>}", $script, $content);
	}

	return $content;
}

/**
 * @since  2.1.3
 */
function awpcp_print_inline_javascript() {
	global $awpcp;

	if (!isset($awpcp->inline_scripts) || !is_array($awpcp->inline_scripts))
		return;

	foreach ($awpcp->inline_scripts as $name => $script) {
		echo $script;
	}
}
