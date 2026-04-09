<?php
if (!defined('ABSPATH')) exit;

class My_Traffic_API {

    public static function register() {
        register_rest_route('my-plugin/v1', '/traffic-stats', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_stats'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public static function get_stats( WP_REST_Request $request ) {
        global $wpdb;
        $table = $wpdb->prefix . My_Traffic_Logger::TABLE;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return new WP_REST_Response(['error' => 'Bang du lieu chua ton tai. Vui long deactivate roi activate lai plugin.'], 200);
        }

        // Filter request: chi tra ve recent rows theo dieu kien, khong tinh stats
        $filter = sanitize_text_field($request->get_param('filter') ?? '');
        $filter_val = sanitize_text_field($request->get_param('filter_val') ?? '');
        if ($filter) {
            return self::get_filtered($table, $filter, $filter_val);
        }

        $ago_1h = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $ago_5m = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ip, url, ua, method, status, response_ms, type, is_login, created_at
             FROM {$table} WHERE created_at >= %s ORDER BY created_at DESC",
            $ago_1h
        ), ARRAY_A);

        if ($rows === null) {
            return new WP_REST_Response(['error' => 'Loi query: ' . $wpdb->last_error], 200);
        }

        $last_hour = $rows;
        $last_5min = array_values(array_filter($rows, fn($r) => $r['created_at'] >= $ago_5m));

        $by_type   = [];
        $by_status = [];
        foreach ($last_hour as $r) {
            $by_type[$r['type']]          = ($by_type[$r['type']] ?? 0) + 1;
            $by_status[(string)$r['status']] = ($by_status[(string)$r['status']] ?? 0) + 1;
        }
        arsort($by_status);

        $login_attempts = count(array_filter($last_hour, fn($r) => (int)$r['is_login'] === 1));
        $slow_requests  = count(array_filter($last_hour, fn($r) => $r['response_ms'] >= 2000));

        $times    = array_filter(array_column($last_hour, 'response_ms'), fn($t) => $t > 0);
        $avg_time = count($times) ? round(array_sum($times) / count($times) / 1000, 3) : 0;

        // Top IPs
        $ip_map = [];
        foreach ($last_hour as $r) {
            $ip = $r['ip'];
            if (!isset($ip_map[$ip])) $ip_map[$ip] = ['ip' => $ip, 'count' => 0, 'type' => $r['type']];
            $ip_map[$ip]['count']++;
        }
        usort($ip_map, fn($a, $b) => $b['count'] - $a['count']);
        $top_ips = array_map(function($r) {
            $r['country'] = self::get_country($r['ip']);
            return $r;
        }, array_slice(array_values($ip_map), 0, 10));

        // Top URLs
        $url_map = [];
        foreach ($last_hour as $r) {
            $url = strtok($r['url'], '?');
            if (!isset($url_map[$url])) $url_map[$url] = ['url' => $url, 'count' => 0, 'times' => []];
            $url_map[$url]['count']++;
            if ($r['response_ms'] > 0) $url_map[$url]['times'][] = $r['response_ms'];
        }
        foreach ($url_map as &$v) {
            $v['avg_time'] = count($v['times']) ? round(array_sum($v['times']) / count($v['times']) / 1000, 3) : 0;
            unset($v['times']);
        }
        usort($url_map, fn($a, $b) => $b['count'] - $a['count']);
        $top_urls = array_slice(array_values($url_map), 0, 10);

        // Per minute
        $per_minute = [];
        foreach ($last_hour as $r) {
            $min = substr($r['created_at'], 11, 5); // HH:MM
            $per_minute[$min] = ($per_minute[$min] ?? 0) + 1;
        }
        ksort($per_minute);

        // Recent 30
        $recent = array_map([self::class, 'format_row'], array_slice($last_hour, 0, 30));

        $blocked_ips = array_column(My_IP_Blocker::get_list(), 'cidr');

        return new WP_REST_Response([
            'total_1h'       => count($last_hour),
            'total_5m'       => count($last_5min),
            'by_type'        => $by_type,
            'by_status'      => $by_status,
            'login_attempts' => $login_attempts,
            'slow_requests'  => $slow_requests,
            'avg_time'       => $avg_time,
            'top_ips'        => $top_ips,
            'top_urls'       => $top_urls,
            'per_minute'     => $per_minute,
            'recent'         => $recent,
            'blocked_ips'    => $blocked_ips,
            'time'           => current_time('H:i:s'),
        ], 200);
    }

    private static function get_filtered( string $table, string $filter, string $val ) {
        global $wpdb;
        $ago_1h = date('Y-m-d H:i:s', strtotime('-1 hour'));

        switch ($filter) {
            case 'type':
                $allowed = ['human', 'bot', 'api', 'admin', 'login', 'static'];
                if (!in_array($val, $allowed, true)) return new WP_REST_Response(['filtered' => []], 200);
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ip, url, ua, method, status, response_ms, type, is_login, created_at
                     FROM {$table} WHERE created_at >= %s AND type = %s ORDER BY created_at DESC LIMIT 100",
                    $ago_1h, $val
                ), ARRAY_A);
                break;
            case 'status':
                $val = (int) $val;
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ip, url, ua, method, status, response_ms, type, is_login, created_at
                     FROM {$table} WHERE created_at >= %s AND status = %d ORDER BY created_at DESC LIMIT 100",
                    $ago_1h, $val
                ), ARRAY_A);
                break;
            case 'error':
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ip, url, ua, method, status, response_ms, type, is_login, created_at
                     FROM {$table} WHERE created_at >= %s AND status >= 400 ORDER BY created_at DESC LIMIT 100",
                    $ago_1h
                ), ARRAY_A);
                break;
            case 'login':
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ip, url, ua, method, status, response_ms, type, is_login, created_at
                     FROM {$table} WHERE created_at >= %s AND is_login = 1 ORDER BY created_at DESC LIMIT 100",
                    $ago_1h
                ), ARRAY_A);
                break;
            default:
                return new WP_REST_Response(['filtered' => []], 200);
        }

        return new WP_REST_Response(['filtered' => array_map([self::class, 'format_row'], $rows ?: [])], 200);
    }

    private static function format_row(array $r): array {
        return [
            'ip'       => $r['ip'],
            'url'      => $r['url'],
            'ua'       => $r['ua'],
            'method'   => $r['method'],
            'status'   => (int) $r['status'],
            'time'     => round($r['response_ms'] / 1000, 3),
            'type'     => $r['type'],
            'is_login' => (bool) $r['is_login'],
            'date'     => substr($r['created_at'], 11),
            'country'  => self::get_country($r['ip']),
        ];
    }

    private static $geo_runtime = [];

    private static function get_country(string $ip): string {
        if (isset(self::$geo_runtime[$ip])) return self::$geo_runtime[$ip];

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return self::$geo_runtime[$ip] = '';
        }
        $key = 'xavia_geo_' . md5($ip);
        $cached = get_transient($key);
        if ($cached !== false) return self::$geo_runtime[$ip] = $cached;

        $res  = wp_remote_get("https://ipinfo.io/{$ip}/json", ['timeout' => 3]);
        $body = wp_remote_retrieve_body($res);
        $data = $body ? json_decode($body, true) : null;
        $code = $data['country'] ?? '';

        set_transient($key, $code, 7 * DAY_IN_SECONDS);
        return self::$geo_runtime[$ip] = $code;
    }
}
