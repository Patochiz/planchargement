<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/camiontype_list.php
 * \ingroup planchargement
 * \brief   List of truck types (admin catalog)
 */

require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/planchargement/class/camiontype.class.php');

$langs->loadLangs(array('admin', 'planchargement@planchargement'));

if (!$user->hasRight('planchargement', 'admin')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

/*
 * Actions
 */

// Delete
if ($action == 'confirm_delete' && $id > 0) {
	$obj = new CamionType($db);
	$obj->fetch($id);
	$result = $obj->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($obj->error), null, 'errors');
	}
	$action = '';
}

// Toggle active
if ($action == 'toggle' && $id > 0) {
	$obj = new CamionType($db);
	$obj->fetch($id);
	$obj->active = $obj->active ? 0 : 1;
	$obj->update($user);
	$action = '';
}

/*
 * View
 */

llxHeader('', $langs->trans('PlanchargementCamionTypeList'), '', '', 0, 0, '', '', '', 'mod-planchargement page-admin-camiontype-list');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('PlanchargementSetup'), $linkback, 'title_setup');

// Admin tabs
$head = array();
$head[] = array(
	dol_buildpath('/planchargement/admin/setup.php', 1),
	$langs->trans('Settings'),
	'settings',
);
$head[] = array(
	dol_buildpath('/planchargement/admin/camiontype_list.php', 1),
	$langs->trans('PlanchargementCamionTypeList'),
	'camiontype',
);
$head[] = array(
	dol_buildpath('/planchargement/admin/umtype_list.php', 1),
	$langs->trans('PlanchargementUmTypeList'),
	'umtype',
);

print dol_get_fiche_head($head, 'camiontype', $langs->trans('PlanchargementSetup'), -1, 'generic');

// New button
print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_buildpath('/planchargement/admin/camiontype_card.php', 1).'?action=create">'.$langs->trans('PlanchargementCamionTypeNew').'</a>';
print '</div>';

// List
$camiontype = new CamionType($db);
$records = $camiontype->fetchAll('ASC', 'label');

if (is_array($records) && count($records) > 0) {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Label').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementLongueurUtile').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementLargeurUtile').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementHauteurUtile').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementChargeUtile').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td class="center">'.$langs->trans('Action').'</td>';
	print '</tr>';

	foreach ($records as $record) {
		print '<tr class="oddeven">';

		// Label (link to card)
		print '<td>';
		print '<a href="'.dol_buildpath('/planchargement/admin/camiontype_card.php', 1).'?id='.$record->id.'">';
		print dol_escape_htmltag($record->label);
		print '</a>';
		print '</td>';

		// Dimensions
		print '<td class="right">'.number_format($record->longueur_utile, 0, '', ' ').'</td>';
		print '<td class="right">'.number_format($record->largeur_utile, 0, '', ' ').'</td>';
		print '<td class="right">'.number_format($record->hauteur_utile, 0, '', ' ').'</td>';
		print '<td class="right">'.number_format($record->charge_utile, 0, '', ' ').'</td>';

		// Status
		print '<td class="center">';
		if ($record->active) {
			print '<span class="badge  badge-status4 badge-status">'.$langs->trans('Enabled').'</span>';
		} else {
			print '<span class="badge  badge-status5 badge-status">'.$langs->trans('Disabled').'</span>';
		}
		print '</td>';

		// Actions
		print '<td class="center nowraponall">';
		// Edit
		print '<a class="paddingright" href="'.dol_buildpath('/planchargement/admin/camiontype_card.php', 1).'?id='.$record->id.'">';
		print img_edit();
		print '</a>';
		// Toggle
		$toggleUrl = $_SERVER['PHP_SELF'].'?action=toggle&id='.$record->id;
		print '<a class="paddingright" href="'.$toggleUrl.'">';
		if ($record->active) {
			print img_picto($langs->trans('Disable'), 'switch_on');
		} else {
			print img_picto($langs->trans('Enable'), 'switch_off');
		}
		print '</a>';
		// Delete
		$deleteUrl = $_SERVER['PHP_SELF'].'?action=confirm_delete&id='.$record->id;
		print '<a class="paddingright" href="'.$deleteUrl.'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteRecord').'\');">';
		print img_delete();
		print '</a>';
		print '</td>';

		print '</tr>';
	}

	print '</table>';
} else {
	print '<div class="opacitymedium">'.$langs->trans('NoRecordFound').'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
