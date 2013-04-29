<?php 

class AWPCP_Show_Ad_Page {
	
	public function AWPCP_Show_Ad_Page() {
		add_action('init', array($this, 'init'));
		add_filter('awpcp-ad-details', array($this, 'oembed'));
	}

	public function init() {
		$regex = '#http://my\.brainshark\.com/([^\s]+)-(\d+)#i';
		wp_embed_register_handler('brainshark', $regex, array($this, 'oembed_handler_brainshark'));
	}

	/**
	 * Copied from Google Video handler in wp-includes/media.php
	 */
	public function oembed_handler_brainshark($matches, $attr, $url, $rawattr) {
		// If the user supplied a fixed width AND height, use it
		if (!empty($rawattr['width']) && !empty($rawattr['height'])) {
			$width  = (int) $rawattr['width'];
			$height = (int) $rawattr['height'];
		} else {
			list($width, $height) = wp_expand_dimensions(440, 366, $attr['width'], $attr['height']);
		}

		$pi = $matches[2];

		$html = '<object width="' . $width . '" height="' . $height . '" id="bsplayer94201" name="bsplayer94201" data="http://www.brainshark.com/brainshark/viewer/getplayer.ashx" type="application/x-shockwave-flash"><param name="movie" value="http://www.brainshark.com/brainshark/viewer/getplayer.ashx" /><param name="allowFullScreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="flashvars" value="pi='. $pi . '&dm=5&pause=1" /><a href="http://www.brainshark.com/brainshark/viewer/fallback.ashx?pi='. $pi . '"><video width="' . $width . '" height="' . $height . '" controls="true" poster="http://www.brainshark.com/brainshark/brainshark.net/common/getimage.ashx?pi='. $pi . '&w=' . $width . '&h=' . $height . '&sln=1"><source src="http://www.brainshark.com/brainshark/brainshark.net/apppresentation/getmovie.aspx?pi='. $pi . '&fmt=2" /><img src="http://www.brainshark.com/brainshark/brainshark.net/apppresentation/splash.aspx?pi='. $pi . '" width="' . $width . '" height="' . $height . '" border="0" /></video></a></object>';

		return apply_filters('embed_brainshark', $html, $matches, $attr, $url, $rawattr );
	}

	/**
	 * Acts on awpcp-ad-details filter to add oEmbed support
	 */
	public function oembed($content) {
		global $wp_embed;

		$usecache = $wp_embed->usecache;
		$wp_embed->usecache = false;
		$content = $wp_embed->run_shortcode($content);
		$content = $wp_embed->autoembed($content);
		$wp_embed->usecache = $usecache;

		return $content;
	}
}