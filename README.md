# Agendify - Sistema de Gestión y Reserva de Turnos WebApp

## Grupo 6 - Bahurlet Micaela - Caceres Tomas - Domé Florencia - Spinetta Carlos Alberto

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

### 2. Gestión de Turnos y Reservas (`reserva.php` / `cancelar.php`)
- **`GET /reserva.php?link={link_unico}`**
  - **Descripción:** Obtiene la información del negocio y sus servicios activos asociados mediante su enlace público único.

- **`GET /reserva.php?action=obtener_horarios&fecha={YYYY-MM-DD}&id_servicio={ID}`**
  - **Descripción:** Endpoint AJAX asíncrono que retorna en JSON los horarios disponibles, calculando la duración del servicio y bloqueando turnos ocupados o cancelados.

- **`POST /reserva.php`**
  - **Descripción:** Crea una nueva reserva para un cliente final y genera un **código único de cancelación de 6 caracteres** (ej: `A8X912`).
  - **Parámetros:** `id_servicio`, `nombre_cliente_final`, `telefono_cliente_final`, `fecha`, `hora`.

- **`POST /cancelar.php`**
  - **Descripción:** Permite al cliente cancelar su turno ingresando su código único de verificación, validando la regla de negocio de **al menos 24 horas de anticipación**.

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

## Infraestructura y Hosting (Despliegue)

Actualmente, **Agendify** se encuentra alojado y desplegado en **InfinityFree** como servidor web y de base de datos para la versión MVP / Prototipo.

### ¿Por qué elegimos InfinityFree en esta etapa?

1. **Cero costo operativo (Plan Gratuito):**
   Al encontrarse el proyecto en fase de desarrollo, validación y testing del MVP, nos permite disponer de un entorno de pruebas real en la nube sin incurrir en costos recurrentes de infraestructura.

2. **Compatibilidad nativa con Stack PHP + MySQL:**
   Ofrece soporte completo de PHP y bases de datos MySQL, permitiendo ejecutar la lógica backend, consultas PDO y migraciones del modelo de datos sin requerir configuraciones complejas de contenedores o servidores dedicados.

3. **Soporte para consumo de APIs REST salientes:**
   A través del soporte de cURL y SSL/HTTPS, nos permite integrar y consumir servicios externos clave para la aplicación (como la API REST de notificaciones transaccionales por email vía Resend).

4. **Entorno seguro con Certificados SSL Gratuitos:**
   Facilita la emisión e integración de certificados SSL (HTTPS), asegurando que el tráfico entre el cliente, el panel de administración y el backend viaje cifrado en todo momento.

5. **Simplicidad para iteraciones rápidas:**
   Su panel de gestión (cPanel) y FTP integrado facilitan el despliegue de cambios en la estructura de archivos modular (`frontend/`, `backend/`, `config/`), optimizando el ciclo de entrega e integración continua durante el desarrollo.

> *Nota de escalabilidad:* En etapas posteriores de producción a mayor escala, se contempla la migración hacia una infraestructura en la nube dedicada (como Railway, VPS o AWS) para habilitar configuraciones avanzadas de servidor, tareas cron de fondo sin restricciones y entornos multi-inquilino de alto tráfico.


###  Sistema de Notificaciones por Email

Las notificaciones de confirmación y gestión de turnos se procesan mediante la API REST de **Resend** a través de solicitudes HTTP salientes (`cURL`).

>  **Nota sobre el entorno de desarrollo y testing:**  
> Actualmente, la API Key de Resend está operando en **modo de prueba (Testing / Onboarding)** sin dominio verificado. Debido a las políticas de seguridad y restricciones de cuentas en prueba de Resend, las notificaciones salientes están limitadas únicamente a la dirección de correo registrada en la plataforma: **`charly.cronomax@gmail.com`**.  
> 

---


## Instrucciones de Instalación en Entorno Local (XAMPP / WAMP)

1. **Clonar el repositorio:**
   ```bash
   git clone <https://github.com/MicaBahurlet/WebApp>


2. **Configurar la Base de Datos:**
   - Abra phpMyAdmin (`http://localhost/phpmyadmin`).
   - Cree una nueva base de datos llamada `sistema_turnos`.
   - Importe el archivo `database/sistema_turnos.sql`.
   - **Nota:** Asegúrese de que la tabla `turnos` contenga la columna `codigo_cancelacion VARCHAR(10)` para permitir cancelaciones por parte de los clientes.
