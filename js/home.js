(function () {
    var roots = document.querySelectorAll('[data-fm-home]');
    if (!roots.length) return;

    var MONTH_SHORT = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    var WEEKDAY_SHORT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];

    Array.prototype.forEach.call(roots, initHome);

    function initHome(root) {
        var config = parseConfig(root);
        if (!config) return;

        var el = {
            grid: root.querySelector('[data-fm-home-grid]'),
            status: root.querySelector('[data-fm-home-status]'),
            empty: root.querySelector('[data-fm-home-empty]'),
            filtersForm: root.querySelector('[data-fm-home-filters]'),
            clear: root.querySelector('[data-fm-home-clear]'),
            estado: root.querySelector('[data-fm-filter="estado"]'),
            cidade: root.querySelector('[data-fm-filter="cidade"]'),
            categoria: root.querySelector('[data-fm-filter="categoria"]'),
            periodo: root.querySelector('[data-fm-filter="periodo"]'),
            busca: root.querySelector('[data-fm-filter="busca"]'),
            prev: root.querySelector('[data-fm-home-prev]'),
            next: root.querySelector('[data-fm-home-next]')
        };

        if (!el.grid) return;

        var defaults = config.defaults || {};
        var state = {
            filters: {
                categoria: defaults.categoria || '',
                periodo: defaults.periodo || 'tudo',
                estado: defaults.estado || '',
                cidade: defaults.cidade || '',
                busca: defaults.busca || ''
            },
            localidades: {},
            ufs: {},
            requestId: 0
        };

        // ===== utils =====
        function pad(n) { return String(n).padStart(2, '0'); }

        function clamp(value, min, max) {
            return Math.max(min, Math.min(max, value));
        }

        function todayKey() {
            var d = new Date();
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c];
            });
        }

        function endpoint(path, params) {
            var base = String(config.restUrl || '').replace(/\/$/, '');
            var url = new URL(base + path, window.location.origin);
            Object.keys(params || {}).forEach(function (key) {
                if (params[key] !== '' && params[key] !== null && typeof params[key] !== 'undefined') {
                    url.searchParams.set(key, params[key]);
                }
            });
            return url.toString();
        }

        function jsonFetch(path, params) {
            if (!window.fetch) return Promise.reject(new Error('fetch-unavailable'));
            return fetch(endpoint(path, params), {
                headers: config.restNonce ? { 'X-WP-Nonce': config.restNonce } : {}
            }).then(function (r) {
                if (!r.ok) throw new Error('request-failed');
                return r.json();
            });
        }

        function trailingSlash(value) {
            return String(value || '').replace(/\/?$/, '/');
        }

        // ===== card (espelha FmodiaEventosWPShortcode::renderHomeCard) =====
        function renderCard(ev) {
            var parts = String(ev.data_inicio || '').split('-').map(Number);
            if (parts.length !== 3 || !parts[0]) return '';

            var date = new Date(parts[0], parts[1] - 1, parts[2]);
            var day = date.getDate();
            var monthShort = MONTH_SHORT[date.getMonth()] || '';
            var weekdayShort = WEEKDAY_SHORT[date.getDay()] || '';

            var localParts = [];
            if (ev.local_nome) localParts.push(ev.local_nome);
            if (ev.cidade && ev.estado) localParts.push(ev.cidade + ', ' + ev.estado);
            else if (ev.cidade || ev.estado) localParts.push(ev.cidade || ev.estado);
            var location = localParts.join(' - ');

            var hora = ev.hora_inicio ? String(ev.hora_inicio).slice(0, 5) : '';
            var color = ev.cor || '#d20143';
            var isToday = ev.data_inicio === todayKey();
            var href = trailingSlash(config.agendaUrl) + '?evento=' + (parseInt(ev.id, 10) || 0);

            var media = ev.thumbnail
                ? '<img src="' + escapeHtml(ev.thumbnail) + '" alt="" loading="lazy">'
                : '<div class="fme-home-card__media-fallback" aria-hidden="true"><span>' +
                    escapeHtml(String(ev.titulo || '?').charAt(0).toUpperCase()) + '</span></div>';

            var liveBadge = '';
            if (isToday) {
                liveBadge = '<span class="fme-home-card__live">Hoje</span>';
            } else if (ev.status === 'esgotado') {
                liveBadge = '<span class="fme-home-card__live fme-home-card__live--warn">Esgotado</span>';
            } else if (ev.status === 'cancelado') {
                liveBadge = '<span class="fme-home-card__live fme-home-card__live--off">Cancelado</span>';
            }

            var catName = ev.categoria && ev.categoria.nome ? ev.categoria.nome : '';
            var promo = ev.promocoes_resumo && typeof ev.promocoes_resumo === 'object' ? ev.promocoes_resumo : {};
            var promoOpen = parseInt(promo.abertas, 10) || 0;
            var promoTotal = parseInt(promo.total, 10) || 0;
            var promoMain = promo.principal && typeof promo.principal === 'object' ? promo.principal : null;
            var badges = '<div class="fme-home-card__badges">' +
                (catName ? '<span class="fme-home-card__cat">' + escapeHtml(catName) + '</span>' : '') +
                (ev.destaque ? '<span class="fme-home-card__cat fme-home-card__cat--featured">Destaque</span>' : '') +
                '</div>';
            var promoHtml = '';

            if (promoTotal > 0) {
                promoHtml = '<span class="fme-home-card__promo ' + (promoOpen > 0 ? 'is-open' : 'is-closed') + '">' +
                    '<strong>' + escapeHtml(promoOpen > 0 ? 'Promocao aberta' : 'Promocoes encerradas') + '</strong>';

                if (promoOpen > 0 && promoMain && promoMain.titulo) {
                    promoHtml += '<span>' + escapeHtml(promoMain.titulo) + '</span>';
                } else {
                    promoHtml += '<span>' + escapeHtml(promoTotal + ' promocao' + (promoTotal > 1 ? 'es' : '')) + '</span>';
                }

                promoHtml += '</span>';
            }

            return '<a class="fme-home-card" href="' + escapeHtml(href) + '" style="--ev-color: ' + escapeHtml(color) + ';">' +
                '<div class="fme-home-card__media">' +
                    media +
                    '<div class="fme-home-card__date" aria-hidden="true">' +
                        '<span class="fme-home-card__date-day">' + escapeHtml(day) + '</span>' +
                        '<span class="fme-home-card__date-mon">' + escapeHtml(monthShort) + '</span>' +
                    '</div>' +
                    liveBadge +
                '</div>' +
                '<div class="fme-home-card__body">' +
                    badges +
                    '<h3 class="fme-home-card__title">' + escapeHtml(ev.titulo) + '</h3>' +
                    '<p class="fme-home-card__meta">' +
                        '<span class="fme-home-card__weekday">' + escapeHtml(weekdayShort) + '</span>' +
                        (hora ? '<span class="fme-home-card__time">' + escapeHtml(hora) + '</span>' : '') +
                        (location ? '<span class="fme-home-card__location">' + escapeHtml(location) + '</span>' : '') +
                    '</p>' +
                    promoHtml +
                '</div>' +
            '</a>';
        }

        function renderCards(eventos) {
            el.grid.innerHTML = eventos.map(renderCard).join('');
            var isEmpty = !eventos.length;
            if (el.empty) el.empty.hidden = !isEmpty;
            el.grid.hidden = isEmpty;
            if (config.layout === 'carrossel') {
                el.grid.scrollLeft = 0;
                updateCarouselMetrics();
                updateNav();
            }
        }

        function renderSkeleton() {
            var count = config.layout === 'carrossel'
                ? carouselVisible()
                : Math.max(1, Math.min(8, parseInt(config.limit, 10) || 4));
            var card = '<div class="fme-home-card fme-home-card--skel" aria-hidden="true">' +
                '<div class="fme-home-card__media fme-home-skel"></div>' +
                '<div class="fme-home-card__body">' +
                    '<div class="fme-home-skel fme-home-skel--bar" style="width:60px;height:14px"></div>' +
                    '<div class="fme-home-skel fme-home-skel--bar" style="width:80%;height:18px"></div>' +
                    '<div class="fme-home-skel fme-home-skel--bar" style="width:55%;height:13px"></div>' +
                '</div>' +
            '</div>';
            var html = '';
            for (var i = 0; i < count; i += 1) html += card;
            el.grid.innerHTML = html;
            el.grid.hidden = false;
            if (el.empty) el.empty.hidden = true;
            if (config.layout === 'carrossel') updateCarouselMetrics();
        }

        function setStatus(msg, loading) {
            if (!el.status) return;
            if (!msg) {
                el.status.hidden = true;
                el.status.textContent = '';
                el.status.classList.remove('is-loading');
                return;
            }
            el.status.hidden = false;
            el.status.textContent = msg;
            el.status.classList.toggle('is-loading', !!loading);
        }

        // ===== filtros =====
        function hasActiveFilters() {
            var f = state.filters;
            return Boolean(f.categoria || f.estado || f.cidade || f.busca ||
                (f.periodo && f.periodo !== (defaults.periodo || 'tudo')));
        }

        function syncClearButton() {
            if (el.clear) el.clear.hidden = !hasActiveFilters();
        }

        function fillSelect(select, options, placeholder, selected) {
            if (!select) return;
            select.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>' +
                options.map(function (item) {
                    return '<option value="' + escapeHtml(item.value) + '"' +
                        (item.value === selected ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
                }).join('');
        }

        function updateCities() {
            if (!el.cidade) return;
            var cidades = [];
            if (state.filters.estado && state.localidades[state.filters.estado]) {
                cidades = state.localidades[state.filters.estado].slice();
            } else {
                Object.keys(state.localidades).forEach(function (uf) {
                    (state.localidades[uf] || []).forEach(function (city) {
                        if (cidades.indexOf(city) === -1) cidades.push(city);
                    });
                });
            }
            if (state.filters.cidade && cidades.indexOf(state.filters.cidade) === -1) {
                cidades.unshift(state.filters.cidade);
            }
            cidades.sort(function (a, b) { return String(a).localeCompare(String(b), 'pt-BR'); });
            fillSelect(el.cidade, cidades.map(function (c) {
                return { value: c, label: c };
            }), 'Todas as cidades', state.filters.cidade);
        }

        function loadLocalidades() {
            if (!el.estado && !el.cidade) return Promise.resolve();
            return jsonFetch('/filtros', {}).then(function (data) {
                state.localidades = (data && data.localidades) || {};
                state.ufs = (data && data.ufs) || {};
                var ufKeys = Object.keys(state.localidades);
                if (state.filters.estado && ufKeys.indexOf(state.filters.estado) === -1) {
                    ufKeys.push(state.filters.estado);
                }
                ufKeys.sort();
                fillSelect(el.estado, ufKeys.map(function (uf) {
                    var name = state.ufs[uf] || uf;
                    return { value: uf, label: uf + (name && name !== uf ? ' - ' + name : '') };
                }), 'Todos os estados', state.filters.estado);
                updateCities();
            }).catch(function () { /* mantem selects vazios */ });
        }

        // ===== carregamento de eventos =====
        function loadEventos() {
            var reqId = ++state.requestId;
            renderSkeleton();
            setStatus('Carregando eventos...', true);

            return jsonFetch('/eventos/proximos', {
                categoria: state.filters.categoria,
                periodo: state.filters.periodo,
                estado: state.filters.estado,
                cidade: state.filters.cidade,
                busca: state.filters.busca,
                ordem: config.ordem || 'data',
                limit: config.pool || config.limit || 4
            }).then(function (data) {
                if (reqId !== state.requestId) return;
                var eventos = Array.isArray(data) ? data : [];
                renderCards(eventos);
                setStatus(eventos.length ? '' : null);
                if (!eventos.length && el.empty) el.empty.hidden = false;
            }).catch(function () {
                if (reqId !== state.requestId) return;
                el.grid.innerHTML = '';
                el.grid.hidden = true;
                if (el.empty) el.empty.hidden = false;
                setStatus('Nao foi possivel carregar os eventos.');
            }).then(function () {
                syncClearButton();
            });
        }

        // ===== carrossel =====
        function carouselVisible() {
            var wanted = clamp(parseInt(config.visible || config.limit, 10) || 4, 1, 8);
            var width = window.innerWidth || document.documentElement.clientWidth || 1200;

            if (width <= 480) return 1;
            if (width <= 768) return Math.min(wanted, 2);
            if (width <= 1024) return Math.min(wanted, 3);
            return wanted;
        }

        function carouselGap() {
            var styles = window.getComputedStyle(el.grid);
            return parseFloat(styles.columnGap || styles.gap || '0') || 0;
        }

        function updateCarouselMetrics() {
            if (config.layout !== 'carrossel' || !el.grid) return;
            var visible = carouselVisible();
            var gap = carouselGap();
            var width = el.grid.clientWidth || 0;

            if (!width) return;

            var cardWidth = Math.max(1, (width - (gap * (visible - 1))) / visible);
            el.grid.style.setProperty('--fme-home-visible-active', String(visible));
            el.grid.style.setProperty('--fme-home-card-width', cardWidth + 'px');
        }

        function scrollAmount() {
            var firstCard = el.grid.querySelector('.fme-home-card');
            if (firstCard) {
                var gap = carouselGap();
                return (firstCard.getBoundingClientRect().width + gap) *
                    carouselVisible();
            }
            return el.grid.clientWidth * 0.9;
        }

        function updateNav() {
            if (!el.prev || !el.next) return;
            updateCarouselMetrics();
            var maxScroll = el.grid.scrollWidth - el.grid.clientWidth - 1;
            el.prev.disabled = el.grid.scrollLeft <= 0;
            el.next.disabled = maxScroll <= 0 || el.grid.scrollLeft >= maxScroll;
        }

        // ===== eventos de UI =====
        if (el.filtersForm) {
            el.filtersForm.addEventListener('submit', function (e) { e.preventDefault(); });

            el.filtersForm.addEventListener('change', function (e) {
                var target = e.target;
                if (target === el.categoria) state.filters.categoria = target.value;
                else if (target === el.periodo) state.filters.periodo = target.value;
                else if (target === el.estado) {
                    state.filters.estado = target.value;
                    state.filters.cidade = '';
                    updateCities();
                } else if (target === el.cidade) state.filters.cidade = target.value;
                else return;
                loadEventos();
            });

            if (el.busca) {
                var debounce;
                el.busca.addEventListener('input', function () {
                    window.clearTimeout(debounce);
                    debounce = window.setTimeout(function () {
                        state.filters.busca = el.busca.value.trim();
                        loadEventos();
                    }, 350);
                });
            }

            if (el.clear) {
                el.clear.addEventListener('click', function () {
                    state.filters = {
                        categoria: defaults.categoria || '',
                        periodo: defaults.periodo || 'tudo',
                        estado: defaults.estado || '',
                        cidade: defaults.cidade || '',
                        busca: defaults.busca || ''
                    };
                    if (el.categoria) el.categoria.value = state.filters.categoria;
                    if (el.periodo) el.periodo.value = state.filters.periodo;
                    if (el.estado) el.estado.value = state.filters.estado;
                    if (el.busca) el.busca.value = state.filters.busca;
                    updateCities();
                    loadEventos();
                });
            }
        }

        if (config.layout === 'carrossel' && el.prev && el.next) {
            el.prev.addEventListener('click', function () {
                el.grid.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
                window.setTimeout(updateNav, 260);
            });
            el.next.addEventListener('click', function () {
                el.grid.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
                window.setTimeout(updateNav, 260);
            });
            var navRaf;
            el.grid.addEventListener('scroll', function () {
                if (navRaf) return;
                navRaf = window.requestAnimationFrame(function () {
                    navRaf = 0;
                    updateNav();
                });
            });
            window.addEventListener('resize', function () {
                updateCarouselMetrics();
                updateNav();
            });
        }

        // ===== boot =====
        // Cards iniciais ja vem renderizados pelo PHP; so buscamos de novo
        // quando precisamos das localidades ou o usuario interage.
        var boot = config.filtros && config.filtros.local ? loadLocalidades() : Promise.resolve();
        boot.then(function () {
            syncClearButton();
            if (config.layout === 'carrossel') {
                updateCarouselMetrics();
                updateNav();
            }
        });
    }

    function parseConfig(root) {
        var raw = root.getAttribute('data-fm-home-config');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }
})();
