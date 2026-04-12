/**
 * Plan de chargement - Composition UI JavaScript
 * Uses native HTML5 Drag & Drop API (no external dependencies)
 */

document.addEventListener('DOMContentLoaded', function () {
	if (typeof planchargement_readonly !== 'undefined' && planchargement_readonly) {
		return; // Read-only mode, no interactions
	}

	initDragDrop();
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
	var form = document.getElementById('custom-um-form');
	if (!form) {
		return;
	}
	if (form.style.display === 'none' || form.style.display === '') {
		form.style.display = 'block';
		var labelInput = document.getElementById('custom-um-label');
		if (labelInput) {
			labelInput.focus();
		}
	} else {
		form.style.display = 'none';
	}
}

function confirmCreateCustomUm() {
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

	var params = 'fk_chargement=' + encodeURIComponent(planchargement_chargement_id) +
		'&label=' + encodeURIComponent(label) +
		'&longueur=' + encodeURIComponent(longueur) +
		'&largeur=' + encodeURIComponent(largeur) +
		'&hauteur=' + encodeURIComponent(hauteur) +
		'&gerbable=' + encodeURIComponent(gerbable);

	ajaxPost(planchargement_ajax_url_create_um_custom, params, function (data) {
		if (data.success) {
			window.location.reload();
		} else {
			alert(data.error || 'Error creating custom UM');
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
