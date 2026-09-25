<?php
/**
 * CLASS CONTROLLER
 *
 * Xu ly cac thao tac CRUD cho lop hoc
 */

namespace TNTT\Controllers;

/**
 * Class Controller
 */
class ClassController extends BaseController
{
    /**
     * Lay danh sach lop
     * GET /api/classes
     */
    public function index(array $params = []): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $me = require_permission('classes', 'view');
        $year = current_year();

        $sql = 'SELECT c.*, b.name AS block_name
                FROM classes c
                LEFT JOIN blocks b ON b.id = c.block_id';

        $bindings = [];
        if ($year) {
            $sql .= ' WHERE c.year_id = ?';
            $bindings[] = $year['id'];
        }

        $sql .= ' ORDER BY b.display_order, c.name';

        $rows = db_all($sql, $bindings);

        $this->ok(['items' => $rows]);
    }

    /**
     * Tao moi lop
     * POST /api/classes
     */
    public function store(array $params = []): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $this->requireWrite();
        $me = require_permission('classes', 'edit');
        $year = current_year();
        if (!$year) $this->fail('Chua co niên khoá nào đang mở.', 409);

        $data = [
            'name'      => trim($this->require('name')),
            'block_id'  => (int) $this->get('block_id', 0),
            'year_id'   => $year['id'],
            'status'    => $this->get('status', 'hoat dong'),
        ];

        if (empty($data['name'])) $this->fail('Thiếu tên lớp.');

        trong_giao_dich(function() use ($data) {
            $id = db_insert('INSERT INTO classes (name, block_id, year_id, status) VALUES (?,?,?,?)',
                [$data['name'], $data['block_id'], $data['year_id'], $data['status']]);
            return $id;
        });

        log_action('tao', 'classes', 'Tạo lớp ' . $data['name'], $data['name']);

        $this->ok(['id' => $id]);
    }

    /**
     * Chi tiet lop
     * GET /api/classes/:id
     */
    public function show(array $params): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $id = (int) ($params[0] ?? $this->require('id'));
        $me = require_permission('classes', 'view');

        $class = db_one('SELECT c.*, b.name AS block_name
                         FROM classes c
                         LEFT JOIN blocks b ON b.id = c.block_id
                         WHERE c.id = ?', [$id]);

        if (!$class) $this->fail('Khong tim thay lop.', 404);

        $this->ok(['item' => $class]);
    }

    /**
     * Cap nhat lop
     * PUT /api/classes/:id
     */
    public function update(array $params): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $this->requireWrite();
        $id = (int) ($params[0] ?? $this->require('id'));
        $me = require_permission('classes', 'edit');

        $class = db_one('SELECT * FROM classes WHERE id = ?', [$id]);
        if (!$class) $this->fail('Khong tim thay lop.', 404);

        $data = [
            'name'     => trim($this->get('name', $class['name'])),
            'block_id' => (int) $this->get('block_id', $class['block_id']),
            'status'   => $this->get('status', $class['status']),
        ];

        if (empty($data['name'])) $this->fail('Thiếu tên lớp.');

        db_run('UPDATE classes SET name=?, block_id=?, status=? WHERE id=?',
            [$data['name'], $data['block_id'], $data['status'], $id]);

        log_action('sua', 'classes', 'Sửa lớp ' . $data['name'], $data['name']);

        $this->ok(['id' => $id]);
    }

    /**
     * Xoa lop
     * DELETE /api/classes/:id
     */
    public function destroy(array $params): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $this->requireWrite();
        $id = (int) ($params[0] ?? $this->require('id'));
        $me = require_permission('classes', 'edit');

        $class = db_one('SELECT * FROM classes WHERE id = ?', [$id]);
        if (!$class) $this->fail('Khong tim thay lop.', 404);

        // Kiem tra co hoc sinh nao trong lop
        $count = db_one('SELECT COUNT(*) n FROM enrollments WHERE class_id = ?', [$id])['n'];
        if ($count > 0) {
            $this->fail('Lớp đang có ' . $count . ' học sinh, không xóa được.');
        }

        db_run('DELETE FROM classes WHERE id = ?', [$id]);

        log_action('xoa', 'classes', 'Xóa lớp ' . $class['name'], $class['name']);

        $this->ok(['deleted' => true]);
    }
}
