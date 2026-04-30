(function () {
    function get(name) {
        return document.querySelector('[name="' + name + '"]');
    }

    function first(names) {
        for (var i = 0; i < names.length; i += 1) {
            var field = get(names[i]);
            if (field) {
                return field;
            }
        }
        return null;
    }

    function validateDates() {
        var start = get('fm_evento_data_inicio');
        var end = get('fm_evento_data_fim');
        var min = get('fm_evento_preco_min');
        var max = get('fm_evento_preco_max');

        if (start && end && start.value && end.value && end.value < start.value) {
            end.setCustomValidity('A data fim deve ser maior ou igual a data inicio.');
        } else if (end) {
            end.setCustomValidity('');
        }

        if (min && max && min.value && max.value && Number(max.value) < Number(min.value)) {
            max.setCustomValidity('O preco maximo deve ser maior ou igual ao minimo.');
        } else if (max) {
            max.setCustomValidity('');
        }
    }

    function geocode() {
        var status = document.querySelector('[data-fm-geocode-status]');
        var lat = first(['fm_evento_lat', 'fm_evento_local_lat']);
        var lng = first(['fm_evento_lng', 'fm_evento_local_lng']);
        var parts = [
            first(['fm_evento_local_nome']) && first(['fm_evento_local_nome']).value,
            first(['fm_evento_endereco', 'fm_evento_local_endereco']) && first(['fm_evento_endereco', 'fm_evento_local_endereco']).value,
            first(['fm_evento_cidade', 'fm_evento_local_cidade']) && first(['fm_evento_cidade', 'fm_evento_local_cidade']).value,
            first(['fm_evento_estado', 'fm_evento_local_estado']) && first(['fm_evento_estado', 'fm_evento_local_estado']).value,
            first(['fm_evento_cep', 'fm_evento_local_cep']) && first(['fm_evento_cep', 'fm_evento_local_cep']).value
        ].filter(Boolean);

        if (!parts.length) {
            status.textContent = 'Preencha o endereco antes de buscar.';
            return;
        }

        status.textContent = 'Buscando...';
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(parts.join(', ')), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.length) {
                    status.textContent = 'Nenhuma coordenada encontrada.';
                    return;
                }
                lat.value = data[0].lat;
                lng.value = data[0].lon;
                status.textContent = 'Coordenadas preenchidas.';
            })
            .catch(function () {
                status.textContent = 'Nao foi possivel consultar o geocoder.';
            });
    }

    function fillAddressFromCep(input) {
        var cep = input.value.replace(/\D/g, '');
        var status = document.querySelector('[data-fm-cep-status]');
        var endereco = input.form ? input.form.querySelector('[data-fm-endereco]') : document.querySelector('[data-fm-endereco]');
        var cidade = input.form ? input.form.querySelector('[data-fm-cidade-field]') : document.querySelector('[data-fm-cidade-field]');
        var estado = input.form ? input.form.querySelector('[data-fm-estado-field]') : document.querySelector('[data-fm-estado-field]');

        if (cep.length !== 8) {
            return;
        }

        if (status) {
            status.textContent = 'Buscando CEP...';
        }

        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.erro) {
                    if (status) status.textContent = 'CEP nao encontrado.';
                    return;
                }

                if (endereco && !endereco.value) {
                    endereco.value = [data.logradouro, data.bairro].filter(Boolean).join(', ');
                }
                if (cidade) {
                    cidade.value = data.localidade || cidade.value;
                }
                if (estado) {
                    estado.value = data.uf || estado.value;
                }
                if (status) {
                    status.textContent = 'Endereco preenchido pelo CEP.';
                }
            })
            .catch(function () {
                if (status) status.textContent = 'Nao foi possivel consultar o CEP.';
            });
    }

    function fillFromSelectedLocation(select) {
        var option = select.options[select.selectedIndex];
        if (!option || !option.value) {
            return;
        }

        var map = {
            name: get('fm_evento_local_nome'),
            endereco: get('fm_evento_endereco'),
            cidade: get('fm_evento_cidade'),
            estado: get('fm_evento_estado'),
            cep: get('fm_evento_cep'),
            lat: get('fm_evento_lat'),
            lng: get('fm_evento_lng')
        };

        Object.keys(map).forEach(function (key) {
            if (map[key] && option.dataset[key]) {
                map[key].value = option.dataset[key];
            }
        });
    }

    document.addEventListener('input', validateDates);
    document.addEventListener('change', function (event) {
        validateDates();
        if (event.target.matches('[data-fm-cep]')) {
            fillAddressFromCep(event.target);
        }
        if (event.target.matches('[data-fm-local-select]')) {
            fillFromSelectedLocation(event.target);
        }
    });
    document.addEventListener('click', function (event) {
        if (event.target.matches('[data-fm-geocode]')) {
            geocode();
        }
    });
})();
