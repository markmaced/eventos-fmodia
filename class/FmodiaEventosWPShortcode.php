<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPShortcode
{
    public static function register()
    {
        add_shortcode('fmodia_eventos', [__CLASS__, 'render']);
        add_shortcode('fmodia_eventos_home', [__CLASS__, 'renderHome']);
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

    public static function renderHome($atts)
    {
        $atts = shortcode_atts([
            'limit' => 4,
            'titulo' => 'Proximos eventos',
            'tag' => 'Eventos',
            'agenda_url' => '/eventos/',
            'categoria' => '',
            'estado' => '',
            'cidade' => '',
        ], $atts, 'fmodia_eventos_home');

        FmodiaEventosWPManager::registerFrontendAssets();
        wp_enqueue_style('fmodia-eventos-home');

        $today = current_time('Y-m-d');
        $args = [
            'post_type' => 'fm_evento',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(12, intval($atts['limit']))),
            'orderby' => 'meta_value',
            'meta_key' => '_fm_evento_data_inicio',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key' => '_fm_evento_data_fim',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE',
                    ],
                    [
                        'key' => '_fm_evento_data_inicio',
                        'value' => $today,
                        'compare' => '>=',
                        'type' => 'DATE',
                    ],
                ],
            ],
        ];

        if ($atts['estado']) {
            $args['meta_query'][] = ['key' => '_fm_evento_estado', 'value' => strtoupper(sanitize_text_field($atts['estado'])), 'compare' => '='];
        }
        if ($atts['cidade']) {
            $args['meta_query'][] = ['key' => '_fm_evento_cidade', 'value' => sanitize_text_field($atts['cidade']), 'compare' => '='];
        }
        if ($atts['categoria']) {
            $args['tax_query'] = [[
                'taxonomy' => 'fm_evento_categoria',
                'field' => 'slug',
                'terms' => sanitize_title($atts['categoria']),
            ]];
        }

        $query = new WP_Query($args);
        $eventos = [];
        foreach ($query->posts as $post) {
            $eventos[] = self::formatEventoForHome($post);
        }
        wp_reset_postdata();

        $titulo = sanitize_text_field($atts['titulo']);
        $tag = sanitize_text_field($atts['tag']);
        $agendaUrl = esc_url($atts['agenda_url']);

        ob_start();
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'shortcodes/home.php';
        return ob_get_clean();
    }

    private static function formatEventoForHome(WP_Post $post)
    {
        $meta = FmodiaEventosWPMetaFields::getMeta($post->ID);
        $terms = get_the_terms($post->ID, 'fm_evento_categoria');
        $term = (!is_wp_error($terms) && !empty($terms)) ? array_values($terms)[0] : null;
        $color = $term ? (get_term_meta($term->term_id, 'cor', true) ?: '#d20143') : '#d20143';

        return [
            'id' => $post->ID,
            'titulo' => get_the_title($post),
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium_large') ?: '',
            'data_inicio' => $meta['data_inicio'],
            'data_fim' => $meta['data_fim'] ?: $meta['data_inicio'],
            'hora_inicio' => $meta['hora_inicio'],
            'local_nome' => $meta['local_nome'],
            'cidade' => $meta['cidade'],
            'estado' => $meta['estado'],
            'status' => $meta['status'] ?: 'confirmado',
            'categoria' => $term ? ['nome' => $term->name, 'slug' => $term->slug, 'cor' => $color] : null,
            'cor' => $color,
        ];
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
