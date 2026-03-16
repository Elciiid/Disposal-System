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
    PCS INT,
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
    Step3ApprovedBy VARCHAR(100),
    Step3ApprovedAt TIMESTAMP,
    Step4ApprovedBy VARCHAR(100),
    Step4ApprovedAt TIMESTAMP,
    Step5ApprovedBy VARCHAR(100),
    Step5ApprovedAt TIMESTAMP,
    
    RejectedBy VARCHAR(100),
    RejectionReason TEXT
);

-- 8. Roles and Permissions
CREATE TABLE wst_Roles (
    RoleID SERIAL PRIMARY KEY,
    RoleName VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE wst_Permissions (
    PermissionID SERIAL PRIMARY KEY,
    PermissionKey VARCHAR(100) UNIQUE NOT NULL,
    Description TEXT
);

CREATE TABLE wst_RolePermissions (
    RoleID INT REFERENCES wst_Roles(RoleID),
    PermissionID INT REFERENCES wst_Permissions(PermissionID),
    PRIMARY KEY (RoleID, PermissionID)
);

-- Initial Mock Data Seed
INSERT INTO wst_Phases (PhaseName) VALUES ('Phase 1'), ('Phase 2'), ('Phase 3');
INSERT INTO wst_LogTypes (TypeName) VALUES ('Waste'), ('Transfer'), ('Others');
INSERT INTO wst_Roles (RoleName) VALUES ('Supervisor'), ('Manager'), ('Internal Security'), ('Admin');
