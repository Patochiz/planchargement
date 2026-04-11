<?php
/* Copyright (C) 2024 Patochiz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    tpl/composition.tpl.php
 * \ingroup planchargement
 * \brief   Composition tab template - UM and package assignment
 *
 * Expected variables: $object (Chargement, already fetched), $db, $langs, $user
 */

// Fetch data
$object->fetchUms();
$colis_libres = $object->getColisNonAffectes();
if (!is_array($colis_libres)) {
	$colis_libres = array();
}

// Fetch colis for each UM
foreach ($object->lines as $um) {
	$um->fetchColis();
}

// Load UM types for labels
$umtype_obj = new UmType($db);
$umtypes = $umtype_obj->fetchAll('ASC', 'label');
$umtype_map = array();
if (is_array($umtypes)) {
	foreach ($umtypes as $ut) {
		$umtype_map[$ut->id] = $ut;
	}
}

// Load truck type for payload capacity
$ct_obj = new CamionType($db);
$charge_utile = 0;
if ($ct_obj->fetch($object->fk_camion_type) > 0) {
	$charge_utile = $ct_obj->charge_utile;
}

// Collect all package IDs (unassigned + assigned) to fetch items in one query
$all_pkg_ids = array();
foreach ($colis_libres as $colis) {
	$all_pkg_ids[] = (int) $colis->fk_package;
}
foreach ($object->lines as $um) {
	if (!empty($um->colis)) {
		foreach ($um->colis as $c) {
			$all_pkg_ids[] = (int) $c->fk_package;
		}
	}
}
$all_pkg_ids = array_unique($all_pkg_ids);

// Fetch items for all packages in one query, with product label
$items_by_package = array();
if (!empty($all_pkg_ids)) {
	$sql_items = "SELECT ci.fk_package, ci.quantity, ci.longueur, ci.largeur,";
	$sql_items .= " ci.weight_unit, ci.custom_name, ci.description as item_description,";
	$sql_items .= " cd.fk_product, cd.description as commandedet_desc,";
	$sql_items .= " p.label as product_label, p.ref as product_ref";
	$sql_items .= " FROM ".MAIN_DB_PREFIX."colisage_items ci";
	$sql_items .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.rowid = ci.fk_commandedet";
	$sql_items .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = cd.fk_product";
	$sql_items .= " WHERE ci.fk_package IN (".implode(',', $all_pkg_ids).")";
	$sql_items .= " ORDER BY ci.fk_package, ci.rowid";

	$resql_items = $db->query($sql_items);
	if ($resql_items) {
		while ($item = $db->fetch_object($resql_items)) {
			$items_by_package[(int) $item->fk_package][] = $item;
		}
		$db->free($resql_items);
	}
}

// Group unassigned packages by order
$colis_by_commande = array();
foreach ($colis_libres as $colis) {
	$colis_by_commande[$colis->fk_commande][] = $colis;
}

// Fetch commande refs for display
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
$commande_cache = array();
foreach ($object->commandes as $fk_cmd) {
	$cmd = new Commande($db);
	$cmd->fetch($fk_cmd);
	$cmd->fetch_thirdparty();
	$commande_cache[$fk_cmd] = $cmd;
}

// Color palette for orders
$colors = array('#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e');
$commande_colors = array();
$i = 0;
foreach ($object->commandes as $fk_cmd) {
	$commande_colors[$fk_cmd] = $colors[$i % count($colors)];
	$i++;
}

$is_draft = ($object->statut == Chargement::STATUS_DRAFT);

// Weight progress calculation
$weight_pct = ($charge_utile > 0) ? min(100, round(($object->poids_total / $charge_utile) * 100)) : 0;
$weight_class = 'ok';
if ($weight_pct > 90) {
	$weight_class = 'danger';
} elseif ($weight_pct > 70) {
	$weight_class = 'warning';
}

/**
 * Helper: build a short product label from an item row
 */
function _planchargement_item_label($item)
{
	if (!empty($item->custom_name)) {
		return $item->custom_name;
	}
	if (!empty($item->product_label)) {
		$label = $item->product_ref ? $item->product_ref.' - ' : '';
		$label .= $item->product_label;
		return $label;
	}
	if (!empty($item->item_description)) {
		return $item->item_description;
	}
	if (!empty($item->commandedet_desc)) {
		// Strip HTML tags from order line description
		return strip_tags($item->commandedet_desc);
	}
	return '?';
}

?>

<!-- Composition UI -->
<div id="planchargement-composition">

	<!-- Top bar -->
	<div class="planchargement-topbar">
		<span>
			<strong><?php echo $langs->trans('PlanchargementWeightCapacity'); ?>:</strong>
			<span id="poids-total"><?php echo number_format($object->poids_total, 1, '.', ' '); ?></span> kg
			<?php if ($charge_utile > 0) { ?>
				/ <?php echo number_format($charge_utile, 0, '', ' '); ?> kg
				<span class="weight-progress">
					<span class="weight-progress-bar <?php echo $weight_class; ?>" style="width: <?php echo $weight_pct; ?>%;"></span>
				</span>
			<?php } ?>
		</span>
	</div>

	<div class="planchargement-columns">

		<!-- LEFT COLUMN: Unassigned packages -->
		<div class="planchargement-col-left" id="col-colis-libres">
			<h3><?php echo $langs->trans('PlanchargementColisNonAffectes'); ?></h3>

			<?php if (empty($colis_libres)) { ?>
				<div class="planchargement-empty">
					<?php
					if (empty($object->commandes)) {
						echo $langs->trans('PlanchargementNoColisAvailable');
					} else {
						echo $langs->trans('PlanchargementAllColisAssigned');
					}
					?>
				</div>
			<?php } else { ?>
				<?php foreach ($colis_by_commande as $fk_cmd => $packages) {
					$cmd = isset($commande_cache[$fk_cmd]) ? $commande_cache[$fk_cmd] : null;
					$color = isset($commande_colors[$fk_cmd]) ? $commande_colors[$fk_cmd] : '#999';
				?>
				<div class="planchargement-order-group" style="border-left: 4px solid <?php echo $color; ?>;" data-commande-id="<?php echo (int) $fk_cmd; ?>">
					<h4>
						<?php
						if ($cmd) {
							echo dol_escape_htmltag($cmd->ref);
							if ($cmd->thirdparty) {
								echo ' - '.dol_escape_htmltag($cmd->thirdparty->name);
							}
						} else {
							echo 'Commande #'.$fk_cmd;
						}
						?>
					</h4>
					<?php foreach ($packages as $pkg) {
						$pkg_items = isset($items_by_package[(int) $pkg->fk_package]) ? $items_by_package[(int) $pkg->fk_package] : array();
					?>
					<div class="planchargement-colis<?php echo ($is_draft ? ' draggable' : ''); ?>"
						 data-fk-package="<?php echo (int) $pkg->fk_package; ?>"
						 data-qty-restante="<?php echo (int) $pkg->qty_restante; ?>"
						 data-weight="<?php echo (float) $pkg->total_weight; ?>">
						<div class="colis-info">
							<div class="colis-header">
								<strong><?php echo $langs->trans('PlanchargementColis'); ?> #<?php echo (int) $pkg->fk_package; ?></strong>
								<span class="opacitymedium">
									&times;<?php echo (int) $pkg->multiplier; ?>
									&mdash; <?php echo number_format($pkg->total_weight, 1, '.', ''); ?> kg
								</span>
								<span class="badge badge-status4 badge-status" style="font-size: 0.75em;">
									<?php echo (int) $pkg->qty_restante; ?> <?php echo $langs->trans('PlanchargementQtyRestante'); ?>
								</span>
							</div>
							<?php if (!empty($pkg_items)) { ?>
							<div class="colis-items">
								<?php foreach ($pkg_items as $item) { ?>
								<div class="colis-item-line">
									<span class="item-label"><?php echo dol_escape_htmltag(dol_trunc(_planchargement_item_label($item), 60)); ?></span>
									<span class="item-details opacitymedium">
										&times;<?php echo (int) $item->quantity; ?>
										<?php if ($item->longueur > 0 && $item->largeur > 0) { ?>
											&mdash; <?php echo (int) $item->longueur; ?>&times;<?php echo (int) $item->largeur; ?> mm
										<?php } ?>
										<?php if ($item->weight_unit > 0) { ?>
											&mdash; <?php echo number_format($item->weight_unit, 2, '.', ''); ?> kg/u
										<?php } ?>
									</span>
								</div>
								<?php } ?>
							</div>
							<?php } ?>
						</div>
						<?php if ($is_draft) { ?>
						<div class="colis-assign">
							<input type="number" class="qty-to-assign" value="1" min="1" max="<?php echo (int) $pkg->qty_restante; ?>" title="<?php echo $langs->trans('PlanchargementQtyToAssign'); ?>">
						</div>
						<?php } ?>
					</div>
					<?php } ?>
				</div>
				<?php } ?>
			<?php } ?>
		</div>

		<!-- RIGHT COLUMN: UMs -->
		<div class="planchargement-col-right" id="col-ums">
			<h3><?php echo $langs->trans('PlanchargementUmList'); ?></h3>

			<?php if ($is_draft && $user->hasRight('planchargement', 'write')) { ?>
			<!-- Inline UM creation -->
			<div class="planchargement-create-um">
				<select id="new-um-type" class="flat">
					<?php if (is_array($umtypes)) {
						foreach ($umtypes as $ut) {
							if (!$ut->active) {
								continue;
							}
							$dims = $ut->longueur.'x'.$ut->largeur.'x'.$ut->hauteur.' mm';
							print '<option value="'.$ut->id.'">'.dol_escape_htmltag($ut->label).' ('.$dims.')</option>';
						}
					} ?>
				</select>
				<button type="button" class="button" onclick="confirmCreateUm();"><?php echo $langs->trans('PlanchargementNewUm'); ?></button>
			</div>
			<?php } ?>

			<?php if (empty($object->lines)) { ?>
				<div class="planchargement-empty"><?php echo $langs->trans('PlanchargementNoUmYet'); ?></div>
			<?php } else { ?>
				<?php foreach ($object->lines as $um) {
					$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
				?>
				<div class="planchargement-um<?php echo ($is_draft ? ' dropzone' : ''); ?>" data-um-id="<?php echo (int) $um->id; ?>">
					<div class="planchargement-um-header" onclick="toggleUmContents(<?php echo (int) $um->id; ?>)">
						<span class="um-info">
							<span class="um-ref"><?php echo dol_escape_htmltag($um->ref_um); ?></span>
							<span class="um-type"><?php echo $ut ? dol_escape_htmltag($ut->label) : '?'; ?></span>
							<span class="um-weight">
								<?php echo number_format($um->poids_total, 1, '.', ' '); ?> kg
							</span>
						</span>
						<span class="um-actions">
							<?php if ($is_draft && $user->hasRight('planchargement', 'write')) { ?>
							<button type="button" class="btn-delete-um" data-um-id="<?php echo (int) $um->id; ?>" title="<?php echo $langs->trans('PlanchargementDeleteUm'); ?>" onclick="event.stopPropagation(); deleteUm(<?php echo (int) $um->id; ?>);">&times;</button>
							<?php } ?>
						</span>
					</div>
					<div class="planchargement-um-contents" id="um-contents-<?php echo (int) $um->id; ?>">
						<?php if (empty($um->colis)) { ?>
							<div class="opacitymedium" style="padding: 6px 0; font-size: 0.85em;"><?php echo $langs->trans('PlanchargementDragDropHint'); ?></div>
						<?php } else { ?>
							<?php foreach ($um->colis as $c) {
								$colis_color = isset($commande_colors[$c->fk_commande]) ? $commande_colors[$c->fk_commande] : '#999';
								$c_items = isset($items_by_package[(int) $c->fk_package]) ? $items_by_package[(int) $c->fk_package] : array();
							?>
							<div class="planchargement-assigned-colis" style="border-left: 3px solid <?php echo $colis_color; ?>; padding-left: 8px;">
								<div class="assigned-colis-info">
									<div>
										<strong>#<?php echo (int) $c->fk_package; ?></strong>
										&times;<?php echo (int) $c->quantity; ?>
										<span class="opacitymedium">(<?php echo number_format($c->total_weight * $c->quantity / max(1, $c->multiplier), 1, '.', ''); ?> kg)</span>
									</div>
									<?php if (!empty($c_items)) { ?>
									<div class="assigned-colis-items opacitymedium">
										<?php foreach ($c_items as $item) { ?>
											<span class="item-tag"><?php echo dol_escape_htmltag(dol_trunc(_planchargement_item_label($item), 40)); ?> &times;<?php echo (int) $item->quantity; ?></span>
										<?php } ?>
									</div>
									<?php } ?>
								</div>
								<?php if ($is_draft && $user->hasRight('planchargement', 'write')) { ?>
								<button type="button" class="btn-remove-colis"
										data-um-id="<?php echo (int) $um->id; ?>"
										data-fk-package="<?php echo (int) $c->fk_package; ?>"
										onclick="removeColis(<?php echo (int) $um->id; ?>, <?php echo (int) $c->fk_package; ?>);"
										title="<?php echo $langs->trans('Delete'); ?>">&times;</button>
								<?php } ?>
							</div>
							<?php } ?>
						<?php } ?>
					</div>
				</div>
				<?php } ?>
			<?php } ?>
		</div>

	</div>
</div>
