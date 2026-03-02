-- ============================================
-- Database Indexes for Performance Optimization
-- ============================================
-- This file contains recommended indexes for frequently queried columns
-- Run this script in your database to improve query performance
-- 
-- IMPORTANT: Test these indexes in a staging environment first!
-- Some indexes may already exist - the script will fail gracefully
-- ============================================

-- ============================================
-- couple_access table
-- ============================================
-- Frequently queried by: access_id, access_code, code_status
CREATE INDEX IF NOT EXISTS idx_couple_access_access_id ON couple_access(access_id);
CREATE INDEX IF NOT EXISTS idx_couple_access_access_code ON couple_access(access_code);
CREATE INDEX IF NOT EXISTS idx_couple_access_code_status ON couple_access(code_status);
CREATE INDEX IF NOT EXISTS idx_couple_access_date_created ON couple_access(date_created);

-- ============================================
-- couple_profile table
-- ============================================
-- Frequently queried by: access_id, sex
CREATE INDEX IF NOT EXISTS idx_couple_profile_access_id ON couple_profile(access_id);
CREATE INDEX IF NOT EXISTS idx_couple_profile_sex ON couple_profile(sex);
CREATE INDEX IF NOT EXISTS idx_couple_profile_access_id_sex ON couple_profile(access_id, sex);

-- ============================================
-- couple_responses table
-- ============================================
-- CRITICAL: Most frequently queried table
-- Queried by: access_id, respondent, category_id, question_id
CREATE INDEX IF NOT EXISTS idx_couple_responses_access_id ON couple_responses(access_id);
CREATE INDEX IF NOT EXISTS idx_couple_responses_respondent ON couple_responses(respondent);
CREATE INDEX IF NOT EXISTS idx_couple_responses_category_id ON couple_responses(category_id);
CREATE INDEX IF NOT EXISTS idx_couple_responses_question_id ON couple_responses(question_id);
-- Composite index for common query pattern
CREATE INDEX IF NOT EXISTS idx_couple_responses_access_respondent ON couple_responses(access_id, respondent);
CREATE INDEX IF NOT EXISTS idx_couple_responses_access_category ON couple_responses(access_id, category_id);

-- ============================================
-- audit_logs table
-- ============================================
-- Frequently queried by: created_at, user_id, action, module
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_module ON audit_logs(module);
-- Composite index for common filter pattern
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at_action ON audit_logs(created_at, action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_created ON audit_logs(user_id, created_at);

-- ============================================
-- admin table
-- ============================================
-- Frequently queried by: admin_id, username, email_address, position, is_active, created_at
CREATE INDEX IF NOT EXISTS idx_admin_admin_id ON admin(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_username ON admin(username);
CREATE INDEX IF NOT EXISTS idx_admin_email_address ON admin(email_address);
CREATE INDEX IF NOT EXISTS idx_admin_position ON admin(position);
CREATE INDEX IF NOT EXISTS idx_admin_is_active ON admin(is_active);
CREATE INDEX IF NOT EXISTS idx_admin_created_at ON admin(created_at);

-- ============================================
-- ml_analysis table
-- ============================================
-- Frequently queried by: access_id, generated_at
CREATE INDEX IF NOT EXISTS idx_ml_analysis_access_id ON ml_analysis(access_id);
CREATE INDEX IF NOT EXISTS idx_ml_analysis_generated_at ON ml_analysis(generated_at);
CREATE INDEX IF NOT EXISTS idx_ml_analysis_risk_level ON ml_analysis(risk_level);

-- ============================================
-- scheduling table
-- ============================================
-- Frequently queried by: access_id, session_date, status, session_type
CREATE INDEX IF NOT EXISTS idx_scheduling_schedule_id ON scheduling(schedule_id);
CREATE INDEX IF NOT EXISTS idx_scheduling_access_id ON scheduling(access_id);
CREATE INDEX IF NOT EXISTS idx_scheduling_session_date ON scheduling(session_date);
CREATE INDEX IF NOT EXISTS idx_scheduling_status ON scheduling(status);
CREATE INDEX IF NOT EXISTS idx_scheduling_session_type ON scheduling(session_type);
CREATE INDEX IF NOT EXISTS idx_scheduling_created_at ON scheduling(created_at);
CREATE INDEX IF NOT EXISTS idx_scheduling_access_date ON scheduling(access_id, session_date);

-- ============================================
-- attendance_logs table
-- ============================================
-- Frequently queried by: schedule_id, access_id, status
CREATE INDEX IF NOT EXISTS idx_attendance_logs_schedule_id ON attendance_logs(schedule_id);
CREATE INDEX IF NOT EXISTS idx_attendance_logs_access_id ON attendance_logs(access_id);
CREATE INDEX IF NOT EXISTS idx_attendance_logs_status ON attendance_logs(status);
CREATE INDEX IF NOT EXISTS idx_attendance_logs_partner_type ON attendance_logs(partner_type);

-- ============================================
-- question_category table
-- ============================================
-- Frequently queried by: category_id
CREATE INDEX IF NOT EXISTS idx_question_category_category_id ON question_category(category_id);

-- ============================================
-- question_assessment table
-- ============================================
-- Frequently queried by: question_id, category_id
CREATE INDEX IF NOT EXISTS idx_question_assessment_question_id ON question_assessment(question_id);
CREATE INDEX IF NOT EXISTS idx_question_assessment_category_id ON question_assessment(category_id);
CREATE INDEX IF NOT EXISTS idx_question_assessment_category_question ON question_assessment(category_id, question_id);

-- ============================================
-- sub_question_assessment table
-- ============================================
-- Frequently queried by: sub_question_id, question_id
CREATE INDEX IF NOT EXISTS idx_sub_question_assessment_sub_question_id ON sub_question_assessment(sub_question_id);
CREATE INDEX IF NOT EXISTS idx_sub_question_assessment_question_id ON sub_question_assessment(question_id);
CREATE INDEX IF NOT EXISTS idx_sub_question_assessment_question_sub ON sub_question_assessment(question_id, sub_question_id);

-- ============================================
-- Performance Notes
-- ============================================
-- 
-- 1. Indexes improve SELECT query performance but slightly slow down INSERT/UPDATE/DELETE
-- 2. Monitor query performance after applying indexes using EXPLAIN
-- 3. Some indexes may already exist - check your database first
-- 4. For MySQL 5.7+, use "CREATE INDEX IF NOT EXISTS" syntax
-- 5. For older MySQL versions, check if index exists before creating:
--    SHOW INDEX FROM table_name;
-- 
-- ============================================
-- Verify Indexes
-- ============================================
-- Run these queries to verify indexes were created:
-- 
-- SHOW INDEX FROM couple_access;
-- SHOW INDEX FROM couple_responses;
-- SHOW INDEX FROM audit_logs;
-- SHOW INDEX FROM ml_analysis;
-- 
-- ============================================

