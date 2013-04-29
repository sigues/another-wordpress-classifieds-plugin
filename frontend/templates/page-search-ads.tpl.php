<div id="classiwrapper" class="awpcp-page-search-ads">

	<?php echo awpcp_inline_javascript_placeholder('search-ads', "<script type=\"text/javascript\">
		function checkform() {
			var the=document.myform;
			if (the.keywordphrase.value==='') {
				if ((the.searchname.value==='') && (the.searchcategory.value==='') &&
					(the.searchpricemin.value==='') && (the.searchpricemax.value==='') &&
					(!the.searchcity || the.searchcity.value==='') &&
					(!the.searchstate || the.searchstate.value==='') && 
					(!the.searchcountry || the.searchcountry.value==='') &&
					(!the.searchcountyvillage || the.searchcountyvillage.value===''))
				{
					alert('" . __("You did not enter a keyword or phrase to search for. You must at the very least provide a keyword or phrase to search for", "AWPCP") . "');
					the.keywordphrase.focus();
					return false;
				}
			}
			return true;
		}
	</script>"); ?>
	
	<?php echo awpcp_menu_items() ?>

	<?php if (isset($message) && !empty($message)): ?>
	<p><?php echo $message; ?></p>
	<?php endif ?>

	<?php if ($hasregionsmodule): ?>
	<?php echo awpcp_region_control_selector() ?>
	<?php endif ?>

	<form method="post" name="myform" id="awpcpui_process" onsubmit="return(checkform())">
		<input type="hidden" name="a" value="dosearch" />
		<p class='awpcp-form-spacer'>
			<?php _e("Search for ads containing this word or phrase","AWPCP") ?>:<br/>
			<input type="text" class="inputbox" size="50" name="keywordphrase" value="<?php echo esc_attr($keywordphrase) ?>" />
		</p>
		<p class='awpcp-form-spacer'>
			<?php _e("Search in Category","AWPCP") ?><br/>
			<select name="searchcategory">
				<option value=""><?php _e("Select Option","AWPCP") ?></option>
				<?php echo $allcategories ?>
			</select>
		</p>


	<?php if (get_awpcp_option('displaypostedbyfield') == 1): ?>
		<p class='awpcp-form-spacer'>
			<?php _e("For Ads Posted By","AWPCP") ?>
			<br/>
			<select name="searchname">
				<option value=""><?php _e("Select Option","AWPCP"); ?></option>
				<?php echo create_ad_postedby_list($searchname) ?>
			</select>
		</p>
	<?php endif ?>


	<?php if (get_awpcp_option('displaypricefield') == 1): ?>
		<?php if (price_field_has_values()): ?>

		<p class='awpcp-form-spacer'>
			<?php _e("Min Price","AWPCP") ?>
			&nbsp;
			<select name="searchpricemin">
				<option value=""><?php _e("Select","AWPCP") ?></option>
				<?php echo create_price_dropdownlist_min($searchpricemin) ?>
			</select>
			<span>&nbsp;</span>
			<?php _e("Max Price","AWPCP") ?>
			&nbsp;
			<select name="searchpricemax">
				<option value=""><?php _e("Select","AWPCP") ?></option>
				<?php echo create_price_dropdownlist_max($searchpricemax) ?>
			</select>
		</p>

		<?php else: ?>

		<input type="hidden" name="searchpricemin" value="" />
		<input type="hidden" name="searchpricemax" value="" />

		<?php endif ?>
	<?php endif ?>


	<?php echo $region_fields ?>
	
		
	<?php if ($hasextrafieldsmodule == 1 && function_exists('build_extra_field_form')): ?>
		<?php echo build_extra_field_form('searchad', '', true) ?>
	<?php endif ?>
	
		<div style="padding-bottom:5px;padding-top:10px;align:left;float:left">
			<input class="button" type="submit" value="<?php _e("Start Search","AWPCP") ?>" />
		</div>
	</form>
</div>