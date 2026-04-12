<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/camiontype_card.php
 * \ingroup planchargement
 * \brief   Truck type card (create/edit)
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
$cancel = GETPOST('cancel', 'alpha');

$object = new CamionType($db);
if ($id > 0) {
	$object->fetch($id);
}

/*
 * Actions
 */

if ($cancel) {
	header('Location: '.dol_buildpath('/planchargement/admin/camiontype_list.php', 1));
	exit;
}

if ($action == 'add') {
	$object->label = GETPOST('label', 'alpha');
	$object->longueur_utile = GETPOSTINT('longueur_utile');
	$object->largeur_utile = GETPOSTINT('largeur_utile');
	$object->hauteur_utile = GETPOSTINT('hauteur_utile');
	$object->charge_utile = GETPOSTFLOAT('charge_utile');
	$object->active = GETPOSTINT('active');

	if (empty($object->label)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordCreatedSuccessfully'), null, 'mesgs');
			header('Location: '.dol_buildpath('/planchargement/admin/camiontype_list.php', 1));
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'update') {
	$object->label = GETPOST('label', 'alpha');
	$object->longueur_utile = GETPOSTINT('longueur_utile');
	$object->largeur_utile = GETPOSTINT('largeur_utile');
	$object->hauteur_utile = GETPOSTINT('hauteur_utile');
	$object->charge_utile = GETPOSTFLOAT('charge_utile');
	$object->active = GETPOSTINT('active');

	if (empty($object->label)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
		$action = 'edit';
	} else {
		$result = $object->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
			header('Location: '.dol_buildpath('/planchargement/admin/camiontype_list.php', 1));
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'edit';
		}
	}
}

/*
 * View
 */

$title = ($action == 'create') ? $langs->trans('PlanchargementCamionTypeNew') : $langs->trans('PlanchargementCamionTypeCard');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-planchargement page-admin-camiontype-card');

if ($action == 'create' || $action == 'edit') {
	// Form
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	if ($action == 'create') {
		print '<input type="hidden" name="action" value="add">';
	} else {
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
	}

	print load_fiche_titre($title, '', 'generic');

	print dol_get_fiche_head(array(), '', '', 0, '');

	print '<table class="border centpercent tableforfieldcreate">';

	// Label
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Label').'</td>';
	print '<td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag(GETPOSTISSET('label') ? GETPOST('label', 'alpha') : $object->label).'"></td></tr>';

	// Longueur utile
	print '<tr><td>'.$langs->trans('PlanchargementLongueurUtile').'</td>';
	print '<td><input type="number" name="longueur_utile" class="width100" value="'.(GETPOSTISSET('longueur_utile') ? GETPOSTINT('longueur_utile') : $object->longueur_utile).'"> mm</td></tr>';

	// Largeur utile
	print '<tr><td>'.$langs->trans('PlanchargementLargeurUtile').'</td>';
	print '<td><input type="number" name="largeur_utile" class="width100" value="'.(GETPOSTISSET('largeur_utile') ? GETPOSTINT('largeur_utile') : $object->largeur_utile).'"> mm</td></tr>';

	// Hauteur utile
	print '<tr><td>'.$langs->trans('PlanchargementHauteurUtile').'</td>';
	print '<td><input type="number" name="hauteur_utile" class="width100" value="'.(GETPOSTISSET('hauteur_utile') ? GETPOSTINT('hauteur_utile') : $object->hauteur_utile).'"> mm</td></tr>';

	// Charge utile
	print '<tr><td>'.$langs->trans('PlanchargementChargeUtile').'</td>';
	print '<td><input type="number" step="0.1" name="charge_utile" class="width100" value="'.(GETPOSTISSET('charge_utile') ? GETPOSTFLOAT('charge_utile') : $object->charge_utile).'"> kg</td></tr>';

	// Active
	print '<tr><td>'.$langs->trans('Status').'</td>';
	print '<td>';
	$active_val = GETPOSTISSET('active') ? GETPOSTINT('active') : ($action == 'create' ? 1 : $object->active);
	print '<select name="active" class="flat">';
	print '<option value="1"'.($active_val ? ' selected' : '').'>'.$langs->trans('Enabled').'</option>';
	print '<option value="0"'.(!$active_val ? ' selected' : '').'>'.$langs->trans('Disabled').'</option>';
	print '</select>';
	print '</td></tr>';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print ' &nbsp; ';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';

	print '</form>';
} else {
	// View card
	if ($object->id > 0) {
		print load_fiche_titre($title, '', 'generic');

		print dol_get_fiche_head(array(), '', '', 0, '');

		print '<table class="border centpercent">';

		print '<tr><td class="titlefield">'.$langs->trans('Label').'</td>';
		print '<td>'.dol_escape_htmltag($object->label).'</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementLongueurUtile').'</td>';
		print '<td>'.number_format($object->longueur_utile, 0, '', ' ').' mm</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementLargeurUtile').'</td>';
		print '<td>'.number_format($object->largeur_utile, 0, '', ' ').' mm</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementHauteurUtile').'</td>';
		print '<td>'.number_format($object->hauteur_utile, 0, '', ' ').' mm</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementChargeUtile').'</td>';
		print '<td>'.number_format($object->charge_utile, 0, '', ' ').' kg</td></tr>';

		print '<tr><td>'.$langs->trans('Status').'</td>';
		print '<td>';
		if ($object->active) {
			print '<span class="badge  badge-status4 badge-status">'.$langs->trans('Enabled').'</span>';
		} else {
			print '<span class="badge  badge-status5 badge-status">'.$langs->trans('Disabled').'</span>';
		}
		print '</td></tr>';

		print '</table>';

		print dol_get_fiche_end();

		// Actions
		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit">'.$langs->trans('Modify').'</a>';
		print '<a class="butActionDelete" href="'.dol_buildpath('/planchargement/admin/camiontype_list.php', 1).'?action=confirm_delete&id='.$object->id.'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteRecord').'\');">'.$langs->trans('Delete').'</a>';
		print '</div>';
	}
}

llxFooter();
$db->close();
