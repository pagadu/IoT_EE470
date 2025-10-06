/* =========================================================
   EE 470 – Part 1 (Complete DB setup)
   Includes: BOOK, PRICE, SENSOR tables, and VIEW
   ========================================================= */

-- Safety: allow dropping in any order
SET FOREIGN_KEY_CHECKS = 0;

-- ---- Views (drop if exist)
DROP VIEW IF EXISTS sensor_combined;

-- ---- Tables (drop if exist)
DROP TABLE IF EXISTS sensor_data;
DROP TABLE IF EXISTS sensor_register;
DROP TABLE IF EXISTS PRICE;
DROP TABLE IF EXISTS BOOK;

SET FOREIGN_KEY_CHECKS = 1;

/* ================================
   A) BOOK / PRICE (Part 1.1 – 1.2)
   ================================ */
CREATE TABLE BOOK (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  Title VARCHAR(50),
  Author VARCHAR(30),
  Year  INT
);

INSERT INTO BOOK (Title, Author, Year) VALUES
('IoT Fundamentals', 'Farid Farahmand', 2022),
('Smart Sensors',     'Alexander Pagaduan', 2023),
('Database Systems',  'Elmasri',            2021);

CREATE TABLE PRICE (
  id INT AUTO_INCREMENT PRIMARY KEY,
  BookTitle VARCHAR(50),
  Price DECIMAL(6,2)
);

INSERT INTO PRICE (BookTitle, Price) VALUES
('IoT Fundamentals', 50.00),
('Smart Sensors',    35.00),
('Database Systems', 60.00);

/* Example JOIN used in 1.2 (kept as reference)
SELECT BOOK.Title, BOOK.Author, BOOK.Year, PRICE.Price
FROM BOOK
JOIN PRICE ON BOOK.Title = PRICE.BookTitle;
*/


/* ============================================
   B) SENSOR REGISTER / SENSOR DATA (Part 1.3)
   Requirements:
   - Start time: 2022-10-01 11:00
   - Every 30 minutes
   - 5 sensors, different manufacturers
   - 4 readings per sensor
   - FK: data only for registered nodes
   - VIEW name: sensor_combined
   ============================================ */

-- Parent table: registered sensors
CREATE TABLE sensor_register (
  node_name    VARCHAR(10) PRIMARY KEY,     -- node_1, node_2, ...
  manufacturer VARCHAR(20),
  longitude    DOUBLE,
  latitude     DOUBLE
);

INSERT INTO sensor_register (node_name, manufacturer, longitude, latitude) VALUES
('node_1','Sony',  -122.708, 38.341),
('node_2','Bosch', -122.711, 38.342),
('node_3','Intel', -122.713, 38.343),
('node_4','TI',    -122.714, 38.345),
('node_5','NXP',   -122.716, 38.347);

-- Child table: sensor measurements
CREATE TABLE sensor_data (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  node_name     VARCHAR(10) NOT NULL,
  time_received DATETIME     NOT NULL,
  temperature   DECIMAL(6,2),
  humidity      DECIMAL(6,2),
  CONSTRAINT fk_node FOREIGN KEY (node_name)
    REFERENCES sensor_register(node_name)
);

-- Data start: 2022-10-01 11:00
-- Interval: 30 minutes
-- Four readings per node (11:00, 11:30, 12:00, 12:30)

-- node_1
INSERT INTO sensor_data (node_name, time_received, temperature, humidity) VALUES
('node_1','2022-10-01 11:00:00',25,40),
('node_1','2022-10-01 11:30:00',26,42),
('node_1','2022-10-01 12:00:00',27,45),
('node_1','2022-10-01 12:30:00',26,44);

-- node_2
INSERT INTO sensor_data (node_name, time_received, temperature, humidity) VALUES
('node_2','2022-10-01 11:00:00',24,39),
('node_2','2022-10-01 11:30:00',25,40),
('node_2','2022-10-01 12:00:00',25,41),
('node_2','2022-10-01 12:30:00',26,42);

-- node_3
INSERT INTO sensor_data (node_name, time_received, temperature, humidity) VALUES
('node_3','2022-10-01 11:00:00',23,38),
('node_3','2022-10-01 11:30:00',24,39),
('node_3','2022-10-01 12:00:00',24,40),
('node_3','2022-10-01 12:30:00',25,41);

-- node_4
INSERT INTO sensor_data (node_name, time_received, temperature, humidity) VALUES
('node_4','2022-10-01 11:00:00',26,43),
('node_4','2022-10-01 11:30:00',27,44),
('node_4','2022-10-01 12:00:00',27,45),
('node_4','2022-10-01 12:30:00',28,46);

-- node_5
INSERT INTO sensor_data (node_name, time_received, temperature, humidity) VALUES
('node_5','2022-10-01 11:00:00',22,37),
('node_5','2022-10-01 11:30:00',23,38),
('node_5','2022-10-01 12:00:00',23,39),
('node_5','2022-10-01 12:30:00',24,40);

-- VIEW combining both tables
CREATE OR REPLACE VIEW sensor_combined AS
SELECT
  d.node_name,
  d.time_received,
  d.temperature,
  d.humidity,
  r.manufacturer,
  r.longitude,
  r.latitude
FROM sensor_data d
JOIN sensor_register r
  ON d.node_name = r.node_name;

/* Proof of FK (should fail; run manually in phpMyAdmin to screenshot)
INSERT INTO sensor_data (node_name, time_received, temperature, humidity)
VALUES ('node_X','2022-10-01 13:00:00',28,50);
*/
