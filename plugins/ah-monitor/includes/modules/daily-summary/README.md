# WC Daily Summary — Plugin de Resumen Diario

Plugin de WordPress para WooCommerce que agrega un menú en el backend con un dashboard completo de órdenes por día.

## Características

- **Resumen KPI**: ingresos del día, total de órdenes, captura Stripe, total de descuentos
- **Desglose por estado**: completed, processing, on-hold, cancelled, etc.
- **Cobros por tipo**:
  - Nuevas (con Stripe Payment Intent / Charge ID)
  - Renovaciones de suscripciones (`_subscription_renewal`)
  - Órdenes con cupón
  - Otras
- **Verificación Stripe**: lista las órdenes con su `payment_intent` o `charge_id` para cotejar manualmente o con la API de Stripe
- **Resumen de cupones**: código, cantidad de usos y descuento total generado
- **Tabla detallada** con filtros por estado y tipo
- Compatible con **HPOS** (High-Performance Order Storage)

## Instalación

1. Subir la carpeta `wc-daily-summary/` a `/wp-content/plugins/`
2. Activar el plugin desde **Plugins → Plugins instalados**
3. Ir a **Resumen Diario** en el menú del administrador

## Estructura de archivos

```
wc-daily-summary/
├── wc-daily-summary.php   ← archivo principal del plugin
└── assets/
    ├── dashboard.css      ← estilos del dashboard
    └── dashboard.js       ← lógica del frontend
```

## Cómo detecta los tipos de orden

| Tipo | Condición |
|------|-----------|
| **Nueva (Stripe)** | Tiene meta `_stripe_intent_id` o `_stripe_charge_id` |
| **Renovación** | Tiene meta `_subscription_renewal` |
| **Cupón** | Tiene cupones aplicados (`get_coupon_codes()`) |
| **Otra** | No cumple ninguna de las anteriores |

## Requisitos

- WordPress 6.0+
- WooCommerce 7.0+ con HPOS activado
- PHP 7.4+

## Integración Stripe API (opcional)

Para comparar totales en tiempo real con Stripe, puedes extender el método `renderStripe()` en `dashboard.js` para llamar a un endpoint propio que use la Stripe PHP SDK y filtre por `created` del día seleccionado.
