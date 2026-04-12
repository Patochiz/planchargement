<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/update_um_position.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to update a UM's position in the loading plan.
 *          Accepts pos_x/pos_y in mm (or null to mark the UM as unplaced).
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

$fk_um  = GETPOSTINT('fk_um');
$unplace = GETPOST('unplace', 'alpha') === '1';
// pos_x / pos_y are integer mm; accept them only when not unplacing
$pos_x = GETPOSTINT('pos_x');
$pos_y = GETPOSTINT('pos_y');

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

if ($unplace) {
	// Move UM back to the overflow zone
	$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_um SET pos_x = NULL, pos_y = NULL";
	$sql .= " WHERE rowid = ".((int) $fk_um);
	if (!$db->query($sql)) {
		echo json_encode(array('success' => false, 'error' => $db->lasterror()));
		exit;
	}
	echo json_encode(array('success' => true, 'placed' => false));
	exit;
}

// Place / move: validate bounds against truck + UM size
$ct = new CamionType($db);
if ($ct->fetch($chg->fk_camion_type) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'CamionType not found'));
	exit;
}

$ut = new UmType($db);
if ($ut->fetch((int) $um->fk_um_type) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'UmType not found'));
	exit;
}

// Account for rotation: when rotation=90, UM length and width are swapped
// relative to the truck axes.
$rot = (int) $um->rotation;
$um_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
$um_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;

if ($pos_x < 0) {
	$pos_x = 0;
}
if ($pos_y < 0) {
	$pos_y = 0;
}
if ((int) $ct->longueur_utile > 0 && $pos_x + $um_len > (int) $ct->longueur_utile) {
	$pos_x = max(0, (int) $ct->longueur_utile - $um_len);
}
if ((int) $ct->largeur_utile > 0 && $pos_y + $um_wid > (int) $ct->largeur_utile) {
	$pos_y = max(0, (int) $ct->largeur_utile - $um_wid);
}

$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_um";
$sql .= " SET pos_x = ".((int) $pos_x).", pos_y = ".((int) $pos_y);
$sql .= " WHERE rowid = ".((int) $fk_um);

if (!$db->query($sql)) {
	echo json_encode(array('success' => false, 'error' => $db->lasterror()));
	exit;
}

echo json_encode(array(
	'success' => true,
	'placed'  => true,
	'pos_x'   => (int) $pos_x,
	'pos_y'   => (int) $pos_y,
));
