(function($) {
	'use strict';

	var FPP = {

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('#fpp-save-settings').on('click', this.saveSettings);
			$('#fpp-add-keyword').on('click', this.addKeyword);
			$('#fpp-update-keyword').on('click', this.updateKeyword);
			$('#fpp-cancel-edit').on('click', this.cancelEdit);
			$(document).on('click', '.fpp-edit-keyword', this.editKeyword);
			$(document).on('click', '.fpp-delete-keyword', this.deleteKeyword);
			$(document).on('click', '.fpp-toggle-keyword', this.toggleKeyword);
			$('#fpp-toggle-settings').on('click', this.toggleSettings);
		},

		toggleSettings: function() {
			$('#fpp-settings-content').slideToggle(200);
			$('#fpp-toggle-settings .dashicons')
				.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
		},

		saveSettings: function(e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text('Saving...');

			// Client-side clamping to match server-side validation.
			var maxVal = parseInt($('#fpp-global-max-replacements').val(), 10) || 1;
			var cap    = parseInt(fppInterlinking.max_replacements_cap, 10) || 100;
			if (maxVal < 1) maxVal = 1;
			if (maxVal > cap) maxVal = cap;
			$('#fpp-global-max-replacements').val(maxVal);

			$.post(fppInterlinking.ajax_url, {
				action:           'fpp_interlinking_save_settings',
				nonce:            fppInterlinking.nonce,
				max_replacements: maxVal,
				nofollow:         $('#fpp-global-nofollow').is(':checked') ? 1 : 0,
				new_tab:          $('#fpp-global-new-tab').is(':checked') ? 1 : 0,
				case_sensitive:   $('#fpp-global-case-sensitive').is(':checked') ? 1 : 0,
				excluded_posts:   $('#fpp-global-excluded-posts').val()
			}, function(response) {
				$btn.prop('disabled', false).text('Save Settings');
				if (response.success) {
					FPP.showNotice('success', response.data.message);
				} else {
					FPP.showNotice('error', response.data.message);
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Save Settings');
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
			});
		},

		addKeyword: function(e) {
			e.preventDefault();
			var keyword   = $.trim($('#fpp-keyword').val());
			var targetUrl = $.trim($('#fpp-target-url').val());

			if (!keyword || !targetUrl) {
				FPP.showNotice('error', fppInterlinking.i18n.required);
				return;
			}

			// Basic URL validation.
			if (!/^https?:\/\/.+/i.test(targetUrl)) {
				FPP.showNotice('error', 'Please enter a valid absolute URL (starting with http:// or https://).');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Adding...');

			// Clamp per-keyword max replacements.
			var perMax = parseInt($('#fpp-per-max-replacements').val(), 10) || 0;
			var cap    = parseInt(fppInterlinking.max_replacements_cap, 10) || 100;
			if (perMax > cap) perMax = cap;

			$.post(fppInterlinking.ajax_url, {
				action:           'fpp_interlinking_add_keyword',
				nonce:            fppInterlinking.nonce,
				keyword:          keyword,
				target_url:       targetUrl,
				nofollow:         $('#fpp-per-nofollow').is(':checked') ? 1 : 0,
				new_tab:          $('#fpp-per-new-tab').is(':checked') ? 1 : 0,
				max_replacements: perMax
			}, function(response) {
				$btn.prop('disabled', false).text('Add Keyword');
				if (response.success) {
					FPP.showNotice('success', response.data.message);
					FPP.appendKeywordRow(response.data.keyword);
					FPP.clearForm();
					$('#fpp-no-keywords').hide();
					$('#fpp-keywords-table').show();
				} else {
					FPP.showNotice('error', response.data.message);
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Add Keyword');
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
			});
		},

		editKeyword: function(e) {
			e.preventDefault();
			var $btn = $(this);

			$('#fpp-edit-id').val($btn.data('id'));
			$('#fpp-keyword').val($btn.data('keyword'));
			$('#fpp-target-url').val($btn.data('url'));
			$('#fpp-per-nofollow').prop('checked', $btn.data('nofollow') == 1);
			$('#fpp-per-new-tab').prop('checked', $btn.data('newtab') == 1);
			$('#fpp-per-max-replacements').val($btn.data('max'));

			$('#fpp-form-title').text('Edit Keyword Mapping');
			$('#fpp-add-keyword').hide();
			$('#fpp-update-keyword, #fpp-cancel-edit').show();

			$('html, body').animate({
				scrollTop: $('#fpp-form-title').offset().top - 50
			}, 300);
		},

		updateKeyword: function(e) {
			e.preventDefault();
			var id        = $('#fpp-edit-id').val();
			var keyword   = $.trim($('#fpp-keyword').val());
			var targetUrl = $.trim($('#fpp-target-url').val());

			if (!id || !keyword || !targetUrl) {
				FPP.showNotice('error', fppInterlinking.i18n.required);
				return;
			}

			if (!/^https?:\/\/.+/i.test(targetUrl)) {
				FPP.showNotice('error', 'Please enter a valid absolute URL (starting with http:// or https://).');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Updating...');

			var perMax = parseInt($('#fpp-per-max-replacements').val(), 10) || 0;
			var cap    = parseInt(fppInterlinking.max_replacements_cap, 10) || 100;
			if (perMax > cap) perMax = cap;

			$.post(fppInterlinking.ajax_url, {
				action:           'fpp_interlinking_update_keyword',
				nonce:            fppInterlinking.nonce,
				id:               id,
				keyword:          keyword,
				target_url:       targetUrl,
				nofollow:         $('#fpp-per-nofollow').is(':checked') ? 1 : 0,
				new_tab:          $('#fpp-per-new-tab').is(':checked') ? 1 : 0,
				max_replacements: perMax
			}, function(response) {
				$btn.prop('disabled', false).text('Update Keyword');
				if (response.success) {
					FPP.showNotice('success', response.data.message);
					FPP.updateKeywordRow(response.data.keyword);
					FPP.cancelEdit();
				} else {
					FPP.showNotice('error', response.data.message);
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Update Keyword');
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
			});
		},

		cancelEdit: function(e) {
			if (e) e.preventDefault();
			$('#fpp-edit-id').val('');
			$('#fpp-form-title').text('Add New Keyword Mapping');
			$('#fpp-add-keyword').show();
			$('#fpp-update-keyword, #fpp-cancel-edit').hide();
			FPP.clearForm();
		},

		deleteKeyword: function(e) {
			e.preventDefault();
			if (!confirm(fppInterlinking.i18n.confirm_delete)) {
				return;
			}

			var $btn = $(this);
			var id   = $btn.data('id');

			$.post(fppInterlinking.ajax_url, {
				action: 'fpp_interlinking_delete_keyword',
				nonce:  fppInterlinking.nonce,
				id:     id
			}, function(response) {
				if (response.success) {
					$('#fpp-keyword-row-' + id).fadeOut(300, function() {
						$(this).remove();
						if ($('#fpp-keywords-tbody tr').length === 0) {
							$('#fpp-keywords-table').hide();
							$('#fpp-no-keywords').show();
						}
					});
					FPP.showNotice('success', response.data.message);
				} else {
					FPP.showNotice('error', response.data.message);
				}
			}).fail(function() {
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
			});
		},

		toggleKeyword: function(e) {
			e.preventDefault();
			var $btn      = $(this);
			var id        = $btn.data('id');
			var isActive  = $btn.data('active');
			var newActive = isActive ? 0 : 1;

			$.post(fppInterlinking.ajax_url, {
				action:    'fpp_interlinking_toggle_keyword',
				nonce:     fppInterlinking.nonce,
				id:        id,
				is_active: newActive
			}, function(response) {
				if (response.success) {
					$btn.data('active', newActive);
					$btn.text(newActive ? 'Disable' : 'Enable');

					var $row   = $('#fpp-keyword-row-' + id);
					var $badge = $row.find('.column-active span');
					$badge
						.removeClass('fpp-badge-active fpp-badge-inactive')
						.addClass(newActive ? 'fpp-badge-active' : 'fpp-badge-inactive')
						.text(newActive ? 'Active' : 'Inactive');

					FPP.showNotice('success', response.data.message);
				} else {
					FPP.showNotice('error', response.data.message);
				}
			}).fail(function() {
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
			});
		},

		appendKeywordRow: function(kw) {
			var row = '<tr id="fpp-keyword-row-' + kw.id + '">'
				+ '<td class="column-keyword">' + FPP.escHtml(kw.keyword) + '</td>'
				+ '<td class="column-url"><a href="' + FPP.escAttr(kw.target_url) + '" target="_blank" rel="noopener noreferrer">' + FPP.escHtml(kw.target_url) + '</a></td>'
				+ '<td class="column-nofollow">' + (kw.nofollow ? 'Yes' : 'No') + '</td>'
				+ '<td class="column-newtab">' + (kw.new_tab ? 'Yes' : 'No') + '</td>'
				+ '<td class="column-max">' + (kw.max_replacements ? FPP.escHtml(String(kw.max_replacements)) : 'Global') + '</td>'
				+ '<td class="column-active"><span class="fpp-badge-active">Active</span></td>'
				+ '<td class="column-actions">'
				+ '<button type="button" class="button button-small fpp-edit-keyword"'
				+ ' data-id="' + kw.id + '"'
				+ ' data-keyword="' + FPP.escAttr(kw.keyword) + '"'
				+ ' data-url="' + FPP.escAttr(kw.target_url) + '"'
				+ ' data-nofollow="' + kw.nofollow + '"'
				+ ' data-newtab="' + kw.new_tab + '"'
				+ ' data-max="' + kw.max_replacements + '">Edit</button> '
				+ '<button type="button" class="button button-small fpp-toggle-keyword"'
				+ ' data-id="' + kw.id + '" data-active="1">Disable</button> '
				+ '<button type="button" class="button button-small fpp-delete-keyword"'
				+ ' data-id="' + kw.id + '">Delete</button>'
				+ '</td></tr>';

			$('#fpp-keywords-tbody').append(row);
		},

		updateKeywordRow: function(kw) {
			var $row = $('#fpp-keyword-row-' + kw.id);
			$row.find('.column-keyword').text(kw.keyword);
			$row.find('.column-url a').attr('href', kw.target_url).text(kw.target_url);
			$row.find('.column-nofollow').text(kw.nofollow ? 'Yes' : 'No');
			$row.find('.column-newtab').text(kw.new_tab ? 'Yes' : 'No');
			$row.find('.column-max').text(kw.max_replacements ? kw.max_replacements : 'Global');

			var $editBtn = $row.find('.fpp-edit-keyword');
			$editBtn.data('keyword', kw.keyword);
			$editBtn.data('url', kw.target_url);
			$editBtn.data('nofollow', kw.nofollow);
			$editBtn.data('newtab', kw.new_tab);
			$editBtn.data('max', kw.max_replacements);
		},

		clearForm: function() {
			$('#fpp-keyword').val('');
			$('#fpp-target-url').val('');
			$('#fpp-per-nofollow').prop('checked', false);
			$('#fpp-per-new-tab').prop('checked', true);
			$('#fpp-per-max-replacements').val(0);
		},

		showNotice: function(type, message) {
			var cssClass = (type === 'success') ? 'notice-success' : 'notice-error';
			var $notice  = $('<div class="notice ' + cssClass + ' is-dismissible"><p>' + FPP.escHtml(message) + '</p></div>');
			$('#fpp-notices').html('').append($notice);
			$('html, body').animate({ scrollTop: 0 }, 200);
			setTimeout(function() { $notice.fadeOut(400, function() { $(this).remove(); }); }, 4000);
		},

		/**
		 * Escape a string for safe use as HTML text content.
		 * Uses the DOM to ensure proper entity encoding.
		 */
		escHtml: function(str) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		},

		/**
		 * Escape a string for safe use inside an HTML attribute.
		 */
		escAttr: function(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		}
	};

	$(document).ready(function() {
		FPP.init();
	});

})(jQuery);
