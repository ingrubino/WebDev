<?php
$host = "db";
$dbname = "testdb";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT DISTINCT identifier FROM dataset ORDER BY identifier ASC");
    $identifiers = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Errore DB: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elenco Identificativi</title>
    <!-- Font Orbitron -->
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
  <!-- Collegamento al tuo foglio di stile -->
  <link rel="stylesheet" href="stile.css">
</head>
<body>
    <h1>Identificativi presenti nel database</h1>
    <ul>
        <?php foreach($identifiers as $id): ?>
            <li>
                <?php 
                    $identif=htmlspecialchars($id);
                    #echo <a href="list_identifiers2.php?identifier=htmlspecialchars($id);" > htmlspecialchars($id); </a> 
                    #echo "<a href=\"list_identifiers2.php?identifier=$identif\" > $identif </a> "
                    echo "<a href=\"storeTable2.php?identifier=$identif\" > $identif </a> "
                ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="storeTable2.php"> Aggiungi device </a>
</body>
</html>
