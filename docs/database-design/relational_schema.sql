-- ==============================================================================
-- Onyx Auto Dealership Relational Schema (MySQL)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 0. Drop existing database and re-create it
-- ------------------------------------------------------------------------------
DROP DATABASE IF EXISTS cs6400_sp26_team040;
CREATE DATABASE IF NOT EXISTS cs6400_sp26_team040;
Use cs6400_sp26_team040;


-- ------------------------------------------------------------------------------
-- 1. Drop existing tables in reverse dependency order
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS Part;
DROP TABLE IF EXISTS PartsOrder;
DROP TABLE IF EXISTS VehicleColor;
DROP TABLE IF EXISTS Sale;
DROP TABLE IF EXISTS Vehicle;
DROP TABLE IF EXISTS Vendor;
DROP TABLE IF EXISTS Business;
DROP TABLE IF EXISTS Individual;
DROP TABLE IF EXISTS Customer;
DROP TABLE IF EXISTS Manufacturer;
DROP TABLE IF EXISTS VehicleType;
DROP TABLE IF EXISTS OperatingManager;
DROP TABLE IF EXISTS SalesAgent;
DROP TABLE IF EXISTS AcquisitionSpecialist;
DROP TABLE IF EXISTS `User`;

-- ------------------------------------------------------------------------------
-- 2. Create Base Tables
-- ------------------------------------------------------------------------------

CREATE TABLE `User` (
    Username VARCHAR(50) NOT NULL,
    Password VARCHAR(100) NOT NULL,
    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    PRIMARY KEY (Username)
);

CREATE TABLE VehicleType (
    TypeName VARCHAR(50) NOT NULL,
    PRIMARY KEY (TypeName)
);

CREATE TABLE Manufacturer (
    MfgName VARCHAR(100) NOT NULL,
    PRIMARY KEY (MfgName)
);

-- Surrogate key table acting as the Category (Union) for Customer
CREATE TABLE Customer (
    CustomerID INT NOT NULL AUTO_INCREMENT, 
    Phone VARCHAR(20) NOT NULL,
    Email VARCHAR(100), 
    StreetAddress VARCHAR(100) NOT NULL,
    City VARCHAR(50) NOT NULL,
    State VARCHAR(20) NOT NULL,
    PostalCode VARCHAR(20) NOT NULL,
    PRIMARY KEY (CustomerID)
);

CREATE TABLE Vendor (
    VendorName VARCHAR(100) NOT NULL,
    Phone VARCHAR(20) NOT NULL,
    StreetAddress VARCHAR(100) NOT NULL,
    City VARCHAR(50) NOT NULL,
    State VARCHAR(20) NOT NULL,
    PostalCode VARCHAR(20) NOT NULL,
    PRIMARY KEY (VendorName)
);

-- ------------------------------------------------------------------------------
-- 3. Create Dependent Subclass Tables
-- ------------------------------------------------------------------------------

CREATE TABLE AcquisitionSpecialist (
    Username VARCHAR(50) NOT NULL,
    PRIMARY KEY (Username),
    FOREIGN KEY (Username) REFERENCES `User`(Username) 
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE SalesAgent (
    Username VARCHAR(50) NOT NULL,
    PRIMARY KEY (Username),
    FOREIGN KEY (Username) REFERENCES `User`(Username) 
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE OperatingManager (
    Username VARCHAR(50) NOT NULL,
    PRIMARY KEY (Username),
    FOREIGN KEY (Username) REFERENCES `User`(Username) 
        ON UPDATE CASCADE ON DELETE CASCADE
);

-- Individual mapped to the Customer Union
CREATE TABLE Individual (
    SSN VARCHAR(11) NOT NULL,
    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    CustomerID INT NOT NULL,
    PRIMARY KEY (SSN),
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID) 
        ON UPDATE CASCADE ON DELETE CASCADE
);

-- Business mapped to the Customer Union
CREATE TABLE Business (
    TaxID VARCHAR(20) NOT NULL,
    BusinessName VARCHAR(100) NOT NULL,
    ContactFirstName VARCHAR(50) NOT NULL,
    ContactLastName VARCHAR(50) NOT NULL,
    ContactTitle VARCHAR(50),
    CustomerID INT NOT NULL,
    PRIMARY KEY (TaxID),
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID) 
        ON UPDATE CASCADE ON DELETE CASCADE
);

-- ------------------------------------------------------------------------------
-- 4. Create Core Central Tables (Vehicle & Sale)
-- ------------------------------------------------------------------------------

CREATE TABLE Vehicle (
    VIN VARCHAR(17) NOT NULL,
    ModelName VARCHAR(50) NOT NULL,
    ModelYear INT NOT NULL CHECK (ModelYear BETWEEN 1000 AND 9999), 
    FuelType VARCHAR(30) NOT NULL,
    `Condition` VARCHAR(20) NOT NULL,
    Horsepower INT NOT NULL,
    Drivetrain VARCHAR(30) NOT NULL,
    Notes VARCHAR(500),
    PurchPrice DECIMAL(10,2) NOT NULL,
    PurchDate DATE NOT NULL,
    
    TypeName VARCHAR(50) NOT NULL,
    MfgName VARCHAR(100) NOT NULL,
    Purchaser_CustomerID INT NOT NULL,
    AcqSpecialist_Username VARCHAR(50) NOT NULL,

    PRIMARY KEY (VIN),
    FOREIGN KEY (TypeName) REFERENCES VehicleType(TypeName) ON UPDATE CASCADE,
    FOREIGN KEY (MfgName) REFERENCES Manufacturer(MfgName) ON UPDATE CASCADE,
    FOREIGN KEY (Purchaser_CustomerID) REFERENCES Customer(CustomerID) ON UPDATE CASCADE,
    FOREIGN KEY (AcqSpecialist_Username) REFERENCES AcquisitionSpecialist(Username) ON UPDATE CASCADE
);

-- Sale is mapped into a separate relation to satisfy the 1:N:1 ternary relationship
-- and avoid NULL values for unsold vehicles.
CREATE TABLE Sale (
    VIN VARCHAR(17) NOT NULL,
    Buyer_CustomerID INT NOT NULL,
    SalesAgent_Username VARCHAR(50) NOT NULL,
    SaleDate DATE NOT NULL,
    
    PRIMARY KEY (VIN),
    FOREIGN KEY (VIN) REFERENCES Vehicle(VIN) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (Buyer_CustomerID) REFERENCES Customer(CustomerID) ON UPDATE CASCADE,
    FOREIGN KEY (SalesAgent_Username) REFERENCES SalesAgent(Username) ON UPDATE CASCADE
);

-- ------------------------------------------------------------------------------
-- 5. Create Multivalued and Weak Entity Tables
-- ------------------------------------------------------------------------------

CREATE TABLE VehicleColor (
    VIN VARCHAR(17) NOT NULL,
    Color VARCHAR(30) NOT NULL,
    PRIMARY KEY (VIN, Color),
    FOREIGN KEY (VIN) REFERENCES Vehicle(VIN) 
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE PartsOrder (
    VIN VARCHAR(17) NOT NULL,
    OrderNum VARCHAR(50) NOT NULL,
    VendorName VARCHAR(100) NOT NULL,
    PRIMARY KEY (VIN, OrderNum),
    FOREIGN KEY (VIN) REFERENCES Vehicle(VIN) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (VendorName) REFERENCES Vendor(VendorName) 
        ON UPDATE CASCADE
);

CREATE TABLE Part (
    VIN VARCHAR(17) NOT NULL,
    OrderNum VARCHAR(50) NOT NULL,
    PartNumber VARCHAR(50) NOT NULL,
    Description VARCHAR(255) NOT NULL,
    UnitPrice DECIMAL(10,2) NOT NULL,
    Quantity INT NOT NULL,
    Status VARCHAR(30) NOT NULL CHECK (Status IN ('Ordered', 'Received', 'Installed')),
    PRIMARY KEY (VIN, OrderNum, PartNumber),
    FOREIGN KEY (VIN, OrderNum) REFERENCES PartsOrder(VIN, OrderNum) 
        ON UPDATE CASCADE ON DELETE CASCADE
);



--  Vehicle

DROP TABLE IF EXISTS Vehicle_pre;
CREATE TABLE IF NOT EXISTS Vehicle_pre(
	VIN VARCHAR(17) NOT NULL,
    ModelName VARCHAR(50) NOT NULL,
    ModelYear INT NOT NULL CHECK (ModelYear BETWEEN 1000 AND 9999), 
    FuelType VARCHAR(30) NOT NULL,
    `Condition` VARCHAR(20) NOT NULL,
    Horsepower INT NOT NULL,
    Drivetrain VARCHAR(30) NOT NULL,
    Notes VARCHAR(500),
    PurchPrice DECIMAL(10,2) NOT NULL,
    PurchDate DATE NOT NULL,
    
    TypeName VARCHAR(50) NOT NULL,
    MfgName VARCHAR(100) NOT NULL,
    Purchaser_CustomerID VARCHAR(20) NOT NULL,
    AcqSpecialist_Username VARCHAR(50) NOT NULL,

    PRIMARY KEY (VIN),
    FOREIGN KEY (TypeName) REFERENCES VehicleType(TypeName) ON UPDATE CASCADE,
    FOREIGN KEY (MfgName) REFERENCES Manufacturer(MfgName) ON UPDATE CASCADE,
    FOREIGN KEY (AcqSpecialist_Username) REFERENCES AcquisitionSpecialist(Username)
);



DROP TABLE IF EXISTS Sale_pre;


-- Parts Order and Parts

DROP TABLE IF EXISTS Part;
DROP TABLE IF EXISTS PartsOrder;
CREATE TABLE IF NOT EXISTS PartsOrder (
    VIN VARCHAR(17) NOT NULL,
    OrderNum VARCHAR(50) NOT NULL,
    VendorName VARCHAR(100) NOT NULL,
    PRIMARY KEY (VIN, OrderNum, VendorName),
    FOREIGN KEY (VIN) REFERENCES Vehicle(VIN) 
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (VendorName) REFERENCES Vendor(VendorName) 
        ON UPDATE CASCADE
);

CREATE TABLE Part (
    VIN VARCHAR(17) NOT NULL,
    OrderNum VARCHAR(50) NOT NULL,
    PartNumber VARCHAR(50) NOT NULL,
    Description VARCHAR(255) NOT NULL,
    UnitPrice DECIMAL(10,2) NOT NULL,
    Quantity INT NOT NULL,
    Status VARCHAR(30) NOT NULL CHECK (Status IN ('Ordered', 'Received', 'Installed')),
    PRIMARY KEY (VIN, OrderNum, PartNumber),
    FOREIGN KEY (VIN, OrderNum) REFERENCES PartsOrder(VIN, OrderNum) 
        ON UPDATE CASCADE ON DELETE CASCADE
);
