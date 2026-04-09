<?php
if (!defined('ABSPATH')) exit;

class Xavia_Admin_Menu {

  public static function register() {
    add_menu_page(
      'Xavia',
      'Xavia',
      'manage_options',
      'xavia-dashboard',
      [self::class, 'render_dashboard'],
      'dashicons-businessman',
      30
    );

    add_submenu_page(
      'xavia-dashboard',
      'Chèn nội dung',
      'Chèn nội dung',
      'manage_options',
      'xavia-inject-content',
      [self::class, 'render_inject_content']
    );

    add_submenu_page(
      'xavia-dashboard',
      'Xóa hàng loạt',
      'Xóa hàng loạt',
      'manage_options',
      'xavia-bulk-delete',
      [self::class, 'render_bulk_delete']
    );

    add_submenu_page(
      'xavia-dashboard',
      'Cập nhật ảnh bìa',
      'Cập nhật ảnh bìa',
      'manage_options',
      'xavia-bulk-featured-image',
      [self::class, 'render_bulk_featured_image']
    );

    add_submenu_page(
      'xavia-dashboard',
      'VPS Health',
      'VPS Health',
      'manage_options',
      'xavia-vps-health',
      [self::class, 'render_vps_health']
    );

    add_submenu_page(
      'xavia-dashboard',
      'Traffic Health',
      'Traffic Health',
      'manage_options',
      'xavia-traffic-health',
      [self::class, 'render_traffic_health']
    );


    add_submenu_page(
      'xavia-dashboard',
      'IP Blocker',
      'IP Blocker',
      'manage_options',
      'xavia-ip-blocker',
      [self::class, 'render_ip_blocker']
    );
  }

  public static function render_dashboard() {
    require_once plugin_dir_path(__FILE__) . 'views/dashboard.php';
  }

  public static function render_vps_health() {
    require_once plugin_dir_path(__FILE__) . 'views/vps-health.php';
  }

  public static function render_bulk_delete() {
    require_once plugin_dir_path(__FILE__) . 'views/bulk-delete.php';
  }

  public static function render_bulk_featured_image() {
    require_once plugin_dir_path(__FILE__) . 'views/bulk-featured-image.php';
  }

  public static function render_traffic_health() {
    require_once plugin_dir_path(__FILE__) . 'views/traffic-health.php';
  }

  public static function render_inject_content() {
    require_once plugin_dir_path(__FILE__) . 'views/inject-content.php';
  }

  public static function render_ip_blocker() {
    require_once plugin_dir_path(__FILE__) . 'views/ip-blocker.php';
  }
}
