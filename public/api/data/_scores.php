<?php
/**
 * DATA API — Scores & Reports Module
 *
 * Điểm số + sổ liên lạc
 */

/**
 * Lấy điểm số.
 * Theo học kỳ của năm nay, chỉ em thuộc phạm vi được xem module 'scores' (#78)
 *
 * @return array Điểm số đã format
 */
function data_load_scores(int $yid, array $me): array
{
    return array_map(fn($s) => [
        'studentId' => (int) $s['student_id'],
        'termId'    => (int) $s['term_id'],
        'type'      => $s['type_code'],
        'value'     => (float) $s['value'],
        'at'        => substr($s['updated_at'], 0, 16),
        'by'        => $s['by_name'] ?? '',
    ], data_scoped_rows(data_scope_for($me, 'scores'),
        'SELECT sc.*, m.full_name AS by_name
           FROM scores sc{JOIN}
           JOIN terms t ON t.id = sc.term_id
           LEFT JOIN members m ON m.id = sc.updated_by
          WHERE t.year_id = ?', 'sc.student_id', $yid, [$yid]));
}

/**
 * Lấy sổ liên lạc.
 * Chỉ em thuộc phạm vi được xem module 'reports' (#78)
 *
 * @return array Sổ liên lạc đã format
 */
function data_load_reports(int $yid, array $me): array
{
    return array_map(fn($r) => [
        'id'         => (int) $r['id'],
        'studentId'  => (int) $r['student_id'],
        'termId'     => (int) $r['term_id'],
        'attendance' => [
            'present'   => (int) $r['att_present'],
            'late'      => (int) $r['att_late'],
            'excused'   => (int) $r['att_excused'],
            'unexcused' => (int) $r['att_unexcused'],
            'total'     => (int) $r['att_total'],
            'rate'      => (int) $r['att_rate'],
        ],
        'score'     => $r['score'] === null ? '' : (float) $r['score'],
        'conduct'   => $r['conduct'],
        'rank'      => $r['rank_label'],
        'remark'    => $r['remark'] ?? '',
        'status'    => $r['status'],
        'createdBy' => $r['by_name'] ?? '',
        'createdAt' => substr($r['created_at'], 0, 16),
    ], data_scoped_rows(data_scope_for($me, 'reports'),
        'SELECT r.*, m.full_name AS by_name
           FROM reports r{JOIN}
           JOIN terms t ON t.id = r.term_id
           LEFT JOIN members m ON m.id = r.created_by
          WHERE t.year_id = ?', 'r.student_id', $yid, [$yid]));
}
