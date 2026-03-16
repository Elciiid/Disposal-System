-- Disposal System - PostgreSQL Schema (Neon Compatible)
-- Idempotent Version: Safe to run multiple times.

-- 1. Phases
CREATE TABLE IF NOT EXISTS wst_Phases (
    PhaseID SERIAL PRIMARY KEY,
    PhaseName VARCHAR(50) NOT NULL UNIQUE
);

-- 2. Areas
CREATE TABLE IF NOT EXISTS wst_Areas (
    AreaID SERIAL PRIMARY KEY,
    AreaName VARCHAR(100) NOT NULL UNIQUE
);

-- 3. Shifts
CREATE TABLE IF NOT EXISTS wst_Shifts (
    ShiftID SERIAL PRIMARY KEY,
    ShiftName VARCHAR(50) NOT NULL UNIQUE
);

-- 4. Log Types (Waste, Transfer, etc.)
CREATE TABLE IF NOT EXISTS wst_LogTypes (
    TypeID SERIAL PRIMARY KEY,
    TypeName VARCHAR(100) NOT NULL UNIQUE
);

-- 5. Product Categories
CREATE TABLE IF NOT EXISTS wst_PCategories (
    CategoryID SERIAL PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL UNIQUE
);

-- 6. Product Descriptions
CREATE TABLE IF NOT EXISTS wst_PDescriptions (
    DescriptionID SERIAL PRIMARY KEY,
    CategoryID INT REFERENCES wst_PCategories(CategoryID),
    DescriptionName VARCHAR(255) NOT NULL UNIQUE
);

-- 7. Main Logs Table
CREATE TABLE IF NOT EXISTS wst_Logs (
    LogID SERIAL PRIMARY KEY,
    LogDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    TypeID INT REFERENCES wst_LogTypes(TypeID),
    PhaseID INT REFERENCES wst_Phases(PhaseID),
    AreaID INT REFERENCES wst_Areas(AreaID),
    ShiftID INT REFERENCES wst_Shifts(ShiftID),
    CategoryID INT REFERENCES wst_PCategories(CategoryID),
    DescriptionID INT REFERENCES wst_PDescriptions(DescriptionID),
    KG DECIMAL(10,2),
    Reason TEXT,
    OtherTypeRemark TEXT,
    SubmittedBy VARCHAR(100),
    CurrentStep INT DEFAULT 1,
    ApprovalStatus VARCHAR(20) DEFAULT 'Pending',
    
    -- Approval Chain Tracking
    Step1ApprovedBy VARCHAR(100),
    Step1ApprovedAt TIMESTAMP,
    Step2ApprovedBy VARCHAR(100),
    Step2ApprovedAt TIMESTAMP,
    
    RejectedBy VARCHAR(100),
    RejectionReason TEXT
);

-- 8. Roles
CREATE TABLE IF NOT EXISTS wst_Roles (
    RoleID SERIAL PRIMARY KEY,
    RoleName VARCHAR(100) UNIQUE NOT NULL
);

-- 9. Permissions
CREATE TABLE IF NOT EXISTS wst_Permissions (
    PermissionID SERIAL PRIMARY KEY,
    PermissionKey VARCHAR(100) UNIQUE NOT NULL,
    Description TEXT
);

-- 10. Users
CREATE TABLE IF NOT EXISTS wst_Users (
    UserID SERIAL PRIMARY KEY,
    Username VARCHAR(50) UNIQUE NOT NULL, -- Biometrics ID
    Password VARCHAR(255) NOT NULL,
    FullName VARCHAR(255),
    EmployeeID VARCHAR(50),
    RoleID INT REFERENCES wst_Roles(RoleID),
    PhaseID INT REFERENCES wst_Phases(PhaseID),
    AreaID INT REFERENCES wst_Areas(AreaID),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Role Permissions
CREATE TABLE IF NOT EXISTS wst_RolePermissions (
    RoleID INT REFERENCES wst_Roles(RoleID),
    PermissionID INT REFERENCES wst_Permissions(PermissionID),
    PRIMARY KEY (RoleID, PermissionID)
);

-- ==========================================================
-- Initial Mock Data Seed (Uses ON CONFLICT to avoid errors)
-- ==========================================================

-- 1. Master Data
INSERT INTO wst_Phases (PhaseName) VALUES ('Phase 1'), ('Phase 2'), ('Phase 3') ON CONFLICT (PhaseName) DO NOTHING;
INSERT INTO wst_Areas (AreaName) VALUES ('Production Line A'), ('Production Line B'), ('Packaging Area'), ('Warehouse') ON CONFLICT (AreaName) DO NOTHING;
INSERT INTO wst_Shifts (ShiftName) VALUES ('Day Shift (6AM-2PM)'), ('Afternoon Shift (2PM-10PM)'), ('Night Shift (10PM-6AM)') ON CONFLICT (ShiftName) DO NOTHING;
INSERT INTO wst_LogTypes (TypeName) VALUES ('Waste'), ('Transfer'), ('Others') ON CONFLICT (TypeName) DO NOTHING;

-- 2. Categories & Descriptions
INSERT INTO wst_PCategories (CategoryName) VALUES ('Raw Materials'), ('Finished Goods'), ('Packaging Materials') ON CONFLICT (CategoryName) DO NOTHING;

-- Descriptions (Mocking a few with IDs)
INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) VALUES 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Raw Materials'), 'Sugar (Premium)'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Raw Materials'), 'Flour (All-Purpose)'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Raw Materials'), 'Vegetable Oil'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Raw Materials'), 'Food Coloring (Red)')
ON CONFLICT (DescriptionName) DO NOTHING;

INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) VALUES 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Finished Goods'), 'Sweet Biscuits (Box 24s)'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Finished Goods'), 'Crackers (Pouch 100g)'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Finished Goods'), 'Cream Sandwich (Pack 12s)')
ON CONFLICT (DescriptionName) DO NOTHING;

INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) VALUES 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Packaging Materials'), 'Cardboard Box (Size L)'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Packaging Materials'), 'Plastic Wrap (Roll)'), 
((SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Packaging Materials'), 'Labels (Batch Print)')
ON CONFLICT (DescriptionName) DO NOTHING;

-- 3. Roles & Permissions
INSERT INTO wst_Roles (RoleName) VALUES ('Supervisor'), ('Manager'), ('Internal Security'), ('Admin') ON CONFLICT (RoleName) DO NOTHING;

INSERT INTO wst_Permissions (PermissionKey, Description) VALUES 
('submit_logs', 'Can submit new waste logs'),
('view_history', 'Can view history of logs'),
('view_own_submissions', 'Can view own submitted logs'),
('approve_step1', 'Can perform Step 1 (Manager) approvals'),
('approve_step2', 'Can perform Step 2 (Security) approvals'),
('access_settings', 'Can access system configuration'),
('export_csv', 'Can export logs to CSV')
ON CONFLICT (PermissionKey) DO NOTHING;

-- Assign Permissions to Roles (Dynamic lookup to avoid hardcoded IDs)
INSERT INTO wst_RolePermissions (RoleID, PermissionID) 
SELECT r.RoleID, p.PermissionID FROM wst_Roles r, wst_Permissions p
WHERE r.RoleName = 'Admin'
ON CONFLICT DO NOTHING;

INSERT INTO wst_RolePermissions (RoleID, PermissionID) 
SELECT r.RoleID, p.PermissionID FROM wst_Roles r, wst_Permissions p
WHERE r.RoleName = 'Supervisor' AND p.PermissionKey IN ('submit_logs', 'view_own_submissions', 'view_history')
ON CONFLICT DO NOTHING;

INSERT INTO wst_RolePermissions (RoleID, PermissionID) 
SELECT r.RoleID, p.PermissionID FROM wst_Roles r, wst_Permissions p
WHERE r.RoleName = 'Manager' AND p.PermissionKey IN ('approve_step1', 'view_history')
ON CONFLICT DO NOTHING;

INSERT INTO wst_RolePermissions (RoleID, PermissionID) 
SELECT r.RoleID, p.PermissionID FROM wst_Roles r, wst_Permissions p
WHERE r.RoleName = 'Internal Security' AND p.PermissionKey IN ('approve_step2', 'view_history', 'export_csv')
ON CONFLICT DO NOTHING;

-- 4. Mock Users (Password for all is 'password123')
INSERT INTO wst_Users (Username, Password, FullName, EmployeeID, RoleID, PhaseID, AreaID) VALUES 
('3096', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'System Administrator', 'EMP-001', (SELECT RoleID FROM wst_Roles WHERE RoleName = 'Admin'), NULL, NULL),
('5678', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'Juan Dela Cruz', 'EMP-002', (SELECT RoleID FROM wst_Roles WHERE RoleName = 'Manager'), (SELECT PhaseID FROM wst_Phases WHERE PhaseName = 'Phase 1'), NULL),
('1234', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'Maria Clara', 'EMP-003', (SELECT RoleID FROM wst_Roles WHERE RoleName = 'Supervisor'), (SELECT PhaseID FROM wst_Phases WHERE PhaseName = 'Phase 1'), (SELECT AreaID FROM wst_Areas WHERE AreaName = 'Production Line A')),
('9999', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'Chief Security', 'EMP-004', (SELECT RoleID FROM wst_Roles WHERE RoleName = 'Internal Security'), NULL, NULL)
ON CONFLICT (Username) DO NOTHING;

-- 5. Sample Logs (Optional)
-- Only inserting if table is empty to avoid duplicates on multiple runs
INSERT INTO wst_Logs (LogDate, TypeID, PhaseID, AreaID, ShiftID, CategoryID, DescriptionID, KG, Reason, SubmittedBy, CurrentStep, ApprovalStatus, Step1ApprovedBy, Step1ApprovedAt, Step2ApprovedBy, Step2ApprovedAt) 
SELECT CURRENT_TIMESTAMP - INTERVAL '2 days', 1, 1, 1, 1, 1, 1, 15.50, 'Expired stock discovered during audit', '1234', 3, 'Approved', '5678', CURRENT_TIMESTAMP - INTERVAL '1 day', '9999', CURRENT_TIMESTAMP - INTERVAL '12 hours'
WHERE NOT EXISTS (SELECT 1 FROM wst_Logs LIMIT 1);
