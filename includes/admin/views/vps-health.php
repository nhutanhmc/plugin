<?php
if (!defined('ABSPATH')) exit;
if (!function_exists('xavia_bar')):
function xavia_bar($id) {
  return "<div style='background:#e0e0e0;border-radius:4px;height:12px;width:100%;max-width:320px;margin-top:5px;'>
    <div id='$id' style='width:0%;height:12px;border-radius:4px;background:#46b450;transition:width .5s,background .5s;'></div>
  </div>";
}
endif;
?>

<div class="wrap">
  <h1>
    VPS Health
    <span id="vps-status" style="font-size:13px;font-weight:normal;margin-left:10px;color:#888;">Đang tải...</span>
  </h1>

  <table class="widefat" style="max-width:720px;margin-top:20px;">
    <thead>
      <tr><th style="width:200px;">Thông số</th><th>Giá trị</th></tr>
    </thead>
    <tbody>

      <tr><td colspan="2" style="background:#f0f0f0;font-weight:bold;padding:6px 10px;">Server</td></tr>

      <tr>
        <td><b>OS</b></td>
        <td><span id="server-os">--</span> <small id="server-os-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>Web Server</b></td>
        <td><span id="server-software">--</span> <small id="server-software-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>Hostname</b></td>
        <td><span id="server-hostname">--</span> <small id="server-hostname-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>CPU Cores</b></td>
        <td><span id="cpu-cores">--</span> <small id="cpu-cores-err" style="color:#c00;"></small></td>
      </tr>

      <tr>
        <td><b>CPU Load</b></td>
        <td>
          <span id="cpu-pct" style="font-size:20px;font-weight:bold;">--</span>%
          &nbsp;<small>Steal: <span id="cpu-steal" style="font-weight:bold;">--</span>%</small>
          <?php echo xavia_bar('cpu-bar'); ?>
          <small id="cpu-err" style="color:#c00;"></small>
        </td>
      </tr>

      <tr>
        <td><b>RAM</b></td>
        <td>
          <span id="ram-used">--</span> MB / <span id="ram-total">--</span> MB &nbsp;<small>(<span id="ram-pct">--</span>%)</small>
          <?php echo xavia_bar('ram-bar'); ?>
          <small id="ram-err" style="color:#c00;"></small>
        </td>
      </tr>

      <tr>
        <td><b>Disk</b></td>
        <td>
          <span id="disk-used">--</span> GB / <span id="disk-total">--</span> GB &nbsp;<small>(<span id="disk-pct">--</span>%)</small>
          <?php echo xavia_bar('disk-bar'); ?>
          <small id="disk-err" style="color:#c00;"></small>
        </td>
      </tr>

      <tr>
        <td><b>Uptime</b></td>
        <td><span id="uptime">--</span> <small id="uptime-err" style="color:#c00;"></small></td>
      </tr>

      <tr><td colspan="2" style="background:#f0f0f0;font-weight:bold;padding:6px 10px;">WordPress / PHP</td></tr>

      <tr>
        <td><b>PHP Memory</b></td>
        <td>
          <span id="wp-mem-used">--</span> MB (peak: <span id="wp-mem-peak">--</span> MB) / limit: <span id="wp-mem-limit">--</span>
          <?php echo xavia_bar('wp-mem-bar'); ?>
          <small id="wp-mem-err" style="color:#c00;"></small>
        </td>
      </tr>

      <tr>
        <td><b>DB Size</b></td>
        <td><span id="wp-db-size">--</span> MB <small id="wp-db-size-err" style="color:#c00;"></small></td>
      </tr>

      <tr>
        <td><b>DB Queries</b></td>
        <td><span id="wp-db-q">--</span> queries/request <small id="wp-db-q-err" style="color:#c00;"></small></td>
      </tr>

      <tr>
        <td><b>Active Plugins</b></td>
        <td><span id="wp-plugins">--</span> plugin <small id="wp-plugins-err" style="color:#c00;"></small></td>
      </tr>

      <tr>
        <td><b>WP Cron</b></td>
        <td><span id="wp-cron">--</span> jobs dang cho <small id="wp-cron-err" style="color:#c00;"></small></td>
      </tr>

      <tr><td colspan="2" style="background:#f0f0f0;font-weight:bold;padding:6px 10px;">Database</td></tr>

      <tr>
        <td><b>DB Version</b></td>
        <td><span id="db-version">--</span> <small id="db-version-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>Max Connections</b></td>
        <td><span id="db-max-conn">--</span> <small id="db-max-conn-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>Max Packet</b></td>
        <td><span id="db-max-packet">--</span> <small id="db-max-packet-err" style="color:#c00;"></small></td>
      </tr>

      <tr><td colspan="2" style="background:#f0f0f0;font-weight:bold;padding:6px 10px;">PHP</td></tr>

      <tr>
        <td><b>PHP Version</b></td>
        <td><?php echo phpversion(); ?></td>
      </tr>
      <tr>
        <td><b>Max Upload</b></td>
        <td><span id="php-upload">--</span> <small id="php-upload-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>Max Post</b></td>
        <td><span id="php-post">--</span> <small id="php-post-err" style="color:#c00;"></small></td>
      </tr>
      <tr>
        <td><b>Max Exec Time</b></td>
        <td><span id="php-exec">--</span> <small id="php-exec-err" style="color:#c00;"></small></td>
      </tr>

      <tr><td colspan="2" style="background:#f0f0f0;font-weight:bold;padding:6px 10px;">WordPress</td></tr>

      <tr>
        <td><b>WP Version</b></td>
        <td><?php echo get_bloginfo('version'); ?></td>
      </tr>
      <tr>
        <td><b>Server IP</b></td>
        <td><?php echo esc_html($_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname())); ?></td>
      </tr>

    </tbody>
  </table>

  <p style="margin-top:12px;color:#888;font-size:12px;">
    Cap nhat moi 2 giay &nbsp;|&nbsp; Lan cuoi: <span id="last-update">--</span>
  </p>
</div>


<script>
(function () {
  const API   = '<?php echo esc_js(rest_url("my-plugin/v1/vps-stats")); ?>';
  const NONCE = '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>';

  function barColor(pct) {
    return pct >= 90 ? '#dc3232' : pct >= 70 ? '#f0a500' : '#46b450';
  }

  function setBar(id, pct) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.width      = Math.min(pct, 100) + '%';
    el.style.background = barColor(pct);
  }

  function set(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function setErr(id, msg) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg ? 'Loi: ' + msg : '';
  }

  function apply(metric, valFn, errId) {
    if (metric.error) {
      setErr(errId, metric.error);
    } else {
      setErr(errId, '');
      valFn(metric.value);
    }
  }

  function fetchStats() {
    fetch(API, { headers: { 'X-WP-Nonce': NONCE } })
      .then(r => r.json())
      .then(d => {
        var status = document.getElementById('vps-status');
        status.textContent = 'Realtime';
        status.style.color = '#46b450';

        apply(d.cpu, function(v) {
          set('cpu-pct', v.pct); setBar('cpu-bar', v.pct);
          var stealEl = document.getElementById('cpu-steal');
          if (stealEl) { stealEl.textContent = v.steal; stealEl.style.color = v.steal > 2 ? '#dc3232' : '#46b450'; }
        }, 'cpu-err');

        apply(d.ram, function(v) {
          set('ram-used', v.used); set('ram-total', v.total); set('ram-pct', v.pct); setBar('ram-bar', v.pct);
        }, 'ram-err');

        apply(d.disk, function(v) {
          set('disk-used', v.used); set('disk-total', v.total); set('disk-pct', v.pct); setBar('disk-bar', v.pct);
        }, 'disk-err');

        apply(d.uptime, function(v) { set('uptime', v); }, 'uptime-err');

        apply(d.wp_memory, function(v) {
          set('wp-mem-used', v.used); set('wp-mem-peak', v.peak); set('wp-mem-limit', v.limit);
          if (v.limit_mb > 0) setBar('wp-mem-bar', Math.round(v.used / v.limit_mb * 100));
        }, 'wp-mem-err');

        apply(d.wp_db_size,    function(v) { set('wp-db-size', v); }, 'wp-db-size-err');
        apply(d.wp_db_queries, function(v) { set('wp-db-q', v); },    'wp-db-q-err');
        apply(d.wp_plugins,    function(v) { set('wp-plugins', v); }, 'wp-plugins-err');
        apply(d.wp_cron,       function(v) { set('wp-cron', v); },    'wp-cron-err');

        apply(d.server_os,       function(v) { set('server-os', v); },       'server-os-err');
        apply(d.server_software, function(v) { set('server-software', v); }, 'server-software-err');
        apply(d.server_hostname, function(v) { set('server-hostname', v); }, 'server-hostname-err');
        apply(d.cpu_cores,       function(v) { set('cpu-cores', v + ' cores'); }, 'cpu-cores-err');

        apply(d.db_version,         function(v) { set('db-version', v); },     'db-version-err');
        apply(d.db_max_connections, function(v) { set('db-max-conn', v); },    'db-max-conn-err');
        apply(d.db_max_packet,      function(v) { set('db-max-packet', v); },  'db-max-packet-err');

        apply(d.php_upload_max, function(v) { set('php-upload', v); }, 'php-upload-err');
        apply(d.php_post_max,   function(v) { set('php-post', v); },   'php-post-err');
        apply(d.php_max_exec,   function(v) { set('php-exec', v); },   'php-exec-err');

        set('last-update', d.time);
        setTimeout(fetchStats, 2000);
      })
      .catch(function () {
        var status = document.getElementById('vps-status');
        status.textContent = 'Loi ket noi, thu lai...';
        status.style.color = '#dc3232';
        setTimeout(fetchStats, 2000);
      });
  }

  fetchStats();
})();
</script>
