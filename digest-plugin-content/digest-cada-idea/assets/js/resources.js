/* ============================================================
   Digest by Cada Idea — Recursos frontend (v1.10)
   Vanilla JS, sin jQuery
   ============================================================ */
(function() {
	'use strict';

	if (typeof window.BoletinesResources === 'undefined') return;

	var R = window.BoletinesResources;

	function findFeedback(form) {
		return form.querySelector('.bol-resource-feedback');
	}
	function findBtn(form) {
		return form.querySelector('.bol-resource-btn');
	}

	function setFeedback(form, message, type) {
		var fb = findFeedback(form);
		if (!fb) return;
		fb.textContent = message;
		fb.className = 'bol-resource-feedback ' + (type || '');
	}

	function handleSubmit(e) {
		e.preventDefault();
		var form = e.currentTarget;
		var resourceId = form.getAttribute('data-resource-id');
		var btn = findBtn(form);

		var email = form.querySelector('input[name="email"]').value.trim();
		var firstName = form.querySelector('input[name="first_name"]').value.trim();
		var nonce = form.querySelector('input[name="bol_res_nonce"]').value;

		if (!email) {
			setFeedback(form, 'Por favor introduce un correo válido.', 'error');
			return;
		}

		btn.disabled = true;
		var originalText = btn.textContent;
		btn.textContent = R.strings.sending || 'Enviando…';
		setFeedback(form, '', '');

		var body = new FormData();
		body.append('email', email);
		body.append('first_name', firstName);
		body.append('resource_id', resourceId);

		fetch(R.ajaxUrl, {
			method: 'POST',
			headers: { 'X-WP-Nonce': R.nonce },
			body: body,
			credentials: 'same-origin'
		})
			.then(function(res) {
				return res.json().then(function(data) {
					return { ok: res.ok, status: res.status, data: data };
				});
			})
			.then(function(payload) {
				if (payload.ok && payload.data && payload.data.success) {
					form.classList.add('bol-success');
					var successMsg = form.getAttribute('data-success-message')
						|| (payload.data.message)
						|| '¡Listo!';
					setFeedback(form, successMsg, 'success');
				} else {
					var msg = (payload.data && payload.data.message)
						|| R.strings.error
						|| 'Error';
					setFeedback(form, msg, 'error');
					btn.disabled = false;
					btn.textContent = originalText;
				}
			})
			.catch(function() {
				setFeedback(form, R.strings.error || 'Error', 'error');
				btn.disabled = false;
				btn.textContent = originalText;
			});
	}

	function bindForms() {
		var forms = document.querySelectorAll('.bol-resource-form');
		for (var i = 0; i < forms.length; i++) {
			if (forms[i].__bolBound) continue;
			forms[i].__bolBound = true;
			forms[i].addEventListener('submit', handleSubmit);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindForms);
	} else {
		bindForms();
	}
})();
