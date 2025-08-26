<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cockpit Futuristico</title>
  <!-- Font Orbitron -->
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
  <!-- Collegamento al tuo foglio di stile -->
  <link rel="stylesheet" href="stile.css">
  <meta http-equiv="refresh" content="2;url=list_identifiers.php">
  </head>
  <body>

<?php
// Configurazione DB
$host = "db";        // o localhost se non sei in Docker
$dbname = "testdb";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupera l'identificativo (da GET o POST)
    $identifier = $_GET['identifier'] ?? $_POST['identifier'] ?? null;

    if (!$identifier) {
        die("⚠️ Nessun identificativo fornito.");
    }

    // Cancella dal database
    $stmt = $pdo->prepare("DELETE FROM dataset WHERE identifier = :identifier");
    $stmt->execute([':identifier' => $identifier]);

    if ($stmt->rowCount() > 0) {
        echo "✅ Record con identificativo <b>$identifier</b> eliminato con successo.";
    } else {
        echo "⚠️ Nessun record trovato con identificativo <b>$identifier</b>.";
    }

} catch (PDOException $e) {
    echo "❌ Errore DB: " . $e->getMessage();
}
