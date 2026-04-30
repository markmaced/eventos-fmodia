<?php if (!defined('ABSPATH')) { exit; } ?>

<?php if (isset($term)) : ?>
<tr class="form-field">
    <th scope="row"><label for="fm_evento_categoria_cor">Cor</label></th>
    <td>
        <input type="color" id="fm_evento_categoria_cor" name="fm_evento_categoria_cor" value="<?php echo esc_attr($color); ?>">
        <p class="description">Cor usada nas pilulas do calendario.</p>
    </td>
</tr>
<?php else : ?>
<div class="form-field">
    <label for="fm_evento_categoria_cor">Cor</label>
    <input type="color" id="fm_evento_categoria_cor" name="fm_evento_categoria_cor" value="<?php echo esc_attr($color); ?>">
    <p>Cor usada nas pilulas do calendario.</p>
</div>
<?php endif; ?>
