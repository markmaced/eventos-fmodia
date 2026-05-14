<?php if (!defined('ABSPATH')) { exit; } ?>

<?php if (empty($promocoes)) : ?>
    <p class="fm-eventos-admin-empty">Nenhuma promocao cadastrada.</p>
<?php else : ?>
    <p class="fm-eventos-admin-note">Selecione as promocoes relacionadas a este evento.</p>
    <input type="search" class="fm-eventos-admin-search" placeholder="Buscar promocao..." data-fm-promotion-search>
    <div class="fm-eventos-admin-checklist" data-fm-promotion-list>
        <?php foreach ($promocoes as $promocao) : ?>
            <?php $statusObject = get_post_status_object($promocao->post_status); ?>
            <?php
            $title = get_the_title($promocao);
            $searchTitle = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
            ?>
            <label class="fm-eventos-admin-checkitem" data-fm-promotion-item data-search="<?php echo esc_attr($searchTitle); ?>">
                <input
                    type="checkbox"
                    name="fm_evento_promocoes[]"
                    value="<?php echo esc_attr($promocao->ID); ?>"
                    <?php checked(in_array((int) $promocao->ID, $selectedPromocoes, true)); ?>
                >
                <span>
                    <?php echo esc_html($title); ?>
                    <small><?php echo esc_html($statusObject ? $statusObject->label : $promocao->post_status); ?></small>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
