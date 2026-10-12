# CrediTrack v5 — SaaS

Plataforma para que **cualquier prestamista o empresa de créditos** gestione su cartera: se registra sola, prueba gratis y paga una suscripción.
**Stack:** Laravel 13 · Filament 5 (multi-empresa) · dompdf · WAHA (WhatsApp)

## Estructura

| Quién | Dónde | Qué hace |
|---|---|---|
| Administrador de la plataforma | `/plataforma` | Empresas, planes, pagos de suscripción, usuarios, ingresos (MRR) |
| Dueño de una empresa | `/admin/{empresa}` | Todo: cartera, equipo, configuración y suscripción |
| Prestamista | `/admin/{empresa}` | Opera toda la cartera; no gestiona equipo, configuración ni suscripción |
| Cobrador | `/admin/{empresa}` | Ve la cartera, registra pagos y promesas, envía recordatorios. No crea, edita, borra, reestructura ni anula |
| Deudor | `/mi-cuenta` | Su préstamo, con lo que la empresa le permita ver |

Una persona puede pertenecer a varias empresas y cambiar de una a otra en el menú superior. Las empresas están **aisladas**: ninguna ve datos de otra.

## Ciclo comercial
1. La empresa se registra en `/admin/register` y crea su negocio → **14 días de prueba** con el plan Profesional.
2. Al vencer la prueba o el pago (con **3 días de gracia**), su panel solo muestra **Suscripción**, con instrucciones de pago y un botón de WhatsApp para enviar el comprobante. Sus datos se conservan.
3. En `/plataforma → Empresas → Registrar pago` eliges el plan y los meses → la empresa queda activa hasta la nueva fecha.
4. Planes iniciales: **Básico** ($39.900), **Profesional** ($89.900) y **Empresarial** ($179.900). Se editan en `/plataforma → Planes` (límites de préstamos, usuarios, WhatsApp diarios y funciones).

El cobro automático (Wompi / Mercado Pago) se conectará después: `subscription_payments.method` ya distingue manual o pasarela.

## Opciones de cada empresa (Configuración de la empresa)
- **Portal del cliente:** mostrar u ocultar el saldo total (si se oculta, el cliente ve solo su próxima cuota y el recibo no muestra el saldo), todas las cuotas, el historial de pagos, el estado de cuenta y el botón de contacto.
- **Avisos por WhatsApp:**
  - qué se envía: víspera, día del pago, atraso, promesa, recibo y resumen diario;
  - en qué **días de atraso** se avisa;
  - si los mensajes **mencionan el saldo** o incluyen el **enlace al PDF**;
  - **hora de envío** y descanso los domingos.
- **Mora**, **plantillas de mensajes** (con bloques opcionales `[[ ]]`), **logo** (planes Profesional y superiores), **webhook** y **número de WhatsApp propio** (Empresarial).

## WhatsApp
Por defecto todos los mensajes salen del número de la plataforma, **firmados con el nombre de cada empresa**, y con un **tope diario por empresa** para proteger el número. El plan Empresarial permite un número propio (requiere WAHA Plus, porque la versión gratuita maneja una sola sesión).

## Actualizar desde v4
1. **Backup de la base.**
2. `composer install && php artisan migrate`. La migración `creditrack_v5_saas`:
   - crea una **empresa por cada prestamista existente**, con él como dueño;
   - mueve allí sus clientes, préstamos, pagos y configuración;
   - deja tu usuario como **administrador de la plataforma**, en el plan Empresarial y sin vencimiento.
3. Entra a `/admin`: llegas a tu empresa. `/plataforma` es tu panel de administración.
4. Configura `SAAS_*` en el `.env` (instrucciones de pago y WhatsApp de soporte).

> La API REST de v3 (`/api/*`) se retiró: ya no la usaba nada y no era multi-empresa.

---

# Historial: v4

Gestión de préstamos para prestamistas independientes: cartera, cobranza, recordatorios por WhatsApp y portal para los clientes.
**Stack:** Laravel 13 · Filament 5 · dompdf · WAHA (WhatsApp) · Docker / Coolify

## Qué trae v4

**Panel del prestamista (`/admin`, Filament)**
- **Resumen:** por cobrar, capital en la calle, mora (en $ y %), recaudado del mes contra lo esperado, intereses cobrados, gráfica de 6 meses y los préstamos con mayor mora.
- **Préstamos:** simulación en vivo al crearlos (cuotas, total y ganancia), ficha con cuotas, pagos y promesas, y estas acciones:
  - **Registrar pago** con medio de pago, referencia, foto del comprobante y recibo por WhatsApp.
  - **Promesa de pago**: ese día se le recuerda al cliente, y mientras la promesa esté vigente no recibe avisos de atraso.
  - **Reestructurar**: reorganiza el saldo en cuotas nuevas y conserva el historial pagado. Muestra el plan antes de confirmar.
  - **Estado de cuenta en PDF**, que también se puede enviar por WhatsApp.
  - Mover la fecha de una cuota, perdonar recargos por mora, cancelar o reactivar el préstamo.
- **Clientes:** calificación de cumplimiento (Excelente / Bueno / Regular / Riesgoso según el % de cuotas pagadas a tiempo), cupo de crédito, codeudor, documentos e historial de los WhatsApp enviados.
- **Bandeja de cobro:** lo que vence hoy y lo vencido, con botones para registrar el pago, enviar un recordatorio, registrar una promesa, llamar o abrir el chat.
- **Pagos:** recibos `RC-0001…`. Un pago **se anula con motivo**: queda en el historial pero deja de contar.
- **Simulador:** el plan completo antes de prestar, y un botón para crear el préstamo con esos datos.
- **Configuración:** nombre del negocio, mora automática (% y días de gracia), **plantillas editables** de los WhatsApp y webhook hacia afdeveloper.
- **Bitácora:** quién creó, editó o borró cada préstamo, pago o cliente.
- **Multi-prestamista:** cada usuario ve solo lo suyo. El administrador gestiona los prestamistas.

**Portal del cliente (`/mi-cuenta`)**: entra con cédula + código por WhatsApp y ve saldo, progreso, próxima cuota, cuotas, pagos y recibos, estado de cuenta y un botón para escribirle al prestamista. Está diseñado primero para el celular.

**Mora automática** (opcional, por prestamista): recargo de un % sobre la cuota vencida, **una sola vez por cuota**, después de los días de gracia. Se puede perdonar.

> El frontend en React se retiró: el panel y el portal se sirven con Laravel y no hace falta Node para construir la imagen. La API (`/api/*`) se conserva por si se hace una app móvil.

## Actualizar desde v3

1. **Backup de la base de datos.**
2. `composer install && php artisan migrate`. La migración `creditrack_v4` agrega columnas y tablas, numera los recibos existentes y no borra nada.
3. Entra a `/admin` con tu usuario de siempre. Si eres el dueño, hazte administrador:
   `php artisan tinker --execute="App\\Models\\User::where('email','TU_CORREO')->update(['role'=>'admin']);"`
4. Ve a **Configuración**: nombre del negocio, tu WhatsApp, mora y plantillas.
5. Tus clientes ahora entran por **`/mi-cuenta`** (el enlace viejo `/login-client` redirige solo).

---

# Historial: v3

Gestión de préstamos, cuotas y cobros con recordatorios por WhatsApp.
**Stack:** Laravel 13 · Sanctum 4 · React 18 (Vite) · WAHA (WhatsApp) · Docker / Coolify

## Qué cambió respecto a v2

### 🔴 Seguridad
| Antes | Ahora |
|---|---|
| El token de un **cliente** funcionaba en las rutas del prestamista y `Auth::id()` devolvía su id: el cliente #3 veía **todo** lo del prestamista #3 | Tokens con permisos (`lender` / `client`) y middleware que verifica el tipo de usuario |
| Entrar como cliente solo pedía la cédula | Cédula + **código de 6 dígitos por WhatsApp**, de un solo uso, vence en 10 min, máx. 5 intentos |
| Registro de prestamistas abierto | Cerrado por defecto (`REGISTRATION_ENABLED=false`) |
| Login sin límite de intentos | 5 por minuto; códigos limitados por IP y por cédula |
| La cédula era única en todo el sistema | Única por prestamista (dos prestamistas pueden tener al mismo cliente) |

### 🔴 Dinero
- **El saldo ignoraba los intereses:** un préstamo de $1.000.000 al 10 % se marcaba *pagado* al abonar $1.000.000. Ahora `total_amount` guarda capital + interés y el saldo se calcula sobre eso.
- **Libro contable** (`App\Services\LoanLedger`): cada pago se reparte entre las cuotas, de la más vieja a la más nueva. Así se sabe qué cuota está pagada, abonada parcialmente o vencida. Se recalcula todo al crear, editar o borrar un pago, y borrar un pago ya no deja el préstamo como "pagado" para siempre.
- **Cuotas vencidas automáticas.** Antes nada marcaba una cuota como vencida.
- No se puede pagar más que el saldo, ni cambiar monto, tasa o fechas de un préstamo que ya tiene pagos.
- **Cronograma:** la primera cuota cae un periodo después del desembolso (antes, el mismo día) y la última en la fecha final. Los meses no se desbordan: una cuota del 31 de enero pasa al 28 de febrero.
- **Cargos adicionales** (ej. mora) desde el calendario: suman al total del préstamo.

> ⚠️ Al migrar, **todos los préstamos existentes se recalculan**. Los que v2 marcó "pagados" sin cubrir los intereses quedarán *atrasados* con su saldo real. Revísalos antes de avisarle a tus clientes.

### 🟢 Nuevo
- **WhatsApp (WAHA):** recordatorio la víspera y el día del pago; aviso de atraso a 1, 3, 7, 15 y 30 días (no todos los días); recibo al registrar un pago y resumen diario para el prestamista. Nunca se repite un aviso, y los mensajes salen espaciados para cuidar el número.
- **Portal del cliente** (`/dashboard-client`): el deudor ve sus cuotas, abonos y progreso.
- **Webhooks firmados** hacia afdeveloper.com: cada desembolso y cada pago aparecen en Finanzas.
- `GET/PUT /api/settings`: avisos y webhook del prestamista.

## Instalar / actualizar

> ⚠️ **Haz backup de la base de datos antes de migrar.**

```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate   # solo en instalación nueva
php artisan migrate
php artisan test
```

Procesos que deben correr siempre (la imagen Docker ya los trae con supervisor):
```bash
php artisan queue:work       # envía WhatsApp y webhooks
php artisan schedule:work    # cobranza diaria a las 8:00 a. m.
```

Para ver qué avisos saldrían hoy sin enviarlos:
```bash
php artisan creditrack:collections --dry-run
```

## WhatsApp con WAHA

1. En Coolify crea un recurso **Docker Compose** con `docker/waha-compose.yml` y define `WAHA_API_KEY` y `WAHA_DASHBOARD_PASSWORD`.
2. Abre el dashboard de WAHA, crea la sesión `default` y escanea el QR con el WhatsApp que enviará los mensajes.
3. En CrediTrack (y en afdeveloper):
   ```
   WAHA_ENABLED=true
   WAHA_URL=http://waha:3000     # nombre interno del servicio en la red de Coolify
   WAHA_API_KEY=el-mismo-valor
   ```

**Importante:** WAHA usa WhatsApp Web, no la API oficial de Meta. Para reducir el riesgo de bloqueo:
- usa un **número dedicado**, no tu WhatsApp personal;
- pídeles a tus clientes que te guarden como contacto;
- no bajes `WAHA_SPACING_SECONDS` de 10.

Cada cliente tiene `whatsapp_opt_in` para excluirlo.

## Conectar con afdeveloper.com

```bash
php artisan creditrack:webhook alancarabali@gmail.com https://afdeveloper.com/webhooks/creditrack
```

Copia el secreto que imprime en el `.env` de afdeveloper como `CREDITRACK_WEBHOOK_SECRET`.
Desde ese momento:
- cada **desembolso** aparece como gasto en "Préstamos: desembolsos";
- cada **pago** aparece como ingreso en "Préstamos: recaudos";
- el cliente se crea solo en Finanzas.

La diferencia entre ambos es tu ganancia por intereses, y la verás en el dashboard.

## API: cambios para el frontend
| Endpoint | Cambio |
|---|---|
| `POST /api/auth/client/login` | **Eliminado** → `POST /api/auth/client/request-code` + `POST /api/auth/client/verify` |
| `GET /api/client/loans` | Nuevo: préstamos del cliente autenticado |
| `PUT /api/schedules/{id}` | Solo reprograma la fecha; el estado lo decide el libro contable (si llega `status` se ignora) |
| `POST /api/schedules` | Ahora es un *cargo adicional* que suma al total del préstamo |
| Préstamos y cuotas | Nuevos campos: `total_amount`, `total_paid`, `balance`, `amount_paid`, `amount_pending` |
