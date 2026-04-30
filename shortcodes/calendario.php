<?php if (!defined('ABSPATH')) { exit; } ?>

<section class="fm-eventos" data-fm-eventos>
    <header class="fm-eventos__header">
        <div>
            <p class="fm-eventos__eyebrow">Agenda FM O Dia</p>
            <h2 data-fm-month-label>Eventos</h2>
        </div>
        <div class="fm-eventos__nav" aria-label="Navegacao do calendario">
            <button type="button" data-fm-prev aria-label="Mes anterior">&lsaquo;</button>
            <button type="button" data-fm-today>Hoje</button>
            <button type="button" data-fm-next aria-label="Proximo mes">&rsaquo;</button>
        </div>
    </header>

    <div class="fm-eventos__filters">
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

    <div class="fm-eventos__weekdays" aria-hidden="true">
        <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sab</span>
    </div>
    <div class="fm-eventos__grid" data-fm-eventos-grid aria-live="polite"></div>
    <p class="fm-eventos__status" data-fm-status></p>

    <div class="fm-eventos-modal" data-fm-modal hidden>
        <div class="fm-eventos-modal__backdrop" data-fm-close></div>
        <article class="fm-eventos-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="fm-eventos-modal-title">
            <button type="button" class="fm-eventos-modal__close" data-fm-close aria-label="Fechar">×</button>
            <div data-fm-modal-content></div>
        </article>
    </div>
</section>
