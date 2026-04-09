<?php
if (!defined('ABSPATH')) exit;

class My_Telegram {

  public static function get_chat_ids() {
    return get_option('my_plugin_telegram_chat_ids', []);
  }

  public static function save_chat_id($chat_id) {
    $chats = self::get_chat_ids();
    foreach ($chats as $chat) {
      if ((string)$chat['id'] === (string)$chat_id) return;
    }
    $chats[] = ['id' => $chat_id, 'active' => true];
    update_option('my_plugin_telegram_chat_ids', $chats);
  }

  public static function toggle_active($chat_id) {
    $chats = self::get_chat_ids();
    foreach ($chats as &$chat) {
      if ((string)$chat['id'] === (string)$chat_id) {
        $chat['active'] = !$chat['active'];
        break;
      }
    }
    update_option('my_plugin_telegram_chat_ids', $chats);
  }

  public static function remove_chat_id($chat_id) {
    $chats = self::get_chat_ids();
    $chats = array_values(array_filter($chats, fn($c) => (string)$c['id'] !== (string)$chat_id));
    update_option('my_plugin_telegram_chat_ids', $chats);
  }

  public static function send_to($chat_id, $message) {
    wp_remote_post('https://api.telegram.org/bot' . MY_PLUGIN_TELEGRAM_TOKEN . '/sendMessage', [
      'body' => [
        'chat_id'    => $chat_id,
        'text'       => $message,
        'parse_mode' => 'HTML',
      ],
      'timeout'  => 5,
      'blocking' => false,
    ]);
  }
  public static function send_bulk($label, $items, $formatter = null) {
    $count     = count($items);
    $type      = $count === 1 ? 'single' : 'bulk ' . $count . ' bai';
    $formatter = $formatter ?? fn($i) => "- $i";
    $list      = implode("\n", array_map($formatter, $items));
    self::send("<b>$label ($type)</b>\n$list");
  }

  public static function send($message, $chat_id = null) {
    if ($chat_id) {
      self::send_to($chat_id, $message);
      return;
    }

    foreach (self::get_chat_ids() as $chat) {
      if ($chat['active']) {
        self::send_to($chat['id'], $message);
      }
    }
  }

  public static function set_webhook() {
    $webhook_url = rest_url('my-plugin/v1/telegram-webhook');
    $res = wp_remote_post(
      'https://api.telegram.org/bot' . MY_PLUGIN_TELEGRAM_TOKEN . '/setWebhook',
      [
        'body' => [
          'url'          => $webhook_url,
          'secret_token' => MY_PLUGIN_WEBHOOK_SECRET,
        ],
        'timeout' => 10,
      ]
    );
    return json_decode(wp_remote_retrieve_body($res), true);
  }
}
