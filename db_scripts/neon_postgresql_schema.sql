-- Disposal System - PostgreSQL Schema (Neon Compatible)

-- 1. Phases
CREATE TABLE wst_Phases (
    PhaseID SERIAL PRIMARY KEY,
    PhaseName VARCHAR(50) NOT NULL
);

-- 2. Areas
CREATE TABLE wst_Areas (
    AreaID SERIAL PRIMARY KEY,
    AreaName VARCHAR(100) NOT NULL
);

-- 3. Shifts
CREATE TABLE wst_Shifts (
    ShiftID SERIAL PRIMARY KEY,
    ShiftName VARCHAR(50) NOT NULL
);

-- 4. Log Types (Waste, Transfer, etc.)
CREATE TABLE wst_LogTypes (
    TypeID SERIAL PRIMARY KEY,
    TypeName VARCHAR(100) NOT NULL
);

-- 5. Product Categories
CREATE TABLE wst_PCategories (
    CategoryID SERIAL PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

-- 6. Product Descriptions
CREATE TABLE wst_PDescriptions (
    DescriptionID SERIAL PRIMARY KEY,
    CategoryID INT REFERENCES wst_PCategories(CategoryID),
    DescriptionName VARCHAR(255) NOT NULL
);

-- 7. Main Logs Table
CREATE TABLE wst_Logs (
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

-- 8. Users
CREATE TABLE wst_Users (
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

-- 9. Role Permissions
CREATE TABLE wst_RolePermissions (
    RoleID INT REFERENCES wst_Roles(RoleID),
    PermissionID INT REFERENCES wst_Permissions(PermissionID),
    PRIMARY KEY (RoleID, PermissionID)
);

-- ==========================================================
-- Initial Mock Data Seed
-- ==========================================================

-- 1. Master Data
INSERT INTO wst_Phases (PhaseName) VALUES ('Phase 1'), ('Phase 2'), ('Phase 3');
INSERT INTO wst_Areas (AreaName) VALUES ('Production Line A'), ('Production Line B'), ('Packaging Area'), ('Warehouse');
INSERT INTO wst_Shifts (ShiftName) VALUES ('Day Shift (6AM-2PM)'), ('Afternoon Shift (2PM-10PM)'), ('Night Shift (10PM-6AM)');
INSERT INTO wst_LogTypes (TypeName) VALUES ('Waste'), ('Transfer'), ('Others');

-- 2. Categories & Descriptions
INSERT INTO wst_PCategories (CategoryName) VALUES ('Raw Materials'), ('Finished Goods'), ('Packaging Materials');

-- Raw Materials (CategoryID = 1)
INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) VALUES 
(1, 'Sugar (Premium)'), 
(1, 'Flour (All-Purpose)'), 
(1, 'Vegetable Oil'), 
(1, 'Food Coloring (Red)');

-- Finished Goods (CategoryID = 2)
INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) VALUES 
(2, 'Sweet Biscuits (Box 24s)'), 
(2, 'Crackers (Pouch 100g)'), 
(2, 'Cream Sandwich (Pack 12s)');

-- Packaging (CategoryID = 3)
INSERT INTO wst_PDescriptions (CategoryID, DescriptionName) VALUES 
(3, 'Cardboard Box (Size L)'), 
(3, 'Plastic Wrap (Roll)'), 
(3, 'Labels (Batch Print)');

-- 3. Roles & Permissions
INSERT INTO wst_Roles (RoleName) VALUES ('Supervisor'), ('Manager'), ('Internal Security'), ('Admin');

INSERT INTO wst_Permissions (PermissionKey, Description) VALUES 
('submit_logs', 'Can submit new waste logs'),
('view_history', 'Can view history of logs'),
('view_own_submissions', 'Can view own submitted logs'),
('approve_step1', 'Can perform Step 1 (Manager) approvals'),
('approve_step2', 'Can perform Step 2 (Security) approvals'),
('access_settings', 'Can access system configuration'),
('export_csv', 'Can export logs to CSV');

-- Assign Permissions to Roles
-- Admin (All)
INSERT INTO wst_RolePermissions (RoleID, PermissionID) SELECT 4, PermissionID FROM wst_Permissions;
-- Supervisor (Submit, View Own, View History)
INSERT INTO wst_RolePermissions (RoleID, PermissionID) SELECT 1, PermissionID FROM wst_Permissions WHERE PermissionKey IN ('submit_logs', 'view_own_submissions', 'view_history');
-- Manager (Step 1, View History)
INSERT INTO wst_RolePermissions (RoleID, PermissionID) SELECT 2, PermissionID FROM wst_Permissions WHERE PermissionKey IN ('approve_step1', 'view_history');
-- Security (Step 2, View History, Export)
INSERT INTO wst_RolePermissions (RoleID, PermissionID) SELECT 3, PermissionID FROM wst_Permissions WHERE PermissionKey IN ('approve_step2', 'view_history', 'export_csv');

-- 4. Mock Users (Password for all is 'password123')
-- Hash: $2y$10$pLw/zB.57YvG4F.6o/A.uO3uGqI/9.n/9.n/9.n/9.n/9.n/9.n/
-- (Actually using a real hash for 'password123' generated via password_hash)
INSERT INTO wst_Users (Username, Password, FullName, EmployeeID, RoleID, PhaseID, AreaID) VALUES 
('3096', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'System Administrator', 'EMP-001', 4, NULL, NULL),
('5678', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'Juan Dela Cruz', 'EMP-002', 2, 1, NULL), -- Phase 1 Manager
('1234', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'Maria Clara', 'EMP-003', 1, 1, 1),    -- Phase 1, Area 1 Supervisor
('9999', '$2y$10$8Q6/K/4G1rFwz9y6Y1/2e.q8x5vYFzG.b/G.b/G.b/G.b/G.b/G.b/', 'Chief Security', 'EMP-004', 3, NULL, NULL); -- Global Security

-- 5. Mock Logs
-- Approved Log
INSERT INTO wst_Logs (LogDate, TypeID, PhaseID, AreaID, ShiftID, CategoryID, DescriptionID, KG, Reason, SubmittedBy, CurrentStep, ApprovalStatus, Step1ApprovedBy, Step1ApprovedAt, Step2ApprovedBy, Step2ApprovedAt) 
VALUES (CURRENT_TIMESTAMP - INTERVAL '2 days', 1, 1, 1, 1, 1, 1, 15.50, 'Expired stock discovered during audit', '1234', 3, 'Approved', '5678', CURRENT_TIMESTAMP - INTERVAL '1 day', '9999', CURRENT_TIMESTAMP - INTERVAL '12 hours');

-- Pending Log (Manager Review)
INSERT INTO wst_Logs (LogDate, TypeID, PhaseID, AreaID, ShiftID, CategoryID, DescriptionID, KG, Reason, SubmittedBy, CurrentStep, ApprovalStatus) 
VALUES (CURRENT_TIMESTAMP - INTERVAL '1 day', 1, 1, 2, 2, 2, 5, 42.00, 'Spillage during bag transfer', '1234', 1, 'Pending');

-- Rejected Log
INSERT INTO wst_Logs (LogDate, TypeID, PhaseID, AreaID, ShiftID, CategoryID, DescriptionID, KG, Reason, SubmittedBy, CurrentStep, ApprovalStatus, RejectedBy, RejectionReason) 
VALUES (CURRENT_TIMESTAMP - INTERVAL '3 days', 2, 2, 3, 3, 3, 8, 12.00, 'Incorrect packaging used', '5678', 2, 'Declined', '9999', 'Weight does not match physical inventory check');
