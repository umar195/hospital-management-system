-- =============================================================
-- Hospital Management System - complete database schema
-- MySQL / MariaDB (utf8mb4)
--
-- Import with:  mysql -u root -p < database/hospital.sql
-- or use the built in installer:  http://localhost/hospital-management-system/install.php
-- =============================================================

CREATE DATABASE IF NOT EXISTS hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_db;

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Users (single admin account by default)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Settings (key/value)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Patients
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id VARCHAR(20) UNIQUE NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  father_husband_name VARCHAR(100),
  gender ENUM('Male','Female','Other') NOT NULL,
  date_of_birth DATE,
  age INT,
  cnic VARCHAR(20),
  phone VARCHAR(20),
  whatsapp VARCHAR(20),
  email VARCHAR(100),
  address TEXT,
  emergency_contact VARCHAR(100),
  blood_group VARCHAR(5),
  referring_doctor VARCHAR(100),
  medical_notes TEXT,
  registration_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_patients_name (full_name),
  INDEX idx_patients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Doctors
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  specialty VARCHAR(100),
  phone VARCHAR(20),
  email VARCHAR(100),
  license_number VARCHAR(50),
  address TEXT,
  notes TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Appointments
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  purpose VARCHAR(255),
  status ENUM('Scheduled','Arrived','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_appt_date (appointment_date),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Visits
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_number VARCHAR(20) UNIQUE NOT NULL,
  patient_id INT NOT NULL,
  visit_date DATETIME NOT NULL,
  visit_type ENUM('Walk-In','Appointment','Emergency','Follow-Up') DEFAULT 'Walk-In',
  doctor_id INT,
  symptoms TEXT,
  diagnosis TEXT,
  notes TEXT,
  total_charges DECIMAL(10,2) DEFAULT 0,
  payment_status ENUM('Pending','Partial','Paid') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Test categories
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS test_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  sort_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tests
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_code VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(150) NOT NULL,
  category_id INT,
  price DECIMAL(10,2) DEFAULT 0,
  sample_type VARCHAR(50),
  unit VARCHAR(30),
  normal_range VARCHAR(100),
  male_range VARCHAR(100),
  female_range VARCHAR(100),
  child_range VARCHAR(100),
  description TEXT,
  instructions TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES test_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Test parameters (multi parameter tests such as CBC)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS test_parameters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  unit VARCHAR(30),
  normal_range VARCHAR(100),
  male_range VARCHAR(100),
  female_range VARCHAR(100),
  child_range VARCHAR(100),
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Test orders
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS test_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(20) UNIQUE NOT NULL,
  patient_id INT NOT NULL,
  visit_id INT,
  order_date DATETIME NOT NULL,
  referring_doctor VARCHAR(100),
  total_amount DECIMAL(10,2) DEFAULT 0,
  discount DECIMAL(10,2) DEFAULT 0,
  net_amount DECIMAL(10,2) DEFAULT 0,
  paid_amount DECIMAL(10,2) DEFAULT 0,
  remaining_amount DECIMAL(10,2) DEFAULT 0,
  payment_method ENUM('Cash','Card','Bank Transfer','Other') DEFAULT 'Cash',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_date (order_date),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Test order items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS test_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  test_id INT NOT NULL,
  price DECIMAL(10,2) DEFAULT 0,
  status ENUM('Ordered','Sample Collected','Processing','Pending','Completed','Delivered') DEFAULT 'Ordered',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES test_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (test_id) REFERENCES tests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Test results
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS test_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT NOT NULL,
  parameter_id INT,
  parameter_name VARCHAR(100),
  result_value VARCHAR(255),
  unit VARCHAR(30),
  reference_range VARCHAR(100),
  flag ENUM('Normal','Low','High','') DEFAULT '',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_item_id) REFERENCES test_order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (parameter_id) REFERENCES test_parameters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Reports
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_number VARCHAR(20) UNIQUE NOT NULL,
  order_id INT NOT NULL,
  patient_id INT NOT NULL,
  generated_at DATETIME NOT NULL,
  authorized_by VARCHAR(100),
  remarks TEXT,
  status ENUM('Draft','Final') DEFAULT 'Final',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES test_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Invoices
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(20) UNIQUE NOT NULL,
  patient_id INT NOT NULL,
  visit_id INT,
  invoice_date DATETIME NOT NULL,
  subtotal DECIMAL(10,2) DEFAULT 0,
  discount DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) DEFAULT 0,
  paid_amount DECIMAL(10,2) DEFAULT 0,
  balance DECIMAL(10,2) DEFAULT 0,
  payment_method ENUM('Cash','Card','Bank Transfer','Other') DEFAULT 'Cash',
  payment_status ENUM('Pending','Partial','Paid') DEFAULT 'Pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_invoice_date (invoice_date),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Invoice items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 1,
  unit_price DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Payments
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  invoice_id INT,
  order_id INT,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('Cash','Card','Bank Transfer','Other') DEFAULT 'Cash',
  payment_date DATETIME NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payment_date (payment_date),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Expenses
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_id VARCHAR(20) UNIQUE NOT NULL,
  expense_date DATE NOT NULL,
  category ENUM('Rent','Electricity','Salaries','Laboratory Supplies','Medicines','Maintenance','Equipment','Other') NOT NULL,
  description TEXT,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('Cash','Card','Bank Transfer','Other') DEFAULT 'Cash',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expense_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Prescriptions
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  visit_id INT,
  prescription_date DATE NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Prescription items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prescription_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  medicine VARCHAR(100) NOT NULL,
  dosage VARCHAR(50),
  frequency VARCHAR(50),
  duration VARCHAR(50),
  instructions TEXT,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Suppliers
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  contact_person VARCHAR(100),
  phone VARCHAR(20),
  email VARCHAR(100),
  address TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Inventory items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(30) UNIQUE NOT NULL,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(100),
  supplier_id INT,
  purchase_price DECIMAL(10,2) DEFAULT 0,
  selling_price DECIMAL(10,2) DEFAULT 0,
  quantity INT DEFAULT 0,
  minimum_stock INT DEFAULT 0,
  expiry_date DATE,
  batch_number VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Inventory transactions
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  type ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
  quantity INT NOT NULL,
  notes TEXT,
  transaction_date DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Activity logs
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  related_table VARCHAR(50),
  related_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- Default data
-- =============================================================

-- Default administrator (username: admin / password: admin123)
INSERT INTO users (username, password, full_name, email) VALUES
('admin', '$2y$10$wunwb40OCyDHEoFersDihOq8UCohMl2NFrD/PD7FmS6QTLttvT9Wq', 'System Administrator', 'admin@hospital.local')
ON DUPLICATE KEY UPDATE username = username;

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('hospital_name', 'City Care Hospital'),
('hospital_tagline', 'Hospital & Diagnostic Center'),
('hospital_address', '123 Mall Road, Lahore, Pakistan'),
('hospital_phone', '042-111-222-333'),
('hospital_email', 'info@citycarehospital.local'),
('hospital_website', 'www.citycarehospital.local'),
('hospital_logo', ''),
('currency_symbol', 'Rs.'),
('country_code', '92'),
('date_format', 'd M Y'),
('time_format', 'h:i A'),
('patient_prefix', 'PAT'),
('invoice_prefix', 'INV'),
('order_prefix', 'ORD'),
('report_prefix', 'RPT'),
('report_header', 'Laboratory Investigation Report'),
('report_footer', 'Thank you for trusting City Care Hospital.'),
('report_disclaimer', 'Laboratory results should always be correlated clinically. In case of any unexpected result please contact the laboratory immediately.'),
('authorized_by', 'Dr. Ayesha Khan (MBBS, FCPS Pathology)'),
('invoice_footer', 'Please keep this receipt for collecting your reports.'),
('default_discount', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Test categories
INSERT INTO test_categories (id, name, description, sort_order, is_active) VALUES
(1, 'Hematology', 'Blood cell counts and related studies', 1, 1),
(2, 'Biochemistry', 'Clinical chemistry and metabolic panels', 2, 1),
(3, 'Serology', 'Antibody and antigen detection tests', 3, 1),
(4, 'Microbiology', 'Cultures, smears and sensitivity', 4, 1),
(5, 'Clinical Pathology', 'Urine, stool and body fluid analysis', 5, 1),
(6, 'Hormones', 'Endocrine and hormonal assays', 6, 1),
(7, 'Radiology', 'X-Ray and ultrasound services', 7, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Tests
INSERT INTO tests (id, test_code, name, category_id, price, sample_type, unit, normal_range, male_range, female_range, child_range, description, instructions, is_active) VALUES
(1,  'T-0001', 'Complete Blood Count (CBC)', 1, 600.00,  'Whole Blood (EDTA)', '', '', '', '', '', 'Full blood picture with differential count', 'No fasting required', 1),
(2,  'T-0002', 'Hemoglobin (Hb)', 1, 200.00, 'Whole Blood (EDTA)', 'g/dL', '12-16', '13.5-17.5', '12-15.5', '11-14', 'Hemoglobin estimation', 'No preparation required', 1),
(3,  'T-0003', 'ESR', 1, 200.00, 'Whole Blood (EDTA)', 'mm/hr', '0-20', '0-15', '0-20', '0-10', 'Erythrocyte sedimentation rate', '', 1),
(4,  'T-0004', 'Blood Group & Rh Factor', 1, 300.00, 'Whole Blood (EDTA)', '', '', '', '', '', 'ABO grouping and Rh typing', '', 1),
(5,  'T-0005', 'Blood Sugar Fasting', 2, 250.00, 'Serum / Plasma (Fluoride)', 'mg/dL', '70-100', '', '', '60-100', 'Fasting plasma glucose', 'Fast for 8-12 hours', 1),
(6,  'T-0006', 'Blood Sugar Random', 2, 250.00, 'Serum / Plasma (Fluoride)', 'mg/dL', '70-140', '', '', '', 'Random plasma glucose', '', 1),
(7,  'T-0007', 'HbA1c', 2, 1200.00, 'Whole Blood (EDTA)', '%', '4-5.6', '', '', '', 'Glycated hemoglobin - 3 month sugar control', '', 1),
(8,  'T-0008', 'Liver Function Test (LFT)', 2, 1500.00, 'Serum', '', '', '', '', '', 'Complete liver profile', 'Fast for 8 hours preferred', 1),
(9,  'T-0009', 'Renal Function Test (RFT)', 2, 1400.00, 'Serum', '', '', '', '', '', 'Kidney function profile', '', 1),
(10, 'T-0010', 'Lipid Profile', 2, 1300.00, 'Serum', '', '', '', '', '', 'Cholesterol and triglyceride panel', 'Fast for 12 hours', 1),
(11, 'T-0011', 'Serum Creatinine', 2, 350.00, 'Serum', 'mg/dL', '0.6-1.3', '0.7-1.3', '0.6-1.1', '0.3-0.7', 'Kidney function marker', '', 1),
(12, 'T-0012', 'Serum Uric Acid', 2, 350.00, 'Serum', 'mg/dL', '3.5-7.2', '3.5-7.2', '2.6-6.0', '2-5.5', 'Uric acid level', '', 1),
(13, 'T-0013', 'Thyroid Profile (T3, T4, TSH)', 6, 1800.00, 'Serum', '', '', '', '', '', 'Complete thyroid assessment', '', 1),
(14, 'T-0014', 'TSH', 6, 900.00, 'Serum', 'uIU/mL', '0.4-4.2', '', '', '0.7-6.4', 'Thyroid stimulating hormone', '', 1),
(15, 'T-0015', 'Urine Routine Examination', 5, 350.00, 'Urine', '', '', '', '', '', 'Physical, chemical and microscopic urine analysis', 'Mid stream sample in sterile container', 1),
(16, 'T-0016', 'Stool Routine Examination', 5, 350.00, 'Stool', '', '', '', '', '', 'Stool for routine examination', '', 1),
(17, 'T-0017', 'Hepatitis B Surface Antigen (HBsAg)', 3, 700.00, 'Serum', '', 'Non Reactive', '', '', '', 'Screening for hepatitis B', '', 1),
(18, 'T-0018', 'Anti HCV', 3, 900.00, 'Serum', '', 'Non Reactive', '', '', '', 'Screening for hepatitis C', '', 1),
(19, 'T-0019', 'Widal Test', 3, 500.00, 'Serum', '', '', '', '', '', 'Typhoid serology', '', 1),
(20, 'T-0020', 'Dengue NS1 Antigen', 3, 1500.00, 'Serum', '', 'Negative', '', '', '', 'Dengue early antigen detection', '', 1),
(21, 'T-0021', 'Urine Culture & Sensitivity', 4, 1600.00, 'Urine', '', 'No growth', '', '', '', 'Culture with antibiotic sensitivity', 'Sterile mid stream sample', 1),
(22, 'T-0022', 'Blood Culture', 4, 2000.00, 'Whole Blood', '', 'No growth', '', '', '', 'Blood culture and sensitivity', '', 1),
(23, 'T-0023', 'Chest X-Ray (PA View)', 7, 1200.00, 'Imaging', '', '', '', '', '', 'Plain radiograph of chest', '', 1),
(24, 'T-0024', 'Ultrasound Abdomen', 7, 2500.00, 'Imaging', '', '', '', '', '', 'Whole abdomen ultrasonography', 'Fast for 6 hours, full bladder', 1),
(25, 'T-0025', 'Vitamin D (25-OH)', 6, 3500.00, 'Serum', 'ng/mL', '30-100', '', '', '', 'Vitamin D level', '', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Test parameters for multi parameter panels
INSERT INTO test_parameters (test_id, name, unit, normal_range, male_range, female_range, child_range, sort_order) VALUES
(1, 'Hemoglobin', 'g/dL', '12-16', '13.5-17.5', '12-15.5', '11-14', 1),
(1, 'Total Leukocyte Count', '10^3/uL', '4-11', '4-11', '4-11', '5-15', 2),
(1, 'Red Blood Cell Count', '10^6/uL', '4.2-5.9', '4.7-6.1', '4.2-5.4', '4-5.2', 3),
(1, 'Platelet Count', '10^3/uL', '150-450', '150-450', '150-450', '150-450', 4),
(1, 'Hematocrit', '%', '36-50', '41-53', '36-46', '33-43', 5),
(1, 'MCV', 'fL', '80-100', '80-100', '80-100', '75-95', 6),
(1, 'MCH', 'pg', '27-33', '27-33', '27-33', '25-33', 7),
(1, 'MCHC', 'g/dL', '32-36', '32-36', '32-36', '31-36', 8),
(1, 'Neutrophils', '%', '40-75', '', '', '30-70', 9),
(1, 'Lymphocytes', '%', '20-45', '', '', '30-60', 10),
(1, 'Monocytes', '%', '2-10', '', '', '2-10', 11),
(1, 'Eosinophils', '%', '1-6', '', '', '1-8', 12),
(1, 'Basophils', '%', '0-1', '', '', '0-1', 13),
(8, 'Total Bilirubin', 'mg/dL', '0.2-1.2', '', '', '0.2-1', 1),
(8, 'Direct Bilirubin', 'mg/dL', '0-0.3', '', '', '', 2),
(8, 'SGPT (ALT)', 'U/L', '0-45', '0-50', '0-35', '0-40', 3),
(8, 'SGOT (AST)', 'U/L', '0-40', '0-40', '0-32', '0-40', 4),
(8, 'Alkaline Phosphatase', 'U/L', '40-130', '', '', '100-320', 5),
(8, 'Total Protein', 'g/dL', '6.4-8.3', '', '', '6-8', 6),
(8, 'Serum Albumin', 'g/dL', '3.5-5.2', '', '', '3.4-4.8', 7),
(9, 'Blood Urea', 'mg/dL', '15-45', '', '', '10-40', 1),
(9, 'Serum Creatinine', 'mg/dL', '0.6-1.3', '0.7-1.3', '0.6-1.1', '0.3-0.7', 2),
(9, 'Serum Sodium', 'mmol/L', '135-145', '', '', '', 3),
(9, 'Serum Potassium', 'mmol/L', '3.5-5.1', '', '', '', 4),
(9, 'Serum Chloride', 'mmol/L', '98-107', '', '', '', 5),
(10, 'Total Cholesterol', 'mg/dL', '<200', '', '', '<180', 1),
(10, 'Triglycerides', 'mg/dL', '<150', '', '', '<130', 2),
(10, 'HDL Cholesterol', 'mg/dL', '>40', '>40', '>50', '>45', 3),
(10, 'LDL Cholesterol', 'mg/dL', '<130', '', '', '<110', 4),
(10, 'VLDL Cholesterol', 'mg/dL', '10-30', '', '', '', 5),
(13, 'T3 (Triiodothyronine)', 'ng/dL', '80-200', '', '', '105-245', 1),
(13, 'T4 (Thyroxine)', 'ug/dL', '5.1-14.1', '', '', '6-15', 2),
(13, 'TSH', 'uIU/mL', '0.4-4.2', '', '', '0.7-6.4', 3),
(15, 'Colour', '', 'Pale Yellow', '', '', '', 1),
(15, 'Appearance', '', 'Clear', '', '', '', 2),
(15, 'Specific Gravity', '', '1.005-1.030', '', '', '', 3),
(15, 'pH', '', '4.6-8', '', '', '', 4),
(15, 'Protein', '', 'Nil', '', '', '', 5),
(15, 'Glucose', '', 'Nil', '', '', '', 6),
(15, 'Pus Cells', '/HPF', '0-5', '', '', '', 7),
(15, 'Red Blood Cells', '/HPF', '0-2', '', '', '', 8),
(15, 'Epithelial Cells', '/HPF', '0-5', '', '', '', 9),
(19, 'Salmonella Typhi O', '', '<1:80', '', '', '', 1),
(19, 'Salmonella Typhi H', '', '<1:80', '', '', '', 2),
(19, 'Salmonella Paratyphi AH', '', '<1:80', '', '', '', 3),
(19, 'Salmonella Paratyphi BH', '', '<1:80', '', '', '', 4);
