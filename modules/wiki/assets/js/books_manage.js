$(function(){
	"use strict";

    $(document).ready(function(){
		// ========== Search ==========
		var fnSearch = function(){
			var value = $('.input-search-list').val();
			var form = $('#form_search');
			var filterQuery = $('#form_search [name="filter_query"]');
			filterQuery.val(value);
			form.submit();
		}
		$(".btn-search-list").on('click', function(){
			fnSearch();
		});
		$('.input-search-list').keypress(function (e) {
			if (e.which == 13) {
				fnSearch();
				return false;
			}
		});

		// ========== Delete confirmation ==========
		$('.wiki-btn-delete').on('click', function(e){
			e.preventDefault();
			var _this = $(this);
			var url = _this.attr('href');
			var confirmMsg = _this.data('lang') || 'Are you sure you want to delete this?';
			if(confirm(confirmMsg)){
				window.location.href = url;
			}
		});

		// ========== View Toggle (Grid / List) ==========
		var savedView = localStorage.getItem('wiki_books_view') || 'grid';
		applyView(savedView);

		$('.wiki-view-btn').on('click', function(){
			var view = $(this).data('view');
			$('.wiki-view-btn').removeClass('active');
			$(this).addClass('active');
			applyView(view);
			localStorage.setItem('wiki_books_view', view);
		});

		function applyView(view){
			$('.wiki-view-btn').removeClass('active');
			$('.wiki-view-btn[data-view="' + view + '"]').addClass('active');
			if(view === 'list'){
				$('#wikiBooksList').addClass('wiki-list-view');
			} else {
				$('#wikiBooksList').removeClass('wiki-list-view');
			}
		}

		// ========== Preview Panel ==========
		var $overlay = $('#wikiPreviewOverlay');
		var $panel = $('#wikiPreviewPanel');
		var $body = $('#previewBody');
		var $footer = $('#previewFooter');
		var currentBookId = null;

		function openPreview(bookId){
			if(currentBookId === bookId && $panel.hasClass('active')){
				closePreview();
				return;
			}
			currentBookId = bookId;

			// Show loading
			$body.html(
				'<div class="wiki-preview-loading">' +
					'<div class="wiki-spinner"></div>' +
					'<p>Loading articles...</p>' +
				'</div>'
			);
			$footer.hide();

			// Open panel
			$overlay.addClass('active');
			$panel.addClass('active');
			$('body').css('overflow', 'hidden');

			// Fetch data
			var postData = {};
			if(typeof APP_CSRF_NAME !== 'undefined'){
				postData[APP_CSRF_NAME] = APP_CSRF_TOKEN;
			}

			$.ajax({
				url: BOOK_PREVIEW_URL + bookId,
				type: 'GET',
				dataType: 'json',
				data: postData,
				success: function(res){
					if(!res.success){
						$body.html('<div class="wiki-preview-empty"><i class="fa fa-exclamation-circle"></i><p>Could not load preview.</p></div>');
						return;
					}

					// Update header
					$('#previewTitle').text(res.book.name);
					$('#previewDesc').text(res.book.short_description || '');

					// Build articles list
					if(res.articles.length === 0){
						$body.html(
							'<div class="wiki-preview-empty">' +
								'<i class="fa fa-file-text-o"></i>' +
								'<p>No articles in this book yet.</p>' +
							'</div>'
						);
						$footer.hide();
					} else {
						var html = '';
						for(var i = 0; i < res.articles.length; i++){
							var a = res.articles[i];
							var iconClass = a.type === 'mindmap' ? 'type-mindmap' : 'type-document';
							var icon = a.type === 'mindmap' ? 'fa-sitemap' : 'fa-file-text-o';
							html += '<a href="' + a.show_url + '" class="wiki-preview-article">' +
								'<div class="wiki-preview-article-icon ' + iconClass + '">' +
									'<i class="fa ' + icon + '"></i>' +
								'</div>' +
								'<div class="wiki-preview-article-info">' +
									'<div class="wiki-preview-article-title">' + escapeHtml(a.title) + '</div>' +
									'<div class="wiki-preview-article-meta">' +
										'<span><i class="fa fa-user"></i> ' + escapeHtml(a.author) + '</span>' +
										'<span><i class="fa fa-eye"></i> ' + a.views + '</span>' +
										'<span><i class="fa fa-clock-o"></i> ' + a.updated_at + '</span>' +
									'</div>' +
								'</div>' +
								'<div class="wiki-preview-article-arrow"><i class="fa fa-chevron-right"></i></div>' +
							'</a>';
						}
						$body.html(html);

						// Show footer
						$('#previewViewAllLink').attr('href', res.articles_url);
						$footer.show();
					}
				},
				error: function(xhr){
					var msg = 'Failed to load preview.';
					try {
						var resp = JSON.parse(xhr.responseText);
						if(resp && resp.message) msg = resp.message;
					} catch(e){}
					$body.html('<div class="wiki-preview-empty"><i class="fa fa-exclamation-triangle"></i><p>' + msg + '</p></div>');
				}
			});
		}

		function closePreview(){
			$overlay.removeClass('active');
			$panel.removeClass('active');
			$('body').css('overflow', '');
			currentBookId = null;
		}

		// Event bindings
		$(document).on('click', '.wiki-preview-btn', function(e){
			e.preventDefault();
			e.stopPropagation();
			var bookId = $(this).data('book-id');
			openPreview(bookId);
		});

		$overlay.on('click', closePreview);
		$('#previewClose').on('click', closePreview);

		$(document).on('keydown', function(e){
			if(e.key === 'Escape') closePreview();
		});

		// Utility
		function escapeHtml(str){
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		}
	});
});
