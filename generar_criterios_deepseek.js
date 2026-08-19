import OpenAI from "openai";
import fs from "fs";
import csv from "csv-parser";
import { createObjectCsvWriter } from "csv-writer";
import dotenv from "dotenv";

dotenv.config();

// Inicializar cliente con la URL base de DeepSeek
const client = new OpenAI({
  baseURL: "https://api.deepseek.com",
  apiKey: process.env.DEEPSEEK_API_KEY,
});

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

async function procesarLoteConReintentos(payloadLote, maxReintentos = 4) {
  const prompt = `
Actúa como un profesor de derecho mexicano y diseñador pedagógico.
A partir de cada pregunta y su respuesta original proporcionadas en el JSON de entrada, genera un arreglo JSON con exactamente este esquema para cada elemento:
[
  {
    "id_temporal": "string",
    "respuesta_modelo": "Explicación clara, concisa y rigurosa en 1 o 2 oraciones.",
    "criterios": ["Criterio 1", "Criterio 2", "Criterio 3"]
  }
]

Devuelve ÚNICAMENTE el arreglo JSON válido, sin bloques markdown ni texto adicional.

Datos de entrada:
${JSON.stringify(payloadLote)}
`;

  for (let intento = 1; intento <= maxReintentos; intento++) {
    try {
      const response = await client.chat.completions.create({
        model: "deepseek-chat", // DeepSeek-V3
        messages: [
          { role: "system", content: "Eres un asistente experto que responde estrictamente en formato JSON válido." },
          { role: "user", content: prompt },
        ],
        response_format: { type: "json_object" }, // Forzar JSON
        temperature: 0.2,
      });

      const contenido = response.choices[0].message.content.trim();

      // Parsear respuesta (manejando si devuelve array directo o envuelto en un objeto)
      const parsed = JSON.parse(contenido);
      return Array.isArray(parsed) ? parsed : (parsed.preguntas || parsed.data || Object.values(parsed)[0]);
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
  const preguntas = await leerPreguntasCSV("examen_derecho_deepseek.csv");
  console.log(`Total de preguntas cargadas: ${preguntas.length}`);

  const preparadas = preguntas.map((p, index) => ({
    id_temporal: String(index),
    pregunta: p.pregunta,
    base_original: p.respuesta_correcta || "",
    filaOriginal: p,
  }));

  const BATCH_SIZE = 10;
  const resultados = [];

  for (let i = 0; i < preparadas.length; i += BATCH_SIZE) {
    const lote = preparadas.slice(i, i + BATCH_SIZE);
    const payloadLote = lote.map(({ id_temporal, pregunta, base_original }) => ({
      id_temporal,
      pregunta,
      base_original,
    }));

    const numLote = Math.floor(i / BATCH_SIZE) + 1;
    const totalLotes = Math.ceil(preparadas.length / BATCH_SIZE);
    console.log(`Procesando lote ${numLote} de ${totalLotes}...`);

    try {
      const loteProcesado = await procesarLoteConReintentos(payloadLote);

      if (Array.isArray(loteProcesado)) {
        loteProcesado.forEach((item) => {
          const original = lote.find((l) => String(l.id_temporal) === String(item.id_temporal));
          if (original) {
            resultados.push({
              tipo: original.filaOriginal.tipo || "abierta",
              materia: original.filaOriginal.materia || "Derecho",
              pregunta: original.filaOriginal.pregunta,
              respuesta_modelo: item.respuesta_modelo,
              criterios: JSON.stringify(item.criterios),
            });
          }
        });
      }
    } catch (err) {
      console.error(`❌ Error en lote ${numLote}:`, err.message);
    }

    await new Promise((res) => setTimeout(res, 1000));
  }

  const csvWriter = createObjectCsvWriter({
    path: "preguntas_procesadas.csv",
    header: [
      { id: "tipo", title: "tipo" },
      { id: "materia", title: "materia" },
      { id: "pregunta", title: "pregunta" },
      { id: "respuesta_modelo", title: "respuesta_modelo" },
      { id: "criterios", title: "criterios" },
    ],
  });

  await csvWriter.writeRecords(resultados);
  console.log(`\n✅ Proceso terminado. Se guardaron ${resultados.length} preguntas en preguntas_procesadas.csv`);
}

main();
