DROP DATABASE IF EXISTS smart_medic;
CREATE DATABASE smart_medic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_medic;

-- ============================================================
-- Core users and social platform tables
-- ============================================================

CREATE TABLE users (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(50) NOT NULL,
	email VARCHAR(255) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	middle_name VARCHAR(100) NULL,
	last_name VARCHAR(100) NOT NULL,
	password VARCHAR(255) NOT NULL,
	profile_picture VARCHAR(255) NULL,
	cover_photo VARCHAR(255) NULL,
	bio TEXT NULL,
	role VARCHAR(32) NOT NULL DEFAULT 'patient',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uq_users_username (username),
	UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	community_id INT UNSIGNED NULL,
	content TEXT NOT NULL,
	image VARCHAR(255) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	KEY idx_posts_user_id (user_id),
	KEY idx_posts_community_id (community_id),
	CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	post_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	comment TEXT NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_comments_post_id (post_id),
	KEY idx_comments_user_id (user_id),
	CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_likes (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	post_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_post_likes_post_user (post_id, user_id),
	KEY idx_post_likes_user_id (user_id),
	CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_shares (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	post_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_post_shares_post_id (post_id),
	KEY idx_post_shares_user_id (user_id),
	CONSTRAINT fk_post_shares_post FOREIGN KEY (post_id) REFERENCES posts (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_post_shares_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE followers (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	follower_id INT UNSIGNED NOT NULL,
	following_id INT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_followers_relationship (follower_id, following_id),
	KEY idx_followers_following_id (following_id),
	CONSTRAINT fk_followers_follower FOREIGN KEY (follower_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_followers_following FOREIGN KEY (following_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	sender_id INT UNSIGNED NOT NULL,
	receiver_id INT UNSIGNED NOT NULL,
	message TEXT NOT NULL,
	is_read TINYINT(1) NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_messages_sender_id (sender_id),
	KEY idx_messages_receiver_id (receiver_id),
	KEY idx_messages_conversation (sender_id, receiver_id, created_at),
	CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	type VARCHAR(50) NOT NULL,
	reference_id INT UNSIGNED NULL,
	message VARCHAR(500) NOT NULL,
	is_read TINYINT(1) NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_notifications_user_id (user_id),
	KEY idx_notifications_reference_id (reference_id),
	CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Communities
-- ============================================================

CREATE TABLE communities (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(150) NOT NULL,
	description TEXT NULL,
	image VARCHAR(255) NULL,
	created_by INT UNSIGNED NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_communities_created_by (created_by),
	CONSTRAINT fk_communities_creator FOREIGN KEY (created_by) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE community_members (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	community_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_community_membership (community_id, user_id),
	KEY idx_community_members_user_id (user_id),
	CONSTRAINT fk_community_members_community FOREIGN KEY (community_id) REFERENCES communities (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_community_members_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE posts
	ADD CONSTRAINT fk_posts_community FOREIGN KEY (community_id) REFERENCES communities (id)
		ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================
-- Child and preventive healthcare records
-- ============================================================

CREATE TABLE children (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	child_name VARCHAR(200) NOT NULL,
	date_of_birth DATE NOT NULL,
	gender VARCHAR(32) NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_children_user_id (user_id),
	CONSTRAINT fk_children_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE immunizations (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	child_id INT UNSIGNED NOT NULL,
	vaccine_name VARCHAR(150) NOT NULL,
	scheduled_date DATE NULL,
	administered_date DATE NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_immunizations_child_id (child_id),
	KEY idx_immunizations_status (status),
	CONSTRAINT fk_immunizations_child FOREIGN KEY (child_id) REFERENCES children (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_checkups (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	checkup_type VARCHAR(100) NOT NULL,
	scheduled_date DATE NULL,
	completed_date DATE NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_health_checkups_user_id (user_id),
	KEY idx_health_checkups_status (status),
	CONSTRAINT fk_health_checkups_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maternal_health (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	pregnancy_start_date DATE NOT NULL,
	expected_delivery_date DATE NULL,
	current_week TINYINT UNSIGNED NULL,
	health_status VARCHAR(32) NOT NULL DEFAULT 'active',
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	KEY idx_maternal_health_user_id (user_id),
	KEY idx_maternal_health_status (health_status),
	CONSTRAINT fk_maternal_health_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Consultations, nutrition, emergencies, and rehabilitation
-- ============================================================

CREATE TABLE consultations (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	patient_id INT UNSIGNED NOT NULL,
	doctor_id INT UNSIGNED NOT NULL,
	consultation_type VARCHAR(100) NOT NULL,
	appointment_date DATETIME NOT NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'scheduled',
	symptoms TEXT NULL,
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_consultations_patient_id (patient_id),
	KEY idx_consultations_doctor_id (doctor_id),
	KEY idx_consultations_appointment_date (appointment_date),
	CONSTRAINT fk_consultations_patient FOREIGN KEY (patient_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_consultations_doctor FOREIGN KEY (doctor_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nutrition_records (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	record_date DATE NOT NULL,
	meal_type VARCHAR(50) NOT NULL,
	food_description TEXT NOT NULL,
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_nutrition_records_user_id (user_id),
	KEY idx_nutrition_records_record_date (record_date),
	CONSTRAINT fk_nutrition_records_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE emergency_requests (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	emergency_type VARCHAR(100) NOT NULL,
	location VARCHAR(255) NOT NULL,
	description TEXT NOT NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'pending',
	requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	responded_at TIMESTAMP NULL DEFAULT NULL,
	KEY idx_emergency_requests_user_id (user_id),
	KEY idx_emergency_requests_status (status),
	CONSTRAINT fk_emergency_requests_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rehabilitation_records (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	therapist_id INT UNSIGNED NULL,
	condition_name VARCHAR(200) NOT NULL,
	treatment_plan TEXT NULL,
	session_date DATE NOT NULL,
	progress TEXT NULL,
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_rehabilitation_records_user_id (user_id),
	KEY idx_rehabilitation_records_therapist_id (therapist_id),
	KEY idx_rehabilitation_records_session_date (session_date),
	CONSTRAINT fk_rehabilitation_records_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_rehabilitation_records_therapist FOREIGN KEY (therapist_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Equipment and equipment bookings
-- ============================================================

CREATE TABLE medical_equipment (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(150) NOT NULL,
	description TEXT NULL,
	condition_status VARCHAR(32) NOT NULL DEFAULT 'good',
	availability_status VARCHAR(32) NOT NULL DEFAULT 'available',
	owner_id INT UNSIGNED NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_medical_equipment_owner_id (owner_id),
	KEY idx_medical_equipment_availability (availability_status),
	CONSTRAINT fk_medical_equipment_owner FOREIGN KEY (owner_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE equipment_bookings (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	equipment_id INT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	start_date DATE NOT NULL,
	end_date DATE NOT NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'pending',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_equipment_bookings_equipment_id (equipment_id),
	KEY idx_equipment_bookings_user_id (user_id),
	KEY idx_equipment_bookings_status (status),
	CONSTRAINT fk_equipment_bookings_equipment FOREIGN KEY (equipment_id) REFERENCES medical_equipment (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_equipment_bookings_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Public health reporting and healthcare facilities
-- ============================================================

CREATE TABLE outbreak_reports (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	disease_name VARCHAR(150) NOT NULL,
	location VARCHAR(255) NOT NULL,
	number_of_cases INT UNSIGNED NOT NULL DEFAULT 0,
	report_date DATE NOT NULL,
	description TEXT NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'reported',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_outbreak_reports_user_id (user_id),
	KEY idx_outbreak_reports_status (status),
	KEY idx_outbreak_reports_report_date (report_date),
	CONSTRAINT fk_outbreak_reports_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE healthcare_facilities (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(200) NOT NULL,
	facility_type VARCHAR(100) NOT NULL,
	address VARCHAR(255) NOT NULL,
	phone VARCHAR(50) NULL,
	email VARCHAR(255) NULL,
	latitude DECIMAL(10, 8) NULL,
	longitude DECIMAL(11, 8) NULL,
	services TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_healthcare_facilities_type (facility_type),
	KEY idx_healthcare_facilities_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE referrals (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	patient_id INT UNSIGNED NOT NULL,
	referring_user_id INT UNSIGNED NOT NULL,
	facility_id INT UNSIGNED NOT NULL,
	reason TEXT NOT NULL,
	referral_date DATE NOT NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'pending',
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_referrals_patient_id (patient_id),
	KEY idx_referrals_referring_user_id (referring_user_id),
	KEY idx_referrals_facility_id (facility_id),
	KEY idx_referrals_status (status),
	CONSTRAINT fk_referrals_patient FOREIGN KEY (patient_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_referrals_referring_user FOREIGN KEY (referring_user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_referrals_facility FOREIGN KEY (facility_id) REFERENCES healthcare_facilities (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- General service bookings
-- ============================================================

CREATE TABLE bookings (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	service_type VARCHAR(100) NOT NULL,
	provider_id INT UNSIGNED NULL,
	booking_date DATE NOT NULL,
	booking_time TIME NOT NULL,
	status VARCHAR(32) NOT NULL DEFAULT 'pending',
	notes TEXT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY idx_bookings_user_id (user_id),
	KEY idx_bookings_provider_id (provider_id),
	KEY idx_bookings_booking_date (booking_date),
	KEY idx_bookings_status (status),
	CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_bookings_provider FOREIGN KEY (provider_id) REFERENCES users (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
