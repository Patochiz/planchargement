<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/stack_um.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to stack a UM on top of another (gerbage).
 *          The child UM inherits the parent's pos_x / pos_y and gets its
 *          fk_um_parent set. Only a single stacking level is allowed.
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

$fk_um        = GETPOSTINT('fk_um');         // child
$fk_um_parent = GETPOSTINT('fk_um_parent');  // target parent

if ($fk_um <= 0 || $fk_um_parent <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Missing parameters'));
	exit;
}
if ($fk_um === $fk_um_parent) {
	echo json_encode(array('success' => false, 'error' => 'CannotStackOnSelf'));
	exit;
}

$child = new ChargementUm($db);
if ($child->fetch($fk_um) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'UM not found'));
	exit;
}

$parent = new ChargementUm($db);
if ($parent->fetch($fk_um_parent) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Parent UM not found'));
	exit;
}

if ((int) $child->fk_chargement !== (int) $parent->fk_chargement) {
	echo json_encode(array('success' => false, 'error' => 'Different chargements'));
	exit;
}

$chg = new Chargement($db);
if ($chg->fetch($child->fk_chargement) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Chargement not found'));
	exit;
}
if ($chg->statut != Chargement::STATUS_DRAFT) {
	echo json_encode(array('success' => false, 'error' => 'Chargement is not in draft status'));
	exit;
}

// Parent must be a root (no multi-level stacking)
if (!empty($parent->fk_um_parent)) {
	echo json_encode(array('success' => false, 'error' => 'ParentIsChild'));
	exit;
}

// Parent must be placed
if ($parent->pos_x === null || $parent->pos_x === '' || $parent->pos_y === null || $parent->pos_y === '') {
	echo json_encode(array('success' => false, 'error' => 'ParentNotPlaced'));
	exit;
}

// Parent UmType must be gerbable
$parent_ut = new UmType($db);
if ($parent_ut->fetch((int) $parent->fk_um_type) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Parent UmType not found'));
	exit;
}
if (empty($parent_ut->gerbable)) {
	echo json_encode(array('success' => false, 'error' => 'NotGerbable'));
	exit;
}

// Child must not itself have children (one level deep only)
$sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."planchargement_um";
$sql .= " WHERE fk_um_parent = ".((int) $fk_um);
$resql = $db->query($sql);
if ($resql) {
	$obj = $db->fetch_object($resql);
	if ($obj && (int) $obj->nb > 0) {
		$db->free($resql);
		echo json_encode(array('success' => false, 'error' => 'ChildHasChildren'));
		exit;
	}
	$db->free($resql);
}

// Child must fit within parent's footprint (accounting for rotation on both)
$child_ut = new UmType($db);
if ($child_ut->fetch((int) $child->fk_um_type) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Child UmType not found'));
	exit;
}

$p_rot = (int) $parent->rotation;
$p_len = ($p_rot === 90) ? (int) $parent_ut->largeur  : (int) $parent_ut->longueur;
$p_wid = ($p_rot === 90) ? (int) $parent_ut->longueur : (int) $parent_ut->largeur;

$c_rot = (int) $child->rotation;
$c_len = ($c_rot === 90) ? (int) $child_ut->largeur  : (int) $child_ut->longueur;
$c_wid = ($c_rot === 90) ? (int) $child_ut->longueur : (int) $child_ut->largeur;

if ($c_len > $p_len || $c_wid > $p_wid) {
	echo json_encode(array('success' => false, 'error' => 'ChildTooBig'));
	exit;
}

// All checks passed: persist. Child inherits parent's position so the side
// view projection is aligned with the parent's column.
$sql = "UPDATE ".MAIN_DB_PREFIX."planchargement_um";
$sql .= " SET fk_um_parent = ".((int) $fk_um_parent);
$sql .= ", pos_x = ".((int) $parent->pos_x);
$sql .= ", pos_y = ".((int) $parent->pos_y);
$sql .= " WHERE rowid = ".((int) $fk_um);

if (!$db->query($sql)) {
	echo json_encode(array('success' => false, 'error' => $db->lasterror()));
	exit;
}

echo json_encode(array(
	'success'       => true,
	'fk_um'         => (int) $fk_um,
	'fk_um_parent'  => (int) $fk_um_parent,
));
