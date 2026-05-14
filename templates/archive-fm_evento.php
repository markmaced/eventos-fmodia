<?php
/**
 * Template do archive de eventos.
 * Carregado automaticamente quando o visitante acessa /eventos/.
 * Renderiza dentro do header/footer do tema ativo.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="primary" class="fme-archive-wrap" role="main">
    <?php echo do_shortcode('[fmodia_eventos]'); ?>
</main>

<?php
get_footer();
