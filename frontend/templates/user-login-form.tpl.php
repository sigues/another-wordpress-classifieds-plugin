<div id="classiwrapper">
	<p><?php echo $message ?></p>

	<h2><?php _e("Login", "AWPCP") ?></h2>

	<form name="loginform" id="loginform" action="<?php echo $post_url ?>" method="post">
		<p>
			<label><?php _e("Username", "AWPCP") ?></label><br/>
			<input name="log" id="user_login" value="" class="textinput" size="20" tabindex="10" type="text" />
		</p>
		<p>
			<label><?php _e("Password", "AWPCP") ?></label><br/>
			<input name="pwd" id="user_pass" value="" class="textinput" size="20" tabindex="20" type="password" />
		</p>

	<?php ob_start();
			do_action('login_form');
			echo ob_get_contents(); 
		ob_end_clean(); ?>

		<p>
			<input name="rememberme" id="rememberme" value="forever" tabindex="90" type="checkbox" />
			<label><?php _e("Remember Me", "AWPCP") ?></label>
		</p>

		<p align="center">
			<input name="login-submit" id="wp-submit" value="<?php _e("Log In", "AWPCP") ?>" class="submitbutton" tabindex="100" type="submit" />
			<input name="redirect_to" value="<?php echo $redirect_to ?>" type="hidden" />
			<input name="testcookie" value="1" type="hidden" />
		</p>
	</form>

	<p>
		<a href="<?php echo $registration_url ?>" title="Register">
			<b><?php _e("Register", "AWPCP") ?></b>
		</a>
	</p>
</div>