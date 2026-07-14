<?php

namespace App\Exceptions\MemberCard;

use RuntimeException;

class NoActiveCardTemplateException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Belum ada template kartu anggota yang aktif. Atur salah satu template di Pengaturan Kartu Anggota terlebih dahulu.');
    }
}
