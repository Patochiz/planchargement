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

// Colisage external classes (consumed read-only, cf. CLAUDE_CODE_GUIDE §4.2)
dol_include_once('/colisage/class/colisagepackage.class.php');
dol_include_once('/colisage/class/colisageitem.class.php');

// Soft reference to the service-product used by Colisage as a section marker
// (fk_product=361, product_type=1). Kept as a local constant — could later be
// promoted to a module setting via getDolGlobalInt('PLANCHARGEMENT_COLISAGE_TITRE_SERVICE_ID').
$COLISAGE_TITRE_SERVICE_ID = 361;

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

// Group unassigned packages by order
$colis_by_commande = array();
foreach ($colis_libres as $colis) {
	$colis_by_commande[$colis->fk_commande][] = $colis;
}

// Fetch commande refs + parse order lines to build:
//   $section_by_commandedet : fk_commandedet -> current section title
//   $linedet_by_id          : fk_commandedet -> ['product_ref','desc','label']
// Section detection replicates Colisage logic (service ID=361, product_type=1),
// title source = extrafield ref_chantier (priority), fallback = line desc.
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
$commande_cache = array();
$section_by_commandedet = array();
$linedet_by_id = array();
foreach ($object->commandes as $fk_cmd) {
	$cmd = new Commande($db);
	$cmd->fetch($fk_cmd);
	$cmd->fetch_thirdparty();
	$commande_cache[$fk_cmd] = $cmd;

	if (empty($cmd->lines)) {
		$cmd->fetch_lines();
	}
	$current_title = '';
	if (!empty($cmd->lines)) {
		foreach ($cmd->lines as $line) {
			$is_section = ((int) $line->fk_product === $COLISAGE_TITRE_SERVICE_ID
				&& (int) $line->product_type === 1);
			if ($is_section) {
				$ref_chantier = '';
				if (!empty($line->array_options['options_ref_chantier'])) {
					$ref_chantier = $line->array_options['options_ref_chantier'];
				}
				if ($ref_chantier !== '') {
					$current_title = $ref_chantier;
				} elseif (!empty($line->desc)) {
					$current_title = $line->desc;
				} elseif (!empty($line->description)) {
					$current_title = $line->description;
				} else {
					$current_title = (string) $line->label;
				}
				continue;
			}
			$rowid = (int) $line->rowid;
			$section_by_commandedet[$rowid] = $current_title;
			$linedet_by_id[$rowid] = array(
				'product_ref' => isset($line->product_ref) ? $line->product_ref : (isset($line->ref) ? $line->ref : ''),
				'desc'        => isset($line->desc) ? $line->desc : '',
				'label'       => isset($line->product_label) ? $line->product_label : '',
			);
		}
	}
}

// Load all referenced packages via ColisagePackage (fetch() auto-calls fetchItems()).
// Replaces the previous raw SQL on llx_colisage_items and keeps in sync with the
// Colisage module's own class logic.
$packages_cache = array();
foreach ($all_pkg_ids as $pid) {
	$cp = new ColisagePackage($db);
	if ($cp->fetch((int) $pid) > 0) {
		$packages_cache[(int) $pid] = $cp;
	}
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
 * Helper: build a short product label for a ColisageItem.
 * Uses $linedet_by_id (indexed on fk_commandedet) built from the commande lines
 * so we don't have to re-query llx_product / llx_commandedet for each item.
 *
 * @param ColisageItem $colisage_item Item loaded via ColisagePackage::fetchItems()
 * @param array        $linedet_by_id Map fk_commandedet => ['product_ref','desc','label']
 * @return string
 */
function _planchargement_item_label($colisage_item, $linedet_by_id)
{
	if (!empty($colisage_item->custom_name)) {
		return $colisage_item->custom_name;
	}
	$cd = isset($colisage_item->fk_commandedet) ? (int) $colisage_item->fk_commandedet : 0;
	if ($cd > 0 && isset($linedet_by_id[$cd])) {
		$l = $linedet_by_id[$cd];
		if (!empty($l['product_ref'])) {
			return $l['product_ref'];
		}
		if (!empty($l['desc'])) {
			return dol_trunc(strip_tags($l['desc']), 40);
		}
		if (!empty($l['label'])) {
			return dol_trunc(strip_tags($l['label']), 40);
		}
	}
	if (!empty($colisage_item->description)) {
		return dol_trunc(strip_tags($colisage_item->description), 40);
	}
	return '?';
}

/**
 * Helper: find the Colisage section title for a package.
 * Returns the title of the first item whose fk_commandedet maps to a non-empty
 * section. Empty string means "no section" (free package, or package before any
 * section marker in the order).
 *
 * @param ColisagePackage $pkg                    Package with items already loaded
 * @param array           $section_by_commandedet Map fk_commandedet => section title
 * @return string
 */
function _planchargement_package_section_title($pkg, $section_by_commandedet)
{
	if (empty($pkg) || empty($pkg->items)) {
		return '';
	}
	foreach ($pkg->items as $it) {
		$cd = isset($it->fk_commandedet) ? (int) $it->fk_commandedet : 0;
		if ($cd > 0 && isset($section_by_commandedet[$cd]) && $section_by_commandedet[$cd] !== '') {
			return $section_by_commandedet[$cd];
		}
	}
	return '';
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
						$cp = isset($packages_cache[(int) $pkg->fk_package]) ? $packages_cache[(int) $pkg->fk_package] : null;
						$pkg_items = $cp ? $cp->items : array();
						$section_title = _planchargement_package_section_title($cp, $section_by_commandedet);
					?>
					<div class="planchargement-colis<?php echo ($is_draft ? ' draggable' : ''); ?>"
						 data-fk-package="<?php echo (int) $pkg->fk_package; ?>"
						 data-qty-restante="<?php echo (int) $pkg->qty_restante; ?>">
						<div class="colis-info">
							<?php if ($section_title !== '') { ?>
							<div class="colis-section-label"><?php echo dol_escape_htmltag($section_title); ?></div>
							<?php } ?>
							<div class="colis-header">
								<strong><?php echo $langs->trans('PlanchargementColis'); ?> #<?php echo (int) $pkg->fk_package; ?></strong>
								<span class="opacitymedium">
									&times;<?php echo (int) $pkg->multiplier; ?>
								</span>
								<span class="badge badge-status4 badge-status" style="font-size: 0.75em;">
									<?php echo (int) $pkg->qty_restante; ?> <?php echo $langs->trans('PlanchargementQtyRestante'); ?>
								</span>
							</div>
							<?php if (!empty($pkg_items)) { ?>
							<div class="colis-items">
								<?php foreach ($pkg_items as $item) { ?>
								<div class="colis-item-line">
									<span class="item-label"><?php echo dol_escape_htmltag(_planchargement_item_label($item, $linedet_by_id)); ?></span>
									<span class="item-details">
										&times;<?php echo (int) $item->quantity; ?>
										<?php if ($item->longueur > 0 && $item->largeur > 0) { ?>
											&mdash; <?php echo (int) $item->longueur; ?>&times;<?php echo (int) $item->largeur; ?> mm
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
								$cp_assigned = isset($packages_cache[(int) $c->fk_package]) ? $packages_cache[(int) $c->fk_package] : null;
								$c_items = $cp_assigned ? $cp_assigned->items : array();
							?>
							<div class="planchargement-assigned-colis" style="border-left: 3px solid <?php echo $colis_color; ?>; padding-left: 8px;">
								<div class="assigned-colis-info">
									<div>
										<strong>#<?php echo (int) $c->fk_package; ?></strong>
										&times;<?php echo (int) $c->quantity; ?>
									</div>
									<?php if (!empty($c_items)) { ?>
									<div class="assigned-colis-items opacitymedium">
										<?php foreach ($c_items as $item) { ?>
											<span class="item-tag"><?php echo dol_escape_htmltag(_planchargement_item_label($item, $linedet_by_id)); ?> &times;<?php echo (int) $item->quantity; ?></span>
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
