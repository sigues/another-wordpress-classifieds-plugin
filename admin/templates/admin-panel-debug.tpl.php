<?php if (!$download): ?>
	<?php $page_id = 'awpcp-admin-debug' ?>
	<?php $page_title = __('AWPCP Debug', 'AWPCP') ?>

	<?php include(AWPCP_DIR . 'admin/templates/admin-panel-header.tpl.php') ?>
<?php endif ?>

		<?php $msg = _x('This information can help AWPCP Developers to debug possible problems. If you are submitting a bug report please <strong><a href="%s">Download the Debug Information</a></strong> and attach it to your bug report or take a minute to copy the information below to <a href="http://fpaste.org" target="_blank">http://fpaste.org</a> and provide the resulting URL in your report.', 'debug page', 'AWPCP') ?>
		<p><?php echo sprintf($msg, add_query_arg('download', 'debug-info', awpcp_current_url())) ?></p>

		<?php $title_pages = _x('AWPCP Pages', 'debug page', 'AWPCP') ?>
		<?php $title_php_info = _x('PHP Info', 'debug page', 'AWPCP') ?>
		<?php $title_settings = _x('AWPCP Settings', 'debug page', 'AWPCP') ?>
		<?php $title_rules = _x('Rewrite Rules', 'debug page', 'AWPCP') ?>

		<ul>
			<li><a href="#awpcp-debug-awpcp-pages"><?php echo $title_pages ?></a></li>
			<li><a href="#awpcp-debug-php-info"><?php echo $title_php_info ?></a></li>
			<li><a href="#awpcp-debug-awpcp-settings"><?php echo $title_settings ?></a></li>
			<li><a href="#awpcp-debug-rewrite-rules"><?php echo $title_rules ?></a></li>
		</ul>

		<div class="metabox-holder">

		<div id="awpcp-debug-awpcp-pages" class="apostboxes">
		    <h3 class="hndle1"><span><?php echo $title_pages ?></span></h3>
		    <div class="inside">
				<table>
					<thead>
						<tr>
							<th><?php _e('Page ID', 'AWPCP') ?></th>
							<th><?php _e('Title', 'AWPCP') ?></th>
							<th><?php _e('Reference', 'AWPCP') ?></th>
							<th><?php _e('Stored ID', 'AWPCP') ?></th>
						</tr>
					</thead>
					<tbody>
				<?php foreach($pages as $page): ?>
						<tr>
							<td class="align-center"><?php echo $page->post ?></td>
							<td><?php echo $page->title ?></td>
							<td class="align-center"><?php echo $page->ref ?></td>
							<td class="align-center"><?php echo $page->id ?></td>
						</tr>
				<?php endforeach ?> 
					</tbody>
				</table>
		    </div>
	    </div>

		<div id="awpcp-debug-awpcp-settings" class="apostboxes">
		    <h3 class="hndle1"><span><?php echo $title_settings ?></span></h3>
		    <div class="inside">
		    	<table>
					<thead>
						<tr>
							<th><?php _e('Option Name', 'AWPCP') ?></th>
							<th><?php _e('Option Value', 'AWPCP') ?></th>
						</tr>
					</thead>
					<tbody>
				<?php foreach($options as $name => $value): ?>
				<?php if ($debug_info->blacklisted($name)) continue ?>
				<?php $value = $debug_info->sanitize($name, $value) ?>
						<tr>
							<th scope="row"><?php echo $name ?></th>
							<td><?php echo esc_html($value) ?></td>
						</tr>
				<?php endforeach ?> 
					</tbody>
				</table>
		    </div>
	    </div>

		<div id="awpcp-debug-rewrite-rules" class="apostboxes">
		    <h3 class="hndle1"><span><?php echo $title_rules ?></span></h3>
		    <div class="inside">
				<table>
					<thead>
						<tr>
							<th><?php _e('Pattern', 'AWPCP') ?></th>
							<th><?php _e('Replacement', 'AWPCP') ?></th>
						</tr>
					</thead>
					<tbody>
				<?php foreach($rules as $pattern => $rule): ?>
						<tr>
							<td><?php echo $pattern ?></td>
							<td><?php echo $rule ?></td>
						</tr>
				<?php endforeach ?> 
					</tbody>
				</table>
		    </div>
	    </div>

		<div id="awpcp-debug-php-info" class="apostboxes">
		    <h3 class="hndle1"><span><?php echo $title_php_info ?></span></h3>
		    <div class="inside">
				<table>
					<tbody>
						<tr>
							<th scope="row"><?php _ex('PHP Version', 'debug page', 'AWPCP') ?></th>
							<td scope="row"><?php echo phpversion() ?></td>
						</tr>
						<tr>
							<th scope="row"><?php _e('cURL', 'debug page', 'AWPCP') ?></th>
							<td><?php echo in_array('curl', get_loaded_extensions()) ? __('Installed') : __('Not Installed') ?></td>
						</tr>
						<tr>
							<th scope="row"><?php _e("cURL's alternate CA info (cacert.pem)", 'debug page', 'AWPCP') ?></th>
							<td><?php echo file_exists(AWPCP_DIR . 'cacert.pem') ? 'Exists' : 'Missing' ?></td>
						</tr>
						<tr>
							<th scope="row"><?php _e('PayPal Connection', 'debug page', 'AWPCP') ?></th>
							<?php $response = awpcp_paypal_verify_received_data(array(), $errors) ?>
							<?php if ($response === 'INVALID'): ?>
							<td><?php _ex('Working', 'debug page', 'AWPCP')	?></td>
							<?php else: ?>
							<td>
								<?php _ex('Not Working', 'debug page', 'AWPCP') ?><br/>
								<?php foreach ($errors as $error): ?>
								<?php echo $error ?><br/>
								<?php endforeach ?>
							</td>
							<?php endif ?>
						</tr>
					</tbody>
				</table>
		    </div>
	    </div>

	    </div>

<?php if (!$download): ?>
		</div><!-- end of .awpcp-main-content -->
	</div><!-- end of .page-content -->
</div><!-- end of #page_id -->
<?php endif ?>