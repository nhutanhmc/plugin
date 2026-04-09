<?php
if (!defined('ABSPATH')) exit;

class My_Inject_Content {

    const OPTION_KEY = 'xavia_inject_rules';

    public static function init() {
        add_filter('the_content', [self::class, 'apply_rules'], 20);
    }
    public static function get_rules(): array {
        return get_option(self::OPTION_KEY, []);
    }

    public static function save_rule(array $data): void {
        $rules = self::get_rules();
        $id    = sanitize_text_field($data['id'] ?? '');

        $rule = [
            'id'        => $id ?: uniqid('inj_'),
            'name'      => sanitize_text_field($data['name'] ?? ''),
            'category'  => (int) ($data['category'] ?? 0),
            'position'  => in_array($data['position'] ?? '', ['top', 'bottom', 'after_p']) ? $data['position'] : 'bottom',
            'paragraph' => max(1, (int) ($data['paragraph'] ?? 1)),
            'content'   => wp_kses($data['content'] ?? '', wp_kses_allowed_html('post') + [
                'iframe' => ['src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'allowfullscreen' => true, 'allow' => true, 'style' => true, 'class' => true],
            ]),
            'active'    => !empty($data['active']),
        ];

        if ($id) { //add no vo mang rule cu, neu co id thi update, khong co thi them moi
            foreach ($rules as &$r) {
                if ($r['id'] === $id) { $r = $rule; break; }
            }
        } else {
            $rules[] = $rule;
        }

        update_option(self::OPTION_KEY, $rules);
    }

    public static function delete_rule(string $id): void {
        $rules = array_values(array_filter(self::get_rules(), fn($r) => $r['id'] !== $id));
        update_option(self::OPTION_KEY, $rules);
    }

    public static function toggle_rule(string $id): void {
        $rules = self::get_rules();
        foreach ($rules as &$r) {
            if ($r['id'] === $id) { $r['active'] = !$r['active']; break; }
        }
        update_option(self::OPTION_KEY, $rules);
    }

    public static function get_rule(string $id): ?array {
        foreach (self::get_rules() as $r) {
            if ($r['id'] === $id) return $r;
        }
        return null;
    }

    public static function apply_rules(string $content): string {
        if (!is_singular('post') || is_admin()) return $content;

        $post_id    = get_the_ID();
        $categories = wp_get_post_categories($post_id);
        if (empty($categories)) return $content;

        $rules = array_filter(self::get_rules(), fn($r) => $r['active'] && in_array($r['category'], $categories));
        if (empty($rules)) return $content;

        // Xu ly top truoc, after_p theo thu tu paragraph tang dan, bottom cuoi cung
        usort($rules, function($a, $b) {
            $order = ['top' => 0, 'after_p' => 1, 'bottom' => 2];
            $oa = $order[$a['position']] ?? 1;
            $ob = $order[$b['position']] ?? 1;
            if ($oa !== $ob) return $oa - $ob;
            // Cung after_p: paragraph nho hon truoc
            return ($a['paragraph'] ?? 1) - ($b['paragraph'] ?? 1);
        });

        // Offset de bu cho noi dung da chen truoc do
        $p_offset = 0;

        foreach ($rules as $rule) {
            $inject = '<div class="xavia-inject">' . wpautop(do_shortcode($rule['content'])) . '</div>';
            switch ($rule['position']) {
                case 'top':
                    $content = $inject . $content;
                    break;
                case 'bottom':
                    $content = $content . $inject;
                    break;
                case 'after_p':
                    $n = ($rule['paragraph'] ?? 1) + $p_offset;
                    [$content, $inserted] = self::insert_after_p($content, $inject, $n);
                    if ($inserted) $p_offset++;
                    break;
            }
        }

        return $content;
    }

    private static function insert_after_p(string $content, string $inject, int $n): array {
        $count  = 0;
        $done   = false;
        $result = preg_replace_callback('/<\/p>/i', function($m) use (&$count, &$done, $n, $inject) {
            $count++;
            if ($count === $n && !$done) {
                $done = true;
                return '</p>' . $inject;
            }
            return $m[0];
        }, $content);

        // Neu khong du doan van, chen vao cuoi (false: khong tang p_offset)
        if (!$done) {
            return [$content . $inject, false];
        }
        return [$result ?? $content, true];
    }
}
