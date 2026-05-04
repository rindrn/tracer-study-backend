<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;dbname=tracer_study;port=5432', 'postgres', 'postgres');
    $pdo->exec('CREATE SCHEMA IF NOT EXISTS tracer_oltp;');
    $pdo->exec('CREATE SCHEMA IF NOT EXISTS tracer_olap;');
    echo "Schemas created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
