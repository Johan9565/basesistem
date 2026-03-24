import { onMounted, onUnmounted } from 'vue';

/** Emite el layout tras validar destinatario (siempre). Incluye `scopedContextMatched`. */
export const NOTIFICACION_TO_USER_EVENT = 'app:notificacion-to-user';

/** Dispara el layout tras `router.reload` en contexto /profile para remarcar campos. */
export const PROFILE_HIGHLIGHT_FIELDS_EVENT = 'app:profile-highlight-fields';

/**
 * Escucha el detalle del broadcast ya autenticado por usuario destinatario.
 * `scopedContextMatched` indica si la URL actual coincide con `payload.currentPaths`
 * (acciones extra: inertia, remarcado en perfil).
 *
 * @param {(payload: Record<string, unknown> & { scopedContextMatched?: boolean }) => void} handler
 *
 * @example
 * useNotificacionToUser((payload) => {
 *   if (payload.scopedContextMatched && payload.meta?.reloadUsers) {
 *     router.reload({ only: ['users'], preserveScroll: true });
 *   }
 * });
 */
export function useNotificacionToUser(handler) {
    const listener = (e) => {
        handler(e.detail);
    };

    onMounted(() => {
        window.addEventListener(NOTIFICACION_TO_USER_EVENT, listener);
    });

    onUnmounted(() => {
        window.removeEventListener(NOTIFICACION_TO_USER_EVENT, listener);
    });
}
