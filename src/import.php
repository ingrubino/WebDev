<?php
// Configurazione DB
$host = "db";
$user = "root";
$pass = "root"; // metti la tua password
$db   = "testdb";

// Connessione al database (modifica se usi credenziali diverse)
$host = "db";        // nome servizio Docker
$dbname = "testdb";
$user = "root";
$pass = "root";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Verifica se è stato caricato un file
if (isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === 0) {
    $filename = $_FILES['csvfile']['tmp_name'];

    if (($handle = fopen($filename, "r")) !== false) {
        $rowIndex = 0;

        // Legge riga per riga
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            // Supponiamo che il CSV sia così:
            // identifier, col1, col2, v1, v2, v3, ...
            $identifier = $conn->real_escape_string($data[0]);
            $col1 = $conn->real_escape_string($data[1]);
            $col2 = $conn->real_escape_string($data[2]);

            // I restanti valori vanno in JSON
            $vector = array_slice($data, 3);
            $vector_json = json_encode($vector, JSON_UNESCAPED_UNICODE);

            // Query di inserimento
            $sql = "INSERT INTO dataset (identifier, row_index, col1, col2, vector_values)
                    VALUES ('$identifier', $rowIndex, '$col1', '$col2', '$vector_json')";

            if (!$conn->query($sql)) {
                echo "Errore alla riga $rowIndex: " . $conn->error . "<br>";
            }

            $rowIndex++;
        }

        fclose($handle);
        echo "<p>✅ Importazione completata.</p>";
    } else {
        echo "Errore nell'aprire il file.";
    }
} else {
    echo "Nessun file caricato o errore nell'upload.";
}

$conn->close();
?>
