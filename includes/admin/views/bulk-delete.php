<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
  <h1>Xoá hàng loạt theo URL</h1>

  <div style="max-width:700px;margin-top:20px;">

    <textarea id="xv-urls" rows="12" style="width:100%;font-size:12px;font-family:monospace;" placeholder="Dan URL vao day, moi URL mot dong...
                                                                                                            Url1: https://example.com/page1
                                                                                                            Url2: https://example.com/page2"></textarea>

    <div style="margin:12px 0;display:flex;align-items:center;gap:24px;">
      <label><input type="radio" name="xv-action" value="trash" checked> Chuyển vào thùng rác</label>
      <label><input type="radio" name="xv-action" value="restore"> Phục hồi từ thùng rác</label>
      <label><input type="radio" name="xv-action" value="delete"> Xóa vĩnh viễn</label>
    </div>

    <button id="xv-start" class="button button-primary">Bắt đầu</button>
    <button id="xv-stop" class="button" style="display:none;margin-left:8px;">Dừng lại</button>

    <div id="xv-progress-wrap" style="display:none;margin-top:20px;">
      <div style="background:#e0e0e0;border-radius:4px;height:18px;width:100%;">
        <div id="xv-bar" style="width:0%;background:#0073aa;height:18px;border-radius:4px;transition:width .3s;"></div>
      </div>
      <p id="xv-status" style="margin:8px 0;"></p>
      <div id="xv-trashed-wrap" style="display:none;background:#fff8e1;border-left:4px solid #f0a500;padding:10px 14px;margin-top:8px;">
        <p style="margin:0 0 6px;"><b>Đã chuyển vào thùng rác:</b>
          <button id="xv-retry-trashed" class="button button-small" style="margin-left:10px;">X</button>
        </p>
        <textarea id="xv-trashed" rows="4" style="width:100%;font-size:12px;font-family:monospace;" readonly></textarea>
      </div>
      <div id="xv-not-in-trash-wrap" style="display:none;background:#e8f5e9;border-left:4px solid #46b450;padding:10px 14px;margin-top:8px;">
        <p style="margin:0 0 6px;"><b>Không có trong Thùng Rác (bài đang sống):</b></p>
        <textarea id="xv-not-in-trash" rows="4" style="width:100%;font-size:12px;font-family:monospace;" readonly></textarea>
      </div>
      <div id="xv-failed-wrap" style="display:none;background:#ffeef0;border-left:4px solid #dc3232;padding:10px 14px;margin-top:8px;">
        <p style="margin:0 0 6px;"><b>Không tìm thấy bài viết:</b>
          <button id="xv-retry" class="button button-small" style="margin-left:10px;">X</button>
        </p>
        <textarea id="xv-failed" rows="4" style="width:100%;font-size:12px;font-family:monospace;" readonly></textarea>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  const API    = '<?php echo esc_js(rest_url("my-plugin/v1/bulk-delete")); ?>';
  const NONCE  = '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>';
  const BATCH  = 10;

  let stopped       = false;
  let allFailed     = [];
  let allTrashed    = [];
  let allNotInTrash = [];
  let allTitles     = [];
  let cumSuccess    = 0;
  let cumFailed     = 0;
  let cumTrashed    = 0;
  let cumNotInTrash = 0;

  document.getElementById('xv-start').addEventListener('click', function () {
    const raw    = document.getElementById('xv-urls').value.trim();
    const action = document.querySelector('input[name="xv-action"]:checked').value;
    const urls   = raw.split('\n').map(u => u.trim()).filter(u => u.length > 0);

    if (!urls.length) { alert('Chua co URL nao!'); return; }

    stopped       = false;
    allFailed     = [];
    allTrashed    = [];
    allNotInTrash = [];
    allTitles     = [];
    cumSuccess    = 0;
    cumFailed     = 0;
    cumTrashed    = 0;
    cumNotInTrash = 0;

    document.getElementById('xv-progress-wrap').style.display       = 'block';
    document.getElementById('xv-trashed-wrap').style.display        = 'none';
    document.getElementById('xv-not-in-trash-wrap').style.display   = 'none';
    document.getElementById('xv-failed-wrap').style.display         = 'none';
    document.getElementById('xv-start').style.display         = 'none';
    document.getElementById('xv-stop').style.display          = 'inline-block';
    setBar(0);
    setStatus('Dang xu ly...');

    const chunks = [];
    for (let i = 0; i < urls.length; i += BATCH) {
      chunks.push(urls.slice(i, i + BATCH));
    }

    processChunk(chunks, 0, action, urls.length);
  });

  document.getElementById('xv-stop').addEventListener('click', function () {
    stopped = true;
    setStatus('Da dung lai.');
    finish();
  });

  function processChunk(chunks, index, action, total) {
    if (stopped || index >= chunks.length) {
      finish();
      return;
    }

    const isLast  = index === chunks.length - 1;
    const done    = (index + 1) * BATCH;
    const pct     = Math.min(Math.round(done / total * 100), 100);

    setStatus('Dang xu ly ' + Math.min(done, total) + ' / ' + total + ' URL...');
    setBar(pct);

    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body: JSON.stringify({
        urls:    chunks[index],
        action:  action,
        is_last: isLast,
        summary: isLast ? { titles: allTitles, failed_urls: allFailed, already_trashed: allTrashed, not_in_trash: allNotInTrash } : null,
      }),
    })
    .then(r => r.json())
    .then(data => {
      cumSuccess    += data.success || 0;
      cumFailed     += (data.failed || []).length;
      cumTrashed    += (data.already_trashed || []).length;
      cumNotInTrash += (data.not_in_trash || []).length;
      allFailed      = allFailed.concat(data.failed || []);
      allTrashed     = allTrashed.concat(data.already_trashed || []);
      allNotInTrash  = allNotInTrash.concat(data.not_in_trash || []);
      allTitles      = allTitles.concat(data.success_titles || []);
      processChunk(chunks, index + 1, action, total);
    })
    .catch(() => {
      setStatus('Loi mang, thu lai...');
      setTimeout(() => processChunk(chunks, index, action, total), 3000);
    });
  }

  function finish() {
    setBar(100);
    let msg = 'Hoan thanh! Thanh cong: ' + cumSuccess;
    if (cumTrashed    > 0) msg += ' | Da trong trash: ' + cumTrashed;
    if (cumNotInTrash > 0) msg += ' | Khong trong trash: ' + cumNotInTrash;
    if (cumFailed     > 0) msg += ' | Khong tim thay: ' + cumFailed;
    setStatus(msg);
    document.getElementById('xv-start').style.display = 'inline-block';
    document.getElementById('xv-stop').style.display  = 'none';

    if (allTrashed.length > 0) {
      document.getElementById('xv-trashed-wrap').style.display = 'block';
      document.getElementById('xv-trashed').value = allTrashed.join('\n');
    }
    if (allNotInTrash.length > 0) {
      document.getElementById('xv-not-in-trash-wrap').style.display = 'block';
      document.getElementById('xv-not-in-trash').value = allNotInTrash.join('\n');
    }
    if (allFailed.length > 0) {
      document.getElementById('xv-failed-wrap').style.display = 'block';
      document.getElementById('xv-failed').value = allFailed.join('\n');
    }
  }

  document.getElementById('xv-retry-trashed').addEventListener('click', function () {
    document.getElementById('xv-urls').value = allTrashed.join('\n');
    document.querySelector('input[name="xv-action"][value="delete"]').checked = true;
    document.getElementById('xv-trashed-wrap').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  document.getElementById('xv-retry').addEventListener('click', function () {
    document.getElementById('xv-urls').value = allFailed.join('\n');
    document.getElementById('xv-failed-wrap').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  function setBar(pct) {
    document.getElementById('xv-bar').style.width = pct + '%';
  }

  function setStatus(msg) {
    document.getElementById('xv-status').textContent = msg;
  }
})();
</script>
