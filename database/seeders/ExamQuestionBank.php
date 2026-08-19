<?php

namespace Database\Seeders;

class ExamQuestionBank
{
    public static function forSlug(string $slug): array
    {
        return self::all()[$slug] ?? [];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'constitucional' => [
                self::question(
                    'const-1',
                    'La Constitución Política de los Estados Unidos Mexicanos es la norma suprema. Eso implica que:',
                    [
                        'a' => 'Las leyes federales pueden contradecirla si son posteriores.',
                        'b' => 'Todas las normas del orden jurídico deben ajustarse a ella.',
                        'c' => 'Solo obliga al Poder Judicial.',
                        'd' => 'Los tratados internacionales siempre están por encima de ella.',
                    ],
                    'b',
                    'El principio de supremacía constitucional exige que el resto del ordenamiento se ajuste a la Constitución.',
                    'Principios'
                ),
                self::question(
                    'const-2',
                    'Tras la reforma de 2011 en materia de derechos humanos, las normas relativas a esos derechos se interpretan:',
                    [
                        'a' => 'Solo conforme al texto original de 1917.',
                        'b' => 'Favoreciendo en todo tiempo a las personas la protección más amplia.',
                        'c' => 'Siempre en sentido restrictivo para el gobernado.',
                        'd' => 'Únicamente si hay jurisprudencia obligatoria.',
                    ],
                    'b',
                    'El artículo 1.º constitucional consagra el principio pro persona: se elige la interpretación más protectora.',
                    'Derechos'
                ),
                self::question(
                    'const-3',
                    'El control de convencionalidad obliga a los jueces a:',
                    [
                        'a' => 'Inaplicar tratados que no gusten al Ejecutivo.',
                        'b' => 'Verificar que las normas internas sean compatibles con tratados de derechos humanos.',
                        'c' => 'Sustituir la Constitución por la Convención Americana en todos los casos.',
                        'd' => 'Consultar siempre a la Corte Interamericana antes de sentenciar.',
                    ],
                    'b',
                    'Los jueces deben confrontar el derecho interno con los tratados de derechos humanos y la interpretación de sus órganos.',
                    'Control constitucional'
                ),
                self::question(
                    'const-4',
                    'La división de poderes en México se organiza, en lo federal, en:',
                    [
                        'a' => 'Ejecutivo, Legislativo y Judicial.',
                        'b' => 'Presidente, gobernadores y presidentes municipales.',
                        'c' => 'Senado, Cámara de Diputados y Consejo de la Judicatura.',
                        'd' => 'Federación, entidades y municipios únicamente.',
                    ],
                    'a',
                    'El artículo 49 constitucional deposita el Supremo Poder de la Federación en Legislativo, Ejecutivo y Judicial.',
                    'Principios'
                ),
                self::question(
                    'const-5',
                    'Los derechos humanos reconocidos en la Constitución y en los tratados de los que México es parte:',
                    [
                        'a' => 'Solo rigen si hay ley reglamentaria.',
                        'b' => 'Pueden suspenderse sin límite en cualquier momento.',
                        'c' => 'Son normas de aplicación directa, con las reservas de la propia Constitución.',
                        'd' => 'Dependen de que el afectado los invoque expresamente.',
                    ],
                    'c',
                    'El artículo 1.º reconoce derechos de fuente constitucional y convencional, de aplicación directa.',
                    'Derechos'
                ),
                self::question(
                    'const-6',
                    'El juicio de amparo es, en esencia, un medio de control constitucional para:',
                    [
                        'a' => 'Resolver controversias entre poderes federales únicamente.',
                        'b' => 'Proteger a las personas frente a normas o actos de autoridad que violen derechos.',
                        'c' => 'Sustituir a la acción penal pública.',
                        'd' => 'Impugnar solo leyes locales de carácter fiscal.',
                    ],
                    'b',
                    'El amparo tutela derechos de las personas contra normas generales, actos u omisiones de autoridad.',
                    'Control constitucional'
                ),
            ],
            'civil' => [
                self::question(
                    'civ-1',
                    'La personalidad jurídica de las personas físicas se adquiere:',
                    [
                        'a' => 'Al cumplir 18 años.',
                        'b' => 'Con el registro del nacimiento.',
                        'c' => 'Por el nacimiento.',
                        'd' => 'Hasta que se emite el acta.',
                    ],
                    'c',
                    'La personalidad se adquiere por el nacimiento; el registro es prueba, no constitutivo de la personalidad.',
                    'Personas'
                ),
                self::question(
                    'civ-2',
                    'Un contrato es válido, en términos generales, cuando existen:',
                    [
                        'a' => 'Consentimiento, objeto y, en su caso, las formalidades que la ley exige.',
                        'b' => 'Solo la firma de una de las partes.',
                        'c' => 'La inscripción en el Registro Público, siempre.',
                        'd' => 'Un testigo y un sello notarial, en todos los casos.',
                    ],
                    'a',
                    'Los elementos de existencia son consentimiento y objeto; la forma puede ser de validez según el acto.',
                    'Contratos'
                ),
                self::question(
                    'civ-3',
                    'La compraventa se perfecciona, por regla general, cuando:',
                    [
                        'a' => 'Se paga el precio por completo.',
                        'b' => 'Se entrega materialmente la cosa.',
                        'c' => 'Las partes convienen en la cosa y el precio.',
                        'd' => 'Se otorga escritura pública, aunque la ley no lo pida.',
                    ],
                    'c',
                    'Es un contrato consensual: se perfecciona por el acuerdo sobre cosa y precio, salvo formalidad especial.',
                    'Contratos'
                ),
                self::question(
                    'civ-4',
                    'Son bienes inmuebles, entre otros:',
                    [
                        'a' => 'El dinero en efectivo.',
                        'b' => 'El suelo y las construcciones adheridas a él.',
                        'c' => 'Los créditos personales.',
                        'd' => 'Las acciones bursátiles al portador.',
                    ],
                    'b',
                    'El predio y lo incorporado de modo permanente se consideran inmuebles.',
                    'Bienes'
                ),
                self::question(
                    'civ-5',
                    'La prescripción adquisitiva permite adquirir el dominio por:',
                    [
                        'a' => 'El simple transcurso del tiempo, sin posesión.',
                        'b' => 'Posesión en concepto de dueño, pacífica, continua y pública, durante el plazo legal.',
                        'c' => 'Una donación verbal.',
                        'd' => 'El pago de contribuciones prediales durante un año.',
                    ],
                    'b',
                    'Se exige posesión calificada y el plazo que fije el código civil aplicable.',
                    'Bienes'
                ),
                self::question(
                    'civ-6',
                    'La capacidad de ejercicio, por regla general, se adquiere:',
                    [
                        'a' => 'Al nacer.',
                        'b' => 'A los 16 años.',
                        'c' => 'Con la mayoría de edad, salvo emancipación u otras excepciones legales.',
                        'd' => 'Solo con título profesional.',
                    ],
                    'c',
                    'La capacidad de ejercicio se vincula a la mayoría de edad, con las excepciones que la ley prevé.',
                    'Personas'
                ),
            ],
            'penal' => [
                self::question(
                    'pen-1',
                    'El delito, en su concepción clásica, se analiza a partir de:',
                    [
                        'a' => 'Solo la pena prevista.',
                        'b' => 'Acción u omisión típica, antijurídica y culpable.',
                        'c' => 'La peligrosidad del sujeto, sin tipo penal.',
                        'd' => 'El daño civil causado a la víctima.',
                    ],
                    'b',
                    'La teoría del delito exige tipicidad, antijuridicidad y culpabilidad sobre una conducta.',
                    'Teoría del delito'
                ),
                self::question(
                    'pen-2',
                    'El principio de legalidad penal (nullum crimen, nulla poena sine lege) implica que:',
                    [
                        'a' => 'El juez puede crear delitos por analogía si hay laguna.',
                        'b' => 'No hay delito ni pena sin ley previa, escrita y estricta.',
                        'c' => 'Basta un reglamento administrativo para tipificar.',
                        'd' => 'La costumbre puede fundar tipos penales.',
                    ],
                    'b',
                    'Nadie puede ser penado sino por una ley anterior que describa el hecho y la pena.',
                    'Teoría del delito'
                ),
                self::question(
                    'pen-3',
                    'El dolo consiste, en esencia, en:',
                    [
                        'a' => 'La imprudencia grave.',
                        'b' => 'El desconocimiento invencible de la ley.',
                        'c' => 'El conocimiento y la voluntad de realizar el hecho típico.',
                        'd' => 'Cualquier resultado lesivo, aunque no se haya querido.',
                    ],
                    'c',
                    'Hay dolo cuando se conoce el hecho y se quiere su realización (o se acepta, en el dolo eventual).',
                    'Teoría del delito'
                ),
                self::question(
                    'pen-4',
                    'En el proceso penal acusatorio, la carga de probar el delito y la responsabilidad recae, por regla, en:',
                    [
                        'a' => 'El imputado.',
                        'b' => 'El Ministerio Público.',
                        'c' => 'El juez de control.',
                        'd' => 'La policía ministerial sin intervención del MP.',
                    ],
                    'b',
                    'Corresponde al Ministerio Público acreditar los hechos y la responsabilidad penal.',
                    'Proceso penal'
                ),
                self::question(
                    'pen-5',
                    'La presunción de inocencia significa que:',
                    [
                        'a' => 'El imputado debe demostrar que no delinquió.',
                        'b' => 'Toda persona se presume inocente mientras no se declare su responsabilidad en sentencia firme.',
                        'c' => 'Solo aplica en delitos culposos.',
                        'd' => 'Queda sin efecto al dictarse la vinculación a proceso.',
                    ],
                    'b',
                    'Es un derecho constitucional: la culpabilidad se declara en sentencia firme, no antes.',
                    'Proceso penal'
                ),
                self::question(
                    'pen-6',
                    'Un tipo penal describe:',
                    [
                        'a' => 'Solo la pena, sin conducta.',
                        'b' => 'La conducta prohibida (o mandada) y sus elementos, a los que se anuda una consecuencia jurídica.',
                        'c' => 'Únicamente el bien jurídico, sin verbo rector.',
                        'd' => 'Las políticas públicas de prevención.',
                    ],
                    'b',
                    'El tipo es la descripción legal de la conducta y de los elementos que la configuran.',
                    'Tipos penales'
                ),
            ],
            'administrativo' => [
                self::question(
                    'adm-1',
                    'El acto administrativo, en su noción clásica, es:',
                    [
                        'a' => 'Cualquier contrato entre particulares.',
                        'b' => 'Una declaración unilateral de la autoridad que crea, reconoce, modifica o extingue una situación jurídica.',
                        'c' => 'Una sentencia judicial.',
                        'd' => 'Una iniciativa de ley.',
                    ],
                    'b',
                    'Es una declaración de voluntad de la administración que produce efectos jurídicos frente al gobernado.',
                    'Acto administrativo'
                ),
                self::question(
                    'adm-2',
                    'Un elemento de validez frecuente del acto administrativo es:',
                    [
                        'a' => 'Que lo firme un particular.',
                        'b' => 'Que la autoridad sea competente y el acto esté fundado y motivado.',
                        'c' => 'Que se publique en redes sociales.',
                        'd' => 'Que el afectado esté de acuerdo, siempre.',
                    ],
                    'b',
                    'Competencia, fundamentación y motivación son exigencias básicas de validez.',
                    'Acto administrativo'
                ),
                self::question(
                    'adm-3',
                    'El procedimiento administrativo sirve, entre otras cosas, para:',
                    [
                        'a' => 'Sustituir al proceso penal.',
                        'b' => 'Garantizar audiencia, legalidad y una decisión ordenada de la autoridad.',
                        'c' => 'Evitar que existan recursos.',
                        'd' => 'Permitir que la autoridad actúe sin fundar.',
                    ],
                    'b',
                    'Ordena cómo debe actuar la administración y tutelar derechos de audiencia y defensa.',
                    'Procedimiento'
                ),
                self::question(
                    'adm-4',
                    'La responsabilidad patrimonial del Estado procede, en términos generales, cuando:',
                    [
                        'a' => 'Hay una actividad administrativa irregular que causa daño.',
                        'b' => 'Un particular incumple un contrato civil.',
                        'c' => 'Se dicta una ley nueva, siempre.',
                        'd' => 'El juez se equivoca en una tesis aislada.',
                    ],
                    'a',
                    'El Estado responde por daños derivados de su actividad administrativa irregular, conforme a la ley de la materia.',
                    'Responsabilidad'
                ),
                self::question(
                    'adm-5',
                    'La fundamentación de un acto administrativo consiste en:',
                    [
                        'a' => 'Explicar los hechos, sin citar normas.',
                        'b' => 'Citar los preceptos legales aplicables al caso.',
                        'c' => 'Indicar solo el nombre del servidor público.',
                        'd' => 'Remitir al interesado a “la ley de la materia”, sin precisar artículos.',
                    ],
                    'b',
                    'Fundar es señalar los preceptos; motivar es relacionar los hechos con esas normas.',
                    'Acto administrativo'
                ),
                self::question(
                    'adm-6',
                    'Agotar el procedimiento o recurso ordinario antes del amparo suele relacionarse con:',
                    [
                        'a' => 'El principio de definitividad.',
                        'b' => 'El principio de inmediación penal.',
                        'c' => 'La litisconsorcio necesaria civil.',
                        'd' => 'La suplencia de la queja en materia laboral, siempre.',
                    ],
                    'a',
                    'La definitividad exige agotar medios ordinarios de defensa, con las excepciones legales.',
                    'Procedimiento'
                ),
            ],
            'laboral' => [
                self::question(
                    'lab-1',
                    'La relación de trabajo se caracteriza, en lo esencial, por:',
                    [
                        'a' => 'Un contrato civil de prestación de servicios sin subordinación.',
                        'b' => 'Prestación personal de trabajo subordinado, mediante el pago de un salario.',
                        'c' => 'Una sociedad mercantil entre patrón y trabajador.',
                        'd' => 'El voluntariado sin retribución.',
                    ],
                    'b',
                    'Hay relación laboral cuando hay trabajo personal subordinado y salario.',
                    'Relación laboral'
                ),
                self::question(
                    'lab-2',
                    'El salario mínimo, por regla constitucional, debe ser suficiente para:',
                    [
                        'a' => 'Cubrir las necesidades normales de un jefe de familia, en el orden material, social y cultural.',
                        'b' => 'Igualar el salario de los servidores públicos de confianza.',
                        'c' => 'Pagar solo el transporte al centro de trabajo.',
                        'd' => 'Fijarse libremente por cada patrón, sin piso legal.',
                    ],
                    'a',
                    'El artículo 123 constitucional fija el sentido protector del salario mínimo.',
                    'Derechos'
                ),
                self::question(
                    'lab-3',
                    'La jornada máxima diurna, en la Constitución, es de:',
                    [
                        'a' => '12 horas.',
                        'b' => '10 horas.',
                        'c' => '8 horas.',
                        'd' => '6 horas.',
                    ],
                    'c',
                    'La jornada diurna máxima es de 8 horas, sin perjuicio de las reglas sobre extraordinarias.',
                    'Derechos'
                ),
                self::question(
                    'lab-4',
                    'El despido injustificado da lugar, en términos generales, a que el trabajador pueda reclamar:',
                    [
                        'a' => 'Solo una disculpa pública.',
                        'b' => 'Reinstalación o indemnización, según las reglas de la materia.',
                        'c' => 'La nulidad de la empresa.',
                        'd' => 'Una pensión vitalicia automática.',
                    ],
                    'b',
                    'El régimen del 123 y la LFT contemplan reinstalación o indemnización, con matices según el caso.',
                    'Conflictos'
                ),
                self::question(
                    'lab-5',
                    'La subordinación laboral se manifiesta, sobre todo, en:',
                    [
                        'a' => 'Que el trabajador ponga sus propias herramientas sin instrucciones.',
                        'b' => 'La dirección y dependencia respecto del patrón en la prestación del servicio.',
                        'c' => 'Un convenio de sociedad.',
                        'd' => 'La compraventa de mercaderías.',
                    ],
                    'b',
                    'La subordinación es el poder jurídico de mando del patrón y el deber de obediencia del trabajador.',
                    'Relación laboral'
                ),
                self::question(
                    'lab-6',
                    'Los conflictos laborales individuales, en el modelo vigente, se canalizan principalmente ante:',
                    [
                        'a' => 'Los tribunales laborales del Poder Judicial y los mecanismos de conciliación preceptiva.',
                        'b' => 'Solo el juicio de amparo directo, como primera instancia.',
                        'c' => 'El juez penal de control.',
                        'd' => 'El Registro Público de la Propiedad.',
                    ],
                    'a',
                    'La reforma laboral desplazó las Juntas hacia tribunales y conciliación previa, con las reglas transitorias aplicables.',
                    'Conflictos'
                ),
            ],
            'amparo' => [
                self::question(
                    'amp-1',
                    'El principio de definitividad en amparo implica, como regla, que:',
                    [
                        'a' => 'Basta un agravio directo para ir al amparo, siempre.',
                        'b' => 'Deben agotarse los recursos ordinarios previstos en la ley, salvo excepciones.',
                        'c' => 'Solo aplica en materia penal.',
                        'd' => 'Nunca hay que agotar instancia previa.',
                    ],
                    'b',
                    'Antes de acudir al amparo debe agotarse el medio ordinario de defensa, con las excepciones de la ley.',
                    'Principios'
                ),
                self::question(
                    'amp-2',
                    'El amparo es improcedente, entre otros supuestos, cuando:',
                    [
                        'a' => 'Existen actos consentidos o se actualiza una causal expresa de la ley.',
                        'b' => 'El quejoso es persona física.',
                        'c' => 'El acto proviene de una autoridad.',
                        'd' => 'Se reclama una norma autoaplicativa.',
                    ],
                    'a',
                    'La Ley de Amparo prevé causales de improcedencia, como actos consentidos, cesación de efectos, etc.',
                    'Procedencia'
                ),
                self::question(
                    'amp-3',
                    'En el amparo indirecto, por regla, se reclaman:',
                    [
                        'a' => 'Solo sentencias definitivas de tribunales judiciales.',
                        'b' => 'Normas, actos u omisiones que no son sentencias definitivas en los términos del amparo directo.',
                        'c' => 'Únicamente controversias constitucionales.',
                        'd' => 'Conflictos entre particulares sin autoridad responsable.',
                    ],
                    'b',
                    'El indirecto es la vía para normas y actos que no corresponden al amparo directo contra sentencias definitivas.',
                    'Procedencia'
                ),
                self::question(
                    'amp-4',
                    'La sentencia de amparo que concede la protección federal tiene como efecto típico:',
                    [
                        'a' => 'Crear una ley nueva.',
                        'b' => 'Restituir al quejoso en el goce del derecho violado, según la naturaleza del acto.',
                        'c' => 'Imponer pena corporal a la autoridad.',
                        'd' => 'Anular todos los contratos del quejoso.',
                    ],
                    'b',
                    'La concesión busca restituir las cosas al estado que guardaban antes de la violación, en lo posible.',
                    'Sentencias'
                ),
                self::question(
                    'amp-5',
                    'El interés jurídico en amparo, en su formulación clásica, exige:',
                    [
                        'a' => 'Un derecho subjetivo afectado de manera personal y directa.',
                        'b' => 'Cualquier inconformidad ciudadana, aunque no haya agravio.',
                        'c' => 'Solo la calidad de vecino del municipio.',
                        'd' => 'Ser servidor público de la autoridad responsable.',
                    ],
                    'a',
                    'Tradicionalmente se pide un derecho subjetivo lesionado; el interés legítimo amplía supuestos, pero no elimina el agravio.',
                    'Principios'
                ),
                self::question(
                    'amp-6',
                    'La suspensión del acto reclamado busca:',
                    [
                        'a' => 'Sustituir el fondo del amparo.',
                        'b' => 'Mantener las cosas en el estado en que se encuentran y evitar que el acto se consume, cuando procede.',
                        'c' => 'Condenar al pago de daños desde el auto inicial.',
                        'd' => 'Dejar sin efectos la Constitución.',
                    ],
                    'b',
                    'Es una medida cautelar para que el amparo no quede sin materia por la ejecución del acto.',
                    'Sentencias'
                ),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private static function question(
        string $id,
        string $prompt,
        array $options,
        string $correct,
        string $explanation,
        string $subject
    ): array {
        $optionTexts = array_values($options);
        $letters = array_keys($options);
        $correctIndex = array_search($correct, $letters, true);

        return [
            'tipo' => 'opcion_unica',
            'pregunta' => $prompt,
            'materia' => $subject,
            'opciones' => $optionTexts,
            'correctas' => [$correctIndex === false ? 0 : $correctIndex],
            'respuesta_correcta' => $optionTexts[$correctIndex === false ? 0 : $correctIndex] ?? '',
            'respuesta_modelo' => $explanation,
            'criterios' => [],
        ];
    }
}
