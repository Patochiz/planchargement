<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/umtype_list.php
 * \ingroup planchargement
 * \brief   List of handling unit types (admin catalog)
 */

require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/planchargement/class/umtype.class.php');

$langs->loadLangs(array('admin', 'planchargement@planchargement'));

if (!$user->hasRight('planchargement', 'admin')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

/*
 * Actions
 */

if ($action == 'confirm_delete' && $id > 0) {
	$obj = new UmType($db);
	$obj->fetch($id);
	$result = $obj->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($obj->error), null, 'errors');
	}
	$action = '';
}

if ($action == 'toggle' && $id > 0) {
	$obj = new UmType($db);
	$obj->fetch($id);
	$obj->active = $obj->active ? 0 : 1;
	$obj->update($user);
	$action = '';
}

/*
 * View
 */

llxHeader('', $langs->trans('PlanchargementUmTypeList'), '', '', 0, 0, '', '', '', 'mod-planchargement page-admin-umtype-list');

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

print dol_get_fiche_head($head, 'umtype', $langs->trans('PlanchargementSetup'), -1, 'generic');

// New button
print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_buildpath('/planchargement/admin/umtype_card.php', 1).'?action=create">'.$langs->trans('PlanchargementUmTypeNew').'</a>';
print '</div>';

// List
$umtype = new UmType($db);
$records = $umtype->fetchAll('ASC', 'label');

if (is_array($records) && count($records) > 0) {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Label').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementLongueur').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementLargeur').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementHauteur').'</td>';
	print '<td class="right">'.$langs->trans('PlanchargementChargeMax').'</td>';
	print '<td class="center">'.$langs->trans('PlanchargementGerbable').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td class="center">'.$langs->trans('Action').'</td>';
	print '</tr>';

	foreach ($records as $record) {
		print '<tr class="oddeven">';

		print '<td>';
		print '<a href="'.dol_buildpath('/planchargement/admin/umtype_card.php', 1).'?id='.$record->id.'">';
		print dol_escape_htmltag($record->label);
		print '</a>';
		print '</td>';

		print '<td class="right">'.number_format($record->longueur, 0, '', ' ').'</td>';
		print '<td class="right">'.number_format($record->largeur, 0, '', ' ').'</td>';
		print '<td class="right">'.number_format($record->hauteur, 0, '', ' ').'</td>';
		print '<td class="right">'.number_format($record->charge_max, 0, '', ' ').'</td>';

		// Gerbable
		print '<td class="center">';
		if ($record->gerbable) {
			print img_picto($langs->trans('Yes'), 'tick');
		} else {
			print '-';
		}
		print '</td>';

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
		print '<a class="paddingright" href="'.dol_buildpath('/planchargement/admin/umtype_card.php', 1).'?id='.$record->id.'">';
		print img_edit();
		print '</a>';
		$toggleUrl = $_SERVER['PHP_SELF'].'?action=toggle&id='.$record->id;
		print '<a class="paddingright" href="'.$toggleUrl.'">';
		if ($record->active) {
			print img_picto($langs->trans('Disable'), 'switch_on');
		} else {
			print img_picto($langs->trans('Enable'), 'switch_off');
		}
		print '</a>';
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
