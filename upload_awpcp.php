<?php

if (!function_exists('imagecreatefrompng')) {
	$message="<div style=\"background-color: #FF99CC;\" id=\"message\" class=\"updated fade\">";
	$message.=__("AWPCP requires the graphics processing library GD and it is not installed.  Contact your web host to fix this.","AWPCP");
	$message.="</div>";
	echo $message;
	die;
}


/**
 * @param $file A $_FILES item
 */
function awpcp_upload_image_file($directory, $filename, $tmpname, $min_size, $max_size, $min_width, $min_height, $uploaded=true) {
	$filename = sanitize_file_name($filename);
	$newname = wp_unique_filename($directory, $filename);
	$newpath = trailingslashit($directory) . $newname;
	$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

	$imginfo = getimagesize($tmpname);
	$size = filesize($tmpname);

	$allowed_extensions = array('gif', 'jpg', 'jpeg', 'png');

	if (empty($filename)) {
		return __('No file was selected.', 'AWPCP');
	}

	if ($uploaded && !is_uploaded_file($tmpname)) {
		return __('Unknown error encountered while uploading the image.', 'AWPCP');
	}

	if (empty($size) || $size <= 0) {
		$message = "There was an error trying to find out the file size of the image %s.";
		return __(sprintf($message, $filename), 'AWPCP');
	}

	if (!(in_array($ext, $allowed_extensions))) {
		return __('The file has an invalid extension and was rejected.', 'AWPCP');

	} elseif ($size < $min_size) {
		$message = __('The size of %1$s was too small. The file was not uploaded. File size must be greater than %2$d bytes.', 'AWPCP');
		return sprintf($message, $filename, $min_size);

	} elseif ($size > $max_size) {
		$message = __('The file %s was larger than the maximum allowed file size of %s bytes. The file was not uploaded.', 'AWPCP');
		return sprintf($message, $filename, $max_size);

	} elseif (!isset($imginfo[0]) && !isset($imginfo[1])) {
		return __('The file does not appear to be a valid image file.', 'AWPCP');

	} elseif ($imginfo[0] < $min_height) {
		$message = __('The image did not meet the minimum width of %s pixels. The file was not uploaded.', 'AWPCP');
		return sprintf($message, $min_width);

	} elseif ($imginfo[1] < $min_height) {
		$message = __('The image did not meet the minimum height of %s pixels. The file was not uploaded.', 'AWPCP');
		return sprintf($message, $min_width);
	}

	if ($uploaded && !@move_uploaded_file($tmpname, $newpath)) {
		$message = __('The file % could not be moved to the destination directory.', 'AWPCP');
		return sprintf($message, $filename);

	} else if (!$uploaded && !@copy($tmpname, $newpath)) {
		$message = __('The file %s could not be moved to the destination directory.', 'AWPCP');
		return sprintf($message, $filename);
	}

	if (!awpcp_create_image_versions($newname, $directory)) {
		$message = __('Could not create resized versions of image %s.', 'AWPCP');
		# TODO: unlink resized version, thumbnail and primary image
		@unlink($newpath);
		return sprintf($message, $filename);
	}

	@chmod($newpath, 0644);

	return array('original' => $filename, 'filename' => $newname);
}


/**
 * Used in the admin panels to add images to existing ads
 */
function admin_handleimagesupload($adid) {
	global $wpdb, $wpcontentdir, $awpcp_plugin_path;

	list($images_dir, $thumbs_dir) = awpcp_setup_uploads_dir();
	list($min_width, $min_height, $min_size, $max_size) = awpcp_get_image_constraints();

	$ad = AWPCP_Ad::find_by_id($adid);
	if (!is_null($ad)) {

		list($images_allowed, $images_uploaded, $images_left) = awpcp_get_ad_images_information($adid);

		if ($images_left > 0) {
			$filename = awpcp_array_data('name', '', $_FILES['awpcp_add_file']);
			$tmpname = awpcp_array_data('tmp_name', '', $_FILES['awpcp_add_file']);
			$result = awpcp_upload_image_file($images_dir, $filename, $tmpname, 
											  $min_size, $max_size, $min_width, $min_height);
		} else {
			$message = __('No more images can be added to this Ad. The Ad already have %d of %d images allowed.', 'AWPCP');
			$result = sprintf($message, $images_uploaded, $images_allowed);
		}
	} else {
		$result = __("The Ad doesn't exists. All uploaded files were rejected.", 'AWPCP');
	}

	if (is_array($result) && isset($result['filename'])) {
		// TODO: consider images approve settings
		$sql = 'insert into ' . AWPCP_TABLE_ADPHOTOS . " set image_name = '%s', ad_id = '$adid', disabled = 0";
		$sql = $wpdb->prepare($sql, $result['filename']);
		$result = $wpdb->query($sql) ;
	} else {
		return '<div class="error"><p>' . $result . '</p></div>';
	}

	return $result !== false ? true : false;
}


/**
 * Resize images if they're too wide or too tall based on admin's Image Settings.
 * Requires both max width and max height to be set otherwise no resizing 
 * takes place. If the image exceeds either max width or max height then the 
 * image is resized proportionally.
 */
function awpcp_resizer($filename, $dir) {
	$maxwidth = get_awpcp_option('imgmaxwidth');
	$maxheight = get_awpcp_option('imgmaxheight');

	if ('' == trim($maxheight) || '' == trim ($maxwidth)) {
		return false;
	}

	$parts = pathinfo( $filename );

	if( 'jpg' == $parts['extension'] || 'jpeg' == $parts['extension'] ) {
		$src = imagecreatefromjpeg( $dir . $filename );
	} else if ( 'png' == $parts['extension'] ) {
		$src = imagecreatefrompng( $dir . $filename );
	} else {
		$src = imagecreatefromgif( $dir . $filename );
	}

	list($width, $height) = getimagesize($dir . $filename);

	if ($width < $maxwidth && $height < $maxheight) {
		return true;
	}
	 
	$newwidth = '';
	$newheight = '';

	$aspect_ratio = (float) $height / $width;

	$newheight = $maxheight;
	$newwidth = round($newheight / $aspect_ratio);

	if ($newwidth > $maxwidth) {
		$newwidth = $maxwidth;
		$newheight = round( $newwidth * $aspect_ratio );
	}

	$tmp = imagecreatetruecolor( $newwidth, $newheight );

	imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

	$newname = $dir . $filename;

	switch ($parts['extension']) {
		case 'gif': 
			@imagegif($tmp, $newname);
			break;
		case 'png': 
			@imagepng($tmp, $newname, 0);
			break;
		case 'jpg': 
		case 'jpeg':
			@imagejpeg($tmp, $newname, 100);
			break;
	}

	imagedestroy($src);
	imagedestroy($tmp);

	return true;
}


function awpcp_setup_uploads_dir() {
	global $wpcontentdir;

	$upload_dir_name = get_awpcp_option('uploadfoldername', 'uploads');
	$upload_dir = $wpcontentdir . '/' . $upload_dir_name . '/';

	// Required to set permission on main upload directory
	require_once(AWPCP_DIR . 'fileop.class.php');

	$fileop = new fileop();
	$owner = fileowner($wpcontentdir);

	if (!is_dir($upload_dir) && is_writable($wpcontentdir)) {
		umask(0);
		mkdir($upload_dir, 0777);
		chown($upload_dir, $owner);
	}
	$fileop->set_permission($upload_dir,0777);
	
	$images_dir = $upload_dir . 'awpcp/';
	$thumbs_dir = $upload_dir . 'awpcp/thumbs/';

	if (!is_dir($images_dir) && is_writable($upload_dir)) {
		umask(0);
		@mkdir($images_dir, 0777);
		@chown($images_dir, $owner);
	}

	if (!is_dir($thumbs_dir) && is_writable($upload_dir)) {
		umask(0);
		@mkdir($thumbs_dir, 0777);
		@chown($thumbs_dir, $owner);
	}

	$fileop->set_permission($images_dir, 0777);
	$fileop->set_permission($thumbs_dir, 0777);

	return array($images_dir, $thumbs_dir);
}


function awpcp_get_image_constraints() {
	$min_width = get_awpcp_option('imgminwidth');
	$min_height = get_awpcp_option('imgminheight');
	$min_size = get_awpcp_option('minimagesize');
	$max_size = get_awpcp_option('maximagesize');
	return array($min_width, $min_height, $min_size, $max_size);
}


function awpcp_handle_uploaded_images($ad_id, &$form_errors=array()) {
	global $wpdb;

	list($images_dir, $thumbs_dir) = awpcp_setup_uploads_dir();
	list($images_allowed, $images_uploaded, $images_left) = awpcp_get_ad_images_information($ad_id);
	list($min_width, $min_height, $min_size, $max_size) = awpcp_get_image_constraints();

	$primary = awpcp_post_param('primary-image');
	$disabled = get_awpcp_option('imagesapprove') == 1 ? 1 : 0;

	if ($images_left <= 0) {
		$form_errors['form'] = __("You can't add more images to this Ad. There are not remaining images slots.", 'AWPCP');
	}

	$count = 0;
	for ($i=0; $i < $images_left; $i++) {
		$field = 'AWPCPfileToUpload' . $i;
		$file = $_FILES[$field];

		if ($file['error'] !== 0) {
			continue;
		}

		$filename = sanitize_file_name($file['name']);
		$tmpname = awpcp_array_data('tmp_name', '', $file);

		$uploaded = awpcp_upload_image_file($images_dir, $filename, $tmpname, $min_size, $max_size, $min_width, $min_height);

		if (is_array($uploaded) && isset($uploaded['filename'])) {
			$sql = 'INSERT INTO ' . AWPCP_TABLE_ADPHOTOS . " SET image_name = '%s', ad_id = %d, disabled = %d";
			$sql = $wpdb->prepare($sql, $uploaded['filename'], $ad_id, $disabled);
			$result = $wpdb->query($sql);

			if ($result !== false) {
				if ($primary == "field-$i") {
					awpcp_set_ad_primary_image($ad_id, $wpdb->insert_id);
				}
				$count += 1;
			} else {
				$msg = __("Could not save the information to the database for: %s", 'AWPCP');
				$form_errors[$field] = sprintf($msg, $uploaded['original']);
			}
		} else {
			$form_errors[$field] = $uploaded;
		}
	}

	if (intval($primary) > 0) {
		awpcp_set_ad_primary_image($ad_id, intval($primary));
	}

	if (empty($form_errors) && $count <= 0) {
		$form_errors['form'] = __('No image files were uploaded');
	}

	$form_errors = array_filter($form_errors);

	if (!empty($form_errors)) {
		return false;
	}

	return true;
}


function handleimagesupload($adid, $adtermid, $nextstep, $adpaymethod, $adaction, $adkey) {
	return awpcp_handle_uploaded_images($ad_id);

	// if(isset($_REQUEST['adid']) && !empty($_REQUEST['adid'])){
	// 	$adid=$_REQUEST['adid'];
	// } else {
	// 	$adid='';
	// }
	// if(isset($_REQUEST['adtermid']) && !empty($_REQUEST['adtermid'])){
	// 	$adtermid=$_REQUEST['adtermid'];
	// } else {
	// 	$adtermid='';
	// }
	// if(isset($_REQUEST['nextstep']) && !empty($_REQUEST['nextstep'])){
	// 	$nextstep=$_REQUEST['nextstep'];
	// }
	// if(isset($_REQUEST['adpaymethod']) && !empty($_REQUEST['adpaymethod'])){
	// 	$adpaymethod=$_REQUEST['adpaymethod'];
	// }
	// if(isset($_REQUEST['adaction']) && !empty($_REQUEST['adaction'])){
	// 	$adaction=$_REQUEST['adaction'];
	// }
	// if(isset($_REQUEST['adkey']) && !empty($_REQUEST['adkey'])){
	// 	$adkey=$_REQUEST['adkey'];
	// }

	// $awpcp_main_folder = $themainawpcpuploaddir;
	// $awpcp_thumb_folder = $themainawpcpuploadthumbsdir;
	// $awpcp_allowedextensions = array(".jpg", ".gif", ".png");
	// $twidth=get_awpcp_option('imgthumbwidth');
	// // if(get_awpcp_option('freepay') == 1)
	// // {
	// // 	if($adtermid != -1)
	// // 	{
	// // 		$numimgsallowed=get_numimgsallowed($adtermid);
	// // 	}
	// // 	else
	// // 	{
	// // 		$numimgsallowed=get_awpcp_option('imagesallowedfree');
	// // 	}
	// // }
	// // else
	// // {
	// // 	$numimgsallowed=get_awpcp_option('imagesallowedfree');
	// // }
	// $numimgsallowed = awpcp_get_ad_number_allowed_images($adid, $adtermid);

	// if(adidexists($adid))
	// {
	// 	$totalimagesuploaded=get_total_imagesuploaded($adid);
	// }

	// $numimgsleft = ($numimgsallowed - $totalimagesuploaded);

	// $errornofiles=true;
	// $awpcpuerror=array();

	// for ($i=0;$i<$numimgsleft;$i++)
	// {
	// 	$theuploadedfilename = $_FILES['AWPCPfileToUpload'. $i]['name'];

	// 	if(!empty($theuploadedfilename))
	// 	{
	// 		$errornofiles=false;
	// 	}
	// }
	// if ($errornofiles)
	// {
	// 	$awpcpuerror[]="<p class=\"uploaderror\">";
	// 	$awpcpuerror[].=__("No file was selected","AWPCP");
	// 	$awpcpuerror[].="</p>";
	// 	$awpcpuploadformshow=display_awpcp_image_upload_form($adid,$adtermid,$adkey,$adaction,$nextstep,$adpaymethod,$awpcpuerror);
	// 	$output .= $awpcpuploadformshow;
	// }
	// else
	// {
	// 	$output .= awpcpuploadimages($adid,$adtermid,$adkey,$imgmaxsize,$imgminsize,$twidth,$nextstep,$adpaymethod,$adaction,$awpcp_main_folder,'AWPCPfileToUpload');
	// }
	// return $output;
}


// function awpcpuploadimages($adid,$adtermid,$adkey,$imgmaxsize,$imgminsize,$twidth,$nextstep,$adpaymethod,$adaction,$destdir,$actual_field_name,$required=false) 
// {
// 	$output = '';
// 	global $wpdb;
// 	$tbl_ad_photos = $wpdb->prefix . "awpcp_adphotos";
// 	$awpcpupdatinserted=false;
// 	$awpcpuploaderror=false;
// 	$awpcpfilesuploaded=true;
// 	$awpcpuerror=array();

// 	if(adidexists($adid)) {
// 		$totalimagesuploaded=get_total_imagesuploaded($adid);
// 	}

// 	$numimgsallowed = awpcp_get_ad_number_allowed_images($adid, $adtermid);

// 	$numimgsleft = ($numimgsallowed - $totalimagesuploaded);
// 	//debug("num imgs left: $numimgsleft");
// 	for ($i = 0; $i < $numimgsleft; $i++) {
// 		//debug("num imgs left: $numimgsleft, i: $i");
// 		$filename = addslashes($_FILES[$actual_field_name.$i]['name']);
// 		$ext = strtolower(substr(strrchr($_FILES[$actual_field_name.$i]['name'],"."),1));
// 		$ext_array = array('gif','jpg','jpeg','png');

// 		if (isset($_FILES[$actual_field_name.$i]['tmp_name']) && 
// 			is_uploaded_file($_FILES[$actual_field_name.$i]['tmp_name'])) {
// 			$imginfo = getimagesize($_FILES[$actual_field_name.$i]['tmp_name']);
// 			$imgfilesizeval=filesize($_FILES[$actual_field_name.$i]['tmp_name']);

// 			$desired_filename = mktime();
// 			$desired_filename .= "_$i";

// 			if(isset($filename) && !empty($filename)) {
// 				if (!(in_array($ext, $ext_array))) {
// 					$awpcpuploaderror=true;
// 					$awpcpuerror[].="<p class=\"uploaderror\">[$filename]";
// 					$awpcpuerror[].=__(" had an invalid file extension and was not uploaded","AWPCP");
// 					$awpcpuerror[].="</p>";
// 				}
// 				elseif(filesize($_FILES[$actual_field_name.$i]['tmp_name']) <= $imgminsize)
// 				{
// 					$awpcpuploaderror=true;
// 					$awpcpuerror[].="<p class=\"uploaderror\">";
// 					$awpcpuerror[].=sprintf(__("The size of %1$s was too small. The file was not uploaded. File size must be greater than %2$d bytes", "AWPCP"), $filename, $imgminsize);
// 					$awpcpuerror[].="</p>";
// 				}
// 				elseif($imginfo[0]< $twidth)
// 				{
// 					// width is too short
// 					$awpcpuploaderror=true;
// 					$awpcpuerror[].="<p class=\"uploaderror\">[$filename]";
// 					$awpcpuerror[].=sprintf(__(" did not meet the minimum width of [%s] pixels. The file was not uploaded", "AWPCP"), $twidth);
// 					$awpcpuerror[].="</p>";
// 				}
// 				elseif ($imginfo[1]< $twidth)
// 				{
// 					// height is too short
// 					$awpcpuploaderror=true;
// 					$awpcpuerror[].="<p class=\"uploaderror\">[$filename]";
// 					$awpcpuerror[].=sprintf(__(" did not meet the minimum height of [%s] pixels. The file was not uploaded", "AWPCP"), $twidth);
// 					$awpcpuerror[].="</p>";
// 				}
// 				elseif(!isset($imginfo[0]) && !isset($imginfo[1]))
// 				{
// 					$awpcpuploaderror=true;
// 					$awpcpuerror[].="<p class=\"uploaderror\">[$filename]";
// 					$awpcpuerror[].=__(" does not appear to be a valid image file","AWPCP");
// 					$awpcpuerror[].="</p>";
// 				}
// 				elseif( $imgfilesizeval > $imgmaxsize )
// 				{
// 					$awpcpuploaderror=true;
// 					$awpcpuerror[].="<p class=\"uploaderror\">[$filename]";
// 					$awpcpuerror[].=sprintf(__(" was larger than the maximum allowed file size of [%s] bytes. The file was not uploaded", "AWPCP"), $imgmaxsize);
// 					$awpcpuerror[].="</p>";
// 				} elseif(!empty($desired_filename)) {
// 					//debug('uploading...');
// 					$filename="$desired_filename.$ext";

// 					if (!move_uploaded_file($_FILES[$actual_field_name.$i]['tmp_name'],$destdir.'/'.$filename)) {
// 						$orfilename=$filename;
// 						$filename='';
// 						$awpcpuploaderror=true;
// 						$awpcpuerror[].="<p class=\"uploaderror\">[$orfilename]";
// 						$awpcpuerror[].=__(" could not be moved to the destination directory","AWPCP");
// 						$awpcpuerror[].="</p>";
// 					} else {
// 						awpcp_resizer($filename, $destdir); 

// 						if(!awpcpcreatethumb($filename,$destdir,$twidth)) {
// 							$awpcpuploaderror=true;
// 							$awpcpuerror[].="<p class=\"uploaderror\">";
// 							$awpcpuerror[].=sprintf(__("Could not create thumbnail image of [ %s ]", "AWPCP"), $filename);
// 							$awpcpuerror[].="</p>";
// 						}

// 						@chmod($destdir.'/'.$filename,0644);

// 						$ctiu = get_total_imagesuploaded($adid);

// 						if(get_awpcp_option('imagesapprove') == 1) {
// 							$disabled=1;
// 						} else {
// 							$disabled=0;
// 						}

// 						if($ctiu < $numimgsallowed) {
// 							$query="INSERT INTO ".$tbl_ad_photos." SET image_name='$filename',ad_id='$adid',disabled='$disabled'";
// 							if (!($res=@mysql_query($query))) {
// 								sqlerrorhandler("(".mysql_errno().") ".mysql_error(), $query, $_SERVER['PHP_SELF'], __LINE__);
// 							}
// 						}

// 						$awpcpupdatinserted=true;

// 						if(!($awpcpupdatinserted)) {
// 							$awpcpuploaderror=true;
// 							$awpcpuerror[].="<p class=\"uploaderror\">";
// 							$awpcpuerror[].=sprintf(__("Could not save the information to the database for [ %s ]", "AWPCP"), $filename);
// 							$awpcpuerror[].="</p>";
// 						}
// 					}
// 				}
// 			} else {
// 				$awpcpuploaderror=true;
// 				$awpcpuerror[].="<p class=\"uploaderror\">";
// 				$awpcpuerror[].=__("Unknown error encountered uploading image","AWPCP");
// 				$awpcpuerror[].="</p>";
// 			}
// 		}

// 		//debug($awpcpuerror);
// 	} // Close for $i...

// 	if ($awpcpuploaderror)
// 	{
// 		$awpcpuploadformshow=display_awpcp_image_upload_form($adid,$adtermid,$adkey,$adaction,$nextstep,$adpaymethod,$awpcpuerror);
// 		$output .= $awpcpuploadformshow;
// 	}
// 	elseif(!($awpcpfilesuploaded))
// 	{
// 		$awpcpuerror[]="<p class=\"uploaderror\">";
// 		$awpcpuerror[].=__("One or more images failed to be uploaded","AWPCP");
// 		$awpcpuerror[].="</p>";
// 		$awpcpuploadformshow=display_awpcp_image_upload_form($adid,$adtermid,$adkey,$adaction,$nextstep,$adpaymethod,$awpcpuerror);
// 		$output .= $awpcpuploadformshow;
// 	}
// 	else
// 	{
// 		if(($nextstep == 'finish') && ($adaction == 'editad')) {
// 			$awpcpadpostedmsg=__("Your ad has been submitted","AWPCP");

// 			if(get_awpcp_option('adapprove') == 1)
// 			{
// 				$awaitingapprovalmsg=get_awpcp_option('notice_awaiting_approval_ad');
// 				$awpcpadpostedmsg.="<p>";
// 				$awpcpadpostedmsg.=$awaitingapprovalmsg;
// 				$awpcpadpostedmsg.="</p>";
// 			}
// 			if(get_awpcp_option('imagesapprove') == 1)
// 			{
// 				$imagesawaitingapprovalmsg=__("If you have uploaded images your images will not show up until an admin has approved them.","AWPCP");
// 				$awpcpadpostedmsg.="<p>";
// 				$awpcpadpostedmsg.=$imagesawaitingapprovalmsg;
// 				$awpcpadpostedmsg.="</p>";
// 			}

// 			$awpcpshowadsample=1;
// 			$awpcpsubmissionresultmessage ='';
// 			$message='';
// 			$awpcpsubmissionresultmessage =ad_success_email($adid,$txn_id='',$adkey,$awpcpadpostedmsg,$gateway='');

// 			$output .= "<div id=\"classiwrapper\">";
// 			$output .= '<p class="ad_status_msg">';
// 			$output .= $awpcpsubmissionresultmessage;
// 			$output .= "</p>";
// 			$output .= awpcp_menu_items();
// 			if($awpcpshowadsample == 1)
// 			{
// 				$output .= '<h2 class="ad-posted">';
// 				$output .= __("You Ad is posted","AWPCP");
// 				$output .= "</h2>";
// 				$output .= showad($adid,$omitmenu=1);
// 			}
// 			$output .= "</div>";
// 		}

// 		elseif($nextstep == 'payment') {
// 			// Move to next step in process
// 			$output .= processadstep3($adid,$adtermid,$adkey,$adpaymethod);
// 		} else {
// 			$awpcpadpostedmsg=__("Your ad has been submitted","AWPCP");

// 			if(get_awpcp_option('adapprove') == 1)
// 			{
// 				$awaitingapprovalmsg=get_awpcp_option('notice_awaiting_approval_ad');
// 				$awpcpadpostedmsg.="<p>";
// 				$awpcpadpostedmsg.=$awaitingapprovalmsg;
// 				$awpcpadpostedmsg.="</p>";
// 			}
// 			if(get_awpcp_option('imagesapprove') == 1)
// 			{
// 				$imagesawaitingapprovalmsg=__("If you have uploaded images your images will not show up until an admin has approved them.","AWPCP");
// 				$awpcpadpostedmsg.="<p>";
// 				$awpcpadpostedmsg.=$imagesawaitingapprovalmsg;
// 				$awpcpadpostedmsg.="</p>";
// 			}

// 			$awpcpshowadsample=1;
// 			$awpcpsubmissionresultmessage ='';
// 			$message='';
// 			$awpcpsubmissionresultmessage = ad_success_email($adid,$txn_id='',$adkey,$awpcpadpostedmsg,$gateway='');

// 			$output .= "<div id=\"classiwrapper\">";
// 			$output .= awpcp_menu_items();
// 			$output .= '<p class="ad_status_msg">';
// 			$output .= $awpcpsubmissionresultmessage;
// 			$output .= "</p>";
// 			if($awpcpshowadsample == 1)
// 			{
// 				$output .= "<h2>";
// 				$output .= __("Your Ad is posted","AWPCP");
// 				$output .= "</h2>";
// 				$output .= showad($adid,$omitmenu=1,$preview=true,$send_email=false);
// 			}
// 			$output .= "</div>";
// 		}
// 	}

// 	return $output;
// }

/**
 * Create thumbnails and resize original image to match image size 
 * restrictions.
 */
function awpcp_create_image_versions($filename, $directory) {
// function awpcpcreatethumb($filename, $directory, $width, $height) {
	$directory = trailingslashit($directory);
	$thumbnails = $directory . 'thumbs/';

	$filepath = $directory . $filename;

	// create thumbnail
	$width = get_awpcp_option('imgthumbwidth');
	$height = get_awpcp_option('imgthumbheight');
	$crop = get_awpcp_option('crop-thumbnails');
	$thumbnail = awpcp_make_intermediate_size($filepath, $thumbnails, $width, $height, $crop);

	// create primary image thumbnail
	$width = get_awpcp_option('primary-image-thumbnail-width');
	$height = get_awpcp_option('primary-image-thumbnail-height');
	$crop = get_awpcp_option('crop-primary-image-thumbnails');
	$primary = awpcp_make_intermediate_size($filepath, $thumbnails, $width, $height, $crop, 'primary');

	// resize original image to match restrictions
	$width = get_awpcp_option('imgmaxwidth');
	$height = get_awpcp_option('imgmaxheight');
	$resized = awpcp_make_intermediate_size($filepath, $directory, $width, $height, false, 'large');

	return $resized && $thumbnail && $primary;
}


function awpcp_make_intermediate_size($file, $directory, $width, $height, $crop=false, $suffix='') {
	$info = pathinfo($file);
	$filename = preg_replace("/\.{$info['extension']}/", '', $info['basename']);
	$suffix = empty($suffix) ? '.' : "-$suffix.";

	$newpath = trailingslashit($directory) . $filename . $suffix . $info['extension'];

	$image = image_make_intermediate_size($file, $width, $height, $crop);

	if (!is_writable($directory)) {
		@chmod($directory, 0755);
		if (!is_writable($directory)) {
			@chmod($directory, 0777);
		}
	}

	if (is_array($image) && !empty($image)) {
		$tmppath = trailingslashit($info['dirname']) . $image['file'];
		$result = rename($tmppath, $newpath);
	} else {
		$result = copy($file, $newpath);
	}
	@chmod($newpath, 0644);

	return $result;
}


function awpcp_GD() {
	$myreturn=array();
	if (function_exists('gd_info')) {
		$myreturn=gd_info();
	} else {
		$myreturn=array('GD Version'=>'');
		ob_start();
		phpinfo(8);
		$info=ob_get_contents();
		ob_end_clean();
		foreach (explode("\n",$info) as $line) {
			if (strpos($line,'GD Version')!==false) {
				$myreturn['GD Version']=trim(str_replace('GD Version', '', strip_tags($line)));
			}
		}
	}
	return $myreturn;
}
