<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/assign_colis.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to assign/remove packages to/from a UM
 */

// Rule 6: 5 constants BEFORE main.inc.php
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

header('Content-Type: application/json');

if (!$user->hasRight('planchargement', 'write')) {
	echo json_encode(array('success' => false, 'error' => 'AccessDenied'));
	exit;
}

$action = GETPOST('action', 'aZ09');
$fk_um = GETPOSTINT('fk_um');
$fk_package = GETPOSTINT('fk_package');
$quantity = GETPOSTINT('quantity');

if ($fk_um <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Missing fk_um'));
	exit;
}

// Fetch UM and verify parent chargement is DRAFT
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

// Assign
if ($action == 'assign') {
	if ($fk_package <= 0 || $quantity <= 0) {
		echo json_encode(array('success' => false, 'error' => 'Missing fk_package or quantity'));
		exit;
	}

	$result = $um->assignColis($fk_package, $quantity);
	if ($result > 0) {
		$um->calculatePoids();
		$chg->calculateTotals();
		echo json_encode(array(
			'success' => true,
			'um_weight' => $um->poids_total,
			'total_weight' => $chg->poids_total,
		));
	} else {
		echo json_encode(array('success' => false, 'error' => $um->error));
	}
	exit;
}

// Remove
if ($action == 'remove') {
	if ($fk_package <= 0) {
		echo json_encode(array('success' => false, 'error' => 'Missing fk_package'));
		exit;
	}

	$result = $um->removeColis($fk_package);
	if ($result > 0) {
		$um->calculatePoids();
		$chg->calculateTotals();
		echo json_encode(array(
			'success' => true,
			'um_weight' => $um->poids_total,
			'total_weight' => $chg->poids_total,
		));
	} else {
		echo json_encode(array('success' => false, 'error' => $um->error));
	}
	exit;
}

echo json_encode(array('success' => false, 'error' => 'Unknown action'));
