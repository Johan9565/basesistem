# Migración a Tailwind CSS v4 + DaisyUI v5

Documentación de los cambios realizados para usar Tailwind CSS v4 y DaisyUI v5 en este proyecto Laravel con Inertia.js y Vue 3.

---

## Resumen de la migración

| Antes (v3) | Después (v4) |
|------------|--------------|
| Tailwind CSS 3.x | Tailwind CSS 4.x |
| DaisyUI 4.x | DaisyUI 5.x |
| `tailwind.config.js` | Configuración en CSS (`app.css`) |
| Directivas `@tailwind base/components/utilities` | `@import "tailwindcss"` |
| PostCSS + tailwindcss plugin | Plugin `@tailwindcss/vite` |
| `postcss.config.js` | **Eliminado** (no necesario) |

---

## 1. Dependencias (package.json)

### Cambios en devDependencies

```json
{
  "devDependencies": {
    "@tailwindcss/vite": "^4.0.0",
    "@vitejs/plugin-vue": "^6.0.0",
    "tailwindcss": "^4.0.0",
    "vite": "^7.0.7"
  }
}
```

**Eliminadas:**
- `@tailwindcss/forms` — No compatible con Tailwind v4 (opcional: migrar estilos manualmente si se necesitan)

**Actualizadas:**
- `tailwindcss`: `^3.2.1` → `^4.0.0`
- `@vitejs/plugin-vue`: `^5.0.0` → `^6.0.0` (requerido para compatibilidad con Vite 7)

### Cambios en dependencies

```json
{
  "dependencies": {
    "daisyui": "^5.0.0"
  }
}
```

**Actualizada:**
- `daisyui`: `^4.x` → `^5.0.0`

### Instalación

```bash
npm install
# O con Laravel Sail:
sail npm install
```

---

## 2. Vite (vite.config.js)

Se añade el plugin de Tailwind **antes** del plugin de Laravel:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: 'resources/js/app.js',
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
```

---

## 3. CSS (resources/css/app.css)

La configuración se hace directamente en el archivo CSS. **Reemplazar todo el contenido** por:

```css
@import "tailwindcss";

@source "../views/**/*.blade.php";
@source "../js/**/*.js";
@source "../js/**/*.vue";
@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php";
@source "../../storage/framework/views/*.php";

@plugin "daisyui" {
    themes: dark --default, light;
    logs: false;
}

@theme {
    --font-sans: "Figtree", ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
}
```

### Explicación

| Directiva | Descripción |
|-----------|-------------|
| `@import "tailwindcss"` | Sustituye a `@tailwind base`, `@tailwind components`, `@tailwind utilities` |
| `@source` | Define dónde buscar clases (equivalente a `content` en tailwind.config.js) |
| `@plugin "daisyui"` | Configura DaisyUI con temas y opciones |
| `@theme` | Personalización de variables (fuentes, colores, etc.) |

### Opciones de DaisyUI

```css
@plugin "daisyui" {
    themes: dark --default, light;  /* dark por defecto, light disponible */
    /* themes: light --default, dark --prefersdark;  /* light por defecto, dark con prefers-color-scheme */
    /* themes: all;  /* todos los temas */
    logs: false;  /* silenciar logs en consola */
}
```

---

## 4. Archivos eliminados

- **`tailwind.config.js`** — La configuración se hace en `app.css`
- **`postcss.config.js`** — No es necesario; `@tailwindcss/vite` procesa todo (incluido autoprefixer). Mantenerlo causaba el error: `@layer base is used but no matching @tailwind base directive is present`

---

## 5. Clases renombradas (Tailwind v4)

Algunas utilidades cambiaron de nombre. Actualizar en los componentes Vue/Blade:

| v3 | v4 |
|----|-----|
| `focus:outline-none` | `focus:outline-hidden` |
| `shadow-sm` | `shadow-xs` |
| `shadow` | `shadow-sm` |
| `rounded-sm` | `rounded-xs` |
| `rounded` | `rounded-sm` |
| `ring` (3px) | `ring-3` |
| `outline-none` | `outline-hidden` |

---

## 6. Tema oscuro (data-theme)

En `resources/views/app.blade.php`, el atributo `data-theme` controla el tema de DaisyUI:

```html
<html lang="..." data-theme="dark">
```

Valores posibles: `light`, `dark`, o cualquier tema definido en `@plugin "daisyui"`.

---

## 7. Comandos

```bash
# Desarrollo
npm run dev
# o
sail npm run dev

# Build producción
npm run build
# o
sail npm run build
```

---

## 8. Problemas conocidos y soluciones

### Error: `@layer base is used but no matching @tailwind base directive is present`

**Causa:** PostCSS procesando el CSS y entrando en conflicto con DaisyUI.

**Solución:** Eliminar `postcss.config.js`. Con `@tailwindcss/vite` no hace falta PostCSS.

---

### Error: `ERESOLVE could not resolve` (conflicto vite / plugin-vue)

**Causa:** `@vitejs/plugin-vue` v5 no soporta Vite 7.

**Solución:** Actualizar a `@vitejs/plugin-vue` ^6.0.0.

---

### DaisyUI no aplica estilos

**Causa:** DaisyUI v5 requiere Tailwind v4. Con Tailwind v3 no funciona.

**Solución:** Comprobar que `tailwindcss` y `daisyui` están en las versiones indicadas en este documento.

---

## 9. Referencias

- [Tailwind CSS v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)
- [DaisyUI v5 Install](https://daisyui.com/docs/install/)
- [DaisyUI Config](https://daisyui.com/docs/config/)
