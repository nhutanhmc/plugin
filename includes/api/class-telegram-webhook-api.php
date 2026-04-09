<?php
if (!defined('ABSPATH')) exit;

class My_Telegram_Webhook_API {

  public static function register() {
    register_rest_route('my-plugin/v1', '/telegram-webhook', [
      'methods'             => 'POST',
      'callback'            => [self::class, 'handle'],
      'permission_callback' => [self::class, 'verify_secret'],
    ]);
  }
  public static function verify_secret(WP_REST_Request $request) {
    $secret = $request->get_header('X-Telegram-Bot-Api-Secret-Token');
    return $secret === MY_PLUGIN_WEBHOOK_SECRET;
  }

  public static function handle(WP_REST_Request $request) {
    $body = $request->get_json_params();
    $update_id = $body['update_id'] ?? null;
    if ($update_id) {
      $cache_key = 'tg_upd_' . $update_id;
      if (get_transient($cache_key)) return new WP_REST_Response(['ok' => true], 200);
      set_transient($cache_key, 1, 6 * HOUR_IN_SECONDS);
    }
    if (empty($body['message'])) {
      return new WP_REST_Response(['ok' => true], 200);
    }

    $chat_id = $body['message']['chat']['id'] ?? null;
    $text    = $body['message']['text'] ?? '';

    if ($chat_id) {
      My_Telegram::save_chat_id($chat_id);
      $command = strtok(trim($text), '@');
      self::handle_command($chat_id, $command);
    }

    return new WP_REST_Response(['ok' => true], 200);
  }

  private static function handle_command($chat_id, $text) {
    switch ($text) {
      case '/start':
      case '/help':
        My_Telegram::send("<b>Bot đã kết nối!</b>\nChat ID: <code>$chat_id</code>", $chat_id);
        break;

      case '/status':
        My_Telegram::send("Plugin dang hoat dong binh thuong.", $chat_id);
        break;

      case '/vpshealth':
        try {
          My_Telegram::send(self::get_vps_summary(), $chat_id);
        } catch (Throwable $e) {
          My_Telegram::send('Loi /vpshealth: ' . $e->getMessage(), $chat_id);
        }
        break;

      case '/traffichealth':
        try {
          My_Telegram::send(self::get_traffic_summary(), $chat_id);
        } catch (Throwable $e) {
          My_Telegram::send('Loi /traffichealth: ' . $e->getMessage(), $chat_id);
        }
        break;

      default:
        break;
    }
  }

  private static function metric_line(string $label, array $metric, callable $format): string {
    if ($metric['error']) return "{$label}: <i>loi</i>\n";
    return "{$label}: <b>" . $format($metric['value']) . "</b>\n";
  }

  private static function get_vps_summary(): string {
    $data = My_VPS_API::get_stats()->get_data();
    $m    = [self::class, 'metric_line'];

    return "<b>VPS Health</b>\n"
      . call_user_func($m, 'CPU',     $data['cpu'],        fn($v) => $v['pct'] . '% | load ' . $v['load1'] . ' | steal ' . $v['steal'] . '%')
      . call_user_func($m, 'RAM',     $data['ram'],        fn($v) => $v['used'] . '/' . $v['total'] . ' MB (' . $v['pct'] . '%)')
      . call_user_func($m, 'Disk',    $data['disk'],       fn($v) => $v['used'] . '/' . $v['total'] . ' GB (' . $v['pct'] . '%)')
      . call_user_func($m, 'Uptime',  $data['uptime'],     fn($v) => $v)
      . call_user_func($m, 'DB Size', $data['wp_db_size'], fn($v) => $v . ' MB')
      . call_user_func($m, 'Plugins', $data['wp_plugins'], fn($v) => $v . ' active')
      . call_user_func($m, 'Cron',    $data['wp_cron'],    fn($v) => $v . ' jobs')
      . 'Luc: ' . $data['time'];
  }

  private static function get_traffic_summary(): string {
    global $wpdb;
    $table = $wpdb->prefix . My_Traffic_Logger::TABLE;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
      return "Bang traffic chua ton tai.";
    }

    $ago_1h = date('Y-m-d H:i:s', current_time('timestamp') - HOUR_IN_SECONDS);
    $ago_5m = date('Y-m-d H:i:s', current_time('timestamp') - 5 * MINUTE_IN_SECONDS);

    $total_1h  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $ago_1h));
    $total_5m  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $ago_5m));
    $logins    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE is_login=1 AND created_at >= %s", $ago_1h));
    $errors    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status >= 400 AND created_at >= %s", $ago_1h));
    $slow      = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE response_ms >= 2000 AND created_at >= %s", $ago_1h));

    $by_type_rows = $wpdb->get_results($wpdb->prepare(
      "SELECT type, COUNT(*) as c FROM {$table} WHERE created_at >= %s GROUP BY type ORDER BY c DESC", $ago_1h
    ));
    $type_str = implode(', ', array_map(fn($r) => $r->type . ': ' . $r->c, $by_type_rows));

    $top_ips = $wpdb->get_results($wpdb->prepare(
      "SELECT ip, COUNT(*) as c FROM {$table} WHERE created_at >= %s GROUP BY ip ORDER BY c DESC LIMIT 3", $ago_1h
    ));
    $ip_str = implode("\n", array_map(fn($r) => "  <code>{$r->ip}</code> — {$r->c} req", $top_ips));

    return "<b>Traffic Health (1h)</b>\n"
      . "Tong: <b>{$total_1h}</b> req | 5 phut: <b>{$total_5m}</b>\n"
      . "Phan loai: {$type_str}\n"
      . "Login attempts: <b>{$logins}</b>\n"
      . "Loi 4xx/5xx: <b>{$errors}</b>\n"
      . "Slow &gt;2s: <b>{$slow}</b>\n"
      . "Top IP:\n{$ip_str}\n"
      . "Luc: " . current_time('H:i:s');
  }
}
