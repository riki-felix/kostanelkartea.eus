(function () {
	'use strict';

	if (typeof komunikazioaImportBatch === 'undefined') {
		return;
	}

	var config = komunikazioaImportBatch;
	var polling = false;
	var pollDelayMs = 1500;

	function updateProgress(data) {
		var bar = document.getElementById('komunikazioa-import-progress-bar');
		var text = document.getElementById('komunikazioa-import-progress-text');
		var stats = data.stats || {};

		if (bar) {
			bar.value = data.percent || 0;
		}

		if (text) {
			text.textContent = (config.i18n.processing || 'Procesando...') + ' ' +
				(data.cursor || 0) + ' ' + (config.i18n.of || 'de') + ' ' + (data.total || 0);
		}

		var map = {
			created: 'komunikazioa-import-stat-created',
			skipped: 'komunikazioa-import-stat-skipped',
			mailed: 'komunikazioa-import-stat-mailed',
			mail_failed: 'komunikazioa-import-stat-mail-failed',
			errors: 'komunikazioa-import-stat-errors'
		};

		Object.keys(map).forEach(function (key) {
			var node = document.getElementById(map[key]);
			if (node && typeof stats[key] !== 'undefined') {
				node.textContent = String(stats[key]);
			}
		});
	}

	function scheduleNextPoll() {
		window.setTimeout(processBatch, pollDelayMs);
	}

	function processBatch() {
		if (polling) {
			return;
		}

		polling = true;

		var body = new URLSearchParams();
		body.append('action', 'komunikazioa_process_import_batch');
		body.append('nonce', config.nonce);
		body.append('job_id', config.jobId);

		window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				polling = false;

				if (!payload || !payload.success || !payload.data) {
					scheduleNextPoll();
					return;
				}

				updateProgress(payload.data);

				if (payload.data.reload) {
					var url = new URL(window.location.href);
					url.searchParams.set('komunikazioa_import', 'done');
					url.searchParams.set('mode', 'full');
					url.searchParams.delete('job');
					window.location.href = url.toString();
					return;
				}

				if (payload.data.status === 'running') {
					scheduleNextPoll();
				}
			})
			.catch(function () {
				polling = false;
				scheduleNextPoll();
			});
	}

	if (config.progress) {
		updateProgress(config.progress);
	}

	if (config.jobId) {
		processBatch();
	}
})();
