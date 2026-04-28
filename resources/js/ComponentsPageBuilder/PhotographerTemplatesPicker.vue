<script setup>
import { ref } from 'vue';
import { getPageBuilder } from '@myissue/vue-website-page-builder';

const pageBuilderService = getPageBuilder();
const open = ref(false);

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
            <a class="pbx-inline-flex pbx-items-center pbx-justify-center pbx-rounded pbx-px-5 pbx-py-3 pbx-bg-black pbx-text-white" href="#">
              Reservar
            </a>
            <a class="pbx-inline-flex pbx-items-center pbx-justify-center pbx-rounded pbx-px-5 pbx-py-3 pbx-border pbx-border-gray-300" href="#tipos">
              Ver tipos de sesión
            </a>
          </div>
        </div>

        <!-- Carrusel (sin JS): horizontal scroll + scroll-snap -->
        <div>
          <div class="pbx-rounded pbx-overflow-hidden pbx-border pbx-border-gray-200">
            <div style="display:flex; overflow-x:auto; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; gap:12px; padding:12px;">
              <img class="pbx-rounded pbx-object-cover" style="scroll-snap-align:start; width:100%; max-width:520px; aspect-ratio: 16 / 10;" src="" alt="Slide 1" />
              <img class="pbx-rounded pbx-object-cover" style="scroll-snap-align:start; width:100%; max-width:520px; aspect-ratio: 16 / 10;" src="" alt="Slide 2" />
              <img class="pbx-rounded pbx-object-cover" style="scroll-snap-align:start; width:100%; max-width:520px; aspect-ratio: 16 / 10;" src="" alt="Slide 3" />
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
      </div>
    </div>
  </div>
</section>
        `.trim(),
    },
];

async function insertTemplate(t) {
    await pageBuilderService.addComponent({
        id: null,
        title: t.title,
        html_code: t.html_code,
    });
    open.value = false;
}
</script>

<template>
    <div>
        <button type="button" class="btn" @click="open = true">Plantillas</button>

        <dialog class="modal" :open="open">
            <div class="modal-box">
                <h3 class="font-semibold text-lg">Plantillas</h3>
                <p class="text-sm opacity-70 mt-1">Inserta un bloque completo en tu landing.</p>

                <div class="mt-4 space-y-2">
                    <button
                        v-for="t in templates"
                        :key="t.title"
                        type="button"
                        class="btn btn-ghost w-full justify-start"
                        @click="insertTemplate(t)"
                    >
                        {{ t.title }}
                    </button>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn" @click="open = false">Cerrar</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop" @submit.prevent="open = false">
                <button>close</button>
            </form>
        </dialog>
    </div>
</template>

