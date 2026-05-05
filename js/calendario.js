(function () {
    var roots = document.querySelectorAll('[data-fm-eventos]');
    if (!roots.length) {
        return;
    }

    Array.prototype.forEach.call(roots, initCalendar);

    function initCalendar(root) {
        var config = window.FMODIA_EVENTOS || fallbackConfig();
        var defaults = config.defaults || {};

        var state = {
            currentMonth: parseMonth(config.defaultMonth),
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
            label: root.querySelector('[data-fm-month-label]'),
            grid: root.querySelector('[data-fm-eventos-grid]'),
            status: root.querySelector('[data-fm-status]'),
            estado: root.querySelector('[data-fm-estado]'),
            cidade: root.querySelector('[data-fm-cidade]'),
            categoria: root.querySelector('[data-fm-categoria]'),
            geo: root.querySelector('[data-fm-geo]'),
            raio: root.querySelector('[data-fm-raio]'),
            raioLabel: root.querySelector('[data-fm-raio-label]'),
            modal: root.querySelector('[data-fm-modal]'),
            modalContent: root.querySelector('[data-fm-modal-content]')
        };

        if (!el.label || !el.grid) {
            return;
        }

        function parseMonth(value) {
            var parts = String(value || '').split('-');
            var now = new Date();
            var year = Number(parts[0]) || now.getFullYear();
            var month = Number(parts[1]) || (now.getMonth() + 1);
            return new Date(year, month - 1, 1);
        }

        function monthKey(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
        }

        function dateKey(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }

        function dateFromKey(value) {
            var parts = String(value || '').split('-').map(Number);
            if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
                return null;
            }
            return new Date(parts[0], parts[1] - 1, parts[2]);
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
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
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
            if (!window.fetch) {
                return Promise.reject(new Error('fetch-unavailable'));
            }

            return fetch(endpoint(path, params), {
                headers: config.restNonce ? { 'X-WP-Nonce': config.restNonce } : {}
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('request-failed');
                }
                return response.json();
            });
        }

        function fillSelect(select, options, placeholder, selected) {
            if (!select) return;

            select.innerHTML = '<option value="">' + placeholder + '</option>' + options.map(function (item) {
                return '<option value="' + escapeHtml(item.value) + '"' + (item.value === selected ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
            }).join('');
        }

        function setupFilters() {
            var ufOptions = availableUfKeys().map(function (uf) {
                var name = state.filterOptions.ufs[uf] || (config.ufs || {})[uf] || uf;
                return { value: uf, label: uf + (name && name !== uf ? ' - ' + name : '') };
            });
            fillSelect(el.estado, ufOptions, 'Todos', state.filters.estado);

            var catOptions = (state.filterOptions.categorias || []).map(function (cat) {
                return { value: cat.slug, label: cat.nome };
            });
            fillSelect(el.categoria, catOptions, 'Todas', state.filters.categoria);
            updateCities();
            syncGeoUi();
        }

        function availableUfKeys() {
            var keys = Object.keys(state.localidades || {});
            if (!keys.length) {
                keys = Object.keys(state.filterOptions.ufs || config.ufs || {});
            }
            if (state.filters.estado && keys.indexOf(state.filters.estado) === -1) {
                keys.unshift(state.filters.estado);
            }
            return keys.sort();
        }

        function updateCities() {
            var cidades = [];
            if (state.filters.estado && state.localidades[state.filters.estado]) {
                cidades = state.localidades[state.filters.estado].slice();
            } else {
                Object.keys(state.localidades || {}).forEach(function (uf) {
                    (state.localidades[uf] || []).forEach(function (city) {
                        if (cidades.indexOf(city) === -1) {
                            cidades.push(city);
                        }
                    });
                });
            }

            if (state.filters.cidade && cidades.indexOf(state.filters.cidade) === -1) {
                cidades.unshift(state.filters.cidade);
            }

            cidades.sort(function (a, b) {
                return String(a).localeCompare(String(b), 'pt-BR');
            });

            fillSelect(el.cidade, cidades.map(function (city) {
                return { value: city, label: city };
            }), 'Todas', state.filters.cidade);
        }

        function loadFiltros() {
            return jsonFetch('/filtros')
                .then(function (data) {
                    state.localidades = data.localidades || {};
                    state.filterOptions.ufs = data.ufs || state.filterOptions.ufs;
                    state.filterOptions.categorias = Array.isArray(data.categorias) ? data.categorias : state.filterOptions.categorias;
                    setupFilters();
                })
                .catch(function () {
                    return jsonFetch('/localidades').then(function (data) {
                        state.localidades = data || {};
                        setupFilters();
                    });
                });
        }

        function loadEventos() {
            state.eventos = [];
            renderCalendar();
            setStatus('Carregando eventos...');

            return jsonFetch('/eventos', {
                mes: monthKey(state.currentMonth),
                estado: state.filters.estado,
                cidade: state.filters.cidade,
                categoria: state.filters.categoria,
                lat: state.filters.lat,
                lng: state.filters.lng,
                raio: state.filters.raio
            })
                .then(function (data) {
                    state.eventos = Array.isArray(data) ? data : [];
                    state.expandedDays = {};
                    renderCalendar();
                    setStatus(statusMessage());
                })
                .catch(function () {
                    state.eventos = [];
                    renderCalendar();
                    setStatus('Nao foi possivel carregar os eventos.');
                });
        }

        function setStatus(message) {
            if (el.status) {
                el.status.textContent = message || '';
            }
        }

        function statusMessage() {
            if (!state.eventos.length) {
                return 'Nenhum evento encontrado para os filtros selecionados.';
            }

            if (hasGeo()) {
                return state.eventos.length + ' evento' + (state.eventos.length > 1 ? 's' : '') + ' em ate ' + state.filters.raio + ' km da sua localizacao.';
            }

            return '';
        }

        function hasGeo() {
            return state.filters.lat !== '' && state.filters.lng !== '';
        }

        function syncGeoUi() {
            if (el.raioLabel && el.raio) {
                el.raioLabel.textContent = el.raio.value + ' km';
            }

            if (!el.geo) {
                return;
            }

            el.geo.classList.toggle('is-active', hasGeo());
            el.geo.setAttribute('aria-pressed', hasGeo() ? 'true' : 'false');
            el.geo.textContent = hasGeo() ? 'Localizacao ativa' : 'Minha localizacao';
            el.geo.title = hasGeo() ? 'Clique para remover o filtro por localizacao' : 'Usar minha localizacao para filtrar por raio';
        }

        function eventOccursOn(evento, key) {
            var start = evento.data_inicio || '';
            var end = evento.data_fim || start;
            return Boolean(start && start <= key && end >= key);
        }

        function eventsForDate(key) {
            return state.eventos
                .filter(function (evento) { return eventOccursOn(evento, key); })
                .sort(function (a, b) {
                    var time = String(a.hora_inicio || '').localeCompare(String(b.hora_inicio || ''));
                    if (time !== 0) return time;
                    return String(a.titulo || '').localeCompare(String(b.titulo || ''), 'pt-BR');
                });
        }

        function renderCalendar() {
            var year = state.currentMonth.getFullYear();
            var month = state.currentMonth.getMonth();
            var first = new Date(year, month, 1);
            var cursor = new Date(year, month, 1 - first.getDay());
            var todayKey = dateKey(new Date());

            el.label.textContent = formatMonth(state.currentMonth);
            el.grid.innerHTML = '';

            for (var i = 0; i < 42; i += 1) {
                var date = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + i);
                var key = dateKey(date);
                var day = document.createElement('div');
                var events = eventsForDate(key);
                var expanded = Boolean(state.expandedDays[key]);
                var maxEvents = expanded ? events.length : 4;
                var classes = ['fm-eventos__day'];

                if (date.getMonth() !== month) classes.push('is-muted');
                if (key === todayKey) classes.push('is-today');
                if (!events.length) classes.push('is-empty');

                day.className = classes.join(' ');
                day.setAttribute('role', 'gridcell');
                day.setAttribute('aria-label', formatDate(date));
                if (key === todayKey) {
                    day.setAttribute('aria-current', 'date');
                }
                day.innerHTML = '<span class="fm-eventos__date">' + date.getDate() + '</span><div class="fm-eventos__events"></div>';

                var list = day.querySelector('.fm-eventos__events');
                events.slice(0, maxEvents).forEach(function (evento) {
                    list.appendChild(createEventPill(evento, key));
                });

                if (events.length > maxEvents) {
                    var more = document.createElement('button');
                    more.type = 'button';
                    more.className = 'fm-eventos__more';
                    more.dataset.fmMoreDay = key;
                    more.textContent = '+ ' + (events.length - maxEvents);
                    more.title = 'Mostrar todos os eventos do dia';
                    list.appendChild(more);
                }

                el.grid.appendChild(day);
            }
        }

        function createEventPill(evento, key) {
            var pill = document.createElement('button');
            var color = normalizeColor(evento.cor) || '#1a73e8';
            var status = evento.status && evento.status !== 'confirmado' ? statusLabel(evento.status) : '';
            var time = evento.hora_inicio && evento.data_inicio === key ? evento.hora_inicio : '';

            pill.type = 'button';
            pill.className = 'fm-eventos__pill';
            pill.dataset.eventoId = evento.id;
            pill.style.setProperty('--event-color', color);
            pill.style.setProperty('--event-text', readableTextColor(color));
            pill.title = [time, evento.titulo, status].filter(Boolean).join(' - ');
            pill.innerHTML =
                (time ? '<span class="fm-eventos__pill-time">' + escapeHtml(time) + '</span>' : '') +
                '<span class="fm-eventos__pill-title">' + escapeHtml(evento.titulo) + '</span>' +
                (status ? '<span class="fm-eventos__pill-status">' + escapeHtml(status) + '</span>' : '');

            return pill;
        }

        function normalizeColor(value) {
            var color = String(value || '').trim();
            if (/^#[0-9a-f]{3}$/i.test(color)) {
                return '#' + color.charAt(1) + color.charAt(1) + color.charAt(2) + color.charAt(2) + color.charAt(3) + color.charAt(3);
            }
            if (/^#[0-9a-f]{6}$/i.test(color)) {
                return color;
            }
            return '';
        }

        function readableTextColor(hex) {
            var color = normalizeColor(hex);
            if (!color) return '#fff';

            var r = parseInt(color.slice(1, 3), 16);
            var g = parseInt(color.slice(3, 5), 16);
            var b = parseInt(color.slice(5, 7), 16);
            var brightness = (r * 299 + g * 587 + b * 114) / 1000;
            return brightness > 170 ? '#202124' : '#fff';
        }

        function statusLabel(value) {
            return ({
                confirmado: 'Confirmado',
                esgotado: 'Esgotado',
                cancelado: 'Cancelado',
                adiado: 'Adiado'
            })[value] || String(value || '');
        }

        function classificacaoLabel(value) {
            return ({
                livre: 'Livre',
                10: '10 anos',
                12: '12 anos',
                14: '14 anos',
                16: '16 anos',
                18: '18 anos'
            })[value] || String(value || '');
        }

        function openEvento(id) {
            setStatus('Abrindo evento...');

            jsonFetch('/eventos/' + id)
                .then(function (evento) {
                    setStatus('');
                    if (!el.modal || !el.modalContent) return;
                    el.modalContent.innerHTML = modalHtml(evento);
                    el.modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                })
                .catch(function () {
                    setStatus('Nao foi possivel abrir o evento.');
                });
        }

        function modalHtml(evento) {
            var data = formatDate(evento.data_inicio);
            if (evento.data_fim && evento.data_fim !== evento.data_inicio) {
                data += ' a ' + formatDate(evento.data_fim);
            }

            var horario = [evento.hora_inicio, evento.hora_fim].filter(Boolean).join(' as ');
            var preco = evento.preco_min || evento.preco_max ? [formatMoney(evento.preco_min), formatMoney(evento.preco_max)].filter(Boolean).join(' a ') : '';
            var location = [evento.local_nome, evento.endereco, evento.cidade, evento.estado].filter(Boolean).join(', ');
            var shareUrl = encodeURIComponent(window.location.href.split('#')[0]);
            var google = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(evento.titulo) +
                '&dates=' + googleDates(evento) +
                '&details=' + encodeURIComponent(stripHtml(evento.descricao || '')) +
                '&location=' + encodeURIComponent(location);
            var shareText = encodeURIComponent(evento.titulo + ' - ' + data);
            var catColor = evento.categoria ? normalizeColor(evento.categoria.cor) || '#1a73e8' : '';
            var buy = evento.link_ingresso && evento.status === 'confirmado'
                ? '<a class="is-primary" target="_blank" rel="noopener" href="' + escapeHtml(evento.link_ingresso) + '">Comprar ingresso</a>'
                : '';

            return '' +
                '<div class="fm-eventos-modal__hero">' + (evento.thumbnail ? '<img src="' + escapeHtml(evento.thumbnail) + '" alt="">' : '') + '</div>' +
                '<div class="fm-eventos-modal__body">' +
                    '<div><h3 id="fm-eventos-modal-title" class="fm-eventos-modal__title">' + escapeHtml(evento.titulo) + '</h3>' +
                    '<div class="fm-eventos-modal__badges">' +
                        (evento.categoria ? '<span class="fm-eventos-modal__badge" style="background:' + escapeHtml(catColor) + ';color:' + escapeHtml(readableTextColor(catColor)) + '">' + escapeHtml(evento.categoria.nome) + '</span>' : '') +
                        (evento.status !== 'confirmado' ? '<span class="fm-eventos-modal__badge">' + escapeHtml(statusLabel(evento.status)) + '</span>' : '') +
                        '<span class="fm-eventos-modal__badge">' + escapeHtml(classificacaoLabel(evento.classificacao || 'livre')) + '</span>' +
                    '</div></div>' +
                    '<div class="fm-eventos-modal__layout">' +
                        '<div class="fm-eventos-modal__main"><div class="fm-eventos-modal__meta">' +
                            '<strong>' + escapeHtml(data) + '</strong>' +
                            (horario ? '<span>' + escapeHtml(horario) + '</span>' : '') +
                            (location ? '<span>' + escapeHtml(location) + '</span>' : '') +
                            (preco ? '<span>' + escapeHtml(preco) + '</span>' : '') +
                        '</div>' +
                        '<div class="fm-eventos-modal__description">' + (evento.descricao || '') + '</div>' +
                        (evento.lineup && evento.lineup.length ? '<h4 class="fm-eventos-modal__lineup-title">Lineup</h4><ul class="fm-eventos-modal__lineup">' + evento.lineup.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>' : '') +
                        '</div>' +
                        '<aside class="fm-eventos-modal__side">' +
                            (evento.mapa_embed ? '<iframe class="fm-eventos-modal__map" loading="lazy" src="' + escapeHtml(evento.mapa_embed) + '"></iframe>' : '') +
                            '<div class="fm-eventos-modal__actions">' + buy +
                                '<a target="_blank" rel="noopener" href="' + google + '">Adicionar ao Google Calendar</a>' +
                                (evento.ics_url ? '<a href="' + escapeHtml(evento.ics_url) + '">Baixar .ics</a>' : '') +
                                '<a target="_blank" rel="noopener" href="https://wa.me/?text=' + shareText + '">WhatsApp</a>' +
                                '<a target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' + shareUrl + '">Facebook</a>' +
                                '<a target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=' + shareText + '">X</a>' +
                            '</div>' +
                        '</aside>' +
                    '</div>' +
                '</div>';
        }

        function stripHtml(html) {
            var div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        }

        function googleDates(evento) {
            function compact(date, time) {
                return String(date || '').replace(/-/g, '') + (time ? 'T' + time.replace(':', '') + '00' : '');
            }
            function nextDay(date) {
                var parts = String(date || '').split('-').map(Number);
                var value = new Date(parts[0], parts[1] - 1, parts[2] + 1);
                return value.getFullYear() + String(value.getMonth() + 1).padStart(2, '0') + String(value.getDate()).padStart(2, '0');
            }
            if (!evento.hora_inicio) {
                return encodeURIComponent(compact(evento.data_inicio, '') + '/' + nextDay(evento.data_fim || evento.data_inicio));
            }
            var start = compact(evento.data_inicio, evento.hora_inicio);
            var end = compact(evento.data_fim || evento.data_inicio, evento.hora_fim || evento.hora_inicio);
            return encodeURIComponent(start + '/' + end);
        }

        function closeModal() {
            if (!el.modal || !el.modalContent) return;
            el.modal.hidden = true;
            el.modalContent.innerHTML = '';
            document.body.style.overflow = '';
        }

        root.addEventListener('click', function (event) {
            var pill = event.target.closest('[data-evento-id]');
            var more = event.target.closest('[data-fm-more-day]');

            if (pill) {
                openEvento(pill.dataset.eventoId);
                return;
            }

            if (more) {
                state.expandedDays[more.dataset.fmMoreDay] = true;
                renderCalendar();
                return;
            }

            if (event.target.closest('[data-fm-prev]')) {
                state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
                loadEventos();
            }
            if (event.target.closest('[data-fm-next]')) {
                state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
                loadEventos();
            }
            if (event.target.closest('[data-fm-today]')) {
                var now = new Date();
                state.currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                loadEventos();
            }
            if (event.target.closest('[data-fm-close]')) closeModal();
            if (event.target.closest('[data-fm-geo]')) requestGeo();
        });

        root.addEventListener('change', function (event) {
            if (event.target === el.estado) {
                state.filters.estado = el.estado.value;
                state.filters.cidade = '';
                updateCities();
                loadEventos();
            }
            if (event.target === el.cidade) {
                state.filters.cidade = el.cidade.value;
                loadEventos();
            }
            if (event.target === el.categoria) {
                state.filters.categoria = el.categoria.value;
                loadEventos();
            }
        });

        if (el.raio) {
            el.raio.addEventListener('input', function () {
                state.filters.raio = el.raio.value;
                if (el.raioLabel) {
                    el.raioLabel.textContent = el.raio.value + ' km';
                }
            });
            el.raio.addEventListener('change', function () {
                if (hasGeo()) {
                    loadEventos();
                    return;
                }

                setStatus('Escolha "Minha localizacao" para aplicar o filtro por raio.');
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && el.modal && !el.modal.hidden) closeModal();
        });

        function requestGeo() {
            if (hasGeo()) {
                state.filters.lat = '';
                state.filters.lng = '';
                syncGeoUi();
                setStatus('Filtro por localizacao removido.');
                loadEventos();
                return;
            }

            if (!navigator.geolocation) {
                setStatus('Geolocalizacao nao disponivel neste navegador.');
                return;
            }

            setStatus('Solicitando localizacao...');
            navigator.geolocation.getCurrentPosition(function (position) {
                state.filters.lat = position.coords.latitude;
                state.filters.lng = position.coords.longitude;
                syncGeoUi();
                loadEventos();
            }, function () {
                setStatus('Nao foi possivel obter sua localizacao.');
            }, {
                enableHighAccuracy: false,
                maximumAge: 300000,
                timeout: 10000
            });
        }

        renderCalendar();
        setupFilters();
        loadFiltros()
            .then(loadEventos)
            .catch(function () {
                setupFilters();
                loadEventos();
            });
    }

    function fallbackConfig() {
        var apiLink = document.querySelector('link[rel="https://api.w.org/"]');
        var restRoot = apiLink && apiLink.href ? apiLink.href.replace(/\/$/, '') : window.location.origin + '/wp-json';
        var now = new Date();

        return {
            restUrl: restRoot + '/fmodia-eventos/v1',
            restNonce: '',
            defaultMonth: now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0'),
            defaults: {
                estado: '',
                cidade: '',
                categoria: ''
            },
            ufs: {},
            categorias: []
        };
    }
})();
