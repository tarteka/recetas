---
name: recetario
description: Guarda recetas de cocina enviadas por URL en el recetario personal.
---

# Recetario

Usa este skill cuando el usuario quiera guardar una receta de cocina,
especialmente cuando envíe una URL de una página que contiene una receta.

## Flujo

1. Obtén el contenido de la URL usando Web Fetch.
2. Si Web Fetch no permite obtener suficientemente bien la receta, usa el navegador.
3. Identifica únicamente la receta publicada en la página.
4. Extrae y normaliza los datos.
5. Envía la receta a la API del recetario.
6. Informa al usuario únicamente cuando la API confirme que la receta ha sido guardada.

## Formato

La API espera un JSON con esta estructura:

{
  "titulo": "Nombre de la receta",
  "descripcion": "Descripción breve o null",
  "fuente_url": "URL original",
  "fuente_nombre": "Nombre de la web o null",
  "imagen_url": "URL de la imagen principal o null",
  "raciones": 4,
  "tiempo_preparacion_min": 15,
  "tiempo_coccion_min": 30,
  "tiempo_total_min": 45,
  "ingredientes": [
    {
      "nombre": "pechuga de pollo",
      "cantidad": 500,
      "unidad": "g",
      "notas": "cortada en dados",
      "texto_original": "500 g de pechuga de pollo cortada en dados"
    }
  ],
  "pasos": [
    {
      "numero": 1,
      "instruccion": "Cortar el pollo en dados.",
      "imagen_url": null
    }
  ],
  "categorias": [
    "Plato principal"
  ],
  "etiquetas": [
    "Pollo",
    "Curry"
  ]
}

## Reglas de extracción

- No inventes información que no aparezca o pueda deducirse razonablemente de la receta.
- Si una cantidad no aparece, usa null.
- Si una unidad no aparece, usa null.
- Conserva siempre `texto_original` para cada ingrediente.
- `cantidad` debe ser numérica cuando sea posible.
- Los tiempos siempre se expresan en minutos.
- Las categorías deben ser generales.
- Las etiquetas pueden describir cocina, ingrediente principal, método o características relevantes.
- No conviertas texto publicitario o contenido editorial en pasos de elaboración.
- Conserva el orden original de ingredientes y pasos.

## Guardado

Envía el JSON mediante una petición HTTP:

POST http://127.0.0.1:8080/recetas

Headers:

Content-Type: application/json
Authorization: Bearer $RECETAS_API_TOKEN

La API devuelve:

{
  "id": 123
}

Solo considera guardada la receta cuando recibas una respuesta HTTP 201.

Si la API devuelve un error, informa del fallo y no afirmes que la receta se ha guardado.
