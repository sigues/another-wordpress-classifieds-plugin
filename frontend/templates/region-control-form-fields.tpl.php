	<div class="awpcp-region-control-region-fields">

	<?php $ordered = array('country', 'state', 'city', 'county'); ?>
	<?php $hidden = true; ?>

	<?php foreach ($ordered as $name): 

			if (!isset($fields[$name])) { continue; }

			$field = $fields[$name];

			// hide if the previous field is hidden, current field has 1 or less entries
			// and the user already selected a region of this type
			$hidden = $hidden && (count($field['entries']) == 1);
			$css = count($field['entries']) == 1 ? "single {$field['class']}" : $field['class']; ?>

		<?php if (!$hidden): ?>
		<p class="awpcp-form-spacer <?php echo $css ?>" region-field="<?php echo $name ?>" data-region-field-name="<?php echo esc_attr($field['name']) ?>">

			<span class="label"><?php echo $field['label'] ?></span>

			<?php if (!empty($field['options'])): ?>

			<span class="helptext hidden">(<?php echo $field['help'] ?>)</span><br/>
			<select name="<?php echo esc_attr($field['name']) ?>">
				<?php echo $field['options'] ?>
			</select>
			<input class="hidden" size="35" type="text" class="inputbox" value="<?php echo awpcp_esc_attr($field['value']) ?>" />

			<?php else: ?>	

			<span class="helptext">(<?php echo $field['help'] ?>)</span><br/>
			<select class="hidden">
				<?php echo $field['options'] ?>
			</select>
			<input size="35" type="text" class="inputbox" name="<?php echo esc_attr($field['name']) ?>" value="<?php echo awpcp_esc_attr($field['value']) ?>" />

			<?php endif ?>
		</p>
		<?php else: ?>
		<p class="awpcp-form-spacer <?php echo $css ?>">
			<?php $value = $field['entries'][0]->region_name ?>
			<?php echo $field['label'] ?>: <strong><?php echo stripslashes($value) ?></strong>
		</p>
		<input type="hidden" name="<?php echo esc_attr($field['name']) ?>" value="<?php echo awpcp_esc_attr($value) ?>" />
		<?php endif ?>

	<?php endforeach ?>

	<?php echo awpcp_inline_javascript_placeholder('region-control-form-fields-ajaxurl', '<script type="text/javascript">
		//<![CDATA[
			var AWPCP = AWPCP || {};
			AWPCP.ajaxurl = "' . awpcp_ajaxurl() . '";
		//]]>
		</script>'); ?>
	</div>