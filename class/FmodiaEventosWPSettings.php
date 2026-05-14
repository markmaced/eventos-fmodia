<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pagina de configuracoes do widget de eventos da home.
 * Menu: Eventos > Configuracoes
 */
class FmodiaEventosWPSettings
{
    const OPTION = 'fmodia_eventos_settings';
    const GROUP = 'fmodia_eventos_settings_group';
    const PAGE = 'fmodia-eventos-config';

    /** @var string */
    private static $hookSuffix = '';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'registerMenu']);
        add_action('admin_init', [__CLASS__, 'registerSettings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAssets']);
    }

    /**
     * Valores padrao das configuracoes.
     */
    public static function defaults()
    {
        return [
            'home_titulo'           => 'Proximos eventos',
            'home_tag'              => 'Agenda',
            'home_agenda_url'       => '/eventos/',
            'home_limit'            => 4,
            'home_carrossel_visible' => 4,
            'home_order'            => 'data',
            'home_layout'           => 'grade',    // grade | lista | carrossel
            'home_card'             => 'padrao',   // padrao | compacto | horizontal
            'home_filtros'          => 1,
            'home_filtro_categoria' => 1,
            'home_filtro_data'      => 1,
            'home_filtro_local'     => 1,
            'home_filtro_busca'     => 1,
        ];
    }

    /**
     * Le uma configuracao (ou todas, se $key for null), mesclada com os padroes.
     *
     * @param string|null $key
     * @return mixed
     */
    public static function get($key = null)
    {
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        $merged = array_merge(self::defaults(), $saved);

        if ($key === null) {
            return $merged;
        }

        return isset($merged[$key]) ? $merged[$key] : null;
    }

    public static function layouts()
    {
        return [
            'grade'     => 'Grade',
            'lista'     => 'Lista',
            'carrossel' => 'Carrossel',
        ];
    }

    public static function cardStyles()
    {
        return [
            'padrao'     => 'Padrao',
            'compacto'   => 'Compacto',
            'horizontal' => 'Horizontal',
        ];
    }

    public static function orderOptions()
    {
        return [
            'data'      => 'Data mais proxima',
            'destaques' => 'Destaques primeiro',
            'promocoes' => 'Promocoes abertas primeiro',
            'categoria' => 'Categoria',
            'cidade'    => 'Cidade',
        ];
    }

    public static function registerMenu()
    {
        self::$hookSuffix = add_submenu_page(
            'edit.php?post_type=fm_evento',
            'Configuracoes dos Eventos',
            'Configuracoes',
            'manage_options',
            self::PAGE,
            [__CLASS__, 'renderPage']
        );
    }

    public static function registerSettings()
    {
        register_setting(self::GROUP, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default'           => self::defaults(),
        ]);
    }

    public static function sanitize($input)
    {
        $d = self::defaults();
        $input = is_array($input) ? $input : [];
        $out = [];

        $out['home_titulo'] = isset($input['home_titulo'])
            ? sanitize_text_field($input['home_titulo'])
            : $d['home_titulo'];
        $out['home_tag'] = isset($input['home_tag'])
            ? sanitize_text_field($input['home_tag'])
            : $d['home_tag'];
        $out['home_agenda_url'] = isset($input['home_agenda_url']) && $input['home_agenda_url'] !== ''
            ? esc_url_raw($input['home_agenda_url'])
            : $d['home_agenda_url'];

        $out['home_limit'] = isset($input['home_limit'])
            ? max(1, min(48, intval($input['home_limit'])))
            : $d['home_limit'];

        $out['home_carrossel_visible'] = isset($input['home_carrossel_visible'])
            ? max(1, min(8, intval($input['home_carrossel_visible'])))
            : $d['home_carrossel_visible'];

        $order = isset($input['home_order']) ? sanitize_key($input['home_order']) : $d['home_order'];
        $out['home_order'] = array_key_exists($order, self::orderOptions()) ? $order : $d['home_order'];

        $layout = isset($input['home_layout']) ? sanitize_key($input['home_layout']) : $d['home_layout'];
        $out['home_layout'] = array_key_exists($layout, self::layouts()) ? $layout : $d['home_layout'];

        $card = isset($input['home_card']) ? sanitize_key($input['home_card']) : $d['home_card'];
        $out['home_card'] = array_key_exists($card, self::cardStyles()) ? $card : $d['home_card'];

        foreach (['home_filtros', 'home_filtro_categoria', 'home_filtro_data', 'home_filtro_local', 'home_filtro_busca'] as $flag) {
            $out[$flag] = empty($input[$flag]) ? 0 : 1;
        }

        return $out;
    }

    public static function enqueueAssets($hook)
    {
        if ($hook !== self::$hookSuffix) {
            return;
        }

        $css = FMODIAEVENTOSWP_PLUGIN_DIR . 'css/admin-settings.css';
        $js = FMODIAEVENTOSWP_PLUGIN_DIR . 'js/admin-settings.js';

        wp_enqueue_style(
            'fmodia-eventos-admin-settings',
            FMODIAEVENTOSWP_PLUGIN_URL . 'css/admin-settings.css',
            [],
            file_exists($css) ? filemtime($css) : FMODIAEVENTOSWP_VERSION
        );

        wp_enqueue_script(
            'fmodia-eventos-admin-settings',
            FMODIAEVENTOSWP_PLUGIN_URL . 'js/admin-settings.js',
            [],
            file_exists($js) ? filemtime($js) : FMODIAEVENTOSWP_VERSION,
            true
        );
    }

    public static function renderPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get();
        $layouts = self::layouts();
        $cardStyles = self::cardStyles();
        $orderOptions = self::orderOptions();

        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/settings-page.php';
    }
}
