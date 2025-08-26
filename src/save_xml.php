<?php
include 'header.php'; //intestazione della pagina

// Connessione al database (modifica se usi credenziali diverse)
$host = "db";        // nome servizio Docker
$dbname = "testdb";
$user = "root";
$pass = "root";


if ($_FILES['xmlfile']['error'] !== 0) {
    die("❌ Errore nel caricamento del file.");
}

$filename = $_FILES['xmlfile']['tmp_name'];
$content = file_get_contents($filename);

if (!$content) {
    die("❌ Impossibile leggere il file.");
}

// Prova a interpretare come XML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($content);

if ($xml === false) {
    die("❌ Il file non è un XML valido o ben formato.");
}

 
// 🔹 Identificativo
$identifier = (string)$xml->identifier;

// 🔹 Matrice 10x2
$matrix = [];
foreach ($xml->matrix->row as $row) {
    $col1 = (string)$row->col1;
    $col2 = (string)$row->col2;
    $matrix[] = [$col1, $col2];
}

// 🔹 Vettore di 10 elementi
$vector = [];
foreach ($xml->vector->value as $val) {
    $vector[] = (string)$val;
}

/*
// Output di debug
echo "<h2>Dati importati</h2>";
echo "<p><strong>Identificativo:</strong> $identifier</p>";

echo "<h3>Matrice (10x2)</h3><table border='1'>";
foreach ($matrix as $i => $row) {
    echo "<tr><td>Riga ".($i+1)."</td><td>{$row[0]}</td><td>{$row[1]}</td></tr>";
}
echo "</table>";

echo "<h3>Vettore (10 elementi)</h3><ul>";
foreach ($vector as $i => $val) {
    echo "<li>Elemento ".($i+1).": $val</li>";
}
echo "</ul>";

// Ora hai le 3 variabili PHP:
// $identifier → stringa
// $matrix     → array 10x2
// $vector     → array di 10 valori
*/

// Gestione database

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupera i dati dal form
    /*
    $identifier = $_POST['identifier'] ?? '';
    $matrix = $_POST['matrix'] ?? [];
    $vector = $_POST['vector'] ?? [];
*/
    if (!$identifier) {
        echo "</head>";
        echo "<body>";
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
?>
<meta http-equiv="refresh" content="2;url=list_identifiers.php">
</head>
<body>
    <?php
    echo "<p>Dati salvati con successo!</p>";
    echo '<a href="list_identifiers.php">Torna indietro</a>';
    include 'footer.php';
    
} catch (PDOException $e) {
    echo "</head>";
    echo "<body>";
    echo "Errore DB: " . $e->getMessage();
    include 'footer.php';
}
?>