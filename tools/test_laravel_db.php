<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = Illuminate\Support\Facades\DB::connection('oracle');

// Lecture avec variable liée
$r = $db->select('select count(*) as n from amp_test.operation where montant >= ?', [1000000]);
echo "Lecture OK : " . array_values((array) $r[0])[0] . " operations >= 1 000 000" . PHP_EOL;

// Écriture : doit être refusée (dans une transaction annulée par sécurité)
$db->beginTransaction();
try {
    $db->delete('delete from amp_test.operation where operation_id = 1');
    echo "ALERTE : ecriture reussie, lecture seule non garantie !" . PHP_EOL;
} catch (Throwable $e) {
    echo "Ecriture refusee (attendu) : " . $e->getMessage() . PHP_EOL;
} finally {
    $db->rollBack();
}