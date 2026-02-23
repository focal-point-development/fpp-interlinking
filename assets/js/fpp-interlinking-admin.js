(function($) {
	'use strict';

	var FPP = {

		suggestionsPage: 1,
		searchTimer: null,

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Global settings.
			$('#fpp-save-settings').on('click', this.saveSettings);
			$('#fpp-toggle-settings').on('click', this.toggleSettings);

			// Keyword CRUD.
			$('#fpp-add-keyword').on('click', this.addKeyword);
			$('#fpp-update-keyword').on('click', this.updateKeyword);
			$('#fpp-cancel-edit').on('click', this.cancelEdit);
			$(document).on('click', '.fpp-edit-keyword', this.editKeyword);
			$(document).on('click', '.fpp-delete-keyword', this.deleteKeyword);
			$(document).on('click', '.fpp-toggle-keyword', this.toggleKeyword);

			// Quick-Add Post Search.
			$('#fpp-post-search').on('input', FPP.debounce(FPP.searchPosts, 300));
			$(document).on('click', '.fpp-search-result-item', this.selectSearchResult);
			$(document).on('click', this.dismissSearchDropdown);

			// Scan per keyword row.
			$(document).on('click', '.fpp-scan-keyword', this.scanKeyword);
			$(document).on('click', '.fpp-use-url', this.useScannedUrl);
			$(document).on('click', '.fpp-close-scan', this.closeScanResults);

			// Suggest Keywords from Content.
			$('#fpp-toggle-suggestions').on('click', this.toggleSuggestions);
			$('#fpp-scan-titles').on('click', this.scanTitles);
			$('#fpp-suggestions-prev').on('click', function() { FPP.loadSuggestionsPage(FPP.suggestionsPage - 1); });
			$('#fpp-suggestions-next').on('click', function() { FPP.loadSuggestionsPage(FPP.suggestionsPage + 1); });
			$(document).on('click', '.fpp-add-suggestion', this.addSuggestion);
		},

		/* ── Utility ──────────────────────────────────────────────────── */

		debounce: function(func, delay) {
			return function() {
				var context = this;
				var args = arguments;
				clearTimeout(FPP.searchTimer);
				FPP.searchTimer = setTimeout(function() {
					func.apply(context, args);
				}, delay);
			};
		},

		/* ── Global Settings ──────────────────────────────────────────── */

		toggleSettings: function() {
			$('#fpp-settings-content').slideToggle(200);
			$('#fpp-toggle-settings .dashicons')
				.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
		},

		saveSettings: function(e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text('Saving...');

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

		/* ── Keyword CRUD ─────────────────────────────────────────────── */

		addKeyword: function(e) {
			e.preventDefault();
			var keyword   = $.trim($('#fpp-keyword').val());
			var targetUrl = $.trim($('#fpp-target-url').val());

			if (!keyword || !targetUrl) {
				FPP.showNotice('error', fppInterlinking.i18n.required);
				return;
			}

			if (!/^https?:\/\/.+/i.test(targetUrl)) {
				FPP.showNotice('error', 'Please enter a valid absolute URL (starting with http:// or https://).');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Adding...');

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
					// Remove both the keyword row and its scan results row.
					$('#fpp-keyword-row-' + id + ', #fpp-scan-results-row-' + id).fadeOut(300, function() {
						$(this).remove();
						if ($('#fpp-keywords-tbody tr:not(.fpp-scan-results-row)').length === 0) {
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

		/* ── DOM Helpers ──────────────────────────────────────────────── */

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
				+ '<button type="button" class="button button-small fpp-scan-keyword"'
				+ ' data-id="' + kw.id + '"'
				+ ' data-keyword="' + FPP.escAttr(kw.keyword) + '">Scan</button> '
				+ '<button type="button" class="button button-small fpp-toggle-keyword"'
				+ ' data-id="' + kw.id + '" data-active="1">Disable</button> '
				+ '<button type="button" class="button button-small fpp-delete-keyword"'
				+ ' data-id="' + kw.id + '">Delete</button>'
				+ '</td></tr>';

			var scanRow = '<tr id="fpp-scan-results-row-' + kw.id + '" class="fpp-scan-results-row" style="display:none;">'
				+ '<td colspan="7"><div class="fpp-scan-results-container">'
				+ '<p class="fpp-scan-results-loading" style="display:none;"><span class="spinner is-active"></span> Scanning...</p>'
				+ '<div class="fpp-scan-results-list"></div>'
				+ '</div></td></tr>';

			$('#fpp-keywords-tbody').append(row).append(scanRow);
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

			// Also update the Scan button's keyword data.
			$row.find('.fpp-scan-keyword').data('keyword', kw.keyword);
		},

		clearForm: function() {
			$('#fpp-keyword').val('');
			$('#fpp-target-url').val('');
			$('#fpp-per-nofollow').prop('checked', false);
			$('#fpp-per-new-tab').prop('checked', true);
			$('#fpp-per-max-replacements').val(0);
		},

		/* ── Quick-Add Post Search ────────────────────────────────────── */

		searchPosts: function() {
			var query = $.trim($('#fpp-post-search').val());
			var $dropdown = $('#fpp-post-search-results');

			if (query.length < 2) {
				$dropdown.hide().empty();
				return;
			}

			$.post(fppInterlinking.ajax_url, {
				action: 'fpp_interlinking_search_posts',
				nonce:  fppInterlinking.nonce,
				search: query
			}, function(response) {
				if (response.success && response.data.results.length > 0) {
					var html = '';
					$.each(response.data.results, function(i, post) {
						html += '<div class="fpp-search-result-item"'
							+ ' data-title="' + FPP.escAttr(post.title) + '"'
							+ ' data-url="' + FPP.escAttr(post.permalink) + '">'
							+ '<strong>' + FPP.escHtml(post.title) + '</strong>'
							+ ' <span class="fpp-search-type">(' + FPP.escHtml(post.post_type) + ')</span>'
							+ '<br><small>' + FPP.escHtml(post.permalink) + '</small>'
							+ '</div>';
					});
					$dropdown.html(html).show();
				} else {
					$dropdown.html(
						'<div class="fpp-search-no-results">'
						+ FPP.escHtml(fppInterlinking.i18n.no_posts_found)
						+ '</div>'
					).show();
				}
			}).fail(function() {
				$dropdown.hide();
			});
		},

		selectSearchResult: function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $item = $(this);
			var title = $item.data('title');
			var url   = $item.data('url');

			// Pre-fill the keyword form.
			FPP.cancelEdit();
			$('#fpp-keyword').val(title);
			$('#fpp-target-url').val(url);
			$('#fpp-post-search').val('');
			$('#fpp-post-search-results').hide().empty();

			$('html, body').animate({
				scrollTop: $('#fpp-form-title').offset().top - 50
			}, 300);

			FPP.highlightSection('.fpp-add-keyword-section');
		},

		dismissSearchDropdown: function(e) {
			if (!$(e.target).closest('.fpp-search-wrapper').length) {
				$('#fpp-post-search-results').hide();
			}
		},

		/* ── Scan per Keyword Row ─────────────────────────────────────── */

		scanKeyword: function(e) {
			e.preventDefault();
			var $btn     = $(this);
			var id       = $btn.data('id');
			var keyword  = $btn.data('keyword');
			var $row     = $('#fpp-scan-results-row-' + id);
			var $loading = $row.find('.fpp-scan-results-loading');
			var $list    = $row.find('.fpp-scan-results-list');

			// Toggle: if already showing, hide it.
			if ($row.is(':visible')) {
				$row.slideUp(200);
				return;
			}

			// Hide any other open scan results.
			$('.fpp-scan-results-row').slideUp(200);

			$row.slideDown(200);
			$loading.show();
			$list.empty();

			$.post(fppInterlinking.ajax_url, {
				action:  'fpp_interlinking_scan_keyword',
				nonce:   fppInterlinking.nonce,
				keyword: keyword
			}, function(response) {
				$loading.hide();
				if (response.success && response.data.results.length > 0) {
					var html = '<p class="fpp-scan-summary">'
						+ FPP.escHtml(fppInterlinking.i18n.scan_found.replace('%d', response.data.results.length))
						+ '</p><ul class="fpp-scan-list">';
					$.each(response.data.results, function(i, post) {
						html += '<li class="fpp-scan-item">'
							+ '<span class="fpp-scan-title">' + FPP.escHtml(post.title) + '</span>'
							+ ' <span class="fpp-scan-type">(' + FPP.escHtml(post.post_type) + ')</span>'
							+ ' <span class="fpp-scan-url">' + FPP.escHtml(post.permalink) + '</span>'
							+ ' <button type="button" class="button button-small button-primary fpp-use-url"'
							+ ' data-keyword-id="' + FPP.escAttr(String(id)) + '"'
							+ ' data-url="' + FPP.escAttr(post.permalink) + '">'
							+ FPP.escHtml(fppInterlinking.i18n.use_this_url)
							+ '</button></li>';
					});
					html += '</ul>';
					html += '<p><button type="button" class="button button-small fpp-close-scan"'
						+ ' data-id="' + id + '">'
						+ FPP.escHtml(fppInterlinking.i18n.close)
						+ '</button></p>';
					$list.html(html);
				} else {
					$list.html(
						'<p class="fpp-scan-empty">'
						+ FPP.escHtml(fppInterlinking.i18n.scan_no_results)
						+ '</p>'
						+ '<p><button type="button" class="button button-small fpp-close-scan"'
						+ ' data-id="' + id + '">'
						+ FPP.escHtml(fppInterlinking.i18n.close)
						+ '</button></p>'
					);
				}
			}).fail(function() {
				$loading.hide();
				$list.html('<p class="fpp-scan-error">' + FPP.escHtml(fppInterlinking.i18n.request_failed) + '</p>');
			});
		},

		useScannedUrl: function(e) {
			e.preventDefault();
			var $btn      = $(this);
			var keywordId = $btn.data('keyword-id');
			var url       = $btn.data('url');

			$btn.prop('disabled', true).text(fppInterlinking.i18n.updating);

			// Read the current keyword data from the Edit button's data attributes.
			var $editBtn = $('#fpp-keyword-row-' + keywordId).find('.fpp-edit-keyword');

			$.post(fppInterlinking.ajax_url, {
				action:           'fpp_interlinking_update_keyword',
				nonce:            fppInterlinking.nonce,
				id:               keywordId,
				keyword:          $editBtn.data('keyword'),
				target_url:       url,
				nofollow:         $editBtn.data('nofollow'),
				new_tab:          $editBtn.data('newtab'),
				max_replacements: $editBtn.data('max')
			}, function(response) {
				if (response.success) {
					FPP.updateKeywordRow(response.data.keyword);
					FPP.showNotice('success', response.data.message);
					$('#fpp-scan-results-row-' + keywordId).slideUp(200);
				} else {
					FPP.showNotice('error', response.data.message);
					$btn.prop('disabled', false).text(fppInterlinking.i18n.use_this_url);
				}
			}).fail(function() {
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
				$btn.prop('disabled', false).text(fppInterlinking.i18n.use_this_url);
			});
		},

		closeScanResults: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			$('#fpp-scan-results-row-' + id).slideUp(200);
		},

		/* ── Suggest Keywords from Content ────────────────────────────── */

		toggleSuggestions: function() {
			$('#fpp-suggestions-content').slideToggle(200);
			$('#fpp-toggle-suggestions .dashicons')
				.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
		},

		scanTitles: function(e) {
			e.preventDefault();
			FPP.suggestionsPage = 1;
			FPP.loadSuggestionsPage(1);
		},

		loadSuggestionsPage: function(page) {
			var $btn = $('#fpp-scan-titles');
			$btn.prop('disabled', true).text(fppInterlinking.i18n.scanning);

			$.post(fppInterlinking.ajax_url, {
				action: 'fpp_interlinking_suggest_keywords',
				nonce:  fppInterlinking.nonce,
				page:   page
			}, function(response) {
				$btn.prop('disabled', false).text(fppInterlinking.i18n.scan_post_titles);
				if (response.success) {
					FPP.suggestionsPage = response.data.page;
					FPP.renderSuggestions(response.data);
				} else {
					FPP.showNotice('error', response.data.message);
				}
			}).fail(function() {
				$btn.prop('disabled', false).text(fppInterlinking.i18n.scan_post_titles);
				FPP.showNotice('error', fppInterlinking.i18n.request_failed);
			});
		},

		renderSuggestions: function(data) {
			var $results = $('#fpp-suggestions-results');
			var $tbody   = $('#fpp-suggestions-tbody');
			$tbody.empty();

			if (data.results.length === 0) {
				$results.hide();
				FPP.showNotice('error', fppInterlinking.i18n.no_suggestions);
				return;
			}

			$.each(data.results, function(i, post) {
				var statusText  = post.already_added ? fppInterlinking.i18n.already_mapped : fppInterlinking.i18n.available;
				var statusClass = post.already_added ? 'fpp-badge-inactive' : 'fpp-badge-active';
				var actionBtn   = post.already_added
					? '<span class="description">' + FPP.escHtml(fppInterlinking.i18n.already_mapped) + '</span>'
					: '<button type="button" class="button button-small button-primary fpp-add-suggestion"'
					  + ' data-title="' + FPP.escAttr(post.title) + '"'
					  + ' data-url="' + FPP.escAttr(post.permalink) + '">'
					  + FPP.escHtml(fppInterlinking.i18n.add_as_keyword)
					  + '</button>';

				$tbody.append(
					'<tr>'
					+ '<td>' + FPP.escHtml(post.title) + '</td>'
					+ '<td>' + FPP.escHtml(post.post_type) + '</td>'
					+ '<td><a href="' + FPP.escAttr(post.permalink) + '" target="_blank" rel="noopener noreferrer">' + FPP.escHtml(post.permalink) + '</a></td>'
					+ '<td><span class="' + statusClass + '">' + FPP.escHtml(statusText) + '</span></td>'
					+ '<td>' + actionBtn + '</td>'
					+ '</tr>'
				);
			});

			// Pagination info.
			var info = fppInterlinking.i18n.page_info
				.replace('%1$d', data.page)
				.replace('%2$d', data.total_pages)
				.replace('%3$d', data.total_posts);
			$('.fpp-suggestions-info').text(info);
			$('#fpp-suggestions-prev').prop('disabled', data.page <= 1);
			$('#fpp-suggestions-next').prop('disabled', data.page >= data.total_pages);

			$results.show();
		},

		addSuggestion: function(e) {
			e.preventDefault();
			var $btn  = $(this);
			var title = $btn.data('title');
			var url   = $btn.data('url');

			// Pre-fill the Add form and scroll to it.
			FPP.cancelEdit();
			$('#fpp-keyword').val(title);
			$('#fpp-target-url').val(url);

			$('html, body').animate({
				scrollTop: $('#fpp-form-title').offset().top - 50
			}, 300);

			FPP.highlightSection('.fpp-add-keyword-section');
		},

		/* ── UI Helpers ───────────────────────────────────────────────── */

		highlightSection: function(selector) {
			var $section = $(selector);
			$section.addClass('fpp-highlight');
			setTimeout(function() {
				$section.removeClass('fpp-highlight');
			}, 2000);
		},

		showNotice: function(type, message) {
			var cssClass = (type === 'success') ? 'notice-success' : 'notice-error';
			var $notice  = $('<div class="notice ' + cssClass + ' is-dismissible"><p>' + FPP.escHtml(message) + '</p></div>');
			$('#fpp-notices').html('').append($notice);
			$('html, body').animate({ scrollTop: 0 }, 200);
			setTimeout(function() { $notice.fadeOut(400, function() { $(this).remove(); }); }, 4000);
		},

		escHtml: function(str) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		},

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
