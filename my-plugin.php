<?php
/**
 * Plugin Name: Pj1-xavia
 * Description: Plugin đa tính năng
 * Version: 1.0
 * Author: Xavia
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'config.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/class-telegram.php';
require_once plugin_dir_path(__FILE__) . 'includes/api/class-telegram-webhook-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/api/class-vps-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/api/class-bulk-delete-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/api/class-bulk-featured-image-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/class-ip-blocker.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/class-traffic-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/class-inject-content.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/class-gsc.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/class-ga4.php';

My_Inject_Content::init();
require_once plugin_dir_path(__FILE__) . 'includes/api/class-ip-blocker-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/api/class-traffic-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/class-admin-menu.php';

My_Traffic_Logger::init();

add_action('rest_api_init', ['My_Telegram_Webhook_API', 'register']);
add_action('rest_api_init', ['My_VPS_API', 'register']);
add_action('rest_api_init', ['My_Bulk_Delete_API', 'register']);
add_action('rest_api_init', ['My_Bulk_Featured_Image_API', 'register']);
add_action('rest_api_init', ['My_IP_Blocker_API', 'register']);
add_action('rest_api_init', ['My_Traffic_API', 'register']);
add_action('admin_menu', ['Xavia_Admin_Menu', 'register']);

// Xu ly inject content rules
add_action('admin_init', function () {
  if (isset($_POST['xavia_inject_save'])) {
    check_admin_referer('xavia_inject_save');
    $is_edit = !empty($_POST['inject_id']);
    $data = [
      'id'        => $_POST['inject_id'] ?? '',
      'name'      => $_POST['inject_name'] ?? '',
      'category'  => $_POST['inject_category'] ?? 0,
      'position'  => $_POST['inject_position'] ?? 'bottom',
      'paragraph' => $_POST['inject_paragraph'] ?? 2,
      'content'   => $_POST['inject_content'] ?? '',
      'active'    => isset($_POST['inject_active']),
    ];
    My_Inject_Content::save_rule($data);

    $cat      = get_category((int) $data['category']);
    $cat_name = $cat ? $cat->name : 'Unknown';
    $pos_map  = ['top' => 'Dau bai', 'bottom' => 'Cuoi bai', 'after_p' => 'Sau doan ' . ($data['paragraph'])];
    $pos      = $pos_map[$data['position']] ?? $data['position'];
    $action   = $is_edit ? 'Chinh sua' : 'Tao moi';
    My_Telegram::send(
      "<b>[Inject Content] {$action} rule</b>\n" .
      "Ten: <b>" . esc_html($data['name']) . "</b>\n" .
      "Danh muc: {$cat_name}\n" .
      "Vi tri: {$pos}\n" .
      "Trang thai: " . ($data['active'] ? '● Bat' : '○ Tat')
    );

    wp_redirect(admin_url('admin.php?page=xavia-inject-content&saved=1'));
    exit;
  }

  if (isset($_POST['xavia_inject_toggle'])) {
    check_admin_referer('xavia_inject_toggle');
    $id   = sanitize_text_field($_POST['xavia_inject_toggle']);
    $rule = My_Inject_Content::get_rule($id);
    My_Inject_Content::toggle_rule($id);
    if ($rule) {
      $new_state = $rule['active'] ? '○ Tat' : '● Bat';
      My_Telegram::send(
        "<b>[Inject Content] Doi trang thai rule</b>\n" .
        "Ten: <b>" . esc_html($rule['name']) . "</b>\n" .
        "Trang thai moi: {$new_state}"
      );
    }
    wp_redirect(admin_url('admin.php?page=xavia-inject-content'));
    exit;
  }

  if (isset($_POST['xavia_inject_delete'])) {
    check_admin_referer('xavia_inject_delete');
    $id   = sanitize_text_field($_POST['xavia_inject_delete']);
    $rule = My_Inject_Content::get_rule($id);
    My_Inject_Content::delete_rule($id);
    if ($rule) {
      My_Telegram::send(
        "<b>[Inject Content] Xoa rule</b>\n" .
        "Ten: <b>" . esc_html($rule['name']) . "</b>"
      );
    }
    wp_redirect(admin_url('admin.php?page=xavia-inject-content'));
    exit;
  }
});

// Xu ly Auto Block settings
add_action('admin_init', function () {
  if (!isset($_POST['xavia_autoblock_save'])) return;
  check_admin_referer('xavia_autoblock_save');
  $input = $_POST['settings'] ?? [];
  $traffic_keys = ['brute_force', 'bot_spike', '404_spike', '5xx_spike'];
  $vps_keys     = ['cpu_alert', 'ram_alert', 'disk_alert'];
  $saved = [];
  foreach ($traffic_keys as $key) {
    $saved[$key] = [
      'block'     => !empty($input[$key]['block']),
      'notify'    => !empty($input[$key]['notify']),
      'threshold' => max(1, (int)($input[$key]['threshold'] ?? 5)),
      'window'    => max(1, (int)($input[$key]['window']    ?? 5)),
      'cooldown'  => max(1, (int)($input[$key]['cooldown']  ?? 15)),
    ];
  }
  foreach ($vps_keys as $key) {
    $saved[$key] = [
      'notify'    => !empty($input[$key]['notify']),
      'threshold' => max(1, (int)($input[$key]['threshold'] ?? 80)),
      'cooldown'  => max(1, (int)($input[$key]['cooldown']  ?? 60)),
    ];
  }
  update_option(My_Traffic_Logger::SETTINGS_KEY, $saved);
  wp_redirect(admin_url('admin.php?page=xavia-traffic-health&tab=autoblock&settings_saved=1'));
  exit;
});

// Xu ly IP Blocker
add_action('admin_init', function () {
  if (isset($_POST['xavia_block_ip_submit'])) {
    check_admin_referer('xavia_block_ip');
    $cidr   = sanitize_text_field($_POST['xavia_block_cidr'] ?? '');
    $reason = sanitize_text_field($_POST['xavia_block_reason'] ?? '');
    if ($cidr) {
      My_IP_Blocker::add($cidr, $reason, 'manual');
      My_Telegram::send(
        "<b>[IP Blocker] Block IP</b>\n" .
        "IP/CIDR: <code>{$cidr}</code>\n" .
        "Ly do: " . ($reason ?: 'Thu cong') . "\n" .
        "Thoi gian: " . current_time('H:i:s')
      );
    }
    wp_redirect(admin_url('admin.php?page=xavia-ip-blocker&blocked=' . urlencode($cidr)));
    exit;
  }

  if (isset($_POST['xavia_unblock_ip'])) {
    check_admin_referer('xavia_unblock_ip');
    $cidr = sanitize_text_field($_POST['xavia_unblock_ip']);
    My_IP_Blocker::remove($cidr);
    wp_redirect(admin_url('admin.php?page=xavia-ip-blocker&unblocked=' . urlencode($cidr)));
    exit;
  }
});


// Xu ly GSC schedule settings
add_action('admin_init', function () {
  if (!isset($_POST['xavia_gsc_schedule_save'])) return;
  check_admin_referer('xavia_gsc_schedule_save');

  $enabled = !empty($_POST['gsc_report_enabled']);
  $time    = sanitize_text_field($_POST['gsc_report_time'] ?? '08:00');
  $days    = (int) ($_POST['gsc_report_days'] ?? 7);
  if (!preg_match('/^\d{2}:\d{2}$/', $time)) $time = '08:00';
  if (!in_array($days, [7, 28, 90])) $days = 7;

  update_option('xavia_gsc_report_enabled', $enabled);
  update_option('xavia_gsc_report_time',    $time);
  update_option('xavia_gsc_report_days',    $days);

  $hook = 'xavia_gsc_daily_report';
  $ts   = wp_next_scheduled($hook);
  if ($ts) wp_unschedule_event($ts, $hook);

  if ($enabled) {
    $tz   = wp_timezone();
    $next = new DateTime('today ' . $time, $tz);
    if ($next->getTimestamp() <= time()) $next->modify('+1 day');
    wp_schedule_event($next->getTimestamp(), 'daily', $hook);
  }

  wp_redirect(admin_url('admin.php?page=xavia-gsc&tab=report&schedule_saved=1'));
  exit;
});

// Xu ly scan ngay
add_action('admin_init', function () {
  if (!isset($_POST['xavia_gsc_scan_now'])) return;
  check_admin_referer('xavia_gsc_scan_now');
  $days = (int) ($_POST['gsc_scan_days'] ?? get_option('xavia_gsc_report_days', 7));
  if (!in_array($days, [7, 28, 90])) $days = 7;
  My_GSC::send_report($days);
  wp_redirect(admin_url('admin.php?page=xavia-gsc&tab=sc_overview&scan_sent=1'));
  exit;
});

// Cron handler
add_action('xavia_gsc_daily_report', function () {
  $days = (int) get_option('xavia_gsc_report_days', 7);
  My_GSC::send_report($days);
});

// Xu ly GA4 check tag
add_action('admin_init', function () {
  if (!isset($_POST['xavia_ga4_check_tag'])) return;
  check_admin_referer('xavia_ga4_check_tag');
  $mid  = My_GA4::get_measurement_id();
  $res  = wp_remote_get(home_url(), ['timeout' => 10, 'sslverify' => false]);
  $body = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
  $found = $mid && $body && strpos($body, $mid) !== false;
  set_transient('xavia_ga4_tag_check', $found ? 1 : 0, 5 * MINUTE_IN_SECONDS);
  wp_redirect(admin_url('admin.php?page=xavia-ga4&tab=settings'));
  exit;
});

// Xu ly GA4 clear cache
add_action('admin_init', function () {
  if (!isset($_POST['xavia_ga4_clear_cache'])) return;
  check_admin_referer('xavia_ga4_clear_cache');
  global $wpdb;
  $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_xavia_ga4_%' OR option_name LIKE '_transient_timeout_xavia_ga4_%'");
  wp_redirect(admin_url('admin.php?page=xavia-ga4&tab=settings&cache_cleared=1'));
  exit;
});

// Xu ly GA4 settings
add_action('admin_init', function () {
  if (!isset($_POST['xavia_ga4_save'])) return;
  check_admin_referer('xavia_ga4_save');
  My_GA4::save_property_id($_POST['ga4_property_id'] ?? '');
  My_GA4::save_measurement_id($_POST['ga4_measurement_id'] ?? '');
  wp_redirect(admin_url('admin.php?page=xavia-ga4&tab=settings&ga4_saved=1'));
  exit;
});

// Inject GA4 tracking tag vao <head>
add_action('wp_head', ['My_GA4', 'inject_tag']);

// Xu ly GA4 schedule settings
add_action('admin_init', function () {
  if (!isset($_POST['xavia_ga4_schedule_save'])) return;
  check_admin_referer('xavia_ga4_schedule_save');

  $enabled = !empty($_POST['ga4_report_enabled']);
  $time    = sanitize_text_field($_POST['ga4_report_time'] ?? '08:00');
  $days    = (int) ($_POST['ga4_report_days'] ?? 7);
  if (!preg_match('/^\d{2}:\d{2}$/', $time)) $time = '08:00';
  if (!in_array($days, [7, 28, 90])) $days = 7;

  update_option('xavia_ga4_report_enabled', $enabled);
  update_option('xavia_ga4_report_time',    $time);
  update_option('xavia_ga4_report_days',    $days);

  $hook = 'xavia_ga4_daily_report';
  $ts   = wp_next_scheduled($hook);
  if ($ts) wp_unschedule_event($ts, $hook);

  if ($enabled) {
    $tz   = wp_timezone();
    $next = new DateTime('today ' . $time, $tz);
    if ($next->getTimestamp() <= time()) $next->modify('+1 day');
    wp_schedule_event($next->getTimestamp(), 'daily', $hook);
  }

  wp_redirect(admin_url('admin.php?page=xavia-ga4&tab=report&schedule_saved=1'));
  exit;
});

// Xu ly GA4 scan ngay
add_action('admin_init', function () {
  if (!isset($_POST['xavia_ga4_scan_now'])) return;
  check_admin_referer('xavia_ga4_scan_now');
  $days = (int) ($_POST['ga4_scan_days'] ?? get_option('xavia_ga4_report_days', 7));
  if (!in_array($days, [7, 28, 90])) $days = 7;
  My_GA4::send_report($days);
  wp_redirect(admin_url('admin.php?page=xavia-ga4&tab=ga4_overview&scan_sent=1'));
  exit;
});

// Cron handler GA4
add_action('xavia_ga4_daily_report', function () {
  $days = (int) get_option('xavia_ga4_report_days', 7);
  My_GA4::send_report($days);
});

// Xử lý xóa / bật-tắt chat_id
add_action('admin_init', function () {
  if (isset($_POST['xavia_remove_chatid'])) {
    check_admin_referer('xavia_remove_chatid');
    My_Telegram::remove_chat_id($_POST['xavia_remove_chatid']);
    wp_redirect(admin_url('admin.php?page=xavia-dashboard'));
    exit;
  }

  if (isset($_POST['xavia_toggle_chatid'])) {
    check_admin_referer('xavia_toggle_chatid');
    My_Telegram::toggle_active($_POST['xavia_toggle_chatid']);
    wp_redirect(admin_url('admin.php?page=xavia-dashboard'));
    exit;
  }
});

register_activation_hook(__FILE__, function () {
  My_Telegram::set_webhook();
  My_Traffic_Logger::create_table();
});

// Queue gom nhom thong bao post: tranh gui nhieu tin rieng le khi bulk action
class Xavia_Notify_Queue {
  private static array $q = [];

  public static function push(string $type, array $item): void {
    self::$q[$type][] = $item;
  }

  public static function suppress(array $types): void {
    foreach ($types as $t) unset(self::$q[$t]);
  }

  public static function flush(): void {
    if (empty(self::$q)) return;
    $labels = [
      'new'       => 'Bai viet moi',
      'edit'      => 'Chinh sua bai viet',
      'future'    => 'Len lich dang',
      'trash'     => 'Chuyen vao thung rac',
      'restore'   => 'Khoi phuc tu thung rac',
      'delete'    => 'Xoa vinh vien',
      'bulk_edit' => 'Chinh sua hang loat',
    ];
    foreach (self::$q as $type => $items) {
      $label    = $labels[$type] ?? $type;
      $count    = count($items);
      $type_str = $count === 1 ? 'single' : 'bulk ' . $count . ' bai';
      $lines    = [];
      foreach ($items as $item) {
        if (!empty($item['url'])) {
          $lines[] = "- {$item['title']}\n  {$item['url']}";
        } elseif (!empty($item['schedule'])) {
          $lines[] = "- {$item['title']} ({$item['schedule']})";
        } else {
          $lines[] = "- {$item['title']}";
        }
      }
      My_Telegram::send("<b>{$label} ({$type_str})</b>\n" . implode("\n", $lines));
    }
  }
}
add_action('shutdown', ['Xavia_Notify_Queue', 'flush'], 5);

add_action('transition_post_status', function ($new_status, $old_status, $post) {
  if ($post->post_type !== 'post') return;
  $is_bulk = is_admin() && !empty($_REQUEST['bulk_edit']);

  if ($new_status === 'publish' && $old_status !== 'publish') {
    set_transient('xavia_new_post_' . $post->ID, 1, 5); // danh dau vua publish, suppress edit lien tiep
    Xavia_Notify_Queue::push('new', ['title' => $post->post_title, 'url' => get_permalink($post->ID)]);
  } elseif ($new_status === 'publish' && $old_status === 'publish') {
    // Bo qua neu post vua duoc publish (Gutenberg gui 2 request lien tiep khi tao bai moi)
    if (get_transient('xavia_new_post_' . $post->ID)) return;
    $type = $is_bulk ? 'bulk_edit' : 'edit';
    Xavia_Notify_Queue::push($type, ['title' => $post->post_title, 'url' => get_permalink($post->ID)]);
  } elseif ($new_status === 'future' && $old_status !== 'future') {
    $schedule = get_post_time('d/m/Y H:i', false, $post->ID);
    Xavia_Notify_Queue::push('future', ['title' => $post->post_title, 'schedule' => $schedule]);
  } elseif ($new_status === 'trash' && $old_status !== 'trash') {
    Xavia_Notify_Queue::push('trash', ['title' => $post->post_title]);
  } elseif ($old_status === 'trash' && $new_status !== 'trash') {
    Xavia_Notify_Queue::push('restore', ['title' => $post->post_title, 'url' => get_permalink($post->ID)]);
  }
}, 10, 3);

add_action('before_delete_post', function ($post_id) {
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'post' || $post->post_status !== 'trash') return;
  Xavia_Notify_Queue::push('delete', ['title' => $post->post_title]);
});

// Bulk edit: chi xu ly post khong phai publish (published da duoc bat boi transition_post_status)
add_action('save_post', function ($post_id, $post, $update) {
  if (!$update || $post->post_type !== 'post') return;
  if (!is_admin() || empty($_REQUEST['bulk_edit'])) return;
  if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
  if ($post->post_status === 'publish') return;
  Xavia_Notify_Queue::push('bulk_edit', ['title' => $post->post_title]);
}, 10, 3);

// Canh bao upload anh lon
add_action('admin_footer', function () {
  if (!did_action('wp_enqueue_media')) return;
  $max_mb  = 8;
  $max_px  = 2560;
  ?>
  <script>
  jQuery(function ($) {
    // Chen ghi chu vao uploader
    function addUploadHint() {
      var $area = $('.upload-ui, .drag-drop-area, #plupload-browse-button').first().closest('.upload-ui, .media-frame-content');
      if ($area.length && !$area.find('.xavia-upload-hint').length) {
        $area.prepend('<p class="xavia-upload-hint" style="margin:8px 0 4px;font-size:12px;color:#888;">Khuyen nghi: anh toi da <?php echo $max_px; ?>px, duoi <?php echo $max_mb; ?>MB de tranh loi xu ly.</p>');
      }
    }

    // Chay moi khi media frame mo
    $(document).on('DOMNodeInserted', '.media-modal', function () {
      setTimeout(addUploadHint, 300);
    });
    addUploadHint();

    // Canh bao khi chon file lon
    $(document).on('change', 'input[type="file"]', function () {
      var files = this.files;
      if (!files) return;
      for (var i = 0; i < files.length; i++) {
        if (files[i].size > <?php echo $max_mb; ?> * 1024 * 1024) {
          alert('Canh bao: "' + files[i].name + '" lon hon <?php echo $max_mb; ?>MB.\nNeu anh co kich thuoc > <?php echo $max_px; ?>px, may chu co the khong xu ly duoc.\nVui long resize anh truoc khi tai len.');
          break;
        }
      }
    });

    // Override thong bao loi cua WordPress
    if (typeof wp !== 'undefined' && wp.Uploader) {
      var _error = wp.Uploader.prototype.error;
      wp.Uploader.prototype.error = function (file, error, up) {
        if (error && (error.code === -200 || (error.message && error.message.toLowerCase().indexOf('http') !== -1))) {
          error.message = 'Anh cua ban qua lon, may chu khong xu ly duoc. Vui long resize xuong duoi <?php echo $max_px; ?>px truoc khi tai len.';
        }
        return _error.apply(this, arguments);
      };
    }

    // Bat loi tu media uploader (hien thi trong modal)
    $(document).on('DOMNodeInserted', '.upload-error, .notice-error', function (e) {
      var $el = $(e.target);
      var txt = $el.text();
      if (txt.indexOf('may chu') !== -1 || txt.indexOf('server') !== -1 || txt.indexOf('khong the xu ly') !== -1) {
        $el.html($el.html().replace(
          /M.y ch.* pixel\./,
          'Anh cua ban qua lon, may chu khong the xu ly. Vui long resize xuong duoi <?php echo $max_px; ?>px roi tai len lai.'
        ));
      }
    });
  });
  </script>
  <?php
});

// Helper dung chung: lay ten plugin tu file path
function xavia_plugin_name(string $file): string {
  $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $file);
  return $data['Name'] ?: $file;
}

// thong bao plugin update / install (gop 2 truong hop thanh 1 logic)
add_action('upgrader_process_complete', function ($upgrader, $options) {
  if ($options['type'] !== 'plugin') return;
  $map = [
    'update'  => ['label' => 'Plugin da duoc cap nhat!',    'fmt' => fn($n, $v) => "- $n → v$v"],
    'install' => ['label' => 'Plugin moi da duoc cai dat!', 'fmt' => fn($n, $v) => "- $n v$v"],
  ];
  $action = $options['action'] ?? '';
  if (!isset($map[$action]) || empty($options['plugins'])) return;
  $cfg   = $map[$action];
  $lines = array_map(function ($f) use ($cfg) {
    $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $f);
    return ($cfg['fmt'])($data['Name'] ?: $f, $data['Version'] ?: '?');
  }, $options['plugins']);
  My_Telegram::send("<b>{$cfg['label']}</b>\n" . implode("\n", $lines));
}, 10, 2);

// thong bao kich hoat / deactivate / xoa plugin (dung xavia_plugin_name chung)
add_action('activated_plugin',   fn($f) => My_Telegram::send("<b>Plugin da duoc kich hoat!</b>\n- " . xavia_plugin_name($f)));
add_action('deactivated_plugin', fn($f) => My_Telegram::send("<b>Plugin da bi tat!</b>\n- " . xavia_plugin_name($f)));
add_filter('pre_uninstall_plugin', function ($f) {
  My_Telegram::send("<b>Plugin sap bi xoa!</b>\n- " . xavia_plugin_name($f));
  return $f;
});
add_action('deleted_plugin', fn($f, $ok) => $ok && My_Telegram::send("<b>Plugin da bi xoa!</b>\n- $f"), 10, 2);

// thong bao dang nhap (dung My_Traffic_Logger::get_ip() thay vi viet lai)
add_action('wp_login', function ($user_login, $user) {
  $role = implode(', ', $user->roles);
  My_Telegram::send("<b>Co nguoi dang nhap!</b>\nUser: <b>$user_login</b>\nRole: $role\nIP: <code>" . My_Traffic_Logger::get_ip() . "</code>");
}, 10, 2);