<?php if (!defined('ABSPATH')) exit;
wp_enqueue_media();
?>

<div class="wrap">
  <h1>Cập nhập ảnh hàng loạt</h1>

  <div style="max-width:700px;margin-top:20px;">

    <div style="margin-bottom:14px;">
      <label style="margin-right:20px;">
        <input type="radio" name="xv-src" value="url" checked> Từ thư viện media
      <label>
        <input type="radio" name="xv-src" value="upload"> Upload ảnh mới từ máy tính
      </label>
    </div>

    <div id="xv-src-url">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
        <button id="xv-pick-media" type="button" class="button">Chọn ảnh từ thư viện</button>
        <span id="xv-media-name" style="color:#888;font-size:12px;">Chưa chọn ảnh</span>
      </div>
      <div id="xv-media-preview" style="display:none;margin-bottom:8px;">
        <img id="xv-media-thumb" style="max-height:80px;max-width:200px;border:1px solid #ddd;border-radius:4px;object-fit:cover;">
      </div>
    </div>

    <div id="xv-src-upload" style="display:none;">
      <input id="xv-file" type="file" accept="image/*" style="margin-bottom:8px;">
      <p style="margin:4px 0 8px;font-size:12px;color:#888;">Khuyến nghị: ảnh tối đa <b>2560px</b>, dưới <b>8MB</b>. Ảnh quá lớn có thể gây lỗi xử lý trên máy chủ.</p>
      <div id="xv-preview-wrap" style="display:none;margin-bottom:8px;">
        <img id="xv-preview" style="max-height:120px;max-width:100%;border:1px solid #ddd;border-radius:4px;">
        <span id="xv-file-info" style="display:block;font-size:11px;color:#888;margin-top:4px;"></span>
      </div>
      <div id="xv-size-warn" style="display:none;background:#fff3cd;border-left:4px solid #f0a500;padding:8px 12px;margin-bottom:8px;font-size:12px;">
         Ảnh này lớn hơn 8MB. Máy chủ có thể không xử lý được. Vui lòng resize xuống dưới 2560px trước khi tải lên.
      </div>
      <span id="xv-upload-status" style="font-size:12px;color:#888;"></span>
    </div>

    <div style="margin:14px 0 6px;">
      <label><b>Danh sách URL (mỗi dòng 1 URL):</b></label>
    </div>
    <textarea id="xv-urls" rows="12" style="width:100%;font-size:12px;font-family:monospace;" placeholder="https://xavia.cloud/bai-viet-1/&#10;https://xavia.cloud/bai-viet-2/"></textarea>

    <div style="margin:12px 0;">
      <button id="xv-start" class="button button-primary">Bắt đầu</button>
      <button id="xv-stop" class="button" style="display:none;margin-left:8px;">Dừng lại</button>
    </div>

    <div id="xv-progress-wrap" style="display:none;margin-top:10px;">
      <div style="background:#e0e0e0;border-radius:4px;height:18px;width:100%;">
        <div id="xv-bar" style="width:0%;background:#0073aa;height:18px;border-radius:4px;transition:width .3s;"></div>
      </div>
      <p id="xv-status" style="margin:8px 0;"></p>
      <div id="xv-failed-wrap" style="display:none;background:#ffeef0;border-left:4px solid #dc3232;padding:10px 14px;margin-top:8px;">
        <p style="margin:0 0 6px;"><b>không tìm thấy bài viết:</b>
          <button id="xv-retry" class="button button-small" style="margin-left:10px;">X</button>
        </p>
        <textarea id="xv-failed" rows="5" style="width:100%;font-size:12px;font-family:monospace;" readonly></textarea>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  const API        = '<?php echo esc_js(rest_url("my-plugin/v1/bulk-featured-image")); ?>';
  const MEDIA_API  = '<?php echo esc_js(rest_url("wp/v2/media")); ?>';
  const NONCE      = '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>';
  const BATCH      = 10;

  let stopped        = false;
  let allFailed      = [];
  let allTitles      = [];
  let cumSuccess     = 0;
  let cumFailed      = 0;
  let selectedMediaId = '';

  document.querySelectorAll('input[name="xv-src"]').forEach(r => {
    r.addEventListener('change', function () {
      document.getElementById('xv-src-url').style.display    = this.value === 'url'    ? 'block' : 'none';
      document.getElementById('xv-src-upload').style.display = this.value === 'upload' ? 'block' : 'none';
    });
  });

  document.getElementById('xv-pick-media').addEventListener('click', function (e) {
    e.preventDefault();
    var frame = wp.media({
      title: 'Chon anh bia',
      button: { text: 'Chon anh nay' },
      multiple: false,
      library: { type: 'image' },
    });
    frame.on('select', function () {
      var att   = frame.state().get('selection').first().toJSON();
      var thumb = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
      selectedMediaId = att.id.toString();
      document.getElementById('xv-media-name').textContent = att.filename || att.title || ('ID: ' + att.id);
      document.getElementById('xv-media-name').style.color = '#333';
      document.getElementById('xv-media-thumb').src = thumb;
      document.getElementById('xv-media-preview').style.display = 'block';
    });
    frame.open();
  });

  document.getElementById('xv-file').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const wrap = document.getElementById('xv-preview-wrap');
    wrap.style.display = 'block';
    document.getElementById('xv-preview').src = URL.createObjectURL(file);
    document.getElementById('xv-upload-status').textContent = '';

    const mb = (file.size / 1048576).toFixed(1);
    document.getElementById('xv-file-info').textContent = file.name + ' — ' + mb + ' MB';
    document.getElementById('xv-size-warn').style.display = file.size > 8 * 1048576 ? 'block' : 'none';
  });

  document.getElementById('xv-start').addEventListener('click', async function () {
    const mode = document.querySelector('input[name="xv-src"]:checked').value;
    const raw  = document.getElementById('xv-urls').value.trim();
    const urls = raw.split('\n').map(u => u.trim()).filter(u => u.length > 0);

    if (!urls.length) { alert('Chua co URL bai viet!'); return; }

    let image = '';

    if (mode === 'url') {
      image = selectedMediaId;
      if (!image) { alert('Chua chon anh tu thu vien!'); return; }
    } else {
      const file = document.getElementById('xv-file').files[0];
      if (!file) { alert('Chua chon anh!'); return; }

      document.getElementById('xv-upload-status').textContent = 'Đang upload ảnh lên thư viện...';
      document.getElementById('xv-start').disabled = true;

      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', file.name);

      try {
        const res  = await fetch(MEDIA_API, { method: 'POST', headers: { 'X-WP-Nonce': NONCE }, body: formData });
        const data = await res.json();
        if (!data.id) { alert('Upload ảnh thất bại!'); document.getElementById('xv-start').disabled = false; return; }
        image = data.id.toString();
        document.getElementById('xv-upload-status').textContent = 'Upload thành công! ID: ' + data.id;
      } catch (e) {
        alert('Lỗi upload ảnh!');
        document.getElementById('xv-start').disabled = false;
        return;
      }

      document.getElementById('xv-start').disabled = false;
    }

    stopped    = false;
    allFailed  = [];
    allTitles  = [];
    cumSuccess = 0;
    cumFailed  = 0;

    document.getElementById('xv-progress-wrap').style.display = 'block';
    document.getElementById('xv-failed-wrap').style.display   = 'none';
    document.getElementById('xv-start').style.display         = 'none';
    document.getElementById('xv-stop').style.display          = 'inline-block';
    setBar(0);
    setStatus('Đang xử lý...');

    const chunks = [];
    for (let i = 0; i < urls.length; i += BATCH) chunks.push(urls.slice(i, i + BATCH));
    processChunk(chunks, 0, image, urls.length);
  });

  document.getElementById('xv-stop').addEventListener('click', function () {
    stopped = true;
    setStatus('Đã dừng lại.');
    finish();
  });

  function processChunk(chunks, index, image, total) {
    if (stopped || index >= chunks.length) { finish(); return; }

    const isLast = index === chunks.length - 1;
    const done   = (index + 1) * BATCH;

    setStatus('Đang xử lý ' + Math.min(done, total) + ' / ' + total + ' URL...');
    setBar(Math.min(Math.round(done / total * 100), 100));

    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
      body: JSON.stringify({
        image:   image,
        urls:    chunks[index],
        is_last: isLast,
        summary: isLast ? { titles: allTitles, failed_urls: allFailed } : null,
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) { setStatus('Loi: ' + data.error); finish(); return; }
      cumSuccess += data.success || 0;
      cumFailed  += (data.failed || []).length;
      allFailed   = allFailed.concat(data.failed || []);
      allTitles   = allTitles.concat(data.success_titles || []);
      processChunk(chunks, index + 1, image, total);
    })
    .catch(() => {
      setStatus('Lỗi mạng, thử lại...');
      setTimeout(() => processChunk(chunks, index, image, total), 3000);
    });
  }

  function finish() {
    setBar(100);
    setStatus('Hoàn thành! Thành công: ' + cumSuccess + ' | Thất bại: ' + cumFailed);
    document.getElementById('xv-start').style.display = 'inline-block';
    document.getElementById('xv-stop').style.display  = 'none';
    if (allFailed.length > 0) {
      document.getElementById('xv-failed-wrap').style.display = 'block';
      document.getElementById('xv-failed').value = allFailed.join('\n');
    }
  }

  document.getElementById('xv-retry').addEventListener('click', function () {
    document.getElementById('xv-urls').value = allFailed.join('\n');
    document.getElementById('xv-failed-wrap').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  function setBar(pct) { document.getElementById('xv-bar').style.width = pct + '%'; }
  function setStatus(msg) { document.getElementById('xv-status').textContent = msg; }
})();
</script>
