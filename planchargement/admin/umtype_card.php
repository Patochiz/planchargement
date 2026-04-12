<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/umtype_card.php
 * \ingroup planchargement
 * \brief   Handling unit type card (create/edit)
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
$cancel = GETPOST('cancel', 'alpha');

$object = new UmType($db);
if ($id > 0) {
	$object->fetch($id);
}

/*
 * Actions
 */

if ($cancel) {
	header('Location: '.dol_buildpath('/planchargement/admin/umtype_list.php', 1));
	exit;
}

if ($action == 'add') {
	$object->label = GETPOST('label', 'alpha');
	$object->longueur = GETPOSTINT('longueur');
	$object->largeur = GETPOSTINT('largeur');
	$object->hauteur = GETPOSTINT('hauteur');
	$object->gerbable = GETPOSTINT('gerbable');
	$object->active = GETPOSTINT('active');

	if (empty($object->label)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordCreatedSuccessfully'), null, 'mesgs');
			header('Location: '.dol_buildpath('/planchargement/admin/umtype_list.php', 1));
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'update') {
	$object->label = GETPOST('label', 'alpha');
	$object->longueur = GETPOSTINT('longueur');
	$object->largeur = GETPOSTINT('largeur');
	$object->hauteur = GETPOSTINT('hauteur');
	$object->gerbable = GETPOSTINT('gerbable');
	$object->active = GETPOSTINT('active');

	if (empty($object->label)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Label')), null, 'errors');
		$action = 'edit';
	} else {
		$result = $object->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
			header('Location: '.dol_buildpath('/planchargement/admin/umtype_list.php', 1));
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

$title = ($action == 'create') ? $langs->trans('PlanchargementUmTypeNew') : $langs->trans('PlanchargementUmTypeCard');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-planchargement page-admin-umtype-card');

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

	// Longueur
	print '<tr><td>'.$langs->trans('PlanchargementLongueur').'</td>';
	print '<td><input type="number" name="longueur" class="width100" value="'.(GETPOSTISSET('longueur') ? GETPOSTINT('longueur') : $object->longueur).'"> mm</td></tr>';

	// Largeur
	print '<tr><td>'.$langs->trans('PlanchargementLargeur').'</td>';
	print '<td><input type="number" name="largeur" class="width100" value="'.(GETPOSTISSET('largeur') ? GETPOSTINT('largeur') : $object->largeur).'"> mm</td></tr>';

	// Hauteur
	print '<tr><td>'.$langs->trans('PlanchargementHauteur').'</td>';
	print '<td><input type="number" name="hauteur" class="width100" value="'.(GETPOSTISSET('hauteur') ? GETPOSTINT('hauteur') : $object->hauteur).'"> mm</td></tr>';

	// Gerbable
	print '<tr><td>'.$langs->trans('PlanchargementGerbable').'</td>';
	print '<td>';
	$gerbable_val = GETPOSTISSET('gerbable') ? GETPOSTINT('gerbable') : $object->gerbable;
	print '<select name="gerbable" class="flat">';
	print '<option value="0"'.(!$gerbable_val ? ' selected' : '').'>'.$langs->trans('No').'</option>';
	print '<option value="1"'.($gerbable_val ? ' selected' : '').'>'.$langs->trans('Yes').'</option>';
	print '</select>';
	print '</td></tr>';

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

		print '<tr><td>'.$langs->trans('PlanchargementLongueur').'</td>';
		print '<td>'.number_format($object->longueur, 0, '', ' ').' mm</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementLargeur').'</td>';
		print '<td>'.number_format($object->largeur, 0, '', ' ').' mm</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementHauteur').'</td>';
		print '<td>'.number_format($object->hauteur, 0, '', ' ').' mm</td></tr>';

		print '<tr><td>'.$langs->trans('PlanchargementGerbable').'</td>';
		print '<td>';
		if ($object->gerbable) {
			print img_picto($langs->trans('Yes'), 'tick').' '.$langs->trans('Yes');
		} else {
			print $langs->trans('No');
		}
		print '</td></tr>';

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
		print '<a class="butActionDelete" href="'.dol_buildpath('/planchargement/admin/umtype_list.php', 1).'?action=confirm_delete&id='.$object->id.'" onclick="return confirm(\''.$langs->trans('ConfirmDeleteRecord').'\');">'.$langs->trans('Delete').'</a>';
		print '</div>';
	}
}

llxFooter();
$db->close();
