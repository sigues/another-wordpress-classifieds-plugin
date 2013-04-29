<?php $page_id = 'awpcp-admin-uninstall' ?>
<?php $page_title = __('AWPCP Classifieds Management System Uninstall Plugin', 'AWPCP') ?>

<?php include(AWPCP_DIR . 'admin/templates/admin-panel-header.tpl.php') ?>

			<?php if (!empty($message)) { echo $message; } ?>

			<?php if ($action == 'confirm'): ?>

			<div style="padding:20px 0;">
				<?php _e("Thank you for using AWPCP. You have arrived at this page by clicking the Uninstall link. If you are certain you wish to uninstall the plugin, please click the link below to proceed. Please note that all your data related to the plugin, your ads, images and everything else created by the plugin will be destroyed","AWPCP"); ?>
				<p><b><?php _e("Important Information","AWPCP"); ?></b></p>
				<blockquote>
					<p>1. <?php _e("If you plan to use the data created by the plugin please export the data from your mysql database before clicking the uninstall link","AWPCP"); ?></p>
					<p>2. <?php _e("If you want to keep your user uploaded images, please download $dirname to your local drive for later use or rename the folder to something else so the uninstaller can bypass it","AWPCP"); ?></p>
				</blockquote>:

				<?php $href = add_query_arg(array('action' => 'uninstall'), $url) ?>
				<a href="<?php echo $href ?>"><?php _e("Proceed with Uninstalling Another Wordpress Classifieds Plugin","AWPCP") ?></a>
			</div>

			<?php elseif ($action == 'uninstall'): ?>

	        <div style=\"padding:50px;font-weight:bold;\">
	        	<h3><?php _e("Almost done...","AWPCP") ?></h3>

	        	<p><?php _e("One More Step","AWPCP") ?></p>

				<?php $href = admin_url('plugins.php?deactivate=true') ?>
	        	<a href="<?php echo $href ?>"><?php _e("Please click here to complete the uninstallation process","AWPCP"); ?></a>
	        </div>

	        <?php endif ?>
		</div><!-- end of .awpcp-main-content -->
	</div><!-- end of .page-content -->
</div><!-- end of #page_id -->