import { GoogleGenAI, Type } from "@google/genai";
import fs from "fs";
import csv from "csv-parser";
import { createObjectCsvWriter } from "csv-writer";
import dotenv from "dotenv";

dotenv.config();

const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });

// 1. Leer preguntas desde examen_derecho.csv
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

// 2. Procesar lote con Gemini 1.5 Flash
async function procesarLoteConGemini(lote) {
    const prompt = `
      Actúa como un profesor de derecho y diseñador pedagógico.
      A partir de cada pregunta y su respuesta original proporcionadas en el JSON, genera:
      1. "respuesta_modelo": Una explicación clara, concisa y rigurosa (1 o 2 oraciones).
      2. "criterios": Un arreglo de 2 a 4 ideas o conceptos clave obligatorios que el alumno debe mencionar para considerar su respuesta correcta.

      Datos de entrada:
      ${JSON.stringify(lote)}
    `;

    const response = await ai.models.generateContent({
      model: "gemini-3.6-flash", // <-- Actualizado aquí
      contents: prompt,
      config: {
        responseMimeType: "application/json",
        responseSchema: {
          type: Type.ARRAY,
          items: {
            type: Type.OBJECT,
            properties: {
              id_temporal: { type: Type.STRING },
              respuesta_modelo: { type: Type.STRING },
              criterios: {
                type: Type.ARRAY,
                items: { type: Type.STRING },
              },
            },
            required: ["id_temporal", "respuesta_modelo", "criterios"],
          },
        },
      },
    });

    return JSON.parse(response.text);
  }

// 3. Ejecución principal
async function main() {
  const preguntas = await leerPreguntasCSV("examen_derecho.csv");
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
      const loteProcesado = await procesarLoteConGemini(payloadLote);

      loteProcesado.forEach((item) => {
        const original = lote.find((l) => l.id_temporal === item.id_temporal);
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
    } catch (err) {
      console.error(`Error en lote ${numLote}:`, err.message);
    }

    // Pausa de 1.5s entre lotes para no saturar la cuota
    await new Promise((res) => setTimeout(res, 1500));
  }

  // 4. Guardar archivo final
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
  console.log(`\n¡Listo! Se guardaron ${resultados.length} preguntas en preguntas_procesadas.csv`);
}

main();
