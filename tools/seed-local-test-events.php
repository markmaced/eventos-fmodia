<?php
/**
 * Creates local test events with generated featured images and adds the home shortcode.
 *
 * Run from the WordPress root or this plugin directory:
 * C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe wp-content\plugins\FmodiaEventosWP\tools\seed-local-test-events.php
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from CLI.\n");
}

$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'fmodiario.test';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$wpLoad = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wpLoad)) {
    exit("wp-load.php not found.\n");
}

require_once $wpLoad;
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$pluginBasename = 'FmodiaEventosWP/FmodiaEventosWP.php';
if (!is_plugin_active($pluginBasename)) {
    activate_plugin($pluginBasename);
}

if (!post_type_exists('fm_evento')) {
    require_once dirname(__DIR__) . '/FmodiaEventosWP.php';
    if (class_exists('FmodiaEventosWPCPT')) {
        FmodiaEventosWPCPT::register();
    }
}

if (!post_type_exists('fm_evento')) {
    exit("Post type fm_evento is not registered.\n");
}

function fme_local_term_id($taxonomy, $name, array $meta = [])
{
    $term = term_exists($name, $taxonomy);
    if (!$term) {
        $term = wp_insert_term($name, $taxonomy);
    }

    if (is_wp_error($term)) {
        throw new RuntimeException($term->get_error_message());
    }

    $termId = is_array($term) ? (int) $term['term_id'] : (int) $term;
    foreach ($meta as $key => $value) {
        update_term_meta($termId, $key, $value);
    }

    return $termId;
}

function fme_local_existing_post_id($key, $postType)
{
    $posts = get_posts([
        'post_type' => $postType,
        'post_status' => 'any',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_fm_evento_seed_key',
        'meta_value' => $key,
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function fme_local_update_event_meta($postId, $field, $value)
{
    $key = '_fm_evento_' . $field;
    if ($value === '' || $value === null) {
        delete_post_meta($postId, $key);
        return;
    }

    update_post_meta($postId, $key, (string) $value);
}

function fme_local_wrap_text($text, $maxChars)
{
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = trim($line . ' ' . $word);
        if (strlen($candidate) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return array_slice($lines, 0, 3);
}

function fme_local_generate_poster($path, $title, $category, array $palette)
{
    if (file_exists($path)) {
        return;
    }

    if (!extension_loaded('gd')) {
        throw new RuntimeException('The GD extension is required to generate test images.');
    }

    $width = 1200;
    $height = 675;
    $image = imagecreatetruecolor($width, $height);
    imageantialias($image, true);

    [$r1, $g1, $b1] = sscanf($palette[0], '#%02x%02x%02x');
    [$r2, $g2, $b2] = sscanf($palette[1], '#%02x%02x%02x');

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / max(1, $height - 1);
        $r = (int) round($r1 + ($r2 - $r1) * $ratio);
        $g = (int) round($g1 + ($g2 - $g1) * $ratio);
        $b = (int) round($b1 + ($b2 - $b1) * $ratio);
        imageline($image, 0, $y, $width, $y, imagecolorallocate($image, $r, $g, $b));
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $yellow = imagecolorallocate($image, 255, 232, 26);
    $dark = imagecolorallocatealpha($image, 0, 0, 0, 65);
    $soft = imagecolorallocatealpha($image, 255, 255, 255, 92);

    imagefilledellipse($image, 980, 80, 360, 360, $soft);
    imagefilledellipse($image, 110, 590, 430, 430, $soft);
    imagefilledrectangle($image, 0, 500, $width, $height, $dark);
    imagefilledrectangle($image, 72, 72, 1128, 114, $yellow);

    imagestring($image, 5, 92, 84, strtoupper($category), imagecolorallocate($image, 210, 1, 67));
    imagestring($image, 5, 92, 142, 'FM O DIA - EVENTO TESTE', $white);

    $y = 218;
    foreach (fme_local_wrap_text(strtoupper($title), 28) as $line) {
        imagestring($image, 5, 92, $y, $line, $white);
        $y += 38;
    }

    imagestring($image, 5, 92, 585, 'fmodiario.test', $white);
    imagestring($image, 5, 930, 585, 'AGENDA LOCAL', $yellow);

    if (!imagepng($image, $path, 6)) {
        imagedestroy($image);
        throw new RuntimeException('Could not save image: ' . $path);
    }

    imagedestroy($image);
}

function fme_local_attachment_id($key, $title, $category, array $palette)
{
    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_fm_evento_test_image_key',
        'meta_value' => $key,
    ]);

    if ($existing) {
        return (int) $existing[0];
    }

    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        throw new RuntimeException($upload['error']);
    }

    $dir = trailingslashit($upload['basedir']) . 'fmodia-eventos-teste';
    if (!is_dir($dir) && !wp_mkdir_p($dir)) {
        throw new RuntimeException('Could not create upload directory: ' . $dir);
    }

    $filename = sanitize_file_name($key . '.png');
    $path = trailingslashit($dir) . $filename;
    fme_local_generate_poster($path, $title, $category, $palette);

    $filetype = wp_check_filetype($filename, null);
    $attachmentId = wp_insert_attachment([
        'guid' => trailingslashit($upload['baseurl']) . 'fmodia-eventos-teste/' . $filename,
        'post_mime_type' => $filetype['type'] ?: 'image/png',
        'post_title' => $title . ' - imagem teste',
        'post_content' => '',
        'post_status' => 'inherit',
    ], $path);

    if (is_wp_error($attachmentId)) {
        throw new RuntimeException($attachmentId->get_error_message());
    }

    update_post_meta($attachmentId, '_fm_evento_test_image_key', $key);
    update_attached_file($attachmentId, $path);
    wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $path));

    return (int) $attachmentId;
}

function fme_local_add_home_shortcode()
{
    $frontId = (int) get_option('page_on_front');
    if (!$frontId) {
        $frontId = 34;
    }

    $page = get_post($frontId);
    if (!$page || $page->post_type !== 'page') {
        throw new RuntimeException('Front page not found.');
    }

    if (strpos($page->post_content, '[fmodia_eventos_home') !== false) {
        return $frontId;
    }

    $shortcodeBlock = "\n\n<!-- wp:shortcode -->\n[fmodia_eventos_home limit=\"10\" titulo=\"Eventos teste\" tag=\"Agenda\" agenda_url=\"/eventos/\"]\n<!-- /wp:shortcode -->";
    $updated = wp_update_post([
        'ID' => $frontId,
        'post_content' => rtrim($page->post_content) . $shortcodeBlock,
    ], true);

    if (is_wp_error($updated)) {
        throw new RuntimeException($updated->get_error_message());
    }

    return $frontId;
}

$categories = [
    'Pagode' => '#188038',
    'Samba' => '#0b8043',
    'Funk' => '#f4511e',
    'Especial' => '#5f6368',
    'FM O Dia Apresenta' => '#1a73e8',
];

$venues = [
    [
        'name' => 'Estudio FM O Dia',
        'address' => 'Rua Carlos Machado, 131 - Barra da Tijuca',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '22775-042',
        'lat' => '-22.9767',
        'lng' => '-43.3715',
    ],
    [
        'name' => 'Vivo Rio',
        'address' => 'Av. Infante Dom Henrique, 85 - Parque do Flamengo',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '20021-140',
        'lat' => '-22.9252',
        'lng' => '-43.1714',
    ],
    [
        'name' => 'Quadra da Portela',
        'address' => 'R. Clara Nunes, 81 - Madureira',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'cep' => '21351-110',
        'lat' => '-22.8723',
        'lng' => '-43.3437',
    ],
];

$palettes = [
    ['#d20143', '#007cc2'],
    ['#007cc2', '#facf1c'],
    ['#7b1fa2', '#d20143'],
    ['#188038', '#00acc1'],
    ['#f4511e', '#d20143'],
];

$baseTimestamp = current_time('timestamp');
$events = [
    ['title' => 'Pagode Teste na Praia', 'cat' => 'Pagode', 'days' => 4, 'time' => '18:00', 'lineup' => ['Grupo Teste 1', 'Convidado Especial']],
    ['title' => 'Samba Teste de Domingo', 'cat' => 'Samba', 'days' => 8, 'time' => '16:00', 'lineup' => ['Roda Local', 'Bateria Teste']],
    ['title' => 'Funk Teste Sunset', 'cat' => 'Funk', 'days' => 12, 'time' => '20:00', 'lineup' => ['DJ Teste', 'MC Local']],
    ['title' => 'FM O Dia Apresenta Teste 1', 'cat' => 'FM O Dia Apresenta', 'days' => 16, 'time' => '19:30', 'lineup' => ['Atracao Principal', 'Abertura Teste']],
    ['title' => 'Especial Teste da Alegria', 'cat' => 'Especial', 'days' => 20, 'time' => '15:00', 'lineup' => ['Equipe FM O Dia']],
    ['title' => 'Pagode Teste no Estudio', 'cat' => 'Pagode', 'days' => 24, 'time' => '18:30', 'lineup' => ['Pagode Local', 'Participacao Teste']],
    ['title' => 'Samba Teste na Lapa', 'cat' => 'Samba', 'days' => 28, 'time' => '21:00', 'lineup' => ['Samba Livre', 'Voz Teste']],
    ['title' => 'Baile Funk Teste', 'cat' => 'Funk', 'days' => 32, 'time' => '22:00', 'lineup' => ['DJ Noite', 'Equipe Teste']],
    ['title' => 'FM O Dia Apresenta Teste 2', 'cat' => 'FM O Dia Apresenta', 'days' => 36, 'time' => '20:00', 'lineup' => ['Dupla Teste', 'Grupo Convidado']],
    ['title' => 'Especial Teste Fim de Semana', 'cat' => 'Especial', 'days' => 40, 'time' => '17:00', 'lineup' => ['Convidados Surpresa']],
];

foreach ($categories as $name => $color) {
    fme_local_term_id('fm_evento_categoria', $name, ['cor' => $color]);
}

$created = 0;
$updated = 0;
$ids = [];

foreach ($events as $index => $event) {
    $key = 'local-test-event-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $venue = $venues[$index % count($venues)];
    $date = date('Y-m-d', strtotime('+' . (int) $event['days'] . ' days', $baseTimestamp));
    $palette = $palettes[$index % count($palettes)];
    $imageId = fme_local_attachment_id($key, $event['title'], $event['cat'], $palette);

    $postData = [
        'post_type' => 'fm_evento',
        'post_status' => 'publish',
        'post_title' => $event['title'],
        'post_content' => '<p>Evento de teste local criado para validar a vitrine e a agenda de eventos.</p>',
        'post_author' => 1,
    ];

    $postId = fme_local_existing_post_id($key, 'fm_evento');
    if ($postId) {
        $postData['ID'] = $postId;
        $result = wp_update_post($postData, true);
        $updated++;
    } else {
        $result = wp_insert_post($postData, true);
        $created++;
    }

    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }

    $postId = (int) $result;
    $categoryId = fme_local_term_id('fm_evento_categoria', $event['cat'], ['cor' => $categories[$event['cat']] ?? '#d20143']);
    $locationId = fme_local_term_id('fm_evento_local', $venue['name'], [
        'endereco' => $venue['address'],
        'cidade' => $venue['city'],
        'estado' => $venue['state'],
        'cep' => $venue['cep'],
        'lat' => $venue['lat'],
        'lng' => $venue['lng'],
    ]);

    wp_set_object_terms($postId, [$categoryId], 'fm_evento_categoria', false);
    wp_set_object_terms($postId, [$locationId], 'fm_evento_local', false);

    update_post_meta($postId, '_fm_evento_seed_key', $key);
    update_post_meta($postId, '_thumbnail_id', $imageId);

    fme_local_update_event_meta($postId, 'data_inicio', $date);
    fme_local_update_event_meta($postId, 'data_fim', $date);
    fme_local_update_event_meta($postId, 'hora_inicio', $event['time']);
    fme_local_update_event_meta($postId, 'hora_fim', date('H:i', strtotime($event['time'] . ' +3 hours')));
    fme_local_update_event_meta($postId, 'local_nome', $venue['name']);
    fme_local_update_event_meta($postId, 'endereco', $venue['address']);
    fme_local_update_event_meta($postId, 'cidade', $venue['city']);
    fme_local_update_event_meta($postId, 'estado', $venue['state']);
    fme_local_update_event_meta($postId, 'cep', $venue['cep']);
    fme_local_update_event_meta($postId, 'lat', $venue['lat']);
    fme_local_update_event_meta($postId, 'lng', $venue['lng']);
    fme_local_update_event_meta($postId, 'link_ingresso', 'https://www.fmodia.com.br/');
    fme_local_update_event_meta($postId, 'preco_min', (string) (30 + ($index * 5)));
    fme_local_update_event_meta($postId, 'preco_max', (string) (90 + ($index * 10)));
    fme_local_update_event_meta($postId, 'lineup', implode("\n", $event['lineup']));
    fme_local_update_event_meta($postId, 'classificacao', $index % 3 === 0 ? 'livre' : '16');
    fme_local_update_event_meta($postId, 'status', 'confirmado');

    $ids[] = $postId;
}

$frontId = fme_local_add_home_shortcode();

flush_rewrite_rules(false);

printf("Local test events ready. Created: %d. Updated: %d. Total: %d.\n", $created, $updated, count($ids));
printf("Event IDs: %s\n", implode(', ', $ids));
printf("Home page updated: %d.\n", $frontId);
