const finder = document.querySelector('[data-consumable-finder]');

if (finder) {
    const maker = finder.querySelector('[data-finder-maker]');
    const device = finder.querySelector('[data-finder-device]');
    const search = finder.querySelector('[data-finder-search]');
    const options = [...device.options].filter((option) => option.value);

    const filterModels = () => {
        options.forEach((option) => { option.hidden = maker.value !== '' && option.dataset.maker !== maker.value; });
        if (device.selectedOptions[0]?.hidden) device.value = '';
    };
    maker.addEventListener('change', filterModels);
    filterModels();

    search.addEventListener('change', () => {
        const match = [...finder.querySelectorAll('#finder-models option')].find((option) => option.value.toLocaleLowerCase() === search.value.trim().toLocaleLowerCase());
        if (!match?.dataset.slug) return;
        device.value = match.dataset.slug;
        maker.value = device.selectedOptions[0]?.dataset.maker || '';
        filterModels();
    });
}
