<script setup>
import { getPageBuilder, usePageBuilderModal } from '@myissue/vue-website-page-builder';

const pageBuilderService = getPageBuilder();
const { closeAddComponentModal } = usePageBuilderModal();

const templates = [
    {
        title: 'Landing Fotografía: Carrusel + Tipos',
        html_code: `
<section data-component-title="Landing Fotografía: Carrusel + Tipos">
  <div class="pbx-px-2 lg:pbx-px-4 pbx-py-10">
    <div class="pbx-mx-auto pbx-max-w-7xl">
      <div class="pbx-grid pbx-grid-cols-1 lg:pbx-grid-cols-2 pbx-gap-8 pbx-items-center">
        <div>
          <h1 class="pbx-text-3xl lg:pbx-text-5xl pbx-font-semibold pbx-leading-tight">
            Fotografía profesional en tu destino
          </h1>
          <p class="pbx-mt-3 pbx-opacity-80 pbx-text-base lg:pbx-text-lg">
            Sesiones en playa, pareja, familia, lifestyle y más. Reserva directo por WhatsApp.
          </p>
          <div class="pbx-mt-6 pbx-flex pbx-gap-3 pbx-flex-wrap">
            <div class="pbx-flex pbx-gap-3">
            <a class="pbx-inline-flex pbx-items-center pbx-justify-center pbx-rounded pbx-px-5 pbx-py-3 pbx-bg-black pbx-text-white"
               href="#"
               rel="noopener noreferrer">
              Reservar
            </a>
            </div>
            <div class="pbx-flex pbx-gap-3">
            <a class="pbx-inline-flex pbx-items-center pbx-justify-center pbx-rounded pbx-px-5 pbx-py-3 pbx-border pbx-border-gray-300"
               href="#tipos">
              Ver tipos de sesión
            </a>
            </div>
          </div>
        </div>

        <!-- Carrusel (sin JS): horizontal scroll + scroll-snap -->
        <div>
          <div class="pbx-rounded pbx-overflow-hidden pbx-border pbx-border-gray-200">
            <div
              style="display:flex; overflow-x:auto; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; gap:12px; padding:12px;"
            >
              <img
                class="pbx-rounded pbx-object-cover"
                style="scroll-snap-align:start; width:100%; max-width:520px; aspect-ratio: 16 / 10;"
                src=""
                alt="Slide 1"
              />
              <img
                class="pbx-rounded pbx-object-cover"
                style="scroll-snap-align:start; width:100%; max-width:520px; aspect-ratio: 16 / 10;"
                src=""
                alt="Slide 2"
              />
              <img
                class="pbx-rounded pbx-object-cover"
                style="scroll-snap-align:start; width:100%; max-width:520px; aspect-ratio: 16 / 10;"
                src=""
                alt="Slide 3"
              />
            </div>
          </div>
          <p class="pbx-mt-2 pbx-text-sm pbx-opacity-70">
            Tip: selecciona una imagen y usa la Media Library para asignar la foto.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Tipos de fotos -->
  <div id="tipos" class="pbx-px-2 lg:pbx-px-4 pbx-pb-12">
    <div class="pbx-mx-auto pbx-max-w-7xl">
      <div class="pbx-flex pbx-items-end pbx-justify-between pbx-gap-4">
        <h2 class="pbx-text-2xl lg:pbx-text-3xl pbx-font-semibold">Tipos de sesión</h2>
        <p class="pbx-text-sm pbx-opacity-70">Personaliza títulos y descripciones.</p>
      </div>

      <div class="pbx-mt-6 pbx-grid pbx-grid-cols-1 sm:pbx-grid-cols-2 lg:pbx-grid-cols-3 pbx-gap-4">
        <div class="pbx-rounded pbx-border pbx-border-gray-200 pbx-p-5">
          <div class="pbx-font-semibold pbx-text-lg">Pareja</div>
          <div class="pbx-mt-2 pbx-text-sm pbx-opacity-80">Atardecer, playa, urbano. 30–60 min.</div>
        </div>
        <div class="pbx-rounded pbx-border pbx-border-gray-200 pbx-p-5">
          <div class="pbx-font-semibold pbx-text-lg">Familiar</div>
          <div class="pbx-mt-2 pbx-text-sm pbx-opacity-80">Momentos naturales y divertidos. 45–90 min.</div>
        </div>
        <div class="pbx-rounded pbx-border pbx-border-gray-200 pbx-p-5">
          <div class="pbx-font-semibold pbx-text-lg">Lifestyle</div>
          <div class="pbx-mt-2 pbx-text-sm pbx-opacity-80">Hotel, playa o experiencia local.</div>
        </div>
        <div class="pbx-rounded pbx-border pbx-border-gray-200 pbx-p-5">
          <div class="pbx-font-semibold pbx-text-lg">Maternidad</div>
          <div class="pbx-mt-2 pbx-text-sm pbx-opacity-80">Luz suave, poses naturales, estilo editorial.</div>
        </div>
        <div class="pbx-rounded pbx-border pbx-border-gray-200 pbx-p-5">
          <div class="pbx-font-semibold pbx-text-lg">Bodas / Civil</div>
          <div class="pbx-mt-2 pbx-text-sm pbx-opacity-80">Cobertura por horas + entrega digital.</div>
        </div>
        <div class="pbx-rounded pbx-border pbx-border-gray-200 pbx-p-5">
          <div class="pbx-font-semibold pbx-text-lg">Marca personal</div>
          <div class="pbx-mt-2 pbx-text-sm pbx-opacity-80">Fotos para redes, web y anuncios.</div>
        </div>
      </div>
    </div>
  </div>
</section>
        `.trim(),
    },
];

async function injectComponentToBuilder(componentObject) {
    const component = {
        id: null,
        html_code: componentObject.html_code,
        title: componentObject.title,
    };

    await pageBuilderService.addComponent(component);
    closeAddComponentModal();
}
</script>

<template>
    <div class="pbx-grid pbx-grid-cols-1 pbx-gap-2">
        <button
            v-for="t in templates"
            :key="t.title"
            type="button"
            class="pbx-w-full pbx-text-left pbx-rounded pbx-border pbx-border-gray-200 pbx-p-4 hover:pbx-border-black pbx-transition"
            @click="injectComponentToBuilder(t)"
        >
            <div class="pbx-font-semibold">{{ t.title }}</div>
            <div class="pbx-mt-1 pbx-text-sm pbx-opacity-70">
                Carrusel (scroll) + tarjetas con tipos de sesión.
            </div>
        </button>
    </div>
</template>

