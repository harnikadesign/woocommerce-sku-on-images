jQuery(function ($) {
  const $wrap = $('.wcsio-wrap');
  if (!$wrap.length) return;
  $('.wcsio-color').wpColorPicker();

  // Log pagination
  const $table = $('.wcsio-log-table');
  if ($table.length && typeof wcsioAdmin !== 'undefined') {
    let currentPage = 1;
    let totalPages = 1;
    const perPage = parseInt(wcsioAdmin.page_size || 50, 10);
    let lastRows = [];

    const $tbody = $table.find('tbody');
    const $prev = $('.wcsio-log-prev');
    const $next = $('.wcsio-log-next');
    const $page = $('.wcsio-log-page');
    const $pages = $('.wcsio-log-pages');
    const $filterOk = $('#wcsio_filter_ok');
    const $filterSkip = $('#wcsio_filter_skip');
    const $filterFail = $('#wcsio_filter_fail');

    function getAllowedResults() {
      return {
        ok: $filterOk.is(':checked'),
        skip: $filterSkip.is(':checked'),
        fail: $filterFail.is(':checked'),
      };
    }

    function renderRows(rows) {
      $tbody.empty();
      const allow = getAllowedResults();
      const toRender = rows.filter((row) => {
        const res = (row.result || '').toLowerCase();
        if (res === 'ok' && !allow.ok) return false;
        if (res === 'skip' && !allow.skip) return false;
        if (res === 'fail' && !allow.fail) return false;
        return true;
      });
      toRender.forEach((row) => {
        const ts = row.ts || '';
        const tr = $('<tr/>');
        const res = (row.result || '').toLowerCase();
        if (res === 'ok') tr.addClass('wcsio-row--ok');
        else if (res === 'skip') tr.addClass('wcsio-row--skip');
        else tr.addClass('wcsio-row--fail');
        tr.append($('<td/>').text(ts));
        tr.append($('<td/>').text(row.type || ''));
        tr.append($('<td/>').text(row.post_id || ''));
        tr.append($('<td/>').text(row.attachment_id || ''));
        tr.append($('<td/>').text(row.sku || ''));
        const chip = $('<span/>').addClass('wcsio-chip').text(row.result || '');
        if (res === 'ok') chip.addClass('wcsio-chip--ok');
        else if (res === 'skip') chip.addClass('wcsio-chip--skip');
        else chip.addClass('wcsio-chip--fail');
        tr.append($('<td/>').append(chip));
        const $file = $('<td/>').css({ maxWidth: '320px', overflowWrap: 'anywhere' }).text(row.file || '');
        tr.append($file);
        $tbody.append(tr);
      });
    }

    function fetchPage(page) {
      $.ajax({
        url: wcsioAdmin.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'wcsio_log_page',
          _ajax_nonce: wcsioAdmin.ajax_nonce,
          page: page,
          per_page: perPage,
        },
      }).done((res) => {
        if (!res || !res.success) return;
        const data = res.data || {};
        currentPage = data.page || 1;
        totalPages = data.pages || 1;
        $page.text(currentPage);
        $pages.text(totalPages);
        lastRows = data.rows || [];
        renderRows(lastRows);
        $prev.prop('disabled', currentPage <= 1);
        $next.prop('disabled', currentPage >= totalPages);
      }).fail(() => {
        // noop
      });
    }

    $prev.on('click', () => {
      if (currentPage > 1) fetchPage(currentPage - 1);
    });
    $next.on('click', () => {
      if (currentPage < totalPages) fetchPage(currentPage + 1);
    });

    fetchPage(1);

    // Filter handlers
    $filterOk.on('change', () => renderRows(lastRows));
    $filterSkip.on('change', () => renderRows(lastRows));
    $filterFail.on('change', () => renderRows(lastRows));

    // Intensity selector
    const $intensity = $('#wcsio_intensity_select');
    const $logCard = $('#wcsio_log_card');
    function applyIntensity(val) {
      const vals = ['subtle','medium','strong'];
      vals.forEach(v => $logCard.removeClass('wcsio-intensity--' + v));
      const chosen = vals.includes(val) ? val : 'medium';
      $logCard.addClass('wcsio-intensity--' + chosen);
    }
    // Initialize from localStorage
    try {
      const saved = localStorage.getItem('wcsioLogIntensity');
      if (saved) {
        $intensity.val(saved);
        applyIntensity(saved);
      } else {
        applyIntensity('medium');
      }
    } catch (e) {
      applyIntensity('medium');
    }
    $intensity.on('change', function(){
      const val = $(this).val();
      applyIntensity(val);
      try { localStorage.setItem('wcsioLogIntensity', val); } catch(e) {}
    });
  }

  // Preview generator
  const $previewBtn = $('#wcsio_preview_btn');
  const $previewFile = $('#wcsio_preview_file');
  const $previewSku = $('#wcsio_preview_sku');
  const $previewOut = $('#wcsio_preview_output');
  const $spinner = $previewBtn.siblings('.spinner');
  const $pickBtn = $('#wcsio_pick_media_btn');
  const $mediaId = $('#wcsio_media_id');
  const $mediaInfo = $('#wcsio_media_info');
  const $previewReset = $('#wcsio_preview_reset');

  function gatherCurrentSettings() {
    const data = {};
    const $form = $wrap.find('form').first();
    const getVal = (name) => $form.find('[name="' + name + '"]').val();
    const getChecked = (name) => $form.find('[name="' + name + '"]').is(':checked');
    data['enabled'] = getChecked('wcsio[enabled]') ? 1 : 0;
    data['position'] = getVal('wcsio[position]');
    data['font_size'] = getVal('wcsio[font_size]');
    data['line_height'] = getVal('wcsio[line_height]');
    data['margin'] = getVal('wcsio[margin]');
    data['margin_top'] = getVal('wcsio[margin_top]');
    data['margin_right'] = getVal('wcsio[margin_right]');
    data['margin_bottom'] = getVal('wcsio[margin_bottom]');
    data['margin_left'] = getVal('wcsio[margin_left]');
    data['text_color'] = getVal('wcsio[text_color]');
    data['bg_color'] = getVal('wcsio[bg_color]');
    data['bg_opacity'] = getVal('wcsio[bg_opacity]');
    data['font_path'] = getVal('wcsio[font_path]');
    data['inner_padding'] = getVal('wcsio[inner_padding]');
    data['inner_padding_top'] = getVal('wcsio[inner_padding_top]');
    data['inner_padding_right'] = getVal('wcsio[inner_padding_right]');
    data['inner_padding_bottom'] = getVal('wcsio[inner_padding_bottom]');
    data['inner_padding_left'] = getVal('wcsio[inner_padding_left]');
    data['text_align'] = getVal('wcsio[text_align]');
    return data;
  }

  $previewBtn.on('click', function () {
    const file = $previewFile[0].files[0];
    const mediaIdVal = $mediaId.val();
    if (!file && !mediaIdVal) {
      alert('Please choose an image file or select one from the Media Library.');
      return;
    }
    const fd = new FormData();
    fd.append('action', 'wcsio_preview_overlay');
    fd.append('_ajax_nonce', wcsioAdmin.preview_nonce);
    fd.append('sku', $previewSku.val() || 'SKU-PREVIEW');
    if (file) fd.append('file', file);
    if (mediaIdVal) fd.append('media_id', mediaIdVal);
    const opts = gatherCurrentSettings();
    Object.keys(opts).forEach((k) => {
      fd.append('wcsio[' + k + ']', opts[k]);
    });

    $spinner.addClass('is-active');
    $previewBtn.prop('disabled', true);

    $.ajax({
      url: wcsioAdmin.ajax_url,
      method: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      dataType: 'json',
    }).done((res) => {
      if (!res || !res.success) {
        alert((res && res.data && res.data.message) ? res.data.message : 'Preview failed.');
        return;
      }
      const url = res.data.url;
      if (url) {
        $previewOut.empty().append($('<img/>',{src:url,css:{maxWidth:'100%',height:'auto'}}));
      }
    }).fail((xhr) => {
      let msg = 'Preview failed.';
      try{ if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) msg = xhr.responseJSON.data.message; }catch(e){}
      alert(msg);
    }).always(() => {
      $spinner.removeClass('is-active');
      $previewBtn.prop('disabled', false);
    });
  });

  // Media library picker
  let mediaFrame = null;
  $pickBtn.on('click', function (e) {
    e.preventDefault();
    if (mediaFrame) {
      mediaFrame.open();
      return;
    }
    mediaFrame = wp.media({
      title: 'Select Sample Image',
      library: { type: 'image' },
      multiple: false,
    });
    mediaFrame.on('select', function () {
      const attachment = mediaFrame.state().get('selection').first().toJSON();
      if (!attachment || !attachment.id) return;
      $mediaId.val(attachment.id);
      $mediaInfo.text((attachment.filename || '') + (attachment.filesizeHumanReadable ? ' (' + attachment.filesizeHumanReadable + ')' : ''));
      // Clear file input if media chosen
      $previewFile.val('');
    });
    mediaFrame.open();
  });

  // Reset preview
  $previewReset.on('click', function () {
    $previewOut.text('Choose an image and click Generate Preview to see overlay with current settings (unsaved changes included).');
    $previewFile.val('');
    $mediaId.val('');
    $mediaInfo.text('');
    $previewSku.val('SKU-PREVIEW');
  });
});
