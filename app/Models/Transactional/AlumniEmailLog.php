<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = status pengiriman satu email alumni, dikelompokkan per
 * batch_id -- lihat migrasi create_alumni_credential_email_log_table dan
 * rename_alumni_credential_email_log_to_alumni_email_log.
 *
 * `kind` membedakan dua jenis email yang lewat tabel yang sama:
 * 'account' (App\Jobs\SendAlumniAccountEmailJob, "Terbitkan Akun") dan
 * 'reminder' (App\Jobs\SendAlumniReminderEmailJob, pengingat isi
 * kuesioner). Statusnya sendiri (queued/sent/failed) berarti sama untuk
 * keduanya, sehingga satu tabel + satu kolom pembeda dipilih alih-alih dua
 * tabel yang isinya nyaris identik.
 */
class AlumniEmailLog extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'alumni_email_log';

    public $timestamps = true;

    protected $fillable = [
        'batch_id', 'kind', 'nim', 'name', 'email', 'status', 'error_message', 'created_by',
    ];
}
