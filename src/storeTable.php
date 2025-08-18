<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Inserimento Matrice e Vettore</title>
  <style>
    table, td, th { border: 1px solid #ccc; border-collapse: collapse; padding: 6px; }
    table { margin: 10px 0; }
  </style>
</head>
<body>
  <h1>Inserisci dati</h1>

  <form method="post" action="save.php">
    <label for="identifier">Identificativo:</label>
    <input type="text" name="identifier" id="identifier" required>
    
    <h3>Matrice (10x2)</h3>
    <table>
      <tr><th>Riga</th><th>Colonna 1</th><th>Colonna 2</th></tr>
      <?php for ($i=0; $i<10; $i++): ?>
        <tr>
          <td><?php echo $i+1; ?></td>
          <td><input type="text" name="matrix[<?php echo $i; ?>][0]"></td>
          <td><input type="text" name="matrix[<?php echo $i; ?>][1]"></td>
        </tr>
      <?php endfor; ?>
    </table>

    <h3>Vettore (10 valori)</h3>
    <?php for ($j=0; $j<10; $j++): ?>
      <input type="text" name="vector[]" size="5">
    <?php endfor; ?>

    <br><br>
    <button type="submit">Salva nel database</button>
  </form>
  <p>
  <a href="list_identifiers.php"> lista tutti gli identificativi </a> <br>
    </p><p>
  <a href="list_identifiers2.php"> lista tutti gli identificativi con get</a>
    </p>
</body>
</html>

