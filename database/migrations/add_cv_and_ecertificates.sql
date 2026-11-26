CREATE TABLE IF NOT EXISTS student_resumes (
  id INT NOT NULL AUTO_INCREMENT,
  student_id INT NOT NULL,
  professional_summary TEXT NULL,
  skills_json TEXT NOT NULL,
  experience_json TEXT NOT NULL,
  portfolio_links_json TEXT NOT NULL,
  included_certificate_ids TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_resumes_student (student_id),
  CONSTRAINT fk_student_resumes_student
    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_certificates (
  id INT NOT NULL AUTO_INCREMENT,
  student_id INT NOT NULL,
  subject_id INT NULL,
  term_id INT NOT NULL,
  certificate_code VARCHAR(50) NOT NULL,
  certificate_type ENUM('subject_completion', 'course_completion') NOT NULL,
  title VARCHAR(150) NOT NULL,
  units_completed DECIMAL(3,1) NOT NULL DEFAULT 0.0,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_generated_certificates_code (certificate_code),
  UNIQUE KEY uq_generated_certificates_subject_term (student_id, subject_id, term_id, certificate_type),
  KEY idx_generated_certificates_student_issued (student_id, issued_at),
  CONSTRAINT fk_generated_certificates_student
    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
  CONSTRAINT fk_generated_certificates_subject
    FOREIGN KEY (subject_id) REFERENCES subjects (id) ON DELETE SET NULL,
  CONSTRAINT fk_generated_certificates_term
    FOREIGN KEY (term_id) REFERENCES terms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
