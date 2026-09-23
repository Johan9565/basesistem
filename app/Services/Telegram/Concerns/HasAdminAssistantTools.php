<?php

namespace App\Services\Telegram\Concerns;

trait HasAdminAssistantTools
{
    /**
     * @return list<array<string, mixed>>
     */
    public function adminTools(): array
    {
        return [
            $this->listAppointmentsToolDefinition(),
            $this->agendaOccupancyToolDefinition(),
            $this->listPackagesToolDefinition(),
            $this->blockAvailabilityToolDefinition(),
            $this->cancelAppointmentToolDefinition(),
            $this->getBusinessInfoToolDefinition(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listAppointmentsToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_appointments',
                'description' => 'Lista citas de la instancia en un rango de fechas (agenda del negocio).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Fecha inicio YYYY-MM-DD (zona de la instancia).',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Fecha fin YYYY-MM-DD inclusive. Si omites, usa el mismo día que date_from.',
                        ],
                    ],
                    'required' => ['date_from'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function agendaOccupancyToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'agenda_occupancy',
                'description' => 'Resume qué tan llena está la agenda: cantidad de citas, minutos ocupados y carga aproximada.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'YYYY-MM-DD',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'YYYY-MM-DD inclusive',
                        ],
                    ],
                    'required' => ['date_from'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listPackagesToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_packages',
                'description' => 'Resume paquetes/sesiones agendadas en un rango (agrupa por día según el título de la cita).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'YYYY-MM-DD',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'YYYY-MM-DD inclusive',
                        ],
                    ],
                    'required' => ['date_from'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blockAvailabilityToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'block_availability',
                'description' => 'Marca un rango como no disponible (crea bloqueo en Google Calendar y en citas locales). Usar cuando el admin no estará disponible.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'starts_at' => [
                            'type' => 'string',
                            'description' => 'Inicio ISO local o YYYY-MM-DD HH:MM (zona de la instancia).',
                        ],
                        'ends_at' => [
                            'type' => 'string',
                            'description' => 'Fin ISO local o YYYY-MM-DD HH:MM. Si es día completo, usa fin del día.',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Motivo breve, ej. No disponible / viaje.',
                        ],
                    ],
                    'required' => ['starts_at', 'ends_at'],
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
                'description' => 'Cancela/elimina una cita por id local. También intenta borrarla de Google Calendar.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'appointment_id' => [
                            'type' => 'string',
                            'description' => 'ID de la cita local (campo id de list_appointments).',
                        ],
                        'delete_google' => [
                            'type' => 'boolean',
                            'description' => 'Si true (default), también elimina el evento de Google.',
                        ],
                    ],
                    'required' => ['appointment_id'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBusinessInfoToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_business_info',
                'description' => 'Devuelve catálogo configurado de la instancia (servicios, precios, horarios, ubicaciones, promociones).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }
}
