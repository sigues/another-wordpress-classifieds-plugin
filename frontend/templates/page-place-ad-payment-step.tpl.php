<?php error_reporting (-1); ?>
<LINK href="/wp-includes/css/mexicoaqui.css" rel="stylesheet" type="text/css">
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
                    <table style="width:200px;">
                        <tr>
                            <td rowspan="2">Cantidad de renglones
                                <input name="renglones" id="renglones" type="text" size="3" value="5" />
                            </td>
                            <td><b><span id="agregaRenglon">+</span></b></td>
                        </tr>
                        <tr>
                            <td><b><span id="eliminaRenglon">-</span></b></td>
                        </tr>
                    </table>
                </div>
                <div id="renglones" class="cont-renglones">
                    <?php
                        for($x=1;$x<=50;$x++){ 
                    ?>
                    <div id="div_renglon_<?=$x?>">
                        Renglon <?=$x?>: <input type="text" name="renglon_<?=$x?>" 
                                                onkeydown="cuentaChar(<?=$x?>);" 
                                                class="renglones" id="renglon_<?=$x?>" maxlength="19" />

                                                <!--onkeypress="cuentaChar(<?=$x?>);" -->
                    </div><br>
                    <?php    
                        }
                    ?>
                   
                
                </div>
				<div id="title-preview" class="title-preview"><h3>Previsualiza tu anuncio</h3></div>
                <div id="renglones" class="preview">
                    <div id="pre-border" 
                         class="pre-border">
                        <div id="pre-fondo"
                             class="pre-fondo">
                                <?php
                                for($x=1;$x<=50;$x++){ 
                                    ?>
                                    <p id="pre_renglon_<?php echo $x; ?>">&nbsp;</p>
                                <?php 
                                } //die();
                                ?>
                        </div>
                    </div>
                
                </div>
                
                <br><br>
                <input type="checkbox" id="fondo_color" name="fondo_color" /><label for="fondo_color"> Fondo A Color</label>
                <select name="fondo_negro" id="fondo_negro" disabled="disabled">
                    <option>Seleccione</option>
                    <option id="amarillo" class="amarillo" value="yellow">Amarillo</option>
                    <option id="cyan" class="cyan" value="cyan">Cyan</option>
                    <option id="magenta" class="magenta" value="magenta">Magenta</option>
                    <option id="blanco" class="blanco" value="white">Blanco</option>
                    <option id="negro" class="negro" value="black">Negro</option>
                </select>
                <br>
                <input type="checkbox" id="marco" name="marco" /><label for="marco"> Quiere marco?</label>
                <div id="div_precio_final">
                    <p id="label_precio_final">Precio Final</p>
                    <div id="precio_final">
						$<span id="num_precio_final"></span> DLLS
                    </div>
                    
                </div>
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
							<input id="<?php echo $element_id ?>" type="radio" clave="<?php echo substr($term->name,0,6);  ?>" 
                                                               precio="<?php echo number_format($term->price, 2) ?>"
                                                               name="payment-term" value="<?php echo esc_attr($id) ?>" <?php echo $id == $selected ? 'checked="checked"' : '' ?> />
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
        function cuentaChar(x){
            if(jQuery("#renglon_"+x).val().length==19){
                cambiaRenglon(x);            
            }
            var KeyID = event.keyCode;
            if(KeyID == 8 && jQuery("#renglon_"+x).val().length==0 && x>5){
                cambiaRenglonMenos(x);
            }
        }
    
    function cambiaRenglon(x){
        x = x + 1;
        var lineasActivas = jQuery("#renglones").val();
        if(x>lineasActivas){
            jQuery("#renglones").val(x);
            habilitaCampos(x);
        }
        jQuery("#renglon_"+x).focus();
    }
    
    function cambiaRenglonMenos(x){
        var x = x - 1;
        var lineasActivas = jQuery("#renglones").val();
        if(x<lineasActivas){
            jQuery("#renglones").val(x);
            habilitaCampos(x);
        }
        jQuery("#renglon_"+x).focus();
    }


    jQuery(document).ready(function(){
        //jQuery("#payment_terms").hide(); 
        jQuery("#submit").click(function(){
           if(jQuery("#place-ad-category").val() == 0){
               alert("Debe seleccionar una categoría");
               jQuery(document).scrollTop( jQuery("#place-ad-category").offset().top );
               jQuery("#place-ad-category").focus();
               return false;
           } 
        });
        
        jQuery("#auto_calculate").show();
        jQuery("#agregaRenglon").click(function(){
            agregarRenglon();
        });
        jQuery("#eliminaRenglon").click(function(){
           eliminarRenglon();
        });
        jQuery("#fondo_color").click(function(){
            if(this.checked != true){
                jQuery("#fondo_negro").attr("disabled","disabled");
            }else{
                jQuery("#fondo_negro").removeAttr("disabled");
            }
            calculaCosto();
        });
        jQuery("#fondo_negro").change(function(){
            var color = jQuery("#fondo_negro").val();
            jQuery("#pre-fondo").css("background-color",color);
			if(color == "black"){
				jQuery("#pre-fondo").css("color","#FFFFFF");
			}else{
				jQuery("#pre-fondo").css("color","#000000");
			}
            if(!jQuery("#marco").attr("checked")){
                jQuery("#pre-fondo").removeClass("marco");
//				jQuery("#pre-border").css("background-color",color);
            }else{
                jQuery("#pre-fondo").addClass("marco");
//                jQuery("#pre-border").css("background-color","black");
            }
        });
        
        jQuery(".renglones").keydown(function(){
            actualizaPreview();
        });
        
        jQuery("#marco").change(function(){
           if(this.checked == true){
                jQuery("#pre-fondo").addClass("marco");
                //jQuery("#pre-border").css("background-color","black");
           } else {
                //var color = jQuery("#fondo_negro").val();
                //jQuery("#pre-border").css("background-color",color);
				jQuery("#pre-fondo").removeClass("marco");
				
           }
           calculaCosto();
        });
        jQuery("#payment_terms").hide();
        //jQuery("#payment_terms").css("display","none");
        //jQuery("#payment_method").css("display","block");
    });
    function agregarRenglon(){
        var renglones = parseInt(jQuery("#renglones").val());
        if(renglones<50){
            jQuery("#renglones").val(renglones+1);
            habilitaCampos(renglones+1);
            var rn = renglones+1;
            jQuery("#pre_renglon_"+renglones).html(jQuery("#renglon_"+rn).val());
        }
    }
    function eliminarRenglon(){
        var renglones = parseInt(jQuery("#renglones").val());
        if(renglones>5){    
            jQuery("#renglones").val(renglones-1);
            habilitaCampos(renglones-1);
            jQuery("#pre_renglon_"+renglones).html("");
        }
    }
    function habilitaCampos(renglones){
        var x = 1;
        for(x=1;x<=50;x++){
            if(x<=renglones){
                jQuery("#div_renglon_"+x).show();
            }else{
                jQuery("#div_renglon_"+x).hide();
                
            }
        }
        calculaCosto();
        
    }
    function actualizaPreview(){
        var renglon;
        for(var x=1;x<=50;x++){
            renglon = jQuery("#renglon_"+x).val();
            jQuery("#pre_renglon_"+x).html(renglon);
        }
    }
    
    
    function calculaCosto(){
        var marco = (jQuery("#marco").is(":checked")) ? 1:0;
        var fondo = (jQuery("#fondo_color").is(":checked")) ? 1:0;
        var renglones = jQuery("#renglones").val();
        var nombre = renglones+"|"+fondo+"|"+marco;
        if(renglones<10){
            nombre += " ";
        }
        var nombreLinea;
        var precio;
        for(var x=1;x<=120;x++){
            nombreLinea = jQuery("#payment-term-ad-term-fee-"+x).attr("clave");
            if(nombreLinea == nombre){
                jQuery("#payment-term-ad-term-fee-"+x).attr("checked","checked");
                precio = jQuery("#payment-term-ad-term-fee-"+x).attr("precio");
                jQuery("#num_precio_final").html(precio);
            }
        }
    }
    habilitaCampos(5);
    
    
    
</script>