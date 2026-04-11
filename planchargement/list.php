<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    list.php
 * \ingroup planchargement
 * \brief   List of loading plans
 */

require_once '../../../main.inc.php';
dol_include_once('/planchargement/class/chargement.class.php');
dol_include_once('/planchargement/class/camiontype.class.php');

$langs->loadLangs(array('planchargement@planchargement'));

if (!$user->hasRight('planchargement', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'planchargementlist';

$search_ref = GETPOST('search_ref', 'alpha');
$search_camion_type = GETPOSTINT('search_camion_type');
$search_status = GETPOST('search_status', 'intcomma');
$search_date_start = '';
$search_date_end = '';
if (GETPOSTINT('search_date_startday')) {
	$search_date_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear'));
}
if (GETPOSTINT('search_date_endday')) {
	$search_date_end = dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear'));
}

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

if (!$sortfield) {
	$sortfield = 'c.date_creation';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

/*
 * Actions
 */

if ($action == 'reset') {
	$search_ref = '';
	$search_camion_type = 0;
	$search_status = '';
	$search_date_start = '';
	$search_date_end = '';
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('PlanchargementList'), '', '', 0, 0, '', '', '', 'mod-planchargement page-list');

// Build WHERE clause
$sql_where = ' WHERE 1 = 1';
if ($search_ref) {
	$sql_where .= natural_search('c.ref', $search_ref);
}
if ($search_camion_type > 0) {
	$sql_where .= ' AND c.fk_camion_type = '.((int) $search_camion_type);
}
if ($search_status !== '' && $search_status !== '-1') {
	$sql_where .= ' AND c.statut = '.((int) $search_status);
}
if ($search_date_start) {
	$sql_where .= " AND c.date_chargement >= '".$db->idate($search_date_start)."'";
}
if ($search_date_end) {
	$sql_where .= " AND c.date_chargement <= '".$db->idate($search_date_end)."'";
}

// Count total
$sql_count = 'SELECT COUNT(*) as total';
$sql_count .= ' FROM '.MAIN_DB_PREFIX.'planchargement_chargement as c';
$sql_count .= $sql_where;

$resql = $db->query($sql_count);
$nbtotalofrecords = 0;
if ($resql) {
	$obj = $db->fetch_object($resql);
	$nbtotalofrecords = (int) $obj->total;
}

// Main query
$sql = 'SELECT c.rowid, c.ref, c.date_chargement, c.fk_camion_type, c.poids_total,';
$sql .= ' c.statut, c.date_creation,';
$sql .= ' ct.label as camion_type_label,';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'planchargement_commande pc WHERE pc.fk_chargement = c.rowid) as nb_commandes,';
$sql .= ' (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'planchargement_um um WHERE um.fk_chargement = c.rowid) as nb_ums';
$sql .= ' FROM '.MAIN_DB_PREFIX.'planchargement_chargement as c';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'planchargement_camion_type ct ON ct.rowid = c.fk_camion_type';
$sql .= $sql_where;
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);

if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

// New button
$newbutton = '';
if ($user->hasRight('planchargement', 'write')) {
	$newbutton = '<a class="butAction" href="'.dol_buildpath('/planchargement/card.php', 1).'?action=create">'.$langs->trans('PlanchargementNew').'</a>';
}

print_barre_liste($langs->trans('PlanchargementList'), $page, $_SERVER['PHP_SELF'], '', $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'generic', 0, $newbutton, '', $limit);

// Load truck types for filter select
$camiontype = new CamionType($db);
$camiontypes = $camiontype->fetchAll('ASC', 'label');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="limit" value="'.$limit.'">';

print '<table class="noborder centpercent">';

// Header
print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'c.ref', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('PlanchargementDateChargement', $_SERVER['PHP_SELF'], 'c.date_chargement', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('PlanchargementCamionType', $_SERVER['PHP_SELF'], 'ct.label', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('PlanchargementPoidsTotal', $_SERVER['PHP_SELF'], 'c.poids_total', '', '', 'class="right"', $sortfield, $sortorder);
print_liste_field_titre('PlanchargementNbCommandes', $_SERVER['PHP_SELF'], '', '', '', 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('PlanchargementNbUms', $_SERVER['PHP_SELF'], '', '', '', 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'c.statut', '', '', 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('DateCreation', $_SERVER['PHP_SELF'], 'c.date_creation', '', '', 'class="right"', $sortfield, $sortorder);
print '</tr>';

// Filter row
print '<tr class="liste_titre_filter">';

// Ref
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';

// Date
print '<td class="liste_titre center">';
print $form->selectDate($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print '<br>';
print $form->selectDate($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('To'));
print '</td>';

// Truck type filter
print '<td class="liste_titre">';
print '<select name="search_camion_type" class="flat maxwidth200">';
print '<option value="0">&nbsp;</option>';
if (is_array($camiontypes)) {
	foreach ($camiontypes as $ct) {
		$selected = ($search_camion_type == $ct->id) ? ' selected' : '';
		print '<option value="'.$ct->id.'"'.$selected.'>'.dol_escape_htmltag($ct->label).'</option>';
	}
}
print '</select>';
print '</td>';

// Poids
print '<td class="liste_titre"></td>';

// Nb commandes
print '<td class="liste_titre"></td>';

// Nb UM
print '<td class="liste_titre"></td>';

// Status filter
print '<td class="liste_titre center">';
print '<select name="search_status" class="flat">';
print '<option value="-1">&nbsp;</option>';
print '<option value="'.Chargement::STATUS_DRAFT.'"'.($search_status === '0' ? ' selected' : '').'>'.$langs->trans('PlanchargementStatusDraft').'</option>';
print '<option value="'.Chargement::STATUS_VALID.'"'.($search_status === '1' ? ' selected' : '').'>'.$langs->trans('PlanchargementStatusValid').'</option>';
print '<option value="'.Chargement::STATUS_DEPARTED.'"'.($search_status === '2' ? ' selected' : '').'>'.$langs->trans('PlanchargementStatusDeparted').'</option>';
print '<option value="'.Chargement::STATUS_CANCELLED.'"'.($search_status === '9' ? ' selected' : '').'>'.$langs->trans('PlanchargementStatusCancelled').'</option>';
print '</select>';
print '</td>';

// Date creation
print '<td class="liste_titre"></td>';

print '</tr>';

// Data rows
$i = 0;
$totalarray = array();
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (!$obj) {
		break;
	}

	// Build a Chargement stub for getNomUrl / LibStatut
	$staticchargement = new Chargement($db);
	$staticchargement->id = $obj->rowid;
	$staticchargement->ref = $obj->ref;
	$staticchargement->statut = $obj->statut;

	print '<tr class="oddeven">';

	// Ref
	print '<td class="nowraponall">'.$staticchargement->getNomUrl(1).'</td>';

	// Date chargement
	print '<td>'.dol_print_date($db->jdate($obj->date_chargement), 'day').'</td>';

	// Camion type
	print '<td>'.dol_escape_htmltag($obj->camion_type_label).'</td>';

	// Poids total
	print '<td class="right">'.($obj->poids_total > 0 ? number_format($obj->poids_total, 1, '.', ' ').' kg' : '-').'</td>';

	// Nb commandes
	print '<td class="center">'.$obj->nb_commandes.'</td>';

	// Nb UMs
	print '<td class="center">'.$obj->nb_ums.'</td>';

	// Statut
	print '<td class="center">'.$staticchargement->getLibStatut(5).'</td>';

	// Date creation
	print '<td class="right nowraponall">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';

	print '</tr>';
	$i++;
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="8" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}

print '</table>';

// Search/reset buttons
print '<div class="tabsAction">';
print '<input type="submit" class="button" name="button_search" value="'.$langs->trans('Search').'">';
print '<input type="submit" class="button" name="button_removefilter" value="'.$langs->trans('RemoveFilter').'" onclick="document.getElementsByName(\'action\')[0] || (function(){var h=document.createElement(\'input\');h.type=\'hidden\';h.name=\'action\';h.value=\'reset\';document.forms[0].appendChild(h)})(); document.getElementsByName(\'action\').length ? document.getElementsByName(\'action\')[0].value=\'reset\' : null;">';
print '</div>';

print '</form>';

llxFooter();
$db->close();
