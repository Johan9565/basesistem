import fs from "node:fs";

// Cargar variables de entorno desde .env si no están en process.env
function cargarEnv() {
  if (fs.existsSync(".env")) {
    const lines = fs.readFileSync(".env", "utf8").split(/\r?\n/);
    for (const line of lines) {
      const match = line.match(/^\s*([\w.-]+)\s*=\s*(.*)?\s*$/);
      if (match) {
        let val = (match[2] || "").trim();
        if (
          (val.startsWith('"') && val.endsWith('"')) ||
          (val.startsWith("'") && val.endsWith("'"))
        ) {
          val = val.slice(1, -1);
        }
        if (!process.env[match[1]]) {
          process.env[match[1]] = val;
        }
      }
    }
  }
}

cargarEnv();

const INPUT_CSV = process.argv[2] || "anatomia.csv";
const OUTPUT_CSV = process.argv[3] || "anatomia_opcion_unica.csv";
const BATCH_SIZE = 10;
const CHECKPOINT_EVERY = 3;
const MAX_OPCION_CHARS = 240;

const DEEPSEEK_API_KEY = process.env.DEEPSEEK_API_KEY;

const CSV_HEADER = [
  { id: "tipo", title: "tipo" },
  { id: "pregunta", title: "pregunta" },
  { id: "materia", title: "materia" },
  { id: "opcion_a", title: "opcion_a" },
  { id: "opcion_b", title: "opcion_b" },
  { id: "opcion_c", title: "opcion_c" },
  { id: "opcion_d", title: "opcion_d" },
  { id: "opcion_e", title: "opcion_e" },
  { id: "opcion_f", title: "opcion_f" },
  { id: "correctas", title: "correctas" },
  { id: "respuesta_correcta", title: "respuesta_correcta" },
  { id: "respuesta_modelo", title: "respuesta_modelo" },
  { id: "criterios", title: "criterios" },
];

// Parser CSV estándar RFC 4180 sin dependencias externas
function leerCSV(rutaArchivo) {
  const text = fs.readFileSync(rutaArchivo, "utf8");
  const rows = [];
  let row = [];
  let field = "";
  let inQuotes = false;

  for (let i = 0; i < text.length; i++) {
    const char = text[i];
    const nextChar = text[i + 1];

    if (char === '"') {
      if (inQuotes && nextChar === '"') {
        field += '"';
        i++;
      } else {
        inQuotes = !inQuotes;
      }
    } else if (char === "," && !inQuotes) {
      row.push(field);
      field = "";
    } else if ((char === "\r" || char === "\n") && !inQuotes) {
      if (char === "\r" && nextChar === "\n") {
        i++;
      }
      row.push(field);
      if (row.length > 1 || (row.length === 1 && row[0].trim() !== "")) {
        rows.push(row);
      }
      row = [];
      field = "";
    } else {
      field += char;
    }
  }

  if (field !== "" || row.length > 0) {
    row.push(field);
    if (row.length > 1 || (row.length === 1 && row[0].trim() !== "")) {
      rows.push(row);
    }
  }

  if (rows.length === 0) return [];

  const headers = rows[0].map((h) => h.trim().replace(/^\uFEFF/, ""));
  return rows.slice(1).map((r) => {
    const obj = {};
    headers.forEach((h, idx) => {
      obj[h] = r[idx] !== undefined ? r[idx].trim() : "";
    });
    return obj;
  });
}

// Escritor CSV seguro compatible con RFC 4180
function guardarCSV(rutaArchivo, headers, records) {
  const escapeCell = (val) => {
    const str = String(val ?? "");
    if (
      str.includes(",") ||
      str.includes('"') ||
      str.includes("\n") ||
      str.includes("\r")
    ) {
      return `"${str.replace(/"/g, '""')}"`;
    }
    return str;
  };

  const headerLine = headers.map((h) => escapeCell(h.id)).join(",");
  const dataLines = records.map((rec) => {
    return headers.map((h) => escapeCell(rec[h.id] ?? "")).join(",");
  });

  const contenido = [headerLine, ...dataLines].join("\n");
  fs.writeFileSync(rutaArchivo, contenido, "utf8");
}

function barajar(array) {
  const copia = [...array];
  for (let i = copia.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [copia[i], copia[j]] = [copia[j], copia[i]];
  }
  return copia;
}

function normalizarLista(parsed) {
  if (Array.isArray(parsed)) return parsed;
  if (Array.isArray(parsed.preguntas)) return parsed.preguntas;
  if (Array.isArray(parsed.data)) return parsed.data;
  if (Array.isArray(parsed.items)) return parsed.items;
  if (Array.isArray(parsed.results)) return parsed.results;
  const values = Object.values(parsed);
  const firstArray = values.find((v) => Array.isArray(v));
  return firstArray || [];
}

function truncarSeguro(texto, max = MAX_OPCION_CHARS) {
  const limpio = String(texto || "").replace(/\s+/g, " ").trim();
  if (limpio.length <= max) return limpio;
  return limpio.slice(0, max - 1).trimEnd() + "…";
}

function validarItem(item) {
  if (!item || item.id_temporal === undefined || item.id_temporal === null) {
    return "falta id_temporal";
  }
  if (!item.pregunta || !String(item.pregunta).trim()) {
    return "falta pregunta";
  }
  if (!item.opcion_correcta || !String(item.opcion_correcta).trim()) {
    return "falta opcion_correcta";
  }
  if (!Array.isArray(item.distractores) || item.distractores.length < 3) {
    return "distractores incompletos (debe incluir 3 distractores)";
  }
  return null;
}

function mapearAFilaImport(item, materia) {
  const correcta = truncarSeguro(item.opcion_correcta);
  const distractores = item.distractores
    .slice(0, 3)
    .map((d) => truncarSeguro(d))
    .filter((d) => d && d.toLowerCase() !== correcta.toLowerCase());

  while (distractores.length < 3) {
    distractores.push(`Estructura o alternativa anatómica incorrecta (${materia || "Anatomía"})`);
  }

  const opciones = barajar([
    { texto: correcta, esCorrecta: true },
    { texto: distractores[0], esCorrecta: false },
    { texto: distractores[1], esCorrecta: false },
    { texto: distractores[2], esCorrecta: false },
  ]);

  const indiceCorrecta = opciones.findIndex((o) => o.esCorrecta);

  return {
    tipo: "opcion_unica",
    pregunta: String(item.pregunta).trim(),
    materia: materia || "Anatomía",
    opcion_a: opciones[0].texto,
    opcion_b: opciones[1].texto,
    opcion_c: opciones[2].texto,
    opcion_d: opciones[3].texto,
    opcion_e: "",
    opcion_f: "",
    correctas: String(indiceCorrecta),
    respuesta_correcta: correcta,
    respuesta_modelo: truncarSeguro(item.respuesta_modelo || "", 400),
    criterios: "",
  };
}

async function llamarDeepSeek(payloadLote) {
  const prompt = `
Actúa como un profesor universitario y médico especialista en Anatomía Humana, experto en diseño pedagógico de reactivos y exámenes de opción múltiple médica.

Para CADA elemento del JSON de entrada, genera UNA pregunta de selección de opción única (4 opciones en total: 1 correcta + 3 distractores incorrectos).

Reglas obligatorias:
1. Redacción de la pregunta: Revisa y perfecciona la redacción, acentuación y terminología anatómica internacional formal. Debe ser un reactivo claro, directo e inequívoco de examen médico.
2. Opción correcta: DEBE basarse estrictamente en el campo "respuesta". Si es extensa, RESÚMELA a máximo ${MAX_OPCION_CHARS} caracteres sin perder la exactitud anatómica.
3. Distractores (3 opciones incorrectas): Deben ser estructuras, relaciones anatómicas o términos médicos reales y plausibles del mismo tema/región, pero anatómicamente falsos para responder a la pregunta. Longitud y sintaxis similar a la correcta. Prohibido "Todas las anteriores" o "Ninguna de las anteriores".
4. Respuesta modelo: Incluye 1 o 2 oraciones breves explicando el fundamento anatómico de por qué la opción correcta es la acertada.
5. Conserva el mismo "id_temporal".

Esquema de salida esperado (JSON estricto):
{
  "preguntas": [
    {
      "id_temporal": "string",
      "pregunta": "¿Enunciado anatómico corregido y claro?",
      "opcion_correcta": "Texto preciso de la respuesta correcta",
      "distractores": [
        "Distractor anatómico 1",
        "Distractor anatómico 2",
        "Distractor anatómico 3"
      ],
      "respuesta_modelo": "Explicación breve del fundamento anatómico."
    }
  ]
}

Devuelve ÚNICAMENTE el objeto JSON válido. Sin bloques markdown (\`\`\`json) ni texto explicativo adicional.

Datos de entrada:
${JSON.stringify(payloadLote)}
`;

  const respuestaHttp = await fetch("https://api.deepseek.com/chat/completions", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${DEEPSEEK_API_KEY}`,
    },
    body: JSON.stringify({
      model: "deepseek-chat",
      messages: [
        {
          role: "system",
          content:
            "Eres un docente especialista en anatomía humana que responde estrictamente en formato JSON válido.",
        },
        { role: "user", content: prompt },
      ],
      response_format: { type: "json_object" },
      temperature: 0.3,
    }),
  });

  if (!respuestaHttp.ok) {
    const errorTexto = await respuestaHttp.text();
    throw new Error(`Error HTTP ${respuestaHttp.status}: ${errorTexto}`);
  }

  const data = await respuestaHttp.json();
  let contenido = data.choices[0].message.content.trim();
  contenido = contenido
    .replace(/^```json\s*/i, "")
    .replace(/^```\s*/i, "")
    .replace(/\s*```$/i, "")
    .trim();

  const parsed = JSON.parse(contenido);
  const lista = normalizarLista(parsed);

  if (!Array.isArray(lista) || lista.length === 0) {
    throw new Error("Respuesta JSON sin arreglo de preguntas reconocible");
  }

  return lista;
}

async function procesarLoteConReintentos(payloadLote, maxReintentos = 4) {
  for (let intento = 1; intento <= maxReintentos; intento++) {
    try {
      return await llamarDeepSeek(payloadLote);
    } catch (err) {
      console.warn(`⚠️ Intento ${intento}/${maxReintentos} falló: ${err.message}`);
      if (intento === maxReintentos) throw err;

      const espera = intento * 3000;
      console.log(`Reintentando en ${espera / 1000} segundos...`);
      await new Promise((res) => setTimeout(res, espera));
    }
  }
}

async function main() {
  if (!DEEPSEEK_API_KEY) {
    console.error("❌ Falta DEEPSEEK_API_KEY en el entorno o archivo .env.");
    process.exit(1);
  }

  if (!fs.existsSync(INPUT_CSV)) {
    console.error(`❌ El archivo de entrada "${INPUT_CSV}" no existe.`);
    process.exit(1);
  }

  console.log(`📖 Leyendo archivo: ${INPUT_CSV}...`);
  const preguntas = leerCSV(INPUT_CSV);
  console.log(`Total de filas leídas: ${preguntas.length}`);

  const preparadas = preguntas
    .map((p, index) => {
      const pregunta = (p.Pregunta || p.pregunta || p.PREGUNTA || "").trim();
      const respuesta = (p.Respuesta || p.respuesta || p.RESPUESTA || "").trim();
      const tema = (
        p.Tema ||
        p.tema ||
        p.TEMA ||
        p.Cuestionario ||
        p.cuestionario ||
        p.materia ||
        "Anatomía"
      ).trim();

      if (!pregunta) {
        return null;
      }

      if (!respuesta) {
        console.warn(
          `⚠️ Fila ${index + 2} omitida: la pregunta "${pregunta.slice(0, 45)}..." no tiene respuesta.`
        );
        return null;
      }

      return {
        id_temporal: String(index),
        cuestionario: tema,
        pregunta,
        respuesta,
      };
    })
    .filter(Boolean);

  console.log(`Preguntas válidas para procesar: ${preparadas.length}`);

  const resultados = [];
  let lotesOk = 0;
  let lotesFallidos = 0;
  let itemsInvalidos = 0;
  const totalLotes = Math.ceil(preparadas.length / BATCH_SIZE);

  for (let i = 0; i < preparadas.length; i += BATCH_SIZE) {
    const lote = preparadas.slice(i, i + BATCH_SIZE);
    const payloadLote = lote.map(({ id_temporal, cuestionario, pregunta, respuesta }) => ({
      id_temporal,
      cuestionario,
      pregunta,
      respuesta,
    }));

    const numLote = Math.floor(i / BATCH_SIZE) + 1;
    console.log(`\n⏳ Procesando lote ${numLote} de ${totalLotes} (${lote.length} preguntas)...`);

    try {
      const loteProcesado = await procesarLoteConReintentos(payloadLote);

      for (const item of loteProcesado) {
        const errorValidacion = validarItem(item);
        if (errorValidacion) {
          itemsInvalidos += 1;
          console.warn(
            `⚠️ Ítem inválido (id=${item?.id_temporal ?? "?"}): ${errorValidacion}`
          );
          continue;
        }

        const original = lote.find(
          (l) => String(l.id_temporal) === String(item.id_temporal)
        );
        if (!original) {
          itemsInvalidos += 1;
          console.warn(`⚠️ id_temporal desconocido: ${item.id_temporal}`);
          continue;
        }

        resultados.push(mapearAFilaImport(item, original.cuestionario));
      }

      lotesOk += 1;
      console.log(`✅ Lote ${numLote} procesado con éxito.`);
    } catch (err) {
      lotesFallidos += 1;
      console.error(`❌ Error en lote ${numLote}:`, err.message);
    }

    if (numLote % CHECKPOINT_EVERY === 0 || numLote === totalLotes) {
      guardarCSV(OUTPUT_CSV, CSV_HEADER, resultados);
      console.log(
        `💾 Checkpoint: ${resultados.length} preguntas guardadas en ${OUTPUT_CSV}`
      );
    }

    if (i + BATCH_SIZE < preparadas.length) {
      await new Promise((res) => setTimeout(res, 1200));
    }
  }

  guardarCSV(OUTPUT_CSV, CSV_HEADER, resultados);

  console.log("\n================ Resumen ================");
  console.log(`Entrada analizada:   ${preparadas.length}`);
  console.log(`Preguntas generadas: ${resultados.length}`);
  console.log(`Lotes exitosos:      ${lotesOk}/${totalLotes}`);
  console.log(`Lotes fallidos:      ${lotesFallidos}`);
  console.log(`Ítems inválidos:     ${itemsInvalidos}`);
  console.log(`Archivo generado:    ${OUTPUT_CSV}`);
  console.log("=========================================\n");
}

main().catch((err) => {
  console.error("❌ Error fatal:", err);
  process.exit(1);
});
