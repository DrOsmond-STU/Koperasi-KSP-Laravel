-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: koperasi_sejahtera_bersama
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounting_periods`
--

DROP TABLE IF EXISTS `accounting_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `closed_by` bigint unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounting_periods_branch_id_period_start_period_end_unique` (`branch_id`,`period_start`,`period_end`),
  KEY `accounting_periods_closed_by_foreign` (`closed_by`),
  CONSTRAINT `accounting_periods_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounting_periods_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_periods`
--

LOCK TABLES `accounting_periods` WRITE;
/*!40000 ALTER TABLE `accounting_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounting_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_branding_settings`
--

DROP TABLE IF EXISTS `app_branding_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_branding_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `app_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_branding_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `app_branding_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_branding_settings`
--

LOCK TABLES `app_branding_settings` WRITE;
/*!40000 ALTER TABLE `app_branding_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `app_branding_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_thresholds`
--

DROP TABLE IF EXISTS `approval_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_thresholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `threshold_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `approval_thresholds_module_unique` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_thresholds`
--

LOCK TABLES `approval_thresholds` WRITE;
/*!40000 ALTER TABLE `approval_thresholds` DISABLE KEYS */;
INSERT INTO `approval_thresholds` VALUES (1,'pembelian',5000000.00,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(2,'aktiva_tetap',10000000.00,'2026-07-18 13:09:48','2026-07-18 13:09:48');
/*!40000 ALTER TABLE `approval_thresholds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint unsigned DEFAULT NULL,
  `actor_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `before` json DEFAULT NULL,
  `after` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_id_foreign` (`actor_id`),
  KEY `audit_logs_branch_id_foreign` (`branch_id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_action_index` (`action`),
  CONSTRAINT `audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,NULL,NULL,'create','App\\Models\\Branch',1,NULL,'{\"id\": 1, \"code\": \"PST\", \"name\": \"Kantor Pusat\", \"address\": \"Jl. Utama No. 1\", \"is_active\": true, \"created_at\": \"2026-07-18 20:10:43\", \"updated_at\": \"2026-07-18 20:10:43\", \"operational_date\": \"2023-07-18 00:00:00\"}','127.0.0.1','Symfony','2026-07-18 13:10:43'),(2,NULL,NULL,NULL,'create','App\\Models\\Branch',2,NULL,'{\"id\": 2, \"code\": \"PLK\", \"name\": \"Cabang Palangka Raya\", \"address\": \"Jl. Cabang Palangka Raya\", \"is_active\": true, \"created_at\": \"2026-07-18 20:10:43\", \"updated_at\": \"2026-07-18 20:10:43\", \"operational_date\": \"2024-07-18 00:00:00\"}','127.0.0.1','Symfony','2026-07-18 13:10:43'),(3,NULL,NULL,NULL,'create','App\\Models\\Branch',3,NULL,'{\"id\": 3, \"code\": \"BTM\", \"name\": \"Cabang Batam\", \"address\": \"Jl. Cabang Batam\", \"is_active\": true, \"created_at\": \"2026-07-18 20:10:43\", \"updated_at\": \"2026-07-18 20:10:43\", \"operational_date\": \"2025-07-18 00:00:00\"}','127.0.0.1','Symfony','2026-07-18 13:10:43'),(4,NULL,NULL,NULL,'create','App\\Models\\User',1,NULL,'{\"id\": 1, \"name\": \"Admin Test\", \"email\": \"admin@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$V2R0bENVRnFGTnVRaEs4ZA$ZzudCWmmNmuEQqsq1a5UaXNT9rP2dvSrPMkQWxYtrwE\", \"created_at\": \"2026-07-18 20:10:56\", \"updated_at\": \"2026-07-18 20:10:56\"}','127.0.0.1','Symfony','2026-07-18 13:10:56'),(5,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',1,NULL,'{\"id\": 1, \"user_id\": 1, \"created_at\": \"2026-07-18 20:10:57\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:10:57\"}','127.0.0.1','Symfony','2026-07-18 13:10:57'),(6,NULL,NULL,NULL,'create','App\\Models\\User',2,NULL,'{\"id\": 2, \"name\": \"Test Admin Sistem\", \"email\": \"admin.sistem@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$LjZyVXowZ1NINVBOdkl4Mw$6BaoVQYqpulyOVs/whJSHRD0/9ctLZurUkouIPPyiCw\", \"created_at\": \"2026-07-18 20:11:06\", \"updated_at\": \"2026-07-18 20:11:06\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:06.219387Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:06'),(7,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',2,NULL,'{\"id\": 2, \"user_id\": 2, \"created_at\": \"2026-07-18 20:11:06\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:06\"}','127.0.0.1','Symfony','2026-07-18 13:11:07'),(8,NULL,NULL,NULL,'create','App\\Models\\User',3,NULL,'{\"id\": 3, \"name\": \"Test Manajer\", \"email\": \"manajer@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$SDZzcnI0RUo0Z25PRGJ3Vg$aI63lYDbNFO/cYR/Wu32l+cjF7nn7CYORpjkB0gMyN8\", \"created_at\": \"2026-07-18 20:11:07\", \"updated_at\": \"2026-07-18 20:11:07\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:07.095505Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:07'),(9,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',3,NULL,'{\"id\": 3, \"user_id\": 3, \"created_at\": \"2026-07-18 20:11:07\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:07\"}','127.0.0.1','Symfony','2026-07-18 13:11:07'),(10,NULL,NULL,NULL,'create','App\\Models\\User',4,NULL,'{\"id\": 4, \"name\": \"Test Teller\", \"email\": \"teller@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$TjhaR2psb3RCZkVqSHZReQ$AgtKBRHMYjnTN3GQpo+3MaLPuZ+8/LFg4UUmgSuM+QA\", \"created_at\": \"2026-07-18 20:11:08\", \"updated_at\": \"2026-07-18 20:11:08\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:07.810909Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:08'),(11,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',4,NULL,'{\"id\": 4, \"user_id\": 4, \"created_at\": \"2026-07-18 20:11:08\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:08\"}','127.0.0.1','Symfony','2026-07-18 13:11:08'),(12,NULL,NULL,NULL,'create','App\\Models\\User',5,NULL,'{\"id\": 5, \"name\": \"Test Petugas Kredit\", \"email\": \"kredit@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$WGhWS1VGQ1dpd0t6Q2twdw$+kO6wCeoUThtDAMiGyva+DxQWMnFAqTZ31SBo5zfTyQ\", \"created_at\": \"2026-07-18 20:11:08\", \"updated_at\": \"2026-07-18 20:11:08\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:08.556774Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:09'),(13,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',5,NULL,'{\"id\": 5, \"user_id\": 5, \"created_at\": \"2026-07-18 20:11:09\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:09\"}','127.0.0.1','Symfony','2026-07-18 13:11:09'),(14,NULL,NULL,NULL,'create','App\\Models\\User',6,NULL,'{\"id\": 6, \"name\": \"Test Petugas UPF\", \"email\": \"upf@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$NDhKL2daNWc4QUNTS0Raeg$h/oA40uF2gubNj+AF6EY+7ZRj+EdQg0/FzYmSQTwnnA\", \"created_at\": \"2026-07-18 20:11:09\", \"updated_at\": \"2026-07-18 20:11:09\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:09.449484Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:09'),(15,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',6,NULL,'{\"id\": 6, \"user_id\": 6, \"created_at\": \"2026-07-18 20:11:10\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:10\"}','127.0.0.1','Symfony','2026-07-18 13:11:10'),(16,NULL,NULL,NULL,'create','App\\Models\\User',7,NULL,'{\"id\": 7, \"name\": \"Test Bendahara\", \"email\": \"bendahara@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$ZU5qbzdyOEttWUJkVkRpdw$Q0MUK+HDg71iFqQ6/C/xBNe2tIiDhFXDLOQiotuCzxU\", \"created_at\": \"2026-07-18 20:11:10\", \"updated_at\": \"2026-07-18 20:11:10\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:10.190574Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:10'),(17,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',7,NULL,'{\"id\": 7, \"user_id\": 7, \"created_at\": \"2026-07-18 20:11:10\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:10\"}','127.0.0.1','Symfony','2026-07-18 13:11:10'),(18,NULL,NULL,NULL,'create','App\\Models\\User',8,NULL,'{\"id\": 8, \"name\": \"Test Pengawas\", \"email\": \"pengawas@ksp.test\", \"password\": \"$argon2id$v=19$m=65536,t=4,p=1$MzBMSklWekI1MC4wNEljVw$ShXC3lMuB00volQI7HN96adV1ejxAjiArmrcQDf556c\", \"created_at\": \"2026-07-18 20:11:11\", \"updated_at\": \"2026-07-18 20:11:11\", \"two_factor_confirmed_at\": \"2026-07-18T13:11:10.888268Z\"}','127.0.0.1','Symfony','2026-07-18 13:11:11'),(19,NULL,NULL,NULL,'create','App\\Models\\UserBranchScope',8,NULL,'{\"id\": 8, \"user_id\": 8, \"created_at\": \"2026-07-18 20:11:11\", \"scope_type\": \"all\", \"updated_at\": \"2026-07-18 20:11:11\"}','127.0.0.1','Symfony','2026-07-18 13:11:11'),(20,NULL,NULL,1,'create','App\\Models\\JournalEntry',1,NULL,'{\"id\": 1, \"branch_id\": 1, \"source_id\": null, \"created_at\": \"2026-07-18 20:11:11\", \"created_by\": 7, \"entry_date\": \"2026-05-19 00:00:00\", \"updated_at\": \"2026-07-18 20:11:11\", \"description\": \"Modal awal koperasi (data demo)\", \"source_type\": null}','127.0.0.1','Symfony','2026-07-18 13:11:11'),(21,NULL,NULL,1,'create','App\\Models\\Member',1,NULL,'{\"id\": 1, \"nik\": \"eyJpdiI6ImxMMkI0MG41L0dNcjYxUVVQNDY4aHc9PSIsInZhbHVlIjoiMTdzVHd3WU9MRlY5L3N4dE9raXphNHFkaUxsU1F6djQ3QjY3M1NORnZROD0iLCJtYWMiOiI0YmY2ZWRmYzY1ODFkNWQ0NzQwODJhYWI4NmQ0YzMyOGNjNmFjZDQwYjhiZDljM2Y0ZTQ5YjMyN2Q2ZDVlYjUyIiwidGFnIjoiIn0=\", \"name\": \"Hendra Wijaya\", \"email\": \"eyJpdiI6IldBbDhIeFEvc0JxbTg3cmFCcXl3TXc9PSIsInZhbHVlIjoiNklvTXl2OWpHM0tJZ0d4ZFlVbWI3SkRJdStrZ3RRSFR4ZmUwb2V1MG1pYz0iLCJtYWMiOiI0MGIzMDk0YTE3MzJkY2RlZmQ5OWEzOGQ0ZTJjYWNmMWVjOTQ5M2E1NzJiMDA0NDQxMzBlNDA1NWNhMGQxYWQzIiwidGFnIjoiIn0=\", \"phone\": \"eyJpdiI6Ilc5R1F3b3VacXVHTE1INDVxVHAxTnc9PSIsInZhbHVlIjoiVzJuTnZJSWxNRVJDZmlFL2lucHIwdz09IiwibWFjIjoiZGY4ODliNmYxOWQ0MDBjM2QyNTJiMjhhOTIyNGIzYTVkYjBiMzI4ZTJkOGQ4ZThlY2Q5NjM1YzFlZTEwOWIwOCIsInRhZyI6IiJ9\", \"status\": \"aktif\", \"address\": \"eyJpdiI6Ik5EeE1PR2o5dWo3VXdtRGgyRHNNOVE9PSIsInZhbHVlIjoiTzVKYmhMU29BVGliTkZ2SmwrckJDVlJPWE5BSzUwU2tMQ3J6M2wwNWR3N0NCSU1BY0VpaVVnQXdzNU1DRElja2pKWGNDLzRwa2ZXbk5RMEJ4RDk0L2c9PSIsIm1hYyI6IjAxNjMwZDJkZmU5ZmNiMTVlYWUzYjNkNzM1YWQ5MmQwMjYyNzFkNzFhZDQ3NTFmMjlkOWVkZDQ4OTc3N2I1YWYiLCJ0YWciOiIifQ==\", \"branch_id\": 1, \"joined_at\": \"2026-03-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:11\", \"updated_at\": \"2026-07-18 20:11:11\", \"date_of_birth\": \"eyJpdiI6ImRWdjVsK0MrMUlGVW1TQThVYUJPdlE9PSIsInZhbHVlIjoieXcwejVKYzd2OWtBdWE2QTMvSHVsdz09IiwibWFjIjoiY2VkMzJjYzFiNjMzMzlkYTNlZTI4MmQ3MGQyYzI0Y2MxMzUyYTJlMWI1ZTc1MGI2MzQ1YjNjYjIzZjJiZTI3MiIsInRhZyI6IiJ9\", \"member_number\": \"AGT-0101\", \"member_type_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:11'),(22,NULL,NULL,1,'create','App\\Models\\Member',2,NULL,'{\"id\": 2, \"nik\": \"eyJpdiI6IlJuUWxORzZJTEJiaDZ6ZnZpd2pBalE9PSIsInZhbHVlIjoiYjlQU1M2L1Q2c0VkdmdZV1BFbVFFVjVMbnUyVnpPZDBXQzkyczU3R0k3TT0iLCJtYWMiOiI5MzlmYTQ1ZWNiMGNjYzExN2Y4NWE2ZjQ2MzI3NTdkMDk1NjIzZDllYTlhZmMzZWZmMjQ5MWFiNGI4ZWZkOGJkIiwidGFnIjoiIn0=\", \"name\": \"Rini Marlina\", \"email\": \"eyJpdiI6Ii9QQURlTkk5SElZdUViMzJ2L2VEUkE9PSIsInZhbHVlIjoiOXhuVm5KK0xRaVlBRFBuT1poU3B5d3QxOCtDejh2Y3ZlU1BndnVGeU5OVT0iLCJtYWMiOiI5NTYxMTU1N2ZjNWQ3MzkwNmE0NmJhZjI3NDhjZTU4NzlmNjYxZjA1OWEzMzBkYWFhMmNkNDJlMDIyOGYxMjg0IiwidGFnIjoiIn0=\", \"phone\": \"eyJpdiI6ImUyYXM0OHgrQ24rVHNRTjBPVlZVaVE9PSIsInZhbHVlIjoiMVAvT2lIempWdGkwY0Nxb1J0SzI1QT09IiwibWFjIjoiNTk4MDE0YjVjMTgwYTI4MTRhYjkxMWY1NjYwNjY2MDc3ZjAxMWRiOWE1MWIwM2QzNDRiMDE3NmJjZjhjZGM4NyIsInRhZyI6IiJ9\", \"status\": \"aktif\", \"address\": \"eyJpdiI6IkkxWk1NMWg3NkRJejFXREZiZVFuRnc9PSIsInZhbHVlIjoiMW8wUXpUejdidysrVWxsbWRvTUlYY2tSb0RrTWF4cnMrdGcyUG1VS0JlemY5eE9jV0pLSWlWRkxqWkpnQXI3TSIsIm1hYyI6ImZhYWY4OGQ2NzBlZTNlZmIzMzQ0N2I4MmM0NjkyMTNmZTVhZjljZDhhYmY3OTRlZmI2ZDU4MGE3MDczNDA1YWMiLCJ0YWciOiIifQ==\", \"branch_id\": 1, \"joined_at\": \"2025-12-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:11\", \"updated_at\": \"2026-07-18 20:11:11\", \"date_of_birth\": \"eyJpdiI6InFoUzZsQmZJd3pVTkxraW5uL0ZITkE9PSIsInZhbHVlIjoiQnpGZ2lva3dWakREYkNCWm9ycVJEZz09IiwibWFjIjoiZDVmNDEzODJlMmJiOGJlZTIyYjIxOTVhMjdlM2U5ZDRlNzM1YjBjYzUxMDQzODBhM2I1MDM1MjlhZGQxNjgxYiIsInRhZyI6IiJ9\", \"member_number\": \"AGT-0102\", \"member_type_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:12'),(23,NULL,NULL,2,'create','App\\Models\\Member',3,NULL,'{\"id\": 3, \"nik\": \"eyJpdiI6IlgvY2RXMGphUktKK01Nbm14QTFXblE9PSIsInZhbHVlIjoiNWVrZGRCdDZRQUdQOVlaWTQvQklqTmx0ODNLYVhGbmE3ZEhyRGVYY0Zodz0iLCJtYWMiOiI2Nzc2OGEzYmI2MTljNWI5YjQwMTU0YTM4ZTU0NjllYzM2ZWJiZjIwMjcxYTg1ZjY2NDMxM2MzNGExMjM1N2NjIiwidGFnIjoiIn0=\", \"name\": \"Agus Setiawan\", \"email\": \"eyJpdiI6IjRDUXVROTVCVDZMUHF2dDlmL3NDQ1E9PSIsInZhbHVlIjoiblFva1BYQ0JsZU90cStxSEZYTkFYTUZxSlBlQjZNc1g4eS9RZnpvbENyST0iLCJtYWMiOiJhNDZjNGVhZTY5OWU1MDA5MDc5ZTk5NjRhMjNjZjUxYTY1NjhhMTg0NTQxZTUwYWRlMmNlMjdiZDYyNDg0Y2Q4IiwidGFnIjoiIn0=\", \"phone\": \"eyJpdiI6IkpKZnMwOGovZlI4NnJVRVd0R3AxWFE9PSIsInZhbHVlIjoiZkkzcjRNaFc4ckx1ZEtmcVRPZTNuUT09IiwibWFjIjoiOWU5M2I3NmI4MTNlNzAyYTFkZDNhNGM3MjU1NzNlMjg1ZjQ4MTlmZjRjZmRlMWI5MTMxNGE1NzkyNGRkNGUxNCIsInRhZyI6IiJ9\", \"status\": \"aktif\", \"address\": \"eyJpdiI6IjkySEtoRFpVS2pFcEF3RW5KTEtkcEE9PSIsInZhbHVlIjoiODYrOE5PcjlzbG1PSExPYTY2NkNtbGkzUGZHNFN3US9RNFBRcCtpalNYQ0JVN3ZWKzlEWERaN1VUL3JxS1FVZiIsIm1hYyI6Ijc2MzA5ZmIyOWU2MTYzM2MxYTU3ODNkYjM1OGQzN2U2NzEwZjAyODE1NDViNWY5YmJkYmNhNTM2MzE2ZTY5ZDciLCJ0YWciOiIifQ==\", \"branch_id\": 2, \"joined_at\": \"2026-03-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:12\", \"updated_at\": \"2026-07-18 20:11:12\", \"date_of_birth\": \"eyJpdiI6IlJvMjhwNGJjWW1UL1R2ZVhUamFXT2c9PSIsInZhbHVlIjoiMjhoUHVacFE2Uk1GaXR2R09YS3BRZz09IiwibWFjIjoiZTNhNWJjYzA2MjI5OWNjNTVmYmIzZjIxN2Y5ZDAxYWY0MTBlNTFhMmE4NTg0NTQyN2QyNjU1YjBjYzYzMjY1OSIsInRhZyI6IiJ9\", \"member_number\": \"AGT-0103\", \"member_type_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:12'),(24,NULL,NULL,3,'create','App\\Models\\Member',4,NULL,'{\"id\": 4, \"nik\": \"eyJpdiI6Ii9DVnJkN0p1SG5udkUzbi8vYXlYWEE9PSIsInZhbHVlIjoiQlcwcTN2VFNyWWFpZUFqRSszUHNHa2h1SGIvL3BqV1Z1bkpSQ3FQZEhFTT0iLCJtYWMiOiI5MmIzZmQ3M2ZjZWVlZTdiZjY0NjAzOTBkYjEwOGZkMzY4MTQ5YWJjNzVjMzFkOWNiMDFiYTNhYmQ3NzMwZGI4IiwidGFnIjoiIn0=\", \"name\": \"Yuliana Putri\", \"email\": \"eyJpdiI6InZzaUxweVdjRG05M3dES1F3UXpyNkE9PSIsInZhbHVlIjoiVjdkYXRDZUxOTk9zQWxmcXVPbmdNdkR6eXNZNXpXZUxTUGxhWnBoZTk1ST0iLCJtYWMiOiJjYWJiZGY4MTRhYWNiOTI1NzE5Y2I1MDkyMWMxZDA5YzcyYWRiNDgwZDA4NzQ3NzA5M2EzMTIzMmNmYzEzMDYwIiwidGFnIjoiIn0=\", \"phone\": \"eyJpdiI6IlZ6Umw1b2VQeWxESjErR0NLaEdQSnc9PSIsInZhbHVlIjoiZDFFc09WdWxOQXBEZW5DMzgxL1o0Zz09IiwibWFjIjoiNzRhNjg5NzRjOTYzMGNkYzkwYmFiY2M4Mjk1NGI3NmY0MDY5YTVjYjY1ZjMxZmQ4ODRmNTMyNDYxYjBlYzJkZiIsInRhZyI6IiJ9\", \"status\": \"aktif\", \"address\": \"eyJpdiI6ImFrMjlmLzJweThHUHNXbzBxK1BPV0E9PSIsInZhbHVlIjoiOHFjMHh1Q1AxYnRFWm1WNHA5cUtVcW5QMWJBTGhGRW5kYlZyNWZwZWVQZE9uejhaRUc0dzRSTmNLcWpmRVZzMiIsIm1hYyI6IjhmMDE5MzA0NGE1MWZiZTIzYTQ3YTBkMmUwNmFmYTMwZWU1NjdkNmE3ODFjNGU0MzAzYzYwZTQzMDhjMjE4NGQiLCJ0YWciOiIifQ==\", \"branch_id\": 3, \"joined_at\": \"2025-12-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:12\", \"updated_at\": \"2026-07-18 20:11:12\", \"date_of_birth\": \"eyJpdiI6ImQ0S01FQVlaeWhkQWY4aS9BdldoV2c9PSIsInZhbHVlIjoiUzZoNmlmLzB6Y3lhRUJsTkpnTXZ5UT09IiwibWFjIjoiMGI4YWM0MGQ0YzFhMTg0NmFkNWEwMmRmMTg3NjZkMTVhODQ1YzM3MDI3NmM4ZjMzODQ3NGMxOTIyOTY4MzNhZCIsInRhZyI6IiJ9\", \"member_number\": \"AGT-0104\", \"member_type_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:12'),(25,NULL,NULL,1,'create','App\\Models\\Member',5,NULL,'{\"id\": 5, \"nik\": \"eyJpdiI6Ii9wUlFmNWRXSlhGdHdyWmJlVUlleEE9PSIsInZhbHVlIjoicGp2bmNFQS9nZWxkdnJZeStEL3pJQWYxdlNUSjVKeUxPNDRaQ0hUdjR1TT0iLCJtYWMiOiI2YjdjNGM4NjljODYzNjI0ZDQyOTAwZmQ2MzE5MDY0NzUxYzhlMjUyZGI5OGYwYmMwYmYxZjFjNjlkMmUwNzBmIiwidGFnIjoiIn0=\", \"name\": \"Toko Sumber Makmur (Kios)\", \"email\": \"eyJpdiI6IjNkbDl2Zml5MGhmclVackloQ2J0VUE9PSIsInZhbHVlIjoiMXdQT25lQUZYTlozalRSbEFYQ2JCcjdCa0I3VSs0SXpoOU1kcktrVHRRUT0iLCJtYWMiOiJkNTQ2YWMzNzUxYjhlZTlmNmZjMGRhZTczYTgyODgwYzhhMmRlMWQwZGFlNDBmYjc2ZDljN2IzODQ5NmJmNWY5IiwidGFnIjoiIn0=\", \"phone\": \"eyJpdiI6IlBxbEJ1UnZMOVMzRVBYM0RJelo1ZUE9PSIsInZhbHVlIjoiZTNHZnpoWmRBS09pOGRiK3lYcmxsUT09IiwibWFjIjoiOTMyNjE1ZjNmYjQxMGYyYmY2OGJmMzJlNTY2ODQ1ZGZhYjc2MWU5N2UxZWFjODdlYmViNTk3Mjg1NDY4ODNjZiIsInRhZyI6IiJ9\", \"status\": \"aktif\", \"address\": \"eyJpdiI6IlZhaWRmczhqSkR1OXdmZXNGR25TcVE9PSIsInZhbHVlIjoiR1VLY3BxZ0U1OWMydk5LUTJLN2E0enlmNEhKZUdBVzZORGtlalRmeXdEbTZtbDdzdlFEdUJjU1ZlRWQwZ0NsbSIsIm1hYyI6IjFlY2Q5MWUyODY3OTE1ODFkYzcyYzFmZmFiNmVhYTk1OGFiYTI0MGMzMTJiMDJlODczN2FjYzc0ZmJhYzI1NzgiLCJ0YWciOiIifQ==\", \"branch_id\": 1, \"joined_at\": \"2025-05-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:12\", \"updated_at\": \"2026-07-18 20:11:12\", \"date_of_birth\": \"eyJpdiI6IlF0eFZqeEQzWUpwbWlFanlxRy85Nmc9PSIsInZhbHVlIjoic3ZIRFREbWdwMzdESHJEUUtpTjI5Zz09IiwibWFjIjoiNjE3ZWNjZDA3NWQ3Y2U0MWNiMzc3NGVjOGJhNzgzZGRhMGQzOWVlNDg1MGY1ZTRmNWQ3MzI0ODc5MGEyNWFmYyIsInRhZyI6IiJ9\", \"member_number\": \"AGT-0105\", \"member_type_id\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:12'),(26,NULL,NULL,1,'create','App\\Models\\Member',6,NULL,'{\"id\": 6, \"nik\": \"eyJpdiI6IkVubFFSUnlBU1g5c0VCS2Z4eFpiY0E9PSIsInZhbHVlIjoiTE9kelVoMFJRWDFFSGxPWGtUbmJKdFczak91RmRnRHF5cVhZSTlFNWFVST0iLCJtYWMiOiI0MDMxZTE0MzNjMmUwNGMwM2M1YmUwYTM0YzdkMTE4ZWM4YzIwYmM3MWZhNjQwZDU3MjYwY2IxMDJjODdhZDM0IiwidGFnIjoiIn0=\", \"name\": \"Warung Blok C-12\", \"email\": \"eyJpdiI6IkpBVGFYTHdTTEZOd053NzNDOVpBYlE9PSIsInZhbHVlIjoiWjFVVkxrdmNodUtMc3V3SXJSWHlxTmthcjJjcFg0UEE0L2drbDA3ZERXYz0iLCJtYWMiOiIzNThjZjc0NTZjMTk4MjYwMTdlZDlhMmM1NTQzMDUwZDk5MWQxNzc1YmUwMjZmOTcwNmZlMzAyYjIyOWE0NDM2IiwidGFnIjoiIn0=\", \"phone\": \"eyJpdiI6ImlKOFpTdHh2c0cvUHBhY21wTlpkU0E9PSIsInZhbHVlIjoiV09uN3oxOEhvdnFGcS9HcjRTc1k3UT09IiwibWFjIjoiYzlhNTQwMTg4MDdjNWJhMTVlNzA0M2Y1NDcxMDMzNzJlMzE2MWVlZDRkOWQ1NGY5NTQxNTI4NDFiYzdiYTQwMSIsInRhZyI6IiJ9\", \"status\": \"aktif\", \"address\": \"eyJpdiI6Ikp2am1Vbk4wU21aN0p1QTRuOUF4Q3c9PSIsInZhbHVlIjoiRVArR0ZmMEVZZ29RVWh0NHJCMUpEaVZITWlhL1dZQmphaEpzZXFac01nTWVvWDRxOHVGdU96Q2xxYkM3WTJKWSIsIm1hYyI6ImU4NzkzOTIxODFmMzY4MTBhNzU2NTc0NjI1Nzk4MTNhOTk4YTU1ZTE4MzBlNzY1OWY1MTVkYjRhZTE3MDVlZTIiLCJ0YWciOiIifQ==\", \"branch_id\": 1, \"joined_at\": \"2024-11-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:12\", \"updated_at\": \"2026-07-18 20:11:12\", \"date_of_birth\": \"eyJpdiI6IlRSVWd5Z3QrM0JvR2FOOXF2ZnoyU0E9PSIsInZhbHVlIjoiSkY3dS8xdUZHMEFLMnYwYXN1Y0tkQT09IiwibWFjIjoiMjBmNDVkNmMwNmMwODE3OTgyMjg2NThmMDBkNTVjM2NiMWUwOTE4MDc4NmMxNzExOTQ2YTI1ZTk0Y2VkMjg0MSIsInRhZyI6IiJ9\", \"member_number\": \"AGT-0106\", \"member_type_id\": 4}','127.0.0.1','Symfony','2026-07-18 13:11:12'),(27,NULL,NULL,NULL,'create','App\\Models\\SavingsProduct',1,NULL,'{\"id\": 1, \"code\": \"SIM-DEMO\", \"name\": \"Simpanan Sukarela Demo\", \"category\": \"sukarela\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:12\", \"updated_at\": \"2026-07-18 20:11:12\", \"interest_method\": \"flat\", \"minimum_initial_deposit\": 10000, \"coa_liability_account_id\": 27, \"minimum_subsequent_deposit\": 5000, \"coa_interest_expense_account_id\": 74}','127.0.0.1','Symfony','2026-07-18 13:11:12'),(28,NULL,NULL,NULL,'create','App\\Models\\SavingsProductRateHistory',1,NULL,'{\"id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"updated_at\": \"2026-07-18 20:11:13\", \"effective_from\": \"2025-07-18 00:00:00\", \"rate_percentage\": 2.5, \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(29,NULL,NULL,1,'create','App\\Models\\SavingsAccount',1,NULL,'{\"id\": 1, \"status\": \"aktif\", \"balance\": 0, \"branch_id\": 1, \"member_id\": 1, \"opened_at\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:13\", \"updated_at\": \"2026-07-18 20:11:13\", \"account_number\": \"SIM-DEMO-260718-2090\", \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(30,NULL,NULL,1,'create','App\\Models\\JournalEntry',2,NULL,'{\"id\": 2, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran awal pembukaan rekening\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(31,NULL,NULL,1,'update','App\\Models\\SavingsAccount',1,'{\"balance\": \"0.00\"}','{\"balance\": \"500000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(32,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',1,NULL,'{\"id\": 1, \"type\": \"setor\", \"amount\": 500000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran awal pembukaan rekening\", \"balance_after\": \"500000.00\", \"journal_entry_id\": 2, \"savings_account_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(33,NULL,NULL,1,'create','App\\Models\\JournalEntry',3,NULL,'{\"id\": 3, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran rutin bulanan\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(34,NULL,NULL,1,'update','App\\Models\\SavingsAccount',1,'{\"balance\": \"500000.00\"}','{\"balance\": \"600000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(35,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',2,NULL,'{\"id\": 2, \"type\": \"setor\", \"amount\": 100000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran rutin bulanan\", \"balance_after\": \"600000.00\", \"journal_entry_id\": 3, \"savings_account_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(36,NULL,NULL,1,'create','App\\Models\\JournalEntry',4,NULL,'{\"id\": 4, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Tarik tunai kebutuhan mendesak\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(37,NULL,NULL,1,'update','App\\Models\\SavingsAccount',1,'{\"balance\": \"600000.00\"}','{\"balance\": \"500000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(38,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',3,NULL,'{\"id\": 3, \"type\": \"tarik\", \"amount\": 100000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Tarik tunai kebutuhan mendesak\", \"balance_after\": \"500000.00\", \"journal_entry_id\": 4, \"savings_account_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(39,NULL,NULL,1,'create','App\\Models\\SavingsAccount',2,NULL,'{\"id\": 2, \"status\": \"aktif\", \"balance\": 0, \"branch_id\": 1, \"member_id\": 2, \"opened_at\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:13\", \"updated_at\": \"2026-07-18 20:11:13\", \"account_number\": \"SIM-DEMO-260718-3310\", \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(40,NULL,NULL,1,'create','App\\Models\\JournalEntry',5,NULL,'{\"id\": 5, \"branch_id\": 1, \"source_id\": 2, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran awal pembukaan rekening\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(41,NULL,NULL,1,'update','App\\Models\\SavingsAccount',2,'{\"balance\": \"0.00\"}','{\"balance\": \"750000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(42,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',4,NULL,'{\"id\": 4, \"type\": \"setor\", \"amount\": 750000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran awal pembukaan rekening\", \"balance_after\": \"750000.00\", \"journal_entry_id\": 5, \"savings_account_id\": 2}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(43,NULL,NULL,1,'create','App\\Models\\JournalEntry',6,NULL,'{\"id\": 6, \"branch_id\": 1, \"source_id\": 2, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran rutin bulanan\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(44,NULL,NULL,1,'update','App\\Models\\SavingsAccount',2,'{\"balance\": \"750000.00\"}','{\"balance\": \"850000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(45,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',5,NULL,'{\"id\": 5, \"type\": \"setor\", \"amount\": 100000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran rutin bulanan\", \"balance_after\": \"850000.00\", \"journal_entry_id\": 6, \"savings_account_id\": 2}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(46,NULL,NULL,2,'create','App\\Models\\SavingsAccount',3,NULL,'{\"id\": 3, \"status\": \"aktif\", \"balance\": 0, \"branch_id\": 2, \"member_id\": 3, \"opened_at\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:13\", \"updated_at\": \"2026-07-18 20:11:13\", \"account_number\": \"SIM-DEMO-260718-5860\", \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(47,NULL,NULL,2,'create','App\\Models\\JournalEntry',7,NULL,'{\"id\": 7, \"branch_id\": 2, \"source_id\": 3, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran awal pembukaan rekening\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(48,NULL,NULL,2,'update','App\\Models\\SavingsAccount',3,'{\"balance\": \"0.00\"}','{\"balance\": \"1000000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(49,NULL,NULL,2,'create','App\\Models\\SavingsTransaction',6,NULL,'{\"id\": 6, \"type\": \"setor\", \"amount\": 1000000, \"branch_id\": 2, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran awal pembukaan rekening\", \"balance_after\": \"1000000.00\", \"journal_entry_id\": 7, \"savings_account_id\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(50,NULL,NULL,2,'create','App\\Models\\JournalEntry',8,NULL,'{\"id\": 8, \"branch_id\": 2, \"source_id\": 3, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran rutin bulanan\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(51,NULL,NULL,2,'update','App\\Models\\SavingsAccount',3,'{\"balance\": \"1000000.00\"}','{\"balance\": \"1300000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(52,NULL,NULL,2,'create','App\\Models\\SavingsTransaction',7,NULL,'{\"id\": 7, \"type\": \"setor\", \"amount\": 300000, \"branch_id\": 2, \"created_at\": \"2026-07-18 20:11:13\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:13\", \"description\": \"Setoran rutin bulanan\", \"balance_after\": \"1300000.00\", \"journal_entry_id\": 8, \"savings_account_id\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(53,NULL,NULL,3,'create','App\\Models\\SavingsAccount',4,NULL,'{\"id\": 4, \"status\": \"aktif\", \"balance\": 0, \"branch_id\": 3, \"member_id\": 4, \"opened_at\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:13\", \"updated_at\": \"2026-07-18 20:11:13\", \"account_number\": \"SIM-DEMO-260718-4584\", \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:13'),(54,NULL,NULL,3,'create','App\\Models\\JournalEntry',9,NULL,'{\"id\": 9, \"branch_id\": 3, \"source_id\": 4, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran awal pembukaan rekening\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(55,NULL,NULL,3,'update','App\\Models\\SavingsAccount',4,'{\"balance\": \"0.00\", \"updated_at\": \"2026-07-18T13:11:13.000000Z\"}','{\"balance\": \"300000.00\", \"updated_at\": \"2026-07-18 20:11:14\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(56,NULL,NULL,3,'create','App\\Models\\SavingsTransaction',8,NULL,'{\"id\": 8, \"type\": \"setor\", \"amount\": 300000, \"branch_id\": 3, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran awal pembukaan rekening\", \"balance_after\": \"300000.00\", \"journal_entry_id\": 9, \"savings_account_id\": 4}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(57,NULL,NULL,3,'create','App\\Models\\JournalEntry',10,NULL,'{\"id\": 10, \"branch_id\": 3, \"source_id\": 4, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran rutin bulanan\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(58,NULL,NULL,3,'update','App\\Models\\SavingsAccount',4,'{\"balance\": \"300000.00\"}','{\"balance\": \"600000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(59,NULL,NULL,3,'create','App\\Models\\SavingsTransaction',9,NULL,'{\"id\": 9, \"type\": \"setor\", \"amount\": 300000, \"branch_id\": 3, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran rutin bulanan\", \"balance_after\": \"600000.00\", \"journal_entry_id\": 10, \"savings_account_id\": 4}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(60,NULL,NULL,3,'create','App\\Models\\JournalEntry',11,NULL,'{\"id\": 11, \"branch_id\": 3, \"source_id\": 4, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Tarik tunai kebutuhan mendesak\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(61,NULL,NULL,3,'update','App\\Models\\SavingsAccount',4,'{\"balance\": \"600000.00\"}','{\"balance\": \"500000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(62,NULL,NULL,3,'create','App\\Models\\SavingsTransaction',10,NULL,'{\"id\": 10, \"type\": \"tarik\", \"amount\": 100000, \"branch_id\": 3, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Tarik tunai kebutuhan mendesak\", \"balance_after\": \"500000.00\", \"journal_entry_id\": 11, \"savings_account_id\": 4}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(63,NULL,NULL,1,'create','App\\Models\\SavingsAccount',5,NULL,'{\"id\": 5, \"status\": \"aktif\", \"balance\": 0, \"branch_id\": 1, \"member_id\": 5, \"opened_at\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:14\", \"updated_at\": \"2026-07-18 20:11:14\", \"account_number\": \"SIM-DEMO-260718-5039\", \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(64,NULL,NULL,1,'create','App\\Models\\JournalEntry',12,NULL,'{\"id\": 12, \"branch_id\": 1, \"source_id\": 5, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran awal pembukaan rekening\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(65,NULL,NULL,1,'update','App\\Models\\SavingsAccount',5,'{\"balance\": \"0.00\"}','{\"balance\": \"2000000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(66,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',11,NULL,'{\"id\": 11, \"type\": \"setor\", \"amount\": 2000000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran awal pembukaan rekening\", \"balance_after\": \"2000000.00\", \"journal_entry_id\": 12, \"savings_account_id\": 5}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(67,NULL,NULL,1,'create','App\\Models\\JournalEntry',13,NULL,'{\"id\": 13, \"branch_id\": 1, \"source_id\": 5, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran rutin bulanan\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(68,NULL,NULL,1,'update','App\\Models\\SavingsAccount',5,'{\"balance\": \"2000000.00\"}','{\"balance\": \"2100000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(69,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',12,NULL,'{\"id\": 12, \"type\": \"setor\", \"amount\": 100000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran rutin bulanan\", \"balance_after\": \"2100000.00\", \"journal_entry_id\": 13, \"savings_account_id\": 5}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(70,NULL,NULL,1,'create','App\\Models\\SavingsAccount',6,NULL,'{\"id\": 6, \"status\": \"aktif\", \"balance\": 0, \"branch_id\": 1, \"member_id\": 6, \"opened_at\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:14\", \"updated_at\": \"2026-07-18 20:11:14\", \"account_number\": \"SIM-DEMO-260718-8808\", \"savings_product_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(71,NULL,NULL,1,'create','App\\Models\\JournalEntry',14,NULL,'{\"id\": 14, \"branch_id\": 1, \"source_id\": 6, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran awal pembukaan rekening\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(72,NULL,NULL,1,'update','App\\Models\\SavingsAccount',6,'{\"balance\": \"0.00\"}','{\"balance\": \"1500000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(73,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',13,NULL,'{\"id\": 13, \"type\": \"setor\", \"amount\": 1500000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran awal pembukaan rekening\", \"balance_after\": \"1500000.00\", \"journal_entry_id\": 14, \"savings_account_id\": 6}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(74,NULL,NULL,1,'create','App\\Models\\JournalEntry',15,NULL,'{\"id\": 15, \"branch_id\": 1, \"source_id\": 6, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran rutin bulanan\", \"source_type\": \"App\\\\Models\\\\SavingsAccount\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(75,NULL,NULL,1,'update','App\\Models\\SavingsAccount',6,'{\"balance\": \"1500000.00\"}','{\"balance\": \"1800000.00\"}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(76,NULL,NULL,1,'create','App\\Models\\SavingsTransaction',14,NULL,'{\"id\": 14, \"type\": \"setor\", \"amount\": 300000, \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:14\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:14\", \"description\": \"Setoran rutin bulanan\", \"balance_after\": \"1800000.00\", \"journal_entry_id\": 15, \"savings_account_id\": 6}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(77,NULL,NULL,NULL,'create','App\\Models\\LoanProduct',1,NULL,'{\"id\": 1, \"code\": \"PINJ-DEMO\", \"name\": \"Pinjaman Modal Usaha Demo\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:14\", \"max_plafon\": 50000000, \"min_plafon\": 500000, \"updated_at\": \"2026-07-18 20:11:14\", \"max_tenor_days\": 24, \"min_tenor_days\": 3, \"approval_threshold\": 10000000, \"calculation_method\": \"flat\", \"provision_fee_percentage\": 1, \"coa_receivable_account_id\": 6, \"penalty_percentage_per_day\": 0.1, \"coa_interest_income_account_id\": 52, \"coa_provision_income_account_id\": 53, \"coa_penalty_receivable_account_id\": 11}','127.0.0.1','Symfony','2026-07-18 13:11:14'),(78,NULL,NULL,NULL,'create','App\\Models\\LoanProductRateHistory',1,NULL,'{\"id\": 1, \"created_at\": \"2026-07-18 20:11:15\", \"updated_at\": \"2026-07-18 20:11:15\", \"effective_from\": \"2025-07-18 00:00:00\", \"loan_product_id\": 1, \"rate_percentage\": 12}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(79,NULL,NULL,1,'create','App\\Models\\Loan',1,NULL,'{\"id\": 1, \"status\": \"diajukan\", \"branch_id\": 1, \"member_id\": 1, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:15\", \"loan_number\": \"PINJ-DEMO-260718-7680\", \"submitted_at\": \"2026-07-18 00:00:00\", \"tenor_days\": 6, \"loan_product_id\": 1, \"principal_amount\": 3000000, \"required_approval_count\": 1, \"interest_rate_percentage\": \"12.000\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(80,NULL,NULL,1,'create','App\\Models\\Loan',2,NULL,'{\"id\": 2, \"status\": \"diajukan\", \"branch_id\": 1, \"member_id\": 2, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:15\", \"loan_number\": \"PINJ-DEMO-260718-4187\", \"submitted_at\": \"2026-07-18 00:00:00\", \"tenor_days\": 3, \"loan_product_id\": 1, \"principal_amount\": 2000000, \"required_approval_count\": 1, \"interest_rate_percentage\": \"12.000\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(81,NULL,NULL,NULL,'create','App\\Models\\LoanApproval',1,NULL,'{\"id\": 1, \"notes\": \"Riwayat angsuran sebelumnya kurang lancar.\", \"loan_id\": 2, \"decision\": \"tolak\", \"created_at\": \"2026-07-18 20:11:15\", \"decided_at\": \"2026-07-18 20:11:15\", \"updated_at\": \"2026-07-18 20:11:15\", \"approved_by\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(82,NULL,NULL,1,'update','App\\Models\\Loan',2,'{\"status\": \"diajukan\"}','{\"status\": \"ditolak\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(83,NULL,NULL,2,'create','App\\Models\\Loan',3,NULL,'{\"id\": 3, \"status\": \"diajukan\", \"branch_id\": 2, \"member_id\": 3, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:15\", \"loan_number\": \"PINJ-DEMO-260718-0828\", \"submitted_at\": \"2026-07-18 00:00:00\", \"tenor_days\": 6, \"loan_product_id\": 1, \"principal_amount\": 5000000, \"required_approval_count\": 1, \"interest_rate_percentage\": \"12.000\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(84,NULL,NULL,NULL,'create','App\\Models\\LoanApproval',2,NULL,'{\"id\": 2, \"notes\": null, \"loan_id\": 3, \"decision\": \"setuju\", \"created_at\": \"2026-07-18 20:11:15\", \"decided_at\": \"2026-07-18 20:11:15\", \"updated_at\": \"2026-07-18 20:11:15\", \"approved_by\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(85,NULL,NULL,2,'update','App\\Models\\Loan',3,'{\"status\": \"diajukan\"}','{\"status\": \"disetujui\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(86,NULL,NULL,2,'create','App\\Models\\JournalEntry',16,NULL,'{\"id\": 16, \"branch_id\": 2, \"source_id\": 3, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 5, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:15\", \"description\": \"Pencairan pinjaman PINJ-DEMO-260718-0828\", \"source_type\": \"App\\\\Models\\\\Loan\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(87,NULL,NULL,2,'update','App\\Models\\Loan',3,'{\"status\": \"disetujui\", \"disbursed_at\": null, \"collectibility\": null, \"provision_fee_amount\": null}','{\"status\": \"dicairkan\", \"disbursed_at\": \"2026-07-18 00:00:00\", \"collectibility\": \"lancar\", \"provision_fee_amount\": 50000}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(88,NULL,NULL,2,'create','App\\Models\\LoanRepayment',1,NULL,'{\"id\": 1, \"amount\": 883333.33, \"loan_id\": 3, \"branch_id\": 2, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 4, \"updated_at\": \"2026-07-18 20:11:15\", \"description\": \"Pembayaran angsuran ke-1\", \"balance_after\": 4416666.67, \"interest_portion\": 50000, \"principal_portion\": 833333.33}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(89,NULL,NULL,2,'create','App\\Models\\JournalEntry',17,NULL,'{\"id\": 17, \"branch_id\": 2, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:15\", \"description\": \"Pembayaran angsuran ke-1\", \"source_type\": \"App\\\\Models\\\\LoanRepayment\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(90,NULL,NULL,2,'update','App\\Models\\LoanRepayment',1,'[]','{\"journal_entry_id\": 17}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(91,NULL,NULL,3,'create','App\\Models\\Loan',4,NULL,'{\"id\": 4, \"status\": \"diajukan\", \"branch_id\": 3, \"member_id\": 4, \"created_at\": \"2026-07-18 20:11:15\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:15\", \"loan_number\": \"PINJ-DEMO-260718-8648\", \"submitted_at\": \"2026-07-18 00:00:00\", \"tenor_days\": 12, \"loan_product_id\": 1, \"principal_amount\": 8000000, \"required_approval_count\": 1, \"interest_rate_percentage\": \"12.000\"}','127.0.0.1','Symfony','2026-07-18 13:11:15'),(92,NULL,NULL,NULL,'create','App\\Models\\LoanApproval',3,NULL,'{\"id\": 3, \"notes\": null, \"loan_id\": 4, \"decision\": \"setuju\", \"created_at\": \"2026-07-18 20:11:16\", \"decided_at\": \"2026-07-18 20:11:16\", \"updated_at\": \"2026-07-18 20:11:16\", \"approved_by\": 7}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(93,NULL,NULL,3,'update','App\\Models\\Loan',4,'{\"status\": \"diajukan\", \"updated_at\": \"2026-07-18T13:11:15.000000Z\"}','{\"status\": \"disetujui\", \"updated_at\": \"2026-07-18 20:11:16\"}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(94,NULL,NULL,3,'create','App\\Models\\JournalEntry',18,NULL,'{\"id\": 18, \"branch_id\": 3, \"source_id\": 4, \"created_at\": \"2026-07-18 20:11:16\", \"created_by\": 5, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:16\", \"description\": \"Pencairan pinjaman PINJ-DEMO-260718-8648\", \"source_type\": \"App\\\\Models\\\\Loan\"}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(95,NULL,NULL,3,'update','App\\Models\\Loan',4,'{\"status\": \"disetujui\", \"disbursed_at\": null, \"collectibility\": null, \"provision_fee_amount\": null}','{\"status\": \"dicairkan\", \"disbursed_at\": \"2026-07-18 00:00:00\", \"collectibility\": \"lancar\", \"provision_fee_amount\": 80000}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(96,NULL,NULL,1,'create','App\\Models\\Loan',5,NULL,'{\"id\": 5, \"status\": \"diajukan\", \"branch_id\": 1, \"member_id\": 5, \"created_at\": \"2026-07-18 20:11:16\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:16\", \"loan_number\": \"PINJ-DEMO-260718-0813\", \"submitted_at\": \"2026-07-18 00:00:00\", \"tenor_days\": 24, \"loan_product_id\": 1, \"principal_amount\": 15000000, \"required_approval_count\": 2, \"interest_rate_percentage\": \"12.000\"}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(97,NULL,NULL,NULL,'create','App\\Models\\LoanApproval',4,NULL,'{\"id\": 4, \"notes\": null, \"loan_id\": 5, \"decision\": \"setuju\", \"created_at\": \"2026-07-18 20:11:16\", \"decided_at\": \"2026-07-18 20:11:16\", \"updated_at\": \"2026-07-18 20:11:16\", \"approved_by\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(98,NULL,NULL,NULL,'create','App\\Models\\LoanApproval',5,NULL,'{\"id\": 5, \"notes\": null, \"loan_id\": 5, \"decision\": \"setuju\", \"created_at\": \"2026-07-18 20:11:16\", \"decided_at\": \"2026-07-18 20:11:16\", \"updated_at\": \"2026-07-18 20:11:16\", \"approved_by\": 7}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(99,NULL,NULL,1,'update','App\\Models\\Loan',5,'{\"status\": \"diajukan\"}','{\"status\": \"disetujui\"}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(100,NULL,NULL,1,'create','App\\Models\\JournalEntry',19,NULL,'{\"id\": 19, \"branch_id\": 1, \"source_id\": 5, \"created_at\": \"2026-07-18 20:11:16\", \"created_by\": 5, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:16\", \"description\": \"Pencairan pinjaman PINJ-DEMO-260718-0813\", \"source_type\": \"App\\\\Models\\\\Loan\"}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(101,NULL,NULL,1,'update','App\\Models\\Loan',5,'{\"status\": \"disetujui\", \"disbursed_at\": null, \"collectibility\": null, \"provision_fee_amount\": null}','{\"status\": \"dicairkan\", \"disbursed_at\": \"2026-07-18 00:00:00\", \"collectibility\": \"lancar\", \"provision_fee_amount\": 150000}','127.0.0.1','Symfony','2026-07-18 13:11:16'),(102,NULL,NULL,NULL,'create','App\\Models\\Supplier',1,NULL,'{\"id\": 1, \"code\": \"SUP-001\", \"name\": \"CV Sumber Rejeki\", \"type\": \"distributor\", \"phone\": \"6281911102787\", \"address\": \"Ds. Kalimalang No. 781, Tangerang Selatan 29883, Sulsel\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:16\", \"updated_at\": \"2026-07-18 20:11:16\", \"contact_name\": \"Darimin Prabowo\", \"payment_term\": \"kredit\", \"payment_term_days\": 30, \"coa_payable_account_id\": 29}','127.0.0.1','Symfony','2026-07-18 13:11:17'),(103,NULL,NULL,NULL,'create','App\\Models\\Supplier',2,NULL,'{\"id\": 2, \"code\": \"SUP-002\", \"name\": \"PT Distribusi Sembako Jaya\", \"type\": \"distributor\", \"phone\": \"6284124948809\", \"address\": \"Dk. Pintu Besar Selatan No. 815, Kotamobagu 99052, Sulut\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:17\", \"updated_at\": \"2026-07-18 20:11:17\", \"contact_name\": \"Hendri Harto Kurniawan\", \"payment_term\": \"tunai\", \"payment_term_days\": null, \"coa_payable_account_id\": 29}','127.0.0.1','Symfony','2026-07-18 13:11:17'),(104,NULL,NULL,NULL,'create','App\\Models\\Product',1,NULL,'{\"id\": 1, \"code\": \"BRG-001\", \"name\": \"Beras 5kg\", \"unit\": \"karung\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:17\", \"updated_at\": \"2026-07-18 20:11:17\", \"selling_price\": 68000, \"purchase_price\": 62000, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:18'),(105,NULL,NULL,NULL,'create','App\\Models\\Product',2,NULL,'{\"id\": 2, \"code\": \"BRG-002\", \"name\": \"Minyak Goreng 1L\", \"unit\": \"botol\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:18\", \"updated_at\": \"2026-07-18 20:11:18\", \"selling_price\": 18000, \"purchase_price\": 15500, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:18'),(106,NULL,NULL,NULL,'create','App\\Models\\Product',3,NULL,'{\"id\": 3, \"code\": \"BRG-003\", \"name\": \"Gula Pasir 1kg\", \"unit\": \"kg\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:18\", \"updated_at\": \"2026-07-18 20:11:18\", \"selling_price\": 16000, \"purchase_price\": 13500, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:18'),(107,NULL,NULL,NULL,'create','App\\Models\\Product',4,NULL,'{\"id\": 4, \"code\": \"BRG-004\", \"name\": \"Telur Ayam 1kg\", \"unit\": \"kg\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:18\", \"updated_at\": \"2026-07-18 20:11:18\", \"selling_price\": 28000, \"purchase_price\": 24000, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:18'),(108,NULL,NULL,NULL,'create','App\\Models\\Product',5,NULL,'{\"id\": 5, \"code\": \"BRG-005\", \"name\": \"Kopi Sachet (renceng)\", \"unit\": \"renceng\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:18\", \"updated_at\": \"2026-07-18 20:11:18\", \"selling_price\": 12000, \"purchase_price\": 9500, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:18'),(109,NULL,NULL,NULL,'create','App\\Models\\Product',6,NULL,'{\"id\": 6, \"code\": \"BRG-006\", \"name\": \"Mie Instan (dus)\", \"unit\": \"dus\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:18\", \"updated_at\": \"2026-07-18 20:11:18\", \"selling_price\": 110000, \"purchase_price\": 95000, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:18'),(110,NULL,NULL,NULL,'create','App\\Models\\Product',7,NULL,'{\"id\": 7, \"code\": \"BRG-007\", \"name\": \"Sabun Mandi\", \"unit\": \"pcs\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:18\", \"updated_at\": \"2026-07-18 20:11:18\", \"selling_price\": 4500, \"purchase_price\": 3200, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(111,NULL,NULL,NULL,'create','App\\Models\\Product',8,NULL,'{\"id\": 8, \"code\": \"BRG-008\", \"name\": \"Air Mineral 600ml (dus)\", \"unit\": \"dus\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:19\", \"updated_at\": \"2026-07-18 20:11:19\", \"selling_price\": 40000, \"purchase_price\": 32000, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(112,NULL,NULL,NULL,'create','App\\Models\\Product',9,NULL,'{\"id\": 9, \"code\": \"BRG-009\", \"name\": \"Tepung Terigu 1kg\", \"unit\": \"kg\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:19\", \"updated_at\": \"2026-07-18 20:11:19\", \"selling_price\": 13000, \"purchase_price\": 10500, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(113,NULL,NULL,NULL,'create','App\\Models\\Product',10,NULL,'{\"id\": 10, \"code\": \"BRG-010\", \"name\": \"Kecap Manis 600ml\", \"unit\": \"botol\", \"category\": \"Sembako\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:19\", \"updated_at\": \"2026-07-18 20:11:19\", \"selling_price\": 17500, \"purchase_price\": 14000, \"coa_cogs_account_id\": 73, \"coa_inventory_account_id\": 12, \"coa_sales_revenue_account_id\": 56}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(114,NULL,NULL,1,'create','App\\Models\\PurchaseTransaction',1,NULL,'{\"id\": 1, \"status\": \"menunggu_approval\", \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:19\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:19\", \"supplier_id\": 1, \"total_amount\": \"7190000.00\", \"purchase_date\": \"2026-07-18 00:00:00\", \"payment_method\": \"kredit\", \"payment_status\": \"belum_lunas\", \"purchase_number\": \"PB-260718-1315\"}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(115,NULL,NULL,1,'create','App\\Models\\JournalEntry',20,NULL,'{\"id\": 20, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:19\", \"created_by\": 5, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:19\", \"description\": \"Pembelian barang dari CV Sumber Rejeki — PB-260718-1315\", \"source_type\": \"App\\\\Models\\\\PurchaseTransaction\"}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(116,NULL,NULL,1,'update','App\\Models\\PurchaseTransaction',1,'{\"status\": \"menunggu_approval\", \"journal_entry_id\": null}','{\"status\": \"diposting\", \"journal_entry_id\": 20}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(117,NULL,NULL,1,'update','App\\Models\\PurchaseTransaction',1,'{\"approved_by\": null}','{\"approved_by\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:19'),(118,NULL,NULL,1,'create','App\\Models\\PurchaseTransaction',2,NULL,'{\"id\": 2, \"status\": \"menunggu_approval\", \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:20\", \"created_by\": 5, \"updated_at\": \"2026-07-18 20:11:20\", \"supplier_id\": 2, \"total_amount\": \"1474500.00\", \"purchase_date\": \"2026-07-18 00:00:00\", \"payment_method\": \"tunai\", \"payment_status\": null, \"purchase_number\": \"PB-260718-0626\"}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(119,NULL,NULL,1,'create','App\\Models\\JournalEntry',21,NULL,'{\"id\": 21, \"branch_id\": 1, \"source_id\": 2, \"created_at\": \"2026-07-18 20:11:20\", \"created_by\": 5, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:20\", \"description\": \"Pembelian barang dari PT Distribusi Sembako Jaya — PB-260718-0626\", \"source_type\": \"App\\\\Models\\\\PurchaseTransaction\"}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(120,NULL,NULL,1,'update','App\\Models\\PurchaseTransaction',2,'{\"status\": \"menunggu_approval\", \"payment_status\": null}','{\"status\": \"diposting\", \"paid_amount\": \"1474500.00\", \"payment_status\": \"lunas\", \"journal_entry_id\": 21}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(121,NULL,NULL,1,'create','App\\Models\\PosSale',1,NULL,'{\"id\": 1, \"branch_id\": 1, \"sale_date\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:20\", \"created_by\": 4, \"total_cogs\": 0, \"updated_at\": \"2026-07-18 20:11:20\", \"sale_number\": \"JL-260718-6109\", \"total_amount\": 0, \"payment_method\": \"tunai\", \"savings_account_id\": null}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(122,NULL,NULL,1,'create','App\\Models\\JournalEntry',22,NULL,'{\"id\": 22, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:20\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:20\", \"description\": \"Penjualan POS JL-260718-6109\", \"source_type\": \"App\\\\Models\\\\PosSale\"}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(123,NULL,NULL,NULL,'create','App\\Models\\PosSaleItem',1,NULL,'{\"id\": 1, \"qty\": \"5\", \"subtotal\": \"80000.00\", \"unit_cost\": \"13500.0000\", \"created_at\": \"2026-07-18 20:11:20\", \"product_id\": 3, \"unit_price\": \"16000.00\", \"updated_at\": \"2026-07-18 20:11:20\", \"pos_sale_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(124,NULL,NULL,NULL,'create','App\\Models\\PosSaleItem',2,NULL,'{\"id\": 2, \"qty\": \"3\", \"subtotal\": \"13500.00\", \"unit_cost\": \"3200.0000\", \"created_at\": \"2026-07-18 20:11:20\", \"product_id\": 7, \"unit_price\": \"4500.00\", \"updated_at\": \"2026-07-18 20:11:20\", \"pos_sale_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(125,NULL,NULL,1,'update','App\\Models\\PosSale',1,'{\"total_cogs\": \"0.00\", \"total_amount\": \"0.00\"}','{\"loan_id\": null, \"total_cogs\": \"77100.00\", \"total_amount\": \"93500.00\", \"journal_entry_id\": 22}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(126,NULL,NULL,1,'create','App\\Models\\PosSale',2,NULL,'{\"id\": 2, \"branch_id\": 1, \"sale_date\": \"2026-07-18 00:00:00\", \"created_at\": \"2026-07-18 20:11:20\", \"created_by\": 4, \"total_cogs\": 0, \"updated_at\": \"2026-07-18 20:11:20\", \"sale_number\": \"JL-260718-4893\", \"total_amount\": 0, \"payment_method\": \"tunai\", \"savings_account_id\": null}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(127,NULL,NULL,1,'create','App\\Models\\JournalEntry',23,NULL,'{\"id\": 23, \"branch_id\": 1, \"source_id\": 2, \"created_at\": \"2026-07-18 20:11:20\", \"created_by\": 4, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:20\", \"description\": \"Penjualan POS JL-260718-4893\", \"source_type\": \"App\\\\Models\\\\PosSale\"}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(128,NULL,NULL,NULL,'create','App\\Models\\PosSaleItem',3,NULL,'{\"id\": 3, \"qty\": \"2\", \"subtotal\": \"56000.00\", \"unit_cost\": \"24000.0000\", \"created_at\": \"2026-07-18 20:11:20\", \"product_id\": 4, \"unit_price\": \"28000.00\", \"updated_at\": \"2026-07-18 20:11:20\", \"pos_sale_id\": 2}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(129,NULL,NULL,NULL,'create','App\\Models\\PosSaleItem',4,NULL,'{\"id\": 4, \"qty\": \"4\", \"subtotal\": \"52000.00\", \"unit_cost\": \"10500.0000\", \"created_at\": \"2026-07-18 20:11:20\", \"product_id\": 9, \"unit_price\": \"13000.00\", \"updated_at\": \"2026-07-18 20:11:20\", \"pos_sale_id\": 2}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(130,NULL,NULL,1,'update','App\\Models\\PosSale',2,'{\"total_cogs\": \"0.00\", \"total_amount\": \"0.00\"}','{\"loan_id\": null, \"total_cogs\": \"90000.00\", \"total_amount\": \"108000.00\", \"journal_entry_id\": 23}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(131,NULL,NULL,NULL,'create','App\\Models\\FixedAssetCategory',1,NULL,'{\"id\": 1, \"code\": \"KAT-PRL\", \"name\": \"Peralatan Kantor\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:20\", \"updated_at\": \"2026-07-18 20:11:20\", \"coa_asset_account_id\": 19, \"default_useful_life_months\": 48, \"default_depreciation_method\": \"garis_lurus\", \"coa_depreciation_expense_account_id\": 80, \"coa_accumulated_depreciation_account_id\": 20}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(132,NULL,NULL,NULL,'create','App\\Models\\FixedAssetCategory',2,NULL,'{\"id\": 2, \"code\": \"KAT-KDR\", \"name\": \"Kendaraan\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:20\", \"updated_at\": \"2026-07-18 20:11:20\", \"coa_asset_account_id\": 21, \"default_useful_life_months\": 96, \"default_depreciation_method\": \"garis_lurus\", \"coa_depreciation_expense_account_id\": 80, \"coa_accumulated_depreciation_account_id\": 22}','127.0.0.1','Symfony','2026-07-18 13:11:20'),(133,NULL,NULL,1,'create','App\\Models\\FixedAsset',1,NULL,'{\"id\": 1, \"code\": \"AT-0001\", \"name\": \"Laptop Kantor Lenovo\", \"status\": \"menunggu_approval\", \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:21\", \"created_by\": 7, \"updated_at\": \"2026-07-18 20:11:21\", \"payment_method\": \"tunai\", \"residual_value\": \"500000\", \"acquisition_cost\": \"8000000\", \"acquisition_date\": \"2026-04-18 00:00:00\", \"useful_life_months\": 36, \"depreciation_method\": \"garis_lurus\", \"fixed_asset_category_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(134,NULL,NULL,1,'create','App\\Models\\JournalEntry',24,NULL,'{\"id\": 24, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:21\", \"created_by\": 7, \"entry_date\": \"2026-04-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:21\", \"description\": \"Pembelian aktiva tetap Laptop Kantor Lenovo (AT-0001)\", \"source_type\": \"App\\\\Models\\\\FixedAsset\"}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(135,NULL,NULL,1,'update','App\\Models\\FixedAsset',1,'{\"status\": \"menunggu_approval\"}','{\"status\": \"aktif\", \"journal_entry_id\": 24}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(136,NULL,NULL,1,'create','App\\Models\\FixedAsset',2,NULL,'{\"id\": 2, \"code\": \"AT-0002\", \"name\": \"Printer Multifungsi\", \"status\": \"menunggu_approval\", \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:21\", \"created_by\": 7, \"updated_at\": \"2026-07-18 20:11:21\", \"payment_method\": \"tunai\", \"residual_value\": \"200000\", \"acquisition_cost\": \"3500000\", \"acquisition_date\": \"2026-06-18 00:00:00\", \"useful_life_months\": 24, \"depreciation_method\": \"garis_lurus\", \"fixed_asset_category_id\": 1}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(137,NULL,NULL,1,'create','App\\Models\\JournalEntry',25,NULL,'{\"id\": 25, \"branch_id\": 1, \"source_id\": 2, \"created_at\": \"2026-07-18 20:11:21\", \"created_by\": 7, \"entry_date\": \"2026-06-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:21\", \"description\": \"Pembelian aktiva tetap Printer Multifungsi (AT-0002)\", \"source_type\": \"App\\\\Models\\\\FixedAsset\"}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(138,NULL,NULL,1,'update','App\\Models\\FixedAsset',2,'{\"status\": \"menunggu_approval\"}','{\"status\": \"aktif\", \"journal_entry_id\": 25}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(139,NULL,NULL,1,'create','App\\Models\\FixedAsset',3,NULL,'{\"id\": 3, \"code\": \"AT-0003\", \"name\": \"Sepeda Motor Operasional\", \"status\": \"menunggu_approval\", \"branch_id\": 1, \"created_at\": \"2026-07-18 20:11:21\", \"created_by\": 7, \"updated_at\": \"2026-07-18 20:11:21\", \"payment_method\": \"tunai\", \"residual_value\": \"4000000\", \"acquisition_cost\": \"22000000\", \"acquisition_date\": \"2026-07-08 00:00:00\", \"useful_life_months\": 60, \"depreciation_method\": \"garis_lurus\", \"fixed_asset_category_id\": 2}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(140,NULL,NULL,1,'create','App\\Models\\JournalEntry',26,NULL,'{\"id\": 26, \"branch_id\": 1, \"source_id\": 3, \"created_at\": \"2026-07-18 20:11:21\", \"created_by\": 7, \"entry_date\": \"2026-07-08 00:00:00\", \"updated_at\": \"2026-07-18 20:11:21\", \"description\": \"Pembelian aktiva tetap Sepeda Motor Operasional (AT-0003)\", \"source_type\": \"App\\\\Models\\\\FixedAsset\"}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(141,NULL,NULL,1,'update','App\\Models\\FixedAsset',3,'{\"status\": \"menunggu_approval\", \"journal_entry_id\": null}','{\"status\": \"aktif\", \"journal_entry_id\": 26}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(142,NULL,NULL,1,'update','App\\Models\\FixedAsset',3,'{\"approved_by\": null}','{\"approved_by\": 3}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(143,NULL,NULL,NULL,'create','App\\Models\\RetributionType',1,NULL,'{\"id\": 1, \"code\": \"RET-KBR\", \"name\": \"Retribusi Kebersihan\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:21\", \"percentage\": 30, \"updated_at\": \"2026-07-18 20:11:21\", \"coa_revenue_account_id\": 61}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(144,NULL,NULL,NULL,'create','App\\Models\\RetributionType',2,NULL,'{\"id\": 2, \"code\": \"RET-KMN\", \"name\": \"Retribusi Keamanan\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:21\", \"percentage\": 25, \"updated_at\": \"2026-07-18 20:11:21\", \"coa_revenue_account_id\": 62}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(145,NULL,NULL,NULL,'create','App\\Models\\RetributionType',3,NULL,'{\"id\": 3, \"code\": \"RET-LST\", \"name\": \"Retribusi Listrik\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:21\", \"percentage\": 20, \"updated_at\": \"2026-07-18 20:11:21\", \"coa_revenue_account_id\": 63}','127.0.0.1','Symfony','2026-07-18 13:11:21'),(146,NULL,NULL,NULL,'create','App\\Models\\RetributionType',4,NULL,'{\"id\": 4, \"code\": \"RET-AIR\", \"name\": \"Retribusi Air\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:21\", \"percentage\": 15, \"updated_at\": \"2026-07-18 20:11:21\", \"coa_revenue_account_id\": 64}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(147,NULL,NULL,NULL,'create','App\\Models\\RetributionType',5,NULL,'{\"id\": 5, \"code\": \"RET-LNN\", \"name\": \"Retribusi Lainnya\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:22\", \"percentage\": 10, \"updated_at\": \"2026-07-18 20:11:22\", \"coa_revenue_account_id\": 65}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(148,NULL,NULL,1,'create','App\\Models\\JournalEntry',27,NULL,'{\"id\": 27, \"branch_id\": 1, \"source_id\": null, \"created_at\": \"2026-07-18 20:11:22\", \"created_by\": 6, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:22\", \"description\": \"Retribusi UPF — Toko Sumber Makmur (Kios) (5 jenis retribusi)\", \"source_type\": null}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(149,NULL,NULL,1,'create','App\\Models\\RetributionTransaction',1,NULL,'{\"id\": 1, \"branch_id\": 1, \"member_id\": 5, \"created_at\": \"2026-07-18 20:11:22\", \"created_by\": 6, \"payer_name\": \"Toko Sumber Makmur (Kios)\", \"payer_type\": \"anggota\", \"updated_at\": \"2026-07-18 20:11:22\", \"description\": \"Iuran bulanan kios\", \"total_amount\": 150000, \"payment_method\": \"tunai\", \"journal_entry_id\": 27, \"transaction_date\": \"2026-07-18 00:00:00\", \"transaction_number\": \"RB-260718-3254\"}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(150,NULL,NULL,1,'create','App\\Models\\JournalEntry',28,NULL,'{\"id\": 28, \"branch_id\": 1, \"source_id\": null, \"created_at\": \"2026-07-18 20:11:22\", \"created_by\": 6, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:22\", \"description\": \"Retribusi UPF — Warung Blok C-12 (5 jenis retribusi)\", \"source_type\": null}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(151,NULL,NULL,1,'create','App\\Models\\RetributionTransaction',2,NULL,'{\"id\": 2, \"branch_id\": 1, \"member_id\": 6, \"created_at\": \"2026-07-18 20:11:22\", \"created_by\": 6, \"payer_name\": \"Warung Blok C-12\", \"payer_type\": \"anggota\", \"updated_at\": \"2026-07-18 20:11:22\", \"description\": \"Iuran bulanan blok\", \"total_amount\": 100000, \"payment_method\": \"tunai\", \"journal_entry_id\": 28, \"transaction_date\": \"2026-07-18 00:00:00\", \"transaction_number\": \"RB-260718-5488\"}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(152,NULL,NULL,1,'create','App\\Models\\JournalEntry',29,NULL,'{\"id\": 29, \"branch_id\": 1, \"source_id\": null, \"created_at\": \"2026-07-18 20:11:22\", \"created_by\": 6, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:22\", \"description\": \"Retribusi UPF — Pedagang Kaki Lima Depan Pasar (5 jenis retribusi)\", \"source_type\": null}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(153,NULL,NULL,1,'create','App\\Models\\RetributionTransaction',3,NULL,'{\"id\": 3, \"branch_id\": 1, \"member_id\": null, \"created_at\": \"2026-07-18 20:11:22\", \"created_by\": 6, \"payer_name\": \"Pedagang Kaki Lima Depan Pasar\", \"payer_type\": \"umum\", \"updated_at\": \"2026-07-18 20:11:22\", \"description\": \"Retribusi harian umum\", \"total_amount\": 50000, \"payment_method\": \"tunai\", \"journal_entry_id\": 29, \"transaction_date\": \"2026-07-18 00:00:00\", \"transaction_number\": \"RB-260718-9156\"}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(154,NULL,NULL,NULL,'create','App\\Models\\BusinessUnit',1,NULL,'{\"id\": 1, \"code\": \"UNIT-001\", \"name\": \"Sewa Aula Serbaguna\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:22\", \"updated_at\": \"2026-07-18 20:11:22\", \"coa_expense_account_id\": 84, \"coa_revenue_account_id\": 60}','127.0.0.1','Symfony','2026-07-18 13:11:22'),(155,NULL,NULL,NULL,'create','App\\Models\\BusinessUnit',2,NULL,'{\"id\": 2, \"code\": \"UNIT-002\", \"name\": \"Fotokopi & ATK\", \"is_active\": true, \"created_at\": \"2026-07-18 20:11:22\", \"updated_at\": \"2026-07-18 20:11:22\", \"coa_expense_account_id\": 82, \"coa_revenue_account_id\": 68}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(156,NULL,NULL,1,'create','App\\Models\\JournalEntry',30,NULL,'{\"id\": 30, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:23\", \"created_by\": 7, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:23\", \"description\": \"Sewa aula untuk acara RT\", \"source_type\": \"App\\\\Models\\\\BusinessUnit\"}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(157,NULL,NULL,1,'create','App\\Models\\JournalEntry',31,NULL,'{\"id\": 31, \"branch_id\": 1, \"source_id\": 1, \"created_at\": \"2026-07-18 20:11:23\", \"created_by\": 7, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:23\", \"description\": \"Biaya kebersihan pasca acara\", \"source_type\": \"App\\\\Models\\\\BusinessUnit\"}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(158,NULL,NULL,1,'create','App\\Models\\JournalEntry',32,NULL,'{\"id\": 32, \"branch_id\": 1, \"source_id\": 2, \"created_at\": \"2026-07-18 20:11:23\", \"created_by\": 7, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-18 20:11:23\", \"description\": \"Penjualan fotokopi & ATK harian\", \"source_type\": \"App\\\\Models\\\\BusinessUnit\"}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(159,NULL,NULL,NULL,'create','App\\Models\\CooperativeEvent',1,NULL,'{\"id\": 1, \"title\": \"Rapat Anggota Tahunan (RAT) 2026\", \"end_at\": \"2026-08-07 13:00:00\", \"status\": \"terjadwal\", \"location\": \"Aula Kantor Pusat\", \"start_at\": \"2026-08-07 09:00:00\", \"created_at\": \"2026-07-18 20:11:23\", \"created_by\": 3, \"updated_at\": \"2026-07-18 20:11:23\", \"description\": \"Pembahasan laporan pertanggungjawaban pengurus tahun buku 2025.\", \"reminder_days\": \"[7,1]\", \"participant_type\": \"semua_anggota_cabang\", \"single_branch_id\": null, \"branch_scope_type\": \"all\"}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(160,NULL,NULL,NULL,'create','App\\Models\\CooperativeEvent',2,NULL,'{\"id\": 2, \"title\": \"Sosialisasi Produk Pinjaman Baru\", \"end_at\": \"2026-07-23 11:30:00\", \"status\": \"terjadwal\", \"location\": \"Ruang Pertemuan Kantor Pusat\", \"start_at\": \"2026-07-23 10:00:00\", \"created_at\": \"2026-07-18 20:11:23\", \"created_by\": 3, \"updated_at\": \"2026-07-18 20:11:23\", \"description\": \"Sosialisasi ke anggota terpilih mengenai produk pinjaman modal usaha.\", \"reminder_days\": \"[1]\", \"participant_type\": \"anggota_tertentu\", \"single_branch_id\": 1, \"branch_scope_type\": \"single\"}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(161,NULL,NULL,NULL,'create','App\\Models\\CooperativeEvent',3,NULL,'{\"id\": 3, \"title\": \"Penutupan Buku Bulanan (sudah lewat)\", \"end_at\": \"2026-07-15 17:00:00\", \"status\": \"terjadwal\", \"location\": \"Kantor Pusat\", \"start_at\": \"2026-07-15 08:00:00\", \"created_at\": \"2026-07-18 20:11:23\", \"created_by\": 7, \"updated_at\": \"2026-07-18 20:11:23\", \"description\": null, \"reminder_days\": \"[]\", \"participant_type\": \"semua_anggota_cabang\", \"single_branch_id\": null, \"branch_scope_type\": \"all\"}','127.0.0.1','Symfony','2026-07-18 13:11:23'),(162,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:34:44'),(163,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.22209.0 Chrome/148.0.7778.271 Electron/42.5.1 Safari/537.36 MSIX','2026-07-18 15:40:04'),(164,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',1,NULL,'{\"id\": 1, \"label\": \"Diperiksa oleh (Pengurus)\", \"created_at\": \"2026-07-18 22:49:50\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:50\", \"document_group\": \"pengajuan_pinjaman\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:50'),(165,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',2,NULL,'{\"id\": 2, \"label\": \"Disetujui oleh (Ketua Koperasi)\", \"created_at\": \"2026-07-18 22:49:50\", \"slot_order\": 1, \"updated_at\": \"2026-07-18 22:49:50\", \"document_group\": \"pengajuan_pinjaman\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:50'),(166,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',3,NULL,'{\"id\": 3, \"label\": \"Diperiksa oleh (Pengurus)\", \"created_at\": \"2026-07-18 22:49:50\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:50\", \"document_group\": \"pengajuan_penarikan\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:50'),(167,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',4,NULL,'{\"id\": 4, \"label\": \"Disetujui oleh (Ketua Koperasi)\", \"created_at\": \"2026-07-18 22:49:50\", \"slot_order\": 1, \"updated_at\": \"2026-07-18 22:49:50\", \"document_group\": \"pengajuan_penarikan\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:51'),(168,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',5,NULL,'{\"id\": 5, \"label\": \"Pengurus/Ketua Koperasi\", \"created_at\": \"2026-07-18 22:49:51\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:51\", \"document_group\": \"kas_keluar\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:51'),(169,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',6,NULL,'{\"id\": 6, \"label\": \"Pengurus/Ketua Koperasi\", \"created_at\": \"2026-07-18 22:49:51\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:51\", \"document_group\": \"kas_masuk\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:51'),(170,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',7,NULL,'{\"id\": 7, \"label\": \"Pengurus\", \"created_at\": \"2026-07-18 22:49:51\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:51\", \"document_group\": \"jurnal_umum\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:52'),(171,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',8,NULL,'{\"id\": 8, \"label\": \"Diketahui/Disetujui oleh (Pengurus)\", \"created_at\": \"2026-07-18 22:49:52\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:52\", \"document_group\": \"dokumen_gudang\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:52'),(172,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',9,NULL,'{\"id\": 9, \"label\": \"Diketahui/Disetujui oleh (Pengurus)\", \"created_at\": \"2026-07-18 22:49:52\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:52\", \"document_group\": \"aktiva_tetap\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:52'),(173,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',10,NULL,'{\"id\": 10, \"label\": \"Pengurus\", \"created_at\": \"2026-07-18 22:49:52\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:52\", \"document_group\": \"laporan_kas_bank\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:52'),(174,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',11,NULL,'{\"id\": 11, \"label\": \"Pengurus\", \"created_at\": \"2026-07-18 22:49:52\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:52\", \"document_group\": \"laporan_upf\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:52'),(175,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'create','App\\Models\\DocumentSignatureSlot',12,NULL,'{\"id\": 12, \"label\": \"Diketahui oleh (Pengurus)\", \"created_at\": \"2026-07-18 22:49:53\", \"slot_order\": 0, \"updated_at\": \"2026-07-18 22:49:53\", \"document_group\": \"unit_usaha\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 15:49:53'),(176,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 17:11:56'),(177,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 17:12:09'),(178,NULL,NULL,NULL,'update','App\\Models\\User',1,'{\"password\": \"$argon2id$v=19$m=65536,t=4,p=1$V2R0bENVRnFGTnVRaEs4ZA$ZzudCWmmNmuEQqsq1a5UaXNT9rP2dvSrPMkQWxYtrwE\", \"updated_at\": \"2026-07-18T13:15:15.000000Z\"}','{\"password\": \"$argon2id$v=19$m=65536,t=4,p=1$S2VTNjl1Y3FxbGw2ZC93Tw$T2jqor0S68ZQCd/sYgdDuLfv57gn8/imaTWoN30+Nko\", \"updated_at\": \"2026-07-19 00:12:46\"}','127.0.0.1','Symfony','2026-07-18 17:12:48'),(179,NULL,NULL,NULL,'update','App\\Models\\User',4,'{\"password\": \"$argon2id$v=19$m=65536,t=4,p=1$TjhaR2psb3RCZkVqSHZReQ$AgtKBRHMYjnTN3GQpo+3MaLPuZ+8/LFg4UUmgSuM+QA\", \"updated_at\": \"2026-07-18T13:11:08.000000Z\"}','{\"password\": \"$argon2id$v=19$m=65536,t=4,p=1$UmhCYUFYYmR0OExzdmZqbw$kpSQKfnx1INL8cRtdeKOA2mcbsmFXe9PRQw9l2tzep8\", \"updated_at\": \"2026-07-19 00:34:10\"}','127.0.0.1','Symfony','2026-07-18 17:34:10'),(180,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'logout','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.22209.0 Chrome/148.0.7778.271 Electron/42.5.1 Safari/537.36 MSIX','2026-07-18 17:35:34'),(181,4,'teller',NULL,'login','App\\Models\\User',4,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.22209.0 Chrome/148.0.7778.271 Electron/42.5.1 Safari/537.36 MSIX','2026-07-18 17:36:40'),(182,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 22:17:03'),(183,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 22:37:25'),(184,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 22:37:41'),(185,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.22209.0 Chrome/148.0.7778.271 Electron/42.5.1 Safari/537.36 MSIX','2026-07-18 22:38:19'),(186,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 22:50:32'),(187,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',1,'create','App\\Models\\OpeningBalanceBatch',1,NULL,'{\"id\": 1, \"branch_id\": \"1\", \"created_at\": \"2026-07-19 06:13:28\", \"updated_at\": \"2026-07-19 06:13:28\", \"cutoff_date\": \"2026-06-30 00:00:00\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-18 23:13:29'),(188,NULL,NULL,1,'create','App\\Models\\OpeningBalanceBatch',2,NULL,'{\"id\": 2, \"status\": \"draft\", \"branch_id\": 1, \"created_at\": \"2026-07-19 06:37:24\", \"updated_at\": \"2026-07-19 06:37:24\", \"cutoff_date\": \"2026-07-18 06:37:24\"}','127.0.0.1','Symfony','2026-07-18 23:37:25'),(189,NULL,NULL,1,'create','App\\Models\\OpeningBalanceBatch',3,NULL,'{\"id\": 3, \"status\": \"draft\", \"branch_id\": 1, \"created_at\": \"2026-07-19 06:37:41\", \"updated_at\": \"2026-07-19 06:37:41\", \"cutoff_date\": \"2026-07-18 06:37:41\"}','127.0.0.1','Symfony','2026-07-18 23:37:41'),(190,NULL,NULL,1,'create','App\\Models\\JournalEntry',33,NULL,'{\"id\": 33, \"branch_id\": 1, \"source_id\": 3, \"created_at\": \"2026-07-19 06:37:42\", \"created_by\": 1, \"entry_date\": \"2026-07-18 00:00:00\", \"updated_at\": \"2026-07-19 06:37:42\", \"description\": \"Jurnal pembukaan migrasi saldo awal (batch #3)\", \"source_type\": \"App\\\\Models\\\\OpeningBalanceBatch\"}','127.0.0.1','Symfony','2026-07-18 23:37:42'),(191,NULL,NULL,1,'update','App\\Models\\OpeningBalanceBatch',3,'{\"status\": \"draft\", \"updated_at\": \"2026-07-18T23:37:41.000000Z\"}','{\"status\": \"locked\", \"locked_at\": \"2026-07-19 06:37:42\", \"locked_by\": 1, \"updated_at\": \"2026-07-19 06:37:42\", \"reconciliation_snapshot\": \"{\\\"coa_debit_total\\\":300000,\\\"coa_credit_total\\\":300000,\\\"savings_discrepancies\\\":[],\\\"loans_discrepancies\\\":[],\\\"upf_discrepancies\\\":[],\\\"stock_discrepancies\\\":[]}\"}','127.0.0.1','Symfony','2026-07-18 23:37:42'),(192,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 23:37:54'),(193,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 23:39:55'),(194,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-18 23:40:10'),(195,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',3,'create','App\\Models\\LoanRepayment',2,NULL,'{\"id\": 2, \"amount\": 90000, \"loan_id\": 4, \"branch_id\": 3, \"created_at\": \"2026-07-19 07:25:09\", \"created_by\": 1, \"updated_at\": \"2026-07-19 07:25:09\", \"description\": \".\", \"balance_after\": 8870000, \"interest_portion\": 80000, \"principal_portion\": 10000}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 00:25:10'),(196,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',3,'create','App\\Models\\JournalEntry',34,NULL,'{\"id\": 34, \"branch_id\": 3, \"source_id\": 2, \"created_at\": \"2026-07-19 07:25:10\", \"created_by\": 1, \"entry_date\": \"2026-07-19 00:00:00\", \"updated_at\": \"2026-07-19 07:25:10\", \"description\": \".\", \"source_type\": \"App\\\\Models\\\\LoanRepayment\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 00:25:10'),(197,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',3,'update','App\\Models\\LoanRepayment',2,'{\"updated_at\": \"2026-07-19T00:25:09.000000Z\"}','{\"updated_at\": \"2026-07-19 07:25:10\", \"journal_entry_id\": 34}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-07-19 00:25:10'),(198,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-19 00:34:30'),(199,1,'anggota,teller,petugas_kredit,petugas_upf,manajer,bendahara,pengawas,admin_sistem',NULL,'login','App\\Models\\User',1,NULL,NULL,'127.0.0.1','Symfony','2026-07-19 00:34:51');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_branch_id` bigint unsigned DEFAULT NULL,
  `operational_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_code_unique` (`code`),
  KEY `branches_parent_branch_id_foreign` (`parent_branch_id`),
  CONSTRAINT `branches_parent_branch_id_foreign` FOREIGN KEY (`parent_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'PST','Kantor Pusat','Jl. Utama No. 1',NULL,'2023-07-18',1,'2026-07-18 13:10:43','2026-07-18 13:10:43'),(2,'PLK','Cabang Palangka Raya','Jl. Cabang Palangka Raya',NULL,'2024-07-18',1,'2026-07-18 13:10:43','2026-07-18 13:10:43'),(3,'BTM','Cabang Batam','Jl. Cabang Batam',NULL,'2025-07-18',1,'2026-07-18 13:10:43','2026-07-18 13:10:43');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `business_unit_transactions`
--

DROP TABLE IF EXISTS `business_unit_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_unit_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_unit_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `type` enum('pendapatan','beban') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `business_unit_transactions_branch_id_foreign` (`branch_id`),
  KEY `business_unit_transactions_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `business_unit_transactions_created_by_foreign` (`created_by`),
  KEY `business_unit_transactions_business_unit_id_created_at_index` (`business_unit_id`,`created_at`),
  CONSTRAINT `business_unit_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `business_unit_transactions_business_unit_id_foreign` FOREIGN KEY (`business_unit_id`) REFERENCES `business_units` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `business_unit_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `business_unit_transactions_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `business_unit_transactions`
--

LOCK TABLES `business_unit_transactions` WRITE;
/*!40000 ALTER TABLE `business_unit_transactions` DISABLE KEYS */;
INSERT INTO `business_unit_transactions` VALUES (1,1,1,'pendapatan',750000.00,'Sewa aula untuk acara RT',30,7,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(2,1,1,'beban',100000.00,'Biaya kebersihan pasca acara',31,7,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(3,2,1,'pendapatan',250000.00,'Penjualan fotokopi & ATK harian',32,7,'2026-07-18 13:11:23','2026-07-18 13:11:23');
/*!40000 ALTER TABLE `business_unit_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `business_units`
--

DROP TABLE IF EXISTS `business_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coa_revenue_account_id` bigint unsigned DEFAULT NULL,
  `coa_expense_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_units_code_unique` (`code`),
  KEY `business_units_coa_revenue_account_id_foreign` (`coa_revenue_account_id`),
  KEY `business_units_coa_expense_account_id_foreign` (`coa_expense_account_id`),
  CONSTRAINT `business_units_coa_expense_account_id_foreign` FOREIGN KEY (`coa_expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `business_units_coa_revenue_account_id_foreign` FOREIGN KEY (`coa_revenue_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `business_units`
--

LOCK TABLES `business_units` WRITE;
/*!40000 ALTER TABLE `business_units` DISABLE KEYS */;
INSERT INTO `business_units` VALUES (1,'UNIT-001','Sewa Aula Serbaguna',60,84,1,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(2,'UNIT-002','Fotokopi & ATK',68,82,1,'2026-07-18 13:11:22','2026-07-18 13:11:22');
/*!40000 ALTER TABLE `business_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_categories`
--

DROP TABLE IF EXISTS `cash_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('masuk','keluar') COLLATE utf8mb4_unicode_ci NOT NULL,
  `coa_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_categories_code_unique` (`code`),
  KEY `cash_categories_coa_account_id_foreign` (`coa_account_id`),
  CONSTRAINT `cash_categories_coa_account_id_foreign` FOREIGN KEY (`coa_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_categories`
--

LOCK TABLES `cash_categories` WRITE;
/*!40000 ALTER TABLE `cash_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chart_of_accounts`
--

DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chart_of_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('ASET','LIABILITAS','EKUITAS','PENDAPATAN','BEBAN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `normal_balance` enum('DEBIT','KREDIT') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_postable` tinyint(1) NOT NULL DEFAULT '0',
  `parent_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statement` enum('NERACA','LABA_RUGI') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_of_accounts_code_unique` (`code`),
  KEY `chart_of_accounts_parent_code_index` (`parent_code`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chart_of_accounts`
--

LOCK TABLES `chart_of_accounts` WRITE;
/*!40000 ALTER TABLE `chart_of_accounts` DISABLE KEYS */;
INSERT INTO `chart_of_accounts` VALUES (1,'1000','ASET','ASET',NULL,'DEBIT',0,NULL,'NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(2,'1100','Aset Lancar','ASET','Aset Lancar','DEBIT',0,'1000','NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(3,'1101','Kas','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Kas di tangan / kas cabang (termasuk kas Teller)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(4,'1102','Kas Kecil','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Petty cash','2026-07-18 13:09:35','2026-07-18 13:09:35'),(5,'1110','Bank','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Rekening bank koperasi','2026-07-18 13:09:35','2026-07-18 13:09:35'),(6,'1120','Piutang Pinjaman Anggota','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Pokok pinjaman beredar','2026-07-18 13:09:35','2026-07-18 13:09:35'),(7,'1121','Penyisihan Penurunan Nilai Piutang','ASET','Aset Lancar','KREDIT',1,'1100','NERACA','Kontra-aset (cadangan kerugian piutang)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(8,'1130','Piutang Jasa Pinjaman','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Jasa yang masih harus diterima','2026-07-18 13:09:35','2026-07-18 13:09:35'),(9,'1131','Piutang Iuran UPF','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','v2 - Piutang iuran kios/tenant (kebersihan/keamanan/listrik/air/dst) - default mapping coa_account_receivable_id Master Jenis Iuran','2026-07-18 13:09:35','2026-07-18 13:09:35'),(10,'1132','Rekening Antar Kantor (RAK)','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','v2 - Hanya dipakai bila transaksi lintas cabang diaktifkan (PRD Sec 6). Saldo per cabang bisa debit/kredit; total seluruh cabang harus nol dan dieliminasi saat laporan konsolidasi (RPT-05)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(11,'1133','Piutang Denda Pinjaman','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','v2 - Denda keterlambatan angsuran yang masih harus diterima - default mapping coa_account_penalty_receivable_id Master Produk Pinjaman','2026-07-18 13:09:35','2026-07-18 13:09:35'),(12,'1140','Persediaan Barang Dagang','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Unit Toko/Ritel - dikelola perpetual via Stock Ledger Engine (average cost)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(13,'1150','Biaya Dibayar Dimuka','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','Sewa/asuransi dibayar dimuka','2026-07-18 13:09:35','2026-07-18 13:09:35'),(14,'1160','Perlengkapan','ASET','Aset Lancar','DEBIT',1,'1100','NERACA','ATK/supplies','2026-07-18 13:09:35','2026-07-18 13:09:35'),(15,'1200','Aset Tidak Lancar','ASET','Aset Tidak Lancar','DEBIT',0,'1000','NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(16,'1210','Tanah','ASET','Aset Tidak Lancar','DEBIT',1,'1200','NERACA',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(17,'1220','Bangunan','ASET','Aset Tidak Lancar','DEBIT',1,'1200','NERACA',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(18,'1221','Akumulasi Penyusutan Bangunan','ASET','Aset Tidak Lancar','KREDIT',1,'1200','NERACA','Kontra-aset','2026-07-18 13:09:35','2026-07-18 13:09:35'),(19,'1230','Peralatan & Inventaris','ASET','Aset Tidak Lancar','DEBIT',1,'1200','NERACA',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(20,'1231','Akumulasi Penyusutan Peralatan','ASET','Aset Tidak Lancar','KREDIT',1,'1200','NERACA','Kontra-aset','2026-07-18 13:09:35','2026-07-18 13:09:35'),(21,'1240','Kendaraan','ASET','Aset Tidak Lancar','DEBIT',1,'1200','NERACA',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(22,'1241','Akumulasi Penyusutan Kendaraan','ASET','Aset Tidak Lancar','KREDIT',1,'1200','NERACA','Kontra-aset','2026-07-18 13:09:35','2026-07-18 13:09:35'),(23,'1250','Aset Tetap Lainnya','ASET','Aset Tidak Lancar','DEBIT',1,'1200','NERACA','v2 - Catch-all untuk Kategori Aktiva Tetap dinamis baru (Master Kategori Aktiva Tetap) yang belum punya akun khusus','2026-07-18 13:09:35','2026-07-18 13:09:35'),(24,'1251','Akumulasi Penyusutan Aset Tetap Lainnya','ASET','Aset Tidak Lancar','KREDIT',1,'1200','NERACA','v2 - Kontra-aset pasangan 1250','2026-07-18 13:09:35','2026-07-18 13:09:35'),(25,'2000','LIABILITAS','LIABILITAS',NULL,'KREDIT',0,NULL,'NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(26,'2100','Liabilitas Jangka Pendek','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',0,'2000','NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(27,'2101','Simpanan Sukarela Anggota','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Dapat ditarik sewaktu-waktu -> kewajiban','2026-07-18 13:09:35','2026-07-18 13:09:35'),(28,'2102','Simpanan Berjangka (< 12 bln)','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Deposito anggota jatuh tempo <12 bln','2026-07-18 13:09:35','2026-07-18 13:09:35'),(29,'2110','Utang Usaha','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','v2 - Utang ke Supplier (Master Supplier) untuk pembelian barang dagang MAUPUN pembelian aktiva tetap - default mapping coa_account_payable_id','2026-07-18 13:09:35','2026-07-18 13:09:35'),(30,'2120','Utang Jasa Simpanan','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Jasa simpanan yang masih harus dibayar','2026-07-18 13:09:35','2026-07-18 13:09:35'),(31,'2130','Beban Masih Harus Dibayar','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Accrued expenses','2026-07-18 13:09:35','2026-07-18 13:09:35'),(32,'2140','Utang Pajak','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(33,'2150','Dana Pembagian SHU','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Bagian SHU anggota yang belum dibayarkan','2026-07-18 13:09:35','2026-07-18 13:09:35'),(34,'2160','Dana Pendidikan','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Dana-dana dari alokasi SHU','2026-07-18 13:09:35','2026-07-18 13:09:35'),(35,'2161','Dana Sosial','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Dana-dana dari alokasi SHU','2026-07-18 13:09:35','2026-07-18 13:09:35'),(36,'2162','Dana Pengurus & Pengawas','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Dana-dana dari alokasi SHU','2026-07-18 13:09:35','2026-07-18 13:09:35'),(37,'2163','Dana Pembangunan Daerah Kerja','LIABILITAS','Liabilitas Jangka Pendek','KREDIT',1,'2100','NERACA','Dana-dana dari alokasi SHU','2026-07-18 13:09:35','2026-07-18 13:09:35'),(38,'2200','Liabilitas Jangka Panjang','LIABILITAS','Liabilitas Jangka Panjang','KREDIT',0,'2000','NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(39,'2210','Simpanan Berjangka (> 12 bln)','LIABILITAS','Liabilitas Jangka Panjang','KREDIT',1,'2200','NERACA','Deposito anggota jatuh tempo >12 bln','2026-07-18 13:09:35','2026-07-18 13:09:35'),(40,'2220','Utang Bank Jangka Panjang','LIABILITAS','Liabilitas Jangka Panjang','KREDIT',1,'2200','NERACA','Pinjaman dari bank/lembaga lain','2026-07-18 13:09:35','2026-07-18 13:09:35'),(41,'3000','EKUITAS','EKUITAS',NULL,'KREDIT',0,NULL,'NERACA','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(42,'3101','Simpanan Pokok','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Modal anggota - dibayar sekali saat masuk. Berlaku untuk Jenis Anggota dengan wajib_simpanan_pokok=TRUE','2026-07-18 13:09:35','2026-07-18 13:09:35'),(43,'3102','Simpanan Wajib','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Modal anggota - dibayar rutin','2026-07-18 13:09:35','2026-07-18 13:09:35'),(44,'3110','Modal Penyertaan','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Modal penyertaan (bila bersifat ekuitas)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(45,'3120','Modal Sumbangan/Hibah','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Hibah/donasi yang tidak mengikat','2026-07-18 13:09:35','2026-07-18 13:09:35'),(46,'3130','Dana Cadangan (Cadangan Umum)','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Akumulasi cadangan dari SHU','2026-07-18 13:09:35','2026-07-18 13:09:35'),(47,'3140','Cadangan Tujuan Risiko','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Cadangan risiko untuk USP','2026-07-18 13:09:35','2026-07-18 13:09:35'),(48,'3150','SHU Tahun Berjalan','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Sisa Hasil Usaha belum dibagi (tahun berjalan) - hanya menghitung kontribusi Jenis Anggota dengan berlaku_untuk_shu=TRUE','2026-07-18 13:09:35','2026-07-18 13:09:35'),(49,'3160','SHU Ditahan / Tahun Lalu','EKUITAS','Ekuitas','KREDIT',1,'3000','NERACA','Akumulasi SHU tahun-tahun sebelumnya','2026-07-18 13:09:35','2026-07-18 13:09:35'),(50,'4000','PENDAPATAN','PENDAPATAN',NULL,'KREDIT',0,NULL,'LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(51,'4100','Pendapatan Unit Simpan Pinjam','PENDAPATAN','Pendapatan Usaha','KREDIT',0,'4000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(52,'4101','Pendapatan Jasa Pinjaman','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4100','LABA_RUGI','Default mapping coa_account_interest_income_id Master Produk Pinjaman','2026-07-18 13:09:35','2026-07-18 13:09:35'),(53,'4102','Pendapatan Provisi & Administrasi','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4100','LABA_RUGI','Default mapping coa_account_provision_income_id Master Produk Pinjaman','2026-07-18 13:09:35','2026-07-18 13:09:35'),(54,'4103','Pendapatan Denda Keterlambatan','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4100','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(55,'4200','Pendapatan Unit Toko','PENDAPATAN','Pendapatan Usaha','KREDIT',0,'4000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(56,'4201','Penjualan Barang Dagang','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4200','LABA_RUGI','Default mapping coa_account_sales_revenue_id Master Barang (POS)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(57,'4202','Retur Penjualan','PENDAPATAN','Pendapatan Usaha','DEBIT',1,'4200','LABA_RUGI','Kontra-pendapatan - dipakai Retur Penjualan (STK-04)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(58,'4203','Potongan Penjualan','PENDAPATAN','Pendapatan Usaha','DEBIT',1,'4200','LABA_RUGI','Kontra-pendapatan / diskon anggota','2026-07-18 13:09:35','2026-07-18 13:09:35'),(59,'4300','Pendapatan Unit Pengelola Fasilitas','PENDAPATAN','Pendapatan Usaha','KREDIT',0,'4000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(60,'4301','Pendapatan Sewa/Jasa Fasilitas','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4300','LABA_RUGI','UPF','2026-07-18 13:09:35','2026-07-18 13:09:35'),(61,'4302','Pendapatan Iuran Kebersihan','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4300','LABA_RUGI','v2 - Default mapping coa_account_revenue_id untuk Jenis Iuran \"Kebersihan\" (contoh bawaan, Master Jenis Iuran dinamis)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(62,'4303','Pendapatan Iuran Keamanan','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4300','LABA_RUGI','v2 - contoh bawaan Jenis Iuran \"Keamanan\"','2026-07-18 13:09:35','2026-07-18 13:09:35'),(63,'4304','Pendapatan Iuran Listrik','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4300','LABA_RUGI','v2 - contoh bawaan Jenis Iuran \"Listrik\" (satuan per-meter)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(64,'4305','Pendapatan Iuran Air','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4300','LABA_RUGI','v2 - contoh bawaan Jenis Iuran \"Air\" (satuan per-meter)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(65,'4306','Pendapatan Iuran Lainnya','PENDAPATAN','Pendapatan Usaha','KREDIT',1,'4300','LABA_RUGI','v2 - Catch-all untuk Jenis Iuran dinamis baru yang belum punya akun khusus','2026-07-18 13:09:35','2026-07-18 13:09:35'),(66,'4900','Pendapatan Lain-lain','PENDAPATAN','Pendapatan Lain-lain','KREDIT',0,'4000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(67,'4901','Pendapatan Bunga Bank','PENDAPATAN','Pendapatan Lain-lain','KREDIT',1,'4900','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(68,'4902','Pendapatan Lain','PENDAPATAN','Pendapatan Lain-lain','KREDIT',1,'4900','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(69,'4903','Laba Pelepasan Aset Tetap','PENDAPATAN','Pendapatan Lain-lain','KREDIT',1,'4900','LABA_RUGI','v2 - Selisih positif nilai jual vs nilai buku saat Pelepasan Aktiva Tetap (DEP-05)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(70,'4904','Pendapatan Penyesuaian Persediaan','PENDAPATAN','Pendapatan Lain-lain','KREDIT',1,'4900','LABA_RUGI','v2 - Selisih plus Koreksi Persediaan (stok fisik > stok sistem, STK-05)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(71,'5000','BEBAN','BEBAN',NULL,'DEBIT',0,NULL,'LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(72,'5100','Beban Pokok','BEBAN','Beban Pokok','DEBIT',0,'5000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(73,'5101','Harga Pokok Penjualan','BEBAN','Beban Pokok','DEBIT',1,'5100','LABA_RUGI','HPP Unit Toko - default mapping coa_account_cogs_id Master Barang, dihitung dari average cost berjalan (Stock Ledger Engine)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(74,'5102','Beban Jasa Simpanan','BEBAN','Beban Pokok','DEBIT',1,'5100','LABA_RUGI','Jasa simpanan sukarela & berjangka - default mapping coa_account_interest_expense_id Master Produk Simpanan','2026-07-18 13:09:35','2026-07-18 13:09:35'),(75,'5103','Retur Pembelian','BEBAN','Beban Pokok','KREDIT',1,'5100','LABA_RUGI','v2 - Kontra-beban, opsional/informasional: pada metode perpetual/average cost (Stock Ledger Engine) retur pembelian secara default langsung mengurangi 1140 Persediaan tanpa menyentuh akun ini; pakai akun ini hanya bila koperasi ingin melacak nilai retur pembelian secara terpisah di CALK','2026-07-18 13:09:35','2026-07-18 13:09:35'),(76,'5200','Beban Operasional','BEBAN','Beban Usaha','DEBIT',0,'5000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(77,'5201','Beban Gaji & Tunjangan','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(78,'5202','Beban Sewa','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(79,'5203','Beban Listrik, Air & Komunikasi','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(80,'5204','Beban Penyusutan','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI','v2 - Dipakai Depreciation Engine (batch bulanan) untuk seluruh Kategori Aktiva Tetap kecuali koperasi memilih memecah ke sub-akun tersendiri per kategori','2026-07-18 13:09:35','2026-07-18 13:09:35'),(81,'5205','Beban Penyisihan Piutang','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI','Kerugian penurunan nilai piutang','2026-07-18 13:09:35','2026-07-18 13:09:35'),(82,'5206','Beban Administrasi & Umum','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(83,'5207','Beban Perlengkapan/ATK','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(84,'5208','Beban Pemeliharaan','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(85,'5209','Beban Rapat Anggota (RAT)','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(86,'5210','Beban Pendidikan & Pelatihan','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(87,'5211','Beban Promosi','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(88,'5212','Beban Susut/Kerugian Persediaan','BEBAN','Beban Usaha','DEBIT',1,'5200','LABA_RUGI','v2 - Selisih minus Koreksi Persediaan setelah disetujui (stok fisik < stok sistem, STK-05/06)','2026-07-18 13:09:35','2026-07-18 13:09:35'),(89,'5900','Beban Lain-lain','BEBAN','Beban Lain-lain','DEBIT',0,'5000','LABA_RUGI','Header','2026-07-18 13:09:35','2026-07-18 13:09:35'),(90,'5901','Beban Bunga Pinjaman Bank','BEBAN','Beban Lain-lain','DEBIT',1,'5900','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(91,'5902','Beban Pajak','BEBAN','Beban Lain-lain','DEBIT',1,'5900','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(92,'5903','Beban Lain','BEBAN','Beban Lain-lain','DEBIT',1,'5900','LABA_RUGI',NULL,'2026-07-18 13:09:35','2026-07-18 13:09:35'),(93,'5904','Rugi Pelepasan Aset Tetap','BEBAN','Beban Lain-lain','DEBIT',1,'5900','LABA_RUGI','v2 - Selisih negatif nilai jual vs nilai buku saat Pelepasan Aktiva Tetap (DEP-05)','2026-07-18 13:09:35','2026-07-18 13:09:35');
/*!40000 ALTER TABLE `chart_of_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cooperative_event_branch`
--

DROP TABLE IF EXISTS `cooperative_event_branch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperative_event_branch` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cooperative_event_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coop_event_branch_unique` (`cooperative_event_id`,`branch_id`),
  KEY `cooperative_event_branch_branch_id_foreign` (`branch_id`),
  CONSTRAINT `cooperative_event_branch_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cooperative_event_branch_cooperative_event_id_foreign` FOREIGN KEY (`cooperative_event_id`) REFERENCES `cooperative_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperative_event_branch`
--

LOCK TABLES `cooperative_event_branch` WRITE;
/*!40000 ALTER TABLE `cooperative_event_branch` DISABLE KEYS */;
/*!40000 ALTER TABLE `cooperative_event_branch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cooperative_event_members`
--

DROP TABLE IF EXISTS `cooperative_event_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperative_event_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cooperative_event_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coop_event_member_unique` (`cooperative_event_id`,`member_id`),
  KEY `cooperative_event_members_member_id_foreign` (`member_id`),
  CONSTRAINT `cooperative_event_members_cooperative_event_id_foreign` FOREIGN KEY (`cooperative_event_id`) REFERENCES `cooperative_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cooperative_event_members_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperative_event_members`
--

LOCK TABLES `cooperative_event_members` WRITE;
/*!40000 ALTER TABLE `cooperative_event_members` DISABLE KEYS */;
INSERT INTO `cooperative_event_members` VALUES (1,2,1),(2,2,2);
/*!40000 ALTER TABLE `cooperative_event_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cooperative_event_roles`
--

DROP TABLE IF EXISTS `cooperative_event_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperative_event_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cooperative_event_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coop_event_role_unique` (`cooperative_event_id`,`role_id`),
  KEY `cooperative_event_roles_role_id_foreign` (`role_id`),
  CONSTRAINT `cooperative_event_roles_cooperative_event_id_foreign` FOREIGN KEY (`cooperative_event_id`) REFERENCES `cooperative_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cooperative_event_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperative_event_roles`
--

LOCK TABLES `cooperative_event_roles` WRITE;
/*!40000 ALTER TABLE `cooperative_event_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `cooperative_event_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cooperative_events`
--

DROP TABLE IF EXISTS `cooperative_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperative_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch_scope_type` enum('single','multiple','all') COLLATE utf8mb4_unicode_ci NOT NULL,
  `single_branch_id` bigint unsigned DEFAULT NULL,
  `participant_type` enum('anggota_tertentu','role_tertentu','semua_anggota_cabang') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reminder_days` json DEFAULT NULL,
  `status` enum('draft','terjadwal','selesai','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cooperative_events_single_branch_id_foreign` (`single_branch_id`),
  KEY `cooperative_events_created_by_foreign` (`created_by`),
  CONSTRAINT `cooperative_events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cooperative_events_single_branch_id_foreign` FOREIGN KEY (`single_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperative_events`
--

LOCK TABLES `cooperative_events` WRITE;
/*!40000 ALTER TABLE `cooperative_events` DISABLE KEYS */;
INSERT INTO `cooperative_events` VALUES (1,'Rapat Anggota Tahunan (RAT) 2026','Pembahasan laporan pertanggungjawaban pengurus tahun buku 2025.','2026-08-07 09:00:00','2026-08-07 13:00:00','Aula Kantor Pusat','all',NULL,'semua_anggota_cabang','[7, 1]','terjadwal',3,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(2,'Sosialisasi Produk Pinjaman Baru','Sosialisasi ke anggota terpilih mengenai produk pinjaman modal usaha.','2026-07-23 10:00:00','2026-07-23 11:30:00','Ruang Pertemuan Kantor Pusat','single',1,'anggota_tertentu','[1]','terjadwal',3,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(3,'Penutupan Buku Bulanan (sudah lewat)',NULL,'2026-07-15 08:00:00','2026-07-15 17:00:00','Kantor Pusat','all',NULL,'semua_anggota_cabang','[]','terjadwal',7,'2026-07-18 13:11:23','2026-07-18 13:11:23');
/*!40000 ALTER TABLE `cooperative_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `depreciation_batch_runs`
--

DROP TABLE IF EXISTS `depreciation_batch_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `depreciation_batch_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assets_processed` int unsigned NOT NULL,
  `assets_skipped` int unsigned NOT NULL,
  `triggered_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `depreciation_batch_runs_triggered_by_foreign` (`triggered_by`),
  CONSTRAINT `depreciation_batch_runs_triggered_by_foreign` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `depreciation_batch_runs`
--

LOCK TABLES `depreciation_batch_runs` WRITE;
/*!40000 ALTER TABLE `depreciation_batch_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `depreciation_batch_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_signatories`
--

DROP TABLE IF EXISTS `document_signatories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_signatories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_signatories`
--

LOCK TABLES `document_signatories` WRITE;
/*!40000 ALTER TABLE `document_signatories` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_signatories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_signature_slots`
--

DROP TABLE IF EXISTS `document_signature_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_signature_slots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_group` enum('pengajuan_pinjaman','pengajuan_penarikan','kas_keluar','kas_masuk','jurnal_umum','dokumen_gudang','aktiva_tetap','laporan_kas_bank','laporan_upf','unit_usaha') COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_order` smallint unsigned NOT NULL DEFAULT '0',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_signatory_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_signature_slots_document_signatory_id_foreign` (`document_signatory_id`),
  KEY `document_signature_slots_document_group_slot_order_index` (`document_group`,`slot_order`),
  CONSTRAINT `document_signature_slots_document_signatory_id_foreign` FOREIGN KEY (`document_signatory_id`) REFERENCES `document_signatories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_signature_slots`
--

LOCK TABLES `document_signature_slots` WRITE;
/*!40000 ALTER TABLE `document_signature_slots` DISABLE KEYS */;
INSERT INTO `document_signature_slots` VALUES (1,'pengajuan_pinjaman',0,'Diperiksa oleh (Pengurus)',NULL,'2026-07-18 15:49:50','2026-07-18 15:49:50'),(2,'pengajuan_pinjaman',1,'Disetujui oleh (Ketua Koperasi)',NULL,'2026-07-18 15:49:50','2026-07-18 15:49:50'),(3,'pengajuan_penarikan',0,'Diperiksa oleh (Pengurus)',NULL,'2026-07-18 15:49:50','2026-07-18 15:49:50'),(4,'pengajuan_penarikan',1,'Disetujui oleh (Ketua Koperasi)',NULL,'2026-07-18 15:49:50','2026-07-18 15:49:50'),(5,'kas_keluar',0,'Pengurus/Ketua Koperasi',NULL,'2026-07-18 15:49:51','2026-07-18 15:49:51'),(6,'kas_masuk',0,'Pengurus/Ketua Koperasi',NULL,'2026-07-18 15:49:51','2026-07-18 15:49:51'),(7,'jurnal_umum',0,'Pengurus',NULL,'2026-07-18 15:49:51','2026-07-18 15:49:51'),(8,'dokumen_gudang',0,'Diketahui/Disetujui oleh (Pengurus)',NULL,'2026-07-18 15:49:52','2026-07-18 15:49:52'),(9,'aktiva_tetap',0,'Diketahui/Disetujui oleh (Pengurus)',NULL,'2026-07-18 15:49:52','2026-07-18 15:49:52'),(10,'laporan_kas_bank',0,'Pengurus',NULL,'2026-07-18 15:49:52','2026-07-18 15:49:52'),(11,'laporan_upf',0,'Pengurus',NULL,'2026-07-18 15:49:52','2026-07-18 15:49:52'),(12,'unit_usaha',0,'Diketahui oleh (Pengurus)',NULL,'2026-07-18 15:49:53','2026-07-18 15:49:53');
/*!40000 ALTER TABLE `document_signature_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_report_exports`
--

DROP TABLE IF EXISTS `financial_report_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_report_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_kind` enum('neraca','laba_rugi','arus_kas','calk') COLLATE utf8mb4_unicode_ci NOT NULL,
  `basis` enum('sak_ep','sak_emkm') COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` bigint unsigned DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `as_of_date` date DEFAULT NULL,
  `format` enum('pdf','xlsx') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu','memproses','selesai','gagal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financial_report_exports_branch_id_foreign` (`branch_id`),
  KEY `financial_report_exports_requested_by_foreign` (`requested_by`),
  CONSTRAINT `financial_report_exports_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financial_report_exports_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_report_exports`
--

LOCK TABLES `financial_report_exports` WRITE;
/*!40000 ALTER TABLE `financial_report_exports` DISABLE KEYS */;
INSERT INTO `financial_report_exports` VALUES (1,'neraca','sak_ep',NULL,NULL,NULL,'2026-07-18','pdf','selesai','financial-report-exports/laporan-keuangan-1.pdf',NULL,1,'2026-07-18 15:50:10','2026-07-18 13:28:17','2026-07-18 15:50:10'),(2,'laba_rugi','sak_ep',NULL,'2026-07-01','2026-07-18',NULL,'pdf','selesai','financial-report-exports/laporan-keuangan-2.pdf',NULL,1,'2026-07-18 15:50:11','2026-07-18 15:35:27','2026-07-18 15:50:11');
/*!40000 ALTER TABLE `financial_report_exports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_categories`
--

DROP TABLE IF EXISTS `fixed_asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_depreciation_method` enum('garis_lurus','saldo_menurun') COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_useful_life_months` smallint unsigned NOT NULL,
  `coa_asset_account_id` bigint unsigned DEFAULT NULL,
  `coa_accumulated_depreciation_account_id` bigint unsigned DEFAULT NULL,
  `coa_depreciation_expense_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fixed_asset_categories_code_unique` (`code`),
  KEY `fac_asset_account_fk` (`coa_asset_account_id`),
  KEY `fac_accum_dep_account_fk` (`coa_accumulated_depreciation_account_id`),
  KEY `fac_dep_expense_account_fk` (`coa_depreciation_expense_account_id`),
  CONSTRAINT `fac_accum_dep_account_fk` FOREIGN KEY (`coa_accumulated_depreciation_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fac_asset_account_fk` FOREIGN KEY (`coa_asset_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fac_dep_expense_account_fk` FOREIGN KEY (`coa_depreciation_expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_categories`
--

LOCK TABLES `fixed_asset_categories` WRITE;
/*!40000 ALTER TABLE `fixed_asset_categories` DISABLE KEYS */;
INSERT INTO `fixed_asset_categories` VALUES (1,'KAT-PRL','Peralatan Kantor','garis_lurus',48,19,20,80,1,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(2,'KAT-KDR','Kendaraan','garis_lurus',96,21,22,80,1,'2026-07-18 13:11:20','2026-07-18 13:11:20');
/*!40000 ALTER TABLE `fixed_asset_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_depreciation_entries`
--

DROP TABLE IF EXISTS `fixed_asset_depreciation_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_depreciation_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fixed_asset_id` bigint unsigned NOT NULL,
  `period` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `opening_book_value` decimal(18,2) NOT NULL,
  `depreciation_amount` decimal(18,2) NOT NULL,
  `closing_book_value` decimal(18,2) NOT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `faded_asset_period_unique` (`fixed_asset_id`,`period`),
  KEY `fixed_asset_depreciation_entries_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `fixed_asset_depreciation_entries_created_by_foreign` (`created_by`),
  CONSTRAINT `fixed_asset_depreciation_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_asset_depreciation_entries_fixed_asset_id_foreign` FOREIGN KEY (`fixed_asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_asset_depreciation_entries_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_depreciation_entries`
--

LOCK TABLES `fixed_asset_depreciation_entries` WRITE;
/*!40000 ALTER TABLE `fixed_asset_depreciation_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `fixed_asset_depreciation_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_asset_disposals`
--

DROP TABLE IF EXISTS `fixed_asset_disposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_asset_disposals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fixed_asset_id` bigint unsigned NOT NULL,
  `disposal_type` enum('dijual','dihapusbukukan','hilang') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `book_value_at_disposal` decimal(18,2) NOT NULL,
  `gain_loss_amount` decimal(18,2) NOT NULL,
  `disposal_date` date NOT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fixed_asset_disposals_fixed_asset_id_foreign` (`fixed_asset_id`),
  KEY `fixed_asset_disposals_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `fixed_asset_disposals_created_by_foreign` (`created_by`),
  KEY `fixed_asset_disposals_cancelled_by_foreign` (`cancelled_by`),
  KEY `fixed_asset_disposals_reversal_journal_entry_id_foreign` (`reversal_journal_entry_id`),
  CONSTRAINT `fixed_asset_disposals_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_asset_disposals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_asset_disposals_fixed_asset_id_foreign` FOREIGN KEY (`fixed_asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_asset_disposals_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_asset_disposals_reversal_journal_entry_id_foreign` FOREIGN KEY (`reversal_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_asset_disposals`
--

LOCK TABLES `fixed_asset_disposals` WRITE;
/*!40000 ALTER TABLE `fixed_asset_disposals` DISABLE KEYS */;
/*!40000 ALTER TABLE `fixed_asset_disposals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_assets`
--

DROP TABLE IF EXISTS `fixed_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `fixed_asset_category_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `ownership_document_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_cost` decimal(18,2) NOT NULL,
  `residual_value` decimal(18,2) NOT NULL,
  `useful_life_months` smallint unsigned NOT NULL,
  `depreciation_method` enum('garis_lurus','saldo_menurun') COLLATE utf8mb4_unicode_ci NOT NULL,
  `depreciation_rate_percentage` decimal(6,3) DEFAULT NULL,
  `payment_method` enum('tunai','kredit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu_approval','ditolak','aktif','dijual','dihapusbukukan','nonaktif_sementara') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_approval',
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fixed_assets_code_unique` (`code`),
  KEY `fixed_assets_fixed_asset_category_id_foreign` (`fixed_asset_category_id`),
  KEY `fixed_assets_supplier_id_foreign` (`supplier_id`),
  KEY `fixed_assets_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `fixed_assets_created_by_foreign` (`created_by`),
  KEY `fixed_assets_approved_by_foreign` (`approved_by`),
  KEY `fixed_assets_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `fixed_assets_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_assets_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_assets_fixed_asset_category_id_foreign` FOREIGN KEY (`fixed_asset_category_id`) REFERENCES `fixed_asset_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fixed_assets_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fixed_assets_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_assets`
--

LOCK TABLES `fixed_assets` WRITE;
/*!40000 ALTER TABLE `fixed_assets` DISABLE KEYS */;
INSERT INTO `fixed_assets` VALUES (1,'AT-0001','Laptop Kantor Lenovo',NULL,1,1,NULL,NULL,'2026-04-18',8000000.00,500000.00,36,'garis_lurus',NULL,'tunai','aktif',24,7,NULL,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(2,'AT-0002','Printer Multifungsi',NULL,1,1,NULL,NULL,'2026-06-18',3500000.00,200000.00,24,'garis_lurus',NULL,'tunai','aktif',25,7,NULL,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(3,'AT-0003','Sepeda Motor Operasional',NULL,2,1,NULL,NULL,'2026-07-08',22000000.00,4000000.00,60,'garis_lurus',NULL,'tunai','aktif',26,7,3,'2026-07-18 13:11:21','2026-07-18 13:11:21');
/*!40000 ALTER TABLE `fixed_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `entry_date` date NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `reversal_of_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entries_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `journal_entries_created_by_foreign` (`created_by`),
  KEY `journal_entries_reversal_of_entry_id_foreign` (`reversal_of_entry_id`),
  KEY `journal_entries_branch_id_entry_date_index` (`branch_id`,`entry_date`),
  CONSTRAINT `journal_entries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `journal_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `journal_entries_reversal_of_entry_id_foreign` FOREIGN KEY (`reversal_of_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
INSERT INTO `journal_entries` VALUES (1,1,'2026-05-19','Modal awal koperasi (data demo)',NULL,NULL,7,NULL,'2026-07-18 13:11:11','2026-07-18 13:11:11'),(2,1,'2026-07-18','Setoran awal pembukaan rekening','App\\Models\\SavingsAccount',1,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(3,1,'2026-07-18','Setoran rutin bulanan','App\\Models\\SavingsAccount',1,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(4,1,'2026-07-18','Tarik tunai kebutuhan mendesak','App\\Models\\SavingsAccount',1,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(5,1,'2026-07-18','Setoran awal pembukaan rekening','App\\Models\\SavingsAccount',2,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(6,1,'2026-07-18','Setoran rutin bulanan','App\\Models\\SavingsAccount',2,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(7,2,'2026-07-18','Setoran awal pembukaan rekening','App\\Models\\SavingsAccount',3,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(8,2,'2026-07-18','Setoran rutin bulanan','App\\Models\\SavingsAccount',3,4,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(9,3,'2026-07-18','Setoran awal pembukaan rekening','App\\Models\\SavingsAccount',4,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(10,3,'2026-07-18','Setoran rutin bulanan','App\\Models\\SavingsAccount',4,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(11,3,'2026-07-18','Tarik tunai kebutuhan mendesak','App\\Models\\SavingsAccount',4,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(12,1,'2026-07-18','Setoran awal pembukaan rekening','App\\Models\\SavingsAccount',5,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(13,1,'2026-07-18','Setoran rutin bulanan','App\\Models\\SavingsAccount',5,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(14,1,'2026-07-18','Setoran awal pembukaan rekening','App\\Models\\SavingsAccount',6,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(15,1,'2026-07-18','Setoran rutin bulanan','App\\Models\\SavingsAccount',6,4,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(16,2,'2026-07-18','Pencairan pinjaman PINJ-DEMO-260718-0828','App\\Models\\Loan',3,5,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(17,2,'2026-07-18','Pembayaran angsuran ke-1','App\\Models\\LoanRepayment',1,4,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(18,3,'2026-07-18','Pencairan pinjaman PINJ-DEMO-260718-8648','App\\Models\\Loan',4,5,NULL,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(19,1,'2026-07-18','Pencairan pinjaman PINJ-DEMO-260718-0813','App\\Models\\Loan',5,5,NULL,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(20,1,'2026-07-18','Pembelian barang dari CV Sumber Rejeki — PB-260718-1315','App\\Models\\PurchaseTransaction',1,5,NULL,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(21,1,'2026-07-18','Pembelian barang dari PT Distribusi Sembako Jaya — PB-260718-0626','App\\Models\\PurchaseTransaction',2,5,NULL,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(22,1,'2026-07-18','Penjualan POS JL-260718-6109','App\\Models\\PosSale',1,4,NULL,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(23,1,'2026-07-18','Penjualan POS JL-260718-4893','App\\Models\\PosSale',2,4,NULL,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(24,1,'2026-04-18','Pembelian aktiva tetap Laptop Kantor Lenovo (AT-0001)','App\\Models\\FixedAsset',1,7,NULL,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(25,1,'2026-06-18','Pembelian aktiva tetap Printer Multifungsi (AT-0002)','App\\Models\\FixedAsset',2,7,NULL,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(26,1,'2026-07-08','Pembelian aktiva tetap Sepeda Motor Operasional (AT-0003)','App\\Models\\FixedAsset',3,7,NULL,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(27,1,'2026-07-18','Retribusi UPF — Toko Sumber Makmur (Kios) (5 jenis retribusi)',NULL,NULL,6,NULL,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(28,1,'2026-07-18','Retribusi UPF — Warung Blok C-12 (5 jenis retribusi)',NULL,NULL,6,NULL,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(29,1,'2026-07-18','Retribusi UPF — Pedagang Kaki Lima Depan Pasar (5 jenis retribusi)',NULL,NULL,6,NULL,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(30,1,'2026-07-18','Sewa aula untuk acara RT','App\\Models\\BusinessUnit',1,7,NULL,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(31,1,'2026-07-18','Biaya kebersihan pasca acara','App\\Models\\BusinessUnit',1,7,NULL,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(32,1,'2026-07-18','Penjualan fotokopi & ATK harian','App\\Models\\BusinessUnit',2,7,NULL,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(34,3,'2026-07-19','.','App\\Models\\LoanRepayment',2,1,NULL,'2026-07-19 00:25:10','2026-07-19 00:25:10');
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entry_idempotency_keys`
--

DROP TABLE IF EXISTS `journal_entry_idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entry_idempotency_keys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journal_entry_idempotency_keys_idempotency_key_unique` (`idempotency_key`),
  KEY `journal_entry_idempotency_keys_journal_entry_id_foreign` (`journal_entry_id`),
  CONSTRAINT `journal_entry_idempotency_keys_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entry_idempotency_keys`
--

LOCK TABLES `journal_entry_idempotency_keys` WRITE;
/*!40000 ALTER TABLE `journal_entry_idempotency_keys` DISABLE KEYS */;
INSERT INTO `journal_entry_idempotency_keys` VALUES (1,'5019567b-2acb-437d-a72e-929ec3b93e63',34,'2026-07-19 00:25:10','2026-07-19 00:25:10');
/*!40000 ALTER TABLE `journal_entry_idempotency_keys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_lines`
--

DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` bigint unsigned NOT NULL,
  `chart_of_account_id` bigint unsigned NOT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_lines_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `journal_lines_chart_of_account_id_index` (`chart_of_account_id`),
  CONSTRAINT `journal_lines_chart_of_account_id_foreign` FOREIGN KEY (`chart_of_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `journal_lines_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_lines`
--

LOCK TABLES `journal_lines` WRITE;
/*!40000 ALTER TABLE `journal_lines` DISABLE KEYS */;
INSERT INTO `journal_lines` VALUES (1,1,3,65000000.00,0.00,'2026-07-18 13:11:11','2026-07-18 13:11:11'),(2,1,42,0.00,1000000.00,'2026-07-18 13:11:11','2026-07-18 13:11:11'),(3,1,43,0.00,6000000.00,'2026-07-18 13:11:11','2026-07-18 13:11:11'),(4,1,44,0.00,58000000.00,'2026-07-18 13:11:11','2026-07-18 13:11:11'),(5,2,3,500000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(6,2,27,0.00,500000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(7,3,3,100000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(8,3,27,0.00,100000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(9,4,27,100000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(10,4,3,0.00,100000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(11,5,3,750000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(12,5,27,0.00,750000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(13,6,3,100000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(14,6,27,0.00,100000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(15,7,3,1000000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(16,7,27,0.00,1000000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(17,8,3,300000.00,0.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(18,8,27,0.00,300000.00,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(19,9,3,300000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(20,9,27,0.00,300000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(21,10,3,300000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(22,10,27,0.00,300000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(23,11,27,100000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(24,11,3,0.00,100000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(25,12,3,2000000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(26,12,27,0.00,2000000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(27,13,3,100000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(28,13,27,0.00,100000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(29,14,3,1500000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(30,14,27,0.00,1500000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(31,15,3,300000.00,0.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(32,15,27,0.00,300000.00,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(33,16,6,5000000.00,0.00,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(34,16,3,0.00,4950000.00,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(35,16,53,0.00,50000.00,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(36,17,3,883333.33,0.00,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(37,17,6,0.00,833333.33,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(38,17,52,0.00,50000.00,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(39,18,6,8000000.00,0.00,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(40,18,3,0.00,7920000.00,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(41,18,53,0.00,80000.00,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(42,19,6,15000000.00,0.00,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(43,19,3,0.00,14850000.00,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(44,19,53,0.00,150000.00,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(45,20,12,3100000.00,0.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(46,20,12,1240000.00,0.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(47,20,12,2850000.00,0.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(48,20,29,0.00,7190000.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(49,21,12,540000.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(50,21,12,480000.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(51,21,12,192000.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(52,21,12,262500.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(53,21,3,0.00,1474500.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(54,22,56,0.00,80000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(55,22,73,67500.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(56,22,12,0.00,67500.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(57,22,56,0.00,13500.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(58,22,73,9600.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(59,22,12,0.00,9600.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(60,22,3,93500.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(61,23,56,0.00,56000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(62,23,73,48000.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(63,23,12,0.00,48000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(64,23,56,0.00,52000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(65,23,73,42000.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(66,23,12,0.00,42000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(67,23,3,108000.00,0.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(68,24,19,8000000.00,0.00,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(69,24,3,0.00,8000000.00,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(70,25,19,3500000.00,0.00,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(71,25,3,0.00,3500000.00,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(72,26,21,22000000.00,0.00,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(73,26,3,0.00,22000000.00,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(74,27,61,0.00,45000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(75,27,62,0.00,37500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(76,27,63,0.00,30000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(77,27,64,0.00,22500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(78,27,65,0.00,15000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(79,27,3,150000.00,0.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(80,28,61,0.00,30000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(81,28,62,0.00,25000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(82,28,63,0.00,20000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(83,28,64,0.00,15000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(84,28,65,0.00,10000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(85,28,3,100000.00,0.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(86,29,61,0.00,15000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(87,29,62,0.00,12500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(88,29,63,0.00,10000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(89,29,64,0.00,7500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(90,29,65,0.00,5000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(91,29,3,50000.00,0.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(92,30,3,750000.00,0.00,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(93,30,60,0.00,750000.00,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(94,31,84,100000.00,0.00,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(95,31,3,0.00,100000.00,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(96,32,3,250000.00,0.00,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(97,32,68,0.00,250000.00,'2026-07-18 13:11:23','2026-07-18 13:11:23'),(100,34,3,90000.00,0.00,'2026-07-19 00:25:10','2026-07-19 00:25:10'),(101,34,6,0.00,10000.00,'2026-07-19 00:25:10','2026-07-19 00:25:10'),(102,34,52,0.00,80000.00,'2026-07-19 00:25:10','2026-07-19 00:25:10');
/*!40000 ALTER TABLE `journal_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_approvals`
--

DROP TABLE IF EXISTS `loan_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned NOT NULL,
  `decision` enum('setuju','tolak') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_approvals_loan_id_approved_by_unique` (`loan_id`,`approved_by`),
  KEY `loan_approvals_approved_by_foreign` (`approved_by`),
  CONSTRAINT `loan_approvals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_approvals_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_approvals`
--

LOCK TABLES `loan_approvals` WRITE;
/*!40000 ALTER TABLE `loan_approvals` DISABLE KEYS */;
INSERT INTO `loan_approvals` VALUES (1,2,3,'tolak','Riwayat angsuran sebelumnya kurang lancar.','2026-07-18 13:11:15','2026-07-18 13:11:15','2026-07-18 13:11:15'),(2,3,3,'setuju',NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15','2026-07-18 13:11:15'),(3,4,7,'setuju',NULL,'2026-07-18 13:11:16','2026-07-18 13:11:16','2026-07-18 13:11:16'),(4,5,3,'setuju',NULL,'2026-07-18 13:11:16','2026-07-18 13:11:16','2026-07-18 13:11:16'),(5,5,7,'setuju',NULL,'2026-07-18 13:11:16','2026-07-18 13:11:16','2026-07-18 13:11:16');
/*!40000 ALTER TABLE `loan_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_product_rate_history`
--

DROP TABLE IF EXISTS `loan_product_rate_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_product_rate_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_product_id` bigint unsigned NOT NULL,
  `rate_percentage` decimal(6,3) NOT NULL,
  `effective_from` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lprh_product_effective_idx` (`loan_product_id`,`effective_from`),
  CONSTRAINT `loan_product_rate_history_loan_product_id_foreign` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_product_rate_history`
--

LOCK TABLES `loan_product_rate_history` WRITE;
/*!40000 ALTER TABLE `loan_product_rate_history` DISABLE KEYS */;
INSERT INTO `loan_product_rate_history` VALUES (1,1,12.000,'2025-07-18','2026-07-18 13:11:15','2026-07-18 13:11:15');
/*!40000 ALTER TABLE `loan_product_rate_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_products`
--

DROP TABLE IF EXISTS `loan_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_plafon` decimal(18,2) NOT NULL,
  `max_plafon` decimal(18,2) NOT NULL,
  `min_tenor_days` smallint unsigned NOT NULL,
  `max_tenor_days` smallint unsigned NOT NULL,
  `calculation_method` enum('flat','efektif','anuitas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `provision_fee_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `penalty_percentage_per_day` decimal(5,3) NOT NULL DEFAULT '0.000',
  `approval_threshold` decimal(18,2) DEFAULT NULL,
  `coa_receivable_account_id` bigint unsigned DEFAULT NULL,
  `coa_interest_income_account_id` bigint unsigned DEFAULT NULL,
  `coa_provision_income_account_id` bigint unsigned DEFAULT NULL,
  `coa_penalty_receivable_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_products_code_unique` (`code`),
  KEY `loan_products_coa_receivable_account_id_foreign` (`coa_receivable_account_id`),
  KEY `loan_products_coa_interest_income_account_id_foreign` (`coa_interest_income_account_id`),
  KEY `loan_products_coa_provision_income_account_id_foreign` (`coa_provision_income_account_id`),
  KEY `loan_products_coa_penalty_receivable_account_id_foreign` (`coa_penalty_receivable_account_id`),
  CONSTRAINT `loan_products_coa_interest_income_account_id_foreign` FOREIGN KEY (`coa_interest_income_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_products_coa_penalty_receivable_account_id_foreign` FOREIGN KEY (`coa_penalty_receivable_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_products_coa_provision_income_account_id_foreign` FOREIGN KEY (`coa_provision_income_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_products_coa_receivable_account_id_foreign` FOREIGN KEY (`coa_receivable_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_products`
--

LOCK TABLES `loan_products` WRITE;
/*!40000 ALTER TABLE `loan_products` DISABLE KEYS */;
INSERT INTO `loan_products` VALUES (1,'PINJ-DEMO','Pinjaman Modal Usaha Demo',500000.00,50000000.00,3,24,'flat',1.00,0.100,10000000.00,6,52,53,11,1,'2026-07-18 13:11:14','2026-07-18 13:11:14');
/*!40000 ALTER TABLE `loan_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_repayment_requests`
--

DROP TABLE IF EXISTS `loan_repayment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_repayment_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `loan_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `status` enum('menunggu_pembayaran','dibayar','kedaluwarsa','gagal','perlu_rekonsiliasi_manual','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_pembayaran',
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `xendit_invoice_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xendit_invoice_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `loan_repayment_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_repayment_requests_external_id_unique` (`external_id`),
  KEY `loan_repayment_requests_loan_id_foreign` (`loan_id`),
  KEY `loan_repayment_requests_member_id_foreign` (`member_id`),
  KEY `loan_repayment_requests_requested_by_foreign` (`requested_by`),
  KEY `loan_repayment_requests_loan_repayment_id_foreign` (`loan_repayment_id`),
  KEY `loan_repayment_requests_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `loan_repayment_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayment_requests_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayment_requests_loan_repayment_id_foreign` FOREIGN KEY (`loan_repayment_id`) REFERENCES `loan_repayments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayment_requests_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayment_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_repayment_requests`
--

LOCK TABLES `loan_repayment_requests` WRITE;
/*!40000 ALTER TABLE `loan_repayment_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `loan_repayment_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_repayments`
--

DROP TABLE IF EXISTS `loan_repayments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_repayments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `loan_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `principal_portion` decimal(18,2) NOT NULL,
  `interest_portion` decimal(18,2) NOT NULL,
  `balance_after` decimal(18,2) NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_repayments_loan_id_foreign` (`loan_id`),
  KEY `loan_repayments_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `loan_repayments_created_by_foreign` (`created_by`),
  KEY `loan_repayments_cancelled_by_foreign` (`cancelled_by`),
  KEY `loan_repayments_reversal_journal_entry_id_foreign` (`reversal_journal_entry_id`),
  KEY `loan_repayments_branch_id_loan_id_index` (`branch_id`,`loan_id`),
  CONSTRAINT `loan_repayments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayments_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayments_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loan_repayments_reversal_journal_entry_id_foreign` FOREIGN KEY (`reversal_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_repayments`
--

LOCK TABLES `loan_repayments` WRITE;
/*!40000 ALTER TABLE `loan_repayments` DISABLE KEYS */;
INSERT INTO `loan_repayments` VALUES (1,2,3,883333.33,833333.33,50000.00,4416666.67,17,4,'Pembayaran angsuran ke-1',NULL,NULL,NULL,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(2,3,4,90000.00,10000.00,80000.00,8870000.00,34,1,'.',NULL,NULL,NULL,NULL,'2026-07-19 00:25:09','2026-07-19 00:25:10');
/*!40000 ALTER TABLE `loan_repayments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_schedules`
--

DROP TABLE IF EXISTS `loan_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `installment_number` smallint unsigned NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(18,2) NOT NULL,
  `interest_amount` decimal(18,2) NOT NULL,
  `total_amount` decimal(18,2) NOT NULL,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `paid_principal_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `paid_interest_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('belum_bayar','sebagian','lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_bayar',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_schedules_loan_id_installment_number_unique` (`loan_id`,`installment_number`),
  KEY `loan_schedules_due_date_status_index` (`due_date`,`status`),
  CONSTRAINT `loan_schedules_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_schedules`
--

LOCK TABLES `loan_schedules` WRITE;
/*!40000 ALTER TABLE `loan_schedules` DISABLE KEYS */;
INSERT INTO `loan_schedules` VALUES (1,3,1,'2026-08-18',833333.33,50000.00,883333.33,883333.33,833333.33,50000.00,'lunas','2026-07-18 13:11:15','2026-07-18 13:11:15'),(2,3,2,'2026-09-18',833333.33,50000.00,883333.33,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:15','2026-07-18 13:11:15'),(3,3,3,'2026-10-18',833333.33,50000.00,883333.33,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:15','2026-07-18 13:11:15'),(4,3,4,'2026-11-18',833333.33,50000.00,883333.33,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:15','2026-07-18 13:11:15'),(5,3,5,'2026-12-18',833333.33,50000.00,883333.33,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:15','2026-07-18 13:11:15'),(6,3,6,'2027-01-18',833333.35,50000.00,883333.35,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:15','2026-07-18 13:11:15'),(7,4,1,'2026-08-18',666666.67,80000.00,746666.67,90000.00,10000.00,80000.00,'sebagian','2026-07-18 13:11:16','2026-07-19 00:25:09'),(8,4,2,'2026-09-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(9,4,3,'2026-10-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(10,4,4,'2026-11-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(11,4,5,'2026-12-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(12,4,6,'2027-01-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(13,4,7,'2027-02-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(14,4,8,'2027-03-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(15,4,9,'2027-04-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(16,4,10,'2027-05-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(17,4,11,'2027-06-18',666666.67,80000.00,746666.67,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(18,4,12,'2027-07-18',666666.63,80000.00,746666.63,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(19,5,1,'2026-08-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(20,5,2,'2026-09-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(21,5,3,'2026-10-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(22,5,4,'2026-11-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(23,5,5,'2026-12-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(24,5,6,'2027-01-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(25,5,7,'2027-02-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(26,5,8,'2027-03-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(27,5,9,'2027-04-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(28,5,10,'2027-05-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(29,5,11,'2027-06-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(30,5,12,'2027-07-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(31,5,13,'2027-08-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(32,5,14,'2027-09-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(33,5,15,'2027-10-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(34,5,16,'2027-11-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(35,5,17,'2027-12-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(36,5,18,'2028-01-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(37,5,19,'2028-02-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(38,5,20,'2028-03-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(39,5,21,'2028-04-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(40,5,22,'2028-05-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(41,5,23,'2028-06-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16'),(42,5,24,'2028-07-18',625000.00,150000.00,775000.00,0.00,0.00,0.00,'belum_bayar','2026-07-18 13:11:16','2026-07-18 13:11:16');
/*!40000 ALTER TABLE `loan_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `loan_product_id` bigint unsigned NOT NULL,
  `loan_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `principal_amount` decimal(18,2) NOT NULL,
  `tenor_days` smallint unsigned NOT NULL,
  `interest_rate_percentage` decimal(6,3) NOT NULL,
  `provision_fee_amount` decimal(18,2) DEFAULT NULL,
  `required_approval_count` tinyint unsigned NOT NULL DEFAULT '1',
  `status` enum('diajukan','disetujui','ditolak','dicairkan','lunas','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diajukan',
  `collectibility` enum('lancar','kurang_lancar','diragukan','macet') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `submitted_at` date NOT NULL,
  `disbursed_at` date DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loans_loan_number_unique` (`loan_number`),
  KEY `loans_member_id_foreign` (`member_id`),
  KEY `loans_loan_product_id_foreign` (`loan_product_id`),
  KEY `loans_created_by_foreign` (`created_by`),
  KEY `loans_branch_id_status_index` (`branch_id`,`status`),
  KEY `loans_cancelled_by_foreign` (`cancelled_by`),
  KEY `loans_reversal_journal_entry_id_foreign` (`reversal_journal_entry_id`),
  CONSTRAINT `loans_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loans_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loans_loan_product_id_foreign` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loans_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `loans_reversal_journal_entry_id_foreign` FOREIGN KEY (`reversal_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
INSERT INTO `loans` VALUES (1,1,1,1,'PINJ-DEMO-260718-7680',3000000.00,6,12.000,NULL,1,'diajukan',NULL,5,'2026-07-18',NULL,NULL,NULL,NULL,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(2,1,2,1,'PINJ-DEMO-260718-4187',2000000.00,3,12.000,NULL,1,'ditolak',NULL,5,'2026-07-18',NULL,NULL,NULL,NULL,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(3,2,3,1,'PINJ-DEMO-260718-0828',5000000.00,6,12.000,50000.00,1,'dicairkan','lancar',5,'2026-07-18','2026-07-18',NULL,NULL,NULL,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:15'),(4,3,4,1,'PINJ-DEMO-260718-8648',8000000.00,12,12.000,80000.00,1,'dicairkan','lancar',5,'2026-07-18','2026-07-18',NULL,NULL,NULL,NULL,'2026-07-18 13:11:15','2026-07-18 13:11:16'),(5,1,5,1,'PINJ-DEMO-260718-0813',15000000.00,24,12.000,150000.00,2,'dicairkan','lancar',5,'2026-07-18','2026-07-18',NULL,NULL,NULL,NULL,'2026-07-18 13:11:16','2026-07-18 13:11:16');
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_card_fields`
--

DROP TABLE IF EXISTS `member_card_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_card_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_card_template_id` bigint unsigned NOT NULL,
  `field_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom_text_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_x_mm` decimal(6,2) NOT NULL,
  `position_y_mm` decimal(6,2) NOT NULL,
  `width_mm` decimal(6,2) DEFAULT NULL,
  `height_mm` decimal(6,2) DEFAULT NULL,
  `font_size_pt` smallint unsigned DEFAULT NULL,
  `font_weight` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_align` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#16201C',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_card_fields_member_card_template_id_index` (`member_card_template_id`),
  CONSTRAINT `member_card_fields_member_card_template_id_foreign` FOREIGN KEY (`member_card_template_id`) REFERENCES `member_card_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_card_fields`
--

LOCK TABLES `member_card_fields` WRITE;
/*!40000 ALTER TABLE `member_card_fields` DISABLE KEYS */;
INSERT INTO `member_card_fields` VALUES (1,1,'custom_text','KARTU ANGGOTA KOPERASI',4.00,2.00,78.00,NULL,7,'bold','center','#11543B',1,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(2,1,'photo',NULL,4.00,8.00,20.00,25.00,NULL,NULL,NULL,'#16201C',2,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(3,1,'name',NULL,28.00,10.00,54.00,NULL,11,'bold','left','#16201C',3,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(4,1,'member_number',NULL,28.00,18.00,54.00,NULL,9,NULL,'left','#5C6E64',4,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(5,1,'member_type_name',NULL,28.00,24.00,54.00,NULL,8,NULL,NULL,'#5C6E64',5,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(6,1,'branch_name',NULL,4.00,46.00,78.00,NULL,7,NULL,'center','#5C6E64',6,'2026-07-18 13:09:48','2026-07-18 13:09:48');
/*!40000 ALTER TABLE `member_card_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_card_templates`
--

DROP TABLE IF EXISTS `member_card_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_card_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width_mm` decimal(6,2) NOT NULL DEFAULT '85.60',
  `height_mm` decimal(6,2) NOT NULL DEFAULT '53.98',
  `background_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FFFFFF',
  `background_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_card_templates`
--

LOCK TABLES `member_card_templates` WRITE;
/*!40000 ALTER TABLE `member_card_templates` DISABLE KEYS */;
INSERT INTO `member_card_templates` VALUES (1,'Kartu Anggota Standar (CR80)',85.60,53.98,'#FFFFFF',NULL,1,'2026-07-18 13:09:47','2026-07-18 13:09:47');
/*!40000 ALTER TABLE `member_card_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_contact_consents`
--

DROP TABLE IF EXISTS `member_contact_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_contact_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint unsigned NOT NULL,
  `channel` enum('whatsapp','email') COLLATE utf8mb4_unicode_ci NOT NULL,
  `consented` tinyint(1) NOT NULL DEFAULT '0',
  `consented_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_contact_consents_member_id_channel_unique` (`member_id`,`channel`),
  CONSTRAINT `member_contact_consents_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_contact_consents`
--

LOCK TABLES `member_contact_consents` WRITE;
/*!40000 ALTER TABLE `member_contact_consents` DISABLE KEYS */;
/*!40000 ALTER TABLE `member_contact_consents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_types`
--

DROP TABLE IF EXISTS `member_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `has_voting_rights` tinyint(1) NOT NULL DEFAULT '1',
  `requires_mandatory_savings` tinyint(1) NOT NULL DEFAULT '1',
  `mandatory_savings_default_amount` decimal(18,2) DEFAULT NULL,
  `counts_toward_shu` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_types_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_types`
--

LOCK TABLES `member_types` WRITE;
/*!40000 ALTER TABLE `member_types` DISABLE KEYS */;
INSERT INTO `member_types` VALUES (1,'ANGGOTA','Anggota Biasa',1,1,NULL,1,1,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(2,'CALON','Calon Anggota',1,1,NULL,1,1,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(3,'KIOS','Anggota Kios',1,1,NULL,1,1,'2026-07-18 13:09:47','2026-07-18 13:09:47'),(4,'BLOK','Anggota Blok',1,1,NULL,1,1,'2026-07-18 13:09:47','2026-07-18 13:09:47');
/*!40000 ALTER TABLE `member_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `member_type_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `member_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nik` text COLLATE utf8mb4_unicode_ci,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` text COLLATE utf8mb4_unicode_ci,
  `email` text COLLATE utf8mb4_unicode_ci,
  `date_of_birth` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('calon','aktif','nonaktif','keluar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'calon',
  `joined_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `members_member_number_unique` (`member_number`),
  UNIQUE KEY `members_user_id_unique` (`user_id`),
  KEY `members_member_type_id_foreign` (`member_type_id`),
  KEY `members_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `members_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `members_member_type_id_foreign` FOREIGN KEY (`member_type_id`) REFERENCES `member_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,1,1,NULL,'AGT-0101','Hendra Wijaya','eyJpdiI6ImxMMkI0MG41L0dNcjYxUVVQNDY4aHc9PSIsInZhbHVlIjoiMTdzVHd3WU9MRlY5L3N4dE9raXphNHFkaUxsU1F6djQ3QjY3M1NORnZROD0iLCJtYWMiOiI0YmY2ZWRmYzY1ODFkNWQ0NzQwODJhYWI4NmQ0YzMyOGNjNmFjZDQwYjhiZDljM2Y0ZTQ5YjMyN2Q2ZDVlYjUyIiwidGFnIjoiIn0=','eyJpdiI6Ik5EeE1PR2o5dWo3VXdtRGgyRHNNOVE9PSIsInZhbHVlIjoiTzVKYmhMU29BVGliTkZ2SmwrckJDVlJPWE5BSzUwU2tMQ3J6M2wwNWR3N0NCSU1BY0VpaVVnQXdzNU1DRElja2pKWGNDLzRwa2ZXbk5RMEJ4RDk0L2c9PSIsIm1hYyI6IjAxNjMwZDJkZmU5ZmNiMTVlYWUzYjNkNzM1YWQ5MmQwMjYyNzFkNzFhZDQ3NTFmMjlkOWVkZDQ4OTc3N2I1YWYiLCJ0YWciOiIifQ==','eyJpdiI6Ilc5R1F3b3VacXVHTE1INDVxVHAxTnc9PSIsInZhbHVlIjoiVzJuTnZJSWxNRVJDZmlFL2lucHIwdz09IiwibWFjIjoiZGY4ODliNmYxOWQ0MDBjM2QyNTJiMjhhOTIyNGIzYTVkYjBiMzI4ZTJkOGQ4ZThlY2Q5NjM1YzFlZTEwOWIwOCIsInRhZyI6IiJ9','eyJpdiI6IldBbDhIeFEvc0JxbTg3cmFCcXl3TXc9PSIsInZhbHVlIjoiNklvTXl2OWpHM0tJZ0d4ZFlVbWI3SkRJdStrZ3RRSFR4ZmUwb2V1MG1pYz0iLCJtYWMiOiI0MGIzMDk0YTE3MzJkY2RlZmQ5OWEzOGQ0ZTJjYWNmMWVjOTQ5M2E1NzJiMDA0NDQxMzBlNDA1NWNhMGQxYWQzIiwidGFnIjoiIn0=','eyJpdiI6ImRWdjVsK0MrMUlGVW1TQThVYUJPdlE9PSIsInZhbHVlIjoieXcwejVKYzd2OWtBdWE2QTMvSHVsdz09IiwibWFjIjoiY2VkMzJjYzFiNjMzMzlkYTNlZTI4MmQ3MGQyYzI0Y2MxMzUyYTJlMWI1ZTc1MGI2MzQ1YjNjYjIzZjJiZTI3MiIsInRhZyI6IiJ9',NULL,'aktif','2026-03-18','2026-07-18 13:11:11','2026-07-18 13:11:11'),(2,1,1,NULL,'AGT-0102','Rini Marlina','eyJpdiI6IlJuUWxORzZJTEJiaDZ6ZnZpd2pBalE9PSIsInZhbHVlIjoiYjlQU1M2L1Q2c0VkdmdZV1BFbVFFVjVMbnUyVnpPZDBXQzkyczU3R0k3TT0iLCJtYWMiOiI5MzlmYTQ1ZWNiMGNjYzExN2Y4NWE2ZjQ2MzI3NTdkMDk1NjIzZDllYTlhZmMzZWZmMjQ5MWFiNGI4ZWZkOGJkIiwidGFnIjoiIn0=','eyJpdiI6IkkxWk1NMWg3NkRJejFXREZiZVFuRnc9PSIsInZhbHVlIjoiMW8wUXpUejdidysrVWxsbWRvTUlYY2tSb0RrTWF4cnMrdGcyUG1VS0JlemY5eE9jV0pLSWlWRkxqWkpnQXI3TSIsIm1hYyI6ImZhYWY4OGQ2NzBlZTNlZmIzMzQ0N2I4MmM0NjkyMTNmZTVhZjljZDhhYmY3OTRlZmI2ZDU4MGE3MDczNDA1YWMiLCJ0YWciOiIifQ==','eyJpdiI6ImUyYXM0OHgrQ24rVHNRTjBPVlZVaVE9PSIsInZhbHVlIjoiMVAvT2lIempWdGkwY0Nxb1J0SzI1QT09IiwibWFjIjoiNTk4MDE0YjVjMTgwYTI4MTRhYjkxMWY1NjYwNjY2MDc3ZjAxMWRiOWE1MWIwM2QzNDRiMDE3NmJjZjhjZGM4NyIsInRhZyI6IiJ9','eyJpdiI6Ii9QQURlTkk5SElZdUViMzJ2L2VEUkE9PSIsInZhbHVlIjoiOXhuVm5KK0xRaVlBRFBuT1poU3B5d3QxOCtDejh2Y3ZlU1BndnVGeU5OVT0iLCJtYWMiOiI5NTYxMTU1N2ZjNWQ3MzkwNmE0NmJhZjI3NDhjZTU4NzlmNjYxZjA1OWEzMzBkYWFhMmNkNDJlMDIyOGYxMjg0IiwidGFnIjoiIn0=','eyJpdiI6InFoUzZsQmZJd3pVTkxraW5uL0ZITkE9PSIsInZhbHVlIjoiQnpGZ2lva3dWakREYkNCWm9ycVJEZz09IiwibWFjIjoiZDVmNDEzODJlMmJiOGJlZTIyYjIxOTVhMjdlM2U5ZDRlNzM1YjBjYzUxMDQzODBhM2I1MDM1MjlhZGQxNjgxYiIsInRhZyI6IiJ9',NULL,'aktif','2025-12-18','2026-07-18 13:11:11','2026-07-18 13:11:11'),(3,2,1,NULL,'AGT-0103','Agus Setiawan','eyJpdiI6IlgvY2RXMGphUktKK01Nbm14QTFXblE9PSIsInZhbHVlIjoiNWVrZGRCdDZRQUdQOVlaWTQvQklqTmx0ODNLYVhGbmE3ZEhyRGVYY0Zodz0iLCJtYWMiOiI2Nzc2OGEzYmI2MTljNWI5YjQwMTU0YTM4ZTU0NjllYzM2ZWJiZjIwMjcxYTg1ZjY2NDMxM2MzNGExMjM1N2NjIiwidGFnIjoiIn0=','eyJpdiI6IjkySEtoRFpVS2pFcEF3RW5KTEtkcEE9PSIsInZhbHVlIjoiODYrOE5PcjlzbG1PSExPYTY2NkNtbGkzUGZHNFN3US9RNFBRcCtpalNYQ0JVN3ZWKzlEWERaN1VUL3JxS1FVZiIsIm1hYyI6Ijc2MzA5ZmIyOWU2MTYzM2MxYTU3ODNkYjM1OGQzN2U2NzEwZjAyODE1NDViNWY5YmJkYmNhNTM2MzE2ZTY5ZDciLCJ0YWciOiIifQ==','eyJpdiI6IkpKZnMwOGovZlI4NnJVRVd0R3AxWFE9PSIsInZhbHVlIjoiZkkzcjRNaFc4ckx1ZEtmcVRPZTNuUT09IiwibWFjIjoiOWU5M2I3NmI4MTNlNzAyYTFkZDNhNGM3MjU1NzNlMjg1ZjQ4MTlmZjRjZmRlMWI5MTMxNGE1NzkyNGRkNGUxNCIsInRhZyI6IiJ9','eyJpdiI6IjRDUXVROTVCVDZMUHF2dDlmL3NDQ1E9PSIsInZhbHVlIjoiblFva1BYQ0JsZU90cStxSEZYTkFYTUZxSlBlQjZNc1g4eS9RZnpvbENyST0iLCJtYWMiOiJhNDZjNGVhZTY5OWU1MDA5MDc5ZTk5NjRhMjNjZjUxYTY1NjhhMTg0NTQxZTUwYWRlMmNlMjdiZDYyNDg0Y2Q4IiwidGFnIjoiIn0=','eyJpdiI6IlJvMjhwNGJjWW1UL1R2ZVhUamFXT2c9PSIsInZhbHVlIjoiMjhoUHVacFE2Uk1GaXR2R09YS3BRZz09IiwibWFjIjoiZTNhNWJjYzA2MjI5OWNjNTVmYmIzZjIxN2Y5ZDAxYWY0MTBlNTFhMmE4NTg0NTQyN2QyNjU1YjBjYzYzMjY1OSIsInRhZyI6IiJ9',NULL,'aktif','2026-03-18','2026-07-18 13:11:12','2026-07-18 13:11:12'),(4,3,1,NULL,'AGT-0104','Yuliana Putri','eyJpdiI6Ii9DVnJkN0p1SG5udkUzbi8vYXlYWEE9PSIsInZhbHVlIjoiQlcwcTN2VFNyWWFpZUFqRSszUHNHa2h1SGIvL3BqV1Z1bkpSQ3FQZEhFTT0iLCJtYWMiOiI5MmIzZmQ3M2ZjZWVlZTdiZjY0NjAzOTBkYjEwOGZkMzY4MTQ5YWJjNzVjMzFkOWNiMDFiYTNhYmQ3NzMwZGI4IiwidGFnIjoiIn0=','eyJpdiI6ImFrMjlmLzJweThHUHNXbzBxK1BPV0E9PSIsInZhbHVlIjoiOHFjMHh1Q1AxYnRFWm1WNHA5cUtVcW5QMWJBTGhGRW5kYlZyNWZwZWVQZE9uejhaRUc0dzRSTmNLcWpmRVZzMiIsIm1hYyI6IjhmMDE5MzA0NGE1MWZiZTIzYTQ3YTBkMmUwNmFmYTMwZWU1NjdkNmE3ODFjNGU0MzAzYzYwZTQzMDhjMjE4NGQiLCJ0YWciOiIifQ==','eyJpdiI6IlZ6Umw1b2VQeWxESjErR0NLaEdQSnc9PSIsInZhbHVlIjoiZDFFc09WdWxOQXBEZW5DMzgxL1o0Zz09IiwibWFjIjoiNzRhNjg5NzRjOTYzMGNkYzkwYmFiY2M4Mjk1NGI3NmY0MDY5YTVjYjY1ZjMxZmQ4ODRmNTMyNDYxYjBlYzJkZiIsInRhZyI6IiJ9','eyJpdiI6InZzaUxweVdjRG05M3dES1F3UXpyNkE9PSIsInZhbHVlIjoiVjdkYXRDZUxOTk9zQWxmcXVPbmdNdkR6eXNZNXpXZUxTUGxhWnBoZTk1ST0iLCJtYWMiOiJjYWJiZGY4MTRhYWNiOTI1NzE5Y2I1MDkyMWMxZDA5YzcyYWRiNDgwZDA4NzQ3NzA5M2EzMTIzMmNmYzEzMDYwIiwidGFnIjoiIn0=','eyJpdiI6ImQ0S01FQVlaeWhkQWY4aS9BdldoV2c9PSIsInZhbHVlIjoiUzZoNmlmLzB6Y3lhRUJsTkpnTXZ5UT09IiwibWFjIjoiMGI4YWM0MGQ0YzFhMTg0NmFkNWEwMmRmMTg3NjZkMTVhODQ1YzM3MDI3NmM4ZjMzODQ3NGMxOTIyOTY4MzNhZCIsInRhZyI6IiJ9',NULL,'aktif','2025-12-18','2026-07-18 13:11:12','2026-07-18 13:11:12'),(5,1,3,NULL,'AGT-0105','Toko Sumber Makmur (Kios)','eyJpdiI6Ii9wUlFmNWRXSlhGdHdyWmJlVUlleEE9PSIsInZhbHVlIjoicGp2bmNFQS9nZWxkdnJZeStEL3pJQWYxdlNUSjVKeUxPNDRaQ0hUdjR1TT0iLCJtYWMiOiI2YjdjNGM4NjljODYzNjI0ZDQyOTAwZmQ2MzE5MDY0NzUxYzhlMjUyZGI5OGYwYmMwYmYxZjFjNjlkMmUwNzBmIiwidGFnIjoiIn0=','eyJpdiI6IlZhaWRmczhqSkR1OXdmZXNGR25TcVE9PSIsInZhbHVlIjoiR1VLY3BxZ0U1OWMydk5LUTJLN2E0enlmNEhKZUdBVzZORGtlalRmeXdEbTZtbDdzdlFEdUJjU1ZlRWQwZ0NsbSIsIm1hYyI6IjFlY2Q5MWUyODY3OTE1ODFkYzcyYzFmZmFiNmVhYTk1OGFiYTI0MGMzMTJiMDJlODczN2FjYzc0ZmJhYzI1NzgiLCJ0YWciOiIifQ==','eyJpdiI6IlBxbEJ1UnZMOVMzRVBYM0RJelo1ZUE9PSIsInZhbHVlIjoiZTNHZnpoWmRBS09pOGRiK3lYcmxsUT09IiwibWFjIjoiOTMyNjE1ZjNmYjQxMGYyYmY2OGJmMzJlNTY2ODQ1ZGZhYjc2MWU5N2UxZWFjODdlYmViNTk3Mjg1NDY4ODNjZiIsInRhZyI6IiJ9','eyJpdiI6IjNkbDl2Zml5MGhmclVackloQ2J0VUE9PSIsInZhbHVlIjoiMXdQT25lQUZYTlozalRSbEFYQ2JCcjdCa0I3VSs0SXpoOU1kcktrVHRRUT0iLCJtYWMiOiJkNTQ2YWMzNzUxYjhlZTlmNmZjMGRhZTczYTgyODgwYzhhMmRlMWQwZGFlNDBmYjc2ZDljN2IzODQ5NmJmNWY5IiwidGFnIjoiIn0=','eyJpdiI6IlF0eFZqeEQzWUpwbWlFanlxRy85Nmc9PSIsInZhbHVlIjoic3ZIRFREbWdwMzdESHJEUUtpTjI5Zz09IiwibWFjIjoiNjE3ZWNjZDA3NWQ3Y2U0MWNiMzc3NGVjOGJhNzgzZGRhMGQzOWVlNDg1MGY1ZTRmNWQ3MzI0ODc5MGEyNWFmYyIsInRhZyI6IiJ9',NULL,'aktif','2025-05-18','2026-07-18 13:11:12','2026-07-18 13:11:12'),(6,1,4,NULL,'AGT-0106','Warung Blok C-12','eyJpdiI6IkVubFFSUnlBU1g5c0VCS2Z4eFpiY0E9PSIsInZhbHVlIjoiTE9kelVoMFJRWDFFSGxPWGtUbmJKdFczak91RmRnRHF5cVhZSTlFNWFVST0iLCJtYWMiOiI0MDMxZTE0MzNjMmUwNGMwM2M1YmUwYTM0YzdkMTE4ZWM4YzIwYmM3MWZhNjQwZDU3MjYwY2IxMDJjODdhZDM0IiwidGFnIjoiIn0=','eyJpdiI6Ikp2am1Vbk4wU21aN0p1QTRuOUF4Q3c9PSIsInZhbHVlIjoiRVArR0ZmMEVZZ29RVWh0NHJCMUpEaVZITWlhL1dZQmphaEpzZXFac01nTWVvWDRxOHVGdU96Q2xxYkM3WTJKWSIsIm1hYyI6ImU4NzkzOTIxODFmMzY4MTBhNzU2NTc0NjI1Nzk4MTNhOTk4YTU1ZTE4MzBlNzY1OWY1MTVkYjRhZTE3MDVlZTIiLCJ0YWciOiIifQ==','eyJpdiI6ImlKOFpTdHh2c0cvUHBhY21wTlpkU0E9PSIsInZhbHVlIjoiV09uN3oxOEhvdnFGcS9HcjRTc1k3UT09IiwibWFjIjoiYzlhNTQwMTg4MDdjNWJhMTVlNzA0M2Y1NDcxMDMzNzJlMzE2MWVlZDRkOWQ1NGY5NTQxNTI4NDFiYzdiYTQwMSIsInRhZyI6IiJ9','eyJpdiI6IkpBVGFYTHdTTEZOd053NzNDOVpBYlE9PSIsInZhbHVlIjoiWjFVVkxrdmNodUtMc3V3SXJSWHlxTmthcjJjcFg0UEE0L2drbDA3ZERXYz0iLCJtYWMiOiIzNThjZjc0NTZjMTk4MjYwMTdlZDlhMmM1NTQzMDUwZDk5MWQxNzc1YmUwMjZmOTcwNmZlMzAyYjIyOWE0NDM2IiwidGFnIjoiIn0=','eyJpdiI6IlRSVWd5Z3QrM0JvR2FOOXF2ZnoyU0E9PSIsInZhbHVlIjoiSkY3dS8xdUZHMEFLMnYwYXN1Y0tkQT09IiwibWFjIjoiMjBmNDVkNmMwNmMwODE3OTgyMjg2NThmMDBkNTVjM2NiMWUwOTE4MDc4NmMxNzExOTQ2YTI1ZTk0Y2VkMjg0MSIsInRhZyI6IiJ9',NULL,'aktif','2024-11-18','2026-07-18 13:11:12','2026-07-18 13:11:12');
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_11_194647_create_permission_tables',1),(5,'2026_07_11_194755_create_branches_table',1),(6,'2026_07_11_194756_create_user_branch_scope_table',1),(7,'2026_07_11_194757_create_user_branch_scope_branch_table',1),(8,'2026_07_11_212422_create_chart_of_accounts_table',1),(9,'2026_07_11_213302_create_accounting_periods_table',1),(10,'2026_07_11_213303_create_journal_entries_table',1),(11,'2026_07_11_213304_create_journal_lines_table',1),(12,'2026_07_11_213705_create_journal_entry_idempotency_keys_table',1),(13,'2026_07_11_221048_add_two_factor_columns_to_users_table',1),(14,'2026_07_11_221652_create_audit_logs_table',1),(15,'2026_07_11_223853_create_member_types_table',1),(16,'2026_07_11_223854_create_members_table',1),(17,'2026_07_11_223855_create_member_contact_consents_table',1),(18,'2026_07_11_230132_create_app_branding_settings_table',1),(19,'2026_07_11_230927_create_member_card_templates_table',1),(20,'2026_07_11_230928_create_member_card_fields_table',1),(21,'2026_07_11_230930_add_photo_path_to_members_table',1),(22,'2026_07_12_042709_create_savings_products_table',1),(23,'2026_07_12_042710_create_savings_product_rate_history_table',1),(24,'2026_07_12_042711_create_savings_accounts_table',1),(25,'2026_07_12_042713_create_savings_transactions_table',1),(26,'2026_07_12_051823_create_loan_products_table',1),(27,'2026_07_12_051824_create_loan_product_rate_history_table',1),(28,'2026_07_12_051825_create_loans_table',1),(29,'2026_07_12_051826_create_loan_approvals_table',1),(30,'2026_07_12_051827_create_loan_schedules_table',1),(31,'2026_07_12_055206_create_opening_balance_batches_table',1),(32,'2026_07_12_055208_create_opening_balance_savings_table',1),(33,'2026_07_12_055209_create_opening_balance_loans_table',1),(34,'2026_07_12_055210_create_opening_balance_installments_table',1),(35,'2026_07_12_055211_create_opening_balance_coa_table',1),(36,'2026_07_12_074034_create_cash_categories_table',1),(37,'2026_07_12_074037_create_teller_cash_transactions_table',1),(38,'2026_07_12_080218_create_business_units_table',1),(39,'2026_07_12_080221_create_business_unit_transactions_table',1),(40,'2026_07_12_085813_create_products_table',1),(41,'2026_07_12_085815_create_suppliers_table',1),(42,'2026_07_12_085818_create_stock_ledger_table',1),(43,'2026_07_12_095452_create_approval_thresholds_table',1),(44,'2026_07_12_095455_create_purchase_transactions_table',1),(45,'2026_07_12_095457_create_purchase_items_table',1),(46,'2026_07_12_095500_create_purchase_payments_table',1),(47,'2026_07_12_115007_create_pos_sales_table',1),(48,'2026_07_12_115011_create_pos_sale_items_table',1),(49,'2026_07_12_121716_create_stock_reasons_table',1),(50,'2026_07_12_121719_create_purchase_returns_table',1),(51,'2026_07_12_121721_create_sales_returns_table',1),(52,'2026_07_12_125525_create_stock_adjustments_table',1),(53,'2026_07_12_135831_create_fixed_asset_categories_table',1),(54,'2026_07_12_135834_create_fixed_assets_table',1),(55,'2026_07_12_142056_add_depreciation_rate_percentage_to_fixed_assets_table',1),(56,'2026_07_12_142058_create_fixed_asset_depreciation_entries_table',1),(57,'2026_07_12_144002_create_fixed_asset_disposals_table',1),(58,'2026_07_12_173057_create_cooperative_events_table',1),(59,'2026_07_12_173100_create_cooperative_event_branch_table',1),(60,'2026_07_12_173103_create_cooperative_event_members_table',1),(61,'2026_07_12_173105_create_cooperative_event_roles_table',1),(62,'2026_07_12_175023_create_notification_templates_table',1),(63,'2026_07_12_175026_create_notification_schedules_table',1),(64,'2026_07_12_175028_create_notification_logs_table',1),(65,'2026_07_12_181104_add_pending_and_paid_off_statuses_to_notification_logs_table',1),(66,'2026_07_12_193906_create_report_templates_table',1),(67,'2026_07_12_193909_create_report_exports_table',1),(68,'2026_07_13_092815_create_financial_report_exports_table',1),(69,'2026_07_13_105713_create_shu_allocation_categories_table',1),(70,'2026_07_13_111059_add_reconciliation_snapshot_to_opening_balance_batches_table',1),(71,'2026_07_13_111102_create_depreciation_batch_runs_table',1),(72,'2026_07_14_133802_add_performance_indexes_for_batch_and_report_queries',1),(73,'2026_07_15_190624_create_retribution_types_table',1),(74,'2026_07_15_191158_create_retribution_transactions_table',1),(75,'2026_07_15_191200_create_retribution_transaction_lines_table',1),(76,'2026_07_16_060220_create_opening_balance_upf_table',1),(77,'2026_07_16_190000_add_image_path_to_products_table',1),(78,'2026_07_16_190001_add_hutang_to_pos_sales_payment_method_enum',1),(79,'2026_07_16_190002_add_loan_id_to_pos_sales_table',1),(80,'2026_07_16_200000_add_is_active_to_users_table',1),(81,'2026_07_16_210000_add_cancellation_columns_to_savings_transactions_table',1),(82,'2026_07_16_220000_add_cancellation_columns_to_retribution_transactions_table',1),(83,'2026_07_17_090000_add_cancellation_to_stock_adjustments_table',1),(84,'2026_07_17_100000_add_cancellation_columns_to_fixed_asset_disposals_table',1),(85,'2026_07_17_110000_add_cancellation_columns_to_loans_table',1),(86,'2026_07_17_120000_create_savings_withdrawal_requests_table',1),(87,'2026_07_17_130000_create_savings_deposit_requests_table',1),(88,'2026_07_17_140000_add_paid_split_columns_to_loan_schedules_table',1),(89,'2026_07_17_150000_create_loan_repayments_table',1),(90,'2026_07_17_160000_create_loan_repayment_requests_table',1),(91,'2026_07_17_170000_create_print_settings_table',1),(92,'2026_07_17_170100_create_document_signatories_table',1),(93,'2026_07_17_170200_create_document_signature_slots_table',1),(94,'2026_07_19_090000_create_opening_balance_stock_table',2),(95,'2026_07_19_090100_add_saldo_awal_to_stock_ledger_transaction_type_enum',2);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(2,'App\\Models\\User',1),(3,'App\\Models\\User',1),(4,'App\\Models\\User',1),(5,'App\\Models\\User',1),(6,'App\\Models\\User',1),(7,'App\\Models\\User',1),(8,'App\\Models\\User',1),(8,'App\\Models\\User',2),(5,'App\\Models\\User',3),(2,'App\\Models\\User',4),(3,'App\\Models\\User',5),(4,'App\\Models\\User',6),(6,'App\\Models\\User',7),(7,'App\\Models\\User',8);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dedupe_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('whatsapp','email') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notifiable_id` bigint unsigned DEFAULT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu','terkirim','gagal','dibaca','tidak_dikirim_tanpa_consent','dibatalkan_lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `cost` decimal(10,2) DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_logs_dedupe_key_unique` (`dedupe_key`),
  KEY `notification_logs_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notification_logs_status_index` (`status`),
  KEY `notification_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_schedules`
--

DROP TABLE IF EXISTS `notification_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trigger_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `day_offset` int NOT NULL,
  `send_time` time NOT NULL DEFAULT '08:00:00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_schedules_trigger_type_day_offset_unique` (`trigger_type`,`day_offset`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_schedules`
--

LOCK TABLES `notification_schedules` WRITE;
/*!40000 ALTER TABLE `notification_schedules` DISABLE KEYS */;
INSERT INTO `notification_schedules` VALUES (1,'jatuh_tempo_angsuran',-3,'08:00:00',1,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(2,'jatuh_tempo_angsuran',-1,'08:00:00',1,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(3,'jatuh_tempo_angsuran',0,'08:00:00',1,'2026-07-18 13:09:48','2026-07-18 13:09:48');
/*!40000 ALTER TABLE `notification_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_templates`
--

DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trigger_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('whatsapp','email') COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_templates_trigger_type_channel_unique` (`trigger_type`,`channel`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_templates`
--

LOCK TABLES `notification_templates` WRITE;
/*!40000 ALTER TABLE `notification_templates` DISABLE KEYS */;
INSERT INTO `notification_templates` VALUES (1,'jatuh_tempo_angsuran','whatsapp',NULL,'Yth. {nama_anggota}, angsuran pinjaman {no_pinjaman} sebesar {nominal_angsuran} jatuh tempo pada {tanggal_jatuh_tempo}. Mohon segera melakukan pembayaran di {nama_cabang}. Terima kasih.',1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(2,'jatuh_tempo_angsuran','email','Pengingat Jatuh Tempo Angsuran — {no_pinjaman}','Yth. {nama_anggota},\\n\\nAngsuran pinjaman {no_pinjaman} sebesar {nominal_angsuran} jatuh tempo pada {tanggal_jatuh_tempo}. Mohon segera melakukan pembayaran di {nama_cabang}.\\n\\nTerima kasih.',1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(3,'kegiatan_dibatalkan','whatsapp',NULL,'Yth. {nama_anggota}, kegiatan \"{judul_kegiatan}\" yang dijadwalkan pada {tanggal_kegiatan} telah DIBATALKAN. Mohon maaf atas ketidaknyamanannya.',1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(4,'kegiatan_dibatalkan','email','Pembatalan Kegiatan — {judul_kegiatan}','Yth. {nama_anggota},\\n\\nKegiatan \"{judul_kegiatan}\" yang dijadwalkan pada {tanggal_kegiatan} telah DIBATALKAN.\\n\\nMohon maaf atas ketidaknyamanannya.',1,'2026-07-18 13:09:49','2026-07-18 13:09:49');
/*!40000 ALTER TABLE `notification_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_batches`
--

DROP TABLE IF EXISTS `opening_balance_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `cutoff_date` date NOT NULL,
  `status` enum('draft','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `locked_by` bigint unsigned DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `reconciliation_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opening_balance_batches_branch_id_foreign` (`branch_id`),
  KEY `opening_balance_batches_locked_by_foreign` (`locked_by`),
  CONSTRAINT `opening_balance_batches_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `opening_balance_batches_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_batches`
--

LOCK TABLES `opening_balance_batches` WRITE;
/*!40000 ALTER TABLE `opening_balance_batches` DISABLE KEYS */;
INSERT INTO `opening_balance_batches` VALUES (1,1,'2026-06-30','draft',NULL,NULL,NULL,'2026-07-18 23:13:28','2026-07-18 23:13:28'),(2,1,'2026-07-18','draft',NULL,NULL,NULL,'2026-07-18 23:37:24','2026-07-18 23:37:24');
/*!40000 ALTER TABLE `opening_balance_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_coa`
--

DROP TABLE IF EXISTS `opening_balance_coa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_coa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance_batch_id` bigint unsigned NOT NULL,
  `chart_of_account_id` bigint unsigned NOT NULL,
  `position` enum('debit','kredit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opening_balance_coa_chart_of_account_id_foreign` (`chart_of_account_id`),
  KEY `obc_batch_idx` (`opening_balance_batch_id`),
  CONSTRAINT `opening_balance_coa_chart_of_account_id_foreign` FOREIGN KEY (`chart_of_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `opening_balance_coa_opening_balance_batch_id_foreign` FOREIGN KEY (`opening_balance_batch_id`) REFERENCES `opening_balance_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_coa`
--

LOCK TABLES `opening_balance_coa` WRITE;
/*!40000 ALTER TABLE `opening_balance_coa` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balance_coa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_installments`
--

DROP TABLE IF EXISTS `opening_balance_installments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_installments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance_batch_id` bigint unsigned NOT NULL,
  `opening_balance_loan_id` bigint unsigned NOT NULL,
  `installment_number` smallint unsigned NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(18,2) NOT NULL,
  `interest_amount` decimal(18,2) NOT NULL,
  `penalty_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('belum_bayar','sebagian') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_bayar',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `obi_loan_installment_unique` (`opening_balance_loan_id`,`installment_number`),
  KEY `opening_balance_installments_opening_balance_batch_id_foreign` (`opening_balance_batch_id`),
  CONSTRAINT `opening_balance_installments_opening_balance_batch_id_foreign` FOREIGN KEY (`opening_balance_batch_id`) REFERENCES `opening_balance_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opening_balance_installments_opening_balance_loan_id_foreign` FOREIGN KEY (`opening_balance_loan_id`) REFERENCES `opening_balance_loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_installments`
--

LOCK TABLES `opening_balance_installments` WRITE;
/*!40000 ALTER TABLE `opening_balance_installments` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balance_installments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_loans`
--

DROP TABLE IF EXISTS `opening_balance_loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_loans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance_batch_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `loan_product_id` bigint unsigned NOT NULL,
  `external_loan_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disbursement_date` date NOT NULL,
  `original_principal` decimal(18,2) NOT NULL,
  `outstanding_principal` decimal(18,2) NOT NULL,
  `outstanding_interest` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tenor_days` smallint unsigned NOT NULL,
  `remaining_tenor_days` smallint unsigned NOT NULL,
  `next_installment_number` smallint unsigned NOT NULL,
  `next_due_date` date NOT NULL,
  `collectibility` enum('lancar','kurang_lancar','diragukan','macet') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lancar',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opening_balance_loans_member_id_foreign` (`member_id`),
  KEY `opening_balance_loans_loan_product_id_foreign` (`loan_product_id`),
  KEY `obl_batch_idx` (`opening_balance_batch_id`),
  CONSTRAINT `opening_balance_loans_loan_product_id_foreign` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `opening_balance_loans_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `opening_balance_loans_opening_balance_batch_id_foreign` FOREIGN KEY (`opening_balance_batch_id`) REFERENCES `opening_balance_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_loans`
--

LOCK TABLES `opening_balance_loans` WRITE;
/*!40000 ALTER TABLE `opening_balance_loans` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balance_loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_savings`
--

DROP TABLE IF EXISTS `opening_balance_savings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_savings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance_batch_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `savings_product_id` bigint unsigned NOT NULL,
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance` decimal(18,2) NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opening_balance_savings_member_id_foreign` (`member_id`),
  KEY `opening_balance_savings_savings_product_id_foreign` (`savings_product_id`),
  KEY `obs_batch_idx` (`opening_balance_batch_id`),
  CONSTRAINT `opening_balance_savings_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `opening_balance_savings_opening_balance_batch_id_foreign` FOREIGN KEY (`opening_balance_batch_id`) REFERENCES `opening_balance_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opening_balance_savings_savings_product_id_foreign` FOREIGN KEY (`savings_product_id`) REFERENCES `savings_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_savings`
--

LOCK TABLES `opening_balance_savings` WRITE;
/*!40000 ALTER TABLE `opening_balance_savings` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balance_savings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_stock`
--

DROP TABLE IF EXISTS `opening_balance_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_stock` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance_batch_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,4) NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opening_balance_stock_product_id_foreign` (`product_id`),
  KEY `obs_batch_idx` (`opening_balance_batch_id`),
  CONSTRAINT `opening_balance_stock_opening_balance_batch_id_foreign` FOREIGN KEY (`opening_balance_batch_id`) REFERENCES `opening_balance_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opening_balance_stock_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_stock`
--

LOCK TABLES `opening_balance_stock` WRITE;
/*!40000 ALTER TABLE `opening_balance_stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balance_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opening_balance_upf`
--

DROP TABLE IF EXISTS `opening_balance_upf`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `opening_balance_upf` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opening_balance_batch_id` bigint unsigned NOT NULL,
  `retribution_type_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `opening_balance_upf_retribution_type_id_foreign` (`retribution_type_id`),
  KEY `obu_batch_idx` (`opening_balance_batch_id`),
  CONSTRAINT `opening_balance_upf_opening_balance_batch_id_foreign` FOREIGN KEY (`opening_balance_batch_id`) REFERENCES `opening_balance_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `opening_balance_upf_retribution_type_id_foreign` FOREIGN KEY (`retribution_type_id`) REFERENCES `retribution_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opening_balance_upf`
--

LOCK TABLES `opening_balance_upf` WRITE;
/*!40000 ALTER TABLE `opening_balance_upf` DISABLE KEYS */;
/*!40000 ALTER TABLE `opening_balance_upf` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'simpanan.create','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(2,'simpanan.read','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(3,'simpanan.update','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(4,'simpanan.delete','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(5,'simpanan.approve','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(6,'simpanan.print','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(7,'pinjaman.create','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(8,'pinjaman.read','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(9,'pinjaman.update','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(10,'pinjaman.delete','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(11,'pinjaman.approve','web','2026-07-18 13:09:36','2026-07-18 13:09:36'),(12,'pinjaman.print','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(13,'retribusi_upf.create','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(14,'retribusi_upf.read','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(15,'retribusi_upf.delete','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(16,'kas_teller.create','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(17,'kas_teller.read','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(18,'kas_teller.update','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(19,'pos.create','web','2026-07-18 13:09:37','2026-07-18 13:09:37'),(20,'pos.read','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(21,'pembelian.create','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(22,'pembelian.read','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(23,'pembelian.approve','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(24,'hutang_supplier_pembayaran.create','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(25,'hutang_supplier_pembayaran.read','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(26,'persediaan_koreksi.create','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(27,'persediaan_koreksi.read','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(28,'persediaan_koreksi.approve','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(29,'persediaan_koreksi.delete','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(30,'aktiva_tetap.create','web','2026-07-18 13:09:38','2026-07-18 13:09:38'),(31,'aktiva_tetap.read','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(32,'aktiva_tetap.update','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(33,'aktiva_tetap.approve','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(34,'aktiva_tetap.delete','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(35,'saldo_awal.create','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(36,'saldo_awal.read','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(37,'saldo_awal.update','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(38,'saldo_awal.lock','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(39,'master_data.create','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(40,'master_data.read','web','2026-07-18 13:09:39','2026-07-18 13:09:39'),(41,'master_data.update','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(42,'coa_mapping.create','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(43,'coa_mapping.read','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(44,'coa_mapping.update','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(45,'chart_of_account.create','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(46,'chart_of_account.read','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(47,'chart_of_account.update','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(48,'chart_of_account.delete','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(49,'jurnal.read','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(50,'jurnal.adjust','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(51,'jurnal.create','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(52,'laporan_keuangan.read','web','2026-07-18 13:09:40','2026-07-18 13:09:40'),(53,'laporan_kustom.create','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(54,'laporan_kustom.read','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(55,'tarif.read','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(56,'tarif.update','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(57,'shu.read','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(58,'shu.calculate','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(59,'rat.read','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(60,'rat.compose','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(61,'kalender.create','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(62,'kalender.read','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(63,'kalender.update','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(64,'kalender.delete','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(65,'notifikasi_template.manage','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(66,'notifikasi_log.read','web','2026-07-18 13:09:41','2026-07-18 13:09:41'),(67,'keamanan_audit.read','web','2026-07-18 13:09:42','2026-07-18 13:09:42'),(68,'keamanan_audit.export','web','2026-07-18 13:09:43','2026-07-18 13:09:43'),(69,'user.manage','web','2026-07-18 13:09:43','2026-07-18 13:09:43'),(70,'role.manage','web','2026-07-18 13:09:44','2026-07-18 13:09:44'),(71,'branch_scope.manage','web','2026-07-18 13:09:44','2026-07-18 13:09:44'),(72,'security_config.manage','web','2026-07-18 13:09:44','2026-07-18 13:09:44'),(73,'unit_usaha.create','web','2026-07-18 13:09:44','2026-07-18 13:09:44'),(74,'unit_usaha.read','web','2026-07-18 13:09:44','2026-07-18 13:09:44'),(75,'unit_usaha.update','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(76,'branding.manage','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(77,'member_card.manage','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(78,'member_card.print','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(79,'cetakan.manage','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(80,'laporan.read','web','2026-07-18 17:03:05','2026-07-18 17:03:05');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_sale_items`
--

DROP TABLE IF EXISTS `pos_sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pos_sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `unit_cost` decimal(18,4) NOT NULL,
  `subtotal` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pos_sale_items_pos_sale_id_foreign` (`pos_sale_id`),
  KEY `pos_sale_items_product_id_foreign` (`product_id`),
  CONSTRAINT `pos_sale_items_pos_sale_id_foreign` FOREIGN KEY (`pos_sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pos_sale_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_sale_items`
--

LOCK TABLES `pos_sale_items` WRITE;
/*!40000 ALTER TABLE `pos_sale_items` DISABLE KEYS */;
INSERT INTO `pos_sale_items` VALUES (1,1,3,5.0000,16000.00,13500.0000,80000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(2,1,7,3.0000,4500.00,3200.0000,13500.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(3,2,4,2.0000,28000.00,24000.0000,56000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(4,2,9,4.0000,13000.00,10500.0000,52000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20');
/*!40000 ALTER TABLE `pos_sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_sales`
--

DROP TABLE IF EXISTS `pos_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `savings_account_id` bigint unsigned DEFAULT NULL,
  `loan_id` bigint unsigned DEFAULT NULL,
  `sale_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_date` date NOT NULL,
  `payment_method` enum('tunai','potong_simpanan','hutang') COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(18,2) NOT NULL,
  `total_cogs` decimal(18,2) NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_sales_sale_number_unique` (`sale_number`),
  KEY `pos_sales_savings_account_id_foreign` (`savings_account_id`),
  KEY `pos_sales_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `pos_sales_created_by_foreign` (`created_by`),
  KEY `pos_sales_branch_id_sale_date_index` (`branch_id`,`sale_date`),
  KEY `pos_sales_loan_id_foreign` (`loan_id`),
  CONSTRAINT `pos_sales_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pos_sales_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pos_sales_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pos_sales_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pos_sales_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_sales`
--

LOCK TABLES `pos_sales` WRITE;
/*!40000 ALTER TABLE `pos_sales` DISABLE KEYS */;
INSERT INTO `pos_sales` VALUES (1,1,NULL,NULL,'JL-260718-6109','2026-07-18','tunai',93500.00,77100.00,22,4,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(2,1,NULL,NULL,'JL-260718-4893','2026-07-18','tunai',108000.00,90000.00,23,4,'2026-07-18 13:11:20','2026-07-18 13:11:20');
/*!40000 ALTER TABLE `pos_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `print_settings`
--

DROP TABLE IF EXISTS `print_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `print_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `paper_size` enum('a4','letter','f4') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a4',
  `orientation` enum('portrait','landscape') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'portrait',
  `margin_mm` smallint unsigned NOT NULL DEFAULT '15',
  `font_size_pt` tinyint unsigned NOT NULL DEFAULT '11',
  `show_address` tinyint(1) NOT NULL DEFAULT '1',
  `address_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `print_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `print_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `print_settings`
--

LOCK TABLES `print_settings` WRITE;
/*!40000 ALTER TABLE `print_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `print_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `coa_inventory_account_id` bigint unsigned DEFAULT NULL,
  `coa_cogs_account_id` bigint unsigned DEFAULT NULL,
  `coa_sales_revenue_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_code_unique` (`code`),
  KEY `products_coa_inventory_account_id_foreign` (`coa_inventory_account_id`),
  KEY `products_coa_cogs_account_id_foreign` (`coa_cogs_account_id`),
  KEY `products_coa_sales_revenue_account_id_foreign` (`coa_sales_revenue_account_id`),
  CONSTRAINT `products_coa_cogs_account_id_foreign` FOREIGN KEY (`coa_cogs_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_coa_inventory_account_id_foreign` FOREIGN KEY (`coa_inventory_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_coa_sales_revenue_account_id_foreign` FOREIGN KEY (`coa_sales_revenue_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'BRG-001','Beras 5kg','Sembako',NULL,'karung',62000.00,68000.00,12,73,56,1,'2026-07-18 13:11:17','2026-07-18 13:11:17'),(2,'BRG-002','Minyak Goreng 1L','Sembako',NULL,'botol',15500.00,18000.00,12,73,56,1,'2026-07-18 13:11:18','2026-07-18 13:11:18'),(3,'BRG-003','Gula Pasir 1kg','Sembako',NULL,'kg',13500.00,16000.00,12,73,56,1,'2026-07-18 13:11:18','2026-07-18 13:11:18'),(4,'BRG-004','Telur Ayam 1kg','Sembako',NULL,'kg',24000.00,28000.00,12,73,56,1,'2026-07-18 13:11:18','2026-07-18 13:11:18'),(5,'BRG-005','Kopi Sachet (renceng)','Sembako',NULL,'renceng',9500.00,12000.00,12,73,56,1,'2026-07-18 13:11:18','2026-07-18 13:11:18'),(6,'BRG-006','Mie Instan (dus)','Sembako',NULL,'dus',95000.00,110000.00,12,73,56,1,'2026-07-18 13:11:18','2026-07-18 13:11:18'),(7,'BRG-007','Sabun Mandi','Sembako',NULL,'pcs',3200.00,4500.00,12,73,56,1,'2026-07-18 13:11:18','2026-07-18 13:11:18'),(8,'BRG-008','Air Mineral 600ml (dus)','Sembako',NULL,'dus',32000.00,40000.00,12,73,56,1,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(9,'BRG-009','Tepung Terigu 1kg','Sembako',NULL,'kg',10500.00,13000.00,12,73,56,1,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(10,'BRG-010','Kecap Manis 600ml','Sembako',NULL,'botol',14000.00,17500.00,12,73,56,1,'2026-07-18 13:11:19','2026-07-18 13:11:19');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_items`
--

DROP TABLE IF EXISTS `purchase_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_transaction_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `subtotal` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_items_purchase_transaction_id_foreign` (`purchase_transaction_id`),
  KEY `purchase_items_product_id_foreign` (`product_id`),
  CONSTRAINT `purchase_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_items_purchase_transaction_id_foreign` FOREIGN KEY (`purchase_transaction_id`) REFERENCES `purchase_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_items`
--

LOCK TABLES `purchase_items` WRITE;
/*!40000 ALTER TABLE `purchase_items` DISABLE KEYS */;
INSERT INTO `purchase_items` VALUES (1,1,1,50.0000,62000.00,3100000.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(2,1,2,80.0000,15500.00,1240000.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(3,1,6,30.0000,95000.00,2850000.00,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(4,2,3,40.0000,13500.00,540000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(5,2,4,20.0000,24000.00,480000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(6,2,7,60.0000,3200.00,192000.00,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(7,2,9,25.0000,10500.00,262500.00,'2026-07-18 13:11:20','2026-07-18 13:11:20');
/*!40000 ALTER TABLE `purchase_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_payments`
--

DROP TABLE IF EXISTS `purchase_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_transaction_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `payment_date` date NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_payments_purchase_transaction_id_foreign` (`purchase_transaction_id`),
  KEY `purchase_payments_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `purchase_payments_created_by_foreign` (`created_by`),
  CONSTRAINT `purchase_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_payments_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_payments_purchase_transaction_id_foreign` FOREIGN KEY (`purchase_transaction_id`) REFERENCES `purchase_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_payments`
--

LOCK TABLES `purchase_payments` WRITE;
/*!40000 ALTER TABLE `purchase_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_returns`
--

DROP TABLE IF EXISTS `purchase_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_item_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `stock_reason_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `return_date` date NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_returns_purchase_item_id_foreign` (`purchase_item_id`),
  KEY `purchase_returns_branch_id_foreign` (`branch_id`),
  KEY `purchase_returns_stock_reason_id_foreign` (`stock_reason_id`),
  KEY `purchase_returns_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `purchase_returns_created_by_foreign` (`created_by`),
  CONSTRAINT `purchase_returns_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_returns_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_returns_purchase_item_id_foreign` FOREIGN KEY (`purchase_item_id`) REFERENCES `purchase_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_returns_stock_reason_id_foreign` FOREIGN KEY (`stock_reason_id`) REFERENCES `stock_reasons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_returns`
--

LOCK TABLES `purchase_returns` WRITE;
/*!40000 ALTER TABLE `purchase_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_transactions`
--

DROP TABLE IF EXISTS `purchase_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `purchase_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_date` date NOT NULL,
  `payment_method` enum('tunai','kredit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(18,2) NOT NULL,
  `status` enum('menunggu_approval','diposting','ditolak') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_approval',
  `payment_status` enum('lunas','belum_lunas','sebagian') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_transactions_purchase_number_unique` (`purchase_number`),
  KEY `purchase_transactions_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_transactions_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `purchase_transactions_created_by_foreign` (`created_by`),
  KEY `purchase_transactions_approved_by_foreign` (`approved_by`),
  KEY `purchase_transactions_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `purchase_transactions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_transactions_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_transactions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_transactions`
--

LOCK TABLES `purchase_transactions` WRITE;
/*!40000 ALTER TABLE `purchase_transactions` DISABLE KEYS */;
INSERT INTO `purchase_transactions` VALUES (1,1,1,'PB-260718-1315','2026-07-18','kredit',7190000.00,'diposting','belum_lunas',0.00,20,5,3,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(2,1,2,'PB-260718-0626','2026-07-18','tunai',1474500.00,'diposting','lunas',1474500.00,21,5,NULL,'2026-07-18 13:11:20','2026-07-18 13:11:20');
/*!40000 ALTER TABLE `purchase_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_exports`
--

DROP TABLE IF EXISTS `report_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_template_id` bigint unsigned DEFAULT NULL,
  `report_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns` json NOT NULL,
  `filters` json DEFAULT NULL,
  `format` enum('pdf','xlsx') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('menunggu','memproses','selesai','gagal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_exports_report_template_id_foreign` (`report_template_id`),
  KEY `report_exports_requested_by_foreign` (`requested_by`),
  CONSTRAINT `report_exports_report_template_id_foreign` FOREIGN KEY (`report_template_id`) REFERENCES `report_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `report_exports_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_exports`
--

LOCK TABLES `report_exports` WRITE;
/*!40000 ALTER TABLE `report_exports` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_exports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_templates`
--

DROP TABLE IF EXISTS `report_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `columns` json NOT NULL,
  `filters` json DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `shared_role_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_templates_created_by_foreign` (`created_by`),
  KEY `report_templates_shared_role_id_foreign` (`shared_role_id`),
  CONSTRAINT `report_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_templates_shared_role_id_foreign` FOREIGN KEY (`shared_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_templates`
--

LOCK TABLES `report_templates` WRITE;
/*!40000 ALTER TABLE `report_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retribution_transaction_lines`
--

DROP TABLE IF EXISTS `retribution_transaction_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retribution_transaction_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `retribution_transaction_id` bigint unsigned NOT NULL,
  `retribution_type_id` bigint unsigned NOT NULL,
  `retribution_type_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage_applied` decimal(5,2) NOT NULL,
  `chart_of_account_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retribution_transaction_lines_retribution_transaction_id_foreign` (`retribution_transaction_id`),
  KEY `retribution_transaction_lines_retribution_type_id_foreign` (`retribution_type_id`),
  KEY `retribution_transaction_lines_chart_of_account_id_foreign` (`chart_of_account_id`),
  CONSTRAINT `retribution_transaction_lines_chart_of_account_id_foreign` FOREIGN KEY (`chart_of_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `retribution_transaction_lines_retribution_transaction_id_foreign` FOREIGN KEY (`retribution_transaction_id`) REFERENCES `retribution_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `retribution_transaction_lines_retribution_type_id_foreign` FOREIGN KEY (`retribution_type_id`) REFERENCES `retribution_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retribution_transaction_lines`
--

LOCK TABLES `retribution_transaction_lines` WRITE;
/*!40000 ALTER TABLE `retribution_transaction_lines` DISABLE KEYS */;
INSERT INTO `retribution_transaction_lines` VALUES (1,1,1,'Retribusi Kebersihan',30.00,61,45000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(2,1,2,'Retribusi Keamanan',25.00,62,37500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(3,1,3,'Retribusi Listrik',20.00,63,30000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(4,1,4,'Retribusi Air',15.00,64,22500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(5,1,5,'Retribusi Lainnya',10.00,65,15000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(6,2,1,'Retribusi Kebersihan',30.00,61,30000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(7,2,2,'Retribusi Keamanan',25.00,62,25000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(8,2,3,'Retribusi Listrik',20.00,63,20000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(9,2,4,'Retribusi Air',15.00,64,15000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(10,2,5,'Retribusi Lainnya',10.00,65,10000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(11,3,1,'Retribusi Kebersihan',30.00,61,15000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(12,3,2,'Retribusi Keamanan',25.00,62,12500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(13,3,3,'Retribusi Listrik',20.00,63,10000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(14,3,4,'Retribusi Air',15.00,64,7500.00,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(15,3,5,'Retribusi Lainnya',10.00,65,5000.00,'2026-07-18 13:11:22','2026-07-18 13:11:22');
/*!40000 ALTER TABLE `retribution_transaction_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retribution_transactions`
--

DROP TABLE IF EXISTS `retribution_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retribution_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `transaction_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_date` date NOT NULL,
  `payer_type` enum('umum','anggota') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` bigint unsigned DEFAULT NULL,
  `total_amount` decimal(18,2) NOT NULL,
  `payment_method` enum('tunai','transfer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tunai',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retribution_transactions_transaction_number_unique` (`transaction_number`),
  KEY `retribution_transactions_member_id_foreign` (`member_id`),
  KEY `retribution_transactions_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `retribution_transactions_created_by_foreign` (`created_by`),
  KEY `retribution_transactions_branch_id_transaction_date_index` (`branch_id`,`transaction_date`),
  KEY `retribution_transactions_cancelled_by_foreign` (`cancelled_by`),
  KEY `retribution_transactions_reversal_journal_entry_id_foreign` (`reversal_journal_entry_id`),
  CONSTRAINT `retribution_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `retribution_transactions_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `retribution_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `retribution_transactions_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `retribution_transactions_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `retribution_transactions_reversal_journal_entry_id_foreign` FOREIGN KEY (`reversal_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retribution_transactions`
--

LOCK TABLES `retribution_transactions` WRITE;
/*!40000 ALTER TABLE `retribution_transactions` DISABLE KEYS */;
INSERT INTO `retribution_transactions` VALUES (1,1,'RB-260718-3254','2026-07-18','anggota','Toko Sumber Makmur (Kios)',5,150000.00,'tunai','Iuran bulanan kios',27,6,NULL,NULL,NULL,NULL,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(2,1,'RB-260718-5488','2026-07-18','anggota','Warung Blok C-12',6,100000.00,'tunai','Iuran bulanan blok',28,6,NULL,NULL,NULL,NULL,'2026-07-18 13:11:22','2026-07-18 13:11:22'),(3,1,'RB-260718-9156','2026-07-18','umum','Pedagang Kaki Lima Depan Pasar',NULL,50000.00,'tunai','Retribusi harian umum',29,6,NULL,NULL,NULL,NULL,'2026-07-18 13:11:22','2026-07-18 13:11:22');
/*!40000 ALTER TABLE `retribution_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retribution_types`
--

DROP TABLE IF EXISTS `retribution_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retribution_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `coa_revenue_account_id` bigint unsigned DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retribution_types_code_unique` (`code`),
  KEY `retribution_types_coa_revenue_account_id_foreign` (`coa_revenue_account_id`),
  CONSTRAINT `retribution_types_coa_revenue_account_id_foreign` FOREIGN KEY (`coa_revenue_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retribution_types`
--

LOCK TABLES `retribution_types` WRITE;
/*!40000 ALTER TABLE `retribution_types` DISABLE KEYS */;
INSERT INTO `retribution_types` VALUES (1,'RET-KBR','Retribusi Kebersihan',30.00,61,0,1,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(2,'RET-KMN','Retribusi Keamanan',25.00,62,0,1,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(3,'RET-LST','Retribusi Listrik',20.00,63,0,1,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(4,'RET-AIR','Retribusi Air',15.00,64,0,1,'2026-07-18 13:11:21','2026-07-18 13:11:21'),(5,'RET-LNN','Retribusi Lainnya',10.00,65,0,1,'2026-07-18 13:11:22','2026-07-18 13:11:22');
/*!40000 ALTER TABLE `retribution_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (2,1),(7,1),(8,1),(1,2),(2,2),(3,2),(4,2),(5,2),(6,2),(7,2),(8,2),(12,2),(16,2),(17,2),(18,2),(19,2),(20,2),(49,2),(78,2),(7,3),(8,3),(9,3),(13,4),(14,4),(15,4),(1,5),(2,5),(3,5),(4,5),(5,5),(6,5),(7,5),(8,5),(9,5),(10,5),(11,5),(12,5),(13,5),(14,5),(15,5),(16,5),(17,5),(18,5),(19,5),(20,5),(21,5),(22,5),(23,5),(26,5),(27,5),(28,5),(29,5),(30,5),(31,5),(32,5),(33,5),(34,5),(39,5),(40,5),(41,5),(49,5),(52,5),(53,5),(54,5),(55,5),(56,5),(57,5),(58,5),(59,5),(60,5),(61,5),(62,5),(63,5),(64,5),(67,5),(73,5),(74,5),(75,5),(77,5),(78,5),(80,5),(14,6),(24,6),(25,6),(27,6),(28,6),(31,6),(33,6),(35,6),(36,6),(37,6),(38,6),(40,6),(42,6),(43,6),(44,6),(49,6),(50,6),(51,6),(52,6),(53,6),(54,6),(57,6),(58,6),(67,6),(80,6),(2,7),(6,7),(8,7),(12,7),(14,7),(17,7),(20,7),(22,7),(27,7),(31,7),(49,7),(52,7),(54,7),(55,7),(57,7),(59,7),(62,7),(66,7),(67,7),(68,7),(80,7),(6,8),(12,8),(45,8),(46,8),(47,8),(48,8),(65,8),(66,8),(67,8),(69,8),(70,8),(71,8),(72,8),(76,8),(77,8),(79,8);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'anggota','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(2,'teller','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(3,'petugas_kredit','web','2026-07-18 13:09:45','2026-07-18 13:09:45'),(4,'petugas_upf','web','2026-07-18 13:09:46','2026-07-18 13:09:46'),(5,'manajer','web','2026-07-18 13:09:46','2026-07-18 13:09:46'),(6,'bendahara','web','2026-07-18 13:09:46','2026-07-18 13:09:46'),(7,'pengawas','web','2026-07-18 13:09:46','2026-07-18 13:09:46'),(8,'admin_sistem','web','2026-07-18 13:09:47','2026-07-18 13:09:47');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_returns`
--

DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pos_sale_item_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `unit_cost` decimal(18,4) NOT NULL,
  `return_date` date NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_returns_pos_sale_item_id_foreign` (`pos_sale_item_id`),
  KEY `sales_returns_branch_id_foreign` (`branch_id`),
  KEY `sales_returns_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `sales_returns_created_by_foreign` (`created_by`),
  CONSTRAINT `sales_returns_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_returns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_returns_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_pos_sale_item_id_foreign` FOREIGN KEY (`pos_sale_item_id`) REFERENCES `pos_sale_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_returns`
--

LOCK TABLES `sales_returns` WRITE;
/*!40000 ALTER TABLE `sales_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_accounts`
--

DROP TABLE IF EXISTS `savings_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `savings_product_id` bigint unsigned NOT NULL,
  `account_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('aktif','ditutup') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aktif',
  `opened_at` date NOT NULL,
  `closed_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `savings_accounts_account_number_unique` (`account_number`),
  KEY `savings_accounts_branch_id_foreign` (`branch_id`),
  KEY `savings_accounts_savings_product_id_foreign` (`savings_product_id`),
  KEY `savings_accounts_member_id_savings_product_id_index` (`member_id`,`savings_product_id`),
  CONSTRAINT `savings_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_accounts_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_accounts_savings_product_id_foreign` FOREIGN KEY (`savings_product_id`) REFERENCES `savings_products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_accounts`
--

LOCK TABLES `savings_accounts` WRITE;
/*!40000 ALTER TABLE `savings_accounts` DISABLE KEYS */;
INSERT INTO `savings_accounts` VALUES (1,1,1,1,'SIM-DEMO-260718-2090',500000.00,'aktif','2026-07-18',NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(2,1,2,1,'SIM-DEMO-260718-3310',850000.00,'aktif','2026-07-18',NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(3,2,3,1,'SIM-DEMO-260718-5860',1300000.00,'aktif','2026-07-18',NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(4,3,4,1,'SIM-DEMO-260718-4584',500000.00,'aktif','2026-07-18',NULL,'2026-07-18 13:11:13','2026-07-18 13:11:14'),(5,1,5,1,'SIM-DEMO-260718-5039',2100000.00,'aktif','2026-07-18',NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(6,1,6,1,'SIM-DEMO-260718-8808',1800000.00,'aktif','2026-07-18',NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14');
/*!40000 ALTER TABLE `savings_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_deposit_requests`
--

DROP TABLE IF EXISTS `savings_deposit_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_deposit_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `savings_account_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `status` enum('menunggu_pembayaran','dibayar','kedaluwarsa','gagal','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_pembayaran',
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `xendit_invoice_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xendit_invoice_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `savings_transaction_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `savings_deposit_requests_external_id_unique` (`external_id`),
  KEY `savings_deposit_requests_savings_account_id_foreign` (`savings_account_id`),
  KEY `savings_deposit_requests_member_id_foreign` (`member_id`),
  KEY `savings_deposit_requests_requested_by_foreign` (`requested_by`),
  KEY `savings_deposit_requests_savings_transaction_id_foreign` (`savings_transaction_id`),
  KEY `savings_deposit_requests_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `savings_deposit_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_deposit_requests_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_deposit_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_deposit_requests_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_deposit_requests_savings_transaction_id_foreign` FOREIGN KEY (`savings_transaction_id`) REFERENCES `savings_transactions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_deposit_requests`
--

LOCK TABLES `savings_deposit_requests` WRITE;
/*!40000 ALTER TABLE `savings_deposit_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `savings_deposit_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_product_rate_history`
--

DROP TABLE IF EXISTS `savings_product_rate_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_product_rate_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `savings_product_id` bigint unsigned NOT NULL,
  `rate_percentage` decimal(6,3) NOT NULL,
  `tiers` json DEFAULT NULL,
  `effective_from` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sprh_product_effective_idx` (`savings_product_id`,`effective_from`),
  CONSTRAINT `savings_product_rate_history_savings_product_id_foreign` FOREIGN KEY (`savings_product_id`) REFERENCES `savings_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_product_rate_history`
--

LOCK TABLES `savings_product_rate_history` WRITE;
/*!40000 ALTER TABLE `savings_product_rate_history` DISABLE KEYS */;
INSERT INTO `savings_product_rate_history` VALUES (1,1,2.500,NULL,'2025-07-18','2026-07-18 13:11:13','2026-07-18 13:11:13');
/*!40000 ALTER TABLE `savings_product_rate_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_products`
--

DROP TABLE IF EXISTS `savings_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('pokok','wajib','sukarela','berjangka') COLLATE utf8mb4_unicode_ci NOT NULL,
  `interest_method` enum('flat','saldo_harian','saldo_rata_rata_bulanan','tiered') COLLATE utf8mb4_unicode_ci NOT NULL,
  `minimum_initial_deposit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `minimum_subsequent_deposit` decimal(18,2) NOT NULL DEFAULT '0.00',
  `admin_fee` decimal(18,2) DEFAULT NULL,
  `admin_fee_period` enum('bulanan','tahunan') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withdrawal_penalty_percentage` decimal(5,2) DEFAULT NULL,
  `coa_liability_account_id` bigint unsigned DEFAULT NULL,
  `coa_interest_expense_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `savings_products_code_unique` (`code`),
  KEY `savings_products_coa_liability_account_id_foreign` (`coa_liability_account_id`),
  KEY `savings_products_coa_interest_expense_account_id_foreign` (`coa_interest_expense_account_id`),
  CONSTRAINT `savings_products_coa_interest_expense_account_id_foreign` FOREIGN KEY (`coa_interest_expense_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_products_coa_liability_account_id_foreign` FOREIGN KEY (`coa_liability_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_products`
--

LOCK TABLES `savings_products` WRITE;
/*!40000 ALTER TABLE `savings_products` DISABLE KEYS */;
INSERT INTO `savings_products` VALUES (1,'SIM-DEMO','Simpanan Sukarela Demo','sukarela','flat',10000.00,5000.00,NULL,NULL,NULL,27,74,1,'2026-07-18 13:11:12','2026-07-18 13:11:12');
/*!40000 ALTER TABLE `savings_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_transactions`
--

DROP TABLE IF EXISTS `savings_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `savings_account_id` bigint unsigned NOT NULL,
  `type` enum('setor','tarik') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `balance_after` decimal(18,2) NOT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_transactions_branch_id_foreign` (`branch_id`),
  KEY `savings_transactions_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `savings_transactions_created_by_foreign` (`created_by`),
  KEY `savings_transactions_savings_account_id_index` (`savings_account_id`),
  KEY `savings_transactions_cancelled_by_foreign` (`cancelled_by`),
  KEY `savings_transactions_reversal_journal_entry_id_foreign` (`reversal_journal_entry_id`),
  CONSTRAINT `savings_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_transactions_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_transactions_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_transactions_reversal_journal_entry_id_foreign` FOREIGN KEY (`reversal_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_transactions_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_transactions`
--

LOCK TABLES `savings_transactions` WRITE;
/*!40000 ALTER TABLE `savings_transactions` DISABLE KEYS */;
INSERT INTO `savings_transactions` VALUES (1,1,1,'setor',500000.00,500000.00,2,4,'Setoran awal pembukaan rekening',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(2,1,1,'setor',100000.00,600000.00,3,4,'Setoran rutin bulanan',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(3,1,1,'tarik',100000.00,500000.00,4,4,'Tarik tunai kebutuhan mendesak',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(4,1,2,'setor',750000.00,750000.00,5,4,'Setoran awal pembukaan rekening',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(5,1,2,'setor',100000.00,850000.00,6,4,'Setoran rutin bulanan',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(6,2,3,'setor',1000000.00,1000000.00,7,4,'Setoran awal pembukaan rekening',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(7,2,3,'setor',300000.00,1300000.00,8,4,'Setoran rutin bulanan',NULL,NULL,NULL,NULL,'2026-07-18 13:11:13','2026-07-18 13:11:13'),(8,3,4,'setor',300000.00,300000.00,9,4,'Setoran awal pembukaan rekening',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(9,3,4,'setor',300000.00,600000.00,10,4,'Setoran rutin bulanan',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(10,3,4,'tarik',100000.00,500000.00,11,4,'Tarik tunai kebutuhan mendesak',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(11,1,5,'setor',2000000.00,2000000.00,12,4,'Setoran awal pembukaan rekening',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(12,1,5,'setor',100000.00,2100000.00,13,4,'Setoran rutin bulanan',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(13,1,6,'setor',1500000.00,1500000.00,14,4,'Setoran awal pembukaan rekening',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14'),(14,1,6,'setor',300000.00,1800000.00,15,4,'Setoran rutin bulanan',NULL,NULL,NULL,NULL,'2026-07-18 13:11:14','2026-07-18 13:11:14');
/*!40000 ALTER TABLE `savings_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_withdrawal_requests`
--

DROP TABLE IF EXISTS `savings_withdrawal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_withdrawal_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `savings_account_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `status` enum('menunggu','disetujui','ditolak') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `requested_by` bigint unsigned NOT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `decision_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `savings_transaction_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_withdrawal_requests_savings_account_id_foreign` (`savings_account_id`),
  KEY `savings_withdrawal_requests_member_id_foreign` (`member_id`),
  KEY `savings_withdrawal_requests_requested_by_foreign` (`requested_by`),
  KEY `savings_withdrawal_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `savings_withdrawal_requests_savings_transaction_id_foreign` (`savings_transaction_id`),
  KEY `savings_withdrawal_requests_branch_id_status_index` (`branch_id`,`status`),
  CONSTRAINT `savings_withdrawal_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_withdrawal_requests_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_withdrawal_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_withdrawal_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_withdrawal_requests_savings_account_id_foreign` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `savings_withdrawal_requests_savings_transaction_id_foreign` FOREIGN KEY (`savings_transaction_id`) REFERENCES `savings_transactions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_withdrawal_requests`
--

LOCK TABLES `savings_withdrawal_requests` WRITE;
/*!40000 ALTER TABLE `savings_withdrawal_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `savings_withdrawal_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shu_allocation_categories`
--

DROP TABLE IF EXISTS `shu_allocation_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shu_allocation_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `is_jasa_anggota` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shu_allocation_categories`
--

LOCK TABLES `shu_allocation_categories` WRITE;
/*!40000 ALTER TABLE `shu_allocation_categories` DISABLE KEYS */;
INSERT INTO `shu_allocation_categories` VALUES (1,'Cadangan Koperasi',40.00,0,1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(2,'Jasa Anggota',25.00,1,1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(3,'Dana Pengurus',5.00,0,1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(4,'Dana Karyawan',5.00,0,1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(5,'Dana Pendidikan',5.00,0,1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(6,'Dana Sosial',10.00,0,1,'2026-07-18 13:09:49','2026-07-18 13:09:49'),(7,'Dana Pembangunan Daerah Kerja',10.00,0,1,'2026-07-18 13:09:49','2026-07-18 13:09:49');
/*!40000 ALTER TABLE `shu_allocation_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `stock_reason_id` bigint unsigned NOT NULL,
  `system_qty` decimal(18,4) NOT NULL,
  `physical_qty` decimal(18,4) NOT NULL,
  `variance_qty` decimal(18,4) NOT NULL,
  `unit_cost` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` enum('menunggu_approval','diposting','ditolak','dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu_approval',
  `adjustment_date` date NOT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustments_product_id_foreign` (`product_id`),
  KEY `stock_adjustments_stock_reason_id_foreign` (`stock_reason_id`),
  KEY `stock_adjustments_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `stock_adjustments_created_by_foreign` (`created_by`),
  KEY `stock_adjustments_approved_by_foreign` (`approved_by`),
  KEY `stock_adjustments_branch_id_status_index` (`branch_id`,`status`),
  KEY `stock_adjustments_cancelled_by_foreign` (`cancelled_by`),
  KEY `stock_adjustments_reversal_journal_entry_id_foreign` (`reversal_journal_entry_id`),
  CONSTRAINT `stock_adjustments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_adjustments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_adjustments_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_adjustments_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_adjustments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_adjustments_reversal_journal_entry_id_foreign` FOREIGN KEY (`reversal_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_adjustments_stock_reason_id_foreign` FOREIGN KEY (`stock_reason_id`) REFERENCES `stock_reasons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_ledger`
--

DROP TABLE IF EXISTS `stock_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('pembelian','penjualan','retur_pembelian','retur_penjualan','koreksi_plus','koreksi_minus','saldo_awal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `qty_in` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `qty_out` decimal(18,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(18,4) NOT NULL,
  `running_qty` decimal(18,4) NOT NULL,
  `running_avg_cost` decimal(18,4) NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_ledger_branch_id_foreign` (`branch_id`),
  KEY `stock_ledger_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `stock_ledger_created_by_foreign` (`created_by`),
  KEY `stock_ledger_product_branch_idx` (`product_id`,`branch_id`,`id`),
  CONSTRAINT `stock_ledger_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_ledger_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `stock_ledger_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_ledger`
--

LOCK TABLES `stock_ledger` WRITE;
/*!40000 ALTER TABLE `stock_ledger` DISABLE KEYS */;
INSERT INTO `stock_ledger` VALUES (1,1,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',1,50.0000,0.0000,62000.0000,50.0000,62000.0000,5,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(2,2,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',1,80.0000,0.0000,15500.0000,80.0000,15500.0000,5,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(3,6,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',1,30.0000,0.0000,95000.0000,30.0000,95000.0000,5,'2026-07-18 13:11:19','2026-07-18 13:11:19'),(4,3,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',2,40.0000,0.0000,13500.0000,40.0000,13500.0000,5,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(5,4,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',2,20.0000,0.0000,24000.0000,20.0000,24000.0000,5,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(6,7,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',2,60.0000,0.0000,3200.0000,60.0000,3200.0000,5,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(7,9,1,'2026-07-18','pembelian','App\\Models\\PurchaseTransaction',2,25.0000,0.0000,10500.0000,25.0000,10500.0000,5,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(8,3,1,'2026-07-18','penjualan','App\\Models\\PosSale',1,0.0000,5.0000,13500.0000,35.0000,13500.0000,4,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(9,7,1,'2026-07-18','penjualan','App\\Models\\PosSale',1,0.0000,3.0000,3200.0000,57.0000,3200.0000,4,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(10,4,1,'2026-07-18','penjualan','App\\Models\\PosSale',2,0.0000,2.0000,24000.0000,18.0000,24000.0000,4,'2026-07-18 13:11:20','2026-07-18 13:11:20'),(11,9,1,'2026-07-18','penjualan','App\\Models\\PosSale',2,0.0000,4.0000,10500.0000,21.0000,10500.0000,4,'2026-07-18 13:11:20','2026-07-18 13:11:20');
/*!40000 ALTER TABLE `stock_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_reasons`
--

DROP TABLE IF EXISTS `stock_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_reasons_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_reasons`
--

LOCK TABLES `stock_reasons` WRITE;
/*!40000 ALTER TABLE `stock_reasons` DISABLE KEYS */;
INSERT INTO `stock_reasons` VALUES (1,'Rusak',1,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(2,'Hilang',1,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(3,'Kadaluarsa',1,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(4,'Kesalahan Input',1,'2026-07-18 13:09:48','2026-07-18 13:09:48'),(5,'Retur ke Supplier',1,'2026-07-18 13:09:48','2026-07-18 13:09:48');
/*!40000 ALTER TABLE `stock_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_term` enum('tunai','kredit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tunai',
  `payment_term_days` smallint unsigned DEFAULT NULL,
  `coa_payable_account_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`),
  KEY `suppliers_coa_payable_account_id_foreign` (`coa_payable_account_id`),
  CONSTRAINT `suppliers_coa_payable_account_id_foreign` FOREIGN KEY (`coa_payable_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'SUP-001','CV Sumber Rejeki','distributor','Darimin Prabowo','6281911102787','Ds. Kalimalang No. 781, Tangerang Selatan 29883, Sulsel','kredit',30,29,1,'2026-07-18 13:11:16','2026-07-18 13:11:16'),(2,'SUP-002','PT Distribusi Sembako Jaya','distributor','Hendri Harto Kurniawan','6284124948809','Dk. Pintu Besar Selatan No. 815, Kotamobagu 99052, Sulut','tunai',NULL,29,1,'2026-07-18 13:11:17','2026-07-18 13:11:17');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teller_cash_transactions`
--

DROP TABLE IF EXISTS `teller_cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teller_cash_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint unsigned NOT NULL,
  `cash_category_id` bigint unsigned NOT NULL,
  `cash_account_id` bigint unsigned NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teller_cash_transactions_cash_category_id_foreign` (`cash_category_id`),
  KEY `teller_cash_transactions_cash_account_id_foreign` (`cash_account_id`),
  KEY `teller_cash_transactions_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `teller_cash_transactions_created_by_foreign` (`created_by`),
  KEY `teller_cash_transactions_branch_id_created_at_index` (`branch_id`,`created_at`),
  CONSTRAINT `teller_cash_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `teller_cash_transactions_cash_account_id_foreign` FOREIGN KEY (`cash_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `teller_cash_transactions_cash_category_id_foreign` FOREIGN KEY (`cash_category_id`) REFERENCES `cash_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `teller_cash_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `teller_cash_transactions_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teller_cash_transactions`
--

LOCK TABLES `teller_cash_transactions` WRITE;
/*!40000 ALTER TABLE `teller_cash_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `teller_cash_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_branch_scope`
--

DROP TABLE IF EXISTS `user_branch_scope`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_branch_scope` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `scope_type` enum('single','multiple','all') COLLATE utf8mb4_unicode_ci NOT NULL,
  `single_branch_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_branch_scope_user_id_unique` (`user_id`),
  KEY `user_branch_scope_single_branch_id_foreign` (`single_branch_id`),
  CONSTRAINT `user_branch_scope_single_branch_id_foreign` FOREIGN KEY (`single_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_branch_scope_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_branch_scope`
--

LOCK TABLES `user_branch_scope` WRITE;
/*!40000 ALTER TABLE `user_branch_scope` DISABLE KEYS */;
INSERT INTO `user_branch_scope` VALUES (1,1,'all',NULL,'2026-07-18 13:10:57','2026-07-18 13:10:57'),(2,2,'all',NULL,'2026-07-18 13:11:06','2026-07-18 13:11:06'),(3,3,'all',NULL,'2026-07-18 13:11:07','2026-07-18 13:11:07'),(4,4,'all',NULL,'2026-07-18 13:11:08','2026-07-18 13:11:08'),(5,5,'all',NULL,'2026-07-18 13:11:09','2026-07-18 13:11:09'),(6,6,'all',NULL,'2026-07-18 13:11:10','2026-07-18 13:11:10'),(7,7,'all',NULL,'2026-07-18 13:11:10','2026-07-18 13:11:10'),(8,8,'all',NULL,'2026-07-18 13:11:11','2026-07-18 13:11:11');
/*!40000 ALTER TABLE `user_branch_scope` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_branch_scope_branch`
--

DROP TABLE IF EXISTS `user_branch_scope_branch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_branch_scope_branch` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_branch_scope_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_branch_scope_branch_user_branch_scope_id_branch_id_unique` (`user_branch_scope_id`,`branch_id`),
  KEY `user_branch_scope_branch_branch_id_foreign` (`branch_id`),
  CONSTRAINT `user_branch_scope_branch_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_branch_scope_branch_user_branch_scope_id_foreign` FOREIGN KEY (`user_branch_scope_id`) REFERENCES `user_branch_scope` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_branch_scope_branch`
--

LOCK TABLES `user_branch_scope_branch` WRITE;
/*!40000 ALTER TABLE `user_branch_scope_branch` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_branch_scope_branch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin Test','admin@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$S2VTNjl1Y3FxbGw2ZC93Tw$T2jqor0S68ZQCd/sYgdDuLfv57gn8/imaTWoN30+Nko',1,NULL,NULL,'2026-07-18 13:15:15',NULL,'2026-07-18 13:10:56','2026-07-18 17:12:46'),(2,'Test Admin Sistem','admin.sistem@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$LjZyVXowZ1NINVBOdkl4Mw$6BaoVQYqpulyOVs/whJSHRD0/9ctLZurUkouIPPyiCw',1,NULL,NULL,'2026-07-18 13:11:06',NULL,'2026-07-18 13:11:06','2026-07-18 13:11:06'),(3,'Test Manajer','manajer@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$SDZzcnI0RUo0Z25PRGJ3Vg$aI63lYDbNFO/cYR/Wu32l+cjF7nn7CYORpjkB0gMyN8',1,NULL,NULL,'2026-07-18 13:11:07',NULL,'2026-07-18 13:11:07','2026-07-18 13:11:07'),(4,'Test Teller','teller@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$UmhCYUFYYmR0OExzdmZqbw$kpSQKfnx1INL8cRtdeKOA2mcbsmFXe9PRQw9l2tzep8',1,NULL,NULL,'2026-07-18 13:11:07',NULL,'2026-07-18 13:11:08','2026-07-18 17:34:10'),(5,'Test Petugas Kredit','kredit@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$WGhWS1VGQ1dpd0t6Q2twdw$+kO6wCeoUThtDAMiGyva+DxQWMnFAqTZ31SBo5zfTyQ',1,NULL,NULL,'2026-07-18 13:11:08',NULL,'2026-07-18 13:11:08','2026-07-18 13:11:08'),(6,'Test Petugas UPF','upf@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$NDhKL2daNWc4QUNTS0Raeg$h/oA40uF2gubNj+AF6EY+7ZRj+EdQg0/FzYmSQTwnnA',1,NULL,NULL,'2026-07-18 13:11:09',NULL,'2026-07-18 13:11:09','2026-07-18 13:11:09'),(7,'Test Bendahara','bendahara@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$ZU5qbzdyOEttWUJkVkRpdw$Q0MUK+HDg71iFqQ6/C/xBNe2tIiDhFXDLOQiotuCzxU',1,NULL,NULL,'2026-07-18 13:11:10',NULL,'2026-07-18 13:11:10','2026-07-18 13:11:10'),(8,'Test Pengawas','pengawas@ksp.test',NULL,'$argon2id$v=19$m=65536,t=4,p=1$MzBMSklWekI1MC4wNEljVw$ShXC3lMuB00volQI7HN96adV1ejxAjiArmrcQDf556c',1,NULL,NULL,'2026-07-18 13:11:10',NULL,'2026-07-18 13:11:11','2026-07-18 13:11:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'koperasi_sejahtera_bersama'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-19  8:19:04
