-- Table for tracking service purchases
CREATE TABLE IF NOT EXISTS service_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    purchase_date DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
);

-- Table for tracking additional service quotas purchased by residents
CREATE TABLE IF NOT EXISTS resident_service_quotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    month VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    additional_quota INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_resident_service_month (resident_id, service_name, month)
);

-- Create indexes for better performance
CREATE INDEX idx_purchase_date ON service_purchases(purchase_date);
CREATE INDEX idx_resident_month ON resident_service_quotas(resident_id, month);
