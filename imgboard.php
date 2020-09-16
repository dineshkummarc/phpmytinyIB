<?php
/*
TinyIB
https://gitlab.com/tslocum/tinyib

MIT License

Copyright (c) 2020 Trevor Slocum <trevor@rocketnine.space>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/
use Gettext\Translator;
use Gettext\Translations;

error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();
setcookie(session_name(), session_id(), time() + 2592000);
ob_implicit_flush();
if (function_exists('ob_get_level')) {
	while (ob_get_level() > 0) {
		ob_end_flush();
	}
}

if (version_compare(phpversion(), '5.3.0', '<')) {
	if (get_magic_quotes_gpc()) {
		foreach ($_GET as $key => $val) {
			$_GET[$key] = stripslashes($val);
		}
		foreach ($_POST as $key => $val) {
			$_POST[$key] = stripslashes($val);
		}
	}
	if (get_magic_quotes_runtime()) {
		set_magic_quotes_runtime(0);
	}
}

function fancyDie($message) {
	die('<body text="#800000" bgcolor="#FFFFEE" align="center"><br><div style="display: inline-block; background-color: #F0E0D6;font-size: 1.25em;font-family: Tahoma, Geneva, sans-serif;padding: 7px;border: 1px solid #D9BFB7;border-left: none;border-top: none;">' . $message . '</div><br><br>- <a href="javascript:history.go(-1)">Click here to go back</a> -</body>');
}

if (!file_exists('settings.php')) {
	fancyDie('Please copy the file settings.default.php to settings.php');
}
require 'settings.php';

if (TINYIB_LOCALE == '') {
	function __($string) {
		return $string;
	}
} else {
	require 'inc/gettext/src/autoloader.php';
	$translations = Translations::fromPoFile('locale/' . TINYIB_LOCALE . '/tinyib.po');
	$translator = new Translator();
	$translator->loadTranslations($translations);
	$translator->register();
}

if (TINYIB_TRIPSEED == '' || TINYIB_ADMINPASS == '') {
	fancyDie(__('TINYIB_TRIPSEED and TINYIB_ADMINPASS must be configured.'));
}

if (TINYIB_CAPTCHA === 'recaptcha' && (TINYIB_RECAPTCHA_SITE == '' || TINYIB_RECAPTCHA_SECRET == '')) {
	fancyDie(__('TINYIB_RECAPTCHA_SITE and TINYIB_RECAPTCHA_SECRET  must be configured.'));
}

// Check directories are writable by the script
$writedirs = array("res", "src", "thumb");
if (TINYIB_DBMODE == 'flatfile') {
	$writedirs[] = "inc/flatfile";
}
foreach ($writedirs as $dir) {
	if (!is_writable($dir)) {
		fancyDie(sprintf(__("Directory '%s' can not be written to.  Please modify its permissions."), $dir));
	}
}

$includes = array("inc/defines.php", "inc/functions.php", "inc/html.php");
if (in_array(TINYIB_DBMODE, array('flatfile', 'mysql', 'mysqli', 'sqlite', 'sqlite3', 'pdo'))) {
	$includes[] = 'inc/database_' . TINYIB_DBMODE . '.php';
} else {
	fancyDie(__('Unknown database mode specified.'));
}

foreach ($includes as $include) {
	include $include;
}

if (TINYIB_TIMEZONE != '') {
	date_default_timezone_set(TINYIB_TIMEZONE);
}

$redirect = true;
// Check if the request is to make a post
if (!isset($_GET['delete']) && !isset($_GET['manage']) && (isset($_POST['name']) || isset($_POST['email']) || isset($_POST['subject']) || isset($_POST['message']) || isset($_POST['file']) || isset($_POST['embed']) || isset($_POST['password']))) {
	if (TINYIB_DBMIGRATE) {
		fancyDie(__('Posting is currently disabled.<br>Please try again in a few moments.'));
	}

	list($loggedin, $isadmin) = manageCheckLogIn();
	$rawpost = isRawPost();
	$rawposttext = '';
	if (!$loggedin) {
		checkCAPTCHA();
		checkBanned();
		checkMessageSize();
		checkFlood();
	}

	$post = newPost(setParent());
	$hide_fields = $post['parent'] == TINYIB_NEWTHREAD ? $tinyib_hidefieldsop : $tinyib_hidefields;

	if ($post['parent'] != TINYIB_NEWTHREAD && !$loggedin) {
		$parent = postByID($post['parent']);
		if (!isset($parent['locked'])) {
			fancyDie(__('Invalid parent thread ID supplied, unable to create post.'));
		} else if ($parent['locked'] == 1) {
			fancyDie(__('Replies are not allowed to locked threads.'));
		}
	}

	$post['ip'] = $_SERVER['REMOTE_ADDR'];
	if ($rawpost || !in_array('name', $hide_fields)) {
		list($post['name'], $post['tripcode']) = nameAndTripcode($_POST['name']);
		$post['name'] = cleanString(substr($post['name'], 0, 75));
	}
	if ($rawpost || !in_array('email', $hide_fields)) {
		$post['email'] = cleanString(str_replace('"', '&quot;', substr($_POST['email'], 0, 75)));
	}
	if ($rawpost || !in_array('subject', $hide_fields)) {
		$post['subject'] = cleanString(substr($_POST['subject'], 0, 75));
	}
	if ($rawpost || !in_array('message', $hide_fields)) {
		$post['message'] = $_POST['message'];
		if ($rawpost) {
			// Treat message as raw HTML
			$rawposttext = ($isadmin) ? ' <span style="color: ' . $tinyib_capcodes[0][1] . ' ;">## ' . $tinyib_capcodes[0][0] . '</span>' : ' <span style="color: ' . $tinyib_capcodes[1][1] . ';">## ' . $tinyib_capcodes[1][0] . '</span>';
		} else {
			if (TINYIB_WORDBREAK > 0) {
				$post['message'] = preg_replace('/([^\s]{' . TINYIB_WORDBREAK . '})(?=[^\s])/', '$1' . TINYIB_WORDBREAK_IDENTIFIER, $post['message']);
			}
			$post['message'] = str_replace("\n", '<br>', makeLinksClickable(colorQuote(postLink(cleanString(rtrim($post['message']))))));
			if (TINYIB_WORDBREAK > 0) {
				$post['message'] = finishWordBreak($post['message']);
			}
		}
	}
	if ($rawpost || !in_array('password', $hide_fields)) {
		$post['password'] = ($_POST['password'] != '') ? md5(md5($_POST['password'])) : '';
	}
	$post['nameblock'] = nameBlock($post['name'], $post['tripcode'], $post['email'], time(), $rawposttext);

	if (isset($_POST['embed']) && trim($_POST['embed']) != '' && ($rawpost || !in_array('embed', $hide_fields))) {
		if (isset($_FILES['file']) && $_FILES['file']['name'] != "") {
			fancyDie(__('Embedding a URL and uploading a file at the same time is not supported.'));
		}

		list($service, $embed) = getEmbed(trim($_POST['embed']));
		if (empty($embed) || !isset($embed['html']) || !isset($embed['title']) || !isset($embed['thumbnail_url'])) {
			fancyDie(sprintf(__('Invalid embed URL. Only %s URLs are supported.'), implode('/', array_keys($tinyib_embeds))));
		}

		$post['file_hex'] = $service;
		$temp_file = time() . substr(microtime(), 2, 3);
		$file_location = "thumb/" . $temp_file;
		file_put_contents($file_location, url_get_contents($embed['thumbnail_url']));

		$file_info = getimagesize($file_location);
		$file_mime = mime_content_type($file_location);
		$post['image_width'] = $file_info[0];
		$post['image_height'] = $file_info[1];

		if ($file_mime == "image/jpeg") {
			$post['thumb'] = $temp_file . '.jpg';
		} else if ($file_mime == "image/gif") {
			$post['thumb'] = $temp_file . '.gif';
		} else if ($file_mime == "image/png") {
			$post['thumb'] = $temp_file . '.png';
		} else {
			fancyDie(__('Error while processing audio/video.'));
		}
		$thumb_location = "thumb/" . $post['thumb'];

		list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);

		if (!createThumbnail($file_location, $thumb_location, $thumb_maxwidth, $thumb_maxheight)) {
			fancyDie(__('Could not create thumbnail.'));
		}

		addVideoOverlay($thumb_location);

		$thumb_info = getimagesize($thumb_location);
		$post['thumb_width'] = $thumb_info[0];
		$post['thumb_height'] = $thumb_info[1];

		$post['file_original'] = cleanString($embed['title']);
		$post['file'] = str_ireplace(array('src="https://', 'src="http://'), 'src="//', $embed['html']);
	} else if (isset($_FILES['file']) && ($rawpost || !in_array('file', $hide_fields))) {
		if ($_FILES['file']['name'] != "") {
			validateFileUpload();

			if (!is_file($_FILES['file']['tmp_name']) || !is_readable($_FILES['file']['tmp_name'])) {
				fancyDie(__('File transfer failure. Please retry the submission.'));
			}

			if ((TINYIB_MAXKB > 0) && (filesize($_FILES['file']['tmp_name']) > (TINYIB_MAXKB * 1024))) {
				fancyDie(sprintf(__('That file is larger than %s.'), TINYIB_MAXKBDESC));
			}

			$post['file_original'] = trim(htmlentities(substr($_FILES['file']['name'], 0, 50), ENT_QUOTES));
			$post['file_hex'] = md5_file($_FILES['file']['tmp_name']);
			$post['file_size'] = $_FILES['file']['size'];
			$post['file_size_formatted'] = convertBytes($post['file_size']);

			checkDuplicateFile($post['file_hex']);

			$file_mime_split = explode(' ', trim(mime_content_type($_FILES['file']['tmp_name'])));
			if (count($file_mime_split) > 0) {
				$file_mime = strtolower(array_pop($file_mime_split));
			} else {
				if (!@getimagesize($_FILES['file']['tmp_name'])) {
					fancyDie(__('Failed to read the MIME type and size of the uploaded file. Please retry the submission.'));
				}

				$file_info = getimagesize($_FILES['file']['tmp_name']);
				$file_mime = mime_content_type($_FILES['file']['tmp_name']);
			}

			if (empty($file_mime) || !isset($tinyib_uploads[$file_mime])) {
				fancyDie(supportedFileTypes());
			}

			$file_name = time() . substr(microtime(), 2, 3);
			$post['file'] = $file_name . "." . $tinyib_uploads[$file_mime][0];

			$file_location = "src/" . $post['file'];
			if (!move_uploaded_file($_FILES['file']['tmp_name'], $file_location)) {
				fancyDie(__('Could not copy uploaded file.'));
			}

			if ($_FILES['file']['size'] != filesize($file_location)) {
				@unlink($file_location);
				fancyDie(__('File transfer failure. Please go back and try again.'));
			}

			if ($file_mime == "audio/webm" || $file_mime == "video/webm" || $file_mime == "audio/mp4" || $file_mime == "video/mp4") {
				$post['image_width'] = max(0, intval(shell_exec('mediainfo --Inform="Video;%Width%" ' . $file_location)));
				$post['image_height'] = max(0, intval(shell_exec('mediainfo --Inform="Video;%Height%" ' . $file_location)));

				if ($post['image_width'] > 0 && $post['image_height'] > 0) {
					list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);
					$post['thumb'] = $file_name . "s.jpg";
					shell_exec("ffmpegthumbnailer -s " . max($thumb_maxwidth, $thumb_maxheight) . " -i $file_location -o thumb/{$post['thumb']}");

					$thumb_info = getimagesize("thumb/" . $post['thumb']);
					$post['thumb_width'] = $thumb_info[0];
					$post['thumb_height'] = $thumb_info[1];

					if ($post['thumb_width'] <= 0 || $post['thumb_height'] <= 0) {
						@unlink($file_location);
						@unlink("thumb/" . $post['thumb']);
						fancyDie(__('Sorry, your video appears to be corrupt.'));
					}

					addVideoOverlay("thumb/" . $post['thumb']);
				}

				$duration = intval(shell_exec('mediainfo --Inform="General;%Duration%" ' . $file_location));
				if ($duration > 0) {
					$mins = floor(round($duration / 1000) / 60);
					$secs = str_pad(floor(round($duration / 1000) % 60), 2, "0", STR_PAD_LEFT);

					$post['file_original'] = "$mins:$secs" . ($post['file_original'] != '' ? (', ' . $post['file_original']) : '');
				}
			} else if (in_array($file_mime, array('image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'application/x-shockwave-flash'))) {
				$file_info = getimagesize($file_location);

				$post['image_width'] = $file_info[0];
				$post['image_height'] = $file_info[1];
			}

			if (isset($tinyib_uploads[$file_mime][1])) {
				$thumbfile_split = explode(".", $tinyib_uploads[$file_mime][1]);
				$post['thumb'] = $file_name . "s." . array_pop($thumbfile_split);
				if (!copy($tinyib_uploads[$file_mime][1], "thumb/" . $post['thumb'])) {
					@unlink($file_location);
					fancyDie(__('Could not create thumbnail.'));
				}
				if ($file_mime == "application/x-shockwave-flash") {
					addVideoOverlay("thumb/" . $post['thumb']);
				}
			} else if (in_array($file_mime, array('image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'))) {
				$post['thumb'] = $file_name . "s." . $tinyib_uploads[$file_mime][0];
				list($thumb_maxwidth, $thumb_maxheight) = thumbnailDimensions($post);

				if (!createThumbnail($file_location, "thumb/" . $post['thumb'], $thumb_maxwidth, $thumb_maxheight)) {
					@unlink($file_location);
					fancyDie(__('Could not create thumbnail.'));
				}
			}

			if ($post['thumb'] != '') {
				$thumb_info = getimagesize("thumb/" . $post['thumb']);
				$post['thumb_width'] = $thumb_info[0];
				$post['thumb_height'] = $thumb_info[1];
			}
		}
	}

	if ($post['file'] == '') { // No file uploaded
		$allowed = "";
		if (!empty($tinyib_uploads) && ($rawpost || !in_array('file', $hide_fields))) {
			$allowed = "file";
		}
		if (!empty($tinyib_embeds) && ($rawpost || !in_array('embed', $hide_fields))) {
			if ($allowed != "") {
				$allowed .= " or ";
			}
			$allowed .= "embed URL";
		}
		if ($post['parent'] == TINYIB_NEWTHREAD && $allowed != "" && !TINYIB_NOFILEOK) {
			fancyDie(sprintf(__('A %s is required to start a thread.'), $allowed));
		}
		if (!$rawpost && str_replace('<br>', '', $post['message']) == "") {
			$die_msg = "";
			if (!in_array('message', $hide_fields)) {
				$die_msg .= "enter a message " . ($allowed != "" ? " and/or " : "");
			}
			if ($allowed != "") {
				$die_msg .= "upload a $allowed";
			}
			fancyDie("Please $die_msg.");
		}
	} else {
		echo sprintf(__('%s uploaded.'), $post['file_original']) . '<br>';
	}

	if (!$loggedin && (($post['file'] != '' && TINYIB_REQMOD == 'files') || TINYIB_REQMOD == 'all')) {
		$post['moderated'] = '0';
		echo sprintf(__('Your %s will be shown <b>once it has been approved</b>.'), $post['parent'] == TINYIB_NEWTHREAD ? 'thread' : 'post') . '<br>';
		$slow_redirect = true;
	}

	$post['id'] = insertPost($post);

	if ($post['moderated'] == '1') {
		if (TINYIB_ALWAYSNOKO || strtolower($post['email']) == 'noko') {
			$redirect = 'res/' . ($post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent']) . '.html#' . $post['id'];
		}

		trimThreads();

		echo __('Updating thread...') . '<br>';
		if ($post['parent'] != TINYIB_NEWTHREAD) {
			rebuildThread($post['parent']);

			if (strtolower($post['email']) != 'sage') {
				if (TINYIB_MAXREPLIES == 0 || numRepliesToThreadByID($post['parent']) <= TINYIB_MAXREPLIES) {
					bumpThreadByID($post['parent']);
				}
			}
		} else {
			rebuildThread($post['id']);
		}

		echo __('Updating index...') . '<br>';
		rebuildIndexes();
	}
// Check if the request is to delete a post and/or its associated image
} elseif (isset($_GET['delete']) && !isset($_GET['manage'])) {
	if (!isset($_POST['delete'])) {
		fancyDie(__('Tick the box next to a post and click "Delete" to delete it.'));
	}

	if (TINYIB_DBMIGRATE) {
		fancyDie(__('Post deletion is currently disabled.<br>Please try again in a few moments.'));
	}

	$post = postByID($_POST['delete']);
	if ($post) {
		list($loggedin, $isadmin) = manageCheckLogIn();

		if ($loggedin && $_POST['password'] == '') {
			// Redirect to post moderation page
			echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=' . basename($_SERVER['PHP_SELF']) . '?manage&moderate=' . $_POST['delete'] . '">';
		} elseif ($post['password'] != '' && md5(md5($_POST['password'])) == $post['password']) {
			deletePostByID($post['id']);
			if ($post['parent'] == TINYIB_NEWTHREAD) {
				threadUpdated($post['id']);
			} else {
				threadUpdated($post['parent']);
			}
			fancyDie(__('Post deleted.'));
		} else {
			fancyDie(__('Invalid password.'));
		}
	} else {
		fancyDie(__('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.'));
	}

	$redirect = false;
// Check if the request is to access the management area
} elseif (isset($_GET['manage'])) {
	$text = '';
	$onload = '';
	$navbar = '&nbsp;';
	$redirect = false;
	$loggedin = false;
	$isadmin = false;
	$returnlink = basename($_SERVER['PHP_SELF']);

	list($loggedin, $isadmin) = manageCheckLogIn();

	if ($loggedin) {
		if ($isadmin) {
			if (isset($_GET['rebuildall'])) {
				$allthreads = allThreads();
				foreach ($allthreads as $thread) {
					rebuildThread($thread['id']);
				}
				rebuildIndexes();
				$text .= manageInfo(__('Rebuilt board.'));
			} elseif (isset($_GET['bans'])) {
				clearExpiredBans();

				if (isset($_POST['ip'])) {
					if ($_POST['ip'] != '') {
						$banexists = banByIP($_POST['ip']);
						if ($banexists) {
							fancyDie(__('Sorry, there is already a ban on record for that IP address.'));
						}

						$ban = array();
						$ban['ip'] = $_POST['ip'];
						$ban['expire'] = ($_POST['expire'] > 0) ? (time() + $_POST['expire']) : 0;
						$ban['reason'] = $_POST['reason'];

						insertBan($ban);
						$text .= manageInfo(sprintf(__('Ban record added for %s'), $ban['ip']));
					}
				} elseif (isset($_GET['lift'])) {
					$ban = banByID($_GET['lift']);
					if ($ban) {
						deleteBanByID($_GET['lift']);
						$text .= manageInfo(sprintf(__('Ban record lifted for %s'), $ban['ip']));
					}
				}

				$onload = manageOnLoad('bans');
				$text .= manageBanForm();
				$text .= manageBansTable();
			} else if (isset($_GET['update'])) {
				if (is_dir('.git')) {
					$git_output = shell_exec('git pull 2>&1');
					$text .= '<blockquote class="reply" style="padding: 7px;font-size: 1.25em;">
					<pre style="margin: 0;padding: 0;">Attempting update...' . "\n\n" . $git_output . '</pre>
					</blockquote>
					<p><b>Note:</b> If TinyIB updates and you have made custom modifications, <a href="https://gitlab.com/tslocum/tinyib/commits/master" target="_blank">review the changes</a> which have been merged into your installation.
					Ensure that your modifications do not interfere with any new/modified files.
					See the <a href="https://gitlab.com/tslocum/tinyib#readme">README</a> for more information.</p>';
				} else {
					$text .= '<p><b>TinyIB was not installed via Git.</b></p>
					<p>If you installed TinyIB without Git, you must <a href="https://gitlab.com/tslocum/tinyib">update manually</a>.  If you did install with Git, ensure the script has read and write access to the <b>.git</b> folder.</p>';
				}
			} elseif (isset($_GET['dbmigrate'])) {
				if (TINYIB_DBMIGRATE) {
					if (isset($_GET['go'])) {
						if (TINYIB_DBMODE == 'flatfile') {
							if (function_exists('mysqli_connect')) {
								$link = @mysqli_connect(TINYIB_DBHOST, TINYIB_DBUSERNAME, TINYIB_DBPASSWORD);
								if (!$link) {
									fancyDie("Could not connect to database: " . ((is_object($link)) ? mysqli_error($link) : (($link_error = mysqli_connect_error()) ? $link_error : '(unknown error)')));
								}
								$db_selected = @mysqli_query($link, "USE " . TINYIB_DBNAME);
								if (!$db_selected) {
									fancyDie("Could not select database: " . ((is_object($link)) ? mysqli_error($link) : (($link_error = mysqli_connect_error()) ? $link_error : '(unknown error')));
								}

								if (mysqli_num_rows(mysqli_query($link, "SHOW TABLES LIKE '" . TINYIB_DBPOSTS . "'")) == 0) {
									if (mysqli_num_rows(mysqli_query($link, "SHOW TABLES LIKE '" . TINYIB_DBBANS . "'")) == 0) {
										mysqli_query($link, $posts_sql);
										mysqli_query($link, $bans_sql);

										$max_id = 0;
										$threads = allThreads();
										foreach ($threads as $thread) {
											$posts = postsInThreadByID($thread['id']);
											foreach ($posts as $post) {
												mysqli_query($link, "INSERT INTO `" . TINYIB_DBPOSTS . "` (`id`, `parent`, `timestamp`, `bumped`, `ip`, `name`, `tripcode`, `email`, `nameblock`, `subject`, `message`, `password`, `file`, `file_hex`, `file_original`, `file_size`, `file_size_formatted`, `image_width`, `image_height`, `thumb`, `thumb_width`, `thumb_height`, `stickied`) VALUES (" . $post['id'] . ", " . $post['parent'] . ", " . time() . ", " . time() . ", '" . $_SERVER['REMOTE_ADDR'] . "', '" . mysqli_real_escape_string($link, $post['name']) . "', '" . mysqli_real_escape_string($link, $post['tripcode']) . "',	'" . mysqli_real_escape_string($link, $post['email']) . "',	'" . mysqli_real_escape_string($link, $post['nameblock']) . "', '" . mysqli_real_escape_string($link, $post['subject']) . "', '" . mysqli_real_escape_string($link, $post['message']) . "', '" . mysqli_real_escape_string($link, $post['password']) . "', '" . $post['file'] . "', '" . $post['file_hex'] . "', '" . mysqli_real_escape_string($link, $post['file_original']) . "', " . $post['file_size'] . ", '" . $post['file_size_formatted'] . "', " . $post['image_width'] . ", " . $post['image_height'] . ", '" . $post['thumb'] . "', " . $post['thumb_width'] . ", " . $post['thumb_height'] . ", " . $post['stickied'] . ")");
												$max_id = max($max_id, $post['id']);
											}
										}
										if ($max_id > 0 && !mysqli_query($link, "ALTER TABLE `" . TINYIB_DBPOSTS . "` AUTO_INCREMENT = " . ($max_id + 1))) {
											$text .= '<p><b>Warning:</b> Unable to update the AUTO_INCREMENT value for table ' . TINYIB_DBPOSTS . ', please set it to ' . ($max_id + 1) . '.</p>';
										}

										$max_id = 0;
										$bans = allBans();
										foreach ($bans as $ban) {
											$max_id = max($max_id, $ban['id']);
											mysqli_query($link, "INSERT INTO `" . TINYIB_DBBANS . "` (`id`, `ip`, `timestamp`, `expire`, `reason`) VALUES ('" . mysqli_real_escape_string($link, $ban['id']) . "', '" . mysqli_real_escape_string($link, $ban['ip']) . "', '" . mysqli_real_escape_string($link, $ban['timestamp']) . "', '" . mysqli_real_escape_string($link, $ban['expire']) . "', '" . mysqli_real_escape_string($link, $ban['reason']) . "')");
										}
										if ($max_id > 0 && !mysqli_query($link, "ALTER TABLE `" . TINYIB_DBBANS . "` AUTO_INCREMENT = " . ($max_id + 1))) {
											$text .= '<p><b>Warning:</b> Unable to update the AUTO_INCREMENT value for table ' . TINYIB_DBBANS . ', please set it to ' . ($max_id + 1) . '.</p>';
										}

										$text .= '<p><b>Database migration complete</b>.  Set TINYIB_DBMODE to mysqli and TINYIB_DBMIGRATE to false, then click <b>Rebuild All</b> above and ensure everything looks the way it should.</p>';
									} else {
										fancyDie('Bans table (' . TINYIB_DBBANS . ') already exists!  Please DROP this table and try again.');
									}
								} else {
									fancyDie('Posts table (' . TINYIB_DBPOSTS . ') already exists!  Please DROP this table and try again.');
								}
							} else {
								fancyDie('Please install the <a href="http://php.net/manual/en/book.mysqli.php">MySQLi extension</a> and try again.');
							}
						} else {
							fancyDie('Set TINYIB_DBMODE to flatfile and enter in your MySQL settings in settings.php before migrating.');
						}
					} else {
						$text .= '<p>This tool currently only supports migration from a flat file database to MySQL.  Your original database will not be deleted.  If the migration fails, disable the tool and your board will be unaffected.  See the <a href="https://gitlab.com/tslocum/tinyib#migrating" target="_blank">README</a> <small>(<a href="README.md" target="_blank">alternate link</a>)</small> for instructions.</a><br><br><a href="?manage&dbmigrate&go"><b>Start the migration</b></a></p>';
					}
				} else {
					fancyDie('Set TINYIB_DBMIGRATE to true in settings.php to use this feature.');
				}
			}
		}

		if (isset($_GET['delete'])) {
			$post = postByID($_GET['delete']);
			if ($post) {
				deletePostByID($post['id']);
				rebuildIndexes();
				if ($post['parent'] != TINYIB_NEWTHREAD) {
					rebuildThread($post['parent']);
				}
				$text .= manageInfo(sprintf(__('Post No.%d deleted.'), $post['id']));
			} else {
				fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
			}
		} elseif (isset($_GET['approve'])) {
			if ($_GET['approve'] > 0) {
				$post = postByID($_GET['approve']);
				if ($post) {
					approvePostByID($post['id']);
					$thread_id = $post['parent'] == TINYIB_NEWTHREAD ? $post['id'] : $post['parent'];

					if (strtolower($post['email']) != 'sage' && (TINYIB_MAXREPLIES == 0 || numRepliesToThreadByID($thread_id) <= TINYIB_MAXREPLIES)) {
						bumpThreadByID($thread_id);
					}
					threadUpdated($thread_id);

					$text .= manageInfo(sprintf(__('Post No.%d approved.'), $post['id']));
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			}
		} elseif (isset($_GET['moderate'])) {
			if ($_GET['moderate'] > 0) {
				$post = postByID($_GET['moderate']);
				if ($post) {
					$text .= manageModeratePost($post);
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			} else {
				$onload = manageOnLoad('moderate');
				$text .= manageModeratePostForm();
			}
		} elseif (isset($_GET['sticky']) && isset($_GET['setsticky'])) {
			if ($_GET['sticky'] > 0) {
				$post = postByID($_GET['sticky']);
				if ($post && $post['parent'] == TINYIB_NEWTHREAD) {
					stickyThreadByID($post['id'], intval($_GET['setsticky']));
					threadUpdated($post['id']);

					$text .= manageInfo('Thread No.' . $post['id'] . ' ' . (intval($_GET['setsticky']) == 1 ? 'stickied' : 'un-stickied') . '.');
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			} else {
				fancyDie(__('Form data was lost. Please go back and try again.'));
			}
		} elseif (isset($_GET['lock']) && isset($_GET['setlock'])) {
			if ($_GET['lock'] > 0) {
				$post = postByID($_GET['lock']);
				if ($post && $post['parent'] == TINYIB_NEWTHREAD) {
					lockThreadByID($post['id'], intval($_GET['setlock']));
					threadUpdated($post['id']);

					$text .= manageInfo('Thread No.' . $post['id'] . ' ' . (intval($_GET['setlock']) == 1 ? 'locked' : 'unlocked') . '.');
				} else {
					fancyDie(__("Sorry, there doesn't appear to be a post with that ID."));
				}
			} else {
				fancyDie(__('Form data was lost. Please go back and try again.'));
			}
		} elseif (isset($_GET["rawpost"])) {
			$onload = manageOnLoad("rawpost");
			$text .= buildPostForm(0, true);
		} elseif (isset($_GET["logout"])) {
			$_SESSION['tinyib'] = '';
			session_destroy();
			die('--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=' . $returnlink . '?manage">');
		}
		if ($text == '') {
			$text = manageStatus();
		}
	} else {
		$onload = manageOnLoad('login');
		$text .= manageLogInForm();
	}

	echo managePage($text, $onload);
} elseif (!file_exists(TINYIB_INDEX) || countThreads() == 0) {
	rebuildIndexes();
}

if ($redirect) {
	echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="' . (isset($slow_redirect) ? '3' : '0') . ';url=' . (is_string($redirect) ? $redirect : TINYIB_INDEX) . '">';
}
