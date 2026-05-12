<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var array $eventos */
/** @var string $titulo */
/** @var string $tag */
/** @var string $agendaUrl */

if (empty($eventos)) {
    return;
}

$months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
?>

<section class="fme-home" aria-label="<?php echo esc_attr($titulo); ?>">
    <h2 class="fme-home__title">
        <span class="fme-home__title-text"><?php echo esc_html($titulo); ?></span>
        <a class="fme-home__title-tag" href="<?php echo esc_url($agendaUrl); ?>"><?php echo esc_html($tag); ?></a>
    </h2>

    <div class="fme-home__grid">
        <?php foreach ($eventos as $ev) :
            $timestamp = strtotime($ev['data_inicio']);
            if (!$timestamp) continue;

            $day = (int) date('j', $timestamp);
            $monthIdx = (int) date('n', $timestamp) - 1;
            $weekdayIdx = (int) date('w', $timestamp);
            $monthShort = isset($months[$monthIdx]) ? $months[$monthIdx] : '';
            $weekdayShort = isset($weekdays[$weekdayIdx]) ? $weekdays[$weekdayIdx] : '';

            $location = trim(implode(' · ', array_filter([
                $ev['local_nome'],
                ($ev['cidade'] && $ev['estado']) ? $ev['cidade'] . ', ' . $ev['estado'] : ($ev['cidade'] ?: $ev['estado']),
            ])));
            $hora = $ev['hora_inicio'] ? substr($ev['hora_inicio'], 0, 5) : '';
            $color = $ev['cor'] ?: '#d20143';
            $isPast = false;
            $today = current_time('Y-m-d');
            $todayShort = date('Y-m-d');
            $isToday = $ev['data_inicio'] === $today;

            $href = trailingslashit($agendaUrl) . '?evento=' . intval($ev['id']);
        ?>
        <a class="fme-home-card" href="<?php echo esc_url($href); ?>" style="--ev-color: <?php echo esc_attr($color); ?>;">
            <div class="fme-home-card__media">
                <?php if ($ev['thumbnail']) : ?>
                    <img src="<?php echo esc_url($ev['thumbnail']); ?>" alt="" loading="lazy">
                <?php else : ?>
                    <div class="fme-home-card__media-fallback" aria-hidden="true">
                        <span><?php echo esc_html(mb_strtoupper(mb_substr($ev['titulo'], 0, 1))); ?></span>
                    </div>
                <?php endif; ?>

                <div class="fme-home-card__date" aria-hidden="true">
                    <span class="fme-home-card__date-day"><?php echo esc_html($day); ?></span>
                    <span class="fme-home-card__date-mon"><?php echo esc_html($monthShort); ?></span>
                </div>

                <?php if ($isToday) : ?>
                    <span class="fme-home-card__live">Hoje</span>
                <?php elseif ($ev['status'] === 'esgotado') : ?>
                    <span class="fme-home-card__live fme-home-card__live--warn">Esgotado</span>
                <?php elseif ($ev['status'] === 'cancelado') : ?>
                    <span class="fme-home-card__live fme-home-card__live--off">Cancelado</span>
                <?php endif; ?>
            </div>

            <div class="fme-home-card__body">
                <?php if (!empty($ev['categoria'])) : ?>
                    <span class="fme-home-card__cat"><?php echo esc_html($ev['categoria']['nome']); ?></span>
                <?php endif; ?>
                <h3 class="fme-home-card__title"><?php echo esc_html($ev['titulo']); ?></h3>
                <p class="fme-home-card__meta">
                    <span class="fme-home-card__weekday"><?php echo esc_html($weekdayShort); ?></span>
                    <?php if ($hora) : ?>
                        <span class="fme-home-card__time"><?php echo esc_html($hora); ?></span>
                    <?php endif; ?>
                    <?php if ($location) : ?>
                        <span class="fme-home-card__location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="fme-home__footer">
        <a class="fme-home__more" href="<?php echo esc_url($agendaUrl); ?>">
            <span>Ver agenda completa</span>
            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M8.6 16.6 13.2 12 8.6 7.4 10 6l6 6-6 6z"/></svg>
        </a>
    </div>
</section>
