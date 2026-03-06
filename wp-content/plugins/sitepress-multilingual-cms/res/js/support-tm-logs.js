jQuery(document).ready(function($) {
  'use strict';

  // Toggle functionality for log sections.
  $(document).on('click', '.tm-log-toggle', function(e) {
    e.preventDefault();
    
    var $button = $(this);
    var targetId = $button.data('target');
    var $targetSection = $('#' + targetId);
    
    if ($targetSection.length) {
      $targetSection.slideToggle(300, function() {
        if ($targetSection.is(':visible')) {
          $button.text($button.data('textclose'));
        } else {
          $button.text($button.data('textopen'));
        }
      });
    }
  });

  // Save if logs are enabled in settings.
  $('#job-log-feature-toggle').on('change', function() {
    var $checkbox = $(this);
    var $label = $('.job-log-toggle-label');
    var $loader = $('.job-log-toggle-loader');
    var isEnabled = $checkbox.is(':checked');

    $checkbox.prop('disabled', true);
    $loader.addClass('is-active').css({'visibility': 'visible', 'display': 'inline-block'});
    $label.text(isEnabled ? $label.data('textenabled') : $label.data('textdisabled'));

    $.ajax({
      url: wpmlTmJobLog.ajaxUrl,
      type: 'POST',
      data: {
        action: 'wpml_tm_job_log_toggle_feature',
        nonce: wpmlTmJobLog.nonce,
        enabled: isEnabled ? 1 : 0
      },
      success: function(response) {
        if (!response.success) {
          console.error('Failed to save job log feature state:', response.data);
          $checkbox.prop('checked', !isEnabled);
          $label.text(!isEnabled ? $label.data('textenabled') : $label.data('textdisabled'));
        }
      },
      error: function() {
        console.error('AJAX error while saving job log feature state');
        $checkbox.prop('checked', !isEnabled);
        $label.text(!isEnabled ? $label.data('textenabled') : $label.data('textdisabled'));
      },
      complete: function() {
        $checkbox.prop('disabled', false);
        $loader.removeClass('is-active').css({'visibility': 'hidden', 'display': 'none'});
      }
    });
  });

  // Clear all logs.
  $('#job-log-clear-button').on('click', function() {
    var $button = $(this);
    var $loader = $('.job-log-clear-loader');
    var $message = $('.job-log-clear-message');

    if (!confirm(wpmlTmJobLog.confirmClearLogs)) {
      return;
    }

    $button.prop('disabled', true);
    $loader.addClass('is-active').css({'visibility': 'visible', 'display': 'inline-block'});
    $message.text('');

    $.ajax({
      url: wpmlTmJobLog.ajaxUrl,
      type: 'POST',
      data: {
        action: 'wpml_tm_job_log_clear',
        nonce: wpmlTmJobLog.nonce
      },
      success: function(response) {
        if (response.success) {
          $message.text(wpmlTmJobLog.logsClearedSuccess).css('color', '#46b450');
          setTimeout(function() {
            location.reload();
          }, 1000);
        } else {
          console.error('Failed to clear job logs:', response.data);
          $message.text(wpmlTmJobLog.logsClearedFailed).css('color', '#dc3232');
        }
      },
      error: function() {
        console.error('AJAX error while clearing job logs');
        $message.text(wpmlTmJobLog.logsClearedError).css('color', '#dc3232');
      },
      complete: function() {
        $button.prop('disabled', false);
        $loader.removeClass('is-active').css({'visibility': 'hidden', 'display': 'none'});
      }
    });
  });

  // Download last send to translation operation log.
  $('#job-log-download-last-send-button').on('click', function(e) {
    e.preventDefault();

    var form = $('<form>', {
      'method': 'POST',
      'action': wpmlTmJobLog.ajaxUrl,
      'target': '_blank'
    });

    form.append($('<input>', {
      'type': 'hidden',
      'name': 'action',
      'value': 'wpml_tm_job_log_download_last_send'
    }));

    form.append($('<input>', {
      'type': 'hidden',
      'name': 'nonce',
      'value': wpmlTmJobLog.nonce
    }));

    $('body').append(form);
    form.submit();
    form.remove();
  });

  // Download log.
  $(document).on('click', '.tm-log-download', function(e) {
    e.preventDefault();
    
    var $button = $(this);
    var logUid = $button.data('loguid');

    var form = $('<form>', {
      'method': 'POST',
      'action': wpmlTmJobLog.ajaxUrl,
      'target': '_blank'
    });
    
    form.append($('<input>', {
      'type': 'hidden',
      'name': 'action',
      'value': 'wpml_tm_job_log_download'
    }));
    
    form.append($('<input>', {
      'type': 'hidden',
      'name': 'nonce',
      'value': wpmlTmJobLog.nonce
    }));
    
    form.append($('<input>', {
      'type': 'hidden',
      'name': 'loguid',
      'value': logUid,
    }));
    
    $('body').append(form);
    form.submit();
    form.remove();
  });
});