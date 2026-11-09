-- ============================================================
-- Karga-Ments Inventory Management System — Hostinger database
-- Schema v4: v3 tables + User_Preference (guided mode setting)
-- Demo data: hardware store, dates relative to the moment you import.
--
-- HOW TO USE (Hostinger):
--   1. hPanel > Databases > MySQL Databases: create a database + user.
--   2. Open it in phpMyAdmin, click Import, choose this file, Go.
--   Works the same on XAMPP: create an empty database "karga_ments" first.
--
-- WARNING: re-importing DELETES the Karga-Ments tables listed below and
-- everything in them, then creates them fresh. Download a backup first
-- (Admin > Backup) if the site already has real data.
--
-- Demo logins: admin / admin123   manager1 / manager123   employee1 / employee123
--              supplier_buildmax, supplier_voltpro, supplier_pacific / supplier123
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS vw_low_stock_products;
DROP TABLE IF EXISTS User_Preference, Audit_Log, Supplier_Submission, Purchase_Order_Item,
                     Purchase_Order, Stock_Transaction, Product, User, Supplier, Category, Role;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE Role (
    RoleID   INT AUTO_INCREMENT PRIMARY KEY,
    RoleName VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Category (
    CategoryID   INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Supplier (
    SupplierID  INT AUTO_INCREMENT PRIMARY KEY,
    Name        VARCHAR(255) NOT NULL,
    ContactInfo VARCHAR(255),
    Address     VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE User (
    UserID       INT AUTO_INCREMENT PRIMARY KEY,
    Username     VARCHAR(255) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    FullName     VARCHAR(255) NOT NULL,
    RoleID       INT NOT NULL,
    SupplierID   INT NULL,
    Status       VARCHAR(50) NOT NULL DEFAULT 'active',
    CONSTRAINT fk_user_role     FOREIGN KEY (RoleID) REFERENCES Role(RoleID),
    CONSTRAINT fk_user_supplier FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Product (
    ProductID    INT AUTO_INCREMENT PRIMARY KEY,
    Name         VARCHAR(255) NOT NULL,
    CategoryID   INT NOT NULL,
    SupplierID   INT NOT NULL,
    UnitPrice    NUMERIC(19,2) NOT NULL DEFAULT 0,
    Quantity     INT NOT NULL DEFAULT 0,
    MinThreshold INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_category FOREIGN KEY (CategoryID) REFERENCES Category(CategoryID),
    CONSTRAINT fk_product_supplier FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Stock_Transaction (
    TransactionID   INT AUTO_INCREMENT PRIMARY KEY,
    ProductID       INT NOT NULL,
    UserID          INT NOT NULL,
    TransactionType VARCHAR(50) NOT NULL,   -- 'Stock-In' or 'Sale'
    Quantity        INT NOT NULL,
    TransactionDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_st_product FOREIGN KEY (ProductID) REFERENCES Product(ProductID),
    CONSTRAINT fk_st_user FOREIGN KEY (UserID) REFERENCES User(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Purchase_Order (
    OrderID    INT AUTO_INCREMENT PRIMARY KEY,
    SupplierID INT NOT NULL,
    UserID     INT NOT NULL,
    OrderDate  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Status     VARCHAR(50) NOT NULL DEFAULT 'Pending',
    CONSTRAINT fk_po_supplier FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID),
    CONSTRAINT fk_po_user FOREIGN KEY (UserID) REFERENCES User(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Purchase_Order_Item (
    OrderItemID     INT AUTO_INCREMENT PRIMARY KEY,
    OrderID         INT NOT NULL,
    ProductID       INT NOT NULL,
    QuantityOrdered INT NOT NULL,
    UnitPrice       NUMERIC(19,2) NOT NULL,
    CONSTRAINT fk_poi_order FOREIGN KEY (OrderID) REFERENCES Purchase_Order(OrderID) ON DELETE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (ProductID) REFERENCES Product(ProductID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Supplier_Submission (
    SubmissionID      INT AUTO_INCREMENT PRIMARY KEY,
    SupplierID        INT NOT NULL,
    ProductID         INT NOT NULL,
    AvailableQuantity INT NOT NULL,
    UnitPrice         NUMERIC(19,2) NOT NULL,
    DeliveryDate      DATE NULL,
    DeliveryNotes     VARCHAR(500) NULL,
    Status            VARCHAR(50) NOT NULL DEFAULT 'Pending', -- Pending / Approved / Rejected
    SubmittedAt       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ReviewedBy        INT NULL,
    ReviewedAt        DATETIME NULL,
    ReviewNotes       VARCHAR(500) NULL,
    CONSTRAINT fk_sub_supplier FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID),
    CONSTRAINT fk_sub_product  FOREIGN KEY (ProductID) REFERENCES Product(ProductID),
    CONSTRAINT fk_sub_reviewer FOREIGN KEY (ReviewedBy) REFERENCES User(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Audit_Log (
    LogID       INT AUTO_INCREMENT PRIMARY KEY,
    UserID      INT NOT NULL,
    Action      VARCHAR(255) NOT NULL,
    TargetTable VARCHAR(100) NOT NULL,
    TargetID    INT NOT NULL,
    Timestamp   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (UserID) REFERENCES User(UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NEW in v4: per-account settings. GuidedMode NULL = not asked yet (the mascot asks on
-- the next login), 1 = on, 0 = off.
CREATE TABLE User_Preference (
    UserID     INT PRIMARY KEY,
    GuidedMode TINYINT(1) NULL,
    UpdatedAt  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pref_user FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE VIEW vw_low_stock_products AS
SELECT ProductID, Name, Quantity, MinThreshold
FROM Product
WHERE Quantity <= MinThreshold;

-- ============================================================
-- DEMO DATA
-- ============================================================

INSERT INTO Role (RoleID, RoleName) VALUES (1,'Admin'),(2,'Manager'),(3,'Employee'),(4,'Supplier');

INSERT INTO Category (CategoryID, CategoryName) VALUES
(1, 'Building Materials'),
(2, 'Fasteners'),
(3, 'Hand Tools'),
(4, 'Power Tools'),
(5, 'Electrical'),
(6, 'Plumbing'),
(7, 'Paint & Finishes');

INSERT INTO Supplier (SupplierID, Name, ContactInfo, Address) VALUES
(1, 'BuildMax Construction Supply', 'sales@buildmax.ph | 0917-555-0101', 'Calamba, Laguna'),
(2, 'VoltPro Electrical Trading', 'orders@voltpro.ph | 0917-555-0202', 'Quezon City, Metro Manila'),
(3, 'Pacific Hardware & Paint', 'info@pacifichw.ph | 0917-555-0303', 'Muntinlupa, Metro Manila');

INSERT INTO User (UserID, Username, PasswordHash, FullName, RoleID, SupplierID, Status) VALUES
(1, 'admin', '$2y$10$N449IbxIPBgYe2KbAKuas.VQKfhZ0xEfw1c.TVI5WjRUu3k15gHLe', 'Andrea Admin', 1, NULL, 'active'),
(2, 'manager1', '$2b$10$euvs4qrR/YXp3JfO8zUZp.xud8iOs.XkE5dUxJSaKEMggkCSXiCaC', 'Grace Manager', 2, NULL, 'active'),
(3, 'employee1', '$2b$10$4dI4c9GKNpmY4hd4SZkux.gnx6Xqysu7wCeM/rn/cxSYxhXQKtd.e', 'Ethan Employee', 3, NULL, 'active'),
(4, 'supplier_buildmax', '$2b$10$V7TflCi3pUWDWFOpa4Xonud5bh9q/IyLWjQu5opQLPD4Gzd1HCx.6', 'BuildMax Construction Supply', 4, 1, 'active'),
(5, 'supplier_voltpro', '$2b$10$V7TflCi3pUWDWFOpa4Xonud5bh9q/IyLWjQu5opQLPD4Gzd1HCx.6', 'VoltPro Electrical Trading', 4, 2, 'active'),
(6, 'supplier_pacific', '$2b$10$V7TflCi3pUWDWFOpa4Xonud5bh9q/IyLWjQu5opQLPD4Gzd1HCx.6', 'Pacific Hardware & Paint', 4, 3, 'active'),
(7, 'employee2', '$2b$10$4dI4c9GKNpmY4hd4SZkux.gnx6Xqysu7wCeM/rn/cxSYxhXQKtd.e', 'Paolo Parttime', 3, NULL, 'inactive');

INSERT INTO Product (ProductID, Name, CategoryID, SupplierID, UnitPrice, Quantity, MinThreshold) VALUES
(1, 'Portland Cement 40kg', 1, 1, 265.00, 120, 40),
(2, 'Common Nails 4" (1kg)', 2, 1, 95.00, 18, 30),
(3, 'Marine Plywood 1/4"', 1, 1, 520.00, 35, 15),
(4, 'Steel Bar 10mm x 6m', 1, 1, 210.00, 9, 25),
(5, 'Claw Hammer 16oz', 3, 3, 385.00, 22, 8),
(6, 'Measuring Tape 5m', 3, 3, 150.00, 40, 10),
(7, 'Cordless Drill 18V', 4, 2, 3450.00, 4, 5),
(8, 'Angle Grinder 4"', 4, 2, 1890.00, 7, 4),
(9, 'THHN Wire 3.5mm (75m roll)', 5, 2, 2650.00, 12, 6),
(10, 'LED Bulb 9W', 5, 2, 85.00, 0, 20),
(11, 'PVC Pipe 1/2" x 3m', 6, 3, 120.00, 60, 20),
(12, 'Teflon Tape', 6, 3, 25.00, 150, 40),
(13, 'Latex Paint White 4L', 7, 3, 780.00, 14, 15),
(14, 'Paint Brush 2"', 7, 3, 45.00, 80, 20),
(15, 'Wood Screws #8 (box of 100)', 2, 1, 160.00, 45, 15);

INSERT INTO Purchase_Order (OrderID, SupplierID, UserID, OrderDate, Status) VALUES
(1, 1, 2, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Received'),
(2, 2, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Pending'),
(3, 3, 1, DATE_SUB(NOW(), INTERVAL 6 DAY), 'Cancelled');

INSERT INTO Purchase_Order_Item (OrderID, ProductID, QuantityOrdered, UnitPrice) VALUES
(1, 1, 100, 250.00),
(1, 3, 30, 490.00),
(2, 7, 10, 3200.00),
(2, 10, 100, 70.00),
(3, 13, 20, 720.00);

INSERT INTO Stock_Transaction (ProductID, UserID, TransactionType, Quantity, TransactionDate) VALUES
(1, 2, 'Stock-In', 118, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(2, 2, 'Stock-In', 61, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(3, 2, 'Stock-In', 25, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(4, 2, 'Stock-In', 26, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(5, 2, 'Stock-In', 37, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(6, 2, 'Stock-In', 68, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(7, 2, 'Stock-In', 18, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(8, 2, 'Stock-In', 35, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(9, 2, 'Stock-In', 32, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(10, 2, 'Stock-In', 45, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(11, 2, 'Stock-In', 118, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(12, 2, 'Stock-In', 226, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(13, 2, 'Stock-In', 37, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(14, 2, 'Stock-In', 116, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(15, 2, 'Stock-In', 87, DATE_SUB(NOW(), INTERVAL 735 HOUR)),
(1, 3, 'Sale', 17, DATE_SUB(NOW(), INTERVAL 644 HOUR)),
(7, 3, 'Sale', 1, DATE_SUB(NOW(), INTERVAL 645 HOUR)),
(2, 3, 'Sale', 13, DATE_SUB(NOW(), INTERVAL 647 HOUR)),
(15, 3, 'Sale', 1, DATE_SUB(NOW(), INTERVAL 620 HOUR)),
(6, 3, 'Sale', 6, DATE_SUB(NOW(), INTERVAL 592 HOUR)),
(14, 3, 'Sale', 10, DATE_SUB(NOW(), INTERVAL 596 HOUR)),
(10, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 598 HOUR)),
(8, 3, 'Sale', 3, DATE_SUB(NOW(), INTERVAL 571 HOUR)),
(7, 3, 'Sale', 3, DATE_SUB(NOW(), INTERVAL 575 HOUR)),
(7, 3, 'Sale', 2, DATE_SUB(NOW(), INTERVAL 544 HOUR)),
(10, 3, 'Sale', 15, DATE_SUB(NOW(), INTERVAL 545 HOUR)),
(10, 3, 'Sale', 8, DATE_SUB(NOW(), INTERVAL 521 HOUR)),
(7, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 522 HOUR)),
(9, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 523 HOUR)),
(10, 3, 'Sale', 1, DATE_SUB(NOW(), INTERVAL 497 HOUR)),
(11, 3, 'Sale', 6, DATE_SUB(NOW(), INTERVAL 498 HOUR)),
(14, 3, 'Sale', 10, DATE_SUB(NOW(), INTERVAL 500 HOUR)),
(2, 3, 'Sale', 15, DATE_SUB(NOW(), INTERVAL 503 HOUR)),
(4, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 472 HOUR)),
(15, 3, 'Sale', 7, DATE_SUB(NOW(), INTERVAL 476 HOUR)),
(2, 3, 'Sale', 15, DATE_SUB(NOW(), INTERVAL 449 HOUR)),
(10, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 423 HOUR)),
(3, 3, 'Sale', 6, DATE_SUB(NOW(), INTERVAL 426 HOUR)),
(4, 3, 'Sale', 9, DATE_SUB(NOW(), INTERVAL 426 HOUR)),
(1, 3, 'Sale', 23, DATE_SUB(NOW(), INTERVAL 430 HOUR)),
(6, 3, 'Sale', 1, DATE_SUB(NOW(), INTERVAL 430 HOUR)),
(1, 3, 'Sale', 21, DATE_SUB(NOW(), INTERVAL 399 HOUR)),
(13, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 403 HOUR)),
(14, 3, 'Sale', 7, DATE_SUB(NOW(), INTERVAL 404 HOUR)),
(12, 3, 'Sale', 23, DATE_SUB(NOW(), INTERVAL 376 HOUR)),
(8, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 377 HOUR)),
(8, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 377 HOUR)),
(6, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 378 HOUR)),
(7, 3, 'Sale', 3, DATE_SUB(NOW(), INTERVAL 378 HOUR)),
(15, 3, 'Sale', 13, DATE_SUB(NOW(), INTERVAL 378 HOUR)),
(12, 3, 'Sale', 15, DATE_SUB(NOW(), INTERVAL 381 HOUR)),
(11, 3, 'Sale', 10, DATE_SUB(NOW(), INTERVAL 358 HOUR)),
(6, 3, 'Sale', 9, DATE_SUB(NOW(), INTERVAL 328 HOUR)),
(3, 3, 'Sale', 6, DATE_SUB(NOW(), INTERVAL 334 HOUR)),
(1, 2, 'Stock-In', 100, DATE_SUB(NOW(), INTERVAL 273 HOUR)),
(3, 2, 'Stock-In', 30, DATE_SUB(NOW(), INTERVAL 273 HOUR)),
(15, 3, 'Sale', 1, DATE_SUB(NOW(), INTERVAL 280 HOUR)),
(12, 3, 'Sale', 9, DATE_SUB(NOW(), INTERVAL 282 HOUR)),
(15, 3, 'Sale', 9, DATE_SUB(NOW(), INTERVAL 284 HOUR)),
(15, 3, 'Sale', 6, DATE_SUB(NOW(), INTERVAL 286 HOUR)),
(5, 3, 'Sale', 3, DATE_SUB(NOW(), INTERVAL 255 HOUR)),
(14, 3, 'Sale', 7, DATE_SUB(NOW(), INTERVAL 260 HOUR)),
(6, 3, 'Sale', 3, DATE_SUB(NOW(), INTERVAL 262 HOUR)),
(12, 3, 'Sale', 18, DATE_SUB(NOW(), INTERVAL 231 HOUR)),
(5, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 232 HOUR)),
(8, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 209 HOUR)),
(15, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 214 HOUR)),
(14, 3, 'Sale', 2, DATE_SUB(NOW(), INTERVAL 185 HOUR)),
(5, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 187 HOUR)),
(5, 3, 'Sale', 1, DATE_SUB(NOW(), INTERVAL 190 HOUR)),
(9, 3, 'Sale', 7, DATE_SUB(NOW(), INTERVAL 191 HOUR)),
(13, 3, 'Sale', 8, DATE_SUB(NOW(), INTERVAL 159 HOUR)),
(4, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 160 HOUR)),
(1, 3, 'Sale', 23, DATE_SUB(NOW(), INTERVAL 161 HOUR)),
(11, 3, 'Sale', 10, DATE_SUB(NOW(), INTERVAL 167 HOUR)),
(6, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 138 HOUR)),
(9, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 141 HOUR)),
(8, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 111 HOUR)),
(10, 3, 'Sale', 12, DATE_SUB(NOW(), INTERVAL 113 HOUR)),
(9, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 115 HOUR)),
(3, 3, 'Sale', 3, DATE_SUB(NOW(), INTERVAL 116 HOUR)),
(1, 3, 'Sale', 14, DATE_SUB(NOW(), INTERVAL 118 HOUR)),
(11, 3, 'Sale', 11, DATE_SUB(NOW(), INTERVAL 89 HOUR)),
(12, 3, 'Sale', 11, DATE_SUB(NOW(), INTERVAL 91 HOUR)),
(8, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 93 HOUR)),
(11, 3, 'Sale', 9, DATE_SUB(NOW(), INTERVAL 93 HOUR)),
(8, 3, 'Sale', 4, DATE_SUB(NOW(), INTERVAL 66 HOUR)),
(5, 3, 'Sale', 2, DATE_SUB(NOW(), INTERVAL 70 HOUR)),
(11, 3, 'Sale', 12, DATE_SUB(NOW(), INTERVAL 44 HOUR)),
(3, 3, 'Sale', 5, DATE_SUB(NOW(), INTERVAL 45 HOUR)),
(13, 3, 'Sale', 11, DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO Supplier_Submission (SubmissionID, SupplierID, ProductID, AvailableQuantity, UnitPrice, DeliveryDate, DeliveryNotes, Status, SubmittedAt, ReviewedBy, ReviewedAt, ReviewNotes) VALUES
(1, 1, 2, 500, 88.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Fresh batch of 4 inch nails, truck leaves Friday.', 'Pending', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL, NULL),
(2, 2, 10, 300, 68.00, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Can include the LED bulbs with your open order.', 'Pending', DATE_SUB(NOW(), INTERVAL 12 HOUR), NULL, NULL, NULL),
(3, 3, 12, 400, 22.00, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Bulk price for 400 rolls and up.', 'Approved', DATE_SUB(NOW(), INTERVAL 4 DAY), 2, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Good price, order when stock gets low.'),
(4, 2, 9, 20, 2900.00, DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Price went up because copper costs more.', 'Rejected', DATE_SUB(NOW(), INTERVAL 5 DAY), 1, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Too expensive compared to our usual price.');

INSERT INTO Audit_Log (UserID, Action, TargetTable, TargetID, Timestamp) VALUES
(1, 'Created user account \'manager1\'', 'User', 2, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(1, 'Created user account \'employee1\'', 'User', 3, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(1, 'Created user account \'employee2\'', 'User', 7, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 'Created product \'Portland Cement 40kg\'', 'Product', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 'Created purchase order #1', 'Purchase_Order', 1, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 'Marked purchase order #1 as Received', 'Purchase_Order', 1, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(1, 'Created purchase order #3', 'Purchase_Order', 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 'Marked purchase order #3 as Cancelled', 'Purchase_Order', 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 'Created purchase order #2', 'Purchase_Order', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'Approved supplier submission #3', 'Supplier_Submission', 3, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'Rejected supplier submission #4', 'Supplier_Submission', 4, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 'Disabled user account \'employee2\'', 'User', 7, DATE_SUB(NOW(), INTERVAL 8 DAY));
