// Admin UI helpers: nav highlighting, badge updates, logout confirmation
(function () {
	'use strict';

	function safeQuery(selector, root = document) {
		try { return root.querySelector(selector); } catch (e) { return null; }
	}

	function safeQueryAll(selector, root = document) {
		try { return Array.from(root.querySelectorAll(selector)); } catch (e) { return []; }
	}

	function highlightNav() {
		var navLinks = safeQueryAll('.nav-item');
		if (!navLinks.length) return;

		var current = window.location.pathname + window.location.search;

		navLinks.forEach(function (a) {
			try {
				var href = a.getAttribute('href') || '';
				// Resolve relative URLs against the current location
				var url = new URL(href, window.location.href);
				// simple match: last segment or full pathname
				if (current.endsWith(url.pathname) || current === url.pathname + url.search) {
					a.classList.add('active');
				} else {
					a.classList.remove('active');
				}
			} catch (err) {
				// ignore invalid hrefs
			}
		});
	}

	function updateBadge(id, value) {
		var el = safeQuery('#' + id);
		if (!el) return;
		el.textContent = String(value == null ? el.textContent : value);
	}

	function updateCounts(counts) {
		if (!counts || typeof counts !== 'object') return;
		if ('edit_requests' in counts) updateBadge('edit-req-count', counts.edit_requests);
		if ('submissions' in counts) updateBadge('submissions-count', counts.submissions);
	}

	function tryFetchCounts() {
		// Attempt to fetch counts from a sensible relative endpoint if present.
		// This is wrapped in try/catch so it fails silently when no API exists.
		var endpoint = './api/counts.php';
		try {
			fetch(endpoint, { credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) throw new Error('no'); return r.json(); })
				.then(function (data) { updateCounts(data); })
				.catch(function () { /* ignore */ });
		} catch (e) { /* ignore */ }
	}

	function wireLogoutConfirm() {
		var logout = Array.from(document.querySelectorAll('a[href$="logout.php"]'))[0];
		if (!logout) return;
		logout.addEventListener('click', function (e) {
			if (!confirm('Are you sure you want to log out?')) {
				e.preventDefault();
			}
		});
	}

	function initPageTransitionOverlay() {
		var links = safeQueryAll('.sidebar .nav-item');
		if (!links.length) return;

		var overlay = document.createElement('div');
		overlay.className = 'page-transition-overlay';
		overlay.setAttribute('aria-hidden', 'true');
		overlay.setAttribute('role', 'status');
		overlay.innerHTML = '<div class="page-transition-card"><img src="../assets/images/logo-ptc.png" alt=""><strong>PATEROS TECHNOLOGICAL COLLEGE</strong><span>Loading section...</span></div>';
		document.body.appendChild(overlay);

		links.forEach(function (link) {
			link.addEventListener('click', function (event) {
				if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
				if (link.dataset.transitioning === 'true') return;
				var href = link.getAttribute('href');
				if (!href || href.charAt(0) === '#' || link.target === '_blank') return;

				event.preventDefault();
				link.dataset.transitioning = 'true';
				overlay.classList.add('is-visible');
				overlay.setAttribute('aria-hidden', 'false');
				window.setTimeout(function () { window.location.assign(href); }, 700);
			});
		});
	}

  	function initNotificationDropdowns() {
		var dropdown = safeQuery('[data-notifications-dropdown]');
		if (!dropdown) return;

		var trigger = safeQuery('[data-notif-trigger]', dropdown);
		if (!trigger) return;

		function closeDropdown(event) {
			if (event && dropdown.contains(event.target)) return;
			dropdown.classList.remove('open');
			trigger.setAttribute('aria-expanded', 'false');
			document.removeEventListener('click', closeDropdown);
		}

		trigger.addEventListener('click', function (event) {
			event.stopPropagation();
			var isOpen = dropdown.classList.toggle('open');
			trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

			document.removeEventListener('click', closeDropdown);
			if (isOpen) {
				setTimeout(function () {
					document.addEventListener('click', closeDropdown);
				}, 0);
			}
		});
	}

	function init() {
		highlightNav();
		wireLogoutConfirm();
		 initPageTransitionOverlay();
		initUserDropdowns();
    initNotificationDropdowns();
		initGradeItemActions();
		initGradingSheetExport();
		initStudentSearchFilters();
		initScoreEntryProgress();
		initGradeOutcomePreviews();
		initGradeCellNavigation();
		initGradingSheetSubmissionGuard();
		// If server exposes counts, try to fetch them; otherwise pages can call updateCounts
		// tryFetchCounts();

		// Expose small API for inline scripts to update counts dynamically
		window.admin = window.admin || {};
		window.admin.updateCounts = updateCounts;
		window.admin.highlightNav = highlightNav;
	}

	function initGradeItemActions() {
		var body = document.body;
		if (!body) return;
		if ((body.getAttribute('data-sheet-editable') || '') !== '1') return;

		var addButtons = safeQueryAll('[data-add-grade-item]');
		var editButtons = safeQueryAll('[data-edit-grade-item]');
		var deleteButtons = safeQueryAll('[data-delete-grade-item]');
		if (!addButtons.length && !editButtons.length && !deleteButtons.length) return;

		var modal = safeQuery('[data-grade-item-modal]');
		var form = modal ? safeQuery('[data-grade-item-form]', modal) : null;
		var titleInput = form ? form.querySelector('#grade-item-name') : null;
		var pointsInput = form ? form.querySelector('#grade-item-points') : null;
		var componentLabel = modal ? safeQuery('[data-grade-item-modal-component]', modal) : null;
		var submitBtn = form ? safeQuery('[data-grade-item-submit]', form) : null;
		var defaultSubmitText = submitBtn ? submitBtn.textContent : '';
		var activeItemId = null;

		function closeModal() {
			if (!modal) return;
			modal.setAttribute('hidden', 'hidden');
			modal.classList.remove('open');
			if (form) {
				form.reset();
			}
			if (submitBtn) {
				submitBtn.disabled = false;
				submitBtn.textContent = defaultSubmitText || 'Save Changes';
			}
			if (componentLabel) {
				componentLabel.textContent = '';
			}
			activeItemId = null;
		}

		function openModal(button) {
			if (!modal || !form || !titleInput || !pointsInput) return;
			activeItemId = button.getAttribute('data-grade-item-id');
			titleInput.value = button.getAttribute('data-title') || '';
			pointsInput.value = button.getAttribute('data-total-points') || '';
			if (componentLabel) {
				var comp = button.getAttribute('data-component-name') || '';
				componentLabel.textContent = comp ? ('Component: ' + comp) : '';
			}
			modal.removeAttribute('hidden');
			modal.classList.add('open');
			setTimeout(function () {
				titleInput.focus();
				titleInput.select();
			}, 50);
		}

		addButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var componentId = btn.getAttribute('data-component-id');
				var componentName = btn.getAttribute('data-component-name') || 'this component';
				if (!componentId) return;

				var originalLabel = btn.textContent;
				btn.disabled = true;
				btn.textContent = '...';

				sendGradeItemRequest({
					action: 'create',
					component_id: componentId
				}).then(function () {
					window.location.reload();
				}).catch(function (err) {
					alert('Unable to add grade item for ' + componentName + ': ' + (err.message || 'Please try again.'));
					btn.disabled = false;
					btn.textContent = originalLabel;
				});
			});
		});

		editButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				openModal(btn);
			});
		});

		deleteButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var title = btn.getAttribute('data-title') || 'this item';
				var componentName = btn.getAttribute('data-component-name') || '';
				var message = 'Delete "' + title + '"';
				if (componentName) {
					message += ' under ' + componentName;
				}
				message += '? This will remove all recorded scores for this activity.';
				if (!confirm(message)) {
					return;
				}

				var gradeItemId = btn.getAttribute('data-grade-item-id');
				if (!gradeItemId) return;

				sendGradeItemRequest({
					action: 'delete',
					grade_item_id: gradeItemId
				}).then(function () {
					window.location.reload();
				}).catch(function (err) {
					alert(err.message || 'Unable to delete grade item at this time.');
				});
			});
		});

		if (form && titleInput && pointsInput) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				if (!activeItemId) return;

				var title = titleInput.value.trim();
				var totalPoints = pointsInput.value.trim();
				if (!title) {
					alert('Please provide a name for the grade item.');
					return;
				}
				if (!totalPoints) {
					alert('Please enter the total points for this grade item.');
					return;
				}

				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = 'Saving...';
				}

				sendGradeItemRequest({
					action: 'update',
					grade_item_id: activeItemId,
					title: title,
					total_points: totalPoints
				}).then(function () {
					window.location.reload();
				}).catch(function (err) {
					alert(err.message || 'Unable to update grade item.');
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = defaultSubmitText || 'Save Changes';
					}
				});
			});
		}

		if (modal) {
			modal.addEventListener('click', function (event) {
				if (event.target === modal) {
					closeModal();
				}
			});
			var closeButtons = safeQueryAll('[data-grade-item-close]', modal).concat(
				safeQueryAll('[data-grade-item-cancel]', modal)
			);
			closeButtons.forEach(function (btn) {
				btn.addEventListener('click', function () {
					closeModal();
				});
			});
			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
					closeModal();
				}
			});
		}
	}

	function sendGradeItemRequest(payload) {
		var formData = new FormData();
		Object.keys(payload || {}).forEach(function (key) {
			if (payload[key] !== undefined && payload[key] !== null) {
				formData.append(key, payload[key]);
			}
		});
		formData.append('csrf_token', document.body.getAttribute('data-csrf-token') || '');

		return fetch('../includes/grade_item_actions.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: formData
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Request failed. Please try again.');
				}
				return response.json();
			})
			.then(function (data) {
				if (!data || data.success !== true) {
					throw new Error((data && data.message) || 'Action could not be completed.');
				}
				return data;
			});
	}

	function initUserDropdowns() {
		var dropdowns = safeQueryAll('[data-user-dropdown]');
		if (!dropdowns.length) return;

		dropdowns.forEach(function (dropdown) {
			var trigger = safeQuery('[data-user-trigger]', dropdown);
			if (!trigger) return;

			function closeDropdown(event) {
				if (event && dropdown.contains(event.target)) return;
				dropdown.classList.remove('open');
				trigger.setAttribute('aria-expanded', 'false');
				document.removeEventListener('click', closeDropdown);
			}

			trigger.addEventListener('click', function (event) {
				event.stopPropagation();
				var isOpen = dropdown.classList.toggle('open');
				trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

				document.removeEventListener('click', closeDropdown);
				if (isOpen) {
					setTimeout(function () {
						document.addEventListener('click', closeDropdown);
					}, 0);
				}
			});
		});
	}

	function initGradingSheetExport() {
		var buttons = safeQueryAll('[data-export-grading-sheet]');
		if (!buttons.length) return;

		var table = safeQuery('.grading-sheet-table');
		if (!table) return;

		function handleExport() {
			var csv = buildGradingSheetCsv(table);
			if (!csv) {
				alert('There is no grading data to export yet.');
				return;
			}
			var fileName = buildExportFilename();
			downloadCsv(csv, fileName);
		}

		buttons.forEach(function (btn) {
			btn.addEventListener('click', handleExport);
		});
	}

	function buildGradingSheetCsv(table) {
		var rows = Array.from(table.querySelectorAll('tr'));
		if (!rows.length) return '';

		var csvRows = rows.map(function (row) {
			var cells = Array.from(row.querySelectorAll('th,td'));
			var values = cells.map(function (cell) {
				var input = cell.querySelector('input');
				var text = '';
				if (input) {
					text = input.value || '';
				} else {
					text = extractCellText(cell);
				}
				return wrapCsvValue(text);
			});
			return values.join(',');
		});

		return csvRows.join('\r\n');
	}

	function extractCellText(cell) {
		var clone = cell.cloneNode(true);
		Array.from(clone.querySelectorAll('button, [data-export-ignore]')).forEach(function (el) {
			if (el && el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
		var text = (clone.textContent || '')
			.replace(/\s+/g, ' ')
			.replace(/\u2014/g, '-')
			.trim();
		return text;
	}

	function wrapCsvValue(value) {
		var normalized = (value == null ? '' : String(value))
			.replace(/\r?\n|\r/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
		var escaped = normalized.replace(/"/g, '""');
		return '"' + escaped + '"';
	}

	function buildExportFilename() {
		var section = '';
		var body = document.body;
		if (body) {
			section = body.getAttribute('data-section-name') || '';
		}
		if (!section) {
			var heading = safeQuery('.content-header h1');
			if (heading) {
				section = heading.textContent || '';
			}
		}
		section = section.replace(/\s*[–—-]\s*Grading Sheet/i, '').trim();
		var safeSection = section
			.toLowerCase()
			.replace(/[^a-z0-9]+/gi, '_')
			.replace(/^_+|_+$/g, '');
		if (!safeSection) {
			safeSection = 'grading_sheet';
		}
		var now = new Date();
		var stamp = [
			now.getFullYear(),
			String(now.getMonth() + 1).padStart(2, '0'),
			String(now.getDate()).padStart(2, '0'),
			String(now.getHours()).padStart(2, '0'),
			String(now.getMinutes()).padStart(2, '0')
		].join('');
		return safeSection + '_' + stamp + '.csv';
	}

	function downloadCsv(content, filename) {
		var blob = new Blob(['\uFEFF' + content], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');
		var url = URL.createObjectURL(blob);
		link.href = url;
		link.download = filename;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		setTimeout(function () {
			URL.revokeObjectURL(url);
		}, 0);
	}

	function initStudentSearchFilters() {
		var inputs = safeQueryAll('[data-student-search-input]');
		if (!inputs.length) return;

		inputs.forEach(function (input) {
			var key = input.getAttribute('data-student-search-input');
			if (!key) return;
			var table = safeQuery('[data-students-table="' + key + '"]');
			if (!table) return;

			var rows = Array.from(table.querySelectorAll('tbody tr')).filter(function (row) {
				return !row.classList.contains('empty-row') && !row.classList.contains('no-results-row');
			});
			var noResultsRow = table.querySelector('.no-results-row');

			function applyFilter() {
				if (!rows.length) {
					if (noResultsRow) {
						noResultsRow.hidden = true;
					}
					return;
				}

				var query = (input.value || '').trim().toLowerCase();
				if (!query) {
					rows.forEach(function (row) {
						row.hidden = false;
					});
					if (noResultsRow) {
						noResultsRow.hidden = true;
					}
					return;
				}

				var visible = 0;
				rows.forEach(function (row) {
					var haystack = (row.textContent || '').toLowerCase();
					var isMatch = haystack.indexOf(query) !== -1;
					row.hidden = !isMatch;
					if (isMatch) {
						visible += 1;
					}
				});

				if (noResultsRow) {
					noResultsRow.hidden = visible !== 0;
				}
			}

			input.addEventListener('input', applyFilter);
			applyFilter();
		});
	}

	function initGradingSheetSubmissionGuard() {
		var body = document.body;
		if (!body || body.getAttribute('data-sheet-editable') !== '1') {
			return;
		}

		var form = document.getElementById('grading-sheet-form');
		if (!form) {
			return;
		}

		var trackedButtons = Array.from(document.querySelectorAll('button[name="action"][form="grading-sheet-form"], #grading-sheet-form button[name="action"]'));
		var lastAction = null;
		trackedButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				lastAction = btn.value || null;
			});
		});
		var confirmedSubmission = false;
		var confirmationDialog = createSubmissionConfirmationDialog();

		function getSubmitAction(event) {
			var submitter = event && event.submitter;
			if (submitter && submitter.name === 'action') {
				return submitter.value || null;
			}
			return lastAction;
		}

		form.addEventListener('submit', function (event) {
			var submitAction = getSubmitAction(event);
			if (submitAction !== 'submit' || confirmedSubmission) {
				confirmedSubmission = false;
				return;
			}

			event.preventDefault();
			var gradeInputs = form.querySelectorAll('.grade-input');
			var blankCount = 0;
			gradeInputs.forEach(function (input) {
				if (input.disabled || input.readOnly) {
					return;
				}
				var value = (input.value || '').trim();
				if (value === '') {
					blankCount += 1;
				}
			});

			var messages = [];
			if (blankCount > 0) {
				messages.push('There ' + (blankCount === 1 ? 'is 1 score' : ('are ' + blankCount + ' scores')) + ' left blank. Submitting now will keep them empty.');
			}
			messages.push('Please double-check that all entered scores are accurate before submitting. Do you want to finalize this grading sheet now?');

			function confirmStep(index) {
				if (index >= messages.length) {
					confirmedSubmission = true;
					var submitter = event.submitter || trackedButtons.find(function (button) { return button.value === submitAction; });
					if (typeof form.requestSubmit === 'function') {
						if (submitter) form.requestSubmit(submitter);
						else form.requestSubmit();
					} else if (confirmationDialog) {
						confirmedSubmission = false;
					}
					return;
				}

				if (!confirmationDialog || typeof confirmationDialog.showModal !== 'function') {
					if (window.confirm(messages[index])) confirmStep(index + 1);
					return;
				}

				confirmationDialog.show(messages[index], index === messages.length - 1 ? 'Submit grading sheet' : 'Review remaining scores');
				confirmationDialog.onConfirm = function () { confirmStep(index + 1); };
			}

			confirmStep(0);
		});
	}

	function createSubmissionConfirmationDialog() {
		var dialog = document.createElement('dialog');
		dialog.className = 'grading-submit-confirmation';
		dialog.setAttribute('aria-labelledby', 'grading-submit-confirmation-title');
		dialog.innerHTML = '<div class="grading-submit-confirmation__card"><span class="material-symbols-rounded" aria-hidden="true">fact_check</span><h2 id="grading-submit-confirmation-title">Confirm submission</h2><p data-grading-confirmation-message></p><div class="grading-submit-confirmation__actions"><button type="button" data-grading-confirm-cancel>Cancel</button><button type="button" data-grading-confirm-continue>Continue</button></div></div>';
		document.body.appendChild(dialog);

		var message = dialog.querySelector('[data-grading-confirmation-message]');
		var continueButton = dialog.querySelector('[data-grading-confirm-continue]');
		var cancelButton = dialog.querySelector('[data-grading-confirm-cancel]');
		if (!message || !continueButton || !cancelButton) return null;

		dialog.show = function (text, actionLabel) {
			message.textContent = text;
			continueButton.textContent = actionLabel;
			dialog.showModal();
			continueButton.focus();
		};
		dialog.onConfirm = null;
		continueButton.addEventListener('click', function () {
			var onConfirm = dialog.onConfirm;
			dialog.close();
			if (typeof onConfirm === 'function') onConfirm();
		});
		cancelButton.addEventListener('click', function () { dialog.close(); });
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) dialog.close();
		});
		return dialog;
	}

	function initScoreEntryProgress() {
		var progress = safeQuery('[data-score-progress]');
		if (!progress) return;

		var inputs = safeQueryAll('[data-grade-score-input]');
		var label = safeQuery('[data-score-progress-label]', progress);
		if (!inputs.length || !label) return;

		function updateProgress() {
			var entered = 0;
			var invalid = 0;

			inputs.forEach(function (input) {
				if ((input.value || '').trim() !== '') entered += 1;
				if (!input.validity.valid) invalid += 1;
			});

			var message = entered + ' of ' + inputs.length + ' scores entered';
			if (invalid > 0) {
				message += ' · ' + invalid + ' score' + (invalid === 1 ? '' : 's') + ' outside the allowed range';
			} else if (entered === inputs.length) {
				message += ' · All scores entered';
			}

			label.textContent = message;
			progress.setAttribute('data-invalid', invalid > 0 ? 'true' : 'false');
		}

		inputs.forEach(function (input) {
			input.addEventListener('input', updateProgress);
			input.addEventListener('change', updateProgress);
		});
		updateProgress();
	}

	function initGradeOutcomePreviews() {
		if (!document.body || document.body.getAttribute('data-sheet-editable') !== '1') return;
		var table = safeQuery('.grading-sheet-table');
		if (!table || !table.tHead || table.tHead.rows.length < 2) return;

		var componentHeaders = safeQueryAll('th.component-header', table.tHead.rows[0]);
		var itemHeaders = Array.from(table.tHead.rows[1].cells);
		var itemOffset = 0;
		var groups = componentHeaders.map(function (header) {
			var count = Math.max(0, header.colSpan - 1);
			var columns = itemHeaders.slice(itemOffset, itemOffset + count);
			itemOffset += count;
			var noItems = columns.some(function (cell) { return cell.textContent.trim() === 'No Grade Items'; });
			return {
				weight: Number(header.getAttribute('data-grade-weight')) || 0,
				itemCount: noItems ? 0 : columns.length
			};
		});
		if (!groups.length) return;

		safeQueryAll('tbody tr', table).forEach(function (row) {
			var inputs = safeQueryAll('.grade-input', row);
			var target = safeQuery('.final-grade-column', row);
			if (!inputs.length || !target) return;

			var badge = document.createElement('span');
			badge.className = 'grade-outcome-preview';
			badge.setAttribute('role', 'status');
			badge.textContent = 'Pending scores';
			target.appendChild(badge);

			function updatePreview() {
				var complete = inputs.every(function (input) { return input.value.trim() !== ''; });
				var valid = inputs.every(function (input) { return input.validity.valid; });
				badge.className = 'grade-outcome-preview';

				if (!complete) {
					badge.textContent = 'Pending scores';
					return;
				}
				if (!valid) {
					badge.textContent = 'Check score range';
					badge.classList.add('grade-outcome-preview--invalid');
					return;
				}

				var gradeOffset = 0;
				var weightedGrade = 0;
				var hasGrade = false;
				groups.forEach(function (group) {
					if (group.itemCount === 0) {
						weightedGrade += group.weight;
						hasGrade = true;
						return;
					}
					var componentInputs = inputs.slice(gradeOffset, gradeOffset + group.itemCount);
					gradeOffset += group.itemCount;
					var possible = componentInputs.reduce(function (sum, input) { return sum + (Number(input.max) || 0); }, 0);
					if (possible <= 0) return;
					var earned = componentInputs.reduce(function (sum, input) { return sum + (Number(input.value) || 0); }, 0);
					weightedGrade += (earned / possible) * group.weight;
					hasGrade = true;
				});

				if (!hasGrade || weightedGrade <= 0) {
					badge.textContent = 'No grade yet';
					return;
				}

				var finalGrade = Math.round(weightedGrade);
				badge.textContent = finalGrade >= 75 ? 'Pass' : 'Below passing';
				badge.classList.add(finalGrade >= 75 ? 'grade-outcome-preview--pass' : 'grade-outcome-preview--fail');
			}

			inputs.forEach(function (input) {
				input.addEventListener('input', updatePreview);
				input.addEventListener('change', updatePreview);
			});
			updatePreview();
		});
	}

	function initGradeCellNavigation() {
		var table = safeQuery('.grading-sheet-table');
		if (!table || !document.body || document.body.getAttribute('data-sheet-editable') !== '1') return;

		var rows = safeQueryAll('tbody tr', table).map(function (row) {
			return safeQueryAll('.grade-input', row);
		});
		table.addEventListener('keydown', function (event) {
			var input = event.target.closest('.grade-input');
			if (!input || input.readOnly || input.disabled) return;
			var rowIndex = rows.findIndex(function (inputs) { return inputs.indexOf(input) !== -1; });
			if (rowIndex < 0) return;
			var columnIndex = rows[rowIndex].indexOf(input);
			var target = null;

			if (event.key === 'ArrowLeft') target = rows[rowIndex][columnIndex - 1];
			if (event.key === 'ArrowRight') target = rows[rowIndex][columnIndex + 1];
			if (event.key === 'ArrowUp' || (event.key === 'Enter' && event.shiftKey)) {
				target = rows[rowIndex - 1] && rows[rowIndex - 1][columnIndex];
			}
			if (event.key === 'ArrowDown' || (event.key === 'Enter' && !event.shiftKey)) {
				target = rows[rowIndex + 1] && rows[rowIndex + 1][columnIndex];
			}
			if (!target) return;
			event.preventDefault();
			target.focus();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
    

})();
