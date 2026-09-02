const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

document.querySelectorAll('[data-cn-product-slider]').forEach((slider) => {
    const viewport = slider.querySelector('[data-cn-product-slider-viewport]');
    const previous = slider.querySelector('[data-cn-product-slider-previous]');
    const next = slider.querySelector('[data-cn-product-slider-next]');

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
        const slide = viewport.querySelector('.cn-product-slider__slide');
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
});
