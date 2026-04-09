<?php
if (!defined('ABSPATH')) exit;

class My_Traffic_Logger {

    const TABLE        = 'xavia_traffic';
    const KEEP_DAYS    = 7;
    const SETTINGS_KEY = 'xavia_autoblock_settings';

    public static function get_settings(): array {
        $defaults = [
            // Traffic auto-block
            'brute_force' => ['block' => true,  'notify' => true,  'threshold' => 5,  'window' => 5, 'cooldown' => 30],
            'bot_spike'   => ['block' => false, 'notify' => false, 'threshold' => 15, 'window' => 5, 'cooldown' => 30],
            '404_spike'   => ['block' => false, 'notify' => false, 'threshold' => 15, 'window' => 5, 'cooldown' => 30],
            '5xx_spike'   => ['block' => false, 'notify' => true,  'threshold' => 10, 'window' => 5, 'cooldown' => 15],
            // VPS alerts
            'cpu_alert'   => ['notify' => true,  'threshold' => 80, 'cooldown' => 60],
            'ram_alert'   => ['notify' => false, 'threshold' => 90, 'cooldown' => 60],
            'disk_alert'  => ['notify' => false, 'threshold' => 90, 'cooldown' => 60],
        ];
        $saved = get_option(self::SETTINGS_KEY, []);
        foreach ($defaults as $key => $def) {
            $defaults[$key] = array_merge($def, $saved[$key] ?? []);
        }
        return $defaults;
    }

    private static $request = null;

    // Goi ngay khi plugin load (truoc bat ky action nao)
    public static function init() {
        add_action('init', [self::class, 'capture_request'], 1);
        add_action('shutdown', [self::class, 'flush_request'], 999);
        add_action('xavia_traffic_cleanup', [self::class, 'cleanup']);
    }

    public static function capture_request() {
        if (defined('DOING_CRON') && DOING_CRON) return;

        // Bo qua tat ca traffic trong wp-admin (bao gom admin-ajax.php)
        if (is_admin()) return;

        $url    = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $ip = self::get_ip();

        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|woff2?|ttf|svg|webp|mp4|mp3)(\?.*)?$/i', $url)) return;

        if (str_contains($url, '/wp-json/my-plugin/v1/')) return;

        My_IP_Blocker::check_and_block($ip);

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_login = str_contains($url, 'wp-login.php') && $method === 'POST';

        self::$request = [
            'ip'       => $ip,
            'url'      => substr($url, 0, 500),
            'ua'       => substr($ua, 0, 500),
            'method'   => $method,
            'is_login' => $is_login ? 1 : 0,
            'type'     => self::classify($url, $ua, $is_login, $ip),
            'start'    => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        ];
    }

    public static function flush_request() {
        if (!self::$request) return;

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $status = http_response_code();
        if ($status === false) $status = 200;

        $ms = (int) round((microtime(true) - self::$request['start']) * 1000);

        $wpdb->insert($table, [
            'ip'          => self::$request['ip'],
            'url'         => self::$request['url'],
            'ua'          => self::$request['ua'],
            'method'      => self::$request['method'],
            'status'      => (int) $status,
            'response_ms' => $ms,
            'type'        => self::$request['type'],
            'is_login'    => self::$request['is_login'],
            'created_at'  => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s']);

        $settings = self::get_settings();

        if (self::$request['is_login']) {
            $bf = $settings['brute_force'];
            if (!empty($bf['block']) || !empty($bf['notify'])) self::check_brute_force(self::$request['ip'], $bf);
        }

        if ($status >= 500) {
            $sp = $settings['5xx_spike'];
            if (!empty($sp['block']) || !empty($sp['notify'])) self::check_5xx_spike($sp);
        }

        if ($status === 404) {
            $s404 = $settings['404_spike'];
            if (!empty($s404['block']) || !empty($s404['notify'])) self::check_404_spike($s404);
        }

        if (self::$request['type'] === 'bot') {
            $bot = $settings['bot_spike'];
            if (!empty($bot['block']) || !empty($bot['notify'])) self::check_bot_spike(self::$request['ip'], $bot);
        }
    }

    private static function check_brute_force(string $ip, array $cfg): void {
        $parts  = explode('.', $ip);
        $subnet = count($parts) === 4 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24' : $ip;

        global $wpdb;
        $table       = $wpdb->prefix . self::TABLE;
        $prefix_like = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.%';

        self::fire_alert('xavia_bf_' . md5($subnet), $cfg,
            fn() => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE is_login = 1 AND ip LIKE %s
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $prefix_like, $cfg['window']
            )),
            function ($count) use ($subnet, $cfg) {
                if (!empty($cfg['block'])) {
                    My_IP_Blocker::add($subnet, "Brute force: {$count} lan trong {$cfg['window']} phut", 'auto');
                }
                if (empty($cfg['notify'])) return null;
                return "<b>Canh bao: Brute force login!</b>\n" .
                    "Subnet: <code>{$subnet}</code>\n" .
                    "So lan thu: <b>{$count}</b> trong {$cfg['window']} phut\n" .
                    (!empty($cfg['block']) ? "Da tu dong block ca dai /24.\n" : "") .
                    "Thoi gian: " . current_time('H:i:s');
            }
        );
    }

    public static function get_ip() {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    private static function classify($url, $ua, $is_login, $ip = '') {
        $bot_ip_prefixes = [
            '66.249.',   // Googlebot
            '66.102.',   // Google
            '64.233.',   // Google
            '72.14.',    // Google
            '40.77.',    // Bingbot
            '40.80.',    // Bing
            '157.55.',   // Bingbot
            '207.46.',   // Bing
        ];
        foreach ($bot_ip_prefixes as $prefix) {
            if (str_starts_with($ip, $prefix)) return 'bot';
        }

        $ua_lower = strtolower($ua);
        $bots = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java/', 'go-http', 'axios', 'ahrefs', 'semrush', 'mj12', 'dotbot', 'yandex', 'baidu'];
        foreach ($bots as $kw) {
            if (str_contains($ua_lower, $kw)) return 'bot';
        }
        if ($is_login)                         return 'login';
        if (str_starts_with($url, '/wp-json')) return 'api';
        return 'human';
    }

    private static function check_5xx_spike(array $cfg): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        self::fire_alert('xavia_5xx_spike', $cfg,
            fn() => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE status >= 500
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $cfg['window']
            )),
            function ($count) use ($cfg, $wpdb, $table) {
                $blocked_subnet = null;
                if (!empty($cfg['block'])) {
                    $top_ip = $wpdb->get_var($wpdb->prepare(
                        "SELECT ip FROM {$table}
                         WHERE status >= 500
                           AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)
                         GROUP BY ip ORDER BY COUNT(*) DESC LIMIT 1",
                        $cfg['window']
                    ));
                    if ($top_ip) {
                        $parts = explode('.', $top_ip);
                        $blocked_subnet = count($parts) === 4
                            ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24'
                            : $top_ip;
                        My_IP_Blocker::add($blocked_subnet, "5xx spike: {$count} loi trong {$cfg['window']} phut", 'auto');
                    }
                }
                if (empty($cfg['notify'])) return null;
                return "<b>Canh bao: 5xx Spike!</b>\n" .
                    "So luong loi 5xx: <b>{$count}</b> trong {$cfg['window']} phut\n" .
                    ($blocked_subnet ? "Da tu dong block: <code>{$blocked_subnet}</code>\n" : "") .
                    "Thoi gian: " . current_time('H:i:s');
            }
        );
    }

    private static function check_bot_spike(string $ip, array $cfg): void {
        $parts  = explode('.', $ip);
        $subnet = count($parts) === 4 ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24' : $ip;

        global $wpdb;
        $table       = $wpdb->prefix . self::TABLE;
        $prefix_like = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.%';

        self::fire_alert('xavia_bot_' . md5($subnet), $cfg,
            fn() => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE type = 'bot' AND ip LIKE %s
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $prefix_like, $cfg['window']
            )),
            function ($count) use ($subnet, $cfg) {
                if (!empty($cfg['block'])) {
                    My_IP_Blocker::add($subnet, "Bot spike: {$count} request trong {$cfg['window']} phut", 'auto');
                }
                if (empty($cfg['notify'])) return null;
                return "<b>Canh bao: Bot spike!</b>\n" .
                    "Subnet: <code>{$subnet}</code>\n" .
                    "So request bot: <b>{$count}</b> trong {$cfg['window']} phut\n" .
                    (!empty($cfg['block']) ? "Da tu dong block ca dai /24.\n" : "") .
                    "Thoi gian: " . current_time('H:i:s');
            }
        );
    }

    private static function check_404_spike(array $cfg): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        self::fire_alert('xavia_404_spike', $cfg,
            fn() => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE status = 404
                   AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $cfg['window']
            )),
            function ($count) use ($cfg, $wpdb, $table) {
                $blocked_subnet = null;
                if (!empty($cfg['block'])) {
                    $top_ip = $wpdb->get_var($wpdb->prepare(
                        "SELECT ip FROM {$table}
                         WHERE status = 404
                           AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)
                         GROUP BY ip ORDER BY COUNT(*) DESC LIMIT 1",
                        $cfg['window']
                    ));
                    if ($top_ip) {
                        $parts = explode('.', $top_ip);
                        $blocked_subnet = count($parts) === 4
                            ? $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24'
                            : $top_ip;
                        My_IP_Blocker::add($blocked_subnet, "404 spike: {$count} loi trong {$cfg['window']} phut", 'auto');
                    }
                }
                if (empty($cfg['notify'])) return null;
                return "<b>Canh bao: 404 Spike!</b>\n" .
                    "So luong 404: <b>{$count}</b> trong {$cfg['window']} phut\n" .
                    ($blocked_subnet ? "Da tu dong block: <code>{$blocked_subnet}</code>\n" : "") .
                    "Thoi gian: " . current_time('H:i:s');
            }
        );
    }

    private static function fire_alert(string $key, array $cfg, callable $count_fn, callable $msg_fn): void {
        if (get_transient($key)) return;
        $count = $count_fn();
        if ($count >= $cfg['threshold']) {
            set_transient($key, true, $cfg['cooldown'] * MINUTE_IN_SECONDS);
            $msg = $msg_fn($count);
            if ($msg) My_Telegram::send($msg);
        }
    }

    // Goi trong register_activation_hook
    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(45) NOT NULL,
            url VARCHAR(500) NOT NULL,
            ua VARCHAR(500) NOT NULL DEFAULT '',
            method VARCHAR(10) NOT NULL DEFAULT 'GET',
            status SMALLINT(3) UNSIGNED NOT NULL DEFAULT 200,
            response_ms INT(11) NOT NULL DEFAULT 0,
            type VARCHAR(20) NOT NULL DEFAULT 'human',
            is_login TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_type (type),
            KEY idx_ip (ip(20))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if (!wp_next_scheduled('xavia_traffic_cleanup')) {
            wp_schedule_event(time(), 'daily', 'xavia_traffic_cleanup');
        }
    }

    public static function cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            date('Y-m-d H:i:s', current_time('timestamp') - self::KEEP_DAYS * DAY_IN_SECONDS)
        ));
    }
}
