<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
  <h1>IP Blocker</h1>

  <?php if (!empty($_GET['unblocked'])): ?>
    <div class="notice notice-success is-dismissible"><p>Đã xoá block: <b><?php echo esc_html($_GET['unblocked']); ?></b></p></div>
  <?php endif; ?>
  <?php if (!empty($_GET['blocked'])): ?>
    <div class="notice notice-warning is-dismissible"><p>Đã block: <b><?php echo esc_html($_GET['blocked']); ?></b></p></div>
  <?php endif; ?>

  <div style="display:flex;gap:32px;flex-wrap:wrap;max-width:900px;margin-top:16px;">

    <div style="flex:2;min-width:320px;">
      <h3 style="margin-top:0;">Danh sach IP bi block</h3>
      <?php $list = My_IP_Blocker::get_list(); ?>
      <?php if (empty($list)): ?>
        <p style="color:#888;">Chưa có IP nào bị block.</p>
      <?php else: ?>
        <table class="widefat striped">
          <thead><tr><th>IP / CIDR</th><th>Ly do</th><th>Them bởi</th><th>Thời gian</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($list as $item): ?>
            <tr>
              <td><code><?php echo esc_html($item['cidr']); ?></code></td>
              <td><?php echo esc_html($item['reason'] ?: '—'); ?></td>
              <td><?php echo esc_html($item['added_by']); ?></td>
              <td style="white-space:nowrap"><?php echo esc_html($item['added_at']); ?></td>
              <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Xac nhan xoa block <?php echo esc_js($item['cidr']); ?>?')">
                  <?php wp_nonce_field('xavia_unblock_ip'); ?>
                  <input type="hidden" name="xavia_unblock_ip" value="<?php echo esc_attr($item['cidr']); ?>">
                  <button type="submit" class="button button-small">Xoá block</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Form them IP -->
    <div style="flex:1;min-width:220px;">
      <h3 style="margin-top:0;">Block IP / CIDR</h3>
      <form method="post">
        <?php wp_nonce_field('xavia_block_ip'); ?>
        <table class="form-table" style="margin:0;">
          <tr>
            <th style="padding:6px 0;">IP / CIDR</th>
            <td style="padding:6px 0;">
              <input type="text" name="xavia_block_cidr" class="regular-text" placeholder="1.2.3.4 hoac 1.2.3.0/24" required>
              <p class="description">Vi du: <code>185.223.152.0/24</code></p>
            </td>
          </tr>
          <tr>
            <th style="padding:6px 0;">Lý do</th>
            <td style="padding:6px 0;">
              <input type="text" name="xavia_block_reason" class="regular-text" placeholder="Brute force, spam,...">
            </td>
          </tr>
        </table>
        <p><button type="submit" name="xavia_block_ip_submit" class="button button-primary">Block</button></p>
      </form>
    </div>

  </div>
</div>
