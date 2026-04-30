<?php if (!defined('ABSPATH')) { exit; } ?>

<div class="fm-eventos-admin-grid">
    <label>
        <span>Data inicio *</span>
        <input type="date" name="fm_evento_data_inicio" value="<?php echo esc_attr($meta['data_inicio']); ?>" required>
    </label>
    <label>
        <span>Data fim</span>
        <input type="date" name="fm_evento_data_fim" value="<?php echo esc_attr($meta['data_fim']); ?>">
    </label>
    <label>
        <span>Hora inicio</span>
        <input type="time" name="fm_evento_hora_inicio" value="<?php echo esc_attr($meta['hora_inicio']); ?>">
    </label>
    <label>
        <span>Hora fim</span>
        <input type="time" name="fm_evento_hora_fim" value="<?php echo esc_attr($meta['hora_fim']); ?>">
    </label>
    <label>
        <span>Preco minimo</span>
        <input type="number" step="0.01" min="0" name="fm_evento_preco_min" value="<?php echo esc_attr($meta['preco_min']); ?>">
    </label>
    <label>
        <span>Preco maximo</span>
        <input type="number" step="0.01" min="0" name="fm_evento_preco_max" value="<?php echo esc_attr($meta['preco_max']); ?>">
    </label>
    <label>
        <span>Inicio das vendas</span>
        <input type="datetime-local" name="fm_evento_data_inicio_venda" value="<?php echo esc_attr($meta['data_inicio_venda']); ?>">
    </label>
    <label>
        <span>Link de ingresso</span>
        <input type="url" name="fm_evento_link_ingresso" value="<?php echo esc_url($meta['link_ingresso']); ?>" placeholder="https://">
    </label>
    <label>
        <span>Classificacao</span>
        <select name="fm_evento_classificacao">
            <?php foreach ($classificacoes as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['classificacao'], $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        <span>Status</span>
        <select name="fm_evento_status">
            <?php foreach ($statuses as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($meta['status'], $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="fm-eventos-admin-full">
        <span>Lineup</span>
        <textarea name="fm_evento_lineup" rows="5" placeholder="Uma atracao por linha"><?php echo esc_textarea($meta['lineup']); ?></textarea>
    </label>
</div>
