<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPIcsBuilder
{
    public static function build(WP_Post $post)
    {
        $meta = FmodiaEventosWPMetaFields::getMeta($post->ID);
        $start = self::formatDate($meta['data_inicio'], $meta['hora_inicio'], false);
        $endDate = $meta['data_fim'] ?: $meta['data_inicio'];
        $end = self::formatDate($endDate, $meta['hora_fim'] ?: $meta['hora_inicio'], true);
        $location = trim(implode(', ', array_filter([$meta['local_nome'], $meta['endereco'], $meta['cidade'], $meta['estado'], $meta['cep']])));
        $description = wp_strip_all_tags($post->post_content);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FM O Dia//Eventos//PT-BR',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:fmodia-evento-' . $post->ID . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start,
            'DTEND:' . $end,
            'SUMMARY:' . self::escape(get_the_title($post)),
            'DESCRIPTION:' . self::escape($description),
            'LOCATION:' . self::escape($location),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    private static function formatDate($date, $time, $isEnd)
    {
        if (!$date) {
            $date = gmdate('Y-m-d');
        }

        if (!$time) {
            $timestamp = strtotime($date . ($isEnd ? ' +1 day' : ''));
            return gmdate('Ymd', $timestamp);
        }

        $timestamp = strtotime($date . ' ' . $time);
        return gmdate('Ymd\THis', $timestamp);
    }

    private static function escape($value)
    {
        $value = str_replace(["\\", "\r\n", "\n", "\r", ',', ';'], ['\\\\', '\n', '\n', '\n', '\,', '\;'], (string) $value);
        return $value;
    }
}
