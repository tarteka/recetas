# Rollback - Volver a Versión Anterior

Si algo falla en producción, puedes volver a una versión anterior en segundos.

---

## Opción 1: Rollback Manual (Rápido)

Conectarse al VPS:

```bash
ssh deploy@recetas.proyectozero.org
cd /opt/recetas
```

Obtener commits recientes:

```bash
# Opción A: Desde Git (si aún tienes repo local)
git log --oneline | head -10

# Opción B: Revisar qué versiones están en GHCR
curl -s https://ghcr.io/v2/tarteka/recetas/recetas-api/tags/list | jq '.tags | sort | reverse | .[0:10]'
```

Seleccionar versión anterior y desplegar:

```bash
# Ejemplo: rollback a commit anterior
ROLLBACK_SHA=9f8e7d6c

# Descargar imágenes antiguas
RELEASE_TAG=$ROLLBACK_SHA docker compose -f compose.prod.yaml pull

# Detener servicios actuales
docker compose -f compose.prod.yaml down

# Levantar versión antigua
RELEASE_TAG=$ROLLBACK_SHA docker compose -f compose.prod.yaml up -d

# Esperar inicialización
sleep 15

# Verificar
curl -f http://127.0.0.1:8080/salud

# Confirmar
echo "✅ Rollback completado a $ROLLBACK_SHA"
```

**Tiempo**: < 2 minutos
**Datos afectados**: Ninguno

---

## Opción 2: Rollback Automático (GitHub Actions)

Usar el workflow `rollback.yml` desde GitHub:

1. Ir a **GitHub → Actions → Manual Rollback**
2. Click **"Run workflow"**
3. Ingresar commit SHA anterior
4. Click **"Run workflow"**

GitHub Actions:
- Se conecta al VPS vía SSH
- Ejecuta el rollback
- Valida con health check
- Reporta resultado

**Ventaja**: No necesitas acceso SSH directo al VPS

---

## ¿Qué SHA Usar?

### Ver histórico de despliegues

```bash
# En el VPS
git log --oneline -20

# O en GitHub
# Go to: Actions → Build & Publish → Click cada run → Ver logs

# O en GHCR (obtener SHAs de imágenes publicadas)
curl -s https://ghcr.io/v2/tarteka/recetas/recetas-api/tags/list | jq '.tags | sort | reverse'
```

### Ejemplo con últimos 5 despliegues

```
a1b2c3d4  (actual - problema)
9f8e7d6c  ← Rollback a este
8e7d6c5b
7d6c5b4a
6c5b4a3f
```

---

## Rollback Parcial (Un Servicio)

Si solo un servicio falla, puedes hacer rollback solo de ese:

```bash
cd /opt/recetas

# Rollback solo recetas-api
RELEASE_TAG=9f8e7d6c docker compose -f compose.prod.yaml pull recetas-api
RELEASE_TAG=9f8e7d6c docker compose -f compose.prod.yaml up -d recetas-api
```

---

## Validación Post-Rollback

```bash
# Checklist
curl -f http://127.0.0.1:8080/salud          # API
curl -f https://recetas.proyectozero.org     # Web
ls -la /opt/recetas/datos/recetas.sqlite     # Datos

# Si alguno falla, volver a intentar rollback o investigar logs
docker compose -f compose.prod.yaml logs recetas-api
```

---

## Preguntas Frecuentes

### ¿Qué pasa con los datos durante rollback?

**Nada**. Los datos en `/opt/recetas/datos/` **nunca se modifican** durante rollback. Solo cambia el código que se ejecuta.

### ¿Cuánto tarda el rollback?

**~2 minutos** desde que comienzas el comando hasta que esté listo.

### ¿Puedo rollback múltiples veces?

**Sí**, tantas veces como necesites. No hay límite.

### ¿Qué pasa si el SHA antiguo no existe en GHCR?

El comando `docker compose pull` fallará con "image not found". En ese caso:
1. Verifica el SHA desde GitHub Actions o Git
2. Usa otro SHA más antiguo que sepas que existe
3. Contáctame si necesitas recuperar una imagen antigua

### ¿Y si rollback falla?

Revisar logs:
```bash
docker compose -f compose.prod.yaml logs recetas-api
```

Si aún así no funciona:
1. Intentar con un SHA más antiguo
2. Contactar al equipo de desarrollo
3. Como última opción: downtime controlado y diagnóstico manual

---

## Escalada de Problemas

### Problema pequeño (un servicio lento)
→ Reiniciar ese servicio: `docker compose restart recetas-web`

### Problema medio (API fallando)
→ Rollback a versión anterior

### Problema grave (todo caído)
→ Rollback + revisar logs + contactar equipo

---

## Auditoría de Rollbacks

Cada rollback queda registrado:

```bash
# Ver histórico de comandos ejecutados (shell history)
history | grep RELEASE_TAG

# O revisar en GitHub Actions si usaste el workflow
# Actions → Manual Rollback → Ver logs
```

---

## Procedimiento Completo (Paso a Paso)

Si algo falla en producción:

1. **Acceder al VPS**
   ```bash
   ssh deploy@recetas.proyectozero.org
   ```

2. **Obtener SHA anterior**
   ```bash
   git log --oneline | head -5
   # Tomar el segundo: 9f8e7d6c
   ```

3. **Ejecutar rollback**
   ```bash
   cd /opt/recetas
   RELEASE_TAG=9f8e7d6c docker compose -f compose.prod.yaml pull
   docker compose -f compose.prod.yaml down
   RELEASE_TAG=9f8e7d6c docker compose -f compose.prod.yaml up -d
   sleep 15
   ```

4. **Validar**
   ```bash
   curl -f http://127.0.0.1:8080/salud
   echo "✅ OK" || echo "❌ FALLA"
   ```

5. **Investigar problema**
   ```bash
   docker compose -f compose.prod.yaml logs --tail=100
   ```

6. **Notificar al equipo**
   - Qué falló
   - Cuándo se detectó
   - Qué versión se desplegó (SHA actual)
   - Qué versión se usó para rollback
   - Acciones tomadas

---

**Tiempo total de rollback**: 3-5 minutos
**Riesgo de pérdida de datos**: CERO
**Datos que se pierden**: Ninguno
