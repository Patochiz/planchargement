<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    tpl/plan.tpl.php
 * \ingroup planchargement
 * \brief   Loading plan tab - top-down and split side views of the truck
 *          with draggable UMs. Supports interactive stacking (gerbage).
 *
 * Expected variables: $object (Chargement, already fetched), $db, $langs, $user
 */

dol_include_once('/planchargement/class/camiontype.class.php');
dol_include_once('/planchargement/class/umtype.class.php');
dol_include_once('/colisage/class/colisagepackage.class.php');

// Fetch data
$object->fetchUms();
foreach ($object->lines as $um) {
	$um->fetchColis();
}

// Load truck type (dimensions in mm)
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

// Load UM types (including this chargement's customs)
$umtype_obj = new UmType($db);
$umtypes_catalog = $umtype_obj->fetchAll('ASC', 'label');
$umtype_map = array();
if (is_array($umtypes_catalog)) {
	foreach ($umtypes_catalog as $ut) {
		$umtype_map[$ut->id] = $ut;
	}
}
$custom_filter = 'is_custom = 1 AND fk_chargement_origin = '.((int) $object->id);
$custom_types = $umtype_obj->fetchAll('ASC', 'label', 0, 0, $custom_filter, 'AND', true);
if (is_array($custom_types)) {
	foreach ($custom_types as $ut) {
		$umtype_map[$ut->id] = $ut;
	}
}

// Color palette by commande (same as composition tab)
$colors = array('#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e');
$commande_colors = array();
$i = 0;
foreach ($object->commandes as $fk_cmd) {
	$commande_colors[$fk_cmd] = $colors[$i % count($colors)];
	$i++;
}

// Commande refs for labels
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
$commande_refs = array();
foreach ($object->commandes as $fk_cmd) {
	$cmd = new Commande($db);
	if ($cmd->fetch($fk_cmd) > 0) {
		$commande_refs[$fk_cmd] = $cmd->ref;
	}
}

// Per-UM dominant commande (first colis' commande)
$um_commande = array();
foreach ($object->lines as $um) {
	if (!empty($um->colis)) {
		$um_commande[$um->id] = (int) $um->colis[0]->fk_commande;
	}
}

// Map rowid -> UM (O(1) lookup for parent) and rowid -> list of children
$um_by_id = array();
foreach ($object->lines as $um) {
	$um_by_id[(int) $um->id] = $um;
}
$children_by_parent = array();
foreach ($object->lines as $um) {
	if (!empty($um->fk_um_parent)) {
		$pid = (int) $um->fk_um_parent;
		if (!isset($children_by_parent[$pid])) {
			$children_by_parent[$pid] = array();
		}
		$children_by_parent[$pid][] = $um;
	}
}

$is_draft = ($object->statut == Chargement::STATUS_DRAFT);
$nb_um_total = count($object->lines);
$nb_um_placed = 0;
foreach ($object->lines as $um) {
	if ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '') {
		$nb_um_placed++;
	}
}

// Scale: top view uses full available width, ~900 px for rendering
$view_width_px = 900;
$scale = ($truck_len > 0) ? ($view_width_px / $truck_len) : 0.1;
$top_h_px  = ($truck_wid > 0) ? (int) round($truck_wid * $scale) : 0;
$side_h_px = ($truck_hei > 0) ? (int) round($truck_hei * $scale) : 0;
$top_w_px  = ($truck_len > 0) ? (int) round($truck_len * $scale) : $view_width_px;

// Grid (1 m × 1 m) drawn as a background gradient on the top/side views
$grid_mm = 1000;
$grid_px = $scale * $grid_mm;
$grid_bg = "background-color:#ecf0f1;"
	." background-image:"
	." repeating-linear-gradient(90deg, rgba(44,62,80,0.18) 0 1px, transparent 1px ".$grid_px."px),"
	." repeating-linear-gradient(0deg,  rgba(44,62,80,0.18) 0 1px, transparent 1px ".$grid_px."px);";

// Snap-to-grid step (mm). 100 mm = 10 cm feels right for pallet placement.
$snap_mm = 100;

if ($truck_len <= 0 || $truck_wid <= 0) {
	print '<div class="opacitymedium" style="padding: 20px;">';
	print $langs->trans('PlanchargementPlanNoTruckDims');
	print '</div>';
	return;
}

// Pre-compute render data for every placed UM for the side views, and split
// by Y half (upper / lower) so each side view only projects its own half of
// the truck. A UM's half is decided by its center Y; children inherit from
// their parent's Y (same as theirs since we set child.pos_y = parent.pos_y).
$side_half_limit = $truck_wid / 2;
$side_renderables_upper = array();
$side_renderables_lower = array();
foreach ($object->lines as $um) {
	$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
	if (!$ut) {
		continue;
	}
	$is_placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
	if (!$is_placed) {
		continue;
	}
	$rot   = (int) $um->rotation;
	$u_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
	$u_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;

	$left = (int) round(((int) $um->pos_x) * $scale);
	$w    = (int) round($u_len * $scale);
	$h    = (int) round(((int) $ut->hauteur) * $scale);

	// From the bottom of the side view (floor)
	$bottom = 0;
	if (!empty($um->fk_um_parent) && isset($um_by_id[(int) $um->fk_um_parent])) {
		$parent_um = $um_by_id[(int) $um->fk_um_parent];
		$parent_ut = isset($umtype_map[$parent_um->fk_um_type]) ? $umtype_map[$parent_um->fk_um_type] : null;
		if ($parent_ut) {
			$bottom = (int) round(((int) $parent_ut->hauteur) * $scale);
		}
	}

	$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
	$color  = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';

	$center_y_mm = (int) $um->pos_y + ($u_wid / 2);
	$entry = array(
		'um'     => $um,
		'ut'     => $ut,
		'left'   => $left,
		'bottom' => $bottom,
		'w'      => $w,
		'h'      => $h,
		'color'  => $color,
	);
	if ($center_y_mm < $side_half_limit) {
		$side_renderables_upper[] = $entry;
	} else {
		$side_renderables_lower[] = $entry;
	}
}

// ------------------------------------------------------------------
// Linear floor meters ("MPL" — Mètres Plancher Linéaires) consumed on
// each lane of the truck, plus the average which is what you usually
// invoice to an affréteur. A UM contributes its [pos_x, pos_x+u_len]
// interval to the upper lane if it intersects [0, wid/2], and to the
// lower lane if it intersects [wid/2, wid] — a UM that straddles the
// split counts on both lanes. Stacked children are skipped because
// they share their parent's X interval.
// ------------------------------------------------------------------
$intervals_upper = array();
$intervals_lower = array();
foreach ($object->lines as $um) {
	$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
	if (!$ut) {
		continue;
	}
	$is_placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
	if (!$is_placed) {
		continue;
	}
	if (!empty($um->fk_um_parent)) {
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
$planchargement_union_length = function ($intervals) {
	if (empty($intervals)) {
		return 0;
	}
	usort($intervals, function ($a, $b) {
		return $a[0] - $b[0];
	});
	$total = 0;
	$cs = $intervals[0][0];
	$ce = $intervals[0][1];
	$n = count($intervals);
	for ($i = 1; $i < $n; $i++) {
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
$mpl_upper_mm = $planchargement_union_length($intervals_upper);
$mpl_lower_mm = $planchargement_union_length($intervals_lower);
$mpl_avg_mm   = ($mpl_upper_mm + $mpl_lower_mm) / 2;

// ------------------------------------------------------------------
// Rough tractor schematics. Drawn to the left of each truck view so
// the reader can tell the trailer's orientation (front = tractor side,
// rear = door side). Inline SVG, viewBox 240×100, ratio preserved,
// right-aligned so the tractor's rear/hitch touches the trailer.
// ------------------------------------------------------------------
$tractor_top_svg = '<svg viewBox="0 0 240 100" preserveAspectRatio="xMaxYMid meet" width="100%" height="100%" aria-hidden="true">'
	.'<rect x="20" y="35" width="210" height="30" fill="#95a5a6" stroke="#2c3e50" stroke-width="1.5"/>'
	.'<rect x="20" y="10" width="75" height="80" fill="#34495e" stroke="#2c3e50" stroke-width="1.5"/>'
	.'<line x1="30" y1="20" x2="90" y2="20" stroke="#bdc3c7" stroke-width="1.5"/>'
	.'<line x1="30" y1="80" x2="90" y2="80" stroke="#bdc3c7" stroke-width="1.5"/>'
	.'<rect x="35" y="0" width="18" height="14" fill="#2c3e50"/>'
	.'<rect x="35" y="86" width="18" height="14" fill="#2c3e50"/>'
	.'<rect x="140" y="0" width="18" height="14" fill="#2c3e50"/>'
	.'<rect x="140" y="86" width="18" height="14" fill="#2c3e50"/>'
	.'<rect x="165" y="0" width="18" height="14" fill="#2c3e50"/>'
	.'<rect x="165" y="86" width="18" height="14" fill="#2c3e50"/>'
	.'<circle cx="205" cy="50" r="8" fill="#7f8c8d" stroke="#2c3e50" stroke-width="1.5"/>'
	.'</svg>';

$tractor_side_svg = '<svg viewBox="0 0 240 100" preserveAspectRatio="xMaxYMax meet" width="100%" height="100%" aria-hidden="true">'
	.'<rect x="40" y="62" width="190" height="12" fill="#95a5a6" stroke="#2c3e50" stroke-width="1.5"/>'
	.'<polygon points="5,75 5,45 50,45 60,22 105,22 105,75" fill="#34495e" stroke="#2c3e50" stroke-width="1.5"/>'
	.'<line x1="52" y1="45" x2="60" y2="25" stroke="#bdc3c7" stroke-width="1.5"/>'
	.'<circle cx="30" cy="82" r="13" fill="#2c3e50"/>'
	.'<circle cx="30" cy="82" r="5" fill="#7f8c8d"/>'
	.'<circle cx="170" cy="82" r="13" fill="#2c3e50"/>'
	.'<circle cx="170" cy="82" r="5" fill="#7f8c8d"/>'
	.'<circle cx="200" cy="82" r="13" fill="#2c3e50"/>'
	.'<circle cx="200" cy="82" r="5" fill="#7f8c8d"/>'
	.'<line x1="0" y1="95" x2="240" y2="95" stroke="#7f8c8d" stroke-width="1"/>'
	.'</svg>';
?>

<div class="planchargement-plan-wrapper">

	<!-- Stats bar -->
	<div class="plan-statsbar">
		<span class="stat">
			<strong><?php echo $nb_um_placed; ?>/<?php echo $nb_um_total; ?></strong>
			<?php echo $langs->trans('PlanchargementPlanUmPlaced'); ?>
		</span>
		<span class="stat">
			<strong><?php echo price2num($object->poids_total, 'MT'); ?> kg</strong>
			<?php if ($charge_utile > 0) { ?>
				/ <?php echo price2num($charge_utile, 'MT'); ?> kg
			<?php } ?>
		</span>
		<span class="stat">
			<?php echo $langs->trans('PlanchargementPlanTruckDims'); ?>:
			<strong><?php echo (int) $truck_len; ?> &times; <?php echo (int) $truck_wid; ?> &times; <?php echo (int) $truck_hei; ?> mm</strong>
		</span>
		<span class="stat stat-mpl" title="<?php echo dol_escape_htmltag($langs->trans('PlanchargementPlanMplHint')); ?>">
			<?php echo $langs->trans('PlanchargementPlanMpl'); ?>:
			<strong><?php echo number_format($mpl_avg_mm / 1000, 2); ?> m</strong>
			<span class="mpl-detail">
				(<?php echo $langs->trans('PlanchargementPlanMplUpper'); ?>
				<?php echo number_format($mpl_upper_mm / 1000, 2); ?> m
				/ <?php echo $langs->trans('PlanchargementPlanMplLower'); ?>
				<?php echo number_format($mpl_lower_mm / 1000, 2); ?> m)
			</span>
		</span>
	</div>

	<!-- UPPER SIDE VIEW (UMs in the upper half of the top view) -->
	<?php if ($truck_hei > 0) { ?>
	<div class="plan-view-block">
		<div class="plan-view-label"><?php echo $langs->trans('PlanchargementPlanSideViewUpper'); ?></div>
		<div class="plan-truck-side"
			style="width: <?php echo $top_w_px; ?>px; height: <?php echo $side_h_px; ?>px; <?php echo $grid_bg; ?>">
			<?php foreach ($side_renderables_upper as $r) { ?>
				<div class="plan-um-side"
					style="left: <?php echo $r['left']; ?>px; bottom: <?php echo $r['bottom']; ?>px; width: <?php echo $r['w']; ?>px; height: <?php echo $r['h']; ?>px; background-color: <?php echo $r['color']; ?>;"
					title="<?php echo dol_escape_htmltag($r['um']->ref_um.' - H '.(int) $r['ut']->hauteur.' mm'); ?>">
					<span class="plan-um-ref"><?php echo dol_escape_htmltag($r['um']->ref_um); ?></span>
				</div>
			<?php } ?>
		</div>
		<div class="plan-tractor plan-tractor-side" style="height: <?php echo $side_h_px; ?>px;"><?php echo $tractor_side_svg; ?></div>
	</div>
	<?php } ?>

	<!-- TOP VIEW -->
	<div class="plan-view-block">
	<div class="plan-view-label"><?php echo $langs->trans('PlanchargementPlanTopView'); ?></div>
	<div class="plan-axis-label plan-axis-front"><?php echo $langs->trans('PlanchargementPlanTablier'); ?></div>
	<div class="plan-axis-label plan-axis-back"><?php echo $langs->trans('PlanchargementPlanPorte'); ?></div>

	<!-- Ruler: one label per meter of truck length, aligned with the 1 m grid -->
	<div class="plan-ruler" style="width: <?php echo $top_w_px; ?>px;">
		<?php
		$nb_m_full = (int) floor($truck_len / 1000);
		for ($m = 0; $m <= $nb_m_full; $m++) {
			$rleft = (int) round($m * 1000 * $scale);
			$rcls  = 'plan-ruler-label';
			if ($m === 0) {
				$rcls .= ' plan-ruler-label-start';
			}
			?>
			<span class="<?php echo $rcls; ?>" style="left: <?php echo $rleft; ?>px;"><?php echo $m; ?> m</span>
			<?php
		}
		if (($truck_len % 1000) !== 0) {
			$rem_m = rtrim(rtrim(number_format($truck_len / 1000, 2, '.', ''), '0'), '.');
			?>
			<span class="plan-ruler-label plan-ruler-label-end" style="left: <?php echo $top_w_px; ?>px;"><?php echo $rem_m; ?> m</span>
			<?php
		}
		?>
	</div>

	<div class="plan-truck-top<?php echo $is_draft ? ' plan-editable' : ''; ?>"
		id="plan-truck-top"
		data-truck-len="<?php echo (int) $truck_len; ?>"
		data-truck-wid="<?php echo (int) $truck_wid; ?>"
		data-scale="<?php echo (float) $scale; ?>"
		data-snap-mm="<?php echo (int) $snap_mm; ?>"
		style="width: <?php echo $top_w_px; ?>px; height: <?php echo $top_h_px; ?>px; <?php echo $grid_bg; ?>">
		<div id="plan-drop-ghost" class="plan-drop-ghost"></div>
		<?php
		foreach ($object->lines as $um) {
			$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
			if (!$ut) {
				continue;
			}
			$is_placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
			if (!$is_placed) {
				continue;
			}
			// Stacked children are visualized via the side views and via a
			// chip on the parent card — not as a separate top-view rect.
			if (!empty($um->fk_um_parent)) {
				continue;
			}
			$rot = (int) $um->rotation;
			$um_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
			$um_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;
			$left = (int) round(((int) $um->pos_x) * $scale);
			$top  = (int) round(((int) $um->pos_y) * $scale);
			$w    = (int) round($um_len * $scale);
			$h    = (int) round($um_wid * $scale);
			$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
			$color = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';
			$cmd_label = isset($commande_refs[$cmd_id]) ? $commande_refs[$cmd_id] : '';

			$is_gerbable = !empty($ut->gerbable) ? 1 : 0;
			$my_children = isset($children_by_parent[(int) $um->id]) ? $children_by_parent[(int) $um->id] : array();
			$nb_children = count($my_children);

			$tooltip = $um->ref_um.' - '.$ut->label.($cmd_label !== '' ? ' ('.$cmd_label.')' : '');
			if ($is_draft) {
				$tooltip .= "\n".$langs->trans('PlanchargementPlanRotateHint');
				if ($is_gerbable) {
					$tooltip .= "\n".$langs->trans('PlanchargementPlanStackHint');
				}
			}
			?>
			<div class="plan-um<?php echo $nb_children > 0 ? ' has-stack' : ''; ?>"
				data-um-id="<?php echo (int) $um->id; ?>"
				data-um-len="<?php echo (int) $um_len; ?>"
				data-um-wid="<?php echo (int) $um_wid; ?>"
				data-um-hei="<?php echo (int) $ut->hauteur; ?>"
				data-um-pos-x="<?php echo (int) $um->pos_x; ?>"
				data-um-pos-y="<?php echo (int) $um->pos_y; ?>"
				data-um-gerbable="<?php echo (int) $is_gerbable; ?>"
				data-um-nb-children="<?php echo (int) $nb_children; ?>"
				<?php if ($is_draft) { ?>draggable="true"<?php } ?>
				style="left: <?php echo $left; ?>px; top: <?php echo $top; ?>px; width: <?php echo $w; ?>px; height: <?php echo $h; ?>px; background-color: <?php echo $color; ?>;"
				title="<?php echo dol_escape_htmltag($tooltip); ?>">
				<span class="plan-um-ref"><?php echo dol_escape_htmltag($um->ref_um); ?></span>
				<?php if ($cmd_label !== '') { ?>
					<span class="plan-um-cmd"><?php echo dol_escape_htmltag($cmd_label); ?></span>
				<?php } ?>
				<?php if ($nb_children > 0) { ?>
					<div class="plan-um-stack-list">
						<?php foreach ($my_children as $child) {
							$child_ut = isset($umtype_map[$child->fk_um_type]) ? $umtype_map[$child->fk_um_type] : null;
							if (!$child_ut) {
								continue;
							}
							$crot = (int) $child->rotation;
							$c_len = ($crot === 90) ? (int) $child_ut->largeur  : (int) $child_ut->longueur;
							$c_wid = ($crot === 90) ? (int) $child_ut->longueur : (int) $child_ut->largeur;
							?>
							<div class="plan-um-stack-chip"
								data-um-id="<?php echo (int) $child->id; ?>"
								data-um-len="<?php echo (int) $c_len; ?>"
								data-um-wid="<?php echo (int) $c_wid; ?>"
								data-um-hei="<?php echo (int) $child_ut->hauteur; ?>"
								<?php if ($is_draft) { ?>draggable="true"<?php } ?>
								title="<?php echo dol_escape_htmltag($child->ref_um.' - '.$child_ut->label); ?>">
								&#8682; <?php echo dol_escape_htmltag($child->ref_um); ?>
							</div>
						<?php } ?>
					</div>
				<?php } ?>
			</div>
			<?php
		}
		?>
	</div>
	<div class="plan-tractor plan-tractor-top" style="height: <?php echo $top_h_px; ?>px;"><?php echo $tractor_top_svg; ?></div>
	</div>
	<!-- /TOP VIEW block -->

	<!-- LOWER SIDE VIEW (UMs in the lower half of the top view) -->
	<?php if ($truck_hei > 0) { ?>
	<div class="plan-view-block">
		<div class="plan-view-label"><?php echo $langs->trans('PlanchargementPlanSideViewLower'); ?></div>
		<div class="plan-truck-side"
			style="width: <?php echo $top_w_px; ?>px; height: <?php echo $side_h_px; ?>px; <?php echo $grid_bg; ?>">
			<?php foreach ($side_renderables_lower as $r) { ?>
				<div class="plan-um-side"
					style="left: <?php echo $r['left']; ?>px; bottom: <?php echo $r['bottom']; ?>px; width: <?php echo $r['w']; ?>px; height: <?php echo $r['h']; ?>px; background-color: <?php echo $r['color']; ?>;"
					title="<?php echo dol_escape_htmltag($r['um']->ref_um.' - H '.(int) $r['ut']->hauteur.' mm'); ?>">
					<span class="plan-um-ref"><?php echo dol_escape_htmltag($r['um']->ref_um); ?></span>
				</div>
			<?php } ?>
		</div>
		<div class="plan-tractor plan-tractor-side" style="height: <?php echo $side_h_px; ?>px;"><?php echo $tractor_side_svg; ?></div>
	</div>
	<?php } ?>

	<!-- OVERFLOW / UNPLACED -->
	<div class="plan-view-label"><?php echo $langs->trans('PlanchargementPlanUnplaced'); ?></div>
	<div class="plan-overflow<?php echo $is_draft ? ' plan-editable' : ''; ?>" id="plan-overflow">
		<?php
		$has_unplaced = false;
		foreach ($object->lines as $um) {
			$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
			if (!$ut) {
				continue;
			}
			$is_placed = ($um->pos_x !== null && $um->pos_x !== '' && $um->pos_y !== null && $um->pos_y !== '');
			if ($is_placed) {
				continue;
			}
			$has_unplaced = true;
			$rot = (int) $um->rotation;
			$um_len = ($rot === 90) ? (int) $ut->largeur  : (int) $ut->longueur;
			$um_wid = ($rot === 90) ? (int) $ut->longueur : (int) $ut->largeur;
			$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
			$color = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';
			$cmd_label = isset($commande_refs[$cmd_id]) ? $commande_refs[$cmd_id] : '';

			$tile_tooltip = $um->ref_um.' - '.$ut->label;
			if ($is_draft) {
				$tile_tooltip .= "\n".$langs->trans('PlanchargementPlanRotateHint');
			}
			?>
			<div class="plan-um-tile"
				data-um-id="<?php echo (int) $um->id; ?>"
				data-um-len="<?php echo (int) $um_len; ?>"
				data-um-wid="<?php echo (int) $um_wid; ?>"
				data-um-hei="<?php echo (int) $ut->hauteur; ?>"
				<?php if ($is_draft) { ?>draggable="true"<?php } ?>
				style="background-color: <?php echo $color; ?>;"
				title="<?php echo dol_escape_htmltag($tile_tooltip); ?>">
				<span class="plan-um-ref"><?php echo dol_escape_htmltag($um->ref_um); ?></span>
				<span class="plan-um-dims"><?php echo (int) $um_len; ?>&times;<?php echo (int) $um_wid; ?></span>
				<?php if ($cmd_label !== '') { ?>
					<span class="plan-um-cmd"><?php echo dol_escape_htmltag($cmd_label); ?></span>
				<?php } ?>
			</div>
			<?php
		}
		if (!$has_unplaced) {
			print '<div class="opacitymedium" style="padding: 10px;">'.$langs->trans('PlanchargementPlanAllPlaced').'</div>';
		}
		?>
	</div>

</div>
