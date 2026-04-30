<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPCategoryColor
{
    public static function init()
    {
        add_action('fm_evento_categoria_add_form_fields', [__CLASS__, 'renderAddField']);
        add_action('fm_evento_categoria_edit_form_fields', [__CLASS__, 'renderEditField']);
        add_action('created_fm_evento_categoria', [__CLASS__, 'save']);
        add_action('edited_fm_evento_categoria', [__CLASS__, 'save']);
    }

    public static function renderAddField()
    {
        $color = '#1976d2';
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/term-color-field.php';
    }

    public static function renderEditField($term)
    {
        $color = get_term_meta($term->term_id, 'cor', true) ?: '#1976d2';
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/term-color-field.php';
    }

    public static function save($termId)
    {
        if (!isset($_POST['fm_evento_categoria_cor'])) {
            return;
        }

        $color = sanitize_hex_color(wp_unslash($_POST['fm_evento_categoria_cor']));
        update_term_meta($termId, 'cor', $color ?: '#1976d2');
    }
}
