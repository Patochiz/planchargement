<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/update_um_custom.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to edit a custom (one-shot) UM created from the
 *          composition tab. Updates the underlying UmType (label + dims +
 *          gerbable). Only allowed for is_custom=1 types belonging to the
 *          chargement of the UM, and only when the chargement is in DRAFT.
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

$fk_um    = GETPOSTINT('fk_um');
$label    = trim((string) GETPOST('label', 'alphanohtml'));
$longueur = GETPOSTINT('longueur');
$largeur  = GETPOSTINT('largeur');
$hauteur  = GETPOSTINT('hauteur');
$gerbable = GETPOSTINT('gerbable') ? 1 : 0;

if ($fk_um <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Missing fk_um'));
	exit;
}
if ($label === '') {
	echo json_encode(array('success' => false, 'error' => 'PlanchargementCustomUmErrorLabelRequired'));
	exit;
}
if ($longueur <= 0 || $largeur <= 0 || $hauteur <= 0) {
	echo json_encode(array('success' => false, 'error' => 'PlanchargementCustomUmErrorDimsRequired'));
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

$umtype = new UmType($db);
if ($umtype->fetch((int) $um->fk_um_type) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'UmType not found'));
	exit;
}

// Guard: only edit one-shot custom types belonging to this chargement.
// Catalog types must stay editable from admin only.
if (empty($umtype->is_custom) || (int) $umtype->fk_chargement_origin !== (int) $chg->id) {
	echo json_encode(array('success' => false, 'error' => 'PlanchargementCustomUmErrorNotEditable'));
	exit;
}

$umtype->label    = $label;
$umtype->longueur = $longueur;
$umtype->largeur  = $largeur;
$umtype->hauteur  = $hauteur;
$umtype->gerbable = $gerbable;

$res = $umtype->update($user);
if ($res <= 0) {
	echo json_encode(array('success' => false, 'error' => $umtype->error ? $umtype->error : 'UmType update failed'));
	exit;
}

echo json_encode(array(
	'success'    => true,
	'um_id'      => (int) $um->id,
	'um_type_id' => (int) $umtype->id,
));
