---
name: recetario
description: Guarda recetas de cocina enviadas por URL en el recetario personal.
metadata: { 'openclaw': { 'emoji': '🍽️ ', 'always': true } }
---

# Recetario

Usa este skill cuando el usuario quiera guardar una receta de cocina, especialmente cuando envíe la URL de una página que contiene una receta.

## Flujo

1. Obtén el contenido de la URL usando Web Fetch.
2. Si Web Fetch no permite obtener suficientemente bien la receta, usa el navegador.
3. Identifica únicamente la receta publicada en la página.
4. Extrae y normaliza los datos.
5. Genera el JSON de la receta con `imagen_url` siempre igual a `null`.
6. Guarda la receta mediante `guardar-receta.sh`.
7. Obtén y conserva el `id` devuelto por la API.
8. Gestiona la imagen de forma independiente:
   - localiza la imagen principal original de la receta;
   - descárgala y valida que sea una imagen correcta;
   - comprueba visualmente que represente razonablemente el plato;
   - si es adecuada, guárdala mediante `guardar-imagen.sh`;
   - si no existe, falla la descarga o no es adecuada, genera y guarda una imagen mediante `generar-imagen.sh`.
9. Informa al usuario del resultado final.

La ausencia o el fallo de una imagen nunca debe impedir guardar una receta válida.

## Formato

La API espera un JSON con esta estructura:

```json
{
  "titulo": "Nombre de la receta",
  "descripcion": "Descripción breve o null",
  "fuente_url": "URL original",
  "fuente_nombre": "Nombre de la web o null",
  "imagen_url": null,
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
  "categorias": ["Plato principal"],
  "etiquetas": ["Pollo", "Curry"]
}
```

## Reglas de extracción

- No inventes información que no aparezca o pueda deducirse razonablemente de la receta.
- Si una cantidad no aparece, usa `null`.
- Si una unidad no aparece, usa `null`.
- Conserva siempre `texto_original` para cada ingrediente.
- `cantidad` debe ser numérica cuando sea posible.
- Los tiempos siempre se expresan en minutos.
- Conserva el orden original de ingredientes y pasos.
- Las categorías deben ser generales.
- Las etiquetas pueden describir cocina, ingrediente principal, método de cocción o características relevantes.
- No conviertas publicidad, introducciones editoriales o contenido irrelevante en pasos de elaboración.
- `imagen_url` debe enviarse siempre como `null` al crear la receta.
- No guardes en la base de datos la URL externa de la imagen original.

## Guardado de la receta

1. Genera el JSON estructurado.
2. Ejecuta:

`~/.openclaw/skills/recetario/scripts/guardar-receta.sh`

3. Envía el JSON completo por `stdin`.
4. No construyas manualmente peticiones HTTP alternativas.
5. La API devolverá una respuesta similar a:

```json
{
  "id": 123
}
```

6. Conserva el `id` devuelto.
7. Solo considera creada la receta cuando el script finalice correctamente y la API devuelva un identificador.

## Imagen de la receta

La imagen se procesa después de crear la receta y nunca forma parte del proceso necesario para que la receta pueda guardarse.

### Imagen original

Si la página contiene una imagen principal candidata:

1. Identifica la imagen principal relacionada con la receta.
2. Descárgala a un archivo temporal local.
3. Comprueba que el archivo sea realmente una imagen válida.
4. Analiza visualmente su contenido.
5. Compárala con:
   - título;
   - descripción;
   - ingredientes principales;
   - tipo de plato;
   - método de preparación;
   - resultado final esperado.

Considera válida la imagen únicamente si representa razonablemente el plato terminado.

Recházala si:

- corresponde a otro plato;
- es un logotipo, banner o publicidad;
- muestra únicamente ingredientes sueltos;
- es una imagen genérica de cocina;
- muestra varias recetas y no puede identificarse claramente la correspondiente;
- contradice ingredientes o características esenciales;
- tiene una calidad visual claramente insuficiente;
- no permite reconocer razonablemente el resultado final.

Si la imagen es adecuada, ejecuta:

`~/.openclaw/skills/recetario/scripts/guardar-imagen.sh <receta_id> <archivo_imagen>`

No redimensiones ni conviertas previamente la imagen.

La API se encargará de:

- validar el archivo;
- normalizarlo;
- recortarlo a relación 3:2;
- convertirlo a WebP 1200x800;
- almacenarlo;
- actualizar `imagen_url` de la receta.

## Generación de imagen

Genera una imagen específica cuando:

- la receta no tenga imagen;
- la imagen original no pueda descargarse;
- el archivo descargado no sea una imagen válida;
- la imagen original no represente correctamente la receta;
- la imagen original tenga una calidad claramente insuficiente.

No uses `image_generate` directamente.

Construye un prompt y ejecuta:

`~/.openclaw/skills/recetario/scripts/generar-imagen.sh <receta_id> "<prompt>"`

`generar-imagen.sh` se encarga de:

1. generar la imagen mediante OpenClaw CLI;
2. almacenarla temporalmente como archivo;
3. enviarla a `guardar-imagen.sh`;
4. permitir que la API la normalice y almacene;
5. asociarla a la receta mediante su `id`.

La imagen generada es un recurso interno del proceso. No la envíes al usuario por Telegram como resultado de la generación.

### Construcción del prompt

Construye el prompt usando como contexto:

- título de la receta;
- descripción;
- ingredientes principales;
- método de cocción;
- tipo de plato;
- presentación razonablemente esperable.

El prompt debe solicitar una imagen que:

- represente el plato terminado;
- respete razonablemente la receta;
- respete sus ingredientes principales;
- evite ingredientes destacados que no aparezcan en la receta;
- tenga aspecto de fotografía gastronómica realista;
- presente el plato como protagonista;
- tenga composición horizontal 3:2;
- no contenga texto;
- no contenga logotipos ni marcas;
- no muestre personas ni manos;
- no incluya elementos que contradigan la receta.

No es necesario solicitar exactamente 1200x800 píxeles. La API realizará la normalización definitiva.

### Ejemplo

Para una receta de marmitako, un prompt apropiado sería:

```text
Fotografía gastronómica realista de un marmitako vasco tradicional terminado, preparado con bonito, patatas, pimiento y caldo, servido en una cazuela. El marmitako debe ser el protagonista de la imagen. Presentación tradicional y realista, fotografía editorial de comida, luz natural suave, composición horizontal 3:2, sin texto, sin logotipos, sin marcas, sin personas y sin manos.
```

Y se ejecutaría:

`~/.openclaw/skills/recetario/scripts/generar-imagen.sh 123 "<prompt>"`

No ejecutes posteriormente `guardar-imagen.sh`, porque `generar-imagen.sh` ya se encarga de hacerlo.

## Prioridad de imagen

Sigue siempre este orden:

1. imagen original adecuada y validada;
2. imagen generada específicamente para la receta;
3. receta sin imagen si ambas opciones fallan.

Nunca:

- vuelvas a crear una receta porque falle su imagen;
- uses `image_generate` directamente;
- envíes al usuario la imagen generada como parte del proceso;
- guardes una URL externa como `imagen_url`;
- ejecutes `guardar-imagen.sh` después de `generar-imagen.sh`, porque sería redundante.

## Resultado

Si la receta y la imagen se guardan correctamente, informa de que la receta se ha añadido al recetario.

Si la receta se guarda correctamente pero falla la imagen, informa de que la receta se ha guardado, pero no se ha podido asociar una imagen.

Si falla el guardado de la receta, informa del fallo y no afirmes que la receta se ha guardado.
