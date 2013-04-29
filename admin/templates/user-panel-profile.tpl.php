<div id="awpcp-user-panel-profile" class="wrap">

	<div class="page-content">
		<h2 class="awpcp-page-header"><?php _e('Default Profile Data', 'AWPCP') ?></h2>

		<div class="awpcp-main-content">
			<form action="<?php echo awpcp_current_url() ?>" method="post">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<label for="awpcp-profile-username"><?php _e('Username', 'AWPCP') ?></label>
							</th>
							<td>
								<input id="awpcp-profile-username" class="regular-text" type="text" name="awpcp-profile[username]" value="<?php echo $profile['username'] ?>" />
								<span class="description"><?php _e('If not empty will override your WordPress username.', 'AWPCP') ?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="awpcp-profile-email"><?php _e('Email Adress', 'AWPCP') ?></label>
							</th>
							<td>
								<input id="awpcp-profile-email" class="regular-text" type="text" name="awpcp-profile[email]" value="<?php echo $profile['email'] ?>" />
								<span class="description"><?php _e('If not empty will override your WordPress email address.', 'AWPCP') ?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="awpcp-profile-address"><?php _e('Address', 'AWPCP') ?></label>
							</th>
							<td>
								<input id="awpcp-profile-address" class="regular-text" type="text" name="awpcp-profile[address]" value="<?php echo $profile['address'] ?>" />
								<span class="description"></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="awpcp-profile-city"><?php _e('City', 'AWPCP') ?></label>
							</th>
							<td>
								<input id="awpcp-profile-city" class="regular-text" type="text" name="awpcp-profile[city]" value="<?php echo $profile['city'] ?>" />
								<span class="description"></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="awpcp-profile-state"><?php _e('State', 'AWPCP') ?></label>
							</th>
							<td>
								<input id="awpcp-profile-state" class="regular-text" type="text" name="awpcp-profile[state]" value="<?php echo $profile['state'] ?>" />
								<span class="description"></span>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" value="Save Changes" class="button-primary" id="submit" name="save">
				</p>
			</form>
		</div>
	</div>
</div>