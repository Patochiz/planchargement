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

// Load UM types for labels and capacity
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

?>

<!-- Composition UI -->
<div id="planchargement-composition">

	<!-- Top bar -->
	<div class="planchargement-topbar">
		<?php if ($is_draft && $user->hasRight('planchargement', 'write')) { ?>
			<button type="button" id="btn-new-um" class="butAction"><?php echo $langs->trans('PlanchargementNewUm'); ?></button>
		<?php } ?>
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
					<?php foreach ($packages as $pkg) { ?>
					<div class="planchargement-colis<?php echo ($is_draft ? ' draggable' : ''); ?>"
						 data-fk-package="<?php echo (int) $pkg->fk_package; ?>"
						 data-qty-restante="<?php echo (int) $pkg->qty_restante; ?>"
						 data-weight="<?php echo (float) $pkg->total_weight; ?>">
						<span class="colis-info">
							#<?php echo (int) $pkg->fk_package; ?>
							(<?php echo (int) $pkg->qty_restante; ?> <?php echo $langs->trans('PlanchargementQtyRestante'); ?>,
							<?php echo number_format($pkg->total_weight, 1, '.', ''); ?> kg)
						</span>
						<?php if ($is_draft) { ?>
						<input type="number" class="qty-to-assign" value="1" min="1" max="<?php echo (int) $pkg->qty_restante; ?>" title="<?php echo $langs->trans('PlanchargementQtyToAssign'); ?>">
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

			<?php if (empty($object->lines)) { ?>
				<div class="planchargement-empty"><?php echo $langs->trans('PlanchargementNoUmYet'); ?></div>
			<?php } else { ?>
				<?php foreach ($object->lines as $um) {
					$ut = isset($umtype_map[$um->fk_um_type]) ? $umtype_map[$um->fk_um_type] : null;
					$um_charge_max = $ut ? $ut->charge_max : 0;
					$um_weight_pct = ($um_charge_max > 0) ? round(($um->poids_total / $um_charge_max) * 100) : 0;
					$is_overweight = ($um_charge_max > 0 && $um->poids_total > $um_charge_max);
				?>
				<div class="planchargement-um<?php echo ($is_draft ? ' dropzone' : ''); ?><?php echo ($is_overweight ? ' overweight' : ''); ?>" data-um-id="<?php echo (int) $um->id; ?>">
					<div class="planchargement-um-header" onclick="toggleUmContents(<?php echo (int) $um->id; ?>)">
						<span class="um-info">
							<span class="um-ref"><?php echo dol_escape_htmltag($um->ref_um); ?></span>
							<span class="um-type"><?php echo $ut ? dol_escape_htmltag($ut->label) : '?'; ?></span>
							<span class="um-weight">
								<?php echo number_format($um->poids_total, 1, '.', ' '); ?> kg
								<?php if ($um_charge_max > 0) {
									echo '/ '.number_format($um_charge_max, 0, '', ' ').' kg';
								} ?>
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
							?>
							<div class="planchargement-assigned-colis" style="border-left: 3px solid <?php echo $colis_color; ?>; padding-left: 8px;">
								<span>
									#<?php echo (int) $c->fk_package; ?>
									&times;<?php echo (int) $c->quantity; ?>
									(<?php echo number_format($c->total_weight * $c->quantity / max(1, $c->multiplier), 1, '.', ''); ?> kg)
								</span>
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

<?php if ($is_draft && $user->hasRight('planchargement', 'write')) { ?>
<!-- New UM Modal (hidden by default) -->
<div id="modal-new-um" class="planchargement-modal-overlay" style="display: none;">
	<div class="planchargement-modal">
		<h3><?php echo $langs->trans('PlanchargementNewUm'); ?></h3>
		<p><?php echo $langs->trans('PlanchargementSelectUmType'); ?>:</p>
		<select id="new-um-type" class="flat minwidth200">
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
		<div class="modal-actions">
			<button type="button" class="button" onclick="confirmCreateUm();"><?php echo $langs->trans('Create'); ?></button>
			<button type="button" class="button button-cancel" onclick="closeNewUmModal();"><?php echo $langs->trans('Cancel'); ?></button>
		</div>
	</div>
</div>
<?php } ?>
