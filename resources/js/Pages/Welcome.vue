<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

defineProps({
    canLogin: Boolean,
});

const page = usePage();

const paletteStyle = computed(() => {
    const palette = page.props.landingPalette ?? {};
    return Object.fromEntries(
        Object.entries(palette).filter(([, value]) => typeof value === 'string' && value !== ''),
    );
});

// Interactive Simulator Tabs
const activeSimulatorTab = ref('whatsapp');

// Module Categories
const selectedModuleCategory = ref('todos');

const moduleCategories = [
    { id: 'todos', label: 'Todos los Módulos', count: 6 },
    { id: 'citas', label: 'Agendamiento de Citas', count: 2 },
    { id: 'ventas', label: 'Ventas & Catálogo', count: 2 },
    { id: 'inventario', label: 'Inventario Telegram/WhatsApp', count: 2 },
];

const modules = [
    {
        id: 'citas-ia',
        category: 'citas',
        badge: 'Citas Automatizadas',
        badgeClass: 'bg-[#EDE7F6] text-[#311B92] border-[#D1C4E9]',
        number: '01',
        title: 'Agendamiento Inteligente 24/7',
        description: 'Tus clientes eligen fecha y hora directamente desde WhatsApp o Telegram sin intervención manual.',
        highlights: [
            'Sincronización bidireccional con Google Calendar',
            'Recordatorios automáticos anti-inasistencia',
            'Detección de disponibilidad y bloqueo de horarios',
            'Reprogramación y cancelaciones automáticas'
        ],
        icon: 'fa-solid fa-calendar-check',
        image: 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe?auto=format&fit=crop&w=900&h=650&q=80',
        ctaText: 'Ver cómo funciona',
        ctaLink: '#simulador'
    },
    {
        id: 'catalogo-ventas',
        category: 'ventas',
        badge: 'E-commerce Conversacional',
        badgeClass: 'bg-[#E0F7FA] text-[#00838F] border-[#80DEEA]',
        number: '02',
        title: 'Venta de Productos y Servicios',
        description: 'Muestra tu catálogo interactivo, envía fotos, precios y genera carritos de compra dentro del chat.',
        highlights: [
            'Catálogo dinámico con imágenes y descripciones',
            'Generación de órdenes y enlaces de pago al instante',
            'Cotizaciones de servicios personalizadas',
            'Historial de compras por cliente'
        ],
        icon: 'fa-solid fa-cart-shopping',
        image: 'https://images.unsplash.com/photo-1556742049-0a67c5574f73?auto=format&fit=crop&w=900&h=650&q=80',
        ctaText: 'Explorar Catálogo',
        ctaLink: '#simulador'
    },
    {
        id: 'inventario-bots',
        category: 'inventario',
        badge: 'Control de Stock',
        badgeClass: 'bg-[#E8F5E9] text-[#2E7D32] border-[#A5D6A7]',
        number: '03',
        title: 'Inventarios por WhatsApp y Telegram',
        description: 'Gestiona tu stock en tiempo real mediante comandos directos o mensajes de voz en tus canales favoritos.',
        highlights: [
            'Consulta de existencias instantánea con /stock',
            'Alertas automáticas de bajo stock en Telegram',
            'Registro de entradas, salidas y ajustes por chat',
            'Descuento automático de stock tras cada venta'
        ],
        icon: 'fa-solid fa-boxes-stacked',
        image: 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=900&h=650&q=80',
        ctaText: 'Ver Gestión de Stock',
        ctaLink: '#simulador'
    },
    {
        id: 'ia-multicanal',
        category: 'citas',
        badge: 'Inteligencia Artificial',
        badgeClass: 'bg-[#EDE7F6] text-[#311B92] border-[#D1C4E9]',
        number: '04',
        title: 'Asistente IA con Memoria de Negocio',
        description: 'Entrenado con la información exacta de tus servicios, políticas de atención, precios y preguntas frecuentes.',
        highlights: [
            'Respuestas humanas y naturales 24 horas al día',
            'Transferencia suave a operadores humanos si es requerido',
            'Soporte multi-idioma y reconocimiento de audios',
            'Potenciado por modelos DeepSeek y Gemini'
        ],
        icon: 'fa-solid fa-robot',
        image: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=900&h=650&q=80',
        ctaText: 'Probar Asistente',
        ctaLink: '#simulador'
    },
    {
        id: 'cobros-facturacion',
        category: 'ventas',
        badge: 'Pagos & Cobros',
        badgeClass: 'bg-[#FFF8E1] text-[#F57F17] border-[#FFE082]',
        number: '05',
        title: 'Cobros Rápidos y Anticipos',
        description: 'Asegura tus citas solicitando anticipos o cobra pedidos completos mediante pasarelas de pago seguras.',
        highlights: [
            'Cobro de anticipos para asegurar citas',
            'Links de pago directos por WhatsApp',
            'Confirmación de pago automática con comprobante',
            'Control de flujo de caja y ventas diarias'
        ],
        icon: 'fa-solid fa-credit-card',
        image: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=900&h=650&q=80',
        ctaText: 'Ver Opciones de Pago',
        ctaLink: '#beneficios'
    },
    {
        id: 'panel-metricas',
        category: 'inventario',
        badge: 'Panel Centralizado',
        badgeClass: 'bg-[#E0F7FA] text-[#00838F] border-[#80DEEA]',
        number: '06',
        title: 'Dashboard de Administración Total',
        description: 'Toda tu operación en una sola pantalla: citas agendadas, pedidos, conversaciones y niveles de almacén.',
        highlights: [
            'Métricas en tiempo real de ingresos y citas',
            'Gestión multi-usuario y roles de administradores',
            'Historial completo de conversaciones WhatsApp y Telegram',
            'Reportes exportables a Excel y PDF'
        ],
        icon: 'fa-solid fa-chart-line',
        image: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=900&h=650&q=80',
        ctaText: 'Conocer el Panel',
        ctaLink: '#beneficios'
    },
];

const filteredModules = computed(() => {
    if (selectedModuleCategory.value === 'todos') {
        return modules;
    }
    return modules.filter((m) => m.category === selectedModuleCategory.value);
});

// Testimonials / Case Studies
const selectedTestimonialCategory = ref('todos');

const testimonialCategories = [
    { id: 'todos', label: 'Todas las opiniones', count: 6 },
    { id: 'citas', label: 'Citas & Servicios', count: 2 },
    { id: 'ventas', label: 'Ventas & Catálogo', count: 2 },
    { id: 'inventario', label: 'Inventario & Stock', count: 2 },
];

const testimonials = [
    {
        id: 1,
        author: 'Carlos Méndez',
        role: 'Director Médico · Clínica Dental San Rafael',
        category: 'citas',
        avatarBg: '#311B92',
        rating: 5,
        text: 'GestionDesk redujo nuestras inasistencias a citas en un 85%. Los pacientes agendan por WhatsApp a cualquier hora de la noche y el sistema sincroniza todo con Google Calendar sin solapamientos.',
        metric: '+85% asistencia a citas'
    },
    {
        id: 2,
        author: 'Lucía Fernández',
        role: 'Propietaria · Glamour Hair & Spa',
        category: 'citas',
        avatarBg: '#512DA8',
        rating: 5,
        text: 'Antes perdíamos horas contestando mensajes para coordinar horarios. Ahora el bot de WhatsApp atiende, pide el anticipo de reserva y nos notifica cuando la cita está confirmada.',
        metric: '12 hrs ahorradas/semana'
    },
    {
        id: 3,
        author: 'Roberto Albarrán',
        role: 'Gerente de Operaciones · Distribuidora Ferretera Express',
        category: 'inventario',
        avatarBg: '#00838F',
        rating: 5,
        text: 'Consultar el stock desde Telegram con el comando /stock ha sido un antes y un después para nuestros choferes y vendedores en ruta. Sabemos en tiempo real qué hay en almacén.',
        metric: 'Cero discrepancias en stock'
    },
    {
        id: 4,
        author: 'Valeria Morales',
        role: 'Fundadora · Moda & Tendencia Boutique',
        category: 'ventas',
        avatarBg: '#311B92',
        rating: 5,
        text: 'Vendemos ropa y calzado enviando nuestro catálogo interactivo por WhatsApp. El cliente escoge talla, color y paga con enlace directo. El bot descuenta el inventario al instante.',
        metric: '+65% en ventas por chat'
    },
    {
        id: 5,
        author: 'Esteban Domínguez',
        role: 'CEO · TecnoReparaciones Pro',
        category: 'ventas',
        avatarBg: '#00B4D8',
        rating: 5,
        text: 'Los clientes solicitan cotizaciones y diagnósticos por Telegram. GestionDesk automatiza el seguimiento y el envío de presupuestos, cerrando más ventas sin esfuerzo.',
        metric: '3x conversión de prospectos'
    },
    {
        id: 6,
        author: 'Mariana Silva',
        role: 'Líder de Almacén · NutriSalud Suplementos',
        category: 'inventario',
        avatarBg: '#00C853',
        rating: 5,
        text: 'Las alertas automáticas en Telegram cuando un producto baja de 5 unidades nos evitan quedarnos sin existencias. El manejo de inventarios por mensajería es pura magia.',
        metric: 'Alertas 24/7 sin quiebres de stock'
    },
];

const filteredTestimonials = computed(() => {
    if (selectedTestimonialCategory.value === 'todos') {
        return testimonials;
    }
    return testimonials.filter((t) => t.category === selectedTestimonialCategory.value);
});

// FAQs
const openFaqIndex = ref(0);
const toggleFaq = (index) => {
    openFaqIndex.value = openFaqIndex.value === index ? -1 : index;
};

const faqs = [
    {
        question: '¿Cómo ayuda GestionDesk a mi negocio a agendar citas por WhatsApp?',
        answer: 'GestionDesk integra un asistente inteligente que interactúa con tus clientes de forma natural. Consulta en tiempo real tus horarios disponibles configurados en Google Calendar o en el panel de GestionDesk, permite al usuario seleccionar su fecha preferida, solicita datos o anticipos y confirma la reserva enviando recordatorios automáticos previos a la cita.'
    },
    {
        question: '¿Cómo funciona la venta de productos y servicios por chat?',
        answer: 'Puedes cargar tu catálogo de productos y servicios con fotos, descripciones, variantes (talla, color, etc.) y precios. Cuando un cliente pregunta por un artículo en WhatsApp o Telegram, el bot le muestra tarjetas interactivas, arma su carrito de compra y genera enlaces de pago directos para cerrar la venta en segundos.'
    },
    {
        question: '¿Cómo se maneja el inventario mediante Telegram y WhatsApp?',
        answer: 'GestionDesk te permite gestionar existencias en tiempo real mediante bots dedicados. Tu equipo puede consultar stock con comandos rápidos (por ejemplo `/stock código` o `/buscar producto`), registrar entradas y salidas de mercancía, y recibir notificaciones automáticas en tu canal privado de Telegram cuando un producto alcance su nivel de stock mínimo.'
    },
    {
        question: '¿Necesito un número de WhatsApp nuevo o puedo usar mi número actual?',
        answer: 'Puedes conectar tu número de WhatsApp existente mediante nuestra integración con Evolution API o QR oficial. Toda la configuración se realiza desde el panel administrativo en pocos minutos.'
    },
    {
        question: '¿GestionDesk permite intervenir manualmente en los chats?',
        answer: '¡Por supuesto! El panel de control te ofrece una bandeja omnicanal donde puedes ver todas las conversaciones en curso. Los administradores pueden pausar el bot y responder directamente al cliente en cualquier momento con un solo clic.'
    },
    {
        question: '¿Qué tipo de negocios pueden utilizar GestionDesk?',
        answer: 'GestionDesk es ideal para clínicas, consultorios médicos, salones de belleza, spas, talleres mecánicos, tiendas de retail, venta de ropa y calzado, distribuidores mayoristas, servicios técnicos y cualquier negocio que requiera coordinar citas, vender y controlar inventarios.'
    }
];

// JSON-LD Schema
const schemaApplication = {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    'name': 'GestionDesk',
    'applicationCategory': 'BusinessApplication',
    'operatingSystem': 'Web, Cloud, WhatsApp, Telegram',
    'description': 'Plataforma integral para agendar citas, vender productos y servicios, y manejar inventarios en tiempo real mediante WhatsApp y Telegram con Inteligencia Artificial.',
    'offers': {
        '@type': 'Offer',
        'price': '0',
        'priceCurrency': 'USD'
    },
    'aggregateRating': {
        '@type': 'AggregateRating',
        'ratingValue': '4.9',
        'reviewCount': '148',
        'bestRating': '5',
        'worstRating': '1'
    }
};

const schemaFAQ = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    'mainEntity': faqs.map((faq) => ({
        '@type': 'Question',
        'name': faq.question,
        'acceptedAnswer': {
            '@type': 'Answer',
            'text': faq.answer
        }
    }))
};

onMounted(() => {
    const scriptApp = document.createElement('script');
    scriptApp.type = 'application/ld+json';
    scriptApp.text = JSON.stringify(schemaApplication);
    document.head.appendChild(scriptApp);

    const scriptFAQ = document.createElement('script');
    scriptFAQ.type = 'application/ld+json';
    scriptFAQ.text = JSON.stringify(schemaFAQ);
    document.head.appendChild(scriptFAQ);

    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -40px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal-fade-up, .reveal-fade-left, .reveal-fade-right, .reveal-zoom-in').forEach((el) => {
        observer.observe(el);
    });
});
</script>

<template>
    <Head>
        <title>GestionDesk | Citas, Ventas e Inventarios por WhatsApp y Telegram</title>
        <meta name="description" content="GestionDesk es la plataforma inteligente para agendar citas, vender productos y servicios y controlar inventarios en tiempo real mediante WhatsApp y Telegram con IA." />
        <meta name="keywords" content="GestionDesk, agendar citas whatsapp, bot de citas telegram, vender por whatsapp, control de inventario whatsapp telegram, crm whatsapp, software de inventarios bot" />
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
        <meta name="author" content="GestionDesk" />

        <!-- Open Graph Meta Tags -->
        <meta property="og:locale" content="es_ES" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="GestionDesk | Citas, Ventas e Inventarios por WhatsApp y Telegram" />
        <meta property="og:description" content="Automatiza tu negocio: agenda citas 24/7, vende productos con catálogo interactivo y controla tu inventario desde WhatsApp y Telegram." />
        <meta property="og:site_name" content="GestionDesk" />

        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="GestionDesk | Automatización de Citas, Ventas e Inventarios" />
        <meta name="twitter:description" content="La suite todo-en-uno que convierte tus chats de WhatsApp y Telegram en canales automáticos de venta, reservas y control de stock." />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    </Head>

    <div class="landing min-h-screen font-sans bg-[#F4F6F8] text-[#1A0C48]" :style="paletteStyle">
        <!-- Header de Navegación Glassmorphism (Púrpura Profundo #311B92 con detalles Cian Eléctrico #00E5FF) -->
        <header class="fixed inset-x-0 top-0 z-40 bg-[#311B92]/95 backdrop-blur-xl border-b border-[#00E5FF]/20 transition-all duration-300 shadow-lg shadow-[#311B92]/30">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
                <!-- Brand Logo Oficial GestionDesk -->
                <a href="#" class="group flex items-center gap-3">
                    <img
                        src="/images/logo_gestiondesk.png"
                        alt="Logo GestionDesk"
                        class="h-10 w-auto object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-105"
                    />
                    <div>
                        <!-- Texto: Gestion (Blanco) + Desk (Cian Eléctrico #00E5FF) -->
                        <span class="text-xl font-black tracking-tight text-white flex items-center">
                            Gestion<span class="text-[#00E5FF] drop-shadow-[0_0_8px_rgba(0,229,255,0.6)]">Desk</span>
                        </span>
                        <span class="block text-[10px] font-semibold text-[#00E5FF]/80 uppercase tracking-widest leading-none">
                            Citas · Ventas · Inventario
                        </span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden items-center gap-7 text-sm font-medium text-white/85 lg:flex">
                    <a href="#modulos" class="hover:text-[#00E5FF] transition-colors duration-200">Módulos</a>
                    <a href="#simulador" class="hover:text-[#00E5FF] transition-colors duration-200">Simulador en Vivo</a>
                    <a href="#beneficios" class="hover:text-[#00E5FF] transition-colors duration-200">Beneficios</a>
                    <a href="#testimonios" class="hover:text-[#00E5FF] transition-colors duration-200">Testimonios</a>
                    <a href="#faq" class="hover:text-[#00E5FF] transition-colors duration-200">Preguntas Frecuentes</a>
                </nav>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#1F1060] px-4 py-2 text-xs sm:text-sm font-bold text-white border border-[#00E5FF]/40 hover:bg-[#15093D] hover:border-[#00E5FF] transition"
                    >
                        <i class="fa-solid fa-gauge-high text-[#00E5FF]"></i>
                        <span>Panel</span>
                    </Link>
                    <Link
                        v-else-if="canLogin"
                        :href="route('login')"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#1F1060]/90 px-4 py-2 text-xs sm:text-sm font-bold text-white border border-white/20 hover:bg-[#15093D] hover:border-[#00E5FF] transition"
                    >
                        <i class="fa-solid fa-right-to-bracket text-[#00E5FF]"></i>
                        <span>Acceso</span>
                    </Link>
                    <!-- Botón CTA Principal: Cian Eléctrico (#00E5FF) con texto Púrpura Profundo (#311B92) -->
                    <a
                        href="https://wa.me/529981046082?text=Hola%20GestionDesk,%20deseo%20una%20demostracion%20del%20sistema"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="hidden sm:inline-flex items-center gap-2 rounded-xl bg-[#00E5FF] px-4 py-2 text-xs sm:text-sm font-extrabold text-[#311B92] shadow-lg shadow-[#00E5FF]/30 hover:bg-[#80F3FF] hover:shadow-[#00E5FF]/50 transition duration-300 hover:scale-[1.02]"
                    >
                        <i class="fa-brands fa-whatsapp text-sm text-[#311B92]"></i>
                        <span>Probar en WhatsApp</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="pt-16">
            <!-- HERO SECTION (Púrpura Profundo #311B92 con resplandores Cian Eléctrico #00E5FF y Verde Menta #00E676) -->
            <section class="relative overflow-hidden bg-gradient-to-b from-[#1F1060] via-[#311B92] to-[#15093D] py-20 lg:py-28 text-white">
                <!-- Background decorative glowing orbs con la paleta oficial -->
                <div class="pointer-events-none absolute -left-40 -top-40 size-96 rounded-full bg-[#00E5FF]/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -right-40 top-1/4 size-96 rounded-full bg-[#00E676]/15 blur-3xl"></div>
                <div class="pointer-events-none absolute left-1/3 bottom-0 size-96 rounded-full bg-[#311B92]/40 blur-3xl"></div>

                <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid gap-12 lg:grid-cols-12 lg:items-center">
                        <!-- Left Column: Copy & CTA -->
                        <div class="lg:col-span-7">
                            <!-- Live Pill Tag: Verde Menta Neón (#00E676) y Cian (#00E5FF) -->
                            <div class="inline-flex items-center gap-2 rounded-full border border-[#00E5FF]/40 bg-[#15093D]/80 px-4 py-1.5 text-xs font-bold text-[#00E5FF] backdrop-blur-md animate-hero-1 mb-6 shadow-sm">
                                <span class="relative flex size-2">
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-[#00E676] opacity-75"></span>
                                    <span class="relative inline-flex size-2 rounded-full bg-[#00E676]"></span>
                                </span>
                                <span>IA Omnicanal para WhatsApp & Telegram · v2.0</span>
                            </div>

                            <!-- Main Headline -->
                            <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl leading-[1.1] animate-hero-2 text-white">
                                Automatiza tus
                                <span class="text-[#00E5FF] drop-shadow-[0_0_15px_rgba(0,229,255,0.4)]">Citas</span>,
                                <span class="text-[#00E676] drop-shadow-[0_0_15px_rgba(0,230,118,0.4)]">Ventas</span> e
                                <span class="bg-gradient-to-r from-[#00E5FF] to-[#80F3FF] bg-clip-text text-transparent">Inventarios</span>
                                en WhatsApp y Telegram
                            </h1>

                            <!-- Subheadline -->
                            <p class="mt-6 max-w-2xl text-base sm:text-lg leading-relaxed text-[#F4F6F8]/90 animate-hero-3">
                                <strong class="text-white font-bold">Gestion<span class="text-[#00E5FF]">Desk</span></strong> convierte tus canales de mensajería en un asistente inteligente que agenda citas 24/7 sin solapamientos, vende productos o servicios con catálogo interactivo y sincroniza tu inventario en tiempo real mediante comandos y respuestas automáticas.
                            </p>

                            <!-- Feature Badges Strip -->
                            <div class="mt-6 flex flex-wrap items-center gap-3 text-xs font-semibold text-white/90 animate-hero-4">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F1060]/90 px-3 py-1.5 border border-[#00E5FF]/30">
                                    <i class="fa-solid fa-calendar-check text-[#00E5FF]"></i> Google Calendar Sync
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F1060]/90 px-3 py-1.5 border border-[#00E676]/30">
                                    <i class="fa-brands fa-whatsapp text-[#00E676]"></i> WhatsApp Evolution API
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F1060]/90 px-3 py-1.5 border border-[#00E5FF]/30">
                                    <i class="fa-brands fa-telegram text-[#00E5FF]"></i> Bots Telegram de Stock
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F1060]/90 px-3 py-1.5 border border-[#00E676]/30">
                                    <i class="fa-solid fa-brain text-[#00E676]"></i> DeepSeek & Gemini IA
                                </span>
                            </div>

                            <!-- CTA Buttons con Cian Eléctrico (#00E5FF) y Verde Menta (#00E676) -->
                            <div class="mt-8 flex flex-wrap items-center gap-4 animate-hero-5">
                                <a
                                    href="https://wa.me/529981046082?text=Hola%20GestionDesk,%20quiero%20probar%20la%20demostracion%20en%20WhatsApp"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center justify-center gap-2.5 rounded-xl bg-[#00E5FF] px-6 py-3.5 text-sm sm:text-base font-black text-[#311B92] shadow-xl shadow-[#00E5FF]/30 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl hover:bg-[#80F3FF]"
                                >
                                    <i class="fa-brands fa-whatsapp text-lg text-[#311B92]"></i>
                                    <span>Demostración en WhatsApp</span>
                                </a>
                                <a
                                    href="#simulador"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F1060]/90 border border-[#00E5FF]/40 px-6 py-3.5 text-sm sm:text-base font-bold text-white transition-all duration-300 hover:bg-[#15093D] hover:border-[#00E5FF]"
                                >
                                    <i class="fa-solid fa-play text-xs text-[#00E5FF]"></i>
                                    <span>Ver Simulador Interactivo</span>
                                </a>
                            </div>

                            <!-- Metrics Row -->
                            <div class="mt-10 grid grid-cols-3 gap-4 border-t border-white/10 pt-6 animate-hero-5">
                                <div>
                                    <p class="text-2xl sm:text-3xl font-black text-[#00E5FF]">24/7</p>
                                    <p class="text-xs text-[#F4F6F8]/80 mt-0.5">Atención continua</p>
                                </div>
                                <div>
                                    <p class="text-2xl sm:text-3xl font-black text-[#00E676]">0%</p>
                                    <p class="text-xs text-[#F4F6F8]/80 mt-0.5">Solapamiento de citas</p>
                                </div>
                                <div>
                                    <p class="text-2xl sm:text-3xl font-black text-[#00E5FF]">Real-Time</p>
                                    <p class="text-xs text-[#F4F6F8]/80 mt-0.5">Control de Stock</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Interactive Visual Showcase (Mockup) -->
                        <div class="lg:col-span-5 reveal-fade-left">
                            <div class="relative mx-auto max-w-md rounded-3xl border border-[#00E5FF]/30 bg-[#15093D]/95 p-5 shadow-2xl backdrop-blur-xl ring-1 ring-white/10">
                                <!-- Top status bar of Mockup -->
                                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="size-3 rounded-full bg-[#311B92]"></div>
                                        <div class="size-3 rounded-full bg-[#00E5FF]"></div>
                                        <div class="size-3 rounded-full bg-[#00E676]"></div>
                                    </div>
                                    <div class="flex items-center gap-2 rounded-full bg-[#1F1060] px-3 py-1 text-[11px] font-mono text-[#00E5FF] border border-[#00E5FF]/20">
                                        <i class="fa-solid fa-shield-halved text-[#00E676] text-[10px]"></i>
                                        <span>GestionDesk Bot Node</span>
                                    </div>
                                    <span class="flex size-2 rounded-full bg-[#00E676] animate-pulse"></span>
                                </div>

                                <!-- Dynamic Chat Showcase Container -->
                                <div class="mt-4 space-y-3 font-sans text-xs">
                                    <!-- Incoming User Message (Cita) - Gris Hielo / Blanco -->
                                    <div class="flex items-start gap-2.5">
                                        <div class="size-7 rounded-full bg-[#311B92] flex items-center justify-center text-[#00E5FF] text-[11px] font-bold border border-[#00E5FF]/40">
                                            CL
                                        </div>
                                        <div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-[#F4F6F8] p-3 text-[#1A0C48] shadow-sm">
                                            <p class="font-bold text-[#311B92] text-[11px]">Cliente (WhatsApp)</p>
                                            <p class="mt-0.5">Hola, me gustaría agendar una cita para mañana a las 4:00 PM y saber el precio del servicio.</p>
                                            <span class="text-[9px] text-slate-500 mt-1 block text-right">14:02</span>
                                        </div>
                                    </div>

                                    <!-- Bot Response (Púrpura Profundo + Cian) -->
                                    <div class="flex items-start justify-end gap-2.5">
                                        <div class="max-w-[85%] rounded-2xl rounded-tr-sm bg-gradient-to-r from-[#311B92] to-[#1F1060] p-3 text-white border border-[#00E5FF]/40 shadow-md">
                                            <div class="flex items-center gap-1.5 text-[#00E5FF] text-[11px] font-bold">
                                                <i class="fa-solid fa-robot"></i>
                                                <span>GestionDesk AI</span>
                                            </div>
                                            <p class="mt-1 text-white">¡Hola! Con gusto. El horario de las <strong class="text-[#00E5FF]">4:00 PM</strong> está disponible.</p>
                                            <div class="mt-2 rounded-lg bg-black/30 p-2 border border-[#00E5FF]/20 text-[11px] space-y-0.5">
                                                <p>📅 <strong class="text-white">Cita:</strong> Mantenimiento Especializado</p>
                                                <p>🕒 <strong class="text-white">Horario:</strong> Mañana, 16:00 hrs</p>
                                                <p>💳 <strong class="text-white">Inversión:</strong> <span class="text-[#00E676] font-bold">$45.00 USD</span></p>
                                            </div>
                                            <p class="mt-1.5 text-[11px] text-[#F4F6F8]">¿Deseas que reserve este espacio en tu agenda ahora?</p>
                                            <span class="text-[9px] text-[#00E5FF] mt-1 block text-right flex items-center justify-end gap-1">
                                                14:02 · Entregado <i class="fa-solid fa-check-double text-[#00E676]"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Telegram Stock Notification Pill (Verde Menta Neón #00E676 + Cian #00E5FF) -->
                                    <div class="rounded-xl border border-[#00E676]/40 bg-[#1F1060]/90 p-3 backdrop-blur-md">
                                        <div class="flex items-center justify-between text-[#00E5FF] font-bold text-[11px]">
                                            <span class="flex items-center gap-1.5">
                                                <i class="fa-brands fa-telegram text-[#00E5FF]"></i> Bot Telegram Almacén
                                            </span>
                                            <span class="text-[9px] bg-[#00E676]/20 px-2 py-0.5 rounded text-[#00E676] font-bold">Stock Update</span>
                                        </div>
                                        <p class="mt-1 text-[#F4F6F8] text-[11px]">
                                            📦 <strong>Item #4892</strong> reservado para cita. Stock restante: <strong class="text-[#00E676]">14 unidades</strong>.
                                        </p>
                                    </div>
                                </div>

                                <!-- Floating Badge -->
                                <div class="mt-4 flex items-center justify-between rounded-xl bg-[#1F1060] p-3 border border-[#00E5FF]/30">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-bolt text-[#00E5FF] text-sm"></i>
                                        <div>
                                            <p class="text-[11px] font-bold text-white">Respuesta Ultrarrápida</p>
                                            <p class="text-[10px] text-[#F4F6F8]/70">Tiempo medio de respuesta &lt; 1.5s</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-[#00E676]/20 px-2.5 py-1 text-[10px] font-bold text-[#00E676] border border-[#00E676]/40">
                                        Activo 100%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECCIÓN MÓDULOS Y SOLUCIONES (Fondo Gris Hielo #F4F6F8 con Tarjetas Blanco Puro #FFFFFF) -->
            <section id="modulos" class="landing-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28 bg-[#F4F6F8]">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end reveal-fade-up">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#311B92]">Módulos Integrados</span>
                            <h2 class="mt-2 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-[#311B92]">
                                Todo lo que tu negocio necesita <span class="text-[#00838F] italic">en una sola plataforma</span>
                            </h2>
                        </div>
                        <p class="max-w-md text-sm leading-relaxed text-slate-600">
                            Diseñado para maximizar ventas, erradicar citas perdidas y mantener tu inventario al día sin fricción.
                        </p>
                    </div>

                    <!-- Filtros Rápidos de Módulos -->
                    <div class="mt-8 flex overflow-x-auto no-scrollbar items-center gap-2 pb-2 sm:flex-wrap reveal-fade-up delay-100">
                        <button
                            v-for="cat in moduleCategories"
                            :key="cat.id"
                            @click="selectedModuleCategory = cat.id"
                            :class="[
                                'shrink-0 rounded-full px-5 py-2 text-xs font-bold transition-all duration-300 cursor-pointer hover:scale-105 active:scale-95',
                                selectedModuleCategory === cat.id
                                    ? 'bg-[#311B92] text-white shadow-md shadow-[#311B92]/20'
                                    : 'bg-white text-slate-700 hover:bg-slate-100 border border-[#E0E3EB]'
                            ]"
                        >
                            {{ cat.label }} ({{ cat.count }})
                        </button>
                    </div>

                    <!-- Grid de Tarjetas de Módulos (Blanco Puro #FFFFFF con bordes #E0E3EB) -->
                    <TransitionGroup
                        name="grid-transition"
                        tag="div"
                        class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="(mod, mIdx) in filteredModules"
                            :key="mod.id"
                            :class="[
                                'group flex h-full flex-col overflow-hidden rounded-2xl bg-white border border-[#E0E3EB] shadow-sm transition duration-500 hover:-translate-y-1.5 hover:shadow-xl hover:border-[#311B92]/40 reveal-fade-up',
                                `delay-${(mIdx % 3 + 1) * 100}`
                            ]"
                        >
                            <!-- Image banner with icon overlay -->
                            <div class="relative aspect-[16/9] shrink-0 overflow-hidden bg-slate-100">
                                <img
                                    :src="mod.image"
                                    :alt="mod.title"
                                    class="size-full object-cover transition duration-700 ease-out group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-[#15093D]/70 via-transparent to-transparent"></div>
                                <span class="absolute right-3 top-3 rounded-full bg-[#311B92]/90 px-3 py-1 font-mono text-xs font-bold text-white backdrop-blur-md">
                                    {{ mod.number }}
                                </span>
                                <div class="absolute bottom-3 left-3 flex size-10 items-center justify-center rounded-xl bg-[#311B92] text-[#00E5FF] shadow-lg border border-[#00E5FF]/40">
                                    <i :class="[mod.icon, 'text-base']"></i>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex flex-1 flex-col p-5 sm:p-6">
                                <span
                                    class="mb-2 inline-flex w-fit rounded-full border px-2.5 py-0.5 text-[10px] font-bold tracking-wide"
                                    :class="mod.badgeClass"
                                >
                                    {{ mod.badge }}
                                </span>

                                <h3 class="text-lg font-bold text-[#311B92] leading-tight group-hover:text-[#00838F] transition-colors">
                                    {{ mod.title }}
                                </h3>

                                <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                    {{ mod.description }}
                                </p>

                                <ul class="mt-4 space-y-2 border-t border-[#E0E3EB] pt-4">
                                    <li
                                        v-for="highlight in mod.highlights"
                                        :key="highlight"
                                        class="flex items-start gap-2 text-xs text-slate-700"
                                    >
                                        <i class="fa-solid fa-circle-check mt-0.5 text-[11px] text-[#00C853] shrink-0"></i>
                                        <span>{{ highlight }}</span>
                                    </li>
                                </ul>

                                <div class="mt-6 pt-2">
                                    <a
                                        :href="mod.ctaLink"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#311B92] px-4 py-2.5 text-xs font-bold text-white transition duration-300 group-hover:bg-[#1F1060] group-hover:text-[#00E5FF]"
                                    >
                                        <span>{{ mod.ctaText }}</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </TransitionGroup>
                </div>
            </section>

            <!-- SIMULADOR INTERACTIVO WHATSAPP & TELEGRAM (Púrpura Profundo #311B92 + Cian Eléctrico #00E5FF) -->
            <section id="simulador" class="landing-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28 bg-[#15093D] text-white">
                <div class="mx-auto max-w-7xl">
                    <div class="text-center max-w-3xl mx-auto reveal-fade-up">
                        <span class="inline-flex items-center gap-2 rounded-full border border-[#00E5FF]/40 bg-[#1F1060] px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-[#00E5FF]">
                            <i class="fa-solid fa-terminal"></i>
                            <span>Simulador en Tiempo Real</span>
                        </span>
                        <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight">
                            Mira cómo funciona <span class="text-[#00E5FF] italic">en vivo</span>
                        </h2>
                        <p class="mt-4 text-sm sm:text-base text-[#F4F6F8]/80 leading-relaxed">
                            Experimenta cómo tus clientes interactúan en WhatsApp para agendar y comprar, mientras tu equipo administra el inventario con comandos en Telegram.
                        </p>
                    </div>

                    <!-- Channel Switcher Tabs -->
                    <div class="mt-10 flex justify-center gap-3 reveal-fade-up delay-100">
                        <button
                            @click="activeSimulatorTab = 'whatsapp'"
                            :class="[
                                'inline-flex items-center gap-2.5 rounded-2xl px-6 py-3 text-xs sm:text-sm font-bold transition-all duration-300 cursor-pointer',
                                activeSimulatorTab === 'whatsapp'
                                    ? 'bg-[#00E676] text-[#15093D] shadow-lg shadow-[#00E676]/30 font-extrabold scale-105'
                                    : 'bg-[#1F1060] text-slate-300 hover:bg-[#311B92] hover:text-white border border-white/10'
                            ]"
                        >
                            <i class="fa-brands fa-whatsapp text-lg"></i>
                            <span>Flujo WhatsApp (Citas & Ventas)</span>
                        </button>
                        <button
                            @click="activeSimulatorTab = 'telegram'"
                            :class="[
                                'inline-flex items-center gap-2.5 rounded-2xl px-6 py-3 text-xs sm:text-sm font-bold transition-all duration-300 cursor-pointer',
                                activeSimulatorTab === 'telegram'
                                    ? 'bg-[#00E5FF] text-[#15093D] shadow-lg shadow-[#00E5FF]/30 font-extrabold scale-105'
                                    : 'bg-[#1F1060] text-slate-300 hover:bg-[#311B92] hover:text-white border border-white/10'
                            ]"
                        >
                            <i class="fa-brands fa-telegram text-lg"></i>
                            <span>Flujo Telegram (Manejo de Stock)</span>
                        </button>
                    </div>

                    <!-- Simulator Content Window (Gris Hielo #F4F6F8 y Blanco #FFFFFF dentro del chat) -->
                    <div class="mt-10 max-w-4xl mx-auto rounded-3xl border border-[#00E5FF]/30 bg-[#1F1060]/90 p-6 sm:p-8 shadow-2xl backdrop-blur-xl reveal-zoom-in">
                        <!-- WHATSAPP VIEW -->
                        <div v-if="activeSimulatorTab === 'whatsapp'" class="space-y-4">
                            <!-- Header Info -->
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-full bg-[#00E676] flex items-center justify-center text-[#15093D] text-lg font-black">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-white text-sm">GestionDesk Bot (WhatsApp)</h4>
                                        <p class="text-xs text-[#00E676] flex items-center gap-1 font-semibold">
                                            <span class="size-2 rounded-full bg-[#00E676]"></span> en línea 24/7
                                        </p>
                                    </div>
                                </div>
                                <span class="text-[11px] rounded-full bg-[#311B92] px-3 py-1 text-[#00E5FF] font-mono border border-[#00E5FF]/30">
                                    Demo Interactivo
                                </span>
                            </div>

                            <!-- Conversation Bubbles -->
                            <div class="space-y-3 pt-2 font-sans text-xs sm:text-sm">
                                <!-- User 1: Mensaje cliente en Verde Menta Suave o Blanco -->
                                <div class="flex justify-end">
                                    <div class="max-w-md rounded-2xl rounded-tr-sm bg-[#00E676]/20 p-3.5 text-white border border-[#00E676]/40">
                                        <p>¡Hola! Quiero comprar 2 unidades del Filtro Pro y además agendar una cita de instalación para este viernes.</p>
                                        <span class="text-[10px] text-[#00E676] mt-1 block text-right">10:15 AM</span>
                                    </div>
                                </div>

                                <!-- Bot 1: Blanco Puro #FFFFFF con texto Púrpura Profundo #311B92 -->
                                <div class="flex justify-start">
                                    <div class="max-w-md rounded-2xl rounded-tl-sm bg-white p-3.5 text-[#1A0C48] shadow-md border border-[#E0E3EB]">
                                        <p class="font-black text-[#311B92] text-xs flex items-center gap-1">
                                            <i class="fa-solid fa-sparkles text-[#00838F]"></i> Asistente GestionDesk
                                        </p>
                                        <p class="mt-1">¡Hola con gusto! Hemos verificado tu solicitud en tiempo real:</p>
                                        <div class="mt-2.5 rounded-xl bg-[#F4F6F8] p-3 border border-[#E0E3EB] space-y-1.5 text-xs">
                                            <p class="text-slate-800">📦 <strong>Producto:</strong> Filtro Pro (2 x $25.00 = $50.00)</p>
                                            <p class="text-[#00838F] font-semibold">✅ <strong>Stock disponible:</strong> 18 unidades en almacén.</p>
                                            <p class="text-slate-800">📅 <strong>Cita de instalación:</strong> Viernes 10:00 AM o 3:00 PM disponibles.</p>
                                        </div>
                                        <p class="mt-2 text-xs font-medium">¿Qué horario prefieres para tu cita?</p>
                                        <span class="text-[10px] text-slate-400 mt-1 block text-right">10:15 AM</span>
                                    </div>
                                </div>

                                <!-- User 2 -->
                                <div class="flex justify-end">
                                    <div class="max-w-md rounded-2xl rounded-tr-sm bg-[#00E676]/20 p-3.5 text-white border border-[#00E676]/40">
                                        <p>Prefiero el viernes a las 3:00 PM por favor.</p>
                                        <span class="text-[10px] text-[#00E676] mt-1 block text-right">10:16 AM</span>
                                    </div>
                                </div>

                                <!-- Bot 2 -->
                                <div class="flex justify-start">
                                    <div class="max-w-md rounded-2xl rounded-tl-sm bg-white p-3.5 text-[#1A0C48] shadow-md border border-[#E0E3EB]">
                                        <p class="font-black text-[#311B92] text-xs">¡Excelente! Tu cita y orden están listas:</p>
                                        <div class="mt-2 rounded-xl bg-[#EDE7F6] p-3 border border-[#311B92]/30 text-xs space-y-1">
                                            <p class="font-bold text-[#311B92]">🎉 Reserva Confirmada #GD-9102</p>
                                            <p class="text-slate-800">📅 Viernes, 15:00 hrs (Sincronizado en Google Calendar)</p>
                                            <p class="text-slate-800">💳 Total orden: <strong class="text-[#311B92]">$50.00 USD</strong></p>
                                            <div class="pt-2">
                                                <a href="#" class="inline-block rounded-lg bg-[#00E5FF] text-[#311B92] font-black px-3 py-1.5 text-xs hover:bg-[#80F3FF] shadow-xs">
                                                    Pagar Enlace Seguro ($50.00) →
                                                </a>
                                            </div>
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1 block text-right flex items-center justify-end gap-1">
                                            10:16 AM <i class="fa-solid fa-check-double text-[#00C853]"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TELEGRAM VIEW -->
                        <div v-else class="space-y-4">
                            <!-- Header Info -->
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-full bg-[#00E5FF] flex items-center justify-center text-[#15093D] text-lg font-black">
                                        <i class="fa-brands fa-telegram"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-white text-sm">GestionDesk Inventory Admin (Telegram)</h4>
                                        <p class="text-xs text-[#00E5FF] flex items-center gap-1 font-semibold">
                                            <span class="size-2 rounded-full bg-[#00E5FF]"></span> Bot de Control Interno
                                        </p>
                                    </div>
                                </div>
                                <span class="text-[11px] rounded-full bg-[#311B92] px-3 py-1 text-[#00E5FF] font-mono border border-[#00E5FF]/30">
                                    Admin View
                                </span>
                            </div>

                            <!-- Conversation Bubbles -->
                            <div class="space-y-3 pt-2 font-sans text-xs sm:text-sm">
                                <!-- Admin Command -->
                                <div class="flex justify-end">
                                    <div class="max-w-md rounded-2xl rounded-tr-sm bg-[#00E5FF]/20 p-3.5 text-white border border-[#00E5FF]/40 font-mono text-xs">
                                        <p>/stock Filtro Pro</p>
                                        <span class="text-[10px] text-[#00E5FF] mt-1 block text-right">11:30 AM</span>
                                    </div>
                                </div>

                                <!-- Bot Response (Blanco Puro #FFFFFF) -->
                                <div class="flex justify-start">
                                    <div class="max-w-md rounded-2xl rounded-tl-sm bg-white p-3.5 text-[#1A0C48] shadow-md border border-[#E0E3EB]">
                                        <p class="font-black text-[#311B92] text-xs flex items-center gap-1 font-mono">
                                            📊 REPORTE DE STOCK #GD-INVENTORY
                                        </p>
                                        <div class="mt-2 rounded-xl bg-[#F4F6F8] p-3 border border-[#E0E3EB] text-xs space-y-1 font-mono text-slate-800">
                                            <p>🏷️ <strong>Artículo:</strong> Filtro Pro (SKU: FP-001)</p>
                                            <p>📦 <strong>Stock Actual:</strong> 16 unidades (descontadas 2 de la venta reciente)</p>
                                            <p>⚠️ <strong>Stock Mínimo:</strong> 5 unidades</p>
                                            <p>💰 <strong>Precio Unitario:</strong> $25.00 USD</p>
                                            <p>📍 <strong>Ubicación:</strong> Almacén Central - Pasillo 3B</p>
                                        </div>
                                        <span class="text-[10px] text-slate-400 mt-1 block text-right">11:30 AM</span>
                                    </div>
                                </div>

                                <!-- Adjust Stock Command -->
                                <div class="flex justify-end">
                                    <div class="max-w-md rounded-2xl rounded-tr-sm bg-[#00E5FF]/20 p-3.5 text-white border border-[#00E5FF]/40 font-mono text-xs">
                                        <p>/entrada FP-001 +20 Lote Nuevo Proveedor</p>
                                        <span class="text-[10px] text-[#00E5FF] mt-1 block text-right">11:31 AM</span>
                                    </div>
                                </div>

                                <!-- Confirmation -->
                                <div class="flex justify-start">
                                    <div class="max-w-md rounded-2xl rounded-tl-sm bg-white p-3.5 text-[#1A0C48] shadow-md border border-[#E0E3EB]">
                                        <p class="text-[#00C853] font-black text-xs">✅ ENTRADA REGISTRADA EXITOSAMENTE</p>
                                        <p class="mt-1 text-xs text-slate-800">Nuevo stock total de <strong class="text-[#311B92]">Filtro Pro: 36 unidades</strong>. Notificación enviada al panel central.</p>
                                        <span class="text-[10px] text-slate-400 mt-1 block text-right">11:31 AM</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECCIÓN BENEFICIOS (Gris Hielo #F4F6F8 con Tarjetas Blancas #FFFFFF y Acentos Púrpura/Cian/Verde) -->
            <section id="beneficios" class="landing-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28 bg-[#F4F6F8]">
                <div class="mx-auto max-w-7xl">
                    <div class="text-center max-w-3xl mx-auto reveal-fade-up">
                        <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#311B92]">Por qué GestionDesk</span>
                        <h2 class="mt-2 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-[#311B92]">
                            Resultados medibles para tu negocio <span class="text-[#00838F] italic">desde el día 1</span>
                        </h2>
                        <p class="mt-3 text-sm sm:text-base leading-relaxed text-slate-600">
                            Diseñado tanto para pequeñas empresas como para negocios en crecimiento que buscan eficiencia sin contratar personal extra.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Card 1: Púrpura Profundo -->
                        <div class="rounded-3xl border border-[#E0E3EB] bg-white p-7 transition duration-300 hover:shadow-xl hover:border-[#311B92] reveal-fade-up delay-100">
                            <div class="size-12 rounded-2xl bg-[#EDE7F6] text-[#311B92] flex items-center justify-center text-xl shadow-xs">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-[#311B92]">Ahorra +15 hrs semanales</h3>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Elimina la necesidad de responder mensajes repetitivos de cotizaciones, disponibilidades de citas y consultas de existencias.
                            </p>
                        </div>

                        <!-- Card 2: Verde Menta Neón -->
                        <div class="rounded-3xl border border-[#E0E3EB] bg-white p-7 transition duration-300 hover:shadow-xl hover:border-[#00E676] reveal-fade-up delay-200">
                            <div class="size-12 rounded-2xl bg-[#E8F5E9] text-[#2E7D32] flex items-center justify-center text-xl shadow-xs">
                                <i class="fa-solid fa-shield-check"></i>
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-[#311B92]">Cero citas perdidas</h3>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Recordatorios inteligentes programados 24h y 2h antes por WhatsApp, reduciendo drásticamente las inasistencias.
                            </p>
                        </div>

                        <!-- Card 3: Cian Eléctrico -->
                        <div class="rounded-3xl border border-[#E0E3EB] bg-white p-7 transition duration-300 hover:shadow-xl hover:border-[#00E5FF] reveal-fade-up delay-300">
                            <div class="size-12 rounded-2xl bg-[#E0F7FA] text-[#00838F] flex items-center justify-center text-xl shadow-xs">
                                <i class="fa-solid fa-boxes-packing"></i>
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-[#311B92]">Control de Almacén al 100%</h3>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                El inventario se descuenta automáticamente con cada venta cerrada por el bot y recibes alertas antes de agotar existencias.
                            </p>
                        </div>

                        <!-- Card 4 -->
                        <div class="rounded-3xl border border-[#E0E3EB] bg-white p-7 transition duration-300 hover:shadow-xl hover:border-[#311B92] reveal-fade-up delay-100">
                            <div class="size-12 rounded-2xl bg-[#FFF8E1] text-[#F57F17] flex items-center justify-center text-xl shadow-xs">
                                <i class="fa-solid fa-credit-card"></i>
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-[#311B92]">Pagos al instante</h3>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Genera links de pago para cobrar anticipos de citas o liquidar pedidos completos sin salir de la conversación.
                            </p>
                        </div>

                        <!-- Card 5 -->
                        <div class="rounded-3xl border border-[#E0E3EB] bg-white p-7 transition duration-300 hover:shadow-xl hover:border-[#00E5FF] reveal-fade-up delay-200">
                            <div class="size-12 rounded-2xl bg-[#EDE7F6] text-[#311B92] flex items-center justify-center text-xl shadow-xs">
                                <i class="fa-solid fa-network-wired"></i>
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-[#311B92]">Omnicanalidad real</h3>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Todo centralizado en una única plataforma web con soporte simultáneo para múltiples números de WhatsApp y bots de Telegram.
                            </p>
                        </div>

                        <!-- Card 6 -->
                        <div class="rounded-3xl border border-[#E0E3EB] bg-white p-7 transition duration-300 hover:shadow-xl hover:border-[#00E676] reveal-fade-up delay-300">
                            <div class="size-12 rounded-2xl bg-[#E8F5E9] text-[#2E7D32] flex items-center justify-center text-xl shadow-xs">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <h3 class="mt-5 text-xl font-bold text-[#311B92]">Intervención humana en 1 clic</h3>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Si un cliente necesita atención especial, cualquier asesor puede tomar el control de la conversación inmediatamente desde el panel.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TESTIMONIOS (Gris Hielo #F4F6F8 con Tarjetas Blancas #FFFFFF) -->
            <section id="testimonios" class="landing-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28 bg-[#F4F6F8] border-t border-[#E0E3EB]">
                <div class="mx-auto max-w-7xl">
                    <div class="text-center max-w-3xl mx-auto reveal-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[#311B92]/20 bg-[#EDE7F6] px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-[#311B92]">
                            <i class="fa-solid fa-star text-amber-500"></i>
                            <span>Casos de Éxito</span>
                        </div>
                        <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-[#311B92]">
                            Negocios que confían en <span class="text-[#00838F] italic">GestionDesk</span>
                        </h2>
                        <p class="mt-3 text-sm sm:text-base leading-relaxed text-slate-600">
                            Descubre cómo clínicas, tiendas y empresas de servicios han transformado su operación.
                        </p>
                    </div>

                    <!-- Categories Filter -->
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-2 reveal-fade-up delay-100">
                        <button
                            v-for="cat in testimonialCategories"
                            :key="cat.id"
                            @click="selectedTestimonialCategory = cat.id"
                            :class="[
                                'rounded-full px-5 py-2 text-xs font-bold transition-all duration-300 cursor-pointer hover:scale-105 active:scale-95',
                                selectedTestimonialCategory === cat.id
                                    ? 'bg-[#311B92] text-white shadow-md shadow-[#311B92]/20'
                                    : 'bg-white text-slate-700 hover:bg-slate-100 border border-[#E0E3EB]'
                            ]"
                        >
                            {{ cat.label }} ({{ cat.count }})
                        </button>
                    </div>

                    <!-- Testimonials Grid -->
                    <TransitionGroup
                        name="grid-transition"
                        tag="div"
                        class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="(t, idx) in filteredTestimonials"
                            :key="t.id"
                            :class="[
                                'flex flex-col justify-between rounded-3xl border border-[#E0E3EB] bg-white p-7 shadow-xs transition duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:border-[#311B92]/30 reveal-fade-up',
                                `delay-${(idx % 3 + 1) * 100}`
                            ]"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-inner"
                                            :style="{ backgroundColor: t.avatarBg }"
                                        >
                                            {{ t.author.split(' ').map(n => n[0]).join('').slice(0, 2) }}
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-[#311B92] text-sm">{{ t.author }}</h3>
                                            <p class="text-xs text-slate-500">{{ t.role }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stars -->
                                <div class="mt-4 flex items-center gap-1 text-amber-400 text-sm">
                                    <i v-for="s in t.rating" :key="s" class="fa-solid fa-star"></i>
                                </div>

                                <p class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed italic">
                                    “{{ t.text }}”
                                </p>
                            </div>

                            <div class="mt-6 border-t border-[#E0E3EB] pt-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[#00C853]">
                                    <i class="fa-solid fa-arrow-trend-up"></i>
                                    {{ t.metric }}
                                </span>
                            </div>
                        </div>
                    </TransitionGroup>
                </div>
            </section>

            <!-- PREGUNTAS FRECUENTES (FAQ) (Fondo Blanco Puro #FFFFFF) -->
            <section id="faq" class="landing-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28 bg-white">
                <div class="mx-auto max-w-4xl">
                    <div class="text-center reveal-fade-up">
                        <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#311B92]">Resolvemos tus dudas</span>
                        <h2 class="mt-2 text-3xl sm:text-4xl font-black tracking-tight text-[#311B92]">
                            Preguntas <span class="text-[#00838F] italic">Frecuentes</span>
                        </h2>
                        <p class="mt-3 text-sm sm:text-base leading-relaxed text-slate-600">
                            Todo lo que necesitas saber antes de empezar a usar GestionDesk.
                        </p>
                    </div>

                    <div class="mt-12 space-y-4 reveal-fade-up delay-100">
                        <div
                            v-for="(faq, fIdx) in faqs"
                            :key="fIdx"
                            class="rounded-2xl border border-[#E0E3EB] bg-[#F4F6F8] overflow-hidden transition-colors duration-200"
                        >
                            <button
                                @click="toggleFaq(fIdx)"
                                class="flex w-full items-center justify-between p-5 sm:p-6 text-left font-bold text-[#311B92] hover:text-[#00838F] transition-colors cursor-pointer text-sm sm:text-base"
                            >
                                <span>{{ faq.question }}</span>
                                <i
                                    class="fa-solid fa-chevron-down text-xs transition-transform duration-300 shrink-0 ml-3"
                                    :class="{ 'rotate-180 text-[#00838F]': openFaqIndex === fIdx }"
                                ></i>
                            </button>
                            <div
                                v-show="openFaqIndex === fIdx"
                                class="px-5 pb-5 sm:px-6 sm:pb-6 text-xs sm:text-sm text-slate-700 leading-relaxed border-t border-[#E0E3EB] pt-4 bg-white"
                            >
                                {{ faq.answer }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- BANNER FINAL CTA (Púrpura Profundo #311B92 con Botón Cian Eléctrico #00E5FF) -->
            <section class="landing-section px-4 py-16 sm:px-6 lg:px-8 lg:py-24 bg-gradient-to-tr from-[#15093D] via-[#311B92] to-[#1F1060] text-white relative overflow-hidden">
                <div class="pointer-events-none absolute -right-20 -bottom-20 size-80 rounded-full bg-[#00E5FF]/20 blur-3xl"></div>
                <div class="relative mx-auto max-w-5xl text-center reveal-zoom-in">
                    <span class="inline-flex items-center gap-2 rounded-full border border-[#00E5FF]/40 bg-[#15093D]/80 px-4 py-1.5 text-xs font-bold text-[#00E5FF] mb-6">
                        ⚡ Empieza hoy mismo
                    </span>
                    <h2 class="text-3xl sm:text-5xl font-black tracking-tight leading-tight">
                        Lleva la atención y ventas de tu negocio al siguiente nivel
                    </h2>
                    <p class="mt-5 max-w-2xl mx-auto text-sm sm:text-lg text-[#F4F6F8]/90 leading-relaxed">
                        Conecta tus números de WhatsApp y bots de Telegram en cuestión de minutos y deja que la inteligencia artificial trabaje para ti.
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center items-center gap-4">
                        <a
                            href="https://wa.me/529981046082?text=Hola%20GestionDesk,%20quiero%20empezar%20a%20usar%20el%20sistema"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2.5 rounded-xl bg-[#00E5FF] px-7 py-4 text-sm sm:text-base font-black text-[#311B92] shadow-xl shadow-[#00E5FF]/30 transition-all duration-300 hover:scale-105 hover:bg-[#80F3FF]"
                        >
                            <i class="fa-brands fa-whatsapp text-lg text-[#311B92]"></i>
                            <span>Contactar por WhatsApp</span>
                        </a>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#1F1060] border border-[#00E5FF]/40 px-7 py-4 text-sm sm:text-base font-bold text-white hover:bg-[#15093D] hover:border-[#00E5FF] transition"
                        >
                            <i class="fa-solid fa-lock text-[#00E5FF]"></i>
                            <span>Ingresar al Panel</span>
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <!-- FOOTER (Púrpura Profundo Oscuro #15093D con acentos Cian #00E5FF) -->
        <footer class="bg-[#15093D] border-t border-[#00E5FF]/20 px-4 py-12 text-[#F4F6F8]/70 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 pb-10 border-b border-white/10">
                    <!-- Brand Column -->
                    <div>
                        <div class="flex items-center gap-3">
                            <img
                                src="/images/logo_gestiondesk.png"
                                alt="Logo GestionDesk"
                                class="h-9 w-auto object-contain drop-shadow-md"
                            />
                            <span class="text-xl font-black text-white">Gestion<span class="text-[#00E5FF]">Desk</span></span>
                        </div>
                        <p class="mt-4 text-xs leading-relaxed text-[#F4F6F8]/70">
                            Plataforma integral para agendamiento de citas, ventas conversacionales y control de inventarios mediante WhatsApp y Telegram con IA.
                        </p>
                    </div>

                    <!-- Solutions Column -->
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-[#00E5FF]">Soluciones</p>
                        <ul class="mt-4 space-y-2 text-xs">
                            <li><a href="#modulos" class="hover:text-[#00E5FF] transition">Agendamiento de Citas</a></li>
                            <li><a href="#modulos" class="hover:text-[#00E5FF] transition">Catálogo & Ventas</a></li>
                            <li><a href="#modulos" class="hover:text-[#00E5FF] transition">Inventario por Telegram</a></li>
                            <li><a href="#modulos" class="hover:text-[#00E5FF] transition">Asistente IA WhatsApp</a></li>
                        </ul>
                    </div>

                    <!-- Integrations Column -->
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-[#00E5FF]">Integraciones</p>
                        <ul class="mt-4 space-y-2 text-xs">
                            <li class="flex items-center gap-1.5"><i class="fa-brands fa-whatsapp text-[#00E676]"></i> WhatsApp Evolution API</li>
                            <li class="flex items-center gap-1.5"><i class="fa-brands fa-telegram text-[#00E5FF]"></i> Telegram Bot API</li>
                            <li class="flex items-center gap-1.5"><i class="fa-solid fa-calendar-days text-[#00E5FF]"></i> Google Calendar</li>
                            <li class="flex items-center gap-1.5"><i class="fa-solid fa-brain text-[#00E676]"></i> DeepSeek & Gemini IA</li>
                        </ul>
                    </div>

                    <!-- Contact Column -->
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-[#00E5FF]">Soporte & Contacto</p>
                        <ul class="mt-4 space-y-2.5 text-xs">
                            <li>
                                <a href="https://wa.me/529981046082" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 hover:text-[#00E676] transition">
                                    <i class="fa-brands fa-whatsapp text-[#00E676]"></i>
                                    <span>WhatsApp Directo</span>
                                </a>
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="fa-solid fa-headset text-[#00E5FF]"></i>
                                <span>Soporte Técnico 24/7</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="fa-solid fa-shield-check text-[#00E676]"></i>
                                <span>Datos Encriptados SSL</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-[#F4F6F8]/60">
                    <p>© 2026 GestionDesk. Todos los derechos reservados.</p>
                    <p>Potenciado con Inteligencia Artificial Omnicanal.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

html {
    scroll-behavior: smooth;
}

.font-sans {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
}

/* Animations */
@keyframes heroFadeUp {
    from {
        opacity: 0;
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-hero-1 { animation: heroFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.05s forwards; opacity: 0; }
.animate-hero-2 { animation: heroFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.15s forwards; opacity: 0; }
.animate-hero-3 { animation: heroFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.25s forwards; opacity: 0; }
.animate-hero-4 { animation: heroFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.35s forwards; opacity: 0; }
.animate-hero-5 { animation: heroFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.45s forwards; opacity: 0; }

/* Scroll Reveal Classes */
.reveal-fade-up {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.reveal-fade-left {
    opacity: 0;
    transform: translateX(30px);
    transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.reveal-zoom-in {
    opacity: 0;
    transform: scale(0.95);
    transition: opacity 0.6s cubic-bezier(0.16, 1, 0.3, 1), transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.reveal-fade-up.revealed,
.reveal-fade-left.revealed,
.reveal-zoom-in.revealed {
    opacity: 1;
    transform: translateY(0) translateX(0) scale(1);
}

.delay-100 { transition-delay: 100ms; }
.delay-200 { transition-delay: 200ms; }
.delay-300 { transition-delay: 300ms; }

/* Grid Vue Transition Group */
.grid-transition-enter-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.grid-transition-leave-active {
    transition: all 0.25s ease-out;
}
.grid-transition-enter-from,
.grid-transition-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
}
.grid-transition-move {
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Hide scrollbar for category tabs */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
