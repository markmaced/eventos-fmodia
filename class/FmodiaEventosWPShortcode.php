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

        $initialEvents = self::getInitialEventos($atts);

        $config = [
            'restUrl' => esc_url_raw(rest_url(FmodiaEventosWPApi::NS)),
            'restNonce' => wp_create_nonce('wp_rest'),
            'defaultMonth' => sanitize_text_field($atts['mes']) ?: current_time('Y-m'),
            'initialMonth' => sanitize_text_field($atts['mes']) ?: current_time('Y-m'),
            'initialEvents' => $initialEvents,
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
        echo self::renderEventSchema($initialEvents);
        return ob_get_clean();
    }

    public static function renderHome($atts)
    {
        $settings = FmodiaEventosWPSettings::get();

        $atts = shortcode_atts([
            'limit'      => $settings['home_limit'],
            'titulo'     => $settings['home_titulo'],
            'tag'        => $settings['home_tag'],
            'agenda_url' => $settings['home_agenda_url'],
            'layout'     => $settings['home_layout'],
            'ordem'      => $settings['home_order'],
            'visiveis'   => $settings['home_carrossel_visible'],
            'carrossel_visiveis' => '',
            'card'       => $settings['home_card'],
            'filtros'    => $settings['home_filtros'] ? 'sim' : 'nao',
            'categoria'  => '',
            'estado'     => '',
            'cidade'     => '',
            'periodo'    => 'tudo',
        ], $atts, 'fmodia_eventos_home');

        // Normaliza/valida as opcoes de aparencia.
        $layouts = FmodiaEventosWPSettings::layouts();
        $cardStyles = FmodiaEventosWPSettings::cardStyles();
        $layout = array_key_exists($atts['layout'], $layouts) ? $atts['layout'] : 'grade';
        $card = array_key_exists($atts['card'], $cardStyles) ? $atts['card'] : 'padrao';
        $orderOptions = FmodiaEventosWPSettings::orderOptions();
        $ordem = array_key_exists($atts['ordem'], $orderOptions) ? $atts['ordem'] : $settings['home_order'];

        $limit = max(1, min(48, intval($atts['limit'])));
        $visibleRaw = $atts['carrossel_visiveis'] !== '' ? $atts['carrossel_visiveis'] : $atts['visiveis'];
        $visible = $visibleRaw === ''
            ? max(1, min(8, intval($settings['home_carrossel_visible'])))
            : max(1, min(8, intval($visibleRaw)));
        $pool = $layout === 'carrossel' ? max($limit, $visible) : $limit;

        $periodos = self::getPeriodos();
        $periodo = array_key_exists($atts['periodo'], $periodos) ? $atts['periodo'] : 'tudo';

        $filtrosOn = in_array(strtolower((string) $atts['filtros']), ['sim', '1', 'true', 'yes', 'on'], true);
        $filtros = [
            'enabled'   => $filtrosOn,
            'categoria' => $filtrosOn && !empty($settings['home_filtro_categoria']),
            'data'      => $filtrosOn && !empty($settings['home_filtro_data']),
            'local'     => $filtrosOn && !empty($settings['home_filtro_local']),
            'busca'     => $filtrosOn && !empty($settings['home_filtro_busca']),
        ];
        $filtros['enabled'] = $filtros['categoria'] || $filtros['data'] || $filtros['local'] || $filtros['busca'];

        FmodiaEventosWPManager::registerFrontendAssets();
        wp_enqueue_style('fmodia-eventos-home');

        $needsJs = $filtros['enabled'] || $layout === 'carrossel';
        if ($needsJs) {
            wp_enqueue_script('fmodia-eventos-home');
        }

        $eventos = FmodiaEventosWPApi::proximosEventos([
            'categoria' => $atts['categoria'],
            'estado'    => $atts['estado'],
            'cidade'    => $atts['cidade'],
            'periodo'   => $periodo,
            'ordem'     => $ordem,
            'limit'     => $pool,
        ]);

        $titulo = sanitize_text_field($atts['titulo']);
        $tag = sanitize_text_field($atts['tag']);
        $agendaUrl = esc_url($atts['agenda_url']);
        $categorias = self::getCategorias();

        $config = [
            'restUrl'   => esc_url_raw(rest_url(FmodiaEventosWPApi::NS)),
            'restNonce' => wp_create_nonce('wp_rest'),
            'limit'     => $limit,
            'visible'   => $visible,
            'pool'      => $pool,
            'ordem'     => $ordem,
            'layout'    => $layout,
            'card'      => $card,
            'agendaUrl' => $agendaUrl,
            'filtros'   => $filtros,
            'defaults'  => [
                'categoria' => sanitize_title($atts['categoria']),
                'estado'    => strtoupper(sanitize_text_field($atts['estado'])),
                'cidade'    => sanitize_text_field($atts['cidade']),
                'periodo'   => $periodo,
                'busca'     => '',
            ],
        ];

        ob_start();
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'shortcodes/home.php';
        return ob_get_clean();
    }

    public static function getPeriodos()
    {
        return [
            'tudo'     => 'Qualquer data',
            'mes'      => 'Este mes',
            'prox-mes' => 'Proximo mes',
            '30d'      => 'Proximos 30 dias',
            '90d'      => 'Proximos 90 dias',
        ];
    }

    /**
     * Renderiza um card de evento do widget da home.
     * O markup gerado aqui e replicado no js/home.js (renderCard) para
     * que a filtragem no cliente produza cards identicos.
     *
     * @param array  $ev        Evento ja formatado por FmodiaEventosWPApi::formatEvent.
     * @param string $agendaUrl URL base da agenda.
     * @return string HTML escapado do card.
     */
    public static function renderHomeCard(array $ev, $agendaUrl)
    {
        $timestamp = strtotime((string) $ev['data_inicio']);
        if (!$timestamp) {
            return '';
        }

        $months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];

        $day = (int) date('j', $timestamp);
        $monthIdx = (int) date('n', $timestamp) - 1;
        $weekdayIdx = (int) date('w', $timestamp);
        $monthShort = isset($months[$monthIdx]) ? $months[$monthIdx] : '';
        $weekdayShort = isset($weekdays[$weekdayIdx]) ? $weekdays[$weekdayIdx] : '';

        $location = trim(implode(' Â· ', array_filter([
            $ev['local_nome'],
            ($ev['cidade'] && $ev['estado']) ? $ev['cidade'] . ', ' . $ev['estado'] : ($ev['cidade'] ?: $ev['estado']),
        ])));
        $hora = $ev['hora_inicio'] ? substr($ev['hora_inicio'], 0, 5) : '';
        $color = $ev['cor'] ?: '#d20143';
        $isToday = $ev['data_inicio'] === current_time('Y-m-d');
        $href = trailingslashit($agendaUrl) . '?evento=' . intval($ev['id']);
        $promo = isset($ev['promocoes_resumo']) && is_array($ev['promocoes_resumo']) ? $ev['promocoes_resumo'] : [];
        $promoOpen = isset($promo['abertas']) ? (int) $promo['abertas'] : 0;
        $promoTotal = isset($promo['total']) ? (int) $promo['total'] : 0;
        $promoMain = isset($promo['principal']) && is_array($promo['principal']) ? $promo['principal'] : null;

        ob_start();
        ?>
        <a class="fme-home-card" href="<?php echo esc_url($href); ?>" style="--ev-color: <?php echo esc_attr($color); ?>;">
            <div class="fme-home-card__media">
                <?php if ($ev['thumbnail']) : ?>
                    <img src="<?php echo esc_url($ev['thumbnail']); ?>" alt="" loading="lazy">
                <?php else : ?>
                    <div class="fme-home-card__media-fallback" aria-hidden="true">
                        <span><?php echo esc_html(mb_strtoupper(mb_substr($ev['titulo'], 0, 1))); ?></span>
                    </div>
                <?php endif; ?>

                <div class="fme-home-card__date" aria-hidden="true">
                    <span class="fme-home-card__date-day"><?php echo esc_html($day); ?></span>
                    <span class="fme-home-card__date-mon"><?php echo esc_html($monthShort); ?></span>
                </div>

                <?php if ($isToday) : ?>
                    <span class="fme-home-card__live">Hoje</span>
                <?php elseif ($ev['status'] === 'esgotado') : ?>
                    <span class="fme-home-card__live fme-home-card__live--warn">Esgotado</span>
                <?php elseif ($ev['status'] === 'cancelado') : ?>
                    <span class="fme-home-card__live fme-home-card__live--off">Cancelado</span>
                <?php endif; ?>
            </div>

            <div class="fme-home-card__body">
                <div class="fme-home-card__badges">
                    <?php if (!empty($ev['categoria'])) : ?>
                        <span class="fme-home-card__cat"><?php echo esc_html($ev['categoria']['nome']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ev['destaque'])) : ?>
                        <span class="fme-home-card__cat fme-home-card__cat--featured">Destaque</span>
                    <?php endif; ?>
                </div>
                <h3 class="fme-home-card__title"><?php echo esc_html($ev['titulo']); ?></h3>
                <p class="fme-home-card__meta">
                    <span class="fme-home-card__weekday"><?php echo esc_html($weekdayShort); ?></span>
                    <?php if ($hora) : ?>
                        <span class="fme-home-card__time"><?php echo esc_html($hora); ?></span>
                    <?php endif; ?>
                    <?php if ($location) : ?>
                        <span class="fme-home-card__location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($promoTotal > 0) : ?>
                    <span class="fme-home-card__promo <?php echo $promoOpen > 0 ? 'is-open' : 'is-closed'; ?>">
                        <strong><?php echo esc_html($promoOpen > 0 ? 'Promocao aberta' : 'Promocoes encerradas'); ?></strong>
                        <?php if ($promoOpen > 0 && $promoMain && !empty($promoMain['titulo'])) : ?>
                            <span><?php echo esc_html($promoMain['titulo']); ?></span>
                        <?php else : ?>
                            <span><?php echo esc_html($promoTotal . ' promocao' . ($promoTotal > 1 ? 'es' : '')); ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
        <?php
        return ob_get_clean();
    }

    private static function getInitialEventos(array $atts)
    {
        $request = new WP_REST_Request('GET', '/' . FmodiaEventosWPApi::NS . '/eventos');
        $request->set_param('mes', sanitize_text_field($atts['mes']) ?: current_time('Y-m'));

        if (!empty($atts['estado'])) {
            $request->set_param('estado', strtoupper(sanitize_text_field($atts['estado'])));
        }
        if (!empty($atts['cidade'])) {
            $request->set_param('cidade', sanitize_text_field($atts['cidade']));
        }
        if (!empty($atts['categoria'])) {
            $request->set_param('categoria', sanitize_title($atts['categoria']));
        }

        $response = FmodiaEventosWPApi::getEventos($request);
        if (is_wp_error($response)) {
            return [];
        }

        if ($response instanceof WP_REST_Response) {
            $data = $response->get_data();
            return is_array($data) ? $data : [];
        }

        return is_array($response) ? $response : [];
    }

    private static function renderEventSchema(array $events)
    {
        if (!$events) {
            return '';
        }

        $pageUrl = get_permalink(get_queried_object_id());
        if (!$pageUrl) {
            $pageUrl = home_url('/');
        }

        $graph = [];
        foreach ($events as $event) {
            $id = isset($event['id']) ? absint($event['id']) : 0;
            if (!$id) {
                continue;
            }

            $schema = self::schemaForEvent($id, $event, $pageUrl);
            if ($schema) {
                $graph[] = $schema;
            }
        }

        if (!$graph) {
            return '';
        }

        $json = wp_json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json ? "\n<script type=\"application/ld+json\">" . $json . "</script>\n" : '';
    }

    private static function schemaForEvent($eventId, array $event, $pageUrl)
    {
        $post = get_post($eventId);
        if (!$post || $post->post_type !== 'fm_evento') {
            return null;
        }

        $meta = FmodiaEventosWPMetaFields::getMeta($eventId);
        $url = add_query_arg('evento', $eventId, $pageUrl);
        $status = $meta['status'] ?: (isset($event['status']) ? $event['status'] : 'confirmado');
        $description = wp_strip_all_tags($post->post_excerpt ?: $post->post_content);
        $description = $description ? wp_trim_words($description, 45, '') : '';
        $lineup = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $meta['lineup']))));
        $image = get_the_post_thumbnail_url($post, 'large') ?: (isset($event['thumbnail']) ? $event['thumbnail'] : '');

        $schema = [
            '@type' => 'Event',
            '@id' => $url . '#event',
            'name' => get_the_title($post),
            'description' => $description,
            'url' => $url,
            'image' => $image ? [$image] : [],
            'startDate' => self::schemaDateTime($meta['data_inicio'], $meta['hora_inicio'], false),
            'endDate' => self::schemaDateTime($meta['data_fim'] ?: $meta['data_inicio'], $meta['hora_fim'] ?: $meta['hora_inicio'], true),
            'eventStatus' => self::schemaEventStatus($status),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => self::schemaLocation($meta),
            'organizer' => [
                '@type' => 'Organization',
                'name' => 'FM O Dia',
                'url' => home_url('/'),
            ],
            'performer' => array_map(function ($name) {
                return [
                    '@type' => 'MusicGroup',
                    'name' => $name,
                ];
            }, $lineup),
            'offers' => self::schemaOffer($meta, $status, $url),
        ];

        return self::cleanSchema($schema);
    }

    private static function schemaDateTime($date, $time, $endOfDay)
    {
        $date = trim((string) $date);
        if (!$date) {
            return '';
        }

        $time = trim((string) $time);
        if ($time) {
            $time = substr($time, 0, 5);
        } else {
            $time = $endOfDay ? '23:59' : '00:00';
        }

        $datetime = date_create_immutable_from_format('Y-m-d H:i', $date . ' ' . $time, wp_timezone());
        if ($datetime instanceof DateTimeImmutable) {
            return $datetime->format(DateTimeInterface::ATOM);
        }

        return $date;
    }

    private static function schemaEventStatus($status)
    {
        $map = [
            'cancelado' => 'https://schema.org/EventCancelled',
            'adiado' => 'https://schema.org/EventPostponed',
        ];

        return isset($map[$status]) ? $map[$status] : 'https://schema.org/EventScheduled';
    }

    private static function schemaLocation(array $meta)
    {
        return self::cleanSchema([
            '@type' => 'Place',
            'name' => $meta['local_nome'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $meta['endereco'],
                'addressLocality' => $meta['cidade'],
                'addressRegion' => $meta['estado'],
                'postalCode' => $meta['cep'],
                'addressCountry' => 'BR',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $meta['lat'],
                'longitude' => $meta['lng'],
            ],
        ]);
    }

    private static function schemaOffer(array $meta, $status, $url)
    {
        $hasPrice = $meta['preco_min'] !== '' || $meta['preco_max'] !== '';
        $price = $meta['preco_min'] !== '' ? $meta['preco_min'] : $meta['preco_max'];
        $availability = $status === 'esgotado'
            ? 'https://schema.org/SoldOut'
            : 'https://schema.org/InStock';

        if ($status === 'cancelado') {
            $availability = 'https://schema.org/Discontinued';
        }

        if (!$meta['link_ingresso'] && !$hasPrice) {
            return [];
        }

        return self::cleanSchema([
            '@type' => 'Offer',
            'url' => $meta['link_ingresso'] ?: $url,
            'price' => $hasPrice ? (string) $price : '',
            'priceCurrency' => $hasPrice ? 'BRL' : '',
            'availability' => $availability,
            'validFrom' => self::schemaDateTime(substr((string) $meta['data_inicio_venda'], 0, 10), substr((string) $meta['data_inicio_venda'], 11, 5), false),
        ]);
    }

    private static function cleanSchema($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        $out = [];

        foreach ($value as $key => $item) {
            $clean = self::cleanSchema($item);
            if ($clean === null || $clean === '' || (is_array($clean) && !$clean)) {
                continue;
            }

            if ($isList) {
                $out[] = $clean;
            } else {
                $out[$key] = $clean;
            }
        }

        return $out;
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
