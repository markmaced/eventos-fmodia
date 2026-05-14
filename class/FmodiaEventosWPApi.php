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

        register_rest_route(self::NS, '/eventos/proximos', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'getProximos'],
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
        $monthParam = $request->get_param('mes');
        $month = sanitize_text_field(is_scalar($monthParam) ? (string) $monthParam : '') ?: gmdate('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return new WP_Error('mes_invalido', 'Mes invalido. Use YYYY-MM.', ['status' => 400]);
        }

        $start = $month . '-01';
        $end = gmdate('Y-m-t', strtotime($start));
        $estadoParam = $request->get_param('estado');
        $cidadeParam = $request->get_param('cidade');
        $categoriaParam = $request->get_param('categoria');
        $estado = strtoupper(sanitize_text_field(is_scalar($estadoParam) ? (string) $estadoParam : ''));
        $cidade = sanitize_text_field(is_scalar($cidadeParam) ? (string) $cidadeParam : '');
        $categoria = sanitize_title(is_scalar($categoriaParam) ? (string) $categoriaParam : '');
        $latParam = $request->get_param('lat');
        $lngParam = $request->get_param('lng');
        $hasGeo = is_numeric($latParam) && is_numeric($lngParam);
        $lat = $hasGeo ? floatval($latParam) : null;
        $lng = $hasGeo ? floatval($lngParam) : null;
        $raioParam = $request->get_param('raio');
        $raio = $raioParam !== null && is_numeric($raioParam)
            ? min(500, max(1, floatval($raioParam)))
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

        $query = self::runEventQuery($args);
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

    /**
     * Endpoint dos proximos eventos usado pelo widget da home.
     * Aceita: categoria, estado, cidade, busca, periodo, limit.
     */
    public static function getProximos(WP_REST_Request $request)
    {
        $events = self::proximosEventos([
            'categoria' => $request->get_param('categoria'),
            'estado'    => $request->get_param('estado'),
            'cidade'    => $request->get_param('cidade'),
            'busca'     => $request->get_param('busca'),
            'periodo'   => $request->get_param('periodo'),
            'ordem'     => $request->get_param('ordem'),
            'limit'     => $request->get_param('limit'),
        ]);

        $response = rest_ensure_response($events);
        $response->header('Cache-Control', 'max-age=120');
        return $response;
    }

    /**
     * Lista de proximos eventos ja formatada, com filtros opcionais.
     * Reutilizada pelo endpoint REST e pelo shortcode da home.
     *
     * @param array $filters categoria, estado, cidade, busca, periodo, limit
     * @return array
     */
    public static function proximosEventos(array $filters = [])
    {
        $today = current_time('Y-m-d');

        $categoria = isset($filters['categoria']) && is_scalar($filters['categoria'])
            ? sanitize_title((string) $filters['categoria']) : '';
        $estado = isset($filters['estado']) && is_scalar($filters['estado'])
            ? strtoupper(sanitize_text_field((string) $filters['estado'])) : '';
        $cidade = isset($filters['cidade']) && is_scalar($filters['cidade'])
            ? sanitize_text_field((string) $filters['cidade']) : '';
        $busca = isset($filters['busca']) && is_scalar($filters['busca'])
            ? sanitize_text_field((string) $filters['busca']) : '';
        $periodo = isset($filters['periodo']) && is_scalar($filters['periodo'])
            ? sanitize_key((string) $filters['periodo']) : 'tudo';
        $ordem = isset($filters['ordem']) && is_scalar($filters['ordem'])
            ? sanitize_key((string) $filters['ordem']) : 'data';
        if (!in_array($ordem, ['data', 'destaques', 'promocoes', 'categoria', 'cidade'], true)) {
            $ordem = 'data';
        }
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 4;
        $limit = max(1, min(48, $limit));

        // Evento ainda nao terminou (data_fim no futuro OU comeca no futuro).
        $upcoming = [
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
        ];

        $metaQuery = ['relation' => 'AND', $upcoming];

        // Janela de tempo (limita a data de inicio do evento).
        $range = self::periodoRange($periodo, $today);
        if ($range['start']) {
            $metaQuery[] = ['key' => '_fm_evento_data_inicio', 'value' => $range['start'], 'compare' => '>=', 'type' => 'DATE'];
        }
        if ($range['end']) {
            $metaQuery[] = ['key' => '_fm_evento_data_inicio', 'value' => $range['end'], 'compare' => '<=', 'type' => 'DATE'];
        }

        if ($estado) {
            $metaQuery[] = ['key' => '_fm_evento_estado', 'value' => $estado, 'compare' => '='];
        }
        if ($cidade) {
            $metaQuery[] = ['key' => '_fm_evento_cidade', 'value' => $cidade, 'compare' => '='];
        }

        $args = [
            'post_type' => 'fm_evento',
            'post_status' => 'publish',
            'posts_per_page' => $ordem === 'data' ? $limit : 48,
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

        if ($busca) {
            $args['s'] = $busca;
        }

        $query = self::runEventQuery($args);
        $events = [];
        foreach ($query->posts as $post) {
            $events[] = self::formatEvent($post, false);
        }
        wp_reset_postdata();

        return array_slice(self::sortEvents($events, $ordem), 0, $limit);
    }

    private static function sortEvents(array $events, $ordem)
    {
        if ($ordem === 'data') {
            return $events;
        }

        usort($events, function ($a, $b) use ($ordem) {
            if ($ordem === 'destaques') {
                $destaque = (int) !empty($b['destaque']) <=> (int) !empty($a['destaque']);
                if ($destaque !== 0) return $destaque;
            }

            if ($ordem === 'promocoes') {
                $promoA = isset($a['promocoes_resumo']['abertas']) ? (int) $a['promocoes_resumo']['abertas'] : 0;
                $promoB = isset($b['promocoes_resumo']['abertas']) ? (int) $b['promocoes_resumo']['abertas'] : 0;
                if ($promoA !== $promoB) return $promoB <=> $promoA;
            }

            if ($ordem === 'categoria') {
                $catA = isset($a['categoria']['nome']) ? (string) $a['categoria']['nome'] : '';
                $catB = isset($b['categoria']['nome']) ? (string) $b['categoria']['nome'] : '';
                $cat = strcasecmp($catA, $catB);
                if ($cat !== 0) return $cat;
            }

            if ($ordem === 'cidade') {
                $city = strcasecmp((string) $a['cidade'], (string) $b['cidade']);
                if ($city !== 0) return $city;
            }

            return strcmp((string) $a['data_inicio'], (string) $b['data_inicio']);
        });

        return $events;
    }

    /**
     * Converte um slug de periodo em uma janela start/end (YYYY-MM-DD).
     */
    private static function periodoRange($periodo, $today)
    {
        $ts = strtotime($today);
        if (!$ts) {
            return ['start' => '', 'end' => ''];
        }

        switch ($periodo) {
            case 'mes':
                return ['start' => '', 'end' => gmdate('Y-m-t', $ts)];
            case 'prox-mes':
                // "first day of next month" evita o salto de mes do "+1 month".
                $firstTs = strtotime('first day of next month', $ts);
                $first = gmdate('Y-m-01', $firstTs);
                return ['start' => $first, 'end' => gmdate('Y-m-t', $firstTs)];
            case '30d':
                return ['start' => '', 'end' => gmdate('Y-m-d', strtotime('+30 days', $ts))];
            case '90d':
                return ['start' => '', 'end' => gmdate('Y-m-d', strtotime('+90 days', $ts))];
            case 'tudo':
            default:
                return ['start' => '', 'end' => ''];
        }
    }

    /**
     * Executa um WP_Query de eventos imune a hooks globais que sobrescrevem
     * o meta_query.
     *
     * O tema fmodia-2023 engancha `pre_get_posts` (extra_fields_post_highlight_type)
     * em TODA consulta de front-end e descarta o meta_query, alem de injetar
     * filtros posts_join/where/orderby. Isso quebra os filtros de data, estado
     * e cidade dos eventos. Aqui desligamos esses hooks apenas durante a nossa
     * consulta e os restauramos logo em seguida, sem afetar o resto da pagina.
     */
    private static function runEventQuery(array $args)
    {
        $hijackHooks = [
            ['pre_get_posts', 'extra_fields_post_highlight_type'],
            ['posts_join', 'extra_fields_post_highlight_type_join'],
            ['posts_where', 'extra_fields_post_highlight_type_where'],
            ['posts_orderby', 'extra_fields_post_highlight_type_orderby'],
            ['posts_groupby', 'extra_fields_post_highlight_type_groupby'],
        ];

        $restore = [];
        foreach ($hijackHooks as $hook) {
            list($tag, $callback) = $hook;
            $priority = has_filter($tag, $callback);
            if ($priority !== false) {
                remove_filter($tag, $callback, $priority);
                $restore[] = [$tag, $callback, $priority];
            }
        }

        try {
            $query = new WP_Query($args);
        } finally {
            foreach ($restore as $hook) {
                add_filter($hook[0], $hook[1], $hook[2]);
            }
        }

        return $query;
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
            'destaque' => !empty($meta['destaque']),
            'categoria' => $term ? ['nome' => $term->name, 'slug' => $term->slug, 'cor' => $color] : null,
            'cor' => $color,
            'thumbnail' => get_the_post_thumbnail_url($post, 'large') ?: '',
            'lat' => $meta['lat'],
            'lng' => $meta['lng'],
            'distancia_km' => null,
            'promocoes_resumo' => self::getEventPromotionSummary($post->ID),
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
                'promocoes' => self::getEventPromotions($post->ID),
            ];
        }

        return $event;
    }

    private static function getEventPromotions($eventId)
    {
        static $cache = [];
        if (isset($cache[$eventId])) {
            return $cache[$eventId];
        }

        $ids = FmodiaEventosWPMetaFields::getPromocoes($eventId);
        if (!$ids || !post_type_exists('promotion')) {
            $cache[$eventId] = [];
            return [];
        }

        $posts = get_posts([
            'post_type' => 'promotion',
            'post_status' => 'publish',
            'post__in' => $ids,
            'posts_per_page' => count($ids),
            'orderby' => 'post__in',
            'suppress_filters' => true,
        ]);

        $cache[$eventId] = array_map([__CLASS__, 'formatPromotion'], $posts);
        return $cache[$eventId];
    }

    private static function getEventPromotionSummary($eventId)
    {
        $promotions = self::getEventPromotions($eventId);
        $summary = [
            'total' => count($promotions),
            'abertas' => 0,
            'encerradas' => 0,
            'principal' => null,
        ];

        foreach ($promotions as $promotion) {
            if ($promotion['status'] === 'encerrada') {
                $summary['encerradas']++;
                continue;
            }

            $summary['abertas']++;
            if (!$summary['principal']) {
                $summary['principal'] = [
                    'id' => $promotion['id'],
                    'titulo' => $promotion['titulo'],
                    'url' => $promotion['url'],
                    'status' => $promotion['status'],
                    'status_label' => $promotion['status_label'],
                ];
            }
        }

        return $summary;
    }

    private static function formatPromotion(WP_Post $post)
    {
        $start = (string) get_post_meta($post->ID, 'promotion_user_participation_date_start', true);
        $end = (string) get_post_meta($post->ID, 'promotion_user_participation_date_end', true);
        $status = self::promotionStatus($start, $end);

        return [
            'id' => $post->ID,
            'titulo' => get_the_title($post),
            'url' => get_permalink($post),
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium_large') ?: '',
            'resumo' => wp_strip_all_tags(get_the_excerpt($post)),
            'participacao_inicio' => $start,
            'participacao_fim' => $end,
            'status' => $status['status'],
            'status_label' => $status['label'],
        ];
    }

    private static function promotionStatus($start, $end)
    {
        $now = current_datetime()->getTimestamp();
        $endTs = self::promotionDateTimestamp($end, true);
        if ($endTs && $endTs < $now) {
            return ['status' => 'encerrada', 'label' => 'Encerrada'];
        }

        $startTs = self::promotionDateTimestamp($start, false);
        if ($startTs && $startTs > $now) {
            return ['status' => 'aberta', 'label' => 'Em breve'];
        }

        return ['status' => 'aberta', 'label' => 'Aberta'];
    }

    private static function promotionDateTimestamp($value, $endOfDay)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $value)) {
            $value .= $endOfDay ? ':59' : ':00';
        }

        $date = date_create_immutable_from_format('Y-m-d H:i:s', $value, wp_timezone());
        if ($date instanceof DateTimeImmutable) {
            return $date->getTimestamp();
        }

        $timestamp = strtotime($value);
        return $timestamp ? $timestamp : 0;
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
