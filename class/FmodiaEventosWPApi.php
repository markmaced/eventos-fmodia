<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPApi
{
    const NS = 'fmodia-eventos/v1';

    public static function registerRoutes()
    {
        register_rest_route(self::NS, '/eventos', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'getEventos'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/eventos/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'getEvento'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/eventos/(?P<id>\d+)/ics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'getIcs'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/localidades', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'getLocalidades'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/filtros', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'getFiltros'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function getEventos(WP_REST_Request $request)
    {
        $month = sanitize_text_field($request->get_param('mes')) ?: gmdate('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return new WP_Error('mes_invalido', 'Mes invalido. Use YYYY-MM.', ['status' => 400]);
        }

        $start = $month . '-01';
        $end = gmdate('Y-m-t', strtotime($start));
        $estado = strtoupper(sanitize_text_field($request->get_param('estado')));
        $cidade = sanitize_text_field($request->get_param('cidade'));
        $categoria = sanitize_title($request->get_param('categoria'));
        $latParam = $request->get_param('lat');
        $lngParam = $request->get_param('lng');
        $hasGeo = is_numeric($latParam) && is_numeric($lngParam);
        $lat = $hasGeo ? floatval($latParam) : null;
        $lng = $hasGeo ? floatval($lngParam) : null;
        $raio = $request->get_param('raio') !== null && is_numeric($request->get_param('raio'))
            ? min(500, max(1, floatval($request->get_param('raio'))))
            : 30;

        $dateQuery = [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key' => '_fm_evento_data_inicio',
                    'value' => $end,
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
                [
                    'key' => '_fm_evento_data_fim',
                    'value' => $start,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key' => '_fm_evento_data_inicio',
                    'value' => $start,
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
                [
                    'key' => '_fm_evento_data_inicio',
                    'value' => $end,
                    'compare' => '<=',
                    'type' => 'DATE',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => '_fm_evento_data_fim',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => '_fm_evento_data_fim',
                        'value' => '',
                        'compare' => '=',
                    ],
                ],
            ],
        ];

        $metaQuery = [
            'relation' => 'AND',
            $dateQuery,
        ];

        if ($estado) {
            $metaQuery[] = ['key' => '_fm_evento_estado', 'value' => $estado, 'compare' => '='];
        }
        if ($cidade) {
            $metaQuery[] = ['key' => '_fm_evento_cidade', 'value' => $cidade, 'compare' => '='];
        }

        $args = [
            'post_type' => 'fm_evento',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'meta_value',
            'meta_key' => '_fm_evento_data_inicio',
            'order' => 'ASC',
            'meta_query' => $metaQuery,
        ];

        if ($categoria) {
            $args['tax_query'] = [[
                'taxonomy' => 'fm_evento_categoria',
                'field' => 'slug',
                'terms' => $categoria,
            ]];
        }

        $query = new WP_Query($args);
        $events = [];

        foreach ($query->posts as $post) {
            $event = self::formatEvent($post, false);
            if ($hasGeo) {
                $distance = self::distanceKm($lat, $lng, floatval($event['lat']), floatval($event['lng']));
                if ($distance > $raio) {
                    continue;
                }
                $event['distancia_km'] = round($distance, 1);
            }
            $events[] = $event;
        }

        wp_reset_postdata();

        $response = rest_ensure_response($events);
        $response->header('Cache-Control', 'max-age=300');
        return $response;
    }

    public static function getFiltros()
    {
        $localidades = self::getLocalidadesMap();
        $categorias = self::getCategorias();

        $response = rest_ensure_response([
            'ufs' => FmodiaEventosWPManager::getUfs(),
            'localidades' => $localidades,
            'categorias' => $categorias,
        ]);
        $response->header('Cache-Control', 'max-age=300');
        return $response;
    }

    public static function getEvento(WP_REST_Request $request)
    {
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== 'fm_evento' || $post->post_status !== 'publish') {
            return new WP_Error('evento_nao_encontrado', 'Evento nao encontrado.', ['status' => 404]);
        }

        $response = rest_ensure_response(self::formatEvent($post, true));
        $response->header('Cache-Control', 'max-age=300');
        return $response;
    }

    public static function getIcs(WP_REST_Request $request)
    {
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== 'fm_evento' || $post->post_status !== 'publish') {
            return new WP_Error('evento_nao_encontrado', 'Evento nao encontrado.', ['status' => 404]);
        }

        $ics = FmodiaEventosWPIcsBuilder::build($post);
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="evento-fmodia-' . absint($post->ID) . '.ics"');
        echo $ics;
        exit;
    }

    public static function getLocalidades()
    {
        $map = self::getLocalidadesMap();

        $response = rest_ensure_response($map);
        $response->header('Cache-Control', 'max-age=300');
        return $response;
    }

    private static function getLocalidadesMap()
    {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT estado.meta_value AS estado, cidade.meta_value AS cidade
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} estado ON estado.post_id = p.ID AND estado.meta_key = '_fm_evento_estado'
            INNER JOIN {$wpdb->postmeta} cidade ON cidade.post_id = p.ID AND cidade.meta_key = '_fm_evento_cidade'
            WHERE p.post_type = 'fm_evento'
              AND p.post_status = 'publish'
              AND estado.meta_value <> ''
              AND cidade.meta_value <> ''
            ORDER BY estado.meta_value ASC, cidade.meta_value ASC
        ");

        $map = [];
        foreach ($rows as $row) {
            $uf = strtoupper($row->estado);
            if (!isset($map[$uf])) {
                $map[$uf] = [];
            }
            if (!in_array($row->cidade, $map[$uf], true)) {
                $map[$uf][] = $row->cidade;
            }
        }

        return $map;
    }

    private static function getCategorias()
    {
        $terms = get_terms([
            'taxonomy' => 'fm_evento_categoria',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
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
                'count' => (int) $term->count,
            ];
        }, $terms);
    }

    private static function formatEvent(WP_Post $post, $full)
    {
        $meta = FmodiaEventosWPMetaFields::getMeta($post->ID);
        $terms = get_the_terms($post->ID, 'fm_evento_categoria');
        $term = (!is_wp_error($terms) && !empty($terms)) ? array_values($terms)[0] : null;
        $color = $term ? (get_term_meta($term->term_id, 'cor', true) ?: '#1976d2') : '#1976d2';
        $lineup = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $meta['lineup']))));

        $event = [
            'id' => $post->ID,
            'titulo' => get_the_title($post),
            'data_inicio' => $meta['data_inicio'],
            'data_fim' => $meta['data_fim'] ?: $meta['data_inicio'],
            'hora_inicio' => $meta['hora_inicio'],
            'hora_fim' => $meta['hora_fim'],
            'local_nome' => $meta['local_nome'],
            'cidade' => $meta['cidade'],
            'estado' => $meta['estado'],
            'status' => $meta['status'] ?: 'confirmado',
            'categoria' => $term ? ['nome' => $term->name, 'slug' => $term->slug, 'cor' => $color] : null,
            'cor' => $color,
            'thumbnail' => get_the_post_thumbnail_url($post, 'large') ?: '',
            'lat' => $meta['lat'],
            'lng' => $meta['lng'],
            'distancia_km' => null,
        ];

        if ($full) {
            $enderecoCompleto = trim(implode(', ', array_filter([$meta['local_nome'], $meta['endereco'], $meta['cidade'], $meta['estado'], $meta['cep']])));
            $event += [
                'descricao' => apply_filters('the_content', $post->post_content),
                'endereco' => $meta['endereco'],
                'cep' => $meta['cep'],
                'link_ingresso' => $meta['link_ingresso'],
                'data_inicio_venda' => $meta['data_inicio_venda'],
                'preco_min' => $meta['preco_min'],
                'preco_max' => $meta['preco_max'],
                'lineup' => $lineup,
                'classificacao' => $meta['classificacao'] ?: 'livre',
                'mapa_embed' => $enderecoCompleto ? 'https://www.google.com/maps?q=' . rawurlencode($enderecoCompleto) . '&output=embed' : '',
                'ics_url' => rest_url(self::NS . '/eventos/' . $post->ID . '/ics'),
            ];
        }

        return $event;
    }

    private static function distanceKm($lat1, $lng1, $lat2, $lng2)
    {
        if (!$lat2 || !$lng2) {
            return 999999;
        }

        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth * $c;
    }
}
