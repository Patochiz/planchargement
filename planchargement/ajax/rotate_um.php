<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/rotate_um.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to toggle a UM rotation between 0° and 90°.
 *          Refuses the change if the new orientation would overflow the
 *          truck or overlap another placed UM.
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
dol_include_once('/planchargement/class/camiontype.class.php');
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

$ut = new UmType($db);
if ($ut->fetch((int) $um->fk_um_type) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'UmType not found'));
	exit;
}

// Toggle 0 <-> 90
$new_rot = ((int) $um->rotation === 90) ? 0 : 90;
$new_len = ($new_rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
$new_wid = ($new_rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;

// If the UM is currently placed, check that the new orientation still fits
// (no truck overflow, no overlap with another placed UM).
$is_placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
if ($is_placed) {
	$ct = new CamionType($db);
	if ($ct->fetch($chg->fk_camion_type) <= 0) {
		echo json_encode(array('success' => false, 'error' => 'CamionType not found'));
		exit;
	}

	$pos_x = (int) $um->pos_x;
	$pos_y = (int) $um->pos_y;

	if ((int) $ct->longueur_utile > 0 && $pos_x + $new_len > (int) $ct->longueur_utile) {
		echo json_encode(array('success' => false, 'error' => 'WouldOverflow'));
		exit;
	}
	if ((int) $ct->largeur_utile > 0 && $pos_y + $new_wid > (int) $ct->largeur_utile) {
		echo json_encode(array('success' => false, 'error' => 'WouldOverflow'));
		exit;
	}

	$conflict = ChargementUm::findOverlap($db, (int) $um->fk_chargement, (int) $fk_um, $pos_x, $pos_y, $new_len, $new_wid);
	if ($conflict > 0) {
		echo json_encode(array(
			'success'     => false,
			'error'       => 'Overlap',
			'conflict_id' => $conflict,
		));
		exit;
	}
}

$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_um";
$sql .= " SET rotation = ".((int) $new_rot);
$sql .= " WHERE rowid = ".((int) $fk_um);

if (!$db->query($sql)) {
	echo json_encode(array('success' => false, 'error' => $db->lasterror()));
	exit;
}

echo json_encode(array(
	'success'  => true,
	'rotation' => $new_rot,
));
