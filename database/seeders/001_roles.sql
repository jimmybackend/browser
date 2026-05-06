INSERT INTO roles (name, description) VALUES
('admin', 'Administrador general del sistema.'),
('user', 'Usuario estándar de la plataforma.'),
('marketing_manager', 'Responsable de clientes y campañas de marketing.'),
('sales_agent', 'Responsable de seguimiento de leads.')
ON DUPLICATE KEY UPDATE description = VALUES(description);
