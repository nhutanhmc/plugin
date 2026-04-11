<?php
if (!defined('ABSPATH')) exit;

class My_GSC {

  const TRANSIENT_TOKEN = 'xavia_gsc_access_token';

  public static function get_config(): array {
    return [
      'client_id'     => MY_PLUGIN_GSC_CLIENT_ID,
      'client_secret' => MY_PLUGIN_GSC_CLIENT_SECRET,
      'refresh_token' => MY_PLUGIN_GSC_REFRESH_TOKEN,
      'site_url'      => rtrim(home_url(), '/'),
    ];
  }

  public static function is_configured(): bool {
    return MY_PLUGIN_GSC_CLIENT_ID && MY_PLUGIN_GSC_CLIENT_SECRET && MY_PLUGIN_GSC_REFRESH_TOKEN;
  }

  private static function get_access_token(): string {
    $cached = get_transient(self::TRANSIENT_TOKEN);
    if ($cached) return $cached;

    $c   = self::get_config();
    $res = wp_remote_post('https://oauth2.googleapis.com/token', [
      'body' => [
        'client_id'     => $c['client_id'],
        'client_secret' => $c['client_secret'],
        'refresh_token' => $c['refresh_token'],
        'grant_type'    => 'refresh_token',
      ],
      'timeout' => 15,
    ]);

    if (is_wp_error($res)) return '';
    $data  = json_decode(wp_remote_retrieve_body($res), true);
    $token = $data['access_token'] ?? '';
    if ($token) set_transient(self::TRANSIENT_TOKEN, $token, 3500);
    return $token;
  }

  private static function query(array $body, int $cache_sec = 3600): array {
    $cache_key = 'xavia_gsc_v2_' . md5(json_encode($body));
    $cached    = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $token = self::get_access_token();
    $site  = urlencode(self::get_config()['site_url']);
    if (!$token || !$site) return ['error' => 'Chưa cấu hình hoặc lấy token thất bại'];

    $res = wp_remote_post(
      "https://searchconsole.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query",
      [
        'headers' => [
          'Authorization' => "Bearer $token",
          'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode($body),
        'timeout' => 20,
      ]
    );

    if (is_wp_error($res)) return ['error' => 'Kết nối thất bại: ' . $res->get_error_message()];
    $http_code = (int) wp_remote_retrieve_response_code($res);
    $body_raw  = wp_remote_retrieve_body($res);
    $data      = json_decode($body_raw, true);
    if (!is_array($data)) {
      error_log('[GSC] HTTP ' . $http_code . ' | body=' . substr($body_raw, 0, 300));
      return ['error' => 'GSC HTTP ' . $http_code . ' – Phản hồi không hợp lệ (xem error log)'];
    }
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Lỗi API'];
    set_transient($cache_key, $data, $cache_sec);
    return $data;
  }

  public static function get_summary(string $start, string $end): array {
    return self::query(['startDate' => $start, 'endDate' => $end, 'rowLimit' => 1]);
  }

  public static function get_queries(string $start, string $end, int $limit = 50): array {
    return self::query([
      'startDate'  => $start, 'endDate' => $end,
      'dimensions' => ['query'], 'rowLimit' => $limit,
      'orderBy'    => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
    ]);
  }

  public static function get_pages(string $start, string $end, int $limit = 50): array {
    return self::query([
      'startDate'  => $start, 'endDate' => $end,
      'dimensions' => ['page'], 'rowLimit' => $limit,
      'orderBy'    => [['fieldName' => 'clicks', 'sortOrder' => 'DESCENDING']],
    ]);
  }

  public static function get_chart(string $start, string $end): array {
    return self::query([
      'startDate'  => $start, 'endDate' => $end,
      'dimensions' => ['date'], 'rowLimit' => 90,
      'orderBy'    => [['fieldName' => 'date', 'sortOrder' => 'ASCENDING']],
    ], 1800);
  }

  public static function send_report(int $days = 7): void {
    $end      = date('Y-m-d', strtotime('-1 day'));
    $start    = date('Y-m-d', strtotime("-{$days} days"));
    $data     = self::get_summary($start, $end);
    $time_str = current_time('d/m/Y H:i');

    if (isset($data['error'])) {
      My_Telegram::send("<b>[GSC Report] Loi</b>\nThoi gian: {$time_str}\n" . esc_html($data['error']));
      return;
    }

    $row = $data['rows'][0] ?? [];
    My_Telegram::send(
      "<b>[GSC Report {$days} ngay]</b>\n" .
      "Tu: {$start} den {$end}\n" .
      "Lay luc: {$time_str}\n\n" .
      "Clicks: <b>" . number_format($row['clicks'] ?? 0) . "</b>\n" .
      "Impressions: <b>" . number_format($row['impressions'] ?? 0) . "</b>\n" .
      "CTR: <b>" . round(($row['ctr'] ?? 0) * 100, 2) . "%</b>\n" .
      "Vi tri TB: <b>" . (isset($row['position']) ? round($row['position'], 1) : '—') . "</b>"
    );
  }
}
