# Protocolo ternario interno de señales

## Definición
El protocolo ternario estandariza decisiones internas con valores numéricos fijos:

- `+1`: positivo / permitido / relevante / confiable
- `0`: neutral / desconocido / pendiente / ambiguo
- `-1`: negativo / bloqueado / irrelevante / riesgoso

La lógica de negocio debe operar con estos números como fuente principal de verdad.

## Convención oficial de valores
- Tipo recomendado en base de datos: `TINYINT SIGNED`.
- Restricción recomendada: `CHECK (signal_value IN (-1, 0, 1))`.
- Etiquetas humanas (`aprobado`, `rechazado`, etc.) son opcionales y solo para UI/reportes.

## Ejemplos por módulo
- **Búsqueda**: `search_relevance`, `trust_score`, `user_intent_match`.
- **Seguridad/privacidad**: `content_safety`, `spam_signal`.
- **Correo**: `email_delivery_risk`.
- **Marketing**: `marketing_lead_quality`.

## Buenas prácticas
1. Comparar siempre por número (`=== 1`, `=== 0`, `=== -1`).
2. Normalizar entradas con `TernarySignal::normalize()`.
3. Validar valores antes de persistir (`TernarySignal::isValid()`).
4. Registrar señales con trazabilidad (`source`, `confidence`, `reason_code`, `metadata` no sensible).
5. Mantener un catálogo central de señales (`ternary_signal_definitions`).

## Qué no se debe hacer
- No comparar estados principales usando texto humano.
- No crear múltiples variantes textuales para la misma decisión.
- No usar strings como fuente principal de verdad.
- No guardar secretos/tokens/passwords en `human_note` o `metadata`.

## Guía para cambios futuros de Codex
- Reutilizar `Browser\Core\TernarySignal` para constantes y validación.
- En nuevos módulos, crear primero su `signal_key` en `ternary_signal_definitions`.
- Si se agregan columnas de señal, usar `TINYINT SIGNED` y `CHECK` cuando el motor lo soporte.
- Evitar nuevos enums de texto cuando un valor ternario cubra la decisión.
- Toda integración nueva debe exponer etiqueta humana solo en capa de presentación, nunca como lógica principal.

- **Marketing**: salud de cliente (`client_health_signal`) y salud de campaña (`campaign_health_signal`).
