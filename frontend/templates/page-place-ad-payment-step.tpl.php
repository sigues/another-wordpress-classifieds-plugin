<div id="classiwrapper">
	<?php if (!is_admin()): ?>
	<?php echo awpcp_menu_items() ?>
	<?php endif ?>

	<h2><?php _e('Select Payment/Category', 'AWPCP') ?></h2>

	<?php foreach ($header as $part): ?>
	<p><?php echo $part ?></p>
	<?php endforeach ?>

	<form id="awpcp-place-ad-payment-step-form" method="post">
		<fieldset>
			<h3><?php _e('Please select a Category for your Ad', 'AWPCP') ?></h3>

			<label for="place-ad-category">Ad Category</label>
			<select id="place-ad-category" name="category">
				<option value="0"><?php _e('Select a Category', 'AWPCP') ?></option>
				<?php echo get_categorynameidall(awpcp_array_data('category', '', $form_values)); ?>
			</select>
			<?php $error = awpcp_array_data('category', '', $form_errors); ?>
			<?php if (!empty($error)): ?>
			<br/><span class="awpcp-error"><?php echo $error ?></span>
			<?php endif ?>
		</fieldset>
            <fieldset id="auto_calculate" style="display:block">
                <div>
                    <table width="300px">
                        <tr>
                            <td rowspan="2">Cantidad de renglones
                                <input name="renglones" id="renglones" type="text" size="3" value="3" />
                            </td>
                            <td><h2><span id="agregaRenglon">+</span></h2></td>
                        </tr>
                        <tr>
                            <td><h2><span id="eliminaRenglon">-</span></h2></td>
                        </tr>
                    </table>
                </div>
                <div id="renglones">
                    <?php
                        for($x=1;$x<=6;$x++){ 
                    ?>
                    <div id="div_renglon_<?=$x?>">
                        <input type="text" name="renglon_<?=$x?>" id="renglon_<?=$x?>" />
                    </div><br>
                    <?php    
                        }
                    ?>
                   
                
                </div>
                <input type="checkbox" id="fondo_negro" name="fondo_negro" /> Fondo Negro
                <input type="checkbox" id="marco" name="marco" /> Marco
                
            </fieldset>
		<fieldset id="payment_terms">
			<h3><?php _e('Please select a payment term for your Ad', 'AWPCP') ?></h3>
			<?php $error = awpcp_array_data('payment-term', '', $form_errors); ?>
			<?php if (!empty($error)): ?>
			<span class="awpcp-error"><?php echo $error ?></span>
			<?php endif ?>

			<table class="awpcp-table">
				<thead>
					<tr>
						<th><?php _e('Payment Term', 'AWPCP') ?></th>
						<th><?php _e('Ads Allowed', 'AWPCP') ?></th>
						<th><?php _e('Images Allowed', 'AWPCP') ?></th>
						<th><?php _e('Characters Allowed', 'AWPCP') ?></th>
						<th><?php _e('Duration', 'AWPCP') ?></th>
						<th><?php _e('Price', 'AWPCP') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $type = '' ?>
					<?php $selected = awpcp_array_data('payment-term', '', $form_values) ?>
					<?php foreach ($payment_terms as $term): ?>

					<?php if ($term->type != $type): ?>
					<tr class="awpcp-payment-term-type-header">
						<th colspan="5" scope="row"><?php echo $term->type_name ?></th>
					</tr>
					<?php endif ?>

					<tr class="js-awpcp-payment-term" data-price="<?php echo esc_attr($term->price) ?>" data-categories="<?php echo esc_attr(json_encode($term->categories)) ?>">
						<td>
							<?php $id = "{$term->type}-{$term->id}" ?>
							<?php $element_id = "payment-term-$id" ?>
							<input id="<?php echo $element_id ?>" type="radio" name="payment-term" value="<?php echo esc_attr($id) ?>" <?php echo $id == $selected ? 'checked="checked"' : '' ?> />
							<label for="<?php echo $id ?>"><strong><?php echo $term->name ?></strong></label><br/>
							<?php echo $term->description ?>
						</td>
						<td><?php echo $term->ads_allowed ?></td>
						<td><?php echo $term->images_allowed ?></td>
						<td><?php echo empty($term->characters_allowed) ? __('No Limit', 'AWPCP') : $term->characters_allowed ?></td>
						<td><?php echo $term->duration ?></td>
						<td><?php echo number_format($term->price, 2) ?></td>
					</tr>
					<?php $type = $term->type ?>

					<?php endforeach ?>
				</tbody>
			</table>
		</fieldset>

		<fieldset id="payment_method">
			<h3><?php _e('Please select a payment method', 'AWPCP') ?></h3>
			<?php $error = awpcp_array_data('payment-method', '', $form_errors); ?>
			<?php if (!empty($error)): ?>
			<span class="awpcp-error"><?php echo $error ?></span>
			<?php endif ?>

			<?php $selected = awpcp_array_data('payment-method', '', $form_values) ?>
			<?php $selected = empty($selected) ? array_shift(awpcp_get_properties($payment_methods, 'slug')) : $selected ?>
			<?php echo awpcp_payments_methods_form($selected) ?>
		</fieldset>

		<p class="form-submit">
			<input class="button" type="submit" value="<?php _e('Continue', 'AWPCP') ?>" id="submit" name="submit">
			<input type="hidden" value="<?php echo esc_attr($transaction->id) ?>" name="awpcp-txn">
			<input type="hidden" value="checkout" name="a">
		</p>
	</form>
</div>
<script type="text/javascript">
    jQuery(document).ready(function(){
        //jQuery("#payment_terms").hide(); 
        jQuery("#auto_calculate").show();
        jQuery("#payment_method").show(); 
        jQuery("#agregaRenglon").click(function(){
            agregarRenglon();
        });
        jQuery("#eliminaRenglon").click(function(){
           eliminarRenglon();
        });
        
    });
    function agregarRenglon(){
        var renglones = parseInt(jQuery("#renglones").val());
        if(renglones<6){
            jQuery("#renglones").val(renglones+1);
        habilitaCampos(renglones+1)
        }
    }
    function eliminarRenglon(){
        var renglones = parseInt(jQuery("#renglones").val());
        if(renglones>1){    
            jQuery("#renglones").val(renglones-1);
        habilitaCampos(renglones-1)
        }
    }
    function habilitaCampos(renglones){
        var x = 1;
        for(x=1;x<=6;x++){
            if(x<=renglones){
                jQuery("#div_renglon_"+x).show();
            }else{
                jQuery("#div_renglon_"+x).hide();
                
            }
        }
    }
    
    habilitaCampos(3);
    
</script>