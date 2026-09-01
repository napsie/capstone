-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: capstone1
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `hardcopy_batches`
--

DROP TABLE IF EXISTS `hardcopy_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hardcopy_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(40) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `handover_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Draft',
  `submitted_by_name` varchar(150) NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `released_at` datetime DEFAULT NULL,
  `received_by_user_id` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `idx_hardcopy_barangay` (`barangay`),
  KEY `idx_hardcopy_status` (`status`),
  KEY `idx_hardcopy_handover` (`handover_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hardcopy_batches`
--

LOCK TABLES `hardcopy_batches` WRITE;
/*!40000 ALTER TABLE `hardcopy_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `hardcopy_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hardcopy_batch_items`
--

DROP TABLE IF EXISTS `hardcopy_batch_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hardcopy_batch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `application_id` varchar(255) NOT NULL,
  `document_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hardcopy_batch_item` (`batch_id`,`application_id`),
  KEY `idx_hardcopy_item_application` (`application_id`),
  CONSTRAINT `fk_hardcopy_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `hardcopy_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hardcopy_batch_items`
--

LOCK TABLES `hardcopy_batch_items` WRITE;
/*!40000 ALTER TABLE `hardcopy_batch_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `hardcopy_batch_items` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-26 20:45:10
