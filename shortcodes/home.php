<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var array  $eventos    Eventos ja formatados. */
/** @var string $titulo     Titulo da secao. */
/** @var string $tag        Tag/botao do cabecalho. */
/** @var string $agendaUrl  URL da agenda completa. */
/** @var string $layout     grade | lista | carrossel */
/** @var string $card       padrao | compacto | horizontal */
/** @var array  $filtros    Flags: enabled, categoria, data, local, busca. */
/** @var array  $periodos   Mapa value => label das janelas de tempo. */
/** @var array  $categorias Lista de categorias [id, nome, slug, cor]. */
/** @var array  $config     Config JSON consumida pelo js/home.js. */
/** @var int    $limit      Eventos visiveis na grade/lista. */
/** @var int    $visible    Cards visiveis por vez no carrossel. */

$hasEventos = !empty($eventos);

// Sem eventos e sem filtros: nada a mostrar (comportamento original).
if (!$hasEventos && empty($filtros['enabled'])) {
    return;
}

$sectionClasses = 'fme-home fme-home--layout-' . $layout . ' fme-home--card-' . $card;
$isCarrossel = $layout === 'carrossel';
?>

<section
    class="<?php echo esc_attr($sectionClasses); ?>"
    data-fm-home
    data-fm-home-config="<?php echo esc_attr(wp_json_encode($config)); ?>"
    aria-label="<?php echo esc_attr($titulo); ?>"
>
    <h2 class="fme-home__title">
        <span class="fme-home__title-text"><?php echo esc_html($titulo); ?></span>
        <a class="fme-home__title-tag" href="<?php echo esc_url($agendaUrl); ?>"><?php echo esc_html($tag); ?></a>
    </h2>

    <?php if (!empty($filtros['enabled'])) : ?>
    <form class="fme-home__filters" data-fm-home-filters role="search" aria-label="Filtrar eventos">
        <?php if (!empty($filtros['categoria'])) : ?>
        <div class="fme-home__filter">
            <select data-fm-filter="categoria" aria-label="Categoria">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $cat) : ?>
                <option value="<?php echo esc_attr($cat['slug']); ?>" <?php selected($config['defaults']['categoria'], $cat['slug']); ?>>
                    <?php echo esc_html($cat['nome']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (!empty($filtros['data'])) : ?>
        <div class="fme-home__filter">
            <select data-fm-filter="periodo" aria-label="Periodo">
                <?php foreach ($periodos as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($config['defaults']['periodo'], $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (!empty($filtros['local'])) : ?>
        <div class="fme-home__filter">
            <select data-fm-filter="estado" aria-label="Estado">
                <option value="">Todos os estados</option>
            </select>
        </div>
        <div class="fme-home__filter">
            <select data-fm-filter="cidade" aria-label="Cidade">
                <option value="">Todas as cidades</option>
            </select>
        </div>
        <?php endif; ?>

        <?php if (!empty($filtros['busca'])) : ?>
        <div class="fme-home__filter fme-home__filter--search">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/></svg>
            <input type="search" data-fm-filter="busca" placeholder="Buscar evento" aria-label="Buscar evento">
        </div>
        <?php endif; ?>

        <button type="button" class="fme-home__filter-clear" data-fm-home-clear hidden>
            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z"/></svg>
            <span>Limpar</span>
        </button>
    </form>
    <?php endif; ?>

    <div class="fme-home__status" data-fm-home-status hidden></div>

    <div class="fme-home__viewport">
        <?php if ($isCarrossel) : ?>
        <button type="button" class="fme-home__nav fme-home__nav--prev" data-fm-home-prev aria-label="Eventos anteriores">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z"/></svg>
        </button>
        <?php endif; ?>

        <div class="fme-home__grid" data-fm-home-grid style="--fme-home-visible: <?php echo intval($visible); ?>;">
            <?php
            if ($hasEventos) {
                foreach ($eventos as $ev) {
                    // renderHomeCard ja retorna HTML escapado.
                    echo FmodiaEventosWPShortcode::renderHomeCard($ev, $agendaUrl);
                }
            }
            ?>
        </div>

        <?php if ($isCarrossel) : ?>
        <button type="button" class="fme-home__nav fme-home__nav--next" data-fm-home-next aria-label="Proximos eventos">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M8.6 7.4 10 6l6 6-6 6-1.4-1.4L13.2 12z"/></svg>
        </button>
        <?php endif; ?>
    </div>

    <p class="fme-home__empty" data-fm-home-empty <?php echo $hasEventos ? 'hidden' : ''; ?>>
        Nenhum evento encontrado para os filtros selecionados.
    </p>

    <div class="fme-home__footer">
        <a class="fme-home__more" href="<?php echo esc_url($agendaUrl); ?>">
            <span>Ver agenda completa</span>
            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M8.6 16.6 13.2 12 8.6 7.4 10 6l6 6-6 6z"/></svg>
        </a>
    </div>
</section>
