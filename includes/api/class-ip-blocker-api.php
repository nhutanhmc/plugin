<?php
if (!defined('ABSPATH')) exit;

class My_IP_Blocker_API {

    public static function register() {
        register_rest_route('my-plugin/v1', '/block-ip', [
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'block'],
                'permission_callback' => fn() => current_user_can('manage_options'),
                'args' => [
                    'ip'     => ['required' => true,  'sanitize_callback' => 'sanitize_text_field'],
                    'reason' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [self::class, 'unblock'],
                'permission_callback' => fn() => current_user_can('manage_options'),
                'args' => [
                    'ip' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);
    }

    public static function block(WP_REST_Request $req) {
        $cidr   = $req->get_param('ip');
        $reason = $req->get_param('reason') ?? '';

        if (!self::valid_cidr($cidr)) {
            return new WP_REST_Response(['error' => 'IP/CIDR khong hop le'], 400);
        }

        My_IP_Blocker::add($cidr, $reason, 'traffic-ui');
        My_Telegram::send(
            "<b>[IP Blocker] Block IP</b>\n" .
            "IP/CIDR: <code>{$cidr}</code>\n" .
            "Ly do: " . ($reason ?: 'Thu cong') . "\n" .
            "Thoi gian: " . current_time('H:i:s')
        );
        return new WP_REST_Response(['ok' => true]);
    }

    public static function unblock(WP_REST_Request $req) {
        $cidr = $req->get_param('ip');
        My_IP_Blocker::remove($cidr);
        return new WP_REST_Response(['ok' => true]);
    }

    private static function valid_cidr(string $cidr): bool {
        if (str_contains($cidr, '/')) {
            [$ip, $bits] = explode('/', $cidr, 2);
            $b = (int) $bits;
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && ctype_digit($bits) && $b >= 0 && $b <= 32;
        }
        return (bool) filter_var($cidr, FILTER_VALIDATE_IP);
    }
}
