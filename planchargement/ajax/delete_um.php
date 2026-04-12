<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/delete_um.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to delete a UM from a loading plan
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}

require_once '../../../main.inc.php';
dol_include_once('/planchargement/class/chargement.class.php');
dol_include_once('/planchargement/class/chargementum.class.php');
dol_include_once('/planchargement/class/umtype.class.php');

header('Content-Type: application/json');

if (!$user->hasRight('planchargement', 'write')) {
	echo json_encode(array('success' => false, 'error' => 'AccessDenied'));
	exit;
}

$fk_um = GETPOSTINT('fk_um');

if ($fk_um <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Missing fk_um'));
	exit;
}

$um = new ChargementUm($db);
if ($um->fetch($fk_um) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'UM not found'));
	exit;
}

$chg = new Chargement($db);
if ($chg->fetch($um->fk_chargement) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Chargement not found'));
	exit;
}

if ($chg->statut != Chargement::STATUS_DRAFT) {
	echo json_encode(array('success' => false, 'error' => 'Chargement is not in draft status'));
	exit;
}

// Capture the linked UmType id BEFORE deletion so we can clean it up
// afterwards if it was a one-shot custom type with no remaining UM.
$linked_um_type_id = (int) $um->fk_um_type;

$result = $um->delete($user);
if ($result > 0) {
	// Cleanup: if the linked UmType was a custom one-shot type and no other
	// UM still references it, delete it too. UmType::delete() already guards
	// against deletion when still in use, so this is safe to call.
	if ($linked_um_type_id > 0) {
		$linked_type = new UmType($db);
		if ($linked_type->fetch($linked_um_type_id) > 0 && !empty($linked_type->is_custom)) {
			$linked_type->delete($user);
		}
	}

	$chg->calculateTotals();
	echo json_encode(array(
		'success' => true,
		'total_weight' => $chg->poids_total,
	));
} else {
	echo json_encode(array('success' => false, 'error' => $um->error));
}
