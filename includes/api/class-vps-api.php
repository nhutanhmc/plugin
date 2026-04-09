<?php
if (!defined('ABSPATH')) exit;

class My_VPS_API {

  public static function register() {
    register_rest_route('my-plugin/v1', '/vps-stats', [
      'methods'             => 'GET',
      'callback'            => [self::class, 'get_stats'],
      'permission_callback' => fn() => current_user_can('manage_options'),
    ]);
  }

  private static function metric($fn): array {
    try {
      return ['value' => $fn(), 'error' => null];
    } catch (Throwable $e) {
      return ['value' => null, 'error' => $e->getMessage()];
    }
  }

  // Cache metric vao transient, chi goi lai khi het han hoac co loi
  private static function cached_metric(string $key, callable $fn, int $ttl): array {
    $cached = get_transient($key);
    if ($cached !== false) return $cached;
    $result = self::metric($fn);
    if ($result['error'] === null) set_transient($key, $result, $ttl);
    return $result;
  }

  public static function get_stats() {
    $data = [];

    // CPU server — dung sys_getloadavg()
    $data['cpu'] = self::metric(function () {
      if (!function_exists('sys_getloadavg')) throw new Exception('sys_getloadavg() khong kha dung');
      $load = sys_getloadavg();
      if (!$load) throw new Exception('Khong lay duoc load average');
      $cores = get_transient('xavia_cpu_cores') ?: 1;
      if ($cores === 1 && is_readable('/proc/cpuinfo')) {
        $cores = max(1, preg_match_all('/^processor\s*:/m', file_get_contents('/proc/cpuinfo')));
        set_transient('xavia_cpu_cores', $cores, DAY_IN_SECONDS);
      }

      // Tinh CPU% va steal% tu /proc/stat delta 
      $pct   = null;
      $steal = 0;
      if (is_readable('/proc/stat')) {
        $line    = preg_split('/\s+/', trim(explode("\n", file_get_contents('/proc/stat'))[0]));
        $values  = array_slice($line, 1);
        $current = [
          'idle'  => (int)($values[3] ?? 0),
          'steal' => (int)($values[7] ?? 0),
          'total' => array_sum(array_map('intval', $values)),
        ];
        $prev = get_transient('xavia_proc_stat_prev');
        set_transient('xavia_proc_stat_prev', $current, 30);
        if ($prev) {
          $delta_total = $current['total'] - $prev['total'];
          if ($delta_total > 0) {
            $delta_idle = $current['idle'] - $prev['idle'];
            $pct   = min(100, max(0, round((1 - $delta_idle / $delta_total) * 100, 1)));
            $steal = max(0, round(($current['steal'] - $prev['steal']) / $delta_total * 100, 1));
          }
        }
      }
      // Fallback sang load average neu chua co du lieu delta (lan dau goi)
      if ($pct === null) {
        $pct = min(100, round($load[0] / $cores * 100, 1));
      }

      return [
        'pct'    => $pct,
        'load1'  => round($load[0], 2),
        'load5'  => round($load[1], 2),
        'load15' => round($load[2], 2),
        'cores'  => $cores,
        'steal'  => $steal,
      ];
    });

    // RAM server
    $data['ram'] = self::metric(function () {
      if (!is_readable('/proc/meminfo')) throw new Exception('Khong doc duoc /proc/meminfo — co the la shared hosting');
      $meminfo = file_get_contents('/proc/meminfo');
      if (!$meminfo) throw new Exception('Doc /proc/meminfo that bai');
      preg_match('/MemTotal:\s+(\d+)/',     $meminfo, $m_total);
      preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m_avail);
      if (empty($m_total[1])) throw new Exception('Khong parse duoc MemTotal tu /proc/meminfo');
      $total = round($m_total[1] / 1024);
      $avail = isset($m_avail[1]) ? round($m_avail[1] / 1024) : 0;
      $used  = $total - $avail;
      return ['used' => $used, 'total' => $total, 'pct' => $total > 0 ? round($used / $total * 100, 1) : 0];
    });

    // Disk
    $data['disk'] = self::metric(function () {
      $total = @disk_total_space('/');
      $free  = @disk_free_space('/');
      if ($total === false) throw new Exception('Khong lay duoc disk_total_space — kiem tra quyen truy cap thu muc /');
      if ($free === false)  throw new Exception('Khong lay duoc disk_free_space — kiem tra quyen truy cap thu muc /');
      $total_gb = round($total / 1073741824, 1);
      $free_gb  = round($free  / 1073741824, 1);
      $used_gb  = round($total_gb - $free_gb, 1);
      return ['used' => $used_gb, 'total' => $total_gb, 'pct' => $total_gb > 0 ? round($used_gb / $total_gb * 100, 1) : 0];
    });

    // Uptime server
    $data['uptime'] = self::metric(function () {
      if (!is_readable('/proc/uptime')) throw new Exception('Khong doc duoc /proc/uptime — co the la shared hosting');
      $content = file_get_contents('/proc/uptime');
      if (!$content) throw new Exception('Doc /proc/uptime that bai');
      $raw = (float) explode(' ', $content)[0];
      if ($raw <= 0) throw new Exception('Gia tri uptime khong hop le');
      return floor($raw / 86400) . 'd ' . floor(($raw % 86400) / 3600) . 'h ' . floor(($raw % 3600) / 60) . 'm';
    });

    // WP memory (request hien tai)
    $data['wp_memory'] = self::metric(function () {
      $used      = round(memory_get_usage(true) / 1048576, 1);
      $peak      = round(memory_get_peak_usage(true) / 1048576, 1);
      $limit    = ini_get('memory_limit');
      $suffix   = strtolower(substr(trim($limit), -1));
      $limit_mb = (int) $limit;
      if ($suffix === 'g') $limit_mb = (int) $limit * 1024;
      elseif ($suffix === 'k') $limit_mb = (int) round((int) $limit / 1024);
      return ['used' => $used, 'peak' => $peak, 'limit' => $limit, 'limit_mb' => $limit_mb];
    });

    // WP DB queries (request hien tai)
    $data['wp_db_queries'] = self::metric(function () {
      global $wpdb;
      if (!isset($wpdb)) throw new Exception('wpdb chua duoc khoi tao');
      return (int) $wpdb->num_queries;
    });

    // WP DB size
    $data['wp_db_size'] = self::cached_metric('xavia_db_size', function () {
      global $wpdb;
      if (!isset($wpdb)) throw new Exception('wpdb chua duoc khoi tao');
      $row = $wpdb->get_row("
        SELECT ROUND(SUM(data_length + index_length) / 1048576, 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
      ");
      if (!$row) throw new Exception('Query DB size that bai — kiem tra quyen information_schema');
      return (float) $row->size_mb;
    }, 5 * MINUTE_IN_SECONDS);

    // Active plugins
    $data['wp_plugins'] = self::metric(function () {
      $active = get_option('active_plugins', []);
      if (!is_array($active)) throw new Exception('get_option active_plugins khong tra ve array');
      return count($active);
    });

    // WP Cron pending 
    $data['wp_cron'] = self::cached_metric('xavia_wp_cron', function () {
      $crons = _get_cron_array();
      if (!is_array($crons)) throw new Exception('_get_cron_array khong tra ve array');
      $count = 0;
      foreach ($crons as $jobs) $count += count((array)$jobs);
      return $count;
    }, MINUTE_IN_SECONDS);

    // VPS alerts
    $vps_cfg = My_Traffic_Logger::get_settings();
    self::check_vps_alert('cpu_alert',  $vps_cfg['cpu_alert'],  $data['cpu'],  fn($v) => $v['pct'],
      fn($v, $thr) => "<b>Canh bao: CPU cao!</b>\nCPU: {$v['pct']}% (nguong: {$thr}%)\nLoad: {$v['load1']}",
      fn($v)       => "<b>CPU da tro lai binh thuong.</b>\nCPU: {$v['pct']}%"
    );
    self::check_vps_alert('ram_alert',  $vps_cfg['ram_alert'],  $data['ram'],  fn($v) => $v['pct'],
      fn($v, $thr) => "<b>Canh bao: RAM cao!</b>\nRAM: {$v['used']}/{$v['total']} MB ({$v['pct']}%) (nguong: {$thr}%)",
      fn($v)       => "<b>RAM da tro lai binh thuong.</b>\nRAM: {$v['pct']}%"
    );
    self::check_vps_alert('disk_alert', $vps_cfg['disk_alert'], $data['disk'], fn($v) => $v['pct'],
      fn($v, $thr) => "<b>Canh bao: Disk day!</b>\nDisk: {$v['used']}/{$v['total']} GB ({$v['pct']}%) (nguong: {$thr}%)",
      fn($v)       => "<b>Disk da tro lai binh thuong.</b>\nDisk: {$v['pct']}%"
    );

    // Server info 
    $data['server_os']       = self::cached_metric('xavia_server_os',  fn() => php_uname('s') . ' ' . php_uname('r'), HOUR_IN_SECONDS);
    $data['server_software'] = self::cached_metric('xavia_server_sw',  fn() => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown', HOUR_IN_SECONDS);
    $data['server_hostname'] = self::cached_metric('xavia_server_hn',  fn() => gethostname() ?: 'Unknown', HOUR_IN_SECONDS);

    // CPU cores 
    $data['cpu_cores'] = [
      'value' => $data['cpu']['error'] === null ? $data['cpu']['value']['cores'] : null,
      'error' => $data['cpu']['error'],
    ];

    // DB info 
    $data['db_version']         = self::cached_metric('xavia_db_ver',     fn() => $GLOBALS['wpdb']->get_var('SELECT VERSION()'), HOUR_IN_SECONDS);
    $data['db_max_connections'] = self::cached_metric('xavia_db_maxconn', function () {
      $row = $GLOBALS['wpdb']->get_row("SHOW VARIABLES LIKE 'max_connections'");
      if (!$row) throw new Exception('Khong lay duoc max_connections');
      return (int) $row->Value;
    }, HOUR_IN_SECONDS);
    $data['db_max_packet'] = self::cached_metric('xavia_db_maxpkt', function () {
      $row = $GLOBALS['wpdb']->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'");
      if (!$row) throw new Exception('Khong lay duoc max_allowed_packet');
      return round($row->Value / 1048576, 0) . ' MB';
    }, HOUR_IN_SECONDS);

    // PHP config 
    $data['php_upload_max'] = self::cached_metric('xavia_php_upload', fn() => ini_get('upload_max_filesize'), HOUR_IN_SECONDS);
    $data['php_post_max']   = self::cached_metric('xavia_php_post',   fn() => ini_get('post_max_size'),       HOUR_IN_SECONDS);
    $data['php_max_exec']   = self::cached_metric('xavia_php_exec',   fn() => ini_get('max_execution_time') . 's', HOUR_IN_SECONDS);

    $data['time'] = current_time('H:i:s');
    return new WP_REST_Response($data, 200);
  }

  private static function check_vps_alert(string $key, array $cfg, array $metric, callable $val_fn, callable $alert_msg, callable $recover_msg): void {
    if (empty($cfg['notify']) || $metric['error'] !== null) return;
    $val       = $val_fn($metric['value']);
    $threshold = (int) $cfg['threshold'];
    $cooldown  = max(1, (int) $cfg['cooldown']) * MINUTE_IN_SECONDS;
    $tkey      = 'xavia_vps_alert_' . $key;
    $active    = get_transient($tkey);
    if ($val >= $threshold && !$active) {
      My_Telegram::send($alert_msg($metric['value'], $threshold));
      set_transient($tkey, true, $cooldown);
    } elseif ($val < $threshold && $active) {
      My_Telegram::send($recover_msg($metric['value']));
      delete_transient($tkey);
    }
  }

}
