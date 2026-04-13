<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    plan_pdf.php
 * \ingroup planchargement
 * \brief   Generate an A4-landscape PDF of the loading plan: top view,
 *          upper and lower side views, stats bar (placed/total, weight, MPL).
 *          Mirrors the structure of tpl/plan.tpl.php.
 */

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

dol_include_once('/planchargement/class/chargement.class.php');
dol_include_once('/planchargement/class/chargementum.class.php');
dol_include_once('/planchargement/class/camiontype.class.php');
dol_include_once('/planchargement/class/umtype.class.php');

$langs->loadLangs(array('planchargement@planchargement', 'main', 'orders'));

if (!$user->hasRight('planchargement', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
if ($id <= 0) {
	print 'Missing id';
	exit;
}

$object = new Chargement($db);
if ($object->fetch($id) <= 0) {
	print 'Chargement not found';
	exit;
}
$object->fetchUms();
foreach ($object->lines as $um) {
	$um->fetchColis();
}

// Truck type
$ct = new CamionType($db);
$truck_len = 0;
$truck_wid = 0;
$truck_hei = 0;
$charge_utile = 0;
if ($ct->fetch($object->fk_camion_type) > 0) {
	$truck_len    = (int) $ct->longueur_utile;
	$truck_wid    = (int) $ct->largeur_utile;
	$truck_hei    = (int) $ct->hauteur_utile;
	$charge_utile = (float) $ct->charge_utile;
}

if ($truck_len <= 0 || $truck_wid <= 0) {
	print $langs->trans('PlanchargementPlanNoTruckDims');
	exit;
}

// UM type catalog (standard + this chargement's customs)
$umtype_obj = new UmType($db);
$umtype_map = array();
$catalog = $umtype_obj->fetchAll('ASC', 'label');
if (is_array($catalog)) {
	foreach ($catalog as $ut) {
		$umtype_map[$ut->id] = $ut;
	}
}
$customs = $umtype_obj->fetchAll('ASC', 'label', 0, 0, 'is_custom = 1 AND fk_chargement_origin = '.((int) $object->id), 'AND', true);
if (is_array($customs)) {
	foreach ($customs as $ut) {
		$umtype_map[$ut->id] = $ut;
	}
}

// Color palette per commande (same as composition / plan tab)
$palette = array('#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e');
$commande_colors = array();
$commande_refs = array();
$i = 0;
foreach ($object->commandes as $fk_cmd) {
	$commande_colors[$fk_cmd] = $palette[$i % count($palette)];
	$cmd = new Commande($db);
	if ($cmd->fetch($fk_cmd) > 0) {
		$commande_refs[$fk_cmd] = $cmd->ref;
	}
	$i++;
}

// Dominant commande per UM (first colis)
$um_commande = array();
foreach ($object->lines as $um) {
	if (!empty($um->colis)) {
		$um_commande[$um->id] = (int) $um->colis[0]->fk_commande;
	}
}

// rowid -> UM lookup (for parent height in side view)
$um_by_id = array();
foreach ($object->lines as $um) {
	$um_by_id[(int) $um->id] = $um;
}

$nb_um_total = count($object->lines);
$nb_um_placed = 0;
foreach ($object->lines as $um) {
	if ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '') {
		$nb_um_placed++;
	}
}

// Parse #rrggbb -> array(R, G, B)
$hex_to_rgb = function ($hex) {
	$hex = ltrim($hex, '#');
	if (strlen($hex) !== 6) {
		return array(149, 165, 166);
	}
	return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
};

// MPL (linear floor meters) per lane — same algorithm as plan.tpl.php
$side_half_limit = $truck_wid / 2;
$intervals_upper = array();
$intervals_lower = array();
foreach ($object->lines as $um) {
	$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
	if (!$ut) {
		continue;
	}
	$is_placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
	if (!$is_placed || !empty($um->fk_um_parent)) {
		continue;
	}
	$rot = (int) $um->rotation;
	$u_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
	$u_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;
	$x1 = (int) $um->pos_x;
	$x2 = $x1 + $u_len;
	$y1 = (int) $um->pos_y;
	$y2 = $y1 + $u_wid;
	$interval = array($x1, $x2);
	if ($y1 < $side_half_limit) {
		$intervals_upper[] = $interval;
	}
	if ($y2 > $side_half_limit) {
		$intervals_lower[] = $interval;
	}
}
$union_length = function ($intervals) {
	if (empty($intervals)) {
		return 0;
	}
	usort($intervals, function ($a, $b) {
		return $a[0] - $b[0];
	});
	$total = 0;
	$cs = $intervals[0][0];
	$ce = $intervals[0][1];
	for ($i = 1; $i < count($intervals); $i++) {
		if ($intervals[$i][0] <= $ce) {
			if ($intervals[$i][1] > $ce) {
				$ce = $intervals[$i][1];
			}
		} else {
			$total += $ce - $cs;
			$cs = $intervals[$i][0];
			$ce = $intervals[$i][1];
		}
	}
	$total += $ce - $cs;
	return $total;
};
$mpl_upper_mm = $union_length($intervals_upper);
$mpl_lower_mm = $union_length($intervals_lower);
$mpl_avg_mm   = ($mpl_upper_mm + $mpl_lower_mm) / 2;

// ====================================================================
// PDF GENERATION
// ====================================================================
$pdf = pdf_getInstance(array(297, 210), 'mm', 'A4');
$pdf->SetCreator('Dolibarr '.DOL_VERSION);
$pdf->SetAuthor(empty($mysoc->name) ? '' : $mysoc->name);
$pdf->SetTitle($langs->trans('PlanchargementChargement').' '.$object->ref);
$pdf->SetAutoPageBreak(0, 0);
$pdf->AddPage('L', 'A4');
$pdf->SetMargins(10, 10, 10);

$page_w = 297;
$page_h = 210;
$margin = 10;
$usable_w = $page_w - 2 * $margin;   // 277 mm
$usable_h = $page_h - 2 * $margin;   // 190 mm

// ---- Header block ----
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetXY($margin, $margin);
$pdf->Cell($usable_w, 7, $langs->trans('PlanchargementChargement').' : '.$object->ref, 0, 1, 'L');

$pdf->SetFont('helvetica', '', 9);
$header_y = $pdf->GetY() + 1;
$pdf->SetXY($margin, $header_y);
$date_str = $object->date_chargement > 0 ? dol_print_date($object->date_chargement, 'day') : '-';
$cols = array();
$cols[] = $langs->trans('PlanchargementDateChargement').' : '.$date_str;
$cols[] = $langs->trans('PlanchargementCamionType').' : '.(empty($ct->ref) ? '-' : $ct->ref);
$cols[] = $langs->trans('PlanchargementPlanTruckDims').' : '.$truck_len.' x '.$truck_wid.' x '.$truck_hei.' mm';
$pdf->Cell($usable_w, 5, implode('     |     ', $cols), 0, 1, 'L');

// ---- Stats bar ----
$pdf->SetFont('helvetica', '', 9);
$stats_y = $pdf->GetY() + 1;
$pdf->SetXY($margin, $stats_y);
$weight_str = price2num($object->poids_total, 'MT').' kg';
if ($charge_utile > 0) {
	$weight_str .= ' / '.price2num($charge_utile, 'MT').' kg';
}
$mpl_str = number_format($mpl_avg_mm / 1000, 2).' m '
	.'('.$langs->trans('PlanchargementPlanMplUpper').' '.number_format($mpl_upper_mm / 1000, 2).' m'
	.' / '.$langs->trans('PlanchargementPlanMplLower').' '.number_format($mpl_lower_mm / 1000, 2).' m)';
$stats = array();
$stats[] = $nb_um_placed.'/'.$nb_um_total.' '.$langs->trans('PlanchargementPlanUmPlaced');
$stats[] = $langs->trans('Weight').' : '.$weight_str;
$stats[] = $langs->trans('PlanchargementPlanMpl').' : '.$mpl_str;
$pdf->SetFillColor(248, 249, 250);
$pdf->SetDrawColor(222, 226, 230);
$pdf->Rect($margin, $stats_y, $usable_w, 6, 'DF');
$pdf->SetXY($margin + 2, $stats_y + 0.5);
$pdf->Cell($usable_w - 4, 5, implode('     -     ', $stats), 0, 1, 'L');

// ---- Drawing area ----
$draw_top    = $stats_y + 6 + 4; // below stats + gap
$draw_bottom = $page_h - $margin - 4;
$draw_h_max  = $draw_bottom - $draw_top;
$draw_w_max  = $usable_w - 10; // reserve left margin for ruler / labels

// Compute scale so top view + 2 side views (when hei > 0) fit.
$gap_views   = 4;    // mm between the 3 views
$nb_side     = ($truck_hei > 0) ? 2 : 0;
$needed_mm_h = $truck_wid + $nb_side * $truck_hei;
$needed_mm_w = $truck_len;
$avail_h     = $draw_h_max - $nb_side * $gap_views;
$scale_w     = $draw_w_max / $needed_mm_w;
$scale_h     = ($needed_mm_h > 0) ? ($avail_h / $needed_mm_h) : $scale_w;
$scale       = min($scale_w, $scale_h);

$top_w_mm    = $truck_len * $scale;
$top_h_mm    = $truck_wid * $scale;
$side_h_mm   = $truck_hei * $scale;

// Horizontal centering in usable area
$draw_x = $margin + 5 + (($draw_w_max - $top_w_mm) / 2);

$y_cursor = $draw_top;

// Draw the grid helper: returns after drawing rect + grid inside.
$draw_grid = function ($pdf, $x, $y, $w, $h) use ($scale) {
	// Background
	$pdf->SetFillColor(236, 240, 241);
	$pdf->SetDrawColor(44, 62, 80);
	$pdf->SetLineWidth(0.3);
	$pdf->Rect($x, $y, $w, $h, 'DF');
	// 1m grid
	$grid_mm_pdf = 1000 * $scale;
	if ($grid_mm_pdf > 0.5) {
		$pdf->SetDrawColor(180, 190, 200);
		$pdf->SetLineWidth(0.1);
		for ($gx = $grid_mm_pdf; $gx < $w; $gx += $grid_mm_pdf) {
			$pdf->Line($x + $gx, $y, $x + $gx, $y + $h);
		}
		for ($gy = $grid_mm_pdf; $gy < $h; $gy += $grid_mm_pdf) {
			$pdf->Line($x, $y + $gy, $x + $w, $y + $gy);
		}
	}
	$pdf->SetDrawColor(44, 62, 80);
	$pdf->SetLineWidth(0.3);
};

// Draw a UM rectangle with centered label, white text on color fill.
$draw_um_rect = function ($pdf, $x, $y, $w, $h, $rgb, $label) {
	$pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
	$pdf->SetDrawColor(44, 62, 80);
	$pdf->SetLineWidth(0.2);
	$pdf->Rect($x, $y, $w, $h, 'DF');
	if ($w < 6 || $h < 3) {
		return;
	}
	$pdf->SetTextColor(255, 255, 255);
	$font_size = max(5, min(8, (int) floor($h * 2)));
	$pdf->SetFont('helvetica', 'B', $font_size);
	$pdf->SetXY($x, $y + ($h - ($font_size / 3)) / 2 - 0.5);
	$pdf->Cell($w, $font_size / 3, $label, 0, 0, 'C');
	$pdf->SetTextColor(0, 0, 0);
};

// ---- UPPER SIDE VIEW ----
if ($truck_hei > 0) {
	$pdf->SetFont('helvetica', 'B', 8);
	$pdf->SetTextColor(44, 62, 80);
	$pdf->SetXY($draw_x, $y_cursor - 3);
	$pdf->Cell($top_w_mm, 3, $langs->trans('PlanchargementPlanSideViewUpper'), 0, 0, 'L');
	$draw_grid($pdf, $draw_x, $y_cursor, $top_w_mm, $side_h_mm);

	foreach ($object->lines as $um) {
		$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
		if (!$ut) {
			continue;
		}
		$placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
		if (!$placed) {
			continue;
		}
		$rot   = (int) $um->rotation;
		$u_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
		$u_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;
		$center_y = (int) $um->pos_y + ($u_wid / 2);
		if ($center_y >= $side_half_limit) {
			continue;
		}
		$bottom = 0;
		if (!empty($um->fk_um_parent) && isset($um_by_id[(int) $um->fk_um_parent])) {
			$parent_um = $um_by_id[(int) $um->fk_um_parent];
			$parent_ut = isset($umtype_map[$parent_um->fk_um_type]) ? $umtype_map[$parent_um->fk_um_type] : null;
			if ($parent_ut) {
				$bottom = (int) $parent_ut->hauteur * $scale;
			}
		}
		$rx = $draw_x + ((int) $um->pos_x) * $scale;
		$ry = $y_cursor + $side_h_mm - $bottom - ((int) $ut->hauteur) * $scale;
		$rw = $u_len * $scale;
		$rh = ((int) $ut->hauteur) * $scale;
		$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
		$color = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';
		$draw_um_rect($pdf, $rx, $ry, $rw, $rh, $hex_to_rgb($color), $um->ref_um);
	}
	$y_cursor += $side_h_mm + $gap_views;
}

// ---- TOP VIEW ----
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(44, 62, 80);
$pdf->SetXY($draw_x, $y_cursor - 3);
$pdf->Cell($top_w_mm, 3, $langs->trans('PlanchargementPlanTopView'), 0, 0, 'L');
// Axis labels at left/right
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(127, 140, 141);
$pdf->SetXY($draw_x - 8, $y_cursor + ($top_h_mm / 2) - 1.5);
$pdf->Cell(7, 3, $langs->trans('PlanchargementPlanTablier'), 0, 0, 'R');
$pdf->SetXY($draw_x + $top_w_mm + 1, $y_cursor + ($top_h_mm / 2) - 1.5);
$pdf->Cell(7, 3, $langs->trans('PlanchargementPlanPorte'), 0, 0, 'L');

$draw_grid($pdf, $draw_x, $y_cursor, $top_w_mm, $top_h_mm);

// Draw each placed (non-child) UM
foreach ($object->lines as $um) {
	$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
	if (!$ut) {
		continue;
	}
	$placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
	if (!$placed || !empty($um->fk_um_parent)) {
		continue;
	}
	$rot   = (int) $um->rotation;
	$u_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
	$u_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;
	$rx = $draw_x + ((int) $um->pos_x) * $scale;
	$ry = $y_cursor + ((int) $um->pos_y) * $scale;
	$rw = $u_len * $scale;
	$rh = $u_wid * $scale;
	$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
	$color = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';
	$draw_um_rect($pdf, $rx, $ry, $rw, $rh, $hex_to_rgb($color), $um->ref_um);
}
$y_cursor += $top_h_mm + $gap_views;

// ---- LOWER SIDE VIEW ----
if ($truck_hei > 0) {
	$pdf->SetFont('helvetica', 'B', 8);
	$pdf->SetTextColor(44, 62, 80);
	$pdf->SetXY($draw_x, $y_cursor - 3);
	$pdf->Cell($top_w_mm, 3, $langs->trans('PlanchargementPlanSideViewLower'), 0, 0, 'L');
	$draw_grid($pdf, $draw_x, $y_cursor, $top_w_mm, $side_h_mm);

	foreach ($object->lines as $um) {
		$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
		if (!$ut) {
			continue;
		}
		$placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
		if (!$placed) {
			continue;
		}
		$rot   = (int) $um->rotation;
		$u_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
		$u_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;
		$center_y = (int) $um->pos_y + ($u_wid / 2);
		if ($center_y < $side_half_limit) {
			continue;
		}
		$bottom = 0;
		if (!empty($um->fk_um_parent) && isset($um_by_id[(int) $um->fk_um_parent])) {
			$parent_um = $um_by_id[(int) $um->fk_um_parent];
			$parent_ut = isset($umtype_map[$parent_um->fk_um_type]) ? $umtype_map[$parent_um->fk_um_type] : null;
			if ($parent_ut) {
				$bottom = (int) $parent_ut->hauteur * $scale;
			}
		}
		$rx = $draw_x + ((int) $um->pos_x) * $scale;
		$ry = $y_cursor + $side_h_mm - $bottom - ((int) $ut->hauteur) * $scale;
		$rw = $u_len * $scale;
		$rh = ((int) $ut->hauteur) * $scale;
		$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
		$color = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';
		$draw_um_rect($pdf, $rx, $ry, $rw, $rh, $hex_to_rgb($color), $um->ref_um);
	}
}

// ---- Footer: legend commande -> color, page number ----
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(80, 80, 80);
$legend_y = $page_h - $margin - 3;
$pdf->SetXY($margin, $legend_y);
$legend_parts = array();
foreach ($commande_refs as $fk => $ref) {
	$rgb = $hex_to_rgb($commande_colors[$fk]);
	$legend_parts[] = array('rgb' => $rgb, 'text' => $ref);
}
$lx = $margin;
foreach ($legend_parts as $lp) {
	$pdf->SetFillColor($lp['rgb'][0], $lp['rgb'][1], $lp['rgb'][2]);
	$pdf->Rect($lx, $legend_y, 3, 3, 'F');
	$pdf->SetXY($lx + 4, $legend_y - 0.5);
	$pdf->Cell(30, 3, $lp['text'], 0, 0, 'L');
	$lx += 4 + 30;
	if ($lx > $page_w - $margin - 35) {
		break;
	}
}
$pdf->SetXY($page_w - $margin - 30, $legend_y);
$pdf->Cell(30, 3, 'Page 1/1', 0, 0, 'R');

// Output
$filename = 'plan_'.dol_sanitizeFileName($object->ref).'.pdf';
$pdf->Output($filename, 'I');
