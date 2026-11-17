<?php

namespace App\Models;

use App\Core\Model;

class NotifikasiWhatsApp extends Model
{
    protected string $table = 'notifikasi_whatsapp';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'tipe_notifikasi',
        'anggota_id',
        'pembayaran_id',
        'periode_bulan',
        'periode_tahun',
        'nomor_tujuan',
        'pesan',
        'status',
        'message_id',
        'provider_status',
        'provider_response',
        'error_message',
        'sent_by',
        'created_at',
        'updated_at'
    ];

    /**
     * Cari log notifikasi berdasarkan ID pembayaran (untuk cegah duplikasi konfirmasi).
     */
    public function getByPembayaranId(int $pembayaranId): ?array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM {$this->table} 
            WHERE pembayaran_id = :pid AND tipe_notifikasi = 'payment_confirmation'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['pid' => $pembayaranId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Cek apakah sudah pernah ada pengingat tunggakan terkirim untuk anggota & periode yang sama.
     */
    public function cekPengingatTerkirim(int $anggotaId, int $bulan, int $tahun): ?array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM {$this->table}
            WHERE anggota_id = :aid
              AND tipe_notifikasi = 'arrears_reminder'
              AND periode_bulan = :bulan
              AND periode_tahun = :tahun
              AND status IN ('sent', 'pending')
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([
            'aid' => $anggotaId,
            'bulan' => $bulan,
            'tahun' => $tahun
        ]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Dapatkan detail notifikasi lengkap dengan relasi nama anggota dan nama user pengirim.
     */
    public function getWithDetails(int $id): ?array
    {
        $stmt = static::db()->prepare("
            SELECT nw.*, 
                   a.nama AS anggota_nama, a.nomor_identitas AS anggota_nis, a.kelas AS anggota_kelas,
                   u.username AS pengirim_username, u.nama AS pengirim_nama
            FROM {$this->table} nw
            LEFT JOIN anggota a ON a.id = nw.anggota_id
            LEFT JOIN users u ON u.id = nw.sent_by
            WHERE nw.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Dapatkan riwayat notifikasi dengan pagination dan filter.
     */
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT nw.*, 
                   a.nama AS anggota_nama, a.nomor_identitas AS anggota_nis, a.kelas AS anggota_kelas,
                   u.username AS pengirim_username, u.nama AS pengirim_nama
            FROM {$this->table} nw
            LEFT JOIN anggota a ON a.id = nw.anggota_id
            LEFT JOIN users u ON u.id = nw.sent_by
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['tipe'])) {
            $sql .= " AND nw.tipe_notifikasi = :tipe";
            $params['tipe'] = $filters['tipe'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND nw.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['anggota_id'])) {
            $sql .= " AND nw.anggota_id = :anggota_id";
            $params['anggota_id'] = (int)$filters['anggota_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.nama LIKE :search1 OR nw.nomor_tujuan LIKE :search2 OR nw.pesan LIKE :search3)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
            $params['search3'] = "%{$filters['search']}%";
        }

        if (!empty($filters['tanggal_mulai'])) {
            $sql .= " AND DATE(nw.created_at) >= :tgl_mulai";
            $params['tgl_mulai'] = $filters['tanggal_mulai'];
        }

        if (!empty($filters['tanggal_akhir'])) {
            $sql .= " AND DATE(nw.created_at) <= :tgl_akhir";
            $params['tgl_akhir'] = $filters['tanggal_akhir'];
        }

        $sql .= " ORDER BY nw.id DESC LIMIT :limit OFFSET :offset";

        $stmt = static::db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Hitung total data hasil filter untuk pagination.
     */
    public function countFiltered(array $filters): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM {$this->table} nw
            LEFT JOIN anggota a ON a.id = nw.anggota_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['tipe'])) {
            $sql .= " AND nw.tipe_notifikasi = :tipe";
            $params['tipe'] = $filters['tipe'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND nw.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['anggota_id'])) {
            $sql .= " AND nw.anggota_id = :anggota_id";
            $params['anggota_id'] = (int)$filters['anggota_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.nama LIKE :search1 OR nw.nomor_tujuan LIKE :search2 OR nw.pesan LIKE :search3)";
            $params['search1'] = "%{$filters['search']}%";
            $params['search2'] = "%{$filters['search']}%";
            $params['search3'] = "%{$filters['search']}%";
        }

        if (!empty($filters['tanggal_mulai'])) {
            $sql .= " AND DATE(nw.created_at) >= :tgl_mulai";
            $params['tgl_mulai'] = $filters['tanggal_mulai'];
        }

        if (!empty($filters['tanggal_akhir'])) {
            $sql .= " AND DATE(nw.created_at) <= :tgl_akhir";
            $params['tgl_akhir'] = $filters['tanggal_akhir'];
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return (int)($res['total'] ?? 0);
    }
}
