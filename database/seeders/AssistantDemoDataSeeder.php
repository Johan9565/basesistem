<?php

namespace Database\Seeders;

use App\Models\AssistantMessage;
use App\Models\CalendarEvent;
use App\Models\NotificationsModel;
use App\Models\Reminder;
use App\Models\RoleModel;
use App\Models\User;
use App\Models\WorkspaceNote;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder de prueba para el Asistente Virtual.
 *
 * Llena el workspace del agente con datos realistas y coherentes entre sí:
 * - Notas / To-Dos / Compras (WorkspaceNote con tags de categorización)
 * - Recordatorios (Reminder: pendientes, vencidos y completados)
 * - Agenda / Citas (CalendarEvent: pasadas, hoy y futuras, algunas sincronizadas con Google)
 * - Conversaciones multi-turno en App y WhatsApp (AssistantMessage)
 * - Notificaciones (NotificationsModel)
 *
 * Uso:
 *   php artisan db:seed --class=AssistantDemoDataSeeder
 *
 * Por defecto crea/usa un usuario demo (demo.assistant@basesistem.test).
 * Para sembrar sobre un usuario existente:
 *   ASSISTANT_SEED_EMAIL=tu@correo.com php artisan db:seed --class=AssistantDemoDataSeeder
 */
class AssistantDemoDataSeeder extends Seeder
{
    protected string $timezone = 'America/Cancun';

    protected string $demoEmail = 'johan_palma45@hotmail.com';

    public function run(): void
    {
        $user = $this->resolveUser();
        $userId = (string) $user->id;

        // Limpieza dirigida: solo los datos de asistente del usuario objetivo,
        // para que el seeder sea re-ejecutable sin basura duplicada.
        $this->wipeAssistantData($userId);

        $this->command?->info("Sembrando datos de asistente para [{$user->name}] ({$user->email})...");

        $this->seedWorkspaceNotes($userId);
        $this->seedReminders($userId);
        $this->seedCalendarEvents($userId);
        $this->seedConversations($userId);
        $this->seedNotifications($userId);

        $this->command?->info('✔ Datos del asistente creados: notas, recordatorios, agenda, conversaciones y notificaciones.');
    }

    /**
     * Usuario demo o usuario indicado vía ASSISTANT_SEED_EMAIL.
     */
    protected function resolveUser(): User
    {
        $email = env('ASSISTANT_SEED_EMAIL') ?: $this->demoEmail;

        $user = User::where('email', $email)->first();

        if (!$user) {
            $role = RoleModel::where('name', 'usuario')->first();

            $user = User::create([
                'name'     => 'Demo Assistant',
                'email'    => $email,
                'password' => bcrypt('password'),
                'role_id'  => $role?->id,
                'status'   => 1,
                'active'   => 1,
            ]);
        }

        return $user;
    }

    protected function wipeAssistantData(string $userId): void
    {
        AssistantMessage::where('user_id', $userId)->delete();
        CalendarEvent::where('user_id', $userId)->delete();
        Reminder::where('user_id', $userId)->delete();
        WorkspaceNote::where('user_id', $userId)->delete();
        NotificationsModel::where('user_id', $userId)->delete();
    }

    /**
     * Notas de workspace: compras, pendientes tech, admin e ideas.
     * Los tags permiten que el orquestador las agrupe en
     * Desarrollo/Tech, Personal/Compras, Casa/Admin o General.
     */
    protected function seedWorkspaceNotes(string $userId): void
    {
        $notes = [
            // Personal/Compras
            ['Comprar paraguas', 'Comprar paraguas antes de que empiece la temporada de lluvias.', ['todo', 'compras'], false, -9],
            ['Comprar impermeable', 'Impermeable ligero para la lluvia de la ciudad.', ['todo', 'compras'], false, -9],
            ['Supermercado: frutas y verduras', "Lista del súper:\n- Plátano\n- Aguacate\n- Tomate\n- Leche\n- Huevos", ['todo', 'compras', 'comida'], false, -6],
            ['Farmacia: comprar vitaminas', 'Vitamina D y complejo B, las de la marca que recomendó el doctor.', ['todo', 'compras', 'salud'], false, -4],

            // Desarrollo/Tech
            ['Terminar módulo de autenticación', 'Falta el flujo de refresh token y los tests del endpoint de login.', ['todo', 'tech', 'desarrollo'], true, -8],
            ['Investigar bug de WebGPU en Safari', 'Los shaders no compilan en Safari 17. Reproducir con el demo de partículas.', ['todo', 'tech', 'webgpu'], false, -5],
            ['Actualizar dependencias del proyecto', 'Correr composer update y revisar breaking changes de Laravel.', ['todo', 'dev'], false, -3],
            ['Deploy de la API a producción', 'Tag v2.1.0 y despliegue del microservicio de notificaciones.', ['todo', 'programacion'], false, -2],

            // Casa/Admin
            ['Pagar factura de la luz (CFE)', 'Vence el fin de semana, pagar en línea antes del corte.', ['todo', 'admin', 'pago'], false, -7],
            ['Renovar póliza del seguro del auto', 'Cotizar con las 3 aseguradoras y comparar coberturas.', ['todo', 'admin', 'finanzas'], false, -5],
            ['Sacar cita con el contador', 'Para la declaración anual faltan los comprobantes de diciembre.', ['todo', 'admin', 'factura'], false, -3],

            // General / ideas
            ['Idea: resumen diario por WhatsApp', 'Automatizar el digest matutino con el orquestador y mandarlo por WhatsApp a las 7 am.', ['idea', 'general'], true, -6],
            ['Gimnasio: rutina de pecho y tríceps', "Martes y viernes:\n- Press banca 4x8\n- Aperturas 3x12\n- Fondos 3x10\n- Extensión de tríceps 3x12", ['salud', 'personal'], false, -4],
            ['Receta: pasta alla norma', 'Berenjena frita, tomate san marzano, albahaca y ricotta salata.', ['receta', 'comida'], false, -2],
        ];

        $base = Carbon::now($this->timezone);

        foreach ($notes as [$title, $content, $tags, $pinned, $daysAgo]) {
            WorkspaceNote::forceCreate([
                'user_id'   => $userId,
                'title'     => $title,
                'content'   => $content,
                'tags'      => $tags,
                'is_pinned' => $pinned,
                'created_at' => (clone $base)->subDays(abs($daysAgo))->subHours(rand(1, 20)),
                'updated_at' => (clone $base)->subDays(abs($daysAgo)),
            ]);
        }
    }

    /**
     * Recordatorios: pendientes (futuros y vencidos) y completados.
     */
    protected function seedReminders(string $userId): void
    {
        $now = Carbon::now($this->timezone);

        $reminders = [
            // title, when (Carbon), status, priority, channel
            ['Comprar paraguas en el centro', (clone $now)->addHours(3), 'pending', 'medium', 'whatsapp'],
            ['Tomar vitaminas', (clone $now)->addHour(), 'pending', 'low', 'app'],
            ['Reunión de seguimiento con el equipo', (clone $now)->addHours(5), 'pending', 'high', 'app'],
            ['Comprar medicamento para la alergia', (clone $now)->addHours(7), 'pending', 'medium', 'whatsapp'],
            ['Llamar al dentista para agendar cita', (clone $now)->addDay()->setTime(18, 0), 'pending', 'medium', 'whatsapp'],
            ['Backup del servidor de producción', (clone $now)->addDay()->setTime(2, 0), 'pending', 'low', 'app'],
            ['Pagar factura de la luz (CFE)', (clone $now)->addDays(2)->setTime(9, 0), 'pending', 'high', 'whatsapp'],
            ['Enviar reporte semanal al cliente', (clone $now)->addDays(3)->setTime(8, 0), 'pending', 'high', 'whatsapp'],
            ['Cena con Ana', (clone $now)->addDays(5)->setTime(19, 30), 'pending', 'medium', 'whatsapp'],
            ['Renovar póliza del seguro del auto', (clone $now)->addDays(9)->setTime(10, 0), 'pending', 'medium', 'whatsapp'],
            ['Renovar dominio del proyecto', (clone $now)->addDays(12)->setTime(11, 0), 'pending', 'low', 'whatsapp'],
            // Vencidos pero aún pendientes (para probar estilos de "overdue")
            ['Recoger laropa de la tintorería', (clone $now)->subDay()->setTime(17, 0), 'pending', 'medium', 'whatsapp'],
            ['Responder correo del banco', (clone $now)->subDays(2)->setTime(12, 0), 'pending', 'high', 'app'],
            // Completados
            ['Comprar pan', (clone $now)->subDay()->setTime(8, 30), 'completed', 'low', 'app'],
            ['Pagar internet', (clone $now)->subDays(3)->setTime(13, 0), 'completed', 'medium', 'whatsapp'],
            ['Confirmar cita del médico', (clone $now)->subDays(4)->setTime(9, 0), 'completed', 'high', 'whatsapp'],
            ['Comprar regalo de cumpleaños de Andrea', (clone $now)->subDays(6)->setTime(16, 0), 'completed', 'medium', 'whatsapp'],
        ];

        foreach ($reminders as [$title, $when, $status, $priority, $channel]) {
            Reminder::forceCreate([
                'user_id'      => $userId,
                'title'        => $title,
                'scheduled_at' => $when,
                'status'       => $status,
                'priority'     => $priority,
                'channel'      => $channel,
                'created_at'   => (clone $when)->subDays(rand(1, 6)),
                'updated_at'   => $when,
            ]);
        }
    }

    /**
     * Eventos de agenda: pasados (completed), de hoy y futuros (scheduled),
     * algunos sincronizados con Google Calendar.
     */
    protected function seedCalendarEvents(string $userId): void
    {
        $now = Carbon::now($this->timezone);

        $events = [
            // title, start, hours, description, location, status, synced
            ['Reunión de seguimiento con el equipo', (clone $now)->addHours(5), 1, 'Standup semanal de proyecto.', 'Sala de juntas', 'scheduled', true],
            ['Cita con el dentista', (clone $now)->addDays(2)->setTime(10, 0), 1, 'Revisión anual y limpieza.', 'Clínica San Felipe', 'scheduled', true],
            ['Clase de React (workshop)', (clone $now)->addDays(3)->setTime(19, 0), 2, 'Taller de hooks avanzados.', 'Academia Online', 'scheduled', false],
            ['Gym - día de pierna', (clone $now)->addDays(4)->setTime(7, 0), 2, 'Rutina de pierna y glúteo.', 'Smart Fit', 'scheduled', false],
            ['Cumpleaños de Andrea', (clone $now)->addDays(7)->setTime(18, 0), 4, 'Cena sorpresa.', 'Casa de Andrea', 'scheduled', true],
            ['Viaje a Mérida', (clone $now)->addDays(14)->setTime(6, 0), 3, 'Vuelo Cancún → Mérida, llevar pasaporte.', 'Aeropuerto Cancún', 'scheduled', false],
            ['Cita con el contador', (clone $now)->addDays(20)->setTime(12, 0), 1, 'Declaración anual.', 'Despacho contable', 'scheduled', false],
            ['Cena de aniversario', (clone $now)->addDays(10)->setTime(20, 0), 3, 'Reservación a nombre de Johan.', 'Restaurante La Pigua', 'scheduled', true],
            // Pasados
            ['Taller de React', (clone $now)->subDays(2)->setTime(19, 0), 2, 'Introducción a hooks.', 'Academia Online', 'completed', false],
            ['Cita con el optometrista', (clone $now)->subDays(5)->setTime(16, 0), 1, 'Cambio de graduación.', 'Óptica Visual', 'completed', true],
            ['Deploy v2.0 a producción', (clone $now)->subDays(7)->setTime(21, 0), 1, 'Release sin incidencias.', null, 'completed', false],
        ];

        foreach ($events as [$title, $start, $hours, $description, $location, $status, $synced]) {
            CalendarEvent::forceCreate([
                'user_id'        => $userId,
                'title'          => $title,
                'start_time'     => $start,
                'end_time'       => (clone $start)->addHours($hours),
                'description'    => $description,
                'location'       => $location,
                'status'         => $status,
                'google_event_id'=> $synced ? 'demo_gcal_' . Str::lower(Str::random(16)) : null,
                'synced_at'      => $synced ? (clone $start)->subDays(rand(1, 3)) : null,
                'created_at'     => (clone $start)->subDays(rand(2, 10)),
                'updated_at'     => (clone $start)->subDays(rand(1, 5)),
            ]);
        }
    }

    /**
     * Conversaciones multi-turno realistas (App y WhatsApp),
     * enlazadas con los recordatorios/eventos/notas creados arriba.
     */
    protected function seedConversations(string $userId): void
    {
        $now = Carbon::now($this->timezone);

        $conversations = [
            [
                // Conversación 1: App - pendientes y nueva nota de compras
                'channel' => 'app',
                'turns'   => [
                    ['Hola, ¿cómo estás?', '¡Hola, Johan! Todo listo por aquí. ¿En qué te puedo ayudar hoy?', 'conversation', -2],
                    ['¿Qué tengo pendiente hoy?', "**Personal/Compras:**\n- Comprar paraguas\n- Farmacia: comprar vitaminas\n\n**Desarrollo/Tech:**\n- Terminar módulo de autenticación\n- Deploy de la API a producción\n\n**Casa/Admin:**\n- Pagar factura de la luz (CFE)", 'get_pending_reminders', -2],
                    ['Anota que debo comprar tomates y aguacate', 'Anotados en pendientes: 1. Comprar tomates. 2. Comprar aguacate.', 'create_multiple', -2],
                    ['Gracias, eso es todo por ahora', '¡Listo! Aquí ando si necesitas algo más.', 'conversation', -2],
                ],
            ],
            [
                // Conversación 2: WhatsApp - agendar cita dentista
                'channel' => 'whatsapp',
                'turns'   => [
                    ['Agéndame cita con el dentista el viernes a las 10', 'Evento Cita con el dentista agendado para el viernes a las 10:00.', 'create_calendar_event', -1],
                    ['Perfecto, gracias', '¡De nada! Te recordaré un día antes.', 'conversation', -1],
                ],
            ],
            [
                // Conversación 3: App - recordatorio + agenda semanal
                'channel' => 'app',
                'turns'   => [
                    ['Recuérdame pagar la luz el jueves a las 9 de la mañana', 'Recordatorio Pagar factura de la luz (CFE) programado para el jueves a las 9:00.', 'create_reminder', -1],
                    ['¿Cómo va mi agenda de esta semana?', "**Esta semana tienes 4 eventos:\n- Reunión de seguimiento con el equipo\n- Cita con el dentista\n- Clase de React (workshop)\n- Gym - día de pierna\n\nAdemás tienes recordatorios pendientes de alta prioridad.", 'get_agenda', 0],
                ],
            ],
            [
                // Conversación 4: WhatsApp - fallo de proceso y reintento
                'channel' => 'whatsapp',
                'turns'   => [
                    ['Cancela la cita del optometrista', 'No pude procesar tu solicitud en este momento. Por favor intenta de nuevo.', 'error', -3, 'failed', 'Timeout al contactar el servicio de calendario', 1],
                    ['Cancela la cita del optometrista', 'Listo, eliminé el evento Cita con el optometrista de tu agenda.', 'conversation', -3, 'completed', null, 1],
                ],
            ],
            [
                // Conversación 5: App - conversación general reciente
                'channel' => 'app',
                'turns'   => [
                    ['Buenos días', '¡Buenos días, Johan! ☀️ Hoy tienes la Reunión de seguimiento con el equipo más tarde. ¿Algo en que te pueda ayudar?', 'conversation', 0],
                    ['Ideas para automatizar el resumen diario', 'Puedo generar tu resumen matutino con los eventos y recordatorios del día y enviarlo por WhatsApp a la hora que prefieras. Solo dime "recuérdame enviar el resumen a las 7 am" y lo dejo programado.', 'conversation', 0],
                ],
            ],
        ];

        foreach ($conversations as $conv) {
            $conversationId = (string) Str::uuid();

            foreach ($conv['turns'] as $turn) {
                [$prompt, $reply, $action, $daysAgo] = $turn;
                $status = $turn[4] ?? 'completed';
                $error  = $turn[5] ?? null;
                $retry  = $turn[6] ?? 0;

                $createdAt = (clone $now)
                    ->subDays(abs($daysAgo))
                    ->subMinutes(rand(5, 240));

                AssistantMessage::forceCreate([
                    'user_id'         => $userId,
                    'conversation_id' => $conversationId,
                    'channel'         => $conv['channel'],
                    'direction'       => 'inbound',
                    'role'            => 'user',
                    'content'         => $prompt,
                    'prompt'          => $prompt,
                    'reply'           => $reply,
                    'display_text'    => $reply,
                    'speech_text'     => strip_tags($reply),
                    'action'          => $action,
                    'status'          => $status,
                    'retry_count'     => $retry,
                    'error_message'   => $error,
                    'metadata'        => [
                        'conversation_id' => $conversationId,
                        ($conv['channel'] === 'whatsapp' ? 'phone' : 'source') =>
                            $conv['channel'] === 'whatsapp' ? '+5219980000000' : 'web',
                    ],
                    'created_at'      => $createdAt,
                    'updated_at'      => (clone $createdAt)->addSeconds(rand(2, 30)),
                ]);

                if (!empty($reply)) {
                    AssistantMessage::forceCreate([
                        'user_id'         => $userId,
                        'conversation_id' => $conversationId,
                        'channel'         => $conv['channel'],
                        'direction'       => 'outbound',
                        'role'            => 'assistant',
                        'content'         => $reply,
                        'display_text'    => $reply,
                        'speech_text'     => strip_tags($reply),
                        'action'          => $action,
                        'status'          => $status,
                        'metadata'        => [
                            'conversation_id' => $conversationId,
                        ],
                        'created_at'      => (clone $createdAt)->addSeconds(2),
                        'updated_at'      => (clone $createdAt)->addSeconds(4),
                    ]);
                }
            }
        }
    }

    /**
     * Notificaciones vinculadas a recordatorios procesados y al digest diario.
     */
    protected function seedNotifications(string $userId): void
    {
        $now = Carbon::now($this->timezone);

        $panReminder = Reminder::where('user_id', $userId)
            ->where('title', 'Comprar pan')
            ->first();

        $notifications = [
            ['☀️ Buenos días, Johan. Hoy tienes la Reunión de seguimiento con el equipo a las ' . $now->format('H') . ':00.', (clone $now)->subHours(6), false, null],
            ['⏰ Recordatorio: Comprar pan', (clone $now)->subDay()->setTime(8, 30), true, $panReminder ? (string) $panReminder->id : null],
            ['✅ Tarea completada: Confirmar cita del médico', (clone $now)->subDays(4)->setTime(9, 5), true, null],
            ['⏰ Recordatorio: Pagar internet', (clone $now)->subDays(3)->setTime(13, 0), true, null],
            ['📅 Cita mañana: Cita con el dentista a las 10:00', (clone $now)->subDays(2)->setTime(9, 0), false, null],
        ];

        foreach ($notifications as [$message, $when, $read, $itemId]) {
            NotificationsModel::forceCreate([
                'user_id'  => $userId,
                'message'  => $message,
                'item_id'  => $itemId,
                'is_read'  => $read,
                'read_at'  => $read ? (clone $when)->addMinutes(30) : null,
                'routes'   => ['name' => 'assistant.workspace'],
                'created_at' => $when,
                'updated_at' => $when,
            ]);
        }
    }
}
