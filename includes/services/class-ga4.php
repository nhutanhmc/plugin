<?php
if (!defined('ABSPATH')) exit;

class My_GA4 {

  const OPT_PROPERTY_ID    = 'xavia_ga4_property_id';
  const OPT_MEASUREMENT_ID = 'xavia_ga4_measurement_id';

  public static function get_property_id(): string {
    return get_option(self::OPT_PROPERTY_ID, '');
  }

  public static function get_measurement_id(): string {
    return get_option(self::OPT_MEASUREMENT_ID, '');
  }

  public static function save_property_id(string $id): void {
    update_option(self::OPT_PROPERTY_ID, sanitize_text_field($id));
  }

  public static function save_measurement_id(string $id): void {
    update_option(self::OPT_MEASUREMENT_ID, sanitize_text_field($id));
  }

  public static function inject_tag(): void {
    $mid = self::get_measurement_id();
    if (!$mid) return;
    $mid = esc_js($mid);
    echo <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$mid}"></script>
<script>
window.dataLayer=window.dataLayer||[];
function gtag(){dataLayer.push(arguments);}
gtag('js',new Date());
gtag('config','{$mid}');
</script>
HTML;
  }

  public static function is_configured(): bool {
    return self::get_property_id() && My_GSC::is_configured();
  }

  private static function get_access_token(): string {
    $cached = get_transient(My_GSC::TRANSIENT_TOKEN);
    if ($cached) return $cached;

    $c   = My_GSC::get_config();
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
    if ($token) set_transient(My_GSC::TRANSIENT_TOKEN, $token, 3500);
    return $token;
  }

  private static function query(string $endpoint, array $body, int $cache_sec = 3600): array {
    $cache_key = 'xavia_ga4_v2_' . md5($endpoint . json_encode($body));
    $cached    = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $token       = self::get_access_token();
    $property_id = self::get_property_id();
    if (!$token || !$property_id) return ['error' => 'Chưa cấu hình'];

    $url = "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:{$endpoint}";
    $res = wp_remote_post($url, [
      'headers' => [
        'Authorization' => "Bearer $token",
        'Content-Type'  => 'application/json',
      ],
      'body'    => json_encode($body),
      'timeout' => 20,
    ]);

    if (is_wp_error($res)) return ['error' => 'Kết nối thất bại: ' . $res->get_error_message()];
    $http_code    = (int) wp_remote_retrieve_response_code($res);
    $response_raw = wp_remote_retrieve_body($res);
    $data         = json_decode($response_raw, true);
    if (!is_array($data)) {
      error_log('[GA4] HTTP ' . $http_code . ' | endpoint=' . $endpoint . ' | body=' . substr($response_raw, 0, 300));
      return ['error' => 'GA4 HTTP ' . $http_code . ' – Phản hồi không hợp lệ (xem error log)'];
    }
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Lỗi API'];
    set_transient($cache_key, $data, $cache_sec);
    return $data;
  }

  public static function get_summary(string $start, string $end): array {
    return self::query('runReport', [
      'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
      'metrics'    => [
        ['name' => 'sessions'],
        ['name' => 'totalUsers'],
        ['name' => 'screenPageViews'],
        ['name' => 'bounceRate'],
        ['name' => 'averageSessionDuration'],
      ],
    ]);
  }

  public static function get_chart(string $start, string $end): array {
    return self::query('runReport', [
      'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
      'dimensions' => [['name' => 'date']],
      'metrics'    => [['name' => 'sessions'], ['name' => 'totalUsers']],
      'orderBys'   => [['dimension' => ['dimensionName' => 'date'], 'desc' => false]],
      'limit'      => 90,
    ], 1800);
  }

  public static function get_top_pages(string $start, string $end, int $limit = 50): array {
    return self::query('runReport', [
      'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
      'dimensions' => [['name' => 'pagePath']],
      'metrics'    => [['name' => 'screenPageViews'], ['name' => 'sessions'], ['name' => 'averageSessionDuration']],
      'orderBys'   => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
      'limit'      => $limit,
    ]);
  }

  public static function get_channels(string $start, string $end): array {
    return self::query('runReport', [
      'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
      'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
      'metrics'    => [['name' => 'sessions'], ['name' => 'totalUsers']],
      'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
      'limit'      => 10,
    ]);
  }

  public static function get_devices(string $start, string $end): array {
    return self::query('runReport', [
      'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
      'dimensions' => [['name' => 'deviceCategory']],
      'metrics'    => [['name' => 'sessions']],
      'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
    ]);
  }

  // Helper: lay gia tri metric tu row
  public static function metric(array $row, int $index): string {
    return $row['metricValues'][$index]['value'] ?? '0';
  }

  public static function dimension(array $row, int $index): string {
    return $row['dimensionValues'][$index]['value'] ?? '';
  }

  public static function send_report(int $days = 7): void {
    $end      = date('Y-m-d');
    $start    = date('Y-m-d', strtotime("-{$days} days"));
    $data     = self::get_summary($start, $end);
    $time_str = current_time('d/m/Y H:i');

    if (isset($data['error'])) {
      My_Telegram::send("<b>[GA4 Report] Loi</b>\nThoi gian: {$time_str}\n" . esc_html($data['error']));
      return;
    }

    $r       = $data['rows'][0] ?? [];
    $dur_sec = (int) self::metric($r, 4);
    My_Telegram::send(
      "<b>[GA4 Report {$days} ngay]</b>\n" .
      "Tu: {$start} den {$end}\n" .
      "Lay luc: {$time_str}\n\n" .
      "Sessions: <b>" . number_format(self::metric($r, 0)) . "</b>\n" .
      "Users: <b>" . number_format(self::metric($r, 1)) . "</b>\n" .
      "Pageviews: <b>" . number_format(self::metric($r, 2)) . "</b>\n" .
      "Bounce Rate: <b>" . round((float) self::metric($r, 3) * 100, 1) . "%</b>\n" .
      "Thoi luong TB: <b>" . sprintf('%d:%02d', intdiv($dur_sec, 60), $dur_sec % 60) . "</b>"
    );
  }
}
