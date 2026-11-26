<?php

final class ResumeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getStudentIdForUser(int $userId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM students WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $studentId = $stmt->fetchColumn();

        return $studentId === false ? null : (int)$studentId;
    }

    public function getResumeData(int $userId): ?array
    {
        $profileStmt = $this->pdo->prepare(
            'SELECT s.id, s.student_id, s.ptc_email, s.first_name, s.middle_name, s.last_name,
                    s.year_level, s.section, u.email AS account_email
               FROM students s
          LEFT JOIN users u ON u.id = s.user_id
              WHERE s.user_id = :user_id
              LIMIT 1'
        );
        $profileStmt->execute([':user_id' => $userId]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            return null;
        }

        $profile['full_name'] = trim(implode(' ', array_filter([
            trim((string)$profile['first_name']),
            trim((string)($profile['middle_name'] ?? '')),
            trim((string)$profile['last_name']),
        ], static function (string $part): bool {
            return $part !== '';
        })));
        $profile['email'] = trim((string)($profile['ptc_email'] ?? ''))
            ?: trim((string)($profile['account_email'] ?? ''));
        $profile['year_label'] = $this->yearLevelLabel((string)($profile['year_level'] ?? ''));
        $profile['course_title'] = $this->findCourseTitle((int)$profile['id'], (string)($profile['section'] ?? ''));

        return [
            'profile' => $profile,
            'completed_subjects' => $this->getCompletedSubjects((int)$profile['id'], (string)($profile['section'] ?? '')),
            'achievements' => [],
            'draft' => $this->getDraft((int)$profile['id']),
        ];
    }

    public function saveDraft(int $userId, array $input): void
    {
        $studentId = $this->getStudentIdForUser($userId);
        if (!$studentId) {
            throw new RuntimeException('Student profile was not found.');
        }

        $summary = $this->readText($input, 'professional_summary', 5000);
        $skills = $this->readLines($input, 'skills', 30, 120);
        $experience = $this->readLines($input, 'experience', 20, 1200);
        $links = $this->readLines($input, 'portfolio_links', 15, 500);
        foreach ($links as $link) {
            $parts = parse_url($link);
            if (
                filter_var($link, FILTER_VALIDATE_URL) === false
                || !is_array($parts)
                || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                throw new InvalidArgumentException('Portfolio links must be valid HTTP or HTTPS URLs.');
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO student_resumes
                (student_id, professional_summary, skills_json, experience_json, portfolio_links_json, included_certificate_ids)
             VALUES
                (:student_id, :professional_summary, :skills_json, :experience_json, :portfolio_links_json, :included_certificate_ids)
             ON DUPLICATE KEY UPDATE
                professional_summary = VALUES(professional_summary),
                skills_json = VALUES(skills_json),
                experience_json = VALUES(experience_json),
                portfolio_links_json = VALUES(portfolio_links_json),
                included_certificate_ids = VALUES(included_certificate_ids),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':professional_summary' => $summary,
            ':skills_json' => $this->encodeJson($skills),
            ':experience_json' => $this->encodeJson($experience),
            ':portfolio_links_json' => $this->encodeJson($links),
            ':included_certificate_ids' => $this->encodeJson([]),
        ]);
    }

    private function findCourseTitle(int $studentId, string $sectionName): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.title
               FROM section_students ss
               JOIN sections sec ON sec.id = ss.section_id
          LEFT JOIN courses c ON c.id = sec.course_id
          LEFT JOIN terms t ON t.id = sec.term_id
              WHERE ss.student_id = :student_id
                AND c.title IS NOT NULL
           ORDER BY COALESCE(t.is_active, 0) DESC, sec.id DESC
              LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId]);
        $course = $stmt->fetchColumn();
        if ($course !== false) {
            return (string)$course;
        }

        if (trim($sectionName) === '') {
            return '';
        }

        $fallbackStmt = $this->pdo->prepare(
            'SELECT c.title
               FROM sections sec
          LEFT JOIN courses c ON c.id = sec.course_id
              WHERE sec.section_name = :section_name
                AND c.title IS NOT NULL
           ORDER BY sec.id DESC
              LIMIT 1'
        );
        $fallbackStmt->execute([':section_name' => $sectionName]);
        $fallback = $fallbackStmt->fetchColumn();

        return $fallback === false ? '' : (string)$fallback;
    }

    private function getCompletedSubjects(int $studentId, string $sectionName): array
    {
        $sectionStmt = $this->pdo->prepare('SELECT section_id FROM section_students WHERE student_id = :student_id');
        $sectionStmt->execute([':student_id' => $studentId]);
        $sectionIds = array_values(array_unique(array_map('intval', $sectionStmt->fetchAll(PDO::FETCH_COLUMN))));

        if (!$sectionIds && trim($sectionName) !== '') {
            $fallbackStmt = $this->pdo->prepare('SELECT id FROM sections WHERE section_name = :section_name');
            $fallbackStmt->execute([':section_name' => $sectionName]);
            $sectionIds = array_values(array_unique(array_map('intval', $fallbackStmt->fetchAll(PDO::FETCH_COLUMN))));
        }

        if (!$sectionIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT sec.id AS section_id, ssb.id AS section_subject_id,
                    sub.subject_code, sub.subject_title, COALESCE(sub.units, 0) AS units,
                    COALESCE(ssb.term_id, sec.term_id) AS term_id, t.term_name, t.school_year
               FROM sections sec
               JOIN section_subjects ssb ON ssb.section_id = sec.id
               JOIN subjects sub ON sub.id = ssb.subject_id
          LEFT JOIN terms t ON t.id = COALESCE(ssb.term_id, sec.term_id)
              WHERE sec.id IN ($placeholders)
           ORDER BY t.school_year DESC, t.term_name DESC, sub.subject_title"
        );
        $stmt->execute($sectionIds);

        $completed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $subject) {
            if (!function_exists('computeStudentGradeForSection')) {
                throw new LogicException('The portal grade calculator is unavailable.');
            }

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

            $subject['final_grade'] = (float)$grade;
            $completed[] = $subject;
        }

        return $completed;
    }

    private function getDraft(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT professional_summary, skills_json, experience_json, portfolio_links_json,
                    included_certificate_ids, updated_at
               FROM student_resumes
              WHERE student_id = :student_id
              LIMIT 1'
        );
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'professional_summary' => '',
                'skills' => [],
                'experience' => [],
                'portfolio_links' => [],
                'included_certificate_ids' => [],
                'updated_at' => null,
            ];
        }

        return [
            'professional_summary' => (string)($row['professional_summary'] ?? ''),
            'skills' => $this->decodeJson((string)$row['skills_json']),
            'experience' => $this->decodeJson((string)$row['experience_json']),
            'portfolio_links' => $this->decodeJson((string)$row['portfolio_links_json']),
            'included_certificate_ids' => $this->decodeJson((string)$row['included_certificate_ids']),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function readText(array $input, string $key, int $maxLength): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || $this->textLength($value) > $maxLength) {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' is too long or invalid.');
        }

        return trim($value);
    }

    private function readLines(array $input, string $key, int $maxItems, int $maxLength): array
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || $this->textLength($value) > $maxItems * $maxLength) {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' is too long or invalid.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);
        $values = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($this->textLength($line) > $maxLength) {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' contains an entry that is too long.');
            }
            $values[] = $line;
        }
        if (count($values) > $maxItems) {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' has too many entries.');
        }

        return $values;
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new UnexpectedValueException('Stored resume data is invalid.');
        }

        return $decoded;
    }

    private function yearLevelLabel(string $yearLevel): string
    {
        $labels = [
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            '4' => '4th Year',
        ];

        return $labels[$yearLevel] ?? $yearLevel;
    }
}
