# Multi-Agent Workflow Guidelines

## Roles & Responsibilities
- **@planner**: Encargado de desglosar issues complejos. Debe entregar una lista de archivos a modificar y contratos de API antes de iniciar cambios.
- **@backend-dev**: Implementa endpoints, modelos, migraciones y lógica de negocio. Debe respetar estrictamente los FormRequests y tipado.
- **@frontend-dev**: Implementa componentes UI, manejo de estado reactivo y consumo de APIs según el contrato del @planner.
- **@qa-tester**: Ejecuta el suite de linters y tests tras cada cambio de código. Si hay fallo, regresa el reporte al desarrollador asignado.

## Multi-Agent Rules
1. Ningún agente puede dar una tarea por cerrada sin que @qa-tester verifique linters y tests en verde.
2. Cada agente debe reportar los archivos modificados con una breve justificación en su entrega.
3. Las dependencias externas o paquetes nuevos requieren confirmación explícita del usuario.

Commits pequeños por agente: Si el flujo permite interactuar con Git, haz que cada agente guarde checkpoints o commits atómicos tras completar su subtarea. Si un agente posterior introduce un bug, el rollback es inmediato.

Mantén los contratos explícitos: Si el agente Backend cambia el payload de una respuesta JSON, ese cambio debe actualizarse en el plan compartido para que el agente Frontend no trabaje con suposiciones obsoletas.

Pruebas automáticas como árbitro: El agente QA no debe "opinar", debe ejecutar scripts concretos en tu terminal integrada o contenedor (linters, pruebas unitarias, build de Vite/Webpack). La salida del comando (código 0 o error) es la verdad absoluta para autorizar el avance.    