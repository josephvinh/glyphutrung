-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: tntt_realfix
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `logged_at` datetime NOT NULL DEFAULT current_timestamp(),
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(128) NOT NULL,
  `action` varchar(24) NOT NULL,
  `module` varchar(32) NOT NULL,
  `what` varchar(255) NOT NULL,
  `detail` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_time` (`logged_at`),
  KEY `idx_log_actor` (`actor_id`),
  CONSTRAINT `fk_log_actor` FOREIGN KEY (`actor_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (125,'2026-09-09 10:07:24',1,'Tường Ngọc Vinh','xoa','settings','Xóa toàn bộ nhật ký thao tác',''),(126,'2026-09-09 12:24:58',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(127,'2026-09-09 14:16:27',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(128,'2026-09-09 16:59:03',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(129,'2026-09-09 18:44:57',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(130,'2026-09-09 20:39:12',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(131,'2026-09-10 08:51:50',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(132,'2026-09-10 21:10:23',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(133,'2026-09-10 21:13:07',1,'Tường Ngọc Vinh','xoa','auth','Đăng xuất',''),(134,'2026-09-10 21:23:57',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(135,'2026-09-10 21:24:40',1,'Tường Ngọc Vinh','xoa','auth','Đăng xuất',''),(136,'2026-09-10 21:26:29',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(137,'2026-09-10 21:28:04',1,'Tường Ngọc Vinh','xoa','auth','Đăng xuất',''),(138,'2026-09-10 21:28:12',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(139,'2026-09-10 21:34:20',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(140,'2026-09-10 22:03:02',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(141,'2026-09-11 06:15:38',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(142,'2026-09-11 09:29:35',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(143,'2026-09-11 10:42:58',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(144,'2026-09-11 16:56:31',3,'Tường Ngọc Bích Nguyệt','tao','auth','Đăng nhập hệ thống','0902397804'),(145,'2026-09-11 17:25:56',3,'Tường Ngọc Bích Nguyệt','tao','students','Nhập danh sách từ file','thêm 38, cập nhật 0, bỏ qua 0'),(146,'2026-09-11 17:47:05',3,'Tường Ngọc Bích Nguyệt','tao','auth','Đăng nhập hệ thống','0902397804'),(147,'2026-09-11 17:48:51',3,'Tường Ngọc Bích Nguyệt','diemdanh','attendance','Quét QR 1 em','Thánh Lễ Thiếu Nhi · 2026-09-06 · đi trễ'),(148,'2026-09-11 17:49:19',3,'Tường Ngọc Bích Nguyệt','diemdanh','attendance','Ghi điểm danh cho NGUYỄN PHI PHÚC HƯNG','Thánh Lễ Thiếu Nhi · 2026-09-06 · đi trễ'),(149,'2026-09-11 17:49:23',3,'Tường Ngọc Bích Nguyệt','diemdanh','attendance','Gỡ điểm danh của NGUYỄN PHI PHÚC HƯNG','Thánh Lễ Thiếu Nhi · 2026-09-06 · đang là đi trễ'),(150,'2026-09-11 17:49:42',3,'Tường Ngọc Bích Nguyệt','diemdanh','attendance','Ghi điểm danh cho NGUYỄN PHI PHÚC HƯNG','Thánh Lễ Thiếu Nhi · 2026-09-06 · đi trễ'),(151,'2026-09-11 19:25:49',3,'Tường Ngọc Bích Nguyệt','tao','auth','Đăng nhập hệ thống','0902397804'),(152,'2026-09-11 19:27:19',3,'Tường Ngọc Bích Nguyệt','tao','leave','Nộp đơn xin phép cho NGUYỄN CÔNG MINH PHÚC','Học Giáo Lý Sáng · 2026-09-13'),(153,'2026-09-11 19:31:47',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(154,'2026-09-11 19:32:39',1,'Tường Ngọc Vinh','sua','org','Phân công chủ nhiệm lớp','Tường Ngọc Bích Nguyệt'),(155,'2026-09-11 19:34:17',1,'Tường Ngọc Vinh','xoa','announcements','Xóa thông báo \"Chào mừng năm học mới 2026 - 2027\"',''),(156,'2026-09-11 19:37:02',3,'Tường Ngọc Bích Nguyệt','duyet','leave','Duyệt đơn phép của NGUYỄN CÔNG MINH PHÚC','2026-09-13 · Gia đình có việc'),(157,'2026-09-11 19:38:34',1,'Tường Ngọc Vinh','xoa','auth','Đăng xuất',''),(158,'2026-09-11 19:38:37',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(159,'2026-09-11 19:38:59',3,'Tường Ngọc Bích Nguyệt','xoa','auth','Đăng xuất',''),(160,'2026-09-11 19:39:02',3,'Tường Ngọc Bích Nguyệt','tao','auth','Đăng nhập hệ thống','0902397804'),(161,'2026-09-11 19:50:49',3,'Tường Ngọc Bích Nguyệt','tao','announcements','Phát thông báo \"Họp khối\"','khối'),(162,'2026-09-11 20:04:41',1,'Tường Ngọc Vinh','sua','announcements','Sửa buổi họp \"Họp khối\"','đã phát'),(163,'2026-09-11 20:05:19',1,'Tường Ngọc Vinh','sua','announcements','Thu hồi thông báo \"Họp khối\"','về bản nháp'),(164,'2026-09-11 20:05:21',1,'Tường Ngọc Vinh','tao','announcements','Phát thông báo \"Họp khối\"','khối'),(165,'2026-09-12 02:05:44',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(166,'2026-09-12 02:07:19',1,'Tường Ngọc Vinh','duyet','org','Duyệt tài khoản Đinh Lê Quý','glv · Khai Tâm 1A'),(167,'2026-09-12 07:22:37',412,'Đinh Lê Quý','tao','auth','Đăng nhập hệ thống','0879845901'),(168,'2026-09-12 07:25:00',412,'Đinh Lê Quý','xoa','auth','Đăng xuất',''),(169,'2026-09-12 07:46:35',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(170,'2026-09-12 07:54:40',412,'Đinh Lê Quý','tao','auth','Đăng nhập hệ thống','0879845901'),(171,'2026-09-12 08:01:39',412,'Đinh Lê Quý','tao','auth','Đăng nhập hệ thống','0879845901'),(172,'2026-09-12 08:06:54',412,'Đinh Lê Quý','tao','auth','Đăng nhập hệ thống','0879845901'),(173,'2026-09-12 09:31:13',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(174,'2026-09-12 16:15:09',3,'Tường Ngọc Bích Nguyệt','tao','auth','Đăng nhập hệ thống','0902397804'),(175,'2026-09-12 16:15:28',3,'Tường Ngọc Bích Nguyệt','sua','students','Sửa hồ sơ ĐẶNG PHƯƠNG CHI','KT2B26001 · Khai Tâm 2B'),(176,'2026-09-12 16:17:41',3,'Tường Ngọc Bích Nguyệt','sua','students','Sửa hồ sơ HOÀNG NGUYÊN BẢO','GDGLPT260006 · Khai Tâm 2B'),(177,'2026-09-12 20:11:50',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(178,'2026-09-12 21:00:08',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(179,'2026-09-12 21:00:37',1,'Tường Ngọc Vinh','sua','org','Phân công chủ nhiệm lớp','Đinh Lê Quý'),(180,'2026-09-12 21:01:24',1,'Tường Ngọc Vinh','xoa','announcements','Xóa thông báo \"Họp khối\"',''),(181,'2026-09-12 21:13:19',412,'Đinh Lê Quý','tao','auth','Đăng nhập hệ thống','0879845901'),(182,'2026-09-12 23:34:47',412,'Đinh Lê Quý','tao','auth','Đăng nhập hệ thống','0879845901'),(183,'2026-09-13 09:37:52',3,'Tường Ngọc Bích Nguyệt','tao','auth','Đăng nhập hệ thống','0902397804'),(184,'2026-09-13 11:05:57',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(185,'2026-09-13 13:08:57',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(186,'2026-09-13 15:47:38',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(187,'2026-09-13 15:57:34',1,'Tường Ngọc Vinh','sua','org','Sửa thành viên Đinh Lê Quý','glv'),(188,'2026-09-13 18:52:49',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(189,'2026-09-14 00:43:51',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508'),(190,'2026-09-14 00:48:30',1,'Tường Ngọc Vinh','xoa','auth','Đăng xuất',''),(191,'2026-09-14 00:59:57',1,'Tường Ngọc Vinh','tao','auth','Đăng nhập hệ thống','0937867508');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement_reads`
--

DROP TABLE IF EXISTS `announcement_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement_reads` (
  `member_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`,`announcement_id`),
  KEY `fk_ar_ann` (`announcement_id`),
  CONSTRAINT `fk_ar_ann` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_reads`
--

LOCK TABLES `announcement_reads` WRITE;
/*!40000 ALTER TABLE `announcement_reads` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcement_reads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `level` enum('thường','quan trọng','khẩn') NOT NULL DEFAULT 'thường',
  `audience_type` enum('toàn đoàn','khối','lớp') NOT NULL DEFAULT 'toàn đoàn',
  `audience_block` int(11) DEFAULT NULL,
  `audience_class` int(11) DEFAULT NULL,
  `status` enum('nháp','đã phát') NOT NULL DEFAULT 'nháp',
  `published_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_meeting` tinyint(4) NOT NULL DEFAULT 0,
  `meeting_at` datetime DEFAULT NULL,
  `meeting_place` varchar(255) DEFAULT NULL,
  `reminded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_an_block` (`audience_block`),
  KEY `fk_an_class` (`audience_class`),
  KEY `fk_an_by` (`created_by`),
  KEY `idx_an_live` (`year_id`,`status`,`expires_at`),
  CONSTRAINT `fk_an_block` FOREIGN KEY (`audience_block`) REFERENCES `blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_an_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_an_class` FOREIGN KEY (`audience_class`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_an_year` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('có mặt','đi trễ') NOT NULL DEFAULT 'có mặt',
  `method` enum('tay','qr') NOT NULL DEFAULT 'tay',
  `marked_by` int(11) DEFAULT NULL,
  `marked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att` (`program_id`,`session_date`,`student_id`) COMMENT 'chống quét trùng ở tầng CSDL',
  KEY `fk_att_by` (`marked_by`),
  KEY `idx_att_lookup` (`year_id`,`session_date`),
  KEY `idx_att_student` (`student_id`,`year_id`),
  KEY `idx_att_student_date` (`student_id`,`session_date`),
  KEY `idx_att_program_date` (`program_id`,`session_date`),
  CONSTRAINT `fk_att_by` FOREIGN KEY (`marked_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_att_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_year` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES (6,1,1,'2026-09-06',6,'đi trễ','qr',3,'2026-09-11 17:48:51'),(8,1,1,'2026-09-06',9,'đi trễ','tay',3,'2026-09-11 17:49:42');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blocks`
--

DROP TABLE IF EXISTS `blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blocks`
--

LOCK TABLES `blocks` WRITE;
/*!40000 ALTER TABLE `blocks` DISABLE KEYS */;
INSERT INTO `blocks` VALUES (1,'Khai Tâm',1),(2,'Rước Lễ',2),(3,'Thêm Sức',3),(4,'Bao Đồng',4);
/*!40000 ALTER TABLE `blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `block_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 1,
  `next_class_id` int(11) DEFAULT NULL COMMENT 'lớp kế tiếp khi lên lớp',
  `is_final` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = lớp cuối, lên lớp là ra trường',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_class_block` (`block_id`),
  KEY `fk_class_next` (`next_class_id`),
  CONSTRAINT `fk_class_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`),
  CONSTRAINT `fk_class_next` FOREIGN KEY (`next_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,1,'Khai Tâm 1A',1,3,0),(2,1,'Khai Tâm 1B',2,3,0),(3,1,'Khai Tâm 2A',3,4,0),(4,2,'Rước Lễ 1A',1,5,0),(5,2,'Rước Lễ 1B',2,6,0),(6,3,'Thêm Sức 1',1,7,0),(7,3,'Thêm Sức 2',2,8,0),(8,4,'Bao Đồng 1',1,NULL,1),(17,1,'Tiền Khai Tâm',1,NULL,0),(18,2,'Rước 2A',1,NULL,0),(19,1,'Khai Tâm 2B',1,NULL,0);
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `status` enum('đang sinh hoạt','dừng sinh hoạt','chuyển xứ','đã ra trường') NOT NULL DEFAULT 'đang sinh hoạt',
  `year_result` enum('chưa xét','lên lớp','ở lại','ra trường') NOT NULL DEFAULT 'chưa xét',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enr` (`year_id`,`student_id`) COMMENT 'một năm một em chỉ ở một lớp',
  KEY `fk_enr_student` (`student_id`),
  KEY `fk_enr_class` (`class_id`),
  KEY `idx_enr_class` (`year_id`,`class_id`),
  KEY `idx_enr_year_status` (`year_id`,`status`),
  CONSTRAINT `fk_enr_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `fk_enr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enr_year` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (6,1,6,19,'đang sinh hoạt','chưa xét',NULL),(7,1,7,19,'chuyển xứ','chưa xét',NULL),(8,1,8,19,'đang sinh hoạt','chưa xét',NULL),(9,1,9,19,'đang sinh hoạt','chưa xét',NULL),(10,1,10,19,'đang sinh hoạt','chưa xét',NULL),(11,1,11,19,'đang sinh hoạt','chưa xét',NULL),(12,1,12,19,'đang sinh hoạt','chưa xét',NULL),(13,1,13,19,'đang sinh hoạt','chưa xét',NULL),(14,1,14,19,'đang sinh hoạt','chưa xét',NULL),(15,1,15,19,'đang sinh hoạt','chưa xét',NULL),(16,1,16,19,'đang sinh hoạt','chưa xét',NULL),(17,1,17,19,'đang sinh hoạt','chưa xét',NULL),(18,1,18,19,'đang sinh hoạt','chưa xét',NULL),(19,1,19,19,'đang sinh hoạt','chưa xét',NULL),(20,1,20,19,'chuyển xứ','chưa xét',NULL),(21,1,21,19,'đang sinh hoạt','chưa xét',NULL),(22,1,22,19,'đang sinh hoạt','chưa xét',NULL),(23,1,23,19,'đang sinh hoạt','chưa xét',NULL),(24,1,24,19,'đang sinh hoạt','chưa xét',NULL),(25,1,25,19,'đang sinh hoạt','chưa xét',NULL),(26,1,26,19,'đang sinh hoạt','chưa xét',NULL),(27,1,27,19,'đang sinh hoạt','chưa xét',NULL),(28,1,28,19,'chuyển xứ','chưa xét',NULL),(29,1,29,19,'đang sinh hoạt','chưa xét',NULL),(30,1,30,19,'đang sinh hoạt','chưa xét',NULL),(31,1,31,19,'đang sinh hoạt','chưa xét',NULL),(32,1,32,19,'đang sinh hoạt','chưa xét',NULL),(33,1,33,19,'đang sinh hoạt','chưa xét',NULL),(34,1,34,19,'đang sinh hoạt','chưa xét',NULL),(35,1,35,19,'đang sinh hoạt','chưa xét',NULL),(36,1,36,19,'đang sinh hoạt','chưa xét',NULL),(37,1,37,19,'đang sinh hoạt','chưa xét',NULL),(38,1,38,19,'đang sinh hoạt','chưa xét',NULL),(39,1,39,19,'đang sinh hoạt','chưa xét',NULL),(40,1,40,19,'đang sinh hoạt','chưa xét',NULL),(41,1,41,19,'đang sinh hoạt','chưa xét',NULL),(42,1,42,19,'đang sinh hoạt','chưa xét',NULL),(43,1,43,19,'đang sinh hoạt','chưa xét',NULL),(45,1,44,19,'đang sinh hoạt','chưa xét',NULL);
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `reason` varchar(500) NOT NULL,
  `status` enum('chờ duyệt','đã duyệt','từ chối') NOT NULL DEFAULT 'chờ duyệt',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reject_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lv` (`program_id`,`session_date`,`student_id`) COMMENT 'một buổi một em một đơn',
  KEY `fk_lv_student` (`student_id`),
  KEY `fk_lv_cby` (`created_by`),
  KEY `fk_lv_aby` (`approved_by`),
  KEY `idx_lv_status` (`year_id`,`status`),
  KEY `idx_lv_status_date` (`status`,`session_date`),
  KEY `idx_lv_student` (`student_id`),
  CONSTRAINT `fk_lv_aby` FOREIGN KEY (`approved_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lv_cby` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lv_prog` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lv_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lv_year` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,1,16,2,'2026-09-13','Gia đình có việc','đã duyệt',3,'2026-09-11 19:27:19',3,'2026-09-11 19:37:02',NULL);
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `ip` varchar(45) NOT NULL COMMENT 'đủ chỗ cho IPv6',
  `tried_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`,`tried_at`),
  KEY `idx_ip` (`ip`,`tried_at`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meeting_rsvp`
--

DROP TABLE IF EXISTS `meeting_rsvp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meeting_rsvp` (
  `announcement_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `status` enum('tham gia','không tham gia') NOT NULL,
  `responded_at` datetime NOT NULL,
  PRIMARY KEY (`announcement_id`,`member_id`),
  KEY `fk_rsvp_member` (`member_id`),
  CONSTRAINT `fk_rsvp_ann` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsvp_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meeting_rsvp`
--

LOCK TABLES `meeting_rsvp` WRITE;
/*!40000 ALTER TABLE `meeting_rsvp` DISABLE KEYS */;
/*!40000 ALTER TABLE `meeting_rsvp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_assignments`
--

DROP TABLE IF EXISTS `member_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `role_code` varchar(24) NOT NULL,
  `block_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'phân công chính = vai trò mặc định khi đăng nhập',
  `from_date` date NOT NULL,
  `to_date` date DEFAULT NULL COMMENT 'null = đang hiệu lực',
  `assigned_by` int(11) NOT NULL COMMENT 'BĐH phân công',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_assign_by` (`assigned_by`),
  KEY `idx_assign_member` (`member_id`,`to_date`),
  KEY `idx_assign_class` (`class_id`,`to_date`),
  KEY `idx_assign_block` (`block_id`,`to_date`),
  KEY `idx_assign_role` (`role_code`,`to_date`),
  CONSTRAINT `fk_assign_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assign_by` FOREIGN KEY (`assigned_by`) REFERENCES `members` (`id`),
  CONSTRAINT `fk_assign_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assign_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_role` FOREIGN KEY (`role_code`) REFERENCES `roles` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=630 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_assignments`
--

LOCK TABLES `member_assignments` WRITE;
/*!40000 ALTER TABLE `member_assignments` DISABLE KEYS */;
INSERT INTO `member_assignments` VALUES (1,1,'admin',NULL,NULL,1,'2026-09-01',NULL,1,NULL,'2026-09-01 00:06:30'),(2,3,'truong_khoi',1,NULL,1,'2026-09-01',NULL,1,NULL,'2026-09-01 00:06:30'),(163,3,'glv',NULL,19,0,'2026-09-05','2026-09-05',1,'','2026-09-05 10:38:27'),(437,3,'glv_chu_nhiem',1,1,0,'2026-09-06','2026-09-06',1,'Phân công chủ nhiệm lớp','2026-09-06 12:00:31'),(438,1,'glv',NULL,1,0,'2026-09-06','2026-09-06',1,'','2026-09-06 12:06:01'),(627,3,'glv_chu_nhiem',1,19,0,'2026-09-11',NULL,1,'Phân công chủ nhiệm lớp','2026-09-11 19:32:39'),(628,412,'glv',NULL,1,1,'2026-09-12','2026-09-12',1,'','2026-09-12 07:47:48'),(629,412,'glv_chu_nhiem',2,4,0,'2026-09-12',NULL,1,'Phân công chủ nhiệm lớp','2026-09-12 21:00:37');
/*!40000 ALTER TABLE `member_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_passkeys`
--

DROP TABLE IF EXISTS `member_passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_passkeys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `public_key` text NOT NULL,
  `user_handle` varchar(255) NOT NULL,
  `sign_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credential` (`credential_id`),
  KEY `idx_pk_member` (`member_id`),
  CONSTRAINT `fk_pk_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_passkeys`
--

LOCK TABLES `member_passkeys` WRITE;
/*!40000 ALTER TABLE `member_passkeys` DISABLE KEYS */;
/*!40000 ALTER TABLE `member_passkeys` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT 'mã GLV',
  `holy_name` varchar(64) DEFAULT NULL,
  `full_name` varchar(128) NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT 'dùng để đăng nhập',
  `email` varchar(128) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_code` varchar(24) NOT NULL,
  `title_id` int(11) DEFAULT NULL,
  `block_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `status` enum('chờ duyệt','đang phục vụ','tạm nghỉ','đã nghỉ') NOT NULL DEFAULT 'đang phục vụ',
  `register_note` varchar(255) DEFAULT NULL COMMENT 'lời nhắn khi tự đăng ký, để Ban Điều Hành biết xếp lớp',
  `registered_at` datetime DEFAULT NULL COMMENT 'thời điểm tự đăng ký, null nghĩa là do BĐH cấp',
  `must_change_pw` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `phone` (`phone`),
  KEY `fk_member_title` (`title_id`),
  KEY `fk_member_block` (`block_id`),
  KEY `idx_member_role` (`role_code`),
  KEY `idx_member_class` (`class_id`),
  KEY `idx_member_phone` (`phone`),
  CONSTRAINT `fk_member_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_member_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_member_role` FOREIGN KEY (`role_code`) REFERENCES `roles` (`code`),
  CONSTRAINT `fk_member_title` FOREIGN KEY (`title_id`) REFERENCES `titles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=413 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,'GLV001','Giuse','Tường Ngọc Vinh','0937867508',NULL,'1992-12-05','$argon2id$v=19$m=65536,t=4,p=3$dFBkakk3ZWx0SjZLRDQzZQ$6jqycT3qkE8nY8yZOh9XE6PxGwQPJbfD9LybP0FWX1w','admin',1,NULL,NULL,'đang phục vụ',NULL,NULL,0,'2026-09-14 00:59:57','2026-08-26 00:03:29'),(3,'GLV002','Maria','Tường Ngọc Bích Nguyệt','0902397804',NULL,'2002-08-25','$argon2id$v=19$m=65536,t=4,p=3$TlFuMzVGQ3FRV28zdU4vQQ$rgkp5yfxoOtIT9pASRSjkIpHJ9kmtnHTZ/anLxm7WcY','truong_khoi',7,1,NULL,'đang phục vụ',NULL,'2026-08-30 15:10:28',0,'2026-09-13 09:37:52','2026-08-30 15:10:28'),(412,'GLV003','Giuse','Đinh Lê Quý','0879845901',NULL,'2004-09-29','$argon2id$v=19$m=65536,t=4,p=3$ZkhhR2RmNnhDc2hmUEVyRw$qo47xx0QlU2WGTIamS9X20o8Khc8AvCtahW7lfa5uCo','glv',10,1,1,'đang phục vụ',NULL,'2026-09-11 22:12:23',0,'2026-09-12 23:34:47','2026-09-11 22:12:23');
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `module_key` varchar(32) NOT NULL,
  `label` varchar(64) NOT NULL,
  `icon` varchar(48) NOT NULL,
  `color` varchar(48) NOT NULL DEFAULT 'text-blue-600',
  `area` enum('glv','bdh') NOT NULL DEFAULT 'glv',
  `sort_order` tinyint(4) NOT NULL DEFAULT 1,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = đang bảo trì',
  PRIMARY KEY (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES ('analytics','Phân tích','bar-chart-2','text-purple-600','glv',6,1),('announcements','Thông báo','megaphone','text-rose-500','bdh',3,1),('attendance','Điểm danh','clipboard-check','text-blue-600','glv',2,1),('birthdays','Sinh nhật','cake','text-rose-500','glv',4,1),('calendar','Lịch trình','calendar-days','text-teal-600','bdh',4,1),('guide','Hướng dẫn','info','text-sky-600','glv',12,1),('leave','Xin phép','file-text','text-blue-600','glv',3,1),('notes','Lịch của tôi','calendar-check','text-teal-600','glv',11,1),('org','Khối lớp','layers','text-indigo-600','glv',6,1),('programs','Chương trình','calendar-plus','text-amber-600','bdh',2,1),('promotion','Lên lớp','trending-up','text-violet-600','bdh',1,1),('reporthub','Báo cáo','bar-chart-3','text-emerald-600','glv',5,1),('reports','Sổ liên lạc','clipboard-list','text-amber-600','glv',8,1),('scores','Điểm số','graduation-cap','text-violet-600','glv',9,1),('staff','Nhân sự','user-cog','text-cyan-600','bdh',7,1),('stats','Thống kê','bar-chart-3','text-emerald-600','glv',5,1),('students','Danh sách','users','text-blue-600','glv',1,1),('years','Niên khoá','calendar-range','text-indigo-600','bdh',8,1);
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `module_key` varchar(32) NOT NULL,
  `role_code` varchar(24) NOT NULL,
  `level` enum('none','view','edit') NOT NULL DEFAULT 'none',
  PRIMARY KEY (`module_key`,`role_code`),
  KEY `fk_pm_role` (`role_code`),
  CONSTRAINT `fk_pm_module` FOREIGN KEY (`module_key`) REFERENCES `modules` (`module_key`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_role` FOREIGN KEY (`role_code`) REFERENCES `roles` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES ('analytics','admin','view'),('analytics','bdh','view'),('analytics','du_bi','view'),('analytics','glv','view'),('analytics','glv_chu_nhiem','view'),('analytics','truong_khoi','view'),('announcements','admin','edit'),('announcements','bdh','edit'),('announcements','du_bi','view'),('announcements','glv','view'),('announcements','glv_chu_nhiem','edit'),('announcements','truong_khoi','edit'),('attendance','admin','edit'),('attendance','bdh','edit'),('attendance','du_bi','edit'),('attendance','glv','edit'),('attendance','glv_chu_nhiem','edit'),('attendance','truong_khoi','edit'),('birthdays','admin','view'),('birthdays','bdh','view'),('birthdays','du_bi','view'),('birthdays','glv','view'),('birthdays','glv_chu_nhiem','view'),('birthdays','truong_khoi','view'),('calendar','admin','view'),('calendar','bdh','view'),('calendar','du_bi','view'),('calendar','glv','view'),('calendar','glv_chu_nhiem','view'),('calendar','truong_khoi','view'),('guide','admin','view'),('guide','bdh','view'),('guide','du_bi','view'),('guide','glv','view'),('guide','glv_chu_nhiem','view'),('guide','truong_khoi','view'),('leave','admin','edit'),('leave','bdh','edit'),('leave','du_bi','view'),('leave','glv','view'),('leave','glv_chu_nhiem','edit'),('leave','truong_khoi','edit'),('notes','admin','edit'),('notes','bdh','edit'),('notes','du_bi','view'),('notes','glv','edit'),('notes','glv_chu_nhiem','edit'),('notes','truong_khoi','edit'),('org','admin','edit'),('org','bdh','edit'),('org','du_bi','view'),('org','glv','view'),('org','glv_chu_nhiem','view'),('org','truong_khoi','edit'),('programs','admin','edit'),('programs','bdh','edit'),('programs','du_bi','none'),('programs','glv','none'),('programs','glv_chu_nhiem','none'),('programs','truong_khoi','none'),('promotion','admin','edit'),('promotion','bdh','edit'),('promotion','du_bi','none'),('promotion','glv','none'),('promotion','glv_chu_nhiem','none'),('promotion','truong_khoi','view'),('reporthub','admin','view'),('reporthub','bdh','view'),('reporthub','du_bi','view'),('reporthub','glv','view'),('reporthub','glv_chu_nhiem','view'),('reporthub','truong_khoi','view'),('reports','admin','edit'),('reports','bdh','view'),('reports','du_bi','view'),('reports','glv','view'),('reports','glv_chu_nhiem','edit'),('reports','truong_khoi','edit'),('scores','admin','edit'),('scores','bdh','view'),('scores','du_bi','view'),('scores','glv','edit'),('scores','glv_chu_nhiem','edit'),('scores','truong_khoi','edit'),('staff','admin','edit'),('staff','bdh','edit'),('staff','du_bi','view'),('staff','glv','view'),('staff','glv_chu_nhiem','view'),('staff','truong_khoi','view'),('stats','admin','view'),('stats','bdh','view'),('stats','du_bi','view'),('stats','glv','view'),('stats','glv_chu_nhiem','view'),('stats','truong_khoi','view'),('students','admin','edit'),('students','bdh','edit'),('students','du_bi','view'),('students','glv','view'),('students','glv_chu_nhiem','edit'),('students','truong_khoi','edit'),('years','admin','edit'),('years','bdh','edit'),('years','du_bi','view'),('years','glv','view'),('years','glv_chu_nhiem','view'),('years','truong_khoi','view');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_notes`
--

DROP TABLE IF EXISTS `personal_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `title` varchar(160) NOT NULL,
  `note` text DEFAULT NULL,
  `remind_at` datetime NOT NULL,
  `all_day` tinyint(4) NOT NULL DEFAULT 0,
  `done` tinyint(4) NOT NULL DEFAULT 0,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_note_member` (`member_id`,`remind_at`),
  KEY `idx_note_due` (`done`,`notified_at`,`remind_at`),
  CONSTRAINT `fk_note_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_notes`
--

LOCK TABLES `personal_notes` WRITE;
/*!40000 ALTER TABLE `personal_notes` DISABLE KEYS */;
INSERT INTO `personal_notes` VALUES (1,3,'Tham dự lễ khai giảng','','2026-09-11 19:53:00',0,0,NULL,'2026-09-11 19:48:28','2026-09-11 19:48:28'),(2,3,'Họp chủ nhiệm Khối Khai Tâm','','2026-09-12 20:00:00',0,0,NULL,'2026-09-11 19:49:10','2026-09-11 19:49:10');
/*!40000 ALTER TABLE `personal_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `type` enum('bắt buộc','chiến dịch') NOT NULL DEFAULT 'bắt buộc',
  `status` enum('kích hoạt','đã đóng') NOT NULL DEFAULT 'kích hoạt',
  `count_for_attendance` tinyint(1) NOT NULL DEFAULT 1,
  `start_time` time NOT NULL,
  `cutoff_time` time DEFAULT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL COMMENT '0 Chúa Nhật ... 6 Thứ Bảy',
  `event_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prog_year` (`year_id`,`status`),
  CONSTRAINT `fk_prog_year` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (1,1,'Thánh Lễ Thiếu Nhi','bắt buộc','kích hoạt',1,'07:00:00',NULL,0,NULL),(2,1,'Học Giáo Lý Sáng','bắt buộc','kích hoạt',1,'09:00:00',NULL,0,NULL),(3,1,'Học Giáo Lý Chiều','bắt buộc','kích hoạt',1,'15:00:00',NULL,0,NULL);
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `push_outbox`
--

DROP TABLE IF EXISTS `push_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_outbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `body` varchar(255) NOT NULL,
  `url` varchar(120) NOT NULL DEFAULT '/',
  `tag` varchar(48) NOT NULL DEFAULT 'tntt-chung' COMMENT 'cùng tag thì gộp lại, không dội chuông',
  `created_at` datetime NOT NULL,
  `taken_at` datetime DEFAULT NULL COMMENT 'lúc máy người nhận đã lấy về hiện',
  PRIMARY KEY (`id`),
  KEY `idx_outbox_cho` (`member_id`,`taken_at`,`id`),
  CONSTRAINT `fk_outbox_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `push_outbox`
--

LOCK TABLES `push_outbox` WRITE;
/*!40000 ALTER TABLE `push_outbox` DISABLE KEYS */;
INSERT INTO `push_outbox` VALUES (1,1,'Thử thông báo','Nếu thấy dòng này thì máy của bạn nhận thông báo được rồi.','/','tntt-thu','2026-08-27 06:21:32','2026-08-27 06:21:34'),(2,1,'Thành viên mới chờ duyệt','Nguyễn Trọng Nhân','/#members','tntt-tv-moi','2026-08-27 12:58:07','2026-08-27 12:58:10'),(3,1,'Thành viên mới chờ duyệt','Tường Ngọc Bích Nguyệt','/#members','tntt-tv-moi','2026-08-30 15:10:28','2026-08-30 15:10:31'),(4,1,'Đơn xin phép chờ duyệt','NGUYỄN CÔNG MINH PHÚC — Học Giáo Lý Sáng ngày 2026-09-13','/#leave','tntt-phep','2026-09-11 19:27:19',NULL),(5,3,'Thông báo mới','Họp khối','/#announcements','tntt-tb-2','2026-09-11 20:05:21','2026-09-11 20:05:23'),(6,1,'Thành viên mới chờ duyệt','Đinh Lê Quý — anh Vinh duyệt cho em','/#members','tntt-tv-moi','2026-09-11 22:12:23',NULL);
/*!40000 ALTER TABLE `push_outbox` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `ua` varchar(255) DEFAULT NULL COMMENT 'để người dùng nhận ra máy nào',
  `created_at` datetime NOT NULL,
  `last_ok_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push` (`endpoint`(255)),
  KEY `idx_push_member` (`member_id`),
  CONSTRAINT `fk_push_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `push_subscriptions`
--

LOCK TABLES `push_subscriptions` WRITE;
/*!40000 ALTER TABLE `push_subscriptions` DISABLE KEYS */;
INSERT INTO `push_subscriptions` VALUES (1,1,'https://web.push.apple.com/QAbDEPwXzu1_oAUr3TIyqVp5SnUCdkUXPBILRRA0HVgZDS478cvwEyJ4-mfH0g-foA4ryKsaGGdURPFFbS2pu5MzzeNBJ9DIMR5W_PSHhXNLSLZZxzriGa6IVAYD2bNH1_FqZkKsMfV6dBMBL-rrJZiAx2oHo6TmCNVSAQmhyF0','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Mobile/15E148 Safari/604.1','2026-08-27 06:21:14','2026-09-11 22:12:23'),(3,3,'https://web.push.apple.com/QO5imSSlUIDWCkerIe7r1RWWYyhKer9v_HFno5o7IbkHmooWY08edq38xpZdJZE3cRUV5FAVfKenbs6Yf0zu3cbBNscNgWBsuVQZDGAL0o9x8ttXIgpRf0gZWUoWKviUKkFHw_b2B3bJroL3S3dna17ptBgx2gegb_kes_sTXjI','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Mobile/15E148 Safari/604.1','2026-08-30 15:13:16','2026-09-11 20:05:21'),(4,1,'https://fcm.googleapis.com/fcm/send/e0DQGciTSdk:APA91bHqVPQh9_oeQw2GRDj544Smsldz-Ui-4S4QS1M8bwZplGUh0duLgSoSPIUHn8su-eJ_Jqz4FeVBZxA6VwS98jgrM9_nBG2KGuJX1Hh3eS-Q1DyyhGiyhU6X_QpgPd2QLsg4h1-b','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-10 21:10:40','2026-09-11 22:12:23'),(5,1,'https://web.push.apple.com/QDGtE2SD9QkrtDWknAhhxvhuxn_EwJbGnRK3IjAUV0sgzCTYqwMkJXeVyFMH4dbgvxQJcqa7xmru9LIuiPIl_IsKML8o2aiVxRtKybjKfZHNWIn0VqSGrQ6u7-__xiswWtn9lP0Od3HCMQIYJV8p1JvOJ_SN2C7Y41B1Na2xSH0','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Mobile/15E148 Safari/604.1','2026-09-11 09:29:49','2026-09-11 22:12:23');
/*!40000 ALTER TABLE `push_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `att_present` smallint(6) NOT NULL DEFAULT 0,
  `att_late` smallint(6) NOT NULL DEFAULT 0,
  `att_excused` smallint(6) NOT NULL DEFAULT 0,
  `att_unexcused` smallint(6) NOT NULL DEFAULT 0,
  `att_total` smallint(6) NOT NULL DEFAULT 0,
  `att_rate` tinyint(4) NOT NULL DEFAULT 0,
  `score` decimal(4,2) DEFAULT NULL,
  `conduct` enum('tốt','khá','trung bình','cần cố gắng') NOT NULL DEFAULT 'tốt',
  `rank_label` enum('Giỏi','Khá','Trung bình','Yếu') NOT NULL DEFAULT 'Trung bình',
  `remark` varchar(1000) DEFAULT NULL,
  `status` enum('nháp','đã gửi') NOT NULL DEFAULT 'nháp',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report` (`term_id`,`student_id`),
  KEY `fk_rp_student` (`student_id`),
  KEY `fk_rp_by` (`created_by`),
  KEY `idx_rp_student` (`student_id`),
  CONSTRAINT `fk_rp_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `code` varchar(24) NOT NULL,
  `label` varchar(64) NOT NULL,
  `level` tinyint(4) NOT NULL COMMENT '5 cao nhất',
  `scope` enum('toàn đoàn','khối','lớp') NOT NULL,
  `descr` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES ('admin','Quản Trị Hệ Thống',5,'toàn đoàn','Toàn quyền, kể cả cấu hình hệ thống'),('bdh','Ban Điều Hành',4,'toàn đoàn','Quản lý toàn đoàn: khối lớp, nhân sự, chương trình'),('du_bi','Dự Bị',1,'','Hỗ trợ tại lớp được phân công'),('glv','Giáo Lý Viên',1,'lớp','Dạy và điểm danh lớp được phân công'),('glv_chu_nhiem','GLV Chủ Nhiệm',2,'lớp','Phụ trách một lớp, được duyệt đơn của lớp'),('truong_khoi','Trưởng Khối',3,'khối','Quản lý các lớp trong khối mình');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_years`
--

DROP TABLE IF EXISTS `school_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL COMMENT 'VD: 2026 - 2027',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'chỉ một năm được bật',
  `status` enum('đang mở','đã khóa') NOT NULL DEFAULT 'đang mở',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_current` (`is_current`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_years`
--

LOCK TABLES `school_years` WRITE;
/*!40000 ALTER TABLE `school_years` DISABLE KEYS */;
INSERT INTO `school_years` VALUES (1,'2026 - 2027','2026-08-01','2027-05-31',1,'đang mở','2026-08-26 00:03:29');
/*!40000 ALTER TABLE `school_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `score_types`
--

DROP TABLE IF EXISTS `score_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `score_types` (
  `code` varchar(16) NOT NULL,
  `label` varchar(32) NOT NULL,
  `short_label` varchar(8) NOT NULL,
  `weight` tinyint(4) NOT NULL DEFAULT 1,
  `sort_order` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `score_types`
--

LOCK TABLES `score_types` WRITE;
/*!40000 ALTER TABLE `score_types` DISABLE KEYS */;
INSERT INTO `score_types` VALUES ('cuoiky','Cuối kỳ','CK',3,4),('giuaky','Giữa kỳ','GK',2,3),('mieng','Miệng','M',1,1),('p15','15 phút','15',1,2);
/*!40000 ALTER TABLE `score_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scores`
--

DROP TABLE IF EXISTS `scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `type_code` varchar(16) NOT NULL,
  `value` decimal(4,2) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_score` (`term_id`,`student_id`,`type_code`),
  KEY `fk_sc_student` (`student_id`),
  KEY `fk_sc_type` (`type_code`),
  KEY `fk_sc_by` (`updated_by`),
  KEY `idx_sc_student_term` (`student_id`,`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scores`
--

LOCK TABLES `scores` WRITE;
/*!40000 ALTER TABLE `scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `k` varchar(64) NOT NULL,
  `v` varchar(255) NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('schema_version','1');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `holy_name` varchar(64) DEFAULT NULL,
  `full_name` varchar(128) NOT NULL,
  `gender` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 nam, 0 nữ',
  `birth_date` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `father_name` varchar(128) DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `mother_name` varchar(128) DEFAULT NULL,
  `mother_phone` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_student_name` (`full_name`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (6,'GDGLPT260002','MARIA','ĐẶNG PHƯƠNG CHI',0,'2019-10-29','213/32 Hồng Lạc, P.10, Q.Tân Bình, TP.HCM','Giuse Đặng Minh Diễn','937180287','Maria Trần Thị Thi Linh','907720326','2026-09-11 17:25:56'),(7,'GDGLPT260003','MARIA','NGUYỄN THỊ ANH ĐÀO',0,'2019-01-28','61/21 đường số 1, P.10, Q.Tân Bình, TP.HCM','Gioan Baotixita Nguyễn Thái Bình','382021438','Maria Nguyễn Thị Huyền','','2026-09-11 17:25:56'),(8,'GDGLPT260004','GIEGORIO','NGUYỄN TRẦN MINH ĐĂNG',1,'2019-08-06','248 Đồng Đen, P.10, Q. Tân Bình','Gregorio Nguyễn Xuân Vương','938172728','Anna Trần Thị Kim Quyên','906835745','2026-09-11 17:25:56'),(9,'GDGLPT260005','ANRE','NGUYỄN PHI PHÚC HƯNG',1,'2019-11-15','81/09 Năm Châu, P.11, Q. Tân Bình, TP. HCM','Nguyễn Phi Tuấn','907070905','Teresa Nguyễn Ngọc Trâm','','2026-09-11 17:25:56'),(10,'GDGLPT260006','PHANXICO ASSISI','ĐỖ HOÀNG GIA KHANG',1,'2019-10-05','1023 Lạc Long Quân, P.11, Q.Tân Bình, TP.HCM','Giuse Đỗ Duy Việt Mỹ','946134914','Maria Đinh Thị Lệ Trúc','','2026-09-11 17:25:56'),(11,'GDGLPT260007','PHERO','NGUYỄN ĐĂNG KHOA',1,'2019-07-27','7.16 lô M, ch.cư Bàu Cát 2, P.10, Q.Tân Bình, TP.HCM','Phero Nguyễn Đức Anh','961934008','Maria Trần Thị Hòa','901234323','2026-09-11 17:25:56'),(12,'GDGLPT260008','GIUSE','NGUYỄN MINH KHÔI',1,'2019-07-16','25 Trần Văn Quang, P.10, Q.Tân Bình, TP.HCM','Phaolo Nguyễn Trường Giang','909142047','Maria Trần Thị Ngọc Ái','906844697','2026-09-11 17:25:56'),(13,'GDGLPT260009','PHANXICO XAVIE','LÊ GIA LONG',1,'2019-10-23','108/89/8/12 Trần Văn Quang, P.10, Q.Tân Bình, TP.HCM','Phanxico Xavie Lê Văn Từ','778709219','Anna Lê Thị Lý','','2026-09-11 17:25:56'),(14,'GDGLPT260010','MARIA','MAI NGỌC QUỲNH NHƯ',0,'2019-06-11','127/38/8 Ni Sư Huỳnh Liên, P.10, Q.Tân Bình, TP.HCM','Phaolo Mai Nguyễn Chí Hiếu','931431810','Maria Phạm Ngọc Thùy Trinh','','2026-09-11 17:25:56'),(15,'GDGLPT260011','MARIA','NGUYỄN HOÀNG PHƯƠNG NGHI',0,'2019-08-26','108/89/8/9 Trần Văn Quang, P.10, Q.Tân Bình, TP.HCM','Nguyễn Thành Lân','976319673','Maria Võ Thị Thảo Trang','','2026-09-11 17:25:56'),(16,'GDGLPT260012','LUCA','NGUYỄN CÔNG MINH PHÚC',1,'2019-07-25','46/06 Bùi Thế Mỹ, P.10, Q.Tân Bình, TP.HCM','Phero Nguyễn Công Thức','963401217','Anna Trần Thị Ngọc Quyến','','2026-09-11 17:25:56'),(17,'GDGLPT260013','MATTHEU','TRẦN ĐÌNH PHÚC',1,'2019-07-22','18/17/17 Bùi Thế Mỹ, P.10, Q.Tân Bình, TP.HCM','Gioan Baotixita Trần Đình Thọ','338462902','Isave Maria Nguyễn Thị Thu','','2026-09-11 17:25:56'),(18,'GDGLPT260014','MARIA','HOÀNG LÊ VÂN',0,'2019-12-10','267 Võ Thành Trang, P.11, Q. Tân Bình, TP. HCM','Gioan Baotixita Hoàng Minh Sơn','938221737','Maria Lê Thị Thanh Thủy','','2026-09-11 17:25:56'),(19,'GDGLPT260015','TERESA','NGUYỄN NGỌC MINH VY',0,'2019-11-20','222 Hồng Lạc, P.11, Q. Tân Bình, TP.HCM','Phero Nguyễn Văn Giaps','981705938','Maria Bùi Thị Thảo','','2026-09-11 17:25:56'),(20,'GDGLPT260016','ANNA','NGUYỄN PHÚC DUY HÂN',0,'2018-03-29','404/76 Phạm Phú Thứ, P.4, Q. Tân Bình','Martino Nguyễn Phú Duy','909226918','Anna Nguyễn Phan Trung Thương','','2026-09-11 17:25:56'),(21,'GDGLPT260017','TERESA','NGUYỄN NGỌC KHÁNH QUỲNH',0,'2019-09-02','409 kênh tân hoá, phường tân phú, tphcm','Phero Nguyễn Văn Cấp','907601935','Maria Phạm Thị Thuý','902613779','2026-09-11 17:25:56'),(22,'GDGLPT260018','GIUSE ĐA MINH','ĐỖ HUY MINH',1,'2019-02-10','Số 8, Huỳnh Tịnh Của, phường Bảy Hiền','Giuse Anton Đỗ Sơn Huy','932727627','Trần Phương Thảo','899992771','2026-09-11 17:25:56'),(23,'GDGLPT260019','CATARINA','BÙI NGUYỄN NGỌC TRÚC',0,'2019-02-15','Căn A3.8.7 Chung cư Sài gòn Town, Số 83/16 Thoại Ngọc Hầu, Phường Tân Phú, Tp.HCM','Giuse Bùi Công Toản','983613241','Maria Nguyễn Thị Kim Bằng','','2026-09-11 17:25:56'),(24,'GDGLPT260020','ĐA MINH','TRẦN KHÔI NGUYÊN',1,'2019-07-01','80/2D võ Thành Trang, Phường Bảy Hiền','Đaminh Trần Quý Côi','908524147','Teresa Trần Thị Lệ Hằng','903106016','2026-09-11 17:25:56'),(25,'GDGLPT260021','TERESA','TÔ HOÀNG NHẬT VY',0,'2019-02-28','20 Trần Mai Ninh, Phường Bảy Hiền, TPHCM','Giuse Tô Trần Anh Quốc','902288721','Maria Goretti Nguyễn Thị Trúc Mai','777677113','2026-09-11 17:25:56'),(26,'GDGLPT260022','CATARINA','PHẠM NGUYỄN UYÊN TRANG',0,'2019-03-30','77/48 Lê Lai, P.12, Q. Tân Bình','Giuse Phạm Văn Tuấn','976508929','Maria Nguyễn Thị Lụa','','2026-09-11 17:25:56'),(27,'GDGLPT260023','MARIA','NGUYỄN NGỌC BẢO LAM',0,'2019-03-31','64 Bàu Cát 1, Phường Tân Bình, TP. HCM','Giuse Nguyễn Tuấn Phong','913168493','Maria Nguyễn Thị Xuân Thảo','936586030','2026-09-11 17:25:56'),(28,'GDGLPT260024','ANNA','NGUYỄN TRẦN BẢO NGỌC',0,'2019-10-08','40/25 Trần Văn Quang, P. Bảy Hiền','Phero Nguyễn Văn Doanh','988064861','Madalena Trần Thị Bê','','2026-09-11 17:25:56'),(29,'GDGLPT260025','TERESA','DIỆP HOÀNG KIM TRÚC',0,'2019-10-28','1013 Lạc Long Quân, Khu phố 19, P. Bảy Hiền, TP.HCM','Micae Điệp Duy Thắng','938095098','Teresa Hoàng Phạm Huệ Trinh','','2026-09-11 17:25:56'),(30,'GDGLPT260026','MARIA','NGÔ QUỲNH NGỌC NGÂN',0,'2019-01-30','186 Ni Sư Huỳnh Liên, P. Bảy Hiền, TP.HCM','Giacobe Ngô Hoàng Huynh','983384984','Maria Phạm Vũ Quỳnh Anh','','2026-09-11 17:25:56'),(31,'GDGLPT260027','MARIA','LÂM PHƯƠNG ANH',0,'2019-11-18','337 chung cư 2 Bàu Cát, Bàu Cát 7, P. Tân Bình','Dominico Lâm Thanh Nhàn','933283836','Maria Lưu Hồng Phương','','2026-09-11 17:25:56'),(32,'GDGLPT260028','MARIA','DƯƠNG NGỌC THIÊN AN',0,'2019-12-28','278 Hòa Bình, P. Phú Thạnh, TP. HCM','Dương Đình Tân','982832003','Hồ Thị Thiên Kim','','2026-09-11 17:25:56'),(33,'GDGLPT260029','MARIA','NGUYỄN VÕ LINH NHI',0,'2019-06-28','55/1 Võ Thành Trang, P. Bảy Hiền, TP. HCM','Hieronimo Nguyễn Vân Huyền','908478268','Maria Võ Thị Vi Huỳnh Na','','2026-09-11 17:25:56'),(34,'GDGLPT260030','MARIA','NGUYỄN AN NHIÊN',0,'2019-06-25','12/1/15A Đăng Minh Trứ, P.10, Q. Tân Bình, TP.HCM','Phero Nguyễn Trung Tín','934115675','Maria Nguyễn Thị Mỹ Qúy','','2026-09-11 17:25:56'),(35,'GDGLPT260031','LUCIA MARIA','HUỲNH PHƯƠNG UYÊN',0,'2019-06-09','97/22 Hồng Lạc, P.10, Q. Tân Bình, TP. HCM','Gioan Baotixita Huỳnh Quốc Cường','968518777','Maria Hồ Uyên Phương','','2026-09-11 17:25:56'),(36,'GDGLPT260032','GIOAN','NGUYỄN HOÀNG MINH KHÔI',1,'2019-11-28','Chung cư Bàu Cát 2 Lô E, đường số 1, P. Bảy Hiền, Q.1','Giuse Nguyêễn Hoàng Minh','982180158','Maria Phạm Thị Ngọc Linh','','2026-09-11 17:25:56'),(37,'GDGLPT260033','GIUSE','ĐINH HOÀNG NGUYÊN',1,'2019-06-15','11 Bế Văn Đàn, P.14, Q. Tân Bình','Giuse Đinh Hoàng Trung','945678996','Maria Lê Thị Hồng','934543151','2026-09-11 17:25:56'),(38,'GDGLPT260034','ANNA','TRẦN AN NHIÊN',0,'2019-02-20','25/2b Nguyễn Minh Châu, P. Tân Phú','Phero Trần Thanh Việt','907611176','Maria Trần Thị Diệu An','','2026-09-11 17:25:56'),(39,'GDGLPT260035','TERESA','ĐÀO LÊ VI',0,'2019-06-09','737/33/33 Lạc Long Quân, P. Bảy Hiền, Q. Tân Bình, TP.HCM','Đào Anh Tuấn','915614188','Teresa Hoàng Phương Thảo','','2026-09-11 17:25:56'),(40,'GDGLPT260036','PHAOLO','BÙI THIÊN KHÔI',1,'2019-03-01','49/9 ĐẶNG MINH TRỨ, BẢY HIỀN, HCM','PHAOLO BÙI VĂN TÍNH','373559640','TERESA TRẦN THỊ HOÀI','','2026-09-11 17:25:56'),(41,'GDGLPT260037','MARIA','HUỲNH NHÃ THANH UYÊN',0,'2019-04-23','1017/6A LẠC LONG QUÂN, BẢY HIỀN, HCM','FX HUỲNH BÙI THANH XUÂN','988538245','MARIA TRẦN NGUYỄN THANH THANH','','2026-09-11 17:25:56'),(42,'GDGLPT260038','MARIA','LÊ QUỲNH HƯƠNG',0,'2019-07-05','71 NI SƯ HUỲNH LIÊN, BẢY HIỀN, HCM','Đã mất','901608120','MARIA LÊ NGỌC UYÊN PHƯƠNG','','2026-09-11 17:25:56'),(43,'GDGLPT260039','MARIA','LÊ KHÁNH THU',0,'2019-06-24','','LÊ TRƯƠNG TRUNG HÓA','903058299','TERESA PHAN LÊ MINH THỨC','937353638','2026-09-11 17:25:56'),(44,'GDGLPT260001','GIOAN BAOTIXITA','HOÀNG NGUYÊN BẢO',1,'2019-10-14','536/32/9/29 ÂU CƠ, BẢY HIỀN, HCM','GIOAN BAOTIXITA HOÀNG VĂN THINH','0975759826','TERESA NGUYỄN NGỌC MINH','0946454996','2026-09-12 16:17:41');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `terms`
--

DROP TABLE IF EXISTS `terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_term` (`year_id`,`name`),
  CONSTRAINT `fk_term_year` FOREIGN KEY (`year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `terms`
--

LOCK TABLES `terms` WRITE;
/*!40000 ALTER TABLE `terms` DISABLE KEYS */;
INSERT INTO `terms` VALUES (1,1,'Học kỳ I','2026-08-01','2026-12-31',1),(2,1,'Học kỳ II','2027-01-01','2027-05-31',2);
/*!40000 ALTER TABLE `terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `titles`
--

DROP TABLE IF EXISTS `titles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `titles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_code` varchar(24) NOT NULL,
  `label` varchar(64) NOT NULL,
  `sort_order` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_title` (`role_code`,`label`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `titles`
--

LOCK TABLES `titles` WRITE;
/*!40000 ALTER TABLE `titles` DISABLE KEYS */;
INSERT INTO `titles` VALUES (1,'admin','Quản trị viên',1),(2,'bdh','Đoàn Trưởng',1),(3,'bdh','Đoàn Phó',2),(4,'bdh','Thư Ký',3),(5,'bdh','Thủ Quỹ',4),(6,'bdh','Ủy Viên',5),(7,'truong_khoi','Trưởng Khối',1),(8,'truong_khoi','Phó Khối',2),(9,'glv_chu_nhiem','GLV Chủ Nhiệm',1),(10,'glv','GLV Phụ Tá',1),(11,'glv','Huynh Trưởng',2),(12,'glv','Dự Trưởng',3),(61,'du_bi','Dự Bị',1);
/*!40000 ALTER TABLE `titles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-14  1:10:39
