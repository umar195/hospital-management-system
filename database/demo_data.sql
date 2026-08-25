-- =============================================================
-- Hospital Management System - DEMO DATA
-- =============================================================
-- Optional sample data for testing and demonstrations.
-- Import AFTER database/hospital.sql, into an otherwise empty
-- installation (it uses fixed primary keys).
--
--   mysql -u root -p hospital_db < database/demo_data.sql
--
-- or use the "Load demo data" option of install.php.
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Doctors
-- -------------------------------------------------------------
INSERT INTO doctors (id, name, specialty, phone, email, license_number, address, notes, is_active) VALUES
(1, 'Dr. Ayesha Khan', 'Pathology', '0300-1234567', 'ayesha.khan@example.com', 'PMC-11021', 'City Care Hospital, Lahore', 'Consultant pathologist and lab director', 1),
(2, 'Dr. Imran Sheikh', 'General Physician', '0301-2345678', 'imran.sheikh@example.com', 'PMC-22045', 'OPD Block A', 'Morning OPD 9am - 2pm', 1),
(3, 'Dr. Sana Riaz', 'Gynaecology', '0302-3456789', 'sana.riaz@example.com', 'PMC-33087', 'OPD Block B', 'Evening clinic', 1),
(4, 'Dr. Bilal Ahmed', 'Cardiology', '0303-4567890', 'bilal.ahmed@example.com', 'PMC-44112', 'Cardiac Care Unit', 'ECG and echo available', 1),
(5, 'Dr. Nadia Aslam', 'Paediatrics', '0304-5678901', 'nadia.aslam@example.com', 'PMC-55190', 'Children Ward', 'Vaccination clinic on Fridays', 1),
(6, 'Dr. Kamran Tariq', 'Orthopaedics', '0305-6789012', 'kamran.tariq@example.com', 'PMC-66203', 'Surgical Block', 'Operates on Tuesdays', 1);

-- -------------------------------------------------------------
-- Patients
-- -------------------------------------------------------------
INSERT INTO patients (id, patient_id, full_name, father_husband_name, gender, date_of_birth, age, cnic, phone, whatsapp, email, address, emergency_contact, blood_group, referring_doctor, medical_notes, registration_date) VALUES
(1,  CONCAT('PAT-', YEAR(CURDATE()), '-00001'), 'Muhammad Usman',   'Abdul Rasheed', 'Male',   '1985-04-12', 40, '35201-1234567-1', '0300-1111111', '0300-1111111', 'usman@example.com', 'House 12, Model Town, Lahore', 'Sadia Usman 0300-1111112', 'B+',  'Dr. Imran Sheikh', 'Known diabetic since 2015', DATE_SUB(CURDATE(), INTERVAL 120 DAY)),
(2,  CONCAT('PAT-', YEAR(CURDATE()), '-00002'), 'Fatima Bibi',      'Ghulam Nabi',   'Female', '1992-09-03', 33, '35202-2234567-8', '0300-2222222', '0300-2222222', NULL, 'Street 5, Johar Town, Lahore', 'Ali Raza 0300-2222223', 'O+',  'Dr. Sana Riaz', 'Anaemia under treatment', DATE_SUB(CURDATE(), INTERVAL 90 DAY)),
(3,  CONCAT('PAT-', YEAR(CURDATE()), '-00003'), 'Ali Raza',         'Muhammad Aslam','Male',   '1978-01-25', 47, '35203-3234567-3', '0300-3333333', NULL, 'ali.raza@example.com', 'Gulberg III, Lahore', 'Hina Ali 0300-3333334', 'A+',  'Dr. Bilal Ahmed', 'Hypertension, on medication', DATE_SUB(CURDATE(), INTERVAL 75 DAY)),
(4,  CONCAT('PAT-', YEAR(CURDATE()), '-00004'), 'Ayesha Siddiqui',  'Tariq Mahmood', 'Female', '2001-06-18', 24, '35204-4234567-6', '0300-4444444', '0300-4444444', NULL, 'DHA Phase 4, Lahore', 'Tariq Mahmood 0300-4444445', 'AB+', 'Dr. Imran Sheikh', NULL, DATE_SUB(CURDATE(), INTERVAL 60 DAY)),
(5,  CONCAT('PAT-', YEAR(CURDATE()), '-00005'), 'Hamza Iqbal',      'Iqbal Hussain', 'Male',   '2016-11-02', 9,  NULL, '0300-5555555', NULL, NULL, 'Township, Lahore', 'Iqbal Hussain 0300-5555556', 'O-',  'Dr. Nadia Aslam', 'Frequent throat infections', DATE_SUB(CURDATE(), INTERVAL 45 DAY)),
(6,  CONCAT('PAT-', YEAR(CURDATE()), '-00006'), 'Rukhsana Parveen', 'Abdul Sattar',  'Female', '1965-02-14', 60, '35206-6234567-2', '0300-6666666', '0300-6666666', NULL, 'Samanabad, Lahore', 'Nasir Sattar 0300-6666667', 'B-',  'Dr. Imran Sheikh', 'Thyroid follow-up', DATE_SUB(CURDATE(), INTERVAL 30 DAY)),
(7,  CONCAT('PAT-', YEAR(CURDATE()), '-00007'), 'Bilal Hussain',    'Sajid Hussain', 'Male',   '1995-07-30', 30, '35207-7234567-9', '0300-7777777', NULL, NULL, 'Faisal Town, Lahore', 'Sajid Hussain 0300-7777778', 'A-',  'Dr. Kamran Tariq', 'Post-fracture follow-up', DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(8,  CONCAT('PAT-', YEAR(CURDATE()), '-00008'), 'Zainab Noor',      'Noor Muhammad', 'Female', '1988-12-09', 37, '35208-8234567-4', '0300-8888888', '0300-8888888', 'zainab@example.com', 'Wapda Town, Lahore', 'Noor Muhammad 0300-8888889', 'O+',  'Dr. Sana Riaz', 'Pregnancy - second trimester', DATE_SUB(CURDATE(), INTERVAL 14 DAY)),
(9,  CONCAT('PAT-', YEAR(CURDATE()), '-00009'), 'Shahid Mehmood',   'Mehmood Alam',  'Male',   '1972-03-21', 53, '35209-9234567-7', '0300-9999999', NULL, NULL, 'Iqbal Town, Lahore', 'Sana Shahid 0300-9999998', 'B+',  'Dr. Bilal Ahmed', 'Cardiac screening', DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(10, CONCAT('PAT-', YEAR(CURDATE()), '-00010'), 'Maryam Javed',     'Javed Akhtar',  'Female', '1999-05-05', 26, '35210-1034567-5', '0301-1212121', '0301-1212121', NULL, 'Garden Town, Lahore', 'Javed Akhtar 0301-1212122', 'A+',  'Dr. Imran Sheikh', NULL, DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(11, CONCAT('PAT-', YEAR(CURDATE()), '-00011'), 'Tahir Mahmood',    'Mahmood Sharif','Male',   '1990-10-10', 35, '35211-1134567-1', '0301-1313131', NULL, NULL, 'Shadman, Lahore', 'Sara Tahir 0301-1313132', 'O+',  NULL, NULL, CURDATE()),
(12, CONCAT('PAT-', YEAR(CURDATE()), '-00012'), 'Sadia Kanwal',     'Rashid Ali',    'Female', '1996-08-22', 29, '35212-1234561-6', '0301-1414141', '0301-1414141', NULL, 'Cantt, Lahore', 'Rashid Ali 0301-1414142', 'AB-', 'Dr. Sana Riaz', NULL, CURDATE());

-- -------------------------------------------------------------
-- Suppliers
-- -------------------------------------------------------------
INSERT INTO suppliers (id, name, contact_person, phone, email, address, notes) VALUES
(1, 'Pak Diagnostics Supplies', 'Mr. Faisal Nadeem', '042-35700111', 'sales@pakdiagnostics.example', 'Hall Road, Lahore', 'Reagents and lab consumables'),
(2, 'MedEquip International', 'Ms. Rabia Khalid', '042-35800222', 'info@medequip.example', 'Ferozepur Road, Lahore', 'Analyzers and maintenance contracts'),
(3, 'City Pharma Distributors', 'Mr. Adnan Butt', '042-35900333', 'orders@citypharma.example', 'Ravi Road, Lahore', 'Medicines and disposables');

-- -------------------------------------------------------------
-- Inventory
-- -------------------------------------------------------------
INSERT INTO inventory_items (id, item_code, name, category, supplier_id, purchase_price, selling_price, quantity, minimum_stock, expiry_date, batch_number, notes) VALUES
(1, 'ITM-00001', 'EDTA Vacutainer Tubes (100 pcs)', 'Consumables', 1, 1800.00, 2400.00, 42, 10, DATE_ADD(CURDATE(), INTERVAL 400 DAY), 'BT-2201', 'Purple top tubes for CBC'),
(2, 'ITM-00002', 'Disposable Syringes 5cc (100 pcs)', 'Consumables', 3, 900.00, 1300.00, 8, 15, DATE_ADD(CURDATE(), INTERVAL 700 DAY), 'SY-4410', 'Low stock - reorder'),
(3, 'ITM-00003', 'Glucose Reagent Kit', 'Reagents', 1, 4200.00, 5200.00, 12, 4, DATE_ADD(CURDATE(), INTERVAL 180 DAY), 'GL-9087', 'Store at 2-8 C'),
(4, 'ITM-00004', 'Urine Strips 10 Parameter (100 strips)', 'Reagents', 1, 2200.00, 3000.00, 5, 5, DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'US-3321', 'Expiring soon'),
(5, 'ITM-00005', 'Examination Gloves Medium (100 pcs)', 'Consumables', 3, 700.00, 1000.00, 60, 20, DATE_ADD(CURDATE(), INTERVAL 900 DAY), 'GV-7788', NULL),
(6, 'ITM-00006', 'Paracetamol 500mg Tablets (200)', 'Medicines', 3, 500.00, 800.00, 24, 10, DATE_ADD(CURDATE(), INTERVAL 500 DAY), 'PC-1123', 'OPD dispensing'),
(7, 'ITM-00007', 'Alcohol Swabs (200 pcs)', 'Consumables', 1, 350.00, 600.00, 3, 10, DATE_ADD(CURDATE(), INTERVAL 300 DAY), 'AS-5566', 'Low stock');

INSERT INTO inventory_transactions (item_id, type, quantity, notes, transaction_date) VALUES
(1, 'Stock In', 50, 'Opening stock', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(1, 'Stock Out', 8, 'Issued to phlebotomy', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 'Stock In', 20, 'Opening stock', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 'Stock Out', 12, 'Issued to OPD', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 'Stock In', 12, 'Monthly reagent purchase', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 'Stock In', 10, 'Opening stock', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(4, 'Stock Out', 5, 'Consumed in lab', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'Stock In', 60, 'Bulk purchase', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(6, 'Stock In', 24, 'Pharmacy stock', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(7, 'Stock In', 10, 'Opening stock', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(7, 'Stock Out', 7, 'Sampling room', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- -------------------------------------------------------------
-- Appointments
-- -------------------------------------------------------------
INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, purpose, status, notes) VALUES
(1, 2, CURDATE(), '10:00:00', 'Diabetes follow-up', 'Scheduled', 'Bring previous sugar reports'),
(2, 3, CURDATE(), '11:30:00', 'Anaemia review', 'Arrived', NULL),
(3, 4, CURDATE(), '12:15:00', 'Blood pressure check', 'Completed', 'ECG advised'),
(8, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:45:00', 'Antenatal visit', 'Scheduled', 'Second trimester scan'),
(5, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '16:00:00', 'Recurrent sore throat', 'Scheduled', NULL),
(9, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '17:30:00', 'Cardiac screening', 'Completed', 'Lipid profile ordered'),
(7, 6, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '14:00:00', 'Fracture follow-up', 'No Show', NULL);

-- -------------------------------------------------------------
-- Visits
-- -------------------------------------------------------------
INSERT INTO visits (id, visit_number, patient_id, visit_date, visit_type, doctor_id, symptoms, diagnosis, notes, total_charges, payment_status) VALUES
(1, CONCAT('VIS-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 30 DAY), '%Y%m%d'), '-0001'), 1, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Walk-In', 2, 'Increased thirst, fatigue', 'Uncontrolled diabetes', 'Lab work-up advised', 1850.00, 'Paid'),
(2, CONCAT('VIS-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 2, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Appointment', 3, 'Weakness, pallor', 'Iron deficiency anaemia', NULL, 800.00, 'Paid'),
(3, CONCAT('VIS-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), 9, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Appointment', 4, 'Chest discomfort on exertion', 'Dyslipidaemia - to rule out IHD', 'Advised diet control', 1300.00, 'Partial'),
(4, CONCAT('VIS-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0001'), 10, NOW(), 'Walk-In', 2, 'Fever for 3 days', 'Viral fever - rule out typhoid', NULL, 950.00, 'Paid'),
(5, CONCAT('VIS-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0002'), 6, NOW(), 'Follow-Up', 2, 'Weight gain, lethargy', 'Hypothyroidism follow-up', NULL, 1800.00, 'Pending');

-- -------------------------------------------------------------
-- Test orders
-- -------------------------------------------------------------
INSERT INTO test_orders (id, order_number, patient_id, visit_id, order_date, referring_doctor, total_amount, discount, net_amount, paid_amount, remaining_amount, payment_method, notes) VALUES
(1, CONCAT('ORD-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 30 DAY), '%Y%m%d'), '-0001'), 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Dr. Imran Sheikh', 1850.00, 0.00, 1850.00, 1850.00, 0.00, 'Cash', 'Diabetic work-up'),
(2, CONCAT('ORD-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 2, 2, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Dr. Sana Riaz', 800.00, 0.00, 800.00, 800.00, 0.00, 'Cash', NULL),
(3, CONCAT('ORD-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), 9, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Dr. Bilal Ahmed', 1300.00, 100.00, 1200.00, 700.00, 500.00, 'Card', 'Cardiac screening panel'),
(4, CONCAT('ORD-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0001'), 10, 4, NOW(), 'Dr. Imran Sheikh', 950.00, 0.00, 950.00, 950.00, 0.00, 'Cash', 'Fever profile'),
(5, CONCAT('ORD-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0002'), 6, 5, NOW(), 'Dr. Imran Sheikh', 1800.00, 200.00, 1600.00, 0.00, 1600.00, 'Cash', 'Thyroid follow-up');

INSERT INTO test_order_items (id, order_id, test_id, price, status) VALUES
(1, 1, 1, 600.00, 'Completed'),
(2, 1, 5, 250.00, 'Completed'),
(3, 1, 7, 1200.00, 'Completed'),
(4, 2, 2, 200.00, 'Completed'),
(5, 2, 3, 200.00, 'Completed'),
(6, 2, 11, 350.00, 'Completed'),
(7, 3, 10, 1300.00, 'Processing'),
(8, 4, 19, 600.00, 'Sample Collected'),
(9, 4, 3, 200.00, 'Completed'),
(10, 4, 6, 250.00, 'Completed'),
(11, 5, 13, 1800.00, 'Ordered');

-- -------------------------------------------------------------
-- Test results (CBC parameters + single value tests)
-- -------------------------------------------------------------
INSERT INTO test_results (order_item_id, parameter_id, parameter_name, result_value, unit, reference_range, flag, notes) VALUES
(1, 1,  'Hemoglobin',              '11.2', 'g/dL',    '13.5-17.5', 'Low',    NULL),
(1, 2,  'Total Leukocyte Count',   '9.4',  '10^3/uL', '4-11',      'Normal', NULL),
(1, 3,  'Red Blood Cell Count',    '4.9',  '10^6/uL', '4.7-6.1',   'Normal', NULL),
(1, 4,  'Platelet Count',          '265',  '10^3/uL', '150-450',   'Normal', NULL),
(1, 5,  'Hematocrit',              '38',   '%',       '41-53',     'Low',    NULL),
(1, 6,  'MCV',                     '84',   'fL',      '80-100',    'Normal', NULL),
(1, 7,  'MCH',                     '28',   'pg',      '27-33',     'Normal', NULL),
(1, 8,  'MCHC',                    '33',   'g/dL',    '32-36',     'Normal', NULL),
(1, 9,  'Neutrophils',             '62',   '%',       '40-75',     'Normal', NULL),
(1, 10, 'Lymphocytes',             '30',   '%',       '20-45',     'Normal', NULL),
(1, 11, 'Monocytes',               '5',    '%',       '2-10',      'Normal', NULL),
(1, 12, 'Eosinophils',             '2',    '%',       '1-6',       'Normal', NULL),
(1, 13, 'Basophils',               '1',    '%',       '0-1',       'Normal', NULL),
(2, NULL, 'Blood Sugar Fasting',   '168',  'mg/dL',   '70-100',    'High',   'Advise repeat after diet control'),
(3, NULL, 'HbA1c',                 '8.4',  '%',       '4-5.6',     'High',   'Poor glycaemic control'),
(4, NULL, 'Hemoglobin (Hb)',       '9.8',  'g/dL',    '12-15.5',   'Low',    'Iron studies suggested'),
(5, NULL, 'ESR',                   '34',   'mm/hr',   '0-20',      'High',   NULL),
(6, NULL, 'Serum Creatinine',      '0.8',  'mg/dL',   '0.6-1.1',   'Normal', NULL),
(9, NULL, 'ESR',                   '18',   'mm/hr',   '0-20',      'Normal', NULL),
(10, NULL, 'Blood Sugar Random',   '112',  'mg/dL',   '70-140',    'Normal', NULL);

-- -------------------------------------------------------------
-- Reports
-- -------------------------------------------------------------
INSERT INTO reports (id, report_number, order_id, patient_id, generated_at, authorized_by, remarks, status) VALUES
(1, CONCAT('RPT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 29 DAY), '%Y%m%d'), '-0001'), 1, 1, DATE_SUB(NOW(), INTERVAL 29 DAY), 'Dr. Ayesha Khan (MBBS, FCPS Pathology)', 'Anaemia with poorly controlled diabetes. Clinical correlation advised.', 'Final'),
(2, CONCAT('RPT-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '%Y%m%d'), '-0001'), 2, 2, DATE_SUB(NOW(), INTERVAL 11 DAY), 'Dr. Ayesha Khan (MBBS, FCPS Pathology)', 'Microcytic hypochromic picture consistent with iron deficiency.', 'Final'),
(3, CONCAT('RPT-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0001'), 4, 10, NOW(), 'Dr. Ayesha Khan (MBBS, FCPS Pathology)', 'Widal test pending. Remaining parameters within normal limits.', 'Draft');

-- -------------------------------------------------------------
-- Invoices, items and payments
-- -------------------------------------------------------------
INSERT INTO invoices (id, invoice_number, patient_id, visit_id, invoice_date, subtotal, discount, total, paid_amount, balance, payment_method, payment_status, notes) VALUES
(1, CONCAT('INV-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 30 DAY), '%Y%m%d'), '-0001'), 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), 1850.00, 0.00, 1850.00, 1850.00, 0.00, 'Cash', 'Paid', 'Test order ORD (diabetic work-up)'),
(2, CONCAT('INV-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m%d'), '-0001'), 2, 2, DATE_SUB(NOW(), INTERVAL 12 DAY), 800.00, 0.00, 800.00, 800.00, 0.00, 'Cash', 'Paid', 'Anaemia panel'),
(3, CONCAT('INV-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '%Y%m%d'), '-0001'), 9, 3, DATE_SUB(NOW(), INTERVAL 3 DAY), 1300.00, 100.00, 1200.00, 700.00, 500.00, 'Card', 'Partial', 'Lipid profile'),
(4, CONCAT('INV-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0001'), 10, 4, NOW(), 950.00, 0.00, 950.00, 950.00, 0.00, 'Cash', 'Paid', 'Fever profile'),
(5, CONCAT('INV-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0002'), 6, 5, NOW(), 1800.00, 200.00, 1600.00, 0.00, 1600.00, 'Cash', 'Pending', 'Thyroid profile');

INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) VALUES
(1, 'Complete Blood Count (CBC)', 1, 600.00, 600.00),
(1, 'Blood Sugar Fasting', 1, 250.00, 250.00),
(1, 'HbA1c', 1, 1200.00, 1200.00),
(2, 'Hemoglobin (Hb)', 1, 200.00, 200.00),
(2, 'ESR', 1, 200.00, 200.00),
(2, 'Serum Creatinine', 1, 350.00, 350.00),
(2, 'Consultation charges', 1, 50.00, 50.00),
(3, 'Lipid Profile', 1, 1300.00, 1300.00),
(4, 'Widal Test', 1, 600.00, 600.00),
(4, 'ESR', 1, 200.00, 200.00),
(4, 'Blood Sugar Random', 1, 250.00, 250.00),
(5, 'Thyroid Profile (T3, T4, TSH)', 1, 1800.00, 1800.00);

INSERT INTO payments (patient_id, invoice_id, order_id, amount, payment_method, payment_date, notes) VALUES
(1, 1, 1, 1850.00, 'Cash', DATE_SUB(NOW(), INTERVAL 30 DAY), 'Full payment at reception'),
(2, 2, 2, 800.00, 'Cash', DATE_SUB(NOW(), INTERVAL 12 DAY), NULL),
(9, 3, 3, 700.00, 'Card', DATE_SUB(NOW(), INTERVAL 3 DAY), 'Advance payment'),
(10, 4, 4, 950.00, 'Cash', NOW(), 'Walk-in payment');

-- -------------------------------------------------------------
-- Prescriptions
-- -------------------------------------------------------------
INSERT INTO prescriptions (id, patient_id, visit_id, prescription_date, notes) VALUES
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Low sugar diet, 30 minutes walk daily. Review after 2 weeks with fasting sugar.'),
(2, 2, 2, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Iron rich diet. Repeat CBC after one month.'),
(3, 10, 4, CURDATE(), 'Plenty of fluids. Return immediately if fever exceeds 103 F.');

INSERT INTO prescription_items (prescription_id, medicine, dosage, frequency, duration, instructions) VALUES
(1, 'Metformin 500mg', '1 tab', 'BD', '30 days', 'After meals'),
(1, 'Multivitamin', '1 tab', 'OD', '30 days', 'After breakfast'),
(2, 'Ferrous Sulphate 200mg', '1 tab', 'OD', '60 days', 'With orange juice, avoid tea'),
(2, 'Folic Acid 5mg', '1 tab', 'OD', '30 days', NULL),
(3, 'Paracetamol 500mg', '1 tab', 'TDS', '5 days', 'After meals'),
(3, 'ORS Sachet', '1 sachet', 'PRN', '3 days', 'Dissolve in 1 litre water');

-- -------------------------------------------------------------
-- Expenses
-- -------------------------------------------------------------
INSERT INTO expenses (expense_id, expense_date, category, description, amount, payment_method, notes) VALUES
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 60 DAY), '%Y%m'), '-0001'), DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Rent', 'Monthly building rent', 120000.00, 'Bank Transfer', NULL),
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 58 DAY), '%Y%m'), '-0002'), DATE_SUB(CURDATE(), INTERVAL 58 DAY), 'Salaries', 'Staff salaries', 260000.00, 'Bank Transfer', '6 employees'),
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 45 DAY), '%Y%m'), '-0003'), DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'Laboratory Supplies', 'Reagent purchase', 48000.00, 'Cash', 'Glucose and lipid kits'),
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 30 DAY), '%Y%m'), '-0004'), DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Electricity', 'Electricity bill', 34500.00, 'Cash', NULL),
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 20 DAY), '%Y%m'), '-0005'), DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Maintenance', 'Analyzer servicing', 15000.00, 'Cash', 'Annual maintenance visit'),
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '%Y%m'), '-0006'), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Medicines', 'OPD medicine stock', 22000.00, 'Cash', NULL),
(CONCAT('EXP-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '%Y%m'), '-0007'), DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Equipment', 'New centrifuge', 85000.00, 'Bank Transfer', 'Replaced old unit'),
(CONCAT('EXP-', DATE_FORMAT(CURDATE(), '%Y%m'), '-0008'), CURDATE(), 'Other', 'Stationery and printing', 6500.00, 'Cash', 'Report paper and files');

-- -------------------------------------------------------------
-- Activity log sample
-- -------------------------------------------------------------
INSERT INTO activity_logs (action, description, related_table, related_id) VALUES
('Demo data loaded', 'Sample patients, orders, invoices and expenses inserted', 'system', NULL);

SET FOREIGN_KEY_CHECKS = 1;
