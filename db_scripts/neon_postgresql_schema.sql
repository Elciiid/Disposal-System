-- Disposal System - PostgreSQL Schema (Neon Compatible)
-- Idempotent Version: Safe to run multiple times, even if unique constraints are missing.

-- 1. Phases
CREATE TABLE IF NOT EXISTS wst_Phases (
    PhaseID SERIAL PRIMARY KEY,
    PhaseName VARCHAR(50) NOT NULL
);

-- 2. Areas
CREATE TABLE IF NOT EXISTS wst_Areas (
    AreaID SERIAL PRIMARY KEY,
    AreaName VARCHAR(100) NOT NULL
);

-- 3. Shifts
CREATE TABLE IF NOT EXISTS wst_Shifts (
    ShiftID SERIAL PRIMARY KEY,
    ShiftName VARCHAR(50) NOT NULL
);

-- 4. Log Types (Waste, Transfer, etc.)
CREATE TABLE IF NOT EXISTS wst_LogTypes (
    TypeID SERIAL PRIMARY KEY,
    TypeName VARCHAR(100) NOT NULL
);

-- 5. Product Categories
CREATE TABLE IF NOT EXISTS wst_PCategories (
    CategoryID SERIAL PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

-- 6. Product Descriptions
CREATE TABLE IF NOT EXISTS wst_PDescriptions (
    DescriptionID SERIAL PRIMARY KEY,
    CategoryID INT REFERENCES wst_PCategories(CategoryID),
    DescriptionName VARCHAR(255) NOT NULL
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
-- Initial Mock Data Seed (Robust Seeding)
-- ==========================================================

-- 1. Master Data
INSERT INTO wst_Phases (PhaseName) SELECT 'Phase 1' WHERE NOT EXISTS (SELECT 1 FROM wst_Phases WHERE PhaseName = 'Phase 1');
INSERT INTO wst_Phases (PhaseName) SELECT 'Phase 2' WHERE NOT EXISTS (SELECT 1 FROM wst_Phases WHERE PhaseName = 'Phase 2');
INSERT INTO wst_Phases (PhaseName) SELECT 'Phase 3' WHERE NOT EXISTS (SELECT 1 FROM wst_Phases WHERE PhaseName = 'Phase 3');

INSERT INTO wst_Areas (AreaName) SELECT 'Production Line A' WHERE NOT EXISTS (SELECT 1 FROM wst_Areas WHERE AreaName = 'Production Line A');
INSERT INTO wst_Areas (AreaName) SELECT 'Production Line B' WHERE NOT EXISTS (SELECT 1 FROM wst_Areas WHERE AreaName = 'Production Line B');
INSERT INTO wst_Areas (AreaName) SELECT 'Packaging Area' WHERE NOT EXISTS (SELECT 1 FROM wst_Areas WHERE AreaName = 'Packaging Area');
INSERT INTO wst_Areas (AreaName) SELECT 'Warehouse' WHERE NOT EXISTS (SELECT 1 FROM wst_Areas WHERE AreaName = 'Warehouse');

INSERT INTO wst_Shifts (ShiftName) SELECT 'Day Shift (6AM-2PM)' WHERE NOT EXISTS (SELECT 1 FROM wst_Shifts WHERE ShiftName = 'Day Shift (6AM-2PM)');
INSERT INTO wst_Shifts (ShiftName) SELECT 'Afternoon Shift (2PM-10PM)' WHERE NOT EXISTS (SELECT 1 FROM wst_Shifts WHERE ShiftName = 'Afternoon Shift (2PM-10PM)');
INSERT INTO wst_Shifts (ShiftName) SELECT 'Night Shift (10PM-6AM)' WHERE NOT EXISTS (SELECT 1 FROM wst_Shifts WHERE ShiftName = 'Night Shift (10PM-6AM)');

INSERT INTO wst_LogTypes (TypeName) SELECT 'Waste' WHERE NOT EXISTS (SELECT 1 FROM wst_LogTypes WHERE TypeName = 'Waste');
INSERT INTO wst_LogTypes (TypeName) SELECT 'Transfer' WHERE NOT EXISTS (SELECT 1 FROM wst_LogTypes WHERE TypeName = 'Transfer');
INSERT INTO wst_LogTypes (TypeName) SELECT 'Others' WHERE NOT EXISTS (SELECT 1 FROM wst_LogTypes WHERE TypeName = 'Others');

-- 2. Categories & Descriptions
INSERT INTO wst_PCategories (CategoryName) SELECT 'Raw Materials' WHERE NOT EXISTS (SELECT 1 FROM wst_PCategories WHERE CategoryName = 'Raw Materials');
INSERT INTO wst_PCategories (CategoryName) SELECT 'Finished Goods' WHERE NOT EXISTS (SELECT 1 FROM wst_PCategories WHERE CategoryName = 'Finished Goods');
INSERT INTO wst_PCategories (CategoryName) SELECT 'Packaging Materials' WHERE NOT EXISTS (SELECT 1 FROM wst_PCategories WHERE CategoryName = 'Packaging Materials');

-- Descriptions
INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) 
SELECT (SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Raw Materials'), 'Sugar (Premium)' 
WHERE NOT EXISTS (SELECT 1 FROM wst_PDescriptions WHERE DescriptionName = 'Sugar (Premium)');

INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) 
SELECT (SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Raw Materials'), 'Flour (All-Purpose)' 
WHERE NOT EXISTS (SELECT 1 FROM wst_PDescriptions WHERE DescriptionName = 'Flour (All-Purpose)');

INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) 
SELECT (SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Finished Goods'), 'Sweet Biscuits (Box 24s)' 
WHERE NOT EXISTS (SELECT 1 FROM wst_PDescriptions WHERE DescriptionName = 'Sweet Biscuits (Box 24s)');

INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) 
SELECT (SELECT CategoryID FROM wst_PCategories WHERE CategoryName = 'Packaging Materials'), 'Cardboard Box (Size L)' 
WHERE NOT EXISTS (SELECT 1 FROM wst_PDescriptions WHERE DescriptionName = 'Cardboard Box (Size L)');

-- 3. Roles & Permissions
INSERT INTO wst_Roles (RoleName) SELECT 'Supervisor' WHERE NOT EXISTS (SELECT 1 FROM wst_Roles WHERE RoleName = 'Supervisor');
INSERT INTO wst_Roles (RoleName) SELECT 'Manager' WHERE NOT EXISTS (SELECT 1 FROM wst_Roles WHERE RoleName = 'Manager');
INSERT INTO wst_Roles (RoleName) SELECT 'Internal Security' WHERE NOT EXISTS (SELECT 1 FROM wst_Roles WHERE RoleName = 'Internal Security');
INSERT INTO wst_Roles (RoleName) SELECT 'Admin' WHERE NOT EXISTS (SELECT 1 FROM wst_Roles WHERE RoleName = 'Admin');

INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'submit_logs', 'Can submit new waste logs' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'submit_logs');
INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'view_history', 'Can view history of logs' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'view_history');
INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'view_own_submissions', 'Can view their own submissions' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'view_own_submissions');
INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'view_daily_products', 'Can view daily products page' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'view_daily_products');
INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'approve_step_1', 'Can perform Step 1 (Manager) approvals' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'approve_step_1');
INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'approve_step_2', 'Can perform Step 2 (Security) approvals' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'approve_step_2');
INSERT INTO wst_Permissions (PermissionKey, Description) SELECT 'access_settings', 'Can access system configuration' WHERE NOT EXISTS (SELECT 1 FROM wst_Permissions WHERE PermissionKey = 'access_settings');

-- Assign Permissions
INSERT INTO wst_RolePermissions (RoleID, PermissionID) 
SELECT r.RoleID, p.PermissionID FROM wst_Roles r, wst_Permissions p
WHERE r.RoleName = 'Admin'
AND NOT EXISTS (SELECT 1 FROM wst_RolePermissions WHERE RoleID = r.RoleID AND PermissionID = p.PermissionID);

-- 4. Mock Users (Password: password123)
INSERT INTO wst_Users (Username, Password, FullName, EmployeeID, RoleID, PhaseID, AreaID) 
SELECT '3096', 'password123', 'Super Admin', 'EMP-001', (SELECT RoleID FROM wst_Roles WHERE RoleName = 'Admin'), NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM wst_Users WHERE Username = '3096');

INSERT INTO wst_Users (Username, Password, FullName, EmployeeID, RoleID, PhaseID, AreaID) 
SELECT '5678', 'password123', 'Juan Dela Cruz', 'EMP-002', (SELECT RoleID FROM wst_Roles WHERE RoleName = 'Manager'), (SELECT PhaseID FROM wst_Phases WHERE PhaseName = 'Phase 1'), NULL
WHERE NOT EXISTS (SELECT 1 FROM wst_Users WHERE Username = '5678');

-- 5. Sample Log (Optional)
INSERT INTO wst_Logs (LogDate, TypeID, PhaseID, AreaID, ShiftID, CategoryID, DescriptionID, KG, Reason, SubmittedBy, CurrentStep, ApprovalStatus) 
SELECT CURRENT_TIMESTAMP, 1, 1, 1, 1, 1, 1, 10.00, 'Mock entry for testing', '3096', 1, 'Pending'
WHERE NOT EXISTS (SELECT 1 FROM wst_Logs LIMIT 1);
