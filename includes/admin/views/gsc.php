<?php
if (!defined('ABSPATH')) exit;

$tab  = sanitize_key($_GET['tab'] ?? 'sc_overview');
$days = (int) ($_GET['days'] ?? 28);
if (!in_array($days, [7, 28, 90])) $days = 28;

$end   = date('Y-m-d', strtotime('-1 day'));
$start = date('Y-m-d', strtotime("-{$days} days"));
?>

<div class="wrap">
  <h1>Search Console</h1>

  <?php if (!empty($_GET['scan_sent'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã gửi báo cáo về Telegram.</p></div>
  <?php endif; ?>
  <?php if (!empty($_GET['schedule_saved'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã lưu lịch báo cáo.</p></div>
  <?php endif; ?>

  <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
    <?php foreach (['sc_overview' => 'Tổng quan', 'sc_queries' => 'Từ khóa', 'sc_pages' => 'Trang'] as $slug => $label): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-gsc&tab={$slug}&days={$days}")); ?>"
         class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
        <?php echo $label; ?>
      </a>
    <?php endforeach; ?>
    <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-gsc&tab=report")); ?>"
       class="nav-tab <?php echo $tab === 'report' ? 'nav-tab-active' : ''; ?>" style="margin-left:12px;">
      Lịch &amp; Scan
    </a>
  </nav>

  <?php if ($tab === 'report'): ?>

  <h3 style="margin-top:0;">Báo cáo tự động</h3>
  <?php
  $r_enabled = get_option('xavia_gsc_report_enabled', false);
  $r_time    = get_option('xavia_gsc_report_time', '08:00');
  $r_days    = (int) get_option('xavia_gsc_report_days', 7);
  $next_ts   = wp_next_scheduled('xavia_gsc_daily_report');
  ?>
  <form method="post" style="max-width:500px;">
    <?php wp_nonce_field('xavia_gsc_schedule_save'); ?>
    <input type="hidden" name="xavia_gsc_schedule_save" value="1">
    <table class="form-table">
      <tr>
        <th>Bật báo cáo tự động</th>
        <td><input type="checkbox" name="gsc_report_enabled" value="1" <?php checked($r_enabled); ?>></td>
      </tr>
      <tr>
        <th>Giờ gửi hàng ngày</th>
        <td>
          <input type="time" name="gsc_report_time" value="<?php echo esc_attr($r_time); ?>">
          <p class="description">Theo múi giờ của website.</p>
        </td>
      </tr>
      <tr>
        <th>Khoảng thời gian data</th>
        <td>
          <select name="gsc_report_days">
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
  <p style="color:#888;font-size:13px;">Lấy dữ liệu GSC lúc này và gửi về Telegram ngay lập tức.</p>
  <form method="post" style="display:flex;align-items:center;gap:10px;">
    <?php wp_nonce_field('xavia_gsc_scan_now'); ?>
    <input type="hidden" name="xavia_gsc_scan_now" value="1">
    <select name="gsc_scan_days">
      <?php foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $lbl): ?>
        <option value="<?php echo $d; ?>" <?php selected($r_days, $d); ?>><?php echo $lbl; ?></option>
      <?php endforeach; ?>
    </select>
    <?php submit_button('Scan & Gửi Telegram ngay', 'primary', 'submit', false); ?>
  </form>

  <?php else: ?>

  <div style="margin-bottom:16px;">
    <?php foreach ([7 => '7 ngày', 28 => '28 ngày', 90 => '90 ngày'] as $d => $lbl): ?>
      <a href="<?php echo esc_url(admin_url("admin.php?page=xavia-gsc&tab={$tab}&days={$d}")); ?>"
         class="button <?php echo $days === $d ? 'button-primary' : ''; ?>" style="margin-right:6px;">
        <?php echo $lbl; ?>
      </a>
    <?php endforeach; ?>
    <span style="margin-left:10px;color:#888;font-size:13px;"><?php echo $start; ?> → <?php echo $end; ?></span>
  </div>

  <?php
  if ($tab === 'sc_overview'):
    $summary = My_GSC::get_summary($start, $end);
    if (isset($summary['error'])): ?>
      <div class="notice notice-error"><p>Lỗi: <?php echo esc_html($summary['error']); ?></p></div>
    <?php else:
      $row   = $summary['rows'][0] ?? [];
      $cards = [
        ['Clicks',     number_format($row['clicks'] ?? 0),                         '#0073aa'],
        ['Impressions',number_format($row['impressions'] ?? 0),                    '#46b450'],
        ['CTR',        round(($row['ctr'] ?? 0) * 100, 2) . '%',                  '#826eb4'],
        ['Vị trí TB', isset($row['position']) ? round($row['position'], 1) : '—', '#dc3232'],
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
    <?php endif; ?>

  <?php elseif ($tab === 'sc_queries'):
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

  <?php elseif ($tab === 'sc_pages'):
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

  <?php endif; ?>

  <?php endif; ?>
</div>
