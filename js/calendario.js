(function () {
    var root = document.querySelector('[data-fm-eventos]');
    if (!root || !window.FMODIA_EVENTOS) {
        return;
    }

    var state = {
        currentMonth: parseMonth(FMODIA_EVENTOS.defaultMonth),
        filters: {
            estado: FMODIA_EVENTOS.defaults.estado || '',
            cidade: FMODIA_EVENTOS.defaults.cidade || '',
            categoria: FMODIA_EVENTOS.defaults.categoria || '',
            lat: '',
            lng: '',
            raio: 30
        },
        localidades: {},
        eventos: []
    };

    var el = {
        label: root.querySelector('[data-fm-month-label]'),
        grid: root.querySelector('[data-fm-eventos-grid]'),
        status: root.querySelector('[data-fm-status]'),
        estado: root.querySelector('[data-fm-estado]'),
        cidade: root.querySelector('[data-fm-cidade]'),
        categoria: root.querySelector('[data-fm-categoria]'),
        raio: root.querySelector('[data-fm-raio]'),
        raioLabel: root.querySelector('[data-fm-raio-label]'),
        modal: root.querySelector('[data-fm-modal]'),
        modalContent: root.querySelector('[data-fm-modal-content]')
    };

    function parseMonth(value) {
        var parts = String(value || '').split('-');
        var year = Number(parts[0]) || new Date().getFullYear();
        var month = Number(parts[1]) || (new Date().getMonth() + 1);
        return new Date(year, month - 1, 1);
    }

    function monthKey(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    }

    function formatDate(value) {
        if (!value) return '';
        var parts = value.split('-').map(Number);
        return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(new Date(parts[0], parts[1] - 1, parts[2]));
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
        var url = new URL(FMODIA_EVENTOS.restUrl.replace(/\/$/, '') + path);
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== '' && params[key] !== null && typeof params[key] !== 'undefined') {
                url.searchParams.set(key, params[key]);
            }
        });
        return url.toString();
    }

    function fillSelect(select, options, placeholder, selected) {
        select.innerHTML = '<option value="">' + placeholder + '</option>' + options.map(function (item) {
            return '<option value="' + escapeHtml(item.value) + '"' + (item.value === selected ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
        }).join('');
    }

    function setupFilters() {
        var ufOptions = Object.keys(FMODIA_EVENTOS.ufs || {}).map(function (uf) {
            return { value: uf, label: uf + ' - ' + FMODIA_EVENTOS.ufs[uf] };
        });
        fillSelect(el.estado, ufOptions, 'Todos', state.filters.estado);

        var catOptions = (FMODIA_EVENTOS.categorias || []).map(function (cat) {
            return { value: cat.slug, label: cat.nome };
        });
        fillSelect(el.categoria, catOptions, 'Todas', state.filters.categoria);
        updateCities();
    }

    function updateCities() {
        var cidades = state.filters.estado && state.localidades[state.filters.estado] ? state.localidades[state.filters.estado] : [];
        fillSelect(el.cidade, cidades.map(function (city) {
            return { value: city, label: city };
        }), 'Todas', state.filters.cidade);
    }

    function loadLocalidades() {
        return fetch(endpoint('/localidades'))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                state.localidades = data || {};
                updateCities();
            });
    }

    function loadEventos() {
        el.status.textContent = 'Carregando eventos...';
        return fetch(endpoint('/eventos', {
            mes: monthKey(state.currentMonth),
            estado: state.filters.estado,
            cidade: state.filters.cidade,
            categoria: state.filters.categoria,
            lat: state.filters.lat,
            lng: state.filters.lng,
            raio: state.filters.raio
        }))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                state.eventos = Array.isArray(data) ? data : [];
                renderCalendar();
                el.status.textContent = state.eventos.length ? '' : 'Nenhum evento encontrado para os filtros selecionados.';
            })
            .catch(function () {
                el.status.textContent = 'Nao foi possivel carregar os eventos.';
            });
    }

    function eventOccursOn(evento, date) {
        var key = date.toISOString().slice(0, 10);
        return evento.data_inicio <= key && (evento.data_fim || evento.data_inicio) >= key;
    }

    function renderCalendar() {
        var year = state.currentMonth.getFullYear();
        var month = state.currentMonth.getMonth();
        var first = new Date(year, month, 1);
        var cursor = new Date(year, month, 1 - first.getDay());
        var todayKey = new Date().toISOString().slice(0, 10);

        el.label.textContent = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(state.currentMonth);
        el.grid.innerHTML = '';

        for (var i = 0; i < 42; i += 1) {
            var date = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + i);
            var key = date.toISOString().slice(0, 10);
            var day = document.createElement('div');
            day.className = 'fm-eventos__day' + (date.getMonth() !== month ? ' is-muted' : '') + (key === todayKey ? ' is-today' : '');
            day.innerHTML = '<span class="fm-eventos__date">' + date.getDate() + '</span><div class="fm-eventos__events"></div>';

            var list = day.querySelector('.fm-eventos__events');
            state.eventos.filter(function (evento) { return eventOccursOn(evento, date); }).forEach(function (evento) {
                var pill = document.createElement('button');
                pill.type = 'button';
                pill.className = 'fm-eventos__pill';
                pill.style.backgroundColor = evento.cor || '#1976d2';
                pill.dataset.eventoId = evento.id;
                pill.innerHTML = '<span>' + escapeHtml((evento.status !== 'confirmado' ? evento.status + ' - ' : '') + evento.titulo) + '</span>';
                list.appendChild(pill);
            });

            el.grid.appendChild(day);
        }
    }

    function openEvento(id) {
        el.status.textContent = 'Abrindo evento...';
        fetch(endpoint('/eventos/' + id))
            .then(function (response) { return response.json(); })
            .then(function (evento) {
                el.status.textContent = '';
                el.modalContent.innerHTML = modalHtml(evento);
                el.modal.hidden = false;
                document.body.style.overflow = 'hidden';
            })
            .catch(function () {
                el.status.textContent = 'Nao foi possivel abrir o evento.';
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
        var google = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(evento.titulo) +
            '&dates=' + googleDates(evento) +
            '&details=' + encodeURIComponent(stripHtml(evento.descricao || '')) +
            '&location=' + encodeURIComponent(location);
        var shareText = encodeURIComponent(evento.titulo + ' - ' + data);
        var buy = evento.link_ingresso && evento.status === 'confirmado'
            ? '<a class="is-primary" target="_blank" rel="noopener" href="' + escapeHtml(evento.link_ingresso) + '">Comprar ingresso</a>'
            : '';

        return '' +
            '<div class="fm-eventos-modal__hero">' + (evento.thumbnail ? '<img src="' + escapeHtml(evento.thumbnail) + '" alt="">' : '') + '</div>' +
            '<div class="fm-eventos-modal__body">' +
                '<div><h3 id="fm-eventos-modal-title" class="fm-eventos-modal__title">' + escapeHtml(evento.titulo) + '</h3>' +
                '<div class="fm-eventos-modal__badges">' +
                    (evento.categoria ? '<span class="fm-eventos-modal__badge" style="background:' + escapeHtml(evento.categoria.cor) + ';color:#fff">' + escapeHtml(evento.categoria.nome) + '</span>' : '') +
                    (evento.status !== 'confirmado' ? '<span class="fm-eventos-modal__badge">' + escapeHtml(evento.status) + '</span>' : '') +
                    '<span class="fm-eventos-modal__badge">' + escapeHtml(evento.classificacao || 'livre') + '</span>' +
                '</div></div>' +
                '<div class="fm-eventos-modal__layout">' +
                    '<div><div class="fm-eventos-modal__meta">' +
                        '<strong>' + escapeHtml(data) + '</strong>' +
                        (horario ? '<span>' + escapeHtml(horario) + '</span>' : '') +
                        (location ? '<span>' + escapeHtml(location) + '</span>' : '') +
                        (preco ? '<span>' + escapeHtml(preco) + '</span>' : '') +
                    '</div>' +
                    '<div class="fm-eventos-modal__description">' + (evento.descricao || '') + '</div>' +
                    (evento.lineup && evento.lineup.length ? '<h4>Lineup</h4><ul>' + evento.lineup.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>' : '') +
                    '</div>' +
                    '<aside class="fm-eventos-modal__side">' +
                        (evento.mapa_embed ? '<iframe class="fm-eventos-modal__map" loading="lazy" src="' + escapeHtml(evento.mapa_embed) + '"></iframe>' : '') +
                        '<div class="fm-eventos-modal__actions">' + buy +
                            '<a target="_blank" rel="noopener" href="' + google + '">Google Calendar</a>' +
                            '<a href="' + escapeHtml(evento.ics_url) + '">Baixar .ics</a>' +
                            '<a target="_blank" rel="noopener" href="https://wa.me/?text=' + shareText + '">WhatsApp</a>' +
                            '<a target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(location) + '">Facebook</a>' +
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
        el.modal.hidden = true;
        el.modalContent.innerHTML = '';
        document.body.style.overflow = '';
    }

    root.addEventListener('click', function (event) {
        var pill = event.target.closest('[data-evento-id]');
        if (pill) openEvento(pill.dataset.eventoId);
        if (event.target.matches('[data-fm-prev]')) {
            state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
            loadEventos();
        }
        if (event.target.matches('[data-fm-next]')) {
            state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
            loadEventos();
        }
        if (event.target.matches('[data-fm-today]')) {
            state.currentMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            loadEventos();
        }
        if (event.target.matches('[data-fm-close]')) closeModal();
        if (event.target.matches('[data-fm-geo]')) requestGeo();
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

    el.raio.addEventListener('input', function () {
        state.filters.raio = el.raio.value;
        el.raioLabel.textContent = el.raio.value + ' km';
    });
    el.raio.addEventListener('change', loadEventos);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !el.modal.hidden) closeModal();
    });

    function requestGeo() {
        if (!navigator.geolocation) {
            el.status.textContent = 'Geolocalizacao nao disponivel neste navegador.';
            return;
        }

        el.status.textContent = 'Solicitando localizacao...';
        navigator.geolocation.getCurrentPosition(function (position) {
            state.filters.lat = position.coords.latitude;
            state.filters.lng = position.coords.longitude;
            loadEventos();
        }, function () {
            el.status.textContent = 'Nao foi possivel obter sua localizacao.';
        });
    }

    setupFilters();
    loadLocalidades().finally(loadEventos);
})();
