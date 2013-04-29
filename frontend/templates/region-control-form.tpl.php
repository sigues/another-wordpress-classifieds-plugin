<form id="awpcp-region-control-selector" action="<?php echo $url ?>" method="post">
	<p class="legend">
	<?php if ($country != null || $state != null || $city != null){
		echo __('Current Location:', 'AWPCP');
		$location = array(
			$city ? stripslashes($city->region_name) : false,
			$state ? stripslashes($state->region_name) : false,
			$country ? stripslashes($country->region_name) : false);
		echo ' <strong>' . join(', ', array_filter($location)) . '</strong>';
	} else {
		echo __('You can use the fields below to refine or clear your current location. Start by selecting a Country, other fields will be automatically updated to show available locations.<br/>Use the <em>Clear Location</em> button if you want to start over.', 'AWPCP');
		echo '<br/>';
	} ?>
	</p>
	<?php echo $fields ?>
	<div class="submit">
		<input class="button" name="clear-location" type="submit" value="Clear Location" />
		<input class="button" name="set-location" type="submit" value="Set Location" />
	</div>
</form>