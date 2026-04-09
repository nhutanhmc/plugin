<?php
if (!defined('ABSPATH')) exit;

class My_Bulk_Featured_Image_API {

  public static function register() {
    register_rest_route('my-plugin/v1', '/bulk-featured-image', [
      'methods'             => 'POST',
      'callback'            => [self::class, 'handle'],
      'permission_callback' => fn() => current_user_can('manage_options'),
    ]);
  }

  public static function handle(WP_REST_Request $request) {
    $image   = $request->get_param('image')   ?? '';
    $urls    = $request->get_param('urls')     ?? [];
    $is_last = $request->get_param('is_last')  ?? false;
    $summary = $request->get_param('summary')  ?? null;

    $attach_id = is_numeric($image)
      ? (int) $image
      : attachment_url_to_postid($image);

    if (!$attach_id) {
      return new WP_REST_Response(['error' => 'Khong tim thay anh'], 400);
    }

    $success_titles = [];
    $failed         = [];

    foreach ($urls as $url) {
      $url     = trim($url);
      $post_id = url_to_postid($url);
      if (!$post_id) { $failed[] = $url; continue; }

      $result = set_post_thumbnail($post_id, $attach_id);
      if ($result) $success_titles[] = get_the_title($post_id);
      else $failed[] = $url;
    }

    if ($is_last && $summary) {
      $all_titles = array_merge($summary['titles'] ?? [], $success_titles);
      $all_failed = array_merge($summary['failed_urls'] ?? [], $failed);

      $items = array_map(fn($t) => "- $t", $all_titles);
      if (!empty($all_failed)) {
        $items[] = "\nURL that bai:";
        foreach (array_slice($all_failed, 0, 10) as $u) $items[] = "- $u";
        if (count($all_failed) > 10) $items[] = "... va " . (count($all_failed) - 10) . " URL khac";
      }

      My_Telegram::send_bulk('Cap nhat anh bia hang loat', $items, fn($i) => $i);
    }

    return new WP_REST_Response([
      'success'        => count($success_titles),
      'success_titles' => $success_titles,
      'failed'         => $failed,
    ], 200);
  }
}
