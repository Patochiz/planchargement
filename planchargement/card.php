<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    card.php
 * \ingroup planchargement
 * \brief   Loading plan card (create/view/edit with tabs)
 */

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/planchargement/class/chargement.class.php');
dol_include_once('/planchargement/class/chargementum.class.php');
dol_include_once('/planchargement/class/camiontype.class.php');
dol_include_once('/planchargement/class/umtype.class.php');

$langs->loadLangs(array('planchargement@planchargement', 'orders', 'companies'));

if (!$user->hasRight('planchargement', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$tab = GETPOST('tab', 'aZ09');
if (empty($tab)) {
	$tab = 'general';
}

$object = new Chargement($db);
if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref);
	$id = $object->id;
}

$form = new Form($db);

/**
 * Build tabs array for the loading plan card
 *
 * @param  Chargement $object Loading plan object
 * @return array              Tabs array
 */
function planchargement_prepare_head($object)
{
	global $langs;
	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/planchargement/card.php', 1).'?id='.$object->id.'&tab=general';
	$head[$h][1] = $langs->trans('PlanchargementTabGeneral');
	$head[$h][2] = 'general';
	$h++;

	$head[$h][0] = dol_buildpath('/planchargement/card.php', 1).'?id='.$object->id.'&tab=composition';
	$head[$h][1] = $langs->trans('PlanchargementTabComposition');
	$head[$h][2] = 'composition';
	$h++;

	$head[$h][0] = dol_buildpath('/planchargement/card.php', 1).'?id='.$object->id.'&tab=plan';
	$head[$h][1] = $langs->trans('PlanchargementTabPlan');
	$head[$h][2] = 'plan';
	$h++;

	$head[$h][0] = dol_buildpath('/planchargement/card.php', 1).'?id='.$object->id.'&tab=info';
	$head[$h][1] = $langs->trans('Info');
	$head[$h][2] = 'info';
	$h++;

	return $head;
}

/*
 * Actions
 */

if ($cancel) {
	if ($action == 'create') {
		header('Location: '.dol_buildpath('/planchargement/list.php', 1));
		exit;
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab='.$tab);
	exit;
}

// Create
if ($action == 'add' && $user->hasRight('planchargement', 'write')) {
	$object->date_chargement = dol_mktime(0, 0, 0, GETPOSTINT('date_chargementmonth'), GETPOSTINT('date_chargementday'), GETPOSTINT('date_chargementyear'));
	$object->fk_camion_type = GETPOSTINT('fk_camion_type');
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	if ($object->fk_camion_type <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('PlanchargementCamionType')), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordCreatedSuccessfully'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// Update
if ($action == 'update' && $user->hasRight('planchargement', 'write')) {
	if ($object->statut != Chargement::STATUS_DRAFT) {
		setEventMessages($langs->trans('ErrorRecordAlreadyValidated'), null, 'errors');
	} else {
		$object->date_chargement = dol_mktime(0, 0, 0, GETPOSTINT('date_chargementmonth'), GETPOSTINT('date_chargementday'), GETPOSTINT('date_chargementyear'));
		$object->fk_camion_type = GETPOSTINT('fk_camion_type');
		$object->note_public = GETPOST('note_public', 'restricthtml');
		$object->note_private = GETPOST('note_private', 'restricthtml');

		$result = $object->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}

// Delete
if ($action == 'confirm_delete' && $confirm == 'yes' && $user->hasRight('planchargement', 'delete')) {
	$result = $object->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/planchargement/list.php', 1));
		exit;
	} else {
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
}

// Validate
if ($action == 'confirm_validate' && $confirm == 'yes' && $user->hasRight('planchargement', 'write')) {
	$result = $object->valid($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}

// Mark departed
if ($action == 'confirm_departed' && $confirm == 'yes' && $user->hasRight('planchargement', 'write')) {
	$result = $object->changeStatus(Chargement::STATUS_DEPARTED, $user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}

// Cancel
if ($action == 'confirm_cancel' && $confirm == 'yes' && $user->hasRight('planchargement', 'write')) {
	$result = $object->changeStatus(Chargement::STATUS_CANCELLED, $user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}

// Reopen (back to draft)
if ($action == 'confirm_reopen' && $confirm == 'yes' && $user->hasRight('planchargement', 'write')) {
	$result = $object->changeStatus(Chargement::STATUS_DRAFT, $user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}

// Add commande
if ($action == 'addcommande' && $user->hasRight('planchargement', 'write')) {
	$fk_commande = GETPOSTINT('fk_commande');
	if ($fk_commande > 0) {
		$result = $object->addCommande($fk_commande);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans($object->error), $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}

// Remove commande
if ($action == 'removecommande' && $user->hasRight('planchargement', 'write')) {
	$fk_commande = GETPOSTINT('fk_commande');
	if ($fk_commande > 0) {
		$result = $object->removeCommande($fk_commande);
		if ($result > 0) {
			setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans($object->error), $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&tab=general');
	exit;
}


/*
 * View
 */

$title = $langs->trans('PlanchargementCard');

// CSS and JS for composition tab
$morehead = '';
$morejsarray = array();
if ($tab == 'composition' && $object->id > 0) {
	$morehead = '<link rel="stylesheet" href="'.dol_buildpath('/planchargement/css/planchargement.css', 1).'">';
	$morejsarray = array(
		dol_buildpath('/planchargement/js/planchargement.js', 1),
	);
}

llxHeader($morehead, $title, '', '', 0, 0, $morejsarray, '', '', 'mod-planchargement page-card');


// ============================================================
// CREATE MODE
// ============================================================
if ($action == 'create') {
	print load_fiche_titre($langs->trans('PlanchargementNew'), '', 'generic');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head(array(), '', '', 0, '');

	print '<table class="border centpercent tableforfieldcreate">';

	// Date chargement
	print '<tr><td class="titlefieldcreate">'.$langs->trans('PlanchargementDateChargement').'</td>';
	print '<td>';
	print $form->selectDate(dol_now(), 'date_chargement', 0, 0, 0, '', 1, 1);
	print '</td></tr>';

	// Truck type
	print '<tr><td class="fieldrequired">'.$langs->trans('PlanchargementCamionType').'</td>';
	print '<td>';
	$camiontype = new CamionType($db);
	$camiontypes = $camiontype->fetchAll('ASC', 'label');
	print '<select name="fk_camion_type" class="flat minwidth300">';
	print '<option value="0">&nbsp;</option>';
	if (is_array($camiontypes)) {
		foreach ($camiontypes as $ct) {
			if (!$ct->active) {
				continue;
			}
			$selected = (GETPOSTINT('fk_camion_type') == $ct->id) ? ' selected' : '';
			$dims = $ct->longueur_utile.'x'.$ct->largeur_utile.'x'.$ct->hauteur_utile.' mm, '.number_format($ct->charge_utile, 0, '', ' ').' kg';
			print '<option value="'.$ct->id.'"'.$selected.'>'.dol_escape_htmltag($ct->label).' ('.$dims.')</option>';
		}
	}
	print '</select>';
	print '</td></tr>';

	// Note public
	print '<tr><td>'.$langs->trans('NotePublic').'</td>';
	print '<td><textarea name="note_public" class="centpercent" rows="3">'.dol_escape_htmltag(GETPOST('note_public', 'restricthtml')).'</textarea></td></tr>';

	// Note private
	print '<tr><td>'.$langs->trans('NotePrivate').'</td>';
	print '<td><textarea name="note_private" class="centpercent" rows="3">'.dol_escape_htmltag(GETPOST('note_private', 'restricthtml')).'</textarea></td></tr>';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Create').'">';
	print ' &nbsp; ';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';

	print '</form>';

// ============================================================
// VIEW / EDIT MODE (existing object)
// ============================================================
} elseif ($object->id > 0) {
	$linkback = '<a href="'.dol_buildpath('/planchargement/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

	$head = planchargement_prepare_head($object);
	print dol_get_fiche_head($head, $tab, $langs->trans('PlanchargementChargement'), -1, 'generic');

	// Confirmation dialogs
	if ($action == 'delete') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('Delete'),
			$langs->trans('PlanchargementConfirmDelete'),
			'confirm_delete',
			'',
			0,
			1
		);
	}
	if ($action == 'validate') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('PlanchargementValidate'),
			$langs->trans('PlanchargementConfirmValidate'),
			'confirm_validate',
			'',
			0,
			1
		);
	}
	if ($action == 'departed') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('PlanchargementSetDeparted'),
			$langs->trans('PlanchargementConfirmDeparted'),
			'confirm_departed',
			'',
			0,
			1
		);
	}
	if ($action == 'cancel_chargement') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('PlanchargementCancel'),
			$langs->trans('PlanchargementConfirmCancel'),
			'confirm_cancel',
			'',
			0,
			1
		);
	}
	if ($action == 'reopen') {
		print $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('PlanchargementBackToDraft'),
			$langs->trans('PlanchargementConfirmReopen'),
			'confirm_reopen',
			'',
			0,
			1
		);
	}

	// ============ GENERAL TAB ============
	if ($tab == 'general') {
		$is_edit = ($action == 'edit');

		if ($is_edit) {
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="update">';
			print '<input type="hidden" name="id" value="'.$object->id.'">';
			print '<input type="hidden" name="tab" value="general">';
		}

		// Banner table
		print '<table class="border centpercent">';

		// Ref
		print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td>';
		print '<td>'.$object->ref;
		print ' '.$linkback;
		print '</td></tr>';

		// Date chargement
		print '<tr><td>'.$langs->trans('PlanchargementDateChargement').'</td>';
		print '<td>';
		if ($is_edit) {
			print $form->selectDate($object->date_chargement, 'date_chargement', 0, 0, 0, '', 1, 1);
		} else {
			print dol_print_date($object->date_chargement, 'day');
		}
		print '</td></tr>';

		// Truck type
		print '<tr><td>'.$langs->trans('PlanchargementCamionType').'</td>';
		print '<td>';
		if ($is_edit) {
			$camiontype = new CamionType($db);
			$camiontypes = $camiontype->fetchAll('ASC', 'label');
			print '<select name="fk_camion_type" class="flat minwidth300">';
			print '<option value="0">&nbsp;</option>';
			if (is_array($camiontypes)) {
				foreach ($camiontypes as $ct) {
					if (!$ct->active && $ct->id != $object->fk_camion_type) {
						continue;
					}
					$selected = ($object->fk_camion_type == $ct->id) ? ' selected' : '';
					$dims = $ct->longueur_utile.'x'.$ct->largeur_utile.'x'.$ct->hauteur_utile.' mm, '.number_format($ct->charge_utile, 0, '', ' ').' kg';
					print '<option value="'.$ct->id.'"'.$selected.'>'.dol_escape_htmltag($ct->label).' ('.$dims.')</option>';
				}
			}
			print '</select>';
		} else {
			$ct_obj = new CamionType($db);
			if ($ct_obj->fetch($object->fk_camion_type) > 0) {
				print dol_escape_htmltag($ct_obj->label);
				print ' <span class="opacitymedium">('.$ct_obj->longueur_utile.'x'.$ct_obj->largeur_utile.'x'.$ct_obj->hauteur_utile.' mm, '.number_format($ct_obj->charge_utile, 0, '', ' ').' kg)</span>';
			}
		}
		print '</td></tr>';

		// Total weight
		print '<tr><td>'.$langs->trans('PlanchargementPoidsTotal').'</td>';
		print '<td>'.number_format($object->poids_total, 1, '.', ' ').' kg</td></tr>';

		// Status
		print '<tr><td>'.$langs->trans('Status').'</td>';
		print '<td>'.$object->getLibStatut(4).'</td></tr>';

		// Notes
		if ($is_edit) {
			print '<tr><td>'.$langs->trans('NotePublic').'</td>';
			print '<td><textarea name="note_public" class="centpercent" rows="3">'.dol_escape_htmltag($object->note_public).'</textarea></td></tr>';
			print '<tr><td>'.$langs->trans('NotePrivate').'</td>';
			print '<td><textarea name="note_private" class="centpercent" rows="3">'.dol_escape_htmltag($object->note_private).'</textarea></td></tr>';
		} else {
			if (!empty($object->note_public)) {
				print '<tr><td>'.$langs->trans('NotePublic').'</td>';
				print '<td>'.dol_htmlentitiesbr($object->note_public).'</td></tr>';
			}
			if (!empty($object->note_private)) {
				print '<tr><td>'.$langs->trans('NotePrivate').'</td>';
				print '<td>'.dol_htmlentitiesbr($object->note_private).'</td></tr>';
			}
		}

		print '</table>';

		if ($is_edit) {
			print '<div class="center" style="margin-top: 10px;">';
			print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
			print ' &nbsp; ';
			print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
			print '</div>';
			print '</form>';
		}

		// ---- Linked orders section ----
		if (!$is_edit) {
			print '<br>';
			print load_fiche_titre($langs->trans('PlanchargementCommandes'), '', '');

			// Add order form (only in DRAFT)
			if ($object->statut == Chargement::STATUS_DRAFT && $user->hasRight('planchargement', 'write')) {
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="addcommande">';
				print '<input type="hidden" name="id" value="'.$object->id.'">';
				print '<input type="hidden" name="tab" value="general">';

				// Select orders not yet linked
				$sql_orders = "SELECT c.rowid, c.ref, s.nom as socname";
				$sql_orders .= " FROM ".MAIN_DB_PREFIX."commande c";
				$sql_orders .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = c.fk_soc";
				$sql_orders .= " WHERE c.fk_statut > 0";
				if (!empty($object->commandes)) {
					$sql_orders .= " AND c.rowid NOT IN (".implode(',', array_map('intval', $object->commandes)).")";
				}
				$sql_orders .= " ORDER BY c.ref DESC";
				$sql_orders .= " LIMIT 200";

				$resql_orders = $db->query($sql_orders);
				print '<select name="fk_commande" class="flat minwidth300">';
				print '<option value="0">'.$langs->trans('PlanchargementSelectCommande').'</option>';
				if ($resql_orders) {
					while ($obj_order = $db->fetch_object($resql_orders)) {
						print '<option value="'.$obj_order->rowid.'">'.dol_escape_htmltag($obj_order->ref.' - '.$obj_order->socname).'</option>';
					}
				}
				print '</select>';
				print ' <input type="submit" class="button" value="'.$langs->trans('PlanchargementAddCommande').'">';
				print '</form>';
				print '<br>';
			}

			// List linked orders
			if (!empty($object->commandes)) {
				print '<table class="noborder centpercent">';
				print '<tr class="liste_titre">';
				print '<td>'.$langs->trans('Ref').'</td>';
				print '<td>'.$langs->trans('Company').'</td>';
				print '<td class="center">'.$langs->trans('Status').'</td>';
				print '<td class="center">'.$langs->trans('PlanchargementNbColis').'</td>';
				if ($object->statut == Chargement::STATUS_DRAFT && $user->hasRight('planchargement', 'write')) {
					print '<td class="center">'.$langs->trans('Action').'</td>';
				}
				print '</tr>';

				require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

				foreach ($object->commandes as $fk_cmd) {
					$commande = new Commande($db);
					$commande->fetch($fk_cmd);
					$commande->fetch_thirdparty();

					// Count packages for this order
					$sql_nb = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."colisage_packages WHERE fk_commande = ".((int) $fk_cmd);
					$resql_nb = $db->query($sql_nb);
					$nb_colis = 0;
					if ($resql_nb) {
						$obj_nb = $db->fetch_object($resql_nb);
						$nb_colis = $obj_nb ? (int) $obj_nb->nb : 0;
					}

					print '<tr class="oddeven">';
					print '<td>'.$commande->getNomUrl(1).'</td>';
					print '<td>'.($commande->thirdparty ? $commande->thirdparty->getNomUrl(1) : '').'</td>';
					print '<td class="center">'.$commande->getLibStatut(5).'</td>';
					print '<td class="center">'.$nb_colis.'</td>';

					if ($object->statut == Chargement::STATUS_DRAFT && $user->hasRight('planchargement', 'write')) {
						print '<td class="center">';
						print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=removecommande&fk_commande='.$fk_cmd.'&token='.newToken().'&tab=general"';
						print ' onclick="return confirm(\''.$langs->trans('PlanchargementRemoveCommande').' ?\');">';
						print img_delete();
						print '</a>';
						print '</td>';
					}
					print '</tr>';
				}

				print '</table>';
			} else {
				print '<div class="opacitymedium">'.$langs->trans('NoRecordFound').'</div>';
			}
		}

	// ============ COMPOSITION TAB ============
	} elseif ($tab == 'composition') {
		// Pass JS config variables
		print '<script>';
		print 'var planchargement_ajax_url_assign = "'.dol_buildpath('/planchargement/ajax/assign_colis.php', 1).'";';
		print 'var planchargement_ajax_url_create_um = "'.dol_buildpath('/planchargement/ajax/create_um.php', 1).'";';
		print 'var planchargement_ajax_url_delete_um = "'.dol_buildpath('/planchargement/ajax/delete_um.php', 1).'";';
		print 'var planchargement_chargement_id = '.$object->id.';';
		print 'var planchargement_readonly = '.($object->statut != Chargement::STATUS_DRAFT ? 'true' : 'false').';';
		print '</script>';

		include dol_buildpath('/planchargement/tpl/composition.tpl.php', 0);

	// ============ PLAN TAB ============
	} elseif ($tab == 'plan') {
		print '<div class="opacitymedium" style="padding: 20px;">'.$langs->trans('PlanchargementPlanPlaceholder').'</div>';

	// ============ INFO TAB ============
	} elseif ($tab == 'info') {
		print '<table class="border centpercent">';

		// Creator
		print '<tr><td class="titlefield">'.$langs->trans('UserCreation').'</td><td>';
		if ($object->fk_user_creat > 0) {
			$userstatic = new User($db);
			$userstatic->fetch($object->fk_user_creat);
			print $userstatic->getNomUrl(1);
		}
		print '</td></tr>';

		// Date creation
		print '<tr><td>'.$langs->trans('DateCreation').'</td>';
		print '<td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';

		// Modifier
		print '<tr><td>'.$langs->trans('UserModif').'</td><td>';
		if ($object->fk_user_modif > 0) {
			$userstatic2 = new User($db);
			$userstatic2->fetch($object->fk_user_modif);
			print $userstatic2->getNomUrl(1);
		} else {
			print '-';
		}
		print '</td></tr>';

		// Date modification
		print '<tr><td>'.$langs->trans('DateModification').'</td>';
		print '<td>'.dol_print_date($object->tms, 'dayhour').'</td></tr>';

		print '</table>';
	}

	print dol_get_fiche_end();

	// ============ ACTION BUTTONS BAR ============
	if ($action != 'edit') {
		print '<div class="tabsAction">';

		if ($object->statut == Chargement::STATUS_DRAFT) {
			if ($user->hasRight('planchargement', 'write')) {
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&tab=general">'.$langs->trans('Modify').'</a>';
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=validate&tab='.$tab.'">'.$langs->trans('PlanchargementValidate').'</a>';
			}
			if ($user->hasRight('planchargement', 'delete')) {
				print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&tab='.$tab.'">'.$langs->trans('Delete').'</a>';
			}
		}

		if ($object->statut == Chargement::STATUS_VALID) {
			if ($user->hasRight('planchargement', 'write')) {
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=departed&tab='.$tab.'">'.$langs->trans('PlanchargementSetDeparted').'</a>';
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=cancel_chargement&tab='.$tab.'">'.$langs->trans('PlanchargementCancel').'</a>';
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reopen&tab='.$tab.'">'.$langs->trans('PlanchargementBackToDraft').'</a>';
			}
		}

		if ($object->statut == Chargement::STATUS_DEPARTED) {
			if ($user->hasRight('planchargement', 'write')) {
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=cancel_chargement&tab='.$tab.'">'.$langs->trans('PlanchargementCancel').'</a>';
			}
		}

		if ($object->statut == Chargement::STATUS_CANCELLED) {
			if ($user->hasRight('planchargement', 'write')) {
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reopen&tab='.$tab.'">'.$langs->trans('PlanchargementBackToDraft').'</a>';
			}
		}

		print '</div>';
	}

} else {
	// Object not found
	print load_fiche_titre($langs->trans('PlanchargementCard'), '', 'generic');
	print '<div class="error">'.$langs->trans('ErrorRecordNotFound').'</div>';
}

llxFooter();
$db->close();
