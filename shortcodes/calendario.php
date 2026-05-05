<?php
if (!defined('ABSPATH')) {
    exit;
}

$fm_eventos_default_month = isset($atts['mes']) && preg_match('/^\d{4}-\d{2}$/', (string) $atts['mes'])
    ? (string) $atts['mes']
    : current_time('Y-m');
$fm_eventos_month_timestamp = strtotime($fm_eventos_default_month . '-01');
if (!$fm_eventos_month_timestamp) {
    $fm_eventos_month_timestamp = current_time('timestamp');
}
$fm_eventos_month_label = date_i18n('F \d\e Y', $fm_eventos_month_timestamp);
$fm_eventos_first_weekday = (int) date('w', $fm_eventos_month_timestamp);
$fm_eventos_grid_start = strtotime('-' . $fm_eventos_first_weekday . ' days', $fm_eventos_month_timestamp);
$fm_eventos_today = current_time('Y-m-d');
?>

<section class="fm-eventos" data-fm-eventos>
    <header class="fm-eventos__header">
        <div class="fm-eventos__brand">
            <span class="fm-eventos__icon" aria-hidden="true"><?php echo esc_html(date_i18n('j', current_time('timestamp'))); ?></span>
            <div>
                <p class="fm-eventos__eyebrow">Agenda FM O Dia</p>
                <h2 data-fm-month-label><?php echo esc_html($fm_eventos_month_label); ?></h2>
            </div>
        </div>
        <div class="fm-eventos__nav" aria-label="Navegacao do calendario">
            <button type="button" data-fm-today>Hoje</button>
            <button type="button" data-fm-prev aria-label="Mes anterior"><span aria-hidden="true">&lsaquo;</span></button>
            <button type="button" data-fm-next aria-label="Proximo mes"><span aria-hidden="true">&rsaquo;</span></button>
        </div>
    </header>

    <div class="fm-eventos__filters" role="search" aria-label="Filtros de eventos">
        <label>
            <span>Estado</span>
            <select data-fm-estado></select>
        </label>
        <label>
            <span>Cidade</span>
            <select data-fm-cidade></select>
        </label>
        <label>
            <span>Categoria</span>
            <select data-fm-categoria></select>
        </label>
        <div class="fm-eventos__geo">
            <button type="button" data-fm-geo>Minha localizacao</button>
            <label>
                <span>Raio</span>
                <input type="range" min="5" max="200" step="5" value="30" data-fm-raio>
                <output data-fm-raio-label>30 km</output>
            </label>
        </div>
    </div>

    <div class="fm-eventos__calendar" data-fm-calendar>
        <div class="fm-eventos__weekdays" aria-hidden="true">
            <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sab</span>
        </div>
        <div class="fm-eventos__grid" data-fm-eventos-grid aria-live="polite" role="grid">
            <?php for ($fm_eventos_i = 0; $fm_eventos_i < 42; $fm_eventos_i += 1) :
                $fm_eventos_day_timestamp = strtotime('+' . $fm_eventos_i . ' days', $fm_eventos_grid_start);
                $fm_eventos_day_key = date('Y-m-d', $fm_eventos_day_timestamp);
                $fm_eventos_day_classes = ['fm-eventos__day', 'is-empty'];
                if (date('m', $fm_eventos_day_timestamp) !== date('m', $fm_eventos_month_timestamp)) {
                    $fm_eventos_day_classes[] = 'is-muted';
                }
                if ($fm_eventos_day_key === $fm_eventos_today) {
                    $fm_eventos_day_classes[] = 'is-today';
                }
                ?>
                <div class="<?php echo esc_attr(implode(' ', $fm_eventos_day_classes)); ?>" role="gridcell" aria-label="<?php echo esc_attr(date_i18n('j \d\e F \d\e Y', $fm_eventos_day_timestamp)); ?>">
                    <span class="fm-eventos__date"><?php echo esc_html(date_i18n('j', $fm_eventos_day_timestamp)); ?></span>
                    <div class="fm-eventos__events"></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <p class="fm-eventos__status" data-fm-status></p>

    <div class="fm-eventos-modal" data-fm-modal hidden>
        <div class="fm-eventos-modal__backdrop" data-fm-close></div>
        <article class="fm-eventos-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="fm-eventos-modal-title">
            <button type="button" class="fm-eventos-modal__close" data-fm-close aria-label="Fechar">&times;</button>
            <div data-fm-modal-content></div>
        </article>
    </div>
</section>
