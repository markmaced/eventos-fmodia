<?php
/**
 * Creates local test promotions and links them to existing FM O Dia events.
 *
 * Run from the WordPress root:
 * C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe wp-content\plugins\FmodiaEventosWP\tools\seed-local-test-promotions.php
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

$pluginBasename = 'FmodiaEventosWP/FmodiaEventosWP.php';
if (function_exists('is_plugin_active') && !is_plugin_active($pluginBasename)) {
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

if (!post_type_exists('promotion')) {
    exit("Post type promotion is not registered. Activate the FM O Dia theme before running this seed.\n");
}

function fme_promo_event_key($eventId)
{
    $seedKey = (string) get_post_meta($eventId, '_fm_evento_seed_key', true);
    return $seedKey !== '' ? sanitize_key($seedKey) : 'event-' . absint($eventId);
}

function fme_promo_existing_id($seedKey)
{
    $posts = get_posts([
        'post_type' => 'promotion',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_fm_evento_promotion_seed_key',
        'meta_value' => $seedKey,
        'suppress_filters' => true,
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function fme_promo_datetime($timestamp, $time)
{
    return date_i18n('Y-m-d', $timestamp) . ' ' . $time;
}

function fme_promo_update_meta($postId, $key, $value)
{
    if ($value === '' || $value === null) {
        delete_post_meta($postId, $key);
        return;
    }

    update_post_meta($postId, $key, $value);
}

function fme_promo_create_or_update($eventId, $eventKey, array $promo)
{
    $seedKey = 'fm-evento-promo-' . $eventKey . '-' . $promo['slot'];
    $postId = fme_promo_existing_id($seedKey);

    $postData = [
        'post_type' => 'promotion',
        'post_status' => 'publish',
        'post_title' => $promo['title'],
        'post_excerpt' => $promo['excerpt'],
        'post_content' => $promo['content'],
        'post_author' => 1,
    ];

    if ($postId) {
        $postData['ID'] = $postId;
        $result = wp_update_post($postData, true);
    } else {
        $result = wp_insert_post($postData, true);
    }

    if (is_wp_error($result)) {
        throw new RuntimeException($result->get_error_message());
    }

    $postId = (int) $result;
    fme_promo_update_meta($postId, '_fm_evento_promotion_seed_key', $seedKey);
    fme_promo_update_meta($postId, '_fm_evento_promotion_seed_event', $eventKey);
    fme_promo_update_meta($postId, '_fm_evento_promotion_event_id', (string) $eventId);
    fme_promo_update_meta($postId, 'promotion_event_date', $promo['event_date']);
    fme_promo_update_meta($postId, 'promotion_user_participation_date_start', $promo['start']);
    fme_promo_update_meta($postId, 'promotion_user_participation_date_end', $promo['end']);
    fme_promo_update_meta($postId, 'promotion_result_date_start', $promo['result_start']);
    fme_promo_update_meta($postId, 'promotion_result_date_end', $promo['result_end']);
    fme_promo_update_meta($postId, 'promotion_user_participation_site', '1');
    fme_promo_update_meta($postId, 'promotion_user_participation_mobile', '1');
    fme_promo_update_meta($postId, 'promotion_user_participation_multiple_times', $promo['multiple'] ? '1' : '');
    fme_promo_update_meta($postId, 'promotion_user_incomplete_user_data', '1');
    fme_promo_update_meta($postId, 'promotion_regulation', "Promocao local de teste vinculada ao evento {$eventId}.");

    return $postId;
}

function fme_promo_replace_event_seed_links($eventId, $eventKey, array $newPromotionIds)
{
    $current = get_post_meta($eventId, '_fm_evento_promocoes', true);
    if (!is_array($current)) {
        $current = $current ? [$current] : [];
    }

    $manual = [];
    foreach ($current as $id) {
        $id = absint($id);
        if (!$id) {
            continue;
        }

        $linkedSeedEvent = (string) get_post_meta($id, '_fm_evento_promotion_seed_event', true);
        if ($linkedSeedEvent === $eventKey) {
            continue;
        }

        $manual[] = $id;
    }

    $ids = array_values(array_unique(array_merge($manual, $newPromotionIds)));
    if ($ids) {
        update_post_meta($eventId, '_fm_evento_promocoes', $ids);
    } else {
        delete_post_meta($eventId, '_fm_evento_promocoes');
    }
}

$events = get_posts([
    'post_type' => 'fm_evento',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'meta_value',
    'meta_key' => '_fm_evento_data_inicio',
    'order' => 'ASC',
    'suppress_filters' => true,
]);

if (!$events) {
    exit("No published events found.\n");
}

$now = current_time('timestamp');
$createdOrUpdated = 0;
$linkedEvents = 0;
$promotionIds = [];

foreach ($events as $event) {
    $eventId = (int) $event->ID;
    $eventKey = fme_promo_event_key($eventId);
    $eventTitle = get_the_title($event);
    $eventDate = (string) get_post_meta($eventId, '_fm_evento_data_inicio', true);
    $eventTime = (string) get_post_meta($eventId, '_fm_evento_hora_inicio', true);
    $eventDateTime = trim($eventDate . ' ' . ($eventTime ?: '00:00'));

    $templates = [
        [
            'slot' => 'aberta-vip',
            'title' => 'Promo teste: VIP - ' . $eventTitle,
            'excerpt' => 'Promocao aberta de teste com par de ingressos VIP.',
            'content' => '<p>Participe desta promocao local de teste para validar a area de promocoes do evento.</p>',
            'start' => fme_promo_datetime(strtotime('-3 days', $now), '09:00'),
            'end' => fme_promo_datetime(strtotime('+14 days', $now), '23:59'),
            'result_start' => fme_promo_datetime(strtotime('+15 days', $now), '10:00'),
            'result_end' => fme_promo_datetime(strtotime('+21 days', $now), '23:59'),
            'event_date' => $eventDateTime,
            'multiple' => true,
        ],
        [
            'slot' => 'aberta-em-breve',
            'title' => 'Promo teste: Bastidores - ' . $eventTitle,
            'excerpt' => 'Promocao em breve para testar exibicao no grupo de abertas.',
            'content' => '<p>Esta promocao local de teste ainda vai abrir, mas ja deve aparecer como associada ao evento.</p>',
            'start' => fme_promo_datetime(strtotime('+3 days', $now), '10:00'),
            'end' => fme_promo_datetime(strtotime('+24 days', $now), '23:59'),
            'result_start' => fme_promo_datetime(strtotime('+25 days', $now), '10:00'),
            'result_end' => fme_promo_datetime(strtotime('+30 days', $now), '23:59'),
            'event_date' => $eventDateTime,
            'multiple' => false,
        ],
        [
            'slot' => 'encerrada',
            'title' => 'Promo teste encerrada - ' . $eventTitle,
            'excerpt' => 'Promocao encerrada de teste para validar o agrupamento no modal.',
            'content' => '<p>Esta promocao local de teste tem prazo final no passado e deve aparecer como encerrada.</p>',
            'start' => fme_promo_datetime(strtotime('-28 days', $now), '09:00'),
            'end' => fme_promo_datetime(strtotime('-7 days', $now), '23:59'),
            'result_start' => fme_promo_datetime(strtotime('-6 days', $now), '10:00'),
            'result_end' => fme_promo_datetime(strtotime('-1 day', $now), '23:59'),
            'event_date' => $eventDateTime,
            'multiple' => false,
        ],
    ];

    $newIds = [];
    foreach ($templates as $template) {
        $newIds[] = fme_promo_create_or_update($eventId, $eventKey, $template);
    }

    fme_promo_replace_event_seed_links($eventId, $eventKey, $newIds);

    $createdOrUpdated += count($newIds);
    $linkedEvents++;
    $promotionIds = array_merge($promotionIds, $newIds);
}

$promotionIds = array_values(array_unique($promotionIds));

printf("Local test promotions ready. Events linked: %d. Promotions created/updated: %d.\n", $linkedEvents, $createdOrUpdated);
printf("Promotion IDs: %s\n", implode(', ', $promotionIds));
