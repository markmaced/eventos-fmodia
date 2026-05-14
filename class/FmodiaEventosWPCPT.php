<?php

if (!defined('ABSPATH')) {
    exit;
}

class FmodiaEventosWPCPT
{
    public static function register()
    {
        register_post_type('fm_evento', [
            'labels' => [
                'name' => 'Eventos',
                'singular_name' => 'Evento',
                'add_new_item' => 'Adicionar novo evento',
                'edit_item' => 'Editar evento',
                'new_item' => 'Novo evento',
                'view_item' => 'Ver evento',
                'search_items' => 'Buscar eventos',
                'not_found' => 'Nenhum evento encontrado',
                'menu_name' => 'Eventos',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'exclude_from_search' => false,
            'has_archive' => 'eventos',
            'rewrite' => ['slug' => 'eventos', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-calendar-alt',
        ]);

        register_taxonomy('fm_evento_categoria', ['fm_evento'], [
            'labels' => [
                'name' => 'Categorias',
                'singular_name' => 'Categoria',
                'search_items' => 'Buscar categorias',
                'all_items' => 'Todas as categorias',
                'edit_item' => 'Editar categoria',
                'update_item' => 'Atualizar categoria',
                'add_new_item' => 'Adicionar nova categoria',
                'new_item_name' => 'Nome da nova categoria',
                'menu_name' => 'Categorias',
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => false,
            'rewrite' => false,
        ]);

        register_taxonomy('fm_evento_local', ['fm_evento'], [
            'labels' => [
                'name' => 'Locais',
                'singular_name' => 'Local',
                'search_items' => 'Buscar locais',
                'all_items' => 'Todos os locais',
                'edit_item' => 'Editar local',
                'update_item' => 'Atualizar local',
                'add_new_item' => 'Adicionar novo local',
                'new_item_name' => 'Nome do novo local',
                'menu_name' => 'Locais',
            ],
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => false,
            'rewrite' => false,
            'meta_box_cb' => false,
        ]);
    }
}
