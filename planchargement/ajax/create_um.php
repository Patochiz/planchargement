<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/create_um.php
 * \ingroup planchargement
 * \brief   AJAX endpoint to create a new UM in a loading plan
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

require_once '../../main.inc.php';
dol_include_once('/planchargement/class/chargement.class.php');

header('Content-Type: application/json');

if (!$user->hasRight('planchargement', 'write')) {
	echo json_encode(array('success' => false, 'error' => 'AccessDenied'));
	exit;
}

$fk_chargement = GETPOSTINT('fk_chargement');
$fk_um_type = GETPOSTINT('fk_um_type');

if ($fk_chargement <= 0 || $fk_um_type <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Missing fk_chargement or fk_um_type'));
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

$um_id = $chg->addUm($fk_um_type);
if ($um_id > 0) {
	echo json_encode(array(
		'success' => true,
		'um_id' => $um_id,
	));
} else {
	echo json_encode(array('success' => false, 'error' => $chg->error));
}
