<?php if (!defined('ABSPATH')) { exit; } ?>

<div class="fm-eventos-admin-grid">
    <label class="fm-eventos-admin-full">
        <span>Local cadastrado</span>
        <select name="fm_evento_local_id" data-fm-local-select>
            <option value="">Nenhum local cadastrado</option>
            <?php foreach ($locais as $local) : ?>
                <option
                    value="<?php echo esc_attr($local['id']); ?>"
                    <?php selected($selectedLocal, $local['id']); ?>
                    data-name="<?php echo esc_attr($local['name']); ?>"
                    data-endereco="<?php echo esc_attr($local['endereco']); ?>"
                    data-cidade="<?php echo esc_attr($local['cidade']); ?>"
                    data-estado="<?php echo esc_attr($local['estado']); ?>"
                    data-cep="<?php echo esc_attr($local['cep']); ?>"
                    data-lat="<?php echo esc_attr($local['lat']); ?>"
                    data-lng="<?php echo esc_attr($local['lng']); ?>"
                ><?php echo esc_html($local['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <small>Cadastre locais em Eventos > Locais. Ao selecionar, os dados abaixo sao preenchidos automaticamente.</small>
    </label>
    <label>
        <span>Nome do local</span>
        <input type="text" name="fm_evento_local_nome" value="<?php echo esc_attr($meta['local_nome']); ?>" placeholder="Ex: Vivo Rio" data-fm-local-name>
    </label>
    <label>
        <span>Endereco</span>
        <input type="text" name="fm_evento_endereco" value="<?php echo esc_attr($meta['endereco']); ?>" placeholder="Rua, numero" data-fm-endereco>
    </label>
    <label>
        <span>Cidade</span>
        <input type="text" name="fm_evento_cidade" value="<?php echo esc_attr($meta['cidade']); ?>" data-fm-cidade-field>
    </label>
    <label>
        <span>UF</span>
        <select name="fm_evento_estado" data-fm-estado-field>
            <option value="">Selecione</option>
            <?php foreach ($ufs as $uf => $name) : ?>
                <option value="<?php echo esc_attr($uf); ?>" <?php selected($meta['estado'], $uf); ?>><?php echo esc_html($uf . ' - ' . $name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>CEP</span>
        <input type="text" name="fm_evento_cep" value="<?php echo esc_attr($meta['cep']); ?>" data-fm-cep>
    </label>
    <label>
        <span>Latitude</span>
        <input type="number" step="any" name="fm_evento_lat" value="<?php echo esc_attr($meta['lat']); ?>">
    </label>
    <label>
        <span>Longitude</span>
        <input type="number" step="any" name="fm_evento_lng" value="<?php echo esc_attr($meta['lng']); ?>">
    </label>
    <div class="fm-eventos-admin-full">
        <button type="button" class="button" data-fm-geocode>Buscar coordenadas</button>
        <span class="fm-eventos-admin-help" data-fm-cep-status data-fm-geocode-status></span>
    </div>
</div>
