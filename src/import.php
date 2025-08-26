<?php
// Configurazione DB
$host = "db";
$user = "root";
$pass = "root"; // metti la tua password
$db   = "testdb";

try {
    // Connessione PDO
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Connection error: " . $e->getMessage());
}

// Verifica se è stato caricato un file
if (isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === 0) {
    $filename = $_FILES['csvfile']['tmp_name'];

    if (($handle = fopen($filename, "r")) !== false) {
        $rowIndex = 0;

        // Prepara query
        $stmt = $pdo->prepare("
            INSERT INTO dataset (identifier, row_index, col1, col2, vector_values)
            VALUES (:identifier, :row_index, :col1, :col2, :vector_values)
        ");

        // Recupera i dati dal form
        $identifier = $_POST['identifier'] ?? '';
        $matrix = $_POST['matrix'] ?? [];
        $vector = $_POST['vector'] ?? [];

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            // Se il CSV ha intestazione, puoi saltarla così:
            // if ($rowIndex === 0) { $rowIndex++; continue; }

            // Supponiamo: identifier, col1, col2, v1, v2, v3...
            $identifier = $data[0] ?? '';
            $col1 = $data[1] ?? null;
            $col2 = $data[2] ?? null;

            // I valori rimanenti in JSON
            $vector = array_slice($data, 3);
            $vector_json = json_encode($vector, JSON_UNESCAPED_UNICODE);

            // Esegui inserimento
            try {
                $stmt->execute([
                    ':identifier'   => $identifier,
                    ':row_index'    => $rowIndex,
                    ':col1'         => $col1,
                    ':col2'         => $col2,
                    ':vector_values'=> $vector_json
                ]);
            } catch (PDOException $e) {
                echo "⚠️ Errore alla riga $rowIndex: " . $e->getMessage() . "<br>";
            }

            $rowIndex++;
        }

        fclose($handle);
        echo "<p>✅ Importazione completata con successo!</p>";
    } else {
        echo "❌ Errore nell'aprire il file CSV.";
    }
} else {
    echo "⚠️ Nessun file caricato o errore nell'upload.";
}
