<?php
/**
 * View da pagina de configuracoes (Eventos > Configuracoes).
 *
 * @var array $settings   Configuracoes atuais mescladas com os padroes.
 * @var array $layouts    Mapa slug => label dos layouts.
 * @var array $cardStyles Mapa slug => label dos estilos de card.
 * @var array $orderOptions Mapa slug => label das ordenacoes.
 */

if (!defined('ABSPATH')) {
    exit;
}

$opt = FmodiaEventosWPSettings::OPTION;
?>
<div class="wrap fme-settings">
    <h1>Eventos &mdash; Configuracoes</h1>
    <p class="fme-settings__intro">
        Controle como o widget de eventos aparece na home. Use o shortcode
        <code>[fmodia_eventos_home]</code> na pagina inicial &mdash; ele segue as opcoes abaixo.
        Atributos passados direto no shortcode (ex.: <code>limit="12" visiveis="4"</code>) continuam tendo prioridade.
    </p>

    <form action="options.php" method="post" class="fme-settings__form">
        <?php settings_fields(FmodiaEventosWPSettings::GROUP); ?>

        <h2 class="title">Conteudo</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="fme-titulo">Titulo da secao</label></th>
                    <td>
                        <input type="text" id="fme-titulo" class="regular-text"
                               name="<?php echo esc_attr($opt); ?>[home_titulo]"
                               value="<?php echo esc_attr($settings['home_titulo']); ?>">
                        <p class="description">Texto exibido no cabecalho do widget.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fme-tag">Tag / botao do cabecalho</label></th>
                    <td>
                        <input type="text" id="fme-tag" class="regular-text"
                               name="<?php echo esc_attr($opt); ?>[home_tag]"
                               value="<?php echo esc_attr($settings['home_tag']); ?>">
                        <p class="description">Selo no canto direito do titulo (leva para a agenda).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fme-agenda-url">URL da agenda completa</label></th>
                    <td>
                        <input type="text" id="fme-agenda-url" class="regular-text"
                               name="<?php echo esc_attr($opt); ?>[home_agenda_url]"
                               value="<?php echo esc_attr($settings['home_agenda_url']); ?>">
                        <p class="description">Destino do botao "Ver agenda completa" e dos cards.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fme-order">Ordenacao dos eventos</label></th>
                    <td>
                        <select id="fme-order" name="<?php echo esc_attr($opt); ?>[home_order]">
                            <?php foreach ($orderOptions as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['home_order'], $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Define a ordem da home e do carrossel.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fme-limit">Quantidade de eventos</label></th>
                    <td>
                        <input type="number" id="fme-limit" min="1" max="48" step="1"
                               name="<?php echo esc_attr($opt); ?>[home_limit]"
                               value="<?php echo esc_attr($settings['home_limit']); ?>">
                        <p class="description">
                            Total de eventos carregados no widget. No carrossel, este e o total disponivel para navegar.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="title">Aparencia</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Layout</th>
                    <td>
                        <fieldset class="fme-settings__cards">
                            <?php foreach ($layouts as $value => $label) : ?>
                                <label class="fme-settings__card">
                                    <input type="radio"
                                           name="<?php echo esc_attr($opt); ?>[home_layout]"
                                           value="<?php echo esc_attr($value); ?>"
                                           <?php checked($settings['home_layout'], $value); ?>>
                                    <span class="fme-settings__card-box" data-fme-preview="layout-<?php echo esc_attr($value); ?>">
                                        <span class="fme-settings__card-label"><?php echo esc_html($label); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">Como os eventos se organizam: grade, lista vertical ou carrossel deslizavel.</p>
                    </td>
                </tr>
                <tr class="fme-settings__carousel-row">
                    <th scope="row"><label for="fme-carrossel-visible">Cards visiveis no carrossel</label></th>
                    <td>
                        <input type="number" id="fme-carrossel-visible" min="1" max="8" step="1"
                               name="<?php echo esc_attr($opt); ?>[home_carrossel_visible]"
                               value="<?php echo esc_attr($settings['home_carrossel_visible']); ?>">
                        <p class="description">Quantos cards devem aparecer por vez na tela quando o layout for carrossel.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Estilo do card</th>
                    <td>
                        <fieldset class="fme-settings__cards">
                            <?php foreach ($cardStyles as $value => $label) : ?>
                                <label class="fme-settings__card">
                                    <input type="radio"
                                           name="<?php echo esc_attr($opt); ?>[home_card]"
                                           value="<?php echo esc_attr($value); ?>"
                                           <?php checked($settings['home_card'], $value); ?>>
                                    <span class="fme-settings__card-box" data-fme-preview="card-<?php echo esc_attr($value); ?>">
                                        <span class="fme-settings__card-label"><?php echo esc_html($label); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">Padrao (imagem grande), compacto (mais enxuto) ou horizontal (imagem ao lado).</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="title">Filtros</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">Barra de filtros</th>
                    <td>
                        <label>
                            <input type="checkbox" id="fme-filtros"
                                   name="<?php echo esc_attr($opt); ?>[home_filtros]" value="1"
                                   <?php checked($settings['home_filtros'], 1); ?>>
                            Exibir filtros no widget da home
                        </label>
                        <p class="description">A filtragem acontece na hora, sem recarregar a pagina.</p>
                    </td>
                </tr>
                <tr class="fme-settings__filtro-row">
                    <th scope="row">Filtros disponiveis</th>
                    <td>
                        <fieldset>
                            <label class="fme-settings__check">
                                <input type="checkbox" name="<?php echo esc_attr($opt); ?>[home_filtro_categoria]" value="1"
                                       <?php checked($settings['home_filtro_categoria'], 1); ?>>
                                Categoria
                            </label>
                            <label class="fme-settings__check">
                                <input type="checkbox" name="<?php echo esc_attr($opt); ?>[home_filtro_data]" value="1"
                                       <?php checked($settings['home_filtro_data'], 1); ?>>
                                Data / periodo
                            </label>
                            <label class="fme-settings__check">
                                <input type="checkbox" name="<?php echo esc_attr($opt); ?>[home_filtro_local]" value="1"
                                       <?php checked($settings['home_filtro_local'], 1); ?>>
                                Local (estado / cidade)
                            </label>
                            <label class="fme-settings__check">
                                <input type="checkbox" name="<?php echo esc_attr($opt); ?>[home_filtro_busca]" value="1"
                                       <?php checked($settings['home_filtro_busca'], 1); ?>>
                                Busca por texto
                            </label>
                        </fieldset>
                        <p class="description">Escolha quais campos de filtro aparecem para o visitante.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('Salvar configuracoes'); ?>
    </form>
</div>
