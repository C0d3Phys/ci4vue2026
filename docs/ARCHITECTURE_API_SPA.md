# Guía de Buenas Prácticas

## API (CodeIgniter 4 + JWT) + SPA (Vue 3 + Vite)

Esta guía define el **estándar oficial del proyecto** para desarrollar, desplegar y mantener
una **API REST segura** y una **SPA moderna**, sin colisiones y con escalabilidad.

---

## 1. Principios fundamentales

-   La **API** y la **SPA** son proyectos separados.
-   La **API** no renderiza vistas.
-   La **SPA** no contiene lógica de negocio ni seguridad.
-   Toda autorización vive en la **API**.
-   Todo lo que se expone debe estar versionado (`/api/v1`).

---

## 2. Arquitectura general

```

Navegador
|
| HTTPS
v
Apache
├── / → SPA (Vue compilado)
└── /api/v1 → API CI4

```

### Tecnologías

-   Backend: CodeIgniter 4 + JWT
-   Frontend: Vue 3 + Vite
-   Servidor: Apache
-   Autenticación: JWT (Authorization Header)

---

## 3. Estructura de carpetas (producción)

```

/var/www/tudominio/
api/
app/
writable/
public/ ← único expuesto
spa/
dist/ ← único expuesto

```

**Nunca** exponer:

-   `app/`
-   `writable/`
-   `.env`
-   código fuente de Vue (`src/`)

---

## 4. API – Buenas prácticas

### 4.1 Versionado

Toda API debe vivir bajo:

```

/api/v1

```

Cambios incompatibles → nueva versión (`/api/v2`).

---

### 4.2 Formato estándar de respuestas

#### Éxito

```json
{
    "status": "success",
    "data": {},
    "message": null
}
```

#### Error

```json
{
    "status": "error",
    "data": null,
    "message": "Descripción del error",
    "errors": {}
}
```

---

### 4.3 Códigos HTTP correctos

| Código | Uso            |
| -----: | -------------- |
|    200 | OK             |
|    201 | Created        |
|    204 | No Content     |
|    400 | Bad Request    |
|    401 | Unauthorized   |
|    403 | Forbidden      |
|    404 | Not Found      |
|    422 | Validación     |
|    500 | Error servidor |

---

## 5. Autenticación JWT

### Reglas obligatorias

-   JWT en header:

    ```
    Authorization: Bearer <token>
    ```

-   Token corto: 15–60 minutos
-   Claims mínimos:

    -   `sub` (user_id)
    -   `iat`, `exp`

### Prohibido

-   No guardar permisos dentro del token
-   No usar JWT en cookies no HttpOnly

---

## 6. Autorización (RBAC)

### Modelo

```
Usuarios → Roles → Permisos
```

### Convención de permisos

```
{module}.{resource}.{action}
```

Ejemplos:

-   `clients.read`
-   `sales.invoice.cancel`
-   `inventory.move.create`

### Control

-   Filtro `auth` → valida JWT
-   Filtro `permission` → valida permiso requerido

---

## 7. SPA – Vue + Vite

### Desarrollo

```bash
npm run dev
```

-   Corre Vite (puerto 5173)
-   Proxy `/api` hacia backend
-   Apache no participa

---

### Producción

```bash
npm run build
```

-   Se genera `dist/`
-   Apache sirve archivos estáticos
-   No se necesita Node en producción

---

### Rewrite obligatorio (SPA router)

Archivo: `dist/.htaccess`

```apache
RewriteEngine On

RewriteRule ^api/v1(/.*)?$ - [L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

RewriteRule ^ index.html [L]
```

---

## 8. Variables de entorno (Vite)

### Archivos

-   `.env.development`
-   `.env.production`

Ejemplo:

```
VITE_API_BASE_URL=https://tudominio.com
```

Uso en código:

```js
import.meta.env.VITE_API_BASE_URL;
```

---

## 9. Comunicación SPA ↔ API

### Axios (recomendado)

-   Interceptor para agregar JWT
-   Manejo central de:

    -   401 → logout
    -   403 → mostrar “sin permisos”

Nunca:

-   Validar permisos en frontend
-   Confiar en datos del cliente

---

## 10. Apache – Configuración recomendada

-   `DocumentRoot` → SPA `dist/`
-   `Alias /api/v1` → CI4 `public/`
-   `mod_rewrite` habilitado
-   HTTPS obligatorio

---

## 11. Pruebas de API (estándar del proyecto)

### Herramienta oficial

-   VSCode + REST Client (`.http`)

Estructura:

```
rest/
  auth.http
  users.http
  roles.http
  permissions.http
```

Ventajas:

-   Versionable
-   Reproducible
-   Documenta la API

---

## 12. Seguridad mínima obligatoria

-   HTTPS
-   Validación server-side
-   Rate limit en login
-   Logs de acciones críticas
-   No exponer errores internos

---

## 13. Checklist antes de producción

### API

-   [ ] JWT funcionando
-   [ ] RBAC activo
-   [ ] Versionado correcto
-   [ ] Logs activos
-   [ ] `.env` seguro

### SPA

-   [ ] `dist/` servido
-   [ ] Rewrite correcto
-   [ ] Variables de entorno correctas
-   [ ] Build sin errores

---

## 14. Regla de oro

> El frontend **consume**.
> El backend **decide**.

Si una decisión de seguridad está en el frontend, está mal.
