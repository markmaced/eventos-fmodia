<?php
/**
 * Plugin Name: FM O Dia - Eventos
 * Description: Area de eventos da FM O Dia com calendario mensal, filtros, modal de detalhes e arquivos iCal.
 * Version: 1.0.0
 * Author: Marcos Macedo
 * Text Domain: fmodia-eventos
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FMODIAEVENTOSWP_VERSION', '1.0.0');
define('FMODIAEVENTOSWP_PLUGIN_FILE', __FILE__);
define('FMODIAEVENTOSWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FMODIAEVENTOSWP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once FMODIAEVENTOSWP_PLUGIN_DIR . 'class/FmodiaEventosWPManager.php';

register_activation_hook(__FILE__, ['FmodiaEventosWPManager', 'activate']);
register_deactivation_hook(__FILE__, ['FmodiaEventosWPManager', 'deactivate']);

FmodiaEventosWPManager::init();
