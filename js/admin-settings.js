(function () {
    var master = document.getElementById('fme-filtros');
    var row = document.querySelector('.fme-settings__filtro-row');
    var carouselRow = document.querySelector('.fme-settings__carousel-row');
    var layoutInputs = document.querySelectorAll('input[name$="[home_layout]"]');

    // Apenas escurece/bloqueia visualmente a linha. Os checkboxes continuam
    // habilitados para que o WordPress preserve a escolha ao salvar.
    function syncFilters() {
        if (!master || !row) return;
        row.classList.toggle('is-disabled', !master.checked);
    }

    function selectedLayout() {
        var selected = Array.prototype.filter.call(layoutInputs, function (input) {
            return input.checked;
        })[0];
        return selected ? selected.value : '';
    }

    function syncCarouselRow() {
        if (!carouselRow) return;
        carouselRow.classList.toggle('is-disabled', selectedLayout() !== 'carrossel');
    }

    if (master) master.addEventListener('change', syncFilters);
    Array.prototype.forEach.call(layoutInputs, function (input) {
        input.addEventListener('change', syncCarouselRow);
    });

    syncFilters();
    syncCarouselRow();
})();
