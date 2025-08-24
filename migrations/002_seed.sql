INSERT INTO settings (id, brand_name, primary_color) VALUES (1, 'Basket Hunt', '#4f46e5');

INSERT INTO departments (name, slug, sort_order) VALUES
('HR','hr',1),
('Sales','sales',2),
('IT','it',3),
('Operations','ops',4);

INSERT INTO applications (name,url,is_global,sort_order) VALUES
('Gmail','https://mail.google.com/',1,1),
('Freshdesk','https://example.freshdesk.com/',1,2),
('Cliq','https://cliq.zoho.com/',1,3),
('Atlas','https://atlas.example.com/',0,4),
('Google Admin','https://admin.google.com/',0,5),
('Meet','https://meet.google.com/',1,6),
('Calendar','https://calendar.google.com/',1,7),
('HubSpot','https://app.hubspot.com/',0,8),
('Drive','https://drive.google.com/',1,9),
('Outlook','https://outlook.office.com/',0,10),
('Teams','https://teams.microsoft.com/',0,11),
('Sheets','https://sheets.google.com/',1,12),
('BasketHunt VPN','https://vpn.baskethunt.com/',0,13);

-- Map applications to departments
-- Retrieve ids with subqueries
INSERT INTO application_department (application_id, department_id)
SELECT a.id, d.id FROM applications a JOIN departments d
WHERE a.name='Atlas' AND d.slug IN ('hr','ops');
INSERT INTO application_department (application_id, department_id)
SELECT a.id, d.id FROM applications a JOIN departments d
WHERE a.name='Google Admin' AND d.slug='it';
INSERT INTO application_department (application_id, department_id)
SELECT a.id, d.id FROM applications a JOIN departments d
WHERE a.name='HubSpot' AND d.slug='sales';
INSERT INTO application_department (application_id, department_id)
SELECT a.id, d.id FROM applications a JOIN departments d
WHERE a.name='Outlook' AND d.slug IN ('sales','hr');
INSERT INTO application_department (application_id, department_id)
SELECT a.id, d.id FROM applications a JOIN departments d
WHERE a.name='Teams' AND d.slug='sales';
INSERT INTO application_department (application_id, department_id)
SELECT a.id, d.id FROM applications a JOIN departments d
WHERE a.name='BasketHunt VPN' AND d.slug IN ('it','ops');
