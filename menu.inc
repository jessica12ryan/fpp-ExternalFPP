<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## menu.inc                                                ##
 * ## Adds entries to the FPP top-level menus.                ##
 * #############################################################
 */

$efppIconUrl = 'api/plugin/fpp-ExternalFPP/icon?v=' . filemtime(__DIR__ . '/icon.png');

$menuEntries = Array(
	Array(
		'text' => '<img src="' . $efppIconUrl . '" width="16" height="16" class="align-middle me-1"> External FPP',
		'type' => 'content',
		'page' => 'config.php',
		'wrap' => 1
	),
	Array(
		'text' => '<img src="' . $efppIconUrl . '" width="16" height="16" class="align-middle me-1"> External FPP',
		'type' => 'status',
		'page' => 'status.php',
		'wrap' => 1
	),
	Array(
		'text' => '<img src="' . $efppIconUrl . '" width="16" height="16" class="align-middle me-1"> External FPP',
		'type' => 'help',
		'page' => 'about.php',
		'wrap' => 1
	)
);

foreach ($menuEntries as $entry) {
	if ($entry['type'] != $menu)
		continue;

	if (preg_match('/^http.?:\/\//', $entry['page'])) {
		printf("<li><a href='%s' target='_blank'>%s</a></li>\n",
			$entry['page'], $entry['text']);
	} else {
		$nopage = '';
		if (isset($entry['wrap']) && ($entry['wrap'] == 0))
			$nopage = '&nopage=1';

		printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
			$plugin, $entry['page'], $nopage, $entry['text']);
	}
}

// Only show the Logout item when the visitor is actually using the external
// (password-protected) port. That vhost sets X-Remote-User from the Apache
// session, while the normal FPP backend port never sends it, so this cleanly
// distinguishes the two, leaving the regular UI unaffected.
if ($menu == 'status' && !empty($_SERVER['HTTP_X_REMOTE_USER'])) {
	// The logout handler is local to the external vhost, so link to it
	// relative (same host/port the page is currently served from).
	printf("<li><a href='logout'>&#9889; Logout</a></li>\n");
}
?>
