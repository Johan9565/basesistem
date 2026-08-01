<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import drAndresPhoto from '../../images/IMAGEN_ANDRES.JPG';

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

const selectedServiceCategory = ref('todos');

const serviceCategories = [
    { id: 'todos', label: 'Todos los servicios', count: 8 },
    { id: 'especialidades', label: 'Especialidades Médicas', count: 4 },
    { id: 'urgencias-viajes', label: 'Urgencias & Aeropuerto', count: 2 },
    { id: 'general', label: 'Medicina General & Vacunas', count: 2 },
];

const services = [
    {
        id: 'consulta-general',
        category: 'general',
        badge: 'Medicina General',
        badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
        number: '01',
        title: 'Consulta General y Diagnóstico',
        description: 'Revisiones clínicas completas, chequeos preventivos y evaluación integral de salud para perros y gatos.',
        highlights: ['Examen físico minucioso', 'Diagnóstico acertado', 'Plan de salud personalizado'],
        ctaText: 'Agendar Consulta',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20deseo%20agendar%20una%20Consulta%20General'
    },
    {
        id: 'dermatologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
        number: '02',
        title: 'Consulta de Dermatología',
        description: 'Diagnóstico especializado y tratamientos para enfermedades de la piel, alergias, dermatitis e infecciones de oído.',
        highlights: ['Pruebas de alergias y piel', 'Tratamiento de otitis y hongos', 'Control de picazón y dermatitis'],
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20consulta%20de%20Dermatologia'
    },
    {
        id: 'oncologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
        number: '03',
        title: 'Consulta de Oncología',
        description: 'Evaluación tumoral oportuna, diagnóstico oncológico integral, esquemas de tratamiento y acompañamiento humano.',
        highlights: ['Diagnóstico tumoral preciso', 'Protocolos de quimioterapia', 'Manejo del dolor y calidad de vida'],
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20informacion%20de%20Oncologia'
    },
    {
        id: 'cardiologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
        number: '04',
        title: 'Consulta de Cardiología',
        description: 'Evaluación cardiovascular especializada, diagnóstico de soplos, control de arritmias y salud del corazón.',
        highlights: ['Evaluación cardiovascular', 'Detección de soplos y arritmias', 'Monitoreo de presión arterial'],
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20consulta%20de%20Cardiologia'
    },
    {
        id: 'odontologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
        number: '05',
        title: 'Consulta de Odontología',
        description: 'Profilaxis bucal especializada, limpieza dental con ultrasonido, salud de encías y tratamiento de halitosis.',
        highlights: ['Profilaxis por ultrasonido', 'Tratamiento de encías y sarro', 'Solución a la halitosis bucal'],
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20consulta%20de%20Odontologia'
    },
    {
        id: 'importacion-exportacion',
        category: 'urgencias-viajes',
        badge: 'Viajes & Aeropuerto',
        badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        number: '06',
        title: 'Trámites de Importación y Exportación',
        description: 'Certificados internacionales zoosanitarios de salud oficiales SENASICA para viajar en el Aeropuerto de Cancún.',
        highlights: ['Certificado de salud para vuelos', 'Atención directa en Aeropuerto', 'Revisión médica y documental'],
        ctaText: 'Trámite Urgente',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20Certificado%20de%20Viaje%20para%20Mascotas'
    },
    {
        id: 'medicina-preventiva',
        category: 'general',
        badge: 'Medicina Preventiva',
        badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        number: '07',
        title: 'Medicina Preventiva y Vacunas',
        description: 'Esquemas completos de vacunación para viajes e interiores, desparasitación interna/externa y nutrición.',
        highlights: ['Vacunas nacionales e internacionales', 'Desparasitación completa', 'Asesoría nutricional clínica'],
        ctaText: 'Agendar Vacunas',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20deseo%20informacion%20de%20Vacunas'
    },
    {
        id: 'urgencias-24h',
        category: 'urgencias-viajes',
        badge: 'Urgencias 24/7',
        badgeClass: 'bg-red-50 text-red-700 border-red-200',
        number: '08',
        title: 'Urgencias 24h y Servicio a Domicilio',
        description: 'Atención médica inmediata en la madrugada, domingos o a domicilio para emergencias y pacientes graves.',
        highlights: ['Disponibilidad 24h los 7 días', 'Atención médica a domicilio', 'Estabilización de emergencias'],
        ctaText: 'Llamar Urgencias 24/7',
        ctaLink: 'tel:+529981046082'
    },
];

const filteredServices = computed(() => {
    if (selectedServiceCategory.value === 'todos') {
        return services;
    }
    return services.filter((s) => s.category === selectedServiceCategory.value);
});

const selectedCategory = ref('todos');
const showAllReviews = ref(false);

const reviews = [
    {
        id: 1,
        author: 'Joely Estefania Rivera',
        badge: null,
        stats: '2 opiniones · 1 foto',
        date: 'Hace 2 meses',
        rating: 5,
        category: 'consulta',
        text: 'La atención del veterinario con mi cachorra moka fue increíble, me gustó mucho la atención y como aclararon nuestras dudas, regresaremos para darle seguimiento a mi perrita🤎…',
    },
    {
        id: 2,
        author: 'Lernyn Pirela',
        badge: 'Local Guide',
        stats: '150 opiniones · 204 fotos',
        date: 'Hace un mes',
        rating: 5,
        category: 'urgencias',
        text: 'Excelente servicio de ayuda cuando uno más lo necesita . Me sacaron de un aprieto con leche materna para gatitos en un horario nocturno difícil para conseguir este producto',
    },
    {
        id: 3,
        author: 'Hernandez Espinosa Maria Lolbe',
        badge: null,
        stats: '3 opiniones · 3 fotos',
        date: 'Hace 5 meses',
        rating: 5,
        category: 'consulta',
        text: 'Sus servicios siempre fueron muy buenos y acertados, lleve a mi gato muchas veces con este veterinario y la verdad mis respetos por las veces que nos tenía casi cada mes o cada una o dos semanas en su consultorio ❤️🩹…',
        ownerResponse: 'Lamentamos mucho el fallecimiento de Denisse. Siempre fue un paciente muy querido en la clínica y lo recordamos con mucho cariño. Agradecemos profundamente su confianza durante todo el tiempo.',
    },
    {
        id: 4,
        author: 'Veronica Hernandez',
        badge: null,
        stats: '8 opiniones',
        date: 'Hace 3 meses',
        rating: 5,
        category: 'consulta',
        text: 'Excelente Doctor, tiene conocimiento, sabe mucho, es muy acertado en su diagnóstico, sus precios son justo y no saca dinero como otros veterinarios donde he llevado a mis perritos. Tengo 5 perritos.…',
    },
    {
        id: 5,
        author: 'Elisa Gpm',
        badge: null,
        stats: '2 opiniones',
        date: 'Hace 5 meses',
        rating: 5,
        category: 'urgencias',
        text: 'Si pudiera darles más estrellas lo haría, fuimos por una emergencia un domingo, y la verdad es que la atención fue maravillosa, todo limpio, mucho amor, explicación a todas mis dudas o preocupaciones, 1000% lo recomiendo. El Dr. es un excelente persona, estamos de verdad muy agradecidos con su personal y todo.',
    },
    {
        id: 6,
        author: 'Iris Azareel Vazquez Cruz',
        badge: null,
        stats: '2 opiniones',
        date: 'Hace 2 meses',
        rating: 5,
        category: 'urgencias',
        text: 'Mi gatito llegó muy grave, el doctor tiene muy buen ojo clínico, mi gato fue atendido en otros lugares, doctores con buenas recomendaciones y no detectaron problema. Doctor muy ético, profesional, excelente atención y trato',
    },
    {
        id: 7,
        author: 'IRANIA AVALOS',
        badge: null,
        stats: '1 opinión',
        date: 'Hace 6 meses',
        rating: 5,
        category: 'urgencias',
        text: 'Excelente servicio y atención lleve a mi perrita de 7 meses muy mal con vomito. Era de noche y logró estabilizarla el doctor Andrés fue como ángel para mi perrita muchas gracias',
    },
    {
        id: 8,
        author: 'Diana Ruiz',
        badge: 'Local Guide',
        stats: '6 opiniones',
        date: 'Hace 6 meses',
        rating: 5,
        category: 'aeropuerto',
        text: 'El Dr. Tiene una atención impecable y sus respuestas son verdaderamente ágiles. Gracias a su amable apoyo pudimos abordar nuestro vuelo sin problema. Excelente servicio!',
    },
    {
        id: 9,
        author: 'Gabriela Chumba',
        badge: null,
        stats: '2 opiniones · 2 fotos',
        date: 'Hace 8 meses',
        rating: 5,
        category: 'consulta',
        text: 'Estoy muy agradecida con el Dr. Andrés y su equipo de trabajo . Desde el primer momento, fueron muy amables y pacientes con mi perrita Sharon. El doctor se tomó el tiempo para explicarme el problema de salud en detalle.',
    },
    {
        id: 10,
        author: 'edson badillo',
        badge: 'Local Guide',
        stats: '17 opiniones · 40 fotos',
        date: 'Hace un mes',
        rating: 5,
        category: 'consulta',
        text: 'Gracias a Dios mi cachorrita está muy bien, su procedimiento fue muy rápido y ella está súper bien,',
    },
    {
        id: 11,
        author: 'Richard Monster',
        badge: 'Local Guide',
        stats: '58 opiniones · 19 fotos',
        date: 'Hace 8 meses',
        rating: 5,
        category: 'urgencias',
        text: 'Excelente lugar con servicio las 24 horas atención personalizada muy amables profesionales ampliamente recomendado instalaciones 10 de 10 sobre todo inspiran mucha confianza y eso no tiene precio',
    },
    {
        id: 12,
        author: 'LAUU J',
        badge: null,
        stats: '7 opiniones · 10 fotos',
        date: 'Hace 6 meses',
        rating: 5,
        category: 'consulta',
        text: 'Muy buena atención de parte del doctor, me explicó todo lo que se tenía mi gatito y me fue sincero con lo que se tenía que hacer.',
    },
    {
        id: 13,
        author: 'Arturo Melgoza',
        badge: null,
        stats: '1 opinión · 1 foto',
        date: 'Hace 2 años',
        rating: 5,
        category: 'domicilio',
        text: 'Aquí encontramos un servicio rápido, serio profesional y a domicilio que nos permitió tomar en tiempo el vuelo. Muy recomendable!!!',
    },
    {
        id: 14,
        author: 'Adriana Oliva',
        badge: null,
        stats: '2 opiniones',
        date: 'Hace un año',
        rating: 5,
        category: 'consulta',
        text: 'Una gran atención del veterinario es muy amable y paciente para explicar y atender. Realmente es una buena decisión acudir con él para cualquier emergencia, su forma de atender y explicar realmente ayudan mucho, ademas que genera un gran vínculo de confianza, sin duda volveré a acudir con usted',
    },
    {
        id: 15,
        author: 'Erika Ruiz',
        badge: null,
        stats: '5 opiniones',
        date: 'Hace 2 años',
        rating: 5,
        category: 'aeropuerto',
        text: 'Excelente atención que recibimos por parte del doctor Andrés, quien fue hasta el aeropuerto en la madrugada para vacunar a nuestros perritos y nos expidió los certificados de salud que se requieren para viajar. Si no ha sido por él, no hubiéramos poder llevar a nuestras mascotas',
    },
    {
        id: 16,
        author: 'Raiza Fernandez',
        badge: 'Local Guide',
        stats: '20 opiniones · 1 foto',
        date: 'Hace 2 años',
        rating: 5,
        category: 'domicilio',
        text: 'Dr. Andres es excelente veterinario. Vino a mi domicilio de inmediato, se tomo el tiempo de explicarme todo y mi perrito se mejoro el mismo día para poder viajar. El costo de su consulta y medicinas fue inexpensive y su diagnóstico y tratamiento fue correcto para que mi perrito se sintiera mejor en pocas horas. Muchas Gracias Dr Andres ya Oscar se siente mejor.',
    },
    {
        id: 17,
        author: 'Brenda Estrada Tirado',
        badge: 'Local Guide',
        stats: '16 opiniones',
        date: 'Hace 2 años',
        rating: 5,
        category: 'aeropuerto',
        text: 'Estabamos en el aeropuerto y necesitabamos un certificado nuevo de emergencia. El veterinario nos pudo ayudar casi inmediatamente y super amable vino hasta el mostrador a asegurarse que todos los papeles estuvieran en regla para abordar :)',
    },
    {
        id: 18,
        author: 'Андрей Бычков',
        badge: null,
        stats: '14 opiniones · 2 fotos',
        date: 'Hace 6 meses',
        rating: 5,
        category: 'consulta',
        text: 'Atención a 5 estrellas a los peluditos, me quedé satisfied al 146%',
    },
    {
        id: 19,
        author: 'gerson hidalgo',
        badge: null,
        stats: '4 opiniones · 2 fotos',
        date: 'Hace 2 años',
        rating: 5,
        category: 'aeropuerto',
        text: 'La SALVACIÓN si van a volar del aeropuerto de CANCUN .. Llega a ayudar con los. Documentos y además tiene TODO LO NECESARIO para ayudarte con tu trámite en el aeropuerto SI VAS CON TU MACOTA.... Super recomendado y super salvador.....',
    },
    {
        id: 20,
        author: 'Brenda Paulina Trejo Orozco',
        badge: null,
        stats: '6 opiniones · 1 foto',
        date: 'Hace 6 meses',
        rating: 5,
        category: 'consulta',
        text: 'Excelente lugar para la atención de los peluditos 🐶…',
    },
    {
        id: 21,
        author: 'Marisol Reyes',
        badge: null,
        stats: '1 opinión',
        date: 'Hace un año',
        rating: 5,
        category: 'consulta',
        text: 'Mas que recomendado el Doctor Aguilar de los pocos veterinarios que se preocupan por Lukas, mi perrito, vio que mi perrito estuviera bien y llevara mejor vida y ahora va mejor Muchas Gracias !!! Doctor Aguilar 🙏…',
    },
    {
        id: 22,
        author: 'Axel Hernandez',
        badge: null,
        stats: '3 opiniones',
        date: 'Hace un año',
        rating: 5,
        category: 'consulta',
        text: 'Muy profesionales, excelente atención, me ayudaron bastante y resolvieron mis dudas!, totalmente recomendables. Buena atención para mi giganton perruno.',
    },
    {
        id: 23,
        author: 'Ivonne Mellado',
        badge: 'Local Guide',
        stats: '23 opiniones',
        date: 'Hace 3 años',
        rating: 5,
        category: 'aeropuerto',
        text: 'Excelente servicio, seriedad y precio justo,a mí me atendió en el aeropuerto,muy recomendable 👍…',
    },
    {
        id: 24,
        author: 'Lupita LópezH',
        badge: null,
        stats: '1 opinión',
        date: 'Hace 2 años',
        rating: 5,
        category: 'domicilio',
        text: 'Un Exelente Médico muy capacitado ,brindado la mejor atención a nuestros pequeños , 🐶🐾🐈siempre con la mejor disposición , con el eh encontrado todos los medicamentos que necesito , y lo mejor que lo llevan a domicilio Lo recomiedo !!…',
    },
    {
        id: 25,
        author: 'Glenis Gonzalez',
        badge: null,
        stats: '8 opiniones · 3 fotos',
        date: 'Hace 2 años',
        rating: 5,
        category: 'domicilio',
        text: 'Hola ,lo recomiendo por su excelente servicio y asistencia a cualquier hora q necesite de su servicio esta disponible De mi parte muy agradecida',
    },
];

const categories = [
    { id: 'todos', label: 'Todas las opiniones', count: 25 },
    { id: 'urgencias', label: 'Urgencias y 24 Horas 🚨', count: 5 },
    { id: 'aeropuerto', label: 'Aeropuerto y Viajes ✈️', count: 5 },
    { id: 'domicilio', label: 'A Domicilio 🏠', count: 4 },
    { id: 'consulta', label: 'Atención Médica 🐾', count: 11 },
];

const avatarColors = [
    '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899',
    '#06b6d4', '#84cc16', '#d97706', '#6366f1', '#14b8a6',
    '#ef4444', '#a855f7', '#059669', '#d97706', '#4f46e5'
];

const getAvatarBg = (index) => avatarColors[index % avatarColors.length];

const getInitials = (name) => {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
};

const filteredReviews = computed(() => {
    let list = reviews;
    if (selectedCategory.value !== 'todos') {
        list = reviews.filter((r) => r.category === selectedCategory.value);
    }
    if (!showAllReviews.value && selectedCategory.value === 'todos') {
        return list.slice(0, 6);
    }
    return list;
});

const schemaVeterinary = {
    '@context': 'https://schema.org',
    '@type': 'VeterinaryCare',
    '@id': 'https://smallanimalclinic.mx/#veterinary',
    'name': 'Small Animal Clinic Cancún',
    'alternateName': 'Small Animal Clinic - Veterinaria 24 Horas Cancún',
    'url': 'https://smallanimalclinic.mx/',
    'logo': 'https://smallanimalclinic.mx/images/logo_with_name.png',
    'image': 'https://smallanimalclinic.mx/images/logo_with_name.png',
    'telephone': '+529981046082',
    'email': 'smallanimalcliniccancun@gmail.com',
    'priceRange': '$$',
    'address': {
        '@type': 'PostalAddress',
        'addressLocality': 'Cancún',
        'addressRegion': 'Quintana Roo',
        'addressCountry': 'MX'
    },
    'geo': {
        '@type': 'GeoCoordinates',
        'latitude': '21.141',
        'longitude': '-86.8515'
    },
    'openingHoursSpecification': {
        '@type': 'OpeningHoursSpecification',
        'dayOfWeek': [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ],
        'opens': '00:00',
        'closes': '23:59'
    },
    'aggregateRating': {
        '@type': 'AggregateRating',
        'ratingValue': '4.7',
        'reviewCount': '25',
        'bestRating': '5',
        'worstRating': '1'
    },
    'founder': {
        '@type': 'Person',
        'name': 'Dr. Andrés Aguilar',
        'jobTitle': 'Médico Veterinario Zootecnista'
    },
    'medicalSpecialty': [
        'Veterinary Dermatology',
        'Veterinary Oncology',
        'Veterinary Cardiology',
        'Veterinary Dentistry',
        'Emergency Veterinary Care'
    ],
    'sameAs': [
        'https://www.facebook.com/profile.php?id=61562957610885',
        'https://www.tiktok.com/@dr.andresaguilarvet',
        'https://maps.app.goo.gl/VH5bcayUB5Vhity57'
    ]
};

const schemaFAQ = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    'mainEntity': [
        {
            '@type': 'Question',
            'name': '¿Ofrecen atención veterinaria de urgencias las 24 horas en Cancún?',
            'acceptedAnswer': {
                '@type': 'Answer',
                'text': 'Sí, en Small Animal Clinic ofrecemos atención médica veterinaria de urgencias 24/7 los 7 días de la semana en Cancún y servicio a domicilio para emergencias de perros y gatos.'
            }
        },
        {
            '@type': 'Question',
            'name': '¿Cómo tramitar un certificado zoosanitario de salud para viajar con mascotas en el Aeropuerto de Cancún?',
            'acceptedAnswer': {
                '@type': 'Answer',
                'text': 'El Dr. Andrés Aguilar emite certificados de salud internacionales y oficiales SENASICA / SAGARPA para vuelos de importación y exportación de mascotas en el Aeropuerto Internacional de Cancún.'
            }
        },
        {
            '@type': 'Question',
            'name': '¿Qué especialidades médicas veterinarias ofrece la clínica en Cancún?',
            'acceptedAnswer': {
                '@type': 'Answer',
                'text': 'Ofrecemos consultas médicas especializadas en Dermatología, Oncología, Cardiología y Odontología veterinaria, además de consulta general, medicina preventiva y esquemas de vacunación.'
            }
        }
    ]
};

onMounted(() => {
    // Inyección dinámica de JSON-LD para SEO (Schema.org)
    const scriptVet = document.createElement('script');
    scriptVet.type = 'application/ld+json';
    scriptVet.text = JSON.stringify(schemaVeterinary);
    document.head.appendChild(scriptVet);

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
        <title>Small Animal Clinic | Clínica Veterinaria en Cancún 24/7 | Dr. Andrés Aguilar</title>
        <meta name="description" content="Clínica veterinaria en Cancún abierta 24/7. Urgencias veterinarias, consultas especializadas (dermatología, oncología, cardiología, odontología) y certificados zoosanitarios internacionales de viaje (SENASICA / Aeropuerto Cancún)." />
        <meta name="keywords" content="veterinaria cancun, veterinario 24 horas cancun, urgencias veterinarias cancun, certificados zoosanitarios cancun, certificado salud mascotas aeropuerto cancun, dr andres aguilar veterinario, dermatologia veterinaria cancun, oncologia veterinaria cancun" />
        <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
        <meta name="author" content="Dr. Andrés Aguilar - Small Animal Clinic" />
        <link rel="canonical" href="https://smallanimalclinic.mx/" />

        <!-- Geo Location Meta Tags (SEO Local Cancún) -->
        <meta name="geo.region" content="MX-ROO" />
        <meta name="geo.placename" content="Cancún, Quintana Roo" />
        <meta name="geo.position" content="21.141;-86.8515" />
        <meta name="ICBM" content="21.141, -86.8515" />

        <!-- Open Graph Meta Tags -->
        <meta property="og:locale" content="es_MX" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Small Animal Clinic | Clínica Veterinaria en Cancún 24/7" />
        <meta property="og:description" content="Atención veterinaria médica especializada, urgencias 24h los 7 días y certificados internacionales zoosanitarios de viaje en el Aeropuerto de Cancún." />
        <meta property="og:url" content="https://smallanimalclinic.mx/" />
        <meta property="og:site_name" content="Small Animal Clinic Cancún" />
        <meta property="og:image" content="https://smallanimalclinic.mx/images/logo_with_name.png" />
        <meta property="og:image:alt" content="Small Animal Clinic Cancún - Dr. Andrés Aguilar" />

        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="Small Animal Clinic | Clínica Veterinaria en Cancún 24/7" />
        <meta name="twitter:description" content="Atención veterinaria de alta calidad 24/7 en Cancún. Urgencias, consultas médicas especializadas y certificados de salud para viaje." />
        <meta name="twitter:image" content="https://smallanimalclinic.mx/images/logo_with_name.png" />
    </Head>


    <div class="landing min-h-screen font-sans" :style="paletteStyle">
        <!-- Header -->
        <header class="absolute inset-x-0 top-0 z-30 animate-hero-1">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6 lg:px-10">
                <a href="#" class="group flex items-center gap-3 text-white">
                    <img src="/images/logo_without_name.png" alt="Logo Small Animal Clinic Cancún - Veterinaria 24h" class="h-12 sm:h-14 w-auto object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-110" />
                    <span>
                        <span class="block font-sans text-xl font-extrabold tracking-tight leading-none group-hover:text-blue-200 transition-colors duration-300">Small Animal</span>
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.34em] text-white/70">Clinic · Cancún</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-white/85 lg:flex">
                    <a href="#servicios" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Servicios</a>
                    <a href="#nosotros" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Nosotros</a>
                    <a href="#certificaciones" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Certificaciones</a>
                    <a href="#opiniones" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Opiniones</a>
                    <a href="#ubicacion" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Ubicación</a>
                    <a href="#redes" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Redes Sociales</a>

                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="transition hover:text-white"
                    >
                        Panel
                    </Link>
                </nav>
                <a href="#contacto" class="landing-cta rounded-full px-4 py-2.5 text-xs font-bold lg:hidden transition hover:scale-105 active:scale-95">Agendar cita</a>
            </div>
        </header>

        <main>
            <!-- Hero Section -->
            <section class="landing-hero relative flex min-h-[850px] items-center overflow-hidden">
                <img
                    src="https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=1800&q=85"
                    alt="Perro feliz en consulta veterinaria en Cancún atendido por Small Animal Clinic"
                    class="absolute inset-0 size-full object-cover object-[68%_center] transition-transform duration-1000 ease-out hover:scale-105"
                />
                <div class="landing-hero-overlay absolute inset-0"></div>
                <div class="absolute inset-0 bg-linear-to-t from-black/40 via-transparent to-black/10"></div>

                <div class="relative mx-auto w-full max-w-7xl px-6 pb-20 pt-36 lg:px-10">
                    <div class="max-w-3xl">
                        <!-- Badges -->
                        <div class="mb-4 flex flex-wrap items-center gap-2 animate-hero-1">

                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-400/40 bg-amber-500/20 px-3.5 py-1.5 text-xs font-bold text-amber-200 backdrop-blur-md animate-pulse-subtle">
                                Atención 24/7 (7 Días)
                            </span>
                        </div>

                        <!-- Main Heading -->
                        <h1 class="font-sans text-4xl font-extrabold tracking-tight leading-[1.02] text-white sm:text-6xl lg:text-[72px] animate-hero-2">
                            <span class="block text-xs sm:text-sm font-bold tracking-[0.25em] uppercase text-blue-300 mb-2">Clínica Veterinaria en Cancún · 24/7</span>
                            Cuidamos a quienes
                            <span class="landing-highlight animate-shimmer-text italic">hacen familia.</span>
                        </h1>


                        <!-- Subtitle -->
                        <p class="mt-4 max-w-xl text-base sm:text-lg leading-7 sm:leading-8 text-white/85 animate-hero-3">
                            Medicina veterinaria especializada (Dermatología, Oncología, Cardiología, Odontología), urgencias 24h los 7 días de la semana y trámites internacionales de importación/exportación de mascotas.
                        </p>

                        <!-- Notice Box for Appointments -->
                        <div class="mt-5 rounded-2xl bg-amber-500/20 border border-amber-400/35 p-3.5 text-xs text-amber-100 backdrop-blur-md flex items-start gap-3 max-w-2xl animate-hero-4 transition-all duration-300 hover:border-amber-400/60 hover:bg-amber-500/25">
                            <span class="text-base shrink-0 animate-bounce-slow">📌</span>
                            <p class="leading-relaxed">
                                <strong>Atención preferente CON CITA.</strong>
                                <span class="text-amber-200/90 ml-1">Las consultas <u>SIN CITA PREVIA</u> tienen un costo adicional por concepto de atención médica inmediata o de urgencia.</span>
                            </p>
                        </div>

                        <!-- Contact Info Card Bar directly in Hero -->
                        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 rounded-2xl bg-slate-900/75 p-4 border border-white/25 backdrop-blur-md text-white text-xs animate-hero-5 shadow-2xl">
                            <!-- Header Indicating Interactive Buttons -->
                            <div class="w-full col-span-full pb-2 flex items-center justify-between text-[11px] font-semibold text-white/90 border-b border-white/10 mb-1">
                                <span class="flex items-center gap-2">
                                    <span class="relative flex size-2">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                                    </span>
                                    <span>Botones de contacto directo <span class="text-blue-300 font-normal">(Haz clic o toca para interactuar)</span></span>
                                </span>
                                <span class="hidden sm:inline text-[10px] text-white/60 uppercase tracking-wider font-mono">Respuesta inmediata</span>
                            </div>

                            <!-- Button 1: Urgent Call -->
                            <a href="tel:+529981046082" title="Haz clic para llamar a Urgencias" class="flex items-center justify-between gap-2.5 p-3 rounded-xl bg-white/10 border border-white/15 hover:bg-blue-600/30 hover:border-blue-400/50 hover:shadow-lg hover:shadow-blue-500/10 transition-all duration-300 group hover:-translate-y-0.5 active:scale-95 cursor-pointer">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-blue-500/30 text-blue-300 text-base group-hover:scale-110 group-hover:bg-blue-500 group-hover:text-white transition duration-300 shadow-sm">📞</span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-white text-xs truncate">Urgencias 24/7</p>
                                        <p class="text-blue-200/90 font-mono text-[11px] font-semibold truncate">+52 998 104 6082</p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-[10px] font-semibold bg-blue-500/20 text-blue-200 group-hover:bg-blue-500 group-hover:text-white px-2 py-1 rounded-md transition-all duration-300 flex items-center gap-1 shadow-xs">
                                    Llamar <span class="group-hover:translate-x-0.5 transition-transform duration-300">↗</span>
                                </span>
                            </a>

                            <!-- Button 2: WhatsApp -->
                            <a href="https://wa.me/529981046082" target="_blank" rel="noopener noreferrer" title="Haz clic para abrir chat de WhatsApp" class="flex items-center justify-between gap-2.5 p-3 rounded-xl bg-white/10 border border-white/15 hover:bg-emerald-600/30 hover:border-emerald-400/50 hover:shadow-lg hover:shadow-emerald-500/10 transition-all duration-300 group hover:-translate-y-0.5 active:scale-95 cursor-pointer">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-500/30 text-emerald-400 group-hover:scale-110 group-hover:bg-emerald-500 group-hover:text-white transition duration-300 shadow-sm">
                                        <svg class="size-4 fill-current" viewBox="0 0 24 24">
                                            <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984 0 1.758.459 3.474 1.33 4.982L2 22l5.166-1.347a9.923 9.923 0 0 0 4.845 1.258h.005c5.505 0 9.988-4.478 9.989-9.985 0-2.666-1.037-5.172-2.924-7.058A9.914 9.914 0 0 0 12.012 2zm5.41 13.336c-.226.634-1.312 1.213-1.803 1.252-.464.037-1.047.147-3.415-.83-2.91-1.2-4.757-4.175-4.903-4.37-.145-.195-1.187-1.579-1.187-3.013 0-1.433.748-2.14 1.014-2.433.266-.292.58-.366.774-.366.194 0 .387.002.556.009.18.007.423-.069.662.505.247.593.844 2.062.917 2.21.073.147.121.32.024.512-.097.195-.146.316-.292.487-.146.17-.307.381-.439.512-.146.146-.299.305-.128.598.17.292.756 1.248 1.625 2.022 1.118.996 2.06 1.305 2.353 1.452.292.146.463.122.634-.073.17-.195.73-0.852.925-1.144.195-.293.39-.244.657-.146.268.097 1.697.801 1.989.947.292.146.487.219.56.341.073.122.073.707-.153 1.341z"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-white text-xs truncate">WhatsApp Directo</p>
                                        <p class="text-emerald-200/90 font-mono text-[11px] font-semibold truncate">+52 998 104 6082</p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-[10px] font-semibold bg-emerald-500/20 text-emerald-200 group-hover:bg-emerald-500 group-hover:text-white px-2 py-1 rounded-md transition-all duration-300 flex items-center gap-1 shadow-xs">
                                    Chat <span class="group-hover:translate-x-0.5 transition-transform duration-300">↗</span>
                                </span>
                            </a>

                            <!-- Button 3: Email -->
                            <a href="mailto:smallanimalcliniccancun@gmail.com" title="Haz clic para enviar correo electrónico" class="flex items-center justify-between gap-2.5 p-3 rounded-xl bg-white/10 border border-white/15 hover:bg-red-600/30 hover:border-red-400/50 hover:shadow-lg hover:shadow-red-500/10 transition-all duration-300 group hover:-translate-y-0.5 active:scale-95 cursor-pointer">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-red-500/30 text-red-300 text-base group-hover:scale-110 group-hover:bg-red-500 group-hover:text-white transition duration-300 shadow-sm">✉️</span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-white text-xs truncate">Correo Electrónico</p>
                                        <p class="text-white/80 text-[10px] truncate" title="smallanimalcliniccancun@gmail.com">smallanimalclinic...</p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-[10px] font-semibold bg-red-500/20 text-red-200 group-hover:bg-red-500 group-hover:text-white px-2 py-1 rounded-md transition-all duration-300 flex items-center gap-1 shadow-xs">
                                    Enviar <span class="group-hover:translate-x-0.5 transition-transform duration-300">↗</span>
                                </span>
                            </a>

                            <!-- Button 4: Google Maps -->
                            <a href="https://maps.app.goo.gl/VH5bcayUB5Vhity57" target="_blank" rel="noopener noreferrer" title="Haz clic para abrir ubicación en Google Maps" class="flex items-center justify-between gap-2.5 p-3 rounded-xl bg-white/10 border border-white/15 hover:bg-amber-600/30 hover:border-amber-400/50 hover:shadow-lg hover:shadow-amber-500/10 transition-all duration-300 group hover:-translate-y-0.5 active:scale-95 cursor-pointer">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-amber-500/30 text-amber-300 text-base group-hover:scale-110 group-hover:bg-amber-500 group-hover:text-white transition duration-300 shadow-sm">📍</span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-white text-xs truncate">Google Maps</p>
                                        <p class="text-white/80 text-[10px] truncate">Cancún, Q. Roo</p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-[10px] font-semibold bg-amber-500/20 text-amber-200 group-hover:bg-amber-500 group-hover:text-white px-2 py-1 rounded-md transition-all duration-300 flex items-center gap-1 shadow-xs">
                                    Mapa <span class="group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform duration-300">↗</span>
                                </span>
                            </a>
                        </div>

                        <!-- Social Media Strip directly in Hero Banner -->
                        <div class="mt-5 flex flex-wrap items-center gap-2.5 text-xs text-white/90 animate-hero-5">
                            <span class="font-bold text-white/70 uppercase text-[10px] tracking-wider">Síguenos:</span>
                            <a href="https://www.facebook.com/profile.php?id=61562957610885" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-blue-600/40 border border-blue-400/40 px-3.5 py-1.5 text-xs font-bold hover:bg-blue-600 hover:scale-105 transition-all duration-300 shadow-xs">
                                <svg class="size-4 fill-current text-blue-300" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                <span>Facebook</span>
                            </a>
                            <a href="https://www.tiktok.com/@dr.andresaguilarvet" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-slate-900/80 border border-white/20 px-3.5 py-1.5 text-xs font-bold hover:bg-black hover:scale-105 transition-all duration-300 shadow-xs">
                                <svg class="size-4 fill-current text-white" viewBox="0 0 24 24">
                                    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.29 0 .56.04.82.12V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.86 4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.04-.12z"/>
                                </svg>
                                <span>TikTok (@dr.andresaguilarvet)</span>
                            </a>
                        </div>

                        <!-- CTA Buttons -->
                        <div class="mt-7 flex flex-wrap items-center gap-4 animate-hero-5">
                            <a href="#contacto" class="landing-cta relative overflow-hidden rounded-full px-7 py-4 text-sm font-bold shadow-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:brightness-110 active:translate-y-0 group">
                                <span class="relative z-10">Agenda una consulta</span>
                                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                            </a>
                            <a href="#servicios" class="group flex items-center gap-3 px-3 py-4 text-sm font-bold text-white transition-colors duration-300 hover:text-blue-300">
                                Conoce nuestros servicios
                                <span class="grid size-8 place-items-center rounded-full border border-white/30 transition-all duration-300 group-hover:translate-x-1.5 group-hover:border-white group-hover:bg-white/10">→</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Rating Float Card -->
                <div class="landing-rating absolute bottom-0 right-0 hidden rounded-tl-[36px] px-10 py-7 lg:block animate-hero-5 animate-float shadow-xl">
                    <div class="flex items-center gap-4">
                        <div class="landing-stars text-xl tracking-wider animate-pulse-glow-text">★★★★★</div>
                        <div>
                            <p class="landing-ink font-sans text-2xl font-extrabold tracking-tight">4.7 / 5</p>
                            <p class="landing-muted text-xs">Familias que confían en nosotros</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección Servicios -->
            <section id="servicios" class="landing-section px-6 py-20 lg:px-10 lg:py-28">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end reveal-fade-up">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-[0.25em] text-blue-600">Servicios Veterinarios</span>
                            <h2 class="landing-ink mt-3 font-sans text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight">
                                Todo lo que necesitan, <span class="landing-soft-title italic">en un mismo lugar</span>
                            </h2>
                        </div>
                        <p class="landing-muted max-w-md text-xs sm:text-sm leading-relaxed">
                            Atención médica especializada, urgencias 24 horas y certificados zoosanitarios internacionales en Cancún.
                        </p>
                    </div>

                    <!-- Filtros Rápidos -->
                    <div class="mt-8 flex overflow-x-auto no-scrollbar items-center gap-2 pb-2 sm:flex-wrap reveal-fade-up delay-100">
                        <button
                            v-for="cat in serviceCategories"
                            :key="cat.id"
                            @click="selectedServiceCategory = cat.id"
                            :class="[
                                'shrink-0 rounded-full px-5 py-2 text-xs font-bold transition-all duration-300 cursor-pointer hover:scale-105 active:scale-95',
                                selectedServiceCategory === cat.id
                                    ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
                                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                            ]"
                        >
                            {{ cat.label }} ({{ cat.count }})
                        </button>
                    </div>

                    <!-- Grid de Tarjetas Animadas con TransitionGroup -->
                    <TransitionGroup
                        name="grid-transition"
                        tag="div"
                        class="mt-8 grid gap-5 sm:gap-6 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <article
                            v-for="(service, sIdx) in filteredServices"
                            :key="service.id"
                            :class="['landing-card group flex flex-col justify-between rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs transition-all duration-500 hover:-translate-y-2 hover:border-blue-400 hover:shadow-2xl reveal-fade-up', `delay-${(sIdx % 4 + 1) * 100}`]"
                        >
                            <div>
                                <!-- Header de Tarjeta: Badge de Categoría + Número -->
                                <div class="flex items-center justify-between gap-2">
                                    <span
                                        class="inline-block rounded-full border px-3 py-1 text-[11px] font-bold tracking-wide transition-transform duration-300 group-hover:scale-105"
                                        :class="service.badgeClass"
                                    >
                                        {{ service.badge }}
                                    </span>
                                    <span class="font-sans text-xl font-black text-slate-300 group-hover:text-blue-500 transition-colors duration-300">
                                        {{ service.number }}
                                    </span>
                                </div>

                                <!-- Título Claro -->
                                <h3 class="font-sans text-lg font-bold tracking-tight text-slate-900 mt-4 leading-snug group-hover:text-blue-600 transition-colors duration-300">
                                    {{ service.title }}
                                </h3>

                                <!-- Descripción Concisa -->
                                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                    {{ service.description }}
                                </p>

                                <!-- Viñetas -->
                                <ul class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                                    <li
                                        v-for="highlight in service.highlights"
                                        :key="highlight"
                                        class="flex items-center gap-2 text-xs font-medium text-slate-700 group-hover:translate-x-1 transition-transform duration-300"
                                    >
                                        <span class="size-1.5 rounded-full bg-blue-500 shrink-0 group-hover:scale-125 transition-transform duration-300"></span>
                                        <span>{{ highlight }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Botón de Acción -->
                            <a
                                :href="service.ctaLink"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-6 flex items-center justify-between rounded-2xl bg-slate-50 border border-slate-200 px-4 py-3 text-xs font-bold text-slate-800 transition-all duration-300 group-hover:bg-blue-600 group-hover:text-white group-hover:border-blue-600 group-hover:shadow-md"
                            >
                                <span>{{ service.ctaText }}</span>
                                <span class="transition-transform duration-300 group-hover:translate-x-1.5">→</span>
                            </a>
                        </article>
                    </TransitionGroup>
                </div>
            </section>

            <!-- Sección Nosotros / Dr. Andrés Aguilar -->
            <section id="nosotros" class="landing-surface px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl reveal-zoom-in">
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm sm:p-12 lg:p-16 transition-shadow duration-500 hover:shadow-xl">
                        <div class="grid gap-12 lg:grid-cols-12 lg:items-center">
                            <!-- Retrato Minimalista -->
                            <div class="lg:col-span-5 reveal-fade-right">
                                <div class="group relative mx-auto max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-md transition-all duration-500 hover:shadow-2xl hover:-translate-y-1">
                                    <img
                                        :src="drAndresPhoto"
                                        alt="Dr. Andrés Aguilar - Médico Veterinario Zootecnista"
                                        class="aspect-[4/5] w-full object-cover object-top transition-transform duration-700 group-hover:scale-105"
                                    />
                                    <div class="border-t border-slate-200 bg-slate-900 px-6 py-4 text-white transition-colors duration-300 group-hover:bg-blue-950">
                                        <p class="text-sm font-semibold tracking-wide flex items-center gap-2">
                                            Dr. Andrés Aguilar
                                            <span class="inline-block size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                        </p>
                                        <p class="text-xs text-slate-400 mt-0.5">Médico Veterinario Zootecnista · 5 Años de Experiencia</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Contenido Tipográfico -->
                            <div class="lg:col-span-7 reveal-fade-left">
                                <span class="text-xs font-bold uppercase tracking-[0.25em] text-blue-600">
                                    Sobre Nosotros · Médico Veterinario Titular
                                </span>

                                <h2 class="landing-ink mt-3 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">
                                    Dr. Andrés Aguilar
                                </h2>

                                <p class="text-sm font-medium text-slate-500 mt-2">
                                    Director Clínico · Small Animal Clinic Cancún
                                </p>

                                <p class="landing-muted mt-6 text-base sm:text-lg leading-relaxed">
                                    Con más de 5 años de práctica clínica ofreciendo atención veterinaria de primer nivel en Cancún, el Dr. Andrés Aguilar lidera Small Animal Clinic. Su trabajo se enfoca en el diagnóstico oportuno, tratamientos éticos y una atención cercana y transparente para la tranquilidad de cada familia.
                                </p>

                                <!-- Grid Informativo Minimalista -->
                                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 transition-all duration-300 hover:border-blue-300 hover:bg-blue-50/40 hover:-translate-y-0.5 reveal-fade-up delay-100">
                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Especialidades</p>
                                        <p class="text-sm font-semibold text-slate-900 mt-1">Dermatología, Oncología, Cardiología y Odontología.</p>
                                    </div>

                                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 transition-all duration-300 hover:border-blue-300 hover:bg-blue-50/40 hover:-translate-y-0.5 reveal-fade-up delay-200">
                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Atención 24/7</p>
                                        <p class="text-sm font-semibold text-slate-900 mt-1">Consultas con cita, urgencias médicas y visitas a domicilio.</p>
                                    </div>

                                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 transition-all duration-300 hover:border-blue-300 hover:bg-blue-50/40 hover:-translate-y-0.5 reveal-fade-up delay-300">
                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Trámites de Vuelo</p>
                                        <p class="text-sm font-semibold text-slate-900 mt-1">Certificados zoosanitarios oficiales SENASICA en el Aeropuerto.</p>
                                    </div>

                                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4 transition-all duration-300 hover:border-blue-300 hover:bg-blue-50/40 hover:-translate-y-0.5 reveal-fade-up delay-400">
                                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Filosofía Médica</p>
                                        <p class="text-sm font-semibold text-slate-900 mt-1">Diagnósticos precisos y trato humano sin sobrecostos innecesarios.</p>
                                    </div>
                                </div>

                                <!-- Cita Minimalista -->
                                <blockquote class="mt-8 border-l-2 border-blue-600 pl-4 py-1 text-sm text-slate-600 italic reveal-fade-up delay-300">
                                    "Cada mascota merece un trato digno y médico riguroso. Nos enfocamos en dar respuestas claras a los propietarios y el mejor cuidado a sus compañeros."
                                </blockquote>

                                <!-- Enlaces Minimalistas -->
                                <div class="mt-8 flex flex-wrap items-center gap-4 reveal-fade-up delay-400">
                                    <a
                                        href="https://wa.me/529981046082"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-full bg-slate-900 px-7 py-3.5 text-xs font-bold text-white transition-all duration-300 hover:bg-blue-600 hover:shadow-lg hover:-translate-y-0.5 shadow-xs"
                                    >
                                        Contactar al Dr. Andrés
                                    </a>
                                    <a
                                        href="#certificaciones"
                                        class="rounded-full border border-slate-300 bg-white px-6 py-3.5 text-xs font-bold text-slate-700 transition-all duration-300 hover:border-slate-900 hover:bg-slate-50 hover:-translate-y-0.5"
                                    >
                                        Ver Certificaciones
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Certificaciones Médicas & QR Section -->
            <section id="certificaciones" class="landing-section px-6 py-20 lg:px-10">
                <div class="mx-auto max-w-7xl reveal-zoom-in">
                    <div class="landing-hero relative overflow-hidden rounded-[36px] p-8 text-white shadow-2xl sm:p-12 lg:p-16 transition-transform duration-500 hover:scale-[1.01]">
                        <div class="relative z-10 grid gap-10 lg:grid-cols-12 lg:items-center">
                            <div class="lg:col-span-7 reveal-fade-right">
                                <div class="inline-flex items-center gap-2.5 rounded-full border border-blue-500/20 bg-blue-500/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-blue-600">
                                    <img src="/images/logo_without_name.png" alt="Logo" class="size-4 object-contain" />
                                    <span>Cédula & Acreditaciones</span>
                                </div>

                                <h2 class="mt-4 font-sans text-3xl font-extrabold tracking-tight leading-tight text-white sm:text-4xl lg:text-5xl">
                                    Certificaciones Médicas del <span class="landing-highlight animate-shimmer-text italic">Dr. Andrés Aguilar</span>
                                </h2>

                                <p class="mt-5 text-base sm:text-lg leading-relaxed text-white/90">
                                    Con <strong>5 años de experiencia profesional</strong>, el Dr. Andrés Aguilar cuenta con Cédula Profesional Veterinaria y certificación acreditada para expedición de Certificados Internacionales de Salud para perros y gatos (SENASICA / SAGARPA).
                                </p>

                                <div class="mt-7 flex flex-wrap gap-3 text-xs font-semibold text-white">
                                    <div class="flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 backdrop-blur-md border border-white/10 transition-transform duration-300 hover:scale-105">
                                        <span class="text-amber-300">✓</span> Cédula Profesional Médica Veterinaria
                                    </div>
                                    <div class="flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 backdrop-blur-md border border-white/10 transition-transform duration-300 hover:scale-105">
                                        <span class="text-amber-300">✓</span> Certificación Zoosanitaria Internacional
                                    </div>
                                    <div class="flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 backdrop-blur-md border border-white/10 transition-transform duration-300 hover:scale-105">
                                        <span class="text-amber-300">✓</span> Trámites Aeropuerto de Cancún
                                    </div>
                                </div>
                            </div>

                            <!-- QR Code Container -->
                            <div class="flex flex-col items-center justify-center lg:col-span-5 reveal-fade-left">
                                <div class="group relative rounded-3xl bg-white p-6 shadow-2xl transition-all duration-500 hover:scale-105 hover:shadow-blue-500/20 animate-float">
                                    <div class="relative size-56 overflow-hidden rounded-2xl bg-white p-2 flex items-center justify-center border border-slate-100 transition-transform duration-300 group-hover:scale-102">
                                        <img
                                            src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=https://smallanimalclinic.mx/certificaciones-dr-andres"
                                            alt="Código QR Certificaciones Médicas Dr. Andrés"
                                            class="size-full object-contain"
                                        />
                                    </div>
                                    <div class="mt-4 text-center">
                                        <p class="text-xs font-bold text-slate-900 group-hover:text-blue-600 transition-colors">Escanea para validar</p>
                                        <p class="text-[11px] text-slate-500 mt-0.5">Certificaciones e Historial Médico</p>
                                    </div>
                                </div>

                                <a
                                    href="https://smallanimalclinic.mx/certificaciones-dr-andres"
                                    target="_blank"
                                    class="mt-6 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/15 px-7 py-3 text-xs font-bold text-white transition-all duration-300 hover:bg-white/30 hover:scale-105 backdrop-blur-md shadow-md"
                                >
                                    <span>Ver Certificaciones Directamente</span>
                                    <span class="transition-transform group-hover:translate-x-1">↗</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Ubicación & Google Maps Section -->
            <section id="ubicacion" class="landing-surface px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col items-center text-center reveal-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-blue-600">
                            <span>📍 Ubicación & Cobertura</span>
                        </div>

                        <h2 class="landing-ink mt-4 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">
                            Encuéntranos en <span class="landing-soft-title italic">Cancún</span>
                        </h2>

                        <p class="landing-muted mt-4 max-w-2xl text-base leading-relaxed">
                            Visítanos en nuestra clínica veterinaria o solicita atención médica a domicilio y en el Aeropuerto Internacional de Cancún.
                        </p>
                    </div>

                    <div class="mt-12 grid gap-8 lg:grid-cols-12 lg:items-center">
                        <!-- Information Side Cards -->
                        <div class="space-y-5 lg:col-span-5 reveal-fade-right">
                            <div class="landing-card rounded-3xl border bg-white p-7 shadow-xs transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:border-blue-300">
                                <div class="flex items-start gap-4">
                                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600 text-xl font-bold transition-transform duration-300 hover:scale-110">
                                        📍
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-lg">Dirección Principal</h3>
                                        <p class="mt-1 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                            Cancún, Quintana Roo, México.<br />
                                            <span class="text-[11px] text-slate-400 font-medium">Ubicación verificada en Google Maps</span>
                                        </p>
                                        <a
                                            href="https://maps.app.goo.gl/VH5bcayUB5Vhity57"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-4 inline-flex items-center gap-2 rounded-full bg-blue-600 px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-blue-700 hover:-translate-y-0.5 hover:shadow-lg"
                                        >
                                            <span>Abrir en Google Maps</span>
                                            <span class="transition-transform group-hover:translate-x-1">↗</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="landing-card rounded-3xl border bg-white p-7 shadow-xs transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:border-amber-300 delay-100">
                                <div class="flex items-start gap-4">
                                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-amber-50 text-amber-600 text-xl font-bold transition-transform duration-300 hover:scale-110">
                                        ✈️
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-lg">Atención en Aeropuerto</h3>
                                        <p class="mt-1 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                            Asistencia veterinaria de urgencia y entrega de Certificados Zoosanitarios en las terminales del Aeropuerto Internacional de Cancún.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="landing-card rounded-3xl border bg-white p-7 shadow-xs transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:border-emerald-300 delay-200">
                                <div class="flex items-start gap-4">
                                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 text-xl font-bold transition-transform duration-300 hover:scale-110">
                                        🏠
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-lg">Servicio a Domicilio 24h</h3>
                                        <p class="mt-1 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                            Cobertura médica veterinaria en Cancún para emergencias y consultas en la comodidad de tu hogar.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Interactive Map Embed Container -->
                        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl lg:col-span-7 reveal-fade-left delay-200 transition-all duration-500 hover:shadow-2xl">
                            <div class="relative w-full h-[460px]">
                                <iframe
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3721.2827116828557!2d-86.8515!3d21.141!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMjHCsDA4JzI3LjYiTiA4NsKwNTEnMDUuNCJX!5e0!3m2!1ses!2smx!4v1700000000000!5m2!1ses!2smx"
                                    class="size-full border-0"
                                    allowfullscreen=""
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    title="Ubicación Small Animal Clinic en Google Maps"
                                ></iframe>

                                <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between rounded-2xl bg-slate-900/90 p-4 text-white backdrop-blur-md transition-all duration-300 hover:bg-slate-900">
                                    <div class="flex items-center gap-3">
                                        <img src="/images/logo_without_name.png" alt="Logo Small Animal Clinic" class="size-9 object-contain shrink-0" />
                                        <div>
                                            <p class="text-xs font-bold text-white">Small Animal Clinic · Cancún</p>
                                            <p class="text-[11px] text-slate-300">4.7 ★★★★★ (25 opiniones en Google)</p>
                                        </div>
                                    </div>
                                    <a
                                        href="https://maps.app.goo.gl/VH5bcayUB5Vhity57"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-full bg-blue-500 px-4 py-2 text-xs font-bold text-white transition-all duration-300 hover:bg-blue-400 hover:scale-105 shrink-0"
                                    >
                                        Cómo llegar ↗
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Stats Section -->
            <section class="landing-stats px-6 py-20 text-white lg:px-10 reveal-zoom-in">
                <div class="mx-auto grid max-w-7xl gap-10 text-center sm:grid-cols-3">
                    <div class="reveal-fade-up delay-100 group">
                        <p class="landing-highlight font-sans text-5xl font-extrabold tracking-tight transition-transform duration-300 group-hover:scale-110 inline-block">+5,000</p>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-white/65 group-hover:text-white transition-colors">Pacientes atendidos</p>
                    </div>
                    <div class="border-white/15 sm:border-x reveal-fade-up delay-200 group">
                        <p class="landing-highlight font-sans text-5xl font-extrabold tracking-tight transition-transform duration-300 group-hover:scale-110 inline-block">5 Años</p>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-white/65 group-hover:text-white transition-colors">De Experiencia Médica</p>
                    </div>
                    <div class="reveal-fade-up delay-300 group">
                        <p class="landing-highlight font-sans text-5xl font-extrabold tracking-tight transition-transform duration-300 group-hover:scale-110 inline-block">7 días</p>
                        <p class="mt-3 text-xs font-bold uppercase tracking-[0.2em] text-white/65 group-hover:text-white transition-colors">Atención a la semana</p>
                    </div>
                </div>
            </section>

            <!-- Sección Opiniones -->
            <section id="opiniones" class="landing-section px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl">
                    <!-- Section Header -->
                    <div class="flex flex-col items-center text-center reveal-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-blue-600">
                            <span>Google Reviews</span>
                            <span class="text-amber-400 animate-pulse-glow-text">★★★★★</span>
                        </div>

                        <h2 class="landing-ink mt-4 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">
                            Lo que nuestras familias <span class="landing-soft-title italic">opinan de nosotros</span>
                        </h2>

                        <p class="landing-muted mt-4 max-w-2xl text-base leading-relaxed">
                            Reseñas reales y verificadas de nuestros clientes en Google Maps. Médicos veterinarios de confianza para trámites de vuelo, urgencias a domicilio y atención en clínica.
                        </p>

                        <!-- Overall rating summary card -->
                        <a href="https://maps.app.goo.gl/VH5bcayUB5Vhity57" target="_blank" rel="noopener noreferrer" class="mt-8 flex flex-wrap items-center justify-center gap-6 rounded-2xl border border-slate-200/80 bg-white px-8 py-4 shadow-xs hover:border-blue-300 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group reveal-zoom-in delay-100">
                            <div class="flex items-center gap-3">
                                <svg class="size-8 shrink-0 group-hover:scale-110 transition-transform duration-300" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <div class="text-left">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-lg">4.7 / 5.0</span>
                                        <span class="text-amber-400 text-sm">★★★★★</span>
                                    </div>
                                    <p class="text-xs text-slate-500 flex items-center gap-1">
                                        25 opiniones verificadas en Google Maps <span class="text-[10px] text-blue-600 font-bold group-hover:translate-x-0.5 transition-transform">↗</span>
                                    </p>
                                </div>
                            </div>
                        </a>

                        <!-- Categories Filter -->
                        <div class="mt-10 flex flex-wrap items-center justify-center gap-2 reveal-fade-up delay-200">
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                @click="selectedCategory = cat.id"
                                :class="[
                                    'rounded-full px-5 py-2.5 text-xs font-bold transition-all duration-300 cursor-pointer hover:scale-105 active:scale-95',
                                    selectedCategory === cat.id
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20'
                                        : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                                ]"
                            >
                                {{ cat.label }} ({{ cat.count }})
                            </button>
                        </div>
                    </div>

                    <!-- Reviews Grid Animada con TransitionGroup -->
                    <TransitionGroup
                        name="grid-transition"
                        tag="div"
                        class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="(review, index) in filteredReviews"
                            :key="review.id"
                            :class="['landing-card flex flex-col justify-between rounded-3xl border bg-white p-7 shadow-xs transition-all duration-500 hover:-translate-y-2 hover:shadow-xl reveal-fade-up', `delay-${(index % 3 + 1) * 100}`]"
                        >
                            <div>
                                <!-- Header with Author Info & Google Icon -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-inner transition-transform duration-300 hover:scale-110"
                                            :style="{ backgroundColor: getAvatarBg(index) }"
                                        >
                                            {{ getInitials(review.author) }}
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-slate-900 leading-tight flex items-center gap-1.5 text-sm">
                                                {{ review.author }}
                                                <span v-if="review.badge" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                                    ★ {{ review.badge }}
                                                </span>
                                            </h3>
                                            <p class="text-xs text-slate-400 mt-0.5">{{ review.stats }} · {{ review.date }}</p>
                                        </div>
                                    </div>
                                    <svg class="size-5 shrink-0 opacity-70" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                    </svg>
                                </div>

                                <!-- Stars -->
                                <div class="mt-4 flex items-center gap-1 text-amber-400 text-sm">
                                    <span v-for="star in review.rating" :key="star">★</span>
                                    <span v-for="star in (5 - review.rating)" :key="'empty-' + star" class="text-slate-200">★</span>
                                </div>

                                <!-- Review Text -->
                                <p class="landing-muted mt-3 text-sm leading-relaxed">
                                    “{{ review.text }}”
                                </p>

                                <!-- Owner Response -->
                                <div v-if="review.ownerResponse" class="mt-3.5 rounded-2xl bg-blue-50/80 p-3.5 border border-blue-100 text-xs transition-colors duration-300 hover:bg-blue-50">
                                    <p class="font-bold text-blue-950 flex items-center gap-1.5 mb-1">
                                        <span>🐾</span> Respuesta del Dr. Andrés / Clínica:
                                    </p>
                                    <p class="text-blue-900 italic">"{{ review.ownerResponse }}"</p>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>

                    <!-- Show More Button -->
                    <div v-if="selectedCategory === 'todos' && !showAllReviews" class="mt-12 text-center reveal-fade-up">
                        <button
                            @click="showAllReviews = true"
                            class="landing-primary-btn group inline-flex items-center gap-2 rounded-full px-8 py-3.5 text-sm font-bold text-white shadow-md transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:bg-blue-700 cursor-pointer active:translate-y-0"
                        >
                            Ver todas las opiniones (25)
                            <span class="transition-transform duration-300 group-hover:translate-y-1">↓</span>
                        </button>
                    </div>
                </div>
            </section>


            <!-- Redes Sociales Section -->
            <section id="redes" class="landing-section px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col items-center text-center reveal-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-blue-600">
                            <span>📱 Comunidad & Redes Sociales</span>
                        </div>

                        <h2 class="landing-ink mt-4 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">
                            Síguenos y <span class="landing-soft-title italic">conecta con nosotros</span>
                        </h2>

                        <p class="landing-muted mt-4 max-w-2xl text-base leading-relaxed">
                            Descubre consejos médicos para mascotas, recomendaciones de salud, historias clínicas y contenido exclusivo del Dr. Andrés Aguilar.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Facebook Card -->
                        <a
                            href="https://www.facebook.com/profile.php?id=61562957610885"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="landing-card group flex flex-col justify-between rounded-3xl border bg-white p-7 transition-all duration-500 hover:-translate-y-3 hover:border-blue-400 hover:shadow-2xl hover:shadow-blue-500/15 reveal-fade-up delay-100"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="grid size-13 place-items-center rounded-2xl bg-blue-600 text-white shadow-md transition-transform duration-300 group-hover:scale-110">
                                        <svg class="size-6 fill-current" viewBox="0 0 24 24">
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                        </svg>
                                    </span>
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-[11px] font-bold text-blue-700">Página Oficial</span>
                                </div>
                                <h3 class="font-sans text-xl font-bold tracking-tight text-slate-900 mt-6 group-hover:text-blue-600 transition-colors">Facebook</h3>
                                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                    Publicaciones, casos de éxito, recomendaciones veterinarias y novedades de la clínica.
                                </p>
                            </div>
                            <div class="mt-6 flex items-center justify-between text-xs font-bold text-blue-600">
                                <span>Visitar Facebook</span>
                                <span class="transition-transform duration-300 group-hover:translate-x-1.5">↗</span>
                            </div>
                        </a>

                        <!-- TikTok Card -->
                        <a
                            href="https://www.tiktok.com/@dr.andresaguilarvet"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="landing-card group flex flex-col justify-between rounded-3xl border bg-white p-7 transition-all duration-500 hover:-translate-y-3 hover:border-slate-800 hover:shadow-2xl hover:shadow-slate-900/15 reveal-fade-up delay-200"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="grid size-13 place-items-center rounded-2xl bg-slate-900 text-white shadow-md transition-transform duration-300 group-hover:scale-110">
                                        <svg class="size-6 fill-current" viewBox="0 0 24 24">
                                            <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.29 0 .56.04.82.12V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.86 4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.04-.12z"/>
                                        </svg>
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-800">@dr.andresaguilarvet</span>
                                </div>
                                <h3 class="font-sans text-xl font-bold tracking-tight text-slate-900 mt-6 group-hover:text-slate-800 transition-colors">TikTok</h3>
                                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                    Videos educativos de salud veterinaria, tips rápidos para tu perro o gato y el día a día en consulta.
                                </p>
                            </div>
                            <div class="mt-6 flex items-center justify-between text-xs font-bold text-slate-900">
                                <span>Seguir en TikTok</span>
                                <span class="transition-transform duration-300 group-hover:translate-x-1.5">↗</span>
                            </div>
                        </a>

                        <!-- Email Card -->
                        <a
                            href="mailto:smallanimalcliniccancun@gmail.com"
                            class="landing-card group flex flex-col justify-between rounded-3xl border bg-white p-7 transition-all duration-500 hover:-translate-y-3 hover:border-red-400 hover:shadow-2xl hover:shadow-red-500/15 reveal-fade-up delay-300"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="grid size-13 place-items-center rounded-2xl bg-red-500 text-white text-2xl shadow-md transition-transform duration-300 group-hover:scale-110">
                                        ✉️
                                    </span>
                                    <span class="rounded-full bg-red-50 px-3 py-1 text-[11px] font-bold text-red-700">Correo</span>
                                </div>
                                <h3 class="font-sans text-xl font-bold tracking-tight text-slate-900 mt-6 group-hover:text-red-600 transition-colors">Email</h3>
                                <p class="text-xs text-slate-500 mt-2 leading-relaxed truncate" title="smallanimalcliniccancun@gmail.com">
                                    smallanimalcliniccancun@gmail.com
                                </p>
                                <p class="text-[11px] text-slate-400 mt-1">Consultas formales y certificados.</p>
                            </div>
                            <div class="mt-6 flex items-center justify-between text-xs font-bold text-red-600">
                                <span>Enviar Email</span>
                                <span class="transition-transform duration-300 group-hover:translate-x-1.5">→</span>
                            </div>
                        </a>

                        <!-- WhatsApp Card -->
                        <a
                            href="https://wa.me/529981046082"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="landing-card group flex flex-col justify-between rounded-3xl border bg-white p-7 transition-all duration-500 hover:-translate-y-3 hover:border-emerald-400 hover:shadow-2xl hover:shadow-emerald-500/15 reveal-fade-up delay-400"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="grid size-13 place-items-center rounded-2xl bg-emerald-500 text-white shadow-md transition-transform duration-300 group-hover:scale-110">
                                        <svg class="size-6 fill-current" viewBox="0 0 24 24">
                                            <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984 0 1.758.459 3.474 1.33 4.982L2 22l5.166-1.347a9.923 9.923 0 0 0 4.845 1.258h.005c5.505 0 9.988-4.478 9.989-9.985 0-2.666-1.037-5.172-2.924-7.058A9.914 9.914 0 0 0 12.012 2zm5.41 13.336c-.226.634-1.312 1.213-1.803 1.252-.464.037-1.047.147-3.415-.83-2.91-1.2-4.757-4.175-4.903-4.37-.145-.195-1.187-1.579-1.187-3.013 0-1.433.748-2.14 1.014-2.433.266-.292.58-.366.774-.366.194 0 .387.002.556.009.18.007.423-.069.662.505.247.593.844 2.062.917 2.21.073.147.121.32.024.512-.097.195-.146.316-.292.487-.146.17-.307.381-.439.512-.146.146-.299.305-.128.598.17.292.756 1.248 1.625 2.022 1.118.996 2.06 1.305 2.353 1.452.292.146.463.122.634-.073.17-.195.73-0.852.925-1.144.195-.293.39-.244.657-.146.268.097 1.697.801 1.989.947.292.146.487.219.56.341.073.122.073.707-.153 1.341z"/>
                                        </svg>
                                    </span>
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700">24/7 Disponible</span>
                                </div>
                                <h3 class="font-sans text-xl font-bold tracking-tight text-slate-900 mt-6 group-hover:text-emerald-600 transition-colors">WhatsApp</h3>
                                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                                    Agendado directo de citas médicas, trámites de vuelo urgentes y atención rápida.
                                </p>
                            </div>
                            <div class="mt-6 flex items-center justify-between text-xs font-bold text-emerald-600">
                                <span>Abrir Chat</span>
                                <span class="transition-transform duration-300 group-hover:translate-x-1.5">↗</span>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Sección Contacto / CTA -->
            <!-- <section id="contacto" class="landing-section px-6 pb-24 lg:px-10 lg:pb-32">
                <div class="landing-cta-band group relative mx-auto max-w-7xl overflow-hidden rounded-[36px] px-7 py-14 sm:px-12 lg:px-20 lg:py-20 reveal-zoom-in shadow-2xl">
                    <img src="/images/logo_without_name.png" alt="Logo Watermark" class="absolute -right-8 -bottom-10 h-72 w-auto opacity-15 pointer-events-none object-contain transition-transform duration-700 group-hover:scale-110 group-hover:rotate-3" />
                    <div class="relative flex flex-col justify-between gap-10 lg:flex-row lg:items-center">
                        <div class="max-w-2xl reveal-fade-right">
                            <p class="landing-eyebrow text-xs font-bold uppercase tracking-[0.24em]">Atención 24/7 · 7 días a la semana</p>
                            <h2 class="landing-ink mt-4 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">Hagamos equipo por su bienestar.</h2>
                            <p class="landing-muted mt-4 text-base">Agenda tu consulta médica veterinaria en Cancún.</p>
                            <div class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900/10 px-4 py-2 border border-slate-900/15 text-xs font-semibold text-slate-900 transition-all duration-300 hover:bg-slate-900/15">
                                <span>📌</span>
                                <span>Atención CON CITA PREVIA. Consultas <u>SIN CITA</u> tienen costo adicional.</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3 reveal-fade-left">
                            <a href="tel:+529981046082" class="landing-primary-btn rounded-full px-7 py-4 text-sm font-bold text-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:bg-blue-900">Llamar ahora</a>
                            <a href="https://wa.me/529981046082" target="_blank" rel="noopener noreferrer" class="landing-outline-btn rounded-full border px-7 py-4 text-sm font-bold transition-all duration-300 hover:-translate-y-1 hover:bg-white/40">WhatsApp</a>
                            <a href="https://maps.app.goo.gl/VH5bcayUB5Vhity57" target="_blank" rel="noopener noreferrer" class="landing-outline-btn rounded-full border px-7 py-4 text-sm font-bold transition-all duration-300 hover:-translate-y-1 hover:bg-white/40 flex items-center gap-1.5">
                                📍 Ver Ubicación ↗
                            </a>
                        </div>
                    </div>
                </div>
            </section> -->
        </main>

        <!-- Footer -->
        <footer class="landing-footer px-6 pb-8 pt-16 text-white/70 lg:px-10 reveal-fade-up">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-12 border-b border-white/10 pb-12 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-1">
                        <img src="/images/logo_with_name.png" alt="Small Animal Clinic Logo" class="h-12 w-auto object-contain mb-4 transition-transform duration-300 hover:scale-105" />
                        <p class="text-xs leading-6 text-white/60">Atención veterinaria profesional y humana para perros y gatos en Cancún.</p>
                    </div>
                    <div>
                        <p class="landing-highlight text-xs font-bold uppercase tracking-[0.2em]">Visítanos & Cobertura</p>
                        <p class="mt-4 text-xs leading-6 text-white/80">Cancún, Quintana Roo, México</p>
                        <a href="https://maps.app.goo.gl/VH5bcayUB5Vhity57" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-blue-300 hover:text-white transition-colors duration-300">
                            📍 Google Maps ↗
                        </a>
                    </div>
                    <div>
                        <p class="landing-highlight text-xs font-bold uppercase tracking-[0.2em]">Horario & Políticas</p>
                        <p class="mt-4 text-xs leading-6 text-white/80">
                            <strong>Atención 24/7 (7 días)</strong><br />
                            <span class="text-white/70">Atención preferente CON CITA.</span><br />
                            <span class="text-amber-300 text-[11px]">Consultas SIN CITA tienen costo adicional.</span>
                        </p>
                    </div>
                    <div>
                        <p class="landing-highlight text-xs font-bold uppercase tracking-[0.2em]">Redes & Contacto</p>
                        <div class="mt-4 flex flex-col gap-2.5 text-xs">
                            <a href="https://www.facebook.com/profile.php?id=61562957610885" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors duration-300 flex items-center gap-2">
                                <svg class="size-3.5 fill-current text-blue-400" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                <span>Facebook Oficial</span>
                            </a>
                            <a href="https://www.tiktok.com/@dr.andresaguilarvet" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors duration-300 flex items-center gap-2">
                                <svg class="size-3.5 fill-current text-white" viewBox="0 0 24 24">
                                    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.29 0 .56.04.82.12V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.86 4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.04-.12z"/>
                                </svg>
                                <span>TikTok @dr.andresaguilarvet</span>
                            </a>
                            <a href="https://wa.me/529981046082" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors duration-300 flex items-center gap-2">
                                <svg class="size-3.5 fill-current text-emerald-400" viewBox="0 0 24 24">
                                    <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984 0 1.758.459 3.474 1.33 4.982L2 22l5.166-1.347a9.923 9.923 0 0 0 4.845 1.258h.005c5.505 0 9.988-4.478 9.989-9.985 0-2.666-1.037-5.172-2.924-7.058A9.914 9.914 0 0 0 12.012 2zm5.41 13.336c-.226.634-1.312 1.213-1.803 1.252-.464.037-1.047.147-3.415-.83-2.91-1.2-4.757-4.175-4.903-4.37-.145-.195-1.187-1.579-1.187-3.013 0-1.433.748-2.14 1.014-2.433.266-.292.58-.366.774-.366.194 0 .387.002.556.009.18.007.423-.069.662.505.247.593.844 2.062.917 2.21.073.147.121.32.024.512-.097.195-.146.316-.292.487-.146.17-.307.381-.439.512-.146.146-.299.305-.128.598.17.292.756 1.248 1.625 2.022 1.118.996 2.06 1.305 2.353 1.452.292.146.463.122.634-.073.17-.195.73-0.852.925-1.144.195-.293.39-.244.657-.146.268.097 1.697.801 1.989.947.292.146.487.219.56.341.073.122.073.707-.153 1.341z"/>
                                </svg>
                                <span>WhatsApp Directo</span>
                            </a>
                            <a href="mailto:smallanimalcliniccancun@gmail.com" class="hover:text-white transition-colors duration-300 flex items-center gap-2 truncate">
                                <span>✉️</span>
                                <span>smallanimalcliniccancun@gmail.com</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-3 pt-7 text-xs text-white/40 sm:flex-row sm:justify-between">
                    <p>© 2026 Small Animal Clinic. Todos los derechos reservados.</p>
                    <p>Hecho con cariño para las mascotas de Cancún.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

html {
    scroll-behavior: smooth;
}

.font-sans {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
}


.landing {
    background: var(--landing-bg, #f3f7fc);
    color: var(--landing-ink, #0c2440);
}

.landing-cta {
    background: var(--landing-cta, #60a5fa);
    color: var(--landing-cta-text, #0c2440);
}

.landing-cta:hover {
    filter: brightness(1.06);
}

.landing-hero {
    background: var(--landing-primary, #1a4b8c);
}

.landing-hero-overlay {
    background: linear-gradient(
        to right,
        color-mix(in srgb, var(--landing-hero-from, #0f3a6e) 96%, transparent),
        color-mix(in srgb, var(--landing-hero-from, #0f3a6e) 78%, transparent),
        color-mix(in srgb, var(--landing-hero-from, #0f3a6e) 18%, transparent)
    );
}

.landing-dot,
.landing-stars,
.landing-highlight {
    color: var(--landing-accent, #93c5fd);
}

.landing-ink {
    color: var(--landing-ink, #0c2440);
}

.landing-muted {
    color: var(--landing-muted, #5b738c);
}

.landing-eyebrow,
.landing-link {
    color: var(--landing-accent-strong, #2563eb);
}

.landing-soft-title {
    color: color-mix(in srgb, var(--landing-primary, #1a4b8c) 72%, white);
}

.landing-rating,
.landing-section {
    background: var(--landing-bg, #f3f7fc);
}

.landing-card {
    border-color: var(--landing-border, #c5d8ef);
}

.landing-card:hover {
    box-shadow: 0 24px 50px color-mix(in srgb, var(--landing-primary, #1a4b8c) 16%, transparent);
}

.landing-icon {
    background: var(--landing-primary-soft, #dbeafe);
    color: var(--landing-primary, #1a4b8c);
}

.landing-card:hover .landing-icon {
    background: var(--landing-primary, #1a4b8c);
    color: white;
}

.landing-number {
    color: color-mix(in srgb, var(--landing-primary-soft, #dbeafe) 70%, white);
}

.landing-surface {
    background: var(--landing-surface, #e8f1fb);
}

.landing-badge {
    background: var(--landing-cta, #60a5fa);
    color: var(--landing-cta-text, #0c2440);
}

.landing-check,
.landing-stats,
.landing-primary-btn {
    background: var(--landing-primary, #1a4b8c);
}

.landing-cta-band {
    background: var(--landing-cta, #60a5fa);
}

.landing-outline-btn {
    border-color: color-mix(in srgb, var(--landing-ink, #0c2440) 35%, transparent);
    background: color-mix(in srgb, white 25%, transparent);
    color: var(--landing-ink, #0c2440);
}

.landing-outline-btn:hover {
    background: color-mix(in srgb, white 50%, transparent);
}

.landing-footer {
    background: var(--landing-footer, #0a1f38);
}

/* ==========================================================================
   ANIMATIONS & SCROLL REVEAL STYLES
   ========================================================================== */

/* Keyframe Animations */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}

@keyframes pulseGlowText {
    0%, 100% { text-shadow: 0 0 10px rgba(147, 197, 253, 0.4); }
    50% { text-shadow: 0 0 20px rgba(147, 197, 253, 0.9); }
}

@keyframes heroFadeUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes shimmerText {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

@keyframes bounceSlow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

/* Hero Entrance Timing */
.animate-hero-1 { animation: heroFadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.1s forwards; opacity: 0; }
.animate-hero-2 { animation: heroFadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.25s forwards; opacity: 0; }
.animate-hero-3 { animation: heroFadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.4s forwards; opacity: 0; }
.animate-hero-4 { animation: heroFadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.55s forwards; opacity: 0; }
.animate-hero-5 { animation: heroFadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.7s forwards; opacity: 0; }

.animate-float {
    animation: float 4s ease-in-out infinite;
}

.animate-pulse-glow-text {
    animation: pulseGlowText 2.5s ease-in-out infinite;
}

.animate-bounce-slow {
    animation: bounceSlow 2s ease-in-out infinite;
}

.animate-ping-slow {
    animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {
    75%, 100% {
        transform: scale(2);
        opacity: 0;
    }
}

.animate-shimmer-text {
    background: linear-gradient(90deg, #93c5fd, #ffffff, #60a5fa, #93c5fd);
    background-size: 200% auto;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: shimmerText 5s linear infinite;
}

/* Scroll Reveal Classes */
.reveal-fade-up {
    opacity: 0;
    transform: translateY(35px);
    transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal-fade-left {
    opacity: 0;
    transform: translateX(35px);
    transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal-fade-right {
    opacity: 0;
    transform: translateX(-35px);
    transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal-zoom-in {
    opacity: 0;
    transform: scale(0.93);
    transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    will-change: opacity, transform;
}

.reveal-fade-up.revealed,
.reveal-fade-left.revealed,
.reveal-fade-right.revealed,
.reveal-zoom-in.revealed {
    opacity: 1;
    transform: translateY(0) translateX(0) scale(1);
}

/* Stagger Delays */
.delay-100 { transition-delay: 100ms; }
.delay-200 { transition-delay: 200ms; }
.delay-300 { transition-delay: 300ms; }
.delay-400 { transition-delay: 400ms; }

/* Grid Vue Transition Group */
.grid-transition-enter-active {
    transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.grid-transition-leave-active {
    transition: all 0.3s ease-out;
}
.grid-transition-enter-from,
.grid-transition-leave-to {
    opacity: 0;
    transform: scale(0.92) translateY(15px);
}
.grid-transition-move {
    transition: transform 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
