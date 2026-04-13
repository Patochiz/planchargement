/**
 * Plan de chargement - Composition UI JavaScript
 * Uses native HTML5 Drag & Drop API (no external dependencies)
 */

document.addEventListener('DOMContentLoaded', function () {
	if (typeof planchargement_readonly !== 'undefined' && planchargement_readonly) {
		return; // Read-only mode, no interactions
	}

	initDragDrop();

	// Enable plan view drag & drop only if the plan tab is rendered
	if (document.getElementById('plan-truck-top')) {
		initPlanDragDrop();
	}
});

// ========================================
// Drag & Drop
// ========================================

function initDragDrop() {
	// Make all .draggable elements draggable
	var draggables = document.querySelectorAll('.planchargement-colis.draggable');
	draggables.forEach(function (el) {
		el.setAttribute('draggable', 'true');

		el.addEventListener('dragstart', function (e) {
			e.dataTransfer.setData('text/plain', JSON.stringify({
				fk_package: el.getAttribute('data-fk-package'),
				qty: el.querySelector('.qty-to-assign') ? el.querySelector('.qty-to-assign').value : 1
			}));
			el.style.opacity = '0.5';
		});

		el.addEventListener('dragend', function () {
			el.style.opacity = '1';
		});
	});

	// Set up drop zones on all .dropzone elements
	var dropzones = document.querySelectorAll('.planchargement-um.dropzone');
	dropzones.forEach(function (zone) {
		zone.addEventListener('dragover', function (e) {
			e.preventDefault();
			zone.classList.add('drop-target');
		});

		zone.addEventListener('dragleave', function () {
			zone.classList.remove('drop-target');
		});

		zone.addEventListener('drop', function (e) {
			e.preventDefault();
			zone.classList.remove('drop-target');

			var data;
			try {
				data = JSON.parse(e.dataTransfer.getData('text/plain'));
			} catch (err) {
				return;
			}

			var umId = zone.getAttribute('data-um-id');
			assignColis(umId, data.fk_package, data.qty);
		});
	});
}

// ========================================
// AJAX Operations
// ========================================

function assignColis(fk_um, fk_package, quantity) {
	var params = 'action=assign&fk_um=' + encodeURIComponent(fk_um) +
		'&fk_package=' + encodeURIComponent(fk_package) +
		'&quantity=' + encodeURIComponent(quantity);

	ajaxPost(planchargement_ajax_url_assign, params, function (data) {
		if (data.success) {
			window.location.reload();
		} else {
			alert(data.error || 'Error assigning package');
		}
	});
}

function removeColis(fk_um, fk_package) {
	if (!confirm('Remove this package from the UM?')) {
		return;
	}

	var params = 'action=remove&fk_um=' + encodeURIComponent(fk_um) +
		'&fk_package=' + encodeURIComponent(fk_package);

	ajaxPost(planchargement_ajax_url_assign, params, function (data) {
		if (data.success) {
			window.location.reload();
		} else {
			alert(data.error || 'Error removing package');
		}
	});
}

function deleteUm(fk_um) {
	if (!confirm('Delete this UM and its assigned packages?')) {
		return;
	}

	var params = 'fk_um=' + encodeURIComponent(fk_um);

	ajaxPost(planchargement_ajax_url_delete_um, params, function (data) {
		if (data.success) {
			window.location.reload();
		} else {
			alert(data.error || 'Error deleting UM');
		}
	});
}

function confirmCreateUm() {
	var select = document.getElementById('new-um-type');
	if (!select || !select.value) {
		return;
	}

	var params = 'fk_chargement=' + encodeURIComponent(planchargement_chargement_id) +
		'&fk_um_type=' + encodeURIComponent(select.value);

	ajaxPost(planchargement_ajax_url_create_um, params, function (data) {
		if (data.success) {
			window.location.reload();
		} else {
			alert(data.error || 'Error creating UM');
		}
	});
}

function toggleCustomUmForm() {
	// Called by the "New custom UM" top button: opens the form in CREATE mode
	// (or closes it if already open in any mode).
	var form = document.getElementById('custom-um-form');
	if (!form) {
		return;
	}
	if (form.style.display === 'block') {
		closeCustomUmForm();
		return;
	}
	resetCustomUmForm();
	form.setAttribute('data-mode', 'create');
	form.setAttribute('data-fk-um', '0');
	form.style.display = 'block';
	var labelInput = document.getElementById('custom-um-label');
	if (labelInput) {
		labelInput.focus();
	}
}

function closeCustomUmForm() {
	var form = document.getElementById('custom-um-form');
	if (!form) {
		return;
	}
	form.style.display = 'none';
	form.setAttribute('data-mode', 'create');
	form.setAttribute('data-fk-um', '0');
	resetCustomUmForm();
}

function resetCustomUmForm() {
	var labelEl    = document.getElementById('custom-um-label');
	var longueurEl = document.getElementById('custom-um-longueur');
	var largeurEl  = document.getElementById('custom-um-largeur');
	var hauteurEl  = document.getElementById('custom-um-hauteur');
	var gerbableEl = document.getElementById('custom-um-gerbable');
	if (labelEl)    labelEl.value = '';
	if (longueurEl) longueurEl.value = '';
	if (largeurEl)  largeurEl.value = '';
	if (hauteurEl)  hauteurEl.value = '';
	if (gerbableEl) gerbableEl.checked = false;
}

function openEditCustomUmForm(btn) {
	var form = document.getElementById('custom-um-form');
	if (!form || !btn) {
		return;
	}
	form.setAttribute('data-mode', 'edit');
	form.setAttribute('data-fk-um', btn.getAttribute('data-um-id') || '0');

	document.getElementById('custom-um-label').value    = btn.getAttribute('data-um-label') || '';
	document.getElementById('custom-um-longueur').value = btn.getAttribute('data-um-longueur') || '';
	document.getElementById('custom-um-largeur').value  = btn.getAttribute('data-um-largeur') || '';
	document.getElementById('custom-um-hauteur').value  = btn.getAttribute('data-um-hauteur') || '';
	document.getElementById('custom-um-gerbable').checked = (btn.getAttribute('data-um-gerbable') === '1');

	form.style.display = 'block';
	form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	var labelInput = document.getElementById('custom-um-label');
	if (labelInput) {
		labelInput.focus();
	}
}

function submitCustomUmForm() {
	var form       = document.getElementById('custom-um-form');
	var labelEl    = document.getElementById('custom-um-label');
	var longueurEl = document.getElementById('custom-um-longueur');
	var largeurEl  = document.getElementById('custom-um-largeur');
	var hauteurEl  = document.getElementById('custom-um-hauteur');
	var gerbableEl = document.getElementById('custom-um-gerbable');

	var label    = labelEl ? labelEl.value.trim() : '';
	var longueur = longueurEl ? parseInt(longueurEl.value, 10) : 0;
	var largeur  = largeurEl ? parseInt(largeurEl.value, 10) : 0;
	var hauteur  = hauteurEl ? parseInt(hauteurEl.value, 10) : 0;
	var gerbable = gerbableEl && gerbableEl.checked ? 1 : 0;

	if (label === '') {
		alert('Label required');
		if (labelEl) {
			labelEl.focus();
		}
		return;
	}
	if (!(longueur > 0) || !(largeur > 0) || !(hauteur > 0)) {
		alert('All dimensions must be > 0');
		return;
	}

	var mode = form ? form.getAttribute('data-mode') : 'create';
	var url, params;

	if (mode === 'edit') {
		var fk_um = form ? parseInt(form.getAttribute('data-fk-um'), 10) : 0;
		if (!(fk_um > 0)) {
			alert('Missing UM id');
			return;
		}
		url = planchargement_ajax_url_update_um_custom;
		params = 'fk_um=' + encodeURIComponent(fk_um) +
			'&label=' + encodeURIComponent(label) +
			'&longueur=' + encodeURIComponent(longueur) +
			'&largeur=' + encodeURIComponent(largeur) +
			'&hauteur=' + encodeURIComponent(hauteur) +
			'&gerbable=' + encodeURIComponent(gerbable);
	} else {
		url = planchargement_ajax_url_create_um_custom;
		params = 'fk_chargement=' + encodeURIComponent(planchargement_chargement_id) +
			'&label=' + encodeURIComponent(label) +
			'&longueur=' + encodeURIComponent(longueur) +
			'&largeur=' + encodeURIComponent(largeur) +
			'&hauteur=' + encodeURIComponent(hauteur) +
			'&gerbable=' + encodeURIComponent(gerbable);
	}

	ajaxPost(url, params, function (data) {
		if (data.success) {
			window.location.reload();
		} else {
			alert(data.error || 'Error saving custom UM');
		}
	});
}

// ========================================
// UI Helpers
// ========================================

function toggleUmContents(umId) {
	var el = document.getElementById('um-contents-' + umId);
	if (el) {
		el.style.display = (el.style.display === 'none') ? 'block' : 'none';
	}
}

// ========================================
// AJAX Helper
// ========================================

function ajaxPost(url, params, callback) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', url, true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	xhr.onreadystatechange = function () {
		if (xhr.readyState === 4) {
			if (xhr.status === 200) {
				try {
					var data = JSON.parse(xhr.responseText);
					callback(data);
				} catch (e) {
					alert('Server returned invalid response');
				}
			} else {
				alert('Server error: ' + xhr.status);
			}
		}
	};

	xhr.send(params);
}

// ========================================
// Plan view drag & drop (loading plan tab)
// ========================================

function initPlanDragDrop() {
	var topView  = document.getElementById('plan-truck-top');
	var overflow = document.getElementById('plan-overflow');
	var ghost    = document.getElementById('plan-drop-ghost');

	if (!topView) {
		return;
	}

	var scale      = parseFloat(topView.getAttribute('data-scale')) || 0;
	var snapMm     = parseInt(topView.getAttribute('data-snap-mm'), 10) || 100;
	var truckLenMm = parseInt(topView.getAttribute('data-truck-len'), 10) || 0;
	var truckWidMm = parseInt(topView.getAttribute('data-truck-wid'), 10) || 0;

	// Shared state for the current drag (dataTransfer.getData is not readable
	// during dragover on most browsers, so we mirror the payload here).
	var dragState = null;

	function snap(mm) {
		return Math.round(mm / snapMm) * snapMm;
	}

	// Snapshot of every other placed UM as an axis-aligned rectangle (mm).
	// Used to detect overlap client-side while dragging. Refreshed on every
	// dragstart so a recently moved UM stays in sync.
	function snapshotPlacedRects(excludeFkUm) {
		var rects = [];
		var nodes = topView.querySelectorAll('.plan-um');
		nodes.forEach(function (n) {
			var id = n.getAttribute('data-um-id');
			if (id === excludeFkUm) {
				return;
			}
			rects.push({
				x: parseInt(n.getAttribute('data-um-pos-x'), 10) || 0,
				y: parseInt(n.getAttribute('data-um-pos-y'), 10) || 0,
				w: parseInt(n.getAttribute('data-um-len'),   10) || 0,
				h: parseInt(n.getAttribute('data-um-wid'),   10) || 0
			});
		});
		return rects;
	}

	function rectsOverlap(ax, ay, aw, ah, b) {
		return ax < b.x + b.w
			&& ax + aw > b.x
			&& ay < b.y + b.h
			&& ay + ah > b.y;
	}

	function hasOverlap(posXmm, posYmm, lenMm, widMm, others) {
		for (var i = 0; i < others.length; i++) {
			if (rectsOverlap(posXmm, posYmm, lenMm, widMm, others[i])) {
				return true;
			}
		}
		return false;
	}

	// Attach dragstart on every draggable UM (top view + overflow tiles)
	var umEls = document.querySelectorAll('.plan-um[draggable="true"], .plan-um-tile[draggable="true"]');
	umEls.forEach(function (el) {
		el.addEventListener('dragstart', function (e) {
			var umLen = parseInt(el.getAttribute('data-um-len'), 10) || 0;
			var umWid = parseInt(el.getAttribute('data-um-wid'), 10) || 0;

			// Where on the UM the user clicked, in mm — so the UM stays
			// anchored to that point on drop (no recentering surprise).
			var elRect = el.getBoundingClientRect();
			var grabOffsetXmm = 0;
			var grabOffsetYmm = 0;
			if (el.classList.contains('plan-um') && scale > 0) {
				grabOffsetXmm = (e.clientX - elRect.left) / scale;
				grabOffsetYmm = (e.clientY - elRect.top) / scale;
			} else {
				// For overflow tiles, center the UM under the cursor on drop
				grabOffsetXmm = umLen / 2;
				grabOffsetYmm = umWid / 2;
			}

			var fkUm = el.getAttribute('data-um-id');
			dragState = {
				fk_um:         fkUm,
				um_len:        umLen,
				um_wid:        umWid,
				grab_off_x_mm: grabOffsetXmm,
				grab_off_y_mm: grabOffsetYmm,
				others:        snapshotPlacedRects(fkUm)
			};

			e.dataTransfer.setData('text/plain', JSON.stringify(dragState));
			e.dataTransfer.effectAllowed = 'move';
			el.style.opacity = '0.4';

			// Prime the ghost with the UM size
			if (ghost && scale > 0) {
				ghost.style.width  = Math.round(umLen * scale) + 'px';
				ghost.style.height = Math.round(umWid * scale) + 'px';
			}
		});

		el.addEventListener('dragend', function () {
			el.style.opacity = '1';
			if (ghost) {
				ghost.style.display = 'none';
			}
			dragState = null;
		});
	});

	// Compute the snapped, clamped (pos_x, pos_y) in mm from a dragover/drop
	// event on the top view.
	function computeDropPosMm(e) {
		if (!dragState || !scale) {
			return null;
		}
		var rect = topView.getBoundingClientRect();
		var cursorXmm = (e.clientX - rect.left) / scale;
		var cursorYmm = (e.clientY - rect.top) / scale;

		var posXmm = snap(cursorXmm - dragState.grab_off_x_mm);
		var posYmm = snap(cursorYmm - dragState.grab_off_y_mm);

		// Clamp inside the truck so the UM never overflows the container
		if (posXmm < 0) { posXmm = 0; }
		if (posYmm < 0) { posYmm = 0; }
		if (truckLenMm > 0 && posXmm + dragState.um_len > truckLenMm) {
			posXmm = Math.max(0, truckLenMm - dragState.um_len);
		}
		if (truckWidMm > 0 && posYmm + dragState.um_wid > truckWidMm) {
			posYmm = Math.max(0, truckWidMm - dragState.um_wid);
		}
		return { pos_x: posXmm, pos_y: posYmm };
	}

	// Drop zone: top view of the truck
	topView.addEventListener('dragover', function (e) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		topView.classList.add('drop-target');

		if (ghost && dragState) {
			var pos = computeDropPosMm(e);
			if (pos) {
				var bad = hasOverlap(pos.pos_x, pos.pos_y, dragState.um_len, dragState.um_wid, dragState.others);
				ghost.style.left = Math.round(pos.pos_x * scale) + 'px';
				ghost.style.top  = Math.round(pos.pos_y * scale) + 'px';
				ghost.style.display = 'block';
				if (bad) {
					ghost.classList.add('invalid');
					e.dataTransfer.dropEffect = 'none';
				} else {
					ghost.classList.remove('invalid');
				}
			}
		}
	});

	topView.addEventListener('dragleave', function (e) {
		// Only clear when actually leaving the container (not a child)
		if (e.target === topView) {
			topView.classList.remove('drop-target');
			if (ghost) {
				ghost.style.display = 'none';
			}
		}
	});

	topView.addEventListener('drop', function (e) {
		e.preventDefault();
		topView.classList.remove('drop-target');
		if (ghost) {
			ghost.style.display = 'none';
			ghost.classList.remove('invalid');
		}

		if (!dragState) {
			// dataTransfer fallback (e.g. drag from outside window)
			try {
				dragState = JSON.parse(e.dataTransfer.getData('text/plain'));
				dragState.others = dragState.others || [];
			} catch (err) {
				return;
			}
			if (!dragState || !dragState.fk_um) {
				return;
			}
		}

		var pos = computeDropPosMm(e);
		if (!pos) {
			return;
		}

		// Refuse the drop client-side if the target rectangle overlaps another
		// already placed UM (the server enforces the same rule as a safety net).
		if (hasOverlap(pos.pos_x, pos.pos_y, dragState.um_len, dragState.um_wid, dragState.others)) {
			return;
		}

		var params = 'fk_um=' + encodeURIComponent(dragState.fk_um) +
			'&pos_x=' + encodeURIComponent(pos.pos_x) +
			'&pos_y=' + encodeURIComponent(pos.pos_y);

		ajaxPost(planchargement_ajax_url_update_um_position, params, function (resp) {
			if (resp && resp.success) {
				window.location.reload();
			} else if (resp && resp.error === 'Overlap') {
				alert('Cette position chevauche une autre UM');
			} else {
				alert((resp && resp.error) || 'Error updating position');
			}
		});
	});

	// Drop zone: overflow area (unplace)
	if (overflow) {
		overflow.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			overflow.classList.add('drop-target');
		});

		overflow.addEventListener('dragleave', function (e) {
			if (e.target === overflow) {
				overflow.classList.remove('drop-target');
			}
		});

		overflow.addEventListener('drop', function (e) {
			e.preventDefault();
			overflow.classList.remove('drop-target');

			var data = dragState;
			if (!data) {
				try {
					data = JSON.parse(e.dataTransfer.getData('text/plain'));
				} catch (err) {
					return;
				}
			}
			if (!data || !data.fk_um) {
				return;
			}

			var params = 'fk_um=' + encodeURIComponent(data.fk_um) + '&unplace=1';

			ajaxPost(planchargement_ajax_url_update_um_position, params, function (resp) {
				if (resp && resp.success) {
					window.location.reload();
				} else {
					alert((resp && resp.error) || 'Error unplacing UM');
				}
			});
		});
	}

	// Double-click on a UM (placed or in overflow) toggles its rotation 0°↔90°
	var rotatables = document.querySelectorAll('.plan-um[draggable="true"], .plan-um-tile[draggable="true"]');
	rotatables.forEach(function (el) {
		el.addEventListener('dblclick', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var fkUm = el.getAttribute('data-um-id');
			if (!fkUm) {
				return;
			}
			var params = 'fk_um=' + encodeURIComponent(fkUm);
			ajaxPost(planchargement_ajax_url_rotate_um, params, function (resp) {
				if (resp && resp.success) {
					window.location.reload();
				} else if (resp && resp.error === 'Overlap') {
					alert('Impossible de pivoter : chevauchement avec une autre UM');
				} else if (resp && resp.error === 'WouldOverflow') {
					alert('Impossible de pivoter : l\'UM déborderait du camion');
				} else {
					alert((resp && resp.error) || 'Erreur lors de la rotation');
				}
			});
		});
	});
}
