<?php
if (!defined('ABSPATH')) exit;

$tab  = sanitize_key($_GET['tab'] ?? 'sc_overview');
$days = (int) ($_GET['days'] ?? 28);
if (!in_array($days, [7, 28, 90])) $days = 28;

$end   = date('Y-m-d', strtotime('-1 day'));
$start = date('Y-m-d', strtotime("-{$days} days"));

$gsc_ok = My_GSC::is_configured();
$ga4_ok = My_GA4::is_configured();
?>

<div class="wrap">
  <h1>Google</h1>

  <?php if (!empty($_GET['google_saved'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
  <?php endif; ?>

  <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
    <span style="display:inline-block;padding:8px 10px;font-size:11px;color:#888;font-weight:600;text-transform:uppercase;">Search Console</span>
    <?php foreach (['sc_overview' => 'Tổng quan', 'sc_queries' => 'Từ khóa', 'sc_pages' => 'Trang'] as $slug => $label): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-google&tab={$slug}&days={$days}")); ?>"
         class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
        <?php echo $label; ?>
      </a>
    <?php endforeach; ?>

    <span style="display:inline-block;padding:8px 10px;font-size:11px;color:#888;font-weight:600;text-transform:uppercase;margin-left:12px;">Analytics GA4</span>
    <?php foreach (['ga4_overview' => 'Tổng quan', 'ga4_pages' => 'Trang', 'ga4_channels' => 'Nguồn traffic'] as $slug => $label): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-google&tab={$slug}&days={$days}")); ?>"
         class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
        <?php echo $label; ?>
      </a>
    <?php endforeach; ?>

    <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-google&tab=settings")); ?>"
       class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" style="margin-left:12px;">
      ⚙ Cài đặt
    </a>
  </nav>

  <?php
  // ===================== SETTINGS =====================
  if ($tab === 'settings'):
    $cfg = My_GSC::get_config();
  ?>
  <form method="post" style="max-width:620px;">
    <?php wp_nonce_field('xavia_google_save'); ?>
    <input type="hidden" name="xavia_google_save" value="1">

    <h3 style="margin-top:0;">Google OAuth2</h3>
    <table class="form-table" style="margin-bottom:0;">
      <tr>
        <th>Client ID</th>
        <td><input type="text" name="gsc_client_id" class="large-text" value="<?php echo esc_attr($cfg['client_id']); ?>"></td>
      </tr>
      <tr>
        <th>Client Secret</th>
        <td><input type="text" name="gsc_client_secret" class="regular-text" value="<?php echo esc_attr($cfg['client_secret']); ?>"></td>
      </tr>
      <tr>
        <th>Refresh Token</th>
        <td><input type="text" name="gsc_refresh_token" class="large-text" value="<?php echo esc_attr($cfg['refresh_token']); ?>"></td>
      </tr>
    </table>

    <h3>Search Console</h3>
    <table class="form-table" style="margin-bottom:0;">
      <tr>
        <th>Site URL</th>
        <td>
          <input type="text" name="gsc_site_url" class="regular-text" value="<?php echo esc_attr($cfg['site_url']); ?>" placeholder="https://example.com">
          <p class="description">Phải trùng chính xác với URL trong Search Console.</p>
        </td>
      </tr>
    </table>

    <h3>Analytics GA4</h3>
    <table class="form-table">
      <tr>
        <th>Property ID</th>
        <td>
          <input type="text" name="ga4_property_id" class="regular-text" value="<?php echo esc_attr(My_GA4::get_property_id()); ?>" placeholder="123456789">
          <p class="description">GA4 → Admin → Property Settings → Property ID (chỉ số).</p>
        </td>
      </tr>
    </table>

    <?php submit_button('Lưu cài đặt'); ?>
  </form>

  <?php
  // ===================== DATE FILTER =====================
  elseif (strpos($tab, 'sc_') === 0 && !$gsc_ok):
  ?>
    <div class="notice notice-warning"><p>Chưa cấu hình Search Console. Vào tab <b>⚙ Cài đặt</b> để nhập thông tin.</p></div>
  <?php elseif (strpos($tab, 'ga4_') === 0 && !$ga4_ok): ?>
    <div class="notice notice-warning"><p>Chưa cấu hình GA4. Vào tab <b>⚙ Cài đặt</b> để nhập thông tin.</p></div>

  <?php else: ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

  <div style="margin-bottom:16px;">
    <?php foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $lbl): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-google&tab={$tab}&days={$d}")); ?>"
         class="button <?php echo $days === $d ? 'button-primary' : ''; ?>" style="margin-right:6px;">
        <?php echo $lbl; ?>
      </a>
    <?php endforeach; ?>
    <span style="margin-left:10px;color:#888;font-size:13px;"><?php echo $start; ?> → <?php echo $end; ?></span>
  </div>

  <?php
  // ===================== SC OVERVIEW =====================
  if ($tab === 'sc_overview'):
    $summary = My_GSC::get_summary($start, $end);
    $chart   = My_GSC::get_chart($start, $end);
    if (isset($summary['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($summary['error']); ?></p></div>
    <?php else:
      $row = $summary['rows'][0] ?? [];
      $cards = [
        ['Clicks',      number_format($row['clicks'] ?? 0),                    '#0073aa'],
        ['Impressions', number_format($row['impressions'] ?? 0),               '#46b450'],
        ['CTR',         round(($row['ctr'] ?? 0) * 100, 2) . '%',             '#826eb4'],
        ['Vị trí TB',  isset($row['position']) ? round($row['position'], 1) : '—', '#dc3232'],
      ];
    ?>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
      <?php foreach ($cards as [$title, $val, $color]): ?>
      <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 28px;min-width:130px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06);">
        <div style="font-size:12px;color:#666;margin-bottom:6px;"><?php echo $title; ?></div>
        <div style="font-size:26px;font-weight:700;color:<?php echo $color; ?>;"><?php echo $val; ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!isset($chart['error']) && !empty($chart['rows'])): ?>
    <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin-bottom:24px;">
      <h3 style="margin-top:0;">Clicks theo ngày</h3>
      <canvas id="sc-chart" height="80"></canvas>
    </div>
    <script>
    (function(){
      var labels=[], clicks=[], imps=[];
      <?php foreach ($chart['rows'] as $r): ?>
      labels.push('<?php echo $r['keys'][0]; ?>');
      clicks.push(<?php echo (int)$r['clicks']; ?>);
      imps.push(<?php echo (int)$r['impressions']; ?>);
      <?php endforeach; ?>
      new Chart(document.getElementById('sc-chart').getContext('2d'),{
        type:'line',
        data:{labels,datasets:[
          {label:'Clicks',data:clicks,borderColor:'#0073aa',backgroundColor:'rgba(0,115,170,.1)',fill:true,tension:.3,pointRadius:2},
          {label:'Impressions',data:imps,borderColor:'#46b450',backgroundColor:'rgba(70,180,80,.07)',fill:true,tension:.3,pointRadius:2},
        ]},
        options:{plugins:{legend:{position:'top'}},scales:{x:{ticks:{maxTicksLimit:10}}}}
      });
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>

  <?php
  // ===================== SC QUERIES =====================
  elseif ($tab === 'sc_queries'):
    $data = My_GSC::get_queries($start, $end, 50);
    if (isset($data['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($data['error']); ?></p></div>
    <?php elseif (empty($data['rows'])): ?>
      <p>Không có dữ liệu.</p>
    <?php else: ?>
    <table class="widefat striped" style="max-width:900px;">
      <thead><tr><th>#</th><th>Từ khóa</th><th style="text-align:right;">Clicks</th><th style="text-align:right;">Impressions</th><th style="text-align:right;">CTR</th><th style="text-align:right;">Vị trí</th></tr></thead>
      <tbody>
        <?php foreach ($data['rows'] as $i => $row): ?>
        <tr>
          <td style="color:#888;"><?php echo $i + 1; ?></td>
          <td><?php echo esc_html($row['keys'][0]); ?></td>
          <td style="text-align:right;"><?php echo number_format($row['clicks']); ?></td>
          <td style="text-align:right;"><?php echo number_format($row['impressions']); ?></td>
          <td style="text-align:right;"><?php echo round($row['ctr'] * 100, 2); ?>%</td>
          <td style="text-align:right;"><?php echo round($row['position'], 1); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  <?php
  // ===================== SC PAGES =====================
  elseif ($tab === 'sc_pages'):
    $data = My_GSC::get_pages($start, $end, 50);
    if (isset($data['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($data['error']); ?></p></div>
    <?php elseif (empty($data['rows'])): ?>
      <p>Không có dữ liệu.</p>
    <?php else: ?>
    <table class="widefat striped" style="max-width:1000px;">
      <thead><tr><th>#</th><th>URL</th><th style="text-align:right;">Clicks</th><th style="text-align:right;">Impressions</th><th style="text-align:right;">CTR</th><th style="text-align:right;">Vị trí</th></tr></thead>
      <tbody>
        <?php foreach ($data['rows'] as $i => $row): ?>
        <tr>
          <td style="color:#888;"><?php echo $i + 1; ?></td>
          <td><a href="<?php echo esc_url($row['keys'][0]); ?>" target="_blank" style="word-break:break-all;"><?php echo esc_html(str_replace(My_GSC::get_config()['site_url'], '', $row['keys'][0]) ?: '/'); ?></a></td>
          <td style="text-align:right;"><?php echo number_format($row['clicks']); ?></td>
          <td style="text-align:right;"><?php echo number_format($row['impressions']); ?></td>
          <td style="text-align:right;"><?php echo round($row['ctr'] * 100, 2); ?>%</td>
          <td style="text-align:right;"><?php echo round($row['position'], 1); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  <?php
  // ===================== GA4 OVERVIEW =====================
  elseif ($tab === 'ga4_overview'):
    $summary = My_GA4::get_summary($start, $end);
    $chart   = My_GA4::get_chart($start, $end);
    if (isset($summary['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($summary['error']); ?></p></div>
    <?php else:
      $r       = $summary['rows'][0] ?? [];
      $dur_sec = (int) My_GA4::metric($r, 4);
      $cards   = [
        ['Sessions',   number_format(My_GA4::metric($r, 0)),                                 '#0073aa'],
        ['Users',      number_format(My_GA4::metric($r, 1)),                                 '#46b450'],
        ['Pageviews',  number_format(My_GA4::metric($r, 2)),                                 '#826eb4'],
        ['Bounce Rate',round((float) My_GA4::metric($r, 3) * 100, 1) . '%',                 '#dc3232'],
        ['Thời lượng', sprintf('%d:%02d', intdiv($dur_sec, 60), $dur_sec % 60),             '#f56e28'],
      ];
    ?>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
      <?php foreach ($cards as [$title, $val, $color]): ?>
      <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;min-width:130px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06);">
        <div style="font-size:12px;color:#666;margin-bottom:6px;"><?php echo $title; ?></div>
        <div style="font-size:26px;font-weight:700;color:<?php echo $color; ?>;"><?php echo $val; ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!isset($chart['error']) && !empty($chart['rows'])): ?>
    <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin-bottom:24px;">
      <h3 style="margin-top:0;">Sessions & Users theo ngày</h3>
      <canvas id="ga4-chart" height="80"></canvas>
    </div>
    <script>
    (function(){
      var labels=[], sessions=[], users=[];
      <?php foreach ($chart['rows'] as $r):
        $d = My_GA4::dimension($r, 0);
        $date = substr($d,0,4).'-'.substr($d,4,2).'-'.substr($d,6,2);
      ?>
      labels.push('<?php echo $date; ?>');
      sessions.push(<?php echo (int) My_GA4::metric($r, 0); ?>);
      users.push(<?php echo (int) My_GA4::metric($r, 1); ?>);
      <?php endforeach; ?>
      new Chart(document.getElementById('ga4-chart').getContext('2d'),{
        type:'line',
        data:{labels,datasets:[
          {label:'Sessions',data:sessions,borderColor:'#0073aa',backgroundColor:'rgba(0,115,170,.1)',fill:true,tension:.3,pointRadius:2},
          {label:'Users',data:users,borderColor:'#46b450',backgroundColor:'rgba(70,180,80,.07)',fill:true,tension:.3,pointRadius:2},
        ]},
        options:{plugins:{legend:{position:'top'}},scales:{x:{ticks:{maxTicksLimit:10}}}}
      });
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>

  <?php
  // ===================== GA4 PAGES =====================
  elseif ($tab === 'ga4_pages'):
    $data = My_GA4::get_top_pages($start, $end);
    if (isset($data['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($data['error']); ?></p></div>
    <?php elseif (empty($data['rows'])): ?>
      <p>Không có dữ liệu.</p>
    <?php else: ?>
    <table class="widefat striped" style="max-width:900px;">
      <thead><tr><th>#</th><th>Trang</th><th style="text-align:right;">Pageviews</th><th style="text-align:right;">Sessions</th><th style="text-align:right;">Thời lượng TB</th></tr></thead>
      <tbody>
        <?php foreach ($data['rows'] as $i => $row):
          $dur = (int) My_GA4::metric($row, 2);
        ?>
        <tr>
          <td style="color:#888;"><?php echo $i + 1; ?></td>
          <td><?php echo esc_html(My_GA4::dimension($row, 0)); ?></td>
          <td style="text-align:right;"><?php echo number_format(My_GA4::metric($row, 0)); ?></td>
          <td style="text-align:right;"><?php echo number_format(My_GA4::metric($row, 1)); ?></td>
          <td style="text-align:right;"><?php echo sprintf('%d:%02d', intdiv($dur, 60), $dur % 60); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  <?php
  // ===================== GA4 CHANNELS =====================
  elseif ($tab === 'ga4_channels'):
    $channels = My_GA4::get_channels($start, $end);
    $devices  = My_GA4::get_devices($start, $end);
  ?>
  <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:1;min-width:320px;">
      <h3>Nguồn traffic</h3>
      <?php if (isset($channels['error'])): ?>
        <p style="color:#c00;"><?php echo esc_html($channels['error']); ?></p>
      <?php elseif (!empty($channels['rows'])):
        $total = array_sum(array_map(function($r) { return (int) My_GA4::metric($r, 0); }, $channels['rows']));
      ?>
      <table class="widefat striped">
        <thead><tr><th>Kênh</th><th style="text-align:right;">Sessions</th><th style="text-align:right;">%</th></tr></thead>
        <tbody>
          <?php foreach ($channels['rows'] as $row):
            $s = (int) My_GA4::metric($row, 0);
          ?>
          <tr>
            <td><?php echo esc_html(My_GA4::dimension($row, 0)); ?></td>
            <td style="text-align:right;"><?php echo number_format($s); ?></td>
            <td style="text-align:right;"><?php echo $total ? round($s / $total * 100, 1) : 0; ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div style="min-width:240px;">
      <h3>Thiết bị</h3>
      <?php if (!isset($devices['error']) && !empty($devices['rows'])):
        $total_d = array_sum(array_map(function($r) { return (int) My_GA4::metric($r, 0); }, $devices['rows']));
      ?>
      <table class="widefat striped">
        <thead><tr><th>Thiết bị</th><th style="text-align:right;">Sessions</th><th style="text-align:right;">%</th></tr></thead>
        <tbody>
          <?php foreach ($devices['rows'] as $row):
            $s = (int) My_GA4::metric($row, 0);
          ?>
          <tr>
            <td><?php echo esc_html(My_GA4::dimension($row, 0)); ?></td>
            <td style="text-align:right;"><?php echo number_format($s); ?></td>
            <td style="text-align:right;"><?php echo $total_d ? round($s / $total_d * 100, 1) : 0; ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; // tabs ?>

  <?php endif; // configured ?>
</div>
