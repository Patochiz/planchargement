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
 * \brief   Loading plan tab - top-down and side views of the truck with
 *          draggable UMs.
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
	</div>

	<!-- TOP VIEW -->
	<div class="plan-view-label"><?php echo $langs->trans('PlanchargementPlanTopView'); ?></div>
	<div class="plan-axis-label plan-axis-front"><?php echo $langs->trans('PlanchargementPlanTablier'); ?></div>
	<div class="plan-axis-label plan-axis-back"><?php echo $langs->trans('PlanchargementPlanPorte'); ?></div>

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
			?>
			<div class="plan-um"
				data-um-id="<?php echo (int) $um->id; ?>"
				data-um-len="<?php echo (int) $um_len; ?>"
				data-um-wid="<?php echo (int) $um_wid; ?>"
				data-um-hei="<?php echo (int) $ut->hauteur; ?>"
				data-um-pos-x="<?php echo (int) $um->pos_x; ?>"
				data-um-pos-y="<?php echo (int) $um->pos_y; ?>"
				<?php if ($is_draft) { ?>draggable="true"<?php } ?>
				style="left: <?php echo $left; ?>px; top: <?php echo $top; ?>px; width: <?php echo $w; ?>px; height: <?php echo $h; ?>px; background-color: <?php echo $color; ?>;"
				title="<?php echo dol_escape_htmltag($um->ref_um.' - '.$ut->label.($cmd_label !== '' ? ' ('.$cmd_label.')' : '')); ?>">
				<span class="plan-um-ref"><?php echo dol_escape_htmltag($um->ref_um); ?></span>
				<?php if ($cmd_label !== '') { ?>
					<span class="plan-um-cmd"><?php echo dol_escape_htmltag($cmd_label); ?></span>
				<?php } ?>
			</div>
			<?php
		}
		?>
	</div>

	<!-- SIDE VIEW (read-only projection onto X-Z plane) -->
	<?php if ($truck_hei > 0) { ?>
	<div class="plan-view-label"><?php echo $langs->trans('PlanchargementPlanSideView'); ?></div>
	<div class="plan-truck-side"
		style="width: <?php echo $top_w_px; ?>px; height: <?php echo $side_h_px; ?>px; <?php echo $grid_bg; ?>">
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
			$rot = (int) $um->rotation;
			$um_len = ($rot === 90) ? (int) $ut->largeur : (int) $ut->longueur;
			$left = (int) round(((int) $um->pos_x) * $scale);
			$w    = (int) round($um_len * $scale);
			$h    = (int) round(((int) $ut->hauteur) * $scale);
			// From the bottom of the side view (floor)
			$bottom = 0;
			if (!empty($um->fk_um_parent)) {
				// Stacked: place it on top of its parent's height
				$parent_ut = null;
				foreach ($object->lines as $parent_um) {
					if ((int) $parent_um->id === (int) $um->fk_um_parent) {
						$parent_ut = isset($umtype_map[$parent_um->fk_um_type]) ? $umtype_map[$parent_um->fk_um_type] : null;
						break;
					}
				}
				if ($parent_ut) {
					$bottom = (int) round(((int) $parent_ut->hauteur) * $scale);
				}
			}
			$cmd_id = isset($um_commande[$um->id]) ? $um_commande[$um->id] : 0;
			$color = isset($commande_colors[$cmd_id]) ? $commande_colors[$cmd_id] : '#95a5a6';
			?>
			<div class="plan-um-side"
				style="left: <?php echo $left; ?>px; bottom: <?php echo $bottom; ?>px; width: <?php echo $w; ?>px; height: <?php echo $h; ?>px; background-color: <?php echo $color; ?>;"
				title="<?php echo dol_escape_htmltag($um->ref_um.' - H '.(int) $ut->hauteur.' mm'); ?>">
				<span class="plan-um-ref"><?php echo dol_escape_htmltag($um->ref_um); ?></span>
			</div>
			<?php
		}
		?>
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
			?>
			<div class="plan-um-tile"
				data-um-id="<?php echo (int) $um->id; ?>"
				data-um-len="<?php echo (int) $um_len; ?>"
				data-um-wid="<?php echo (int) $um_wid; ?>"
				data-um-hei="<?php echo (int) $ut->hauteur; ?>"
				<?php if ($is_draft) { ?>draggable="true"<?php } ?>
				style="background-color: <?php echo $color; ?>;"
				title="<?php echo dol_escape_htmltag($um->ref_um.' - '.$ut->label); ?>">
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
