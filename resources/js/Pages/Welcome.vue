<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import drAndresPhoto from '../../images/IMAGEN_ANDRES.JPG';
import draMichellePhoto from '../../images/IMAGEN_CARDIOLOGA.jpg';

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
        highlights: ['Examen físico minucioso', 'Diagnóstico', 'Plan de salud personalizado'],
        image: 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-[center_35%]',
        ctaText: 'Agendar Consulta',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20deseo%20agendar%20una%20Consulta%20General'
    },
    {
        id: 'dermatologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-violet-50 text-violet-700 border-violet-200',
        number: '02',
        title: 'Consulta de Dermatología',
        highlights: ['Pruebas de alergias y piel', 'Tratamiento de otitis y hongos', 'Control de picazón y dermatitis'],
        image: 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-[center_40%]',
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20consulta%20de%20Dermatologia'
    },
    {
        id: 'oncologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-violet-50 text-violet-700 border-violet-200',
        number: '03',
        title: 'Consulta de Oncología',
        highlights: ['Diagnóstico tumoral', 'Protocolos de quimioterapia', 'Manejo del dolor y calidad de vida'],
        image: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-center',
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20informacion%20de%20Oncologia'
    },
    {
        id: 'cardiologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-violet-50 text-violet-700 border-violet-200',
        number: '04',
        title: 'Consulta de Cardiología',
        highlights: ['Evaluación cardiovascular', 'Detección de soplos y arritmias', 'Ecocardiogramas doppler color'],
        image: 'https://images.unsplash.com/photo-1560807707-8cc77767d783?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-center',
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20consulta%20de%20Cardiologia'
    },
    {
        id: 'odontologia',
        category: 'especialidades',
        badge: 'Especialidad',
        badgeClass: 'bg-violet-50 text-violet-700 border-violet-200',
        number: '05',
        title: 'Consulta de Odontología',
        highlights: ['Profilaxis por ultrasonido', 'Tratamiento de encías y sarro', 'Solución a la halitosis bucal'],
        image: 'https://images.unsplash.com/photo-1583337130417-3346a1be7dee?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-[center_35%]',
        ctaText: 'Consultar Especialista',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20necesito%20consulta%20de%20Odontologia'
    },
    {
        id: 'importacion-exportacion',
        category: 'urgencias-viajes',
        badge: 'Viajes & Aeropuerto',
        badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        number: '06',
        title: 'Trámites de salud para viajes',
        highlights: ['Certificado de salud para vuelos nacionales e internacionales', 'Atención directa en Aeropuerto', 'Revisión médica y documental'],
        image: 'https://images.unsplash.com/photo-1544568100-847a948585b9?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-[center_25%]',
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
        highlights: ['Vacunas', 'Desparasitación completa', 'Asesoría nutricional clínica'],
        image: 'https://images.unsplash.com/photo-1516734212186-a967f81ad0d7?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-[center_40%]',
        ctaText: 'Agendar Vacunas',
        ctaLink: 'https://wa.me/529981046082?text=Hola,%20deseo%20informacion%20de%20Vacunas'
    },
    {
        id: 'urgencias-24h',
        category: 'urgencias-viajes',
        badge: 'Urgencias 24/7',
        badgeClass: 'bg-red-50 text-red-700 border-red-200',
        number: '08',
        title: 'Urgencias 24 Horas',
        highlights: ['Disponibilidad 24h los 7 días', 'Estabilización de emergencias'],
        image: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&w=900&h=650&q=80',
        imagePosition: 'object-[center_30%]',
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
        category: 'aeropuerto',
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
        category: 'urgencias',
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
        category: 'consulta',
        text: 'Un Exelente Médico muy capacitado ,brindado la mejor atención a nuestros pequeños , 🐶🐾🐈siempre con la mejor disposición , con el eh encontrado todos los medicamentos que necesito , y lo mejor que lo llevan a domicilio Lo recomiedo !!…',
    },
    {
        id: 25,
        author: 'Glenis Gonzalez',
        badge: null,
        stats: '8 opiniones · 3 fotos',
        date: 'Hace 2 años',
        rating: 5,
        category: 'urgencias',
        text: 'Hola ,lo recomiendo por su excelente servicio y asistencia a cualquier hora q necesite de su servicio esta disponible De mi parte muy agradecida',
    },
];

const categories = [
    { id: 'todos', label: 'Todas las opiniones', count: 25 },
    { id: 'urgencias', label: 'Urgencias y 24 Horas 🚨', count: 7 },
    { id: 'aeropuerto', label: 'Aeropuerto y Viajes ✈️', count: 6 },
    { id: 'consulta', label: 'Atención Médica 🐾', count: 12 },
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
        'streetAddress': 'Zac Nicte 19, Ángeles',
        'addressLocality': 'Cancún',
        'postalCode': '77533',
        'addressRegion': 'Quintana Roo',
        'addressCountry': 'MX'
    },
    'geo': {
        '@type': 'GeoCoordinates',
        'latitude': '21.1436315',
        'longitude': '-86.8483052'
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
                'text': 'Sí, en Small Animal Clinic ofrecemos atención médica veterinaria de urgencias 24/7 los 7 días de la semana en Cancún para perros y gatos.'
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

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    </Head>


    <div class="landing min-h-screen font-sans" :style="paletteStyle">
        <!-- Header -->
        <header class="absolute inset-x-0 top-0 z-30 animate-hero-1">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:px-6 sm:py-5 lg:flex-row lg:items-center lg:justify-between lg:gap-6 lg:px-10 lg:py-6">
                <a href="#" class="group flex w-full items-center gap-2.5 text-white lg:w-auto lg:shrink-0 lg:gap-3">
                    <img src="/images/logo_without_name.png" alt="Logo Small Animal Clinic Cancún - Veterinaria 24h" class="h-10 w-auto shrink-0 object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-110 sm:h-12 lg:h-14" />
                    <span class="whitespace-nowrap font-sans text-base font-extrabold tracking-tight leading-none transition-colors duration-300 group-hover:text-blue-200 sm:text-lg lg:text-xl">
                        Small Animal Clinic Cancún
                    </span>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-medium text-white/85 xl:gap-8 lg:flex">
                    <a href="#servicios" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Servicios</a>
                    <a href="#nosotros" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Nosotros</a>
                    <a href="#opiniones" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Opiniones</a>
                    <a href="#ubicacion" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Ubicación</a>
                    <a href="#redes" class="relative hidden py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full xl:inline">Redes Sociales</a>
                    <a href="#contacto" class="relative py-1 transition hover:text-white after:absolute after:bottom-0 after:left-0 after:h-[2px] after:w-0 after:bg-blue-400 after:transition-all after:duration-300 hover:after:w-full">Contacto</a>
                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="transition hover:text-white"
                    >
                        Panel
                    </Link>
                    <Link v-else-if="canLogin" :href="route('login')" class="transition hover:text-white">Acceso</Link>
                    <a href="https://wa.me/529981046082?text=Hola,%20deseo%20agendar%20una%20cita" target="_blank" rel="noopener noreferrer" class="landing-cta-btn landing-cta inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-bold shadow-md transition duration-300 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0">
                        <i class="fa-brands fa-whatsapp text-[13px]" aria-hidden="true"></i>
                        <span>Agendar cita</span>
                    </a>
                </nav>

                <a href="https://wa.me/529981046082?text=Hola,%20deseo%20agendar%20una%20cita" target="_blank" rel="noopener noreferrer" class="landing-cta-btn landing-cta inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold shadow-md ring-1 ring-black/5 transition duration-200 hover:brightness-105 active:scale-[0.98] sm:text-sm lg:hidden" aria-label="Agendar cita">
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    <span>Agendar cita</span>
                </a>
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

                <div class="relative mx-auto w-full max-w-7xl px-6 pb-20 pt-44 sm:pt-40 lg:px-10 lg:pt-36">
                    <div class="max-w-3xl">
                        <!-- Badges -->
                        <!-- <div class="mb-4 flex flex-wrap items-center gap-2 animate-hero-1">

                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-400/40 bg-amber-500/20 px-3.5 py-1.5 text-xs font-bold text-amber-200 backdrop-blur-md animate-pulse-subtle">
                                Atención 24/7 (7 Días)
                            </span>
                        </div> -->

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
                        <!-- <div class="mt-5 rounded-2xl bg-amber-500/20 border border-amber-400/35 p-3.5 text-xs text-amber-100 backdrop-blur-md flex items-start gap-3 max-w-2xl animate-hero-4 transition-all duration-300 hover:border-amber-400/60 hover:bg-amber-500/25">
                            <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-amber-400/20 text-amber-200 animate-bounce-slow">
                                <i class="fa-solid fa-thumbtack text-sm" aria-hidden="true"></i>
                            </span>
                            <p class="leading-relaxed">
                                <strong>Atención preferente CON CITA.</strong>
                                <span class="text-amber-200/90 ml-1">Las consultas <u>SIN CITA PREVIA</u> tienen un costo adicional por concepto de atención médica inmediata o de urgencia.</span>
                            </p>
                        </div> -->

                        <!-- Contact cards — mobile first -->
                        <div class="mt-6 animate-hero-5">
                            <p class="mb-4 flex items-center gap-2 text-[11px] font-semibold text-white/85">
                                <span class="relative flex size-2 shrink-0">
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                                </span>
                                Contacto directo
                            </p>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                                <!-- Teléfono -->
                                <a
                                    href="tel:+529981046082"
                                    title="Llamar a Urgencias"
                                    class="group flex items-center gap-3 rounded-2xl border border-sky-300/30 bg-white/95 p-3.5 shadow-lg shadow-sky-950/20 transition duration-300 hover:-translate-y-1 hover:border-sky-400 hover:shadow-xl active:scale-[0.99] sm:p-4"
                                >
                                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-600 transition duration-300 group-hover:bg-sky-600 group-hover:text-white sm:size-14">
                                        <i class="fa-solid fa-phone text-lg" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-bold text-slate-900 sm:text-base">Urgencias 24/7</h3>
                                        <p class="mt-0.5 break-all font-mono text-[12px] text-slate-600 sm:text-[13px]">+52 998 104 6082</p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-white transition group-hover:bg-sky-700">
                                        Llamar
                                        <i class="fa-solid fa-arrow-right text-[9px]" aria-hidden="true"></i>
                                    </span>
                                </a>

                                <!-- WhatsApp -->
                                <a
                                    href="https://wa.me/529981046082"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Abrir WhatsApp"
                                    class="wa-spotlight group flex items-center gap-3 rounded-2xl border-2 border-emerald-400/80 bg-white p-3.5 shadow-lg shadow-emerald-900/25 transition duration-300 hover:-translate-y-1 hover:border-emerald-400 hover:shadow-xl hover:shadow-emerald-500/30 active:scale-[0.99] sm:p-4"
                                >
                                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-emerald-500 text-white shadow-md shadow-emerald-900/20 transition duration-300 group-hover:scale-105 sm:size-14">
                                        <i class="fa-brands fa-whatsapp wa-icon-bounce text-xl" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <h3 class="text-sm font-bold text-slate-900 sm:text-base">WhatsApp</h3>
                                            <span class="wa-badge rounded-full bg-emerald-400 px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide text-emerald-950">Rápido</span>
                                        </div>
                                        <p class="mt-0.5 break-all font-mono text-[12px] text-slate-600 sm:text-[13px]">+52 998 104 6082</p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-white transition group-hover:bg-emerald-500">
                                        Chat
                                        <i class="fa-solid fa-arrow-right text-[9px]" aria-hidden="true"></i>
                                    </span>
                                </a>

                                <!-- Correo -->
                                <a
                                    href="mailto:smallanimalcliniccancun@gmail.com"
                                    title="Enviar correo"
                                    class="group flex items-center gap-3 rounded-2xl border border-rose-300/30 bg-white/95 p-3.5 shadow-lg shadow-rose-950/20 transition duration-300 hover:-translate-y-1 hover:border-rose-400 hover:shadow-xl active:scale-[0.99] sm:p-4"
                                >
                                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-600 transition duration-300 group-hover:bg-rose-600 group-hover:text-white sm:size-14">
                                        <i class="fa-solid fa-envelope text-lg" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-bold text-slate-900 sm:text-base">Correo</h3>
                                        <p class="mt-0.5 break-all text-[11px] leading-snug text-slate-600 sm:text-xs">smallanimalcliniccancun@gmail.com</p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-white transition group-hover:bg-rose-700">
                                        Enviar
                                        <i class="fa-solid fa-arrow-right text-[9px]" aria-hidden="true"></i>
                                    </span>
                                </a>

                                <!-- Maps -->
                                <a
                                    href="https://maps.app.goo.gl/VH5bcayUB5Vhity57"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Abrir Google Maps"
                                    class="group flex items-center gap-3 rounded-2xl border border-amber-300/30 bg-white/95 p-3.5 shadow-lg shadow-amber-950/20 transition duration-300 hover:-translate-y-1 hover:border-amber-400 hover:shadow-xl active:scale-[0.99] sm:p-4"
                                >
                                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700 transition duration-300 group-hover:bg-amber-500 group-hover:text-white sm:size-14">
                                        <i class="fa-solid fa-location-dot text-lg" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-bold text-slate-900 sm:text-base">Google Maps</h3>
                                        <p class="mt-0.5 text-[12px] text-slate-600 sm:text-[13px]">Cancún, Quintana Roo, México</p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-white transition group-hover:bg-amber-700">
                                        Mapa
                                        <i class="fa-solid fa-arrow-right text-[9px]" aria-hidden="true"></i>
                                    </span>
                                </a>
                            </div>
                        </div>

                        <!-- Social Media Strip directly in Hero Banner -->
                        <div class="mt-5 flex flex-wrap items-center gap-2.5 text-xs text-white/90 animate-hero-5">
                            <span class="font-bold text-white/70 uppercase text-[10px] tracking-wider">Síguenos:</span>
                            <a href="https://www.facebook.com/profile.php?id=61562957610885" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-blue-600/40 border border-blue-400/40 px-3.5 py-1.5 text-xs font-bold hover:bg-blue-600 hover:scale-105 transition-all duration-300 shadow-xs">
                                <i class="fa-brands fa-facebook-f text-blue-200" aria-hidden="true"></i>
                                <span>Facebook</span>
                            </a>
                            <a href="https://www.tiktok.com/@dr.andresaguilarvet" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full bg-slate-900/80 border border-white/20 px-3.5 py-1.5 text-xs font-bold hover:bg-black hover:scale-105 transition-all duration-300 shadow-xs">
                                <i class="fa-brands fa-tiktok" aria-hidden="true"></i>
                                <span>TikTok (@dr.andresaguilarvet)</span>
                            </a>
                        </div>

                        <!-- CTA Buttons -->
                        <div class="mt-7 flex flex-wrap items-center gap-3 sm:gap-4 animate-hero-5">
                            <a href="https://wa.me/529981046082?text=Hola,%20deseo%20agendar%20una%20cita" target="_blank" rel="noopener noreferrer" class="landing-cta-btn landing-cta inline-flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-bold shadow-xl transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl active:translate-y-0 sm:w-auto sm:px-7 sm:py-4">
                                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                                <span>Agenda una consulta</span>
                            </a>
                            <a href="#servicios" class="group inline-flex w-full items-center justify-center gap-3 px-3 py-3.5 text-sm font-bold text-white transition-colors duration-300 hover:text-blue-300 sm:w-auto sm:justify-start sm:py-4">
                                Conoce nuestros servicios
                                <span class="grid size-8 place-items-center rounded-full border border-white/30 transition-all duration-300 group-hover:translate-x-1.5 group-hover:border-white group-hover:bg-white/10">
                                    <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Rating Float Card -->
                <div class="landing-rating absolute bottom-0 right-0 hidden rounded-tl-[36px] px-10 py-7 lg:block animate-hero-5 animate-float shadow-xl">
                    <div class="flex items-center gap-4">
                        <div class="landing-stars text-xl tracking-wider animate-pulse-glow-text inline-flex gap-1">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                        </div>
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
                        class="mt-8 grid gap-5 sm:gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <article
                            v-for="(service, sIdx) in filteredServices"
                            :key="service.id"
                            :class="[
                                'group flex h-full flex-col overflow-hidden rounded-xl bg-white shadow-lg shadow-slate-900/10 transition duration-500 ease-[cubic-bezier(0.215,0.61,0.355,1)] hover:-translate-y-1.5 hover:shadow-2xl reveal-fade-up',
                                `delay-${(sIdx % 4 + 1) * 100}`
                            ]"
                        >
                            <!-- Imagen -->
                            <div class="relative aspect-[16/11] shrink-0 overflow-hidden bg-slate-200">
                                <img
                                    :src="service.image"
                                    :alt="service.title"
                                    :class="[
                                        'size-full object-cover transition duration-500 ease-[cubic-bezier(0.215,0.61,0.355,1)] group-hover:scale-105',
                                        service.imagePosition || 'object-center'
                                    ]"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    @error="($event.target.src = 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&h=650&q=80')"
                                />
                                <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/25 via-transparent to-transparent"></div>
                                <span class="absolute right-3 top-3 rounded-full bg-black/45 px-2.5 py-1 font-mono text-[11px] font-bold text-white/90 backdrop-blur-sm">
                                    {{ service.number }}
                                </span>
                            </div>

                            <!-- Contenido -->
                            <div class="relative z-10 flex flex-1 flex-col px-4 py-4 sm:px-5 sm:py-5">
                                <span
                                    class="mb-2 inline-flex w-fit rounded-full border px-2.5 py-0.5 text-[10px] font-bold tracking-wide"
                                    :class="service.badgeClass"
                                >
                                    {{ service.badge }}
                                </span>

                                <h3 class="font-sans text-base font-bold leading-snug tracking-tight text-slate-900 sm:text-lg">
                                    {{ service.title }}
                                </h3>

                                <ul class="mt-3 space-y-1.5 border-t border-slate-100 pt-3">
                                    <li
                                        v-for="highlight in service.highlights"
                                        :key="highlight"
                                        class="flex items-start gap-2 text-[11px] font-medium text-slate-700 sm:text-xs"
                                    >
                                        <i class="fa-solid fa-check mt-0.5 text-[10px] text-blue-500" aria-hidden="true"></i>
                                        <span>{{ highlight }}</span>
                                    </li>
                                </ul>

                                <a
                                    :href="service.ctaLink"
                                    :target="service.ctaLink.startsWith('tel:') ? undefined : '_blank'"
                                    :rel="service.ctaLink.startsWith('tel:') ? undefined : 'noopener noreferrer'"
                                    class="mt-auto inline-flex w-fit items-center gap-2 rounded-md bg-slate-900 px-3.5 py-2 text-xs font-bold text-white transition duration-300 group-hover:bg-blue-600 pt-4"
                                >
                                    {{ service.ctaText }}
                                    <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                                </a>
                            </div>
                        </article>
                    </TransitionGroup>
                </div>
            </section>

            <!-- Sección Nosotros / Equipo médico -->
            <section id="nosotros" class="landing-surface px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-12 text-center reveal-fade-up sm:mb-16 sm:text-left">
                        <span class="text-xs font-bold uppercase tracking-[0.25em] text-blue-600">Equipo Médico</span>
                        <h2 class="landing-ink mt-3 font-sans text-3xl font-extrabold tracking-tight leading-tight sm:text-4xl lg:text-5xl">
                            Quienes cuidan a tu familia
                        </h2>
                        <p class="landing-muted mt-3 max-w-2xl text-sm leading-relaxed sm:text-base">
                            Personas que aman a los animales tanto como tú. Aquí conoces a quienes te acompañan en cada etapa.
                        </p>
                    </div>

                    <div class="space-y-8 sm:space-y-10">
                        <!-- Dr. Andrés Aguilar -->
                        <article class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm transition-shadow duration-500 hover:shadow-xl reveal-fade-up">
                            <div class="grid gap-0 lg:grid-cols-12 lg:items-center">
                                <div class="lg:col-span-5">
                                    <div class="group relative overflow-hidden bg-slate-100">
                                        <img
                                            :src="drAndresPhoto"
                                            alt="Dr. Andrés Aguilar - Médico Veterinario Zootecnista"
                                            class="aspect-[4/5] w-full object-cover object-[center_12%] transition-transform duration-700 group-hover:scale-105 sm:aspect-[3/4] lg:aspect-[4/5] lg:max-h-[520px]"
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-col justify-center p-6 sm:p-8 lg:col-span-7 lg:p-10">
                                    <p class="flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">
                                        <span class="h-px w-8 bg-blue-400/70" aria-hidden="true"></span>
                                        Contigo en cada etapa
                                    </p>
                                    <h3 class="landing-ink mt-4 font-sans text-2xl font-extrabold tracking-tight sm:text-3xl">
                                        Dr. Andrés Aguilar
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        Médico Veterinario Zootecnista · 8 años acompañando familias
                                    </p>
                                    <p class="landing-muted mt-4 text-sm leading-relaxed sm:text-base">
                                        Construir Small Animal Clinic Cancún significó elevar el estándar de atención para pequeñas especies en nuestra ciudad. Mi labor diaria es supervisar de cerca el criterio clínico con el que evaluamos a cada paciente, combinando actualización científica y tecnología diagnóstica para darte respuestas claras y soluciones médicas efectivas cuando más las necesitas.
                                    </p>
                                    <ul class="mt-5 grid gap-2 sm:grid-cols-2">
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-paw text-[10px] text-blue-500" aria-hidden="true"></i>
                                            Dermatología y Oncología
                                        </li>
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-paw text-[10px] text-blue-500" aria-hidden="true"></i>
                                            Medicina General
                                        </li>
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-paw text-[10px] text-blue-500" aria-hidden="true"></i>
                                            Urgencias 24/7
                                        </li>
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-paw text-[10px] text-blue-500" aria-hidden="true"></i>
                                            Certificados SENASICA / Aeropuerto
                                        </li>
                                    </ul>
                                    <div class="mt-7 flex flex-wrap gap-3">
                                        <a
                                            href="https://smallanimalclinic.mx/certificaciones-dr-andres"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-6 py-3 text-xs font-bold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-lg"
                                        >
                                            <i class="fa-solid fa-certificate" aria-hidden="true"></i>
                                            Ver certificados
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                        </a>

                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- Dra. Michelle Green Vargas -->
                        <article class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm transition-shadow duration-500 hover:shadow-xl reveal-fade-up delay-100">
                            <div class="grid gap-0 lg:grid-cols-12 lg:items-center">
                                <div class="order-1 lg:order-2 lg:col-span-5">
                                    <div class="group relative overflow-hidden bg-slate-100">
                                        <img
                                            :src="draMichellePhoto"
                                            alt="Dra. Michelle Green Vargas - Médica Veterinaria Zootecnista"
                                            class="aspect-[4/5] w-full object-cover object-[center_18%] transition-transform duration-700 group-hover:scale-105 sm:aspect-[3/4] lg:aspect-[4/5] lg:max-h-[520px]"
                                        />
                                    </div>
                                </div>
                                <div class="order-2 flex flex-col justify-center p-6 sm:p-8 lg:order-1 lg:col-span-7 lg:p-10">
                                    <p class="flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.22em] text-rose-600">
                                        <span class="h-px w-8 bg-rose-400/70" aria-hidden="true"></span>
                                        Corazones en buenas manos
                                    </p>
                                    <h3 class="landing-ink mt-4 font-sans text-2xl font-extrabold tracking-tight sm:text-3xl">
                                        Dra. Michelle Green Vargas
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-500">
                                        Médica Veterinaria Zootecnista · 6 años de servicio
                                    </p>
                                    <p class="landing-muted mt-4 text-sm leading-relaxed sm:text-base">
                                        Cuidar el corazón de tu mascota es proteger los momentos y la energía que llenan tu hogar. Como especialista en cardiología veterinaria en Small Animal Clinic Cancún, realizo evaluaciones cardiovasculares minuciosas, ecocardiogramas y seguimiento puntual para detectar a tiempo arritmias, soplos y cardiopatías. La precisión médica y el monitoreo oportuno son la clave para que su corazón siga latiendo fuerte y disfruten muchos más años juntos.
                                    </p>
                                    <ul class="mt-5 grid gap-2 sm:grid-cols-2">
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-heart text-[10px] text-rose-500" aria-hidden="true"></i>
                                            Evaluación cardiovascular
                                        </li>
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-heart text-[10px] text-rose-500" aria-hidden="true"></i>
                                            Detección de soplos y arritmias
                                        </li>
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-heart text-[10px] text-rose-500" aria-hidden="true"></i>
                                            Ecocardiogramas Doppler color
                                        </li>
                                        <li class="flex items-center gap-2 text-xs font-medium text-slate-700">
                                            <i class="fa-solid fa-heart text-[10px] text-rose-500" aria-hidden="true"></i>
                                            Seguimiento de pacientes cardíacos
                                        </li>
                                    </ul>
                                    <div class="mt-7 flex flex-wrap gap-3">
                                        <a
                                            href="https://smallanimalclinic.mx/certificaciones-dra-michelle"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 rounded-full bg-rose-600 px-6 py-3 text-xs font-bold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-rose-700 hover:shadow-lg"
                                        >
                                            <i class="fa-solid fa-certificate" aria-hidden="true"></i>
                                            Ver certificados
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                        </a>

                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Ubicación & Google Maps Section -->
            <section id="ubicacion" class="landing-surface px-6 py-24 lg:px-10 lg:py-32">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col items-center text-center reveal-fade-up">
                        <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/20 bg-blue-500/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-blue-600">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <span>Ubicación</span>
                        </div>

                        <h2 class="landing-ink mt-4 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">
                            Encuéntranos en <span class="landing-soft-title italic">Cancún</span>
                        </h2>

                        <p class="landing-muted mt-4 max-w-2xl text-base leading-relaxed">
                            Visítanos en nuestra clínica veterinaria o solicita atención en el Aeropuerto Internacional de Cancún.
                        </p>
                    </div>

                    <div class="mt-12 grid gap-8 lg:grid-cols-12 lg:items-center">
                        <!-- Information Side Cards -->
                        <div class="space-y-5 lg:col-span-5 reveal-fade-right">
                            <div class="landing-card rounded-3xl border bg-white p-7 shadow-xs transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:border-blue-300">
                                <div class="flex items-start gap-4">
                                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600 text-xl transition-transform duration-300 hover:scale-110">
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-lg">Dirección Principal</h3>
                                        <p class="mt-1 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                            Zac Nicte 19, Ángeles, 77533 Cancún, Quintana Roo, México.<br />
                                            <span class="text-[11px] text-slate-400 font-medium">Ubicación verificada en Google Maps</span>
                                        </p>
                                        <a
                                            href="https://maps.app.goo.gl/VH5bcayUB5Vhity57"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-4 inline-flex items-center gap-2 rounded-full bg-blue-600 px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-blue-700 hover:-translate-y-0.5 hover:shadow-lg"
                                        >
                                            <span>Abrir en Google Maps</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="landing-card rounded-3xl border bg-white p-7 shadow-xs transition-all duration-500 hover:-translate-y-1 hover:shadow-xl hover:border-amber-300 delay-100">
                                <div class="flex items-start gap-4">
                                    <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-amber-50 text-amber-600 text-xl transition-transform duration-300 hover:scale-110">
                                        <i class="fa-solid fa-plane" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-lg">Atención en Aeropuerto</h3>
                                        <p class="mt-1 text-xs sm:text-sm text-slate-600 leading-relaxed">
                                            Asistencia veterinaria de urgencia y entrega de Certificados Zoosanitarios en las terminales del Aeropuerto Internacional de Cancún.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Interactive Map Embed Container -->
                        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl lg:col-span-7 reveal-fade-left delay-200 transition-all duration-500 hover:shadow-2xl">
                            <div class="relative w-full h-[460px]">
                                <iframe
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3721.2827!2d-86.850553!3d21.1436315!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8f4c2d8466468dad%3A0x605898a3b0c09ac4!2sSmall%20animal%20clinic%20cancun!5e0!3m2!1ses!2smx!4v1726400000000!5m2!1ses!2smx"
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
                                            <p class="text-[11px] text-slate-300">4.7 <i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i><i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i><i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i><i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i><i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i> (25 opiniones en Google)</p>
                                        </div>
                                    </div>
                                    <a
                                        href="https://maps.app.goo.gl/VH5bcayUB5Vhity57"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="rounded-full bg-blue-500 px-4 py-2 text-xs font-bold text-white transition-all duration-300 hover:bg-blue-400 hover:scale-105 shrink-0"
                                    >
                                        Cómo llegar <i class="fa-solid fa-arrow-up-right-from-square text-[10px] ml-1" aria-hidden="true"></i>
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
                        <p class="landing-highlight font-sans text-5xl font-extrabold tracking-tight transition-transform duration-300 group-hover:scale-110 inline-block">8 Años</p>
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
                            <span class="text-amber-400 animate-pulse-glow-text inline-flex gap-0.5" aria-label="5 estrellas">
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                            </span>
                        </div>

                        <h2 class="landing-ink mt-4 font-sans text-4xl font-extrabold tracking-tight leading-tight sm:text-5xl">
                            Lo que nuestras familias <span class="landing-soft-title italic">opinan de nosotros</span>
                        </h2>

                        <p class="landing-muted mt-4 max-w-2xl text-base leading-relaxed">
                            Reseñas reales y verificadas de nuestros clientes en Google Maps. Médicos veterinarios de confianza para trámites de vuelo, urgencias y atención en clínica.
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
                                        <span class="text-amber-400 text-sm inline-flex gap-0.5" aria-label="5 estrellas">
                                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 flex items-center gap-1">
                                        25 opiniones verificadas en Google Maps
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-blue-600 group-hover:translate-x-0.5 transition-transform" aria-hidden="true"></i>
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
                            <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                            <span>Comunidad & Redes Sociales</span>
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
                                        <i class="fa-brands fa-facebook-f text-xl" aria-hidden="true"></i>
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
                                <i class="fa-solid fa-arrow-up-right-from-square transition-transform duration-300 group-hover:translate-x-1.5" aria-hidden="true"></i>
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
                                        <i class="fa-brands fa-tiktok text-xl" aria-hidden="true"></i>
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
                                <i class="fa-solid fa-arrow-up-right-from-square transition-transform duration-300 group-hover:translate-x-1.5" aria-hidden="true"></i>
                            </div>
                        </a>

                        <!-- Email Card -->
                        <a
                            href="mailto:smallanimalcliniccancun@gmail.com"
                            class="landing-card group flex flex-col justify-between rounded-3xl border bg-white p-7 transition-all duration-500 hover:-translate-y-3 hover:border-red-400 hover:shadow-2xl hover:shadow-red-500/15 reveal-fade-up delay-300"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="grid size-13 place-items-center rounded-2xl bg-red-500 text-white shadow-md transition-transform duration-300 group-hover:scale-110">
                                        <i class="fa-solid fa-envelope text-xl" aria-hidden="true"></i>
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
                                <i class="fa-solid fa-arrow-right transition-transform duration-300 group-hover:translate-x-1.5" aria-hidden="true"></i>
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
                                        <i class="fa-brands fa-whatsapp text-2xl" aria-hidden="true"></i>
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
                                <i class="fa-solid fa-arrow-up-right-from-square transition-transform duration-300 group-hover:translate-x-1.5" aria-hidden="true"></i>
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
                        <p class="landing-highlight text-xs font-bold uppercase tracking-[0.2em]">Visítanos</p>
                        <p class="mt-4 text-xs leading-6 text-white/80">Cancún, Quintana Roo, México</p>
                        <a href="https://maps.app.goo.gl/VH5bcayUB5Vhity57" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-blue-300 hover:text-white transition-colors duration-300">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            Google Maps
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
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
                                <i class="fa-brands fa-facebook-f text-blue-400" aria-hidden="true"></i>
                                <span>Facebook Oficial</span>
                            </a>
                            <a href="https://www.tiktok.com/@dr.andresaguilarvet" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors duration-300 flex items-center gap-2">
                                <i class="fa-brands fa-tiktok" aria-hidden="true"></i>
                                <span>TikTok @dr.andresaguilarvet</span>
                            </a>
                            <a href="https://wa.me/529981046082" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors duration-300 flex items-center gap-2">
                                <i class="fa-brands fa-whatsapp text-emerald-400" aria-hidden="true"></i>
                                <span>WhatsApp Directo</span>
                            </a>
                            <a href="mailto:smallanimalcliniccancun@gmail.com" class="hover:text-white transition-colors duration-300 flex items-center gap-2 truncate">
                                <i class="fa-solid fa-envelope text-red-300" aria-hidden="true"></i>
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

.landing-cta-btn {
    letter-spacing: 0.01em;
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

@keyframes waIconBounce {
    0%, 100% { transform: translateY(0) scale(1); }
    15% { transform: translateY(-2px) scale(1.08); }
    30% { transform: translateY(0) scale(1); }
    45% { transform: translateY(-1.5px) scale(1.05); }
    60% { transform: translateY(0) scale(1); }
}

@keyframes waCardGlow {
    0%, 100% { box-shadow: 0 10px 28px rgba(16, 185, 129, 0.22); }
    50% { box-shadow: 0 14px 40px rgba(16, 185, 129, 0.45); }
}

@keyframes waBadgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.wa-spotlight {
    animation: waCardGlow 2.4s ease-in-out infinite;
}

.wa-icon-bounce {
    animation: waIconBounce 2.2s ease-in-out infinite;
}

.wa-badge {
    animation: waBadgePulse 1.8s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .wa-spotlight,
    .wa-icon-bounce,
    .wa-badge {
        animation: none !important;
        transition: none !important;
        transform: none !important;
    }
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
