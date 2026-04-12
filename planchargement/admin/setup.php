<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup planchargement
 * \brief   Module setup page
 */

// Load Dolibarr environment
require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Load translation files
$langs->loadLangs(array('admin', 'planchargement@planchargement'));

// Access control
if (!$user->hasRight('planchargement', 'admin')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action == 'update') {
	// Future config options will be saved here
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

/*
 * View
 */

$page_name = 'PlanchargementSetup';
llxHeader('', $langs->trans($page_name), '', '', 0, 0, '', '', '', 'mod-planchargement page-admin-setup');

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Admin tabs
$head = array();

$head[] = array(
	dol_buildpath('/planchargement/admin/setup.php', 1),
	$langs->trans('Settings'),
	'settings',
	'',
	'',
	'',
	'',
	1
);
$head[] = array(
	dol_buildpath('/planchargement/admin/camiontype_list.php', 1),
	$langs->trans('PlanchargementCamionTypeList'),
	'camiontype',
	'',
	'',
	'',
	'',
	1
);
$head[] = array(
	dol_buildpath('/planchargement/admin/umtype_list.php', 1),
	$langs->trans('PlanchargementUmTypeList'),
	'umtype',
	'',
	'',
	'',
	'',
	1
);

print dol_get_fiche_head($head, 'settings', $langs->trans('PlanchargementSetup'), -1, 'generic');

print '<div class="opacitymedium">'.$langs->trans('PlanchargementSetupDesc').'</div>';
print '<br>';

// Numbering module info
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('PlanchargementNumberingModule').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('PlanchargementNumberingModule').'</td>';
print '<td>'.getDolGlobalString('PLANCHARGEMENT_CHARGEMENT_ADDON', 'mod_chargement_standard').'</td>';
print '</tr>';

print '</table>';

print dol_get_fiche_end();

llxFooter();
$db->close();
