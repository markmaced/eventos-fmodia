<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPShortcode
{
    public static function register()
    {
        add_shortcode('fmodia_eventos', [__CLASS__, 'render']);
    }

    public static function render($atts)
    {
        $atts = shortcode_atts([
            'categoria' => '',
            'estado' => '',
            'cidade' => '',
            'mes' => current_time('Y-m'),
        ], $atts, 'fmodia_eventos');

        FmodiaEventosWPManager::registerFrontendAssets();

        $config = [
            'restUrl' => esc_url_raw(rest_url(FmodiaEventosWPApi::NS)),
            'restNonce' => wp_create_nonce('wp_rest'),
            'defaultMonth' => sanitize_text_field($atts['mes']) ?: current_time('Y-m'),
            'defaults' => [
                'categoria' => sanitize_title($atts['categoria']),
                'estado' => strtoupper(sanitize_text_field($atts['estado'])),
                'cidade' => sanitize_text_field($atts['cidade']),
            ],
            'ufs' => FmodiaEventosWPManager::getUfs(),
            'categorias' => self::getCategorias(),
        ];

        wp_enqueue_style('fmodia-eventos-calendario');
        wp_enqueue_script('fmodia-eventos-calendario');
        wp_add_inline_script(
            'fmodia-eventos-calendario',
            'window.FMODIA_EVENTOS = ' . wp_json_encode($config) . ';',
            'before'
        );

        ob_start();
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'shortcodes/calendario.php';
        return ob_get_clean();
    }

    private static function getCategorias()
    {
        $terms = get_terms([
            'taxonomy' => 'fm_evento_categoria',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_map(function ($term) {
            return [
                'id' => $term->term_id,
                'nome' => $term->name,
                'slug' => $term->slug,
                'cor' => get_term_meta($term->term_id, 'cor', true) ?: '#1976d2',
            ];
        }, $terms);
    }
}
