<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/create_um_custom.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to create a one-shot custom UM in a loading plan.
 *          Creates a hidden UmType (is_custom=1) bound to the chargement,
 *          then creates a ChargementUm linked to it.
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
dol_include_once('/planchargement/class/umtype.class.php');

header('Content-Type: application/json');

if (!$user->hasRight('planchargement', 'write')) {
	echo json_encode(array('success' => false, 'error' => 'AccessDenied'));
	exit;
}

$fk_chargement = GETPOSTINT('fk_chargement');
$label         = trim((string) GETPOST('label', 'alphanohtml'));
$longueur      = GETPOSTINT('longueur');
$largeur       = GETPOSTINT('largeur');
$hauteur       = GETPOSTINT('hauteur');
$gerbable      = GETPOSTINT('gerbable') ? 1 : 0;

// Validation
if ($fk_chargement <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Missing fk_chargement'));
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

$chg = new Chargement($db);
if ($chg->fetch($fk_chargement) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Chargement not found'));
	exit;
}

if ($chg->statut != Chargement::STATUS_DRAFT) {
	echo json_encode(array('success' => false, 'error' => 'Chargement is not in draft status'));
	exit;
}

$db->begin();

// 1. Create the hidden custom UmType, bound to this chargement
$umtype                       = new UmType($db);
$umtype->label                = $label;
$umtype->longueur             = $longueur;
$umtype->largeur              = $largeur;
$umtype->hauteur              = $hauteur;
$umtype->gerbable             = $gerbable;
$umtype->active               = 1;
$umtype->is_custom            = 1;
$umtype->fk_chargement_origin = $fk_chargement;

$res = $umtype->create($user);
if ($res <= 0) {
	$db->rollback();
	echo json_encode(array('success' => false, 'error' => $umtype->error ? $umtype->error : 'UmType create failed'));
	exit;
}

// 2. Create the ChargementUm instance linked to the new type
$um_id = $chg->addUm($umtype->id);
if ($um_id <= 0) {
	$db->rollback();
	echo json_encode(array('success' => false, 'error' => $chg->error ? $chg->error : 'addUm failed'));
	exit;
}

$db->commit();

echo json_encode(array(
	'success'    => true,
	'um_id'      => $um_id,
	'um_type_id' => $umtype->id,
));
