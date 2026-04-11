<?php
if (!defined('ABSPATH')) exit;

$tab  = sanitize_key($_GET['tab'] ?? 'ga4_overview');
$days = (int) ($_GET['days'] ?? 28);
if (!in_array($days, [7, 28, 90])) $days = 28;

$end   = date('Y-m-d');
$start = date('Y-m-d', strtotime("-{$days} days"));

$ga4_ok = My_GA4::is_configured();
?>

<div class="wrap">
  <h1>Analytics GA4</h1>

  <?php if (!empty($_GET['ga4_saved'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
  <?php endif; ?>
  <?php if (!empty($_GET['scan_sent'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã gửi báo cáo về Telegram.</p></div>
  <?php endif; ?>
  <?php if (!empty($_GET['schedule_saved'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã lưu lịch báo cáo.</p></div>
  <?php endif; ?>

  <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
    <?php foreach (['ga4_overview' => 'Tổng quan', 'ga4_pages' => 'Trang', 'ga4_channels' => 'Nguồn traffic'] as $slug => $label): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-ga4&tab={$slug}&days={$days}")); ?>"
         class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
        <?php echo $label; ?>
      </a>
    <?php endforeach; ?>
    <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-ga4&tab=report")); ?>"
       class="nav-tab <?php echo $tab === 'report' ? 'nav-tab-active' : ''; ?>" style="margin-left:6px;">
      Lịch &amp; Scan
    </a>
    <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-ga4&tab=settings")); ?>"
       class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" style="margin-left:6px;">
      ⚙ Cài đặt
    </a>
  </nav>

  <?php if ($tab === 'report'):
    $r_enabled = get_option('xavia_ga4_report_enabled', false);
    $r_time    = get_option('xavia_ga4_report_time', '08:00');
    $r_days    = (int) get_option('xavia_ga4_report_days', 7);
    $next_ts   = wp_next_scheduled('xavia_ga4_daily_report');
  ?>

  <h3 style="margin-top:0;">Báo cáo tự động</h3>
  <form method="post" style="max-width:500px;">
    <?php wp_nonce_field('xavia_ga4_schedule_save'); ?>
    <input type="hidden" name="xavia_ga4_schedule_save" value="1">
    <table class="form-table">
      <tr>
        <th>Bật báo cáo tự động</th>
        <td><input type="checkbox" name="ga4_report_enabled" value="1" <?php checked($r_enabled); ?>></td>
      </tr>
      <tr>
        <th>Giờ gửi hàng ngày</th>
        <td>
          <input type="time" name="ga4_report_time" value="<?php echo esc_attr($r_time); ?>">
          <p class="description">Theo múi giờ của website.</p>
        </td>
      </tr>
      <tr>
        <th>Khoảng thời gian data</th>
        <td>
          <select name="ga4_report_days">
            <?php foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $lbl): ?>
              <option value="<?php echo $d; ?>" <?php selected($r_days, $d); ?>><?php echo $lbl; ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <?php if ($next_ts): ?>
      <tr>
        <th>Lần chạy tiếp theo</th>
        <td style="color:#46b450;font-weight:600;"><?php echo wp_date('d/m/Y H:i', $next_ts); ?></td>
      </tr>
      <?php endif; ?>
    </table>
    <?php submit_button('Lưu lịch', 'secondary'); ?>
  </form>

  <hr style="margin:24px 0;">

  <h3>Scan ngay</h3>
  <p style="color:#888;font-size:13px;">Lấy dữ liệu GA4 lúc này và gửi về Telegram ngay lập tức.</p>
  <form method="post" style="display:flex;align-items:center;gap:10px;">
    <?php wp_nonce_field('xavia_ga4_scan_now'); ?>
    <input type="hidden" name="xavia_ga4_scan_now" value="1">
    <select name="ga4_scan_days">
      <?php foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $lbl): ?>
        <option value="<?php echo $d; ?>" <?php selected($r_days, $d); ?>><?php echo $lbl; ?></option>
      <?php endforeach; ?>
    </select>
    <?php submit_button('Scan & Gửi Telegram ngay', 'primary', 'submit', false); ?>
  </form>

  <?php
  // ===================== SETTINGS =====================
  elseif ($tab === 'settings'):
  ?>
  <form method="post" style="max-width:620px;">
    <?php wp_nonce_field('xavia_ga4_save'); ?>
    <input type="hidden" name="xavia_ga4_save" value="1">

    <h3 style="margin-top:0;">Analytics GA4</h3>
    <p class="description" style="margin-bottom:12px;">
      OAuth credentials dùng chung với Search Console. Cấu hình tại
      <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-gsc&tab=settings')); ?>">Search Console → Cài đặt</a>.
    </p>
    <table class="form-table">
      <tr>
        <th>Measurement ID</th>
        <td>
          <input type="text" name="ga4_measurement_id" class="regular-text"
                 value="<?php echo esc_attr(My_GA4::get_measurement_id()); ?>"
                 placeholder="G-XXXXXXXXXX">
          <p class="description">GA4 → Admin → Data Streams → chọn stream → Measurement ID (dạng G-XXXXXXXXXX). Dùng để inject tracking tag vào website tự động.</p>
        </td>
      </tr>
      <tr>
        <th>Property ID</th>
        <td>
          <input type="text" name="ga4_property_id" class="regular-text"
                 value="<?php echo esc_attr(My_GA4::get_property_id()); ?>"
                 placeholder="123456789">
          <p class="description">GA4 → Admin → Property Settings → Property ID (chỉ số). Dùng để lấy dữ liệu báo cáo.</p>
        </td>
      </tr>
    </table>

    <?php submit_button('Lưu cài đặt'); ?>
  </form>

  <hr style="margin:24px 0;">

  <h3>Kiểm tra tracking tag</h3>
  <?php
  $mid        = My_GA4::get_measurement_id();
  $tag_result = get_transient('xavia_ga4_tag_check');
  if ($tag_result !== false):
  ?>
  <div style="padding:10px 14px;margin-bottom:12px;border-left:4px solid <?php echo $tag_result ? '#46b450' : '#dc3232'; ?>;background:#fff;">
    <?php echo $tag_result
      ? "Tracking tag <b>" . esc_html($mid) . "</b> đã được cài đúng trên website."
      : "Không tìm thấy tag <b>" . esc_html($mid) . "</b> trên website. Kiểm tra lại Measurement ID hoặc plugin inject tag.";
    ?>
  </div>
  <?php endif; ?>
  <form method="post">
    <?php wp_nonce_field('xavia_ga4_check_tag'); ?>
    <input type="hidden" name="xavia_ga4_check_tag" value="1">
    <?php submit_button('Kiểm tra ngay', 'secondary', 'submit', false); ?>
  </form>

  <hr style="margin:24px 0;">
  <h3>Cache</h3>
  <?php if (!empty($_GET['cache_cleared'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã xóa cache GA4.</p></div>
  <?php endif; ?>
  <p style="color:#888;font-size:13px;">Xóa toàn bộ cache data GA4 để plugin gọi lại API ngay lập tức.</p>
  <form method="post">
    <?php wp_nonce_field('xavia_ga4_clear_cache'); ?>
    <input type="hidden" name="xavia_ga4_clear_cache" value="1">
    <?php submit_button('Xóa cache', 'delete', 'submit', false); ?>
  </form>

  <?php if (My_GA4::get_property_id() && My_GSC::is_configured()):
    $test = My_GA4::get_summary(date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-1 day')));
  ?>
  <hr style="margin:24px 0;">
  <h3>Test kết nối</h3>
  <p style="color:#888;font-size:13px;">Gọi API GA4 với 7 ngày gần nhất, hiển thị response thô để debug.</p>
  <pre style="background:#f6f7f7;border:1px solid #ddd;padding:12px;max-width:720px;overflow:auto;font-size:12px;"><?php echo esc_html(json_encode($test, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
  <?php endif; ?>

  <?php elseif (!$ga4_ok): ?>
    <div class="notice notice-warning">
      <p>Chưa cấu hình GA4. Vào tab <b>⚙ Cài đặt</b> để nhập Property ID, và đảm bảo đã cấu hình OAuth tại trang
        <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-gsc&tab=settings')); ?>">Search Console → Cài đặt</a>.
      </p>
    </div>

  <?php else: ?>

  <div style="margin-bottom:16px;">
    <?php foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $lbl): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-ga4&tab={$tab}&days={$d}")); ?>"
         class="button <?php echo $days === $d ? 'button-primary' : ''; ?>" style="margin-right:6px;">
        <?php echo $lbl; ?>
      </a>
    <?php endforeach; ?>
    <span style="margin-left:10px;color:#888;font-size:13px;"><?php echo $start; ?> → <?php echo $end; ?></span>
  </div>

  <?php
  // ===================== GA4 OVERVIEW =====================
  if ($tab === 'ga4_overview'):
    $summary = My_GA4::get_summary($start, $end);
    if (isset($summary['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($summary['error']); ?></p></div>
    <?php elseif (empty($summary['rows'])): ?>
      <div class="notice notice-warning"><p>GA4 kết nối thành công nhưng chưa có dữ liệu. Kiểm tra lại GA4 tracking tag đã được cài trên website chưa.</p></div>
    <?php else:
      $r       = $summary['rows'][0];
      $dur_sec = (int) My_GA4::metric($r, 4);
      $cards   = [
        ['Sessions',    number_format(My_GA4::metric($r, 0)),                              '#0073aa'],
        ['Users',       number_format(My_GA4::metric($r, 1)),                              '#46b450'],
        ['Pageviews',   number_format(My_GA4::metric($r, 2)),                              '#826eb4'],
        ['Bounce Rate', round((float) My_GA4::metric($r, 3) * 100, 1) . '%',              '#dc3232'],
        ['Thời lượng',  sprintf('%d:%02d', intdiv($dur_sec, 60), $dur_sec % 60),          '#f56e28'],
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

    <?php endif; ?>

  <?php
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

  <?php endif; ?>

  <?php endif;  ?>
</div>
