# Agendify - Sistema de Gestión y Reserva de Turnos WebApp

Aplicación Fullstack desarrollada para el **Trabajo Práctico Integrador N.º 1** de la materia **Aplicaciones Móviles y Cloud Computing** (UPSO).

---

## Despliegue en la Nube
- **URL de la Aplicación:** [https://upso-agendify.free.nf](https://upso-agendify.free.nf)
- **Servicio Cloud Integrado:** Hosting e infraestructura en la nube proporcionada por **InfinityFree** (Apache + PHP 8.x + MySQL Server en la nube).

---

## Arquitectura del Sistema
El sistema está construido bajo el patrón Cliente-Servidor desacoplado y arquitectura REST:
- **Frontend:** HTML5, CSS3 módern con variables y Grid/Flexbox, JavaScript Vanilla (Fetch API asíncrono).
- **Backend:** PHP 8.x con PDO (PHP Data Objects), consultas preparadas para prevención de inyecciones SQL y respuestas estructuradas en formato JSON.
- **Base de Datos:** MySQL / MariaDB (`sistema_turnos`).

---

## Descripción de Endpoints de la API REST

### 1. Gestión de Autenticación y Cuentas (`index.php`)
- **`POST /index.php`** (`action = register`)
  - **Descripción:** Registra un nuevo negocio o profesional en la plataforma.
  - **Parámetros:** `nombre`, `dni`, `rubro`, `email`, `password`.
  - **Respuesta:** Redirección al dashboard e inserción de servicio base por defecto.

- **`POST /index.php`** (`action = login`)
  - **Descripción:** Inicia sesión comprobando las credenciales contra el hash Bcrypt.
  - **Parámetros:** `email`, `password`.

### 2. Gestión de Turnos y Reservas (`backend/routers/turnos.php` / `reserva.php`)
- **`GET /reserva.php?link={link_unico}`**
  - **Descripción:** Obtiene la información del negocio y sus servicios activos asociados mediante su enlace público único.

- **`POST /reserva.php`** / **`POST /api/turnos`**
  - **Descripción:** Crea una nueva reserva para un cliente final comprobando la no superposición de horarios ocupados.
  - **Parámetros (JSON/Form):** `id_servicio`, `nombre_cliente_final`, `telefono_cliente_final`, `fecha`, `hora`.

- **`DELETE /api/turnos`**
  - **Descripción:** Cancela un turno reservado mediante un código único de validación (exige un mínimo de 24 hs de anticipación).

---

## Pantallas
- **Reserva de Turnos:** `http://localhost/WebApp/frontend/reservar.html`
- **Cancelación de Turnos:** `http://localhost/WebApp/frontend/cancelar.html`

---

## Variables de Entorno Necesarias

Para ejecutar el proyecto en un entorno local o de producción, se deben configurar los siguientes parámetros en el archivo de configuración del servidor (`conexion.php` o `.env`):

| Variable | Descripción | Ejemplo de Valor |
| :--- | :--- | :--- |
| `DB_HOST` | Servidor de Base de Datos MySQL | `localhost` / `sqlXXX.infinityfree.com` |
| `DB_NAME` | Nombre de la Base de Datos | `sistema_turnos` |
| `DB_USER` | Usuario de MySQL | `root` / `if0_XXXXXX` |
| `DB_PASS` | Contraseña de acceso a MySQL | *(Vacío en local / Clave de producción)* |

---

## Instrucciones de Instalación en Entorno Local (XAMPP / WAMP)

1. **Clonar el repositorio:**
   ```bash
   git clone <https://github.com/MicaBahurlet/WebApp>
