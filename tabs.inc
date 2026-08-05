<?php
/**
 * #############################################################
 * ## External FPP Web Access (fpp-ExternalFPP)               ##
 * ## Author: jessica12ryan                                ##
 * ## URL: https://github.com/jessica12ryan/fpp-ExternalFPP##
 * #############################################################
 * ## tabs.inc                                                ##
 * ## Shared tab bar rendered by all plugin pages.            ##
 * #############################################################
 */

function efpp_tab_active($page) {
	$current = $_GET['page'] ?? basename(debug_backtrace()[0]['file']);
	return $current === $page ? 'active' : '';
}

// UI level controls which advanced features are shown. The value is read from
// FPP's runtime settings ("uiLevel"), which is an integer:
//   Basic=0, Advanced=1, Experimental=2, Developer=3
// FPP loads settings into the global $settings array when rendering pages, so
// that is checked first; a number of on-disk locations/formats are tried as
// fallbacks to cover the different ways FPP stores its settings.
function efpp_ui_level() {
    if (isset($GLOBALS['settings']) && is_array($GLOBALS['settings'])
        && isset($GLOBALS['settings']['uiLevel'])) {
        return (int)$GLOBALS['settings']['uiLevel'];
    }

    $candidates = array(
        '/home/fpp/media/settings',   // key=value, used by modern FPP
        '/opt/fpp/config/settings.json',
        '/home/fpp/config/settings.json'
    );
    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        // JSON values file.
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['uiLevel'])) {
            return (int)$json['uiLevel'];
        }
        // key=value flat file (allow optional whitespace around '=').
        if (preg_match('/^uiLevel[ \t]*=[ \t]*(\d+)/m', $raw, $m)) {
            return (int)$m[1];
        }
    }
    return 0;
}

$efpp_ui_level = efpp_ui_level();

// Renders FPP's standard "?" help-tooltip icon next to a field label. The
// markup (class, icon path, data-bs-* attributes) matches what FPP itself
// emits via ArgHelpIcon() so tooltips behave and look identical to FPP's
// settings pages. Bootstrap tooltips are wired up in the script below.
function efppHelp($tip) {
	$tip = trim((string)$tip);
	if ($tip === '') {
		return '';
	}
	$t = htmlspecialchars($tip, ENT_QUOTES, 'UTF-8');
	return ' <span class="argHelpIcon" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="auto" data-bs-title="' . $t . '"><img src="images/redesign/help-icon.svg" class="icon-help" alt="Help icon"></span>';
}

$efpp_tabs = Array(
	Array('page' => 'status.php', 'icon' => '&#9632;', 'label' => 'Status'),
	Array('page' => 'config.php', 'icon' => '&#9881;', 'label' => 'Config'),
	Array('page' => 'users.php',  'icon' => '&#128101;', 'label' => 'Users'),
	Array('page' => 'pages.php',  'icon' => '&#128274;', 'label' => 'Pages'),
	Array('page' => 'help.php',   'icon' => '&#63;',    'label' => 'Help'),
	Array('page' => 'about.php',  'icon' => '&#9432;',  'label' => 'About')
);

// The Logs tab is only useful to advanced users, so hide it below the
// Advanced UI level.
if ($efpp_ui_level >= 1) {
	$efpp_tabs = array_merge(
		array_slice($efpp_tabs, 0, 4),
		array(array('page' => 'logs.php', 'icon' => '&#9776;', 'label' => 'Logs')),
		array_slice($efpp_tabs, 4)
	);
}
?>
<style>
.tab-bar { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--bs-border-color, #dee2e6); }
.tab-bar a { display: block; padding: 8px 18px; text-decoration: none; color: var(--bs-body-color, #495057); background: var(--bs-tertiary-bg, #f8f9fa); border: 1px solid var(--bs-border-color, #dee2e6); border-bottom: none; border-radius: 4px 4px 0 0; margin-bottom: -2px; margin-right: 3px; font-size: 14px; }
.tab-bar a.active { background: var(--bs-body-bg, #fff); color: var(--bs-body-color, #212529); border-color: var(--bs-border-color, #dee2e6); border-bottom-color: var(--bs-body-bg, #fff); font-weight: 600; }
.tab-bar a:hover:not(.active) { background: var(--bs-secondary-bg, #e9ecef); }
@media only screen and (max-width: 480px) {
	fieldset { padding: 5px !important; }
	table { width: 100%; table-layout: fixed; word-wrap: break-word; }
	td { display: block; width: 100% !important; box-sizing: border-box; }
	input[type="text"], input[type="password"], input[type="number"] { width: 100% !important; box-sizing: border-box; }
	input.buttons { width: 100%; margin-bottom: 4px; box-sizing: border-box; }
}
</style>

<div class="tab-bar">
<?php foreach ($efpp_tabs as $efpp_tab): ?>
	<a href="plugin.php?plugin=fpp-ExternalFPP&page=<?php echo $efpp_tab['page']; ?>" class="<?php echo efpp_tab_active($efpp_tab['page']); ?>"><?php echo $efpp_tab['icon']; ?> <?php echo $efpp_tab['label']; ?></a>
<?php endforeach; ?>
</div>

<script>
// Wire up the "?" help icons the same way FPP does (Bootstrap tooltip with
// data-bs-title). Runs on ready since the tab bar is emitted before the rest
// of the page body.
$(function () {
	if (window.bootstrap && bootstrap.Tooltip) {
		$('.argHelpIcon').each(function () {
			try { new bootstrap.Tooltip(this); }
			catch (e) { console.warn('Skipping invalid efpp help tooltip:', e); }
		});
	}
});
</script>
