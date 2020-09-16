<?php
if (!defined('TINYIB_BOARD')) {
	die('');
}

function pageHeader() {
	$js_captcha = TINYIB_CAPTCHA === 'recaptcha' ? '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' : '';

	$return = <<<EOF
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html;charset=UTF-8">
		<meta http-equiv="cache-control" content="max-age=0">
		<meta http-equiv="cache-control" content="no-cache">
		<meta http-equiv="expires" content="0">
		<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
		<meta http-equiv="pragma" content="no-cache">
		<meta name="viewport" content="width=device-width,initial-scale=1">
		<title>
EOF;
	$return .= TINYIB_BOARDDESC . <<<EOF
		</title>
		<link rel="shortcut icon" href="favicon.ico">
		<link rel="stylesheet" type="text/css" href="css/global.css">
		<link rel="stylesheet" type="text/css" href="css/futaba.css" title="Futaba" id="mainStylesheet">
		<link rel="alternate stylesheet" type="text/css" href="css/burichan.css" title="Burichan">
		<script src="js/jquery.js"></script>
		<script src="js/tinyib.js"></script>
		$js_captcha
	</head>
EOF;
	return $return;
}

function pageFooter() {
	// If the footer link is removed from the page, please link to TinyIB somewhere on the site.
	// This is all I ask in return for the free software you are using.

	return <<<EOF
		<div class="footer">
			- <a href="http://www.2chan.net" target="_top">futaba</a> + <a href="http://www.1chan.net" target="_top">futallaby</a> + <a href="https://gitlab.com/tslocum/tinyib" target="_top">tinyib</a> -
		</div>
	</body>
</html>
EOF;
}

function supportedFileTypes() {
	global $tinyib_uploads;
	if (empty($tinyib_uploads)) {
		return "";
	}

	$types_allowed = array_map('strtoupper', array_unique(array_column($tinyib_uploads, 0)));
	$types_last = array_pop($types_allowed);
	$types_formatted = $types_allowed
		? implode(', ', $types_allowed) . ' and ' . $types_last
		: $types_last;

	if (count($tinyib_uploads) == 1) {
		return sprintf(__('Supported file type is %s'), $types_formatted);
	} else {
		return sprintf(__('Supported file types are %s.'), $types_formatted);
	}
}

function makeLinksClickable($text) {
	$text = preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%\!_+.,~#?&;//=]+)!i', '<a href="$1" target="_blank">$1</a>', $text);
	$text = preg_replace('/\(\<a href\=\"(.*)\)"\ target\=\"\_blank\">(.*)\)\<\/a>/i', '(<a href="$1" target="_blank">$2</a>)', $text);
	$text = preg_replace('/\<a href\=\"(.*)\."\ target\=\"\_blank\">(.*)\.\<\/a>/i', '<a href="$1" target="_blank">$2</a>.', $text);
	$text = preg_replace('/\<a href\=\"(.*)\,"\ target\=\"\_blank\">(.*)\,\<\/a>/i', '<a href="$1" target="_blank">$2</a>,', $text);

	return $text;
}

function buildPostForm($parent, $raw_post = false) {
	global $tinyib_hidefieldsop, $tinyib_hidefields, $tinyib_uploads, $tinyib_embeds;
	$hide_fields = $parent == TINYIB_NEWTHREAD ? $tinyib_hidefieldsop : $tinyib_hidefields;

	$postform_extra = array('name' => '', 'email' => '', 'subject' => '', 'footer' => '');
	$input_submit = '<input type="submit" value="' . __('Submit') . '" accesskey="z">';
	if ($raw_post || !in_array('subject', $hide_fields)) {
		$postform_extra['subject'] = $input_submit;
	} else if (!in_array('email', $hide_fields)) {
		$postform_extra['email'] = $input_submit;
	} else if (!in_array('name', $hide_fields)) {
		$postform_extra['name'] = $input_submit;
	} else if (!in_array('email', $hide_fields)) {
		$postform_extra['email'] = $input_submit;
	} else {
		$postform_extra['footer'] = $input_submit;
	}

	$form_action = 'imgboard.php';
	$form_extra = '<input type="hidden" name="parent" value="' . $parent . '">';
	$input_extra = '';
	$rules_extra = '';
	if ($raw_post) {
		$txt_reply_to = __('Reply to');
		$txt_new_thread = __('0 to start a new thread');
		$txt_info_1 = __('Text entered in the Message field will be posted as is with no formatting applied.');
		$txt_info_2 = __('Line-breaks must be specified with "&lt;br&gt;".');

		$form_action = '?';
		$form_extra = '<input type="hidden" name="rawpost" value="1">';
		$input_extra = <<<EOF
					<tr>
						<td class="postblock">
							$txt_reply_to
						</td>
						<td>
							<input type="text" name="parent" size="28" maxlength="75" value="0" accesskey="t">&nbsp;$txt_new_thread
						</td>
					</tr>
EOF;
		$rules_extra = <<<EOF
							<ul>
								<li>$txt_info_1</li>
								<li>$txt_info_2</li>
							</ul><br>
EOF;
	}

	$max_file_size_input_html = '';
	$max_file_size_rules_html = '';
	$reqmod_html = '';
	$filetypes_html = '';
	$file_input_html = '';
	$embed_input_html = '';
	$unique_posts_html = '';

	$captcha_html = '';
	if (TINYIB_CAPTCHA && !$raw_post) {
		if (TINYIB_CAPTCHA === 'recaptcha') {
			$captcha_inner_html = '
<div style="min-height: 80px;">
	<div class="g-recaptcha" data-sitekey="' . TINYIB_RECAPTCHA_SITE . '"></div>
	<noscript>
		<div>
			<div style="width: 302px; height: 422px; position: relative;">
				<div style="width: 302px; height: 422px; position: absolute;">
					<iframe src="https://www.google.com/recaptcha/api/fallback?k=' . TINYIB_RECAPTCHA_SITE . '" frameborder="0" scrolling="no" style="width: 302px; height:422px; border-style: none;"></iframe>
				</div>
			</div>
			<div style="width: 300px; height: 60px; border-style: none;bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
				<textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid #c1c1c1; margin: 10px 25px; padding: 0px; resize: none;"></textarea>
			</div>
		</div>
	</noscript>
</div>';
		} else { // Simple CAPTCHA
			$captcha_inner_html = '
<input type="text" name="captcha" id="captcha" size="6" accesskey="c" autocomplete="off">&nbsp;&nbsp;' . __('(enter the text below)') . '<br>
<img id="captchaimage" src="inc/captcha.php" width="175" height="55" alt="CAPTCHA" onclick="javascript:reloadCAPTCHA()" style="margin-top: 5px;cursor: pointer;">';
		}

		$txt_captcha = __('CAPTCHA');
		$captcha_html = <<<EOF
					<tr>
						<td class="postblock">
							$txt_captcha
						</td>
						<td>
							$captcha_inner_html
						</td>
					</tr>
EOF;
	}

	if (!empty($tinyib_uploads) && ($raw_post || !in_array('file', $hide_fields))) {
		if (TINYIB_MAXKB > 0) {
			$max_file_size_input_html = '<input type="hidden" name="MAX_FILE_SIZE" value="' . strval(TINYIB_MAXKB * 1024) . '">';
			$max_file_size_rules_html = '<li>' . sprintf(__('Maximum file size allowed is %s.'), TINYIB_MAXKBDESC) . '</li>';
		}

		$filetypes_html = '<li>' . supportedFileTypes() . '</li>';

		$txt_file = __('File');
		$file_input_html = <<<EOF
					<tr>
						<td class="postblock">
							$txt_file
						</td>
						<td>
							<input type="file" name="file" size="35" accesskey="f">
						</td>
					</tr>
EOF;
	}

	if (!empty($tinyib_embeds) && ($raw_post || !in_array('embed', $hide_fields))) {
		$txt_embed = __('Embed');
		$txt_embed_help = __('(paste a YouTube URL)');
		$embed_input_html = <<<EOF
					<tr>
						<td class="postblock">
							$txt_embed
						</td>
						<td>
							<input type="text" name="embed" size="28" accesskey="x" autocomplete="off">&nbsp;&nbsp;$txt_embed_help
						</td>
					</tr>
EOF;
	}

	if (TINYIB_REQMOD == 'files' || TINYIB_REQMOD == 'all') {
		$reqmod_html = '<li>All posts' . (TINYIB_REQMOD == 'files' ? ' with a file attached' : '') . ' will be moderated before being shown.</li>';
	}

	$thumbnails_html = '';
	if (isset($tinyib_uploads['image/jpeg']) || isset($tinyib_uploads['image/pjpeg']) || isset($tinyib_uploads['image/png']) || isset($tinyib_uploads['image/gif'])) {
		$maxdimensions = TINYIB_MAXWOP . 'x' . TINYIB_MAXHOP;
		if (TINYIB_MAXW != TINYIB_MAXWOP || TINYIB_MAXH != TINYIB_MAXHOP) {
			$maxdimensions .= ' (new thread) or ' . TINYIB_MAXW . 'x' . TINYIB_MAXH . ' (reply)';
		}

		$thumbnails_html = '<li>' . sprintf(__('Images greater than %s will be thumbnailed.'), $maxdimensions) . '</li>';
	}

	$unique_posts = uniquePosts();
	if ($unique_posts > 0) {
		$unique_posts_html = '<li>' . printf(__('Currently %s unique user posts.'), $unique_posts) . '</li>' . "\n";
	}

	$output = <<<EOF
		<div class="postarea">
			<form name="postform" id="postform" action="$form_action" method="post" enctype="multipart/form-data">
			$max_file_size_input_html
			$form_extra
			<table class="postform">
				<tbody>
					$input_extra
EOF;
	if ($raw_post || !in_array('name', $hide_fields)) {
		$txt_name = __('Name');
		$output .= <<<EOF
					<tr>
						<td class="postblock">
							$txt_name
						</td>
						<td>
							<input type="text" name="name" size="28" maxlength="75" accesskey="n">
							{$postform_extra['name']}
						</td>
					</tr>
EOF;
	}
	if ($raw_post || !in_array('email', $hide_fields)) {
		$txt_email = __('E-mail');
		$output .= <<<EOF
					<tr>
						<td class="postblock">
							$txt_email
						</td>
						<td>
							<input type="text" name="email" size="28" maxlength="75" accesskey="e">
							{$postform_extra['email']}
						</td>
					</tr>
EOF;
	}
	if ($raw_post || !in_array('subject', $hide_fields)) {
		$txt_subject = __('Subject');
		$output .= <<<EOF
					<tr>
						<td class="postblock">
							$txt_subject
						</td>
						<td>
							<input type="text" name="subject" size="40" maxlength="75" accesskey="s" autocomplete="off">
							{$postform_extra['subject']}
						</td>
					</tr>
EOF;
	}
	if ($raw_post || !in_array('message', $hide_fields)) {
		$txt_message = __('Message');
		$output .= <<<EOF
					<tr>
						<td class="postblock">
							$txt_message
						</td>
						<td>
							<textarea id="message" name="message" cols="48" rows="4" accesskey="m"></textarea>
						</td>
					</tr>
EOF;
	}

	$output .= <<<EOF
					$captcha_html
					$file_input_html
					$embed_input_html
EOF;
	if ($raw_post || !in_array('password', $hide_fields)) {
		$txt_password = __('Password');
		$txt_password_help = __('(for post and file deletion)');
		$output .= <<<EOF
					<tr>
						<td class="postblock">
							$txt_password
						</td>
						<td>
							<input type="password" name="password" id="newpostpassword" size="8" accesskey="p">&nbsp;&nbsp;$txt_password_help
						</td>
					</tr>
EOF;
	}
	if ($postform_extra['footer'] != '') {
		$output .= <<<EOF
					<tr>
						<td>
							&nbsp;
						</td>
						<td>
							{$postform_extra['footer']}
						</td>
					</tr>
EOF;
	}
	$output .= <<<EOF
					<tr>
						<td colspan="2" class="rules">
							$rules_extra
							<ul>
								$reqmod_html
								$filetypes_html
								$max_file_size_rules_html
								$thumbnails_html
								$unique_posts_html
							</ul>
						</td>
					</tr>
				</tbody>
			</table>
			</form>
		</div>
EOF;

	return $output;
}

function buildPost($post, $res) {
	$return = "";
	$threadid = ($post['parent'] == TINYIB_NEWTHREAD) ? $post['id'] : $post['parent'];

	if ($res == TINYIB_RESPAGE) {
		$reflink = "<a href=\"$threadid.html#{$post['id']}\">No.</a><a href=\"$threadid.html#q{$post['id']}\" onclick=\"javascript:quotePost('{$post['id']}')\">{$post['id']}</a>";
	} else {
		$reflink = "<a href=\"res/$threadid.html#{$post['id']}\">No.</a><a href=\"res/$threadid.html#q{$post['id']}\">{$post['id']}</a>";
	}

	if ($post["stickied"] == 1) {
		$reflink .= ' <img src="sticky.png" alt="Stickied" title="Stickied" width="16" height="16">';
	}

	if ($post["locked"] == 1) {
		$reflink .= ' <img src="lock.png" alt="Locked" title="Locked" width="16" height="16">';
	}

	if (!isset($post["omitted"])) {
		$post["omitted"] = 0;
	}

	$filehtml = '';
	$filesize = '';
	$expandhtml = '';
	$direct_link = isEmbed($post["file_hex"]) ? "#" : (($res == TINYIB_RESPAGE ? "../" : "") . "src/" . $post["file"]);

	if ($post['parent'] == TINYIB_NEWTHREAD && $post["file"] != '') {
		$filesize .= (isEmbed($post['file_hex']) ? __('Embed:') : __('File:')) . ' ';
	}

	if (isEmbed($post["file_hex"])) {
		$expandhtml = $post['file'];
	} else if (substr($post['file'], -5) == '.webm' || substr($post['file'], -4) == '.mp4') {
		$dimensions = 'width="500" height="50"';
		if ($post['image_width'] > 0 && $post['image_height'] > 0) {
			$dimensions = 'width="' . $post['image_width'] . '" height="' . $post['image_height'] . '"';
		}
		$expandhtml = <<<EOF
<video $dimensions style="position: static; pointer-events: inherit; display: inline; max-width: 100%; max-height: 100%;" controls autoplay loop>
	<source src="$direct_link"></source>
</video>
EOF;
	} else if (in_array(substr($post['file'], -4), array('.jpg', '.png', '.gif'))) {
		$expandhtml = "<a href=\"$direct_link\" onclick=\"return expandFile(event, '${post['id']}');\"><img src=\"" . ($res == TINYIB_RESPAGE ? "../" : "") . "src/${post["file"]}\" width=\"${post["image_width"]}\" style=\"max-width: 100%;height: auto;\"></a>";
	}

	$thumblink = "<a href=\"$direct_link\" target=\"_blank\"" . ((isEmbed($post["file_hex"]) || in_array(substr($post['file'], -4), array('.jpg', '.png', '.gif', 'webm', '.mp4'))) ? " onclick=\"return expandFile(event, '${post['id']}');\"" : "") . ">";
	$expandhtml = rawurlencode($expandhtml);

	if (isEmbed($post["file_hex"])) {
		$filesize .= "<a href=\"$direct_link\" onclick=\"return expandFile(event, '${post['id']}');\">${post['file_original']}</a>&ndash;(${post['file_hex']})";
	} else if ($post["file"] != '') {
		$filesize .= $thumblink . "${post["file"]}</a>&ndash;(${post["file_size_formatted"]}";
		if ($post["image_width"] > 0 && $post["image_height"] > 0) {
			$filesize .= ", " . $post["image_width"] . "x" . $post["image_height"];
		}
		if ($post["file_original"] != "") {
			$filesize .= ", " . $post["file_original"];
		}
		$filesize .= ")";
	}

	if ($filesize != '') {
		$filesize = '<span class="filesize">' . $filesize . '</span>';
	}

	if ($filesize != '') {
		if ($post['parent'] != TINYIB_NEWTHREAD) {
			$filehtml .= '<br>';
		}
		$filehtml .= $filesize . '<br><div id="thumbfile' . $post['id'] . '">';
		if ($post["thumb_width"] > 0 && $post["thumb_height"] > 0) {
			$filehtml .= <<<EOF
$thumblink
	<img src="thumb/${post["thumb"]}" alt="${post["id"]}" class="thumb" id="thumbnail${post['id']}" width="${post["thumb_width"]}" height="${post["thumb_height"]}">
</a>
EOF;
		}
		$filehtml .= '</div>';

		if ($expandhtml != '') {
			$filehtml .= <<<EOF
<div id="expand${post['id']}" style="display: none;">$expandhtml</div>
<div id="file${post['id']}" class="thumb" style="display: none;"></div>
EOF;
		}
	}
	if ($post["parent"] == TINYIB_NEWTHREAD) {
		$return .= $filehtml;
	} else {
		$return .= <<<EOF
<table>
<tbody>
<tr>
<td class="doubledash">
	&#0168;
</td>
<td class="reply" id="reply${post["id"]}">
EOF;
	}

	$return .= <<<EOF
<a id="${post['id']}"></a>
<label>
	<input type="checkbox" name="delete" value="${post['id']}"> 
EOF;

	if ($post['subject'] != '') {
		$return .= ' <span class="filetitle">' . $post['subject'] . '</span> ';
	}

	$return .= <<<EOF
${post["nameblock"]}
</label>
<span class="reflink">
	$reflink
</span>
EOF;

	if ($post['parent'] != TINYIB_NEWTHREAD) {
		$return .= $filehtml;
	}

	if ($post['parent'] == TINYIB_NEWTHREAD && $res == TINYIB_INDEXPAGE) {
		$return .= "&nbsp;[<a href=\"res/${post["id"]}.html\">" . __("Reply") . "</a>]";
	}

	if (TINYIB_TRUNCATE > 0 && !$res && substr_count($post['message'], '<br>') > TINYIB_TRUNCATE) { // Truncate messages on board index pages for readability
		$br_offsets = strallpos($post['message'], '<br>');
		$post['message'] = substr($post['message'], 0, $br_offsets[TINYIB_TRUNCATE - 1]);
		$post['message'] .= '<br><span class="omittedposts">' . __('Post truncated.  Click Reply to view.') . '</span><br>';
	}
	$return .= <<<EOF
<div class="message">
${post["message"]}
</div>
EOF;

	if ($post['parent'] == TINYIB_NEWTHREAD) {
		if ($res == TINYIB_INDEXPAGE && $post['omitted'] > 0) {
			if ($post['omitted'] == 1) {
				$return .= '<span class="omittedposts">' . __('1 post omitted. Click Reply to view.') . '</span>';
			} else {
				$return .= '<span class="omittedposts">' . sprintf(__('%d posts omitted. Click Reply to view.'), $post['omitted']) . '</span>';
			}
		}
	} else {
		$return .= <<<EOF
</td>
</tr>
</tbody>
</table>
EOF;
	}

	return $return;
}

function buildPage($htmlposts, $parent, $pages = 0, $thispage = 0) {
	$cataloglink = TINYIB_CATALOG ? ('[<a href="catalog.html" style="text-decoration: underline;">' . __('Catalog') . '</a>]') : '';
	$managelink = basename($_SERVER['PHP_SELF']) . "?manage";

	$postingmode = "";
	$pagenavigator = "";
	if ($parent == TINYIB_NEWTHREAD) {
		$pages = max($pages, 0);
		$previous = ($thispage == 1) ? "index" : $thispage - 1;
		$next = $thispage + 1;

		$pagelinks = ($thispage == 0) ? ('<td>' . __('Previous') . '</td>') : ('<td><form method="get" action="' . $previous . '.html"><input value="' . __('Previous') . '" type="submit"></form></td>');

		$pagelinks .= "<td>";
		for ($i = 0; $i <= $pages; $i++) {
			if ($thispage == $i) {
				$pagelinks .= '&#91;' . $i . '&#93; ';
			} else {
				$href = ($i == 0) ? "index" : $i;
				$pagelinks .= '&#91;<a href="' . $href . '.html">' . $i . '</a>&#93; ';
			}
		}
		$pagelinks .= "</td>";

		$pagelinks .= ($pages <= $thispage) ? ('<td>' . __('Next') . '</td>') : ('<td><form method="get" action="' . $next . '.html"><input value="' . __('Next') . '" type="submit"></form></td>');

		$pagenavigator = <<<EOF
<table border="1" style="display: inline-block;">
	<tbody>
		<tr>
			$pagelinks
		</tr>
	</tbody>
</table>
EOF;
		if (TINYIB_CATALOG) {
			$txt_catalog = __('Catalog');
			$pagenavigator .= <<<EOF
<table border="1" style="display: inline-block;margin-left: 21px;">
	<tbody>
		<tr>
			<td><form method="get" action="catalog.html"><input value="$txt_catalog" type="submit"></form></td>
		</tr>
	</tbody>
</table>
EOF;
		}
	} else if ($parent == -1) {
		$postingmode = '&#91;<a href="index.html">' . __('Return') . '</a>&#93;<div class="replymode">' . __('Catalog') . '</div> ';
	} else {
		$postingmode = '&#91;<a href="../">' . __('Return') . '</a>&#93;<div class="replymode">' . __('Posting mode: Reply') . '</div> ';
	}

	$postform = '';
	if ($parent >= TINYIB_NEWTHREAD) {
		$postform = buildPostForm($parent);
	}

	$txt_manage = __('Manage');
	$txt_style = __('Style');
	$txt_password = __('Password');
	$txt_delete = __('Delete');
	$txt_delete_post = __('Delete Post');
	$body = <<<EOF
	<body>
		<div class="adminbar">
			$cataloglink
			[<a href="$managelink" style="text-decoration: underline;">$txt_manage</a>]
			<select id="switchStylesheet"><option value="">$txt_style</option><option value="futaba">Futaba</option><option value="burichan">Burichan</option></select>
		</div>
		<div class="logo">
EOF;
	$body .= TINYIB_LOGO . TINYIB_BOARDDESC . <<<EOF
		</div>
		<hr width="90%">
		$postingmode
		$postform
		<hr>
		<form id="delform" action="imgboard.php?delete" method="post">
		<input type="hidden" name="board" 
EOF;
	$body .= 'value="' . TINYIB_BOARD . '">' . <<<EOF
		$htmlposts
		<table class="userdelete">
			<tbody>
				<tr>
					<td>
						$txt_delete_post <input type="password" name="password" id="deletepostpassword" size="8" placeholder="$txt_password">&nbsp;<input name="deletepost" value="$txt_delete" type="submit">
					</td>
				</tr>
			</tbody>
		</table>
		</form>
		$pagenavigator
		<br>
EOF;
	return pageHeader() . $body . pageFooter();
}

function buildCatalogPost($post) {
	$maxwidth = max(100, $post['thumb_width']);
	$replies = numRepliesToThreadByID($post['id']);
	$subject = trim($post['subject']) != '' ? $post['subject'] : substr(trim(str_ireplace("\n", '', strip_tags($post['message']))), 0, 75);

	return <<<EOF
<div class="catalogpost" style="max-width: {$maxwidth}px;">
	<a href="res/{$post['id']}.html">
		<img src="thumb/{$post["thumb"]}" alt="{$post["id"]}" width="{$post['thumb_width']}" height="{$post['thumb_height']}" border="0">
	</a><br>
	<b>$replies</b><br>
	$subject
</div>
EOF;
}

function rebuildCatalog() {
	$threads = allThreads();
	$htmlposts = '';
	foreach ($threads as $post) {
		$htmlposts .= buildCatalogPost($post);
	}
	$htmlposts .= '<hr size="1">';

	writePage('catalog.html', buildPage($htmlposts, -1));
}

function rebuildIndexes() {
	$page = 0;
	$i = 0;
	$htmlposts = '';
	$threads = allThreads();
	$pages = ceil(count($threads) / TINYIB_THREADSPERPAGE) - 1;

	foreach ($threads as $thread) {
		$replies = postsInThreadByID($thread['id']);
		$thread['omitted'] = max(0, count($replies) - TINYIB_PREVIEWREPLIES - 1);

		// Build replies for preview
		$htmlreplies = array();
		for ($j = count($replies) - 1; $j > $thread['omitted']; $j--) {
			$htmlreplies[] = buildPost($replies[$j], TINYIB_INDEXPAGE);
		}

		$htmlposts .= buildPost($thread, TINYIB_INDEXPAGE) . implode('', array_reverse($htmlreplies)) . "\n<hr>";

		if (++$i >= TINYIB_THREADSPERPAGE) {
			$file = ($page == 0) ? TINYIB_INDEX : ($page . '.html');
			writePage($file, buildPage($htmlposts, 0, $pages, $page));

			$page++;
			$i = 0;
			$htmlposts = '';
		}
	}

	if ($page == 0 || $htmlposts != '') {
		$file = ($page == 0) ? TINYIB_INDEX : ($page . '.html');
		writePage($file, buildPage($htmlposts, 0, $pages, $page));
	}

	if (TINYIB_CATALOG) {
		rebuildCatalog();
	}

	if (TINYIB_JSON) {
		writePage('threads.json', buildIndexJSON());
		writePage('catalog.json', buildCatalogJSON());
	}
}

function rebuildThread($id) {
	$id = intval($id);

	$htmlposts = "";
	$posts = postsInThreadByID($id);
	foreach ($posts as $post) {
		$htmlposts .= buildPost($post, TINYIB_RESPAGE);
	}

	$htmlposts .= "\n<hr>";

	writePage('res/' . $id . '.html', fixLinksInRes(buildPage($htmlposts, $id)));

	if (TINYIB_JSON) {
		writePage('res/' . $id . '.json', buildSingleThreadJSON($id));
	}
}

function adminBar() {
	global $loggedin, $isadmin, $returnlink;

	$return = '[<a href="' . $returnlink . '" style="text-decoration: underline;">' . __('Return') . '</a>]';
	if (!$loggedin) {
		return $return;
	}

	$output = '[<a href="?manage">' . __('Status') . '</a>] [';
	if ($isadmin) {
		$output.= '<a href="?manage&bans">' . __('Bans') . '</a>] [';
	}
	$output.= '<a href="?manage&moderate">' . __('Moderate Post') . '</a>] [<a href="?manage&rawpost">' . __('Raw Post') . '</a>] [';
	if ($isadmin) {
		$output.= '<a href="?manage&rebuildall">' . __('Rebuild All<') . '/a>] [';
	}
	if ($isadmin && installedViaGit()) {
		$output .= '<a href="?manage&update">' . __('Update') . '</a>] [';
	}
	if ($isadmin && TINYIB_DBMIGRATE) {
		$output.= '<a href="?manage&dbmigrate"><b>' . __('Migrate Database') . '</b></a>] [';
	}
	$output.= '<a href="?manage&logout">' . __('Log Out') . '</a>] &middot; ' . $return;
	return $output;
}

function managePage($text, $onload = '') {
	$adminbar = adminBar();
	$txt_manage_mode = __('Manage mode');
	$body = <<<EOF
	<body$onload>
		<div class="adminbar">
			$adminbar
		</div>
		<div class="logo">
EOF;
	$body .= TINYIB_LOGO . TINYIB_BOARDDESC . <<<EOF
		</div>
		<hr width="90%">
		<div class="replymode">$txt_manage_mode</div>
		$text
		<hr>
EOF;
	return pageHeader() . $body . pageFooter();
}

function manageOnLoad($page) {
	switch ($page) {
		case 'login':
			return ' onload="document.tinyib.managepassword.focus();"';
		case 'moderate':
			return ' onload="document.tinyib.moderate.focus();"';
		case 'rawpost':
			return ' onload="document.tinyib.message.focus();"';
		case 'bans':
			return ' onload="document.tinyib.ip.focus();"';
	}
}

function manageLogInForm() {
	$txt_login = __('Log In');
	$txt_login_prompt = __('Enter an administrator or moderator password');
	return <<<EOF
	<form id="tinyib" name="tinyib" method="post" action="?manage">
	<fieldset>
	<legend align="center">$txt_login_prompt</legend>
	<div class="login">
	<input type="password" id="managepassword" name="managepassword"><br>
	<input type="submit" value="$txt_login" class="managebutton">
	</div>
	</fieldset>
	</form>
	<br>
EOF;
}

function manageBanForm() {
	$txt_ban = __('Ban an IP address');
	$txt_ban_ip = __('IP Address:');
	$txt_ban_expire = __('Expire(sec):');
	$txt_ban_reason = __('Reason:');
	$txt_ban_never = __('never');
	$txt_ban_optional = __('optional');
	$txt_submit = __('Submit');
	return <<<EOF
	<form id="tinyib" name="tinyib" method="post" action="?manage&bans">
	<fieldset>
	<legend></legend>
	<label for="ip">$txt_ban_ip</label> <input type="text" name="ip" id="ip" value="${_GET['bans']}"> <input type="submit" value="$txt_submit" class="managebutton"><br>
	<label for="expire">$txt_ban_expire</label> <input type="text" name="expire" id="expire" value="0">&nbsp;&nbsp;<small><a href="#" onclick="document.tinyib.expire.value='3600';return false;">1hr</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='86400';return false;">1d</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='172800';return false;">2d</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='604800';return false;">1w</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='1209600';return false;">2w</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='2592000';return false;">30d</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='0';return false;">$txt_ban_never</a></small><br>
	<label for="reason">$txt_ban_reason&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label> <input type="text" name="reason" id="reason">&nbsp;&nbsp;<small>$txt_ban_optional</small>
	<legend>
	</fieldset>
	</form><br>
EOF;
}

function manageBansTable() {
	$text = '';
	$allbans = allBans();
	if (count($allbans) > 0) {
		$text .= '<table border="1"><tr><th>' . __('IP Address') . '</th><th>' . __('Set At') . '</th><th>' . __('Expires') . '</th><th>' . __('Reason') . '</th><th>&nbsp;</th></tr>';
		foreach ($allbans as $ban) {
			$expire = ($ban['expire'] > 0) ? date(TINYIB_DATEFMT, $ban['expire']) : __('Does not expire');
			$reason = ($ban['reason'] == '') ? '&nbsp;' : htmlentities($ban['reason']);
			$text .= '<tr><td>' . $ban['ip'] . '</td><td>' . date(TINYIB_DATEFMT, $ban['timestamp']) . '</td><td>' . $expire . '</td><td>' . $reason . '</td><td><a href="?manage&bans&lift=' . $ban['id'] . '">' . __('lift') . '</a></td></tr>';
		}
		$text .= '</table>';
	}
	return $text;
}

function manageModeratePostForm() {
	$txt_submit = __('Submit');
	return <<<EOF
	<form id="tinyib" name="tinyib" method="get" action="?">
	<input type="hidden" name="manage" value="">
	<fieldset>
	<legend>Moderate a post</legend>
	<div valign="top"><label for="moderate">Post ID:</label> <input type="text" name="moderate" id="moderate"> <input type="submit" value="$txt_submit" class="managebutton"></div><br>
	<small><b>Tip:</b> While browsing the image board, you can easily moderate a post if you are logged in:<br>
	Tick the box next to a post and click "Delete" at the bottom of the page with a blank password.</small><br>
	</fieldset>
	</form><br>
EOF;
}

function manageModeratePost($post) {
	global $isadmin;
	$ban = banByIP($post['ip']);
	$ban_disabled = (!$ban && $isadmin) ? '' : ' disabled';
	$ban_info = (!$ban) ? ((!$isadmin) ? 'Only an administrator may ban an IP address.' : ('IP address: ' . $post["ip"])) : (' A ban record already exists for ' . $post['ip']);
	$delete_info = ($post['parent'] == TINYIB_NEWTHREAD) ? __('This will delete the entire thread below.') : __('This will delete the post below.');
	$post_or_thread = ($post['parent'] == TINYIB_NEWTHREAD) ? __('Thread') : __('Post');

	$sticky_html = "";
	$lock_html = "";
	if ($post["parent"] == TINYIB_NEWTHREAD) {
		$sticky_set = $post['stickied'] == 1 ? '0' : '1';
		$sticky_unsticky = $post['stickied'] == 1 ? __('Un-sticky') : __('Sticky');
		$sticky_unsticky_help = $post['stickied'] == 1 ? __('Return this thread to a normal state.') : __('Keep this thread at the top of the board.');
		$sticky_html = <<<EOF
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td align="right" width="50%;">
		<form method="get" action="?">
		<input type="hidden" name="manage" value="">
		<input type="hidden" name="sticky" value="${post['id']}">
		<input type="hidden" name="setsticky" value="$sticky_set">
		<input type="submit" value="$sticky_unsticky Thread" class="managebutton" style="width: 50%;">
		</form>
	</td><td><small>$sticky_unsticky_help</small></td></tr>
EOF;

		$lock_set = $post['locked'] == 1 ? '0' : '1';
		$lock_label = $post['locked'] == 1 ? __('Unlock') : __('Lock');
		$lock_help = $post['locked'] == 1 ? __('Allow replying to this thread.') : __('Disallow replying to this thread.');
		$lock_html = <<<EOF
	<tr><td align="right" width="50%;">
		<form method="get" action="?">
		<input type="hidden" name="manage" value="">
		<input type="hidden" name="lock" value="${post['id']}">
		<input type="hidden" name="setlock" value="$lock_set">
		<input type="submit" value="$lock_label Thread" class="managebutton" style="width: 50%;">
		</form>
	</td><td><small>$lock_help</small></td></tr>
EOF;

		$post_html = "";
		$posts = postsInThreadByID($post["id"]);
		foreach ($posts as $post_temp) {
			$post_html .= buildPost($post_temp, TINYIB_INDEXPAGE);
		}
	} else {
		$post_html = buildPost($post, TINYIB_INDEXPAGE);
	}

	$txt_action = __('Action');
	return <<<EOF
	<fieldset>
	<legend>Moderating No.${post['id']}</legend>
	
	<fieldset>
	<legend>$txt_action</legend>
	
	<table border="0" cellspacing="0" cellpadding="0" width="100%">
	<tr><td align="right" width="50%;">
	
	<form method="get" action="?">
	<input type="hidden" name="manage" value="">
	<input type="hidden" name="delete" value="${post['id']}">
	<input type="submit" value="Delete $post_or_thread" class="managebutton" style="width: 50%;">
	</form>
	
	</td><td><small>$delete_info</small></td></tr>
	<tr><td align="right" width="50%;">
	
	<form method="get" action="?">
	<input type="hidden" name="manage" value="">
	<input type="hidden" name="bans" value="${post['ip']}">
	<input type="submit" value="Ban Poster" class="managebutton" style="width: 50%;"$ban_disabled>
	</form>
	
	</td><td><small>$ban_info</small></td></tr>

	$sticky_html
	
	$lock_html
	
	</table>
	
	</fieldset>
	
	<fieldset>
	<legend>$post_or_thread</legend>	
	$post_html
	</fieldset>
	
	</fieldset>
	<br>
EOF;
}

function manageStatus() {
	global $isadmin;
	$threads = countThreads();
	$bans = count(allBans());

	$info = $threads . ' ' . plural($threads, __('thread'), __('threads')) . ', ' . $bans . ' ' . plural($bans, __('ban'), __('bans'));
	$output = '';

	if ($isadmin && TINYIB_DBMODE == 'mysql' && function_exists('mysqli_connect')) { // Recommend MySQLi
		$output .= <<<EOF
	<fieldset>
	<legend>Notice</legend>
	<p><b>TINYIB_DBMODE</b> is currently <b>mysql</b> in <b>settings.php</b>, but <a href="http://www.php.net/manual/en/book.mysqli.php">MySQLi</a> is installed.  Please change it to <b>mysqli</b>.  This will not affect your data.</p>
	</fieldset>
EOF;
	}

	$reqmod_html = '';

	if (TINYIB_REQMOD == 'files' || TINYIB_REQMOD == 'all') {
		$reqmod_post_html = '';

		$reqmod_posts = latestPosts(false);
		foreach ($reqmod_posts as $post) {
			if ($reqmod_post_html != '') {
				$reqmod_post_html .= '<tr><td colspan="2"><hr></td></tr>';
			}
			$reqmod_post_html .= '<tr><td>' . buildPost($post, TINYIB_INDEXPAGE) . '</td><td valign="top" align="right">
			<table border="0"><tr><td>
			<form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="approve" value="' . $post['id'] . '"><input type="submit" value="' . __('Approve') . '" class="managebutton"></form>
			</td><td>
			<form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="moderate" value="' . $post['id'] . '"><input type="submit" value="' . __('More Info') . '" class="managebutton"></form>
			</td></tr><tr><td align="right" colspan="2">
			<form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="delete" value="' . $post['id'] . '"><input type="submit" value="' . __('Delete') . '" class="managebutton"></form>
			</td></tr></table>
			</td></tr>';
		}

		if ($reqmod_post_html != '') {
			$txt_pending = __('Pending posts');
			$reqmod_html = <<<EOF
	<fieldset>
	<legend>$txt_pending</legend>
	<table border="0" cellspacing="0" cellpadding="0" width="100%">
	$reqmod_post_html
	</table>
	</fieldset>
EOF;
		}
	}

	$post_html = '';
	$posts = latestPosts(true);
	foreach ($posts as $post) {
		if ($post_html != '') {
			$post_html .= '<tr><td colspan="2"><hr></td></tr>';
		}
		$post_html .= '<tr><td>' . buildPost($post, TINYIB_INDEXPAGE) . '</td><td valign="top" align="right"><form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="moderate" value="' . $post['id'] . '"><input type="submit" value="' . __('Moderate') . '" class="managebutton"></form></td></tr>';
	}

	$txt_status = __('Status');
	$txt_info = __('Info');
	$txt_recent_posts = __('Recent posts');
	$output .= <<<EOF
	<fieldset>
	<legend>$txt_status</legend>
	
	<fieldset>
	<legend>$txt_info</legend>
	<table border="0" cellspacing="0" cellpadding="0" width="100%">
	<tbody>
	<tr><td>
		$info
	</td>
	</tr>
	</tbody>
	</table>
	</fieldset>

	$reqmod_html
	
	<fieldset>
	<legend>$txt_recent_posts</legend>
	<table border="0" cellspacing="0" cellpadding="0" width="100%">
	$post_html
	</table>
	</fieldset>
	
	</fieldset>
	<br>
EOF;

	return $output;
}

function manageInfo($text) {
	return '<div class="manageinfo">' . $text . '</div>';
}

function encodeJSON($array) {
	if (version_compare(phpversion(), '5.4.0', '>')) {
		return json_encode($array, JSON_PRETTY_PRINT);
	}
	return json_encode($array);
}

function buildSinglePostJSON($post) {
	$name = $post['name'];
	if ($name == '') {
		$name = __('Anonymous');
	}

	$output = array('id' => $post['id'], 'parent' => $post['parent'], 'timestamp' => $post['timestamp'], 'bumped' => $post['bumped'], 'name' => $name, 'tripcode' => $post['tripcode'], 'subject' => $post['subject'], 'message' => $post['message'], 'file' => $post['file'], 'file_hex' => $post['file_hex'], 'file_original' => $post['file_original'], 'file_size' => $post['file_size'], 'file_size_formated' => $post['file_size_formatted'], 'image_width' => $post['image_width'], 'image_height' => $post['image_height'], 'thumb' => $post['thumb'], 'thumb_width' => $post['thumb_width'], 'thumb_height' => $post['thumb_height']);

	if($post['parent'] == TINYIB_NEWTHREAD) {
		$replies = count(postsInThreadByID($post['id'])) - 1;
		$images = imagesInThreadByID($post['id']);

		$output = array_merge($output, array('stickied' => $post['stickied'], 'locked' => $post['locked'], 'replies' => $replies, 'images' => $images));
	}

	return $output;
}

function buildIndexJSON() {
	$output = array('threads' => array());

	$threads = allThreads();
	foreach ($threads as $thread) {
		array_push($output['threads'], array('id' => $thread['id'], 'subject' => $thread['subject'], 'bumped' => $thread['bumped']));
	}

	return encodeJSON($output);
}

function buildCatalogJSON() {
	$output = array('threads' => array());

	$threads = allThreads();
	foreach ($threads as $post) {
		array_push($output['threads'], buildSinglePostJSON($post));
	}

	return encodeJSON($output);
}

function buildSingleThreadJSON($id) {
	$output = array('posts' => array());

	$posts = postsInThreadByID($id);
	foreach ($posts as $post) {
		array_push($output['posts'], buildSinglePostJSON($post));
	}

	return encodeJSON($output);
}
