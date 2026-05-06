INSERT INTO ternary_signal_definitions (
    signal_key,
    module,
    positive_label,
    neutral_label,
    negative_label,
    description,
    is_active
) VALUES
('search_relevance', 'search', 'relevante', 'ambiguo', 'irrelevante', 'Señal de relevancia para resultados de búsqueda.', 1),
('content_safety', 'security', 'permitido', 'requiere revisión', 'bloqueado', 'Señal de seguridad de contenido.', 1),
('trust_score', 'search', 'confiable', 'desconocido', 'no confiable', 'Señal de confianza de fuente o dominio.', 1),
('user_intent_match', 'search', 'coincide', 'ambigua', 'no coincide', 'Señal de coincidencia entre intención y resultado.', 1),
('spam_signal', 'security', 'limpio', 'desconocido', 'spam/sospechoso', 'Señal anti-spam para entradas y eventos.', 1),
('marketing_lead_quality', 'marketing', 'lead bueno', 'lead pendiente', 'lead débil/no calificado', 'Calidad del lead para priorización comercial.', 1),
('email_delivery_risk', 'mail', 'riesgo bajo', 'riesgo desconocido', 'riesgo alto', 'Riesgo de entrega para correo interno/futuro.', 1),
('client_health_signal', 'marketing', 'cliente saludable', 'cliente prospecto/pendiente', 'cliente pausado/inactivo', 'Salud comercial del cliente según su estado actual.', 1),
('campaign_health_signal', 'marketing', 'campaña activa', 'campaña en borrador', 'campaña detenida/finalizada/cancelada', 'Salud operativa de campaña según su estado.', 1)
ON DUPLICATE KEY UPDATE
    module = VALUES(module),
    positive_label = VALUES(positive_label),
    neutral_label = VALUES(neutral_label),
    negative_label = VALUES(negative_label),
    description = VALUES(description),
    is_active = VALUES(is_active);
