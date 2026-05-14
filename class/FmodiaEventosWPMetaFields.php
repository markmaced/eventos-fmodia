<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPMetaFields
{
    public static function register()
    {
        add_meta_box('fm-evento-detalhes', 'Detalhes do Evento', [__CLASS__, 'renderDetalhes'], 'fm_evento', 'normal', 'high');
        add_meta_box('fm-evento-localizacao', 'Localizacao', [__CLASS__, 'renderLocalizacao'], 'fm_evento', 'normal', 'default');
        add_meta_box('fm-evento-promocoes', 'Promocoes do Evento', [__CLASS__, 'renderPromocoes'], 'fm_evento', 'side', 'default');
    }

    public static function renderDetalhes($post)
    {
        wp_nonce_field('fm_evento_save_meta', 'fm_evento_meta_nonce');
        $meta = self::getMeta($post->ID);
        $statuses = FmodiaEventosWPManager::getStatuses();
        $classificacoes = FmodiaEventosWPManager::getClassificacoes();
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/meta-box-detalhes.php';
    }

    public static function renderLocalizacao($post)
    {
        $meta = self::getMeta($post->ID);
        $ufs = FmodiaEventosWPManager::getUfs();
        $locais = FmodiaEventosWPLocationTerm::getOptions();
        $selectedLocal = self::getSelectedLocal($post->ID);
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/meta-box-localizacao.php';
    }

    public static function renderPromocoes($post)
    {
        $selectedPromocoes = self::getPromocoes($post->ID);
        $promocoes = self::getPromotionOptions($selectedPromocoes);
        require FMODIAEVENTOSWP_PLUGIN_DIR . 'admin/meta-box-promocoes.php';
    }

    public static function save($postId, $post)
    {
        if (!isset($_POST['fm_evento_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fm_evento_meta_nonce'])), 'fm_evento_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $textFields = [
            'data_inicio', 'data_fim', 'hora_inicio', 'hora_fim', 'local_nome', 'endereco',
            'cidade', 'estado', 'cep', 'data_inicio_venda',
        ];
        foreach ($textFields as $field) {
            self::updateMeta($postId, $field, isset($_POST['fm_evento_' . $field]) ? sanitize_text_field(wp_unslash($_POST['fm_evento_' . $field])) : '');
        }

        $url = isset($_POST['fm_evento_link_ingresso']) ? esc_url_raw(wp_unslash($_POST['fm_evento_link_ingresso'])) : '';
        self::updateMeta($postId, 'link_ingresso', $url);

        $lineup = isset($_POST['fm_evento_lineup']) ? sanitize_textarea_field(wp_unslash($_POST['fm_evento_lineup'])) : '';
        self::updateMeta($postId, 'lineup', $lineup);

        foreach (['lat', 'lng', 'preco_min', 'preco_max'] as $field) {
            $value = isset($_POST['fm_evento_' . $field]) ? str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['fm_evento_' . $field]))) : '';
            self::updateMeta($postId, $field, $value === '' ? '' : (string) floatval($value));
        }

        self::updateMeta($postId, 'destaque', empty($_POST['fm_evento_destaque']) ? '' : '1');

        $localId = isset($_POST['fm_evento_local_id']) ? absint($_POST['fm_evento_local_id']) : 0;
        if ($localId) {
            wp_set_object_terms($postId, [$localId], 'fm_evento_local', false);
        } else {
            wp_set_object_terms($postId, [], 'fm_evento_local', false);
        }

        $status = isset($_POST['fm_evento_status']) ? sanitize_key(wp_unslash($_POST['fm_evento_status'])) : 'confirmado';
        if (!array_key_exists($status, FmodiaEventosWPManager::getStatuses())) {
            $status = 'confirmado';
        }
        self::updateMeta($postId, 'status', $status);

        $classificacao = isset($_POST['fm_evento_classificacao']) ? sanitize_key(wp_unslash($_POST['fm_evento_classificacao'])) : 'livre';
        if (!array_key_exists($classificacao, FmodiaEventosWPManager::getClassificacoes())) {
            $classificacao = 'livre';
        }
        self::updateMeta($postId, 'classificacao', $classificacao);

        self::savePromocoes($postId);
    }

    public static function getMeta($postId)
    {
        $fields = [
            'data_inicio', 'data_fim', 'hora_inicio', 'hora_fim', 'local_nome', 'endereco',
            'cidade', 'estado', 'cep', 'lat', 'lng', 'link_ingresso', 'data_inicio_venda',
            'preco_min', 'preco_max', 'lineup', 'classificacao', 'status', 'destaque',
        ];

        $meta = [];
        foreach ($fields as $field) {
            $meta[$field] = get_post_meta($postId, '_fm_evento_' . $field, true);
        }

        $meta['status'] = $meta['status'] ?: 'confirmado';
        $meta['classificacao'] = $meta['classificacao'] ?: 'livre';

        return $meta;
    }

    public static function getPromocoes($postId)
    {
        $ids = get_post_meta($postId, '_fm_evento_promocoes', true);
        if (!is_array($ids)) {
            $ids = $ids ? [$ids] : [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    private static function getSelectedLocal($postId)
    {
        $terms = wp_get_object_terms($postId, 'fm_evento_local', ['fields' => 'ids']);
        if (is_wp_error($terms) || empty($terms)) {
            return 0;
        }

        return absint($terms[0]);
    }

    private static function updateMeta($postId, $field, $value)
    {
        if ($value === '') {
            delete_post_meta($postId, '_fm_evento_' . $field);
            return;
        }

        update_post_meta($postId, '_fm_evento_' . $field, $value);
    }

    private static function savePromocoes($postId)
    {
        $raw = isset($_POST['fm_evento_promocoes']) && is_array($_POST['fm_evento_promocoes'])
            ? wp_unslash($_POST['fm_evento_promocoes'])
            : [];

        $ids = [];
        foreach ($raw as $value) {
            $id = absint($value);
            if (!$id || get_post_type($id) !== 'promotion') {
                continue;
            }
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if (!$ids) {
            delete_post_meta($postId, '_fm_evento_promocoes');
            return;
        }

        update_post_meta($postId, '_fm_evento_promocoes', $ids);
    }

    private static function getPromotionOptions(array $selectedIds = [])
    {
        if (!post_type_exists('promotion')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'promotion',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);

        $byId = [];
        foreach ($posts as $post) {
            $byId[$post->ID] = $post;
        }

        foreach ($selectedIds as $id) {
            if (!isset($byId[$id])) {
                $post = get_post($id);
                if ($post && $post->post_type === 'promotion') {
                    $byId[$post->ID] = $post;
                }
            }
        }

        return array_values($byId);
    }
}
