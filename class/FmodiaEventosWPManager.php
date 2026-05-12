<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPManager
{
    private static $initiated = false;

    public static function init()
    {
        if (self::$initiated) {
            return;
        }

        self::$initiated = true;
        self::loadClasses();

        add_action('init', ['FmodiaEventosWPCPT', 'register']);
        add_action('init', ['FmodiaEventosWPShortcode', 'register']);
        add_action('init', [__CLASS__, 'maybeFlushRewrites'], 99);
        add_action('wp_enqueue_scripts', [__CLASS__, 'registerFrontendAssets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'registerAdminAssets']);
        add_action('add_meta_boxes', ['FmodiaEventosWPMetaFields', 'register']);
        add_action('save_post_fm_evento', ['FmodiaEventosWPMetaFields', 'save'], 10, 2);
        add_action('rest_api_init', ['FmodiaEventosWPApi', 'registerRoutes']);
        add_action('template_redirect', [__CLASS__, 'redirectSingleToArchive']);
        add_filter('template_include', [__CLASS__, 'useArchiveTemplate']);
        FmodiaEventosWPCategoryColor::init();
        FmodiaEventosWPLocationTerm::init();
    }

    const REWRITE_VERSION = '1.0.0-archive-v1';

    public static function maybeFlushRewrites()
    {
        if (get_option('fmodia_eventos_rewrite_version') !== self::REWRITE_VERSION) {
            flush_rewrite_rules(false);
            update_option('fmodia_eventos_rewrite_version', self::REWRITE_VERSION);
        }
    }

    public static function useArchiveTemplate($template)
    {
        if (is_post_type_archive('fm_evento')) {
            $plugin_template = FMODIAEVENTOSWP_PLUGIN_DIR . 'templates/archive-fm_evento.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public static function redirectSingleToArchive()
    {
        if (!is_singular('fm_evento')) {
            return;
        }
        $archive = get_post_type_archive_link('fm_evento');
        if (!$archive) {
            return;
        }
        wp_safe_redirect(add_query_arg('evento', get_the_ID(), $archive), 302);
        exit;
    }

    private static function loadClasses()
    {
        $classes = [
            'FmodiaEventosWPCPT',
            'FmodiaEventosWPMetaFields',
            'FmodiaEventosWPCategoryColor',
            'FmodiaEventosWPLocationTerm',
            'FmodiaEventosWPApi',
            'FmodiaEventosWPShortcode',
            'FmodiaEventosWPIcsBuilder',
        ];

        foreach ($classes as $className) {
            $file = FMODIAEVENTOSWP_PLUGIN_DIR . 'class/' . $className . '.php';
            if (!class_exists($className) && file_exists($file)) {
                require_once $file;
            }
        }
    }

    public static function activate()
    {
        self::loadClasses();
        FmodiaEventosWPCPT::register();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    public static function registerFrontendAssets()
    {
        $css = FMODIAEVENTOSWP_PLUGIN_DIR . 'css/calendario.css';
        $js = FMODIAEVENTOSWP_PLUGIN_DIR . 'js/calendario.js';
        $homeCss = FMODIAEVENTOSWP_PLUGIN_DIR . 'css/home.css';

        wp_register_style(
            'fmodia-eventos-calendario',
            FMODIAEVENTOSWP_PLUGIN_URL . 'css/calendario.css',
            [],
            file_exists($css) ? filemtime($css) : FMODIAEVENTOSWP_VERSION
        );

        wp_register_script(
            'fmodia-eventos-calendario',
            FMODIAEVENTOSWP_PLUGIN_URL . 'js/calendario.js',
            [],
            file_exists($js) ? filemtime($js) : FMODIAEVENTOSWP_VERSION,
            true
        );

        wp_register_style(
            'fmodia-eventos-home',
            FMODIAEVENTOSWP_PLUGIN_URL . 'css/home.css',
            [],
            file_exists($homeCss) ? filemtime($homeCss) : FMODIAEVENTOSWP_VERSION
        );
    }

    public static function registerAdminAssets($hook)
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $isEventScreen = $screen && (
            $screen->post_type === 'fm_evento' ||
            $screen->taxonomy === 'fm_evento_categoria' ||
            $screen->taxonomy === 'fm_evento_local'
        );

        if (!$isEventScreen) {
            return;
        }

        $css = FMODIAEVENTOSWP_PLUGIN_DIR . 'css/admin.css';
        $js = FMODIAEVENTOSWP_PLUGIN_DIR . 'js/admin.js';

        wp_enqueue_style(
            'fmodia-eventos-admin',
            FMODIAEVENTOSWP_PLUGIN_URL . 'css/admin.css',
            [],
            file_exists($css) ? filemtime($css) : FMODIAEVENTOSWP_VERSION
        );

        wp_enqueue_script(
            'fmodia-eventos-admin',
            FMODIAEVENTOSWP_PLUGIN_URL . 'js/admin.js',
            [],
            file_exists($js) ? filemtime($js) : FMODIAEVENTOSWP_VERSION,
            true
        );
    }

    public static function getUfs()
    {
        return [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapa', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceara', 'DF' => 'Distrito Federal', 'ES' => 'Espirito Santo',
            'GO' => 'Goias', 'MA' => 'Maranhao', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Para', 'PB' => 'Paraiba', 'PR' => 'Parana',
            'PE' => 'Pernambuco', 'PI' => 'Piaui', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondonia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'Sao Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
    }

    public static function getStatuses()
    {
        return [
            'confirmado' => 'Confirmado',
            'esgotado' => 'Esgotado',
            'cancelado' => 'Cancelado',
            'adiado' => 'Adiado',
        ];
    }

    public static function getClassificacoes()
    {
        return [
            'livre' => 'Livre',
            '10' => '10 anos',
            '12' => '12 anos',
            '14' => '14 anos',
            '16' => '16 anos',
            '18' => '18 anos',
        ];
    }
}
