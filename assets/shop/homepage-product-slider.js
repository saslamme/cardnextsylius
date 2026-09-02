const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const sliderTypes = [
    {
        root: '[data-cn-product-slider]',
        viewport: '[data-cn-product-slider-viewport]',
        previous: '[data-cn-product-slider-previous]',
        next: '[data-cn-product-slider-next]',
        slide: '.cn-product-slider__slide',
    },
    {
        root: '[data-cn-category-slider]',
        viewport: '[data-cn-category-slider-viewport]',
        previous: '[data-cn-category-slider-previous]',
        next: '[data-cn-category-slider-next]',
        slide: '.cn-category-slider__slide',
    },
];

sliderTypes.forEach((type) => document.querySelectorAll(type.root).forEach((slider) => {
    const viewport = slider.querySelector(type.viewport);
    const previous = slider.querySelector(type.previous);
    const next = slider.querySelector(type.next);

    if (!viewport || !previous || !next) return;

    let frame;
    const update = () => {
        frame = undefined;
        const end = viewport.scrollWidth - viewport.clientWidth;
        previous.disabled = viewport.scrollLeft <= 1;
        next.disabled = end <= 1 || viewport.scrollLeft >= end - 1;
    };
    const scheduleUpdate = () => {
        if (frame === undefined) frame = window.requestAnimationFrame(update);
    };
    const scroll = (direction) => {
        const slide = viewport.querySelector(type.slide);
        if (!slide) return;

        const track = slide.parentElement;
        const gap = Number.parseFloat(window.getComputedStyle(track).columnGap) || 0;
        viewport.scrollBy({
            left: direction * (slide.getBoundingClientRect().width + gap),
            behavior: reducedMotion.matches ? 'auto' : 'smooth',
        });
    };

    previous.addEventListener('click', () => scroll(-1));
    next.addEventListener('click', () => scroll(1));
    viewport.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    update();
}));
