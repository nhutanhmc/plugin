<?php if (!defined('ABSPATH')) exit;

$editing   = null;
$edit_id   = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
if ($edit_id) {
    $editing = My_Inject_Content::get_rule($edit_id);
}
$is_adding = isset($_GET['action']) && $_GET['action'] === 'add';
$show_form = $editing || $is_adding;

$categories = get_categories(['hide_empty' => false]);
$rules      = My_Inject_Content::get_rules();
?>

<div class="wrap">
  <h1>
    Chèn nội dung tự động vào bài viết
    <?php if (!$show_form): ?>
      <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-inject-content&action=add')); ?>"
         class="page-title-action">+ Thêm rule</a>
    <?php endif; ?>
  </h1>

  <?php if ($show_form): ?>
  <div style="max-width:800px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:24px;margin-top:16px;">
    <h2 style="margin-top:0;"><?php echo $editing ? 'Chỉnh sửa rule' : 'Thêm rule mới'; ?></h2>
    <form method="post">
      <?php wp_nonce_field('xavia_inject_save'); ?>
      <input type="hidden" name="xavia_inject_save" value="1">
      <?php if ($editing): ?>
        <input type="hidden" name="inject_id" value="<?php echo esc_attr($editing['id']); ?>">
      <?php endif; ?>

      <table class="form-table" style="max-width:700px;">
        <tr>
          <th style="width:140px;"><label>Ten rule</label></th>
          <td><input type="text" name="inject_name" class="regular-text"
              value="<?php echo esc_attr($editing['name'] ?? ''); ?>" required></td>
        </tr>
        <tr>
          <th><label>Danh mục</label></th>
          <td>
            <select name="inject_category" style="min-width:200px;">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat->term_id; ?>"
                  <?php selected(($editing['category'] ?? 0), $cat->term_id); ?>>
                  <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> bai)
                </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <th><label>Vị trí chèn</label></th>
          <td>
            <select name="inject_position" id="inject_position" onchange="toggleParagraph(this.value)" style="min-width:160px;">
              <option value="top"     <?php selected(($editing['position'] ?? ''), 'top'); ?>>Đầu bài viết</option>
              <option value="after_p" <?php selected(($editing['position'] ?? 'after_p'), 'after_p'); ?>>Sau đoạn thứ N</option>
              <option value="bottom"  <?php selected(($editing['position'] ?? ''), 'bottom'); ?>>Cuối bài viết</option>
            </select>
            <span id="paragraph_wrap" style="margin-left:10px;<?php echo (($editing['position'] ?? 'after_p') !== 'after_p') ? 'display:none' : ''; ?>">
              Đoạn số: <input type="number" name="inject_paragraph" min="1" max="20" style="width:60px;"
                value="<?php echo (int)($editing['paragraph'] ?? 2); ?>">
            </span>
          </td>
        </tr>
        <tr>
          <th><label>Trạng thái</label></th>
          <td>
            <label>
              <input type="checkbox" name="inject_active" value="1"
                <?php checked(!isset($editing) || !empty($editing['active'])); ?>>
              Bật ngay
            </label>
          </td>
        </tr>
      </table>

      <div style="margin:16px 0 8px;font-weight:600;">Nội dung chèn:</div>
      <?php
      wp_editor(
        $editing['content'] ?? '',
        'inject_content_editor',
        [
          'textarea_name' => 'inject_content',
          'media_buttons' => true,
          'teeny'         => false,
          'textarea_rows' => 10,
        ]
      );
      ?>

      <div style="margin-top:16px;display:flex;gap:8px;">
        <button type="submit" class="button button-primary">Lưu rule</button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-inject-content')); ?>"
           class="button">Hủy</a>
      </div>
    </form>
  </div>

  <?php else: ?>
  <?php if (empty($rules)): ?>
    <p style="margin-top:20px;color:#888;">Chưa có rule nào. Bấm <b>+ Thêm rule</b> để bắt đầu.</p>
  <?php else: ?>
  <table class="widefat striped" style="max-width:900px;margin-top:20px;">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th>Tên rule</th>
        <th>Danh mục</th>
        <th>Vị trí</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rules as $i => $rule):
        $cat = get_category($rule['category']);
        $cat_name = $cat ? $cat->name : '<em style="color:#c00">Da xoa</em>';
        $pos_label = match($rule['position']) {
          'top'     => 'Dau bai',
          'bottom'  => 'Cuoi bai',
          'after_p' => 'Sau doan ' . ($rule['paragraph'] ?? 1),
          default   => $rule['position'],
        };
      ?>
      <tr>
        <td><?php echo $i + 1; ?></td>
        <td><b><?php echo esc_html($rule['name']); ?></b></td>
        <td><?php echo $cat_name; ?></td>
        <td><?php echo esc_html($pos_label); ?></td>
        <td>
          <?php if ($rule['active']): ?>
            <span style="color:#46b450;font-weight:bold;">● Bật</span>
          <?php else: ?>
            <span style="color:#999;">● Tắt</span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:6px;flex-wrap:wrap;">
          <a href="<?php echo esc_url(admin_url('admin.php?page=xavia-inject-content&edit=' . $rule['id'])); ?>"
             class="button button-small">Sửa</a>

          <form method="post" style="display:inline;">
            <?php wp_nonce_field('xavia_inject_toggle'); ?>
            <input type="hidden" name="xavia_inject_toggle" value="<?php echo esc_attr($rule['id']); ?>">
            <button type="submit" class="button button-small">
              <?php echo $rule['active'] ? 'Tat' : 'Bat'; ?>
            </button>
          </form>

          <form method="post" style="display:inline;"
                onsubmit="return confirm('Xoa rule \'<?php echo esc_js($rule['name']); ?>\'?')">
            <?php wp_nonce_field('xavia_inject_delete'); ?>
            <input type="hidden" name="xavia_inject_delete" value="<?php echo esc_attr($rule['id']); ?>">
            <button type="submit" class="button button-small button-link-delete">Xoa</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
function toggleParagraph(val) {
  document.getElementById('paragraph_wrap').style.display = val === 'after_p' ? 'inline' : 'none';
}
</script>
