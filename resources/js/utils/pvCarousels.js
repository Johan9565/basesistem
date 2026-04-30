import { createApp, h } from 'vue';
import PrimeVue from 'primevue/config';
import Carousel from 'primevue/carousel';

function readImages(el) {
    const raw = el.getAttribute('data-images') ?? '[]';
    try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return parsed
            .map((it) => ({
                src: typeof it?.src === 'string' ? it.src : '',
                alt: typeof it?.alt === 'string' ? it.alt : '',
            }))
            .filter((it) => it.src || it.alt);
    } catch (e) {
        return [];
    }
}

export function unmountPrimeVueCarousels(rootEl) {
    if (!rootEl || !(rootEl instanceof HTMLElement)) return;
    const nodes = rootEl.querySelectorAll('[data-pv-carousel]');
    nodes.forEach((el) => {
        const app = el.__pvCarouselApp;
        if (app && typeof app.unmount === 'function') {
            try {
                app.unmount();
            } catch (e) {
                // ignore
            }
        }
        delete el.__pvCarouselApp;
    });
}

export function mountPrimeVueCarousels(rootEl) {
    if (!rootEl || !(rootEl instanceof HTMLElement)) return;

    const nodes = rootEl.querySelectorAll('[data-pv-carousel]');
    nodes.forEach((el) => {
        if (el.__pvCarouselApp) return;
        const images = readImages(el);

        const app = createApp({
            render() {
                return h(
                    Carousel,
                    {
                        value: images,
                        numVisible: 1,
                        numScroll: 1,
                        circular: true,
                        autoplayInterval: 3000,
                    },
                    {
                        item: ({ data }) =>
                            h('div', { class: 'pbx-p-2' }, [
                                h('img', {
                                    src: data?.src ?? '',
                                    alt: data?.alt ?? '',
                                    class: 'pbx-w-full pbx-rounded pbx-object-cover pbx-aspect-[16/10]',
                                }),
                            ]),
                    },
                );
            },
        });

        app.use(PrimeVue);
        app.mount(el);
        el.__pvCarouselApp = app;
    });
}

