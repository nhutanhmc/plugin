<?php
if (!defined('ABSPATH')) exit;

class My_IP_Blocker {

    const OPTION_KEY = 'xavia_blocked_ips';
    public static function get_list(): array {
        return get_option(self::OPTION_KEY, []);
    }

    public static function add(string $cidr, string $reason = '', string $added_by = 'manual'): void {
        $cidr  = trim($cidr);
        $list  = self::get_list();
        // Tranh them trung
        foreach ($list as $item) {
            if ($item['cidr'] === $cidr) return;
        }
        $list[] = [
            'cidr'     => $cidr,
            'reason'   => sanitize_text_field($reason),
            'added_by' => $added_by,
            'added_at' => current_time('Y-m-d H:i:s'),
        ];
        update_option(self::OPTION_KEY, $list);
    }

    public static function remove(string $cidr): void {
        $list = array_values(array_filter(self::get_list(), fn($i) => $i['cidr'] !== $cidr));
        update_option(self::OPTION_KEY, $list);
    }
    public static function is_blocked(string $ip): bool {
        foreach (self::get_list() as $item) {
            if (self::ip_matches($ip, $item['cidr'])) return true;
        }
        return false;
    }

    // Goi dau capture_request — neu bi block thi tra 403 va exit
    public static function check_and_block(string $ip): void {
        if (!self::is_blocked($ip)) return;
        http_response_code(403);
        exit('Forbidden');
    }
    private static function ip_matches(string $ip, string $cidr): bool {
        if (!str_contains($cidr, '/')) return $ip === $cidr;
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
        $bits     = (int) $bits;
        $ip_long  = ip2long($ip);
        $sub_long = ip2long($subnet);
        if ($ip_long === false || $sub_long === false) return false;
        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
        return ($ip_long & $mask) === ($sub_long & $mask);
    }
}
