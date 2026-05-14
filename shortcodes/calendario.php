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
?>

<section class="fme" data-fm-eventos>
    <header class="fme__hero">
        <div class="fme__hero-text">
            <p class="fme__eyebrow">FM O Dia &middot; Agenda</p>
            <h1 class="fme__heading">Eventos</h1>
            <p class="fme__subheading">Os melhores shows, festas e experiencias selecionados para voce.</p>
        </div>
        <div class="fme__monthnav">
            <button type="button" class="fme__icon-btn" data-fm-prev aria-label="Mes anterior">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z"/></svg>
            </button>
            <h2 class="fme__monthtitle" data-fm-month-label><?php echo esc_html($fm_eventos_month_label); ?></h2>
            <button type="button" class="fme__icon-btn" data-fm-next aria-label="Proximo mes">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M8.6 7.4 10 6l6 6-6 6-1.4-1.4L13.2 12z"/></svg>
            </button>
            <button type="button" class="fme__today-btn" data-fm-today>Hoje</button>
        </div>
    </header>

    <div class="fme__toolbar">
        <div class="fme__viewswitch" role="tablist" aria-label="Modo de visualizacao">
            <button type="button" data-fm-view-btn="agenda" class="is-active" role="tab" aria-selected="true">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3 5h18v2H3zm0 6h18v2H3zm0 6h18v2H3z"/></svg>
                <span>Agenda</span>
            </button>
            <button type="button" data-fm-view-btn="mes" role="tab" aria-selected="false">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14zM5 8V6h14v2z"/></svg>
                <span>Mes</span>
            </button>
        </div>

        <div class="fme__filters" role="search" aria-label="Filtros de eventos">
            <div class="fme__filter">
                <svg class="fme__filter-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                <select data-fm-estado aria-label="Estado"></select>
            </div>
            <div class="fme__filter">
                <svg class="fme__filter-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3 21h18v-2H3v2zm2-2h4V9H5v10zm6 0h4V3h-4v16zm6 0h4v-7h-4v7z"/></svg>
                <select data-fm-cidade aria-label="Cidade"></select>
            </div>
            <div class="fme__filter">
                <svg class="fme__filter-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M21.4 11.6 12.4 2.6c-.4-.4-.9-.6-1.4-.6H4c-1.1 0-2 .9-2 2v7c0 .6.2 1 .6 1.4l9 9c.4.4.9.6 1.4.6.6 0 1-.2 1.4-.6l7-7c.4-.4.6-.9.6-1.4 0-.6-.2-1-.6-1.4zM6.5 8C5.7 8 5 7.3 5 6.5S5.7 5 6.5 5 8 5.7 8 6.5 7.3 8 6.5 8z"/></svg>
                <select data-fm-categoria aria-label="Categoria"></select>
            </div>
            <button type="button" class="fme__geo-btn" data-fm-geo>
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="3" fill="currentColor"/><path fill="currentColor" d="M12 2v3m0 14v3M2 12h3m14 0h3" stroke="currentColor" stroke-width="2"/></svg>
                <span data-fm-geo-label>Perto de mim</span>
            </button>
            <div class="fme__radius" data-fm-radius hidden>
                <input type="range" min="5" max="200" step="5" value="30" data-fm-raio aria-label="Raio de busca em km">
                <output data-fm-raio-label>30 km</output>
            </div>
            <button type="button" class="fme__clear-btn" data-fm-clear aria-label="Limpar filtros">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>
            </button>
        </div>
    </div>

    <div class="fme__status" data-fm-status>Carregando eventos&hellip;</div>

    <div class="fme__view fme__view--agenda" data-fm-agenda></div>

    <div class="fme__view fme__view--mes" data-fm-mes hidden>
        <div class="fme__weekdays" aria-hidden="true">
            <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sab</span>
        </div>
        <div class="fme__grid" data-fm-eventos-grid aria-live="polite" role="grid"></div>
    </div>

    <div class="fme-modal" data-fm-modal hidden>
        <div class="fme-modal__backdrop" data-fm-close></div>
        <article class="fme-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="fme-modal-title">
            <button type="button" class="fme-modal__close" data-fm-close aria-label="Fechar">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>
            </button>
            <div data-fm-modal-content></div>
        </article>
    </div>
</section>
