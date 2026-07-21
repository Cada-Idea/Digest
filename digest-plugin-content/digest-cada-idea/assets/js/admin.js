/**
 * Boletines — admin JS
 */
(function ($) {
	'use strict';

	const api = window.BoletinesAdmin || {};
	const i18n = api.i18n || {};

	// Versión visible: añade data-version a todas las H1 de páginas del plugin.
	$(function(){
		const v = api.version || '';
		if (!v) return;
		$('.boletines-wrap .boletines-header h1').attr('data-version', v);
	});

	function call(path, method, body) {
		return wp.apiFetch({
			path: 'boletines/v1/' + path,
			method: method || 'GET',
			data: body || undefined,
		});
	}

	// Confirmar y enviar campaña
	$(document).on('click', '.bol-send-campaign', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const id = $btn.data('id');
		if (!confirm(i18n.confirm_send)) return;

		$btn.prop('disabled', true).text(i18n.sending);
		call('campaigns/' + id + '/send', 'POST')
			.then(function (res) {
				if (res && res.success) {
					alert(res.message || i18n.sent);
					window.location.reload();
				} else {
					alert((res && res.message) || i18n.error);
					$btn.prop('disabled', false).text('Enviar ahora');
				}
			})
			.catch(function (err) {
				alert((err && err.message) || i18n.error);
				$btn.prop('disabled', false).text('Enviar ahora');
			});
	});

	// Eliminar suscriptor
	$(document).on('click', '.bol-delete-subscriber', function (e) {
		e.preventDefault();
		const $row = $(this).closest('tr');
		const id = $(this).data('id');
		if (!confirm(i18n.confirm_delete)) return;
		call('subscribers/' + id, 'DELETE').then(function (res) {
			if (res && res.success) $row.fadeOut(200, function () { $(this).remove(); });
		});
	});

	// Eliminar lista
	$(document).on('click', '.bol-delete-list', function (e) {
		e.preventDefault();
		const $row = $(this).closest('tr');
		const id = $(this).data('id');
		if (!confirm(i18n.confirm_delete)) return;
		call('lists/' + id, 'DELETE').then(function (res) {
			if (res && res.success) $row.fadeOut(200, function () { $(this).remove(); });
		});
	});

	// Eliminar plantilla
	$(document).on('click', '.bol-delete-template', function (e) {
		e.preventDefault();
		const $row = $(this).closest('tr');
		const id = $(this).data('id');
		if (!confirm(i18n.confirm_delete)) return;
		call('templates/' + id, 'DELETE').then(function (res) {
			if (res && res.success) $row.fadeOut(200, function () { $(this).remove(); });
		});
	});

	// Eliminar automatización
	$(document).on('click', '.bol-delete-automation', function (e) {
		e.preventDefault();
		const $row = $(this).closest('tr');
		const id = $(this).data('id');
		if (!confirm(i18n.confirm_delete)) return;
		call('automations/' + id, 'DELETE').then(function (res) {
			if (res && res.success) $row.fadeOut(200, function () { $(this).remove(); });
		});
	});

	// Eliminar formulario
	$(document).on('click', '.bol-delete-form', function (e) {
		e.preventDefault();
		const $row = $(this).closest('tr');
		const id = $(this).data('id');
		if (!confirm(i18n.confirm_delete)) return;
		call('forms/' + id, 'DELETE').then(function (res) {
			if (res && res.success) $row.fadeOut(200, function () { $(this).remove(); });
		});
	});

	// Insertor de bloques dinámicos (campañas)
	$(document).on('click', '.bol-insert-block', function (e) {
		e.preventDefault();
		const block = $(this).data('block');
		let shortcode = '';

		switch (block) {
			case 'posts': {
				const ids = prompt('IDs de posts separados por coma (deja vacío para usar categoría):', '');
				if (ids === null) return;
				if (ids.trim()) {
					shortcode = '[boletines_posts ids="' + ids.trim().replace(/"/g, '') + '"]';
				} else {
					const cat = prompt('Slug de categoría (ej: noticias). Vacío = todas:', '');
					if (cat === null) return;
					const count = prompt('¿Cuántos posts mostrar?', '3');
					if (count === null) return;
					shortcode = '[boletines_posts' + (cat.trim() ? ' category="' + cat.trim().replace(/"/g, '') + '"' : '') + ' count="' + (parseInt(count, 10) || 3) + '"]';
				}
				break;
			}
			case 'popular_posts': {
				const count = prompt('¿Cuántos posts populares mostrar?', '3');
				if (count === null) return;
				const days = prompt('Periodo en días (30 = último mes):', '30');
				if (days === null) return;
				shortcode = '[boletines_popular_posts count="' + (parseInt(count, 10) || 3) + '" days="' + (parseInt(days, 10) || 30) + '"]';
				break;
			}
			case 'products': {
				const ids = prompt('IDs de productos separados por coma (deja vacío para usar categoría):', '');
				if (ids === null) return;
				if (ids.trim()) {
					shortcode = '[boletines_products ids="' + ids.trim().replace(/"/g, '') + '"]';
				} else {
					const cat = prompt('Slug de categoría de producto. Vacío = todas:', '');
					if (cat === null) return;
					const count = prompt('¿Cuántos productos mostrar?', '3');
					if (count === null) return;
					shortcode = '[boletines_products' + (cat.trim() ? ' category="' + cat.trim().replace(/"/g, '') + '"' : '') + ' count="' + (parseInt(count, 10) || 3) + '"]';
				}
				break;
			}
			case 'top_products': {
				const by = prompt('Ordenar por: rating (valoración) o sales (ventas)', 'rating');
				if (by === null) return;
				const count = prompt('¿Cuántos productos mostrar?', '3');
				if (count === null) return;
				shortcode = '[boletines_top_products by="' + (by === 'sales' ? 'sales' : 'rating') + '" count="' + (parseInt(count, 10) || 3) + '"]';
				break;
			}
		}

		if (!shortcode) return;
		insertIntoEditor('body_html', shortcode + '\n');
	});

	// Helper: inserta texto en el editor TinyMCE o el textarea fallback.
	function insertIntoEditor(editorId, text) {
		if (typeof window.tinymce !== 'undefined' && window.tinymce.get(editorId) && !window.tinymce.get(editorId).isHidden()) {
			window.tinymce.get(editorId).execCommand('mceInsertContent', false, text);
		} else {
			const $ta = $('#' + editorId);
			if ($ta.length) {
				const cur = $ta.val();
				const pos = $ta[0].selectionStart || cur.length;
				$ta.val(cur.slice(0, pos) + text + cur.slice(pos));
			}
		}
	}

	// Test SMTP
	$(document).on('click', '.bol-test-email', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const to = $('#bol-test-email-to').val();
		if (!to) { alert('Introduce un correo destino'); return; }
		$btn.prop('disabled', true).text(i18n.sending);
		call('test-email', 'POST', { to: to })
			.then(function (res) {
				alert(res.message || (res.success ? i18n.sent : i18n.error));
			})
			.catch(function (err) { alert((err && err.message) || i18n.error); })
			.finally(function () { $btn.prop('disabled', false).text('Enviar prueba'); });
	});

	// Plantillas rápidas en el editor de campañas
	$(document).on('click', '.bol-editor-templates button', function (e) {
		e.preventDefault();
		const tpl = $(this).data('tpl');
		const editor = window.tinymce && window.tinymce.get('body_html');
		if (!editor) return;
		const snippets = {
			heading: '<h2 style="margin:0 0 12px;font-size:22px;">Tu titular aquí</h2>',
			paragraph: '<p style="margin:0 0 16px;line-height:1.6;">Tu contenido aquí.</p>',
			button: '<p style="margin:24px 0;text-align:center;"><a href="#" style="display:inline-block;padding:12px 28px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Llamada a la acción</a></p>',
			divider: '<hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0;" />',
			image: '<p style="text-align:center;margin:16px 0;"><img src="https://via.placeholder.com/520x260" alt="" style="max-width:100%;height:auto;border-radius:6px;" /></p>',
			signature: '<p style="margin-top:32px;color:#6b7280;">Un saludo,<br/><strong>{site_name}</strong></p>',
		};
		if (snippets[tpl]) {
			editor.execCommand('mceInsertContent', false, snippets[tpl]);
		}
	});

})(jQuery);
