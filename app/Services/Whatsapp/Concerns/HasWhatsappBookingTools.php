<?php

namespace App\Services\Whatsapp\Concerns;

trait HasWhatsappBookingTools
{
    /**
     * Resuelve las herramientas disponibles según los módulos habilitados de la empresa.
     *
     * @return list<array<string, mixed>>
     */
    public function resolveToolsForCompany(?\App\Models\Company $company): array
    {
        $tools = $this->coreTools();

        // Módulo de citas
        if (! $company || $company->isModuleEnabled('appointments')) {
            $tools = array_merge($tools, $this->appointmentTools());
        }

        // Módulo de inventario / productos
        if ($company && $company->isModuleEnabled('inventory')) {
            $tools[] = $this->queryInventoryProductsToolDefinition();
        }

        // Módulo de pedidos (inherente a inventario)
        if (! $company || $company->isModuleEnabled('orders')) {
            $tools[] = $this->createOrderToolDefinition();
            $tools[] = $this->listMyOrdersToolDefinition();
        }

        // Módulo de servicios
        if ($company && $company->isModuleEnabled('services')) {
            $tools[] = $this->queryCompanyServicesToolDefinition();
        }

        // Envío de imágenes de productos y servicios por WhatsApp
        if (! $company || $company->isModuleEnabled('inventory') || $company->isModuleEnabled('services')) {
            $tools[] = $this->sendCatalogImageToolDefinition();
        }

        return $tools;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookingTools(): array
    {
        return $this->resolveToolsForCompany(null);
    }

    /**
     * Herramientas básicas de conversación.
     */
    public function coreTools(): array
    {
        return [
            $this->bookingStateToolDefinition(),
            $this->resetBookingToolDefinition(),
            $this->escalateToHumanToolDefinition(),
        ];
    }

    /**
     * Herramientas para gestión de citas y calendario.
     */
    public function appointmentTools(): array
    {
        return [
            $this->checkAvailabilityToolDefinition(),
            $this->calendarToolDefinition(),
            $this->listMyAppointmentsToolDefinition(),
            $this->rescheduleAppointmentToolDefinition(),
            $this->cancelAppointmentToolDefinition(),
        ];
    }

    /**
     * Herramienta para consultar catálogo y stock de productos en inventario.
     */
    public function queryInventoryProductsToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'query_inventory_products',
                'description' => 'Consulta el inventario de productos disponibles, stock y precios. Úsala cuando el cliente pregunte por disponibilidad de productos o precios específicos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Término de búsqueda o nombre del producto.',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Categoría opcional.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Herramienta para consultar el catálogo de servicios de la empresa.
     */
    public function queryCompanyServicesToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'query_company_services',
                'description' => 'Consulta el catálogo de servicios ofrecidos, duración y precios. Úsala cuando el cliente solicite información sobre los servicios.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Término de búsqueda o nombre del servicio.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Herramienta para enviar al cliente fotografías o imágenes de productos o servicios del catálogo.
     */
    public function sendCatalogImageToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'send_catalog_image',
                'description' => 'Envía al cliente de WhatsApp la fotografía o imagen oficial de un producto del inventario o servicio del catálogo. Úsala inmediatamente cuando el cliente solicite ver fotos, imágenes, catálogos visuales o cómo luce un producto o servicio.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name_or_sku' => [
                            'type' => 'string',
                            'description' => 'Nombre, término clave o SKU del producto o servicio cuya foto se desea enviar.',
                        ],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['product', 'service', 'any'],
                            'description' => 'Tipo de ítem ("product" para producto de inventario, "service" para servicio del catálogo, o "any" para buscar en ambos). Por defecto "any".',
                        ],
                        'caption' => [
                            'type' => 'string',
                            'description' => 'Pie de foto amigable y breve que acompañará a la imagen (ej: "Aquí tienes la foto del producto X").',
                        ],
                    ],
                    'required' => ['name_or_sku'],
                ],
            ],
        ];
    }

    /**
     * Herramienta para registrar pedidos de compra de productos descontando del inventario.
     */
    public function createOrderToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_order',
                'description' => 'Genera y registra formalmente una orden o pedido de productos en el sistema, descontando de forma inmediata el stock del inventario. Úsala cuando el cliente confirme explícitamente su compra tras haberle presentado el resumen y el total.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'description' => 'Lista de productos a comprar con sus cantidades.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'description' => 'Nombre o término del producto tal como figura en el catálogo de inventario.',
                                    ],
                                    'sku' => [
                                        'type' => 'string',
                                        'description' => 'SKU del producto si se conoce.',
                                    ],
                                    'product_id' => [
                                        'type' => 'string',
                                        'description' => 'ID del producto si se conoce.',
                                    ],
                                    'quantity' => [
                                        'type' => 'integer',
                                        'description' => 'Cantidad solicitada (mínimo 1).',
                                    ],
                                    'unit_price' => [
                                        'type' => 'number',
                                        'description' => 'Precio unitario pactado o del catálogo.',
                                    ],
                                ],
                                'required' => ['name', 'quantity'],
                            ],
                        ],
                        'customer_name' => [
                            'type' => 'string',
                            'description' => 'Nombre del cliente para la orden.',
                        ],
                        'delivery_address' => [
                            'type' => 'string',
                            'description' => 'Dirección de entrega o indicación de recogida en sucursal.',
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Observaciones o notas adicionales sobre el pedido.',
                        ],
                        'client_confirmed' => [
                            'type' => 'boolean',
                            'description' => 'true SOLO si el cliente confirmó explícitamente la compra tras conocer productos y total.',
                        ],
                    ],
                    'required' => ['items', 'client_confirmed'],
                ],
            ],
        ];
    }

    /**
     * Herramienta para consultar pedidos anteriores o activos del cliente.
     */
    public function listMyOrdersToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_my_orders',
                'description' => 'Consulta el historial de pedidos y compras recientes asociadas al cliente / teléfono actual en este negocio.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Número de pedido específico si el cliente lo indicó (ej. PED-00001).',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listMyAppointmentsToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_my_appointments',
                'description' => 'Lista citas FUTURAS de ESTE número de WhatsApp en la agenda. Úsala ANTES de cancelar o si el cliente pregunta qué citas tiene. No inventes citas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'include_past_hours' => [
                            'type' => 'integer',
                            'description' => 'Horas hacia atrás a incluir (default 0 = solo futuras). Máx 48.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelAppointmentToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'cancel_appointment',
                'description' => 'CANCELA una cita real (sistema + Google). PELIGROSO: úsala SOLO tras list_my_appointments, haber mostrado el resumen al cliente, y recibir un SÍ explícito en ESTE turno. Requiere client_confirmed=true y appointment_id que pertenezca a ESTE teléfono. NUNCA canceles por ambigüedad, ni la cita de otro número, ni varias a la vez.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'appointment_id' => [
                            'type' => 'string',
                            'description' => 'ID exacto devuelto por list_my_appointments.',
                        ],
                        'client_confirmed' => [
                            'type' => 'boolean',
                            'description' => 'true SOLO si el cliente confirmó explícitamente cancelar ESA cita (sí, confirma, cancelala).',
                        ],
                        'confirm_summary' => [
                            'type' => 'string',
                            'description' => 'Resumen breve de lo que se cancela (servicio + fecha/hora) para auditoría.',
                        ],
                    ],
                    'required' => ['appointment_id', 'client_confirmed'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rescheduleAppointmentToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'reschedule_appointment',
                'description' => 'REPROGRAMA una cita existente (sistema + Google) a una nueva fecha/hora. Úsala en lugar de cancelar y volver a crear: conserva el mismo appointment_id y el embudo del cliente. PELIGROSO: úsala SOLO tras list_my_appointments, haber mostrado el nuevo horario al cliente y recibir un SÍ explícito en ESTE turno. Requiere client_confirmed=true y un appointment_id que pertenezca a ESTE teléfono.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'appointment_id' => [
                            'type' => 'string',
                            'description' => 'ID exacto devuelto por list_my_appointments.',
                        ],
                        'start_iso' => [
                            'type' => 'string',
                            'description' => 'Nuevo inicio en ISO 8601 (ej. 2026-11-07T11:00:00).',
                        ],
                        'end_iso' => [
                            'type' => 'string',
                            'description' => 'Nuevo fin en ISO 8601. Si no hay duración, usa 30 minutos.',
                        ],
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Asunto o servicio de la cita. Si se omite, se conserva el actual.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Notas opcionales actualizadas.',
                        ],
                        'client_confirmed' => [
                            'type' => 'boolean',
                            'description' => 'true SOLO si el cliente confirmó explícitamente el nuevo horario en este turno.',
                        ],
                        'confirm_summary' => [
                            'type' => 'string',
                            'description' => 'Resumen breve del cambio (cita + horario anterior → nuevo) para auditoría.',
                        ],
                    ],
                    'required' => ['appointment_id', 'start_iso', 'end_iso', 'client_confirmed'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function calendarToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_calendar_event',
                'description' => 'Registra la cita en el sistema. Úsala SOLO en CONFIRMING con client_confirmed=true. NUNCA digas al cliente que la cita quedó confirmada hasta recibir ok=true de esta herramienta. Si ok=false (horario ocupado u otro error), informa el problema y ofrece otra hora.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Asunto o título de la cita (servicio/producto).',
                        ],
                        'start_iso' => [
                            'type' => 'string',
                            'description' => 'Inicio en ISO 8601 (ej. 2026-09-19T10:00:00).',
                        ],
                        'end_iso' => [
                            'type' => 'string',
                            'description' => 'Fin en ISO 8601. Si no hay duración, usa 30 minutos.',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Notas opcionales (nombre del cliente, teléfono, etc.).',
                        ],
                        'client_confirmed' => [
                            'type' => 'boolean',
                            'description' => 'true solo si el cliente confirmó el resumen en esta conversación.',
                        ],
                    ],
                    'required' => ['summary', 'start_iso', 'end_iso', 'client_confirmed'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkAvailabilityToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'check_availability',
                'description' => 'Consulta si un horario está libre en el sistema y Google Calendar ANTES de ofrecerlo como disponible o de intentar agendar. Obligatorio si dudas o el cliente pide un horario concreto.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'start_iso' => [
                            'type' => 'string',
                            'description' => 'Inicio en ISO 8601 (ej. 2026-09-30T09:00:00).',
                        ],
                        'end_iso' => [
                            'type' => 'string',
                            'description' => 'Fin en ISO 8601. Si se omite, se asumen 30 minutos.',
                        ],
                    ],
                    'required' => ['start_iso'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingStateToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'update_booking_state',
                'description' => 'Guarda progreso. Llama en el mismo turno en que el cliente da un dato. Si ya dijo nombre o servicio, guárdalos YA. Al actualizar solo date/time (tras check_availability), NO envíes name/service vacíos ni clear_fields. Si el tipo de sesión/paquete ya está o lo mencionó, guarda service y NO lo vuelvas a pedir. Usa step=COLLECTING al detectar intención.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'step' => [
                            'type' => 'string',
                            'description' => 'RECEPTION|COLLECTING|CONFIRMING|COMPLETED',
                        ],
                        'intent' => [
                            'type' => 'string',
                            'description' => 'buy|schedule|quote|info u otra intención detectada',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Nombre del cliente. Si corrige o da otro nombre, envía el nuevo y sobrescribe el anterior.',
                        ],
                        'service' => [
                            'type' => 'string',
                            'description' => 'Paquete o tipo de sesión elegido (ej. Flying Dron, SILVER PACKAGE). Si ya está en estado o lo mencionó, no lo pidas de nuevo.',
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Fecha YYYY-MM-DD',
                        ],
                        'time' => [
                            'type' => 'string',
                            'description' => 'Hora HH:MM (24h)',
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Ubicación completa: tipo de lugar + localidad/ciudad. Ej. "Playa pública, Puerto Morelos". No omitas la ciudad si el cliente la dijo (Cancún, Puerto Morelos, Playa del Carmen u otra).',
                        ],
                        'notes' => ['type' => 'string'],
                        'clear_fields' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Campos a borrar: name, service, date, time, location, notes, intent',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resetBookingToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'reset_booking',
                'description' => 'Limpia solo el embudo de conversación (nombre/servicio/fecha en estado) y vuelve a RECEPTION. NO elimina citas del calendario. Para cancelar una cita ya agendada usa list_my_appointments + cancel_appointment.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'cancel|restart|other',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function escalateToHumanToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'escalate_to_human',
                'description' => 'Detiene el bot y alerta al equipo. Úsalo de inmediato cuando no puedas contestar o realizar una solicitud, dudas fuera de catálogo, quejas, requerimientos de diseño complejos, o si el cliente pide una persona. NUNCA proporciones números telefónicos externos; indica al cliente que se va a revisar su solicitud y en breve se comunicarán con él por este mismo chat.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'complex_question|request_human|loop|out_of_catalog|cannot_fulfill',
                        ],
                        'detail' => [
                            'type' => 'string',
                            'description' => 'Breve motivo interno para el equipo.',
                        ],
                    ],
                    'required' => ['reason'],
                ],
            ],
        ];
    }
}
