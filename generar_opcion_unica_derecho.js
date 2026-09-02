import OpenAI from "openai";
import fs from "fs";
import csv from "csv-parser";
import { createObjectCsvWriter } from "csv-writer";
import dotenv from "dotenv";

dotenv.config();

const INPUT_CSV = "derecho_multiple.csv";
const OUTPUT_CSV = "derecho_multiple_opcion_unica.csv";
const BATCH_SIZE = 10;
const CHECKPOINT_EVERY = 5;
const MAX_OPCION_CHARS = 220;

const client = new OpenAI({
  baseURL: "https://api.deepseek.com",
  apiKey: process.env.DEEPSEEK_API_KEY,
});

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

async function leerPreguntasCSV(rutaArchivo) {
  return new Promise((resolve, reject) => {
    const filas = [];
    fs.createReadStream(rutaArchivo)
      .pipe(csv())
      .on("data", (data) => filas.push(data))
      .on("end", () => resolve(filas))
      .on("error", (error) => reject(error));
  });
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
    return "distractores incompletos";
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
    distractores.push(`Opción incorrecta relacionada con ${materia || "el tema"}`);
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
    materia: materia || "Derecho",
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

async function guardarCsv(ruta, registros) {
  const writer = createObjectCsvWriter({
    path: ruta,
    header: CSV_HEADER,
  });
  await writer.writeRecords(registros);
}

async function procesarLoteConReintentos(payloadLote, maxReintentos = 4) {
  const prompt = `
Actúa como un profesor de derecho mexicano y diseñador de exámenes de opción única.

Para CADA elemento del JSON de entrada, genera UNA pregunta de selección de una sola opción (4 opciones en total: 1 correcta + 3 incorrectas).

Reglas obligatorias:
1. Corrige ortografía, acentuación, puntuación y dicción del enunciado. Redáctalo como pregunta clara de examen.
2. La opción correcta DEBE basarse en el campo "respuesta" (no inventes otra respuesta). Si es muy larga, RESÚMELA a máximo ${MAX_OPCION_CHARS} caracteres sin perder el núcleo jurídico.
3. Genera exactamente 3 distractores: incorrectos pero plausibles, del mismo tema, longitud similar a la correcta, sin duplicar la correcta ni entre sí.
4. Si la respuesta original es Verdadero/Falso: preferible reformular a pregunta de contenido con 4 opciones sustantivas. Si mantienes el juicio V/F, la correcta es "Verdadero" o "Falso" y los 3 distractores son afirmaciones alternativas incorrectas (no solo la opuesta).
5. Incluye "respuesta_modelo": 1 oración breve que explique por qué es correcta.
6. Conserva el mismo "id_temporal".

Esquema de salida (arreglo JSON):
[
  {
    "id_temporal": "string",
    "pregunta": "Enunciado corregido y claro",
    "opcion_correcta": "Texto de la respuesta correcta (resumida si aplica)",
    "distractores": ["Incorrecta 1", "Incorrecta 2", "Incorrecta 3"],
    "respuesta_modelo": "Explicación breve en una oración."
  }
]

Devuelve ÚNICAMENTE JSON válido (objeto con clave "preguntas" o el arreglo directo). Sin markdown ni texto adicional.

Datos de entrada:
${JSON.stringify(payloadLote)}
`;

  for (let intento = 1; intento <= maxReintentos; intento++) {
    try {
      const response = await client.chat.completions.create({
        model: "deepseek-chat",
        messages: [
          {
            role: "system",
            content:
              "Eres un asistente experto en derecho mexicano que responde estrictamente en formato JSON válido.",
          },
          { role: "user", content: prompt },
        ],
        response_format: { type: "json_object" },
        temperature: 0.35,
      });

      const contenido = response.choices[0].message.content.trim();
      const parsed = JSON.parse(contenido);
      const lista = normalizarLista(parsed);

      if (!Array.isArray(lista) || lista.length === 0) {
        throw new Error("Respuesta JSON sin arreglo de preguntas");
      }

      return lista;
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
  if (!process.env.DEEPSEEK_API_KEY) {
    console.error("❌ Falta DEEPSEEK_API_KEY en el entorno (.env).");
    process.exit(1);
  }

  const preguntas = await leerPreguntasCSV(INPUT_CSV);
  console.log(`Total de preguntas cargadas: ${preguntas.length}`);

  const preparadas = preguntas
    .map((p, index) => {
      const pregunta = (p.Pregunta || p.pregunta || "").trim();
      const respuesta = (p.Respuesta || p.respuesta || "").trim();
      const cuestionario = (p.Cuestionario || p.cuestionario || p.materia || "Derecho").trim();

      if (!pregunta) return null;

      return {
        id_temporal: String(index),
        cuestionario,
        pregunta,
        respuesta,
      };
    })
    .filter(Boolean);

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
    console.log(`Procesando lote ${numLote} de ${totalLotes} (${lote.length} preguntas)...`);

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
    } catch (err) {
      lotesFallidos += 1;
      console.error(`❌ Error en lote ${numLote}:`, err.message);
    }

    if (numLote % CHECKPOINT_EVERY === 0 || numLote === totalLotes) {
      await guardarCsv(OUTPUT_CSV, resultados);
      console.log(
        `💾 Checkpoint: ${resultados.length} preguntas guardadas en ${OUTPUT_CSV}`
      );
    }

    await new Promise((res) => setTimeout(res, 1000));
  }

  await guardarCsv(OUTPUT_CSV, resultados);

  console.log("\n======= Resumen =======");
  console.log(`Entrada:            ${preparadas.length}`);
  console.log(`Generadas:          ${resultados.length}`);
  console.log(`Lotes OK:           ${lotesOk}/${totalLotes}`);
  console.log(`Lotes fallidos:     ${lotesFallidos}`);
  console.log(`Ítems inválidos:    ${itemsInvalidos}`);
  console.log(`Archivo de salida:  ${OUTPUT_CSV}`);
  console.log("=======================\n");
}

main().catch((err) => {
  console.error("❌ Error fatal:", err);
  process.exit(1);
});
