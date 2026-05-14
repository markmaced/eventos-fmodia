(function () {
    var roots = document.querySelectorAll('[data-fm-eventos]');
    if (!roots.length) return;
    Array.prototype.forEach.call(roots, initCalendar);

    var WEEKDAY_FULL = ['Domingo', 'Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];
    var WEEKDAY_SHORT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
    var MONTH_SHORT = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    function initCalendar(root) {
        var config = window.FMODIA_EVENTOS || fallbackConfig();
        var defaults = config.defaults || {};

        var state = {
            currentMonth: parseMonth(config.defaultMonth),
            view: 'agenda',
            loading: true,
            filters: {
                estado: defaults.estado || '',
                cidade: defaults.cidade || '',
                categoria: defaults.categoria || '',
                lat: '',
                lng: '',
                raio: 30
            },
            filterOptions: {
                ufs: config.ufs || {},
                categorias: config.categorias || []
            },
            localidades: {},
            eventos: [],
            expandedDays: {}
        };

        var el = {
            monthLabel: root.querySelector('[data-fm-month-label]'),
            agenda: root.querySelector('[data-fm-agenda]'),
            mesView: root.querySelector('[data-fm-mes]'),
            grid: root.querySelector('[data-fm-eventos-grid]'),
            status: root.querySelector('[data-fm-status]'),
            estado: root.querySelector('[data-fm-estado]'),
            cidade: root.querySelector('[data-fm-cidade]'),
            categoria: root.querySelector('[data-fm-categoria]'),
            clear: root.querySelector('[data-fm-clear]'),
            geo: root.querySelector('[data-fm-geo]'),
            geoLabel: root.querySelector('[data-fm-geo-label]'),
            radius: root.querySelector('[data-fm-radius]'),
            raio: root.querySelector('[data-fm-raio]'),
            raioLabel: root.querySelector('[data-fm-raio-label]'),
            modal: root.querySelector('[data-fm-modal]'),
            modalContent: root.querySelector('[data-fm-modal-content]'),
            viewButtons: root.querySelectorAll('[data-fm-view-btn]')
        };

        if (!el.monthLabel || (!el.agenda && !el.grid)) return;

        // ===== utils =====
        function parseMonth(value) {
            var parts = String(value || '').split('-');
            var now = new Date();
            var year = Number(parts[0]) || now.getFullYear();
            var month = Number(parts[1]) || (now.getMonth() + 1);
            return new Date(year, month - 1, 1);
        }

        function monthKey(date) {
            return date.getFullYear() + '-' + pad(date.getMonth() + 1);
        }

        function dateKey(date) {
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        function pad(n) { return String(n).padStart(2, '0'); }

        function dateFromKey(value) {
            var parts = String(value || '').split('-').map(Number);
            if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return null;
            return new Date(parts[0], parts[1] - 1, parts[2]);
        }

        function eventEndDate(evento) {
            var date = dateFromKey(evento.data_fim || evento.data_inicio);
            if (!date) return null;

            var time = String(evento.hora_fim || '').match(/^(\d{1,2}):(\d{2})/);
            if (time) date.setHours(Number(time[1]), Number(time[2]), 0, 0);
            else date.setHours(23, 59, 59, 999);

            return date;
        }

        function eventHasEnded(evento, now) {
            var end = eventEndDate(evento);
            return Boolean(end && end.getTime() < (now || new Date()).getTime());
        }

        function formatDate(value) {
            var date = typeof value === 'string' ? dateFromKey(value) : value;
            if (!date) return '';
            return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(date);
        }

        function formatMonth(value) {
            return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(value);
        }

        function formatMoney(value) {
            if (value === '' || value === null || typeof value === 'undefined') return '';
            return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (c) {
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

        function normalizeColor(value) {
            var color = String(value || '').trim();
            if (/^#[0-9a-f]{3}$/i.test(color)) {
                return '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
            }
            if (/^#[0-9a-f]{6}$/i.test(color)) return color;
            return '';
        }

        function readableTextColor(hex) {
            var color = normalizeColor(hex);
            if (!color) return '#fff';
            var r = parseInt(color.slice(1, 3), 16);
            var g = parseInt(color.slice(3, 5), 16);
            var b = parseInt(color.slice(5, 7), 16);
            return (r * 299 + g * 587 + b * 114) / 1000 > 170 ? '#0a0a0a' : '#fff';
        }

        function statusLabel(value) {
            return ({ confirmado: 'Confirmado', esgotado: 'Esgotado', cancelado: 'Cancelado', adiado: 'Adiado' })[value] || String(value || '');
        }

        function classificacaoLabel(value) {
            return ({ livre: 'Livre', 10: '10 anos', 12: '12 anos', 14: '14 anos', 16: '16 anos', 18: '18 anos' })[value] || String(value || '');
        }

        function eventUrl(id) {
            var url = new URL(window.location.href);
            url.searchParams.set('evento', String(parseInt(id, 10) || 0));
            url.hash = '';
            return url.toString();
        }

        function setEventUrl(id) {
            if (!window.history || !window.history.replaceState) return;
            try {
                var url = new URL(window.location.href);
                var cleanId = String(parseInt(id, 10) || 0);
                if (url.searchParams.get('evento') === cleanId) return;
                url.searchParams.set('evento', cleanId);
                window.history.replaceState({}, '', url.toString());
            } catch (e) { /* noop */ }
        }

        function clearEventUrl() {
            if (!window.history || !window.history.replaceState) return;
            try {
                var url = new URL(window.location.href);
                if (!url.searchParams.has('evento')) return;
                url.searchParams.delete('evento');
                window.history.replaceState({}, '', url.toString());
            } catch (e) { /* noop */ }
        }

        function parseDateTimeValue(value) {
            var raw = String(value || '').trim();
            if (!raw) return null;

            var parts = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
            if (parts) {
                return new Date(
                    Number(parts[1]),
                    Number(parts[2]) - 1,
                    Number(parts[3]),
                    Number(parts[4] || 0),
                    Number(parts[5] || 0),
                    Number(parts[6] || 0)
                );
            }

            var date = new Date(raw);
            return Number.isNaN(date.getTime()) ? null : date;
        }

        function formatDateTimeLabel(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
            return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(date);
        }

        function hasExplicitPrice(ev) {
            return (ev.preco_min !== '' && ev.preco_min !== null && typeof ev.preco_min !== 'undefined') ||
                (ev.preco_max !== '' && ev.preco_max !== null && typeof ev.preco_max !== 'undefined');
        }

        function isFreeEvent(ev) {
            if (!hasExplicitPrice(ev)) return false;
            var min = ev.preco_min === '' || ev.preco_min === null || typeof ev.preco_min === 'undefined' ? 0 : Number(ev.preco_min);
            var max = ev.preco_max === '' || ev.preco_max === null || typeof ev.preco_max === 'undefined' ? 0 : Number(ev.preco_max);
            return min <= 0 && max <= 0;
        }

        function priceLabel(ev) {
            if (!hasExplicitPrice(ev)) return '';
            if (isFreeEvent(ev)) return 'Gratuito';

            var min = formatMoney(ev.preco_min);
            var max = formatMoney(ev.preco_max);
            if (min && max && min !== max) return min + ' a ' + max;
            return min || max;
        }

        // ===== filtros =====
        function fillSelect(select, options, placeholder, selected) {
            if (!select) return;
            select.innerHTML = '<option value="">' + placeholder + '</option>' + options.map(function (item) {
                return '<option value="' + escapeHtml(item.value) + '"' + (item.value === selected ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
            }).join('');
        }

        function availableUfKeys() {
            var keys = Object.keys(state.localidades || {});
            if (!keys.length) keys = Object.keys(state.filterOptions.ufs || config.ufs || {});
            if (state.filters.estado && keys.indexOf(state.filters.estado) === -1) keys.unshift(state.filters.estado);
            return keys.sort();
        }

        function setupFilters() {
            var ufOptions = availableUfKeys().map(function (uf) {
                var name = state.filterOptions.ufs[uf] || (config.ufs || {})[uf] || uf;
                return { value: uf, label: uf + (name && name !== uf ? ' - ' + name : '') };
            });
            fillSelect(el.estado, ufOptions, 'Todos os estados', state.filters.estado);

            var catOptions = (state.filterOptions.categorias || []).map(function (cat) {
                return { value: cat.slug, label: cat.nome };
            });
            fillSelect(el.categoria, catOptions, 'Todas as categorias', state.filters.categoria);
            updateCities();
            syncGeoUi();
        }

        function updateCities() {
            var cidades = [];
            if (state.filters.estado && state.localidades[state.filters.estado]) {
                cidades = state.localidades[state.filters.estado].slice();
            } else {
                Object.keys(state.localidades || {}).forEach(function (uf) {
                    (state.localidades[uf] || []).forEach(function (city) {
                        if (cidades.indexOf(city) === -1) cidades.push(city);
                    });
                });
            }
            if (state.filters.cidade && cidades.indexOf(state.filters.cidade) === -1) cidades.unshift(state.filters.cidade);
            cidades.sort(function (a, b) { return String(a).localeCompare(String(b), 'pt-BR'); });
            fillSelect(el.cidade, cidades.map(function (c) { return { value: c, label: c }; }), 'Todas as cidades', state.filters.cidade);
        }

        function loadFiltros() {
            return jsonFetch('/filtros').then(function (data) {
                state.localidades = data.localidades || {};
                state.filterOptions.ufs = data.ufs || state.filterOptions.ufs;
                state.filterOptions.categorias = Array.isArray(data.categorias) ? data.categorias : state.filterOptions.categorias;
                setupFilters();
            }).catch(function () {
                return jsonFetch('/localidades').then(function (data) {
                    state.localidades = data || {};
                    setupFilters();
                });
            });
        }

        // ===== eventos =====
        function loadEventos() {
            if (canUseInitialEvents()) {
                useInitialEvents();
                return Promise.resolve();
            }

            state.eventos = [];
            state.loading = true;
            renderActiveView();
            setStatus('Carregando eventos...', true);

            return jsonFetch('/eventos', {
                mes: monthKey(state.currentMonth),
                estado: state.filters.estado,
                cidade: state.filters.cidade,
                categoria: state.filters.categoria,
                lat: state.filters.lat,
                lng: state.filters.lng,
                raio: state.filters.raio
            }).then(function (data) {
                state.eventos = Array.isArray(data) ? data : [];
                state.expandedDays = {};
                state.loading = false;
                renderActiveView();
                setStatus(statusMessage());
            }).catch(function () {
                state.eventos = [];
                state.loading = false;
                renderActiveView();
                setStatus('Nao foi possivel carregar os eventos.');
            });
        }

        function setStatus(msg, loading) {
            if (!el.status) return;
            el.status.classList.toggle('is-loading', !!loading);
            el.status.innerHTML = (loading ? '<span class="fme__status-spinner" aria-hidden="true"></span>' : '') +
                '<span class="fme__status-text">' + escapeHtml(msg || '') + '</span>';
        }

        function statusMessage() {
            if (!state.eventos.length) {
                if (hasGeo()) {
                    return 'Nenhum evento em ate ' + state.filters.raio + ' km. Tente aumentar o raio no filtro.';
                }
                return 'Nenhum evento para os filtros selecionados.';
            }
            var count = state.eventos.length + ' evento' + (state.eventos.length > 1 ? 's' : '');
            if (hasGeo()) return count + ' em ate ' + state.filters.raio + ' km da sua localizacao.';
            return count + ' em ' + formatMonth(state.currentMonth) + '.';
        }

        function hasGeo() {
            return state.filters.lat !== '' && state.filters.lng !== '';
        }

        function canUseInitialEvents() {
            var initialMonth = config.initialMonth || config.defaultMonth || '';
            return Array.isArray(config.initialEvents) &&
                config.initialEvents.length &&
                monthKey(state.currentMonth) === initialMonth &&
                !state.filters.estado &&
                !state.filters.cidade &&
                !state.filters.categoria &&
                !hasGeo();
        }

        function useInitialEvents() {
            state.eventos = config.initialEvents.slice();
            state.expandedDays = {};
            state.loading = false;
            renderActiveView();
            setStatus(statusMessage());
        }

        function syncGeoUi() {
            if (el.raioLabel && el.raio) el.raioLabel.textContent = el.raio.value + ' km';
            if (el.radius) el.radius.hidden = !hasGeo();
            if (!el.geo) return;
            el.geo.classList.toggle('is-active', hasGeo());
            el.geo.setAttribute('aria-pressed', hasGeo() ? 'true' : 'false');
            if (el.geoLabel) {
                el.geoLabel.textContent = hasGeo() ? 'Perto: ' + state.filters.raio + ' km' : 'Perto de mim';
            }
        }

        function eventOccursOn(evento, key) {
            var start = evento.data_inicio || '';
            var end = evento.data_fim || start;
            return Boolean(start && start <= key && end >= key);
        }

        function eventsForDate(key) {
            return state.eventos
                .filter(function (e) { return eventOccursOn(e, key); })
                .sort(function (a, b) {
                    var t = String(a.hora_inicio || '').localeCompare(String(b.hora_inicio || ''));
                    if (t !== 0) return t;
                    return String(a.titulo || '').localeCompare(String(b.titulo || ''), 'pt-BR');
                });
        }

        // ===== views =====
        function setView(view) {
            if (view !== 'agenda' && view !== 'mes') view = 'agenda';
            state.view = view;
            Array.prototype.forEach.call(el.viewButtons, function (btn) {
                var active = btn.dataset.fmViewBtn === view;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            if (el.agenda) {
                el.agenda.hidden = view !== 'agenda';
                el.agenda.parentElement && el.agenda.parentElement.classList.toggle('fme__view--active', view === 'agenda');
            }
            if (el.mesView) el.mesView.hidden = view !== 'mes';
            renderActiveView();
        }

        function renderActiveView() {
            if (el.monthLabel) el.monthLabel.textContent = formatMonth(state.currentMonth);
            if (state.view === 'mes') renderMonthGrid();
            else renderAgenda();
        }

        // ===== agenda view (cards) =====
        function renderAgenda() {
            if (!el.agenda) return;
            el.agenda.innerHTML = '';

            if (state.loading) {
                el.agenda.appendChild(renderAgendaSkeleton());
                return;
            }

            if (!state.eventos.length) {
                el.agenda.appendChild(renderEmptyState());
                return;
            }

            var month = state.currentMonth.getMonth();
            var year = state.currentMonth.getFullYear();
            var todayKey = dateKey(new Date());
            var tomorrowKey = dateKey(new Date(Date.now() + 86400000));

            // Agrupa por data de inicio. Eventos multi-dia agrupam pelo primeiro dia visivel no mes.
            var groups = {};
            state.eventos.forEach(function (ev) {
                var key = ev.data_inicio;
                var d = dateFromKey(key);
                if (d && (d.getMonth() !== month || d.getFullYear() !== year)) {
                    // evento comecou antes do mes mas se estende para dentro â€” usa primeiro dia do mes
                    var first = new Date(year, month, 1);
                    if (ev.data_fim && ev.data_fim >= dateKey(first)) {
                        key = dateKey(first);
                    }
                }
                if (!groups[key]) groups[key] = [];
                groups[key].push(ev);
            });

            // Wrapper da timeline (a linha vertical e desenhada via CSS).
            var timeline = document.createElement('div');
            timeline.className = 'fme-agenda';

            Object.keys(groups).sort().forEach(function (dateStr) {
                var date = dateFromKey(dateStr);
                if (!date) return;

                var isToday = dateStr === todayKey;
                var isPast = dateStr < todayKey;

                var group = document.createElement('section');
                group.className = 'fme-agenda__group' +
                    (isToday ? ' is-today' : '') +
                    (isPast ? ' is-past' : '');

                // Pilula de data â€” funciona como o "no" da timeline.
                var dayPill = document.createElement('div');
                dayPill.className = 'fme-agenda__daypill' + (isToday ? ' is-today' : '');
                dayPill.innerHTML =
                    '<span class="fme-agenda__daypill-num">' + date.getDate() + '</span>' +
                    '<span class="fme-agenda__daypill-mon">' + MONTH_SHORT[date.getMonth()] + '</span>';
                group.appendChild(dayPill);

                var header = document.createElement('div');
                header.className = 'fme-agenda__group-header';

                var labels = document.createElement('div');
                labels.className = 'fme-agenda__group-labels';
                var prefix = '';
                if (isToday) prefix = 'Hoje &middot; ';
                else if (dateStr === tomorrowKey) prefix = 'Amanha &middot; ';
                var count = groups[dateStr].length;
                labels.innerHTML =
                    '<p class="fme-agenda__weekday">' + prefix + WEEKDAY_FULL[date.getDay()] + '</p>' +
                    '<p class="fme-agenda__fulldate">' + date.getDate() + ' de ' +
                    new Intl.DateTimeFormat('pt-BR', { month: 'long' }).format(date) +
                    ' &middot; ' + count + ' evento' + (count > 1 ? 's' : '') +
                    (isPast ? ' &middot; encerrado' : '') + '</p>';

                header.appendChild(labels);
                group.appendChild(header);

                var list = document.createElement('div');
                list.className = 'fme-agenda__list';
                groups[dateStr].forEach(function (ev) {
                    list.appendChild(renderEventCard(ev));
                });
                group.appendChild(list);

                timeline.appendChild(group);
            });

            el.agenda.appendChild(timeline);
        }

        function renderEventCard(ev) {
            var card = document.createElement('button');
            card.type = 'button';
            var isPast = eventHasEnded(ev);
            card.className = 'fme-card' + (isPast ? ' fme-card--past' : '');
            card.dataset.eventoId = ev.id;
            var color = normalizeColor(ev.cor) || '#1a73e8';
            card.style.setProperty('--event-color', color);
            card.style.setProperty('--event-text', readableTextColor(color));

            var time = ev.hora_inicio ? ev.hora_inicio.slice(0, 5) : '';
            var location = [ev.local_nome, ev.cidade && ev.estado ? ev.cidade + ', ' + ev.estado : ev.cidade || ev.estado].filter(Boolean).join(' &middot; ');
            var category = ev.categoria ? ev.categoria.nome : '';
            var nonConfirmed = ev.status && ev.status !== 'confirmado';
            var promoChip = agendaPromotionChipHtml(ev, isPast);

            var thumb = ev.thumbnail
                ? '<div class="fme-card__media"><img src="' + escapeHtml(ev.thumbnail) + '" alt="" loading="lazy"></div>'
                : '<div class="fme-card__media fme-card__media--placeholder"><span>' + escapeHtml((ev.titulo || '?').charAt(0).toUpperCase()) + '</span></div>';

            card.innerHTML =
                thumb +
                '<div class="fme-card__body">' +
                    '<div class="fme-card__meta">' +
                        (category ? '<span class="fme-card__chip" style="background:' + escapeHtml(color) + '14;color:' + escapeHtml(color) + '"><span class="fme-card__chip-dot" style="background:' + escapeHtml(color) + '"></span>' + escapeHtml(category) + '</span>' : '') +
                        (isPast
                            ? '<span class="fme-card__chip fme-card__chip--past">Encerrado</span>'
                            : (nonConfirmed ? '<span class="fme-card__chip fme-card__chip--warn">' + escapeHtml(statusLabel(ev.status)) + '</span>' : '')) +
                        promoChip +
                    '</div>' +
                    '<h3 class="fme-card__title">' + escapeHtml(ev.titulo) + '</h3>' +
                    '<div class="fme-card__details">' +
                        (time ? '<span class="fme-card__detail"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8zm.5-13H11v6l5 3 .8-1.3-4.3-2.6z"/></svg>' + escapeHtml(time) + '</span>' : '') +
                        (location ? '<span class="fme-card__detail"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>' + location + '</span>' : '') +
                        (typeof ev.distancia_km === 'number' ? '<span class="fme-card__distance"><svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.99 8.99 0 0 0 13 3.06V1h-2v2.06A8.99 8.99 0 0 0 3.06 11H1v2h2.06A8.99 8.99 0 0 0 11 20.94V23h2v-2.06A8.99 8.99 0 0 0 20.94 13H23v-2zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>a ' + ev.distancia_km.toFixed(1).replace('.', ',') + ' km</span>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="fme-card__cta" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M8.6 16.6 13.2 12 8.6 7.4 10 6l6 6-6 6z"/></svg>' +
                '</div>';

            return card;
        }

        function agendaPromotionChipHtml(ev, eventClosed) {
            var promo = ev.promocoes_resumo && typeof ev.promocoes_resumo === 'object' ? ev.promocoes_resumo : {};
            var total = parseInt(promo.total, 10) || 0;
            if (!total) return '';

            var open = parseInt(promo.abertas, 10) || 0;
            var principal = promo.principal && typeof promo.principal === 'object' ? promo.principal : null;
            var principalLabel = String(principal && principal.status_label || '').toLowerCase();
            var label = 'Promocoes encerradas';
            var stateClass = ' is-closed';

            if (!eventClosed && open > 0) {
                if (principalLabel === 'em breve') {
                    label = 'Promocao em breve';
                    stateClass = ' is-soon';
                } else {
                    label = open > 1 ? open + ' promocoes abertas' : 'Promocao aberta';
                    stateClass = ' is-open';
                }
            }

            return '<span class="fme-card__chip fme-card__chip--promo' + stateClass + '">' +
                '<svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path fill="currentColor" d="M20 6h-2.2c.1-.3.2-.7.2-1a3 3 0 0 0-5.4-1.8L12 4l-.6-.8A3 3 0 0 0 6 5c0 .3.1.7.2 1H4a2 2 0 0 0-2 2v3h2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9h2V8a2 2 0 0 0-2-2zM15 4a1 1 0 0 1 0 2h-1.8l1-1.4c.2-.4.5-.6.8-.6zM9 4c.3 0 .6.2.8.6l1 1.4H9a1 1 0 0 1 0-2zm3 16H6v-9h6v9zm6 0h-4v-9h4v9zM4 9V8h7v1H4zm9 0V8h7v1h-7z"/></svg>' +
                escapeHtml(label) +
            '</span>';
        }

        function renderEmptyState() {
            var empty = document.createElement('div');
            empty.className = 'fme-empty';
            empty.innerHTML =
                '<div class="fme-empty__icon">' +
                    '<svg viewBox="0 0 24 24" width="48" height="48" aria-hidden="true"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14zM5 8V6h14v2z" opacity=".4"/></svg>' +
                '</div>' +
                '<h3 class="fme-empty__title">Nenhum evento encontrado</h3>' +
                '<p class="fme-empty__text">Ajuste os filtros ou navegue para outro mes.</p>';
            return empty;
        }

        function renderAgendaSkeleton() {
            var wrap = document.createElement('div');
            wrap.className = 'fme-skel-wrap';
            wrap.setAttribute('aria-hidden', 'true');
            wrap.setAttribute('aria-busy', 'true');

            var cardSkel =
                '<div class="fme-card fme-card--skel">' +
                    '<div class="fme-card__media fme-skel"></div>' +
                    '<div class="fme-card__body">' +
                        '<div class="fme-skel fme-skel--bar" style="width:64px;height:14px;margin-bottom:8px"></div>' +
                        '<div class="fme-skel fme-skel--bar" style="width:78%;height:18px;margin-bottom:10px"></div>' +
                        '<div class="fme-skel fme-skel--bar" style="width:55%;height:13px"></div>' +
                    '</div>' +
                    '<div class="fme-skel fme-skel--bar" style="width:24px;height:24px;border-radius:50%;align-self:center;margin-right:4px"></div>' +
                '</div>';

            var groupHtml =
                '<section class="fme-agenda__group">' +
                    '<div class="fme-agenda__daypill fme-skel"></div>' +
                    '<div class="fme-agenda__group-header">' +
                        '<div class="fme-agenda__group-labels" style="flex:1">' +
                            '<div class="fme-skel fme-skel--bar" style="width:160px;height:18px;margin-bottom:6px"></div>' +
                            '<div class="fme-skel fme-skel--bar" style="width:200px;height:13px"></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="fme-agenda__list">' + cardSkel + cardSkel + '</div>' +
                '</section>';

            wrap.innerHTML = '<div class="fme-agenda">' + groupHtml + groupHtml + '</div>';
            return wrap;
        }

        function injectMonthSkeletons() {
            if (!el.grid) return;
            var cells = el.grid.children;
            // Spalha skeletons em algumas celulas para sugerir carregamento.
            var positions = [4, 9, 16, 22, 27, 33];
            positions.forEach(function (i) {
                var cell = cells[i];
                if (!cell) return;
                var list = cell.querySelector('.fme-day__events');
                if (!list) return;
                var pill = document.createElement('div');
                pill.className = 'fme-pill fme-pill--skel fme-skel';
                pill.setAttribute('aria-hidden', 'true');
                list.appendChild(pill);
                if (i % 2 === 0) {
                    var second = pill.cloneNode();
                    list.appendChild(second);
                }
            });
        }

        // ===== month grid view =====
        function renderMonthGrid() {
            if (!el.grid) return;
            var year = state.currentMonth.getFullYear();
            var month = state.currentMonth.getMonth();
            var first = new Date(year, month, 1);
            var cursor = new Date(year, month, 1 - first.getDay());
            var todayKey = dateKey(new Date());

            el.grid.innerHTML = '';

            for (var i = 0; i < 42; i += 1) {
                var date = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + i);
                var key = dateKey(date);
                var day = document.createElement('div');
                var events = eventsForDate(key);
                var expanded = Boolean(state.expandedDays[key]);
                var maxEvents = expanded ? events.length : 3;
                var classes = ['fme-day'];

                if (date.getMonth() !== month) classes.push('is-muted');
                if (key === todayKey) classes.push('is-today');
                if (!events.length) classes.push('is-empty');

                day.className = classes.join(' ');
                day.setAttribute('role', 'gridcell');
                day.setAttribute('aria-label', formatDate(date));
                if (key === todayKey) day.setAttribute('aria-current', 'date');
                day.innerHTML = '<span class="fme-day__num">' + date.getDate() + '</span><div class="fme-day__events"></div>';

                var list = day.querySelector('.fme-day__events');
                events.slice(0, maxEvents).forEach(function (ev) {
                    list.appendChild(createPill(ev, key));
                });

                if (events.length > maxEvents) {
                    var more = document.createElement('button');
                    more.type = 'button';
                    more.className = 'fme-day__more';
                    more.dataset.fmMoreDay = key;
                    more.textContent = '+ ' + (events.length - maxEvents) + ' mais';
                    list.appendChild(more);
                }

                el.grid.appendChild(day);
            }

            if (state.loading) injectMonthSkeletons();
        }

        function createPill(ev, key) {
            var pill = document.createElement('button');
            var color = normalizeColor(ev.cor) || '#1a73e8';
            var time = ev.hora_inicio && ev.data_inicio === key ? ev.hora_inicio.slice(0, 5) : '';
            var status = ev.status && ev.status !== 'confirmado' ? statusLabel(ev.status) : '';

            pill.type = 'button';
            pill.className = 'fme-pill' + (time ? '' : ' fme-pill--allday') + (status ? ' fme-pill--' + ev.status : '');
            pill.dataset.eventoId = ev.id;
            pill.style.setProperty('--event-color', color);
            pill.style.setProperty('--event-text', readableTextColor(color));
            pill.title = [time, ev.titulo, status].filter(Boolean).join(' - ');
            pill.innerHTML =
                (time ? '<span class="fme-pill__time">' + escapeHtml(time) + '</span>' : '<span class="fme-pill__dot" aria-hidden="true"></span>') +
                '<span class="fme-pill__title">' + escapeHtml(ev.titulo) + '</span>';

            return pill;
        }

        // ===== modal =====
        function openEvento(id) {
            var eventId = parseInt(id, 10) || 0;
            if (!eventId) return;
            setStatus('Abrindo evento...');
            jsonFetch('/eventos/' + eventId).then(function (ev) {
                setStatus(statusMessage());
                if (!el.modal || !el.modalContent) return;
                el.modalContent.innerHTML = modalHtml(ev);
                el.modal.hidden = false;
                document.body.style.overflow = 'hidden';
                el.modal.scrollTop = 0;
                setEventUrl(ev.id || eventId);
            }).catch(function () {
                setStatus('Nao foi possivel abrir o evento.');
            });
        }

        function modalHtml(ev) {
            var date = formatDate(ev.data_inicio);
            if (ev.data_fim && ev.data_fim !== ev.data_inicio) date += ' a ' + formatDate(ev.data_fim);

            var isPast = eventHasEnded(ev);

            var time = [ev.hora_inicio, ev.hora_fim].filter(Boolean).map(function (t) { return String(t).slice(0, 5); }).join(' - ');
            var price = priceLabel(ev);
            var location = [ev.local_nome, ev.endereco, ev.cidade, ev.estado].filter(Boolean).join(', ');
            var canonicalUrl = eventUrl(ev.id);
            var shareUrl = encodeURIComponent(canonicalUrl);
            var google = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(ev.titulo) +
                '&dates=' + googleDates(ev) +
                '&details=' + encodeURIComponent(stripHtml(ev.descricao || '')) +
                '&location=' + encodeURIComponent(location);
            var shareText = encodeURIComponent(ev.titulo + ' - ' + date + ' ' + canonicalUrl);
            var color = ev.categoria ? normalizeColor(ev.categoria.cor) || '#1a73e8' : '#1a73e8';
            var textColor = readableTextColor(color);
            var heroBg = ev.thumbnail
                ? 'background-image:url(' + JSON.stringify(ev.thumbnail).slice(1, -1) + ')'
                : 'background:linear-gradient(135deg,' + color + ',' + color + 'cc)';
            var promoBtn = promotionActionHtml(ev.promocoes, isPast);
            var ticketBtn = ticketActionHtml(ev, isPast, !promoBtn);

            return '' +
                '<div class="fme-modal__hero" style="' + heroBg + '">' +
                    (ev.categoria ? '<span class="fme-modal__hero-chip" style="background:' + color + ';color:' + textColor + '">' + escapeHtml(ev.categoria.nome) + '</span>' : '') +
                '</div>' +
                '<div class="fme-modal__content">' +
                    '<header class="fme-modal__header">' +
                        '<h2 id="fme-modal-title" class="fme-modal__title">' + escapeHtml(ev.titulo) + '</h2>' +
                        '<div class="fme-modal__badges">' +
                            (isPast ? '<span class="fme-modal__badge fme-modal__badge--warn">Encerrado</span>' : '') +
                            (ev.status && ev.status !== 'confirmado' ? '<span class="fme-modal__badge fme-modal__badge--warn">' + escapeHtml(statusLabel(ev.status)) + '</span>' : '') +
                            '<span class="fme-modal__badge">' + escapeHtml(classificacaoLabel(ev.classificacao || 'livre')) + '</span>' +
                        '</div>' +
                    '</header>' +

                    '<div class="fme-modal__infogrid">' +
                        '<div class="fme-modal__infoblock">' +
                            '<div class="fme-modal__infoicon"><svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14z"/></svg></div>' +
                            '<div><p class="fme-modal__infolabel">Data</p><p class="fme-modal__infovalue">' + escapeHtml(date) + '</p></div>' +
                        '</div>' +
                        (time ? '<div class="fme-modal__infoblock">' +
                            '<div class="fme-modal__infoicon"><svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm.5-13H11v6l5 3 .8-1.3-4.3-2.6z"/></svg></div>' +
                            '<div><p class="fme-modal__infolabel">Horario</p><p class="fme-modal__infovalue">' + escapeHtml(time) + '</p></div>' +
                        '</div>' : '') +
                        (location ? '<div class="fme-modal__infoblock">' +
                            '<div class="fme-modal__infoicon"><svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg></div>' +
                            '<div><p class="fme-modal__infolabel">Local</p><p class="fme-modal__infovalue">' + escapeHtml(location) + '</p></div>' +
                        '</div>' : '') +
                        (price ? '<div class="fme-modal__infoblock">' +
                            '<div class="fme-modal__infoicon"><svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 17v-1.5c1.7-.3 3-1.3 3-3.2 0-2.3-2-3-3.6-3.5-1.6-.4-2.2-.8-2.2-1.5 0-.8.7-1.3 1.8-1.3 1.2 0 1.7.5 1.7 1.4h2.1c-.1-1.7-1.1-2.7-2.8-3v-1.4h-2v1.4c-1.7.3-2.8 1.4-2.8 2.9 0 1.9 1.6 2.8 3.4 3.3 1.7.4 2.3.9 2.3 1.7 0 .7-.6 1.3-1.9 1.3-1.4 0-1.9-.6-2-1.5H8.7c.1 1.7 1.3 2.7 2.8 3v1.5z"/></svg></div>' +
                            '<div><p class="fme-modal__infolabel">Ingresso</p><p class="fme-modal__infovalue">' + escapeHtml(price) + '</p></div>' +
                        '</div>' : '') +
                    '</div>' +

                    (ev.descricao ? '<div class="fme-modal__section"><h3 class="fme-modal__h3">Sobre</h3><div class="fme-modal__prose">' + ev.descricao + '</div></div>' : '') +

                    (ev.lineup && ev.lineup.length ? '<div class="fme-modal__section"><h3 class="fme-modal__h3">Lineup</h3><ul class="fme-modal__lineup">' +
                        ev.lineup.map(function (item) { return '<li><span></span>' + escapeHtml(item) + '</li>'; }).join('') +
                    '</ul></div>' : '') +

                    promotionsHtml(ev.promocoes, isPast) +

                    (ev.mapa_embed ? '<div class="fme-modal__section"><h3 class="fme-modal__h3">Como chegar</h3><iframe class="fme-modal__map" loading="lazy" src="' + escapeHtml(ev.mapa_embed) + '"></iframe></div>' : '') +

                    '<footer class="fme-modal__footer">' +
                        promoBtn +
                        ticketBtn +
                        '<a class="fme-modal__btn" target="_blank" rel="noopener" href="' + google + '">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM12 17.5 7.5 13l1.4-1.4L12 14.7 15.1 11.6 16.5 13z"/></svg>' +
                            '<span>Google Calendar</span></a>' +
                        (ev.ics_url ? '<a class="fme-modal__btn" href="' + escapeHtml(ev.ics_url) + '">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M19 12v7H5v-7H3v9h18v-9zM12 16l5-5h-3V3h-4v8H7z"/></svg>' +
                            '<span>Baixar .ics</span></a>' : '') +
                        '<button type="button" class="fme-modal__btn fme-modal__btn--icon" data-fm-copy-event="' + escapeHtml(ev.id) + '" aria-label="Copiar link do evento">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>' +
                        '</button>' +
                        '<a class="fme-modal__btn fme-modal__btn--icon" target="_blank" rel="noopener" aria-label="Compartilhar no WhatsApp" href="https://wa.me/?text=' + shareText + '">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.92.55 3.77 1.59 5.4L2 22l4.84-1.27a9.9 9.9 0 0 0 5.2 1.42h.01c5.45 0 9.9-4.45 9.9-9.9C21.95 6.45 17.5 2 12.04 2zM17 14.85c-.21.59-1.23 1.13-1.7 1.2-.43.06-.99.09-1.6-.1-.37-.12-.84-.27-1.45-.53-2.55-1.1-4.21-3.67-4.34-3.84-.13-.17-1.04-1.38-1.04-2.64s.66-1.87.9-2.13c.24-.26.51-.32.69-.32h.49c.16 0 .37-.06.58.44.21.51.72 1.77.78 1.9.06.13.1.28.02.45-.08.17-.12.27-.24.42-.12.15-.25.34-.36.45-.12.12-.25.25-.11.49.14.24.62 1.02 1.33 1.65.91.81 1.68 1.07 1.92 1.19.24.12.38.1.52-.06.14-.16.6-.7.76-.93.16-.24.32-.2.55-.12.23.08 1.45.69 1.7.81.25.12.41.18.47.28.06.1.06.6-.15 1.19z"/></svg>' +
                        '</a>' +
                        '<a class="fme-modal__btn fme-modal__btn--icon" target="_blank" rel="noopener" aria-label="Compartilhar no Facebook" href="https://www.facebook.com/sharer/sharer.php?u=' + shareUrl + '">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.79c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.89h-2.33v6.99A10 10 0 0 0 22 12z"/></svg>' +
                        '</a>' +
                        '<a class="fme-modal__btn fme-modal__btn--icon" target="_blank" rel="noopener" aria-label="Compartilhar no X" href="https://twitter.com/intent/tweet?text=' + shareText + '">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M18.244 2H21.5l-7.5 8.57L23 22h-6.8l-5.32-6.96L4.8 22H1.54l8.04-9.18L1 2h6.97l4.81 6.36zm-1.17 18h1.84L7.05 4H5.07z"/></svg>' +
                        '</a>' +
                    '</footer>' +
                '</div>';
        }

        function ticketActionHtml(ev, isPast, primary) {
            var state = ticketState(ev, isPast);
            if (!state || !state.label) return '';

            var cls = 'fme-modal__btn fme-modal__btn--ticket' + (primary && state.url ? ' fme-modal__btn--primary' : '');
            var content = '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M22 10V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4a2 2 0 0 1 0 4v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 1 0-4zM13 17.5h-2v-2h2zm0-4.5h-2v-2h2zm0-4.5h-2v-2h2z"/></svg>' +
                '<span>' + escapeHtml(state.label) + (state.detail ? '<small>' + escapeHtml(state.detail) + '</small>' : '') + '</span>';

            if (state.url) {
                return '<a class="' + cls + '" target="_blank" rel="noopener" href="' + escapeHtml(state.url) + '">' + content + '</a>';
            }

            return '<span class="' + cls + ' fme-modal__btn--disabled" aria-disabled="true">' + content + '</span>';
        }

        function ticketState(ev, isPast) {
            if (isPast) return { label: 'Evento encerrado' };
            if (ev.status === 'cancelado') return { label: 'Evento cancelado' };
            if (ev.status === 'adiado') return { label: 'Evento adiado' };
            if (ev.status === 'esgotado') return { label: 'Ingressos esgotados' };

            var saleStart = parseDateTimeValue(ev.data_inicio_venda);
            if (saleStart && saleStart.getTime() > Date.now()) {
                return { label: 'Vendas em breve', detail: formatDateTimeLabel(saleStart) };
            }

            if (ev.link_ingresso) {
                return { label: isFreeEvent(ev) ? 'Reservar gratuito' : 'Comprar ingresso', url: ev.link_ingresso };
            }

            if (isFreeEvent(ev)) return { label: 'Evento gratuito' };

            return { label: 'Ingressos indisponiveis' };
        }

        function promotionActionHtml(promocoes, eventClosed) {
            var promo = firstActionPromotion(promocoes, eventClosed);
            if (!promo) return '';

            return '<a class="fme-modal__btn fme-modal__btn--primary fme-modal__btn--promo" target="_blank" rel="noopener" href="' + escapeHtml(promo.url) + '">' +
                '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M20 6h-2.2c.1-.3.2-.7.2-1a3 3 0 0 0-5.4-1.8L12 4l-.6-.8A3 3 0 0 0 6 5c0 .3.1.7.2 1H4a2 2 0 0 0-2 2v3h2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9h2V8a2 2 0 0 0-2-2zM15 4a1 1 0 0 1 0 2h-1.8l1-1.4c.2-.4.5-.6.8-.6zM9 4c.3 0 .6.2.8.6l1 1.4H9a1 1 0 0 1 0-2zm3 16H6v-9h6v9zm6 0h-4v-9h4v9zM4 9V8h7v1H4zm9 0V8h7v1h-7z"/></svg>' +
                '<span>Participar da promocao<small>' + escapeHtml(promo.titulo || '') + '</small></span>' +
            '</a>';
        }

        function firstActionPromotion(promocoes, eventClosed) {
            if (eventClosed) return null;
            if (!Array.isArray(promocoes)) return null;
            for (var i = 0; i < promocoes.length; i += 1) {
                if (promocoes[i] &&
                    promocoes[i].url &&
                    promocoes[i].status !== 'encerrada' &&
                    String(promocoes[i].status_label || '').toLowerCase() !== 'em breve') {
                    return promocoes[i];
                }
            }
            return null;
        }

        function promotionsHtml(promocoes, eventClosed) {
            if (!Array.isArray(promocoes) || !promocoes.length) return '';

            var abertas = promocoes.filter(function (promo) { return promo.status !== 'encerrada'; });
            var encerradas = promocoes.filter(function (promo) { return promo.status === 'encerrada'; });

            return '<div class="fme-modal__section">' +
                '<h3 class="fme-modal__h3">Promo&ccedil;&otilde;es</h3>' +
                '<div class="fme-modal__promotions">' +
                    promotionGroupHtml('Abertas', abertas, eventClosed) +
                    promotionGroupHtml('Encerradas', encerradas, eventClosed) +
                '</div>' +
            '</div>';
        }

        function promotionGroupHtml(title, items, eventClosed) {
            if (!items.length) return '';

            return '<div class="fme-modal__promotion-group">' +
                '<p class="fme-modal__promotion-group-title">' + escapeHtml(title) + '</p>' +
                '<div class="fme-modal__promotion-list">' +
                    items.map(function (promo) { return promotionCardHtml(promo, eventClosed); }).join('') +
                '</div>' +
            '</div>';
        }

        function promotionCardHtml(promo, eventClosed) {
            var isClosed = promo.status === 'encerrada';
            var isSoon = String(promo.status_label || '').toLowerCase() === 'em breve';
            var period = promotionPeriod(promo);
            var thumb = promo.thumbnail
                ? '<img src="' + escapeHtml(promo.thumbnail) + '" alt="" loading="lazy">'
                : '<span>' + escapeHtml((promo.titulo || '?').charAt(0).toUpperCase()) + '</span>';
            var cta = eventClosed ? 'Evento encerrado' : (isClosed ? 'Encerrada' : (isSoon ? 'Em breve' : 'Participar'));

            return '<a class="fme-modal__promotion' + (isClosed || eventClosed ? ' is-closed' : '') + '" target="_blank" rel="noopener" href="' + escapeHtml(promo.url || '#') + '">' +
                '<span class="fme-modal__promotion-media">' + thumb + '</span>' +
                '<span class="fme-modal__promotion-body">' +
                    '<span class="fme-modal__promotion-topline">' +
                        '<span class="fme-modal__promotion-status">' + escapeHtml(promo.status_label || (isClosed ? 'Encerrada' : 'Aberta')) + '</span>' +
                        (period ? '<span class="fme-modal__promotion-period">' + escapeHtml(period) + '</span>' : '') +
                    '</span>' +
                    '<strong>' + escapeHtml(promo.titulo) + '</strong>' +
                    (promo.resumo ? '<span class="fme-modal__promotion-summary">' + escapeHtml(promo.resumo) + '</span>' : '') +
                    '<span class="fme-modal__promotion-cta">' + escapeHtml(cta) + '</span>' +
                '</span>' +
            '</a>';
        }

        function promotionPeriod(promo) {
            var start = formatDateTime(promo.participacao_inicio);
            var end = formatDateTime(promo.participacao_fim);

            if (start && end) return start + ' ate ' + end;
            if (start) return 'A partir de ' + start;
            if (end) return 'Ate ' + end;
            return '';
        }

        function formatDateTime(value) {
            var parts = String(value || '').trim().split(/\s+/);
            if (!parts[0]) return '';

            var date = formatDate(parts[0]);
            var time = parts[1] ? parts[1].slice(0, 5) : '';
            return [date, time].filter(Boolean).join(' ');
        }

        function stripHtml(html) {
            var d = document.createElement('div');
            d.innerHTML = html;
            return d.textContent || d.innerText || '';
        }

        function googleDates(ev) {
            function compact(date, time) {
                return String(date || '').replace(/-/g, '') + (time ? 'T' + time.replace(':', '') + '00' : '');
            }
            function nextDay(date) {
                var p = String(date || '').split('-').map(Number);
                var d = new Date(p[0], p[1] - 1, p[2] + 1);
                return d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate());
            }
            if (!ev.hora_inicio) {
                return encodeURIComponent(compact(ev.data_inicio, '') + '/' + nextDay(ev.data_fim || ev.data_inicio));
            }
            return encodeURIComponent(
                compact(ev.data_inicio, ev.hora_inicio) + '/' + compact(ev.data_fim || ev.data_inicio, ev.hora_fim || ev.hora_inicio)
            );
        }

        function closeModal() {
            if (!el.modal || !el.modalContent) return;
            el.modal.hidden = true;
            el.modalContent.innerHTML = '';
            document.body.style.overflow = '';
            clearEventUrl();
        }

        function copyEventLink(id, button) {
            var text = eventUrl(id);
            var onDone = function () {
                if (!button) return;
                button.classList.add('is-copied');
                button.setAttribute('aria-label', 'Link copiado');
                window.setTimeout(function () {
                    button.classList.remove('is-copied');
                    button.setAttribute('aria-label', 'Copiar link do evento');
                }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(onDone).catch(function () {
                    fallbackCopy(text);
                    onDone();
                });
                return;
            }

            fallbackCopy(text);
            onDone();
        }

        function fallbackCopy(text) {
            var input = document.createElement('textarea');
            input.value = text;
            input.setAttribute('readonly', 'readonly');
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy');
            } catch (e) { /* noop */ }
            document.body.removeChild(input);
        }

        // ===== events =====
        root.addEventListener('click', function (e) {
            var target = e.target;
            var card = target.closest('[data-evento-id]');
            var more = target.closest('[data-fm-more-day]');
            var viewBtn = target.closest('[data-fm-view-btn]');
            var copyBtn = target.closest('[data-fm-copy-event]');

            if (copyBtn) { copyEventLink(copyBtn.dataset.fmCopyEvent, copyBtn); return; }
            if (card) { openEvento(card.dataset.eventoId); return; }
            if (more) { state.expandedDays[more.dataset.fmMoreDay] = true; renderActiveView(); return; }
            if (viewBtn) { setView(viewBtn.dataset.fmViewBtn); return; }

            if (target.closest('[data-fm-prev]')) {
                state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
                loadEventos();
            }
            if (target.closest('[data-fm-next]')) {
                state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
                loadEventos();
            }
            if (target.closest('[data-fm-today]')) {
                var now = new Date();
                state.currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                loadEventos();
            }
            if (target.closest('[data-fm-close]')) closeModal();
            if (target.closest('[data-fm-geo]')) requestGeo();
            if (target.closest('[data-fm-clear]')) clearFilters();
        });

        root.addEventListener('change', function (e) {
            if (e.target === el.estado) { state.filters.estado = el.estado.value; state.filters.cidade = ''; updateCities(); loadEventos(); }
            if (e.target === el.cidade) { state.filters.cidade = el.cidade.value; loadEventos(); }
            if (e.target === el.categoria) { state.filters.categoria = el.categoria.value; loadEventos(); }
        });

        if (el.raio) {
            el.raio.addEventListener('input', function () {
                state.filters.raio = el.raio.value;
                if (el.raioLabel) el.raioLabel.textContent = el.raio.value + ' km';
                syncGeoUi();
            });
            el.raio.addEventListener('change', function () {
                if (hasGeo()) loadEventos();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && el.modal && !el.modal.hidden) closeModal();
        });

        function requestGeo() {
            // Toggle off: ja esta ativo? desliga e recarrega sem geo
            if (hasGeo()) {
                state.filters.lat = '';
                state.filters.lng = '';
                syncGeoUi();
                loadEventos();
                return;
            }

            if (!navigator.geolocation) {
                setStatus('Seu navegador nao suporta geolocalizacao.');
                return;
            }

            // Geolocation so funciona em origens seguras (HTTPS) ou em localhost/127.0.0.1
            var isSecure = window.isSecureContext ||
                window.location.protocol === 'https:' ||
                window.location.hostname === 'localhost' ||
                window.location.hostname === '127.0.0.1' ||
                window.location.hostname === '[::1]';

            if (!isSecure) {
                setStatus('Geolocalizacao precisa de HTTPS. Acesse o site em https:// para usar este filtro.');
                return;
            }

            setStatus('Solicitando sua localizacao...', true);

            navigator.geolocation.getCurrentPosition(function (pos) {
                state.filters.lat = pos.coords.latitude;
                state.filters.lng = pos.coords.longitude;
                syncGeoUi();
                loadEventos();
            }, function (err) {
                var msg = 'Nao foi possivel obter sua localizacao.';
                if (err && typeof err.code === 'number') {
                    if (err.code === 1) msg = 'Permissao de localizacao negada. Libere o acesso nas configuracoes do navegador.';
                    else if (err.code === 2) msg = 'Localizacao indisponivel no momento. Tente novamente em alguns segundos.';
                    else if (err.code === 3) msg = 'Tempo esgotado ao buscar sua localizacao. Tente novamente.';
                }
                setStatus(msg);
            }, {
                enableHighAccuracy: false,
                maximumAge: 300000,
                timeout: 10000
            });
        }

        function clearFilters() {
            state.filters = { estado: '', cidade: '', categoria: '', lat: '', lng: '', raio: 30 };
            if (el.raio) el.raio.value = '30';
            setupFilters();
            loadEventos();
        }

        function openFromUrl() {
            try {
                var params = new URL(window.location.href).searchParams;
                var id = params.get('evento');
                if (id && /^\d+$/.test(id)) openEvento(id);
            } catch (e) { /* noop */ }
        }

        // boot
        setView('agenda');
        setupFilters();
        loadFiltros()
            .then(loadEventos)
            .catch(function () { setupFilters(); loadEventos(); })
            .then(openFromUrl);
    }

    function fallbackConfig() {
        var apiLink = document.querySelector('link[rel="https://api.w.org/"]');
        var restRoot = apiLink && apiLink.href ? apiLink.href.replace(/\/$/, '') : window.location.origin + '/wp-json';
        var now = new Date();
        return {
            restUrl: restRoot + '/fmodia-eventos/v1',
            restNonce: '',
            defaultMonth: now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0'),
            defaults: { estado: '', cidade: '', categoria: '' },
            ufs: {},
            categorias: []
        };
    }
})();
