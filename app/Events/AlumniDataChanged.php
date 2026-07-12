<?php
// app/Events/AlumniDataChanged.php
namespace App\Events;

class AlumniDataChanged
{
    public function __construct(
        public readonly int $programId,
        public readonly int $graduatedYear,
    ) {}
}