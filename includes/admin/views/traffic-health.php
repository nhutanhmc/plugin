<?php
if (!defined('ABSPATH')) exit;
$active_tab = sanitize_key($_GET['tab'] ?? 'traffic');
?>

<div class="wrap">
  <h1>Traffic Health
    <?php if ($active_tab === 'traffic'): ?>
      <span id="tr-status" style="font-size:13px;font-weight:normal;margin-left:10px;color:#888;">Đang tải...</span>
    <?php endif; ?>
  </h1>

  <?php if (!empty($_GET['settings_saved'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt Auto Block.</p></div>
  <?php endif; ?>

  <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-traffic-health&tab=traffic')); ?>"
       class="nav-tab <?php echo $active_tab === 'traffic' ? 'nav-tab-active' : ''; ?>">Traffic</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-traffic-health&tab=autoblock')); ?>"
       class="nav-tab <?php echo $active_tab === 'autoblock' ? 'nav-tab-active' : ''; ?>">Cài đặt thông báo</a>
  </nav>

  <?php if ($active_tab === 'autoblock'):
    $s = My_Traffic_Logger::get_settings();
    $traffic_rules = [
      'brute_force' => ['label' => 'Brute force login',   'unit' => 'lan thu'],
      'bot_spike'   => ['label' => 'Bot / Crawler spike', 'unit' => 'request'],
      '404_spike'   => ['label' => '404 spike',           'unit' => 'loi'],
      '5xx_spike'   => ['label' => '5xx Spike',           'unit' => 'loi'],
    ];
    $vps_rules = [
      'cpu_alert'  => ['label' => 'CPU cao', 'unit' => '%'],
      'ram_alert'  => ['label' => 'RAM cao', 'unit' => '%'],
      'disk_alert' => ['label' => 'Disk day', 'unit' => '%'],
    ];
  ?>
  <form method="post" style="max-width:860px;">
    <?php wp_nonce_field('xavia_autoblock_save'); ?>
    <input type="hidden" name="xavia_autoblock_save" value="1">

    <h3 style="margin-top:0;">Cảnh báo VPS</h3>
    <table class="widefat" style="border-collapse:collapse;margin-bottom:28px;">
      <thead>
        <tr style="background:#f0f0f0;">
          <th style="padding:10px;">ính năng</th>
          <th style="padding:10px;text-align:center;">Thông báo</th>
          <th style="padding:10px;">Ngưỡng</th>
          <th style="padding:10px;">Cooldown</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($vps_rules as $key => $meta):
        $cfg = $s[$key];
      ?>
        <tr style="border-top:1px solid #ddd;">
          <td style="padding:12px;"><b><?php echo $meta['label']; ?></b></td>
          <td style="padding:12px;text-align:center;">
            <input type="checkbox" name="settings[<?php echo $key; ?>][notify]" value="1" <?php checked(!empty($cfg['notify'])); ?>>
          </td>
          <td style="padding:12px;">
            <input type="number" name="settings[<?php echo $key; ?>][threshold]"
              value="<?php echo (int)$cfg['threshold']; ?>" min="1" max="100" style="width:60px;">
            <small><?php echo $meta['unit']; ?></small>
          </td>
          <td style="padding:12px;">
            <input type="number" name="settings[<?php echo $key; ?>][cooldown]"
              value="<?php echo (int)$cfg['cooldown']; ?>" min="1" style="width:60px;">
            <small>phut</small>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h3>Traffic Auto Block</h3>
    <table class="widefat" style="border-collapse:collapse;">
      <thead>
        <tr style="background:#f0f0f0;">
          <th style="padding:10px;">Tính năng</th>
          <th style="padding:10px;text-align:center;">Auto block</th>
          <th style="padding:10px;text-align:center;">Thông báo</th>
          <th style="padding:10px;">Ngưỡng</th>
          <th style="padding:10px;">Cửa sổ</th>
          <th style="padding:10px;">Cooldown</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($traffic_rules as $key => $meta):
        $cfg = $s[$key];
      ?>
        <tr style="border-top:1px solid #ddd;">
          <td style="padding:12px;"><b><?php echo $meta['label']; ?></b></td>
          <td style="padding:12px;text-align:center;">
            <input type="checkbox" name="settings[<?php echo $key; ?>][block]" value="1" <?php checked(!empty($cfg['block'])); ?>>
          </td>
          <td style="padding:12px;text-align:center;">
            <input type="checkbox" name="settings[<?php echo $key; ?>][notify]" value="1" <?php checked(!empty($cfg['notify'])); ?>>
          </td>
          <td style="padding:12px;">
            <input type="number" name="settings[<?php echo $key; ?>][threshold]"
              value="<?php echo (int)$cfg['threshold']; ?>" min="1" style="width:60px;">
            <small><?php echo $meta['unit']; ?></small>
          </td>
          <td style="padding:12px;">
            <input type="number" name="settings[<?php echo $key; ?>][window]"
              value="<?php echo (int)$cfg['window']; ?>" min="1" style="width:60px;">
            <small>phut</small>
          </td>
          <td style="padding:12px;">
            <input type="number" name="settings[<?php echo $key; ?>][cooldown]"
              value="<?php echo (int)$cfg['cooldown']; ?>" min="1" style="width:60px;">
            <small>phut</small>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <p style="margin-top:14px;">
      <button type="submit" class="button button-primary">Luu cai dat</button>
    </p>
  </form>

  <?php else: // TAB TRAFFIC ?>

  <div id="tr-error" style="display:none;background:#ffeef0;border-left:4px solid #dc3232;padding:10px;margin:10px 0;max-width:800px;"></div>

  <div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;max-width:900px;">
    <?php
    $cards = [
      ['tr-total-1h',   'Requests / 1h',      '#0073aa'],
      ['tr-total-5m',   'Requests / 5 phut',  '#0073aa'],
      ['tr-human',      'Nguoi that',          '#46b450'],
      ['tr-bot',        'Bot / Crawler',       '#f0a500'],
      ['tr-api',        'API calls',           '#826eb4'],
      ['tr-errors',     'Loi (4xx/5xx)',       '#dc3232'],
      ['tr-login',      'Login attempts',      '#dc3232'],
      ['tr-slow',       'Slow (>2s)',          '#f0a500'],
      ['tr-avg-time',   'Avg response',        '#00a0d2'],
    ];
    foreach ($cards as [$id, $label, $color]):
    ?>
    <?php
    $clickable = !in_array($id, ['tr-slow', 'tr-avg-time', 'tr-total-1h', 'tr-total-5m']);
    $cursor    = $clickable ? 'cursor:pointer;' : '';
    $title     = $clickable ? 'title="Bam de loc"' : '';
    ?>
    <div data-card="<?php echo $id; ?>" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:14px 18px;min-width:130px;text-align:center;<?php echo $cursor; ?>" <?php echo $title; ?>>
      <div id="<?php echo $id; ?>" style="font-size:24px;font-weight:bold;color:<?php echo $color; ?>;">--</div>
      <div style="font-size:11px;color:#888;margin-top:4px;"><?php echo $label; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:20px;flex-wrap:wrap;max-width:900px;">

    <div style="flex:1;min-width:280px;">
      <h3>Top IP (1h)</h3>
      <table class="widefat striped" id="tr-top-ips">
        <thead><tr><th>IP</th><th>Loại</th><th>Requests</th></tr></thead>
        <tbody><tr><td colspan="3">Đang tải...</td></tr></tbody>
      </table>
    </div>

    <div style="flex:1;min-width:280px;">
      <h3>Top URL (1h)</h3>
      <table class="widefat striped" id="tr-top-urls">
        <thead><tr><th>URL</th><th>Hits</th><th>Avg(s)</th></tr></thead>
        <tbody><tr><td colspan="3">Đang tải...</td></tr></tbody>
      </table>
    </div>

  </div>

  <div style="max-width:900px;margin-top:20px;">
    <h3>Status codes (1h)</h3>
    <div id="tr-status-codes" style="display:flex;gap:8px;flex-wrap:wrap;"></div>
  </div>

  <div style="max-width:900px;margin-top:20px;">
    <h3>
      30 request gần nhất
      <span id="tr-filter-label" style="display:none;font-size:12px;font-weight:normal;margin-left:10px;background:#0073aa;color:#fff;padding:3px 10px;border-radius:10px;"></span>
      <button id="tr-filter-clear" style="display:none;font-size:11px;margin-left:6px;" class="button button-small">Xóa lọc</button>
    </h3>
    <table class="widefat striped" id="tr-recent">
      <thead><tr><th>Gio</th><th>IP</th><th>Loại</th><th>Method</th><th>URL / UA</th><th>Status</th><th>Time(s)</th></tr></thead>
      <tbody><tr><td colspan="7">Đang tải...</td></tr></tbody>
    </table>
  </div>

  <p style="margin-top:12px;color:#888;font-size:12px;">
    Cap nhat moi 5 giay &nbsp;|&nbsp; Luu tru <?php echo My_Traffic_Logger::KEEP_DAYS; ?> ngay &nbsp;|&nbsp; Lan cuoi: <span id="tr-time">--</span>
  </p>

  <?php endif; // end tab traffic ?>
</div>

<script>
(function () {
  const API        = '<?php echo esc_js(rest_url("my-plugin/v1/traffic-stats")); ?>';
  const BLOCK_API  = '<?php echo esc_js(rest_url("my-plugin/v1/block-ip")); ?>';
  const NONCE      = '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>';

  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function flag(code) {
    if (!code) return '';
    return code.toUpperCase().replace(/./g, c => String.fromCodePoint(c.charCodeAt(0) + 127397));
  }

  const TYPE_LABEL = { human: 'Nguoi that', bot: 'Bot', api: 'API', admin: 'Admin', login: 'Login', static: 'Static' };
  const TYPE_COLOR = { human: '#46b450', bot: '#f0a500', api: '#826eb4', admin: '#0073aa', login: '#dc3232', static: '#aaa' };
  const STATUS_COLOR = (s) => s >= 500 ? '#dc3232' : s >= 400 ? '#f0a500' : s >= 300 ? '#826eb4' : '#46b450';

  var last_data          = null;
  var active_filter      = null;
  var last_filtered_rows = undefined;
  var blocked_cidrs      = [];

  function ip2long(ip) {
    var parts = ip.split('.');
    if (parts.length !== 4) return null;
    return parts.reduce(function(acc, p) { return (acc << 8) + parseInt(p, 10); }, 0) >>> 0;
  }

  function isBlocked(ip) {
    var ipLong = ip2long(ip);
    if (ipLong === null) return false;
    for (var i = 0; i < blocked_cidrs.length; i++) {
      var cidr = blocked_cidrs[i];
      if (!cidr.includes('/')) {
        if (ip === cidr) return true;
      } else {
        var parts  = cidr.split('/');
        var subLong = ip2long(parts[0]);
        var bits    = parseInt(parts[1], 10);
        if (subLong === null) continue;
        var mask = bits === 0 ? 0 : (~0 << (32 - bits)) >>> 0;
        if ((ipLong & mask) === (subLong & mask)) return true;
      }
    }
    return false;
  }

  function ipToSubnet24(ip) {
    var p = ip.split('.');
    return p.length === 4 ? p[0]+'.'+p[1]+'.'+p[2]+'.0/24' : ip;
  }

  function blockBtn(ip) {
    var subnet = ipToSubnet24(ip);
    if (isBlocked(ip)) {
      return '<span style="font-size:11px;background:#46b450;color:#fff;padding:2px 7px;border-radius:3px;">Blocked</span>';
    }
    return '<button class="button button-small btn-block-ip" data-ip="' + ip + '" data-cidr="' + ip + '" style="color:#dc3232;border-color:#dc3232;font-size:11px;">IP</button>'
         + ' <button class="button button-small btn-block-ip" data-ip="' + ip + '" data-cidr="' + subnet + '" style="color:#dc3232;border-color:#dc3232;font-size:11px;" title="Block ca dai ' + subnet + '">/24</button>';
  }

  // Card filter map
  var CARD_FILTER = {
    'tr-human':  { kind: 'type',   value: 'human',  label: 'Nguoi that' },
    'tr-bot':    { kind: 'type',   value: 'bot',    label: 'Bot' },
    'tr-api':    { kind: 'type',   value: 'api',    label: 'API' },
    'tr-errors': { kind: 'error',  value: null,     label: 'Loi 4xx/5xx' },
    'tr-login':  { kind: 'login',  value: null,     label: 'Login attempts' },
  };

  document.querySelectorAll('[data-card]').forEach(function(el) {
    el.addEventListener('click', function() {
      var f = CARD_FILTER[el.getAttribute('data-card')];
      if (!f) return;
      active_filter = (active_filter && active_filter.label === f.label) ? null : f;
      if (last_data) render(last_data);
      if (active_filter) fetch_filtered();
    });
  });

  document.getElementById('tr-filter-clear').addEventListener('click', function() {
    active_filter      = null;
    last_filtered_rows = undefined;
    if (last_data) render(last_data);
  });

  function fetch_filtered() {
    if (!active_filter) return;
    var url = API + '?filter=' + active_filter.kind + (active_filter.value ? '&filter_val=' + active_filter.value : '');
    fetch(url, { headers: { 'X-WP-Nonce': NONCE } })
      .then(r => r.json())
      .then(d => {
        if (!active_filter) return;
        last_filtered_rows = d.filtered || [];
        if (last_data) render(last_data, last_filtered_rows);
      });
  }

  function set(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }

  function render(d, filtered_rows) {
    if (d.blocked_ips) blocked_cidrs = d.blocked_ips;

    set('tr-total-1h',  d.total_1h);
    set('tr-total-5m',  d.total_5m);
    set('tr-human',     d.by_type.human  || 0);
    set('tr-bot',       d.by_type.bot    || 0);
    set('tr-api',       d.by_type.api    || 0);
    set('tr-login',     d.login_attempts || 0);
    set('tr-slow',      d.slow_requests  || 0);
    set('tr-avg-time',  d.avg_time + 's');

    var errors = 0;
    Object.entries(d.by_status || {}).forEach(([s, c]) => { if (parseInt(s) >= 400) errors += c; });
    set('tr-errors', errors);

    var ipsHtml = '';
    (d.top_ips || []).forEach(r => {
      var color = TYPE_COLOR[r.type] || '#888';
      ipsHtml += '<tr>'
        + '<td>' + flag(r.country) + ' ' + esc(r.ip) + '</td>'
        + '<td style="color:' + color + '">' + esc(TYPE_LABEL[r.type] || r.type) + '</td>'
        + '<td>' + esc(r.count) + '</td>'
        + '<td>' + blockBtn(r.ip) + '</td>'
        + '</tr>';
    });
    document.querySelector('#tr-top-ips tbody').innerHTML = ipsHtml || '<tr><td colspan="4">Khong co du lieu</td></tr>';
    document.querySelector('#tr-top-ips thead tr').innerHTML = '<th>IP</th><th>Loai</th><th>Requests</th><th></th>';

    var urlsHtml = '';
    (d.top_urls || []).forEach(r => {
      var url = r.url.length > 40 ? r.url.substring(0, 40) + '...' : r.url;
      urlsHtml += '<tr><td title="' + esc(r.url) + '">' + esc(url) + '</td><td>' + esc(r.count) + '</td><td>' + esc(r.avg_time) + '</td></tr>';
    });
    document.querySelector('#tr-top-urls tbody').innerHTML = urlsHtml || '<tr><td colspan="3">Khong co du lieu</td></tr>';

    var statusHtml = '';
    var active_s = (active_filter && active_filter.kind === 'status') ? active_filter.value : null;
    Object.entries(d.by_status || {}).sort((a,b) => parseInt(a[0]) - parseInt(b[0])).forEach(([s, c]) => {
      var bg      = STATUS_COLOR(parseInt(s));
      var outline = (active_s == s) ? 'box-shadow:0 0 0 3px #000;' : '';
      statusHtml += '<div data-status="' + s + '" style="background:' + bg + ';color:#fff;padding:6px 12px;border-radius:4px;font-weight:bold;cursor:pointer;' + outline + '">' + s + ': ' + c + '</div>';
    });
    document.getElementById('tr-status-codes').innerHTML = statusHtml;
    document.querySelectorAll('[data-status]').forEach(function(el) {
      el.addEventListener('click', function() {
        var s = el.getAttribute('data-status');
        var f = { kind: 'status', value: s, label: 'Status ' + s };
        active_filter = (active_filter && active_filter.value === s) ? null : f;
        if (last_data) render(last_data);
        if (active_filter) fetch_filtered();
      });
    });

    // Dung filtered_rows tu API neu co, fallback ve recent binh thuong
    var rows = filtered_rows !== undefined ? filtered_rows : (d.recent || []);

    // Update filter label
    var lbl = document.getElementById('tr-filter-label');
    var clr = document.getElementById('tr-filter-clear');
    if (active_filter) {
      lbl.textContent = 'Loc: ' + active_filter.label;
      lbl.style.display = 'inline';
      clr.style.display = 'inline';
    } else {
      lbl.style.display = 'none';
      clr.style.display = 'none';
    }

    // Highlight active card
    document.querySelectorAll('[data-card]').forEach(function(el) {
      var f = CARD_FILTER[el.getAttribute('data-card')];
      el.style.outline = (active_filter && f && active_filter.label === f.label) ? '2px solid #0073aa' : '';
    });

    var recentHtml = '';
    rows.forEach(function(r) {
      var color  = TYPE_COLOR[r.type] || '#888';
      var scolor = STATUS_COLOR(r.status);
      var url    = r.url.length > 50 ? r.url.substring(0, 50) + '...' : r.url;
      var ua     = r.ua ? '<br><small style="color:#aaa;font-size:10px;" title="' + esc(r.ua) + '">' + esc(r.ua.length > 60 ? r.ua.substring(0,60)+'...' : r.ua) + '</small>' : '';
      recentHtml += '<tr>'
        + '<td style="white-space:nowrap">' + esc(r.date || '') + '</td>'
        + '<td style="white-space:nowrap">' + flag(r.country) + ' ' + esc(r.ip) + ' ' + blockBtn(r.ip) + '</td>'
        + '<td style="color:' + color + '">' + esc(TYPE_LABEL[r.type] || r.type) + '</td>'
        + '<td>' + esc(r.method) + '</td>'
        + '<td title="' + esc(r.url) + '">' + esc(url) + ua + '</td>'
        + '<td style="color:' + scolor + ';font-weight:bold">' + esc(r.status) + '</td>'
        + '<td>' + (r.time > 0 ? esc(r.time) + 's' : '-') + '</td>'
        + '</tr>';
    });
    document.querySelector('#tr-recent tbody').innerHTML = recentHtml || '<tr><td colspan="7">Khong co du lieu</td></tr>';

    set('tr-time', d.time);
  }

  function fetch_stats() {
    fetch(API, { headers: { 'X-WP-Nonce': NONCE } })
      .then(r => r.json())
      .then(d => {
        document.getElementById('tr-error').style.display = 'none';

        if (d.error) {
          document.getElementById('tr-error').style.display = 'block';
          document.getElementById('tr-error').textContent = 'Loi: ' + d.error;
          document.getElementById('tr-status').textContent = 'Loi';
          document.getElementById('tr-status').style.color = '#dc3232';
          setTimeout(fetch_stats, 5000);
          return;
        }

        document.getElementById('tr-status').textContent = 'Status';
        document.getElementById('tr-status').style.color = '#46b450';

        last_data = d;
        render(d, active_filter ? last_filtered_rows : undefined);
        if (active_filter) fetch_filtered();
        setTimeout(fetch_stats, 5000);
      })
      .catch(() => {
        document.getElementById('tr-status').textContent = 'Loi ket noi, thu lai...';
        document.getElementById('tr-status').style.color = '#dc3232';
        setTimeout(fetch_stats, 3000);
      });
  }

  fetch_stats();

  // Block IP handler (event delegation vi tbody duoc render lai)
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-block-ip');
    if (!btn) return;
    var ip   = btn.getAttribute('data-ip');
    var cidr = btn.getAttribute('data-cidr');
    var label = cidr.includes('/') ? 'ca dai ' + cidr : 'IP ' + cidr;
    if (!confirm('Block ' + label + '?')) return;
    btn.disabled = true;
    btn.textContent = '...';
    fetch(BLOCK_API, {
      method: 'POST',
      headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
      body: JSON.stringify({ ip: cidr, reason: 'Block tu Traffic Health' }),
    })
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        if (!blocked_cidrs.includes(cidr)) blocked_cidrs.push(cidr);
        if (last_data) render(last_data, active_filter ? last_filtered_rows : undefined);
      } else {
        btn.disabled = false;
        btn.textContent = cidr.includes('/') ? '/24' : 'IP';
        alert('Loi: ' + (d.error || 'Unknown'));
      }
    })
    .catch(() => { btn.disabled = false; btn.textContent = cidr.includes('/') ? '/24' : 'IP'; });
  });
})();
</script>
