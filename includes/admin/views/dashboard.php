<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
  <h1>Xavia</h1>

  <nav class="nav-tab-wrapper">
    <a href="#" class="nav-tab nav-tab-active">Danh sách Chat ID</a>
  </nav>

  <div style="margin-top: 20px;">

    <?php $chats = My_Telegram::get_chat_ids(); ?>

    <h2>Telegram Chat IDs đã kết nối</h2>

    <?php if (empty($chats)) : ?>
      <p>Chưa có chat ID nào. Nhắn <code>/start</code> vào bot để thêm.</p>
    <?php else : ?>
      <table class="widefat striped" style="max-width: 650px;">
        <thead>
          <tr>
            <th>#</th>
            <th>Chat ID</th>
            <th>Loại</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($chats as $index => $chat) : ?>
            <tr>
              <td><?php echo $index + 1; ?></td>
              <td><code><?php echo esc_html($chat['id']); ?></code></td>
              <td><?php echo $chat['id'] < 0 ? 'Group' : 'Cá nhân'; ?></td>
              <td>
                <?php if ($chat['active']) : ?>
                  <span style="color: green; font-weight: bold;">● Active</span>
                <?php else : ?>
                  <span style="color: #999;">● Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" style="display:inline;">
                  <?php wp_nonce_field('xavia_toggle_chatid'); ?>
                  <input type="hidden" name="xavia_toggle_chatid" value="<?php echo esc_attr($chat['id']); ?>">
                  <button type="submit" class="button button-small">
                    <?php echo $chat['active'] ? 'Tắt' : 'Bật'; ?>
                  </button>
                </form>
                <form method="post" style="display:inline; margin-left: 5px;">
                  <?php wp_nonce_field('xavia_remove_chatid'); ?>
                  <input type="hidden" name="xavia_remove_chatid" value="<?php echo esc_attr($chat['id']); ?>">
                  <button type="submit" class="button button-small button-link-delete">Xóa</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </div>
</div>
