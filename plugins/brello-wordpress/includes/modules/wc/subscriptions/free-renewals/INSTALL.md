# Módulo `free-renewals` — instalación y prueba

Modelo: **cada N días la suscripción entrega; 1 de cada (X+1) entregas se cobra, las
otras X son gratis — y el ciclo se repite indefinidamente mientras la suscripción
esté activa.** No hay un punto donde el plan "se completa" y deja de renovar: la
suscripción sigue su calendario nativo para siempre (o hasta que el término nativo /
`Plan Duration Days` la termine — ver sección 3, es una cosa aparte).

Plan de 6 meses = `{N:77, X:1}` → cobra, gratis, cobra, gratis, ... cada 77 días.
Plan de 1 año = `{N:77, X:4}` → cobra, 4 gratis, cobra, 4 gratis, ...
Mismo código, distinta config de variación.

## 1. Ubicación

Copiar la carpeta completa a:

```
includes/modules/wc/subscriptions/free-renewals/
```

## 2. Registrar en el loader

En `includes/modules/loader.php`, en la sección SUBSCRIPTIONS (después de la línea de `subscriptions-next-payment-date.php`), agregar:

```php
$this->safe_require( $base . 'wc/subscriptions/free-renewals/loader.php' );
```

Es lo único que se toca fuera de la carpeta. El módulo es additivo: solo engancha `wcs_renewal_order_created` y `woocommerce_checkout_subscription_created`, y solo interviene en productos con `_bh_free_renewals >= 1`. No modifica `AH_Subscription_Next_Payment_Date` ni el calendario nativo de renovación.

## 3. Config de la variación (Camino B — el cobro $0 lo fuerza el código)

En la variación del producto de 6 meses:

- **Subscription price:** `899` (recurrente real — se muestra "$899 every 6 months" y es lo que cobra cada renovación que le toque cobrar en el ciclo).
- **Sign-up fee:** `0`.
- **Stop renewing after:** dejar en *never* o un término largo — el plan ya no se detiene por agotar las renovaciones gratis, así que "Stop renewing after: 6 months" ya **no** aplica para este propósito (ver sección 6, `Plan Duration Days` es la vía si en algún momento sí hace falta un término real).
- **Renewal Days:** `77` (ya existía).
- **Free Renewals:** `1` (nuevo) — X gratis por ciclo, se repite.
- **Plan Duration Days:** *vacío* salvo que el plan deba terminar en algún momento explícito (no es el caso por defecto ahora).

El módulo pone en $0 las renovaciones que le toca ser gratis dentro del ciclo, en `wcs_renewal_order_created`, antes de que WC evalúe `needs_payment()`. Por eso la garantía de no-cobro **no depende** de que el recurrente sea 0: podés dejar $899 visible y las entregas gratis del ciclo nunca cobran, mientras que la que le toca cobrar (1 de cada X+1) se procesa como una renovación normal. Esto aplica sin importar qué dispare la renovación — scheduler, "Process renewal"/"Create pending renewal order" desde admin, renovación anticipada del cliente, IPN de PayPal — porque todas pasan por `wcs_create_renewal_order()`, que siempre aplica este filtro antes de devolver la orden.

Para el plan de 1 año: `Free Renewals = 4` (cobra 1 de cada 5 renovaciones).

## 4. Qué hace

- Día 0 (orden inicial): cobra $899, entrega 1, otorga BrelloRise.
- Día 77 (renovación #1): free renewals restantes en el ciclo = 0 de 1 → se pone en $0, tag `_bh_free_renewal_delivery=yes` → entrega 2 vía el free-order handler → Telegra.
- Día 154 (renovación #2): ya se usó la única entrega gratis del ciclo → esta renovación se **cobra** normalmente ($899) → arranca un ciclo nuevo.
- Día 231 (renovación #3): primera del nuevo ciclo → gratis de nuevo.
- ...y así indefinidamente, mientras la suscripción siga activa.

La posición dentro del ciclo se deriva de las órdenes de renovación reales cada vez (no de un contador que pueda desincronizarse): `count_free_deliveries_in_cycle()` camina hacia atrás desde la renovación más reciente contando cuántas seguidas están tageadas como gratis, hasta toparse con la última cobrada (o el inicio del historial). Canceladas/failed/refunded no cuentan, así que una entrega bloqueada por otro motivo (p. ej. el bloqueo por estado en `renewal-blocker.php`) no consume ni adelanta el ciclo.

## 5. Checklist de staging

1. Crear la variación con la config de arriba y comprar. Verificar orden inicial cobra $899 y entrega 1 sale a Telegra.
2. Confirmar `next_payment = +77` en la suscripción.
3. Disparar la renovación del día 77 (adelantar fecha, o "Create renewal order"/"Process renewal" desde admin). Verificar en el log `ah-free-renewals` la línea `zeroed (1/1 free this cycle)`, que la orden queda en **$0**, sin PaymentIntent, y pasa `processing → send_to_telegra`.
4. Disparar la siguiente renovación (día 154). Debe **cobrar** $899 normalmente (PaymentIntent real) y el log debe mostrar `charged, cycle reset (max=1 free per cycle)`. Confirmar que `next_payment` se sigue agendando con normalidad (no se limpia, no hay ningún punto donde deje de renovar).
5. Disparar una renovación más (día 231): debe volver a ser gratis, igual que el paso 3 — confirma que el ciclo se repite.
6. Regresión: una suscripción normal (sin Free Renewals) debe seguir renovando y cobrando como siempre.
7. Emails de recordatorio (2 y 7 días antes): el texto "Your plan renews every ___" debe mostrar las semanas del **ciclo completo de cobro** (N × (X+1) / 7), no el intervalo de cada entrega — para `{N:77, X:1}` eso es 22 semanas, no 11. Ver sección 7.

## 6. Si el plan sí necesita terminar en algún momento

`Plan Duration Days` (T) sigue existiendo y es independiente del ciclo de cobro: si se llena, `set_plan_end_on_checkout()` fija la fecha `end` de la suscripción a `fecha_inicial + T días`, y WooCommerce Subscriptions la expira solo cuando llegue esa fecha — sin importar en qué punto del ciclo de cobro esté. Vacío = sin término explícito (la suscripción vive mientras el cliente no la cancele o el nativo "Stop renewing after" no la corte).

## 7. Emails: cadencia real de cobro

`AH_Free_Renewals::get_charge_cycle_weeks_for_subscription( $subscription )` calcula, para cualquier free-renewal plan, cuántas semanas dura el ciclo completo de cobro (`_bh_renewal_days * (_bh_free_renewals + 1) / 7`, redondeado). Las plantillas `templates/woocommerce/emails/customer-subscription-reminder-2.php` y `-7.php` lo usan primero (si el producto es un free-renewal plan) y solo caen al texto legacy (`BH_WEEKS_THREE_MONTH_PLAN`, atado a `billing_interval == 3`) para productos fuera de esta familia. Esto es dinámico — cambiar `Free Renewals` o `Renewal Days` en la variación ajusta el texto del email automáticamente, sin tocar código.

## Límites conocidos (v1)

- Intervalo N uniforme entre todas las entregas del ciclo (no soporta intervalos variables dentro de un mismo ciclo).
- La posición en el ciclo se deriva de las órdenes de renovación reales (canceladas/failed/refunded excluidas). Una entrega bloqueada por estado **no** consume ni adelanta el ciclo y puede reintentarse.
- Validación de config (`class-ah-free-renewals-admin.php`) sigue siendo suave (aviso + log); su chequeo de "plan duration vs free_renewals × renewal_days" solo aplica si se llena `Plan Duration Days` explícitamente — no tiene efecto en el modelo cíclico por defecto (sin T).
