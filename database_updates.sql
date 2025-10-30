-- =========================================================
-- DATABASE UPDATES FOR RBAC & ACTIVITY LOGGING
-- Admin Panel POSM - Security Enhancement Phase 2
-- =========================================================

-- 1. ROLES TABLE
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO roles (role_name, role_display_name, description) VALUES
('super_admin', 'Super Admin', 'Full access to all features including user management'),
('admin', 'Admin', 'Full access to operations, cannot manage users or delete stores/employees'),
('kasir', 'Kasir', 'Can only input setoran for assigned store'),
('viewer', 'Viewer', 'Read-only access to reports and data')
ON DUPLICATE KEY UPDATE role_display_name = VALUES(role_display_name);

-- 2. UPDATE USERS TABLE - Add role_id
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id);

-- Update existing users to super_admin role
UPDATE users SET role_id = 1 WHERE role_id IS NULL OR role_id = 0;

-- 3. ACTIVITY LOG TABLE
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(100),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. PERMISSIONS TABLE
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    permission_display_name VARCHAR(150) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default permissions
INSERT INTO permissions (permission_name, permission_display_name, description) VALUES
('view_dashboard', 'View Dashboard', 'Can view dashboard and wallet information'),
('view_setoran', 'View Setoran History', 'Can view setoran history'),
('add_setoran', 'Add Setoran', 'Can add new setoran'),
('edit_setoran', 'Edit Setoran', 'Can edit existing setoran'),
('delete_setoran', 'Delete Setoran', 'Can delete setoran'),
('view_cashflow', 'View Cashflow', 'Can view cashflow management'),
('add_cashflow', 'Add Cashflow', 'Can add cashflow transactions'),
('edit_cashflow', 'Edit Cashflow', 'Can edit cashflow transactions'),
('delete_cashflow', 'Delete Cashflow', 'Can delete cashflow transactions'),
('view_stores', 'View Stores', 'Can view stores list'),
('add_store', 'Add Store', 'Can add new store'),
('edit_store', 'Edit Store', 'Can edit store information'),
('delete_store', 'Delete Store', 'Can delete store'),
('view_employees', 'View Employees', 'Can view employees list'),
('add_employee', 'Add Employee', 'Can add new employee'),
('edit_employee', 'Edit Employee', 'Can edit employee information'),
('delete_employee', 'Delete Employee', 'Can delete employee'),
('export_data', 'Export Data', 'Can export data to Excel/PDF'),
('manage_users', 'Manage Users', 'Can manage user accounts and permissions')
ON DUPLICATE KEY UPDATE permission_display_name = VALUES(permission_display_name);

-- 5. ROLE_PERMISSIONS MAPPING TABLE
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assign permissions to Super Admin (all permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Assign permissions to Admin (all except manage_users, delete_store, delete_employee)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions 
WHERE permission_name NOT IN ('manage_users', 'delete_store', 'delete_employee');

-- Assign permissions to Kasir (limited to setoran operations for their store)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions 
WHERE permission_name IN ('view_setoran', 'add_setoran', 'view_stores');

-- Assign permissions to Viewer (read-only)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions 
WHERE permission_name IN ('view_dashboard', 'view_setoran', 'view_cashflow', 'view_stores', 'view_employees', 'export_data');

-- 6. RATE LIMITING TABLE
CREATE TABLE IF NOT EXISTS rate_limit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    attempt_count INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    INDEX idx_ip_endpoint (ip_address, endpoint),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. USER SESSIONS TABLE (for better session management)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Add indexes for performance optimization
ALTER TABLE setoran 
ADD INDEX IF NOT EXISTS idx_tanggal (tanggal),
ADD INDEX IF NOT EXISTS idx_store_id (store_id),
ADD INDEX IF NOT EXISTS idx_employee_id (employee_id),
ADD INDEX IF NOT EXISTS idx_store_tanggal (store_id, tanggal);

ALTER TABLE cash_flow_management
ADD INDEX IF NOT EXISTS idx_tanggal (tanggal),
ADD INDEX IF NOT EXISTS idx_store_id (store_id),
ADD INDEX IF NOT EXISTS idx_type (type),
ADD INDEX IF NOT EXISTS idx_category (category),
ADD INDEX IF NOT EXISTS idx_store_tanggal (store_id, tanggal);

-- 9. Clean up old sessions (run periodically via cron)
-- DELETE FROM user_sessions WHERE expires_at < NOW();
-- DELETE FROM rate_limit_log WHERE blocked_until < NOW() - INTERVAL 1 DAY;

-- =========================================================
-- VERIFICATION QUERIES
-- =========================================================

-- Check if all tables are created
SELECT 
    'roles' as table_name, COUNT(*) as count FROM roles
UNION ALL
SELECT 'permissions', COUNT(*) FROM permissions
UNION ALL
SELECT 'role_permissions', COUNT(*) FROM role_permissions
UNION ALL
SELECT 'activity_log', COUNT(*) FROM activity_log
UNION ALL
SELECT 'rate_limit_log', COUNT(*) FROM rate_limit_log
UNION ALL
SELECT 'user_sessions', COUNT(*) FROM user_sessions;

-- =========================================================
-- END OF DATABASE UPDATES
-- =========================================================
