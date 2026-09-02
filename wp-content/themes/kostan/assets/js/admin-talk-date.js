/**
 * Default talk_date Date Time Picker time to 10:00:00 for empty fields.
 *
 * ACF Pro exposes JS filter `date_time_picker_args` (no matching PHP filter
 * in current versions). Existing saved values are left untouched.
 */
(function () {
	'use strict';

	if (typeof acf === 'undefined' || typeof acf.addFilter !== 'function') {
		return;
	}

	acf.addFilter('date_time_picker_args', function (args, field) {
		var name = '';

		if (field && typeof field.get === 'function') {
			name = field.get('name') || '';
		}

		if (!name && field && field.$el && typeof field.$el.attr === 'function') {
			name = field.$el.attr('data-name') || '';
		}

		if (name !== 'talk_date') {
			return args;
		}

		var $alt = field && typeof field.$input === 'function' ? field.$input() : null;
		if ($alt && $alt.length && String($alt.val() || '').trim() !== '') {
			return args;
		}

		args.hour = 10;
		args.minute = 0;
		args.second = 0;

		return args;
	});
})();
