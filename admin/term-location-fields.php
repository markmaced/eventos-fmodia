<?php if (!defined('ABSPATH')) { exit; } ?>

<?php
$fieldsHtml = function () use ($meta, $ufs) {
    ?>
    <label>
        <span>CEP</span>
        <input type="text" name="fm_evento_local_cep" value="<?php echo esc_attr($meta['cep']); ?>" data-fm-cep>
    </label>
    <label>
        <span>Endereco</span>
        <input type="text" name="fm_evento_local_endereco" value="<?php echo esc_attr($meta['endereco']); ?>" data-fm-endereco>
    </label>
    <label>
        <span>Cidade</span>
        <input type="text" name="fm_evento_local_cidade" value="<?php echo esc_attr($meta['cidade']); ?>" data-fm-cidade-field>
    </label>
    <label>
        <span>UF</span>
        <select name="fm_evento_local_estado" data-fm-estado-field>
            <option value="">Selecione</option>
            <?php foreach ($ufs as $uf => $name) : ?>
                <option value="<?php echo esc_attr($uf); ?>" <?php selected($meta['estado'], $uf); ?>><?php echo esc_html($uf . ' - ' . $name); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>Latitude</span>
        <input type="number" step="any" name="fm_evento_local_lat" value="<?php echo esc_attr($meta['lat']); ?>">
    </label>
    <label>
        <span>Longitude</span>
        <input type="number" step="any" name="fm_evento_local_lng" value="<?php echo esc_attr($meta['lng']); ?>">
    </label>
    <div class="fm-eventos-admin-full">
        <button type="button" class="button" data-fm-geocode>Buscar coordenadas</button>
        <span class="fm-eventos-admin-help" data-fm-cep-status data-fm-geocode-status></span>
    </div>
    <?php
};
?>

<?php if ($isEdit) : ?>
<tr class="form-field">
    <th scope="row">Dados do local</th>
    <td><div class="fm-eventos-admin-grid"><?php $fieldsHtml(); ?></div></td>
</tr>
<?php else : ?>
<div class="form-field">
    <label>Dados do local</label>
    <div class="fm-eventos-admin-grid"><?php $fieldsHtml(); ?></div>
</div>
<?php endif; ?>
