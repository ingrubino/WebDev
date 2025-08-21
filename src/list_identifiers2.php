<?php
$host = "db";
$dbname = "testdb";
$user = "root";
$pass = "root";

if (!isset($_GET['identifier'])) {
    die("Parametro identifier mancante.");
}

$identifier = $_GET['identifier'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prendiamo tutte le righe della matrice per l'identificativo
    $stmt = $pdo->prepare("SELECT row_index, col1, col2, vector_values FROM dataset WHERE identifier = :identifier ORDER BY row_index ASC");
    $stmt->execute(['identifier' => $identifier]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Errore DB: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dataset: <?php echo htmlspecialchars($identifier); ?></title>
</head>
<body>
    <h1>Dataset: <?php echo htmlspecialchars($identifier); ?></h1>

    <?php if (empty($rows)): ?>
        <p>Nessun dato trovato per questo identificativo.</p>
    <?php else: ?>
        <table border="1" cellpadding="5">
            <tr>
                <th>Riga</th>
                <th>Colonna 1</th>
                <th>Colonna 2</th>
            </tr>
            <?php foreach($rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['row_index']); ?></td>
                <td><?php echo htmlspecialchars($row['col1']); ?></td>
                <td><?php echo htmlspecialchars($row['col2']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <p>
<table>
<tr>
    <?php
    // Mostriamo il vettore JSON come elenco
    $vector = json_decode($row['vector_values'], true);
    echo htmlspecialchars(implode(", ", $vector));
    ?>
</tr>
</table>

    <p><a href="list_identifiers.php">← Torna agli identificativi</a></p>
</body>
</html>

