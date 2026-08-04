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

$efpp_tabs = Array(
	Array('page' => 'status.php',    'icon' => '&#9632;',  'label' => 'Status'),
	Array('page' => 'config.php',    'icon' => '&#9881;',  'label' => 'Config'),
	Array('page' => 'users.php',     'icon' => '&#128101;', 'label' => 'Users'),
	Array('page' => 'loginpage.php', 'icon' => '&#128274;', 'label' => 'Login Page'),
	Array('page' => 'logs.php',      'icon' => '&#9776;',  'label' => 'Logs'),
	Array('page' => 'help.php',      'icon' => '&#63;',    'label' => 'Help'),
	Array('page' => 'about.php',     'icon' => '&#9432;',  'label' => 'About')
);
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
