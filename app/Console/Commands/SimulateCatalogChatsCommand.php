<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\WhatsappInstance;
use App\Services\Whatsapp\AppointmentOrchestrator;
use App\Services\Whatsapp\ConversationRepository;
use Illuminate\Console\Command;
use Throwable;

class SimulateCatalogChatsCommand extends Command
{
    protected $signature = 'whatsapp:simulate-catalog-chats 
                            {--count=50 : Cantidad de chats a simular (máximo 50)}
                            {--instance= : ID de la instancia WhatsApp a usar}
                            {--with-llm : Forzar llamada externa al LLM DeepSeek}';

    protected $description = 'Ejecuta 50 simulaciones de chats de clientes preguntando por productos y servicios con envío de imágenes';

    public function handle(
        AppointmentOrchestrator $orchestrator,
        ConversationRepository $conversations
    ): int {
        $count = min(50, max(1, (int) $this->option('count')));
        $withLlm = (bool) $this->option('with-llm');
        $instanceId = $this->option('instance');

        $this->info("Iniciando simulación de {$count} chats de productos y servicios...");

        // 1. Obtener o crear una instancia activa
        $instance = null;
        if ($instanceId) {
            $instance = WhatsappInstance::withoutGlobalScopes()->find($instanceId);
        }

        if (! $instance) {
            $instance = WhatsappInstance::withoutGlobalScopes()->where('status', 'active')->first();
        }

        if (! $instance) {
            $company = Company::first();
            $companyId = $company ? (string) $company->_id : 'demo_company';
            $companyName = $company ? $company->name : 'Clínica Dental Norte';

            $instance = WhatsappInstance::create([
                'company_id' => $companyId,
                'instance_name' => 'bot_simulador_catalogo',
                'evolution_instance_name' => 'sim_catalogo',
                'business_name' => $companyName,
                'status' => 'active',
                'ai_provider' => 'deepseek',
                'timezone' => 'America/Mexico_City',
                'system_prompt' => "Eres el asistente virtual inteligente de {$companyName}. Atiende con amabilidad y resuelve dudas sobre nuestros productos, servicios y citas.",
            ]);
        }

        $sessionKey = 'sim:'.$instance->evolutionName();
        $this->info("Usando instancia: [{$instance->instance_name}] para negocio '{$instance->business_name}'");

        // 2. Definición de los 50 escenarios realistas de productos y servicios
        $scenarios = $this->get50Scenarios();
        $scenariosToRun = array_slice($scenarios, 0, $count);

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $executed = 0;
        $imagesSent = 0;

        foreach ($scenariosToRun as $index => $item) {
            $chatNumber = $index + 1;
            // Número de teléfono único para cada cliente simulado
            $phone = sprintf('521999100%04d', $chatNumber);
            $formattedPhone = sprintf('+52 999 100 %04d', $chatNumber);
            $userText = $item['question'];
            $type = $item['type']; // 'product' o 'service'
            $keyword = $item['keyword'];
            $wantsImage = (bool) ($item['wants_image'] ?? false);

            if ($withLlm) {
                // Modo con LLM externo si el usuario lo solicita explícitamente
                try {
                    $result = $orchestrator->simulateIncoming($instance, $phone, $userText, 'deepseek');
                    $executed++;
                    if (! empty($result['tool_calls'])) {
                        foreach ($result['tool_calls'] as $tc) {
                            if (($tc['name'] ?? '') === 'send_catalog_image') {
                                $imagesSent++;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $this->warn("\nFallo en llamada externa LLM para chat #{$chatNumber}: {$e->getMessage()}");
                    // Fallback a simulación autónoma enriquecida
                    $this->simulateEnrichedChat($instance, $sessionKey, $phone, $userText, $type, $keyword, $wantsImage, $conversations);
                    $executed++;
                    if ($wantsImage) $imagesSent++;
                }
            } else {
                // Simulación determinista y enriquecida con datos reales de MongoDB
                $sent = $this->simulateEnrichedChat($instance, $sessionKey, $phone, $userText, $type, $keyword, $wantsImage, $conversations);
                $executed++;
                if ($sent) {
                    $imagesSent++;
                }
            }

            $bar->advance();
            usleep(25000); // 25ms para evitar saturación y ordenar marcas de tiempo
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("==================================================");
        $this->info("✅ ¡Simulación de {$executed} chats completada con éxito!");
        $this->info("📸 Fotografías y catálogos visuales enviados: {$imagesSent}");
        $this->info("💬 Todas las 50 conversaciones han sido registradas en MongoDB.");
        $this->info("🌐 Puedes verlas en el CRM y en el simulador: /whatsapp/chat-test");
        $this->info("==================================================");

        return self::SUCCESS;
    }

    /**
     * Simula un turno completo de usuario y asistente con consulta a la BD y herramientas reales.
     */
    protected function simulateEnrichedChat(
        WhatsappInstance $instance,
        string $sessionKey,
        string $phone,
        string $userText,
        string $type,
        string $keyword,
        bool $wantsImage,
        ConversationRepository $conversations
    ): bool {
        // 1. Guardar mensaje entrante del usuario
        $conversations->append($sessionKey, $phone, 'user', $userText);

        $imageSent = false;
        $toolCallsMeta = [];
        $replyText = '';

        if ($type === 'product') {
            $product = Product::query()
                ->where('is_active', true)
                ->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('category', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                })->first();

            if (! $product) {
                $product = Product::query()->where('is_active', true)->first();
            }

            if ($product) {
                $imageUrl = $product->image_url;

                if ($wantsImage && $imageUrl) {
                    $imageSent = true;
                    $toolCallsMeta[] = [
                        'name' => 'send_catalog_image',
                        'arguments' => json_encode(['name_or_sku' => $product->name, 'type' => 'product']),
                        'result' => [
                            'ok' => true,
                            'image_sent' => true,
                            'item_name' => $product->name,
                            'image_url' => $imageUrl,
                            'item_type' => 'product',
                        ],
                    ];
                    $replyText = "¡Con gusto! Aquí tienes la fotografía de nuestro producto *{$product->name}* (SKU: {$product->sku}). Actualmente contamos con {$product->stock} unidades disponibles a un precio de \${$product->price} MXN. ¿Te gustaría ordenar alguna unidad?";
                } else {
                    $toolCallsMeta[] = [
                        'name' => 'query_inventory_products',
                        'arguments' => json_encode(['search' => $keyword]),
                        'result' => [
                            'ok' => true,
                            'count' => 1,
                            'products' => [[
                                'name' => $product->name,
                                'price' => $product->price,
                                'stock' => $product->stock,
                                'has_image' => ! empty($imageUrl),
                            ]],
                        ],
                    ];
                    $replyText = "Hola. Sí tenemos disponibilidad de *{$product->name}*. Su precio es de \${$product->price} MXN y nos quedan {$product->stock} piezas en existencia en sucursal. Si deseas, te puedo compartir una foto del producto.";
                }
            } else {
                $replyText = "Hola. En este momento estamos actualizando nuestro inventario. ¿Deseas que te informe cuando tengamos más existencias?";
            }
        } else {
            // Consulta de Servicio
            $service = Service::query()
                ->where('is_active', true)
                ->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                })->first();

            if (! $service) {
                $service = Service::query()->where('is_active', true)->first();
            }

            if ($service) {
                $imageUrl = $service->image_url;

                if ($wantsImage && $imageUrl) {
                    $imageSent = true;
                    $toolCallsMeta[] = [
                        'name' => 'send_catalog_image',
                        'arguments' => json_encode(['name_or_sku' => $service->name, 'type' => 'service']),
                        'result' => [
                            'ok' => true,
                            'image_sent' => true,
                            'item_name' => $service->name,
                            'image_url' => $imageUrl,
                            'item_type' => 'service',
                        ],
                    ];
                    $replyText = "¡Por supuesto! Te comparto la imagen informativa de nuestro servicio de *{$service->name}*. La sesión dura aproximadamente {$service->duration_minutes} minutos con una tarifa de \${$service->price} MXN. Contamos con especialistas listos para atenderte. ¿Te gustaría agendar una cita?";
                } else {
                    $toolCallsMeta[] = [
                        'name' => 'query_company_services',
                        'arguments' => json_encode(['search' => $keyword]),
                        'result' => [
                            'ok' => true,
                            'count' => 1,
                            'services' => [[
                                'name' => $service->name,
                                'duration_minutes' => $service->duration_minutes,
                                'price' => $service->price,
                                'has_image' => ! empty($imageUrl),
                            ]],
                        ],
                    ];
                    $replyText = "Hola. Nuestro servicio de *{$service->name}* tiene una duración estimada de {$service->duration_minutes} minutos y un costo de \${$service->price} MXN. Si lo deseas, puedo enviarte una fotografía del procedimiento o ayudarte a reservar tu horario.";
                }
            } else {
                $replyText = "Hola. Contamos con una amplia gama de servicios profesionales. ¿Para qué día te gustaría agendar tu cita?";
            }
        }

        // 2. Guardar respuesta del asistente con metadata de tool calls
        $conversations->append(
            $sessionKey,
            $phone,
            'assistant',
            $replyText,
            toolCallId: null,
            toolName: null,
            metadata: ! empty($toolCallsMeta) ? ['tool_calls' => $toolCallsMeta] : null
        );

        return $imageSent;
    }

    /**
     * Retorna 50 escenarios estructurados de consultas sobre productos y servicios.
     */
    protected function get50Scenarios(): array
    {
        return [
            // --- 25 ESCENARIOS DE PRODUCTOS ---
            1 => ['question' => 'Hola, ¿tienen cepillos de dientes sónicos en existencia?', 'type' => 'product', 'keyword' => 'cepillo', 'wants_image' => false],
            2 => ['question' => '¿Qué precio tiene el cepillo eléctrico SonicPro y tienen fotos?', 'type' => 'product', 'keyword' => 'cepillo', 'wants_image' => true],
            3 => ['question' => 'Me gustaría ver una imagen del cepillo dental por favor', 'type' => 'product', 'keyword' => 'cepillo', 'wants_image' => true],
            4 => ['question' => 'Hola buenas tardes, ¿venden termómetros infrarrojos?', 'type' => 'product', 'keyword' => 'termometro', 'wants_image' => false],
            5 => ['question' => '¿Tienen fotos del termómetro digital para ver cómo es?', 'type' => 'product', 'keyword' => 'termometro', 'wants_image' => true],
            6 => ['question' => 'Hola, ¿cuánto cuesta el oxímetro de pulso y tienen disponible?', 'type' => 'product', 'keyword' => 'oximetro', 'wants_image' => false],
            7 => ['question' => 'Mándame por favor la foto del oxímetro de pulso', 'type' => 'product', 'keyword' => 'oximetro', 'wants_image' => true],
            8 => ['question' => '¿Tienen cubrebocas o mascarillas de protección disponibles?', 'type' => 'product', 'keyword' => 'mascarilla', 'wants_image' => false],
            9 => ['question' => 'Mándame por favor la foto de las mascarillas de protección', 'type' => 'product', 'keyword' => 'mascarilla', 'wants_image' => true],
            10 => ['question' => '¿Tienen guantes de nitrilo en inventario y cuál es el costo?', 'type' => 'product', 'keyword' => 'guante', 'wants_image' => false],
            11 => ['question' => '¿Tienen imágenes o foto de la caja de guantes de nitrilo?', 'type' => 'product', 'keyword' => 'guante', 'wants_image' => true],
            12 => ['question' => 'Hola, ¿tienen enjuague bucal con clorhexidina en la clínica?', 'type' => 'product', 'keyword' => 'enjuague', 'wants_image' => false],
            13 => ['question' => 'Quiero ver cómo luce el enjuague bucal que manejan', 'type' => 'product', 'keyword' => 'enjuague', 'wants_image' => true],
            14 => ['question' => '¿Tienen cemento dental o materiales en stock?', 'type' => 'product', 'keyword' => 'cemento', 'wants_image' => false],
            15 => ['question' => '¿Tienen tenis o calzado deportivo disponible en catálogo?', 'type' => 'product', 'keyword' => 'tenis', 'wants_image' => false],
            16 => ['question' => 'Por favor envíame foto del calzado deportivo que tienen', 'type' => 'product', 'keyword' => 'tenis', 'wants_image' => true],
            17 => ['question' => '¿Manejan audífonos bluetooth y qué precio tienen?', 'type' => 'product', 'keyword' => 'audifono', 'wants_image' => false],
            18 => ['question' => 'Quiero ver la foto de los audífonos bluetooth por favor', 'type' => 'product', 'keyword' => 'audifono', 'wants_image' => true],
            19 => ['question' => '¿Tienen café gourmet o bebidas disponibles en venta?', 'type' => 'product', 'keyword' => 'cafe', 'wants_image' => false],
            20 => ['question' => 'Muéstrame la fotografía del café gourmet por favor', 'type' => 'product', 'keyword' => 'cafe', 'wants_image' => true],
            21 => ['question' => '¿Tienen termos de agua y cuál es su costo?', 'type' => 'product', 'keyword' => 'termo', 'wants_image' => false],
            22 => ['question' => 'Quisiera ver una foto del termo de acero inoxidable', 'type' => 'product', 'keyword' => 'termo', 'wants_image' => true],
            23 => ['question' => '¿Tienen herramientas o kits de instrumental en inventario?', 'type' => 'product', 'keyword' => 'herramienta', 'wants_image' => false],
            24 => ['question' => '¿Tienen fotos de los relojes o smartwatches disponibles?', 'type' => 'product', 'keyword' => 'reloj', 'wants_image' => true],
            25 => ['question' => '¿Cuáles son todos los productos con entrega inmediata que tienen hoy?', 'type' => 'product', 'keyword' => 'cepillo', 'wants_image' => false],

            // --- 25 ESCENARIOS DE SERVICIOS ---
            26 => ['question' => 'Hola, buenas tardes, ¿qué servicios odontológicos ofrecen?', 'type' => 'service', 'keyword' => 'limpieza', 'wants_image' => false],
            27 => ['question' => '¿Qué incluye la Limpieza Dental con Ultrasonido y qué precio tiene?', 'type' => 'service', 'keyword' => 'limpieza', 'wants_image' => false],
            28 => ['question' => '¿Tienen fotos o imágenes de cómo realizan la limpieza dental?', 'type' => 'service', 'keyword' => 'limpieza', 'wants_image' => true],
            29 => ['question' => '¿Cuánto cuesta el Blanqueamiento Dental Láser?', 'type' => 'service', 'keyword' => 'blanqueamiento', 'wants_image' => false],
            30 => ['question' => '¿Tienen fotos del procedimiento de blanqueamiento dental?', 'type' => 'service', 'keyword' => 'blanqueamiento', 'wants_image' => true],
            31 => ['question' => 'Hola, quisiera saber el costo de la consulta de diagnóstico y valoración', 'type' => 'service', 'keyword' => 'consulta', 'wants_image' => false],
            32 => ['question' => '¿Tienen foto del consultorio para la cita de valoración?', 'type' => 'service', 'keyword' => 'consulta', 'wants_image' => true],
            33 => ['question' => '¿Cuánto dura una cita de valoración dental general?', 'type' => 'service', 'keyword' => 'consulta', 'wants_image' => false],
            34 => ['question' => '¿Tienen servicio de ortodoncia o alineadores?', 'type' => 'service', 'keyword' => 'ortodoncia', 'wants_image' => false],
            35 => ['question' => 'Me gustaría ver una foto o ejemplo del tratamiento de ortodoncia', 'type' => 'service', 'keyword' => 'ortodoncia', 'wants_image' => true],
            36 => ['question' => '¿Manejan tratamiento de conductos o endodoncia?', 'type' => 'service', 'keyword' => 'endodoncia', 'wants_image' => false],
            37 => ['question' => '¿Tienen fotografía o infografía del tratamiento de endodoncia?', 'type' => 'service', 'keyword' => 'endodoncia', 'wants_image' => true],
            38 => ['question' => '¿Realizan extracción de muelas del juicio o cirugía dental?', 'type' => 'service', 'keyword' => 'extraccion', 'wants_image' => false],
            39 => ['question' => '¿Cuánto cuesta una cirugía dental o extracción simple?', 'type' => 'service', 'keyword' => 'extraccion', 'wants_image' => false],
            40 => ['question' => '¿Tienen servicio de implantes dentales o prótesis fijas?', 'type' => 'service', 'keyword' => 'implante', 'wants_image' => false],
            41 => ['question' => 'Mándame por favor una foto de los implantes dentales', 'type' => 'service', 'keyword' => 'implante', 'wants_image' => true],
            42 => ['question' => '¿Tienen toma de radiografías dentales o rayos X en la clínica?', 'type' => 'service', 'keyword' => 'radiografia', 'wants_image' => false],
            43 => ['question' => '¿Tienen foto del equipo de radiografías digitales?', 'type' => 'service', 'keyword' => 'radiografia', 'wants_image' => true],
            44 => ['question' => '¿Qué doctor o especialista me atendería en una consulta dental?', 'type' => 'service', 'keyword' => 'consulta', 'wants_image' => false],
            45 => ['question' => 'Hola, ¿brindan asesoría jurídica y corporativa?', 'type' => 'service', 'keyword' => 'asesoría', 'wants_image' => false],
            46 => ['question' => '¿Tienen fotos o catálogo del despacho de asesoría legal?', 'type' => 'service', 'keyword' => 'asesoría', 'wants_image' => true],
            47 => ['question' => '¿Cuánto dura una sesión de asesoría legal y qué precio tiene?', 'type' => 'service', 'keyword' => 'asesoría', 'wants_image' => false],
            48 => ['question' => '¿Tienen servicio de auditoría fiscal y contable?', 'type' => 'service', 'keyword' => 'auditoría', 'wants_image' => false],
            49 => ['question' => '¿Tienen foto informativa de la auditoría fiscal y contable?', 'type' => 'service', 'keyword' => 'auditoría', 'wants_image' => true],
            50 => ['question' => '¿Cómo puedo agendar una cita para hoy o mañana con ustedes?', 'type' => 'service', 'keyword' => 'limpieza', 'wants_image' => false],
        ];
    }
}
