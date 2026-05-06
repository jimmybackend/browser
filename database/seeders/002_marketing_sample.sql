INSERT INTO marketing_clients (company_name, contact_name, contact_email, website, status, notes)
VALUES
('Cliente Demo', 'Contacto Demo', 'demo@example.com', 'https://example.com', 'prospect', 'Cliente de prueba para desarrollo local.')
ON DUPLICATE KEY UPDATE company_name = company_name;
