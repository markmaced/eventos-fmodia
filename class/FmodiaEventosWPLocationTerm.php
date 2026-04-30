<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPLocationTerm
{
    public static function init()
    {
        add_action('fm_evento_local_add_form_fields', [__CLASS__, 'renderAddFields']);
        add_action('fm_evento_local_edit_form_fields', [__CLASS__, 'renderEditFields']);
        add_action('created_fm_evento_local', [__CLASS__, 'save']);
        add_action('edited_fm_evento_local', [__CLASS__, 'save']);
    }

    public static function renderAddFields()
    {
        $meta = self::emptyMeta();
        $ufs = FmodiaEventosWPManager::getUfs();
        $isEdit = false;
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/term-location-fields.php';
    }

    public static function renderEditFields($term)
    {
        $meta = self::getMeta($term->term_id);
        $ufs = FmodiaEventosWPManager::getUfs();
        $isEdit = true;
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/term-location-fields.php';
    }

    public static function save($termId)
    {
        foreach (['endereco', 'cidade', 'estado', 'cep', 'lat', 'lng'] as $field) {
            $key = 'fm_evento_local_' . $field;
            $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';

            if (in_array($field, ['lat', 'lng'], true)) {
                $value = str_replace(',', '.', $value);
                $value = $value === '' ? '' : (string) floatval($value);
            }

            if ($field === 'estado') {
                $value = strtoupper($value);
            }

            if ($value === '') {
                delete_term_meta($termId, $field);
            } else {
                update_term_meta($termId, $field, $value);
            }
        }
    }

    public static function getMeta($termId)
    {
        $meta = self::emptyMeta();
        foreach ($meta as $field => $value) {
            $meta[$field] = get_term_meta($termId, $field, true);
        }

        return $meta;
    }

    public static function getOptions()
    {
        $terms = get_terms([
            'taxonomy' => 'fm_evento_local',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_map(function ($term) {
            $meta = self::getMeta($term->term_id);
            return [
                'id' => $term->term_id,
                'name' => $term->name,
                'endereco' => $meta['endereco'],
                'cidade' => $meta['cidade'],
                'estado' => $meta['estado'],
                'cep' => $meta['cep'],
                'lat' => $meta['lat'],
                'lng' => $meta['lng'],
            ];
        }, $terms);
    }

    private static function emptyMeta()
    {
        return [
            'endereco' => '',
            'cidade' => '',
            'estado' => '',
            'cep' => '',
            'lat' => '',
            'lng' => '',
        ];
    }
}
