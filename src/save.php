<?php
// Connessione al database (modifica se usi credenziali diverse)
$host = "db";        // nome servizio Docker
$dbname = "testdb";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupera i dati dal form
    $identifier = $_POST['identifier'] ?? '';
    $matrix = $_POST['matrix'] ?? [];
    $vector = $_POST['vector'] ?? [];

    if (!$identifier) {
        die("Identificativo mancante");
    }

    // Inserisce ogni riga della matrice
    foreach ($matrix as $rowIndex => $cols) {
        $col1 = $cols[0] ?? null;
        $col2 = $cols[1] ?? null;

        $stmt = $pdo->prepare("INSERT INTO dataset (identifier, row_index, col1, col2, vector_values) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $identifier,
            $rowIndex,
            $col1,
            $col2,
            json_encode($vector)
        ]);
    }

    echo "<p>Dati salvati con successo!</p>";
    echo '<a href="storeTable.php">Torna indietro</a>';

} catch (PDOException $e) {
    echo "Errore DB: " . $e->getMessage();
}
