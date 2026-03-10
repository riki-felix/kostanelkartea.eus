<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';

$posts = get_posts([
    'post_type'      => 'talks',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
]);

echo "<pre>\n";
foreach ($posts as $p) {
    $raw       = get_post_meta($p->ID, 'talk_date', true);
    $acf_val   = get_field('talk_date', $p->ID);
    $dt_raw    = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
    $dt_acf    = DateTime::createFromFormat('Y-m-d H:i:s', $acf_val);
    echo "ID: {$p->ID} | raw meta: [{$raw}] | get_field: [{$acf_val}] | raw parse: " . ($dt_raw ? 'OK' : 'FAIL') . " | acf parse: " . ($dt_acf ? 'OK' : 'FAIL') . "\n";
}
echo "</pre>";
