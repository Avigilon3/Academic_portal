<?php

final class CertificateService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function issueEligibleSubjectCertificates(int $studentId): int
    {
        if ($studentId <= 0) {
            throw new InvalidArgumentException('A valid student ID is required.');
        }
        if (!function_exists('computeStudentGradeForSection')) {
            throw new LogicException('The portal grade calculator is unavailable.');
        }

        $sectionStmt = $this->pdo->prepare('SELECT section_id FROM section_students WHERE student_id = :student_id');
        $sectionStmt->execute([':student_id' => $studentId]);
        $sectionIds = array_values(array_unique(array_map('intval', $sectionStmt->fetchAll(PDO::FETCH_COLUMN))));
        if (!$sectionIds) {
            $fallbackStmt = $this->pdo->prepare(
                'SELECT sec.id
                   FROM students s
                   JOIN sections sec ON sec.section_name = s.section
                  WHERE s.id = :student_id'
            );
            $fallbackStmt->execute([':student_id' => $studentId]);
            $sectionIds = array_values(array_unique(array_map('intval', $fallbackStmt->fetchAll(PDO::FETCH_COLUMN))));
        }
        if (!$sectionIds) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT sec.id AS section_id, ssb.id AS section_subject_id,
                    ssb.subject_id, COALESCE(ssb.term_id, sec.term_id) AS term_id,
                    sub.subject_title, COALESCE(sub.units, 0) AS units
               FROM sections sec
               JOIN section_subjects ssb ON ssb.section_id = sec.id
               JOIN subjects sub ON sub.id = ssb.subject_id
               JOIN terms t ON t.id = COALESCE(ssb.term_id, sec.term_id)
               WHERE sec.id IN ($placeholders)"
        );
        $stmt->execute($sectionIds);
        $existingStmt = $this->pdo->prepare(
            "SELECT id
               FROM generated_certificates
              WHERE student_id = :student_id
                AND subject_id = :subject_id
                AND term_id = :term_id
                AND certificate_type = 'subject_completion'
              LIMIT 1"
        );
        $insertStmt = $this->pdo->prepare(
            "INSERT INTO generated_certificates
                (student_id, subject_id, term_id, certificate_code, certificate_type, title, units_completed)
             VALUES
                (:student_id, :subject_id, :term_id, :certificate_code, 'subject_completion', :title, :units_completed)"
        );

        $issuedCount = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $subject) {
            $gradeInfo = computeStudentGradeForSection(
                $this->pdo,
                $studentId,
                (int)$subject['section_id'],
                (int)$subject['section_subject_id']
            );
            $grade = $gradeInfo['final_grade_display'] ?? $gradeInfo['final_grade'] ?? null;
            if (!is_numeric($grade) || (float)$grade < 75) {
                continue;
            }

            $identity = [
                ':student_id' => $studentId,
                ':subject_id' => (int)$subject['subject_id'],
                ':term_id' => (int)$subject['term_id'],
            ];
            $existingStmt->execute($identity);
            if ($existingStmt->fetchColumn() !== false) {
                continue;
            }

            $units = number_format((float)$subject['units'], 1, '.', '');
            $saved = false;
            for ($attempt = 0; $attempt < 5 && !$saved; $attempt++) {
                try {
                    $insertStmt->execute([
                        ':student_id' => $studentId,
                        ':subject_id' => (int)$subject['subject_id'],
                        ':term_id' => (int)$subject['term_id'],
                        ':certificate_code' => $this->newCertificateCode(),
                        ':title' => (string)$subject['subject_title'],
                        ':units_completed' => $units,
                    ]);
                    $issuedCount++;
                    $saved = true;
                } catch (PDOException $exception) {
                    $existingStmt->execute($identity);
                    if ($existingStmt->fetchColumn() !== false) {
                        $saved = true;
                    } elseif ((int)($exception->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) {
                        throw $exception;
                    }
                }
            }
        }

        return $issuedCount;
    }

    public function getCertificatesForStudent(int $studentId): array
    {
        $this->issueEligibleSubjectCertificates($studentId);

        $stmt = $this->pdo->prepare(
            'SELECT gc.id, gc.certificate_code, gc.certificate_type, gc.title,
                    gc.units_completed, gc.issued_at, gc.term_id, t.term_name, t.school_year
               FROM generated_certificates gc
               JOIN terms t ON t.id = gc.term_id
              WHERE gc.student_id = :student_id
           ORDER BY gc.issued_at DESC, gc.id DESC'
        );
        $stmt->execute([':student_id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCertificateForStudent(int $studentId, int $certificateId): ?array
    {
        if ($studentId <= 0 || $certificateId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT gc.id, gc.student_id, gc.subject_id, gc.term_id, gc.certificate_code,
                    gc.certificate_type, gc.title, gc.units_completed, gc.issued_at,
                    t.term_name, t.school_year,
                    CONCAT_WS(\' \', NULLIF(s.first_name, \'\'), NULLIF(s.middle_name, \'\'), NULLIF(s.last_name, \'\')) AS full_name,
                    s.student_id AS student_number, c.title AS course_title
               FROM generated_certificates gc
               JOIN students s ON s.id = gc.student_id
               JOIN terms t ON t.id = gc.term_id
          LEFT JOIN subjects sub ON sub.id = gc.subject_id
          LEFT JOIN courses c ON c.id = sub.course_id
              WHERE gc.student_id = :student_id
                AND gc.id = :certificate_id
              LIMIT 1'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':certificate_id' => $certificateId,
        ]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

        return $certificate ?: null;
    }

    public function verifyCertificate(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^PTC-CERT-[0-9]{4}-[A-F0-9]{20}$/', $code)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT gc.certificate_code, gc.certificate_type, gc.title,
                    gc.units_completed, gc.issued_at, t.term_name, t.school_year,
                    CONCAT_WS(\' \', NULLIF(s.first_name, \'\'), NULLIF(s.middle_name, \'\'), NULLIF(s.last_name, \'\')) AS full_name
               FROM generated_certificates gc
               JOIN students s ON s.id = gc.student_id
               JOIN terms t ON t.id = gc.term_id
              WHERE gc.certificate_code = :certificate_code
              LIMIT 1'
        );
        $stmt->execute([':certificate_code' => $code]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

        return $certificate ?: null;
    }

    private function newCertificateCode(): string
    {
        return 'PTC-CERT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(10)));
    }
}
