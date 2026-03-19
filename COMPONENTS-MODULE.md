# Módulo de Componentes

Módulo para visualizar todos los componentes del sistema y editar sus estilos globalmente.

## Activar el módulo

1. **Ejecutar el seeder** (crea el permiso y el módulo en MongoDB):

```bash
php artisan db:seed --class=ComponentsModuleSeeder
```

2. **Asignar el permiso al rol**: Ir a **Roles** y marcar el permiso `components` para los roles que deben ver el módulo.

3. El enlace **Componentes** aparecerá en el menú de navegación.

## Uso

- **Catálogo**: Muestra todos los componentes (botones, inputs, navegación, etc.) con vista previa en vivo.
- **Editar estilos**: Clic en "Editar estilos globales" para modificar colores y variables CSS.
- Los cambios se guardan automáticamente y se aplican en **toda la aplicación**.

## Variables editables

| Variable | Descripción |
|----------|-------------|
| `--btn-primary-bg` | Fondo del botón primario |
| `--btn-primary-hover` | Fondo en hover |
| `--btn-primary-text` | Color del texto |
| `--btn-primary-ring` | Color del ring de focus |
| `--btn-secondary-*` | Botón secundario |
| `--btn-danger-*` | Botón de peligro |
| `--input-*` | Inputs de formulario |

## Archivos creados

- `app/Http/Controllers/ComponentsController.php`
- `app/Models/ComponentThemeModel.php`
- `resources/js/Pages/Components/Index.vue`
- `database/seeders/ComponentsModuleSeeder.php`
