#!/usr/bin/env python3
import glob
import json
import os
import re
import subprocess
import sys

def parse_env(file_path):
    env_vars = {}
    if not os.path.exists(file_path):
        return env_vars
    with open(file_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if "=" in line:
                key, val = line.split("=", 1)
                key = key.strip()
                val = val.strip().strip("\"'")
                env_vars[key] = val
    return env_vars

def main():
    base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
    env_file = os.path.join(base_dir, ".env")
    data_dir = os.path.join(base_dir, "data")

    env_vars = parse_env(env_file)
    db_name = env_vars.get("DB_DATABASE", "gestion_desk")
    db_user = env_vars.get("DB_USERNAME", "root")
    db_pass = env_vars.get("DB_PASSWORD", "8J1Ç124,.312")

    print(f"==================================================")
    print(f"🚀 Iniciando migración de archivos JSON a MongoDB")
    print(f"📦 Base de datos: {db_name}")
    print(f"👤 Usuario: {db_user}")
    print(f"📁 Directorio de datos: {data_dir}")
    print(f"==================================================\n")

    json_files = sorted(glob.glob(os.path.join(data_dir, "*.json")))
    if not json_files:
        print(f"❌ No se encontraron archivos JSON en {data_dir}")
        sys.exit(1)

    results = []

    for file_path in json_files:
        filename = os.path.basename(file_path)
        collection = os.path.splitext(filename)[0]

        try:
            with open(file_path, "r", encoding="utf-8") as fp:
                data = json.load(fp)
            file_count = len(data) if isinstance(data, list) else 1
        except Exception as e:
            print(f"❌ Error al leer {filename}: {e}")
            results.append({
                "collection": collection,
                "file_count": 0,
                "db_count": 0,
                "status": f"Error lectura: {e}"
            })
            continue

        if file_count == 0:
            # Crear colección vacía si no existe
            mongosh_cmd = [
                "docker", "compose", "exec", "-T", "mongo",
                "mongosh", "-u", db_user, "-p", db_pass,
                "--authenticationDatabase", "admin", db_name,
                "--eval", f"if (!db.getCollectionNames().includes('{collection}')) db.createCollection('{collection}');"
            ]
            res = subprocess.run(mongosh_cmd, cwd=base_dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if res.returncode == 0:
                print(f"⚪ [{collection}] Archivo vacío: colección creada/verificada.")
                results.append({
                    "collection": collection,
                    "file_count": 0,
                    "db_count": 0,
                    "status": "OK (Vacía)"
                })
            else:
                print(f"❌ Error al crear colección {collection}: {res.stderr}")
                results.append({
                    "collection": collection,
                    "file_count": 0,
                    "db_count": 0,
                    "status": "Error al crear"
                })
            continue

        # Importar con mongoimport
        mongoimport_cmd = [
            "docker", "compose", "exec", "-T", "mongo",
            "mongoimport", "-u", db_user, "-p", db_pass,
            "--authenticationDatabase", "admin",
            "-d", db_name,
            "-c", collection,
            "--jsonArray",
            "--mode=upsert",
            "--upsertFields=_id"
        ]

        with open(file_path, "rb") as stdin_file:
            res = subprocess.run(
                mongoimport_cmd,
                cwd=base_dir,
                stdin=stdin_file,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True
            )

        if res.returncode != 0:
            print(f"❌ Error al importar {collection}: {res.stderr.strip() or res.stdout.strip()}")
            results.append({
                "collection": collection,
                "file_count": file_count,
                "db_count": 0,
                "status": f"Fallo importación"
            })
        else:
            print(f"✅ [{collection}] Importados/Actualizados {file_count} documentos.")
            results.append({
                "collection": collection,
                "file_count": file_count,
                "db_count": 0,  # Se actualizará en la verificación
                "status": "Importado"
            })

    print("\n🔍 Verificando conteos en MongoDB...")
    # Verificar conteo final en base de datos
    check_eval = """
    const counts = {};
    db.getCollectionNames().forEach(c => {
        counts[c] = db.getCollection(c).countDocuments();
    });
    print(JSON.stringify(counts));
    """
    mongosh_verify = [
        "docker", "compose", "exec", "-T", "mongo",
        "mongosh", "-u", db_user, "-p", db_pass,
        "--authenticationDatabase", "admin", db_name,
        "--quiet", "--eval", check_eval
    ]
    verify_res = subprocess.run(mongosh_verify, cwd=base_dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)

    db_counts = {}
    try:
        # En caso de que haya texto antes o después del JSON
        match = re.search(r"\{.*\}", verify_res.stdout, re.DOTALL)
        if match:
            db_counts = json.loads(match.group(0))
    except Exception as e:
        print(f"⚠️ No se pudo parsear el resultado de verificación: {e}")

    print("\n" + "="*70)
    print(f"{'Colección':<30} | {'JSON':<10} | {'MongoDB':<10} | {'Estado':<12}")
    print("="*70)

    all_matched = True
    total_json = 0
    total_db = 0

    for item in results:
        col = item["collection"]
        f_count = item["file_count"]
        d_count = db_counts.get(col, 0)
        item["db_count"] = d_count

        total_json += f_count
        total_db += d_count

        if f_count == d_count:
            status_str = "✅ Correcto"
        else:
            status_str = "⚠️ Discrepancia"
            all_matched = False

        print(f"{col:<30} | {f_count:<10} | {d_count:<10} | {status_str:<12}")

    print("="*70)
    print(f"{'TOTALES':<30} | {total_json:<10} | {total_db:<10} | {'✅ OK' if all_matched else '⚠️ Revisar'}")
    print("="*70)

    if all_matched:
        print("\n🎉 ¡Todos los archivos fueron migrados a la base de datos con éxito!")
    else:
        print("\n⚠️ La migración finalizó con algunas discrepancias.")

if __name__ == "__main__":
    main()

