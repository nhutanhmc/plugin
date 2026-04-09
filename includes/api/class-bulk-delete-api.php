<?php
if (!defined('ABSPATH')) exit;

class My_Bulk_Delete_API {

  public static function register() {
    register_rest_route('my-plugin/v1', '/bulk-delete', [
      'methods'             => 'POST',
      'callback'            => [self::class, 'handle'],
      'permission_callback' => fn() => current_user_can('manage_options'),
    ]);
  }

  public static function handle(WP_REST_Request $request) {
    $urls    = $request->get_param('urls')    ?? [];
    $action  = $request->get_param('action')  ?? 'trash';
    $is_last = $request->get_param('is_last') ?? false;
    $summary = $request->get_param('summary') ?? null;

    $success_titles  = [];
    $failed          = [];
    $already_trashed = [];
    $not_in_trash    = [];

    foreach ($urls as $url) {
      $url     = trim($url);
      $post_id = url_to_postid($url);

      $slug       = basename(rtrim(parse_url($url, PHP_URL_PATH), '/'));
      $trashed    = get_page_by_path($slug . '__trashed', OBJECT, 'post');
      $trashed_id = $trashed ? $trashed->ID : 0;

      // Phuc hoi tu trash
      if ($action === 'restore') {
        if (!$trashed_id) {
          if ($post_id) { $not_in_trash[] = $url; } else { $failed[] = $url; }
          continue;
        }
        $result = wp_untrash_post($trashed_id);
        if ($result) $success_titles[] = $trashed->post_title;
        else $failed[] = $url;
        continue;
      }

      if (!$post_id && $trashed_id && $action === 'trash') {
        $already_trashed[] = $url;
        continue;
      }

      if (!$post_id && $trashed_id) $post_id = $trashed_id;

      if (!$post_id) { $failed[] = $url; continue; }

      $title  = get_the_title($post_id);
      $result = $action === 'delete'
        ? wp_delete_post($post_id, true)
        : wp_trash_post($post_id);

      if ($result) $success_titles[] = $title;
      else $failed[] = $url;
    }

    if ($is_last && $summary) {
      $all_titles          = array_merge($summary['titles'] ?? [], $success_titles);
      $all_failed          = array_merge($summary['failed_urls'] ?? [], $failed);
      $all_already_trashed = array_merge($summary['already_trashed'] ?? [], $already_trashed);
      $all_not_in_trash    = array_merge($summary['not_in_trash'] ?? [], $not_in_trash);
      $label               = match($action) {
        'delete'  => 'Xoa vinh vien',
        'restore' => 'Phuc hoi tu trash',
        default   => 'Chuyen vao thung rac',
      };
      $success_count = count($all_titles);

      $lines = ["<b>{$label} ({$success_count} bai)</b>"];
      foreach ($all_titles as $t) $lines[] = "- $t";

      if (!empty($all_already_trashed)) {
        $lines[] = "\nDa co trong trash (" . count($all_already_trashed) . " URL):";
        foreach (array_slice($all_already_trashed, 0, 5) as $u) $lines[] = "- $u";
        if (count($all_already_trashed) > 5) $lines[] = "... va " . (count($all_already_trashed) - 5) . " URL khac";
      }
      if (!empty($all_not_in_trash)) {
        $lines[] = "\nKhong co trong trash (" . count($all_not_in_trash) . " URL):";
        foreach (array_slice($all_not_in_trash, 0, 5) as $u) $lines[] = "- $u";
        if (count($all_not_in_trash) > 5) $lines[] = "... va " . (count($all_not_in_trash) - 5) . " URL khac";
      }
      if (!empty($all_failed)) {
        $lines[] = "\nKhong tim thay (" . count($all_failed) . " URL):";
        foreach (array_slice($all_failed, 0, 5) as $u) $lines[] = "- $u";
        if (count($all_failed) > 5) $lines[] = "... va " . (count($all_failed) - 5) . " URL khac";
      }

      if ($success_count > 0 || !empty($all_failed) || !empty($all_not_in_trash)) {
        My_Telegram::send(implode("\n", $lines));
      }
      Xavia_Notify_Queue::suppress(['trash', 'delete', 'restore']);
    }

    return new WP_REST_Response([
      'success'         => count($success_titles),
      'success_titles'  => $success_titles,
      'failed'          => $failed,
      'already_trashed' => $already_trashed,
      'not_in_trash'    => $not_in_trash,
    ], 200);
  }
}
