if($.cookie('items_mode') == 'read_and_unread') {
	var items_mode = 'read_and_unread';
} else {
	var items_mode = 'unread_only';
}
$.cookie('items_mode', items_mode, { expires: 30, path: '/' });
if($.cookie('items_display') == 'collapse') {
	var items_display = 'collapse';
} else {
	var items_display = 'expand';
}
$.cookie('items_display', items_display, { expires: 30, path: '/' });
var pagination = 0;
var lock_add_items = false;
var lock_no_more_items = false;
var g_key = false;

function load_items(url) {
	if($('#search_items').val() != '') {
		url = $('.mdl-navigation #search_items_form').attr('action') + '/' + encodeURIComponent( $('#search_items').val() ) ;
	}
	url = url + '/?items_mode=' + items_mode + '&items_display=' + items_display;
	$('.mdl-layout__content').scrollTop(0);
	$('.mdl-grid').html('<div class="mdl-spinner mdl-js-spinner is-active"></div>');componentHandler.upgradeDom('MaterialSpinner', 'mdl-spinner');
	var params = [];
	params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
	pagination = 0;
	params.push({'name': 'pagination', 'value': pagination});
	$.ajax({
		async: true,
		cache: true,
		data: params,
		dataType: 'json',
		statusCode: {
			200: function(data_return, textStatus, jqXHR) {
				for(i in data_return.nav) {
					if(data_return.nav[i]) {
						$('.' + i).parent().removeClass('hide');
					} else {
						$('.' + i).parent().addClass('hide');
					}
				}
				var content = '';
				if(data_return.errors_count > 0) {
					for(i in data_return.errors) {
						content += '<article class="title"><p><i class="icon icon-bug"></i>' + data_return.errors[i] + '</p></article>';
					}
				}
				if(data_return.begin) {
					content += data_return.begin;
				}
				if(data_return.result_type == 'items') {
					if(data_return.total > 0) {
						lock_no_more_items = false;
						for(i in data_return.items) {
							var itm = data_return.items[i];
							if($('#item_' + itm.itm_id).length == 0) {
								content += itm.itm_content;
							}
						}
					}
				}
				if(data_return.result_type == 'cloud') {
					content += data_return.cloud;
				}
				if(data_return.end) {
					content += data_return.end;
				}
				$('.mdl-grid').html(content);
				componentHandler.upgradeDom('MaterialMenu', 'mdl-menu');
				if(data_return.result_type == 'items') {
					/*if($('#debug_queries').length == 0) {
						$('body').prepend('<div id="debug_queries" style="background-color:#FFFFFF;border:1px solid #CCCCCC;font-size:10px;left:10px;padding:10px;position:absolute;top:10px;right:10px;z-index:5000;"></div>');
					}
					$('#debug_queries').html('');
					for(i in data_return.queries) {
						obj = data_return.queries[i];
						if(obj.time > 0.1) {
							$('#debug_queries').append('<p>' + obj.query + '<br>' + obj.time + '</p>');
						}
					}*/
					for(i in data_return.items) {
						itm = data_return.items[i];
						$('#item_' + itm.itm_id).find('.item-content-result').find('a').addClass('mdl-color-text--' + material_design_colors_text_link);
						item_swipe('#item_' + itm.itm_id);
					}
					$('.timeago').timeago();
				}
				refresh();
			}
		},
		type: 'POST',
		url: url
	});
}
function add_items(url) {
	if($('#search_items').val() != '') {
		url = $('.mdl-navigation #search_items_form').attr('action') + '/' + encodeURIComponent( $('#search_items').val() ) ;
	}
	url = url + '/?items_mode=' + items_mode + '&items_display=' + items_display;
	if(!lock_add_items && !lock_no_more_items) {
		lock_add_items = true;
		lock_refresh = true;
		$('.mdl-grid').append('<div class="mdl-spinner mdl-js-spinner is-active"></div>');componentHandler.upgradeDom('MaterialSpinner', 'mdl-spinner');
		var params = [];
		params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
		if(items_display == 'collapse') {
			pagination = pagination + 30;
		} else {
			pagination = pagination + 10;
		}
		params.push({'name': 'pagination', 'value': pagination});
		$.ajax({
			async: true,
			cache: true,
			data: params,
			dataType: 'json',
			statusCode: {
				200: function(data_return, textStatus, jqXHR) {
					var content = '';
					if(data_return.errors_count > 0) {
						for(i in data_return.errors) {
							content += '<article class="title"><p><i class="icon icon-bug"></i>' + data_return.errors[i] + '</p></article>';
						}
					}
					if(data_return.total > 0) {
						for(i in data_return.items) {
							var itm = data_return.items[i];
							if($('#item_' + itm.itm_id).length == 0) {
								content += itm.itm_content;
							}
						}
					} else {
						lock_no_more_items = true;
					}
					if(data_return.end) {
						content += data_return.end;
					}
					$('.mdl-spinner').remove();
					$('.mdl-grid').append(content);
					componentHandler.upgradeDom('MaterialMenu', 'mdl-menu');
					for(i in data_return.items) {
						itm = data_return.items[i];
						$('#item_' + itm.itm_id).find('.item-content-result').find('a').addClass('mdl-color-text--' + material_design_colors_text_link);
						item_swipe('#item_' + itm.itm_id);
					}
					$('.timeago').timeago();
					lock_add_items = false;
					lock_refresh = false;
					refresh();
				}
			},
			type: 'POST',
			url: url
		});
	}
}
function item_swipe(selector) {
	/*if('ontouchstart' in window || navigator.msMaxTouchPoints) {
		$(selector).swipe('destroy');
		$(selector).swipe({
			swipe:function(event, direction, distance, duration, fingerCount) {
				if(direction == 'left') {
					item_star($(selector).find('.star'));
				}
				if(direction == 'right') {
					if($(selector).hasClass('collapse')) {
						item_expand($(selector).find('.expand'));
					} else {
						item_collapse($(selector));
					}
					
				}
			},
			threshold: 120
		});
	}*/
}
function click_navigation(ref) {
	$('#search_items').val('');
	$('.mdl-navigation li').removeClass('active');
	ref.parent().addClass('active');
	$.cookie('menu', ref.attr('id'), { expires: 30, path: '/' });
	load_items(ref.attr('href'));
	if($('aside').css('position') == 'absolute') {
		//toggle_sidebar();
	}
}
function navigation_up() {
	var ref = $('.mdl-layout__drawer .mdl-navigation').find('li.active');
	prev = ref.prev('li');
	var last_sub = prev.find('ul li:last');
	if(last_sub.length > 0) {
		prev = last_sub;
	}
	if(prev.length == 0) {
		prev = $('.mdl-layout__drawer .mdl-navigation').find('li.active').parent().parent();
	}
	if(prev.length > 0) {
		var ref = prev.find('a.mdl-navigation__link:first');
		if(ref.length > 0) {
			click_navigation(ref);
		}
	}
}
function navigation_down() {
	var ref = $('.mdl-layout__drawer .mdl-navigation').find('li.active');
	var first_sub = ref.find('ul li:first');
	if(first_sub.length > 0) {
		next = first_sub;
	} else {
		next = ref.next('li');
		if(next.length == 0) {
			next = $('.mdl-layout__drawer .mdl-navigation').find('li.active').parent().parent().next();
		}
	}
	if(next.length > 0) {
		var ref = next.find('a.mdl-navigation__link:first');
		if(ref.length > 0) {
			click_navigation(ref);
		}
	}
}
function toggle_folder(ref) {
	if(ref.find('i').text() == 'keyboard_arrow_up') {
		var params = [];
		params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
		$.ajax({
			async: true,
			cache: true,
			data: params,
			dataType: 'json',
			statusCode: {
				200: function(data_return, textStatus, jqXHR) {
					if(data_return.subscriptions) {
						ref.html('<i class="material-icons md-24">keyboard_arrow_down</i>');

						ref.parent().find('ul').html('');
						for(i in data_return.subscriptions) {
							var sub = data_return.subscriptions[i];
							if(sub.direction) {
								var content = '<li dir="' + sub.direction + '">';
							} else {
								var content = '<li>';
							}
							if(sub.sub_priority == 1) {
								var icon = 'flag';
							} else {
								var icon = 'rss';
							}
							if(sub.sub_title) {
								var title = sub.sub_title;
							} else {
								var title = sub.fed_title;
							}
							content += '<a style="background-image:url(' + build_favicon(sub.fed_host) + ');" id="load-feed-' + sub.fed_id + '-items" class="favicon mdl-navigation__link" href="' + base_url + 'items/get/feed/' + sub.fed_id + '">' + title + ' (<span>0</span>)</a></li>';
							result_subscriptions.push(sub.fed_id);
							ref.parent().find('ul').append(content);
						}
						refresh();
					}
				}
			},
			type: 'POST',
			url: ref.attr('href')
		});
	} else {
		ref.html('<i class="material-icons md-24">keyboard_arrow_up</i>');

		ref.parent().find('ul').html('');
	}

}
function item_up() {
	var itm_id = $('.mdl-grid .item-selected').attr('id');
	var prev = $('#' + itm_id).prev().attr('id');
	if(prev) {
		scroll_to('#' + prev);
	}
}
function item_down() {
	if($('.mdl-grid .item-selected').length == 0) {
		var itm_id = $('.mdl-grid').find('.item:first').attr('id');
		var next = $('#' + itm_id).attr('id');
		$('#' + itm_id).addClass('item-selected');
	} else {
		var itm_id = $('.mdl-grid .item-selected').attr('id');
		var next = $('#' + itm_id).next().attr('id');
	}
	if(next) {
		scroll_to('#' + next);
		if($('#' + next).hasClass('item')) {
			item_read_auto($('#' + next));
			var last = $('.mdl-grid').find('.item:last').attr('id');
			if(last == next) {
				add_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
			}
		}
	}
}
function item_star(ref) {
	var params = [];
	params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
	$.ajax({
		async: true,
		cache: true,
		data: params,
		dataType: 'json',
		statusCode: {
			200: function(data_return, textStatus, jqXHR) {
				if(data_return.status == 'star') {
					ref.html('<i class="material-icons md-18">star</i>');
				}
				if(data_return.status == 'unstar') {
					ref.html('<i class="material-icons md-18">star_border</i>');
				}
				refresh();
			}
		},
		type: 'POST',
		url: ref.attr('href')
	});
}
function item_share(ref) {
	var params = [];
	params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
	$.ajax({
		async: true,
		cache: true,
		data: params,
		dataType: 'json',
		statusCode: {
			200: function(data_return, textStatus, jqXHR) {
				if(data_return.status == 'share') {
					ref.html('<i class="material-icons md-18">favorite</i>');
				}
				if(data_return.status == 'unshare') {
					ref.html('<i class="material-icons md-18">favorite_border</i>');
				}
				refresh();
			}
		},
		type: 'POST',
		url: ref.attr('href')
	});
}
function item_read(ref) {
	var params = [];
	params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
	$.ajax({
		async: true,
		cache: true,
		data: params,
		dataType: 'json',
		statusCode: {
			200: function(data_return, textStatus, jqXHR) {
				if(data_return.mode == 'toggle') {
					if(data_return.status == 'read') {
						ref.html('<i class="material-icons md-18">check_circle</i>');
						$('#item_' + data_return.itm_id).removeClass('unread');
						$('#item_' + data_return.itm_id).addClass('read');
					}
					if(data_return.status == 'unread') {
						ref.html('<i class="material-icons md-18">panorama_fish_eye</i>');
						$('#item_' + data_return.itm_id).removeClass('read');
						$('#item_' + data_return.itm_id).addClass('unread');
					}
				}
				refresh();
			}
		},
		type: 'POST',
		url: ref.attr('href')
	});
}
function item_read_auto(ref) {
	if(ref.hasClass('unread')) {
	} else if(ref.hasClass('read')) {
	} else {
		ref.addClass('read');
		var params = [];
		params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
		$.ajax({
			async: true,
			cache: true,
			data: params,
			dataType: 'json',
			statusCode: {
				200: function(data_return, textStatus, jqXHR) {
					if(data_return.status == 'read') {
						ref.find('.history').html('<i class="material-icons md-18">check_circle</i>');
						refresh();
					}
				}
			},
			type: 'POST',
			url: ref.find('.history').attr('href') + '/auto'
		});
	}
}
function item_expand(ref) {
	var params = [];
	params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
	$.ajax({
		async: true,
		cache: true,
		data: params,
		dataType: 'json',
		statusCode: {
			200: function(data_return, textStatus, jqXHR) {
				if(data_return.itm_content) {
					$('#item_' + data_return.itm_id).find('.expand').hide();
					$('#item_' + data_return.itm_id).find('.collapse').show();
					$('#item_' + data_return.itm_id).find('.mdl-card__supporting-text').html(data_return.itm_content);
					$('#item_' + data_return.itm_id).find('.mdl-card__supporting-text').show();
					$('#item_' + data_return.itm_id).removeClass('collapse');
					$('#item_' + data_return.itm_id).addClass('expand');
					scroll_to('#item_' + data_return.itm_id);
				}
			}
		},
		type: 'POST',
		url: ref.attr('href')
	});
}
function item_collapse(ref) {
	ref.find('.mdl-card__supporting-text').hide();
	ref.find('.mdl-card__supporting-text').html('');
	ref.find('.collapse').hide();
	ref.find('.expand').show();
	ref.removeClass('expand');
	ref.addClass('collapse');
	scroll_to('#' + ref.attr('id'));
}
function items_collapse() {
	items_display = 'collapse';
	$('#hdrbtn_display').html('<i class="material-icons md-24">keyboard_arrow_up</i>');
	$.cookie('items_display', items_display, { expires: 30, path: '/' });
	load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
}
function items_expand() {
	items_display = 'expand';
	$('#hdrbtn_display').html('<i class="material-icons md-24">keyboard_arrow_down</i>');
	$.cookie('items_display', items_display, { expires: 30, path: '/' });
	load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
}
function build_favicon(icon) {
	return 'https://www.google.com/s2/favicons?domain=' + icon + '&amp;alt=feed';
}
$(document).ready(function() {
	var menu = 'load-all-items';
	if($.cookie('menu')) {
		if($('#' + $.cookie('menu')).length > 0) {
			menu = $.cookie('menu');
		}
	}
	$('#' + menu).parent().addClass('active');
	load_items($('#' + menu).attr('href'));
	$.cookie('menu', menu, { expires: 30, path: '/' });

	$(document).bind('keyup', function(event) {
		var keycode = event.which || event.keyCode;
		if($(event.target).parents('form').length == 0) {
			//g
			if(keycode == 71) {
				g_key = true;
			} else {
				g_key = false;
			}
		}
	});

	$(document).bind('keydown', function(event) {
		var keycode = event.which || event.keyCode;
		if($(event.target).parents('form').length == 0) {
			//g then a
			if(g_key && keycode == 65) {
				click_navigation($('#load-all-items'));

			//g then p
			} else if(g_key && keycode == 80) {
				click_navigation($('#load-priority-items'));

			//g then shift + s
			} else if(g_key && event.shiftKey && keycode == 83) {
				click_navigation($('#load-shared-items'));

			//g then s
			} else if(g_key && keycode == 83) {
				click_navigation($('#load-starred-items'));

			//g then v
			} else if(g_key && keycode == 86) {
				click_navigation($('#load-video-items'));

			//g then g
			} else if(g_key && keycode == 71) {
				click_navigation($('#load-geolocation-items'));

			//shift + 1
			} else if(event.shiftKey && keycode == 49) {
				event.preventDefault();
				if(items_mode == 'unread_only') {
					$('#hdrbtn_mode').html('<i class="material-icons md-24">visibility</i>');
					items_mode = 'read_and_unread';
					$.cookie('items_mode', items_mode, { expires: 30, path: '/' });
					load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
				}

			//shift + 2
			} else if(event.shiftKey && keycode == 50) {
				event.preventDefault();
				if(items_mode == 'read_and_unread') {
					$('#hdrbtn_mode').html('<i class="material-icons md-24">visibility_off</i>');
					items_mode = 'unread_only';
					$.cookie('items_mode', items_mode, { expires: 30, path: '/' });
					load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
				}

			//shift + f
			} else if(event.shiftKey && keycode == 70) {
				event.preventDefault();
				fullscreen();

			//1
			} else if(keycode == 49) {
				event.preventDefault();
				if(items_display == 'expand') {
					items_collapse();
				}

			//2
			} else if(keycode == 50) {
				event.preventDefault();
				if(items_display == 'collapse') {
					items_expand();
				}

			//v
			} else if(keycode == 86) {
				var href = $('.mdl-grid .item-selected').find('h1').find('a').attr('href');
				var name = $('.mdl-grid .item-selected').attr('id');
				window.open(href, 'window_' + name);

			//m
			} else if(keycode == 77) {
				if($('.mdl-grid .item-selected').length > 0) {
					item_read($('.mdl-grid .item-selected').find('.history'));
				}

			//shift + s
			} else if(event.shiftKey && keycode == 83) {
				if($('.mdl-grid .item-selected').length > 0) {
					item_share($('.mdl-grid .item-selected').find('.share'));
				}

			//s
			} else if(keycode == 83) {
				if($('.mdl-grid .item-selected').length > 0) {
					item_star($('.mdl-grid .item-selected').find('.star'));
				}

			///
			} else if(keycode == 58) {
				event.preventDefault();
				//$('#search_items').focus().select();

			//h or ?
			} else if(keycode == 72 || keycode == 188) {
				//modal_show($('#link_shortcuts').attr('href'));

			//o or enter
			} else if(keycode == 79 || keycode == 13) {
				if($('.mdl-grid .item-selected').length > 0) {
					ref = $('.mdl-grid .item-selected');
					if(ref.hasClass('collapse')) {
						item_expand(ref.find('.expand'));
					} else {
						item_collapse(ref);
					}
				}

			} else if(keycode == 65) {
				//shift + a
				if(event.shiftKey) {
					//modal_show($('#items_read').attr('href'));
				//a
				} else {
					window.location.href = base_url + 'subscriptions/create';
				}

			//nothing when meta + k
			} else if(event.metaKey && keycode == 75) {

			//nothing when ctrl + k
			} else if(event.ctrlKey && keycode == 75) {

			//shift + x
			} else if(event.shiftKey && keycode == 88) {
				var ref = $('.mdl-navigation').find('li.active').find('.folder');
				if(ref.length > 0) {
					toggle_folder(ref);
				}

			//shift + p
			} else if(event.shiftKey && keycode == 80) {
				navigation_up();

			//shift + n
			} else if(event.shiftKey && keycode == 78) {
				navigation_down();

			//k or p or shift + space
			} else if(keycode == 75 || keycode == 80 || (keycode == 32 && event.shiftKey)) {
				item_up();

			//j or n or space
			} else if(keycode == 74 || keycode == 78|| keycode == 32) {
				item_down();

			//r
			} else if(keycode == 82) {
				load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
			}
		}
	});

	$('.mdl-layout__content').bind('scroll', function(event) {
		$('.mdl-grid').find('.mdl-card').each(function(index) {
			var itm_id = $(this).attr('id');
			var ref = $('#' + itm_id);

			$('.mdl-grid .item-selected').removeClass('item-selected');
			ref.addClass('item-selected');

			if($(this).hasClass('item')) {
				var last = $('.mdl-grid').find('.item:last').attr('id');
				if(last == itm_id) {
					add_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
				}
			}

			var offset = $(this).offset()
			if(offset.top + ref.height() - 60 < 0) {
				if($(this).hasClass('item') && items_display == 'expand') {
					item_read_auto(ref);
				}
				return true;
			} else {
				return false;
			}
		});
    });

	$('header').on('click', '#item_up', function(event) {
		event.preventDefault();
		item_up();
	});

	$('header').on('click', '#item_down', function(event) {
		event.preventDefault();
		item_down();
	});

	$('.mdl-grid').on('click', '.item h1 a.title_link, .mdl-card__supporting-text a', function(event) {
		var ref = $(this).parents('.item');
		if(ref.hasClass('collapse')) {
			event.preventDefault();
			item_expand(ref.find('.expand'));
		}
		item_read_auto(ref);
    });

	$(document).on('click', '.mdl-navigation a', function(event) {
		event.preventDefault();
		var ref = $(this);
		if(ref.hasClass('folder')) {
			toggle_folder(ref);
		} else if(ref.hasClass('mdl-navigation__link')) {
			click_navigation(ref);
		}
	});

	$(document).on('click', '.mdl-card a.folder', function(event) {
		event.preventDefault();
		$('#search_items').val('');
		var ref = $(this);

		$('.mdl-navigation li').removeClass('active');

		$(ref.attr('href')).parent().addClass('active');
		load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
	});

	$(document).on('click', '.item a.author, #cloud a.author', function(event) {
		event.preventDefault();
		$('#search_items').val('');
		var ref = $(this);

		$('.mdl-navigation li').removeClass('active');

		$('.mdl-navigation > ul').find('.result').remove();
		var title = ref.find('i').remove();
		title = ref.text();
		var content = '<li class="result active"><a id="load-author-items" class="mdl-navigation__link" href="' + base_url + 'items/get/author/' + ref.data('auh_id') + '"><i class="material-icons md-18">person</i>' + title + ' (<span>0</span>)</a></li>';
		$('.mdl-navigation > ul').append(content);

		load_items(ref.attr('href'));
	});

	$(document).on('click', '.item a.from', function(event) {
		event.preventDefault();
		$('#search_items').val('');
		var ref = $(this);

		$('.mdl-navigation li').removeClass('active');

		$('.mdl-navigation > ul').find('.result').remove();
		if(ref.data('direction')) {
			var content = '<li dir="' + ref.data('direction') + '" class="result active">';
		} else {
			var content = '<li class="result active">';
		}
		if(ref.data('priority') == 1) {
			var icon = 'flag';
		} else {
			var icon = 'rss';
		}
		var title = ref.find('i').remove();
		title = ref.text();
		content += '<a style="background-image:url(' + build_favicon(ref.data('fed_host')) + ');" id="load-feed-' + ref.data('fed_id') + '-items" class="favicon mdl-navigation__link" href="' + base_url + 'items/get/feed/' + ref.data('fed_id') + '">' + title + ' (<span>0</span>)</a></li>';
		result_subscriptions.push(ref.data('fed_id'));
		$('.mdl-navigation > ul').append(content);

		load_items(ref.attr('href'));
	});

	$(document).on('click', 'a.category', function(event) {
		event.preventDefault();
		$('#search_items').val('');
		var ref = $(this);

		$('.mdl-navigation li').removeClass('active');

		$('.mdl-navigation > ul').find('.result').remove();
		var title = ref.find('i').remove();
		title = ref.text();
		var content = '<li class="result active"><a id="load-category-items" class="mdl-navigation__link" href="' + base_url + 'items/get/category/' + ref.data('cat_id') + '"><i class="material-icons md-18">label</i>' + title + ' (<span>0</span>)</a></li>';
		$('.mdl-navigation > ul').append(content);

		load_items(ref.attr('href'));
	});

	$('.items_refresh').bind('click', function(event) {
		event.preventDefault();
		load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
	});

	$('.items_mode').bind('click', function(event) {
		event.preventDefault();
		items_mode = $(this).attr('href');
		if(items_mode == 'unread_only') {
			$('#hdrbtn_mode').html('<i class="material-icons md-24">visibility_off</i>');
		}
		if(items_mode == 'read_and_unread') {
			$('#hdrbtn_mode').html('<i class="material-icons md-24">visibility</i>');
		}
		$.cookie('items_mode', items_mode, { expires: 30, path: '/' });
		load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
	});

	$('.items_display').bind('click', function(event) {
		event.preventDefault();
		var ref = $(this);
		var href = $(this).attr('href');
		if(href == 'collapse') {
			items_collapse();
		} else if(href == 'expand') {
			items_expand();
		}
	});

	$('.mdl-navigation #search_items_form').bind('submit', function(event) {
		event.preventDefault();
		$('#search_items').blur();
		if($('#search_items').val() != '') {
			var ref = $(this);
			load_items('search');
			if($('aside').css('position') == 'absolute') {
				$('aside').hide();
			}
		}
	});

	$('.mdl-navigation #search_subscriptions_form').bind('submit', function(event) {
		event.preventDefault();
		var ref = $(this);
		var params = [];
		params.push({'name': 'fed_title', 'value': $('#fed_title').val()});
		params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
		$.ajax({
			async: true,
			cache: true,
			data: params,
			dataType: 'json',
			statusCode: {
				200: function(data_return, textStatus, jqXHR) {
					if(data_return.subscriptions) {
						//var result_subscriptions = [];
						$('.mdl-navigation > ul').find('.result').remove();
						for(i in data_return.subscriptions) {
							var sub = data_return.subscriptions[i];
							if(sub.direction) {
								var content = '<li dir="' + sub.direction + '" class="result">';
							} else {
								var content = '<li class="result">';
							}
							if(sub.sub_priority == 1) {
								var icon = 'flag';
							} else {
								var icon = 'rss';
							}
							if(sub.sub_title) {
								var title = sub.sub_title;
							} else {
								var title = sub.fed_title;
							}
							content += '<a style="background-image:url(' + build_favicon(sub.fed_host) + ');" id="load-feed-' + sub.fed_id + '-items" class="favicon mdl-navigation__link" href="' + base_url + 'items/get/feed/' + sub.fed_id + '">' + title + ' (<span>0</span>)</a></li>';
							result_subscriptions.push(sub.fed_id);
							$('.mdl-navigation > ul').append(content);
						}
						refresh();
					}
				}
			},
			type: 'POST',
			url: ref.attr('action')
		});
	});

	$(document).on('click', '.star', function(event) {
		event.preventDefault();
		item_star($(this));
	});

	$(document).on('click', '.share', function(event) {
		event.preventDefault();
		item_share($(this));
	});

	$(document).on('click', '.history', function(event) {
		event.preventDefault();
		var ref = $(this);
		item_read($(this));
	});

	$(document).on('click', '.item .expand', function(event) {
		event.preventDefault();
		var ref = $(this);
		item_expand($(this));
	});

	$(document).on('click', '.item .collapse', function(event) {
		event.preventDefault();
		var href = $(this).attr('href');
		item_collapse($(href));
	});

	$(document).on('click', '.link-item-share', function(event) {
		event.preventDefault();
		var ref = $(this).attr('href');
		$(this).parent().remove();
		$(ref).find('.item-share').removeClass('hide');
	});

	/*$(document).on('click', '.sharedcount', function(event) {
		event.preventDefault();
		var ref = $(this);
		if(ref.hasClass('sharedcount_done')) {
		} else {
			var itm_id = ref.data('itm_id');
			var url = $('#item_' + itm_id).find('.title_link').attr('href');
			$.ajax({
				async: true,
				cache: true,
				dataType: 'json',
				statusCode: {
					200: function(data_return, textStatus, jqXHR) {
						ref.adClass('sharedcount_done');
						$('#item_' + itm_id).find('.share_facebook').append(' (' + data_return.Facebook.total_count + ')');
						$('#item_' + itm_id).find('.share_google').append(' (' + data_return.GooglePlusOne + ')');
						$('#item_' + itm_id).find('.share_twitter').append(' (' + data_return.Twitter + ')');
					}
				},
				type: 'GET',
				url: 'https://free.sharedcount.com/?apikey=&url=' + url,
			});
		}
	});*/

	$(document).on('click', '.geolocation', function(event) {
		event.preventDefault();
		if(navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(
				function(position) {
					params = [];
					params.push({'name': 'latitude', 'value': position.coords.latitude});
					params.push({'name': 'longitude', 'value': position.coords.longitude});
					params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
					$.ajax({
						async: true,
						cache: true,
						data: params,
						dataType: 'json',
						statusCode: {
							200: function(data_return, textStatus, jqXHR) {
								load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
							}
						},
						type: 'POST',
						url: base_url + 'home/geolocation'
					});
				},
				function(error) {
					if(error.code == 1) {
						modal_show(base_url + 'home/error/geo1');
					} else if(error.code == 2) {
						modal_show(base_url + 'home/error/geo2');
					} else if(error.code == 3) {
						modal_show(base_url + 'home/error/geo3');
					} else {
						modal_show(base_url + 'home/error/geo0');
					}
				},
				{'enableHighAccuracy': true, 'timeout': 30000}
			);
		}
	});

	$(document).on('click', '.items_read', function(event) {
		event.preventDefault();
		params = [];
		params.push({'name': csrf_token_name, 'value': $.cookie(csrf_cookie_name)});
		$.ajax({
			async: true,
			cache: true,
			data: params,
			dataType: 'json',
			statusCode: {
				200: function(data_return, textStatus, jqXHR) {
					load_items( $('.mdl-navigation').find('li.active').find('a.mdl-navigation__link').attr('href') );
				}
			},
			type: 'POST',
			url: $(this).attr('href')
		});
	});
});
