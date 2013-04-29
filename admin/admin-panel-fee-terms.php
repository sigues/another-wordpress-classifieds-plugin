<?php 


function awpcp_opsconfig_fees() {
	$output = '';
	$cpagename_awpcp=get_awpcp_option('main-page-name');
	$awpcppagename = sanitize_title($cpagename_awpcp, $post_ID='');

		global $wpdb;
		global $message;

		$tbl_ad_fees = $wpdb->prefix . "awpcp_adfees";
		// Start the page display
		$output .= "<div class=\"wrap\">";
		$output .= "<h2>";
		$output .= __("AWPCP Classifieds Management System: Listing Fees Management","AWPCP");
		$output .= "</h2>";

		// no need to check if the sidebar was generated. The layout of
		// this page already takes all available espace.
		$output .= awpcp_admin_sidebar();

		if (isset($message) && !empty($message)) {
			$output .= $message;
		}

		$output .= "<p style=\"padding:10px;\">";
		 $output .= __("Below you can add and edit your listing fees. As an example you can add an entry set at $9.99 for a 30 day listing, then another entry set at $17.99 for a 60 day listing. For each entry you can set a specific number of images a user can upload. If you have allow images turned off in your main configuration settings the value you add here will not matter as an upload option will not be included in the ad post form. You can also set a text limit for all ads. The value is in characters.","AWPCP");
		 $output .= "</p>";

		if (function_exists('fpc_check_awpcp_ver')) {
			$output .= '<div style="background-color: #FFFBCC;  color: #555555; background-color: #FFFBCC; border: 1px solid #E6DB55; margin: 0 20px 20px 0; font-size: 12px; padding: 10px;">' . 
				    __("You're using the Fee Per Category Module. Be sure to either assign all categories to a fee plan, or create at least one or more plans with no categories assigned.",'AWPCP') . 
				    '</div>';
		}


		///////
		// Handle case of adding new settings

		$create = false;
		$terms = array();
		
		if (isset($_REQUEST['addnewlistingfeeplan']) && !empty($_REQUEST['addnewlistingfeeplan'])) {
			$terms[] = array_fill(0, 8, '');
			$create = true;
		} else {
		 	$query = "SELECT adterm_id,adterm_name,amount,rec_period,rec_increment,";
		 	$query.= "imagesallowed,is_featured_ad_pricing,characters_allowed FROM " . AWPCP_TABLE_ADFEES;
		 	$res = awpcp_query($query, __LINE__);

		 	if (mysql_num_rows($res)) {
		 		while ($rsrow = mysql_fetch_row($res)) {
		 			$terms[] = $rsrow;
				}
			}
		}

		if (!$create) {
			$output .= "<form method=\"post\" id=\"awpcp_opsconfig_fees\">
				<p style=\"padding:10px;\"><input class=\"button-primary\" type=\"submit\" name=\"addnewlistingfeeplan\" value=\"";
			$output .= __("Add a new listing fee plan","AWPCP");
			$output .= "\" /></p></form>";
		 	$output .= "<ul style='width: 80%'>";
		}

		foreach ($terms as $term) {

			list($adterm_id,$adterm_name,$amount,$rec_period,$rec_increment,$imagesallowed,$is_featured_ad_pricing,$characters_allowed,$categories) = $term;
			$categories = explode(',', $categories);

			$rec_increment_op = '';
			$increments = array('D' => __('Days'), 'W' => __('Weeks'), 'M' => __('Months'), 'Y' => __('Years'));
			foreach ($increments as $key => $increment) {
				if (!$create && $rec_increment == $key) {
					$rec_increment_op .= '<option value="' . $key . '" selected="selected">' . $increment . '</option>';
				} else {
					$rec_increment_op .= '<option value="' . $key . '">' . $increment . '</option>';
				}
			}
			$rec_increment_op .= "\n";

			$awpcpfeeform ="<form method=\"post\" id=\"awpcp_launch\">";
		 	$awpcpfeeform.="<p>";
		 	$awpcpfeeform.=__("Plan Name [eg; 30 day Listing]","AWPCP");
		 	$awpcpfeeform.="<br/>";
		 	$awpcpfeeform.="<input class=\"regular-text1\" size=\"30\" type=\"text\" class=\"inputbox\" name=\"adterm_name\" value=\"$adterm_name\" /></p>";
		 	$awpcpfeeform.="<p>";
		 	$awpcpfeeform.=__("Price [x.xx format]","AWPCP");
		 	$awpcpfeeform.="<br/>";
		 	$awpcpfeeform.="<input class=\"regular-text1\" size=\"5\" type=\"text\" class=\"inputbox\" name=\"amount\" value=\"$amount\" /></p>";
		 	$awpcpfeeform.="<p>";
		 	$awpcpfeeform.=__("Term Duration","AWPCP");
		 	$awpcpfeeform.="<br/>";
		 	$awpcpfeeform.="<input class=\"regular-text1\" size=\"5\" type=\"text\" class=\"inputbox\" name=\"rec_period\" value=\"$rec_period\" /></p>";
		 	$awpcpfeeform.="<p>";
		 	$awpcpfeeform.=__("Term Increment","AWPCP");
		 	$awpcpfeeform.="<br/>";
		 	$awpcpfeeform.="<select name=\"rec_increment\" size=\"1\">$rec_increment_op</select></p>";
		 	$awpcpfeeform.="<p>";
		 	$awpcpfeeform.=__("Images Allowed","AWPCP");
		 	$awpcpfeeform.="<br/>";
		 	$awpcpfeeform.="<input class=\"regular-text1\" size=\"5\" type=\"text\" class=\"inputbox\" name=\"imagesallowed\" value=\"$imagesallowed\" /></p>";
		 	$awpcpfeeform.="<p>";
		 	$awpcpfeeform.=__("Characters Allowed", "AWPCP");
		 	$awpcpfeeform.="<br/>";
		 	$awpcpfeeform.="<input class=\"regular-text1\" size=\"5\" type=\"text\" class=\"inputbox\" name=\"characters_allowed\" value=\"$characters_allowed\" /></p>";

		 	if ($create) {
				if ( function_exists('awpcp_featured_ads') ) {
				    $awpcpfeeform .= awpcp_featured_ads_price_new();
				}
				if ( function_exists('awpcp_price_cats') ) {
				    $awpcpfeeform .= awpcp_price_cats();
				}
			
				$awpcpfeeform.="<input class=\"button-primary\" type=\"submit\" name=\"addnewfeesetting\" value=\"";
			 	$awpcpfeeform.=__("Add New Plan","AWPCP");
			 	$awpcpfeeform.="\" />";
			 	$awpcpfeeform.="</form>";

		 		$output .= "<div class=\"postbox\" style=\"padding:20px; width:300px;\">$awpcpfeeform</div>";
		 	} else {
			 	if ( function_exists('awpcp_featured_ads') ) {
				    $awpcpfeeform.= awpcp_featured_ads_price_config($is_featured_ad_pricing);
				}
				if ( function_exists('awpcp_price_cats') ) {
				    $awpcpfeeform .= awpcp_price_cats($categories, $adterm_id);
				}

				$awpcpfeeform.="<input class=\"button-primary\" type=\"submit\" name=\"savefeesetting\" value=\"";
	 			$awpcpfeeform.=__("Update Plan","AWPCP");
	 			$awpcpfeeform.="\" />";
	 			$awpcpfeeform.="<input type=\"hidden\" name=\"adterm_id\" value=\"$adterm_id\">";
	 			$awpcpfeeform.="<input class=\"button-primary\" type=\"submit\" name=\"deletefeesetting\" value=\"";
	 			$awpcpfeeform.=__("Delete Plan","AWPCP");
	 			$awpcpfeeform.="\" />";
		 		$awpcpfeeform.="</form>";

				$output .= "<li class=\"postbox\" style=\"float:left;width:280px;padding:10px; margin-right:20px;\">$awpcpfeeform</li>";
			}

		 	$message="<div style=\"background-color: rgb(255, 251, 204);\" id=\"message\" class=\"updated fade\">";
		 	$message.=__("The new plan has been added!","AWPCP");
		 	$message.="</div>";
		}

		if (!$create) {
			$output .= "</ul>";
			$output .= "<div style=\"clear:both;\"></div>";
		}

		$output .= "</div><br/>";

	//Echo OK here
	echo $output;
}