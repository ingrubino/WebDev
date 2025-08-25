<?php
$host = "db";
$dbname = "testdb";
$user = "root";
$pass = "root";

// Recupero dei dati se viene passato un identifier
$identifier = $_GET['identifier'] ?? '';
$matrix = array_fill(0, 10, ["", ""]);
$vector = array_fill(0, 10, "");

if ($identifier) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Recuperiamo la matrice e un vettore (tutti uguali, quindi prendiamo il primo)
        $stmt = $pdo->prepare("SELECT row_index, col1, col2, vector_values 
                               FROM dataset 
                               WHERE identifier = :identifier 
                               ORDER BY row_index ASC");
        $stmt->execute(['identifier' => $identifier]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            foreach ($rows as $row) {
                $matrix[$row['row_index']] = [$row['col1'], $row['col2']];
            }
            // Vettore salvato nella prima riga
            $vector = json_decode($rows[0]['vector_values'], true);
        }
    } catch (PDOException $e) {
        die("Errore DB: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inserimento Matrice e Vettore</title>
  <?php
  #<style>
  #  table, td, th { border: 1px solid #ccc; border-collapse: collapse; padding: 6px; }
  #  table { margin: 10px 0; }
  #</style>
  ?>

 <!-- Font Orbitron -->
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
  <!-- Collegamento al tuo foglio di stile -->
  <link rel="stylesheet" href="stile.css">
</head>
<body>
  <h1>Inserisci o modifica dati</h1>
  <form action="import.php" method="post" enctype="multipart/form-data">
    <input type="file" name="csvfile" accept=".csv" required>
    <br><br>
    <button type="submit">Carica e importa</button>
  </form>
  <p>

  <form method="post" action="save.php">
    <label for="identifier">Identificativo:</label>
    <input type="text" name="identifier" id="identifier" required value="<?php echo htmlspecialchars($identifier); ?>">
    
    <h3>Matrice (10x2)</h3>
    <table>
      <tr><th>Riga</th><th>Colonna 1</th><th>Colonna 2</th></tr>
      <?php for ($i=0; $i<10; $i++): ?>
        <tr>
          <td><?php echo $i+1; ?></td>
          <td>
            <input type="number" step="any" name="matrix[<?php echo $i; ?>][0]" 
                   value="<?php echo htmlspecialchars($matrix[$i][0]); ?>">
          </td>
          <td>
            <input type="number" step="any" name="matrix[<?php echo $i; ?>][1]" 
                   value="<?php echo htmlspecialchars($matrix[$i][1]); ?>">
          </td>
        </tr>
      <?php endfor; ?>
    </table>

    <h3>Vettore (10 valori)</h3>
    <?php for ($j=0; $j<10; $j++): ?>
      <input type="text" name="vector[]" size="5" 
             value="<?php echo htmlspecialchars($vector[$j]); ?>">
    <?php endfor; ?>

    <br><br>
    <button type="submit">Salva nel database</button>
  </form>

  <p><a href="list_identifiers.php">← Torna agli identificativi</a></p>
</body>
</html>
